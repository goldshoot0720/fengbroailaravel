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
$allowedTables = ['subscription', 'food', 'article', 'commonaccount', 'image', 'music', 'podcast', 'video', 'commondocument', 'bank', 'routine'];

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
];
// Appwrite # 前綴欄位（如 #filetype）動態去除 #，在 header 處理時套用

// Appwrite 匯出時可能附帶的 metadata 欄位，需忽略
$ignoredColumns = ['$permissions', '$databaseId', '$collectionId', '$tenant'];

$pdo = getConnection();

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
        $duplicateId = findExistingImportRecordId($pdo, $table, $data);
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
