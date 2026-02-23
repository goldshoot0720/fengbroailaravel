<?php
/**
 * push_subscribe.php — Web Push 訂閱管理 API
 * POST  {endpoint, keys:{auth, p256dh}} → 新增或更新訂閱
 * DELETE {endpoint}                      → 刪除訂閱
 */

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true);

if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getConnection();

// ── 建立資料表（若不存在）────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint TEXT NOT NULL,
    auth VARCHAR(255) NOT NULL,
    p256dh VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_endpoint (endpoint(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── POST：新增訂閱 ───────────────────────────────────────────────────────────
if ($method === 'POST') {
    $endpoint = $body['endpoint'] ?? '';
    $auth     = $body['keys']['auth'] ?? '';
    $p256dh   = $body['keys']['p256dh'] ?? '';

    if (!$endpoint || !$auth || !$p256dh) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO push_subscriptions (endpoint, auth, p256dh) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE auth = VALUES(auth), p256dh = VALUES(p256dh)"
        );
        $stmt->execute([$endpoint, $auth, $p256dh]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ── DELETE：移除訂閱 ─────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $endpoint = $body['endpoint'] ?? '';

    if (!$endpoint) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing endpoint'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
        $stmt->execute([$endpoint]);
        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
