<?php
ini_set('memory_limit', '512M');
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => '請使用 POST 方法'], 400);
}

// Support both direct upload and tempFile from preview
$tempFileFromPreview = $_POST['tempFile'] ?? '';
$zipFile = '';
$cleanupTempFile = false;

if ($tempFileFromPreview && file_exists($tempFileFromPreview) && strpos(realpath($tempFileFromPreview), realpath('uploads/temp')) === 0) {
    $zipFile = $tempFileFromPreview;
    $cleanupTempFile = true;
} elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $zipFile = $_FILES['file']['tmp_name'];
} else {
    jsonResponse(['error' => '請上傳檔案'], 400);
}

// Create temp directory for extraction
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_doc_' . uniqid();
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// Extract ZIP using pure PHP
$zip = new PureZipExtract();
if (!$zip->open($zipFile)) {
    cleanupDir($tempDir);
    if ($cleanupTempFile) @unlink($zipFile);
    jsonResponse(['error' => '無法開啟 ZIP 檔案'], 400);
}

$zip->extractTo($tempDir);

// Copy files to uploads directory
$uploadDir = 'uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$pdo = getConnection();
$imported = 0;
$errors = [];

// 尋找 CSV 檔案
$csvFile = null;
$searchPaths = [
    $tempDir . DIRECTORY_SEPARATOR . 'document.csv',
];
foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*.csv') as $f) {
    $searchPaths[] = $f;
}
foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.csv') as $f) {
    $searchPaths[] = $f;
}

foreach ($searchPaths as $path) {
    if (file_exists($path)) {
        $csvFile = $path;
        break;
    }
}

// 判斷模式：有 CSV = Appwrite 結構，無 CSV = 純文件 ZIP
$hasCsv = ($csvFile !== null);

if ($hasCsv) {
    // ===== Appwrite 格式：CSV + document/ + covers/ 資料夾 =====
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
        cleanupDir($tempDir);
        if ($cleanupTempFile) @unlink($zipFile);
        jsonResponse(['error' => 'CSV 格式錯誤'], 400);
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

        // 處理檔案欄位 (document/ 和 covers/ 資料夾)
        foreach ($fileFields as $fileField) {
            if (!isset($data[$fileField]) || empty($data[$fileField])) continue;

            $zipPath = $data[$fileField]; // e.g. "document/1_文件名稱.pdf" or "covers/1_thumb.png"

            $sourcePath = dirname($csvFile) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $zipPath);
            if (!file_exists($sourcePath)) {
                $sourcePath = $tempDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $zipPath);
            }

            if (file_exists($sourcePath)) {
                $baseName = basename($zipPath);
                // 移除流水號前綴 (e.g. "1_文件名稱.pdf" -> "文件名稱.pdf")
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
                // 如果是相對路徑但檔案不存在，清空
                if (strpos($zipPath, 'document/') === 0 || strpos($zipPath, 'covers/') === 0) {
                    $data[$fileField] = '';
                }
            }
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

        $stmt = $pdo->prepare("SELECT id FROM commondocument WHERE id = ?");
        $stmt->execute([$currentId]);
        $exists = $stmt->fetch();

        try {
            if ($exists) {
                unset($data['id']);
                $sets = [];
                foreach (array_keys($data) as $col) {
                    $sets[] = "`{$col}` = ?";
                }
                $sql = "UPDATE commondocument SET " . implode(',', $sets) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $values = array_values($data);
                $values[] = $currentId;
                $stmt->execute($values);
            } else {
                $columns = array_map(function ($c) { return "`{$c}`"; }, array_keys($data));
                $placeholders = array_fill(0, count($data), '?');
                $sql = "INSERT INTO commondocument (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
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
    // ===== 舊格式：純文件 ZIP（無 CSV） =====
    $validExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php', 'py', 'sql', 'csv'];
    $files = glob($tempDir . DIRECTORY_SEPARATOR . '*');

    foreach ($files as $file) {
        if (!is_file($file)) continue;

        $fileName = basename($file);

        // Skip cover files and hidden files
        if (strpos($fileName, 'cover_') === 0) continue;
        if (strpos($fileName, '.') === 0) continue;

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Skip non-document files
        if (!in_array($ext, $validExtensions)) continue;

        // Copy to uploads
        $destPath = $uploadDir . '/' . $fileName;

        // Handle duplicate filenames
        if (file_exists($destPath)) {
            $info = pathinfo($fileName);
            $base = $info['filename'];
            $counter = 1;
            while (file_exists($uploadDir . '/' . $base . '_' . $counter . '.' . $ext)) {
                $counter++;
            }
            $fileName = $base . '_' . $counter . '.' . $ext;
            $destPath = $uploadDir . '/' . $fileName;
        }

        if (!copy($file, $destPath)) {
            $errors[] = "無法複製: $fileName";
            continue;
        }

        // Create database record
        $name = pathinfo($fileName, PATHINFO_FILENAME);

        try {
            $id = generateUUID();
            $sql = "INSERT INTO commondocument (id, name, file) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id, $name, $destPath]);
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "$fileName: " . $e->getMessage();
        }
    }
}

// 清理
cleanupDir($tempDir);
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
