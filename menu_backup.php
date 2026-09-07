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
@ini_set('memory_limit', '2048M');

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
        $uploadError = $_FILES['file']['error'] ?? null;
        $message = match ($uploadError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '檔案超過伺服器允許的上傳大小上限',
            UPLOAD_ERR_PARTIAL => '檔案上傳中斷，請重新上傳',
            default => '請上傳 ZIP 或 CSV',
        };
        jsonResponse(['success' => false, 'error' => $message], 400);
    }
    ob_start();
    try {
        $path = (string) $_FILES['file']['tmp_name'];
        $name = (string) ($_FILES['file']['name'] ?? 'backup.zip');
        $run = fengbroMenuBackupImport($kind, $path, $name);
        ob_end_clean();
        jsonResponse([
            'success' => !empty($run['success']),
            'results' => $run['results'] ?? [],
            'summary' => fengbroMenuBackupSummarize($run['results'] ?? []),
            'newsSites' => $run['newsSites'] ?? null,
            'error' => empty($run['success']) ? ($run['results'][0]['message'] ?? '匯入失敗') : null,
        ]);
    } catch (Throwable $e) {
        ob_end_clean();
        jsonResponse(['success' => false, 'error' => '匯入發生錯誤：' . $e->getMessage()], 500);
    }
}

jsonResponse(['success' => false, 'error' => '無效動作'], 400);
