<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/management_tables.php';
require_once __DIR__ . '/site_stats.php';

/**
 * 執行環境判斷：本機為 local，其餘（線上主機）為 remote。
 * 以 $GLOBALS['ENV'] 提供給 about / settings 等頁面使用。
 */
function fengbroEnvironment(): string
{
    static $env = null;
    if ($env !== null) return $env;

    $override = getenv('APP_ENV');
    if (is_string($override) && $override !== '') {
        return $env = strtolower($override);
    }

    if (PHP_SAPI === 'cli') return $env = 'local';

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $host = (string) preg_replace('/:\d+$/', '', $host);
    $localHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
    $isLocal = $host === ''
        || in_array($host, $localHosts, true)
        || str_ends_with($host, '.local')
        || str_ends_with($host, '.test')
        || str_starts_with($host, '192.168.')
        || str_starts_with($host, '10.');

    return $env = $isLocal ? 'local' : 'remote';
}

$GLOBALS['ENV'] = fengbroEnvironment();

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getAll($table, $orderBy = 'created_at DESC') {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY {$orderBy}");
    return $stmt->fetchAll();
}

function getById($table, $id) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function deleteById($table, $id) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
    return $stmt->execute([$id]);
}

function formatDate($date) {
    if (empty($date)) return '-';
    return date('Y-m-d', strtotime($date));
}

function formatDateTime($date) {
    if (empty($date)) return '-';
    return date('Y-m-d H:i', strtotime($date));
}

function formatMoney($amount) {
    if (empty($amount)) return '$0';
    return '$' . number_format($amount);
}

function findExistingImportRecordId(PDO $pdo, string $table, array $data, array $identityColumns = []): ?string {
    $ignored = ['id', 'created_at', 'updated_at'];
    if (!$identityColumns && !empty($data['hash'])) {
        $identityColumns = ['hash'];
    }
    $columns = $identityColumns ?: array_values(array_diff(array_keys($data), $ignored));
    $where = [];
    $values = [];

    foreach ($columns as $column) {
        if (in_array($column, $ignored, true) || !array_key_exists($column, $data)) {
            continue;
        }
        $value = $data[$column];
        if ($value === '') {
            $value = null;
        }
        if ($value === null) {
            $where[] = "`{$column}` IS NULL";
        } else {
            $where[] = "`{$column}` = ?";
            $values[] = $value;
        }
    }

    if (!$where) {
        return null;
    }

    $sql = "SELECT id FROM `{$table}` WHERE " . implode(' AND ', $where) . " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    $id = $stmt->fetchColumn();
    return $id ? (string)$id : null;
}

function importRecordExists(PDO $pdo, string $table, array $identity): bool {
    return findExistingImportRecordId($pdo, $table, $identity, array_keys($identity)) !== null;
}

/* ===========================================================
 * CSV 匯入去重：同一筆資料在檔案中重複出現時，只保留最新版本
 * =========================================================== */

/**
 * 各資料表的「自然識別欄位」：沒有 id / hash 時，用這些欄位判斷是否為同一筆。
 * 與 fengbroFind*ImportId() / findExistingImportRecordId() 的判斷邏輯一致。
 */
function fengbroImportIdentityColumns(string $table): array
{
    switch ($table) {
        case 'subscription':
        case 'bank':
        case 'trialpurchase':
        case 'quota':
            return ['name', 'account'];
        case 'food':
            return ['name', 'shop'];
        case 'article':
            return ['title'];
        case 'reinstall':
            return ['name', 'system'];
        case 'image':
        case 'music':
        case 'podcast':
        case 'video':
        case 'commondocument':
            return ['name', 'file'];
        case 'commonaccount':
        case 'routine':
        case 'shoppinglist':
        default:
            return ['name'];
    }
}

/**
 * 一般 CSV 匯入應將支援垃圾桶的資料視為有效資料。
 *
 * 匯入若命中垃圾桶中的既有紀錄，後續寫入會清除 deleted_at 以復原它；
 * 同時略過 CSV 內的 deleted_at，避免一般匯入意外帶入已刪除狀態。
 */
function fengbroImportRestoresSoftDeletedRows(string $table, array $dbColumns): bool
{
    return in_array($table, ['article', 'subscription'], true)
        && in_array('deleted_at', $dbColumns, true);
}

/**
 * 把各種時間格式（Appwrite ISO 8601、純日期、MySQL DATETIME）正規化成可比較的字串。
 */
function fengbroNormalizeImportTimestamp($value): string
{
    if ($value === null) {
        return '';
    }
    $value = trim((string) $value);
    if ($value === '' || strtolower($value) === 'null') {
        return '';
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2}:\d{2})/', $value, $m)) {
        return $m[1] . ' ' . $m[2];
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value . ' 00:00:00';
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : '';
}

/**
 * 取得一列資料的「版本時間」：updated_at 優先，其次 created_at。
 * 都沒有時回傳空字串，代表無法判斷版本（由檔案順序決定，後面的較新）。
 */
function fengbroImportRowVersion(array $row): string
{
    foreach (['updated_at', '$updatedAt', 'updatedAt', 'created_at', '$createdAt', 'createdAt'] as $key) {
        if (!array_key_exists($key, $row)) {
            continue;
        }
        $stamp = fengbroNormalizeImportTimestamp($row[$key]);
        if ($stamp !== '') {
            return $stamp;
        }
    }
    return '';
}

/**
 * 計算一列資料的去重鍵。回傳 null 代表無法判斷身分，該列一律保留。
 */
function fengbroImportDedupeKey(string $table, array $row): ?string
{
    foreach (['id', 'hash'] as $unique) {
        $value = isset($row[$unique]) ? trim((string) $row[$unique]) : '';
        if ($value !== '' && strtolower($value) !== 'null') {
            return $unique . ':' . mb_strtolower($value);
        }
    }

    $parts = [];
    $hasValue = false;
    foreach (fengbroImportIdentityColumns($table) as $column) {
        $value = isset($row[$column]) ? trim((string) $row[$column]) : '';
        if (strtolower($value) === 'null') {
            $value = '';
        }
        if ($value !== '') {
            $hasValue = true;
        }
        $parts[] = mb_strtolower($value);
    }

    return $hasValue ? $table . ':' . implode("\x1f", $parts) : null;
}

/**
 * 去除同一批匯入資料中的重複列，同一筆只保留最新版本。
 *
 * 版本判斷：updated_at / created_at 較新者勝；時間相同或都沒有時，以檔案中較後面者為準
 * （沿用原本「後寫入覆蓋先寫入」的行為，只是少跑一次 DB 寫入）。
 * 保留的列會停在該筆第一次出現的位置，維持原本的匯入順序。
 *
 * $reader 可讓呼叫端傳入包了額外資訊的元素（例如帶行號的 ['index' => n, 'data' => [...]]），
 * 回傳的 rows 會是原本的元素型別。
 *
 * @return array{rows: array, removed: int}
 */
function fengbroDedupeImportRows(string $table, array $rows, ?callable $reader = null): array
{
    $slots = [];
    $index = [];
    $removed = 0;

    foreach ($rows as $item) {
        $row = $reader ? $reader($item) : $item;
        if (!is_array($row)) {
            $slots[] = $item;
            continue;
        }

        $key = fengbroImportDedupeKey($table, $row);
        if ($key === null) {
            $slots[] = $item;
            continue;
        }

        $version = fengbroImportRowVersion($row);
        if (!isset($index[$key])) {
            $slots[] = $item;
            $index[$key] = ['slot' => array_key_last($slots), 'version' => $version];
            continue;
        }

        $removed++;
        if (strcmp($version, $index[$key]['version']) >= 0) {
            $slots[$index[$key]['slot']] = $item;
            $index[$key]['version'] = $version;
        }
    }

    return ['rows' => array_values($slots), 'removed' => $removed];
}
