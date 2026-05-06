<?php

function fengbroFinanceItems()
{
    return [
        ['name' => 'Nikkei 225 Index', 'symbol' => '.N225', 'url' => 'https://www.cnbc.com/quotes/.N225', 'group' => 'Asia'],
        ['name' => 'KOSPI Index', 'symbol' => '.KS11', 'url' => 'https://www.cnbc.com/quotes/.KS11?qsearchterm=kospi', 'group' => 'Asia'],
        ['name' => 'ICE Brent Crude', 'symbol' => '@LCO.1', 'url' => 'https://www.cnbc.com/quotes/@LCO.1', 'group' => 'Commodities'],
        ['name' => 'U.S. 30 Year Treasury', 'symbol' => 'US30Y', 'url' => 'https://www.cnbc.com/quotes/US30Y', 'group' => 'Rates'],
        ['name' => 'Gold COMEX', 'symbol' => '@GC.1', 'url' => 'https://www.cnbc.com/quotes/@GC.1', 'group' => 'Commodities'],
        ['name' => 'Dow Jones Industrial Average', 'symbol' => '.DJI', 'url' => 'https://www.cnbc.com/quotes/.DJI', 'group' => 'US Index'],
        ['name' => 'S&P 500 Index', 'symbol' => '.SPX', 'url' => 'https://www.cnbc.com/quotes/.SPX', 'group' => 'US Index'],
        ['name' => 'NASDAQ Composite', 'symbol' => '.IXIC', 'url' => 'https://www.cnbc.com/quotes/.IXIC', 'group' => 'US Index'],
        ['name' => 'CBOE Volatility Index', 'symbol' => 'VIX', 'url' => 'https://www.cnbc.com/quotes/VIX', 'group' => 'Volatility'],
        ['name' => 'Bitcoin/USD Coin Metrics', 'symbol' => 'BTC.CM=', 'url' => 'https://www.cnbc.com/quotes/BTC.CM=', 'group' => 'Crypto'],
        ['name' => 'Ether/USD Coin Metrics', 'symbol' => 'ETH.CM=', 'url' => 'https://www.cnbc.com/quotes/ETH.CM=', 'group' => 'Crypto'],
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
            CURLOPT_USERAGENT => 'Mozilla/5.0 FengbroAI/1.0',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ($status >= 200 && $status < 400 && is_string($body)) ? $body : '';
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'header' => "User-Agent: Mozilla/5.0 FengbroAI/1.0\r\n",
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

function fengbroFinanceNumber($value)
{
    $clean = str_replace([',', '%'], '', (string) $value);
    return is_numeric($clean) ? (float) $clean : null;
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

function fengbroFinanceParseQuote($item)
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

    return [
        'name' => $item['name'],
        'symbol' => $item['symbol'],
        'group' => $item['group'],
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

function fengbroFinanceGetData($force = false)
{
    $cache = fengbroFinanceReadCache();
    $dataKey = 'finance_data';
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
        'source' => 'CNBC',
    ];
    $cache[$dataKey] = ['checkedAt' => time(), 'value' => $data];
    fengbroFinanceWriteCache($cache);
    return $data;
}
