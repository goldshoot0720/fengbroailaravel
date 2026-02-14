<?php
ini_set('memory_limit', '512M');
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 0);

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
    echo json_encode(['success' => false, 'error' => '請上傳 ZIP 檔案']);
    exit;
}

// 解壓 ZIP
$extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_image_' . uniqid();
if (!is_dir($extractDir)) {
    mkdir($extractDir, 0755, true);
}

$zip = new PureZipExtract();
if (!$zip->open($zipFile)) {
    if ($cleanupTempFile) @unlink($zipFile);
    echo json_encode(['success' => false, 'error' => '無法解壓 ZIP 檔案']);
    exit;
}

$zip->extractTo($extractDir);

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

if ($hasCsv) {
    // ===== Appwrite 格式：CSV + images/ 資料夾 =====
    $fieldMapping = [
        '$id' => 'id',
        '$createdAt' => 'created_at',
        '$updatedAt' => 'updated_at'
    ];
    $ignoredColumns = ['$permissions', '$databaseId', '$collectionId', '$tenant'];

    $handle = fopen($csvFile, 'r');
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        cleanupDir($extractDir);
        if ($cleanupTempFile) @unlink($zipFile);
        echo json_encode(['success' => false, 'error' => 'CSV 格式錯誤']);
        exit;
    }

    $headers = array_map(function ($h) use ($fieldMapping) {
        $h = trim($h);
        return $fieldMapping[$h] ?? $h;
    }, $headers);

    $ignoredIndexes = [];
    foreach ($headers as $i => $h) {
        if (in_array($h, $ignoredColumns) || (str_starts_with($h, '$') && !isset($fieldMapping[$h]))) {
            $ignoredIndexes[] = $i;
        }
    }
    foreach ($ignoredIndexes as $i) {
        unset($headers[$i]);
    }
    $headers = array_values($headers);
    $headerCount = count($headers);

    $lineNum = 1;
    $fileFields = ['file', 'cover'];

    while (($row = fgetcsv($handle)) !== false) {
        $lineNum++;

        foreach ($ignoredIndexes as $i) {
            unset($row[$i]);
        }
        $row = array_values($row);

        if (count($row) !== $headerCount) {
            $errors[] = "第 {$lineNum} 行: 欄位數不匹配";
            continue;
        }

        $data = array_combine($headers, $row);

        if (empty($data['id'])) {
            $data['id'] = generateUUID();
        }
        $currentId = $data['id'];

        unset($data['created_at']);
        unset($data['updated_at']);

        // 處理檔案欄位
        foreach ($fileFields as $fileField) {
            if (!isset($data[$fileField]) || empty($data[$fileField])) continue;

            $zipPath = $data[$fileField]; // e.g. "images/1_photo.png"

            $sourcePath = dirname($csvFile) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $zipPath);
            if (!file_exists($sourcePath)) {
                $sourcePath = $extractDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $zipPath);
            }

            if (file_exists($sourcePath)) {
                $baseName = basename($zipPath);
                $originalName = preg_replace('/^\d+_/', '', $baseName);
                if (empty($originalName)) $originalName = $baseName;

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

        // 轉換 ISO 8601 日期
        foreach ($data as $key => $value) {
            if ($value !== null && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
                $data[$key] = substr($value, 0, 10);
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
                $columns = array_map(function ($c) { return "`{$c}`"; }, array_keys($data));
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

    fclose($handle);

} else {
    // ===== 舊格式：純圖片 ZIP（無 CSV） =====
    $files = glob($extractDir . DIRECTORY_SEPARATOR . '*');

    foreach ($files as $file) {
        if (!is_file($file)) continue;

        $fileName = basename($file);
        if (strpos($fileName, '.') === 0) continue;

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $imageExtensions)) continue;

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

        try {
            $stmt = $pdo->prepare("INSERT INTO image (id, name, file, cover) VALUES (?, ?, ?, ?)");
            $stmt->execute([generateUUID(), $name, $filePath, $filePath]);
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "$fileName: " . $e->getMessage();
        }
    }
}

// 清理
cleanupDir($extractDir);
if ($cleanupTempFile) @unlink($zipFile);

echo json_encode([
    'success' => true,
    'imported' => $imported,
    'errors' => $errors
]);

function cleanupDir($dir)
{
    if (!is_dir($dir)) return;
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
