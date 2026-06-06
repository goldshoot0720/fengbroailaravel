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
    unset($cache['tube_data'], $cache['tube_data_v2']);
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
        $patterns = [
            '/"channelId"\s*:\s*"(UC[a-zA-Z0-9_-]+)"/',
            '/"externalId"\s*:\s*"(UC[a-zA-Z0-9_-]+)"/',
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
        $videos[] = [
            'id' => $videoId,
            'title' => (string) $entry->title,
            'url' => (string) $entry->link['href'],
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

function fengbroTubeExtractUpdateBadge($channel, $videos)
{
    $handle = strtolower((string) ($channel['handle'] ?? ''));
    if ($handle !== 'henren778') {
        return [];
    }

    foreach ($videos as $video) {
        $title = (string) ($video['title'] ?? '');
        if (preg_match('/倒台指[數数]\D*(\d+(?:\.\d+)?)/u', $title, $m)) {
            return [
                'label' => '倒台指數',
                'value' => number_format((float) str_replace(',', '', $m[1]), 2, '.', ''),
                'title' => $title,
            ];
        }
    }

    return [];
}

function fengbroTubeGetData($force = false)
{
    $cache = fengbroTubeReadCache();
    $dataKey = 'tube_data_v2';
    if (!$force && !empty($cache[$dataKey]['checkedAt']) && time() - (int) $cache[$dataKey]['checkedAt'] < 21600) {
        return $cache[$dataKey]['value'];
    }

    $channels = [];
    $newVideos = [];
    foreach (fengbroTubeChannels() as $channel) {
        $channelId = fengbroTubeResolveChannelId($channel, $cache);
        $videos = [];
        $error = '';
        $feedXml = '';
        if ($channelId) {
            $feedUrl = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode($channelId);
            $feedXml = fengbroTubeFetchUrl($feedUrl);
            $videos = fengbroTubeParseFeed($feedXml, 10);
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
        $updateBadge = fengbroTubeExtractUpdateBadge($channel, $videos);
        $channels[] = [
            'name' => $channelName,
            'defaultName' => $channel['name'],
            'handle' => $channel['handle'] ?? '',
            'url' => $channel['url'],
            'channelId' => $channelId,
            'videos' => $videos,
            'error' => $error,
            'updateBadge' => $updateBadge,
        ];
    }

    $data = [
        'checkedAt' => date('Y-m-d H:i:s'),
        'channels' => $channels,
        'newVideos' => $newVideos,
    ];
    $cache[$dataKey] = ['checkedAt' => time(), 'value' => $data];
    fengbroTubeWriteCache($cache);
    return $data;
}
