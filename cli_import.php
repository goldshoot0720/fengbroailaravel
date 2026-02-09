<?php
// CLI 匯入腳本 - 從 CSV 檔案匯入訂閱資料
require_once 'includes/functions.php';

$csvFile = $argv[1] ?? 'import_data.csv';
$table = $argv[2] ?? 'subscription';

$fieldMapping = [
    '$id' => 'id',
    '$createdAt' => 'created_at',
    '$updatedAt' => 'updated_at'
];

$pdo = getConnection();
$handle = fopen($csvFile, 'r');

$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

$headers = fgetcsv($handle, 0, ',', '"', '\\');
$headers = array_map(function($h) use ($fieldMapping) {
    $h = trim($h);
    return $fieldMapping[$h] ?? $h;
}, $headers);

$imported = 0;
$errors = [];

while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
    if (count($row) !== count($headers)) continue;
    $data = array_combine($headers, $row);

    if (empty($data['id'])) $data['id'] = generateUUID();
    unset($data['created_at'], $data['updated_at']);

    foreach ($data as $key => $value) {
        if ($value === '' || $value === 'null') $data[$key] = null;
    }

    if (isset($data['continue'])) {
        $data['continue'] = filter_var($data['continue'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id = ?");
    $stmt->execute([$data['id']]);
    $exists = $stmt->fetch();

    try {
        if ($exists) {
            $id = $data['id'];
            unset($data['id']);
            $sets = [];
            foreach (array_keys($data) as $col) $sets[] = "`{$col}` = ?";
            $sql = "UPDATE {$table} SET " . implode(',', $sets) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $values = array_values($data);
            $values[] = $id;
            $stmt->execute($values);
        } else {
            $columns = array_map(function($c) { return "`{$c}`"; }, array_keys($data));
            $placeholders = array_fill(0, count($data), '?');
            $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
        }
        $imported++;
        echo "OK: {$data['name']}\n";
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
        echo "ERROR: {$e->getMessage()}\n";
    }
}

fclose($handle);
echo "\n匯入完成: {$imported} 筆\n";
if ($errors) echo "錯誤: " . count($errors) . " 筆\n";
