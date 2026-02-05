<?php
ini_set('memory_limit', '256M');
set_time_limit(0);

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => '請使用 POST 方法'], 400);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => '請上傳檔案'], 400);
}

$zipFile = $_FILES['file']['tmp_name'];

// Create temp directory for extraction
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_podcast_' . uniqid();
mkdir($tempDir);

// Extract ZIP using pure PHP
$zip = new PureZipExtract();
if (!$zip->open($zipFile)) {
    rmdir($tempDir);
    jsonResponse(['error' => '無法開啟 ZIP 檔案'], 400);
}

$zip->extractTo($tempDir);

// Copy podcast files to uploads directory
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$pdo = getConnection();
$imported = 0;
$errors = [];

// Get all podcast files from ZIP (audio and video)
$podcastExtensions = ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac', 'wma', 'mp4', 'webm', 'mkv', 'avi', 'mov'];
$files = glob($tempDir . DIRECTORY_SEPARATOR . '*');

foreach ($files as $file) {
    if (!is_file($file))
        continue;

    $fileName = basename($file);

    // Skip cover files
    if (strpos($fileName, 'cover_') === 0)
        continue;

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Skip non-podcast files
    if (!in_array($ext, $podcastExtensions))
        continue;

    // Copy to uploads
    $destPath = $uploadDir . $fileName;

    // Handle duplicate filenames
    if (file_exists($destPath)) {
        $info = pathinfo($fileName);
        $base = $info['filename'];
        $counter = 1;
        while (file_exists($uploadDir . $base . '_' . $counter . '.' . $ext)) {
            $counter++;
        }
        $fileName = $base . '_' . $counter . '.' . $ext;
        $destPath = $uploadDir . $fileName;
    }

    if (!copy($file, $destPath)) {
        $errors[] = "無法複製: $fileName";
        continue;
    }

    // Create database record
    $name = pathinfo($fileName, PATHINFO_FILENAME);
    $filePath = 'uploads/' . $fileName;

    try {
        $id = generateUUID();
        $sql = "INSERT INTO podcast (id, name, file) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $name, $filePath]);
        $imported++;
    } catch (PDOException $e) {
        $errors[] = "$fileName: " . $e->getMessage();
    }
}

// Cleanup temp directory
$cleanup = function ($dir) use (&$cleanup) {
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $file) {
        is_dir($file) ? $cleanup($file) : unlink($file);
    }
    @rmdir($dir);
};
$cleanup($tempDir);

jsonResponse([
    'success' => true,
    'imported' => $imported,
    'errors' => $errors
]);
