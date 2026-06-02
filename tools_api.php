<?php
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$pdo = getConnection();
ensureToolSettingsTable($pdo);
ensureToolPriceHistory($pdo);

function ensureToolSettingsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id VARCHAR(36) PRIMARY KEY,
        user_id VARCHAR(36) NULL,
        setting_key VARCHAR(50) NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_setting (user_id, setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ensureToolPriceHistory(PDO $pdo): void
{
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

function readJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];
    return is_array($data) ? $data : [];
}

function fetchUrlForTool(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'header' => "User-Agent: Mozilla/5.0 FengbroTools/1.0\r\nAccept-Language: zh-TW,zh;q=0.9,en;q=0.8\r\n",
        ],
    ]);
    $html = @file_get_contents($url, false, $context);
    return is_string($html) ? $html : '';
}

function fetchJsonForTool(string $url, array $headers = []): ?array
{
    $headerLines = array_merge([
        'User-Agent: Mozilla/5.0 FengbroTools/1.0',
        'Accept: application/json',
        'Accept-Language: zh-TW,zh;q=0.9,en;q=0.8',
    ], $headers);
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headerLines) . "\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if (!is_string($body) || trim($body) === '') {
        return null;
    }
    $json = json_decode($body, true);
    return is_array($json) ? $json : null;
}

function extractPrices(string $html): array
{
    $prices = [];
    if (preg_match_all('/(?:NT\\$|TWD|\\$|價格|售價)[^0-9]{0,12}([0-9]{2,3}(?:,[0-9]{3})+|[0-9]{3,8})/u', $html, $matches)) {
        foreach ($matches[1] as $raw) {
            $price = normalizeToolPrice($raw);
            if ($price !== null) {
                $prices[] = $price;
            }
        }
    }
    $prices = array_values(array_unique($prices));
    sort($prices);
    return $prices;
}

function toolGetSetting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND user_id IS NULL LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : (string) $value;
    } catch (Throwable $e) {
        return $default;
    }
}

function toolSettingOrEnv(PDO $pdo, string $key, string $default = ''): string
{
    $value = trim(toolGetSetting($pdo, $key));
    if ($value !== '') {
        return $value;
    }
    $env = getenv($key);
    return is_string($env) && trim($env) !== '' ? trim($env) : $default;
}

function firstToolSettingOrEnv(PDO $pdo, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        $value = toolSettingOrEnv($pdo, $key);
        if ($value !== '') {
            return $value;
        }
    }
    return $default;
}

function normalizeToolPrice($value): ?int
{
    if (is_int($value) || is_float($value)) {
        $price = (int) round($value);
    } else {
        $raw = preg_replace('/[^\d.]/', '', (string) $value);
        if ($raw === '') {
            return null;
        }
        $price = (int) round((float) $raw);
    }
    return ($price >= 10 && $price <= 5000000) ? $price : null;
}

function firstStringValue(array $item, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($item[$key]) && is_scalar($item[$key]) && trim((string) $item[$key]) !== '') {
            return trim((string) $item[$key]);
        }
    }
    return '';
}

function collectBigGoApiItems($data, array &$items, int $limit = 12): void
{
    if (count($items) >= $limit || !is_array($data)) {
        return;
    }

    $price = null;
    foreach (['price', 'current_price', 'sale_price', 'min_price', 'amount', 'value'] as $key) {
        if (array_key_exists($key, $data)) {
            $price = normalizeToolPrice($data[$key]);
            if ($price !== null) {
                break;
            }
        }
    }

    if ($price !== null) {
        $title = firstStringValue($data, ['title', 'name', 'product_name', 'keyword']);
        $items[] = [
            'title' => $title !== '' ? $title : 'BigGo API item',
            'price' => $price,
            'url' => firstStringValue($data, ['url', 'link', 'product_url', 'redirect_url']),
            'source' => firstStringValue($data, ['source', 'shop', 'store', 'merchant']) ?: 'BigGo API',
        ];
    }

    foreach ($data as $value) {
        if (is_array($value)) {
            collectBigGoApiItems($value, $items, $limit);
            if (count($items) >= $limit) {
                return;
            }
        }
    }
}

function callBigGoApi(PDO $pdo, string $query): array
{
    $apiKey = firstToolSettingOrEnv($pdo, ['BIGGO_API_KEY', 'BIGGO_MCP_SERVER_CLIENT_ID']);
    $secret = firstToolSettingOrEnv($pdo, ['BIGGO_API_SECRET_KEY', 'BIGGO_API_SECRET', 'BIGGO_MCP_SERVER_CLIENT_SECRET']);
    $region = firstToolSettingOrEnv($pdo, ['BIGGO_API_REGION', 'BIGGO_MCP_SERVER_REGION'], 'tw');
    $endpoint = toolSettingOrEnv($pdo, 'BIGGO_API_ENDPOINT', 'https://api.biggo.com.tw/v1/search');

    if ($apiKey === '' || $endpoint === '') {
        return ['ok' => false, 'items' => [], 'notice' => 'BIGGO_API_KEY / BIGGO_MCP_SERVER_CLIENT_ID 未設定，已改用 BigGo 搜尋頁連結。'];
    }

    $url = str_contains($endpoint, '{query}')
        ? str_replace('{query}', rawurlencode($query), $endpoint)
        : $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . 'q=' . rawurlencode($query);
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'X-API-Key: ' . $apiKey,
        'X-BigGo-Client-Id: ' . $apiKey,
        'X-BigGo-Region: ' . $region,
    ];
    if ($secret !== '') {
        $headers[] = 'X-API-Secret: ' . $secret;
        $headers[] = 'X-BigGo-Client-Secret: ' . $secret;
    }

    $json = fetchJsonForTool($url, $headers);
    if (!$json) {
        return ['ok' => false, 'items' => [], 'notice' => 'BigGo API 無回應或不是 JSON，已改用 BigGo 搜尋頁連結。'];
    }

    $items = [];
    collectBigGoApiItems($json, $items);
    if (!$items) {
        return ['ok' => false, 'items' => [], 'notice' => 'BigGo API 回傳資料中沒有可辨識價格，已保留外部查詢連結。'];
    }

    return ['ok' => true, 'items' => $items, 'notice' => '已使用 BigGo API / MCP 認證取得價格資料。'];
}

function saveSnapshot(PDO $pdo, array $snapshot): void
{
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

function loadHistory(PDO $pdo, string $toolType, string $queryText): array
{
    $stmt = $pdo->prepare("SELECT * FROM tool_price_history WHERE tool_type = ? AND query_text = ? ORDER BY created_at ASC LIMIT 30");
    $stmt->execute([$toolType, $queryText]);
    return $stmt->fetchAll();
}

if ($action === 'price_lookup') {
    $input = readJsonInput();
    $query = trim((string) ($input['query'] ?? ''));
    if ($query === '') {
        jsonResponse(['success' => false, 'error' => '請輸入商品關鍵字或網址。'], 400);
    }

    $searchUrl = 'https://biggo.com.tw/s/' . rawurlencode($query) . '/';
    $apiResult = callBigGoApi($pdo, $query);
    $items = $apiResult['items'] ?? [];
    $prices = array_values(array_filter(array_map(fn($item) => normalizeToolPrice($item['price'] ?? null), $items)));
    $title = !empty($items[0]['title']) ? $items[0]['title'] : $query;
    $notice = $apiResult['notice'] ?? '';
    $source = !empty($prices) ? 'BigGo API' : 'BigGo';

    if (!$prices) {
        $html = fetchUrlForTool($searchUrl);
        $prices = $html ? extractPrices($html) : [];
        if ($html && preg_match('/<title[^>]*>(.*?)<\/title>/isu', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8')) ?: $query;
        }
        $notice = $prices
            ? trim(($notice ? $notice . ' ' : '') . '已由 BigGo 搜尋頁保守解析價格。')
            : ($notice ?: 'BigGo API 未設定或無法解析價格，已保留查詢快照與外部連結。');
    }

    $snapshot = [
        'tool_type' => 'biggo',
        'query_text' => $query,
        'title' => $title,
        'source' => $source,
        'current_price' => $prices[0] ?? null,
        'high_price' => $prices ? max($prices) : null,
        'low_price' => $prices ? min($prices) : null,
        'result_url' => $searchUrl,
        'notice' => $notice,
    ];
    saveSnapshot($pdo, $snapshot);
    jsonResponse([
        'success' => true,
        'snapshot' => $snapshot,
        'items' => $items,
        'history' => loadHistory($pdo, 'biggo', $query),
    ]);
}

if ($action === 'phone_lookup') {
    $input = readJsonInput();
    $query = trim((string) ($input['query'] ?? 'Samsung S26'));
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

jsonResponse(['success' => false, 'error' => '不支援的工具動作。'], 400);
