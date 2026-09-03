<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Catch all errors and return as JSON
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode(['error' => "Error: $errstr in $errfile on line $errline"]);
    exit;
});

set_exception_handler(function ($e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
});

require_once 'includes/functions.php';

@set_time_limit(300);
@ini_set('memory_limit', '256M');

function detectCsvDelimiter(string $line): string {
    $delimiters = [',', "\t", ';'];
    $bestDelimiter = ',';
    $bestCount = 0;
    foreach ($delimiters as $delimiter) {
        $count = count(str_getcsv($line, $delimiter, '"', ''));
        if ($count > $bestCount) {
            $bestCount = $count;
            $bestDelimiter = $delimiter;
        }
    }
    return $bestDelimiter;
}

function normalizeImportMoney($value) {
    if ($value === null || $value === '') {
        return 0;
    }
    $clean = preg_replace('/[^\d\-.]/u', '', (string) $value);
    if ($clean === '' || $clean === '-' || $clean === '.') {
        return 0;
    }
    return (int) round((float) $clean);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => '請使用 POST 方法'], 400);
}

$table = $_POST['table'] ?? '';
$allowedTables = ['subscription', 'food', 'article', 'commonaccount', 'image', 'music', 'podcast', 'video', 'commondocument', 'bank', 'routine', 'trialpurchase', 'reinstall', 'quota', 'shoppinglist'];

if (!in_array($table, $allowedTables)) {
    jsonResponse(['error' => '無效的資料表'], 400);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => '請上傳檔案'], 400);
}

$file = $_FILES['file']['tmp_name'];

// Appwrite 欄位名稱對應
$fieldMapping = [
    '$id' => 'id',
    '$createdAt' => 'created_at',
    '$updatedAt' => 'updated_at',
    '名稱' => 'name',
    '銀行' => 'name',
    '銀行名稱' => 'name',
    '電子票證' => 'name',
    '存款' => 'deposit',
    '餘額' => 'deposit',
    '金額' => 'deposit',
    '提款' => 'withdrawals',
    '支出' => 'withdrawals',
    '轉帳' => 'transfer',
    '帳號' => 'account',
    '卡號' => 'card',
    '地址' => 'address',
    '網站' => 'site',
    '活動網址' => 'activity',
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
    'service_type' => 'serviceType',
    '服務類型' => 'serviceType',
    'quota_remaining' => 'quotaRemaining',
    '剩餘次數' => 'quotaRemaining',
    '剩餘額度' => 'quotaRemaining',
    '額度剩餘次數' => 'quotaRemaining',
    'quota_ratio' => 'quotaRatio',
    '剩餘比例' => 'quotaRatio',
    '額度剩餘比例' => 'quotaRatio',
    'quota_expiry' => 'quotaExpiry',
    '額度到期日' => 'quotaExpiry',
    'ratio5h' => 'ratio5h',
    '5 小時比例' => 'ratio5h',
    'expiry5h' => 'expiry5h',
    '5 小時到期' => 'expiry5h',
    'ratio_week' => 'ratioWeek',
    '一週比例' => 'ratioWeek',
    'expiry_week' => 'expiryWeek',
    '一週到期' => 'expiryWeek',
    'ratio_month' => 'ratioMonth',
    '一月比例' => 'ratioMonth',
    'expiry_month' => 'expiryMonth',
    '一月到期' => 'expiryMonth',
    'planned_date' => 'plannedDate',
    'planneddate' => 'plannedDate',
    '預定購買日' => 'plannedDate',
    '購買日' => 'plannedDate',
    '預定日期' => 'plannedDate',
    '預定價格' => 'price',
    '預定數量' => 'quantity',
    '預定商店' => 'shop',
    '店家' => 'shop',
    'pickup_method' => 'pickupMethod',
    'pickupmethod' => 'pickupMethod',
    '預定取貨方式' => 'pickupMethod',
    '取貨方式' => 'pickupMethod',
    '取貨' => 'pickupMethod',
    '購物名稱' => 'name',
    '商品名稱' => 'name',
    '幣種' => 'currency',
    '貨幣' => 'currency',
    'image_url' => 'imageUrl',
    'imageurl' => 'imageUrl',
    '圖片網址' => 'imageUrl',
    '商品圖片' => 'imageUrl',
    '商品圖片網址' => 'imageUrl',
];
// Appwrite # 前綴欄位（如 #filetype）動態去除 #，在 header 處理時套用

// Appwrite 匯出時可能附帶的 metadata 欄位，需忽略
$ignoredColumns = ['$permissions', '$databaseId', '$collectionId', '$tenant'];

$pdo = getConnection();
if ($table === 'trialpurchase') {
    fengbroEnsureTrialPurchaseTable($pdo);
}
if ($table === 'reinstall') {
    fengbroEnsureReinstallTable($pdo);
}
if ($table === 'quota') {
    fengbroEnsureQuotaTable($pdo);
}
if ($table === 'shoppinglist') {
    fengbroEnsureShoppingListTable($pdo);
}

$csvContent = file_get_contents($file);
if ($csvContent === false || trim($csvContent) === '') {
    jsonResponse(['error' => 'CSV 格式錯誤：檔案為空或無法讀取'], 400);
}

if (substr($csvContent, 0, 2) === "\xFF\xFE") {
    $csvContent = mb_convert_encoding(substr($csvContent, 2), 'UTF-8', 'UTF-16LE');
} elseif (substr($csvContent, 0, 2) === "\xFE\xFF") {
    $csvContent = mb_convert_encoding(substr($csvContent, 2), 'UTF-8', 'UTF-16BE');
} else {
    $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
    if (function_exists('mb_check_encoding') && !mb_check_encoding($csvContent, 'UTF-8')) {
        $csvContent = mb_convert_encoding($csvContent, 'UTF-8', 'CP950, BIG5, UTF-8');
    }
}

$lines = preg_split('/\r\n|\n|\r/', $csvContent);
$lines = array_values(array_filter($lines, function ($line) {
    return trim($line) !== '';
}));
if (!$lines) {
    jsonResponse(['error' => 'CSV 格式錯誤：找不到欄位列'], 400);
}

$delimiter = detectCsvDelimiter($lines[0]);
$headers = str_getcsv($lines[0], $delimiter, '"', '');
if (!$headers || count(array_filter($headers, 'strlen')) === 0) {
    jsonResponse(['error' => 'CSV 格式錯誤：無法解析欄位列'], 400);
}

$csvContent = implode("\n", $lines);
$handle = fopen('php://temp', 'r+');
fwrite($handle, $csvContent);
rewind($handle);
fgetcsv($handle, 0, $delimiter, '"', '');

// 轉換標頭名稱 (支援 LaravelMySQL 和 Appwrite 雙格式)
$headers = array_map(function ($h) use ($fieldMapping) {
    $h = trim($h);
    if (isset($fieldMapping[$h]))
        return $fieldMapping[$h];
    // Appwrite # 前綴欄位（如 #filetype -> filetype）
    if (str_starts_with($h, '#'))
        return substr($h, 1);
    return $h;
}, $headers);

// 動態取得資料表欄位，只保留 DB 中存在的欄位
$dbColumns = [];
try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM {$table}");
    foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $dbColumns[] = $col['Field'];
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'DB 錯誤: ' . $e->getMessage()], 500);
}

$ignoredIndexes = [];
foreach ($headers as $i => $h) {
    if (in_array($h, $ignoredColumns) || (str_starts_with($h, '$') && !isset($fieldMapping[$h]))) {
        $ignoredIndexes[] = $i;
    } elseif (!in_array($h, $dbColumns)) {
        // DB 中不存在的欄位一律忽略
        $ignoredIndexes[] = $i;
    }
}
// 移除被忽略的欄位
foreach ($ignoredIndexes as $i) {
    unset($headers[$i]);
}
$headers = array_values($headers);
$headerCount = count($headers);
if ($headerCount === 0) {
    jsonResponse(['error' => 'CSV 格式錯誤：找不到可匯入欄位，請確認欄位名稱是否正確'], 400);
}

$imported = 0;
$skipped = 0;
$errors = [];
$lineNum = 1;

while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
    $lineNum++;

    // 移除被忽略的欄位值
    foreach ($ignoredIndexes as $i) {
        unset($row[$i]);
    }
    $row = array_values($row);

    if (count($row) !== $headerCount) {
        $skipped++;
        $errors[] = "第 {$lineNum} 行: 欄位數不匹配 (預期 {$headerCount}, 實際 " . count($row) . ")";
        continue;
    }

    $data = array_combine($headers, $row);
    $recordName = $data['name'] ?? '未知';

    // 處理 ID
    $hasSourceId = !empty($data['id']);
    if (!$hasSourceId) {
        $data['id'] = generateUUID();
    }
    $currentId = $data['id'];

    // Appwrite 時間戳保留（不再 unset，讓 DB 保留原始記錄時間）
    // created_at / updated_at 在後面 ISO 轉換時會被處理

    // 處理空值
    foreach ($data as $key => $value) {
        if ($value === '' || $value === 'null') {
            $data[$key] = null;
        }
    }

    if ($table === 'bank') {
        foreach (['deposit', 'withdrawals', 'transfer'] as $moneyColumn) {
            if (array_key_exists($moneyColumn, $data)) {
                $data[$moneyColumn] = normalizeImportMoney($data[$moneyColumn]);
            }
        }
    }

    // subscription.note is VARCHAR(100); truncate to avoid import failure
    if ($table === 'subscription' && array_key_exists('note', $data) && $data['note'] !== null) {
        $data['note'] = mb_substr($data['note'], 0, 100);
    }

    if ($table === 'trialpurchase') {
        try {
            $data = array_merge($data, fengbroSanitizeTrialPurchaseRow($data));
        } catch (InvalidArgumentException $e) {
            $skipped++;
            $errors[] = "第 {$lineNum} 行: " . $e->getMessage();
            continue;
        }
    }
    if ($table === 'reinstall') {
        try {
            $data = array_merge($data, fengbroSanitizeReinstallRow($data));
        } catch (InvalidArgumentException $e) {
            $skipped++;
            $errors[] = "第 {$lineNum} 行: " . $e->getMessage();
            continue;
        }
    }
    if ($table === 'quota') {
        try {
            $data = array_merge($data, fengbroSanitizeQuotaRow($data));
        } catch (InvalidArgumentException $e) {
            $skipped++;
            $errors[] = "第 {$lineNum} 行: " . $e->getMessage();
            continue;
        }
    }
    if ($table === 'shoppinglist') {
        try {
            $data = array_merge($data, fengbroSanitizeShoppingItemRow($data));
        } catch (InvalidArgumentException $e) {
            $skipped++;
            $errors[] = "第 {$lineNum} 行: " . $e->getMessage();
            continue;
        }
    }

    // 轉換 ISO 8601 日期 -> MySQL DATETIME 格式
    // Appwrite 格式：2024-01-15T08:30:00.000+00:00 -> 2024-01-15 08:30:00
    foreach ($data as $key => $value) {
        if ($value !== null && preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/', $value, $m)) {
            $data[$key] = $m[1] . ' ' . $m[2];
        }
    }

    // 處理布林值
    if (array_key_exists('continue', $data) && $data['continue'] !== null) {
        $data['continue'] = filter_var($data['continue'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    } elseif (array_key_exists('continue', $data)) {
        $data['continue'] = 1; // 預設為 true
    }

    if (!$hasSourceId) {
        $duplicateId = null;
        if ($table === 'trialpurchase') {
            $duplicateId = fengbroFindTrialPurchaseImportId($pdo, $data);
        } elseif ($table === 'reinstall') {
            $duplicateId = fengbroFindReinstallImportId($pdo, $data);
        } elseif ($table === 'quota') {
            $duplicateId = fengbroFindQuotaImportId($pdo, $data);
        } elseif ($table === 'shoppinglist') {
            $duplicateId = fengbroFindShoppingImportId($pdo, $data);
        } else {
            $duplicateId = findExistingImportRecordId($pdo, $table, $data);
        }
        if ($duplicateId) {
            $currentId = $duplicateId;
            $data['id'] = $duplicateId;
        }
    }

    // 檢查是否已存在
    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id = ?");
    $stmt->execute([$currentId]);
    $exists = $stmt->fetch();

    try {
        if ($exists) {
            // 更新
            unset($data['id']);
            $sets = [];
            foreach (array_keys($data) as $col) {
                $sets[] = "`{$col}` = ?";
            }
            $sql = "UPDATE {$table} SET " . implode(',', $sets) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $values = array_values($data);
            $values[] = $currentId;
            $stmt->execute($values);
        } else {
            // 新增
            $columns = array_map(function ($c) {
                return "`{$c}`";
            }, array_keys($data));
            $placeholders = array_fill(0, count($data), '?');
            $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
        }
        $imported++;
    } catch (PDOException $e) {
        $errors[] = "{$recordName}: " . $e->getMessage();
    }
}

fclose($handle);

jsonResponse([
    'success' => true,
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => $errors
]);
