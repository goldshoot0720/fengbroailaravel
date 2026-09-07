<?php
/**
 * 選單一鍵備份／還原 — 對齊 fengbroaiappwrite lib/menuBackup。
 *
 * CSV 備份：各選單文字資料打成 csv/*.csv。
 * 全部備份：再加上圖片／影片／音樂／播客／文件／筆記的 zip/*.zip。
 * 匯入時相同鍵更新、其餘新增，不刪除備份裡沒有的紀錄。
 */

require_once __DIR__ . '/PureZip.php';

const FENGBRO_MENU_BACKUP_VERSION = 1;
const FENGBRO_MENU_BACKUP_CSV_DIR = 'csv';
const FENGBRO_MENU_BACKUP_ZIP_DIR = 'zip';

function fengbroMenuBackupEntries(): array
{
    return [
        ['id' => 'food', 'label' => '鋒兄食品', 'csvStem' => 'food', 'table' => 'food', 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'subscription', 'label' => '鋒兄訂閱', 'csvStem' => 'subscription', 'table' => 'subscription', 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'trial-purchase', 'label' => '鋒兄試用/首購', 'csvStem' => 'trialpurchase', 'table' => 'trialpurchase', 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'reinstall', 'label' => '鋒兄重灌', 'csvStem' => 'reinstall', 'table' => 'reinstall', 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'quota', 'label' => '鋒兄額度', 'csvStem' => 'quota', 'table' => 'quota', 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'shopping-list', 'label' => '鋒兄購物清單', 'csvStem' => 'shoppinglist', 'table' => 'shoppinglist', 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'common', 'label' => '鋒兄常用', 'csvStem' => 'commonaccount', 'table' => 'commonaccount', 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'bank-stats', 'label' => '鋒兄銀行', 'csvStem' => 'bank', 'table' => 'bank', 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'routine', 'label' => '鋒兄例行', 'csvStem' => 'routine', 'table' => 'routine', 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'price-compare', 'label' => '鋒兄比價', 'csvStem' => 'manual-price', 'table' => null, 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'landtop', 'label' => '手機比價', 'csvStem' => 'landtop-history', 'table' => null, 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'fengbro-tube', 'label' => '鋒兄Tube', 'csvStem' => 'fengbro-tube', 'table' => null, 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'fengbro-finance', 'label' => '鋒兄金融', 'csvStem' => 'fengbro-finance', 'table' => null, 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'fengbro-news', 'label' => '鋒兄新聞', 'csvStem' => 'fengbro-news', 'table' => null, 'csvOnly' => true, 'zipBundle' => false],
        ['id' => 'music', 'label' => '鋒兄音樂', 'csvStem' => 'music', 'zipStem' => 'music', 'table' => 'music', 'csvOnly' => true, 'zipBundle' => true],
        ['id' => 'videos', 'label' => '鋒兄影片', 'csvStem' => 'video', 'zipStem' => 'videos', 'table' => 'video', 'csvOnly' => true, 'zipBundle' => true],
        ['id' => 'images', 'label' => '鋒兄圖片', 'zipStem' => 'images', 'table' => 'image', 'csvOnly' => false, 'zipBundle' => true],
        ['id' => 'podcast', 'label' => '鋒兄播客', 'zipStem' => 'podcast', 'table' => 'podcast', 'csvOnly' => false, 'zipBundle' => true],
        ['id' => 'documents', 'label' => '鋒兄文件', 'zipStem' => 'documents', 'table' => 'commondocument', 'csvOnly' => false, 'zipBundle' => true],
        ['id' => 'notes', 'label' => '鋒兄筆記', 'zipStem' => 'notes', 'table' => 'article', 'csvOnly' => false, 'zipBundle' => true],
    ];
}

function fengbroMenuBackupCsvMenus(): array
{
    return array_values(array_filter(fengbroMenuBackupEntries(), static fn($e) => !empty($e['csvOnly'])));
}

function fengbroMenuBackupZipMenus(): array
{
    return array_values(array_filter(fengbroMenuBackupEntries(), static fn($e) => !empty($e['zipBundle'])));
}

function fengbroMenuBackupCsvAliases(): array
{
    return [
        'food' => 'food',
        'subscription' => 'subscription',
        'trialpurchase' => 'trial-purchase',
        'trial-purchase' => 'trial-purchase',
        'reinstall' => 'reinstall',
        'quota' => 'quota',
        'shoppinglist' => 'shopping-list',
        'shopping-list' => 'shopping-list',
        'commonaccount' => 'common',
        'common' => 'common',
        'bank' => 'bank-stats',
        'bank-stats' => 'bank-stats',
        'routine' => 'routine',
        'manual-price' => 'price-compare',
        'manualprice' => 'price-compare',
        'landtop-history' => 'landtop',
        'landtop' => 'landtop',
        'phone-price-history' => 'landtop',
        'fengbro-tube' => 'fengbro-tube',
        'fengbro-tube-channels' => 'fengbro-tube',
        'tubechannel' => 'fengbro-tube',
        'fengbro-finance' => 'fengbro-finance',
        'financeinstrument' => 'fengbro-finance',
        'fengbro-news' => 'fengbro-news',
        'music' => 'music',
        'video' => 'videos',
    ];
}

function fengbroMenuBackupZipAliases(): array
{
    return [
        'images' => 'images',
        'image' => 'images',
        'videos' => 'videos',
        'video' => 'videos',
        'music' => 'music',
        'podcast' => 'podcast',
        'documents' => 'documents',
        'document' => 'documents',
        'commondocument' => 'documents',
        'notes' => 'notes',
        'article' => 'notes',
        'appwrite-article' => 'notes',
    ];
}

function fengbroMenuBackupEntryById(string $id): ?array
{
    foreach (fengbroMenuBackupEntries() as $entry) {
        if ($entry['id'] === $id) {
            return $entry;
        }
    }
    return null;
}

function fengbroMenuBackupIdentifyFile(string $path): ?array
{
    $base = strtolower(str_replace('\\', '/', $path));
    $base = basename($base);
    if ($base === 'manifest.json' || $base === 'report.txt') {
        return null;
    }
    $kind = null;
    if (str_ends_with($base, '.csv')) {
        $kind = 'csv';
    } elseif (str_ends_with($base, '.zip')) {
        $kind = 'zip';
    }
    if (!$kind) {
        return null;
    }
    $stem = preg_replace('/\.(csv|zip)$/i', '', $base);
    if (str_starts_with($stem, 'appwrite-')) {
        $stem = substr($stem, strlen('appwrite-'));
    }
    if (str_starts_with($stem, 'laravel-')) {
        $stem = substr($stem, strlen('laravel-'));
    }
    $stem = preg_replace('/-\d{8}$/', '', $stem);
    $aliases = $kind === 'csv' ? fengbroMenuBackupCsvAliases() : fengbroMenuBackupZipAliases();
    if (isset($aliases[$stem])) {
        return ['id' => $aliases[$stem], 'kind' => $kind];
    }
    $known = array_keys($aliases);
    usort($known, static fn($a, $b) => strlen($b) <=> strlen($a));
    foreach ($known as $name) {
        if ($stem === $name || str_ends_with($stem, '-' . $name)) {
            return ['id' => $aliases[$name], 'kind' => $kind];
        }
    }
    return null;
}

function fengbroMenuBackupCsvEscape($value): string
{
    $s = $value === null ? '' : (string) $value;
    if (strpbrk($s, ",\"\n\r") !== false) {
        return '"' . str_replace('"', '""', $s) . '"';
    }
    return $s;
}

function fengbroMenuBackupTableCsv(PDO $pdo, string $table): array
{
    $rows = [];
    $columns = [];
    try {
        $stmt = $pdo->query("SELECT * FROM `{$table}` ORDER BY created_at DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows) {
            $columns = array_keys($rows[0]);
        } else {
            foreach ($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $columns[] = $col['Field'];
            }
        }
    } catch (Throwable $e) {
        throw new RuntimeException($table . ' 讀取失敗：' . $e->getMessage());
    }
    $map = ['id' => '$id', 'created_at' => '$createdAt', 'updated_at' => '$updatedAt'];
    $headers = array_map(static fn($c) => $map[$c] ?? $c, $columns);
    $lines = [implode(',', array_map('fengbroMenuBackupCsvEscape', $headers))];
    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $col) {
            $value = $row[$col] ?? '';
            if (in_array($col, ['created_at', 'updated_at'], true) && $value) {
                $ts = strtotime((string) $value);
                $value = $ts ? date('c', $ts) : $value;
            }
            $values[] = $value;
        }
        $lines[] = implode(',', array_map('fengbroMenuBackupCsvEscape', $values));
    }
    return ['csv' => "\xEF\xBB\xBF" . implode("\n", $lines) . "\n", 'rows' => count($rows)];
}

function fengbroMenuBackupManualPriceCsv(PDO $pdo): array
{
    fengbroEnsureManualPriceTable($pdo);
    $rows = $pdo->query('SELECT * FROM manualprice ORDER BY updated_at DESC, created_at DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $lines = ['name,currency,price,date,note,productId,recordId'];
    $count = 0;
    foreach ($rows as $row) {
        $product = fengbroManualPriceToClientProduct($row);
        $records = $product['records'] ?? [];
        if (!$records) {
            $lines[] = implode(',', array_map('fengbroMenuBackupCsvEscape', [
                $product['name'], $product['currency'], '', '', '', $product['id'], '',
            ]));
            $count++;
            continue;
        }
        foreach ($records as $record) {
            $lines[] = implode(',', array_map('fengbroMenuBackupCsvEscape', [
                $product['name'],
                $product['currency'],
                $record['price'] ?? '',
                $record['date'] ?? '',
                $record['note'] ?? '',
                $product['id'],
                $record['id'] ?? '',
            ]));
            $count++;
        }
    }
    return ['csv' => "\xEF\xBB\xBF" . implode("\n", $lines) . "\n", 'rows' => $count];
}

function fengbroMenuBackupLandtopCsv(PDO $pdo): array
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS tool_phone_product_history (
        id VARCHAR(36) PRIMARY KEY,
        product_id VARCHAR(190) NOT NULL,
        brand VARCHAR(50),
        name VARCHAR(500) NOT NULL,
        source VARCHAR(50) NOT NULL,
        price INT NULL,
        source_url VARCHAR(1000),
        snapshot_day DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_product_day_source (product_id, snapshot_day, source),
        INDEX idx_product_day (product_id, snapshot_day)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $rows = $pdo->query("SELECT product_id, brand, name, source, price, source_url, snapshot_day
        FROM tool_phone_product_history
        ORDER BY snapshot_day DESC, name ASC
        LIMIT 8000")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $bucket = [];
    foreach ($rows as $r) {
        $key = ($r['product_id'] ?? '') . '|' . ($r['snapshot_day'] ?? '');
        if (!isset($bucket[$key])) {
            $bucket[$key] = [
                'productId' => $r['product_id'] ?? '',
                'brand' => $r['brand'] ?? '',
                'name' => $r['name'] ?? '',
                'sourceUrl' => $r['source_url'] ?? '',
                'landtopPrice' => '',
                'jyesPrice' => '',
                'snapshotDate' => $r['snapshot_day'] ?? '',
                'source' => $r['source'] ?? '',
            ];
        }
        $src = strtolower((string) ($r['source'] ?? ''));
        $price = $r['price'] !== null ? (string) $r['price'] : '';
        if (str_contains($src, 'landtop') || $src === '地標' || $src === '地標網通') {
            $bucket[$key]['landtopPrice'] = $price;
        } elseif (str_contains($src, 'jyes') || str_contains($src, '傑昇')) {
            $bucket[$key]['jyesPrice'] = $price;
        } elseif ($bucket[$key]['landtopPrice'] === '') {
            $bucket[$key]['landtopPrice'] = $price;
        }
        if (!empty($r['source_url'])) {
            $bucket[$key]['sourceUrl'] = $r['source_url'];
        }
    }
    $lines = ['productId,brand,name,sourceUrl,landtopPrice,jyesPrice,snapshotDate,source'];
    foreach ($bucket as $row) {
        $lines[] = implode(',', array_map('fengbroMenuBackupCsvEscape', [
            $row['productId'], $row['brand'], $row['name'], $row['sourceUrl'],
            $row['landtopPrice'], $row['jyesPrice'], $row['snapshotDate'], $row['source'],
        ]));
    }
    return ['csv' => "\xEF\xBB\xBF" . implode("\n", $lines) . "\n", 'rows' => count($bucket)];
}

function fengbroMenuBackupTubeCsv(): array
{
    require_once __DIR__ . '/fengbro_tube.php';
    $channels = fengbroTubeChannels();
    $lines = ['alias,sourceUrl'];
    foreach ($channels as $ch) {
        $lines[] = implode(',', array_map('fengbroMenuBackupCsvEscape', [
            (string) ($ch['name'] ?? ''),
            (string) ($ch['url'] ?? ''),
        ]));
    }
    return ['csv' => "\xEF\xBB\xBF" . implode("\n", $lines) . "\n", 'rows' => count($channels)];
}

function fengbroMenuBackupFinanceCsv(): array
{
    require_once __DIR__ . '/fengbro_finance.php';
    $csv = fengbroFinanceBuildCsv();
    $rows = max(0, substr_count($csv, "\n") - 1);
    return ['csv' => (str_starts_with($csv, "\xEF") ? '' : "\xEF\xBB\xBF") . $csv, 'rows' => $rows];
}

function fengbroMenuBackupExportCsvEntry(PDO $pdo, array $entry, string $newsCsv = ''): array
{
    switch ($entry['id']) {
        case 'price-compare':
            return fengbroMenuBackupManualPriceCsv($pdo);
        case 'landtop':
            return fengbroMenuBackupLandtopCsv($pdo);
        case 'fengbro-tube':
            return fengbroMenuBackupTubeCsv();
        case 'fengbro-finance':
            return fengbroMenuBackupFinanceCsv();
        case 'fengbro-news':
            $text = trim($newsCsv);
            if ($text === '') {
                return ['csv' => "\xEF\xBB\xBF" . "id,name,domain,homeUrl,adapter,searchUrlTemplate,locked\n", 'rows' => 0];
            }
            if (!str_starts_with($text, "\xEF")) {
                $text = "\xEF\xBB\xBF" . ltrim($text, "\xEF\xBB\xBF");
            }
            $rows = max(0, substr_count(preg_replace('/^\xEF\xBB\xBF/', '', $text), "\n") - 1);
            return ['csv' => $text, 'rows' => $rows];
        default:
            if (empty($entry['table'])) {
                throw new RuntimeException('未知的 CSV 選單：' . $entry['id']);
            }
            if ($entry['table'] === 'trialpurchase') {
                fengbroEnsureTrialPurchaseTable($pdo);
            } elseif ($entry['table'] === 'reinstall') {
                fengbroEnsureReinstallTable($pdo);
            } elseif ($entry['table'] === 'quota') {
                fengbroEnsureQuotaTable($pdo);
            } elseif ($entry['table'] === 'shoppinglist') {
                fengbroEnsureShoppingListTable($pdo);
            }
            return fengbroMenuBackupTableCsv($pdo, $entry['table']);
    }
}

function fengbroMenuBackupSafeName(string $name): string
{
    $safe = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name);
    return $safe !== '' ? $safe : 'file';
}

function fengbroMenuBackupLocalFile(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '' || preg_match('#^https?://#i', $path)) {
        return null;
    }
    return is_file($path) ? $path : null;
}

function fengbroMenuBackupBuildMediaZip(PDO $pdo, array $entry, string $destPath): int
{
    $table = $entry['table'] ?? '';
    if ($table === '') {
        throw new RuntimeException('未知的 ZIP 選單：' . $entry['id']);
    }
    $sql = "SELECT * FROM `{$table}` ORDER BY created_at DESC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $innerCsv = [
        'images' => 'image.csv',
        'videos' => 'video.csv',
        'music' => 'music.csv',
        'podcast' => 'podcast.csv',
        'documents' => 'document.csv',
        'notes' => 'appwrite-article.csv',
    ][$entry['id']] ?? ($table . '.csv');

    $map = ['id' => '$id', 'created_at' => '$createdAt', 'updated_at' => '$updatedAt'];
    $columns = $rows ? array_keys($rows[0]) : [];
    if (!$columns) {
        foreach ($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $columns[] = $col['Field'];
        }
    }
    $headers = array_map(static fn($c) => $map[$c] ?? $c, $columns);
    $csvTemp = tempnam(sys_get_temp_dir(), 'mb_csv_');
    $csvHandle = fopen($csvTemp, 'w');
    fwrite($csvHandle, "\xEF\xBB\xBF");
    fputcsv($csvHandle, $headers);

    $fileMap = [];
    $counters = [];
    $folderByField = [
        'images' => ['file' => 'images', 'cover' => 'images'],
        'videos' => ['file' => 'videos', 'cover' => 'covers'],
        'music' => ['file' => 'music', 'cover' => 'covers'],
        'podcast' => ['file' => 'podcast', 'cover' => 'covers'],
        'documents' => ['file' => 'files', 'cover' => 'covers'],
        'notes' => ['file1' => 'files', 'file2' => 'files', 'file3' => 'files'],
    ][$entry['id']] ?? ['file' => 'files', 'cover' => 'covers'];

    foreach ($rows as $rowIdx => $row) {
        $rowFiles = [];
        foreach ($folderByField as $field => $folder) {
            $local = fengbroMenuBackupLocalFile($row[$field] ?? '');
            if (!$local) {
                continue;
            }
            $counters[$folder] = ($counters[$folder] ?? 0) + 1;
            $original = basename($local);
            if ($entry['id'] === 'notes') {
                $original = $row[$field . 'name'] ?? $original;
            }
            $safe = fengbroMenuBackupSafeName($original);
            $zipName = $folder . '/' . sprintf('%03d', $counters[$folder]) . '_' . $safe;
            $rowFiles[$field] = ['zipName' => $zipName, 'localPath' => $local];
        }
        if ($entry['id'] === 'music' && !empty($row['lyrics']) && !fengbroMenuBackupLocalFile($row['lyrics'])) {
            $counters['lyrics'] = ($counters['lyrics'] ?? 0) + 1;
            $lang = fengbroMenuBackupSafeName((string) ($row['language'] ?? ''));
            $name = fengbroMenuBackupSafeName((string) ($row['name'] ?? 'lyrics'));
            $zipName = 'lyrics/' . sprintf('%03d', $counters['lyrics']) . '_' . $name . ($lang ? '_' . $lang : '') . '.txt';
            $lyricsTemp = tempnam(sys_get_temp_dir(), 'mb_ly_');
            file_put_contents($lyricsTemp, (string) $row['lyrics']);
            $rowFiles['lyrics'] = ['zipName' => $zipName, 'localPath' => $lyricsTemp, 'temp' => true];
        }
        $fileMap[$rowIdx] = $rowFiles;
        $values = [];
        foreach ($columns as $col) {
            $value = $row[$col] ?? '';
            if (isset($rowFiles[$col])) {
                $value = $rowFiles[$col]['zipName'];
            } elseif ($col === 'cover' && isset($rowFiles['file']) && ($folderByField['cover'] ?? '') === ($folderByField['file'] ?? '')) {
                $value = $rowFiles['file']['zipName'];
            }
            if (in_array($col, ['created_at', 'updated_at'], true) && $value) {
                $ts = strtotime((string) $value);
                $value = $ts ? date('c', $ts) : $value;
            }
            $values[] = $value;
        }
        fputcsv($csvHandle, $values);
    }
    fclose($csvHandle);

    $zip = new StreamingZip();
    $zip->beginToFile($destPath);
    $zip->addLargeFile($csvTemp, $innerCsv);
    foreach ($fileMap as $rowFiles) {
        foreach ($rowFiles as $info) {
            $zip->addLargeFile($info['localPath'], $info['zipName']);
            if (!empty($info['temp'])) {
                @unlink($info['localPath']);
            }
        }
    }
    $zip->finish();
    @unlink($csvTemp);
    return count($rows);
}

function fengbroMenuBackupCleanupDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            fengbroMenuBackupCleanupDir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function fengbroMenuBackupWriteTemp(string $contents, string $suffix = '.csv'): string
{
    $path = tempnam(sys_get_temp_dir(), 'mb_');
    $named = $path . $suffix;
    @rename($path, $named);
    file_put_contents($named, $contents);
    return $named;
}

function fengbroMenuBackupExport(string $kind, string $newsCsv = ''): void
{
    $pdo = getConnection();
    $stamp = date('Ymd');
    $filename = $kind === 'all' ? "laravel-all-menus-{$stamp}.zip" : "laravel-all-csv-{$stamp}.zip";
    $workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fengbro_backup_' . uniqid();
    mkdir($workDir, 0755, true);
    $bundlePath = $workDir . DIRECTORY_SEPARATOR . $filename;
    $temps = [];
    $results = [];
    $included = [];

    $csvTargets = array_values(array_filter(
        fengbroMenuBackupCsvMenus(),
        static fn($e) => $kind === 'csv' || empty($e['zipBundle'])
    ));
    $zipTargets = $kind === 'all' ? fengbroMenuBackupZipMenus() : [];

    $outer = new StreamingZip();
    $outer->begin($filename);

    foreach ($csvTargets as $entry) {
        try {
            $built = fengbroMenuBackupExportCsvEntry($pdo, $entry, $newsCsv);
            $temp = fengbroMenuBackupWriteTemp($built['csv']);
            $temps[] = $temp;
            $outer->addLargeFile($temp, FENGBRO_MENU_BACKUP_CSV_DIR . '/' . $entry['csvStem'] . '.csv');
            $results[] = ['id' => $entry['id'], 'label' => $entry['label'], 'status' => 'ok', 'rows' => $built['rows']];
            $included[] = $entry['id'];
        } catch (Throwable $e) {
            $results[] = ['id' => $entry['id'], 'label' => $entry['label'], 'status' => 'error', 'rows' => 0, 'message' => $e->getMessage()];
        }
    }

    foreach ($zipTargets as $entry) {
        try {
            $innerPath = $workDir . DIRECTORY_SEPARATOR . ($entry['zipStem'] ?? $entry['id']) . '.zip';
            $rows = fengbroMenuBackupBuildMediaZip($pdo, $entry, $innerPath);
            $temps[] = $innerPath;
            $outer->addLargeFile($innerPath, FENGBRO_MENU_BACKUP_ZIP_DIR . '/' . ($entry['zipStem'] ?? $entry['id']) . '.zip');
            $results[] = ['id' => $entry['id'], 'label' => $entry['label'], 'status' => 'ok', 'rows' => $rows, 'message' => '已打包 ZIP'];
            $included[] = $entry['id'];
        } catch (Throwable $e) {
            $results[] = ['id' => $entry['id'], 'label' => $entry['label'], 'status' => 'error', 'rows' => 0, 'message' => $e->getMessage()];
        }
    }

    $manifest = json_encode([
        'version' => FENGBRO_MENU_BACKUP_VERSION,
        'kind' => $kind,
        'exportedAt' => date('c'),
        'menus' => $included,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $manifestPath = fengbroMenuBackupWriteTemp($manifest, '.json');
    $temps[] = $manifestPath;
    $outer->addLargeFile($manifestPath, 'manifest.json');

    $report = ["鋒兄選單備份", "kind: {$kind}", 'exportedAt: ' . date('c'), ''];
    foreach ($results as $result) {
        $report[] = sprintf(
            '%s (%s): %s %s 筆%s',
            $result['label'],
            $result['id'],
            $result['status'],
            $result['rows'],
            !empty($result['message']) ? ' — ' . $result['message'] : ''
        );
    }
    $reportPath = fengbroMenuBackupWriteTemp(implode("\n", $report), '.txt');
    $temps[] = $reportPath;
    $outer->addLargeFile($reportPath, 'report.txt');
    $outer->finish();

    foreach ($temps as $temp) {
        @unlink($temp);
    }
    fengbroMenuBackupCleanupDir($workDir);
    exit;
}

function fengbroMenuBackupFieldMap(): array
{
    return [
        '$id' => 'id', '$createdAt' => 'created_at', '$updatedAt' => 'updated_at',
        '名稱' => 'name', '銀行' => 'name', '銀行名稱' => 'name', '電子票證' => 'name',
        '存款' => 'deposit', '餘額' => 'deposit', '金額' => 'deposit',
        '提款' => 'withdrawals', '支出' => 'withdrawals', '轉帳' => 'transfer',
        '帳號' => 'account', '卡號' => 'card', '地址' => 'address', '網站' => 'site',
        '活動網址' => 'activity', '服務' => 'name', '服務名稱' => 'name',
        'event_date' => 'eventDate', '日期' => 'eventDate', '試用日' => 'eventDate',
        '首購日' => 'eventDate', '到期日' => 'eventDate', '扣款日' => 'eventDate',
        '試用／首購／到期日' => 'eventDate', '試用/首購/到期日' => 'eventDate',
        '試用／首購／到期日（扣款日）' => 'eventDate',
        'first_purchase_price' => 'firstPurchasePrice', '首購價格' => 'firstPurchasePrice',
        'regular_price' => 'regularPrice', '非首購價格' => 'regularPrice', '一般價格' => 'regularPrice',
        '備註' => 'note', 'trial_status' => 'trialStatus', '試用狀態' => 'trialStatus',
        'purchase_status' => 'purchaseStatus', '首購狀態' => 'purchaseStatus',
        '使用系統' => 'system', '系統' => 'system',
        'software_type' => 'softwareType', '軟體類型' => 'softwareType',
        'license_type' => 'licenseType', '授權方式' => 'licenseType',
        '付費序號' => 'serial', '序號' => 'serial',
        'view_password' => 'viewPassword', '查看密碼' => 'viewPassword',
        'subscription_software' => 'subscriptionSoftware', '訂閱制軟體' => 'subscriptionSoftware', '訂閱制' => 'subscriptionSoftware',
        'subscription_period' => 'subscriptionPeriod', '訂閱週期' => 'subscriptionPeriod', '週期' => 'subscriptionPeriod',
        'subscription_price' => 'subscriptionPrice', '訂閱費用' => 'subscriptionPrice', '費用' => 'subscriptionPrice',
        'subscription_currency' => 'subscriptionCurrency', '訂閱費用幣別' => 'subscriptionCurrency', '幣別' => 'subscriptionCurrency',
        '軟體網站' => 'site', 'service_type' => 'serviceType', '服務類型' => 'serviceType',
        'quota_remaining' => 'quotaRemaining', '剩餘次數' => 'quotaRemaining', '剩餘額度' => 'quotaRemaining', '額度剩餘次數' => 'quotaRemaining',
        'quota_ratio' => 'quotaRatio', '剩餘比例' => 'quotaRatio', '額度剩餘比例' => 'quotaRatio',
        'quota_expiry' => 'quotaExpiry', '額度到期日' => 'quotaExpiry',
        'ratio5h' => 'ratio5h', '5 小時比例' => 'ratio5h',
        'expiry5h' => 'expiry5h', '5 小時到期' => 'expiry5h',
        'ratio_week' => 'ratioWeek', '一週比例' => 'ratioWeek',
        'expiry_week' => 'expiryWeek', '一週到期' => 'expiryWeek',
        'ratio_month' => 'ratioMonth', '一月比例' => 'ratioMonth',
        'expiry_month' => 'expiryMonth', '一月到期' => 'expiryMonth',
        'planned_date' => 'plannedDate', 'planneddate' => 'plannedDate', '預定購買日' => 'plannedDate', '購買日' => 'plannedDate',
        '預定價格' => 'price', '預定數量' => 'quantity', '數量' => 'amount',
        '預定商店' => 'shop', '店家' => 'shop', '商店' => 'shop',
        'pickup_method' => 'pickupMethod', 'pickupmethod' => 'pickupMethod', '預定取貨方式' => 'pickupMethod', '取貨方式' => 'pickupMethod',
        '購物名稱' => 'name', '商品名稱' => 'name', '食物名稱' => 'name', '食品名稱' => 'name',
        '幣種' => 'currency', '貨幣' => 'currency',
        'image_url' => 'imageUrl', 'imageurl' => 'imageUrl', '圖片網址' => 'imageUrl', '商品圖片' => 'imageUrl',
        '照片' => 'photo', '圖片' => 'photo',
    ];
}

function fengbroMenuBackupImportStandardCsv(PDO $pdo, string $table, string $csvPath): array
{
    if ($table === 'trialpurchase') {
        fengbroEnsureTrialPurchaseTable($pdo);
    } elseif ($table === 'reinstall') {
        fengbroEnsureReinstallTable($pdo);
    } elseif ($table === 'quota') {
        fengbroEnsureQuotaTable($pdo);
    } elseif ($table === 'shoppinglist') {
        fengbroEnsureShoppingListTable($pdo);
    }
    $csvContent = file_get_contents($csvPath);
    if ($csvContent === false || trim($csvContent) === '') {
        return ['success' => false, 'imported' => 0, 'error' => 'CSV 為空'];
    }
    $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
    $lines = preg_split('/\r\n|\n|\r/', $csvContent);
    $lines = array_values(array_filter($lines, static fn($line) => trim($line) !== ''));
    if (!$lines) {
        return ['success' => false, 'imported' => 0, 'error' => '找不到欄位列'];
    }
    $delimiter = ',';
    $headerLine = $lines[0];
    foreach ([',', "\t", ';'] as $d) {
        if (substr_count($headerLine, $d) > substr_count($headerLine, $delimiter)) {
            $delimiter = $d;
        }
    }
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, implode("\n", $lines));
    rewind($handle);
    $headers = fgetcsv($handle, 0, $delimiter, '"', '');
    $fieldMapping = fengbroMenuBackupFieldMap();
    $headers = array_map(static function ($h) use ($fieldMapping) {
        $h = trim((string) $h);
        if (isset($fieldMapping[$h])) {
            return $fieldMapping[$h];
        }
        if (str_starts_with($h, '#')) {
            return substr($h, 1);
        }
        return $h;
    }, $headers ?: []);
    $dbColumns = [];
    foreach ($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $dbColumns[] = $col['Field'];
    }
    $ignored = ['$permissions', '$databaseId', '$collectionId', '$tenant'];
    $ignoredIndexes = [];
    foreach ($headers as $i => $h) {
        if (in_array($h, $ignored, true) || (str_starts_with((string) $h, '$') && !isset($fieldMapping[$h])) || !in_array($h, $dbColumns, true)) {
            $ignoredIndexes[] = $i;
        }
    }
    foreach ($ignoredIndexes as $i) {
        unset($headers[$i]);
    }
    $headers = array_values($headers);
    if (!$headers) {
        return ['success' => false, 'imported' => 0, 'error' => '找不到可匯入欄位'];
    }
    $imported = 0;
    $skipped = 0;
    $errors = [];
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
        foreach ($ignoredIndexes as $i) {
            unset($row[$i]);
        }
        $row = array_values($row);
        if (count($row) !== count($headers)) {
            $skipped++;
            continue;
        }
        $data = array_combine($headers, $row);
        $hasSourceId = !empty($data['id']);
        if (!$hasSourceId) {
            $data['id'] = generateUUID();
        }
        $currentId = $data['id'];
        foreach ($data as $key => $value) {
            if ($value === '' || $value === 'null') {
                $data[$key] = null;
            }
        }
        if ($table === 'bank') {
            foreach (['deposit', 'withdrawals', 'transfer'] as $moneyColumn) {
                if (array_key_exists($moneyColumn, $data)) {
                    $clean = preg_replace('/[^\d\-.]/u', '', (string) $data[$moneyColumn]);
                    $data[$moneyColumn] = ($clean === '' || $clean === '-' || $clean === '.') ? 0 : (int) round((float) $clean);
                }
            }
        }
        if ($table === 'subscription' && array_key_exists('note', $data) && $data['note'] !== null) {
            $data['note'] = mb_substr((string) $data['note'], 0, 100);
        }
        try {
            if ($table === 'trialpurchase') {
                $data = array_merge($data, fengbroSanitizeTrialPurchaseRow($data));
            } elseif ($table === 'reinstall') {
                $data = array_merge($data, fengbroSanitizeReinstallRow($data));
            } elseif ($table === 'quota') {
                $data = array_merge($data, fengbroSanitizeQuotaRow($data));
            } elseif ($table === 'shoppinglist') {
                $data = array_merge($data, fengbroSanitizeShoppingItemRow($data));
            }
        } catch (InvalidArgumentException $e) {
            $skipped++;
            $errors[] = $e->getMessage();
            continue;
        }
        foreach ($data as $key => $value) {
            if ($value !== null && is_string($value) && preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/', $value, $m)) {
                $data[$key] = $m[1] . ' ' . $m[2];
            }
        }
        if (array_key_exists('continue', $data) && $data['continue'] !== null) {
            $data['continue'] = filter_var($data['continue'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        if (!$hasSourceId) {
            $duplicateId = null;
            if ($table === 'trialpurchase') {
                $duplicateId = fengbroFindTrialPurchaseImportId($pdo, $data);
            } elseif ($table === 'reinstall') {
                $duplicateId = fengbroFindReinstallImportId($pdo, $data);
            } elseif ($table === 'quota') {
                $duplicateId = fengbroFindQuotaImportId($pdo, $data);
            } elseif ($table === 'shoppinglist') {
                $duplicateId = fengbroFindShoppingImportId($pdo, $data);
            } else {
                $duplicateId = findExistingImportRecordId($pdo, $table, $data);
            }
            if ($duplicateId) {
                $currentId = $duplicateId;
                $data['id'] = $duplicateId;
            }
        }
        $existsStmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE id = ?");
        $existsStmt->execute([$currentId]);
        $exists = $existsStmt->fetch();
        try {
            if ($exists) {
                unset($data['id']);
                $sets = [];
                foreach (array_keys($data) as $col) {
                    $sets[] = "`{$col}` = ?";
                }
                $stmt = $pdo->prepare("UPDATE `{$table}` SET " . implode(',', $sets) . ' WHERE id = ?');
                $values = array_values($data);
                $values[] = $currentId;
                $stmt->execute($values);
            } else {
                $columns = array_map(static fn($c) => "`{$c}`", array_keys($data));
                $placeholders = array_fill(0, count($data), '?');
                $stmt = $pdo->prepare("INSERT INTO `{$table}` (" . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')');
                $stmt->execute(array_values($data));
            }
            $imported++;
        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
        }
    }
    fclose($handle);
    return ['success' => true, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
}

function fengbroMenuBackupImportManualPrice(PDO $pdo, string $csvText): array
{
    fengbroEnsureManualPriceTable($pdo);
    $csvText = preg_replace('/^\xEF\xBB\xBF/', '', $csvText);
    $lines = preg_split('/\r\n|\n|\r/', $csvText) ?: [];
    $start = 0;
    if ($lines && preg_match('/name|currency|price|date/i', $lines[0])) {
        $start = 1;
    }
    $byId = [];
    for ($i = $start; $i < count($lines); $i++) {
        $cols = str_getcsv($lines[$i]);
        if (!$cols || count(array_filter($cols, 'strlen')) === 0) {
            continue;
        }
        $name = trim((string) ($cols[0] ?? ''));
        if ($name === '') {
            continue;
        }
        $currency = fengbroManualPriceNormalizeCurrency($cols[1] ?? 'TWD');
        $productId = trim((string) ($cols[5] ?? ''));
        if ($productId === '') {
            $productId = generateUUID();
        }
        if (!isset($byId[$productId])) {
            $byId[$productId] = ['id' => $productId, 'name' => $name, 'currency' => $currency, 'records' => []];
        }
        $priceRaw = trim((string) ($cols[2] ?? ''));
        $date = trim((string) ($cols[3] ?? ''));
        if ($priceRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $rec = [
                'id' => trim((string) ($cols[6] ?? '')) ?: generateUUID(),
                'price' => (float) $priceRaw,
                'date' => $date,
            ];
            $note = trim((string) ($cols[4] ?? ''));
            if ($note !== '') {
                $rec['note'] = $note;
            }
            $byId[$productId]['records'][] = $rec;
        }
    }
    $ok = 0;
    foreach ($byId as $product) {
        $existing = $pdo->prepare('SELECT id FROM manualprice WHERE id = ?');
        $existing->execute([$product['id']]);
        $row = [
            'id' => $product['id'],
            'name' => $product['name'],
            'currency' => $product['currency'],
            'recordsJson' => fengbroManualPriceSerializeRecords($product['records']),
        ];
        try {
            $clean = fengbroSanitizeManualPriceRow($row);
        } catch (InvalidArgumentException $e) {
            continue;
        }
        if ($existing->fetch()) {
            $stmt = $pdo->prepare('UPDATE manualprice SET name = ?, currency = ?, recordsJson = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$clean['name'], $clean['currency'], $clean['recordsJson'], $product['id']]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO manualprice (id, name, currency, recordsJson) VALUES (?, ?, ?, ?)');
            $stmt->execute([$product['id'], $clean['name'], $clean['currency'], $clean['recordsJson']]);
        }
        $ok++;
    }
    return ['success' => true, 'imported' => $ok];
}

function fengbroMenuBackupImportLandtop(PDO $pdo, string $csvText): array
{
    $csvText = preg_replace('/^\xEF\xBB\xBF/', '', $csvText);
    $pdo->exec("CREATE TABLE IF NOT EXISTS tool_phone_product_history (
        id VARCHAR(36) PRIMARY KEY,
        product_id VARCHAR(190) NOT NULL,
        brand VARCHAR(50),
        name VARCHAR(500) NOT NULL,
        source VARCHAR(50) NOT NULL,
        price INT NULL,
        source_url VARCHAR(1000),
        snapshot_day DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $lines = preg_split('/\r\n|\r|\n/', $csvText) ?: [];
    $start = 0;
    if ($lines && preg_match('/productid|product_id|brand|name|snapshot/i', $lines[0])) {
        $start = 1;
    }
    $imported = 0;
    $stmt = $pdo->prepare("INSERT INTO tool_phone_product_history
        (id, product_id, brand, name, source, price, source_url, snapshot_day)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    for ($i = $start; $i < count($lines); $i++) {
        $cols = str_getcsv($lines[$i]);
        $productId = trim((string) ($cols[0] ?? ''));
        $brand = trim((string) ($cols[1] ?? ''));
        $name = trim((string) ($cols[2] ?? ''));
        $sourceUrl = trim((string) ($cols[3] ?? ''));
        $landtop = $cols[4] ?? '';
        $jyes = $cols[5] ?? '';
        $day = trim((string) ($cols[6] ?? ''));
        if ($day !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $day, $dm)) {
            $day = $dm[0];
        } else {
            $day = date('Y-m-d');
        }
        if ($productId === '' && $name === '') {
            continue;
        }
        if ($productId === '') {
            $productId = substr(preg_replace('/[^a-z0-9]+/i', '-', strtolower($brand . '-' . $name)) ?? 'p', 0, 160);
        }
        $rowsToWrite = [];
        if ($landtop !== '' && is_numeric(str_replace(',', '', (string) $landtop))) {
            $rowsToWrite[] = ['landtop', (int) str_replace(',', '', (string) $landtop)];
        }
        if ($jyes !== '' && is_numeric(str_replace(',', '', (string) $jyes))) {
            $rowsToWrite[] = ['jyes', (int) str_replace(',', '', (string) $jyes)];
        }
        foreach ($rowsToWrite as [$source, $price]) {
            try {
                $stmt->execute([
                    generateUUID(), $productId, $brand !== '' ? $brand : null, $name !== '' ? $name : $productId,
                    $source, $price, $sourceUrl !== '' ? $sourceUrl : null, $day,
                ]);
                $imported++;
            } catch (Throwable $e) {
                // skip duplicate / constraint
            }
        }
    }
    return ['success' => true, 'imported' => $imported];
}

function fengbroMenuBackupParseCsvObjects(string $text): array
{
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $lines = preg_split('/\r\n|\n|\r/', $text) ?: [];
    $lines = array_values(array_filter($lines, static fn($l) => trim($l) !== ''));
    if (count($lines) < 2) {
        return [];
    }
    $headers = array_map('trim', str_getcsv($lines[0]));
    $out = [];
    for ($i = 1; $i < count($lines); $i++) {
        $cols = str_getcsv($lines[$i]);
        $row = [];
        $has = false;
        foreach ($headers as $idx => $h) {
            if ($h === '') {
                continue;
            }
            $v = isset($cols[$idx]) ? trim((string) $cols[$idx]) : '';
            $row[$h] = $v;
            if ($v !== '') {
                $has = true;
            }
        }
        if ($has) {
            $out[] = $row;
        }
    }
    return $out;
}

function fengbroMenuBackupExtractZip(string $zipPath, string $destDir): bool
{
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    if (class_exists('ZipArchive')) {
        $za = new ZipArchive();
        if ($za->open($zipPath) === true) {
            $ok = $za->extractTo($destDir);
            $za->close();
            return $ok;
        }
    }
    $zip = new PureZipExtract();
    if (!$zip->open($zipPath)) {
        return false;
    }
    $zip->extractTo($destDir);
    return true;
}

function fengbroMenuBackupFindFile(string $root, string $relative): ?string
{
    $relative = str_replace('\\', '/', $relative);
    $direct = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (is_file($direct)) {
        return $direct;
    }
    $base = basename($relative);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (strcasecmp($file->getFilename(), $base) === 0) {
            return $file->getPathname();
        }
    }
    return null;
}

function fengbroMenuBackupCopyAsset(string $extractDir, string $relative, string $uploadDir): string
{
    $relative = trim($relative);
    if ($relative === '' || preg_match('#^https?://#i', $relative)) {
        return $relative;
    }
    $src = fengbroMenuBackupFindFile($extractDir, $relative);
    if (!$src) {
        return $relative;
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $dest = rtrim($uploadDir, '/\\') . '/' . uniqid('mb_', true) . '_' . fengbroMenuBackupSafeName(basename($src));
    if (!@copy($src, $dest)) {
        return $relative;
    }
    return $dest;
}

function fengbroMenuBackupImportMediaZip(PDO $pdo, array $entry, string $zipPath): array
{
    $table = $entry['table'] ?? '';
    if ($table === '') {
        return ['success' => false, 'imported' => 0, 'error' => '未知 ZIP 選單'];
    }
    $extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mb_in_' . uniqid();
    if (!fengbroMenuBackupExtractZip($zipPath, $extractDir)) {
        return ['success' => false, 'imported' => 0, 'error' => '解壓失敗'];
    }
    $csvFile = null;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (strcasecmp($file->getExtension(), 'csv') === 0) {
            $csvFile = $file->getPathname();
            break;
        }
    }
    if (!$csvFile) {
        fengbroMenuBackupCleanupDir($extractDir);
        return ['success' => false, 'imported' => 0, 'error' => 'ZIP 裡沒有 CSV'];
    }
    $objects = fengbroMenuBackupParseCsvObjects((string) file_get_contents($csvFile));
    $fileFields = ['file', 'cover', 'file1', 'file2', 'file3', 'lyrics', 'photo'];
    $tmpCsv = tempnam(sys_get_temp_dir(), 'mb_imp_');
    $fh = fopen($tmpCsv, 'w');
    fwrite($fh, "\xEF\xBB\xBF");
    if ($objects) {
        $headers = array_keys($objects[0]);
        fputcsv($fh, $headers);
        foreach ($objects as $row) {
            foreach ($fileFields as $field) {
                if (!empty($row[$field]) && !preg_match('#^https?://#i', $row[$field]) && !str_contains($row[$field], "\n")) {
                    $copied = fengbroMenuBackupCopyAsset($extractDir, $row[$field], 'uploads');
                    if ($copied !== $row[$field] && is_file($copied)) {
                        $row[$field] = $copied;
                    } elseif ($field === 'lyrics' && ($src = fengbroMenuBackupFindFile($extractDir, $row[$field]))) {
                        $row[$field] = (string) file_get_contents($src);
                    }
                }
            }
            $values = [];
            foreach ($headers as $h) {
                $values[] = $row[$h] ?? '';
            }
            fputcsv($fh, $values);
        }
    }
    fclose($fh);
    $result = fengbroMenuBackupImportStandardCsv($pdo, $table, $tmpCsv);
    @unlink($tmpCsv);
    fengbroMenuBackupCleanupDir($extractDir);
    return $result;
}

function fengbroMenuBackupImportCsvEntry(PDO $pdo, array $entry, string $csvText): array
{
    switch ($entry['id']) {
        case 'price-compare':
            return fengbroMenuBackupImportManualPrice($pdo, $csvText);
        case 'landtop':
            return fengbroMenuBackupImportLandtop($pdo, $csvText);
        case 'fengbro-tube':
            require_once __DIR__ . '/fengbro_tube.php';
            $csvText = preg_replace('/^\xEF\xBB\xBF/', '', $csvText);
            $lines = preg_split('/\r\n|\r|\n/', $csvText) ?: [];
            $start = 0;
            if ($lines && preg_match('/alias|sourceurl|網址|名稱/i', $lines[0])) {
                $start = 1;
            }
            $imported = [];
            $seen = [];
            for ($i = $start; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if ($line === '') {
                    continue;
                }
                $parts = str_getcsv($line);
                $alias = trim((string) ($parts[0] ?? ''));
                $url = trim((string) ($parts[1] ?? $parts[0] ?? ''));
                if ($url === '' || isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;
                $imported[] = ['name' => $alias, 'url' => $url];
            }
            if (!$imported) {
                return ['success' => false, 'imported' => 0, 'error' => 'CSV 沒有可匯入的頻道'];
            }
            $existing = fengbroTubeChannels() ?: [];
            $byUrl = [];
            foreach ($existing as $ch) {
                $byUrl[(string) ($ch['url'] ?? '')] = $ch;
            }
            foreach ($imported as $ch) {
                $byUrl[$ch['url']] = $ch;
            }
            fengbroTubeSaveChannels(array_values($byUrl));
            return ['success' => true, 'imported' => count($imported)];
        case 'fengbro-finance':
            require_once __DIR__ . '/fengbro_finance.php';
            $info = fengbroFinanceImportCsv($csvText);
            $count = is_array($info) ? (int) ($info['customCount'] ?? 0) + (int) ($info['imageCount'] ?? 0) : 0;
            if ($count <= 0 && is_array($info)) {
                $count = max(1, count($info['errors'] ?? []) === 0 ? 1 : 0);
            }
            $error = !empty($info['errors']) ? implode('；', array_slice($info['errors'], 0, 3)) : null;
            return ['success' => empty($info['errors']) || $count > 0, 'imported' => $count, 'error' => $error];
        case 'fengbro-news':
            $sites = [];
            foreach (fengbroMenuBackupParseCsvObjects($csvText) as $row) {
                $id = trim((string) ($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $sites[] = [
                    'id' => $id,
                    'name' => $row['name'] ?? $id,
                    'domain' => $row['domain'] ?? '',
                    'homeUrl' => $row['homeUrl'] ?? ($row['homeurl'] ?? ''),
                    'adapter' => $row['adapter'] ?? 'generic-keyword-url',
                    'searchUrlTemplate' => $row['searchUrlTemplate'] ?? ($row['searchurltemplate'] ?? null),
                    'locked' => in_array(strtolower((string) ($row['locked'] ?? '')), ['1', 'true', 'yes'], true),
                ];
            }
            return ['success' => true, 'imported' => count($sites), 'newsSites' => $sites];
        default:
            if (empty($entry['table'])) {
                return ['success' => false, 'imported' => 0, 'error' => '未知選單'];
            }
            $temp = fengbroMenuBackupWriteTemp($csvText);
            $result = fengbroMenuBackupImportStandardCsv($pdo, $entry['table'], $temp);
            @unlink($temp);
            return $result;
    }
}

function fengbroMenuBackupCollectFiles(string $root): array
{
    $out = [];
    if (!is_dir($root)) {
        return $out;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $out[] = ['path' => $rel, 'full' => $file->getPathname()];
    }
    return $out;
}

function fengbroMenuBackupImport(string $kind, string $uploadPath, string $originalName): array
{
    $pdo = getConnection();
    $results = [];
    $newsSites = null;
    $name = strtolower($originalName);

    if (str_ends_with($name, '.csv')) {
        $identified = fengbroMenuBackupIdentifyFile($originalName);
        $entry = $identified ? fengbroMenuBackupEntryById($identified['id']) : null;
        if (!$entry || empty($entry['csvOnly'])) {
            return [
                'success' => false,
                'results' => [['id' => 'unknown', 'label' => $originalName, 'status' => 'error', 'rows' => 0, 'message' => '無法辨識這個 CSV 屬於哪個選單']],
            ];
        }
        $text = (string) file_get_contents($uploadPath);
        $imported = fengbroMenuBackupImportCsvEntry($pdo, $entry, $text);
        $item = [
            'id' => $entry['id'],
            'label' => $entry['label'],
            'status' => !empty($imported['success']) ? 'ok' : 'error',
            'rows' => (int) ($imported['imported'] ?? 0),
            'message' => $imported['error'] ?? null,
        ];
        if (!empty($imported['newsSites'])) {
            $newsSites = $imported['newsSites'];
        }
        return ['success' => !empty($imported['success']), 'results' => [$item], 'newsSites' => $newsSites];
    }

    if (!str_ends_with($name, '.zip')) {
        return [
            'success' => false,
            'results' => [['id' => 'unknown', 'label' => $originalName, 'status' => 'error', 'rows' => 0, 'message' => '請選擇 .zip 或 .csv']],
        ];
    }

    $extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mb_bundle_' . uniqid();
    if (!fengbroMenuBackupExtractZip($uploadPath, $extractDir)) {
        return [
            'success' => false,
            'results' => [['id' => 'unknown', 'label' => $originalName, 'status' => 'error', 'rows' => 0, 'message' => '解壓失敗']],
        ];
    }

    $seenCsv = [];
    $seenZip = [];
    foreach (fengbroMenuBackupCollectFiles($extractDir) as $file) {
        $identified = fengbroMenuBackupIdentifyFile($file['path']);
        if (!$identified) {
            continue;
        }
        $entry = fengbroMenuBackupEntryById($identified['id']);
        if (!$entry) {
            continue;
        }
        if ($identified['kind'] === 'zip') {
            if ($kind === 'csv' || isset($seenZip[$entry['id']])) {
                continue;
            }
            $seenZip[$entry['id']] = true;
            $imported = fengbroMenuBackupImportMediaZip($pdo, $entry, $file['full']);
            $results[] = [
                'id' => $entry['id'],
                'label' => $entry['label'],
                'status' => !empty($imported['success']) ? 'ok' : 'error',
                'rows' => (int) ($imported['imported'] ?? 0),
                'message' => $imported['error'] ?? null,
            ];
            continue;
        }
        if ($identified['kind'] === 'csv') {
            if ($kind === 'all' && !empty($entry['zipBundle'])) {
                continue;
            }
            if (isset($seenCsv[$entry['id']])) {
                continue;
            }
            $seenCsv[$entry['id']] = true;
            $imported = fengbroMenuBackupImportCsvEntry($pdo, $entry, (string) file_get_contents($file['full']));
            if (!empty($imported['newsSites'])) {
                $newsSites = $imported['newsSites'];
            }
            $results[] = [
                'id' => $entry['id'],
                'label' => $entry['label'],
                'status' => !empty($imported['success']) ? 'ok' : 'error',
                'rows' => (int) ($imported['imported'] ?? 0),
                'message' => $imported['error'] ?? null,
            ];
        }
    }
    fengbroMenuBackupCleanupDir($extractDir);
    if (!$results) {
        $results[] = [
            'id' => 'unknown',
            'label' => $originalName,
            'status' => 'error',
            'rows' => 0,
            'message' => $kind === 'csv'
                ? 'ZIP 裡沒有可辨識的 CSV（請放在 csv/ 或檔名含選單名稱）'
                : 'ZIP 裡沒有可辨識的 CSV / ZIP（請放在 csv/ 與 zip/）',
        ];
    }
    $fail = count(array_filter($results, static fn($r) => $r['status'] === 'error'));
    return ['success' => $fail === 0, 'results' => $results, 'newsSites' => $newsSites];
}

function fengbroMenuBackupSummarize(array $results): string
{
    $ok = count(array_filter($results, static fn($r) => ($r['status'] ?? '') === 'ok'));
    $fail = count(array_filter($results, static fn($r) => ($r['status'] ?? '') === 'error'));
    $skipped = count(array_filter($results, static fn($r) => ($r['status'] ?? '') === 'skipped'));
    $lines = ["完成 {$ok} 個選單" . ($fail ? "，失敗 {$fail}" : '') . ($skipped ? "，略過 {$skipped}" : '') . '。'];
    foreach ($results as $result) {
        if (($result['status'] ?? '') === 'error') {
            $lines[] = ($result['label'] ?? '') . '：' . ($result['message'] ?? '失敗');
        }
        if (count($lines) >= 9) {
            break;
        }
    }
    return implode("\n", $lines);
}
