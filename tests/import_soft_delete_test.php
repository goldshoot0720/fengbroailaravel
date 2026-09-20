<?php
/**
 * 一般 CSV 匯入的垃圾桶復原規則測試：
 *   php tests/import_soft_delete_test.php
 */

$source = file_get_contents(__DIR__ . '/../includes/functions.php');
$start = strpos($source, 'function fengbroImportRestoresSoftDeletedRows');
$end = $start === false ? false : strpos($source, "\n}", $start);
if ($start === false || $end === false) {
    fwrite(STDERR, "找不到垃圾桶匯入規則\n");
    exit(1);
}
eval(substr($source, $start, $end - $start + 2));

$cases = [
    ['訂閱資料表有 deleted_at 時會復原', true, 'subscription', ['id', 'name', 'deleted_at']],
    ['缺少 deleted_at 時不套用', false, 'subscription', ['id', 'name']],
    ['其他資料表不套用', false, 'food', ['id', 'name', 'deleted_at']],
];

$failures = 0;
foreach ($cases as [$label, $expected, $table, $columns]) {
    $actual = fengbroImportRestoresSoftDeletedRows($table, $columns);
    $ok = $actual === $expected;
    $failures += $ok ? 0 : 1;
    echo ($ok ? "✓" : "✗") . " {$label}\n";
}

exit($failures === 0 ? 0 : 1);
