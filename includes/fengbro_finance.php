<?php

/**
 * 預設金融標的（對齊 fengbroaiappwrite INSTRUMENTS）。
 */
function fengbroFinanceDefaultItems()
{
    return [
        ['id' => 'taiex', 'name' => '加權指數', 'symbol' => '^TWII', 'url' => 'https://tw.stock.yahoo.com/s/tse.php', 'group' => 'Taiwan', 'source' => 'Yahoo股市', 'parser' => 'yahoo_tw', 'apiSymbol' => '^TWII', 'historySymbol' => '^TWII', 'breakoutValue' => 126820, 'breakoutLabel' => '突破 126820'],
        ['id' => 'tsmc', 'name' => '台積電', 'symbol' => '2330.TW', 'url' => 'https://tw.stock.yahoo.com/quote/2330.TW', 'group' => 'Taiwan', 'source' => 'Yahoo股市', 'parser' => 'yahoo_tw', 'apiSymbol' => '2330.TW', 'historySymbol' => '2330.TW', 'breakoutValue' => 3333, 'breakoutLabel' => '突破 3333'],
        ['id' => 'tsm', 'name' => '台積電 ADR', 'symbol' => 'TSM', 'url' => 'https://finance.yahoo.com/quote/TSM', 'group' => 'US', 'source' => 'Yahoo Finance', 'parser' => 'yahoo_quote', 'apiSymbol' => 'TSM', 'historySymbol' => 'TSM'],
        ['id' => 'nikkei-225', 'name' => 'Nikkei 225 Index', 'symbol' => '.N225', 'url' => 'https://www.cnbc.com/quotes/.N225', 'group' => 'Asia', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => '^N225', 'breakoutValue' => 110000, 'breakoutLabel' => '突破 110000', 'localLabel' => '日経平均株価'],
        ['id' => 'kioxia', 'name' => 'キオクシア 鎧俠', 'symbol' => '285A.T', 'url' => 'https://finance.yahoo.com/quote/285A.T', 'group' => 'Asia', 'source' => 'Yahoo Finance', 'parser' => 'yahoo_quote', 'apiSymbol' => '285A.T', 'historySymbol' => '285A.T', 'localLabel' => 'TYO: 285A'],
        ['id' => 'kospi', 'name' => 'KOSPI Index', 'symbol' => '.KS11', 'url' => 'https://www.cnbc.com/quotes/.KS11?qsearchterm=kospi', 'group' => 'Asia', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => '^KS11', 'breakoutValue' => 12682, 'breakoutLabel' => '突破 12682', 'localLabel' => '코스피'],
        ['id' => 'samsung-electronics', 'name' => '三星電子', 'symbol' => '005930.KS', 'url' => 'https://finance.yahoo.com/quote/005930.KS', 'group' => 'Korea', 'source' => 'Yahoo股市', 'parser' => 'yahoo_quote', 'apiSymbol' => '005930.KS', 'historySymbol' => '005930.KS', 'breakoutValue' => 1110000, 'breakoutLabel' => '突破 1110000'],
        ['id' => 'sk-hynix', 'name' => 'SK 海力士', 'symbol' => '000660.KS', 'url' => 'https://finance.yahoo.com/quote/000660.KS', 'group' => 'Korea', 'source' => 'Yahoo股市', 'parser' => 'yahoo_quote', 'apiSymbol' => '000660.KS', 'historySymbol' => '000660.KS', 'breakoutValue' => 11110000, 'breakoutLabel' => '突破 11110000'],
        ['id' => 'sk-hynix-adr', 'name' => 'SK hynix Inc. ADR', 'symbol' => 'SKHY', 'url' => 'https://finance.yahoo.com/quote/SKHY', 'group' => 'Korea', 'source' => 'Yahoo Finance', 'parser' => 'yahoo_quote', 'apiSymbol' => 'SKHY', 'historySymbol' => 'SKHY'],
        ['id' => 'usd-twd', 'name' => '美元對台幣匯率', 'symbol' => 'USDTWD=X', 'url' => 'https://finance.yahoo.com/quote/USDTWD=X', 'group' => 'FX', 'source' => 'Yahoo Finance', 'parser' => 'yahoo_quote', 'apiSymbol' => 'USDTWD=X', 'historySymbol' => 'USDTWD=X', 'breakoutValue' => 37, 'breakoutLabel' => '突破 37'],
        ['id' => 'usd-jpy', 'name' => '美元對日元匯率', 'symbol' => 'USDJPY=X', 'url' => 'https://finance.yahoo.com/quote/USDJPY=X', 'group' => 'FX', 'source' => 'Yahoo Finance', 'parser' => 'yahoo_quote', 'apiSymbol' => 'USDJPY=X', 'historySymbol' => 'USDJPY=X', 'breakoutValue' => 222, 'breakoutLabel' => '突破 222'],
        ['id' => 'brent', 'name' => 'ICE Brent Crude', 'symbol' => '@LCO.1', 'url' => 'https://www.cnbc.com/quotes/@LCO.1', 'group' => 'Commodities', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => 'BZ=F', 'breakoutValue' => 222, 'breakoutLabel' => '突破 222'],
        ['id' => 'us30y', 'name' => 'U.S. 30 Year Treasury', 'symbol' => 'US.30', 'url' => 'https://www.cnbc.com/quotes/US.30', 'group' => 'Rates', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => '^TYX', 'breakoutValue' => 6.66, 'breakoutLabel' => '突破 6.66'],
        ['id' => 'gold', 'name' => 'Gold COMEX', 'symbol' => '@GC.1', 'url' => 'https://www.cnbc.com/quotes/@GC.1', 'group' => 'Commodities', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => 'GC=F', 'breakoutValue' => 6666, 'breakoutLabel' => '突破 6666'],
        ['id' => 'dow', 'name' => 'Dow Jones Industrial Average', 'symbol' => '.DJI', 'url' => 'https://www.cnbc.com/quotes/.DJI', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => '^DJI', 'breakoutValue' => 66666, 'breakoutLabel' => '突破 66666', 'localLabel' => "Roaring '20s"],
        ['id' => 'sp500', 'name' => 'S&P 500 Index', 'symbol' => '.SPX', 'url' => 'https://www.cnbc.com/quotes/.SPX', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => '^GSPC', 'breakoutValue' => 11111, 'breakoutLabel' => '突破 11111'],
        ['id' => 'nasdaq', 'name' => 'NASDAQ Composite', 'symbol' => '.IXIC', 'url' => 'https://www.cnbc.com/quotes/.IXIC', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => '^IXIC', 'breakoutValue' => 33333, 'breakoutLabel' => '突破 33333', 'localLabel' => '科技泡沫'],
        ['id' => 'phlx-semiconductor', 'name' => '費城半導體指數', 'symbol' => '.SOX', 'url' => 'https://www.cnbc.com/quotes/.SOX', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => '^SOX', 'localLabel' => '半導體泡沫'],
        ['id' => 'berkshire-a', 'name' => 'Berkshire Hathaway Inc Class A', 'symbol' => 'BRK.A', 'url' => 'https://www.cnbc.com/quotes/BRK.A', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => 'BRK-A', 'localLabel' => '巴菲特'],
        ['id' => 'berkshire-b', 'name' => 'Berkshire Hathaway Inc Class B', 'symbol' => 'BRK.B', 'url' => 'https://www.cnbc.com/quotes/BRK.B', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => 'BRK-B', 'localLabel' => '巴菲特'],
        ['id' => 'intel', 'name' => 'Intel Corp', 'symbol' => 'INTC', 'url' => 'https://www.cnbc.com/quotes/INTC', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => 'INTC', 'localLabel' => 'NASDAQ: INTC'],
        ['id' => 'amd', 'name' => 'Advanced Micro Devices Inc', 'symbol' => 'AMD', 'url' => 'https://www.cnbc.com/quotes/AMD', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => 'AMD', 'localLabel' => 'NASDAQ: AMD'],
        ['id' => 'nvidia', 'name' => 'NVIDIA Corp', 'symbol' => 'NVDA', 'url' => 'https://www.cnbc.com/quotes/NVDA', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => 'NVDA', 'localLabel' => '重零開始'],
        ['id' => 'micron', 'name' => '美光科技', 'symbol' => 'MU', 'url' => 'https://www.cnbc.com/quotes/MU', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => 'MU', 'localLabel' => 'AI泡沫'],
        ['id' => 'spacex', 'name' => 'SpaceX', 'symbol' => 'SPCX', 'url' => 'https://www.cnbc.com/quotes/SPCX', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'localLabel' => '人類泡沫'],
        ['id' => 'apple', 'name' => '蘋果', 'symbol' => 'AAPL', 'url' => 'https://www.cnbc.com/quotes/AAPL', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => 'AAPL', 'localLabel' => '手機泡沫'],
        ['id' => 'vix', 'name' => 'CBOE Volatility Index', 'symbol' => '.VIX', 'url' => 'https://www.cnbc.com/quotes/.VIX', 'group' => 'US', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => '^VIX'],
        ['id' => 'shiller-pe', 'name' => 'Shiller PE Ratio', 'symbol' => 'CAPE', 'url' => 'https://www.multpl.com/shiller-pe', 'group' => 'Valuation', 'source' => 'Multpl', 'parser' => 'multpl_shiller', 'recordHigh' => 45, 'recordHighDate' => 'Threshold', 'breakoutValue' => 45, 'breakoutLabel' => '突破 45'],
        ['id' => 'bitcoin', 'name' => 'Bitcoin/USD Coin Metrics', 'symbol' => 'BTC.CM=', 'url' => 'https://www.cnbc.com/quotes/BTC.CM=', 'group' => 'Crypto', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => 'BTC-USD', 'breakoutValue' => 111111, 'breakoutLabel' => '突破 111111'],
        ['id' => 'ether', 'name' => 'Ether/USD Coin Metrics', 'symbol' => 'ETH.CM=', 'url' => 'https://www.cnbc.com/quotes/ETH.CM=', 'group' => 'Crypto', 'source' => 'CNBC', 'parser' => 'cnbc', 'historySymbol' => 'ETH-USD', 'breakoutValue' => 2222, 'breakoutLabel' => '突破 2222'],
    ];
}

/** @deprecated 使用 fengbroFinanceActiveItems() */
function fengbroFinanceItems()
{
    return fengbroFinanceActiveItems();
}

function fengbroFinanceCachePath()
{
    return __DIR__ . '/../uploads/temp/fengbro_finance_cache.json';
}

function fengbroFinanceConfigPath()
{
    return __DIR__ . '/../uploads/temp/fengbro_finance_config.json';
}

function fengbroFinanceDefaultIds()
{
    return array_values(array_map(static fn($item) => $item['id'], fengbroFinanceDefaultItems()));
}

function fengbroFinanceReadConfig()
{
    $path = fengbroFinanceConfigPath();
    if (!is_file($path)) {
        return [
            'defaultIds' => fengbroFinanceDefaultIds(),
            'custom' => [],
            'featuredIds' => [],
            'imageById' => [],
        ];
    }
    $data = json_decode((string) @file_get_contents($path), true);
    if (!is_array($data)) {
        return [
            'defaultIds' => fengbroFinanceDefaultIds(),
            'custom' => [],
            'featuredIds' => [],
            'imageById' => [],
        ];
    }
    $allowed = array_flip(fengbroFinanceDefaultIds());
    $defaultIds = [];
    foreach ((array) ($data['defaultIds'] ?? fengbroFinanceDefaultIds()) as $id) {
        $id = trim((string) $id);
        if ($id !== '' && isset($allowed[$id])) {
            $defaultIds[] = $id;
        }
    }
    if (!$defaultIds) {
        $defaultIds = fengbroFinanceDefaultIds();
    }
    $custom = [];
    foreach (array_slice((array) ($data['custom'] ?? []), 0, 30) as $index => $row) {
        $normalized = fengbroFinanceNormalizeCustomInstrument($row, (int) $index);
        if ($normalized) {
            $custom[] = $normalized;
        }
    }
    $customIds = array_flip(array_map(static fn($c) => $c['id'] ?? '', $custom));
    $featuredIds = [];
    foreach ((array) ($data['featuredIds'] ?? []) as $id) {
        $id = trim((string) $id);
        if ($id === '') {
            continue;
        }
        if (isset($allowed[$id]) || isset($customIds[$id])) {
            $featuredIds[] = $id;
        }
        if (count($featuredIds) >= 9) {
            break;
        }
    }
    $imageById = [];
    foreach ((array) ($data['imageById'] ?? []) as $id => $urls) {
        $id = trim((string) $id);
        if ($id === '' || (!isset($allowed[$id]) && !isset($customIds[$id]))) {
            continue;
        }
        $normalizedUrls = fengbroFinanceNormalizeImageUrls($urls);
        if ($normalizedUrls) {
            $imageById[$id] = $normalizedUrls;
        }
    }
    // Seed imageById from custom instruments that carry image fields
    foreach ($custom as $c) {
        $cid = (string) ($c['id'] ?? '');
        if ($cid === '' || isset($imageById[$cid])) {
            continue;
        }
        $fromCustom = fengbroFinanceResolveImageUrls($c, ['imageById' => []]);
        if ($fromCustom) {
            $imageById[$cid] = $fromCustom;
        }
    }
    return [
        'defaultIds' => array_values(array_unique($defaultIds)),
        'custom' => $custom,
        'featuredIds' => array_values(array_unique($featuredIds)),
        'imageById' => $imageById,
    ];
}

/** Max 9 featured instrument ids (default or custom). */
function fengbroFinanceSaveFeaturedIds(array $ids): void
{
    $config = fengbroFinanceReadConfig();
    $allowed = array_flip(fengbroFinanceDefaultIds());
    foreach ($config['custom'] as $c) {
        if (!empty($c['id'])) {
            $allowed[$c['id']] = true;
        }
    }
    $filtered = [];
    foreach ($ids as $id) {
        $id = trim((string) $id);
        if ($id !== '' && isset($allowed[$id])) {
            $filtered[] = $id;
        }
        if (count($filtered) >= 9) {
            break;
        }
    }
    $config['featuredIds'] = array_values(array_unique($filtered));
    fengbroFinanceWriteConfig($config);
}

function fengbroFinanceToggleFeatured(string $id): array
{
    $id = trim($id);
    $config = fengbroFinanceReadConfig();
    $featured = $config['featuredIds'] ?? [];
    $pos = array_search($id, $featured, true);
    if ($pos !== false) {
        array_splice($featured, (int) $pos, 1);
    } else {
        if (count($featured) >= 9) {
            return ['ok' => false, 'error' => '精選最多 9 項', 'featuredIds' => $featured];
        }
        $featured[] = $id;
    }
    fengbroFinanceSaveFeaturedIds($featured);
    return ['ok' => true, 'featuredIds' => fengbroFinanceReadConfig()['featuredIds'] ?? []];
}

function fengbroFinanceWriteConfig(array $config)
{
    $path = fengbroFinanceConfigPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($path, json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    fengbroFinanceSyncToInstrumentTable($config);
    fengbroFinanceClearDataCache();
}

/**
 * 把 finance config（自訂標的＋精選）同步到 financeinstrument 表（additive write-through）。
 * JSON 仍為行情邏輯主要來源；寫入表失敗不影響原功能。
 */
function fengbroFinanceSyncToInstrumentTable(array $config): void
{
    if (!function_exists('getConnection') || !function_exists('fengbroEnsureFinanceInstrumentTable')) {
        return;
    }
    try {
        $pdo = getConnection();
        fengbroEnsureFinanceInstrumentTable($pdo);
    } catch (Throwable $e) {
        return;
    }

    $featuredIds = array_flip((array) ($config['featuredIds'] ?? []));
    $imageById = (array) ($config['imageById'] ?? []);
    $relatedById = (array) ($config['relatedLinksById'] ?? []);
    $youtubeById = (array) ($config['youtubeById'] ?? []);
    $bilibiliById = (array) ($config['bilibiliById'] ?? []);

    $rows = [];
    $index = 0;
    // 自訂標的（每筆一列）
    foreach ((array) ($config['custom'] ?? []) as $custom) {
        if (!is_array($custom)) {
            continue;
        }
        $id = (string) ($custom['id'] ?? ('custom-' . ($index++)));
        $symbol = strtoupper(trim((string) ($custom['symbol'] ?? '')));
        $name = trim((string) ($custom['name'] ?? ''));
        if ($name === '' || $symbol === '') {
            continue;
        }
        $provider = in_array((string) ($custom['provider'] ?? ''), ['yahoo', 'cnbc'], true) ? $custom['provider'] : 'yahoo';
        $groupRaw = strtolower(trim((string) ($custom['group'] ?? 'other')));
        $rows[] = [
            'id' => $id,
            'name' => fengbroMbCut($name, 200),
            'symbol' => fengbroMbCut($symbol, 64),
            'provider' => $provider,
            'group' => in_array($groupRaw, ['korea', 'japan', 'taiwan', 'us', 'other'], true) ? $groupRaw : 'other',
            'imageUrls' => is_array($imageById[$id] ?? null) ? implode("\n", array_slice($imageById[$id], 0, 9)) : '',
            'youtubeUrl' => (string) ($youtubeById[$id] ?? $custom['youtubeUrl'] ?? ''),
            'bilibiliUrl' => (string) ($bilibiliById[$id] ?? $custom['bilibiliUrl'] ?? ''),
            'relatedLinks' => is_array($relatedById[$id] ?? null) ? json_encode($relatedById[$id], JSON_UNESCAPED_UNICODE) : '',
            'featured' => isset($featuredIds[$id]) ? 1 : 0,
        ];
    }

    if (!$rows) {
        return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO financeinstrument
            (id, name, symbol, provider, `group`, imageUrls, youtubeUrl, bilibiliUrl, relatedLinks, featured)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            imageUrls = VALUES(imageUrls),
            youtubeUrl = VALUES(youtubeUrl),
            bilibiliUrl = VALUES(bilibiliUrl),
            relatedLinks = VALUES(relatedLinks),
            featured = VALUES(featured)"
    );
    foreach ($rows as $row) {
        try {
            $stmt->execute([
                $row['id'],
                $row['name'],
                $row['symbol'],
                $row['provider'],
                $row['group'],
                $row['imageUrls'],
                $row['youtubeUrl'],
                $row['bilibiliUrl'],
                $row['relatedLinks'],
                $row['featured'],
            ]);
        } catch (Throwable $e) {
            // 單筆失敗不中斷
        }
    }
}

function fengbroFinanceClearDataCache()
{
    $cache = fengbroFinanceReadCache();
    foreach (array_keys($cache) as $key) {
        if (is_string($key) && (
            str_starts_with($key, 'finance_data_v3')
            || str_starts_with($key, 'finance_data_v4')
            || str_starts_with($key, 'finance_data_v5')
        )) {
            unset($cache[$key]);
        }
    }
    fengbroFinanceWriteCache($cache);
}

function fengbroFinanceSlugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
    return substr(trim($value, '-'), 0, 48);
}

const FENGBRO_FINANCE_MAX_IMAGE_URLS = 9;
const FENGBRO_FINANCE_MAX_RELATED_LINKS = 9;

/**
 * Normalize optional http(s) or site-relative image URL.
 * Relative paths (e.g. /finance/xxx.png or assets/...) are kept for local assets.
 */
function fengbroFinanceNormalizeHttpUrl($value, int $maxLen = 800): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $trimmed = trim(mb_substr($value, 0, $maxLen, 'UTF-8'));
    if ($trimmed === '') {
        return null;
    }
    // Site-relative path (local asset)
    if (isset($trimmed[0]) && $trimmed[0] === '/') {
        if (preg_match('#^/[A-Za-z0-9_./%+\-]+$#', $trimmed) && !str_contains($trimmed, '..')) {
            return $trimmed;
        }
        return null;
    }
    // Relative asset path without leading slash
    if (preg_match('#^(assets|uploads|finance)/[A-Za-z0-9_./%+\-]+$#', $trimmed) && !str_contains($trimmed, '..')) {
        return $trimmed;
    }
    $withProtocol = preg_match('#^https?://#i', $trimmed) ? $trimmed : ('https://' . $trimmed);
    if (filter_var($withProtocol, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $scheme = strtolower((string) (parse_url($withProtocol, PHP_URL_SCHEME) ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return null;
    }
    return $withProtocol;
}

/**
 * Parse draft textarea / array / CSV cell into clean image URLs (max 9).
 * Accepts newlines, commas, or semicolons as separators.
 *
 * @param mixed $input
 * @return list<string>
 */
function fengbroFinanceNormalizeImageUrls($input): array
{
    $rawList = [];
    if (is_string($input)) {
        foreach (preg_split('/[\n,;]+/', $input) ?: [] as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $rawList[] = $part;
            }
        }
    } elseif (is_array($input)) {
        foreach ($input as $item) {
            if (is_string($item) && trim($item) !== '') {
                $rawList[] = trim($item);
            }
        }
    }

    $seen = [];
    $urls = [];
    foreach ($rawList as $raw) {
        $url = fengbroFinanceNormalizeHttpUrl($raw, 800);
        if ($url === null || isset($seen[$url])) {
            continue;
        }
        $seen[$url] = true;
        $urls[] = $url;
        if (count($urls) >= FENGBRO_FINANCE_MAX_IMAGE_URLS) {
            break;
        }
    }
    return $urls;
}

/**
 * Guess a short chip label from a page URL when the user only pastes the link.
 * Aligns with Appwrite guessFinanceRelatedLinkLabel.
 */
function fengbroFinanceGuessRelatedLinkLabel(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return '連結';
    }
    $host = strtolower((string) $parts['host']);
    $host = preg_replace('/^www\./i', '', $host) ?? $host;
    $path = (string) ($parts['path'] ?? '');

    if ($host === 'ptt.cc' || str_ends_with($host, '.ptt.cc')) {
        if (preg_match('#/bbs/([^/]+)#i', $path, $m)) {
            $board = rawurldecode($m[1]);
            if (strcasecmp($board, 'stock') === 0) {
                return 'PTT 股板';
            }
            if (strcasecmp($board, 'home-sale') === 0) {
                return 'PTT 房屋';
            }
            if (strcasecmp($board, 'railway') === 0) {
                return 'PTT 鐵道';
            }
            return mb_substr('PTT ' . $board, 0, 40, 'UTF-8');
        }
        return 'PTT';
    }

    $hostLabels = [
        'investing.com' => 'Investing',
        'twse.com.tw' => '證交所',
        'tpex.org.tw' => '櫃買中心',
        'cnyes.com' => '鉅亨網',
        'moneydj.com' => 'MoneyDJ',
        'cmoney.tw' => 'CMoney',
        'wantgoo.com' => '玩股網',
        'goodinfo.tw' => 'Goodinfo',
        'yahoo.com' => 'Yahoo',
        'yahoo.co.jp' => 'Yahoo',
        'cnbc.com' => 'CNBC',
        'bloomberg.com' => 'Bloomberg',
        'reuters.com' => 'Reuters',
        'youtube.com' => 'YouTube',
        'youtu.be' => 'YouTube',
        'bilibili.com' => 'Bilibili',
        'b23.tv' => 'Bilibili',
    ];
    foreach ($hostLabels as $needle => $label) {
        if ($host === $needle || str_ends_with($host, '.' . $needle) || str_contains($host, $needle)) {
            return $label;
        }
    }

    $base = explode('.', $host)[0] ?? $host;
    if ($base === '') {
        return '連結';
    }
    return mb_substr(mb_strtoupper(mb_substr($base, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($base, 1, null, 'UTF-8'), 0, 40, 'UTF-8');
}

/**
 * Parse draft textarea / stored list / CSV cell into relatedLinks.
 * Accepts plain URL lines, `標籤|網址` / `標籤｜網址`, or `{label,url}` objects.
 * Multi-value CSV cells use `;` between entries.
 *
 * @param mixed $input
 * @return list<array{label:string,url:string}>
 */
function fengbroFinanceNormalizeRelatedLinks($input): array
{
    $rawLines = [];

    if (is_string($input)) {
        // CSV multi-value uses `;`; draft textarea uses newlines.
        $chunks = preg_split('/[\n;]+/', $input) ?: [];
        foreach ($chunks as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/^(.+?)\s*[|｜]\s*(https?:\/\/\S+|www\.\S+|\S+\.\S+\/\S.*)$/iu', $trimmed, $m)) {
                $rawLines[] = ['label' => trim($m[1]), 'url' => trim($m[2])];
                continue;
            }
            $rawLines[] = ['url' => $trimmed];
        }
    } elseif (is_array($input)) {
        foreach ($input as $item) {
            if (is_string($item) && trim($item) !== '') {
                $rawLines[] = ['url' => trim($item)];
                continue;
            }
            if (is_array($item)) {
                $url = '';
                if (isset($item['url']) && is_string($item['url'])) {
                    $url = $item['url'];
                } elseif (isset($item['href']) && is_string($item['href'])) {
                    $url = $item['href'];
                }
                if (trim($url) === '') {
                    continue;
                }
                $entry = ['url' => trim($url)];
                if (isset($item['label']) && is_string($item['label'])) {
                    $entry['label'] = $item['label'];
                }
                $rawLines[] = $entry;
            }
        }
    }

    $seen = [];
    $links = [];
    foreach ($rawLines as $raw) {
        $url = fengbroFinanceNormalizeHttpUrl($raw['url'] ?? '', 800);
        if ($url === null || isset($seen[$url])) {
            continue;
        }
        // Related links should be absolute http(s) pages (not relative assets)
        if (!preg_match('#^https?://#i', $url)) {
            continue;
        }
        $seen[$url] = true;
        $label = isset($raw['label']) && is_string($raw['label']) && trim($raw['label']) !== ''
            ? mb_substr(trim($raw['label']), 0, 40, 'UTF-8')
            : fengbroFinanceGuessRelatedLinkLabel($url);
        $links[] = ['label' => $label, 'url' => $url];
        if (count($links) >= FENGBRO_FINANCE_MAX_RELATED_LINKS) {
            break;
        }
    }
    return $links;
}

/**
 * Serialize related links for draft textarea (one per line; label only when custom).
 */
function fengbroFinanceFormatRelatedLinksText(array $links): string
{
    if (!$links) {
        return '';
    }
    $lines = [];
    foreach ($links as $link) {
        if (!is_array($link) || empty($link['url'])) {
            continue;
        }
        $url = (string) $link['url'];
        $label = isset($link['label']) ? trim((string) $link['label']) : '';
        $guessed = fengbroFinanceGuessRelatedLinkLabel($url);
        if ($label === '' || $label === $guessed) {
            $lines[] = $url;
        } else {
            $lines[] = $label . '|' . $url;
        }
    }
    return implode("\n", $lines);
}

/**
 * Join related links for a single CSV cell (`;` between entries).
 */
function fengbroFinanceRelatedLinksToCsvCell(array $links): string
{
    $text = fengbroFinanceFormatRelatedLinksText($links);
    return $text === '' ? '' : str_replace("\n", ';', $text);
}

/**
 * Resolve display image URLs for an instrument (config override > item fields).
 *
 * @return list<string>
 */
function fengbroFinanceResolveImageUrls(array $item, ?array $config = null): array
{
    $id = trim((string) ($item['id'] ?? ''));
    if ($config === null) {
        $config = fengbroFinanceReadConfig();
    }
    $byId = $config['imageById'] ?? [];
    if ($id !== '' && !empty($byId[$id])) {
        $fromConfig = fengbroFinanceNormalizeImageUrls($byId[$id]);
        if ($fromConfig) {
            return $fromConfig;
        }
    }
    if (!empty($item['imageUrls'])) {
        $fromItem = fengbroFinanceNormalizeImageUrls($item['imageUrls']);
        if ($fromItem) {
            return $fromItem;
        }
    }
    if (!empty($item['imageUrl'])) {
        return fengbroFinanceNormalizeImageUrls([$item['imageUrl']]);
    }
    return [];
}

/**
 * Save image URL list for any instrument id (default or custom). Empty clears.
 *
 * @param list<string>|string $urls
 */
function fengbroFinanceSaveImagesForId(string $id, $urls): void
{
    $id = trim($id);
    if ($id === '') {
        return;
    }
    $config = fengbroFinanceReadConfig();
    $allowed = array_flip(fengbroFinanceDefaultIds());
    foreach ($config['custom'] as $c) {
        if (!empty($c['id'])) {
            $allowed[$c['id']] = true;
        }
    }
    if (!isset($allowed[$id])) {
        return;
    }
    $normalized = fengbroFinanceNormalizeImageUrls($urls);
    if (!isset($config['imageById']) || !is_array($config['imageById'])) {
        $config['imageById'] = [];
    }
    if ($normalized) {
        $config['imageById'][$id] = $normalized;
    } else {
        unset($config['imageById'][$id]);
    }
    // Keep custom instrument object in sync when applicable
    foreach ($config['custom'] as $i => $row) {
        if (($row['id'] ?? '') === $id) {
            if ($normalized) {
                $config['custom'][$i]['imageUrl'] = $normalized[0];
                $config['custom'][$i]['imageUrls'] = $normalized;
            } else {
                unset($config['custom'][$i]['imageUrl'], $config['custom'][$i]['imageUrls']);
            }
            break;
        }
    }
    fengbroFinanceWriteConfig($config);
}

/** CSV headers for 鋒兄金融 (align Appwrite + id for default image round-trip). */
function fengbroFinanceCsvHeaders(): array
{
    return ['id', 'name', 'symbol', 'provider', 'group', 'imageUrls', 'youtubeUrl', 'bilibiliUrl', 'relatedLinks', 'featured'];
}

/**
 * Escape a CSV cell (quote when needed for Excel / multi-value imageUrls).
 */
function fengbroFinanceCsvEscape($value): string
{
    $stringValue = (string) ($value ?? '');
    if (
        $stringValue === ''
        || (
            !str_contains($stringValue, ',')
            && !str_contains($stringValue, '"')
            && !str_contains($stringValue, "\n")
            && !str_contains($stringValue, "\r")
            && !str_contains($stringValue, ';')
            && !str_contains($stringValue, '?')
            && !str_contains($stringValue, '&')
        )
    ) {
        return $stringValue;
    }
    return '"' . str_replace('"', '""', $stringValue) . '"';
}

/**
 * RFC-style CSV parse that keeps quoted commas/newlines (image URLs often need this).
 *
 * @return list<list<string>>
 */
function fengbroFinanceParseCsvText(string $text): array
{
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $rows = [];
    $currentRow = [];
    $currentField = '';
    $inQuotes = false;
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $char = $text[$i];
        if ($inQuotes) {
            if ($char === '"') {
                if ($i + 1 < $len && $text[$i + 1] === '"') {
                    $currentField .= '"';
                    $i++;
                } else {
                    $inQuotes = false;
                }
            } else {
                $currentField .= $char;
            }
        } elseif ($char === '"') {
            $inQuotes = true;
        } elseif ($char === ',') {
            $currentRow[] = $currentField;
            $currentField = '';
        } elseif ($char === "\n") {
            $currentRow[] = $currentField;
            if ($currentRow && array_filter($currentRow, static fn($f) => trim((string) $f) !== '')) {
                $rows[] = $currentRow;
            }
            $currentRow = [];
            $currentField = '';
        } else {
            $currentField .= $char;
        }
    }
    if ($currentField !== '' || $currentRow) {
        $currentRow[] = $currentField;
        if ($currentRow && array_filter($currentRow, static fn($f) => trim((string) $f) !== '')) {
            $rows[] = $currentRow;
        }
    }
    return $rows;
}

/**
 * Map a header cell to a canonical CSV field name.
 */
function fengbroFinanceMapCsvHeader(string $raw): ?string
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return null;
    }
    $aliases = [
        'id' => 'id',
        'instrumentid' => 'id',
        '標的id' => 'id',
        'name' => 'name',
        '名稱' => 'name',
        '代稱' => 'name',
        'symbol' => 'symbol',
        '代號' => 'symbol',
        '代碼' => 'symbol',
        'ticker' => 'symbol',
        'provider' => 'provider',
        '來源' => 'provider',
        'group' => 'group',
        '分類' => 'group',
        'region' => 'group',
        'imageurls' => 'imageUrls',
        'imageurl' => 'imageUrls',
        'image_urls' => 'imageUrls',
        'images' => 'imageUrls',
        '圖片' => 'imageUrls',
        '圖片網址' => 'imageUrls',
        '連結圖片' => 'imageUrls',
        'youtubeurl' => 'youtubeUrl',
        'youtube' => 'youtubeUrl',
        'youtubelink' => 'youtubeUrl',
        'bilibiliurl' => 'bilibiliUrl',
        'bilibili' => 'bilibiliUrl',
        'relatedlinks' => 'relatedLinks',
        'related_links' => 'relatedLinks',
        'links' => 'relatedLinks',
        '自訂網址' => 'relatedLinks',
        '連結' => 'relatedLinks',
        'featured' => 'featured',
        '精選' => 'featured',
        '精選焦點' => 'featured',
    ];
    $lower = strtolower($trimmed);
    $compact = strtolower(preg_replace('/[\s_]+/u', '', $trimmed) ?? $trimmed);
    if (isset($aliases[$lower])) {
        return $aliases[$lower];
    }
    if (isset($aliases[$compact])) {
        return $aliases[$compact];
    }
    if (isset($aliases[$trimmed])) {
        return $aliases[$trimmed];
    }
    // Direct English header match
    foreach (fengbroFinanceCsvHeaders() as $header) {
        if (strtolower($header) === $lower) {
            return $header;
        }
    }
    return null;
}

/**
 * Build CSV text for active instruments (defaults + custom).
 * Multi-value cells (imageUrls, relatedLinks) use `;` (Appwrite-compatible).
 * Columns: id,name,symbol,provider,group,imageUrls,youtubeUrl,bilibiliUrl,relatedLinks,featured
 */
function fengbroFinanceBuildCsv(?array $config = null): string
{
    $config = $config ?? fengbroFinanceReadConfig();
    $featuredSet = array_flip($config['featuredIds'] ?? []);
    $headers = fengbroFinanceCsvHeaders();
    $lines = [implode(',', $headers)];

    $appendRow = static function (array $row) use (&$lines, $featuredSet): void {
        $id = (string) ($row['id'] ?? '');
        $imgs = fengbroFinanceResolveImageUrls($row);
        $provider = (string) ($row['provider'] ?? '');
        if ($provider === '') {
            $parser = (string) ($row['parser'] ?? 'cnbc');
            if ($parser === 'yahoo_quote' || $parser === 'yahoo_tw') {
                $provider = 'yahoo';
            } elseif ($parser === 'multpl_shiller') {
                $provider = 'multpl';
            } else {
                $provider = 'cnbc';
            }
        }
        $related = fengbroFinanceNormalizeRelatedLinks($row['relatedLinks'] ?? []);
        $cells = [
            fengbroFinanceCsvEscape($id),
            fengbroFinanceCsvEscape($row['name'] ?? ''),
            fengbroFinanceCsvEscape($row['symbol'] ?? ''),
            fengbroFinanceCsvEscape($provider),
            fengbroFinanceCsvEscape($row['group'] ?? ''),
            fengbroFinanceCsvEscape(implode(';', $imgs)),
            fengbroFinanceCsvEscape($row['youtubeUrl'] ?? ''),
            fengbroFinanceCsvEscape($row['bilibiliUrl'] ?? ''),
            fengbroFinanceCsvEscape(fengbroFinanceRelatedLinksToCsvCell($related)),
            fengbroFinanceCsvEscape(!empty($featuredSet[$id]) || !empty($row['featured']) ? '1' : '0'),
        ];
        $lines[] = implode(',', $cells);
    };

    foreach (fengbroFinanceActiveItems() as $item) {
        $appendRow($item);
    }

    return implode("\n", $lines) . "\n";
}

/**
 * Parse finance CSV into rows keyed by header names.
 *
 * @return array{
 *   rows: list<array<string,string>>,
 *   errors: list<string>,
 *   hasImageColumn: bool,
 *   hasFeaturedColumn: bool,
 *   hasYoutubeColumn: bool,
 *   hasBilibiliColumn: bool,
 *   hasRelatedLinksColumn: bool
 * }
 */
function fengbroFinanceParseCsv(string $text): array
{
    $emptyFlags = [
        'hasImageColumn' => false,
        'hasFeaturedColumn' => false,
        'hasYoutubeColumn' => false,
        'hasBilibiliColumn' => false,
        'hasRelatedLinksColumn' => false,
    ];
    $errors = [];
    $matrix = fengbroFinanceParseCsvText($text);
    if (count($matrix) < 1) {
        return array_merge(['rows' => [], 'errors' => ['CSV 檔案是空的']], $emptyFlags);
    }

    $headerCells = $matrix[0];
    $columnIndex = [];
    $looksLikeHeader = false;
    foreach ($headerCells as $i => $cell) {
        $mapped = fengbroFinanceMapCsvHeader((string) $cell);
        if ($mapped !== null) {
            $looksLikeHeader = true;
            if (!isset($columnIndex[$mapped])) {
                $columnIndex[$mapped] = $i;
            }
        }
    }

    $hasImageColumn = isset($columnIndex['imageUrls']);
    $hasFeaturedColumn = isset($columnIndex['featured']);
    $hasYoutubeColumn = isset($columnIndex['youtubeUrl']);
    $hasBilibiliColumn = isset($columnIndex['bilibiliUrl']);
    $hasRelatedLinksColumn = isset($columnIndex['relatedLinks']);
    $savedImageIdx = $columnIndex['imageUrls'] ?? null;
    $savedFeaturedIdx = $columnIndex['featured'] ?? null;
    $savedYoutubeIdx = $columnIndex['youtubeUrl'] ?? null;
    $savedBilibiliIdx = $columnIndex['bilibiliUrl'] ?? null;
    $savedRelatedIdx = $columnIndex['relatedLinks'] ?? null;

    // Legacy positional: name,symbol,provider,group[,imageUrls]
    if (!$looksLikeHeader || !isset($columnIndex['symbol'])) {
        $columnIndex = [
            'name' => 0,
            'symbol' => 1,
            'provider' => 2,
            'group' => 3,
        ];
        $start = 0;
        if ($looksLikeHeader || preg_match('/name|symbol|名稱|代號/i', implode(',', $headerCells))) {
            $start = 1;
        }
        if ($savedImageIdx !== null) {
            $columnIndex['imageUrls'] = $savedImageIdx;
            $hasImageColumn = true;
        } else {
            $maxCells = 0;
            for ($ri = $start; $ri < count($matrix); $ri++) {
                $maxCells = max($maxCells, count($matrix[$ri]));
            }
            if ($maxCells >= 5) {
                $columnIndex['imageUrls'] = 4;
                $hasImageColumn = true;
            }
        }
        if ($savedFeaturedIdx !== null) {
            $columnIndex['featured'] = $savedFeaturedIdx;
            $hasFeaturedColumn = true;
        }
        if ($savedYoutubeIdx !== null) {
            $columnIndex['youtubeUrl'] = $savedYoutubeIdx;
            $hasYoutubeColumn = true;
        }
        if ($savedBilibiliIdx !== null) {
            $columnIndex['bilibiliUrl'] = $savedBilibiliIdx;
            $hasBilibiliColumn = true;
        }
        if ($savedRelatedIdx !== null) {
            $columnIndex['relatedLinks'] = $savedRelatedIdx;
            $hasRelatedLinksColumn = true;
        }
    } else {
        $start = 1;
    }

    if (!isset($columnIndex['symbol'])) {
        return array_merge(
            ['rows' => [], 'errors' => ['表頭缺少必要欄位 symbol（代號）']],
            $emptyFlags
        );
    }

    $rows = [];
    for ($i = $start; $i < count($matrix); $i++) {
        $values = $matrix[$i];
        $cell = static function (string $key) use ($columnIndex, $values): string {
            $idx = $columnIndex[$key] ?? null;
            if ($idx === null) {
                return '';
            }
            return trim((string) ($values[$idx] ?? ''));
        };
        $symbol = $cell('symbol');
        if ($symbol === '') {
            $errors[] = '第 ' . ($i + 1) . ' 行: symbol 不能為空';
            continue;
        }
        $rows[] = [
            'id' => $cell('id'),
            'name' => $cell('name'),
            'symbol' => $symbol,
            'provider' => $cell('provider') !== '' ? $cell('provider') : 'yahoo',
            'group' => $cell('group') !== '' ? $cell('group') : 'US',
            'imageUrls' => $cell('imageUrls'),
            'youtubeUrl' => $cell('youtubeUrl'),
            'bilibiliUrl' => $cell('bilibiliUrl'),
            'relatedLinks' => $cell('relatedLinks'),
            'featured' => $cell('featured'),
        ];
    }

    return [
        'rows' => $rows,
        'errors' => $errors,
        'hasImageColumn' => $hasImageColumn,
        'hasFeaturedColumn' => $hasFeaturedColumn,
        'hasYoutubeColumn' => $hasYoutubeColumn,
        'hasBilibiliColumn' => $hasBilibiliColumn,
        'hasRelatedLinksColumn' => $hasRelatedLinksColumn,
    ];
}

/**
 * Import finance CSV: upsert custom instruments; apply imageUrls to default or custom by id/symbol.
 *
 * @return array{customCount:int, imageCount:int, errors:list<string>}
 */
function fengbroFinanceImportCsv(string $text): array
{
    $parsed = fengbroFinanceParseCsv($text);
    $errors = $parsed['errors'];
    $hasImageColumn = !empty($parsed['hasImageColumn']);
    $hasFeaturedColumn = !empty($parsed['hasFeaturedColumn']);
    $hasYoutubeColumn = !empty($parsed['hasYoutubeColumn']);
    $hasBilibiliColumn = !empty($parsed['hasBilibiliColumn']);
    $hasRelatedLinksColumn = !empty($parsed['hasRelatedLinksColumn']);
    $config = fengbroFinanceReadConfig();
    $custom = $config['custom'];
    $defaultCatalog = [];
    foreach (fengbroFinanceDefaultItems() as $item) {
        $defaultCatalog[$item['id']] = $item;
        $defaultCatalog['sym:' . strtoupper((string) $item['symbol'])] = $item;
    }
    $defaultIds = $config['defaultIds'];
    $featuredIds = $config['featuredIds'] ?? [];
    $imageWrites = []; // id => urls[] (only when CSV has image column)
    $customCount = 0;

    $parseFeatured = static function (string $value): bool {
        $v = strtolower(trim($value));
        return in_array($v, ['1', 'true', 'yes', 'y', '是', '精選'], true);
    };

    foreach ($parsed['rows'] as $row) {
        $imageUrls = $hasImageColumn
            ? fengbroFinanceNormalizeImageUrls(
                str_replace([';'], ["\n"], (string) ($row['imageUrls'] ?? ''))
            )
            : null;
        $rowId = trim((string) ($row['id'] ?? ''));
        $symbol = strtoupper(trim((string) $row['symbol']));

        // Match default instrument by id or symbol
        $defaultItem = null;
        if ($rowId !== '' && isset($defaultCatalog[$rowId])) {
            $defaultItem = $defaultCatalog[$rowId];
        } elseif (isset($defaultCatalog['sym:' . $symbol])) {
            $defaultItem = $defaultCatalog['sym:' . $symbol];
        }

        if ($defaultItem) {
            $id = (string) $defaultItem['id'];
            if (!in_array($id, $defaultIds, true)) {
                $defaultIds[] = $id;
            }
            if ($hasImageColumn) {
                $imageWrites[$id] = $imageUrls ?? [];
            }
            if ($hasFeaturedColumn && $parseFeatured((string) ($row['featured'] ?? ''))) {
                if (!in_array($id, $featuredIds, true) && count($featuredIds) < 9) {
                    $featuredIds[] = $id;
                }
            }
            continue;
        }

        // Custom instrument
        $instrumentInput = [
            'name' => $row['name'] ?? '',
            'symbol' => $symbol,
            'provider' => $row['provider'] ?? 'yahoo',
            'group' => $row['group'] ?? 'US',
            'featured' => $hasFeaturedColumn && $parseFeatured((string) ($row['featured'] ?? '')),
        ];
        if ($hasImageColumn) {
            $instrumentInput['imageUrls'] = $imageUrls ?? [];
        }
        if ($hasYoutubeColumn) {
            $instrumentInput['youtubeUrl'] = $row['youtubeUrl'] ?? '';
        }
        if ($hasBilibiliColumn) {
            $instrumentInput['bilibiliUrl'] = $row['bilibiliUrl'] ?? '';
        }
        if ($hasRelatedLinksColumn) {
            $instrumentInput['relatedLinks'] = fengbroFinanceNormalizeRelatedLinks($row['relatedLinks'] ?? '');
        }
        $instrument = fengbroFinanceNormalizeCustomInstrument($instrumentInput, count($custom));
        if (!$instrument) {
            $errors[] = '無法解析標的: ' . $symbol;
            continue;
        }
        // Prefer stable id from CSV when it is a custom-* id
        if ($rowId !== '' && str_starts_with($rowId, 'custom-')) {
            $instrument['id'] = $rowId;
        }
        $replaced = false;
        foreach ($custom as $ci => $existing) {
            $sameId = ($existing['id'] ?? '') === ($instrument['id'] ?? '');
            $sameSym = strtoupper((string) ($existing['symbol'] ?? '')) === $symbol
                && $symbol !== '';
            if ($sameId || $sameSym) {
                // Missing optional columns → keep existing media / link fields
                if (!$hasImageColumn) {
                    if (!empty($existing['imageUrl'])) {
                        $instrument['imageUrl'] = $existing['imageUrl'];
                    }
                    if (!empty($existing['imageUrls'])) {
                        $instrument['imageUrls'] = $existing['imageUrls'];
                    }
                }
                if (!$hasYoutubeColumn && !empty($existing['youtubeUrl'])) {
                    $instrument['youtubeUrl'] = $existing['youtubeUrl'];
                }
                if (!$hasBilibiliColumn && !empty($existing['bilibiliUrl'])) {
                    $instrument['bilibiliUrl'] = $existing['bilibiliUrl'];
                }
                if (!$hasRelatedLinksColumn && !empty($existing['relatedLinks'])) {
                    $instrument['relatedLinks'] = $existing['relatedLinks'];
                }
                $custom[$ci] = $instrument;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            if (count($custom) >= 30) {
                $errors[] = '自訂標的已達 30 上限，略過 ' . $symbol;
                continue;
            }
            $custom[] = $instrument;
        }
        $customCount++;
        $id = (string) ($instrument['id'] ?? '');
        if ($id !== '') {
            if ($hasImageColumn) {
                $imageWrites[$id] = $imageUrls ?? [];
            }
            if (!empty($instrument['featured']) && !in_array($id, $featuredIds, true) && count($featuredIds) < 9) {
                $featuredIds[] = $id;
            }
        }
    }

    $config['defaultIds'] = array_values(array_unique($defaultIds));
    $config['custom'] = array_slice($custom, 0, 30);
    $config['featuredIds'] = array_values(array_unique(array_slice($featuredIds, 0, 9)));
    if (!isset($config['imageById']) || !is_array($config['imageById'])) {
        $config['imageById'] = [];
    }
    $imageCount = 0;
    foreach ($imageWrites as $id => $urls) {
        if ($urls) {
            $config['imageById'][$id] = $urls;
            $imageCount++;
            foreach ($config['custom'] as $i => $c) {
                if (($c['id'] ?? '') === $id) {
                    $config['custom'][$i]['imageUrl'] = $urls[0];
                    $config['custom'][$i]['imageUrls'] = $urls;
                    break;
                }
            }
        } else {
            unset($config['imageById'][$id]);
            foreach ($config['custom'] as $i => $c) {
                if (($c['id'] ?? '') === $id) {
                    unset($config['custom'][$i]['imageUrl'], $config['custom'][$i]['imageUrls']);
                    break;
                }
            }
        }
    }
    fengbroFinanceWriteConfig($config);

    return [
        'customCount' => $customCount,
        'imageCount' => $imageCount,
        'errors' => $errors,
    ];
}

/**
 * Resolve a display name for a quote symbol (Yahoo chart / TW page / JP page).
 * Aligns with Appwrite /api/fengbro-finance/resolve-name.
 *
 * @return array{ok:bool,name:?string,symbol:string,source:?string,error?:string}
 */
function fengbroFinanceResolveName(string $symbol, string $provider = 'yahoo'): array
{
    $symbol = strtoupper(trim($symbol));
    if ($symbol === '' || strlen($symbol) > 40) {
        return ['ok' => false, 'name' => null, 'symbol' => $symbol, 'source' => null, 'error' => '無效代碼'];
    }
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    $headers = [
        'User-Agent: ' . $ua,
        'Accept: text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
        'Accept-Language: zh-TW,zh;q=0.9,en;q=0.8',
    ];

    $fetch = static function (string $url, array $extraHeaders = []) use ($headers): string {
        $h = array_merge($headers, $extraHeaders);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => $h,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return ($code >= 200 && $code < 400 && is_string($body)) ? $body : '';
        }
        $ctx = stream_context_create(['http' => ['timeout' => 12, 'header' => implode("\r\n", $h) . "\r\n"]]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : '';
    };

    // 1) Yahoo chart meta shortName / longName
    $chartSymbol = $symbol;
    if (preg_match('/^998407/i', $symbol)) {
        $chartSymbol = '^N225';
    }
    $chartUrl = 'https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($chartSymbol)
        . '?interval=1d&range=5d';
    $chartJson = $fetch($chartUrl, ['Accept: application/json']);
    if ($chartJson !== '') {
        $data = json_decode($chartJson, true);
        $meta = $data['chart']['result'][0]['meta'] ?? null;
        if (is_array($meta)) {
            $name = trim((string) ($meta['shortName'] ?? $meta['longName'] ?? $meta['symbol'] ?? ''));
            if ($name !== '') {
                return ['ok' => true, 'name' => mb_substr($name, 0, 80, 'UTF-8'), 'symbol' => $symbol, 'source' => 'yahoo-chart'];
            }
        }
    }

    // 2) Taiwan Yahoo stock page
    if (preg_match('/\.(TW|TWO)$/i', $symbol) || preg_match('/^\d{4}$/', $symbol)) {
        $twSym = preg_match('/\.(TW|TWO)$/i', $symbol) ? $symbol : ($symbol . '.TW');
        $pageUrl = 'https://tw.stock.yahoo.com/quote/' . rawurlencode($twSym);
        $html = $fetch($pageUrl);
        if ($html !== '') {
            if (preg_match('/<title[^>]*>([^<]+)/iu', $html, $m)) {
                $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // e.g. "台積電 (2330.TW) ..."
                if (preg_match('/^(.+?)\s*[\(（]/u', $title, $tm)) {
                    $name = trim($tm[1]);
                    $name = preg_replace('/\s*[-|].*$/u', '', $name) ?? $name;
                    if ($name !== '' && !preg_match('/yahoo/i', $name)) {
                        return ['ok' => true, 'name' => mb_substr($name, 0, 80, 'UTF-8'), 'symbol' => $symbol, 'source' => 'yahoo-tw'];
                    }
                }
            }
            if (preg_match('/"symbolName"\s*:\s*"([^"]{1,80})"/u', $html, $sm)) {
                $name = trim($sm[1]);
                if ($name !== '') {
                    return ['ok' => true, 'name' => mb_substr($name, 0, 80, 'UTF-8'), 'symbol' => $symbol, 'source' => 'yahoo-tw-json'];
                }
            }
        }
    }

    // 3) Japan Yahoo
    if (preg_match('/\.T$/i', $symbol)) {
        $pageUrl = 'https://finance.yahoo.co.jp/quote/' . rawurlencode($symbol);
        $html = $fetch($pageUrl, ['Accept-Language: ja,ja-JP;q=0.9,en;q=0.5']);
        if ($html !== '' && preg_match('/<title[^>]*>([^<]+)/iu', $html, $m)) {
            $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('/^(.+?)\s*[\(（]/u', $title, $tm)) {
                $name = trim($tm[1]);
                if ($name !== '') {
                    return ['ok' => true, 'name' => mb_substr($name, 0, 80, 'UTF-8'), 'symbol' => $symbol, 'source' => 'yahoo-jp'];
                }
            }
        }
    }

    return ['ok' => false, 'name' => null, 'symbol' => $symbol, 'source' => null, 'error' => '無法解析名稱'];
}

function fengbroFinanceNormalizeCustomInstrument($input, int $index = 0): ?array
{
    if (!is_array($input)) {
        return null;
    }
    $symbol = strtoupper(trim((string) ($input['symbol'] ?? '')));
    if ($symbol === '' || strlen($symbol) > 32) {
        return null;
    }
    $provider = (($input['provider'] ?? 'cnbc') === 'yahoo') ? 'yahoo' : 'cnbc';
    $group = trim((string) ($input['group'] ?? 'US'));
    $allowedGroups = ['Taiwan', 'Asia', 'Korea', 'FX', 'Commodities', 'Rates', 'US', 'Crypto', 'Valuation'];
    if (!in_array($group, $allowedGroups, true)) {
        $group = 'US';
    }
    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        $name = $symbol;
    }
    $name = mb_substr($name, 0, 80, 'UTF-8');
    $idBase = fengbroFinanceSlugify($provider . '-' . $symbol) ?: ('custom-' . ($index + 1));
    $encoded = rawurlencode($symbol);
    if ($provider === 'yahoo') {
        $url = 'https://finance.yahoo.com/quote/' . $encoded;
        $parser = 'yahoo_quote';
        $source = 'Yahoo Finance';
        $apiSymbol = $symbol;
        $historySymbol = $symbol;
    } else {
        $url = 'https://www.cnbc.com/quotes/' . $encoded;
        $parser = 'cnbc';
        $source = 'CNBC';
        $apiSymbol = $symbol;
        $historySymbol = preg_match('/^[A-Z0-9.=^-]+$/', $symbol) ? $symbol : '';
    }
    $imageUrls = fengbroFinanceNormalizeImageUrls(
        !empty($input['imageUrls'])
            ? $input['imageUrls']
            : (!empty($input['imageUrl'])
                ? [$input['imageUrl']]
                : ($input['imageUrlsText'] ?? ($input['image_urls'] ?? '')))
    );
    $youtubeUrl = fengbroFinanceNormalizeHttpUrl($input['youtubeUrl'] ?? '', 800);
    if ($youtubeUrl !== null && !preg_match('#^https?://#i', $youtubeUrl)) {
        $youtubeUrl = null;
    }
    $bilibiliUrl = fengbroFinanceNormalizeHttpUrl($input['bilibiliUrl'] ?? '', 800);
    if ($bilibiliUrl !== null && !preg_match('#^https?://#i', $bilibiliUrl)) {
        $bilibiliUrl = null;
    }
    $relatedLinks = fengbroFinanceNormalizeRelatedLinks(
        !empty($input['relatedLinks'])
            ? $input['relatedLinks']
            : ($input['relatedLinksText'] ?? ($input['related_links'] ?? ''))
    );

    $id = 'custom-' . $idBase;
    $inputId = trim((string) ($input['id'] ?? ''));
    if ($inputId !== '' && str_starts_with($inputId, 'custom-') && strlen($inputId) <= 80) {
        $id = $inputId;
    }

    $out = [
        'id' => $id,
        'name' => $name,
        'symbol' => $symbol,
        'url' => $url,
        'group' => $group,
        'source' => $source,
        'parser' => $parser,
        'apiSymbol' => $apiSymbol,
        'historySymbol' => $historySymbol,
        'localLabel' => strtoupper($provider) . ': ' . $symbol,
        'isCustom' => true,
        'featured' => !empty($input['featured']),
        'provider' => $provider,
    ];
    if ($imageUrls) {
        $out['imageUrl'] = $imageUrls[0];
        $out['imageUrls'] = $imageUrls;
    }
    if ($youtubeUrl) {
        $out['youtubeUrl'] = $youtubeUrl;
    }
    if ($bilibiliUrl) {
        $out['bilibiliUrl'] = $bilibiliUrl;
    }
    if ($relatedLinks) {
        $out['relatedLinks'] = $relatedLinks;
    }
    return $out;
}

function fengbroFinanceSaveDefaultIds(array $ids): void
{
    $config = fengbroFinanceReadConfig();
    $allowed = array_flip(fengbroFinanceDefaultIds());
    $filtered = [];
    foreach ($ids as $id) {
        $id = trim((string) $id);
        if ($id !== '' && isset($allowed[$id])) {
            $filtered[] = $id;
        }
    }
    $config['defaultIds'] = $filtered ?: fengbroFinanceDefaultIds();
    fengbroFinanceWriteConfig($config);
}

function fengbroFinanceSaveCustomInstruments(array $custom): void
{
    $config = fengbroFinanceReadConfig();
    $normalized = [];
    foreach (array_slice($custom, 0, 30) as $index => $row) {
        $item = fengbroFinanceNormalizeCustomInstrument($row, (int) $index);
        if ($item) {
            $normalized[] = $item;
        }
    }
    $config['custom'] = $normalized;
    fengbroFinanceWriteConfig($config);
}

function fengbroFinanceResetConfig(): void
{
    $path = fengbroFinanceConfigPath();
    if (is_file($path)) {
        @unlink($path);
    }
    fengbroFinanceClearDataCache();
}

function fengbroFinanceActiveItems(): array
{
    $config = fengbroFinanceReadConfig();
    $selected = array_flip($config['defaultIds']);
    $imageById = $config['imageById'] ?? [];
    $items = [];
    foreach (fengbroFinanceDefaultItems() as $item) {
        if (!isset($selected[$item['id']])) {
            continue;
        }
        $id = $item['id'];
        if (!empty($imageById[$id])) {
            $urls = fengbroFinanceNormalizeImageUrls($imageById[$id]);
            if ($urls) {
                $item['imageUrl'] = $urls[0];
                $item['imageUrls'] = $urls;
            }
        }
        $items[] = $item;
    }
    foreach ($config['custom'] as $custom) {
        $id = (string) ($custom['id'] ?? '');
        if ($id !== '' && !empty($imageById[$id])) {
            $urls = fengbroFinanceNormalizeImageUrls($imageById[$id]);
            if ($urls) {
                $custom['imageUrl'] = $urls[0];
                $custom['imageUrls'] = $urls;
            }
        }
        $items[] = $custom;
    }
    return $items;
}

function fengbroFinanceReadCache()
{
    $path = fengbroFinanceCachePath();
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) @file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function fengbroFinanceWriteCache($cache)
{
    $path = fengbroFinanceCachePath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($path, json_encode($cache, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function fengbroFinanceFetchUrl($url, $timeout = 8)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 FengbroAI/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,application/json;q=0.8,*/*;q=0.7',
                'Accept-Language: zh-TW,zh;q=0.9,en;q=0.8',
            ],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ($status >= 200 && $status < 400 && is_string($body)) ? $body : '';
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) FengbroAI/1.0\r\nAccept-Language: zh-TW,zh;q=0.9,en;q=0.8\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    return is_string($body) ? $body : '';
}

function fengbroFinanceText($html)
{
    $text = preg_replace('/<[^>]+>/', ' ', (string) $html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return preg_replace('/\s+/u', ' ', $text);
}

function fengbroFinanceMetaContent($html, $name)
{
    $html = (string) $html;
    $name = preg_quote((string) $name, '/');
    if (preg_match('/<meta\b(?=[^>]*\bname=["\']' . $name . '["\'])(?=[^>]*\bcontent=["\']([^"\']*)["\'])[^>]*>/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match('/<meta\b(?=[^>]*\bcontent=["\']([^"\']*)["\'])(?=[^>]*\bname=["\']' . $name . '["\'])[^>]*>/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
}

function fengbroFinanceNumber($value)
{
    $clean = str_replace([',', '%', '−', '－'], ['', '', '-', '-'], (string) $value);
    return is_numeric($clean) ? (float) $clean : null;
}

function fengbroFinanceFormatNumber($value, $decimals = 2)
{
    if ($value === null || $value === '') {
        return '';
    }
    $number = (float) $value;
    $fixed = number_format($number, $decimals);
    return rtrim(rtrim($fixed, '0'), '.');
}

function fengbroFinanceFindNumber($text, $labels)
{
    foreach ((array) $labels as $label) {
        if (preg_match('/' . preg_quote($label, '/') . '\s*([+-]?\d[\d,]*(?:\.\d+)?%?)/u', $text, $m)) {
            return $m[1];
        }
    }
    return '';
}

function fengbroFinanceApplyBreakoutStatus($item, $valueNumber, $defaultStatus)
{
    $breakoutValue = $item['breakoutValue'] ?? null;
    if ($valueNumber !== null && is_numeric($breakoutValue) && $valueNumber >= (float) $breakoutValue) {
        return (string) ($item['breakoutLabel'] ?? $defaultStatus);
    }
    return $defaultStatus;
}

function fengbroFinanceFindMainValue($text, $label)
{
    if (preg_match('/' . preg_quote($label, '/') . '\s*\|\s*(.{0,220})/iu', $text, $m)) {
        $segment = $m[1];
        $segment = preg_replace('/^\s*\d{1,2}\/\d{1,2}\/\d{2,4}\s*(?:[A-Z]+)?\s*/u', '', $segment);
        $segment = preg_replace('/^\s*\d{1,2}:\d{2}\s*(?:AM|PM|上午|下午)?\s*(?:[A-Z]+)?\s*/iu', '', $segment);
        if (preg_match_all('/[+-]?\d[\d,]*(?:\.\d+)?%?/', $segment, $numbers)) {
            foreach ($numbers[0] as $number) {
                $pos = strpos($segment, $number);
                $after = $pos !== false ? substr($segment, $pos + strlen($number), 1) : '';
                $before = $pos !== false && $pos > 0 ? substr($segment, $pos - 1, 1) : '';
                if ($after === ':' || $after === '/' || $before === '/') {
                    continue;
                }
                return $number;
            }
        }
    }
    return fengbroFinanceFindNumber($text, [$label]);
}

function fengbroFinanceParseCnbcQuote($item)
{
    $html = fengbroFinanceFetchUrl($item['url']);
    $text = fengbroFinanceText($html);
    $valueLabel = $item['symbol'] === 'US30Y' ? 'Yield' : 'Last';
    $value = fengbroFinanceFindMainValue($text, $valueLabel);

    $change = '';
    $changePercent = '';
    if ($value !== '') {
        $valuePos = strpos($text, $value);
        $tail = $valuePos !== false ? substr($text, $valuePos + strlen($value), 180) : '';
        if (preg_match('/([+-]\d[\d,]*(?:\.\d+)?)\s*\(([+-]?\d[\d,]*(?:\.\d+)?%)\)/', $tail, $m)) {
            $change = $m[1];
            $changePercent = $m[2];
        } elseif (preg_match('/([+-]\d[\d,]*(?:\.\d+)?)/', $tail, $m)) {
            $change = $m[1];
        }
    }

    $high52 = fengbroFinanceFindNumber($text, ['52 Week High']);
    $low52 = fengbroFinanceFindNumber($text, ['52 Week Low']);
    $open = fengbroFinanceFindNumber($text, ['Open', 'Yield Open']);
    $dayHigh = fengbroFinanceFindNumber($text, ['Day High', 'Yield Day High']);
    $dayLow = fengbroFinanceFindNumber($text, ['Day Low', 'Yield Day Low']);
    $prevClose = fengbroFinanceFindNumber($text, ['Prev Close', 'Yield Prev Close']);

    $status = '';
    $valueNumber = fengbroFinanceNumber($value);
    $highNumber = fengbroFinanceNumber($high52);
    $lowNumber = fengbroFinanceNumber($low52);
    if ($valueNumber !== null && $highNumber !== null && $valueNumber >= $highNumber) {
        $status = '創新高';
    } elseif ($valueNumber !== null && $lowNumber !== null && $valueNumber <= $lowNumber) {
        $status = '創新低';
    }
    $status = fengbroFinanceApplyBreakoutStatus($item, $valueNumber, $status);

    return [
        'name' => $item['name'],
        'symbol' => $item['symbol'],
        'group' => $item['group'],
        'source' => $item['source'] ?? 'CNBC',
        'url' => $item['url'],
        'valueLabel' => $valueLabel,
        'value' => $value,
        'change' => $change,
        'changePercent' => $changePercent,
        'open' => $open,
        'dayHigh' => $dayHigh,
        'dayLow' => $dayLow,
        'prevClose' => $prevClose,
        'high52' => $high52,
        'low52' => $low52,
        'status' => $status,
        'error' => $value === '' ? '暫時抓不到 CNBC 報價' : '',
    ];
}

function fengbroFinanceFindTwNumber($text, $labels)
{
    foreach ((array) $labels as $label) {
        if (preg_match('/' . preg_quote($label, '/') . '\s*([+-]?\d[\d,]*(?:\.\d+)?%?)/u', $text, $m)) {
            return $m[1];
        }
    }
    return '';
}

function fengbroFinanceYahooChart($symbol, $range = '1y', $interval = '1d')
{
    if (!$symbol) {
        return [];
    }

    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($symbol)
        . '?range=' . rawurlencode($range)
        . '&interval=' . rawurlencode($interval)
        . '&lang=zh-TW&region=TW';
    $json = fengbroFinanceFetchUrl($url, 12);
    $data = json_decode($json, true);
    $result = $data['chart']['result'][0] ?? null;
    if (!$result) {
        return [];
    }

    $meta = $result['meta'] ?? [];
    $quote = $result['indicators']['quote'][0] ?? [];
    $highs = array_values(array_filter($quote['high'] ?? [], 'is_numeric'));
    $lows = array_values(array_filter($quote['low'] ?? [], 'is_numeric'));
    $closes = array_values(array_filter($quote['close'] ?? [], 'is_numeric'));
    $timestamps = $result['timestamp'] ?? [];
    $marketPrice = $meta['regularMarketPrice'] ?? null;
    $prevClose = $meta['regularMarketPreviousClose'] ?? null;
    if ($prevClose === null && $closes) {
        $lastIndex = count($closes) - 1;
        $lastClose = (float) $closes[$lastIndex];
        if ($marketPrice !== null && abs($lastClose - (float) $marketPrice) < 0.01 && $lastIndex > 0) {
            $prevClose = $closes[$lastIndex - 1];
        } else {
            $prevClose = $lastClose;
        }
    }

    $points = [];
    if (is_array($timestamps) && is_array($quote['close'] ?? null)) {
        foreach ($timestamps as $i => $ts) {
            $close = $quote['close'][$i] ?? null;
            if (!is_numeric($close) || !is_numeric($ts)) {
                continue;
            }
            $points[] = [
                'date' => date('Y-m-d', (int) $ts),
                'price' => (float) $close,
            ];
        }
    }

    return [
        'value' => $marketPrice,
        'open' => $meta['regularMarketOpen'] ?? null,
        'dayHigh' => $meta['regularMarketDayHigh'] ?? null,
        'dayLow' => $meta['regularMarketDayLow'] ?? null,
        'prevClose' => $prevClose,
        'high52' => $meta['fiftyTwoWeekHigh'] ?? ($highs ? max($highs) : null),
        'low52' => $meta['fiftyTwoWeekLow'] ?? ($lows ? min($lows) : null),
        'points' => $points,
    ];
}

function fengbroFinanceHistoryRanges()
{
    // 頁面初次只抓 1y；5y/10y 改由 tools_api finance_history 懶載入
    return [
        ['key' => '1y', 'range' => '1y', 'interval' => '1wk'],
    ];
}

function fengbroFinanceAllHistoryRanges()
{
    return [
        ['key' => '1y', 'range' => '1y', 'interval' => '1wk', 'label' => '近一年'],
        ['key' => '5y', 'range' => '5y', 'interval' => '1mo', 'label' => '近五年'],
        ['key' => '10y', 'range' => '10y', 'interval' => '1mo', 'label' => '近十年'],
    ];
}

/**
 * 依 Yahoo 代碼抓取單一歷史區間（供 AJAX 使用）。
 */
function fengbroFinanceFetchSingleHistory(string $symbol, string $rangeKey = '1y'): array
{
    $symbol = trim($symbol);
    $ranges = [];
    foreach (fengbroFinanceAllHistoryRanges() as $range) {
        $ranges[$range['key']] = $range;
    }
    if ($symbol === '' || !isset($ranges[$rangeKey])) {
        return ['points' => [], 'error' => '無效的代碼或區間'];
    }
    $range = $ranges[$rangeKey];
    try {
        $chart = fengbroFinanceYahooChart($symbol, $range['range'], $range['interval']);
        $points = $chart['points'] ?? [];
        return [
            'points' => $points,
            'error' => $points ? '' : '無歷史資料',
            'range' => $rangeKey,
            'symbol' => $symbol,
            'label' => $range['label'],
        ];
    } catch (Throwable $e) {
        return ['points' => [], 'error' => $e->getMessage(), 'range' => $rangeKey, 'symbol' => $symbol];
    }
}

function fengbroFinanceHistorySymbol(array $item): string
{
    if (!empty($item['historySymbol'])) {
        return (string) $item['historySymbol'];
    }
    if (($item['parser'] ?? '') === 'yahoo_quote' || ($item['parser'] ?? '') === 'yahoo_tw') {
        return (string) ($item['apiSymbol'] ?? $item['symbol'] ?? '');
    }
    $symbol = (string) ($item['symbol'] ?? '');
    return preg_match('/^[A-Z0-9.=^-]+$/', $symbol) ? $symbol : '';
}

function fengbroFinanceFetchHistoryRanges(array $item): array
{
    $symbol = fengbroFinanceHistorySymbol($item);
    $historyRanges = [];
    $historyErrors = [];
    if ($symbol === '') {
        return ['historyRanges' => $historyRanges, 'historyErrors' => $historyErrors];
    }
    foreach (fengbroFinanceHistoryRanges() as $range) {
        try {
            $chart = fengbroFinanceYahooChart($symbol, $range['range'], $range['interval']);
            $historyRanges[$range['key']] = $chart['points'] ?? [];
            if (empty($historyRanges[$range['key']])) {
                $historyErrors[$range['key']] = '無歷史資料';
            }
        } catch (Throwable $e) {
            $historyRanges[$range['key']] = [];
            $historyErrors[$range['key']] = $e->getMessage();
        }
    }
    return ['historyRanges' => $historyRanges, 'historyErrors' => $historyErrors];
}

function fengbroFinanceEnrichQuote(array $quote, array $item, bool $withHistory = true): array
{
    $quote['id'] = $item['id'] ?? ($item['symbol'] ?? '');
    $quote['localLabel'] = $item['localLabel'] ?? '';
    $quote['isCustom'] = !empty($item['isCustom']);
    $quote['historySymbol'] = fengbroFinanceHistorySymbol($item);
    $imageUrls = fengbroFinanceResolveImageUrls($item);
    if ($imageUrls) {
        $quote['imageUrl'] = $imageUrls[0];
        $quote['imageUrls'] = $imageUrls;
    } else {
        $quote['imageUrl'] = '';
        $quote['imageUrls'] = [];
    }
    $youtubeUrl = fengbroFinanceNormalizeHttpUrl($item['youtubeUrl'] ?? '', 800);
    $quote['youtubeUrl'] = ($youtubeUrl && preg_match('#^https?://#i', $youtubeUrl)) ? $youtubeUrl : '';
    $bilibiliUrl = fengbroFinanceNormalizeHttpUrl($item['bilibiliUrl'] ?? '', 800);
    $quote['bilibiliUrl'] = ($bilibiliUrl && preg_match('#^https?://#i', $bilibiliUrl)) ? $bilibiliUrl : '';
    $quote['relatedLinks'] = fengbroFinanceNormalizeRelatedLinks($item['relatedLinks'] ?? []);
    if ($withHistory) {
        $history = fengbroFinanceFetchHistoryRanges($item);
        $quote['historyRanges'] = $history['historyRanges'];
        $quote['historyErrors'] = $history['historyErrors'];
    } else {
        $quote['historyRanges'] = [];
        $quote['historyErrors'] = [];
    }
    return $quote;
}

function fengbroFinanceParseYahooTwQuote($item)
{
    $html = fengbroFinanceFetchUrl($item['url']);
    $text = fengbroFinanceText($html);
    $chart = fengbroFinanceYahooChart($item['apiSymbol'] ?? $item['symbol']);

    $value = '';
    if (($item['symbol'] ?? '') === '2330.TW') {
        $value = fengbroFinanceFindTwNumber($text, ['成交']);
    } elseif (preg_match('/加權指數\s+([+-]?\d[\d,]*(?:\.\d+)?)/u', $text, $m)) {
        $value = $m[1] !== '-' ? $m[1] : '';
    }
    if ($value === '' && isset($chart['value'])) {
        $value = fengbroFinanceFormatNumber($chart['value'], 2);
    }

    $open = fengbroFinanceFindTwNumber($text, ['開盤']);
    $dayHigh = fengbroFinanceFindTwNumber($text, ['最高']);
    $dayLow = fengbroFinanceFindTwNumber($text, ['最低']);
    $prevClose = fengbroFinanceFindTwNumber($text, ['昨收']);
    $change = fengbroFinanceFindTwNumber($text, ['漲跌']);
    $changePercent = fengbroFinanceFindTwNumber($text, ['漲跌幅']);

    if ($open === '' && isset($chart['open'])) {
        $open = fengbroFinanceFormatNumber($chart['open'], 2);
    }
    if ($dayHigh === '' && isset($chart['dayHigh'])) {
        $dayHigh = fengbroFinanceFormatNumber($chart['dayHigh'], 2);
    }
    if ($dayLow === '' && isset($chart['dayLow'])) {
        $dayLow = fengbroFinanceFormatNumber($chart['dayLow'], 2);
    }
    if ($prevClose === '' && isset($chart['prevClose'])) {
        $prevClose = fengbroFinanceFormatNumber($chart['prevClose'], 2);
    }

    $valueNumber = fengbroFinanceNumber($value);
    $prevCloseNumber = fengbroFinanceNumber($prevClose);
    if ($change === '' && $valueNumber !== null && $prevCloseNumber !== null) {
        $change = fengbroFinanceFormatNumber($valueNumber - $prevCloseNumber, 2);
    }
    if ($changePercent === '' && $valueNumber !== null && $prevCloseNumber !== null && $prevCloseNumber != 0.0) {
        $changePercent = fengbroFinanceFormatNumber((($valueNumber - $prevCloseNumber) / $prevCloseNumber) * 100, 2) . '%';
    }

    $high52 = isset($chart['high52']) ? fengbroFinanceFormatNumber($chart['high52'], 2) : '';
    $low52 = isset($chart['low52']) ? fengbroFinanceFormatNumber($chart['low52'], 2) : '';
    $highNumber = fengbroFinanceNumber($high52);
    $lowNumber = fengbroFinanceNumber($low52);
    $status = '';
    if ($valueNumber !== null && $highNumber !== null && $valueNumber >= $highNumber) {
        $status = '創新高';
    } elseif ($valueNumber !== null && $lowNumber !== null && $valueNumber <= $lowNumber) {
        $status = '創新低';
    }
    $status = fengbroFinanceApplyBreakoutStatus($item, $valueNumber, $status);

    return [
        'name' => $item['name'],
        'symbol' => $item['symbol'],
        'group' => $item['group'],
        'source' => $item['source'] ?? 'Yahoo股市',
        'url' => $item['url'],
        'valueLabel' => '成交',
        'value' => $value,
        'change' => $change,
        'changePercent' => $changePercent,
        'open' => $open,
        'dayHigh' => $dayHigh,
        'dayLow' => $dayLow,
        'prevClose' => $prevClose,
        'high52' => $high52,
        'low52' => $low52,
        'status' => $status,
        'error' => $value === '' ? '暫時抓不到 Yahoo 台股報價' : '',
    ];
}

function fengbroFinanceParseYahooQuote($item)
{
    $chart = fengbroFinanceYahooChart($item['apiSymbol'] ?? $item['symbol']);
    $value = isset($chart['value']) ? fengbroFinanceFormatNumber($chart['value'], 2) : '';
    $open = isset($chart['open']) ? fengbroFinanceFormatNumber($chart['open'], 2) : '';
    $dayHigh = isset($chart['dayHigh']) ? fengbroFinanceFormatNumber($chart['dayHigh'], 2) : '';
    $dayLow = isset($chart['dayLow']) ? fengbroFinanceFormatNumber($chart['dayLow'], 2) : '';
    $prevClose = isset($chart['prevClose']) ? fengbroFinanceFormatNumber($chart['prevClose'], 2) : '';
    $high52 = isset($chart['high52']) ? fengbroFinanceFormatNumber($chart['high52'], 2) : '';
    $low52 = isset($chart['low52']) ? fengbroFinanceFormatNumber($chart['low52'], 2) : '';

    $valueNumber = fengbroFinanceNumber($value);
    $prevCloseNumber = fengbroFinanceNumber($prevClose);
    $change = '';
    $changePercent = '';
    if ($valueNumber !== null && $prevCloseNumber !== null) {
        $change = fengbroFinanceFormatNumber($valueNumber - $prevCloseNumber, 2);
    }
    if ($valueNumber !== null && $prevCloseNumber !== null && $prevCloseNumber != 0.0) {
        $changePercent = fengbroFinanceFormatNumber((($valueNumber - $prevCloseNumber) / $prevCloseNumber) * 100, 2) . '%';
    }

    $highNumber = fengbroFinanceNumber($high52);
    $lowNumber = fengbroFinanceNumber($low52);
    $status = '';
    if ($valueNumber !== null && $highNumber !== null && $valueNumber >= $highNumber) {
        $status = '創新高';
    } elseif ($valueNumber !== null && $lowNumber !== null && $valueNumber <= $lowNumber) {
        $status = '創新低';
    }
    $status = fengbroFinanceApplyBreakoutStatus($item, $valueNumber, $status);

    return [
        'name' => $item['name'],
        'symbol' => $item['symbol'],
        'group' => $item['group'],
        'source' => $item['source'] ?? 'Yahoo Finance',
        'url' => $item['url'],
        'valueLabel' => '成交',
        'value' => $value,
        'change' => $change,
        'changePercent' => $changePercent,
        'open' => $open,
        'dayHigh' => $dayHigh,
        'dayLow' => $dayLow,
        'prevClose' => $prevClose,
        'high52' => $high52,
        'low52' => $low52,
        'status' => $status,
        'error' => $value === '' ? '暫時抓不到 Yahoo 報價' : '',
    ];
}

function fengbroFinanceParseMultplShiller($item)
{
    $html = fengbroFinanceFetchUrl($item['url']);
    $text = fengbroFinanceText($html);
    $description = fengbroFinanceMetaContent($html, 'description');
    $currentText = '';
    if (preg_match('/<div\s+id=["\']current["\'][^>]*>(.*?)<\/div>/is', $html, $m)) {
        $currentText = fengbroFinanceText($m[1]);
    }
    $searchText = trim($description . ' ' . $currentText . ' ' . $text);
    $value = '';
    $change = '';
    $changePercent = '';

    $patterns = [
        '/Current\s+Shiller\s+PE\s+Ratio\s+is\s*([+-]?\d[\d,]*(?:\.\d+)?)/iu',
        '/Current\s+Shiller\s+PE\s+Ratio\s*:\s*([+-]?\d[\d,]*(?:\.\d+)?)/iu',
        '/Current\s+S&P\s*500\s+Shiller\s+CAPE\s+Ratio\s+(?:is|:)\s*([+-]?\d[\d,]*(?:\.\d+)?)/iu',
        '/current level of\s*([+-]?\d[\d,]*(?:\.\d+)?)/iu',
        '/Shiller\s+PE\s+Ratio\s*(?:is|:|\|)\s*([+-]?\d[\d,]*(?:\.\d+)?)/iu',
        '/S&P\s*500\s+Shiller\s+CAPE\s+Ratio\s*(?:is|:|\|)\s*([+-]?\d[\d,]*(?:\.\d+)?)/iu',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $searchText, $m)) {
            $value = $m[1];
            break;
        }
    }

    if ($value === '' && $currentText !== '' && preg_match('/:\s*([+-]?\d[\d,]*(?:\.\d+)?)/', $currentText, $m)) {
        $value = $m[1];
    }

    if ($value !== '') {
        $changeText = trim($currentText . ' ' . $description);
        if (preg_match('/' . preg_quote($value, '/') . '\s*([+-]\d[\d,]*(?:\.\d+)?)\s*\(([+-]?\d[\d,]*(?:\.\d+)?%)\)/', $changeText, $m)) {
            $change = $m[1];
            $changePercent = $m[2];
        }
    }

    $valueNumber = fengbroFinanceNumber($value);
    $recordHigh = (float) ($item['recordHigh'] ?? 44.19);
    $status = ($valueNumber !== null && $valueNumber >= $recordHigh) ? '創新高' : '';
    $status = fengbroFinanceApplyBreakoutStatus($item, $valueNumber, $status);

    return [
        'name' => $item['name'],
        'symbol' => $item['symbol'],
        'group' => $item['group'],
        'source' => $item['source'] ?? 'Multpl',
        'url' => $item['url'],
        'valueLabel' => 'Ratio',
        'value' => $value,
        'change' => $change,
        'changePercent' => $changePercent,
        'open' => '',
        'dayHigh' => '',
        'dayLow' => '',
        'prevClose' => '',
        'high52' => 'Max: ' . fengbroFinanceFormatNumber($recordHigh, 2) . ' (' . ($item['recordHighDate'] ?? 'Dec 1999') . ')',
        'low52' => '',
        'status' => $status,
        'error' => $value === '' ? '無法讀取 Multpl Shiller PE Ratio' : '',
    ];
}

function fengbroFinanceParseQuote($item, bool $withHistory = true)
{
    if (($item['parser'] ?? 'cnbc') === 'yahoo_tw') {
        $quote = fengbroFinanceParseYahooTwQuote($item);
    } elseif (($item['parser'] ?? 'cnbc') === 'yahoo_quote') {
        $quote = fengbroFinanceParseYahooQuote($item);
    } elseif (($item['parser'] ?? 'cnbc') === 'multpl_shiller') {
        $quote = fengbroFinanceParseMultplShiller($item);
    } else {
        $quote = fengbroFinanceParseCnbcQuote($item);
    }

    return fengbroFinanceEnrichQuote($quote, $item, $withHistory);
}

/**
 * @param bool $force 強制重新抓取
 * @param bool $withHistory 是否附帶 Yahoo 1Y 走勢（首頁提醒可關閉以加速）
 */
function fengbroFinanceGetData($force = false, bool $withHistory = true)
{
    $cache = fengbroFinanceReadCache();
    $config = fengbroFinanceReadConfig();
    $configFingerprint = md5(json_encode([
        'defaultIds' => $config['defaultIds'],
        'custom' => array_map(static fn($c) => $c['id'] ?? '', $config['custom']),
        'imageById' => $config['imageById'] ?? [],
        'history' => $withHistory ? 1 : 0,
    ], JSON_UNESCAPED_UNICODE));
    $dataKey = 'finance_data_v5:' . $configFingerprint;
    if (!$force && !empty($cache[$dataKey]['checkedAt']) && time() - (int) $cache[$dataKey]['checkedAt'] < 900) {
        return $cache[$dataKey]['value'];
    }

    $quotes = [];
    foreach (fengbroFinanceActiveItems() as $item) {
        $quotes[] = fengbroFinanceParseQuote($item, $withHistory);
    }

    $data = [
        'checkedAt' => date('Y-m-d H:i:s'),
        'quotes' => $quotes,
        'source' => 'CNBC / Yahoo股市 / Multpl',
        'config' => $config,
        'defaultCatalog' => array_map(static function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'symbol' => $item['symbol'],
                'group' => $item['group'],
            ];
        }, fengbroFinanceDefaultItems()),
    ];
    $cache[$dataKey] = ['checkedAt' => time(), 'value' => $data];
    fengbroFinanceWriteCache($cache);
    return $data;
}
