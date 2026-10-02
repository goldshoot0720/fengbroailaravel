<?php
/**
 * 鋒兄 Udemy 欄位正規化測試（不需資料庫）：
 *   php tests/udemy_helpers_test.php
 */

require __DIR__ . '/../includes/udemy_helpers.php';

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

function errorOf(callable $fn): ?string
{
    try {
        $fn();
        return null;
    } catch (InvalidArgumentException $e) {
        return $e->getMessage();
    }
}

// 1. 完整欄位正規化（Appwrite CSV 的 true/false、只有年月的日期）
$row = fengbroSanitizeUdemyRow([
    'name' => '  The Complete JavaScript Course  ',
    'instructor' => 'Jonas Schmedtmann',
    'language' => 'JavaScript, TypeScript',
    'framework' => '',
    'technology' => 'Node.js',
    'watchedLectures' => '120',
    'totalLectures' => '320',
    'courseUpdatedAt' => '2025/8',
    'totalHours' => '69.456',
    'completed' => 'false',
]);
check('課程名稱去除前後空白', 'The Complete JavaScript Course', $row['name']);
check('已觀看堂數轉整數', 120, $row['watchedLectures']);
check('只有年月補成當月 1 日', '2025-08-01', $row['courseUpdatedAt']);
check('總時長取到小數第二位', 69.46, $row['totalHours']);
check('完整收看 false → 0', 0, $row['completed']);

// 2. 日期格式
check('2025-08-01 00:00:00 取日期', '2025-08-01', fengbroUdemyCourseDate('2025-08-01 00:00:00'));
check('ISO 8601 取日期', '2024-12-31', fengbroUdemyCourseDate('2024-12-31T08:30:00.000+00:00'));
check('2025/8/9 補零', '2025-08-09', fengbroUdemyCourseDate('2025/8/9'));
check('空白日期 → null', null, fengbroUdemyCourseDate(''));
check('不存在的日期會報錯', true, errorOf(fn() => fengbroUdemyCourseDate('2025-02-30')) !== null);
check('亂寫日期會報錯', true, errorOf(fn() => fengbroUdemyCourseDate('上個月')) !== null);

// 3. 完整收看布林值
check('是 → 1', 1, fengbroUdemyCompleted('是'));
check('TRUE → 1', 1, fengbroUdemyCompleted('TRUE'));
check('JSON true → 1', 1, fengbroUdemyCompleted(true));
check('空白 → 0', 0, fengbroUdemyCompleted(''));
check('無法辨識的值會報錯', true, errorOf(fn() => fengbroUdemyCompleted('maybe')) !== null);

// 4. 驗證規則
check('缺課程名稱', '請填寫課程名稱', errorOf(fn() => fengbroSanitizeUdemyRow(['name' => ' '])));
check('已觀看堂數超過總堂數', '已觀看堂數不能超過課程總堂數', errorOf(fn() => fengbroSanitizeUdemyRow(['name' => 'A', 'watchedLectures' => 11, 'totalLectures' => 10])));
check('未填總堂數時不限制已觀看堂數', 5, fengbroSanitizeUdemyRow(['name' => 'A', 'watchedLectures' => 5])['watchedLectures']);
check('負數堂數會報錯', true, errorOf(fn() => fengbroSanitizeUdemyRow(['name' => 'A', 'watchedLectures' => '-1'])) !== null);
check('小數堂數會報錯', true, errorOf(fn() => fengbroSanitizeUdemyRow(['name' => 'A', 'totalLectures' => 3.5])) !== null);
check('負數時長會報錯', true, errorOf(fn() => fengbroSanitizeUdemyRow(['name' => 'A', 'totalHours' => '-2'])) !== null);
check('課程名稱超過 200 字會報錯', '課程名稱最多 200 個字元', errorOf(fn() => fengbroSanitizeUdemyRow(['name' => str_repeat('課', 201)])));

// 5. 多值標籤
check('多值以 , 、 ， 分隔並去重', ['React', 'Next.js', 'Vue'], fengbroUdemySplitTags('React、Next.js, Vue，React'));

// 6. 觀看比重與合計
check('完整收看算 100%', 100, fengbroUdemyWatchedPercent(['watchedLectures' => 1, 'totalLectures' => 10, 'completed' => 1]));
check('未填總堂數算 0%', 0, fengbroUdemyWatchedPercent(['watchedLectures' => 3, 'totalLectures' => 0, 'completed' => 0]));
check('一般比重四捨五入', 33, fengbroUdemyWatchedPercent(['watchedLectures' => 1, 'totalLectures' => 3, 'completed' => 0]));
$summary = fengbroUdemySummary([
    ['watchedLectures' => 5, 'totalLectures' => 10, 'completed' => 0, 'totalHours' => 2.5],
    ['watchedLectures' => 2, 'totalLectures' => 10, 'completed' => 1, 'totalHours' => 3],
]);
check('合計已觀看（完整收看以總堂數計）', 15, $summary['watched']);
check('合計總堂數', 20, $summary['total']);
check('合計比重', 75, $summary['percent']);
check('已完整收看門數', 1, $summary['completedCount']);
check('合計時長', 5.5, $summary['hours']);

exit($failures === 0 ? 0 : 1);
