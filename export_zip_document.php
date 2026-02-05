<?php
ini_set('memory_limit', '512M');
set_time_limit(0);

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

$pdo = getConnection();
$stmt = $pdo->query("SELECT * FROM commondocument WHERE category != 'video' OR category IS NULL ORDER BY created_at DESC");
$data = $stmt->fetchAll();

if (empty($data)) {
    die('沒有文件可匯出');
}

// Collect files to export
$filesToExport = [];
foreach ($data as $row) {
    $filePath = $row['file'] ?? '';
    if ($filePath && file_exists($filePath)) {
        $filesToExport[] = [
            'path' => $filePath,
            'name' => basename($filePath)
        ];
    }

    $coverPath = $row['cover'] ?? '';
    if ($coverPath && file_exists($coverPath) && $coverPath !== $filePath) {
        $filesToExport[] = [
            'path' => $coverPath,
            'name' => 'cover_' . basename($coverPath)
        ];
    }
}

if (empty($filesToExport)) {
    die('沒有文件檔案可匯出');
}

// Use streaming ZIP for large files
$zip = new StreamingZip();
$filename = 'documents-' . date('Y-m-d') . '.zip';
$zip->begin($filename);

foreach ($filesToExport as $file) {
    $zip->addLargeFile($file['path'], $file['name']);
}

$zip->finish();
