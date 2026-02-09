<?php
// CLI 匯入腳本 - 支援 LaravelMySQL 和 Appwrite 雙格式 CSV
require_once 'includes/functions.php';

$csvFile = $argv[1] ?? 'import_data.csv';
$table = $argv[2] ?? 'subscription';

// 支援 LaravelMySQL 和 Appwrite 雙格式
$fieldMapping = [
    '$id' => 'id',
    '$createdAt' => 'created_at',
    '$updatedAt' => 'updated_at'
];

$ignoredColumns = ['$permissions', '$databaseId', '$collectionId', '$tenant'];

$pdo = getConnection();
$handle = fopen($csvFile, 'r');

// 跳過 BOM
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

// 讀取並轉換標頭
$headers = fgetcsv($handle);
$headers = array_map(function($h) use ($fieldMapping) {
    $h = trim($h);
    return $fieldMapping[$h] ?? $h;
}, $headers);

// 忽略 Appwrite metadata 欄位
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

// 偵測格式
$format = empty($ignoredIndexes) ? 'LaravelMySQL' : 'Appwrite';
echo "偵測格式: {$format}\n";
echo "欄位 ({$headerCount}): " . implode(', ', $headers) . "\n\n";

$imported = 0;
$skipped = 0;
$errors = [];
$lineNum = 1;

while (($row = fgetcsv($handle)) !== false) {
    $lineNum++;

    foreach ($ignoredIndexes as $i) {
        unset($row[$i]);
    }
    $row = array_values($row);

    if (count($row) !== $headerCount) {
        $skipped++;
        echo "SKIP 第 {$lineNum} 行: 欄位數不匹配 (預期 {$headerCount}, 實際 " . count($row) . ")\n";
        continue;
    }

    $data = array_combine($headers, $row);
    $recordName = $data['name'] ?? '未知';

    if (empty($data['id'])) $data['id'] = generateUUID();
    $currentId = $data['id'];
    unset($data['created_at'], $data['updated_at']);

    foreach ($data as $key => $value) {
        if ($value === '' || $value === 'null') $data[$key] = null;
    }

    if (array_key_exists('continue', $data) && $data['continue'] !== null) {
        $data['continue'] = filter_var($data['continue'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    } elseif (array_key_exists('continue', $data)) {
        $data['continue'] = 1;
    }

    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id = ?");
    $stmt->execute([$currentId]);
    $exists = $stmt->fetch();

    try {
        if ($exists) {
            unset($data['id']);
            $sets = [];
            foreach (array_keys($data) as $col) $sets[] = "`{$col}` = ?";
            $sql = "UPDATE {$table} SET " . implode(',', $sets) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $values = array_values($data);
            $values[] = $currentId;
            $stmt->execute($values);
            echo "UPDATE: {$recordName}\n";
        } else {
            $columns = array_map(function($c) { return "`{$c}`"; }, array_keys($data));
            $placeholders = array_fill(0, count($data), '?');
            $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            echo "INSERT: {$recordName}\n";
        }
        $imported++;
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
        echo "ERROR [{$recordName}]: {$e->getMessage()}\n";
    }
}

fclose($handle);
echo "\n=============================\n";
echo "匯入完成: {$imported} 筆\n";
if ($skipped) echo "跳過: {$skipped} 筆\n";
if ($errors) echo "錯誤: " . count($errors) . " 筆\n";
