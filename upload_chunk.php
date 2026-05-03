<?php
/**
 * upload_chunk.php - general chunked upload endpoint.
 *
 * Modes:
 * - target=temp: assemble to uploads/temp/{uploadId}.{ext}, used by ZIP import preview.
 * - target=file: assemble to uploads/{ext}/{uuid}.{ext}, used by media/document uploads.
 */
ob_start();
ini_set('memory_limit', '512M');
set_time_limit(300);
error_reporting(0);
ini_set('display_errors', 0);

require_once 'includes/functions.php';

function chunkJson($data, $status = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function cleanUploadId($value)
{
    return preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $value);
}

function cleanFilename($value)
{
    $filename = basename((string) $value);
    return $filename !== '' ? $filename : 'upload.bin';
}

function removeChunkDirectory($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . '/chunk_*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($dir);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chunkJson(['error' => '請使用 POST 上傳'], 405);
}

$uploadId = cleanUploadId($_POST['uploadId'] ?? '');
$chunkIndex = (int) ($_POST['chunkIndex'] ?? -1);
$totalChunks = (int) ($_POST['totalChunks'] ?? 0);
$filename = cleanFilename($_POST['filename'] ?? 'upload.bin');
$target = strtolower((string) ($_POST['target'] ?? 'temp'));
$target = in_array($target, ['temp', 'file'], true) ? $target : 'temp';

if ($uploadId === '' || $chunkIndex < 0 || $totalChunks <= 0) {
    chunkJson(['error' => '缺少必要參數 uploadId/chunkIndex/totalChunks'], 400);
}

if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    $errorCode = $_FILES['chunk']['error'] ?? -1;
    chunkJson(['error' => "片段上傳失敗 (error={$errorCode}, chunkIndex={$chunkIndex})"], 400);
}

$chunkDir = 'uploads/temp/chunks/' . $uploadId;
if (!is_dir($chunkDir) && !mkdir($chunkDir, 0755, true)) {
    chunkJson(['error' => '無法建立片段暫存目錄'], 500);
}

$chunkFile = $chunkDir . '/chunk_' . sprintf('%05d', $chunkIndex);
if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile)) {
    chunkJson(['error' => "無法儲存片段 chunk_{$chunkIndex}"], 500);
}

$receivedChunks = glob($chunkDir . '/chunk_*') ?: [];
$receivedCount = count($receivedChunks);

if ($receivedCount < $totalChunks) {
    chunkJson([
        'status' => 'chunk_received',
        'received' => $receivedCount,
        'total' => $totalChunks,
    ]);
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$ext = preg_replace('/[^a-z0-9]/', '', $ext);
$ext = $ext !== '' ? $ext : 'bin';

if ($target === 'temp') {
    $outputDir = 'uploads/temp';
    $outputName = $uploadId . '.' . $ext;
} else {
    $outputDir = 'uploads/' . $ext;
    $outputName = generateUUID() . '.' . $ext;
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
    chunkJson(['error' => '無法建立上傳目錄'], 500);
}

$assembledFile = $outputDir . '/' . $outputName;
$out = fopen($assembledFile, 'wb');
if (!$out) {
    chunkJson(['error' => '無法建立合併檔案'], 500);
}

for ($i = 0; $i < $totalChunks; $i++) {
    $part = $chunkDir . '/chunk_' . sprintf('%05d', $i);
    if (!is_file($part)) {
        fclose($out);
        @unlink($assembledFile);
        chunkJson(['error' => "片段 {$i} 缺失，請重新上傳"], 400);
    }

    $in = fopen($part, 'rb');
    if (!$in) {
        fclose($out);
        @unlink($assembledFile);
        chunkJson(['error' => "無法讀取片段 {$i}"], 500);
    }

    while (!feof($in)) {
        fwrite($out, fread($in, 1024 * 1024));
    }
    fclose($in);
}
fclose($out);
removeChunkDirectory($chunkDir);

$response = [
    'success' => true,
    'status' => 'assembled',
    'filename' => $filename,
    'filetype' => '.' . $ext,
    'size' => filesize($assembledFile),
    'sizeMB' => round(filesize($assembledFile) / 1024 / 1024, 2),
];

if ($target === 'temp') {
    $response['tempFile'] = $assembledFile;
} else {
    $response['file'] = $assembledFile;
}

chunkJson($response);
