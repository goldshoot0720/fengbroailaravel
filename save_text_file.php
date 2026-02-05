<?php
require_once 'includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '請使用 POST 方法']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['path']) || !isset($input['content'])) {
    echo json_encode(['success' => false, 'error' => '缺少必要參數']);
    exit;
}

$filePath = $input['path'];
$content = $input['content'];

// 安全檢查 - 只允許編輯 uploads 目錄下的檔案
$realPath = realpath($filePath);
$uploadsPath = realpath('uploads');

// 檢查檔案是否在 uploads 目錄下
if ($realPath === false) {
    // 新檔案或路徑不存在
    if (strpos($filePath, 'uploads/') !== 0) {
        echo json_encode(['success' => false, 'error' => '只能編輯 uploads 目錄下的檔案']);
        exit;
    }
} else {
    if (strpos($realPath, $uploadsPath) !== 0) {
        echo json_encode(['success' => false, 'error' => '只能編輯 uploads 目錄下的檔案']);
        exit;
    }
}

// 檢查檔案類型 - 只允許文字檔案
$allowedExtensions = ['txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php', 'py', 'sql', 'csv', 'log'];
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    echo json_encode(['success' => false, 'error' => '不支援此檔案類型的編輯']);
    exit;
}

// 儲存檔案
if (file_put_contents($filePath, $content) !== false) {
    echo json_encode(['success' => true, 'message' => '儲存成功']);
} else {
    echo json_encode(['success' => false, 'error' => '無法寫入檔案']);
}
