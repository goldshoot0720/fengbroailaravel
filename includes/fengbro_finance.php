<?php

function fengbroFinanceItems()
{
    return [
        ['name' => '加權指數', 'symbol' => '^TWII', 'url' => 'https://tw.stock.yahoo.com/s/tse.php', 'group' => 'Taiwan', 'source' => 'Yahoo股市', 'parser' => 'yahoo_tw', 'apiSymbol' => '^TWII', 'breakoutValue' => 126820, 'breakoutLabel' => '突破 126820'],
        ['name' => '台積電', 'symbol' => '2330.TW', 'url' => 'https://tw.stock.yahoo.com/quote/2330.TW', 'group' => 'Taiwan', 'source' => 'Yahoo股市', 'parser' => 'yahoo_tw', 'apiSymbol' => '2330.TW', 'breakoutValue' => 3333, 'breakoutLabel' => '突破 3333'],
        ['name' => '美元對台幣匯率', 'symbol' => 'USD/TWD', 'url' => 'https://www.cnbc.com/quotes/USDTWD=', 'group' => 'FX', 'source' => 'CNBC', 'parser' => 'cnbc', 'breakoutValue' => 37, 'breakoutLabel' => '突破 37'],
        ['name' => '美元對日元匯率', 'symbol' => 'USD/JPY', 'url' => 'https://www.cnbc.com/quotes/USDJPY=', 'group' => 'FX', 'source' => 'CNBC', 'parser' => 'cnbc', 'breakoutValue' => 222, 'breakoutLabel' => '突破 222'],
        ['name' => 'Nikkei 225 Index', 'symbol' => '.N225', 'url' => 'https://www.cnbc.com/quotes/.N225', 'group' => 'Asia', 'source' => 'CNBC', 'parser' => 'cnbc', 'breakoutValue' => 110000, 'breakoutLabel' => '突破 110000'],
        ['name' => 'KOSPI Index', 'symbol' => '.KS11', 'url' => 'https://www.cnbc.com/quotes/.KS11?qsearchterm=kospi', 'group' => 'Asia', 'source' => 'CNBC', 'parser' => 'cnbc', 'breakoutValue' => 12682, 'breakoutLabel' => '突破 12682'],
        ['name' => '三星電子', 'symbol' => '005930.KS', 'url' => 'https://tw.stock.yahoo.com/quote/005930.KS', 'group' => 'Korea', 'source' => 'Yahoo股市', 'parser' => 'yahoo_quote', 'apiSymbol' => '005930.KS', 'breakoutValue' => 1110000, 'breakoutLabel' => '突破 1110000'],
        ['name' => 'SK 海力士', 'symbol' => '000660.KS', 'url' => 'https://tw.stock.yahoo.com/quote/000660.KS', 'group' => 'Korea', 'source' => 'Yahoo股市', 'parser' => 'yahoo_quote', 'apiSymbol' => '000660.KS', 'breakoutValue' => 11110000, 'breakoutLabel' => '突破 11110000'],
        ['name' => 'ICE Brent Crude', 'symbol' => '@LCO.1', 'url' => 'https://www.cnbc.com/quotes/@LCO.1', 'group' => 'Commodities', 'source' => 'CNBC', 'parser' => 'cnbc', 'breakoutValue' => 222, 'breakoutLabel' => '突破 222'],
        ['name' => 'U.S. 30 Year Treasury', 'symbol' => 'US30Y', 'url' => 'https://www.cnbc.com/quotes/US30Y', 'group' => 'Rates', 'source' => 'CNBC', 'parser' => 'cnbc', 'breakoutValue' => 6.66, 'breakoutLabel' => '突破 6.66'],
        ['name' => 'Gold COMEX', 'symbol' => '@GC.1', 'url' => 'https://www.cnbc.com/quotes/@GC.1', 'group' => 'Commodities', 'source' => 'CNBC', 'parser' => 'cnbc', 'breakoutValue' => 6666, 'breakoutLabel' => '突破 6666'],
        ['name' => 'Dow Jones Industrial Average', 'symbol' => '.DJI', 'url' => 'https://www.cnbc.com/quotes/.DJI', 'group' => 'US Index', 'source' => 'CNBC', 'parser' => 'cnbc', 'breakoutValue' => 66666, 'breakoutLabel' => '突破 66666'],
        ['name' => 'S&P 500 Index', 'symbol' => '.SPX', 'url' => 'https://www.cnbc.com/quotes/.SPX', 'group' => 'US Index', 'source' => 'CNBC', 'parser' => 'cnbc', 'breakoutValue' => 11111, 'breakoutLabel' => '突破 11111'],
        ['name' => 'NASDAQ Composite', 'symbol' => '.IXIC', 'url' => 'https://www.cnbc.com/quotes/.IXIC', 'group' => 'US Index', 'source' => 'CNBC', 'parser' => 'cnbc', 'breakoutValue' => 33333, 'breakoutLabel' => '突破 33333'],
        ['name' => 'CBOE Volatility Index', 'symbol' => 'VIX', 'url' => 'https://www.cnbc.com/quotes/VIX', 'group' => 'Volatility', 'source' => 'CNBC', 'parser' => 'cnbc'],
        ['name' => 'Shiller PE Ratio', 'symbol' => 'SHILLER_PE', 'url' => 'https://www.multpl.com/shiller-pe', 'group' => 'Valuation', 'source' => 'Multpl', 'parser' => 'multpl_shiller', 'recordHigh' => 45, 'recordHighDate' => 'Threshold', 'breakoutValue' => 45, 'breakoutLabel' => '突破 45'],
        ['name' => 'Bitcoin/USD Coin Metrics', 'symbol' => 'BTC.CM=', 'url' => 'https://www.cnbc.com/quotes/BTC.CM=', 'group' => 'Crypto', 'source' => 'CNBC', 'parser' => 'cnbc', 'breakoutValue' => 111111, 'breakoutLabel' => '突破 111111'],
        ['name' => 'Ether/USD Coin Metrics', 'symbol' => 'ETH.CM=', 'url' => 'https://www.cnbc.com/quotes/ETH.CM=', 'group' => 'Crypto', 'source' => 'CNBC', 'parser' => 'cnbc', 'breakoutValue' => 2222, 'breakoutLabel' => '突破 2222'],
    ];
}

function fengbroFinanceCachePath()
{
    return __DIR__ . '/../uploads/temp/fengbro_finance_cache.json';
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

function fengbroFinanceYahooChart($symbol)
{
    if (!$symbol) {
        return [];
    }

    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($symbol) . '?range=1y&interval=1d';
    $json = fengbroFinanceFetchUrl($url);
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

    return [
        'value' => $marketPrice,
        'open' => $meta['regularMarketOpen'] ?? null,
        'dayHigh' => $meta['regularMarketDayHigh'] ?? null,
        'dayLow' => $meta['regularMarketDayLow'] ?? null,
        'prevClose' => $prevClose,
        'high52' => $meta['fiftyTwoWeekHigh'] ?? ($highs ? max($highs) : null),
        'low52' => $meta['fiftyTwoWeekLow'] ?? ($lows ? min($lows) : null),
    ];
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

function fengbroFinanceParseQuote($item)
{
    if (($item['parser'] ?? 'cnbc') === 'yahoo_tw') {
        return fengbroFinanceParseYahooTwQuote($item);
    }
    if (($item['parser'] ?? 'cnbc') === 'yahoo_quote') {
        return fengbroFinanceParseYahooQuote($item);
    }
    if (($item['parser'] ?? 'cnbc') === 'multpl_shiller') {
        return fengbroFinanceParseMultplShiller($item);
    }

    return fengbroFinanceParseCnbcQuote($item);
}

function fengbroFinanceGetData($force = false)
{
    $cache = fengbroFinanceReadCache();
    $dataKey = 'finance_data_v3';
    if (!$force && !empty($cache[$dataKey]['checkedAt']) && time() - (int) $cache[$dataKey]['checkedAt'] < 900) {
        return $cache[$dataKey]['value'];
    }

    $quotes = [];
    foreach (fengbroFinanceItems() as $item) {
        $quotes[] = fengbroFinanceParseQuote($item);
    }

    $data = [
        'checkedAt' => date('Y-m-d H:i:s'),
        'quotes' => $quotes,
        'source' => 'CNBC / Yahoo股市 / Multpl',
    ];
    $cache[$dataKey] = ['checkedAt' => time(), 'value' => $data];
    fengbroFinanceWriteCache($cache);
    return $data;
}
