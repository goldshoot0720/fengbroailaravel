<?php
// 關閉所有可能的輸出
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '256M');

// 確保只輸出 JSON
function outputJson($data)
{
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once 'includes/functions.php';
    require_once 'includes/PureZip.php';
} catch (Exception $e) {
    outputJson(['success' => false, 'error' => '載入失敗: ' . $e->getMessage()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    outputJson(['success' => false, 'error' => '請使用 POST 方法']);
}

$type = $_POST['type'] ?? '';
$allowedTypes = ['image', 'music', 'document', 'video', 'podcast'];

if (!in_array($type, $allowedTypes)) {
    outputJson(['success' => false, 'error' => '無效的類型']);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    outputJson(['success' => false, 'error' => '請上傳 ZIP 檔案']);
}

// Save ZIP to temp directory
$tempDir = 'uploads/temp/';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

$tempFile = $tempDir . 'zip_' . uniqid() . '.zip';
if (!move_uploaded_file($_FILES['file']['tmp_name'], $tempFile)) {
    outputJson(['success' => false, 'error' => '無法儲存暫存檔案']);
}

// Read ZIP file list using PureZipExtract
$zip = new PureZipExtract();
if (!$zip->open($tempFile)) {
    @unlink($tempFile);
    outputJson(['success' => false, 'error' => '無法開啟 ZIP 檔案']);
}

$allFiles = $zip->getFiles();

// Define valid extensions per type
$validExtensions = [
    'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
    'music' => ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac', 'wma'],
    'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php', 'py', 'sql', 'csv'],
    'video' => ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v'],
    'podcast' => ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac', 'wma', 'mp4', 'webm', 'mkv', 'avi', 'mov']
];

$exts = $validExtensions[$type] ?? [];
$files = [];
$validCount = 0;

foreach ($allFiles as $fileName) {
    // Skip directories
    if (substr($fileName, -1) === '/')
        continue;

    $baseName = basename($fileName);

    // Skip cover files
    if (strpos($baseName, 'cover_') === 0)
        continue;

    // Skip hidden files
    if (strpos($baseName, '.') === 0)
        continue;

    // Skip __MACOSX entries
    if (strpos($fileName, '__MACOSX') !== false)
        continue;

    $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
    $valid = in_array($ext, $exts);

    if ($valid)
        $validCount++;

    $files[] = [
        'name' => $baseName,
        'ext' => $ext,
        'valid' => $valid
    ];
}

outputJson([
    'success' => true,
    'files' => $files,
    'totalFiles' => count($files),
    'validFiles' => $validCount,
    'tempFile' => $tempFile
]);
