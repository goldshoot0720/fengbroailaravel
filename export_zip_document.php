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

// === 1. 產生 Appwrite 格式 CSV 到暫存檔 ===
$fieldMapping = [
    'id' => '$id',
    'created_at' => '$createdAt',
    'updated_at' => '$updatedAt'
];

$columns = array_keys($data[0]);
$headers = array_map(function ($col) use ($fieldMapping) {
    return $fieldMapping[$col] ?? $col;
}, $columns);

$csvTempFile = tempnam(sys_get_temp_dir(), 'doc_csv_');
$csvHandle = fopen($csvTempFile, 'w');
fwrite($csvHandle, "\xEF\xBB\xBF");
fputcsv($csvHandle, $headers);

$docIndex = 0;
$coverIndex = 0;
$fileMap = [];

foreach ($data as $rowIdx => $row) {
    $rowFileMap = [];

    // file 欄位 -> document/ 資料夾，命名：流水號_文件名稱.檔案格式
    $filePath = $row['file'] ?? '';
    if ($filePath && file_exists($filePath)) {
        $docIndex++;
        $name = $row['name'] ?? '';
        $ext = pathinfo(basename($filePath), PATHINFO_EXTENSION) ?: 'pdf';
        $safeName = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name);
        $zipFileName = "{$docIndex}_{$safeName}.{$ext}";
        $zipName = "document/{$zipFileName}";
        $rowFileMap['file'] = [
            'zipName' => $zipName,
            'localPath' => $filePath
        ];
    }

    // cover 欄位 -> covers/ 資料夾，命名：流水號_圖片名稱.png
    $coverPath = $row['cover'] ?? '';
    if ($coverPath && file_exists($coverPath) && $coverPath !== $filePath) {
        $coverIndex++;
        $originalName = basename($coverPath);
        $safeName = preg_replace('/[\/\\\\:*?"<>|]/', '_', $originalName);
        $zipName = "covers/{$coverIndex}_{$safeName}";
        $rowFileMap['cover'] = [
            'zipName' => $zipName,
            'localPath' => $coverPath
        ];
    }

    $fileMap[$rowIdx] = $rowFileMap;

    $values = [];
    foreach ($columns as $col) {
        $value = $row[$col];

        if (isset($rowFileMap[$col])) {
            $value = $rowFileMap[$col]['zipName'];
        }

        if (in_array($col, ['created_at', 'updated_at']) && $value) {
            $value = date('c', strtotime($value));
        }
        $values[] = $value;
    }
    fputcsv($csvHandle, $values);
}

fclose($csvHandle);

// === 2. 建立 ZIP ===
$zip = new StreamingZip();
$zip->begin('appwrite-document.zip');

$zip->addLargeFile($csvTempFile, 'document.csv');

foreach ($fileMap as $rowFiles) {
    foreach ($rowFiles as $info) {
        $zip->addLargeFile($info['localPath'], $info['zipName']);
    }
}

$zip->finish();

// 清理暫存檔
@unlink($csvTempFile);
