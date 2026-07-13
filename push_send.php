<?php
/**
 * push_send.php — Web Push 推播發送腳本
 *
 * HTTP 模式：
 *   GET  push_send.php?action=init_vapid  → 產生並儲存 VAPID 金鑰
 *   POST push_send.php                    → 查詢到期訂閱並發送推播
 *
 * CLI 模式：
 *   php push_send.php              → 發送到期提醒推播
 *   php push_send.php init_vapid   → 產生 VAPID 金鑰
 *
 * Cron 範例（每天台灣時間 09:00 發送）：
 *   CRON_TZ=Asia/Taipei
 *   0 9 * * * php /path/to/push_send.php >> /var/log/push_send.log 2>&1
 */

date_default_timezone_set('Asia/Taipei');
$isCli = (PHP_SAPI === 'cli');

if ($isCli) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notification_helpers.php';
require_once __DIR__ . '/push/WebPushHelper.php';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

function pushSendOutput(array $result, bool $isCli): void
{
    $flags = JSON_UNESCAPED_UNICODE | ($isCli ? JSON_PRETTY_PRINT : 0);
    echo json_encode($result, $flags) . ($isCli ? "\n" : '');
}

$action = '';
if ($isCli) {
    $action = $argv[1] ?? '';
} else {
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
}

// ── 動作：初始化 VAPID 金鑰 ──────────────────────────────────────────────────
if ($action === 'init_vapid') {
    try {
        $force = $isCli ? in_array('--force', $argv, true) : (($_GET['force'] ?? '') === '1');
        $existingKey = WebPushHelper::getVapidPublicKey();
        if ($existingKey && !$force) {
            $result = [
                'success'   => true,
                'message'   => 'VAPID 金鑰已存在，略過（加 ?force=1 強制重新產生）',
                'publicKey' => $existingKey,
            ];
        } else {
            $keys = WebPushHelper::generateVapidKeys();
            WebPushHelper::saveSetting('vapid_public_key', $keys['publicKey']);
            WebPushHelper::saveSetting('vapid_private_key', $keys['privateKey']);
            $result = [
                'success'   => true,
                'message'   => 'VAPID 金鑰已產生並儲存',
                'publicKey' => $keys['publicKey'],
            ];
        }
    } catch (Exception $e) {
        $result = ['success' => false, 'error' => $e->getMessage()];
    }

    pushSendOutput($result, $isCli);
    exit;
}

// ── 動作：發送到期提醒推播 ───────────────────────────────────────────────────

$pdo = getConnection();
notifEnsurePushSubscriptionsTable($pdo);

$expiringRows = notifGetExpiringSubscriptions($pdo, 3);

if (empty($expiringRows)) {
    pushSendOutput([
        'success' => true,
        'message' => '無到期訂閱，不發送推播',
        'sent' => 0,
        'failed' => 0,
        'details' => [],
    ], $isCli);
    exit;
}

$message = notifBuildSubscriptionPushMessage($expiringRows);
$title = $message['title'];
$body = $message['body'];

$subscriptions = notifGetPushSubscriptions($pdo);

if (empty($subscriptions)) {
    pushSendOutput([
        'success' => true,
        'message' => '無推播訂閱裝置',
        'sent' => 0,
        'failed' => 0,
        'details' => [],
    ], $isCli);
    exit;
}

$sent = 0;
$failed = 0;
$details = [];

foreach ($subscriptions as $sub) {
    $subArray = [
        'endpoint' => $sub['endpoint'],
        'keys' => [
            'auth' => $sub['auth'],
            'p256dh' => $sub['p256dh'],
        ],
    ];

    $r = WebPushHelper::sendNotification($subArray, $title, $body);

    if ($r['success']) {
        $sent++;
    } else {
        $failed++;
        if ((int) ($r['http_code'] ?? 0) === 410) {
            notifDeletePushSubscription($pdo, $sub['endpoint']);
        }
    }

    $details[] = [
        'endpoint_preview' => substr($sub['endpoint'], 0, 60) . '...',
        'success' => $r['success'],
        'http_code' => $r['http_code'],
        'error' => $r['error'],
    ];
}

pushSendOutput([
    'success' => true,
    'sent' => $sent,
    'failed' => $failed,
    'details' => $details,
], $isCli);
