<?php
/**
 * 網站統計：進站人次／連續進站天數（sitevisit）與選單使用次數（menuusage）。
 * 對齊 fengbroaiappwrite 的 /api/site-visit 與 /api/menu-usage。
 */

const FENGBRO_SITE_ORIGIN_DATE = '2025-09-28';

function fengbroSiteVisitCreateSql(): string
{
    return "CREATE TABLE IF NOT EXISTS sitevisit (
            id VARCHAR(36) PRIMARY KEY,
            count INT NOT NULL DEFAULT 0,
            lastVisitAt DATETIME NULL,
            currentStreak INT NOT NULL DEFAULT 0,
            lastVisitDate VARCHAR(10) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

function fengbroMenuUsageCreateSql(): string
{
    return "CREATE TABLE IF NOT EXISTS menuusage (
            id VARCHAR(36) PRIMARY KEY,
            moduleId VARCHAR(100) NOT NULL,
            count INT NOT NULL DEFAULT 0,
            lastUsedAt DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_module_id (moduleId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

function fengbroEnsureSiteVisitTable(?PDO $pdo = null): void
{
    $pdo = $pdo ?: getConnection();
    $pdo->exec(fengbroSiteVisitCreateSql());
    fengbroEnsureTableColumns($pdo, 'sitevisit', [
        "count INT NOT NULL DEFAULT 0",
        "lastVisitAt DATETIME NULL",
        "currentStreak INT NOT NULL DEFAULT 0",
        "lastVisitDate VARCHAR(10) DEFAULT ''",
    ]);
}

function fengbroEnsureMenuUsageTable(?PDO $pdo = null): void
{
    $pdo = $pdo ?: getConnection();
    $pdo->exec(fengbroMenuUsageCreateSql());
    fengbroEnsureTableColumns($pdo, 'menuusage', [
        "moduleId VARCHAR(100) NOT NULL",
        "count INT NOT NULL DEFAULT 0",
        "lastUsedAt DATETIME NULL",
    ]);
}

/** 台北時區的 YYYY-MM-DD 日期鍵。 */
function fengbroTaipeiDateKey(?DateTimeInterface $now = null): string
{
    $tz = new DateTimeZone('Asia/Taipei');
    $dt = $now ? DateTimeImmutable::createFromInterface($now) : new DateTimeImmutable('now');
    return $dt->setTimezone($tz)->format('Y-m-d');
}

/**
 * 把 YYYY-MM-DD 或 DATETIME 正規化成台北日曆日鍵。
 */
function fengbroToVisitDateKey($value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }
    $dt = new DateTimeImmutable('@' . $ts);
    return $dt->setTimezone(new DateTimeZone('Asia/Taipei'))->format('Y-m-d');
}

/**
 * 距離 today 的日數差（last - today；昨天 = -1，今天 = 0）。
 */
function fengbroVisitDayDelta(string $lastVisitDate, string $today): int
{
    $last = new DateTimeImmutable($lastVisitDate, new DateTimeZone('Asia/Taipei'));
    $curr = new DateTimeImmutable($today, new DateTimeZone('Asia/Taipei'));
    return (int) $curr->diff($last)->format('%r%a');
}

/**
 * 新增一次到站：同一台北日曆日保持 streak，昨天進站 +1，斷一天重算為 1。
 * 回傳下次要寫入的欄位。
 */
function fengbroNextSiteVisitStreak(array $row, ?DateTimeInterface $now = null): array
{
    $now = $now ?: new DateTimeImmutable('now');
    $today = fengbroTaipeiDateKey($now);
    $lastVisitDate = fengbroToVisitDateKey($row['lastVisitDate'] ?? '')
        ?: fengbroToVisitDateKey($row['lastVisitAt'] ?? '');
    $stored = max(0, (int) ($row['currentStreak'] ?? 0));

    if (!$lastVisitDate) {
        return ['today' => $today, 'lastVisitDate' => null, 'currentStreak' => 1];
    }
    $delta = fengbroVisitDayDelta($lastVisitDate, $today);
    if ($delta === 0) {
        return ['today' => $today, 'lastVisitDate' => $lastVisitDate, 'currentStreak' => max($stored, 1)];
    }
    if ($delta === -1) {
        return ['today' => $today, 'lastVisitDate' => $lastVisitDate, 'currentStreak' => max($stored, 1) + 1];
    }
    return ['today' => $today, 'lastVisitDate' => $lastVisitDate, 'currentStreak' => 1];
}

/**
 * 關於頁顯示用：最後進站是今天或昨天才顯示 streak，否則視為中斷顯示 0。
 */
function fengbroDisplaySiteVisitStreak(array $row, ?DateTimeInterface $now = null): int
{
    $now = $now ?: new DateTimeImmutable('now');
    $today = fengbroTaipeiDateKey($now);
    $lastVisitDate = fengbroToVisitDateKey($row['lastVisitDate'] ?? '')
        ?: fengbroToVisitDateKey($row['lastVisitAt'] ?? '');
    if (!$lastVisitDate) {
        return 0;
    }
    $delta = fengbroVisitDayDelta($lastVisitDate, $today);
    if ($delta === 0 || $delta === -1) {
        return max((int) ($row['currentStreak'] ?? 0), 1);
    }
    return 0;
}

/** 讀取單一 sitevisit 計數列（沒有就回 null）。 */
function fengbroGetSiteVisitRow(PDO $pdo): ?array
{
    try {
        fengbroEnsureSiteVisitTable($pdo);
    } catch (Throwable $e) {
        return null;
    }
    $row = $pdo->query("SELECT * FROM sitevisit ORDER BY created_at ASC LIMIT 1")->fetch();
    return $row ?: null;
}

/** 讀取 menuusage Top N。 */
function fengbroGetMenuUsageItems(PDO $pdo, int $limit = 100): array
{
    try {
        fengbroEnsureMenuUsageTable($pdo);
    } catch (Throwable $e) {
        return [];
    }
    $stmt = $pdo->query("SELECT moduleId, count, lastUsedAt FROM menuusage ORDER BY count DESC, moduleId ASC LIMIT " . max(1, (int) $limit));
    return $stmt->fetchAll();
}

/** 記錄一次選單點擊：有該 moduleId 就累加，否則新增。 */
function fengbroRecordMenuUsage(PDO $pdo, string $moduleId): void
{
    $moduleId = trim($moduleId);
    if ($moduleId === '') {
        return;
    }
    fengbroEnsureMenuUsageTable($pdo);
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE menuusage SET count = count + 1, lastUsedAt = ? WHERE moduleId = ?");
    $stmt->execute([$now, $moduleId]);
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare("INSERT INTO menuusage (id, moduleId, count, lastUsedAt) VALUES (?, ?, 1, ?)");
        $stmt->execute([generateUUID(), $moduleId, $now]);
    }
}

/** 記錄一次進站（每個瀏覽器 session 一次）。 */
function fengbroRecordSiteVisit(PDO $pdo): array
{
    fengbroEnsureSiteVisitTable($pdo);
    $row = fengbroGetSiteVisitRow($pdo) ?: [];
    $next = fengbroNextSiteVisitStreak($row);
    $now = date('Y-m-d H:i:s');
    $count = ((int) ($row['count'] ?? 0)) + 1;

    $payload = [
        'count' => $count,
        'lastVisitAt' => $now,
        'currentStreak' => $next['currentStreak'],
        'lastVisitDate' => $next['today'],
    ];

    if (!empty($row['id'])) {
        $sets = [];
        $values = [];
        foreach ($payload as $col => $value) {
            $sets[] = "`{$col}` = ?";
            $values[] = $value;
        }
        $values[] = $row['id'];
        $stmt = $pdo->prepare("UPDATE sitevisit SET " . implode(',', $sets) . " WHERE id = ?");
        $stmt->execute($values);
    } else {
        $payload['id'] = generateUUID();
        $columns = array_map(fn($col) => "`{$col}`", array_keys($payload));
        $placeholders = array_fill(0, count($payload), '?');
        $stmt = $pdo->prepare("INSERT INTO sitevisit (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")");
        $stmt->execute(array_values($payload));
    }

    return [
        'success' => true,
        'count' => $count,
        'currentStreak' => $next['currentStreak'],
        'lastVisitAt' => $now,
        'lastVisitDate' => $next['today'],
    ];
}

/** 網站營運天數（自起源日 2025-09-28 起算）。 */
function fengbroSiteDaysSinceOrigin(?DateTimeInterface $now = null): int
{
    $now = $now ?: new DateTimeImmutable('now');
    $origin = new DateTimeImmutable(FENGBRO_SITE_ORIGIN_DATE . ' 00:00:00', new DateTimeZone('Asia/Taipei'));
    $current = $now->setTimezone(new DateTimeZone('Asia/Taipei'));
    return max(0, (int) $current->diff($origin)->format('%a'));
}

/** 選單使用 moduleId → 中文標籤（與 sidebar.php 的 page 鍵對應）。 */
function fengbroMenuModuleLabel(string $moduleId): string
{
    $labels = [
        'home' => '首頁',
        'dashboard' => '儀表',
        'subscription' => '訂閱',
        'trialpurchase' => '試用/首購',
        'reinstall' => '重灌',
        'quota' => '額度',
        'shoppinglist' => '購物清單',
        'food' => '食品',
        'notes' => '筆記',
        'favorites' => '常用',
        'images' => '圖片',
        'videos' => '影片',
        'music' => '音樂',
        'documents' => '文件',
        'podcast' => '播客',
        'bank' => '銀行',
        'routine' => '例行',
        'tools' => '工具',
        'settings' => '鋒兄設定',
        'about' => '鋒兄關於',
    ];
    if (str_starts_with($moduleId, 'tool:')) {
        $toolLabels = [
            'price' => '比價',
            'manual' => '手動價格',
            'phone' => '手機比價',
            'tube' => 'Tube',
            'finance' => '金融',
            'news' => '新聞',
            'image-convert' => 'PNG/JPEG',
            'image-voice' => '圖片+語音=影片',
            'video-merge' => '影片合併',
            'yt-bili' => 'YouTube/Bilibili',
        ];
        $tool = substr($moduleId, 5);
        return $toolLabels[$tool] ?? $tool;
    }
    return $labels[$moduleId] ?? $moduleId;
}
