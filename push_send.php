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
 * Cron 範例（每天 09:00 發送）：
 *   0 9 * * * php /path/to/push_send.php >> /var/log/push_send.log 2>&1
 */

// ── 環境初始化 ───────────────────────────────────────────────────────────────
$isCli = (PHP_SAPI === 'cli');

// CLI 模式：模擬 $_SERVER 讓 getConnection() 正常運作
if ($isCli) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/push/WebPushHelper.php';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

// ── 判斷動作 ─────────────────────────────────────────────────────────────────
$action = '';
if ($isCli) {
    $action = $argv[1] ?? '';
} else {
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
}

// ── 動作：初始化 VAPID 金鑰 ──────────────────────────────────────────────────
if ($action === 'init_vapid') {
    try {
        // 若已存在則不覆蓋（可加 ?force=1 強制重新產生）
        $force = $isCli ? in_array('--force', $argv) : (($_GET['force'] ?? '') === '1');
        $existingKey = WebPushHelper::getVapidPublicKey();
        if ($existingKey && !$force) {
            $result = [
                'success'    => true,
                'message'    => 'VAPID 金鑰已存在，略過（加 ?force=1 強制重新產生）',
                'publicKey'  => $existingKey,
            ];
        } else {
            $keys = WebPushHelper::generateVapidKeys();
            WebPushHelper::saveSetting('vapid_public_key',  $keys['publicKey']);
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

    if ($isCli) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    } else {
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ── 動作：發送到期提醒推播 ───────────────────────────────────────────────────

$pdo = getConnection();

// 確認 push_subscriptions 表存在
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        endpoint TEXT NOT NULL,
        auth VARCHAR(255) NOT NULL,
        p256dh VARCHAR(500) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_endpoint (endpoint(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // 表已存在時靜默略過
}

// 查詢 3 天內到期訂閱（continue=1）
$expiringRows = $pdo->query(
    "SELECT name, nextdate FROM subscription
     WHERE `continue` = 1
       AND nextdate IS NOT NULL
       AND nextdate >= CURDATE()
       AND nextdate <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
     ORDER BY nextdate ASC"
)->fetchAll();

if (empty($expiringRows)) {
    $result = ['success' => true, 'message' => '無到期訂閱，不發送推播', 'sent' => 0, 'failed' => 0, 'details' => []];
    if ($isCli) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    } else {
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 組合通知內容
$notifLines = [];
foreach ($expiringRows as $row) {
    $days = (int) round((strtotime($row['nextdate']) - strtotime(date('Y-m-d'))) / 86400);
    $daysText = $days === 0 ? '今天到期' : ($days === 1 ? '明天到期' : $days . ' 天後到期');
    $notifLines[] = $row['name'] . '（' . $daysText . '）';
}

$title = '訂閱到期提醒 — 鋒兄AI';
$body  = implode('、', $notifLines);

// 讀取所有推播訂閱
$subscriptions = $pdo->query("SELECT endpoint, auth, p256dh FROM push_subscriptions")->fetchAll();

if (empty($subscriptions)) {
    $result = ['success' => true, 'message' => '無推播訂閱裝置', 'sent' => 0, 'failed' => 0, 'details' => []];
    if ($isCli) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    } else {
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 逐一發送
$sent    = 0;
$failed  = 0;
$details = [];

foreach ($subscriptions as $sub) {
    $subArray = [
        'endpoint' => $sub['endpoint'],
        'keys'     => [
            'auth'   => $sub['auth'],
            'p256dh' => $sub['p256dh'],
        ],
    ];

    $r = WebPushHelper::sendNotification($subArray, $title, $body);

    if ($r['success']) {
        $sent++;
    } else {
        $failed++;
        // 410 Gone：訂閱已失效，自動清除
        if ($r['http_code'] === 410) {
            $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")
                ->execute([$sub['endpoint']]);
        }
    }

    $details[] = [
        'endpoint_preview' => substr($sub['endpoint'], 0, 60) . '...',
        'success'          => $r['success'],
        'http_code'        => $r['http_code'],
        'error'            => $r['error'],
    ];
}

$result = [
    'success' => true,
    'sent'    => $sent,
    'failed'  => $failed,
    'details' => $details,
];

if ($isCli) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} else {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
