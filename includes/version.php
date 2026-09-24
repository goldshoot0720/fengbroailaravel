<?php
declare(strict_types=1);

/**
 * 版本資訊。
 *
 * 兩種格式並用：
 * - FENGBRO_VERSION：語意化版本號，改功能時「手動」調整。
 * - fengbroVersionDate()：日期版本，由專案內最新的原始碼修改時間「自動」算出。
 *
 * 顯示時用 fengbroVersionLabel()，例如：1.0.0（2026.09.07）
 */

// ── 手動維護：有新功能改中間那碼，修 bug 改最後那碼 ──────────────────────
const FENGBRO_VERSION = '1.1.0';

/**
 * 走訪專案內的原始碼檔（.php / .css / .js / .sql），跳過 uploads、vendor、node_modules、.git。
 * 跳過的目錄「不會進入」，不必逐一掃過 uploads 內大量的媒體檔。
 *
 * @return iterable<SplFileInfo>
 */
function fengbroSourceFiles(?string $root = null): iterable
{
    $root = $root ?? dirname(__DIR__);
    $skipDirs = ['uploads', 'vendor', 'node_modules', '.git'];
    $exts = ['php', 'css', 'js', 'sql'];
    try {
        $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $file, $key, RecursiveDirectoryIterator $iterator) use ($skipDirs, $exts): bool {
                if ($iterator->hasChildren()) {
                    return !in_array($file->getFilename(), $skipDirs, true);
                }
                return $file->isFile() && in_array(strtolower($file->getExtension()), $exts, true);
            }
        );
        yield from new RecursiveIteratorIterator(
            $filter,
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
    } catch (Throwable $e) {
        return;
    }
}

/**
 * 程式碼行數統計：['php' => 行數, 'css' => …, 'js' => …, 'sql' => …, 'files' => 檔案數, 'total' => 總行數]
 */
function fengbroCodeStats(?string $root = null): array
{
    $stats = ['php' => 0, 'css' => 0, 'js' => 0, 'sql' => 0, 'files' => 0];
    foreach (fengbroSourceFiles($root) as $file) {
        $ext = strtolower($file->getExtension());
        $lines = @count(file($file->getPathname()) ?: []);
        $stats[$ext] += $lines;
        $stats['files']++;
    }
    $stats['total'] = $stats['php'] + $stats['css'] + $stats['js'] + $stats['sql'];
    return $stats;
}

/**
 * 自動：專案內最新一次原始碼修改的日期（Y.m.d）。
 */
function fengbroVersionDate(string $format = 'Y.m.d'): string
{
    static $latest = null;

    if ($latest === null) {
        $latest = 0;
        foreach (fengbroSourceFiles() as $file) {
            $mtime = (int) @$file->getMTime();
            if ($mtime > $latest) $latest = $mtime;
        }
        if ($latest <= 0) $latest = time();
    }

    return date($format, $latest);
}

/**
 * 顯示用的完整版本字串：1.0.0（2026.09.07）
 */
function fengbroVersionLabel(): string
{
    return FENGBRO_VERSION . '（' . fengbroVersionDate() . '）';
}
