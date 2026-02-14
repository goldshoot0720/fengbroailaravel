<?php
ini_set('memory_limit', '512M');
set_time_limit(0);
error_reporting(E_ALL);

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

// 收集錯誤訊息
$errors = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['zipfile'])) {
    header('Location: index.php?page=documents&error=' . urlencode('請選擇 ZIP 檔案'));
    exit;
}

$uploadedFile = $_FILES['zipfile']['tmp_name'];
$originalName = $_FILES['zipfile']['name'];
$uploadError = $_FILES['zipfile']['error'];

// 檢查上傳錯誤
if ($uploadError !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => '檔案太大，超過伺服器限制',
        UPLOAD_ERR_FORM_SIZE => '檔案太大，超過表單限制',
        UPLOAD_ERR_PARTIAL => '檔案只上傳部分內容',
        UPLOAD_ERR_NO_FILE => '未選擇檔案',
        UPLOAD_ERR_NO_TMP_DIR => '伺服器暫存目錄不存在',
        UPLOAD_ERR_CANT_WRITE => '伺服器無法寫入檔案',
    ];
    $msg = $errorMessages[$uploadError] ?? '上傳失敗 (錯誤碼: ' . $uploadError . ')';
    header('Location: index.php?page=documents&error=' . urlencode($msg));
    exit;
}

if (!$uploadedFile || !file_exists($uploadedFile)) {
    header('Location: index.php?page=documents&error=' . urlencode('上傳檔案不存在'));
    exit;
}

// Create temp extraction directory
$extractDir = sys_get_temp_dir() . '/doc_import_' . time();
if (!is_dir($extractDir)) {
    if (!mkdir($extractDir, 0755, true)) {
        header('Location: index.php?page=documents&error=' . urlencode('無法建立暫存目錄'));
        exit;
    }
}

// Extract ZIP
$zip = new PureZipExtract();
if (!$zip->open($uploadedFile)) {
    header('Location: index.php?page=documents&error=' . urlencode('無法解壓 ZIP 檔案，格式可能不正確'));
    exit;
}

$zip->extractTo($extractDir);
$extractedFiles = $zip->getFiles();

if (empty($extractedFiles)) {
    header('Location: index.php?page=documents&error=' . urlencode('ZIP 檔案是空的'));
    exit;
}

// Move files to uploads directory
$uploadDir = 'uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$importedCount = 0;
$pdo = getConnection();

// 尋找 CSV 檔案
$csvFile = null;
$searchPaths = [
    $extractDir . DIRECTORY_SEPARATOR . 'document.csv',
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
        cleanupDir($extractDir);
        header('Location: index.php?page=documents&error=' . urlencode('CSV 格式錯誤'));
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

            $zipPath = $data[$fileField];

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
            $importedCount++;
        } catch (PDOException $e) {
            $errors[] = "第 {$lineNum} 行: " . $e->getMessage();
        }
    }

    fclose($handle);

} else {
    // ===== 舊格式：純文件 ZIP（無 CSV） =====
    foreach ($extractedFiles as $fileName) {
        if (substr($fileName, -1) === '/') continue;

        $sourcePath = $extractDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileName);
        if (!file_exists($sourcePath)) continue;

        $baseName = basename($fileName);

        if (strpos($baseName, 'cover_') === 0) continue;
        if (strpos($baseName, '.') === 0) continue;
        if (strpos($fileName, '__MACOSX') !== false) continue;

        $targetPath = $uploadDir . '/' . $baseName;

        if (file_exists($targetPath)) {
            $pathInfo = pathinfo($baseName);
            $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $targetPath = $uploadDir . '/' . $pathInfo['filename'] . '_' . time() . $ext;
        }

        if (copy($sourcePath, $targetPath)) {
            $name = pathinfo($baseName, PATHINFO_FILENAME);

            try {
                $stmt = $pdo->prepare("INSERT INTO commondocument (id, name, file, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([generateUUID(), $name, $targetPath]);
                $importedCount++;
            } catch (Exception $e) {
                $errors[] = "資料庫錯誤: " . $e->getMessage();
            }
        } else {
            $errors[] = "無法複製檔案: " . $baseName;
        }
    }
}

// Cleanup temp directory
cleanupDir($extractDir);

if ($importedCount > 0) {
    header('Location: index.php?page=documents&success=' . urlencode("成功匯入 {$importedCount} 個文件"));
} else {
    $errorMsg = empty($errors) ? 'ZIP 中沒有可匯入的文件' : implode('; ', $errors);
    header('Location: index.php?page=documents&error=' . urlencode($errorMsg));
}

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
