<?php
/**
 * 媒體工具：yt-dlp / ffmpeg 解析與轉檔（對齊 Appwrite youtube-bilibili-convert + video merge）。
 */

function mediaToolsIsWindows(): bool
{
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

function mediaToolsWhich(string $name): ?string
{
    $envKey = strtoupper(str_replace('-', '_', $name)) . '_PATH';
    $env = getenv($envKey);
    if (is_string($env) && $env !== '' && is_file($env)) {
        return $env;
    }
    // Common aliases
    if ($name === 'yt-dlp') {
        foreach (['YT_DLP_PATH', 'YTDLP_PATH'] as $k) {
            $v = getenv($k);
            if (is_string($v) && $v !== '' && is_file($v)) {
                return $v;
            }
        }
    }

    $cmd = mediaToolsIsWindows()
        ? 'where ' . escapeshellarg($name)
        : 'command -v ' . escapeshellarg($name);
    $out = [];
    $code = 0;
    @exec($cmd, $out, $code);
    if ($code === 0 && !empty($out[0])) {
        $path = trim($out[0]);
        if ($path !== '' && is_file($path)) {
            return $path;
        }
    }

    // Winget package layout on this machine (best-effort)
    if (mediaToolsIsWindows()) {
        $local = getenv('LOCALAPPDATA') ?: '';
        if ($local !== '') {
            $candidates = [];
            if ($name === 'yt-dlp') {
                $candidates[] = $local . '\\Microsoft\\WinGet\\Packages\\yt-dlp.yt-dlp_Microsoft.Winget.Source_8wekyb3d8bbwe\\yt-dlp.exe';
            }
            if ($name === 'ffmpeg') {
                $winget = $local . '\\Microsoft\\WinGet\\Packages';
                if (is_dir($winget)) {
                    $hits = glob($winget . '\\*\\**\\ffmpeg.exe') ?: [];
                    // recursive glob may not work on all PHP; fallback scan shallow
                    if (!$hits) {
                        foreach (glob($winget . '\\*') ?: [] as $pkg) {
                            if (!is_dir($pkg)) {
                                continue;
                            }
                            $nested = glob($pkg . '\\*\\bin\\ffmpeg.exe') ?: [];
                            $hits = array_merge($hits, $nested);
                            $nested2 = glob($pkg . '\\bin\\ffmpeg.exe') ?: [];
                            $hits = array_merge($hits, $nested2);
                        }
                    }
                    foreach ($hits as $h) {
                        if (is_file($h)) {
                            $candidates[] = $h;
                        }
                    }
                }
            }
            foreach ($candidates as $c) {
                if (is_file($c)) {
                    return $c;
                }
            }
        }
    }

    return null;
}

/**
 * @return array{ytDlp:?string,ffmpeg:?string,available:bool,installHint:list<string>,platform:string}
 */
function mediaToolsResolve(): array
{
    $yt = mediaToolsWhich('yt-dlp');
    $ff = mediaToolsWhich('ffmpeg');
    $hints = [];
    if (!$yt || !$ff) {
        if (mediaToolsIsWindows()) {
            $hints[] = 'Windows 請安裝：winget install yt-dlp.yt-dlp Gyan.FFmpeg';
            $hints[] = '或設定環境變數 YT_DLP_PATH / FFMPEG_PATH 指向執行檔。';
        } else {
            $hints[] = '請安裝 yt-dlp 與 ffmpeg（apt/brew），或設定 YT_DLP_PATH / FFMPEG_PATH。';
        }
    }
    return [
        'ytDlp' => $yt,
        'ffmpeg' => $ff,
        'available' => (bool) ($yt && $ff),
        'installHint' => $hints,
        'platform' => PHP_OS_FAMILY,
    ];
}

function mediaToolsDetectPlatform(string $url): string
{
    $url = trim($url);
    try {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return 'unknown';
        }
        if (
            $host === 'youtu.be' ||
            $host === 'youtube.com' ||
            str_ends_with($host, '.youtube.com') ||
            $host === 'music.youtube.com'
        ) {
            return 'youtube';
        }
        if (preg_match('/(^|\.)bilibili\.com$/i', $host) || preg_match('/(^|\.)b23\.tv$/i', $host)) {
            return 'bilibili';
        }
    } catch (Throwable $e) {
        return 'unknown';
    }
    return 'unknown';
}

function mediaToolsIsAllowedUrl(string $url): bool
{
    return mediaToolsDetectPlatform($url) !== 'unknown';
}

function mediaToolsNormalizeUrl(string $url): string
{
    $url = trim($url);
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return $url;
    }
    $host = strtolower($parts['host']);
    if (!preg_match('/(^|\.)bilibili\.com$/i', $host) && !preg_match('/(^|\.)b23\.tv$/i', $host)) {
        return $url;
    }
    $drop = ['spm_id_from', 'from_spmid', 'vd_source', 'share_source', 'share_medium', 'share_plat', 'share_session_id', 'unique_k'];
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $q);
        foreach ($q as $k => $v) {
            if (!in_array($k, $drop, true)) {
                $query[$k] = $v;
            }
        }
    }
    $scheme = $parts['scheme'] ?? 'https';
    $path = $parts['path'] ?? '';
    $qs = http_build_query($query);
    return $scheme . '://' . $parts['host'] . $path . ($qs !== '' ? '?' . $qs : '');
}

function mediaToolsTempDir(string $prefix = 'fengbro_media_'): string
{
    $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(6));
    if (!is_dir($base) && !@mkdir($base, 0700, true)) {
        throw new RuntimeException('無法建立暫存目錄');
    }
    return $base;
}

function mediaToolsCleanupDir(string $dir): void
{
    if ($dir === '' || !is_dir($dir)) {
        return;
    }
    $real = realpath($dir);
    $tmp = realpath(sys_get_temp_dir());
    if ($real === false || $tmp === false || !str_starts_with($real, $tmp)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        if ($file->isDir()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }
    @rmdir($real);
}

/**
 * @return array{ok:bool,exitCode:int,stdout:string,stderr:string}
 */
function mediaToolsRun(array $args, int $timeoutSec = 600, ?string $cwd = null): array
{
    if (!$args) {
        return ['ok' => false, 'exitCode' => -1, 'stdout' => '', 'stderr' => 'empty command'];
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    // PHP 7.4+ array command bypasses shell (safer paths with spaces on Windows)
    $proc = @proc_open($args, $descriptors, $pipes, $cwd ?: null, null);
    if (!is_resource($proc)) {
        return ['ok' => false, 'exitCode' => -1, 'stdout' => '', 'stderr' => 'proc_open failed'];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $start = time();
    $exitCode = -1;
    while (true) {
        $status = proc_get_status($proc);
        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        if (!$status['running']) {
            $exitCode = (int) $status['exitcode'];
            break;
        }
        if (time() - $start > $timeoutSec) {
            proc_terminate($proc, 9);
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            return ['ok' => false, 'exitCode' => -1, 'stdout' => $stdout, 'stderr' => $stderr . "\ntimeout after {$timeoutSec}s"];
        }
        usleep(100000);
    }
    $stdout .= stream_get_contents($pipes[1]) ?: '';
    $stderr .= stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return [
        'ok' => $exitCode === 0,
        'exitCode' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/**
 * @param list<string> $urls
 * @return array{workDir:string,filePath:string,filename:string,mime:string,size:int,successCount:int,total:int,logs:list<string>}
 */
function mediaToolsConvertUrls(array $urls, string $format = 'mp3', string $mp4Quality = '1080p', ?string $cookiesText = null): array
{
    $tools = mediaToolsResolve();
    if (!$tools['available']) {
        throw new RuntimeException('TOOLS_MISSING: 找不到 yt-dlp / ffmpeg');
    }

    $format = strtolower($format) === 'mp4' ? 'mp4' : 'mp3';
    $mp4Quality = $mp4Quality === '720p' ? '720p' : '1080p';
    $norm = [];
    foreach ($urls as $u) {
        $u = mediaToolsNormalizeUrl((string) $u);
        if (!mediaToolsIsAllowedUrl($u)) {
            throw new InvalidArgumentException('僅支援 YouTube 或 Bilibili 網址');
        }
        $norm[] = $u;
    }
    $norm = array_values(array_unique($norm));
    if (!$norm) {
        throw new InvalidArgumentException('請至少提供一個網址');
    }
    if (count($norm) > 7) {
        throw new InvalidArgumentException('一次最多 7 個網址');
    }

    $workDir = mediaToolsTempDir('fengbro_yt_');
    $outDir = $workDir . DIRECTORY_SEPARATOR . 'out';
    @mkdir($outDir, 0700, true);
    $logs = [];
    $cookiesPath = null;
    if (is_string($cookiesText) && trim($cookiesText) !== '') {
        $cookiesPath = $workDir . DIRECTORY_SEPARATOR . 'cookies.txt';
        file_put_contents($cookiesPath, $cookiesText);
    } else {
        $envCookies = getenv('YT_DLP_COOKIES_PATH');
        if (is_string($envCookies) && $envCookies !== '' && is_file($envCookies)) {
            $cookiesPath = $envCookies;
        }
    }

    $successFiles = [];
    foreach ($norm as $i => $url) {
        $platform = mediaToolsDetectPlatform($url);
        $tpl = $outDir . DIRECTORY_SEPARATOR . sprintf('%02d_%%(title).80B.%%(ext)s', $i + 1);
        $args = [
            $tools['ytDlp'],
            '--no-playlist',
            '--newline',
            '-o', $tpl,
            '--ffmpeg-location', dirname($tools['ffmpeg']),
        ];
        if ($cookiesPath) {
            $args[] = '--cookies';
            $args[] = $cookiesPath;
        }
        if ($format === 'mp3') {
            $args[] = '-x';
            $args[] = '--audio-format';
            $args[] = 'mp3';
            $args[] = '--audio-quality';
            $args[] = '0';
            $args[] = '-f';
            $args[] = $platform === 'youtube' ? 'bestaudio/best[ext=mp4]/18/best' : 'bestaudio/best';
        } else {
            $h = $mp4Quality === '720p' ? 720 : 1080;
            $args[] = '-f';
            $args[] = "bestvideo*[height<={$h}]+bestaudio[ext=m4a]/bestaudio/best[height<={$h}]/best";
            $args[] = '--merge-output-format';
            $args[] = 'mp4';
        }
        $args[] = $url;
        $logs[] = '>>> ' . $url;
        $run = mediaToolsRun($args, 600, $workDir);
        $logs[] = trim($run['stdout'] . "\n" . $run['stderr']);
        if (!$run['ok']) {
            // progressive fallback for youtube MP4
            if ($platform === 'youtube' && $format === 'mp4') {
                $argsFb = [
                    $tools['ytDlp'],
                    '--no-playlist',
                    '-o', $tpl,
                    '--ffmpeg-location', dirname($tools['ffmpeg']),
                ];
                if ($cookiesPath) {
                    array_push($argsFb, '--cookies', $cookiesPath);
                }
                array_push($argsFb, '-f', '18/best[ext=mp4]/best', '--merge-output-format', 'mp4', $url);
                $run2 = mediaToolsRun($argsFb, 600, $workDir);
                $logs[] = 'fallback: ' . trim($run2['stdout'] . "\n" . $run2['stderr']);
                if (!$run2['ok']) {
                    continue;
                }
            } else {
                continue;
            }
        }
    }

    $files = [];
    foreach (glob($outDir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
        if (is_file($f) && preg_match('/\.(mp3|mp4|m4a|webm|mkv)$/i', $f)) {
            $files[] = $f;
        }
    }
    sort($files);
    if (!$files) {
        mediaToolsCleanupDir($workDir);
        throw new RuntimeException("轉檔失敗，未產生輸出檔。\n" . implode("\n", array_slice($logs, -8)));
    }

    $successCount = count($files);
    if (count($files) === 1) {
        $filePath = $files[0];
        $filename = basename($filePath);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = $ext === 'mp3' ? 'audio/mpeg' : 'video/mp4';
        return [
            'workDir' => $workDir,
            'filePath' => $filePath,
            'filename' => $filename,
            'mime' => $mime,
            'size' => (int) filesize($filePath),
            'successCount' => $successCount,
            'total' => count($norm),
            'logs' => $logs,
        ];
    }

    // zip multiple
    $zipPath = $workDir . DIRECTORY_SEPARATOR . 'converted.zip';
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($files as $f) {
                $zip->addFile($f, basename($f));
            }
            $zip->close();
        }
    }
    if (!is_file($zipPath)) {
        // fallback PureZip if available
        require_once __DIR__ . '/PureZip.php';
        if (class_exists('PureZip')) {
            // PureZip may have different API - skip and return first file
        }
        // return first file only if zip failed
        $filePath = $files[0];
        return [
            'workDir' => $workDir,
            'filePath' => $filePath,
            'filename' => basename($filePath),
            'mime' => preg_match('/\.mp3$/i', $filePath) ? 'audio/mpeg' : 'video/mp4',
            'size' => (int) filesize($filePath),
            'successCount' => $successCount,
            'total' => count($norm),
            'logs' => array_merge($logs, ['ZIP 不可用，僅回傳第一個檔案']),
        ];
    }

    return [
        'workDir' => $workDir,
        'filePath' => $zipPath,
        'filename' => 'converted.zip',
        'mime' => 'application/zip',
        'size' => (int) filesize($zipPath),
        'successCount' => $successCount,
        'total' => count($norm),
        'logs' => $logs,
    ];
}

/**
 * Concatenate uploaded video/audio clips with ffmpeg.
 * @param list<string> $inputPaths absolute temp paths already saved
 * @return array{workDir:string,filePath:string,filename:string,mime:string,size:int,logs:list<string>}
 */
function mediaToolsMergeClips(array $inputPaths, string $outputFormat = 'mp4'): array
{
    $tools = mediaToolsResolve();
    if (empty($tools['ffmpeg'])) {
        throw new RuntimeException('TOOLS_MISSING: 找不到 ffmpeg');
    }
    $paths = array_values(array_filter($inputPaths, static fn($p) => is_string($p) && is_file($p)));
    if (count($paths) < 2) {
        throw new InvalidArgumentException('請至少上傳 2 個片段');
    }
    if (count($paths) > 12) {
        throw new InvalidArgumentException('一次最多 12 個片段');
    }

    $workDir = mediaToolsTempDir('fengbro_merge_');
    $listFile = $workDir . DIRECTORY_SEPARATOR . 'list.txt';
    $lines = [];
    foreach ($paths as $i => $src) {
        $ext = pathinfo($src, PATHINFO_EXTENSION) ?: 'bin';
        $dest = $workDir . DIRECTORY_SEPARATOR . sprintf('clip_%02d.%s', $i + 1, preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'bin');
        if (!@copy($src, $dest)) {
            throw new RuntimeException('無法複製上傳片段');
        }
        // ffmpeg concat demuxer needs escaped single quotes in path
        $safe = str_replace("'", "'\\''", $dest);
        if (mediaToolsIsWindows()) {
            $safe = str_replace('\\', '/', $dest);
        }
        $lines[] = "file '" . $safe . "'";
    }
    file_put_contents($listFile, implode("\n", $lines) . "\n");

    $outputFormat = strtolower($outputFormat) === 'mp3' ? 'mp3' : 'mp4';
    $outName = 'merged.' . $outputFormat;
    $outPath = $workDir . DIRECTORY_SEPARATOR . $outName;
    $args = [
        $tools['ffmpeg'],
        '-y',
        '-f', 'concat',
        '-safe', '0',
        '-i', $listFile,
    ];
    if ($outputFormat === 'mp3') {
        array_push($args, '-vn', '-acodec', 'libmp3lame', '-q:a', '2', $outPath);
    } else {
        // re-encode for mixed codecs
        array_push($args, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-c:a', 'aac', '-movflags', '+faststart', $outPath);
    }
    $run = mediaToolsRun($args, 600, $workDir);
    $logs = [trim($run['stdout'] . "\n" . $run['stderr'])];
    if (!$run['ok'] || !is_file($outPath)) {
        mediaToolsCleanupDir($workDir);
        throw new RuntimeException("合併失敗\n" . implode("\n", $logs));
    }
    return [
        'workDir' => $workDir,
        'filePath' => $outPath,
        'filename' => $outName,
        'mime' => $outputFormat === 'mp3' ? 'audio/mpeg' : 'video/mp4',
        'size' => (int) filesize($outPath),
        'logs' => $logs,
    ];
}

/**
 * Image + audio → video via ffmpeg (server assist for browser-recorded webm/mp3).
 * For pure browser IVV, client may not call this.
 *
 * @return array{workDir:string,filePath:string,filename:string,mime:string,size:int,logs:list<string>}
 */
function mediaToolsImageAudioToVideo(string $imagePath, string $audioPath, string $outExt = 'mp4'): array
{
    $tools = mediaToolsResolve();
    if (empty($tools['ffmpeg'])) {
        throw new RuntimeException('TOOLS_MISSING: 找不到 ffmpeg');
    }
    if (!is_file($imagePath) || !is_file($audioPath)) {
        throw new InvalidArgumentException('缺少圖片或音訊檔');
    }
    $workDir = mediaToolsTempDir('fengbro_ivv_');
    $img = $workDir . DIRECTORY_SEPARATOR . 'cover' . (pathinfo($imagePath, PATHINFO_EXTENSION) ? '.' . pathinfo($imagePath, PATHINFO_EXTENSION) : '.jpg');
    $aud = $workDir . DIRECTORY_SEPARATOR . 'voice' . (pathinfo($audioPath, PATHINFO_EXTENSION) ? '.' . pathinfo($audioPath, PATHINFO_EXTENSION) : '.webm');
    copy($imagePath, $img);
    copy($audioPath, $aud);
    $outPath = $workDir . DIRECTORY_SEPARATOR . 'output.mp4';
    $args = [
        $tools['ffmpeg'],
        '-y',
        '-loop', '1',
        '-i', $img,
        '-i', $aud,
        '-c:v', 'libx264',
        '-tune', 'stillimage',
        '-c:a', 'aac',
        '-b:a', '192k',
        '-pix_fmt', 'yuv420p',
        '-shortest',
        '-movflags', '+faststart',
        $outPath,
    ];
    $run = mediaToolsRun($args, 300, $workDir);
    $logs = [trim($run['stdout'] . "\n" . $run['stderr'])];
    if (!$run['ok'] || !is_file($outPath)) {
        mediaToolsCleanupDir($workDir);
        throw new RuntimeException("圖片+語音轉影片失敗\n" . implode("\n", $logs));
    }
    return [
        'workDir' => $workDir,
        'filePath' => $outPath,
        'filename' => 'image-voice-video.mp4',
        'mime' => 'video/mp4',
        'size' => (int) filesize($outPath),
        'logs' => $logs,
    ];
}
