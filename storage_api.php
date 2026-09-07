<?php
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'scan';
$uploadsRoot = realpath(__DIR__ . '/uploads');
$uploadsMissing = (!$uploadsRoot || !is_dir($uploadsRoot));

// 有檔案欄位、且刪除紀錄不會連動其他資料的媒體資料表
const FENGBRO_MEDIA_TABLES = [
    'image' => '鋒兄圖片',
    'video' => '鋒兄影片',
    'music' => '鋒兄音樂',
    'podcast' => '鋒兄播客',
    'commondocument' => '鋒兄文件',
];

function normalizeStoragePath($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    $parts = parse_url($value);
    $path = $parts['path'] ?? $value;
    $path = str_replace('\\', '/', $path);
    $pos = strpos($path, 'uploads/');
    if ($pos !== false) {
        return ltrim(substr($path, $pos), '/');
    }
    if (strpos($path, '/uploads/') !== false) {
        return ltrim(substr($path, strpos($path, '/uploads/') + 1), '/');
    }
    return ltrim($path, '/');
}

/**
 * 只有本站 uploads 目錄底下的相對路徑才「可以檢查檔案在不在」。
 * 外部網址（http/https/data/blob）與看不出 uploads 結構的值一律略過，避免誤判成殘餘。
 */
function localUploadPath($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) || strpos($value, '//') === 0) return '';
    $path = normalizeStoragePath($value);
    if ($path === '' || strpos($path, 'uploads/') !== 0 || strpos($path, '..') !== false) return '';
    return $path;
}

function uploadFileExists($relativePath) {
    if ($relativePath === '') return true;
    return is_file(__DIR__ . '/' . $relativePath);
}

function collectReferencedFiles(PDO $pdo) {
    $refs = [];
    $map = [
        'food' => ['photo'],
        'image' => ['file', 'cover'],
        'music' => ['file', 'cover'],
        'podcast' => ['file', 'cover'],
        'video' => ['file', 'cover'],
        'commondocument' => ['file', 'cover'],
        'routine' => ['photo'],
        'article' => ['file1', 'file2', 'file3'],
    ];
    foreach ($map as $table => $columns) {
        try {
            $sql = 'SELECT ' . implode(',', array_map(fn($c) => "`{$c}`", $columns)) . " FROM `{$table}`";
            foreach ($pdo->query($sql)->fetchAll() as $row) {
                foreach ($columns as $column) {
                    $path = normalizeStoragePath($row[$column] ?? '');
                    if ($path !== '') {
                        $refs[$path] = true;
                        $refs[basename($path)] = true;
                    }
                }
            }
        } catch (Exception $e) {
            continue;
        }
    }
    return $refs;
}

/**
 * 一筆媒體紀錄的殘餘狀態：
 *   null     = 正常（檔案都在，或欄位是外部網址）
 *   'record' = 主檔 file 指向的本機檔案已消失 → 整筆是資料庫殘餘
 *   'cover'  = 主檔正常、只有封面 cover 檔案消失 → 清掉封面欄位即可
 */
function inspectMediaRow(array $row) {
    $filePath = localUploadPath($row['file'] ?? '');
    $coverPath = localUploadPath($row['cover'] ?? '');
    $missing = [];
    if ($filePath !== '' && !uploadFileExists($filePath)) $missing[] = 'file';
    if ($coverPath !== '' && !uploadFileExists($coverPath)) $missing[] = 'cover';
    if (!$missing) return null;
    return [
        'kind' => in_array('file', $missing, true) ? 'record' : 'cover',
        'missing' => $missing,
    ];
}

function collectOrphanRecords(PDO $pdo) {
    $orphans = [];
    foreach (FENGBRO_MEDIA_TABLES as $table => $label) {
        try {
            $rows = $pdo->query("SELECT `id`, `name`, `file`, `cover`, `created_at` FROM `{$table}`")->fetchAll();
        } catch (Exception $e) {
            continue;
        }
        foreach ($rows as $row) {
            $state = inspectMediaRow($row);
            if ($state === null) continue;
            $orphans[] = [
                'table' => $table,
                'label' => $label,
                'id' => (string) $row['id'],
                'name' => (string) ($row['name'] ?? ''),
                'file' => (string) ($row['file'] ?? ''),
                'cover' => (string) ($row['cover'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'kind' => $state['kind'],
                'missing' => $state['missing'],
            ];
        }
    }
    return $orphans;
}

function scanUploads($uploadsRoot, $refs) {
    $files = [];
    $referenced = 0;
    $rootLength = strlen($uploadsRoot) + 1;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsRoot, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
        if (!$file->isFile()) continue;
        $absolute = $file->getPathname();
        $relative = 'uploads/' . str_replace('\\', '/', substr($absolute, $rootLength));
        $isReferenced = isset($refs[$relative]) || isset($refs[basename($relative)]);
        if ($isReferenced) {
            $referenced++;
        } else {
            $files[] = [
                'path' => $relative,
                'size' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }
    }
    return [$files, $referenced];
}

$pdo = getConnection();

// ── 資料庫殘餘紀錄清理（不需要先掃描 uploads 目錄）──────────────────────
if ($action === 'delete_orphans' || $action === 'clear_orphan_covers') {
    $input = json_decode(file_get_contents('php://input'), true);
    $items = is_array($input['items'] ?? null) ? $input['items'] : [];
    $done = 0;
    $errors = [];
    foreach ($items as $item) {
        $table = (string) ($item['table'] ?? '');
        $id = (string) ($item['id'] ?? '');
        if (!isset(FENGBRO_MEDIA_TABLES[$table]) || $id === '') {
            $errors[] = '無效的紀錄參數';
            continue;
        }
        try {
            $stmt = $pdo->prepare("SELECT `id`, `name`, `file`, `cover` FROM `{$table}` WHERE `id` = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) {
                $errors[] = "{$table} / {$id} 已不存在";
                continue;
            }
            // 重新確認一次狀態：掃描後檔案若又被補回來，就不該再被清掉
            $state = inspectMediaRow($row);
            $name = (string) ($row['name'] ?? '') !== '' ? (string) $row['name'] : $id;
            if ($state === null) {
                $errors[] = "{$name} 的檔案目前存在，已略過";
                continue;
            }
            if ($action === 'delete_orphans') {
                if ($state['kind'] !== 'record') {
                    $errors[] = "{$name} 只有封面失效，請改用清除封面";
                    continue;
                }
                $del = $pdo->prepare("DELETE FROM `{$table}` WHERE `id` = ?");
                $del->execute([$id]);
                $done += $del->rowCount();
            } else {
                if (!in_array('cover', $state['missing'], true)) {
                    $errors[] = "{$name} 的封面目前存在，已略過";
                    continue;
                }
                $upd = $pdo->prepare("UPDATE `{$table}` SET `cover` = NULL WHERE `id` = ?");
                $upd->execute([$id]);
                $done++;
            }
        } catch (Exception $e) {
            $errors[] = "{$table} / {$id}：" . $e->getMessage();
        }
    }
    jsonResponse(['success' => true, 'done' => $done, 'errors' => $errors]);
}

$orphanRecords = collectOrphanRecords($pdo);
$orphanSummary = [
    'orphanRecords' => $orphanRecords,
    'orphanRecordCount' => count(array_filter($orphanRecords, fn($o) => $o['kind'] === 'record')),
    'orphanCoverCount' => count(array_filter($orphanRecords, fn($o) => $o['kind'] === 'cover')),
    'uploadsMissing' => $uploadsMissing,
];

if ($uploadsMissing) {
    jsonResponse(array_merge([
        'success' => true,
        'totalFiles' => 0,
        'referencedCount' => 0,
        'unusedFiles' => [],
        'message' => 'uploads 目錄不存在',
    ], $orphanSummary));
}

$refs = collectReferencedFiles($pdo);
[$unusedFiles, $referencedCount] = scanUploads($uploadsRoot, $refs);

if ($action === 'delete') {
    $input = json_decode(file_get_contents('php://input'), true);
    $paths = is_array($input['paths'] ?? null) ? $input['paths'] : [];
    $unusedMap = array_fill_keys(array_column($unusedFiles, 'path'), true);
    $deleted = 0;
    $errors = [];
    foreach ($paths as $path) {
        $path = str_replace('\\', '/', (string) $path);
        if (!isset($unusedMap[$path])) {
            $errors[] = "{$path} 不是目前掃描出的未引用檔案";
            continue;
        }
        $absolute = realpath(__DIR__ . '/' . $path);
        if (!$absolute || strpos($absolute, $uploadsRoot) !== 0 || !is_file($absolute)) {
            $errors[] = "{$path} 路徑無效";
            continue;
        }
        if (@unlink($absolute)) {
            $deleted++;
        } else {
            $errors[] = "{$path} 刪除失敗";
        }
    }
    jsonResponse(['success' => true, 'deleted' => $deleted, 'errors' => $errors]);
}

jsonResponse(array_merge([
    'success' => true,
    'totalFiles' => $referencedCount + count($unusedFiles),
    'referencedCount' => $referencedCount,
    'unusedFiles' => $unusedFiles,
], $orphanSummary));
