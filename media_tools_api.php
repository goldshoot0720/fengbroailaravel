<?php
/**
 * 媒體工具 API：環境檢查、YT/B站轉檔、影片合併、圖片+音訊轉影片。
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/media_tools.php';
require_once __DIR__ . '/includes/media_tts.php';

@set_time_limit(660);
@ini_set('max_execution_time', '660');
@ini_set('memory_limit', '512M');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function mediaToolsJson(array $data, int $status = 200): void
{
    jsonResponse($data, $status);
}

function mediaToolsSendFile(array $result): void
{
    $path = $result['filePath'] ?? '';
    $filename = $result['filename'] ?? 'download.bin';
    $mime = $result['mime'] ?? 'application/octet-stream';
    $workDir = $result['workDir'] ?? '';
    if (!is_file($path)) {
        if ($workDir) {
            mediaToolsCleanupDir($workDir);
        }
        mediaToolsJson(['success' => false, 'error' => '輸出檔不存在'], 500);
    }

    // For JSON meta mode
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (isset($_GET['meta']) || (str_contains($accept, 'application/json') && isset($_GET['metaOnly']))) {
        $payload = [
            'success' => true,
            'filename' => $filename,
            'mime' => $mime,
            'size' => (int) ($result['size'] ?? filesize($path)),
            'successCount' => $result['successCount'] ?? 1,
            'total' => $result['total'] ?? 1,
            'logs' => $result['logs'] ?? [],
        ];
        // still need to cleanup - client won't download
        mediaToolsCleanupDir($workDir);
        mediaToolsJson($payload);
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    $ascii = preg_replace('/[^\x20-\x7E]+/', '_', $filename) ?: 'download';
    header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('X-Fengbro-Filename: ' . rawurlencode($filename));
    if (isset($result['successCount'])) {
        header('X-Fengbro-Success-Count: ' . (string) $result['successCount']);
    }
    if (isset($result['total'])) {
        header('X-Fengbro-Total: ' . (string) $result['total']);
    }
    // disable output buffering
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    readfile($path);
    mediaToolsCleanupDir($workDir);
    exit;
}

if ($action === 'status') {
    $tools = mediaToolsResolve();
    $voices = [];
    $ttsOk = true; // Google TTS is network-based default
    try {
        $voices = mediaTtsListVoices();
    } catch (Throwable $e) {
        $voices = [];
    }
    $langs = [];
    foreach (mediaTtsLangMap() as $code => $meta) {
        $langs[] = ['code' => $code, 'label' => $meta['label']];
    }
    mediaToolsJson([
        'success' => true,
        'available' => $tools['available'],
        'ytDlp' => $tools['ytDlp'] ? true : false,
        'ffmpeg' => $tools['ffmpeg'] ? true : false,
        'tts' => $ttsOk && !empty($tools['ffmpeg']),
        'ttsEngine' => 'google-tts(+sapi-fallback)',
        'languages' => $langs,
        'voices' => array_map(static fn($v) => [
            'name' => $v['name'],
            'culture' => $v['culture'],
            'gender' => $v['gender'],
        ], $voices),
        'ytDlpPath' => $tools['ytDlp'] ? basename($tools['ytDlp']) : null,
        'ffmpegPath' => $tools['ffmpeg'] ? basename($tools['ffmpeg']) : null,
        'installHint' => $tools['installHint'],
        'platform' => $tools['platform'],
        'note' => $tools['available']
            ? '已偵測到 yt-dlp 與 ffmpeg；TTS 預設 Google 多語，Windows 可備援 SAPI。'
            : '本機未找到 yt-dlp/ffmpeg。瀏覽器端工具仍可用。',
    ]);
}

if ($action === 'ytbili_convert') {
    try {
        $urls = [];
        $format = 'mp3';
        $mp4Quality = '1080p';
        $cookies = null;

        if (!empty($_POST['urls'])) {
            $raw = $_POST['urls'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $urls = is_array($decoded) ? $decoded : preg_split('/\r\n|\r|\n/', $raw);
            } elseif (is_array($raw)) {
                $urls = $raw;
            }
            $format = $_POST['format'] ?? $format;
            $mp4Quality = $_POST['mp4Quality'] ?? $mp4Quality;
            $cookies = isset($_POST['cookies']) ? (string) $_POST['cookies'] : null;
        } else {
            $body = file_get_contents('php://input');
            $json = is_string($body) && $body !== '' ? json_decode($body, true) : null;
            if (is_array($json)) {
                $urls = $json['urls'] ?? (isset($json['url']) ? [$json['url']] : []);
                $format = $json['format'] ?? $format;
                $mp4Quality = $json['mp4Quality'] ?? $mp4Quality;
                $cookies = isset($json['cookies']) ? (string) $json['cookies'] : null;
            }
        }

        $urls = array_values(array_filter(array_map(static fn($u) => trim((string) $u), (array) $urls)));
        $result = mediaToolsConvertUrls($urls, (string) $format, (string) $mp4Quality, $cookies);
        mediaToolsSendFile($result);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $code = str_starts_with($msg, 'TOOLS_MISSING') ? 503 : 500;
        if ($e instanceof InvalidArgumentException) {
            $code = 400;
        }
        mediaToolsJson([
            'success' => false,
            'error' => $msg,
            'code' => str_starts_with($msg, 'TOOLS_MISSING') ? 'TOOLS_MISSING' : null,
        ], $code);
    }
}

if ($action === 'video_merge') {
    try {
        if (empty($_FILES['clips'])) {
            mediaToolsJson(['success' => false, 'error' => '請上傳至少 2 個影片片段（欄位 clips[]）'], 400);
        }
        $files = $_FILES['clips'];
        $paths = [];
        $names = $files['name'] ?? [];
        $tmps = $files['tmp_name'] ?? [];
        $errs = $files['error'] ?? [];
        if (!is_array($tmps)) {
            $names = [$names];
            $tmps = [$tmps];
            $errs = [$errs];
        }
        $staging = mediaToolsTempDir('fengbro_upload_');
        foreach ($tmps as $i => $tmp) {
            if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                continue;
            }
            $orig = (string) ($names[$i] ?? 'clip.bin');
            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            $safeExt = preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'bin';
            $dest = $staging . DIRECTORY_SEPARATOR . sprintf('up_%02d.%s', $i + 1, $safeExt);
            if (!move_uploaded_file($tmp, $dest)) {
                continue;
            }
            $paths[] = $dest;
        }
        if (count($paths) < 2) {
            mediaToolsCleanupDir($staging);
            mediaToolsJson(['success' => false, 'error' => '有效片段不足 2 個'], 400);
        }
        $format = $_POST['format'] ?? 'mp4';
        $result = mediaToolsMergeClips($paths, (string) $format);
        $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
        if ($subtitle !== '' && strtolower((string) $format) !== 'mp3') {
            $lines = preg_split('/\r\n|\r|\n/', $subtitle) ?: [];
            try {
                $subbed = mediaToolsBurnSubtitles($result['filePath'], $lines);
                mediaToolsCleanupDir($result['workDir']);
                $result = $subbed;
            } catch (Throwable $subErr) {
                // keep unsubbed merge if burn fails
                $result['logs'][] = 'subtitle skipped: ' . $subErr->getMessage();
            }
        }
        // cleanup staging uploads
        mediaToolsCleanupDir($staging);
        mediaToolsSendFile($result);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $code = str_starts_with($msg, 'TOOLS_MISSING') ? 503 : 500;
        if ($e instanceof InvalidArgumentException) {
            $code = 400;
        }
        mediaToolsJson(['success' => false, 'error' => $msg], $code);
    }
}

if ($action === 'image_voice_video') {
    try {
        if (empty($_FILES['image']) || empty($_FILES['audio'])) {
            mediaToolsJson(['success' => false, 'error' => '請上傳 image 與 audio'], 400);
        }
        if (($_FILES['image']['error'] ?? 1) !== UPLOAD_ERR_OK || ($_FILES['audio']['error'] ?? 1) !== UPLOAD_ERR_OK) {
            mediaToolsJson(['success' => false, 'error' => '上傳失敗'], 400);
        }
        $staging = mediaToolsTempDir('fengbro_ivv_up_');
        $imgExt = pathinfo((string) $_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $audExt = pathinfo((string) $_FILES['audio']['name'], PATHINFO_EXTENSION) ?: 'webm';
        $imgPath = $staging . DIRECTORY_SEPARATOR . 'image.' . preg_replace('/[^a-z0-9]/i', '', $imgExt);
        $audPath = $staging . DIRECTORY_SEPARATOR . 'audio.' . preg_replace('/[^a-z0-9]/i', '', $audExt);
        move_uploaded_file($_FILES['image']['tmp_name'], $imgPath);
        move_uploaded_file($_FILES['audio']['tmp_name'], $audPath);
        $result = mediaToolsImageAudioToVideo($imgPath, $audPath);
        mediaToolsCleanupDir($staging);
        mediaToolsSendFile($result);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $code = (str_starts_with($msg, 'TOOLS_MISSING') || str_starts_with($msg, 'TTS_UNAVAILABLE')) ? 503 : 500;
        if ($e instanceof InvalidArgumentException) {
            $code = 400;
        }
        mediaToolsJson(['success' => false, 'error' => $msg], $code);
    }
}

/** 一鍵：封面圖 + 語音稿 → SAPI TTS + ffmpeg（嵌音軌 + 燒錄字幕） */
if ($action === 'ivv_generate') {
    try {
        if (empty($_FILES['image']) || ($_FILES['image']['error'] ?? 1) !== UPLOAD_ERR_OK) {
            mediaToolsJson(['success' => false, 'error' => '請上傳封面圖片'], 400);
        }
        $script = trim((string) ($_POST['script'] ?? ''));
        if ($script === '') {
            mediaToolsJson(['success' => false, 'error' => '請提供語音稿 script'], 400);
        }
        $lines = preg_split('/\r\n|\r|\n/', $script) ?: [];
        $gender = strtolower(trim((string) ($_POST['gender'] ?? 'female'))) === 'male' ? 'male' : 'female';
        $rate = (int) ($_POST['rate'] ?? 0);
        $orient = (string) ($_POST['orientation'] ?? 'auto');
        if (!in_array($orient, ['auto', 'portrait', 'landscape'], true)) {
            $orient = 'auto';
        }
        $lang = (string) ($_POST['lang'] ?? 'zh-TW');
        $translateTo = trim((string) ($_POST['translateTo'] ?? ''));
        $staging = mediaToolsTempDir('fengbro_ivv_gen_');
        $imgExt = pathinfo((string) $_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $imgPath = $staging . DIRECTORY_SEPARATOR . 'image.' . preg_replace('/[^a-z0-9]/i', '', $imgExt);
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $imgPath)) {
            mediaToolsCleanupDir($staging);
            mediaToolsJson(['success' => false, 'error' => '圖片上傳失敗'], 400);
        }
        $result = mediaTtsImageScriptToVideo($imgPath, $lines, $gender, $rate, $orient, $lang, $translateTo);
        mediaToolsCleanupDir($staging);
        mediaToolsSendFile($result);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $code = (str_starts_with($msg, 'TOOLS_MISSING') || str_starts_with($msg, 'TTS_UNAVAILABLE')) ? 503 : 500;
        if ($e instanceof InvalidArgumentException) {
            $code = 400;
        }
        mediaToolsJson(['success' => false, 'error' => $msg], $code);
    }
}

if ($action === 'extract_audio') {
    try {
        if (empty($_FILES['media']) || ($_FILES['media']['error'] ?? 1) !== UPLOAD_ERR_OK) {
            mediaToolsJson(['success' => false, 'error' => '請上傳 media 檔案（影片或音訊）'], 400);
        }
        $maxSec = (int) ($_POST['maxSeconds'] ?? $_GET['maxSeconds'] ?? 600);
        $staging = mediaToolsTempDir('fengbro_aext_up_');
        $ext = pathinfo((string) $_FILES['media']['name'], PATHINFO_EXTENSION) ?: 'bin';
        $src = $staging . DIRECTORY_SEPARATOR . 'up.' . preg_replace('/[^a-z0-9]/i', '', $ext);
        if (!move_uploaded_file($_FILES['media']['tmp_name'], $src)) {
            mediaToolsCleanupDir($staging);
            mediaToolsJson(['success' => false, 'error' => '上傳失敗'], 400);
        }
        $result = mediaToolsExtractAudio($src, $maxSec);
        mediaToolsCleanupDir($staging);
        mediaToolsSendFile($result);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $code = str_starts_with($msg, 'TOOLS_MISSING') ? 503 : 500;
        if ($e instanceof InvalidArgumentException) {
            $code = 400;
        }
        mediaToolsJson(['success' => false, 'error' => $msg], $code);
    }
}

if ($action === 'translate') {
    try {
        $payload = [];
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $lines = $payload['lines'] ?? [];
        if (!is_array($lines) || !$lines) {
            mediaToolsJson(['success' => false, 'error' => 'lines required'], 400);
        }
        if (count($lines) > 120) {
            mediaToolsJson(['success' => false, 'error' => 'too many lines'], 400);
        }
        $target = (string) ($payload['language'] ?? $payload['target'] ?? 'en-US');
        $source = (string) ($payload['source_language'] ?? $payload['source'] ?? 'auto');
        $translated = mediaTtsTranslateLines($lines, $target, $source);
        mediaToolsJson(['success' => true, 'lines' => $translated, 'target' => $target]);
    } catch (Throwable $e) {
        mediaToolsJson(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'phone_history_csv') {
    try {
        require_once __DIR__ . '/includes/functions.php';
        $pdo = getConnection();
        // ensure table exists via tools_api helper pattern
        $pdo->exec("CREATE TABLE IF NOT EXISTS tool_phone_product_history (
            id VARCHAR(36) PRIMARY KEY,
            product_id VARCHAR(190) NOT NULL,
            brand VARCHAR(50),
            name VARCHAR(500) NOT NULL,
            source VARCHAR(50) NOT NULL,
            price INT NULL,
            source_url VARCHAR(1000),
            snapshot_day DATE NOT NULL,
            INDEX idx_phone_hist (product_id, snapshot_day)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // suggested price not always stored — export landtop/jyes rows
        $rows = $pdo->query("SELECT product_id, brand, name, source, price, source_url, snapshot_day
            FROM tool_phone_product_history
            ORDER BY snapshot_day DESC, name ASC
            LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="phone-price-history.csv"');
        echo "\xEF\xBB\xBF";
        echo "productId,brand,name,sourceUrl,landtopPrice,jyesPrice,snapshotDate,source\n";
        // pivot same product/day
        $bucket = [];
        foreach ($rows as $r) {
            $key = ($r['product_id'] ?? '') . '|' . ($r['snapshot_day'] ?? '');
            if (!isset($bucket[$key])) {
                $bucket[$key] = [
                    'productId' => $r['product_id'] ?? '',
                    'brand' => $r['brand'] ?? '',
                    'name' => $r['name'] ?? '',
                    'sourceUrl' => $r['source_url'] ?? '',
                    'landtopPrice' => '',
                    'jyesPrice' => '',
                    'snapshotDate' => $r['snapshot_day'] ?? '',
                    'source' => $r['source'] ?? '',
                ];
            }
            $src = strtolower((string) ($r['source'] ?? ''));
            $price = $r['price'] !== null ? (string) $r['price'] : '';
            if (str_contains($src, 'landtop') || $src === '地標' || $src === '地標網通') {
                $bucket[$key]['landtopPrice'] = $price;
            } elseif (str_contains($src, 'jyes') || str_contains($src, '傑昇')) {
                $bucket[$key]['jyesPrice'] = $price;
            } else {
                if ($bucket[$key]['landtopPrice'] === '') {
                    $bucket[$key]['landtopPrice'] = $price;
                }
            }
            if (!empty($r['source_url'])) {
                $bucket[$key]['sourceUrl'] = $r['source_url'];
            }
        }
        foreach ($bucket as $row) {
            $cells = [
                $row['productId'], $row['brand'], $row['name'], $row['sourceUrl'],
                $row['landtopPrice'], $row['jyesPrice'], $row['snapshotDate'], $row['source'],
            ];
            $out = [];
            foreach ($cells as $c) {
                $out[] = '"' . str_replace('"', '""', (string) $c) . '"';
            }
            echo implode(',', $out) . "\n";
        }
        exit;
    } catch (Throwable $e) {
        mediaToolsJson(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

mediaToolsJson(['success' => false, 'error' => '不支援的動作'], 400);
