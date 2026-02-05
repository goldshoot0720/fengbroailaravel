<?php
ini_set('memory_limit', '512M');
set_time_limit(0);
error_reporting(E_ALL);

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

// 收集錯誤訊息
$errors = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['zipfile'])) {
    header('Location: index.php?page=documents&error=' . urlencode('請選擇 ZIP 檔案'));
    exit;
}

$uploadedFile = $_FILES['zipfile']['tmp_name'];
$originalName = $_FILES['zipfile']['name'];
$uploadError = $_FILES['zipfile']['error'];

// 檢查上傳錯誤
if ($uploadError !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => '檔案太大，超過伺服器限制',
        UPLOAD_ERR_FORM_SIZE => '檔案太大，超過表單限制',
        UPLOAD_ERR_PARTIAL => '檔案只上傳部分內容',
        UPLOAD_ERR_NO_FILE => '未選擇檔案',
        UPLOAD_ERR_NO_TMP_DIR => '伺服器暫存目錄不存在',
        UPLOAD_ERR_CANT_WRITE => '伺服器無法寫入檔案',
    ];
    $msg = $errorMessages[$uploadError] ?? '上傳失敗 (錯誤碼: ' . $uploadError . ')';
    header('Location: index.php?page=documents&error=' . urlencode($msg));
    exit;
}

if (!$uploadedFile || !file_exists($uploadedFile)) {
    header('Location: index.php?page=documents&error=' . urlencode('上傳檔案不存在'));
    exit;
}

// Create temp extraction directory
$extractDir = sys_get_temp_dir() . '/doc_import_' . time();
if (!is_dir($extractDir)) {
    if (!mkdir($extractDir, 0755, true)) {
        header('Location: index.php?page=documents&error=' . urlencode('無法建立暫存目錄'));
        exit;
    }
}

// Extract ZIP
$zip = new PureZipExtract();
if (!$zip->open($uploadedFile)) {
    header('Location: index.php?page=documents&error=' . urlencode('無法解壓 ZIP 檔案，格式可能不正確'));
    exit;
}

$zip->extractTo($extractDir);
$extractedFiles = $zip->getFiles();

if (empty($extractedFiles)) {
    header('Location: index.php?page=documents&error=' . urlencode('ZIP 檔案是空的'));
    exit;
}

// Move files to uploads directory
$uploadDir = 'uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$importedCount = 0;
$pdo = getConnection();

foreach ($extractedFiles as $fileName) {
    // 跳過目錄
    if (substr($fileName, -1) === '/') {
        continue;
    }

    $sourcePath = $extractDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileName);

    if (!file_exists($sourcePath)) {
        continue;
    }

    // 取得基本檔名 (去除路徑)
    $baseName = basename($fileName);

    // Skip cover files
    if (strpos($baseName, 'cover_') === 0) {
        continue;
    }

    $targetPath = $uploadDir . '/' . $baseName;

    // Avoid overwriting - add timestamp if exists
    if (file_exists($targetPath)) {
        $pathInfo = pathinfo($baseName);
        $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $targetPath = $uploadDir . '/' . $pathInfo['filename'] . '_' . time() . $ext;
    }

    if (copy($sourcePath, $targetPath)) {
        // Create database entry
        $name = pathinfo($baseName, PATHINFO_FILENAME);

        try {
            $stmt = $pdo->prepare("INSERT INTO commondocument (id, name, file, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([generateUUID(), $name, $targetPath]);
            $importedCount++;
        } catch (Exception $e) {
            $errors[] = "資料庫錯誤: " . $e->getMessage();
        }
    } else {
        $errors[] = "無法複製檔案: " . $baseName;
    }
}

// Cleanup temp directory
$tempFiles = glob("$extractDir/*");
if ($tempFiles) {
    foreach ($tempFiles as $tempFile) {
        if (is_file($tempFile)) {
            unlink($tempFile);
        }
    }
}
if (is_dir($extractDir)) {
    rmdir($extractDir);
}

if ($importedCount > 0) {
    header('Location: index.php?page=documents&success=' . urlencode("成功匯入 {$importedCount} 個文件"));
} else {
    $errorMsg = empty($errors) ? 'ZIP 中沒有可匯入的文件' : implode('; ', $errors);
    header('Location: index.php?page=documents&error=' . urlencode($errorMsg));
}
