<?php
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(90);

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

    // 手機比價：商品級每日快照（對齊 Appwrite landtophistory）
    $pdo->exec("CREATE TABLE IF NOT EXISTS tool_phone_product_history (
        id VARCHAR(36) PRIMARY KEY,
        product_id VARCHAR(190) NOT NULL,
        brand VARCHAR(50),
        name VARCHAR(500) NOT NULL,
        source VARCHAR(50) NOT NULL,
        price INT NULL,
        source_url VARCHAR(1000),
        snapshot_day DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_product_day_source (product_id, snapshot_day, source),
        INDEX idx_product_day (product_id, snapshot_day)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function savePhoneProductSnapshots(PDO $pdo, array $products): int
{
    $stored = 0;
    $day = date('Y-m-d');
    $stmt = $pdo->prepare("INSERT INTO tool_phone_product_history
        (id, product_id, brand, name, source, price, source_url, snapshot_day)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            price = VALUES(price),
            source_url = VALUES(source_url),
            name = VALUES(name),
            brand = VALUES(brand)");

    foreach (array_slice($products, 0, 60) as $product) {
        $productId = trim((string) ($product['id'] ?? ''));
        $name = trim((string) ($product['name'] ?? ''));
        if ($productId === '' || $name === '') {
            continue;
        }
        $entries = [];
        if (isset($product['landtopPrice']) && is_int($product['landtopPrice'])) {
            $entries[] = [
                'source' => 'landtop',
                'price' => $product['landtopPrice'],
                'url' => (string) ($product['sourceUrl'] ?? ''),
            ];
        }
        if (isset($product['jyesPrice']) && is_int($product['jyesPrice'])) {
            $entries[] = [
                'source' => 'jyes',
                'price' => $product['jyesPrice'],
                'url' => (string) ($product['jyesUrl'] ?? ''),
            ];
        }
        foreach ($entries as $entry) {
            try {
                $stmt->execute([
                    generateUUID(),
                    $productId,
                    (string) ($product['brand'] ?? ''),
                    $name,
                    $entry['source'],
                    $entry['price'],
                    $entry['url'],
                    $day,
                ]);
                $stored++;
            } catch (Throwable $e) {
                // ignore single-row failures
            }
        }
    }
    return $stored;
}

function loadPhoneProductHistories(PDO $pdo, array $products): array
{
    $ids = array_values(array_filter(array_map(
        static fn($p) => trim((string) ($p['id'] ?? '')),
        $products
    )));
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT product_id, brand, name, source, price, source_url, snapshot_day
        FROM tool_phone_product_history
        WHERE product_id IN ($placeholders)
        ORDER BY snapshot_day ASC");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    $grouped = [];
    foreach ($rows as $row) {
        $pid = $row['product_id'];
        if (!isset($grouped[$pid])) {
            $grouped[$pid] = [
                'id' => $pid,
                'brand' => $row['brand'],
                'name' => $row['name'],
                'sourceUrl' => $row['source_url'],
                'points' => [],
            ];
        }
        $grouped[$pid]['points'][] = [
            'date' => $row['snapshot_day'],
            'source' => $row['source'],
            'price' => $row['price'] !== null ? (int) $row['price'] : null,
            'landtopPrice' => $row['source'] === 'landtop' && $row['price'] !== null ? (int) $row['price'] : null,
            'jyesPrice' => $row['source'] === 'jyes' && $row['price'] !== null ? (int) $row['price'] : null,
        ];
    }
    return array_values($grouped);
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

function fetchJsonResponseForTool(string $url, array $headers = []): array
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
    $status = 0;
    if (!empty($http_response_header) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    if (!is_string($body) || trim($body) === '') {
        return ['status' => $status, 'json' => null];
    }
    $json = json_decode($body, true);
    return ['status' => $status, 'json' => is_array($json) ? $json : null];
}

function fetchJsonForTool(string $url, array $headers = []): ?array
{
    $response = fetchJsonResponseForTool($url, $headers);
    return $response['json'];
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
        return ['ok' => false, 'items' => [], 'notice' => 'BigGo API/MCP 尚未設定，採用可行方案：保留查詢快照與 BigGo 搜尋連結，不再抓取容易被限流的頁面。'];
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

    $response = fetchJsonResponseForTool($url, $headers);
    $json = $response['json'];
    $status = (int) ($response['status'] ?? 0);
    if ($status === 429) {
        return ['ok' => false, 'items' => [], 'notice' => 'BigGo API 回傳 429，已採用可行方案：暫停自動抓價，只保留查詢快照與外部連結，避免連續請求造成限流。'];
    }
    if ($status >= 400) {
        return ['ok' => false, 'items' => [], 'notice' => 'BigGo API 回傳 HTTP ' . $status . '，已採用可行方案：保留查詢快照與外部連結。'];
    }
    if (!$json) {
        return ['ok' => false, 'items' => [], 'notice' => 'BigGo API 無回應或不是 JSON，已採用可行方案：保留查詢快照與外部連結。'];
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

if ($action === 'finance_history') {
    require_once __DIR__ . '/includes/fengbro_finance.php';
    $input = readJsonInput();
    $symbol = trim((string) ($input['symbol'] ?? $_GET['symbol'] ?? ''));
    $range = trim((string) ($input['range'] ?? $_GET['range'] ?? '1y'));
    if ($symbol === '') {
        jsonResponse(['success' => false, 'error' => '請提供 symbol'], 400);
    }
    $allowed = array_column(fengbroFinanceAllHistoryRanges(), 'key');
    if (!in_array($range, $allowed, true)) {
        $range = '1y';
    }
    $result = fengbroFinanceFetchSingleHistory($symbol, $range);
    jsonResponse([
        'success' => $result['error'] === '',
        'symbol' => $result['symbol'] ?? $symbol,
        'range' => $result['range'] ?? $range,
        'label' => $result['label'] ?? $range,
        'points' => $result['points'] ?? [],
        'error' => $result['error'] ?? '',
    ]);
}

if ($action === 'finance_resolve_name') {
    require_once __DIR__ . '/includes/fengbro_finance.php';
    $input = readJsonInput();
    $symbol = trim((string) ($input['symbol'] ?? $_GET['symbol'] ?? ''));
    $provider = trim((string) ($input['provider'] ?? $_GET['provider'] ?? 'yahoo'));
    $result = fengbroFinanceResolveName($symbol, $provider);
    jsonResponse([
        'success' => !empty($result['ok']),
        'name' => $result['name'] ?? null,
        'symbol' => $result['symbol'] ?? $symbol,
        'source' => $result['source'] ?? null,
        'error' => $result['error'] ?? null,
    ], !empty($result['ok']) ? 200 : 404);
}

if ($action === 'phone_history_import') {
    $input = readJsonInput();
    // Also accept multipart CSV
    $csvText = '';
    if (!empty($_FILES['csv']['tmp_name'])) {
        $csvText = (string) file_get_contents($_FILES['csv']['tmp_name']);
    } elseif (!empty($input['csv'])) {
        $csvText = (string) $input['csv'];
    } else {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '' && !str_starts_with(ltrim($raw), '{')) {
            $csvText = $raw;
        }
    }
    $csvText = preg_replace('/^\xEF\xBB\xBF/', '', $csvText) ?? $csvText;
    if (trim($csvText) === '') {
        jsonResponse(['success' => false, 'error' => '請提供 CSV'], 400);
    }
    ensureToolPriceHistory($pdo);
    $lines = preg_split('/\r\n|\r|\n/', $csvText) ?: [];
    $start = 0;
    if ($lines && preg_match('/productid|product_id|brand|name|snapshot/i', $lines[0])) {
        $start = 1;
    }
    $imported = 0;
    $stmt = $pdo->prepare("INSERT INTO tool_phone_product_history
        (id, product_id, brand, name, source, price, source_url, snapshot_day)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE price = VALUES(price), source_url = VALUES(source_url), name = VALUES(name), brand = VALUES(brand)");
    // table may not have unique key — try plain insert with dedupe day
    try {
        $pdo->exec("ALTER TABLE tool_phone_product_history ADD UNIQUE KEY uq_phone_day (product_id, source, snapshot_day)");
    } catch (Throwable $e) {
        // ignore if exists
    }
    for ($i = $start; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if ($line === '') {
            continue;
        }
        $cols = str_getcsv($line);
        // productId,brand,name,sourceUrl,landtopPrice,jyesPrice,snapshotDate,source
        $productId = trim((string) ($cols[0] ?? ''));
        $brand = trim((string) ($cols[1] ?? ''));
        $name = trim((string) ($cols[2] ?? ''));
        $sourceUrl = trim((string) ($cols[3] ?? ''));
        $landtop = $cols[4] ?? '';
        $jyes = $cols[5] ?? '';
        $day = trim((string) ($cols[6] ?? ''));
        if ($day !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $day, $dm)) {
            $day = $dm[0];
        } else {
            $day = date('Y-m-d');
        }
        if ($productId === '' && $name === '') {
            continue;
        }
        if ($productId === '') {
            $productId = substr(preg_replace('/[^a-z0-9]+/i', '-', strtolower($brand . '-' . $name)) ?? 'p', 0, 160);
        }
        $rowsToWrite = [];
        if ($landtop !== '' && is_numeric(str_replace(',', '', (string) $landtop))) {
            $rowsToWrite[] = ['landtop', (int) str_replace(',', '', (string) $landtop)];
        }
        if ($jyes !== '' && is_numeric(str_replace(',', '', (string) $jyes))) {
            $rowsToWrite[] = ['jyes', (int) str_replace(',', '', (string) $jyes)];
        }
        if (!$rowsToWrite && isset($cols[7]) && is_numeric(str_replace(',', '', (string) ($cols[4] ?? '')))) {
            // single price column fallback
        }
        if (!$rowsToWrite) {
            continue;
        }
        foreach ($rowsToWrite as [$source, $price]) {
            try {
                $stmt->execute([
                    generateUUID(),
                    $productId,
                    $brand !== '' ? $brand : null,
                    $name !== '' ? $name : $productId,
                    $source,
                    $price,
                    $sourceUrl !== '' ? $sourceUrl : null,
                    $day,
                ]);
                $imported++;
            } catch (Throwable $e) {
                // try without unique
                try {
                    $pdo->prepare("INSERT INTO tool_phone_product_history
                        (id, product_id, brand, name, source, price, source_url, snapshot_day)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                        generateUUID(), $productId, $brand ?: null, $name ?: $productId,
                        $source, $price, $sourceUrl ?: null, $day,
                    ]);
                    $imported++;
                } catch (Throwable $e2) {
                    // skip
                }
            }
        }
        if ($imported >= 5000) {
            break;
        }
    }
    jsonResponse(['success' => true, 'imported' => $imported]);
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
    $source = !empty($prices) ? 'BigGo API' : 'BigGo 可行方案';
    if (!$prices && $notice === '') {
        $notice = '已採用 BigGo 可行方案：本次不抓取 BigGo HTML 或來源商品頁，只建立外部查詢連結與歷史快照。';
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
    if ($query === '') {
        $query = 'Samsung S26';
    }

    require_once __DIR__ . '/includes/phone_compare.php';
    $compare = phoneCompareLookup($query);
    $products = $compare['products'] ?? [];
    $summary = $compare['priceSummary'] ?? [];
    $warnings = $compare['warnings'] ?? [];
    $noticeParts = [];
    if (!empty($products)) {
        $noticeParts[] = '已對齊 Appwrite 版抓取地標網通與傑昇通信，共 ' . count($products) . ' 筆可比價結果。';
    } else {
        $noticeParts[] = '通路頁面暫時抓不到商品；已保留外部搜尋連結與歷史快照。';
    }
    if ($warnings) {
        $noticeParts[] = implode('；', $warnings);
    }

    $snapshot = [
        'tool_type' => 'phone',
        'query_text' => $query,
        'title' => $query . ' 手機比價',
        'source' => '地標網通 + 傑昇通信',
        'current_price' => $summary['current'] ?? null,
        'high_price' => $summary['high'] ?? null,
        'low_price' => $summary['low'] ?? null,
        'result_url' => ($compare['targets']['地標網通'] ?? 'https://www.landtop.com.tw/'),
        'notice' => implode(' ', $noticeParts),
    ];
    saveSnapshot($pdo, $snapshot);
    $snapshotStored = 0;
    $histories = [];
    try {
        $snapshotStored = savePhoneProductSnapshots($pdo, $products);
        $histories = loadPhoneProductHistories($pdo, $products);
    } catch (Throwable $e) {
        $warnings[] = '商品歷史價格儲存/讀取失敗：' . $e->getMessage();
    }

    jsonResponse([
        'success' => true,
        'snapshot' => $snapshot,
        'targets' => $compare['targets'] ?? [],
        'products' => $products,
        'warnings' => $warnings,
        'total' => (int) ($compare['total'] ?? count($products)),
        'sourceUrls' => $compare['sourceUrls'] ?? [],
        'fetchedAt' => $compare['fetchedAt'] ?? null,
        'history' => loadHistory($pdo, 'phone', $query),
        'histories' => $histories,
        'historyAvailable' => true,
        'snapshotStored' => $snapshotStored,
    ]);
}

if ($action === 'news_sites') {
    require_once __DIR__ . '/includes/fengbro_news.php';
    jsonResponse([
        'success' => true,
        'sites' => fengbroNewsDefaultSites(),
        'count' => count(fengbroNewsDefaultSites()),
    ]);
}

if ($action === 'news_search') {
    require_once __DIR__ . '/includes/fengbro_news.php';
    $payload = [];
    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
    $query = trim((string) ($payload['query'] ?? $_GET['q'] ?? ''));
    $sites = [];
    if (!empty($payload['sites']) && is_array($payload['sites'])) {
        foreach ($payload['sites'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? $id));
            $domain = trim((string) ($row['domain'] ?? ''));
            $homeUrl = trim((string) ($row['homeUrl'] ?? ''));
            $adapter = trim((string) ($row['adapter'] ?? 'generic-keyword-url'));
            if ($id === '' || ($domain === '' && $homeUrl === '')) {
                continue;
            }
            $sites[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : $id,
                'domain' => $domain,
                'homeUrl' => $homeUrl,
                'adapter' => $adapter !== '' ? $adapter : 'generic-keyword-url',
                'searchUrlTemplate' => isset($row['searchUrlTemplate']) ? (string) $row['searchUrlTemplate'] : null,
                'locked' => !empty($row['locked']),
            ];
        }
    }
    if ($sites) {
        $sites = array_values(array_filter($sites, static fn($s) => !empty($s['locked'])));
    }
    @set_time_limit(90);
    $result = fengbroNewsSearch($query, $sites);
    if (!empty($result['error']) && empty($result['articles'])) {
        jsonResponse(array_merge(['success' => false], $result), 400);
    }
    jsonResponse(array_merge(['success' => true], $result));
}

if ($action === 'news_bento') {
    require_once __DIR__ . '/includes/fengbro_news.php';
    $focus = ($_GET['focus'] ?? '1') !== '0';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($decoded) && array_key_exists('focus', $decoded)) {
            $focus = !empty($decoded['focus']);
        }
    }
    $result = fengbroNewsBentoStores($focus);
    jsonResponse(array_merge(['success' => true], $result));
}

if ($action === 'news_population') {
    require_once __DIR__ . '/includes/fengbro_news.php';
    @set_time_limit(45);
    $result = fengbroNewsPopulationStats();
    jsonResponse(array_merge(['success' => true], $result));
}

jsonResponse(['success' => false, 'error' => '不支援的工具動作。'], 400);
