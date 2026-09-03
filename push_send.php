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

// ── 動作：發送到期提醒推播（五模組彙總，對齊 Appwrite aggregatePushSummary）────

$pdo = getConnection();
notifEnsurePushSubscriptionsTable($pdo);

$subs = notifGetExpiringSubscriptions($pdo, 3);
$foods = notifGetExpiringFood($pdo, 7);
$trials = notifGetExpiringTrialPurchases($pdo, 3);
$quotas = notifGetExpiringQuotas($pdo);
$shoppings = notifGetExpiringShoppingItems($pdo, 3);

$totalItems = count($subs) + count($foods) + count($trials) + count($quotas) + count($shoppings);

if ($totalItems === 0) {
    pushSendOutput([
        'success' => true,
        'message' => '無到期項目，不發送推播',
        'sent' => 0,
        'failed' => 0,
        'details' => [],
    ], $isCli);
    exit;
}

function pushItemLine(string $name, $dateValue, string $unit = '到期'): string
{
    $days = notifDayDelta((string) $dateValue);
    if ($days === null) {
        return $name;
    }
    if ($days === 0) {
        return $name . ' 今天' . $unit . '！';
    }
    return $name . ' ' . $days . ' 天後' . $unit;
}

$lines = [];
foreach ($subs as $row) {
    $lines[] = pushItemLine($row['name'] ?? '未命名訂閱', $row['nextdate'] ?? '');
}
foreach ($foods as $row) {
    $lines[] = pushItemLine($row['name'] ?? '未命名食品', $row['todate'] ?? '', '過期');
}
foreach ($trials as $row) {
    $lines[] = pushItemLine($row['name'] ?? '未命名試用', $row['eventDate'] ?? '');
}
foreach ($quotas as $row) {
    $lines[] = pushItemLine(
        ($row['name'] ?? '未命名額度') . (!empty($row['label']) ? '（' . $row['label'] . '）' : ''),
        $row['expiryDate'] ?? ''
    );
}
foreach ($shoppings as $row) {
    $lines[] = pushItemLine($row['name'] ?? '未命名購物', $row['plannedDate'] ?? '');
}

$counts = array_values(array_filter([
    count($subs) ? count($subs) . ' 訂閱' : null,
    count($foods) ? count($foods) . ' 食品' : null,
    count($trials) ? count($trials) . ' 試用/首購' : null,
    count($quotas) ? count($quotas) . ' 額度' : null,
    count($shoppings) ? count($shoppings) . ' 購物' : null,
]));
$title = '⏰ 鋒兄到期提醒';
$body = $totalItems . ' 個項目即將到期（' . implode(' + ', $counts) . "）\n" . implode("\n", array_slice($lines, 0, 8));
if (count($lines) > 8) {
    $body .= "\n…還有 " . (count($lines) - 8) . ' 項';
}

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
