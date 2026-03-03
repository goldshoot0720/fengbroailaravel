<?php
ob_start();
ini_set('memory_limit', '512M');
set_time_limit(300);
error_reporting(E_ALL);
ini_set('display_errors', 0);

$debug = [];
$debugLog = function ($msg) use (&$debug) {
    $debug[] = '[' . date('H:i:s') . '] ' . $msg;
};

function outputJson($data)
{
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    outputJson(['error' => '請使用 POST 方法']);
}

// Support both direct upload and tempFile from preview
$tempFileFromPreview = $_POST['tempFile'] ?? '';
$zipFile = '';
$cleanupTempFile = false;

$debugLog('POST keys: ' . implode(', ', array_keys($_POST)));
$debugLog('FILES keys: ' . implode(', ', array_keys($_FILES)));
$debugLog('PHP memory_limit: ' . ini_get('memory_limit'));
$debugLog('upload_max_filesize: ' . ini_get('upload_max_filesize'));
$debugLog('post_max_size: ' . ini_get('post_max_size'));
$debugLog('ZipArchive available: ' . (class_exists('ZipArchive') ? 'YES' : 'NO'));

if ($tempFileFromPreview) {
    $debugLog('tempFile from preview: ' . $tempFileFromPreview);
    $realTemp = realpath($tempFileFromPreview);
    $uploadsTemp = realpath('uploads/temp');
    $debugLog('realpath(tempFile)=' . ($realTemp ?: 'NOT FOUND'));
    $debugLog('realpath(uploads/temp)=' . ($uploadsTemp ?: 'NOT FOUND'));
    if ($realTemp && $uploadsTemp && strpos($realTemp, $uploadsTemp) === 0) {
        $zipFile = $tempFileFromPreview;
        $cleanupTempFile = true;
        $debugLog('Using tempFile from preview: ' . $zipFile);
    } else {
        $debugLog('tempFile path security check FAILED');
        outputJson(['error' => '暫存檔案路徑不安全或不存在', 'debug' => $debug]);
    }
} elseif (isset($_FILES['file'])) {
    $uploadErr = $_FILES['file']['error'];
    $debugLog('File upload error code: ' . $uploadErr . ', size: ' . ($_FILES['file']['size'] ?? 'N/A') . ', name: ' . ($_FILES['file']['name'] ?? 'N/A'));
    if ($uploadErr === UPLOAD_ERR_OK) {
        $zipFile = $_FILES['file']['tmp_name'];
        $debugLog('Direct file upload OK: ' . $zipFile . ' (exists=' . (file_exists($zipFile) ? 'yes' : 'no') . ', size=' . (file_exists($zipFile) ? filesize($zipFile) : 'N/A') . ')');
    } elseif ($uploadErr === UPLOAD_ERR_INI_SIZE || $uploadErr === UPLOAD_ERR_FORM_SIZE) {
        outputJson(['error' => '檔案太大，超過伺服器上傳限制 (upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . ')', 'debug' => $debug]);
    } else {
        $errMessages = [
            UPLOAD_ERR_PARTIAL => '檔案只上傳部分，請重試',
            UPLOAD_ERR_NO_FILE => '未選擇檔案',
            UPLOAD_ERR_NO_TMP_DIR => '伺服器暫存目錄不存在',
            UPLOAD_ERR_CANT_WRITE => '伺服器無法寫入檔案',
        ];
        $msg = $errMessages[$uploadErr] ?? "上傳失敗（錯誤碼: {$uploadErr}）";
        outputJson(['error' => $msg, 'debug' => $debug]);
    }
} else {
    outputJson(['error' => '請上傳 ZIP 檔案 (no file received)', 'debug' => $debug]);
}

if (empty($zipFile) || !file_exists($zipFile)) {
    outputJson(['error' => '上傳的 ZIP 檔案不存在: ' . $zipFile, 'debug' => $debug]);
}

$debugLog('ZIP file size: ' . filesize($zipFile) . ' bytes (' . round(filesize($zipFile) / 1024 / 1024, 2) . ' MB)');

// Create temp directory for extraction
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_music_' . uniqid();
if (!mkdir($tempDir, 0755, true)) {
    outputJson(['error' => '無法建立暫存目錄: ' . $tempDir, 'debug' => $debug]);
}
$debugLog('Temp dir: ' . $tempDir);

// Try to open the ZIP file
$debugLog('Checking ZIP magic bytes...');
$fh = fopen($zipFile, 'rb');
$magic = bin2hex(fread($fh, 4));
fclose($fh);
$debugLog('ZIP magic bytes (hex): ' . $magic . ' (expect: 504b0304)');

// Use ZipArchive if available (preferred), otherwise fall back to PureZipExtract
$allZipFiles = [];
$extractOk = false;

if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    $openResult = $zip->open($zipFile);
    $debugLog('ZipArchive::open result: ' . ($openResult === true ? 'SUCCESS' : 'FAILED (code=' . $openResult . ')'));
    if ($openResult === true) {
        $debugLog('ZIP has ' . $zip->numFiles . ' entries');
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $allZipFiles[] = $zip->getNameIndex($i);
        }
        $debugLog('ZIP entries (first 20): ' . implode(', ', array_slice($allZipFiles, 0, 20)));
        $zip->extractTo($tempDir);
        $zip->close();
        $extractOk = true;
        $debugLog('ZipArchive extraction complete');
    } else {
        $debugLog('ZipArchive failed, will try PureZip fallback');
    }
}

if (!$extractOk) {
    // Fallback: PureZip
    $debugLog('Trying PureZip fallback...');
    require_once 'includes/PureZip.php';
    $zip2 = new PureZipExtract();
    if (!$zip2->open($zipFile)) {
        cleanupDir($tempDir);
        if ($cleanupTempFile)
            @unlink($zipFile);
        outputJson(['error' => '無法開啟 ZIP 檔案（ZipArchive 和 PureZip 均失敗）。Magic bytes: 0x' . $magic, 'debug' => $debug]);
    }
    $zip2->extractTo($tempDir);
    $allZipFiles = $zip2->getFiles();
    $extractOk = true;
    $debugLog('PureZip extraction OK, files: ' . count($allZipFiles));
}

// List extracted files
$extractedList = [];
$rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($rit as $f) {
    if ($f->isFile()) {
        $rel = substr($f->getPathname(), strlen($tempDir) + 1);
        $extractedList[] = str_replace('\\', '/', $rel);
    }
}
$debugLog('Extracted ' . count($extractedList) . ' files. First 20: ' . implode(', ', array_slice($extractedList, 0, 20)));

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
    $tempDir . DIRECTORY_SEPARATOR . 'music.csv',
];
foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*.csv') as $f) {
    $searchPaths[] = $f;
}
foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.csv') as $f) {
    $searchPaths[] = $f;
}
$debugLog('CSV search paths (' . count($searchPaths) . '): ' . implode(', ', $searchPaths));

foreach ($searchPaths as $path) {
    $exists = file_exists($path);
    $debugLog('CSV check: ' . $path . ' => ' . ($exists ? 'EXISTS' : 'not found'));
    if ($exists) {
        $csvFile = $path;
        break;
    }
}

$hasCsv = ($csvFile !== null);
$debugLog('Mode: ' . ($hasCsv ? 'Appwrite CSV mode, CSV=' . $csvFile : 'Plain ZIP mode (no CSV)'));

if ($hasCsv) {
    // ===== Appwrite 格式：CSV + music/ + covers/ + lyrics/ 資料夾 =====
    $fieldMapping = [
        '$id' => 'id',
        '$createdAt' => 'created_at',
        '$updatedAt' => 'updated_at',
        '#filetype' => 'filetype',   // Appwrite 特殊欄位前綴
        '#category' => 'category',
        '#language' => 'language',
    ];

    // 動態取得 DB 欄位
    $dbColumns = [];
    try {
        $colStmt = $pdo->query("SHOW COLUMNS FROM music");
        foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $dbColumns[] = $col['Field'];
        }
        $debugLog('DB columns in music: ' . implode(', ', $dbColumns));
    } catch (PDOException $e) {
        outputJson(['error' => 'DB 錯誤: ' . $e->getMessage(), 'debug' => $debug]);
    }

    $handle = fopen($csvFile, 'r');
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
        $debugLog('No BOM');
    } else {
        $debugLog('BOM detected and skipped');
    }

    $rawHeaders = fgetcsv($handle, 0, ',', '"', '');
    $debugLog('Raw CSV headers (' . count((array) $rawHeaders) . '): ' . json_encode($rawHeaders, JSON_UNESCAPED_UNICODE));
    $headers = $rawHeaders;

    if (!$headers) {
        fclose($handle);
        cleanupDir($tempDir);
        if ($cleanupTempFile)
            @unlink($zipFile);
        outputJson(['error' => 'CSV 格式錯誤（無法讀取標頭列）', 'debug' => $debug]);
    }

    // 把 Appwrite 欄位轉換成 DB 欄位名稱
    $headers = array_map(function ($h) use ($fieldMapping) {
        $h = trim($h);
        return $fieldMapping[$h] ?? $h;
    }, $headers);
    $debugLog('Mapped headers: ' . json_encode($headers, JSON_UNESCAPED_UNICODE));

    // 只保留 DB 中存在的欄位
    $ignoredIndexes = [];
    foreach ($headers as $i => $h) {
        if (!in_array($h, $dbColumns)) {
            $ignoredIndexes[] = $i;
            $debugLog("Ignoring CSV col [{$i}]: {$h} (not in DB)");
        }
    }
    foreach ($ignoredIndexes as $i) {
        unset($headers[$i]);
    }
    $headers = array_values($headers);
    $headerCount = count($headers);
    $debugLog('Final headers (' . $headerCount . '): ' . implode(', ', $headers));

    $lineNum = 1;
    $fileFields = ['file', 'cover'];

    while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        $lineNum++;

        foreach ($ignoredIndexes as $i) {
            unset($row[$i]);
        }
        $row = array_values($row);

        if (count($row) !== $headerCount) {
            $errors[] = "第 {$lineNum} 行: 欄位數不匹配 (expected {$headerCount}, got " . count($row) . ")";
            continue;
        }

        $data = array_combine($headers, $row);

        if (empty($data['id'])) {
            $data['id'] = generateUUID();
        }
        $currentId = $data['id'];

        unset($data['created_at']);
        unset($data['updated_at']);

        // 處理檔案欄位 (music/ 和 covers/ 資料夾)
        foreach ($fileFields as $fileField) {
            if (!isset($data[$fileField]) || empty($data[$fileField]))
                continue;

            $zipPath = $data[$fileField];

            // 如果是 HTTP URL，保留原始 URL 不做本地複製
            if (preg_match('#^https?://#i', $zipPath)) {
                $debugLog("Row {$lineNum} {$fileField}: keeping URL as-is: " . substr($zipPath, 0, 80));
                continue;
            }

            // Normalize path separators
            $zipPathNorm = str_replace('/', DIRECTORY_SEPARATOR, $zipPath);

            // 嘗試多個路徑（優先：csvFile 同目錄，次要：tempDir 根）
            $candidatePaths = [
                dirname($csvFile) . DIRECTORY_SEPARATOR . $zipPathNorm,
                $tempDir . DIRECTORY_SEPARATOR . $zipPathNorm,
            ];

            // fallback：在對應子目錄中用 basename 搜尋
            $subDir = strtok($zipPath, '/'); // 'music' 或 'covers'
            $baseName = basename($zipPath);
            if ($subDir && $baseName) {
                foreach (glob($tempDir . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR . '*') as $candidate) {
                    if (basename($candidate) === $baseName) {
                        $candidatePaths[] = $candidate;
                        break;
                    }
                }
            }

            $sourcePath = null;
            foreach ($candidatePaths as $candidate) {
                if (file_exists($candidate)) {
                    $sourcePath = $candidate;
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
                    $debugLog("Row {$lineNum} {$fileField}: copied to {$targetPath}");
                } else {
                    $data[$fileField] = '';
                    $errors[] = "第 {$lineNum} 行: 無法複製檔案 {$baseName}";
                }
            } else {
                if (strpos($zipPath, 'music/') === 0 || strpos($zipPath, 'covers/') === 0) {
                    $debugLog("Row {$lineNum} {$fileField}: file not found: {$zipPath}");
                    $errors[] = "第 {$lineNum} 行: 檔案不存在 {$zipPath}";
                    $data[$fileField] = '';
                }
            }
        }

        // 處理歌詞欄位 (lyrics/ 資料夾 -> 讀取文字內容)
        if (isset($data['lyrics']) && !empty($data['lyrics']) && strpos($data['lyrics'], 'lyrics/') === 0) {
            $lyricsZipPath = $data['lyrics'];
            $lyricsBaseName = basename($lyricsZipPath);
            $lyricsNorm = str_replace('/', DIRECTORY_SEPARATOR, $lyricsZipPath);

            $lyricsCandidates = [
                dirname($csvFile) . DIRECTORY_SEPARATOR . $lyricsNorm,
                $tempDir . DIRECTORY_SEPARATOR . $lyricsNorm,
            ];
            // fallback：在 lyrics/ 子目錄用 basename 搜尋
            foreach (glob($tempDir . DIRECTORY_SEPARATOR . 'lyrics' . DIRECTORY_SEPARATOR . '*') as $candidate) {
                if (basename($candidate) === $lyricsBaseName) {
                    $lyricsCandidates[] = $candidate;
                    break;
                }
            }

            $lyricsSourcePath = null;
            foreach ($lyricsCandidates as $candidate) {
                if (file_exists($candidate)) {
                    $lyricsSourcePath = $candidate;
                    break;
                }
            }

            if ($lyricsSourcePath !== null) {
                $data['lyrics'] = file_get_contents($lyricsSourcePath);
                $debugLog("Row {$lineNum} lyrics: loaded from {$lyricsSourcePath}");
            } else {
                $debugLog("Row {$lineNum} lyrics: file not found: {$lyricsZipPath}");
                $data['lyrics'] = '';
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

        $stmt = $pdo->prepare("SELECT id FROM music WHERE id = ?");
        $stmt->execute([$currentId]);
        $exists = $stmt->fetch();

        try {
            if ($exists) {
                unset($data['id']);
                $sets = [];
                foreach (array_keys($data) as $col) {
                    $sets[] = "`{$col}` = ?";
                }
                $sql = "UPDATE music SET " . implode(',', $sets) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $values = array_values($data);
                $values[] = $currentId;
                $stmt->execute($values);
            } else {
                $columns = array_map(function ($c) {
                    return "`{$c}`";
                }, array_keys($data));
                $placeholders = array_fill(0, count($data), '?');
                $sql = "INSERT INTO music (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($data));
            }
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "第 {$lineNum} 行: " . $e->getMessage();
        }
    }

    fclose($handle);
    $debugLog("CSV processing done: imported={$imported}, errors=" . count($errors));

} else {
    // ===== 舊格式：純音樂 ZIP（無 CSV） =====
    $musicExtensions = ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac', 'wma'];
    $allFiles = [];

    // Recurse through extracted files
    $rit2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($rit2 as $f) {
        if ($f->isFile())
            $allFiles[] = $f->getPathname();
    }

    $debugLog('Plain ZIP mode: found ' . count($allFiles) . ' files total');

    foreach ($allFiles as $file) {
        $fileName = basename($file);

        // Skip cover files and hidden files
        if (strpos($fileName, 'cover_') === 0)
            continue;
        if (strpos($fileName, '.') === 0)
            continue;
        if (strpos(str_replace('\\', '/', $file), '__MACOSX') !== false)
            continue;

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $musicExtensions))
            continue;

        // Copy to uploads
        $destPath = $uploadDir . '/' . $fileName;
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

        // Create DB record
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $filePath = 'uploads/' . $fileName;
        $filetype = $ext;

        try {
            $id = generateUUID();
            $sql = "INSERT INTO music (id, name, file, filetype) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id, $name, $filePath, $filetype]);
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "$fileName: " . $e->getMessage();
        }
    }

    $debugLog("Plain ZIP done: imported={$imported}, errors=" . count($errors));
}

// Cleanup
cleanupDir($tempDir);
if ($cleanupTempFile)
    @unlink($zipFile);

outputJson([
    'success' => true,
    'imported' => $imported,
    'errors' => $errors,
    'debug' => $debug
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
