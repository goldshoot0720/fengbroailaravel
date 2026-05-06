<?php

function fengbroTubeChannels()
{
    return [
        ['name' => 'SJdiao', 'url' => 'https://www.youtube.com/@SJdiao/videos'],
        ['name' => 'henren778', 'url' => 'https://www.youtube.com/@henren778'],
        ['name' => 'libertas1984', 'url' => 'https://www.youtube.com/@libertas1984/videos'],
        ['name' => 'sunlao', 'url' => 'https://www.youtube.com/@sunlao/videos'],
        ['name' => 'Torontobigface', 'url' => 'https://www.youtube.com/@Torontobigface/videos'],
        ['name' => 'junyulan', 'url' => 'https://www.youtube.com/@junyulan/videos'],
        ['name' => 'blackwhite_raven', 'url' => 'https://www.youtube.com/@blackwhite_raven/videos'],
        ['name' => 'quedaren', 'url' => 'https://www.youtube.com/@quedaren/videos'],
        ['name' => '夸克说', 'url' => 'https://www.youtube.com/@%E5%A4%B8%E5%85%8B%E8%AF%B4'],
        ['name' => '喵喵看一看', 'url' => 'https://www.youtube.com/@%E5%96%B5%E5%96%B5%E7%9C%8B%E4%B8%80%E7%9C%8B/videos'],
    ];
}

function fengbroTubeCachePath()
{
    return __DIR__ . '/../uploads/temp/fengbro_tube_cache.json';
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
    if ($xmlText === '') {
        return [];
    }
    if (!function_exists('simplexml_load_string')) {
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

function fengbroTubeGetData($force = false)
{
    $cache = fengbroTubeReadCache();
    $dataKey = 'tube_data';
    if (!$force && !empty($cache[$dataKey]['checkedAt']) && time() - (int) $cache[$dataKey]['checkedAt'] < 21600) {
        return $cache[$dataKey]['value'];
    }

    $channels = [];
    $newVideos = [];
    foreach (fengbroTubeChannels() as $channel) {
        $channelId = fengbroTubeResolveChannelId($channel, $cache);
        $videos = [];
        $error = '';
        if ($channelId) {
            $feedUrl = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode($channelId);
            $videos = fengbroTubeParseFeed(fengbroTubeFetchUrl($feedUrl), 10);
        } else {
            $error = '無法解析頻道 ID';
        }
        foreach ($videos as $video) {
            if (!empty($video['isNew'])) {
                $newVideos[] = [
                    'channel' => $channel['name'],
                    'title' => $video['title'],
                    'url' => $video['url'],
                    'publishedText' => $video['publishedText'],
                ];
            }
        }
        $channels[] = [
            'name' => $channel['name'],
            'url' => $channel['url'],
            'channelId' => $channelId,
            'videos' => $videos,
            'error' => $error,
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
