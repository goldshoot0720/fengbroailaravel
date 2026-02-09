<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Catch all errors and return as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode(['error' => "Error: $errstr in $errfile on line $errline"]);
    exit;
});

set_exception_handler(function($e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
});

require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => '請使用 POST 方法'], 400);
}

$table = $_POST['table'] ?? '';
$allowedTables = ['subscription', 'food', 'article', 'commonaccount', 'image', 'music', 'podcast', 'commondocument', 'bank', 'routine'];

if (!in_array($table, $allowedTables)) {
    jsonResponse(['error' => '無效的資料表'], 400);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => '請上傳檔案'], 400);
}

$file = $_FILES['file']['tmp_name'];

// 支援 LaravelMySQL 和 Appwrite 雙格式的欄位名稱對應
$fieldMapping = [
    '$id' => 'id',
    '$createdAt' => 'created_at',
    '$updatedAt' => 'updated_at'
];

// Appwrite 匯出時可能附帶的 metadata 欄位，需忽略
$ignoredColumns = ['$permissions', '$databaseId', '$collectionId', '$tenant'];

$pdo = getConnection();

// 讀取 CSV
$handle = fopen($file, 'r');

// 檢測 BOM 並跳過
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

// 讀取標頭
$headers = fgetcsv($handle);
if (!$headers) {
    jsonResponse(['error' => 'CSV 格式錯誤'], 400);
}

// 轉換標頭名稱 (支援 LaravelMySQL 和 Appwrite 雙格式)
$headers = array_map(function($h) use ($fieldMapping) {
    $h = trim($h);
    return $fieldMapping[$h] ?? $h;
}, $headers);

// 找出需要忽略的欄位索引 (Appwrite metadata)
$ignoredIndexes = [];
foreach ($headers as $i => $h) {
    if (in_array($h, $ignoredColumns) || (str_starts_with($h, '$') && !isset($fieldMapping[$h]))) {
        $ignoredIndexes[] = $i;
    }
}
// 移除被忽略的欄位
foreach ($ignoredIndexes as $i) {
    unset($headers[$i]);
}
$headers = array_values($headers);
$headerCount = count($headers);

$imported = 0;
$skipped = 0;
$errors = [];
$lineNum = 1;

while (($row = fgetcsv($handle)) !== false) {
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
    if (empty($data['id'])) {
        $data['id'] = generateUUID();
    }
    $currentId = $data['id'];

    // 移除時間戳欄位，讓資料庫自動處理
    unset($data['created_at']);
    unset($data['updated_at']);

    // 處理空值
    foreach ($data as $key => $value) {
        if ($value === '' || $value === 'null') {
            $data[$key] = null;
        }
    }

    // 處理布林值
    if (array_key_exists('continue', $data) && $data['continue'] !== null) {
        $data['continue'] = filter_var($data['continue'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    } elseif (array_key_exists('continue', $data)) {
        $data['continue'] = 1; // 預設為 true
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
            $columns = array_map(function($c) { return "`{$c}`"; }, array_keys($data));
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
