<?php
require_once __DIR__ . '/includes/functions.php';

header('Cache-Control: private, no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    return;
}
// Slow external feeds must not hold the session lock while the user navigates.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$panel = $_GET['panel'] ?? '';
if ($panel === 'dashboard') {
    require __DIR__ . '/resources/views/fengbro/dashboard.php';
    return;
}

$testing = ($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing';
if ($panel === 'tube') {
    require_once __DIR__ . '/includes/fengbro_tube.php';
    $videos = $testing ? [] : (fengbroTubeGetData(false)['newVideos'] ?? []);
    jsonResponse($videos ? [
        'title' => '鋒兄tube 有 ' . count($videos) . ' 部 3 天內新影片',
        'summary' => ($videos[0]['channel'] ?? 'YouTube') . '：' . ($videos[0]['title'] ?? '最新影片'),
    ] : null);
}

if ($panel === 'finance') {
    require_once __DIR__ . '/includes/fengbro_finance.php';
    $quotes = $testing ? [] : (fengbroFinanceGetData(false, false)['quotes'] ?? []);
    $notices = array_filter($quotes, static fn ($quote) => trim((string) ($quote['status'] ?? '')) !== '');
    jsonResponse($notices ? [
        'title' => '鋒兄金融 ' . count($notices) . ' 項突破提醒',
        'summary' => implode('、', array_map(
            static fn ($quote) => ($quote['name'] ?? '-') . ' ' . $quote['status'],
            array_slice($notices, 0, 5)
        )),
    ] : null);
}

http_response_code(404);
