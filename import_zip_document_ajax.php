<?php
ini_set('memory_limit', '512M');
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

// 接收從 zip-preview 確認的暫存檔案
$tempFile = $_POST['tempFile'] ?? '';

if (empty($tempFile) || !file_exists($tempFile)) {
    echo json_encode(['success' => false, 'error' => '暫存檔案不存在或已過期']);
    exit;
}

// Extract ZIP
$extractDir = sys_get_temp_dir() . '/doc_import_' . uniqid();
if (!is_dir($extractDir)) {
    mkdir($extractDir, 0755, true);
}

$zip = new PureZipExtract();
if (!$zip->open($tempFile)) {
    @unlink($tempFile);
    echo json_encode(['success' => false, 'error' => '無法解壓 ZIP 檔案']);
    exit;
}

$zip->extractTo($extractDir);
$extractedFiles = $zip->getFiles();

// Move files to uploads directory
$uploadDir = 'uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$importedCount = 0;
$errors = [];
$pdo = getConnection();

// Valid document extensions
$validExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php', 'py', 'sql', 'csv'];

foreach ($extractedFiles as $fileName) {
    // 跳過目錄
    if (substr($fileName, -1) === '/')
        continue;

    $sourcePath = $extractDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileName);
    if (!file_exists($sourcePath))
        continue;

    $baseName = basename($fileName);

    // Skip cover files, hidden files, and __MACOSX
    if (strpos($baseName, 'cover_') === 0)
        continue;
    if (strpos($baseName, '.') === 0)
        continue;
    if (strpos($fileName, '__MACOSX') !== false)
        continue;

    // Check extension
    $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
    if (!in_array($ext, $validExtensions))
        continue;

    $targetPath = $uploadDir . '/' . $baseName;

    // Avoid overwriting
    if (file_exists($targetPath)) {
        $pathInfo = pathinfo($baseName);
        $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $targetPath = $uploadDir . '/' . $pathInfo['filename'] . '_' . time() . $ext;
    }

    if (copy($sourcePath, $targetPath)) {
        $name = pathinfo($baseName, PATHINFO_FILENAME);
        try {
            $stmt = $pdo->prepare("INSERT INTO commondocument (id, name, file, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([generateUUID(), $name, $targetPath]);
            $importedCount++;
        } catch (Exception $e) {
            $errors[] = "資料庫錯誤: " . $e->getMessage();
        }
    } else {
        $errors[] = "無法複製: " . $baseName;
    }
}

// Cleanup temp directory
$tempFiles = glob("$extractDir/*");
if ($tempFiles) {
    foreach ($tempFiles as $tf) {
        if (is_file($tf))
            @unlink($tf);
    }
}
if (is_dir($extractDir))
    @rmdir($extractDir);

// Cleanup temp zip
@unlink($tempFile);

echo json_encode([
    'success' => true,
    'imported' => $importedCount,
    'errors' => $errors
]);
