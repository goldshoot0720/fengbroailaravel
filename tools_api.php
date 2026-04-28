<?php
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$pdo = getConnection();
ensureToolPriceHistory($pdo);

function ensureToolPriceHistory(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tool_price_history (
        id VARCHAR(36) PRIMARY KEY,
        tool_type VARCHAR(30) NOT NULL,
        query_text VARCHAR(500) NOT NULL,
        title VARCHAR(500),
        source VARCHAR(100),
        current_price INT NULL,
        high_price INT NULL,
        low_price INT NULL,
        result_url VARCHAR(1000),
        notice TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tool_query (tool_type, query_text(191), created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function readJsonInput() {
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];
    return is_array($data) ? $data : [];
}

function fetchUrlForTool($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'header' => "User-Agent: Mozilla/5.0 FengbroTools/1.0\r\nAccept-Language: zh-TW,zh;q=0.9,en;q=0.8\r\n"
        ]
    ]);
    $html = @file_get_contents($url, false, $context);
    return is_string($html) ? $html : '';
}

function extractPrices($html) {
    $prices = [];
    if (preg_match_all('/(?:NT\\$|\\$|售價|價格)[^0-9]{0,12}([0-9]{2,3}(?:,[0-9]{3})+|[0-9]{4,7})/u', $html, $matches)) {
        foreach ($matches[1] as $raw) {
            $price = (int) str_replace(',', '', $raw);
            if ($price >= 10 && $price <= 500000) {
                $prices[] = $price;
            }
        }
    }
    $prices = array_values(array_unique($prices));
    sort($prices);
    return $prices;
}

function saveSnapshot(PDO $pdo, array $snapshot) {
    $stmt = $pdo->prepare("INSERT INTO tool_price_history
        (id, tool_type, query_text, title, source, current_price, high_price, low_price, result_url, notice)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        generateUUID(),
        $snapshot['tool_type'],
        $snapshot['query_text'],
        $snapshot['title'] ?? null,
        $snapshot['source'] ?? null,
        $snapshot['current_price'] ?? null,
        $snapshot['high_price'] ?? null,
        $snapshot['low_price'] ?? null,
        $snapshot['result_url'] ?? null,
        $snapshot['notice'] ?? null,
    ]);
}

function loadHistory(PDO $pdo, $toolType, $queryText) {
    $stmt = $pdo->prepare("SELECT * FROM tool_price_history WHERE tool_type = ? AND query_text = ? ORDER BY created_at ASC LIMIT 30");
    $stmt->execute([$toolType, $queryText]);
    return $stmt->fetchAll();
}

if ($action === 'price_lookup') {
    $input = readJsonInput();
    $query = trim($input['query'] ?? '');
    if ($query === '') {
        jsonResponse(['success' => false, 'error' => '請輸入商品關鍵字或網址'], 400);
    }

    $searchUrl = 'https://biggo.com.tw/s/' . rawurlencode($query) . '/';
    $html = fetchUrlForTool($searchUrl);
    $prices = $html ? extractPrices($html) : [];
    $title = $query;
    $notice = '';

    if ($html && preg_match('/<title[^>]*>(.*?)<\/title>/isu', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8')) ?: $query;
    }

    if (empty($prices)) {
        $notice = 'PHP 伺服器無法穩定解析 BigGo 價格，已保留查詢快照與外部連結。';
    }

    $snapshot = [
        'tool_type' => 'biggo',
        'query_text' => $query,
        'title' => $title,
        'source' => 'BigGo',
        'current_price' => $prices[0] ?? null,
        'high_price' => !empty($prices) ? max($prices) : null,
        'low_price' => !empty($prices) ? min($prices) : null,
        'result_url' => $searchUrl,
        'notice' => $notice,
    ];
    saveSnapshot($pdo, $snapshot);
    jsonResponse(['success' => true, 'snapshot' => $snapshot, 'history' => loadHistory($pdo, 'biggo', $query)]);
}

if ($action === 'phone_lookup') {
    $input = readJsonInput();
    $query = trim($input['query'] ?? 'Samsung S26');
    $targets = [
        '地標網通' => 'https://www.google.com/search?q=' . rawurlencode('site:landtop.com.tw ' . $query),
        '傑昇通信' => 'https://www.google.com/search?q=' . rawurlencode('site:jyes.com.tw ' . $query),
    ];
    $snapshot = [
        'tool_type' => 'phone',
        'query_text' => $query,
        'title' => $query . ' 手機比價',
        'source' => 'Google site search',
        'result_url' => reset($targets),
        'notice' => 'PHP 版使用站內搜尋保守整合；若要完整自動比價，需要穩定可用的通路 API 或允許爬取。',
    ];
    saveSnapshot($pdo, $snapshot);
    jsonResponse(['success' => true, 'snapshot' => $snapshot, 'targets' => $targets, 'history' => loadHistory($pdo, 'phone', $query)]);
}

jsonResponse(['success' => false, 'error' => '無效的工具操作'], 400);
