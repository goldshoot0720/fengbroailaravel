<?php
/**
 * 鋒兄新聞：對齊 fengbroaiappwrite / fengbroaisupabase 的多來源關鍵字搜尋。
 * 策略：Google News RSS（site:）為主，必要時掃描站內搜尋頁連結；YouTube 用頻道頁標題比對。
 */

function fengbroNewsUserAgent(): string
{
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
}

function fengbroNewsFetchText(string $url, int $timeout = 8, array $extraHeaders = []): array
{
    $headers = array_merge([
        'User-Agent: ' . fengbroNewsUserAgent(),
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,text/plain,*/*;q=0.8',
        'Accept-Language: zh-TW,zh;q=0.9,en;q=0.8',
        'Cache-Control: no-cache',
    ], $extraHeaders);

    try {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if (str_contains($host, 'ptt.cc')) {
            $headers[] = 'Cookie: over18=1';
            $headers[] = 'Referer: https://www.ptt.cc/';
        }
    } catch (Throwable $e) {
        // ignore
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $err = curl_error($ch);
        curl_close($ch);
        if (!is_string($body) || $body === '') {
            return ['ok' => false, 'status' => $status, 'text' => '', 'finalUrl' => $url, 'error' => $err ?: 'empty'];
        }
        return [
            'ok' => $status >= 200 && $status < 400,
            'status' => $status,
            'text' => $body,
            'finalUrl' => $finalUrl ?: $url,
            'error' => ($status >= 200 && $status < 400) ? null : ('HTTP ' . $status),
        ];
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers) . "\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if (!is_string($body)) {
        return ['ok' => false, 'status' => 0, 'text' => '', 'finalUrl' => $url, 'error' => 'fetch failed'];
    }
    return ['ok' => true, 'status' => 200, 'text' => $body, 'finalUrl' => $url, 'error' => null];
}

function fengbroNewsDecodeHtml(string $value): string
{
    return html_entity_decode(
        str_replace(['&nbsp;'], [' '], $value),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
}

function fengbroNewsNormalizeSpace(string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function fengbroNewsStripTags(string $value): string
{
    return fengbroNewsNormalizeSpace(fengbroNewsDecodeHtml(strip_tags($value)));
}

function fengbroNewsNormalizeDomain(string $input): string
{
    $raw = strtolower(trim($input));
    if ($raw === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $raw)) {
        $host = parse_url($raw, PHP_URL_HOST);
        $raw = is_string($host) ? $host : $raw;
    }
    $raw = preg_replace('#^www\.#', '', $raw) ?? $raw;
    $raw = explode('/', $raw)[0];
    $raw = explode('?', $raw)[0];
    return strtolower(trim($raw));
}

/**
 * 預設鎖定來源（對齊 Appwrite DEFAULT_FENGBRO_NEWS_SITES 主要清單）。
 * @return list<array{id:string,name:string,domain:string,homeUrl:string,adapter:string,searchUrlTemplate?:string,locked:bool}>
 */
function fengbroNewsDefaultSites(): array
{
    return [
        ['id' => 'tycg-traffic', 'name' => '桃園市政府交通局', 'domain' => 'traffic.tycg.gov.tw', 'homeUrl' => 'https://traffic.tycg.gov.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://traffic.tycg.gov.tw/', 'locked' => true],
        ['id' => 'rb-nreo', 'name' => '交通部鐵道局北部工程分局', 'domain' => 'rb.gov.tw', 'homeUrl' => 'https://www.rb.gov.tw/zh-TW/NREO/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.rb.gov.tw/zh-TW/NREO/', 'locked' => true],
        ['id' => 'tycg-zhongli', 'name' => '桃園市中壢區公所', 'domain' => 'zhongli.tycg.gov.tw', 'homeUrl' => 'https://www.zhongli.tycg.gov.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.zhongli.tycg.gov.tw/', 'locked' => true],
        ['id' => 'youtube-tnews6460', 'name' => 'TNEWS聯播網', 'domain' => 'youtube.com', 'homeUrl' => 'https://www.youtube.com/@tnews6460/videos', 'adapter' => 'youtube-channel', 'locked' => true],
        ['id' => 'ptt-railway', 'name' => 'PTT 鐵路板', 'domain' => 'ptt.cc', 'homeUrl' => 'https://www.ptt.cc/bbs/Railway/index.html', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.ptt.cc/bbs/Railway/search?q={q}', 'locked' => true],
        ['id' => 'ltn', 'name' => '自由時報', 'domain' => 'ltn.com.tw', 'homeUrl' => 'https://www.ltn.com.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://search.ltn.com.tw/list?keyword={q}', 'locked' => true],
        ['id' => 'youtube-ntyprogram', 'name' => '年代向錢看', 'domain' => 'youtube.com', 'homeUrl' => 'https://www.youtube.com/@ntyprogram/videos', 'adapter' => 'youtube-channel', 'locked' => true],
        ['id' => 'chinatimes', 'name' => '中時新聞網', 'domain' => 'chinatimes.com', 'homeUrl' => 'https://www.chinatimes.com/?chdtv', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.chinatimes.com/search/{q}?chdtv', 'locked' => true],
        ['id' => 'leho', 'name' => '樂活', 'domain' => 'leho.com.tw', 'homeUrl' => 'https://leho.com.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://leho.com.tw/?s={q}', 'locked' => true],
        ['id' => 'udn', 'name' => '聯合新聞網', 'domain' => 'udn.com', 'homeUrl' => 'https://udn.com/news/index', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://udn.com/search/word/2/{q}', 'locked' => true],
        ['id' => 'tycg', 'name' => '桃園市政府', 'domain' => 'tycg.gov.tw', 'homeUrl' => 'https://www.tycg.gov.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.tycg.gov.tw/Advanced_Search.aspx?q={q}', 'locked' => true],
        ['id' => 'bella', 'name' => 'Bella 儂儂', 'domain' => 'bella.tw', 'homeUrl' => 'https://www.bella.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.bella.tw/search?q={q}', 'locked' => true],
        ['id' => 'youtube-tbc-news-nty', 'name' => 'TBC 新聞 (NTY)', 'domain' => 'youtube.com', 'homeUrl' => 'https://www.youtube.com/@TBC-news-NTY/videos', 'adapter' => 'youtube-channel', 'locked' => true],
        ['id' => 'youtube-pnnpts', 'name' => 'PNN 公視新聞網', 'domain' => 'youtube.com', 'homeUrl' => 'https://www.youtube.com/@PNNPTS/videos', 'adapter' => 'youtube-channel', 'locked' => true],
        ['id' => 'ey-gov', 'name' => '行政院', 'domain' => 'ey.gov.tw', 'homeUrl' => 'https://www.ey.gov.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.ey.gov.tw/Page/4EC20EEEEEAF363C', 'locked' => true],
        ['id' => 'dorts-tycg', 'name' => '桃園市政府捷運工程局', 'domain' => 'dorts.tycg.gov.tw', 'homeUrl' => 'https://dorts.tycg.gov.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://dorts.tycg.gov.tw/News.aspx', 'locked' => true],
        ['id' => 'hakkanews', 'name' => '客新聞', 'domain' => 'hakkanews.tw', 'homeUrl' => 'https://hakkanews.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://hakkanews.tw/?s={q}', 'locked' => true],
        ['id' => 'businesstoday', 'name' => '今周刊', 'domain' => 'businesstoday.com.tw', 'homeUrl' => 'https://www.businesstoday.com.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.businesstoday.com.tw/search?q={q}', 'locked' => true],
        ['id' => 'yahoo-news-tw', 'name' => 'Yahoo奇摩新聞', 'domain' => 'tw.news.yahoo.com', 'homeUrl' => 'https://tw.news.yahoo.com/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://tw.news.yahoo.com/search?p={q}', 'locked' => true],
        ['id' => 'tycc', 'name' => '桃園市議會', 'domain' => 'tycc.gov.tw', 'homeUrl' => 'https://www.tycc.gov.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.tycc.gov.tw/home.jsp?id=45&q={q}', 'locked' => true],
        ['id' => 'motc', 'name' => '交通部', 'domain' => 'motc.gov.tw', 'homeUrl' => 'https://www.motc.gov.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.motc.gov.tw/ch/home.jsp?id=14&parentpath=0,2', 'locked' => true],
        ['id' => 'housefun-news', 'name' => '好房網新聞', 'domain' => 'news.housefun.com.tw', 'homeUrl' => 'https://news.housefun.com.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://news.housefun.com.tw/search?q={q}', 'locked' => true],
        ['id' => 'ctee', 'name' => '工商時報', 'domain' => 'ctee.com.tw', 'homeUrl' => 'https://www.ctee.com.tw/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.ctee.com.tw/search/{q}', 'locked' => true],
        ['id' => 'tyenews', 'name' => '桃園電子報', 'domain' => 'tyenews.com', 'homeUrl' => 'https://tyenews.com/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://tyenews.com/?s={q}', 'locked' => true],
        ['id' => 'storm-new7', 'name' => '新新聞', 'domain' => 'storm.mg', 'homeUrl' => 'https://new7.storm.mg/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://new7.storm.mg/?s={q}', 'locked' => true],
        ['id' => 'ptt-home-sale', 'name' => 'PTT 房屋板', 'domain' => 'ptt.cc', 'homeUrl' => 'https://www.ptt.cc/bbs/home-sale/index.html', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.ptt.cc/bbs/home-sale/search?q={q}', 'locked' => true],
        ['id' => 'mobile01', 'name' => 'Mobile01', 'domain' => 'mobile01.com', 'homeUrl' => 'https://www.mobile01.com/', 'adapter' => 'generic-keyword-url', 'searchUrlTemplate' => 'https://www.mobile01.com/googlesearch.php?q={q}', 'locked' => true],
    ];
}

function fengbroNewsTitleMatches(string $title, string $query): bool
{
    $t = mb_strtolower(fengbroNewsNormalizeSpace($title), 'UTF-8');
    $q = mb_strtolower(fengbroNewsNormalizeSpace($query), 'UTF-8');
    if ($q === '') {
        return true;
    }
    $tokens = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($tokens as $token) {
        if ($token !== '' && mb_strpos($t, $token, 0, 'UTF-8') === false) {
            return false;
        }
    }
    return true;
}

function fengbroNewsIsJunkTitle(string $title): bool
{
    $t = fengbroNewsNormalizeSpace($title);
    if ($t === '' || mb_strlen($t, 'UTF-8') < 4) {
        return true;
    }
    if (preg_match('/^(home|index|login|註冊|更多|next|prev|上一頁|下一頁)$/iu', $t)) {
        return true;
    }
    return false;
}

function fengbroNewsAbsoluteUrl(string $base, string $href): string
{
    $href = trim($href);
    if ($href === '' || str_starts_with($href, 'javascript:') || str_starts_with($href, '#')) {
        return '';
    }
    if (preg_match('#^https?://#i', $href)) {
        return $href;
    }
    $parts = parse_url($base);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return $href;
    }
    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    if (str_starts_with($href, '//')) {
        return $parts['scheme'] . ':' . $href;
    }
    if (str_starts_with($href, '/')) {
        return $origin . $href;
    }
    $path = $parts['path'] ?? '/';
    $dir = preg_replace('#/[^/]*$#', '/', $path) ?: '/';
    return $origin . $dir . $href;
}

function fengbroNewsParseGoogleRss(string $xml, array $site, string $query): array
{
    $articles = [];
    if ($xml === '') {
        return $articles;
    }
    $prev = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if ($doc === false) {
        return $articles;
    }

    $items = $doc->channel->item ?? [];
    foreach ($items as $item) {
        $title = fengbroNewsStripTags((string) ($item->title ?? ''));
        $link = trim((string) ($item->link ?? ''));
        $pub = trim((string) ($item->pubDate ?? ''));
        if ($title === '' || $link === '') {
            continue;
        }
        if (!fengbroNewsTitleMatches($title, $query) && !fengbroNewsTitleMatches($title, str_replace(' ', '', $query))) {
            // Google already filtered by keyword; keep if title is non-junk
            if (fengbroNewsIsJunkTitle($title)) {
                continue;
            }
        }
        if (fengbroNewsIsJunkTitle($title)) {
            continue;
        }
        $publishedAt = null;
        if ($pub !== '') {
            $ts = strtotime($pub);
            if ($ts !== false) {
                $publishedAt = gmdate('c', $ts);
            }
        }
        $articles[] = [
            'title' => $title,
            'url' => $link,
            'siteId' => $site['id'],
            'siteName' => $site['name'],
            'domain' => $site['domain'],
            'publishedAt' => $publishedAt,
            'snippet' => fengbroNewsStripTags((string) ($item->description ?? '')),
            'source' => 'google-news',
        ];
        if (count($articles) >= 8) {
            break;
        }
    }
    return $articles;
}

function fengbroNewsExtractLinksFromHtml(string $html, string $baseUrl, array $site, string $query): array
{
    $articles = [];
    if ($html === '') {
        return $articles;
    }
    if (!preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>([\s\S]*?)<\/a>/iu', $html, $matches, PREG_SET_ORDER)) {
        return $articles;
    }
    $seen = [];
    foreach ($matches as $m) {
        $href = fengbroNewsAbsoluteUrl($baseUrl, $m[1]);
        $title = fengbroNewsStripTags($m[2]);
        if ($href === '' || fengbroNewsIsJunkTitle($title)) {
            continue;
        }
        if (!fengbroNewsTitleMatches($title, $query)) {
            continue;
        }
        $host = fengbroNewsNormalizeDomain($href);
        $siteHost = fengbroNewsNormalizeDomain($site['domain'] ?? '');
        if ($siteHost !== '' && $host !== '' && $host !== $siteHost && !str_ends_with($host, '.' . $siteHost)) {
            // allow ptt / youtube / google redirects loosely
            if (!str_contains($siteHost, 'youtube') && !str_contains($siteHost, 'ptt')) {
                continue;
            }
        }
        $key = mb_strtolower($title, 'UTF-8') . '|' . $href;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $articles[] = [
            'title' => $title,
            'url' => $href,
            'siteId' => $site['id'],
            'siteName' => $site['name'],
            'domain' => $site['domain'],
            'publishedAt' => null,
            'snippet' => '',
            'source' => 'html-scan',
        ];
        if (count($articles) >= 8) {
            break;
        }
    }
    return $articles;
}

function fengbroNewsSearchYouTubeChannel(array $site, string $query): array
{
    $home = (string) ($site['homeUrl'] ?? '');
    if ($home === '') {
        return ['articles' => [], 'error' => '缺少 YouTube 網址'];
    }
    $res = fengbroNewsFetchText($home, 10);
    if (!$res['ok']) {
        return ['articles' => [], 'error' => $res['error'] ?: 'YouTube 讀取失敗'];
    }
    $html = $res['text'];
    $articles = [];
    // ytInitialData videoRenderer titles
    if (preg_match_all('/"videoId":"([a-zA-Z0-9_-]{6,})"[\s\S]{0,400}?"title":\s*\{\s*"runs":\s*\[\s*\{\s*"text":\s*"([^"]+)"/u', $html, $m, PREG_SET_ORDER)) {
        $seen = [];
        foreach ($m as $row) {
            $vid = $row[1];
            $title = fengbroNewsDecodeHtml($row[2]);
            if (isset($seen[$vid]) || !fengbroNewsTitleMatches($title, $query)) {
                continue;
            }
            $seen[$vid] = true;
            $articles[] = [
                'title' => $title,
                'url' => 'https://www.youtube.com/watch?v=' . $vid,
                'siteId' => $site['id'],
                'siteName' => $site['name'],
                'domain' => 'youtube.com',
                'publishedAt' => null,
                'snippet' => '',
                'source' => 'youtube-channel',
            ];
            if (count($articles) >= 8) {
                break;
            }
        }
    }
    if (!$articles) {
        // looser title match fallback via link scan
        $articles = fengbroNewsExtractLinksFromHtml($html, $home, $site, $query);
        foreach ($articles as &$a) {
            $a['source'] = 'youtube-channel';
        }
        unset($a);
    }
    return ['articles' => $articles, 'error' => $articles ? null : '此頻道近期標題無符合關鍵字'];
}

function fengbroNewsSearchSite(array $site, string $query): array
{
    $siteId = (string) ($site['id'] ?? '');
    $siteName = (string) ($site['name'] ?? $siteId);
    $domain = fengbroNewsNormalizeDomain((string) ($site['domain'] ?? $site['homeUrl'] ?? ''));
    $adapter = (string) ($site['adapter'] ?? 'generic-keyword-url');
    $result = [
        'siteId' => $siteId,
        'siteName' => $siteName,
        'domain' => $domain,
        'articles' => [],
        'error' => null,
        'source' => null,
    ];

    if ($adapter === 'youtube-channel' || str_contains($domain, 'youtube.com')) {
        $yt = fengbroNewsSearchYouTubeChannel($site, $query);
        $result['articles'] = $yt['articles'];
        $result['error'] = $yt['error'];
        $result['source'] = 'youtube-channel';
        return $result;
    }

    // 1) Google News RSS with site:
    $rssQuery = rawurlencode($query . ' site:' . $domain);
    $rssUrl = 'https://news.google.com/rss/search?q=' . $rssQuery . '&hl=zh-TW&gl=TW&ceid=TW:zh-Hant';
    $rss = fengbroNewsFetchText($rssUrl, 8, ['Accept: application/rss+xml, application/xml, text/xml, */*']);
    if ($rss['ok']) {
        $articles = fengbroNewsParseGoogleRss($rss['text'], $site, $query);
        if ($articles) {
            $result['articles'] = $articles;
            $result['source'] = 'google-news';
            return $result;
        }
    }

    // 2) Site search / list page HTML scan
    $template = (string) ($site['searchUrlTemplate'] ?? $site['homeUrl'] ?? '');
    if ($template !== '') {
        $url = str_contains($template, '{q}')
            ? str_replace('{q}', rawurlencode($query), $template)
            : $template;
        $page = fengbroNewsFetchText($url, 8);
        if ($page['ok']) {
            $articles = fengbroNewsExtractLinksFromHtml($page['text'], $page['finalUrl'] ?: $url, $site, $query);
            if ($articles) {
                $result['articles'] = $articles;
                $result['source'] = 'html-scan';
                return $result;
            }
        } else {
            $result['error'] = $page['error'] ?: '站內頁面讀取失敗';
        }
    }

    // 3) Jina reader fallback for bot-walled pages
    if ($template !== '') {
        $url = str_contains($template, '{q}')
            ? str_replace('{q}', rawurlencode($query), $template)
            : $template;
        $jinaUrl = 'https://r.jina.ai/http://' . preg_replace('#^https?://#i', '', $url);
        $jina = fengbroNewsFetchText($jinaUrl, 10, ['Accept: text/plain']);
        if ($jina['ok'] && $jina['text'] !== '') {
            // markdown-ish links [title](url)
            $articles = [];
            if (preg_match_all('/\[([^\]]{4,200})\]\((https?:\/\/[^)\s]+)\)/u', $jina['text'], $mm, PREG_SET_ORDER)) {
                foreach ($mm as $row) {
                    $title = fengbroNewsNormalizeSpace($row[1]);
                    $link = $row[2];
                    if (!fengbroNewsTitleMatches($title, $query) || fengbroNewsIsJunkTitle($title)) {
                        continue;
                    }
                    $articles[] = [
                        'title' => $title,
                        'url' => $link,
                        'siteId' => $site['id'],
                        'siteName' => $site['name'],
                        'domain' => $domain,
                        'publishedAt' => null,
                        'snippet' => '',
                        'source' => 'jina',
                    ];
                    if (count($articles) >= 8) {
                        break;
                    }
                }
            }
            if ($articles) {
                $result['articles'] = $articles;
                $result['source'] = 'jina';
                $result['error'] = null;
                return $result;
            }
        }
    }

    if (!$result['error']) {
        $result['error'] = '此來源暫無符合結果';
    }
    return $result;
}

/**
 * @param list<array> $sites
 * @return array{query:string,fetchedAt:string,sites:list<array>,articles:list<array>,total:int}
 */
function fengbroNewsSearch(string $query, array $sites = [], int $concurrencyHint = 5): array
{
    $query = fengbroNewsNormalizeSpace($query);
    if ($query === '') {
        return [
            'query' => '',
            'fetchedAt' => gmdate('c'),
            'sites' => [],
            'articles' => [],
            'total' => 0,
            'error' => '請輸入搜尋關鍵字',
        ];
    }

    if (!$sites) {
        $sites = array_values(array_filter(
            fengbroNewsDefaultSites(),
            static fn($s) => !empty($s['locked'])
        ));
    }

    // PHP 同步執行：依序但限制數量，避免超時
    $deadline = microtime(true) + 22.0;
    $siteResults = [];
    $allArticles = [];
    $seenUrls = [];

    foreach ($sites as $site) {
        if (microtime(true) >= $deadline) {
            $siteResults[] = [
                'siteId' => $site['id'] ?? '',
                'siteName' => $site['name'] ?? '',
                'domain' => $site['domain'] ?? '',
                'articles' => [],
                'error' => '請求逾時略過',
                'source' => null,
            ];
            continue;
        }
        $one = fengbroNewsSearchSite($site, $query);
        $siteResults[] = $one;
        foreach ($one['articles'] as $article) {
            $url = (string) ($article['url'] ?? '');
            if ($url === '' || isset($seenUrls[$url])) {
                continue;
            }
            $seenUrls[$url] = true;
            $allArticles[] = $article;
        }
    }

    usort($allArticles, static function ($a, $b) {
        $pa = $a['publishedAt'] ?? '';
        $pb = $b['publishedAt'] ?? '';
        if ($pa !== '' && $pb !== '') {
            return strcmp($pb, $pa);
        }
        if ($pa !== '') {
            return -1;
        }
        if ($pb !== '') {
            return 1;
        }
        return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
    });

    return [
        'query' => $query,
        'fetchedAt' => gmdate('c'),
        'sites' => $siteResults,
        'articles' => $allArticles,
        'total' => count($allArticles),
        'siteCount' => count($sites),
        'matchedSites' => count(array_filter($siteResults, static fn($s) => !empty($s['articles']))),
    ];
}

/** 台鐵便當門市據點（對齊 Appwrite bento-stores）。 */
function fengbroNewsBentoStores(bool $focusOnly = true): array
{
    $sourceUrl = 'https://www.railway.gov.tw/tra-tip-web/tip/tip004/tip421/storeLocation';
    $fallback = [
        [
            'name' => '桃園車站-臺鐵便當本舖桃園店',
            'detail' => '桃園車站2樓臨臺鐵售票口，(10:00~13:30,16:00~18:00)',
            'focus' => true,
            'stationHint' => '桃園',
        ],
        [
            'name' => '中壢車站-臺鐵便當本舖中壢台',
            'detail' => '中壢車站2樓臨臺鐵剪票口(週一~週五營業)，(10:10~11:30,11:30~13:00)',
            'focus' => true,
            'stationHint' => '中壢',
        ],
    ];

    $res = fengbroNewsFetchText($sourceUrl, 12);
    $stores = [];
    $live = false;
    if ($res['ok'] && $res['text'] !== '') {
        if (preg_match_all('/class="sublist-title"[^>]*>([\s\S]*?)<\/div>\s*<ol>([\s\S]*?)<\/ol>/iu', $res['text'], $blocks, PREG_SET_ORDER)) {
            foreach ($blocks as $block) {
                $name = fengbroNewsStripTags($block[1]);
                if ($name === '' || !preg_match('/便當|本舖|舖/u', $name)) {
                    continue;
                }
                $details = [];
                if (preg_match_all('/<li[^>]*>([\s\S]*?)<\/li>/iu', $block[2], $lis)) {
                    foreach ($lis[1] as $li) {
                        $t = fengbroNewsStripTags($li);
                        if ($t !== '') {
                            $details[] = $t;
                        }
                    }
                }
                $focus = (bool) preg_match('/桃園|中壢/u', $name);
                $hint = null;
                if (str_contains($name, '桃園')) {
                    $hint = '桃園';
                } elseif (str_contains($name, '中壢')) {
                    $hint = '中壢';
                } elseif (str_contains($name, '板橋')) {
                    $hint = '板橋';
                } elseif (str_contains($name, '臺北') || str_contains($name, '台北')) {
                    $hint = '臺北';
                }
                $stores[] = [
                    'name' => $name,
                    'detail' => $details ? implode('；', $details) : $name,
                    'focus' => $focus,
                    'stationHint' => $hint,
                ];
            }
            $live = count($stores) > 0;
        }
    }

    if (!$stores) {
        $stores = $fallback;
        $live = false;
    }

    if ($focusOnly) {
        $focused = array_values(array_filter($stores, static fn($s) => !empty($s['focus'])));
        $stores = $focused ?: $fallback;
    }

    return [
        'sourceUrl' => $sourceUrl,
        'sourceLabel' => '台鐵便當門市據點',
        'focusOnly' => $focusOnly,
        'fetchedAt' => gmdate('c'),
        'count' => count($stores),
        'stores' => $stores,
        'live' => $live,
        'warning' => $live ? null : '官方頁面讀取失敗，已顯示備援桃園／中壢門市',
    ];
}

/**
 * 桃園市／中壢區人口統計：最近三個月 + 近十年走勢。
 * 優先 Wikimedia Commons 年表、維基百科 TWTSData／條目月資料；失敗時使用備援（內政部戶政／民政局公開數字）。
 */
function fengbroNewsPopulationFallback(): array
{
    return [
        'taoyuan' => [
            'id' => 'taoyuan',
            'name' => '桃園市',
            'scope' => '全市',
            'recentMonths' => [
                ['period' => '2026-01', 'label' => '2026年1月', 'population' => 2355444, 'change' => 338],
                ['period' => '2026-02', 'label' => '2026年2月', 'population' => 2355512, 'change' => 68],
                ['period' => '2026-03', 'label' => '2026年3月', 'population' => 2356734, 'change' => 1222],
            ],
            'years' => [
                ['year' => 2016, 'population' => 2147763],
                ['year' => 2017, 'population' => 2188017],
                ['year' => 2018, 'population' => 2220872],
                ['year' => 2019, 'population' => 2249037],
                ['year' => 2020, 'population' => 2268807],
                ['year' => 2021, 'population' => 2272391],
                ['year' => 2022, 'population' => 2281464],
                ['year' => 2023, 'population' => 2317445],
                ['year' => 2024, 'population' => 2338648],
                ['year' => 2025, 'population' => 2355106],
            ],
            'sourceUrls' => [
                'https://www.ris.gov.tw/app/portal/346',
                'https://cab.tycg.gov.tw/News.aspx?n=7902&sms=12475',
            ],
        ],
        'zhongli' => [
            'id' => 'zhongli',
            'name' => '中壢區',
            'scope' => '桃園市中壢區',
            // 備援：近期公開月底人口（戶政／民政局／TWTSData）。非連續月不填增減。
            'recentMonths' => [
                ['period' => '2025-12', 'label' => '2025年12月', 'population' => 439213, 'change' => null],
                ['period' => '2026-03', 'label' => '2026年3月', 'population' => 439275, 'change' => -15],
                ['period' => '2026-06', 'label' => '2026年6月', 'population' => 439184, 'change' => null],
            ],
            'years' => [
                ['year' => 2016, 'population' => 396453],
                ['year' => 2017, 'population' => 405216],
                ['year' => 2018, 'population' => 412063],
                ['year' => 2019, 'population' => 417380],
                ['year' => 2020, 'population' => 422471],
                ['year' => 2021, 'population' => 422529],
                ['year' => 2022, 'population' => 423722],
                ['year' => 2023, 'population' => 431255],
                ['year' => 2024, 'population' => 435050],
                ['year' => 2025, 'population' => 439213],
            ],
            'sourceUrls' => [
                'https://www.ris.gov.tw/app/portal/346',
                'https://www.zhongli-hro.tycg.gov.tw/',
                'https://cab.tycg.gov.tw/News.aspx?n=7902&sms=12475',
            ],
        ],
    ];
}

function fengbroNewsPopulationParseInt($value): ?int
{
    if ($value === null) {
        return null;
    }
    if (is_int($value)) {
        return $value;
    }
    if (is_float($value)) {
        return (int) round($value);
    }
    $raw = preg_replace('/[^\d\-]/u', '', (string) $value);
    if ($raw === '' || $raw === '-' || !is_numeric($raw)) {
        return null;
    }
    return (int) $raw;
}

function fengbroNewsPopulationPeriodLabel(string $period): string
{
    if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
        return ((int) $m[1]) . '年' . ((int) $m[2]) . '月';
    }
    if (preg_match('/^(\d{4})$/', $period, $m)) {
        return ((int) $m[1]) . '年';
    }
    return $period;
}

function fengbroNewsPopulationWikiParseText(string $wikitext): string
{
    $url = 'https://zh.wikipedia.org/w/api.php?action=parse&text=' . rawurlencode($wikitext)
        . '&contentmodel=wikitext&format=json&formatversion=2';
    $res = fengbroNewsFetchText($url, 12, [
        'Accept: application/json',
    ]);
    if (!$res['ok'] || $res['text'] === '') {
        return '';
    }
    $json = json_decode($res['text'], true);
    if (!is_array($json)) {
        return '';
    }
    $html = (string) ($json['parse']['text'] ?? '');
    if ($html === '') {
        return '';
    }
    $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
    return trim($plain);
}

function fengbroNewsPopulationFetchCommonsYears(string $title, int $lastN = 10): array
{
    $url = 'https://commons.wikimedia.org/wiki/Data:' . rawurlencode($title) . '?action=raw';
    $res = fengbroNewsFetchText($url, 12, ['Accept: application/json,text/plain,*/*']);
    if (!$res['ok'] || $res['text'] === '') {
        return [];
    }
    $json = json_decode($res['text'], true);
    if (!is_array($json) || empty($json['data']) || !is_array($json['data'])) {
        return [];
    }
    $rows = [];
    foreach ($json['data'] as $row) {
        if (!is_array($row) || count($row) < 2) {
            continue;
        }
        $year = fengbroNewsPopulationParseInt($row[0]);
        $pop = fengbroNewsPopulationParseInt($row[1]);
        if ($year === null || $pop === null || $year < 1900) {
            continue;
        }
        $rows[$year] = $pop;
    }
    if (!$rows) {
        return [];
    }
    ksort($rows, SORT_NUMERIC);
    $slice = array_slice($rows, -$lastN, null, true);
    $out = [];
    foreach ($slice as $year => $pop) {
        $out[] = ['year' => (int) $year, 'population' => (int) $pop];
    }
    return $out;
}

function fengbroNewsPopulationParseTaoyuanMonthly(string $wikitext): array
{
    $months = [];
    // Split around each |年份=YYYY marker and parse following month fields.
    if (!preg_match_all('/\|年份\s*=\s*(\d{4})/u', $wikitext, $yearHits, PREG_OFFSET_CAPTURE)) {
        return [];
    }

    $count = count($yearHits[0]);
    for ($i = 0; $i < $count; $i++) {
        $year = (int) $yearHits[1][$i][0];
        if ($year < 2000) {
            continue;
        }
        $start = (int) $yearHits[0][$i][1];
        $end = ($i + 1 < $count) ? (int) $yearHits[0][$i + 1][1] : strlen($wikitext);
        $chunk = substr($wikitext, $start, max(0, $end - $start));
        // Keep chunks modest so age-structure monthly blocks don't leak wrong totals
        if (strlen($chunk) > 2500) {
            $chunk = substr($chunk, 0, 2500);
        }
        // Prefer blocks that look like total-population monthly series
        $hasTotal = (bool) preg_match('/\|\d{1,2}月人口\s*=/u', $chunk);
        if (!$hasTotal) {
            continue;
        }
        // Skip pure age-band series (幼年/壯年/老年) which also use N月人口
        if (preg_match('/幼年|壯年|老年|老化/u', $chunk) && !preg_match('/人口變化|每月人口|桃園市人口/u', $chunk)) {
            // still allow if values are city-scale (> 1e6)
        }

        for ($m = 1; $m <= 12; $m++) {
            if (!preg_match('/\|' . $m . '月人口\s*=\s*([0-9,]+)/u', $chunk, $pm)) {
                continue;
            }
            $pop = fengbroNewsPopulationParseInt($pm[1]);
            if ($pop === null) {
                continue;
            }
            // City monthly totals are ~2.3M; age bands are smaller — keep city-scale only
            if ($pop < 1500000) {
                continue;
            }
            $change = null;
            if (preg_match('/\|' . $m . '月消長\s*=\s*([+\-−]?[0-9,]+)/u', $chunk, $cm)) {
                $change = fengbroNewsPopulationParseInt(str_replace('−', '-', $cm[1]));
            }
            $period = sprintf('%04d-%02d', $year, $m);
            $months[$period] = [
                'period' => $period,
                'label' => fengbroNewsPopulationPeriodLabel($period),
                'population' => $pop,
                'change' => $change,
            ];
        }
    }

    ksort($months);
    return array_values($months);
}

function fengbroNewsPopulationMonthIndex(string $period): ?int
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
        return null;
    }
    return ((int) $m[1]) * 12 + ((int) $m[2]);
}

function fengbroNewsPopulationFinalizeMonths(array $months, int $limit = 3): array
{
    if (!$months) {
        return [];
    }
    usort($months, static fn($a, $b) => strcmp((string) ($a['period'] ?? ''), (string) ($b['period'] ?? '')));

    // Fill missing change from previous population only when months are consecutive
    $prevPop = null;
    $prevIdx = null;
    foreach ($months as $i => $row) {
        $pop = fengbroNewsPopulationParseInt($row['population'] ?? null);
        $period = (string) ($row['period'] ?? '');
        $idx = fengbroNewsPopulationMonthIndex($period);
        $change = array_key_exists('change', $row) ? $row['change'] : null;
        $parsedChange = $change !== null ? fengbroNewsPopulationParseInt($change) : null;

        if ($parsedChange !== null) {
            $months[$i]['change'] = $parsedChange;
        } elseif ($pop !== null && $prevPop !== null && $idx !== null && $prevIdx !== null && ($idx - $prevIdx) === 1) {
            $months[$i]['change'] = $pop - $prevPop;
        } else {
            // Non-consecutive snapshot: do not invent a multi-month delta
            $months[$i]['change'] = null;
        }

        $months[$i]['population'] = $pop;
        $months[$i]['label'] = $row['label'] ?? fengbroNewsPopulationPeriodLabel($period);
        $months[$i]['period'] = $period;
        $prevPop = $pop;
        $prevIdx = $idx;
    }

    $months = array_values(array_filter($months, static fn($r) => !empty($r['population'])));
    if (count($months) <= $limit) {
        return $months;
    }

    // Prefer the latest consecutive window of $limit months (true「最近三個月」)
    $n = count($months);
    for ($end = $n - 1; $end >= $limit - 1; $end--) {
        $ok = true;
        for ($j = 0; $j < $limit - 1; $j++) {
            $newer = fengbroNewsPopulationMonthIndex((string) $months[$end - $j]['period']);
            $older = fengbroNewsPopulationMonthIndex((string) $months[$end - $j - 1]['period']);
            if ($newer === null || $older === null || ($newer - $older) !== 1) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            return array_slice($months, $end - $limit + 1, $limit);
        }
    }

    // Otherwise fall back to the latest available snapshots
    return array_slice($months, -$limit);
}

function fengbroNewsPopulationNormalizeRegion(array $region): array
{
    // Normalize full series first (limit large) so latest can use true newest snapshot
    $allMonths = fengbroNewsPopulationFinalizeMonths($region['recentMonths'] ?? [], 36);
    $months = fengbroNewsPopulationFinalizeMonths($region['recentMonths'] ?? [], 3);
    $years = [];
    foreach ($region['years'] ?? [] as $row) {
        $year = fengbroNewsPopulationParseInt($row['year'] ?? null);
        $pop = fengbroNewsPopulationParseInt($row['population'] ?? null);
        if ($year === null || $pop === null) {
            continue;
        }
        $years[$year] = ['year' => $year, 'population' => $pop];
    }
    ksort($years, SORT_NUMERIC);
    $years = array_values(array_slice($years, -10, null, true));

    $latest = null;
    $latestSource = $allMonths ?: $months;
    if ($latestSource) {
        $last = $latestSource[count($latestSource) - 1];
        $latest = [
            'period' => $last['period'],
            'label' => $last['label'],
            'population' => $last['population'],
            'change' => $last['change'] ?? null,
        ];
    }

    return [
        'id' => (string) ($region['id'] ?? ''),
        'name' => (string) ($region['name'] ?? ''),
        'scope' => (string) ($region['scope'] ?? ''),
        'latest' => $latest,
        'recentMonths' => $months,
        'years' => $years,
        'sourceUrls' => array_values(array_unique(array_filter($region['sourceUrls'] ?? []))),
    ];
}

function fengbroNewsPopulationStats(): array
{
    $fallback = fengbroNewsPopulationFallback();
    $liveBits = 0;
    $notes = [];

    $taoyuanYears = fengbroNewsPopulationFetchCommonsYears('Population_of_Taoyuan_City_(1946-).tab', 10);
    if ($taoyuanYears) {
        $fallback['taoyuan']['years'] = $taoyuanYears;
        $liveBits++;
    }

    $zhongliYears = fengbroNewsPopulationFetchCommonsYears('Population_of_Zhongli_District,_Taoyuan.tab', 10);
    if ($zhongliYears) {
        $fallback['zhongli']['years'] = $zhongliYears;
        $liveBits++;
    }

    // Latest city / district totals via Wikipedia data templates (內政部戶政月報彙整)
    $cityLatestText = fengbroNewsPopulationWikiParseText('{{ROCCPD|subdivision=桃園市}}');
    $cityDateText = fengbroNewsPopulationWikiParseText('{{ROCCPD|TXT=date}}');
    $cityPop = fengbroNewsPopulationParseInt($cityLatestText);
    $zlLatestText = fengbroNewsPopulationWikiParseText('{{TWTSData|P|320}}');
    $zlDateText = fengbroNewsPopulationWikiParseText('{{TWTSData|P|date}}');
    $zlPop = fengbroNewsPopulationParseInt($zlLatestText);

    $parseWikiDate = static function (string $text): ?string {
        if (preg_match('/(20\d{2})\s*年\s*(\d{1,2})\s*月/u', $text, $m)) {
            return sprintf('%04d-%02d', (int) $m[1], (int) $m[2]);
        }
        return null;
    };

    // Monthly series from 桃園市人口 article
    $pageRes = fengbroNewsFetchText(
        'https://zh.wikipedia.org/w/api.php?action=parse&page=' . rawurlencode('桃園市人口')
        . '&prop=wikitext&format=json&formatversion=2',
        12,
        ['Accept: application/json']
    );
    if ($pageRes['ok'] && $pageRes['text'] !== '') {
        $pageJson = json_decode($pageRes['text'], true);
        $wikitext = (string) ($pageJson['parse']['wikitext'] ?? '');
        if ($wikitext !== '') {
            $parsedMonths = fengbroNewsPopulationParseTaoyuanMonthly($wikitext);
            if ($parsedMonths) {
                $fallback['taoyuan']['recentMonths'] = $parsedMonths;
                $liveBits++;
            }
            // District snapshot table row for 中壢區 (人口數 ~40 萬級)
            $periodHint = null;
            if (preg_match('/以下資料時間為\s*(20\d{2})\s*年\s*(\d{1,2})\s*月/u', $wikitext, $dm)) {
                $periodHint = sprintf('%04d-%02d', (int) $dm[1], (int) $dm[2]);
            }
            if ($periodHint && preg_match('/\[\[中壢區[^\]]*\]\]([^\n]+)/u', $wikitext, $zrow)) {
                $rowText = $zrow[1];
                $p = null;
                $c = null;
                if (preg_match_all('/\|\s*([\d,]{6,})\s*\|/u', $rowText, $nums)) {
                    foreach ($nums[1] as $numRaw) {
                        $candidate = fengbroNewsPopulationParseInt($numRaw);
                        if ($candidate !== null && $candidate >= 300000 && $candidate <= 600000) {
                            $p = $candidate;
                            break;
                        }
                    }
                }
                if (preg_match('/\{\{(?:Red|Blue|Green)\|([+\-−]?\d+)\}\}/u', $rowText, $cm)
                    || preg_match('/([+\-−]\d+)\s*\|/u', $rowText, $cm)
                ) {
                    $c = fengbroNewsPopulationParseInt(str_replace('−', '-', $cm[1]));
                }
                if ($p !== null) {
                    $existing = [];
                    foreach ($fallback['zhongli']['recentMonths'] as $row) {
                        $existing[$row['period']] = $row;
                    }
                    $existing[$periodHint] = [
                        'period' => $periodHint,
                        'label' => fengbroNewsPopulationPeriodLabel($periodHint),
                        'population' => $p,
                        'change' => $c,
                    ];
                    ksort($existing);
                    $fallback['zhongli']['recentMonths'] = array_values($existing);
                    $liveBits++;
                }
            }
        }
    }

    // Merge latest template totals into monthly series
    if ($cityPop !== null) {
        $period = $parseWikiDate($cityDateText) ?: date('Y-m');
        $months = [];
        foreach ($fallback['taoyuan']['recentMonths'] as $row) {
            $months[$row['period']] = $row;
        }
        $months[$period] = [
            'period' => $period,
            'label' => fengbroNewsPopulationPeriodLabel($period),
            'population' => $cityPop,
            'change' => $months[$period]['change'] ?? null,
        ];
        ksort($months);
        $fallback['taoyuan']['recentMonths'] = array_values($months);
        $liveBits++;
        $notes[] = '桃園市最新人口：' . fengbroNewsPopulationPeriodLabel($period);
    }

    if ($zlPop !== null) {
        $period = $parseWikiDate($zlDateText) ?: ($parseWikiDate($cityDateText) ?: date('Y-m'));
        $months = [];
        foreach ($fallback['zhongli']['recentMonths'] as $row) {
            $months[$row['period']] = $row;
        }
        $prev = null;
        foreach ($months as $p => $row) {
            if ($p < $period) {
                $prev = $row;
            }
        }
        $change = null;
        if ($prev && isset($prev['population'])) {
            // Only set auto change when previous point is previous calendar month
            if (preg_match('/^(\d{4})-(\d{2})$/', $period, $pm) && preg_match('/^(\d{4})-(\d{2})$/', (string) $prev['period'], $pp)) {
                $currIdx = ((int) $pm[1]) * 12 + ((int) $pm[2]);
                $prevIdx = ((int) $pp[1]) * 12 + ((int) $pp[2]);
                if ($currIdx - $prevIdx === 1) {
                    $change = $zlPop - (int) $prev['population'];
                }
            }
        }
        $months[$period] = [
            'period' => $period,
            'label' => fengbroNewsPopulationPeriodLabel($period),
            'population' => $zlPop,
            'change' => $change ?? ($months[$period]['change'] ?? null),
        ];
        ksort($months);
        $fallback['zhongli']['recentMonths'] = array_values($months);
        $liveBits++;
        $notes[] = '中壢區最新人口：' . fengbroNewsPopulationPeriodLabel($period);
    }

    $regions = [
        'taoyuan' => fengbroNewsPopulationNormalizeRegion($fallback['taoyuan']),
        'zhongli' => fengbroNewsPopulationNormalizeRegion($fallback['zhongli']),
    ];

    $live = $liveBits > 0;
    return [
        'fetchedAt' => gmdate('c'),
        'live' => $live,
        'sourceLabel' => '內政部戶政司／桃園市民政局（經 Wikimedia 公開彙整）',
        'sourceUrls' => [
            'https://www.ris.gov.tw/app/portal/346',
            'https://cab.tycg.gov.tw/News.aspx?n=7902&sms=12475',
            'https://zh.wikipedia.org/wiki/桃園市人口',
            'https://zh.wikipedia.org/wiki/中壢區',
        ],
        'regions' => $regions,
        'warning' => $live ? null : '即時資料讀取失敗，已顯示備援統計（截至公開年表）',
        'notes' => $notes,
    ];
}
