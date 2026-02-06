<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => '請使用 POST 方法']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$tempFile = $input['tempFile'] ?? '';

// Validate the path is within uploads/temp/
if (empty($tempFile) || strpos(realpath(dirname($tempFile)), realpath('uploads/temp')) !== 0) {
    echo json_encode(['error' => '無效的檔案路徑']);
    exit;
}

if (file_exists($tempFile)) {
    @unlink($tempFile);
}

echo json_encode(['success' => true]);
