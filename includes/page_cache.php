<?php
/**
 * 頁面條件式快取（ETag / 304）。
 *
 * 切換選單回到同一頁時，若資料沒有變更，伺服器直接回 304 Not Modified：
 * 不查資料庫、不重新產生 HTML，瀏覽器直接用手上那份。
 *
 *  - 資料版本：任何寫入請求（POST/PUT/PATCH/DELETE，或 GET 帶 create/update/delete… 等動作）
 *    結束時更新一次版本標記檔（fengbroBumpDataVersion）。
 *  - ETag = 資料版本 + 今天日期（到期天數、顏色標示每天會變）+ 程式版本（檔案修改時間）
 *    + CSRF token（換 session 時不會拿到舊 token 的頁面）+ 網址。
 *  - Cache-Control: private, no-cache：每次都向伺服器確認，但允許重用與上一頁／下一頁快取（bfcache）。
 */

/** 會觸發寫入的 GET 動作（其餘 GET 視為唯讀）。 */
function fengbroIsWriteAction(string $action): bool
{
    return (bool) preg_match(
        '/^(create|update|delete|restore|empty|save|import|remove|clean|send|init|set|add|toggle|sync|reset|clear|record|upload|merge|rename|move)/i',
        $action
    );
}

function fengbroRequestIsWrite(): bool
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return true;
    }
    return fengbroIsWriteAction((string) ($_GET['action'] ?? ''));
}

function fengbroDataVersionPath(): ?string
{
    if (function_exists('fengbroSchemaCacheDir')) {
        $dir = fengbroSchemaCacheDir();
        if ($dir !== null) {
            return $dir . DIRECTORY_SEPARATOR . 'data-version';
        }
    }
    $fallback = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'fengbro_data_version_' . substr(md5(__DIR__ . '|' . (defined('DB_NAME') ? (string) DB_NAME : '')), 0, 12);
    return is_writable(dirname($fallback)) ? $fallback : null;
}

/** 目前資料版本；無法讀寫標記檔時回傳 null（此時不啟用 304）。 */
function fengbroDataVersion(): ?string
{
    $path = fengbroDataVersionPath();
    if ($path === null) {
        return null;
    }
    $value = @file_get_contents($path);
    if (!is_string($value) || $value === '') {
        return fengbroBumpDataVersion();
    }
    return trim($value);
}

function fengbroBumpDataVersion(): ?string
{
    $path = fengbroDataVersionPath();
    if ($path === null) {
        return null;
    }
    $value = sprintf('%.6f-%s', microtime(true), bin2hex(random_bytes(4)));
    return @file_put_contents($path, $value, LOCK_EX) === false ? null : $value;
}

/** 寫入請求結束時（不論成功與否）更新資料版本，讓之後的頁面請求一定拿到新內容。 */
function fengbroTrackDataWrites(): void
{
    static $registered = false;
    if ($registered || PHP_SAPI === 'cli' || !fengbroRequestIsWrite()) {
        return;
    }
    $registered = true;
    register_shutdown_function(static function (): void {
        fengbroBumpDataVersion();
    });
}

/** 程式版本：頁面與共用樣板的最後修改時間，部署新程式後 ETag 自動失效。 */
function fengbroCodeVersion(string $pageFile): string
{
    $root = dirname(__DIR__);
    $files = array_merge(
        [$root . '/index.php', $root . '/' . ltrim($pageFile, '/')],
        (array) glob(__DIR__ . '/*.php')
    );
    $latest = 0;
    foreach ($files as $file) {
        $mtime = @filemtime((string) $file);
        if ($mtime !== false && $mtime > $latest) {
            $latest = $mtime;
        }
    }
    return (string) $latest;
}

function fengbroPageEtag(string $dataVersion, string $pageFile, string $csrfToken, string $uri, ?string $today = null): string
{
    $today = $today ?? date('Y-m-d');
    return '"fb-' . substr(sha1(implode('|', [
        $dataVersion, $today, fengbroCodeVersion($pageFile), $csrfToken, $uri,
    ])), 0, 32) . '"';
}

/** If-None-Match 是否符合（容忍 W/ 前綴與 Apache 壓縮時加的 -gzip / -br 後綴）。 */
function fengbroEtagMatches(string $ifNoneMatch, string $etag): bool
{
    $normalize = static function (string $tag): string {
        $tag = trim($tag);
        if (stripos($tag, 'W/') === 0) {
            $tag = substr($tag, 2);
        }
        $tag = trim($tag, '"');
        return (string) preg_replace('/-(gzip|br|deflate|zstd)$/i', '', $tag);
    };
    $want = $normalize($etag);
    foreach (explode(',', $ifNoneMatch) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '*' || ($candidate !== '' && $normalize($candidate) === $want)) {
            return true;
        }
    }
    return false;
}

/**
 * 頁面進入點呼叫：資料沒變就回 304 並結束；否則送出 ETag 讓瀏覽器下次可以問「有沒有變」。
 * $cacheable = false 的頁面（工具、設定：含外部即時資料或診斷）維持 no-store。
 */
function fengbroServePageWithEtag(string $pageFile, bool $cacheable): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!$cacheable || !in_array($method, ['GET', 'HEAD'], true)) {
        return;
    }
    $dataVersion = fengbroDataVersion();
    if ($dataVersion === null) {
        return;
    }
    $etag = fengbroPageEtag(
        $dataVersion,
        $pageFile,
        fengbroCsrfToken(),
        (string) ($_SERVER['REQUEST_URI'] ?? '')
    );
    header_remove('Pragma');
    header('Cache-Control: private, no-cache');
    header('ETag: ' . $etag);
    $ifNoneMatch = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
    if ($ifNoneMatch !== '' && fengbroEtagMatches($ifNoneMatch, $etag)) {
        http_response_code(304);
        exit;
    }
}
