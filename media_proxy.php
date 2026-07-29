<?php
/**
 * 簡易媒體代理：供圖片格式轉換「網址加入」避開 CORS。
 * 僅允許 http/https 圖片類回應，並限制大小。
 */

@set_time_limit(30);

$url = isset($_GET['url']) ? trim((string) $_GET['url']) : '';
if ($url === '' || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing or invalid url';
    exit;
}

$parts = parse_url($url);
$host = strtolower((string) ($parts['host'] ?? ''));
if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || str_starts_with($host, '192.168.') || str_starts_with($host, '10.')) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Host not allowed';
    exit;
}

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
$maxBytes = 12 * 1024 * 1024;

if (!function_exists('curl_init')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'curl required';
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => $ua,
    CURLOPT_HTTPHEADER => [
        'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        'Accept-Language: zh-TW,zh;q=0.9,en;q=0.8',
    ],
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
]);
$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$err = curl_error($ch);
curl_close($ch);

if (!is_string($body) || $body === '' || $status >= 400) {
    http_response_code($status >= 400 ? $status : 502);
    header('Content-Type: text/plain; charset=utf-8');
    echo $err !== '' ? $err : ('Media fetch failed: HTTP ' . $status);
    exit;
}

if (strlen($body) > $maxBytes) {
    http_response_code(413);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File too large';
    exit;
}

$ct = strtolower(trim(explode(';', $contentType)[0] ?? ''));
$allowed = [
    'image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/gif',
    'image/bmp', 'image/x-ms-bmp', 'image/avif', 'image/tiff', 'image/heic', 'image/heif',
    'application/octet-stream',
];
// Some CDNs omit type; sniff magic
if ($ct === '' || $ct === 'application/octet-stream' || $ct === 'text/plain') {
    $magic = substr($body, 0, 12);
    if (str_starts_with($magic, "\x89PNG")) {
        $ct = 'image/png';
    } elseif (str_starts_with($magic, "\xFF\xD8\xFF")) {
        $ct = 'image/jpeg';
    } elseif (str_starts_with($magic, 'GIF8')) {
        $ct = 'image/gif';
    } elseif (str_starts_with($magic, 'RIFF') && str_contains(substr($magic, 0, 12), 'WEBP')) {
        $ct = 'image/webp';
    } elseif (str_starts_with($magic, 'BM')) {
        $ct = 'image/bmp';
    } else {
        $ct = 'application/octet-stream';
    }
}

if (!in_array($ct, $allowed, true) && !str_starts_with($ct, 'image/')) {
    http_response_code(415);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unsupported content type: ' . $ct;
    exit;
}

header('Content-Type: ' . ($ct ?: 'application/octet-stream'));
header('Cache-Control: private, max-age=300');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');
echo $body;
