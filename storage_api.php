<?php
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'scan';
$uploadsRoot = realpath(__DIR__ . '/uploads');
if (!$uploadsRoot || !is_dir($uploadsRoot)) {
    jsonResponse(['success' => true, 'totalFiles' => 0, 'referencedCount' => 0, 'unusedFiles' => [], 'message' => 'uploads 目錄不存在']);
}

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

jsonResponse([
    'success' => true,
    'totalFiles' => $referencedCount + count($unusedFiles),
    'referencedCount' => $referencedCount,
    'unusedFiles' => $unusedFiles,
]);
