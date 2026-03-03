<?php
// Local test of import_zip_music.php logic against the actual appwrite-music.zip
// Run: php test_import_local.php

echo "=== Testing Import Logic Locally ===\n\n";

require_once 'includes/functions.php';

$zipPath = 'C:/Users/chbon/Downloads/appwrite-music (1).zip';
echo "ZIP: $zipPath\n";
echo "Size: " . number_format(filesize($zipPath)) . " bytes\n\n";

// 1. Open ZIP
$zip = new ZipArchive();
$result = $zip->open($zipPath);
echo "ZipArchive::open: " . ($result === true ? "SUCCESS" : "FAILED (code=$result)") . "\n";
if ($result !== true)
    exit(1);

echo "Entries: " . $zip->numFiles . "\n";

// 2. Extract to temp
$tempDir = sys_get_temp_dir() . '/test_import_' . uniqid();
mkdir($tempDir, 0755, true);
echo "Extracting to: $tempDir\n";

$start = microtime(true);
$zip->extractTo($tempDir);
$zip->close();
echo "Extract time: " . round(microtime(true) - $start, 2) . "s\n";

// 3. List files
$allFiles = [];
$rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($rit as $f) {
    if ($f->isFile())
        $allFiles[] = str_replace('\\', '/', substr($f->getPathname(), strlen($tempDir) + 1));
}
echo "Extracted " . count($allFiles) . " files\n";

// 4. Find CSV
$csvFile = null;
foreach (glob($tempDir . '/*.csv') as $f) {
    $csvFile = $f;
    break;
}
echo "CSV: " . ($csvFile ? basename($csvFile) : "NOT FOUND") . "\n";

if (!$csvFile) {
    echo "ERROR: No CSV found!\n";
    exit(1);
}

// 5. Get DB columns
$pdo = getConnection();
$dbColumns = [];
$colStmt = $pdo->query("SHOW COLUMNS FROM music");
foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    $dbColumns[] = $col['Field'];
}
echo "DB columns: " . implode(', ', $dbColumns) . "\n\n";

// 6. Parse CSV headers
$fieldMapping = ['$id' => 'id', '$createdAt' => 'created_at', '$updatedAt' => 'updated_at'];
$handle = fopen($csvFile, 'r');
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF")
    rewind($handle);
$rawHeaders = fgetcsv($handle, 0, ',', '"', '');
echo "Raw headers: " . json_encode($rawHeaders, JSON_UNESCAPED_UNICODE) . "\n";

$headers = array_map(function ($h) use ($fieldMapping) {
    return $fieldMapping[trim($h)] ?? trim($h);
}, $rawHeaders);
echo "Mapped headers: " . json_encode($headers, JSON_UNESCAPED_UNICODE) . "\n";

$ignoredIndexes = [];
foreach ($headers as $i => $h) {
    if (!in_array($h, $dbColumns)) {
        $ignoredIndexes[] = $i;
        echo "Ignoring col [$i]: $h\n";
    }
}
foreach ($ignoredIndexes as $i)
    unset($headers[$i]);
$headers = array_values($headers);
echo "Final headers: " . implode(', ', $headers) . "\n\n";

// 7. Process first 3 rows only (dry run - no DB insert)
$lineNum = 0;
$errors = [];
while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false && $lineNum < 3) {
    $lineNum++;
    foreach ($ignoredIndexes as $i)
        unset($row[$i]);
    $row = array_values($row);
    if (count($row) !== count($headers)) {
        echo "Row $lineNum: column count mismatch (" . count($row) . " vs " . count($headers) . ")\n";
        continue;
    }
    $data = array_combine($headers, $row);
    echo "Row $lineNum: id=" . ($data['id'] ?? '?') . ", name=" . ($data['name'] ?? '?') . ", file=" . ($data['file'] ?? '?') . "\n";

    // Check if file exists in extracted dir
    if (!empty($data['file'])) {
        $filePath = $tempDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $data['file']);
        echo "  file path: " . $data['file'] . " => " . (file_exists($filePath) ? "EXISTS" : "NOT FOUND") . "\n";
    }
}
fclose($handle);

// Cleanup
$rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($rit as $f) {
    if ($f->isDir())
        @rmdir($f->getRealPath());
    else
        @unlink($f->getRealPath());
}
@rmdir($tempDir);

echo "\nDone.\n";
