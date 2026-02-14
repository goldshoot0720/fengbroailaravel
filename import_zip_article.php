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
$extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_article_' . uniqid();
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

// 尋找 CSV 檔案 (appwrite-article.csv 或任何 .csv)
$csvFile = null;
$searchPaths = [
    $extractDir . DIRECTORY_SEPARATOR . 'appwrite-article.csv',
];

// 也搜尋子目錄
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

if (!$csvFile) {
    cleanupDir($extractDir);
    if ($cleanupTempFile) @unlink($zipFile);
    echo json_encode(['success' => false, 'error' => 'ZIP 中找不到 CSV 檔案']);
    exit;
}

// 找出 files 目錄的實際路徑
$filesDir = dirname($csvFile) . DIRECTORY_SEPARATOR . 'files';
if (!is_dir($filesDir)) {
    // 嘗試在解壓根目錄尋找
    $filesDir = $extractDir . DIRECTORY_SEPARATOR . 'files';
}

// 確保 uploads 目錄存在
$uploadDir = 'uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Appwrite 格式欄位對應
$fieldMapping = [
    '$id' => 'id',
    '$createdAt' => 'created_at',
    '$updatedAt' => 'updated_at'
];
$ignoredColumns = ['$permissions', '$databaseId', '$collectionId', '$tenant'];

// 解析 CSV
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

// 轉換標頭
$headers = array_map(function ($h) use ($fieldMapping) {
    $h = trim($h);
    return $fieldMapping[$h] ?? $h;
}, $headers);

// 移除忽略欄位
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

$pdo = getConnection();
$imported = 0;
$errors = [];
$lineNum = 1;

// 檔案欄位 (file1, file2, file3)
$fileFields = ['file1', 'file2', 'file3'];

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

    // 處理 ID
    if (empty($data['id'])) {
        $data['id'] = generateUUID();
    }
    $currentId = $data['id'];

    // 移除時間戳
    unset($data['created_at']);
    unset($data['updated_at']);

    // 處理檔案欄位：把 ZIP 內路徑的檔案複製到 uploads/
    foreach ($fileFields as $fileField) {
        if (!isset($data[$fileField]) || empty($data[$fileField])) continue;

        $zipPath = $data[$fileField]; // e.g. "files/1_filename.jpg"

        // 在解壓目錄中尋找檔案
        $sourcePath = dirname($csvFile) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $zipPath);
        if (!file_exists($sourcePath)) {
            $sourcePath = $extractDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $zipPath);
        }

        if (file_exists($sourcePath)) {
            // 從 ZIP 路徑提取原始檔名 (去掉 "files/" 和流水號前綴)
            $baseName = basename($zipPath);
            // 移除流水號前綴 (例如 "1_photo.jpg" -> "photo.jpg")
            $originalName = preg_replace('/^\d+_/', '', $baseName);
            if (empty($originalName)) {
                $originalName = $baseName;
            }

            // 用 UUID 命名避免衝突
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $newName = generateUUID() . ($ext ? '.' . $ext : '');
            $targetPath = $uploadDir . '/' . $newName;

            if (copy($sourcePath, $targetPath)) {
                $data[$fileField] = $targetPath;

                // 設定 filename 和 filetype
                $nameField = $fileField . 'name';
                $typeField = $fileField . 'type';
                if (!isset($data[$nameField]) || empty($data[$nameField])) {
                    $data[$nameField] = $originalName;
                }
                if (!isset($data[$typeField]) || empty($data[$typeField])) {
                    $data[$typeField] = mime_content_type($targetPath) ?: '';
                }
            } else {
                $data[$fileField] = '';
                $errors[] = "第 {$lineNum} 行: 無法複製檔案 {$baseName}";
            }
        } else {
            // 檔案在 ZIP 中不存在，清空路徑
            if (strpos($zipPath, 'files/') === 0) {
                $data[$fileField] = '';
            }
            // 如果是已有的 uploads/ 路徑則保留
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

    // 檢查是否已存在
    $stmt = $pdo->prepare("SELECT id FROM article WHERE id = ?");
    $stmt->execute([$currentId]);
    $exists = $stmt->fetch();

    try {
        if ($exists) {
            unset($data['id']);
            $sets = [];
            foreach (array_keys($data) as $col) {
                $sets[] = "`{$col}` = ?";
            }
            $sql = "UPDATE article SET " . implode(',', $sets) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $values = array_values($data);
            $values[] = $currentId;
            $stmt->execute($values);
        } else {
            $columns = array_map(function ($c) { return "`{$c}`"; }, array_keys($data));
            $placeholders = array_fill(0, count($data), '?');
            $sql = "INSERT INTO article (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
        }
        $imported++;
    } catch (PDOException $e) {
        $errors[] = "第 {$lineNum} 行: " . $e->getMessage();
    }
}

fclose($handle);

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
