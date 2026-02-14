<?php
ini_set('memory_limit', '512M');
set_time_limit(0);

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

$pdo = getConnection();
$stmt = $pdo->query("SELECT * FROM article ORDER BY created_at DESC");
$data = $stmt->fetchAll();

if (empty($data)) {
    die('沒有筆記可匯出');
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

$csvTempFile = tempnam(sys_get_temp_dir(), 'article_csv_');
$csvHandle = fopen($csvTempFile, 'w');
// BOM
fwrite($csvHandle, "\xEF\xBB\xBF");
fputcsv($csvHandle, $headers);

// 檔案收集 (流水號_檔案名稱.格式)
$fileIndex = 0;
$fileMap = []; // row index => [field => info]

foreach ($data as $rowIdx => $row) {
    $rowFileMap = [];

    // 收集 file1~file3 的檔案
    for ($i = 1; $i <= 3; $i++) {
        $fileField = "file{$i}";
        $filePath = $row[$fileField] ?? '';
        if ($filePath && file_exists($filePath)) {
            $fileIndex++;
            $originalName = $row["file{$i}name"] ?? basename($filePath);
            // 確保檔名安全
            $safeName = preg_replace('/[\/\\\\:*?"<>|]/', '_', $originalName);
            $zipName = "files/{$fileIndex}_{$safeName}";
            $rowFileMap[$fileField] = [
                'zipName' => $zipName,
                'localPath' => $filePath
            ];
        }
    }

    $fileMap[$rowIdx] = $rowFileMap;

    // 寫入 CSV 行（把 file 路徑替換為 ZIP 內路徑）
    $values = [];
    foreach ($columns as $col) {
        $value = $row[$col];

        // 替換檔案路徑為 ZIP 內路徑
        if (isset($rowFileMap[$col])) {
            $value = $rowFileMap[$col]['zipName'];
        }

        // 日期格式轉換為 ISO 8601
        if (in_array($col, ['created_at', 'updated_at']) && $value) {
            $value = date('c', strtotime($value));
        }
        $values[] = $value;
    }
    fputcsv($csvHandle, $values);
}

fclose($csvHandle);

// === 2. 建立 ZIP (CSV + files/) ===
$zip = new StreamingZip();
$zip->begin('appwrite-article.zip');

// 加入 CSV
$zip->addLargeFile($csvTempFile, 'appwrite-article.csv');

// 加入所有檔案到 files/ 目錄
foreach ($fileMap as $rowIdx => $rowFiles) {
    foreach ($rowFiles as $field => $info) {
        $zip->addLargeFile($info['localPath'], $info['zipName']);
    }
}

$zip->finish();

// 清理暫存 CSV
@unlink($csvTempFile);
