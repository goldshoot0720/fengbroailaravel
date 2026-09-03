<?php
/**
 * CSV 分批寫入 API：前端解析後逐批送 rows，降低超大檔一次匯入逾時風險。
 * POST JSON: { "table": "food", "rows": [ { "name": "...", ... }, ... ] }
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => "Error: $errstr"], JSON_UNESCAPED_UNICODE);
    exit;
});
set_exception_handler(function ($e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});

require_once 'includes/functions.php';
@set_time_limit(120);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => '請使用 POST'], 400);
}

$raw = file_get_contents('php://input');
$payload = $raw ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    jsonResponse(['success' => false, 'error' => 'JSON 格式錯誤'], 400);
}

$table = (string) ($payload['table'] ?? '');
$allowedTables = ['subscription', 'food', 'article', 'commonaccount', 'image', 'music', 'podcast', 'video', 'commondocument', 'bank', 'routine', 'trialpurchase', 'reinstall'];
if (!in_array($table, $allowedTables, true)) {
    jsonResponse(['success' => false, 'error' => '無效的資料表'], 400);
}

$rows = $payload['rows'] ?? null;
if (!is_array($rows) || !$rows) {
    jsonResponse(['success' => false, 'error' => 'rows 不可為空'], 400);
}
if (count($rows) > 80) {
    jsonResponse(['success' => false, 'error' => '每批最多 80 筆'], 400);
}

$fieldMapping = [
    '$id' => 'id',
    '$createdAt' => 'created_at',
    '$updatedAt' => 'updated_at',
    '名稱' => 'name',
    '食物名稱' => 'name',
    '食品名稱' => 'name',
    '數量' => 'amount',
    '價格' => 'price',
    '商店' => 'shop',
    '到期日' => 'todate',
    '有效期限' => 'todate',
    '照片' => 'photo',
    '圖片' => 'photo',
    '服務' => 'name',
    '服務名稱' => 'name',
    'event_date' => 'eventDate',
    '日期' => 'eventDate',
    '試用日' => 'eventDate',
    '首購日' => 'eventDate',
    '到期日' => 'eventDate',
    '扣款日' => 'eventDate',
    '試用／首購／到期日' => 'eventDate',
    '試用/首購/到期日' => 'eventDate',
    '試用／首購／到期日（扣款日）' => 'eventDate',
    'first_purchase_price' => 'firstPurchasePrice',
    '首購價格' => 'firstPurchasePrice',
    'regular_price' => 'regularPrice',
    '非首購價格' => 'regularPrice',
    '一般價格' => 'regularPrice',
    '帳號' => 'account',
    '備註' => 'note',
    'trial_status' => 'trialStatus',
    '試用狀態' => 'trialStatus',
    'purchase_status' => 'purchaseStatus',
    '首購狀態' => 'purchaseStatus',
    '使用系統' => 'system',
    '系統' => 'system',
    'software_type' => 'softwareType',
    '軟體類型' => 'softwareType',
    'license_type' => 'licenseType',
    '授權方式' => 'licenseType',
    '付費序號' => 'serial',
    '序號' => 'serial',
    'view_password' => 'viewPassword',
    '查看密碼' => 'viewPassword',
    'subscription_software' => 'subscriptionSoftware',
    '訂閱制軟體' => 'subscriptionSoftware',
    '訂閱制' => 'subscriptionSoftware',
    'subscription_period' => 'subscriptionPeriod',
    '訂閱週期' => 'subscriptionPeriod',
    '週期' => 'subscriptionPeriod',
    'subscription_price' => 'subscriptionPrice',
    '訂閱費用' => 'subscriptionPrice',
    '費用' => 'subscriptionPrice',
    'subscription_currency' => 'subscriptionCurrency',
    '訂閱費用幣別' => 'subscriptionCurrency',
    '幣別' => 'subscriptionCurrency',
    '軟體網站' => 'site',
];

function normalizeImportMoneyChunk($value)
{
    if ($value === null || $value === '') {
        return 0;
    }
    $clean = preg_replace('/[^\d\-.]/u', '', (string) $value);
    if ($clean === '' || $clean === '-' || $clean === '.') {
        return 0;
    }
    return (int) round((float) $clean);
}

function mapImportRowKeys(array $row, array $fieldMapping): array
{
    $out = [];
    foreach ($row as $key => $value) {
        $key = trim((string) $key);
        if ($key === '') {
            continue;
        }
        if (isset($fieldMapping[$key])) {
            $key = $fieldMapping[$key];
        } elseif (str_starts_with($key, '#')) {
            $key = substr($key, 1);
        } elseif (str_starts_with($key, '$') && $key !== '$id') {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

$pdo = getConnection();
if ($table === 'trialpurchase') {
    fengbroEnsureTrialPurchaseTable($pdo);
}
if ($table === 'reinstall') {
    fengbroEnsureReinstallTable($pdo);
}
$dbColumns = [];
try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $dbColumns[] = $col['Field'];
    }
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'error' => 'DB 錯誤: ' . $e->getMessage()], 500);
}

$imported = 0;
$skipped = 0;
$errors = [];

foreach ($rows as $index => $rawRow) {
    if (!is_array($rawRow)) {
        $skipped++;
        $errors[] = '第 ' . ($index + 1) . ' 筆: 不是物件';
        continue;
    }

    $data = mapImportRowKeys($rawRow, $fieldMapping);
    // 只保留 DB 欄位
    $data = array_intersect_key($data, array_flip($dbColumns));
    if (!$data) {
        $skipped++;
        $errors[] = '第 ' . ($index + 1) . ' 筆: 沒有可寫入欄位';
        continue;
    }

    foreach ($data as $key => $value) {
        if ($value === '' || $value === 'null') {
            $data[$key] = null;
        }
    }

    if ($table === 'bank') {
        foreach (['deposit', 'withdrawals', 'transfer'] as $moneyColumn) {
            if (array_key_exists($moneyColumn, $data)) {
                $data[$moneyColumn] = normalizeImportMoneyChunk($data[$moneyColumn]);
            }
        }
    }
    if ($table === 'subscription' && array_key_exists('note', $data) && $data['note'] !== null) {
        $data['note'] = mb_substr((string) $data['note'], 0, 100);
    }
    if ($table === 'trialpurchase') {
        try {
            $data = array_merge($data, fengbroSanitizeTrialPurchaseRow($data));
        } catch (InvalidArgumentException $e) {
            $skipped++;
            $errors[] = '第 ' . ($index + 1) . ' 筆: ' . $e->getMessage();
            continue;
        }
    }
    if ($table === 'reinstall') {
        try {
            $data = array_merge($data, fengbroSanitizeReinstallRow($data));
        } catch (InvalidArgumentException $e) {
            $skipped++;
            $errors[] = '第 ' . ($index + 1) . ' 筆: ' . $e->getMessage();
            continue;
        }
    }
    foreach ($data as $key => $value) {
        if ($value !== null && is_string($value) && preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/', $value, $m)) {
            $data[$key] = $m[1] . ' ' . $m[2];
        }
    }
    if (array_key_exists('continue', $data) && $data['continue'] !== null) {
        $data['continue'] = filter_var($data['continue'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    } elseif (array_key_exists('continue', $data)) {
        $data['continue'] = 1;
    }

    $recordName = $data['name'] ?? ('#' . ($index + 1));
    $hasSourceId = !empty($data['id']);
    if (!$hasSourceId) {
        $duplicateId = null;
        if ($table === 'trialpurchase') {
            $duplicateId = fengbroFindTrialPurchaseImportId($pdo, $data);
        } elseif ($table === 'reinstall') {
            $duplicateId = fengbroFindReinstallImportId($pdo, $data);
        } else {
            $duplicateId = findExistingImportRecordId($pdo, $table, $data);
        }
        if ($duplicateId) {
            $data['id'] = $duplicateId;
        } else {
            $data['id'] = generateUUID();
        }
    }
    $currentId = $data['id'];

    $stmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE id = ?");
    $stmt->execute([$currentId]);
    $exists = $stmt->fetch();

    try {
        if ($exists) {
            $update = $data;
            unset($update['id'], $update['created_at']);
            if (!$update) {
                $skipped++;
                continue;
            }
            $sets = [];
            foreach (array_keys($update) as $col) {
                $sets[] = "`{$col}` = ?";
            }
            $sql = "UPDATE `{$table}` SET " . implode(',', $sets) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $values = array_values($update);
            $values[] = $currentId;
            $stmt->execute($values);
        } else {
            $columns = array_map(static fn($c) => "`{$c}`", array_keys($data));
            $placeholders = array_fill(0, count($data), '?');
            $sql = "INSERT INTO `{$table}` (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
        }
        $imported++;
    } catch (PDOException $e) {
        $errors[] = "{$recordName}: " . $e->getMessage();
    }
}

jsonResponse([
    'success' => true,
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => $errors,
]);
