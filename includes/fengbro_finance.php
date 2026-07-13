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
        ];
    }
    $data = json_decode((string) @file_get_contents($path), true);
    if (!is_array($data)) {
        return [
            'defaultIds' => fengbroFinanceDefaultIds(),
            'custom' => [],
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
    return ['defaultIds' => array_values(array_unique($defaultIds)), 'custom' => $custom];
}

function fengbroFinanceWriteConfig(array $config)
{
    $path = fengbroFinanceConfigPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($path, json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    fengbroFinanceClearDataCache();
}

function fengbroFinanceClearDataCache()
{
    $cache = fengbroFinanceReadCache();
    unset($cache['finance_data_v3'], $cache['finance_data_v4'], $cache['finance_data_v5']);
    fengbroFinanceWriteCache($cache);
}

function fengbroFinanceSlugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
    return substr(trim($value, '-'), 0, 48);
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
    return [
        'id' => 'custom-' . $idBase,
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
    ];
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
    $items = [];
    foreach (fengbroFinanceDefaultItems() as $item) {
        if (isset($selected[$item['id']])) {
            $items[] = $item;
        }
    }
    foreach ($config['custom'] as $custom) {
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
