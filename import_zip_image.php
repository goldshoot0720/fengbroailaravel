<?php
ini_set('memory_limit', '512M');
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 0);


ob_start();

function outputJson($data)
{
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $error['message'],
        'debug' => [
            'file' => $error['file'] ?? '',
            'line' => $error['line'] ?? 0,
            'type' => $error['type'] ?? 0,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

header('Content-Type: application/json; charset=utf-8');

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

// 支援從 preview 傳入 tempFile 或直接上傳
$tempFile = $_POST['tempFile'] ?? '';
$cleanupTempFile = false;

if ($tempFile && file_exists($tempFile)) {
    $zipFile = $tempFile;
    $cleanupTempFile = true;
} elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $zipFile = $_FILES['file']['tmp_name'];
} else {
    outputJson(['success' => false, 'error' => '請上傳 ZIP 檔案']);
}

// 解壓 ZIP
$extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_image_' . uniqid();
if (!is_dir($extractDir)) {
    mkdir($extractDir, 0755, true);
}

$extracted = false;

if (class_exists('ZipArchive')) {
    $za = new ZipArchive();
    $openResult = $za->open($zipFile);
    if ($openResult === true) {
        if ($za->extractTo($extractDir)) {
            $extracted = true;
        }
        $za->close();
    }
}

if (!$extracted) {
    $zip = new PureZipExtract();
    if (!$zip->open($zipFile)) {
        if ($cleanupTempFile)
            @unlink($zipFile);
        outputJson(['success' => false, 'error' => 'Unzip failed']);
    }

    $zip->extractTo($extractDir);
}


// 尋找 CSV 檔案
$csvFile = null;
$searchPaths = [
    $extractDir . DIRECTORY_SEPARATOR . 'image.csv',
];
foreach (glob($extractDir . DIRECTORY_SEPARATOR . '*.csv') as $f) {
    $searchPaths[] = $f;
}
foreach (glob($extractDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.csv') as $f) {
    $searchPaths[] = $f;
}

foreach ($searchPaths as $path) {
    if (file_exists($path)) {
        $csvFile = $path;
        break;
    }
}

// 判斷模式：有 CSV = Appwrite 結構，無 CSV = 純圖片 ZIP
$hasCsv = ($csvFile !== null);

$uploadDir = 'uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$pdo = getConnection();
$imported = 0;
$errors = [];
$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
$debugInfo = ['mode' => 'unknown', 'csvFound' => false];

function limitImportText($value, $maxLen)
{
    if ($value === null) {
        return $value;
    }
    $str = (string)$value;
    if ($maxLen <= 0) {
        return '';
    }
    if (function_exists('mb_strlen')) {
        if (mb_strlen($str) > $maxLen) {
            return mb_substr($str, 0, $maxLen);
        }
        return $str;
    }
    if (strlen($str) > $maxLen) {
        return substr($str, 0, $maxLen);
    }
    return $str;
}


if ($hasCsv) {
    // ===== Appwrite 格式：CSV + images/ 資料夾 =====
    $debugInfo['mode'] = 'appwrite';
    $debugInfo['csvFound'] = true;
    $debugInfo['csvFile'] = basename($csvFile);

    $fieldMapping = [
        '$id' => 'id',
        '$createdAt' => 'created_at',
        '$updatedAt' => 'updated_at',
        '#filetype' => 'filetype',
    ];
    $ignoredColumns = ['$permissions', '$databaseId', '$collectionId', '$tenant'];

    $handle = fopen($csvFile, 'r');
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $headers = fgetcsv($handle, 0, ',', '"', '');
    if (!$headers) {
        fclose($handle);
        cleanupDir($extractDir);
        if ($cleanupTempFile)
            @unlink($zipFile);
        outputJson(['success' => false, 'error' => 'CSV 格式錯誤']);
    }

    // 保存原始標頭用於除錯
    $debugInfo['rawHeaders'] = $headers;

    $headers = array_map(function ($h) use ($fieldMapping) {
        $h = trim($h);
        if (isset($fieldMapping[$h]))
            return $fieldMapping[$h];
        if (str_starts_with($h, '#'))
            return substr($h, 1);
        return $h;
    }, $headers);

    // 動態取得 DB 欄位
    $dbColumns = [];
    $colStmt = $pdo->query("SHOW COLUMNS FROM image");
    foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $dbColumns[] = $col['Field'];
    }

    $ignoredIndexes = [];
    foreach ($headers as $i => $h) {
        if (
            in_array($h, $ignoredColumns) || (str_starts_with($h, '$') && !isset($fieldMapping[$h]))
            || !in_array($h, $dbColumns)
        ) {
            $ignoredIndexes[] = $i;
        }
    }
    foreach ($ignoredIndexes as $i) {
        unset($headers[$i]);
    }
    $headers = array_values($headers);
    $headerCount = count($headers);

    $debugInfo['mappedHeaders'] = $headers;
    $debugInfo['headerCount'] = $headerCount;
    $debugInfo['ignoredCount'] = count($ignoredIndexes);

    $lineNum = 1;
    $fileFields = ['file', 'cover'];
    $rowsProcessed = 0;

    while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        $lineNum++;
        $rowsProcessed++;

        foreach ($ignoredIndexes as $i) {
            unset($row[$i]);
        }
        $row = array_values($row);

        if (count($row) !== $headerCount) {
            $errors[] = "第 {$lineNum} 行: 欄位數不匹配 (期望 {$headerCount}, 實際 " . count($row) . ")";
            continue;
        }

        $data = array_combine($headers, $row);

        if (empty($data['id'])) {
            $data['id'] = generateUUID();
        }
        $currentId = $data['id'];

        // Appwrite 時間戳保留

        // 處理檔案欄位
        foreach ($fileFields as $fileField) {
            if (!isset($data[$fileField]) || empty($data[$fileField]))
                continue;

            $zipPath = $data[$fileField]; // e.g. "images/1_photo.png"

            // 跳過 URL 類型的路徑（不需要從 ZIP 複製）
            if (preg_match('#^https?://#', $zipPath))
                continue;

            // 候選路徑（優先：csv 同目錄，次要：extractDir 根）
            $zipPathNorm = str_replace('/', DIRECTORY_SEPARATOR, $zipPath);
            $candidatePaths = [
                dirname($csvFile) . DIRECTORY_SEPARATOR . $zipPathNorm,
                $extractDir . DIRECTORY_SEPARATOR . $zipPathNorm,
            ];
            $subDir = strtok($zipPath, '/');
            $baseName = basename($zipPath);
            if ($subDir && $baseName) {
                foreach (glob($extractDir . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR . '*') as $cand) {
                    if (basename($cand) === $baseName) {
                        $candidatePaths[] = $cand;
                        break;
                    }
                }
            }
            $sourcePath = null;
            foreach ($candidatePaths as $cand) {
                if (file_exists($cand)) {
                    $sourcePath = $cand;
                    break;
                }
            }

            if ($sourcePath !== null) {
                $originalName = preg_replace('/^\d+_/', '', $baseName);
                if (empty($originalName))
                    $originalName = $baseName;

                $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                $newName = generateUUID() . ($ext ? '.' . $ext : '');
                $targetPath = $uploadDir . '/' . $newName;

                if (copy($sourcePath, $targetPath)) {
                    $data[$fileField] = $targetPath;
                } else {
                    $data[$fileField] = '';
                    $errors[] = "第 {$lineNum} 行: 無法複製檔案 {$baseName}";
                }
            } else {
                if (strpos($zipPath, 'images/') === 0) {
                    $data[$fileField] = '';
                }
            }
        }

        // cover 和 file 相同時同步
        if (isset($data['cover']) && isset($data['file']) && empty($data['cover'])) {
            $data['cover'] = $data['file'];
        }

        // 處理空值
        foreach ($data as $key => $value) {
            if ($value === '' || $value === 'null') {
                $data[$key] = null;
            }
        }

        // 轉換 ISO 8601 日期 -> MySQL DATETIME
        foreach ($data as $key => $value) {
            if ($value !== null && preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/', $value, $m)) {
                $data[$key] = $m[1] . ' ' . $m[2];
            }
        }

        
        // Truncate fields to match DB column lengths
        $fieldLimits = [
            'name' => 100,
            'file' => 150,
            'filetype' => 50,
            'note' => 100,
            'ref' => 100,
            'category' => 100,
            'hash' => 300,
            'cover' => 150,
        ];
        foreach ($fieldLimits as $col => $maxLen) {
            if (isset($data[$col]) && $data[$col] !== null) {
                $data[$col] = limitImportText($data[$col], $maxLen);
            }
        }

$stmt = $pdo->prepare("SELECT id FROM image WHERE id = ?");
        $stmt->execute([$currentId]);
        $exists = $stmt->fetch();

        try {
            if ($exists) {
                unset($data['id']);
                $sets = [];
                foreach (array_keys($data) as $col) {
                    $sets[] = "`{$col}` = ?";
                }
                $sql = "UPDATE image SET " . implode(',', $sets) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $values = array_values($data);
                $values[] = $currentId;
                $stmt->execute($values);
            } else {
                $columns = array_map(function ($c) {
                    return "`{$c}`";
                }, array_keys($data));
                $placeholders = array_fill(0, count($data), '?');
                $sql = "INSERT INTO image (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($data));
            }
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "第 {$lineNum} 行: " . $e->getMessage();
        }
    }

    $debugInfo['rowsProcessed'] = $rowsProcessed;
    fclose($handle);

} else {
    $debugInfo['mode'] = 'legacy';
    // ===== 舊格式：純圖片 ZIP（無 CSV） =====
    $files = glob($extractDir . DIRECTORY_SEPARATOR . '*');

    foreach ($files as $file) {
        if (!is_file($file))
            continue;

        $fileName = basename($file);
        if (strpos($fileName, '.') === 0)
            continue;

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $imageExtensions))
            continue;

        $destPath = $uploadDir . '/' . $fileName;
        if (file_exists($destPath)) {
            $info = pathinfo($fileName);
            $counter = 1;
            while (file_exists($uploadDir . '/' . $info['filename'] . '_' . $counter . '.' . $ext)) {
                $counter++;
            }
            $fileName = $info['filename'] . '_' . $counter . '.' . $ext;
            $destPath = $uploadDir . '/' . $fileName;
        }

        if (!copy($file, $destPath)) {
            $errors[] = "無法複製: $fileName";
            continue;
        }

        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $filePath = 'uploads/' . $fileName;
        $filetype = $ext; // derive filetype from extension

        try {
            $stmt = $pdo->prepare("INSERT INTO image (id, name, file, filetype, cover) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([generateUUID(), $name, $filePath, $filetype, $filePath]);
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "$fileName: " . $e->getMessage();
        }
    }
}

// 清理
cleanupDir($extractDir);
if ($cleanupTempFile)
    @unlink($zipFile);

outputJson([
    'success' => true,
    'imported' => $imported,
    'errors' => $errors,
    'debug' => $debugInfo
]);

function cleanupDir($dir)
{
    if (!is_dir($dir))
        return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getRealPath());
        } else {
            @unlink($item->getRealPath());
        }
    }
    @rmdir($dir);
}
