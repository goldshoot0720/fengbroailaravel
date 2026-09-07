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
const FENGBRO_VERSION = '1.0.0';

/**
 * 自動：專案內最新一次原始碼修改的日期（Y.m.d）。
 * 只看 .php / .css / .js / .sql，且跳過 uploads、vendor、node_modules、.git。
 */
function fengbroVersionDate(string $format = 'Y.m.d'): string
{
    static $latest = null;

    if ($latest === null) {
        $latest = 0;
        $root = dirname(__DIR__);
        $skipDirs = ['uploads', 'vendor', 'node_modules', '.git'];
        $exts = ['php', 'css', 'js', 'sql'];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;

                $path = str_replace('\\', '/', $file->getPathname());
                foreach ($skipDirs as $dir) {
                    if (str_contains($path, '/' . $dir . '/')) continue 2;
                }
                if (!in_array(strtolower($file->getExtension()), $exts, true)) continue;

                $mtime = (int) @$file->getMTime();
                if ($mtime > $latest) $latest = $mtime;
            }
        } catch (Throwable $e) {
            $latest = 0;
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
