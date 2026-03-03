<?php
ob_start();
ini_set('memory_limit', '512M');
ini_set('display_errors', 0);
set_time_limit(300);

function outputJson($data)
{
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once 'includes/functions.php';
} catch (Exception $e) {
    outputJson(['success' => false, 'error' => '載入失敗: ' . $e->getMessage()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    outputJson(['success' => false, 'error' => '請使用 POST 方法']);
}

// 優先從 URL 查詢參數取得 type（JS 用 ?type=xxx 傳遞，較可靠）
// 再 fallback 到 POST body
$type = trim($_GET['type'] ?? $_POST['type'] ?? '');
// Normalize plural forms (e.g. 'documents' -> 'document')
$type = rtrim($type, 's'); // 'documents' -> 'document', etc.

$allowedTypes = ['image', 'music', 'document', 'video', 'podcast', 'article', 'note'];

// 延遲驗證 type（不在此處立即退出），先處理 tempFile/file，最後再驗證

// ===== 支援 tempFile（分段上傳後組裝）或直接 file 上傳 =====
$tempFileFromChunk = $_POST['tempFile'] ?? '';
$tempFile = '';
$isChunkedTemp = false;

if ($tempFileFromChunk) {
    // 安全性驗證：必須在 uploads/temp/ 目錄下
    $realTemp = realpath($tempFileFromChunk);
    $uploadsTemp = realpath('uploads/temp');
    if ($realTemp && $uploadsTemp && strpos($realTemp, $uploadsTemp) === 0 && file_exists($realTemp)) {
        $tempFile = $tempFileFromChunk;
        $isChunkedTemp = true; // 已組裝，不需再搬移；也不在此刪除
    } else {
        outputJson(['success' => false, 'error' => '暫存檔案路徑不安全或不存在']);
    }
} elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    // 直接上傳 → 搬到 uploads/temp/
    $tempDir = 'uploads/temp/';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    $tempFile = $tempDir . 'zip_' . uniqid() . '.zip';
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $tempFile)) {
        outputJson(['success' => false, 'error' => '無法儲存暫存檔案']);
    }
} else {
    $errCode = $_FILES['file']['error'] ?? -1;
    $errMsgs = [
        UPLOAD_ERR_INI_SIZE => '檔案太大，超過 upload_max_filesize=' . ini_get('upload_max_filesize'),
        UPLOAD_ERR_FORM_SIZE => '檔案太大，超過表單限制',
        UPLOAD_ERR_PARTIAL => '上傳不完整，請重試',
        UPLOAD_ERR_NO_FILE => '未選擇檔案',
        UPLOAD_ERR_NO_TMP_DIR => '伺服器暫存目錄不存在',
        UPLOAD_ERR_CANT_WRITE => '伺服器無法寫入暫存目錄',
    ];
    outputJson(['success' => false, 'error' => $errMsgs[$errCode] ?? "上傳失敗 (code=$errCode, post_max_size=" . ini_get('post_max_size') . ")"]);
}

// ===== 驗證 type（延遲到 file 解析後，方便 debug）=====
if (!in_array($type, $allowedTypes)) {
    outputJson([
        'success' => false,
        'error' => '無效的類型: "' . $type . '" (允許: ' . implode(', ', $allowedTypes) . ')',
        'debug' => [
            'post_keys' => array_keys($_POST),
            'type_raw' => $_POST['type'] ?? '(未傳入)',
            'tempFile' => $_POST['tempFile'] ?? '(未傳入)',
        ]
    ]);
}

// Use ZipArchive (preferred: streams from disk, no RAM load)
$zipEntries = [];
$openOk = false;

if (class_exists('ZipArchive')) {
    $za = new ZipArchive();
    $openResult = $za->open($tempFile);
    if ($openResult === true) {
        for ($i = 0; $i < $za->numFiles; $i++) {
            $zipEntries[] = $za->getNameIndex($i);
        }
        $za->close();
        $openOk = true;
    }
}

// Fallback to PureZip only if ZipArchive not available / failed
if (!$openOk) {
    if (!file_exists('includes/PureZip.php')) {
        @unlink($tempFile);
        outputJson(['success' => false, 'error' => '無法開啟 ZIP 檔案']);
    }
    require_once 'includes/PureZip.php';
    $pz = new PureZipExtract();
    if (!$pz->open($tempFile)) {
        @unlink($tempFile);
        outputJson(['success' => false, 'error' => '無法開啟 ZIP 檔案']);
    }
    $zipEntries = $pz->getFiles();
}

// Define valid extensions per type
$validExtensions = [
    'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
    'music' => ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac', 'wma'],
    'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php', 'py', 'sql', 'csv', 'zip', 'rar', 'mp3', 'mp4', 'wav', 'jpg', 'jpeg', 'png', 'gif', 'webp'],
    'video' => ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v'],
    'podcast' => ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac', 'wma', 'mp4', 'webm', 'mkv', 'avi', 'mov'],
    'article' => ['csv', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md', 'mp3', 'wav', 'mp4', 'webm', 'mov', 'zip', 'rar'],
    'note' => ['csv', 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'md', 'mp3', 'mp4', 'zip'],
];

$exts = $validExtensions[$type] ?? [];
$files = [];
$validCount = 0;

foreach ($zipEntries as $fileName) {
    // Skip directories
    if (substr($fileName, -1) === '/')
        continue;
    $baseName = basename($fileName);
    // Skip hidden / macOS metadata / cover files
    if (strpos($baseName, '.') === 0)
        continue;
    if (strpos($baseName, 'cover_') === 0)
        continue;
    if (strpos($fileName, '__MACOSX') !== false)
        continue;

    $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
    $valid = empty($exts) || in_array($ext, $exts);
    if ($valid)
        $validCount++;

    $files[] = ['name' => $baseName, 'ext' => $ext, 'valid' => $valid];
}

outputJson([
    'success' => true,
    'files' => $files,
    'totalFiles' => count($files),
    'validFiles' => $validCount,
    'tempFile' => $tempFile
]);
