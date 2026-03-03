<?php
/**
 * upload_chunk.php - 分片上傳（Chunked Upload）伺服器端
 * 每片上傳後寫入暫存目錄，全部片段收齊後自動組裝成完整檔案
 */
ob_start(); // 緩衝所有輸出，防止 PHP notice/warning 污染 JSON
ini_set('memory_limit', '512M');
set_time_limit(300);
error_reporting(0);       // 關閉錯誤輸出（避免 warning 混入 JSON）
ini_set('display_errors', 0);

function jsonOut($data)
{
    ob_end_clean(); // 丟棄所有緩衝的非 JSON 輸出
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['error' => '請使用 POST 方法']);
}

$uploadId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['uploadId'] ?? '');
$chunkIndex = intval($_POST['chunkIndex'] ?? -1);
$totalChunks = intval($_POST['totalChunks'] ?? 0);
$filename = basename($_POST['filename'] ?? 'upload.zip');

if (!$uploadId || $chunkIndex < 0 || $totalChunks <= 0) {
    jsonOut(['error' => '缺少必要參數 (uploadId/chunkIndex/totalChunks)']);
}

// 暫存片段目錄
$chunkDir = 'uploads/temp/chunks/' . $uploadId;
if (!is_dir($chunkDir)) {
    if (!mkdir($chunkDir, 0755, true)) {
        jsonOut(['error' => '無法建立暫存目錄']);
    }
}

// 接收片段檔案
if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['chunk']['error'] ?? -1;
    jsonOut(['error' => "片段上傳失敗 (error={$errCode}, chunkIndex={$chunkIndex})"]);
}

$chunkFile = $chunkDir . '/chunk_' . sprintf('%05d', $chunkIndex);
if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile)) {
    jsonOut(['error' => "無法儲存片段 chunk_{$chunkIndex}"]);
}

// 確認是否全部片段都到齊
$receivedChunks = glob($chunkDir . '/chunk_*');
$receivedCount = count($receivedChunks);

if ($receivedCount < $totalChunks) {
    jsonOut([
        'status' => 'chunk_received',
        'received' => $receivedCount,
        'total' => $totalChunks,
    ]);
}

// ===== 全部片段到齊，開始組裝 =====
$tempDir = 'uploads/temp';
if (!is_dir($tempDir))
    mkdir($tempDir, 0755, true);

$assembledFile = $tempDir . '/' . $uploadId . '.zip';
$out = fopen($assembledFile, 'wb');
if (!$out) {
    jsonOut(['error' => '無法建立組裝檔案']);
}

// 依序串接片段
for ($i = 0; $i < $totalChunks; $i++) {
    $part = $chunkDir . '/chunk_' . sprintf('%05d', $i);
    if (!file_exists($part)) {
        fclose($out);
        jsonOut(['error' => "片段 {$i} 遺失，請重新上傳"]);
    }
    $in = fopen($part, 'rb');
    while (!feof($in)) {
        fwrite($out, fread($in, 1024 * 1024)); // 1MB block
    }
    fclose($in);
    @unlink($part); // 清理片段
}
fclose($out);

// 清理片段目錄
@rmdir($chunkDir);

// 驗證組裝後的 ZIP 魔術字節
$fh = fopen($assembledFile, 'rb');
$magic = bin2hex(fread($fh, 4));
fclose($fh);

if ($magic !== '504b0304') {
    @unlink($assembledFile);
    jsonOut(['error' => '組裝後的檔案不是有效的 ZIP (magic=' . $magic . ')']);
}

jsonOut([
    'status' => 'assembled',
    'tempFile' => $assembledFile,
    'size' => filesize($assembledFile),
    'sizeMB' => round(filesize($assembledFile) / 1024 / 1024, 2),
]);
