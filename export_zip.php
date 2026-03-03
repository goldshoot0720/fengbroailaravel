<?php
ini_set('memory_limit', '256M');
set_time_limit(0);

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

$table = $_GET['table'] ?? '';
// 純資料表（無附件）
$pureDataTables = ['subscription', 'food', 'commonaccount', 'bank', 'routine'];
// 有圖片附件的資料表
$imageTables = ['image'];

$allowedTables = array_merge($pureDataTables, $imageTables);

if (!in_array($table, $allowedTables)) {
    die('無效的資料表');
}

$pdo = getConnection();
$stmt = $pdo->query("SELECT * FROM {$table} ORDER BY created_at DESC");
$data = $stmt->fetchAll();

if (empty($data)) {
    die('沒有資料可匯出');
}

// ===================================================================
// 情況A：純資料表（無附件），打包 CSV 成 ZIP 下載
// ===================================================================
if (in_array($table, $pureDataTables)) {
    $columns = array_keys($data[0]);
    $headers = $columns;

    $csvTempFile = tempnam(sys_get_temp_dir(), $table . '_csv_');
    $csvHandle = fopen($csvTempFile, 'w');
    fwrite($csvHandle, "\xEF\xBB\xBF"); // BOM
    fputcsv($csvHandle, $headers);

    foreach ($data as $row) {
        $values = [];
        foreach ($columns as $col) {
            $values[] = $row[$col];
        }
        fputcsv($csvHandle, $values);
    }
    fclose($csvHandle);

    $zipFilename = 'laravel-' . $table . '.zip';
    $csvFilename = 'laravel-' . $table . '.csv';

    $zip = new StreamingZip();
    $zip->begin($zipFilename);
    $zip->addLargeFile($csvTempFile, $csvFilename);
    $zip->finish();

    @unlink($csvTempFile);
    exit;
}

// ===================================================================
// 情況B：image 資料表（有圖片附件）
// ===================================================================
$zipFilename = 'laravel-' . $table . '.zip';
$zip = new StreamingZip();
$zip->begin($zipFilename);

// CSV 部分
$columns = array_keys($data[0]);
$csvTempFile = tempnam(sys_get_temp_dir(), $table . '_csv_');
$csvHandle = fopen($csvTempFile, 'w');
fwrite($csvHandle, "\xEF\xBB\xBF");
fputcsv($csvHandle, $columns);

$fileIndex = 0;
$fileMap = [];

foreach ($data as $rowIdx => $row) {
    $filePath = $row['file'] ?? '';
    $rowFileMap = [];
    if ($filePath && !preg_match('#^https?://#i', $filePath) && file_exists($filePath)) {
        $fileIndex++;
        $originalName = basename($filePath);
        $zipName = 'files/' . sprintf('%03d', $fileIndex) . '_' . $originalName;
        $rowFileMap['file'] = ['zipName' => $zipName, 'localPath' => $filePath];
    }
    $fileMap[$rowIdx] = $rowFileMap;

    $values = [];
    foreach ($columns as $col) {
        $value = $row[$col];
        if (isset($rowFileMap[$col])) {
            $value = $rowFileMap[$col]['zipName'];
        }
        $values[] = $value;
    }
    fputcsv($csvHandle, $values);
}
fclose($csvHandle);

$zip->addLargeFile($csvTempFile, 'laravel-' . $table . '.csv');

foreach ($fileMap as $rowFiles) {
    foreach ($rowFiles as $info) {
        $zip->addLargeFile($info['localPath'], $info['zipName']);
    }
}

$zip->finish();
@unlink($csvTempFile);
