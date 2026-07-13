<?php
/**
 * Notification self-diagnostic endpoint (read-only).
 *
 * HTTP: GET/POST notif_diag.php
 * CLI:  php notif_diag.php
 *
 * Does not send email or push; only reports configuration, files, and due-date candidates.
 */

date_default_timezone_set('Asia/Taipei');
$isCli = PHP_SAPI === 'cli';

if ($isCli) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notification_helpers.php';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

try {
    $pdo = getConnection();
    $result = notifRunSelfCheck($pdo);
} catch (Throwable $e) {
    $result = [
        'success' => false,
        'overall' => 'fail',
        'checked_at' => date('c'),
        'timezone' => date_default_timezone_get(),
        'summary' => ['ok' => 0, 'warn' => 0, 'fail' => 1, 'total' => 1],
        'checks' => [[
            'id' => 'fatal',
            'channel' => 'runtime',
            'label' => '自我檢測致命錯誤',
            'ok' => false,
            'level' => 'fail',
            'detail' => $e->getMessage(),
        ]],
        'due' => new stdClass(),
        'client_hints' => new stdClass(),
        'error' => $e->getMessage(),
    ];
}

$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($isCli ? JSON_PRETTY_PRINT : 0);
echo json_encode($result, $flags) . ($isCli ? "\n" : '');
