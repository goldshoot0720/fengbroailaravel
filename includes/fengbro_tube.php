<?php

function fengbroTubeDefaultChannels()
{
    return [
        ['name' => '', 'url' => 'https://www.youtube.com/@SJdiao/videos'],
        ['name' => '', 'handle' => 'henren778', 'url' => 'https://www.youtube.com/@henren778'],
        ['name' => '', 'url' => 'https://www.youtube.com/@libertas1984/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@sunlao/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@Torontobigface/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@junyulan/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@blackwhite_raven/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@quedaren/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@%E5%A4%B8%E5%85%8B%E8%AF%B4'],
        ['name' => '', 'url' => 'https://www.youtube.com/@%E5%96%B5%E5%96%B5%E7%9C%8B%E4%B8%80%E7%9C%8B/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@ma-siku/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@monsterise/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@informant510/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@jilixiaoshimei/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@SunChannelHK/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@jlaw/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@NeixianZhang/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@%E4%BF%AE%E4%BB%99%E8%80%85%E5%B0%8F%E7%83%A8/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@xiaoye1757/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@cheapaoe/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@StorytellerHK/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@mrshenofficial/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@jiangtaigong/videos'],
        ['name' => '', 'url' => 'https://www.youtube.com/@GC%E8%B6%99%E6%B0%8F%E8%AE%80%E6%9B%B8%E7%94%9F%E6%B4%BB'],
    ];
}

function fengbroTubeChannels()
{
    $custom = fengbroTubeReadChannelsFile();
    return $custom !== null ? $custom : fengbroTubeDefaultChannels();
}

function fengbroTubeCachePath()
{
    return __DIR__ . '/../uploads/temp/fengbro_tube_cache.json';
}

function fengbroTubeChannelsPath()
{
    return __DIR__ . '/../uploads/temp/fengbro_tube_channels.json';
}

function fengbroTubeReadCache()
{
    $path = fengbroTubeCachePath();
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) @file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function fengbroTubeWriteCache($cache)
{
    $path = fengbroTubeCachePath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($path, json_encode($cache, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function fengbroTubeClearDataCache()
{
    $cache = fengbroTubeReadCache();
    unset($cache['tube_data'], $cache['tube_data_v2'], $cache['tube_data_v3'], $cache['tube_data_v4'], $cache['tube_data_v5']);
    fengbroTubeWriteCache($cache);
}

function fengbroTubeReadChannelsFile()
{
    $path = fengbroTubeChannelsPath();
    if (!is_file($path)) {
        return null;
    }

    $data = json_decode((string) @file_get_contents($path), true);
    if (!is_array($data)) {
        return null;
    }

    $channels = [];
    foreach ($data as $channel) {
        $normalized = fengbroTubeNormalizeChannel($channel);
        if ($normalized !== null) {
            $channels[] = $normalized;
        }
    }

    return $channels;
}

function fengbroTubeSaveChannels($channels)
{
    $normalized = [];
    foreach ($channels as $channel) {
        $item = fengbroTubeNormalizeChannel($channel);
        if ($item !== null) {
            $normalized[] = $item;
        }
    }

    $path = fengbroTubeChannelsPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($path, json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    fengbroTubeClearDataCache();
}

function fengbroTubeResetChannels()
{
    $path = fengbroTubeChannelsPath();
    if (is_file($path)) {
        @unlink($path);
    }
    fengbroTubeClearDataCache();
}

function fengbroTubeNormalizeChannel($channel)
{
    $url = trim((string) ($channel['url'] ?? ''));
    if ($url === '') {
        return null;
    }
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://www.youtube.com/' . ltrim($url, '/');
    }

    $name = trim((string) ($channel['name'] ?? ''));
    $handle = trim((string) ($channel['handle'] ?? ''));
    if ($handle === '') {
        $handle = fengbroTubeExtractHandleFromUrl($url);
    }

    $item = ['name' => $name, 'url' => $url];
    if ($handle !== '') {
        $item['handle'] = $handle;
    }
    return $item;
}

function fengbroTubeExtractHandleFromUrl($url)
{
    if (preg_match('~/@([^/?#]+)~', (string) $url, $m)) {
        return rawurldecode($m[1]);
    }
    return '';
}

function fengbroTubeFetchUrl($url, $timeout = 8)
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

function fengbroTubeNormalizeUrl($url)
{
    return preg_replace('~/videos/?$~', '', trim((string) $url));
}

function fengbroTubeResolveChannelId($channel, &$cache)
{
    $url = fengbroTubeNormalizeUrl($channel['url']);
    $key = 'channel_id:' . $url;
    if (!empty($cache[$key]['value']) && !empty($cache[$key]['checkedAt']) && time() - (int) $cache[$key]['checkedAt'] < 604800) {
        return $cache[$key]['value'];
    }

    if (preg_match('~/channel/(UC[a-zA-Z0-9_-]+)~', $url, $m)) {
        $channelId = $m[1];
    } else {
        $html = fengbroTubeFetchUrl($url);
        $channelId = '';
        // 優先 externalId，避免頁面多個 channelId 造成頻道錯位（對齊 Appwrite）
        $patterns = [
            '/"externalId"\s*:\s*"(UC[a-zA-Z0-9_-]+)"/',
            '/"channelId"\s*:\s*"(UC[a-zA-Z0-9_-]+)"/',
            '/itemprop="channelId"\s+content="(UC[a-zA-Z0-9_-]+)"/',
            '/\/channel\/(UC[a-zA-Z0-9_-]+)/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $channelId = $m[1];
                break;
            }
        }
    }

    if (!empty($channelId)) {
        $cache[$key] = ['value' => $channelId, 'checkedAt' => time()];
    }
    return $channelId ?? '';
}

function fengbroTubeParseFeed($xmlText, $limit = 10)
{
    if ($xmlText === '' || !function_exists('simplexml_load_string')) {
        return [];
    }

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlText);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$xml) {
        return [];
    }

    $videos = [];
    foreach ($xml->entry as $entry) {
        $media = $entry->children('http://search.yahoo.com/mrss/');
        $group = $media->group ?? null;
        $thumbnail = '';
        if ($group && isset($group->thumbnail)) {
            $attrs = $group->thumbnail->attributes();
            $thumbnail = isset($attrs['url']) ? (string) $attrs['url'] : '';
        }
        $published = (string) $entry->published;
        $videoId = '';
        $yt = $entry->children('http://www.youtube.com/xml/schemas/2015');
        if (isset($yt->videoId)) {
            $videoId = (string) $yt->videoId;
        }
        $url = (string) $entry->link['href'];
        // 過濾 YouTube Shorts（對齊 Appwrite fengbro-tube）
        if (str_contains($url, '/shorts/')) {
            continue;
        }
        $videos[] = [
            'id' => $videoId,
            'title' => (string) $entry->title,
            'url' => $url,
            'published' => $published,
            'publishedText' => $published ? date('Y-m-d H:i', strtotime($published)) : '',
            'thumbnail' => $thumbnail,
            'isNew' => $published ? (strtotime($published) >= time() - 3 * 86400) : false,
        ];
        if (count($videos) >= $limit) {
            break;
        }
    }
    return $videos;
}

function fengbroTubeExtractFeedChannelName($xmlText)
{
    if ($xmlText === '' || !function_exists('simplexml_load_string')) {
        return '';
    }

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlText);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$xml) {
        return '';
    }

    $name = trim((string) ($xml->author->name ?? ''));
    if ($name === '') {
        $name = trim((string) ($xml->title ?? ''));
    }
    return $name;
}

function fengbroTubeExtractHtmlChannelName($html)
{
    if ($html === '') {
        return '';
    }

    $patterns = [
        '/<meta\s+property="og:title"\s+content="([^"]+)"/i',
        '/<meta\s+name="title"\s+content="([^"]+)"/i',
        '/"title"\s*:\s*{\s*"simpleText"\s*:\s*"([^"]+)"/',
        '/"channelMetadataRenderer"\s*:\s*{[^}]*"title"\s*:\s*"([^"]+)"/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $m)) {
            $name = html_entity_decode(stripslashes($m[1]), ENT_QUOTES, 'UTF-8');
            $name = preg_replace('/\s*-\s*YouTube$/i', '', $name);
            $name = trim($name);
            if ($name !== '') {
                return $name;
            }
        }
    }
    return '';
}

function fengbroTubeResolveChannelName($channel, $channelId, $feedXml, &$cache)
{
    $url = fengbroTubeNormalizeUrl($channel['url'] ?? '');
    $key = 'channel_name:' . ($channelId ?: $url);
    if (!empty($cache[$key]['value']) && !empty($cache[$key]['checkedAt']) && time() - (int) $cache[$key]['checkedAt'] < 604800) {
        return $cache[$key]['value'];
    }

    $name = fengbroTubeExtractFeedChannelName($feedXml);
    if ($name === '') {
        $name = fengbroTubeExtractHtmlChannelName(fengbroTubeFetchUrl($url));
    }
    if ($name === '') {
        $name = trim((string) ($channel['name'] ?? ''));
    }
    if ($name === '') {
        $name = trim((string) ($channel['handle'] ?? ''));
    }
    if ($name === '') {
        $name = 'YouTube';
    }

    $cache[$key] = ['value' => $name, 'checkedAt' => time()];
    return $name;
}

function fengbroTubeNormalizeDigits($value)
{
    $map = [
        '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
        '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
    ];
    return strtr((string) $value, $map);
}

/**
 * 從影片標題解析倒台指數（對齊 Appwrite extractTubeDownfallIndex）。
 */
function fengbroTubeExtractDownfallIndex($title)
{
    $normalized = fengbroTubeNormalizeDigits($title);
    $formatIndex = static function ($value) {
        return sprintf('%05.2f', (float) $value);
    };

    if (preg_match('/倒台指[數数]/u', $normalized, $labelMatch, PREG_OFFSET_CAPTURE)) {
        $labelPos = $labelMatch[0][1];
        $labelLen = strlen($labelMatch[0][0]);
        $afterLabel = substr($normalized, $labelPos + $labelLen, 80);

        if (preg_match('/(?:飆至|飙至|升至|漲至|涨至|達到|达到|達|达|至|突破|破)\s*([0-9]+(?:\.[0-9]+)?)/u', $afterLabel, $m)) {
            return $formatIndex($m[1]);
        }
        if (preg_match_all('/([0-9]+(?:\.[0-9]+)?)/', $afterLabel, $nums, PREG_OFFSET_CAPTURE)) {
            foreach ($nums[1] as $match) {
                $next = ltrim(substr($afterLabel, $match[1] + strlen($match[0])));
                if (preg_match('/^[月日號号]/u', $next)) {
                    continue;
                }
                return $formatIndex($match[0]);
            }
        }
    }

    if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*(?:分|%|％)?\s*倒台指[數数]/u', $normalized, $m)) {
        return $formatIndex($m[1]);
    }
    return '';
}

function fengbroTubeIsHenrenChannel($channel, $channelName = '')
{
    $handle = strtolower((string) ($channel['handle'] ?? ''));
    $url = (string) ($channel['url'] ?? '');
    $name = (string) $channelName;
    if ($handle === 'henren778' || stripos($url, 'henren778') !== false) {
        return true;
    }
    return (bool) preg_match('/一[個个]狠人/u', $name);
}

function fengbroTubeHardcodedDownfallHistory()
{
    return [
        ['date' => '2023-10-01T00:00:00Z', 'price' => 67.44],
        ['date' => '2023-11-01T00:00:00Z', 'price' => 68.28],
        ['date' => '2024-06-01T00:00:00Z', 'price' => 70.58],
    ];
}

function fengbroTubeExtractUpdateBadge($channel, $videos, $channelName = '')
{
    if (!fengbroTubeIsHenrenChannel($channel, $channelName)) {
        return [];
    }

    foreach ($videos as $video) {
        $value = fengbroTubeExtractDownfallIndex($video['title'] ?? '');
        if ($value !== '') {
            return [
                'label' => '更新',
                'value' => $value,
                'title' => (string) ($video['title'] ?? ''),
                'url' => (string) ($video['url'] ?? ''),
                'published' => (string) ($video['published'] ?? ''),
            ];
        }
    }

    return [];
}

function fengbroTubeBuildDownfallHistory($channel, $videos)
{
    $hardcoded = fengbroTubeHardcodedDownfallHistory();
    if (!$channel) {
        return $hardcoded;
    }

    $dynamic = [];
    foreach ($videos as $video) {
        $value = fengbroTubeExtractDownfallIndex($video['title'] ?? '');
        if ($value === '') {
            continue;
        }
        $dynamic[] = [
            'date' => (string) ($video['published'] ?: date('c')),
            'price' => (float) $value,
            'title' => (string) ($video['title'] ?? ''),
            'url' => (string) ($video['url'] ?? ''),
        ];
    }
    usort($dynamic, static function ($a, $b) {
        return strtotime($a['date']) <=> strtotime($b['date']);
    });

    $lastHardcoded = strtotime($hardcoded[count($hardcoded) - 1]['date']);
    $newPoints = array_values(array_filter(
        $dynamic,
        static fn($p) => strtotime($p['date']) > $lastHardcoded
    ));

    return array_merge($hardcoded, $newPoints);
}

/**
 * 最近兩次倒台指數發布的間隔天數（以曆日計算，含不足一天亦算 0）。
 * history 須依 date 由舊到新排序。
 */
function fengbroTubeDownfallPublishIntervalDays(array $history)
{
    if (count($history) < 2) {
        return null;
    }
    $prev = $history[count($history) - 2];
    $last = $history[count($history) - 1];
    $t1 = strtotime((string) ($prev['date'] ?? ''));
    $t2 = strtotime((string) ($last['date'] ?? ''));
    if ($t1 === false || $t2 === false) {
        return null;
    }
    $d1 = (new DateTimeImmutable('@' . $t1))->setTimezone(new DateTimeZone(date_default_timezone_get() ?: 'UTC'))->format('Y-m-d');
    $d2 = (new DateTimeImmutable('@' . $t2))->setTimezone(new DateTimeZone(date_default_timezone_get() ?: 'UTC'))->format('Y-m-d');
    $from = new DateTimeImmutable($d1);
    $to = new DateTimeImmutable($d2);
    return (int) $from->diff($to)->days;
}

function fengbroTubeGetData($force = false)
{
    $cache = fengbroTubeReadCache();
    $dataKey = 'tube_data_v5'; // v5: 倒台指數最近兩次發布間隔天數
    if (!$force && !empty($cache[$dataKey]['checkedAt']) && time() - (int) $cache[$dataKey]['checkedAt'] < 21600) {
        return $cache[$dataKey]['value'];
    }

    $channels = [];
    $newVideos = [];
    $downfallChannel = null;
    $downfallIndexUpdate = null;
    $downfallHistory = fengbroTubeHardcodedDownfallHistory();

    foreach (fengbroTubeChannels() as $channel) {
        $channelId = fengbroTubeResolveChannelId($channel, $cache);
        $videos = [];
        $error = '';
        $feedXml = '';
        if ($channelId) {
            $feedUrl = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode($channelId);
            $feedXml = fengbroTubeFetchUrl($feedUrl);
            $videos = fengbroTubeParseFeed($feedXml, 15);
        } else {
            $error = '無法解析頻道 ID';
        }
        $channelName = fengbroTubeResolveChannelName($channel, $channelId, $feedXml, $cache);
        foreach ($videos as $video) {
            if (!empty($video['isNew'])) {
                $newVideos[] = [
                    'channel' => $channelName,
                    'title' => $video['title'],
                    'url' => $video['url'],
                    'publishedText' => $video['publishedText'],
                ];
            }
        }
        $updateBadge = fengbroTubeExtractUpdateBadge($channel, $videos, $channelName);
        $channelRow = [
            'name' => $channelName,
            'defaultName' => $channel['name'],
            'handle' => $channel['handle'] ?? '',
            'url' => $channel['url'],
            'channelId' => $channelId,
            'videos' => array_slice($videos, 0, 10),
            'error' => $error,
            'updateBadge' => $updateBadge,
            'isHenren' => fengbroTubeIsHenrenChannel($channel, $channelName),
        ];
        if ($channelRow['isHenren']) {
            $downfallChannel = $channelRow;
            $downfallHistory = fengbroTubeBuildDownfallHistory($channel, $videos);
            if ($updateBadge) {
                $downfallIndexUpdate = [
                    'value' => $updateBadge['value'],
                    'title' => $updateBadge['title'],
                    'url' => $updateBadge['url'] ?? '',
                    'publishedAt' => $updateBadge['published'] ?? '',
                ];
            }
        }
        $channels[] = $channelRow;
    }

    if (!$downfallIndexUpdate && $downfallHistory) {
        $last = $downfallHistory[count($downfallHistory) - 1];
        $downfallIndexUpdate = [
            'value' => number_format((float) $last['price'], 2, '.', ''),
            'title' => $last['title'] ?? '倒台指數歷史資料',
            'url' => $last['url'] ?? '',
            'publishedAt' => $last['date'] ?? '',
        ];
    }

    $downfallPublishIntervalDays = fengbroTubeDownfallPublishIntervalDays($downfallHistory);

    $data = [
        'checkedAt' => date('Y-m-d H:i:s'),
        'channels' => $channels,
        'newVideos' => $newVideos,
        'downfallChannel' => $downfallChannel,
        'downfallIndexUpdate' => $downfallIndexUpdate,
        'downfallHistory' => $downfallHistory,
        'downfallPublishIntervalDays' => $downfallPublishIntervalDays,
    ];
    $cache[$dataKey] = ['checkedAt' => time(), 'value' => $data];
    // 一併清掉舊 key，避免殘留
    unset($cache['tube_data'], $cache['tube_data_v2'], $cache['tube_data_v3'], $cache['tube_data_v4']);
    fengbroTubeWriteCache($cache);
    return $data;
}
