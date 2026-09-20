<?php
/**
 * 匯入去重測試（不需資料庫）：
 *   php tests/import_dedupe_test.php
 */

// 只載入 functions.php 中的去重區段，避免測試時連線資料庫
$source = file_get_contents(__DIR__ . '/../includes/functions.php');
$marker = '/* ===========================================================';
$offset = strpos($source, $marker);
if ($offset === false) {
    fwrite(STDERR, "找不到去重區段\n");
    exit(1);
}
eval(substr($source, $offset));

$failures = 0;
function check(string $label, $expected, $actual): void
{
    global $failures;
    $ok = $expected === $actual;
    if (!$ok) {
        $failures++;
    }
    printf(
        "%s %s (預期 %s，實際 %s)\n",
        $ok ? '✓' : '✗',
        $label,
        json_encode($expected, JSON_UNESCAPED_UNICODE),
        json_encode($actual, JSON_UNESCAPED_UNICODE)
    );
}

// 1. 同一個 id 出現三次，保留 updated_at 最新的那筆
$rows = [
    ['id' => 'a1', 'name' => 'Netflix', 'price' => '100', 'updated_at' => '2025-01-01 10:00:00'],
    ['id' => 'a1', 'name' => 'Netflix', 'price' => '390', 'updated_at' => '2025-06-01 10:00:00'],
    ['id' => 'a1', 'name' => 'Netflix', 'price' => '250', 'updated_at' => '2025-03-01 10:00:00'],
];
$result = fengbroDedupeImportRows('subscription', $rows);
check('同 id 去重後筆數', 1, count($result['rows']));
check('同 id 保留最新版本', '390', $result['rows'][0]['price']);
check('同 id 移除筆數', 2, $result['removed']);

// 2. Appwrite ISO 8601 時間格式也能比較
$rows = [
    ['$id' => 'b2', 'id' => 'b2', 'name' => 'Spotify', 'price' => '149', '$updatedAt' => '2025-09-01T08:30:00.000+00:00'],
    ['$id' => 'b2', 'id' => 'b2', 'name' => 'Spotify', 'price' => '199', '$updatedAt' => '2024-09-01T08:30:00.000+00:00'],
];
$result = fengbroDedupeImportRows('subscription', $rows);
check('ISO 8601 保留最新版本', '149', $result['rows'][0]['price']);

// 3. 沒有 id 時，用自然識別欄位（subscription = name + account）
$rows = [
    ['name' => 'YouTube', 'account' => 'me@x.com', 'price' => '179', 'updated_at' => '2025-02-01 00:00:00'],
    ['name' => 'YouTube', 'account' => 'me@x.com', 'price' => '199', 'updated_at' => '2025-08-01 00:00:00'],
    ['name' => 'YouTube', 'account' => 'other@x.com', 'price' => '99', 'updated_at' => '2025-08-01 00:00:00'],
];
$result = fengbroDedupeImportRows('subscription', $rows);
check('自然鍵去重後筆數', 2, count($result['rows']));
check('自然鍵保留最新版本', '199', $result['rows'][0]['price']);
check('不同 account 視為不同筆', '99', $result['rows'][1]['price']);

// 4. 沒有時間欄位時，以檔案中較後面者為準（維持原本後寫入覆蓋的行為）
$rows = [
    ['name' => '牛奶', 'shop' => '全聯', 'price' => '60'],
    ['name' => '牛奶', 'shop' => '全聯', 'price' => '75'],
];
$result = fengbroDedupeImportRows('food', $rows);
check('無時間欄位時後者勝', '75', $result['rows'][0]['price']);

// 5. 有 created_at 沒 updated_at 時用 created_at 比較
$rows = [
    ['name' => '麵包', 'shop' => '7-11', 'price' => '35', 'created_at' => '2025-05-05 00:00:00'],
    ['name' => '麵包', 'shop' => '7-11', 'price' => '45', 'created_at' => '2025-01-05 00:00:00'],
];
$result = fengbroDedupeImportRows('food', $rows);
check('退回 created_at 比較', '35', $result['rows'][0]['price']);

// 6. 保留位置：第一次出現的順序不變
$rows = [
    ['name' => 'A'],
    ['name' => 'B'],
    ['name' => 'A', 'note' => 'newer'],
    ['name' => 'C'],
];
$result = fengbroDedupeImportRows('routine', $rows);
check('保留第一次出現的順序', ['A', 'B', 'C'], array_column($result['rows'], 'name'));

// 7. 媒體表以 hash 為優先鍵
$rows = [
    ['name' => '封面舊檔名', 'file' => 'old.png', 'hash' => 'HASH1', 'updated_at' => '2025-01-01 00:00:00'],
    ['name' => '封面新檔名', 'file' => 'new.png', 'hash' => 'hash1', 'updated_at' => '2025-07-01 00:00:00'],
];
$result = fengbroDedupeImportRows('image', $rows);
check('hash 優先且不分大小寫', 1, count($result['rows']));
check('hash 保留最新版本', '封面新檔名', $result['rows'][0]['name']);

// 8. 無法判斷身分的列一律保留
$rows = [
    ['note' => '只有備註'],
    ['note' => '只有備註'],
];
$result = fengbroDedupeImportRows('routine', $rows);
check('無識別欄位時全部保留', 2, count($result['rows']));

// 9. reader 版本（import_chunk.php 用來保留行號）
$wrapped = [
    ['index' => 0, 'data' => ['id' => 'c3', 'name' => '舊', 'updated_at' => '2025-01-01 00:00:00']],
    ['index' => 1, 'data' => ['id' => 'c3', 'name' => '新', 'updated_at' => '2025-09-01 00:00:00']],
    ['index' => 2, 'data' => ['id' => 'c4', 'name' => '另一筆']],
];
$result = fengbroDedupeImportRows('article', $wrapped, static fn($p) => $p['data']);
check('reader 版本筆數', 2, count($result['rows']));
check('reader 版本保留最新內容', '新', $result['rows'][0]['data']['name']);
// 勝出的是第 1 列，錯誤訊息要指向那一列，但位置停在該筆第一次出現的順位
check('reader 版本沿用勝出列的行號', [1, 2], array_column($result['rows'], 'index'));

echo $failures === 0 ? "\n全部通過\n" : "\n有 {$failures} 項失敗\n";
exit($failures === 0 ? 0 : 1);
