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

// Appwrite 相容的欄位名稱對應 (反向)
$fieldMapping = [
    '$id' => 'id',
    '$createdAt' => 'created_at',
    '$updatedAt' => 'updated_at'
];

$pdo = getConnection();

// 讀取 CSV
$handle = fopen($file, 'r');

// 檢測 BOM 並跳過
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

// 讀取標頭
$headers = fgetcsv($handle, 0, ',', '"', '\\');
if (!$headers) {
    jsonResponse(['error' => 'CSV 格式錯誤'], 400);
}

// 轉換標頭名稱
$headers = array_map(function($h) use ($fieldMapping) {
    $h = trim($h);
    return $fieldMapping[$h] ?? $h;
}, $headers);

$imported = 0;
$errors = [];

while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
    if (count($row) !== count($headers)) {
        continue;
    }

    $data = array_combine($headers, $row);

    // 處理 ID
    if (empty($data['id'])) {
        $data['id'] = generateUUID();
    }

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
    if (isset($data['continue'])) {
        $data['continue'] = filter_var($data['continue'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    // 檢查是否已存在
    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id = ?");
    $stmt->execute([$data['id']]);
    $exists = $stmt->fetch();

    try {
        if ($exists) {
            // 更新
            $id = $data['id'];
            unset($data['id']);
            $sets = [];
            foreach (array_keys($data) as $col) {
                $sets[] = "`{$col}` = ?";
            }
            $sql = "UPDATE {$table} SET " . implode(',', $sets) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $values = array_values($data);
            $values[] = $id;
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
        $errors[] = "ID {$data['id']}: " . $e->getMessage();
    }
}

fclose($handle);

jsonResponse([
    'success' => true,
    'imported' => $imported,
    'errors' => $errors
]);
