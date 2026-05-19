<?php
/**
 * Resend email notifications.
 *
 * CLI:
 *   php resend_notify.php
 *
 * Cron example:
 *   CRON_TZ=Asia/Taipei
 *   0 9 * * * php /path/to/resend_notify.php >> /var/log/fengbro_resend.log 2>&1
 */

date_default_timezone_set('Asia/Taipei');
$isCli = PHP_SAPI === 'cli';
if ($isCli) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/resend_notifications.php';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    $pdo = getConnection();
    $result = fengbroResendRunDueNotifications($pdo);
} catch (Throwable $e) {
    $result = [
        'success' => false,
        'sent' => 0,
        'failed' => 0,
        'skipped' => 0,
        'error' => $e->getMessage(),
        'details' => [],
    ];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . ($isCli ? "\n" : '');
