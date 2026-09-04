<?php
/**
 * 選單備份／還原 API（對齊 Appwrite /lib/menuBackup）。
 *
 *   POST menu_backup.php?action=export&kind=csv|all   body: news_csv（選填）
 *   POST menu_backup.php?action=import&kind=csv|all   file upload
 */
require_once 'includes/functions.php';
require_once 'includes/menu_backup.php';

@set_time_limit(0);
@ini_set('memory_limit', '1024M');

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
$kind = (string) ($_GET['kind'] ?? $_POST['kind'] ?? 'csv');
if (!in_array($kind, ['csv', 'all'], true)) {
    $kind = 'csv';
}

if ($action === 'export') {
    $newsCsv = (string) ($_POST['news_csv'] ?? '');
    fengbroMenuBackupExport($kind, $newsCsv);
}

if ($action === 'import') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => '請使用 POST'], 400);
    }
    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'error' => '請上傳 ZIP 或 CSV'], 400);
    }
    $path = (string) $_FILES['file']['tmp_name'];
    $name = (string) ($_FILES['file']['name'] ?? 'backup.zip');
    $run = fengbroMenuBackupImport($kind, $path, $name);
    jsonResponse([
        'success' => !empty($run['success']),
        'results' => $run['results'] ?? [],
        'summary' => fengbroMenuBackupSummarize($run['results'] ?? []),
        'newsSites' => $run['newsSites'] ?? null,
        'error' => empty($run['success']) ? ($run['results'][0]['message'] ?? '匯入失敗') : null,
    ]);
}

jsonResponse(['success' => false, 'error' => '無效動作'], 400);
