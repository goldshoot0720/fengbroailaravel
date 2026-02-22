<?php
require_once 'includes/functions.php';

ini_set('upload_max_filesize', '200M');
ini_set('post_max_size', '200M');
set_time_limit(0);

function parseSizeToBytes($value)
{
    $value = trim((string) $value);
    if ($value === '')
        return 0;
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    if ($unit === 'g')
        return (int) round($number * 1024 * 1024 * 1024);
    if ($unit === 'm')
        return (int) round($number * 1024 * 1024);
    if ($unit === 'k')
        return (int) round($number * 1024);
    return (int) round($number);
}

$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');
$uploadMaxBytes = parseSizeToBytes($uploadMax);
$postMaxBytes = parseSizeToBytes($postMax);
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
    jsonResponse(['error' => "檔案太大，超過伺服器限制 post_max_size={$postMax}"], 400);
}

// 建立上傳目錄
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => '請使用 POST 方法'], 400);
}

if (!isset($_FILES['file'])) {
    jsonResponse(['error' => '請上傳檔案'], 400);
}

if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorCode = $_FILES['file']['error'];
    $errorMessage = '檔案上傳失敗';
    if ($errorCode === UPLOAD_ERR_INI_SIZE) {
        $errorMessage = "檔案太大，超過伺服器限制 upload_max_filesize={$uploadMax}";
    } elseif ($errorCode === UPLOAD_ERR_FORM_SIZE) {
        $errorMessage = "檔案太大，超過表單限制";
    } elseif ($errorCode === UPLOAD_ERR_PARTIAL) {
        $errorMessage = '檔案只上傳部分內容';
    } elseif ($errorCode === UPLOAD_ERR_NO_FILE) {
        $errorMessage = '未選擇檔案';
    } elseif ($errorCode === UPLOAD_ERR_NO_TMP_DIR) {
        $errorMessage = '伺服器暫存目錄不存在';
    } elseif ($errorCode === UPLOAD_ERR_CANT_WRITE) {
        $errorMessage = '伺服器無法寫入檔案';
    } elseif ($errorCode === UPLOAD_ERR_EXTENSION) {
        $errorMessage = '伺服器擴充模組中止上傳';
    }
    jsonResponse(['error' => $errorMessage], 400);
}

$file = $_FILES['file'];
$originalName = $file['name'];
$fileType = $file['type'];
$fileSize = $file['size'];

// 檢查檔案大小 (最大 200MB)
if ($fileSize > 200 * 1024 * 1024) {
    jsonResponse(['error' => '檔案大小不能超過 200MB'], 400);
}

if ($uploadMaxBytes > 0 && $fileSize > $uploadMaxBytes) {
    jsonResponse(['error' => "檔案太大，超過伺服器限制 upload_max_filesize={$uploadMax}"], 400);
}

// 生成唯一檔名，按副檔名分資料夾
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$subDir = $ext ? $ext : 'other';
$uploadSubDir = $uploadDir . $subDir . '/';
if (!is_dir($uploadSubDir)) {
    mkdir($uploadSubDir, 0755, true);
}
$newName = generateUUID() . ($ext ? '.' . $ext : '');
$filePath = $uploadSubDir . $newName;

if (move_uploaded_file($file['tmp_name'], $filePath)) {
    jsonResponse([
        'success' => true,
        'file' => $filePath,
        'filename' => $originalName,
        'filetype' => $ext ? '.' . $ext : ''
    ]);
} else {
    jsonResponse(['error' => '檔案上傳失敗'], 500);
}
