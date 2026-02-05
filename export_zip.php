<?php
ini_set('memory_limit', '512M');
set_time_limit(0);

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

$table = $_GET['table'] ?? '';
$allowedTables = ['image'];

if (!in_array($table, $allowedTables)) {
    die('無效的資料表');
}

$pdo = getConnection();
$stmt = $pdo->query("SELECT * FROM {$table} ORDER BY created_at DESC");
$data = $stmt->fetchAll();

if (empty($data)) {
    die('沒有資料可匯出');
}

$zip = new PureZip();
$fileCount = 0;

foreach ($data as $row) {
    $filePath = $row['file'] ?? '';
    if ($filePath && file_exists($filePath)) {
        $fileName = basename($filePath);
        $zip->addFileFromPath($filePath, $fileName);
        $fileCount++;
    }
}

if ($fileCount === 0) {
    die('沒有圖片可匯出');
}

// Download ZIP
$filename = 'images-' . date('Y-m-d') . '.zip';
$zip->download($filename);
