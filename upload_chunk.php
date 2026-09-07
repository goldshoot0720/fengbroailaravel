<?php
/**
 * upload_chunk.php - general chunked upload endpoint.
 *
 * Modes:
 * - target=temp: save chunks under uploads/temp/chunks/{uploadId}/, then assemble for ZIP import preview.
 * - target=file: save chunks under uploads/temp/chunks/{uploadId}/, then assemble for media/document uploads.
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

function cleanExtension($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/', '', $ext);
    return $ext !== '' ? $ext : 'bin';
}

function ensureDir($dir)
{
    return is_dir($dir) || mkdir($dir, 0755, true);
}

function readUploadState($statePath)
{
    if (!is_file($statePath)) {
        return null;
    }
    $state = json_decode((string) file_get_contents($statePath), true);
    return is_array($state) ? $state : null;
}

function writeUploadState($statePath, $state)
{
    file_put_contents($statePath, json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function rrmdir($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . '/*') ?: [] as $path) {
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function cleanupOldChunkArtifacts($stateDir, $ttlSeconds = 21600)
{
    if (!is_dir($stateDir)) {
        return;
    }

    $cutoff = time() - $ttlSeconds;
    foreach (glob($stateDir . '/*.json') ?: [] as $statePath) {
        if (@filemtime($statePath) >= $cutoff) {
            continue;
        }
        $state = readUploadState($statePath);
        if ($state && !empty($state['partPath']) && is_file($state['partPath'])) {
            @unlink($state['partPath']);
        }
        if ($state && !empty($state['chunkDir']) && is_dir($state['chunkDir'])) {
            rrmdir($state['chunkDir']);
        }
        @unlink($statePath);
    }

    foreach (glob($stateDir . '/*', GLOB_ONLYDIR) ?: [] as $oldDir) {
        if (@filemtime($oldDir) >= $cutoff) {
            continue;
        }
        foreach (glob($oldDir . '/chunk_*') ?: [] as $oldChunk) {
            @unlink($oldChunk);
        }
        @rmdir($oldDir);
    }
}

function copyStreamToFile($in, $path)
{
    $tmpPath = $path . '.tmp';
    $out = fopen($tmpPath, 'wb');
    if (!$out) {
        return false;
    }

    $ok = true;
    while (!feof($in)) {
        $bytes = fwrite($out, fread($in, 64 * 1024));
        if ($bytes === false) {
            $ok = false;
            break;
        }
    }

    fclose($out);
    if (!$ok) {
        @unlink($tmpPath);
        return false;
    }
    return @rename($tmpPath, $path);
}

function copyFileToChunk($tmpPath, $chunkPath)
{
    $in = fopen($tmpPath, 'rb');
    if (!$in) {
        return false;
    }
    $ok = copyStreamToFile($in, $chunkPath);
    fclose($in);
    return $ok;
}

function writeStringToFile($data, $path)
{
    $tmpPath = $path . '.tmp';
    $out = fopen($tmpPath, 'wb');
    if (!$out) {
        return false;
    }
    $written = fwrite($out, $data);
    fclose($out);
    if ($written === false || $written !== strlen($data)) {
        @unlink($tmpPath);
        return false;
    }
    return @rename($tmpPath, $path);
}

function assembleChunks($state, $totalChunks)
{
    $partPath = $state['partPath'];
    $out = fopen($partPath, 'wb');
    if (!$out) {
        return false;
    }

    $ok = true;
    for ($i = 0; $i < $totalChunks; $i++) {
        $chunkPath = $state['chunkDir'] . '/chunk_' . str_pad((string) $i, 6, '0', STR_PAD_LEFT);
        $in = fopen($chunkPath, 'rb');
        if (!$in) {
            $ok = false;
            break;
        }
        while (!feof($in)) {
            $bytes = fwrite($out, fread($in, 64 * 1024));
            if ($bytes === false) {
                $ok = false;
                break 2;
            }
        }
        fclose($in);
    }

    fclose($out);
    if (!$ok) {
        @unlink($partPath);
        return false;
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chunkJson(['error' => '請使用 POST 上傳'], 405);
}

$uploadId = cleanUploadId($_POST['uploadId'] ?? $_GET['uploadId'] ?? '');
$chunkIndex = (int) ($_POST['chunkIndex'] ?? $_GET['chunkIndex'] ?? -1);
$totalChunks = (int) ($_POST['totalChunks'] ?? $_GET['totalChunks'] ?? 0);
$filename = cleanFilename($_POST['filename'] ?? $_GET['filename'] ?? 'upload.bin');
$target = strtolower((string) ($_POST['target'] ?? $_GET['target'] ?? 'temp'));
$target = in_array($target, ['temp', 'file'], true) ? $target : 'temp';
$ext = cleanExtension($filename);

if ($uploadId === '' || $chunkIndex < 0 || $totalChunks <= 0) {
    chunkJson(['error' => '缺少必要參數 uploadId/chunkIndex/totalChunks'], 400);
}

$stateDir = 'uploads/temp/chunks';
if (!ensureDir($stateDir)) {
    chunkJson(['error' => '無法建立分段狀態目錄'], 500);
}
cleanupOldChunkArtifacts($stateDir);

$statePath = $stateDir . '/' . $uploadId . '.json';
$state = readUploadState($statePath);

if (!$state) {
    if ($target === 'temp') {
        $outputDir = 'uploads/temp';
        $outputName = $uploadId . '.' . $ext;
    } else {
        $outputDir = 'uploads/' . $ext;
        $outputName = generateUUID() . '.' . $ext;
    }

    if (!ensureDir($outputDir)) {
        chunkJson(['error' => '無法建立上傳目錄'], 500);
    }

    $finalPath = $outputDir . '/' . $outputName;
    $state = [
        'uploadId' => $uploadId,
        'filename' => $filename,
        'target' => $target,
        'ext' => $ext,
        'totalChunks' => $totalChunks,
        'nextIndex' => 0,
        'finalPath' => $finalPath,
        'partPath' => $finalPath . '.part',
        'chunkDir' => $stateDir . '/' . $uploadId,
        'createdAt' => time(),
    ];
    writeUploadState($statePath, $state);
}

if (empty($state['chunkDir'])) {
    $state['chunkDir'] = $stateDir . '/' . $uploadId;
}

if (!ensureDir($state['chunkDir'])) {
    chunkJson(['error' => '無法建立片段暫存資料夾'], 500);
}

if ((int) ($state['totalChunks'] ?? 0) !== $totalChunks) {
    chunkJson(['error' => '分段總數不一致，請重新上傳'], 400);
}

if ($chunkIndex < (int) $state['nextIndex']) {
    $received = (int) $state['nextIndex'];
    chunkJson([
        'status' => 'chunk_received',
        'received' => min($received, $totalChunks),
        'total' => $totalChunks,
        'duplicate' => true,
    ]);
}

if ($chunkIndex > (int) $state['nextIndex']) {
    chunkJson(['error' => "片段 {$chunkIndex} 太早抵達，缺少片段 {$state['nextIndex']}"], 409);
}

$transport = strtolower((string) ($_GET['transport'] ?? $_POST['transport'] ?? 'form'));
$chunkPath = $state['chunkDir'] . '/chunk_' . str_pad((string) $chunkIndex, 6, '0', STR_PAD_LEFT);

if ($transport === 'raw') {
    $raw = fopen('php://input', 'rb');
    if (!$raw) {
        chunkJson(['error' => "無法讀取 raw 片段 {$chunkIndex}"], 400);
    }
    $ok = copyStreamToFile($raw, $chunkPath);
    fclose($raw);
    if (!$ok) {
        chunkJson(['error' => "無法寫入 raw 片段 {$chunkIndex}，請稍後重試"], 500);
    }
} elseif ($transport === 'base64') {
    $encoded = (string) ($_POST['chunkData'] ?? '');
    if ($encoded === '') {
        chunkJson(['error' => "缺少 base64 片段 {$chunkIndex}"], 400);
    }
    $decoded = base64_decode($encoded, true);
    if ($decoded === false) {
        chunkJson(['error' => "base64 片段 {$chunkIndex} 解碼失敗"], 400);
    }
    if (!writeStringToFile($decoded, $chunkPath)) {
        chunkJson(['error' => "無法寫入 base64 片段 {$chunkIndex}，請稍後重試"], 500);
    }
} else {
    if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['chunk']['error'] ?? -1;
        chunkJson(['error' => "片段上傳失敗 (error={$errorCode}, chunkIndex={$chunkIndex})"], 400);
    }
    if (!copyFileToChunk($_FILES['chunk']['tmp_name'], $chunkPath)) {
        chunkJson(['error' => "無法寫入片段 {$chunkIndex}，請稍後重試"], 500);
    }
}

$state['nextIndex'] = (int) $state['nextIndex'] + 1;
$state['updatedAt'] = time();
writeUploadState($statePath, $state);

if ($state['nextIndex'] < $totalChunks) {
    chunkJson([
        'status' => 'chunk_received',
        'received' => $state['nextIndex'],
        'total' => $totalChunks,
    ]);
}

if (!assembleChunks($state, $totalChunks)) {
    chunkJson(['error' => '無法合併片段，請稍後重試'], 500);
}

if (!@rename($state['partPath'], $state['finalPath'])) {
    chunkJson(['error' => '分段已收齊，但無法完成檔案合併'], 500);
}
rrmdir($state['chunkDir']);
@unlink($statePath);

$response = [
    'success' => true,
    'status' => 'assembled',
    'filename' => $filename,
    'filetype' => '.' . $state['ext'],
    'size' => filesize($state['finalPath']),
    'sizeMB' => round(filesize($state['finalPath']) / 1024 / 1024, 2),
];

if ($state['target'] === 'temp') {
    $response['tempFile'] = $state['finalPath'];
} else {
    $response['file'] = $state['finalPath'];
}

chunkJson($response);
