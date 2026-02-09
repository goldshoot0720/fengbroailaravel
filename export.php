<?php
require_once 'includes/functions.php';

$table = $_GET['table'] ?? '';
$allowedTables = ['subscription', 'food', 'article', 'commonaccount', 'image', 'music', 'podcast', 'commondocument', 'bank', 'routine'];

if (!in_array($table, $allowedTables)) {
    die('無效的資料表');
}

// 匯出格式: appwrite (預設) 或 laravel
$format = $_GET['format'] ?? 'appwrite';

$pdo = getConnection();
$stmt = $pdo->query("SELECT * FROM {$table} ORDER BY created_at DESC");
$data = $stmt->fetchAll();

if (empty($data)) {
    die('沒有資料可匯出');
}

// 取得欄位名稱
$columns = array_keys($data[0]);

if ($format === 'appwrite') {
    // Appwrite 格式: $id, $createdAt, $updatedAt
    $fieldMapping = [
        'id' => '$id',
        'created_at' => '$createdAt',
        'updated_at' => '$updatedAt'
    ];
    $headers = array_map(function($col) use ($fieldMapping) {
        return $fieldMapping[$col] ?? $col;
    }, $columns);
    $filename = 'Appwrite-' . $table . '.csv';
} else {
    // LaravelMySQL 格式: id, created_at, updated_at (原始欄位名)
    $headers = $columns;
    $filename = 'LaravelMySQL-' . $table . '.csv';
}

// 設定 CSV 下載標頭
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 輸出 BOM 以支援 Excel 正確顯示中文
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// 寫入標頭
fputcsv($output, $headers);

// 寫入資料
foreach ($data as $row) {
    $values = [];
    foreach ($columns as $col) {
        $value = $row[$col];
        // 日期格式轉換為 ISO 8601
        if (in_array($col, ['created_at', 'updated_at']) && $value) {
            $value = date('c', strtotime($value));
        }
        $values[] = $value;
    }
    fputcsv($output, $values);
}

fclose($output);
