<?php
ini_set('memory_limit', '512M');
set_time_limit(0);

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

$pdo = getConnection();
$stmt = $pdo->query("SELECT * FROM music ORDER BY created_at DESC");
$data = $stmt->fetchAll();

if (empty($data)) {
    die('沒有音樂可匯出');
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

$csvTempFile = tempnam(sys_get_temp_dir(), 'music_csv_');
$csvHandle = fopen($csvTempFile, 'w');
fwrite($csvHandle, "\xEF\xBB\xBF");
fputcsv($csvHandle, $headers);

$musicIndex = 0;
$coverIndex = 0;
$lyricsIndex = 0;
$fileMap = [];

foreach ($data as $rowIdx => $row) {
    $rowFileMap = [];

    // file 欄位 -> music/ 資料夾，命名：流水號_音樂名稱_語言.mp3
    $filePath = $row['file'] ?? '';
    if ($filePath && file_exists($filePath)) {
        $musicIndex++;
        $name = $row['name'] ?? '';
        $language = $row['language'] ?? '';
        $ext = pathinfo(basename($filePath), PATHINFO_EXTENSION) ?: 'mp3';
        $safeName = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name);
        $safeLang = preg_replace('/[\/\\\\:*?"<>|]/', '_', $language);
        $zipFileName = $safeLang ? "{$musicIndex}_{$safeName}_{$safeLang}.{$ext}" : "{$musicIndex}_{$safeName}.{$ext}";
        $zipName = "music/{$zipFileName}";
        $rowFileMap['file'] = [
            'zipName' => $zipName,
            'localPath' => $filePath
        ];
    }

    // cover 欄位 -> covers/ 資料夾
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

    // lyrics 欄位 -> lyrics/ 資料夾，命名：流水號_音樂名稱_語言.txt
    $lyrics = $row['lyrics'] ?? '';
    if (!empty($lyrics)) {
        $lyricsIndex++;
        $name = $row['name'] ?? '';
        $language = $row['language'] ?? '';
        $safeName = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name);
        $safeLang = preg_replace('/[\/\\\\:*?"<>|]/', '_', $language);
        $zipFileName = $safeLang ? "{$lyricsIndex}_{$safeName}_{$safeLang}.txt" : "{$lyricsIndex}_{$safeName}.txt";
        $zipName = "lyrics/{$zipFileName}";

        // 寫入歌詞到暫存檔
        $lyricsTempFile = tempnam(sys_get_temp_dir(), 'lyrics_');
        file_put_contents($lyricsTempFile, $lyrics);

        $rowFileMap['lyrics'] = [
            'zipName' => $zipName,
            'localPath' => $lyricsTempFile,
            'isTemp' => true
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
$zip->begin('appwrite-music.zip');

$zip->addLargeFile($csvTempFile, 'music.csv');

foreach ($fileMap as $rowFiles) {
    foreach ($rowFiles as $info) {
        $zip->addLargeFile($info['localPath'], $info['zipName']);
    }
}

$zip->finish();

// 清理暫存檔
@unlink($csvTempFile);
foreach ($fileMap as $rowFiles) {
    foreach ($rowFiles as $info) {
        if (!empty($info['isTemp'])) {
            @unlink($info['localPath']);
        }
    }
}
