<?php
/**
 * Schema 快取：讓「確保資料表／欄位存在」只在真的需要時才碰資料庫。
 *
 * 舊做法每個請求都會跑 CREATE TABLE IF NOT EXISTS + 十幾個必定失敗的 ALTER TABLE ADD COLUMN，
 * 在遠端 MySQL 上每一句都是一次往返加 metadata lock，列表／新增／編輯都被拖慢。
 *
 * 新做法：
 *   1. 同一請求內用 static 記憶，同一張表只檢查一次。
 *   2. 跨請求用暫存目錄的標記檔（預設 6 小時有效），標記存在就完全不查資料庫。
 *   3. 標記鍵包含資料庫名稱與「定義雜湊」，改了 CREATE / 欄位定義會自動重新檢查。
 *   4. 真的要檢查欄位時，只跑一次 SHOW COLUMNS，缺的欄位才 ALTER（能合併就合併成一句）。
 *   5. 查詢遇到「資料表／欄位不存在」時，呼叫端可 fengbroSchemaForget() 後重試。
 */

if (!defined('FENGBRO_SCHEMA_CACHE_TTL')) {
    define('FENGBRO_SCHEMA_CACHE_TTL', 6 * 3600);
}

function fengbroSchemaCacheDir(): ?string
{
    static $dir = false;
    if ($dir !== false) {
        return $dir;
    }

    $dbName = defined('DB_NAME') ? (string) DB_NAME : '';
    $suffix = 'fengbro_schema_' . substr(md5(__DIR__ . '|' . $dbName), 0, 12);
    $candidates = [
        __DIR__ . '/../uploads/temp/.schema',
        rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $suffix,
    ];

    foreach ($candidates as $candidate) {
        if (is_dir($candidate) || @mkdir($candidate, 0775, true)) {
            if (is_writable($candidate)) {
                return $dir = $candidate;
            }
        }
    }

    return $dir = null;
}

/** 請求內的記憶表（key => true）。 */
function &fengbroSchemaMemo(): array
{
    static $memo = [];
    return $memo;
}

function fengbroSchemaMarkerPath(string $key): ?string
{
    $dir = fengbroSchemaCacheDir();
    if ($dir === null) {
        return null;
    }
    $dbName = defined('DB_NAME') ? (string) DB_NAME : '';
    return $dir . DIRECTORY_SEPARATOR . md5($dbName . '|' . $key) . '.ok';
}

function fengbroSchemaIsReady(string $key): bool
{
    $memo = &fengbroSchemaMemo();
    if (isset($memo[$key])) {
        return true;
    }

    $path = fengbroSchemaMarkerPath($key);
    if ($path !== null) {
        $mtime = @filemtime($path);
        if ($mtime !== false && (time() - $mtime) < FENGBRO_SCHEMA_CACHE_TTL) {
            $memo[$key] = true;
            return true;
        }
    }

    return false;
}

function fengbroSchemaMarkReady(string $key): void
{
    $memo = &fengbroSchemaMemo();
    $memo[$key] = true;

    $path = fengbroSchemaMarkerPath($key);
    if ($path !== null) {
        @file_put_contents($path, (string) time(), LOCK_EX);
    }
}

/**
 * 清除 schema 快取。傳 null 清全部（例如設定頁初始化、重建資料表後）。
 */
function fengbroSchemaForget(?string $key = null): void
{
    $memo = &fengbroSchemaMemo();
    fengbroTableColumnsForget();

    if ($key !== null) {
        unset($memo[$key]);
        $path = fengbroSchemaMarkerPath($key);
        if ($path !== null) {
            @unlink($path);
        }
        return;
    }

    $memo = [];
    $dir = fengbroSchemaCacheDir();
    if ($dir !== null) {
        foreach ((array) glob($dir . DIRECTORY_SEPARATOR . '*.ok') as $file) {
            @unlink($file);
        }
    }
}

/**
 * 只執行一次 $ensure（同請求 + 跨請求 TTL）。$fingerprint 應該是定義內容，改定義會自動重跑。
 * $ensure 丟例外時不會寫入標記，下次請求會再試。
 */
function fengbroSchemaEnsureOnce(string $name, string $fingerprint, callable $ensure): void
{
    $key = $name . ':' . substr(md5($fingerprint), 0, 12);
    if (fengbroSchemaIsReady($key)) {
        return;
    }
    $ensure();
    fengbroSchemaMarkReady($key);
}

/* -----------------------------------------------------------
 * 欄位清單（請求內快取）
 * ----------------------------------------------------------- */

function &fengbroTableColumnsMemo(): array
{
    static $memo = [];
    return $memo;
}

function fengbroTableColumnsForget(?string $table = null): void
{
    $memo = &fengbroTableColumnsMemo();
    if ($table === null) {
        $memo = [];
    } else {
        unset($memo[$table]);
    }
}

/**
 * 取得資料表欄位名稱（同請求只查一次 SHOW COLUMNS）。
 *
 * @return string[]
 */
function fengbroTableColumnNames(PDO $pdo, string $table, bool $fresh = false): array
{
    $memo = &fengbroTableColumnsMemo();
    if (!$fresh && isset($memo[$table])) {
        return $memo[$table];
    }
    $safe = str_replace('`', '', $table);
    $columns = [];
    foreach ($pdo->query("SHOW COLUMNS FROM `{$safe}`")->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $columns[] = (string) $col['Field'];
    }
    return $memo[$table] = $columns;
}

/** 從「欄位定義 SQL」取出欄位名，例如 "`system` VARCHAR(10)" → system。 */
function fengbroColumnNameFromDefinition(string $definition): string
{
    $definition = ltrim($definition);
    if ($definition !== '' && $definition[0] === '`') {
        $end = strpos($definition, '`', 1);
        return $end === false ? trim($definition, '` ') : substr($definition, 1, $end - 1);
    }
    return (string) strtok($definition, " \t\r\n");
}

/**
 * 只補上缺少的欄位：一次 SHOW COLUMNS，缺欄位時合併成一句 ALTER；合併失敗才逐欄補。
 */
function fengbroAddMissingColumns(PDO $pdo, string $table, array $definitions): void
{
    if (!$definitions) {
        return;
    }

    try {
        $existing = array_map('strtolower', fengbroTableColumnNames($pdo, $table, true));
    } catch (Throwable $e) {
        $existing = null;
    }

    $missing = [];
    foreach ($definitions as $definition) {
        $name = strtolower(fengbroColumnNameFromDefinition($definition));
        if ($existing === null || !in_array($name, $existing, true)) {
            $missing[] = $definition;
        }
    }
    if (!$missing) {
        return;
    }

    $safe = str_replace('`', '', $table);
    if ($existing !== null && count($missing) > 1) {
        try {
            $pdo->exec("ALTER TABLE `{$safe}` " . implode(', ', array_map(static function ($d) {
                return 'ADD COLUMN ' . $d;
            }, $missing)));
            fengbroTableColumnsForget($table);
            return;
        } catch (Throwable $e) {
            // 有欄位衝突或舊版 MySQL 不吃合併語法，改逐欄補。
        }
    }

    foreach ($missing as $definition) {
        try {
            $pdo->exec("ALTER TABLE `{$safe}` ADD COLUMN {$definition}");
        } catch (Throwable $e) {
            // 欄位已存在則略過
        }
    }
    fengbroTableColumnsForget($table);
}

/**
 * CREATE TABLE IF NOT EXISTS + 補欄位，整組快取。
 */
function fengbroEnsureTableSchema(PDO $pdo, string $table, string $createSql, array $columns = []): void
{
    fengbroSchemaEnsureOnce('table:' . $table, $createSql . '|' . implode('|', $columns), static function () use ($pdo, $table, $createSql, $columns) {
        $pdo->exec($createSql);
        fengbroAddMissingColumns($pdo, $table, $columns);
    });
}

/** 垃圾桶欄位（article / subscription）。 */
function fengbroEnsureSoftDeleteColumn(PDO $pdo, string $table): void
{
    fengbroSchemaEnsureOnce('softdelete:' . $table, 'deleted_at DATETIME NULL', static function () use ($pdo, $table) {
        fengbroAddMissingColumns($pdo, $table, ['deleted_at DATETIME NULL']);
    });
}

/** 判斷例外是否為「資料表或欄位不存在」，可用來清快取重試。 */
function fengbroIsMissingSchemaError(Throwable $e): bool
{
    $code = (string) $e->getCode();
    if (in_array($code, ['42S02', '42S22'], true)) {
        return true;
    }
    $msg = $e->getMessage();
    return stripos($msg, "doesn't exist") !== false || stripos($msg, 'Unknown column') !== false;
}

/* -----------------------------------------------------------
 * 效能索引：舊部署的資料表可能是在 database.sql 加索引之前建立的，
 * 列表（ORDER BY created_at）、垃圾桶（deleted_at）、匯入去重（name/hash）會全表掃描。
 * 這裡每個 TTL 週期最多檢查一次 SHOW INDEX，缺的才補，補不了（欄位不存在等）就略過。
 * ----------------------------------------------------------- */

function fengbroPerformanceIndexes(): array
{
    return [
        'subscription' => [
            'idx_subscription_deleted_next' => '`deleted_at`, `nextdate`',
            'idx_subscription_created' => '`created_at`',
            'idx_subscription_name' => '`name`',
        ],
        'food' => [
            'idx_food_todate' => '`todate`, `created_at`',
            'idx_food_created' => '`created_at`',
            'idx_food_name_shop' => '`name`, `shop`',
        ],
        'article' => [
            'idx_article_deleted_created' => '`deleted_at`, `created_at`',
            'idx_article_title' => '`title`',
        ],
        'image' => ['idx_image_created' => '`created_at`', 'idx_image_hash' => '`hash`(191)', 'idx_image_name' => '`name`'],
        'music' => ['idx_music_created' => '`created_at`', 'idx_music_hash' => '`hash`(191)', 'idx_music_name' => '`name`'],
        'podcast' => ['idx_podcast_created' => '`created_at`', 'idx_podcast_hash' => '`hash`(191)', 'idx_podcast_name' => '`name`'],
        'video' => ['idx_video_created' => '`created_at`', 'idx_video_hash' => '`hash`(191)', 'idx_video_name' => '`name`'],
        'commondocument' => [
            'idx_commondocument_created' => '`created_at`',
            'idx_commondocument_hash' => '`hash`(191)',
            'idx_commondocument_name' => '`name`',
        ],
        'bank' => ['idx_bank_created' => '`created_at`', 'idx_bank_name' => '`name`'],
        'routine' => ['idx_routine_created' => '`created_at`', 'idx_routine_name' => '`name`'],
        'commonaccount' => ['idx_commonaccount_created' => '`created_at`', 'idx_commonaccount_name' => '`name`'],
        'trialpurchase' => ['idx_trialpurchase_created' => '`created_at`'],
        'reinstall' => ['idx_reinstall_created' => '`created_at`'],
        'quota' => ['idx_quota_created' => '`created_at`'],
        'shoppinglist' => ['idx_shoppinglist_created' => '`created_at`', 'idx_shoppinglist_planned' => '`plannedDate`'],
    ];
}

function fengbroEnsureTableIndexes(PDO $pdo, string $table, array $indexes): void
{
    $safe = str_replace('`', '', $table);
    try {
        $rows = $pdo->query("SHOW INDEX FROM `{$safe}`")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return; // 資料表不存在
    }
    $existing = [];
    $firstColumns = [];
    foreach ($rows as $row) {
        $existing[strtolower((string) $row['Key_name'])] = true;
        if ((int) $row['Seq_in_index'] === 1) {
            $firstColumns[strtolower((string) $row['Column_name'])] = true;
        }
    }
    foreach ($indexes as $name => $columns) {
        if (isset($existing[strtolower($name)])) {
            continue;
        }
        // 單欄索引若已有同欄位開頭的其他索引，就不重複建立。
        if (strpos($columns, ',') === false) {
            $col = strtolower(trim(preg_replace('/\(\d+\)/', '', $columns), '` '));
            if (isset($firstColumns[$col])) {
                continue;
            }
        }
        try {
            $pdo->exec("ALTER TABLE `{$safe}` ADD INDEX `{$name}` ({$columns})");
        } catch (Throwable $e) {
            // 欄位不存在或其他原因：略過，不影響功能
        }
    }
}

function fengbroEnsurePerformanceIndexes(?PDO $pdo = null, ?array $tables = null): void
{
    $all = fengbroPerformanceIndexes();
    $targets = $tables === null ? array_keys($all) : array_values(array_intersect($tables, array_keys($all)));
    foreach ($targets as $table) {
        $indexes = $all[$table];
        fengbroSchemaEnsureOnce('indexes:' . $table, json_encode($indexes), static function () use (&$pdo, $table, $indexes) {
            $pdo = $pdo ?: getConnection();
            fengbroEnsureTableIndexes($pdo, $table, $indexes);
        });
    }
}
