<?php
// 驗證頁面 ETag / 304：寫入請求判斷、資料版本更新、ETag 比對（含 W/ 與 -gzip 後綴）。
define('DB_NAME', 'page_cache_test_' . getmypid());
require __DIR__ . '/../includes/schema_cache.php';
require __DIR__ . '/../includes/page_cache.php';

$fail = 0;
function check(string $label, bool $ok): void
{
    global $fail;
    echo ($ok ? '✓ ' : '✗ ') . $label . "\n";
    if (!$ok) $fail++;
}

check('create / update / delete 視為寫入', fengbroIsWriteAction('create') && fengbroIsWriteAction('update') && fengbroIsWriteAction('delete'));
check('restore / empty_trash / import 視為寫入', fengbroIsWriteAction('restore') && fengbroIsWriteAction('empty_trash') && fengbroIsWriteAction('import'));
check('list / get / export 是唯讀', !fengbroIsWriteAction('list') && !fengbroIsWriteAction('get') && !fengbroIsWriteAction('export') && !fengbroIsWriteAction(''));

$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET = [];
check('POST 一律視為寫入', fengbroRequestIsWrite());
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['action' => 'delete'];
check('GET 帶 delete 視為寫入', fengbroRequestIsWrite());
$_GET = ['page' => 'food'];
check('一般 GET 頁面不是寫入', !fengbroRequestIsWrite());

$v1 = fengbroDataVersion();
check('可以取得資料版本', $v1 !== null || fengbroDataVersionPath() === null);
if ($v1 !== null) {
    check('沒有寫入時版本不變', fengbroDataVersion() === $v1);
    $v2 = fengbroBumpDataVersion();
    check('寫入後版本改變', $v2 !== null && $v2 !== $v1 && fengbroDataVersion() === $v2);
}

$page = 'pages/food.php';
$a = fengbroPageEtag('v1', $page, 'tok', '/index.php?page=food', '2026-09-24');
check('同樣條件 ETag 相同', $a === fengbroPageEtag('v1', $page, 'tok', '/index.php?page=food', '2026-09-24'));
check('資料版本不同 ETag 不同', $a !== fengbroPageEtag('v2', $page, 'tok', '/index.php?page=food', '2026-09-24'));
check('換日 ETag 不同', $a !== fengbroPageEtag('v1', $page, 'tok', '/index.php?page=food', '2026-09-25'));
check('換 session（CSRF token）ETag 不同', $a !== fengbroPageEtag('v1', $page, 'tok2', '/index.php?page=food', '2026-09-24'));
check('不同網址 ETag 不同', $a !== fengbroPageEtag('v1', $page, 'tok', '/index.php?page=bank', '2026-09-24'));

check('ETag 完全相符', fengbroEtagMatches($a, $a));
check('容忍 W/ 前綴', fengbroEtagMatches('W/' . $a, $a));
check('容忍 Apache -gzip 後綴', fengbroEtagMatches(rtrim($a, '"') . '-gzip"', $a));
check('多個候選其中之一相符', fengbroEtagMatches('"other", ' . $a, $a));
check('不相符', !fengbroEtagMatches('"fb-nope"', $a));

$path = fengbroDataVersionPath();
if ($path !== null) @unlink($path);
echo $fail ? "\n{$fail} 項失敗\n" : "\n全部通過\n";
exit($fail ? 1 : 0);
