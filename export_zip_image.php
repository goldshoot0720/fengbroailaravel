<?php
ini_set('memory_limit', '512M');
set_time_limit(0);

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

$pdo = getConnection();
$stmt = $pdo->query("SELECT * FROM image ORDER BY created_at DESC");
$data = $stmt->fetchAll();

if (empty($data)) {
    die('沒有圖片可匯出');
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

$csvTempFile = tempnam(sys_get_temp_dir(), 'image_csv_');
$csvHandle = fopen($csvTempFile, 'w');
fwrite($csvHandle, "\xEF\xBB\xBF");
fputcsv($csvHandle, $headers);

$fileIndex = 0;
$fileMap = [];

foreach ($data as $rowIdx => $row) {
    $rowFileMap = [];

    // file 欄位
    $filePath = $row['file'] ?? '';
    if ($filePath && file_exists($filePath)) {
        $fileIndex++;
        $originalName = basename($filePath);
        $safeName = preg_replace('/[\/\\\\:*?"<>|]/', '_', $originalName);
        $zipName = "images/{$fileIndex}_{$safeName}";
        $rowFileMap['file'] = [
            'zipName' => $zipName,
            'localPath' => $filePath
        ];
    }

    // cover 欄位（如果和 file 不同）
    $coverPath = $row['cover'] ?? '';
    if ($coverPath && file_exists($coverPath) && $coverPath !== $filePath) {
        $fileIndex++;
        $originalName = basename($coverPath);
        $safeName = preg_replace('/[\/\\\\:*?"<>|]/', '_', $originalName);
        $zipName = "images/{$fileIndex}_{$safeName}";
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
        } elseif ($col === 'cover' && isset($rowFileMap['file'])) {
            // cover 和 file 相同時，也指向同一個 ZIP 路徑
            $value = $rowFileMap['file']['zipName'];
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
$zip->begin('appwrite-image.zip');

$zip->addLargeFile($csvTempFile, 'image.csv');

foreach ($fileMap as $rowFiles) {
    foreach ($rowFiles as $info) {
        $zip->addLargeFile($info['localPath'], $info['zipName']);
    }
}

$zip->finish();

@unlink($csvTempFile);
