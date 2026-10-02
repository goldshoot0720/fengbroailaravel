<?php
require_once __DIR__ . '/management_tables.php';
/**
 * 鋒兄 Udemy：一筆代表一門課程與觀看進度。
 * 對齊 fengbroaiappwrite 的 udemy Table（lib/udemyCsv.ts、buildUdemyCourseWritePayload）。
 */

function fengbroUdemyCreateSql(): string
{
    return "CREATE TABLE IF NOT EXISTS udemy (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            instructor VARCHAR(200),
            `language` VARCHAR(200),
            framework VARCHAR(200),
            technology VARCHAR(200),
            watchedLectures INT DEFAULT 0,
            totalLectures INT DEFAULT 0,
            courseUpdatedAt DATE NULL,
            totalHours DECIMAL(8,2) DEFAULT 0,
            completed TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_udemy_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

function fengbroEnsureUdemyTable(?PDO $pdo = null): void
{
    $pdo = $pdo ?: getConnection();
    fengbroEnsureTableSchema($pdo, 'udemy', fengbroUdemyCreateSql(), [
        "instructor VARCHAR(200)",
        "`language` VARCHAR(200)",
        "framework VARCHAR(200)",
        "technology VARCHAR(200)",
        "watchedLectures INT DEFAULT 0",
        "totalLectures INT DEFAULT 0",
        "courseUpdatedAt DATE NULL",
        "totalHours DECIMAL(8,2) DEFAULT 0",
        "completed TINYINT(1) DEFAULT 0",
    ]);
}

/** 程式語言／框架／技術名稱可填多個值，以「,」「、」「，」分隔。 */
function fengbroUdemySplitTags($value): array
{
    $tags = [];
    foreach (preg_split('/[,、，]/u', (string) $value) as $tag) {
        $tag = trim($tag);
        if ($tag !== '') {
            $tags[$tag] = $tag;
        }
    }
    return array_values($tags);
}

/** 堂數：空白算 0，其餘必須是 0 以上的整數。 */
function fengbroUdemyLectureCount($value, string $label): int
{
    if ($value === null || $value === '') {
        return 0;
    }
    if (is_int($value) || (is_float($value) && floor($value) === $value)) {
        if ($value < 0) {
            throw new InvalidArgumentException("{$label}必須是 0 以上的整數");
        }
        return (int) $value;
    }
    $text = trim((string) $value);
    if ($text === '') {
        return 0;
    }
    if (!preg_match('/^\d+$/', $text)) {
        throw new InvalidArgumentException("{$label}必須是 0 以上的整數");
    }
    return (int) $text;
}

/** 課程總時長（小時）：0 以上的數字，取到小數第二位。 */
function fengbroUdemyHours($value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }
    $text = trim((string) $value);
    if ($text === '') {
        return 0.0;
    }
    if (!preg_match('/^\d*(?:\.\d+)?$/', $text) || !is_numeric($text)) {
        throw new InvalidArgumentException('課程總時長必須是 0 以上的數字');
    }
    return round((float) $text, 2);
}

/**
 * 課程上次更新時間：接受 2025-08-01、2025/8/1，或只有年月的 2025/8（補成當月 1 日，
 * 對應 Udemy 的「上次更新 2025/8」）；也接受帶時間的 2025-08-01 00:00:00 / ISO 8601。
 * 空白回傳 null，格式錯誤丟出例外。
 */
function fengbroUdemyCourseDate($value): ?string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return null;
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ]\d{2}:\d{2}/', $text, $m)) {
        $text = $m[1];
    }
    if (!preg_match('/^(\d{4})[-\/.](\d{1,2})(?:[-\/.](\d{1,2}))?$/', $text, $m)) {
        throw new InvalidArgumentException('課程上次更新時間格式不正確（例如 2025-08-01 或 2025/8）');
    }
    $year = (int) $m[1];
    $month = (int) $m[2];
    $day = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 1;
    if (!checkdate($month, $day, $year)) {
        throw new InvalidArgumentException('課程上次更新時間格式不正確（例如 2025-08-01 或 2025/8）');
    }
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

/** 課程已經完整收看：接受 true/false、是/否、1/0、yes/no，空白算否。 */
function fengbroUdemyCompleted($value): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    if (is_int($value)) {
        return $value ? 1 : 0;
    }
    $text = strtolower(trim((string) ($value ?? '')));
    if (in_array($text, ['true', 'yes', '1', '是', '已看完', 'v', '✓'], true)) {
        return 1;
    }
    if (in_array($text, ['', 'false', 'no', '0', '否', '未看完'], true)) {
        return 0;
    }
    throw new InvalidArgumentException('課程已經完整收看需為 true／false（或 是／否）');
}

function fengbroSanitizeUdemyRow(array $input): array
{
    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('請填寫課程名稱');
    }
    if (mb_strlen($name, 'UTF-8') > 200) {
        throw new InvalidArgumentException('課程名稱最多 200 個字元');
    }
    $text = [];
    foreach (['instructor' => '講師名稱', 'language' => '程式語言', 'framework' => '框架', 'technology' => '技術名稱'] as $field => $label) {
        $value = trim((string) ($input[$field] ?? ''));
        if (mb_strlen($value, 'UTF-8') > 200) {
            throw new InvalidArgumentException("{$label}最多 200 個字元");
        }
        $text[$field] = $value;
    }
    $watched = fengbroUdemyLectureCount($input['watchedLectures'] ?? 0, '已觀看堂數');
    $total = fengbroUdemyLectureCount($input['totalLectures'] ?? 0, '課程總堂數');
    if ($total > 0 && $watched > $total) {
        throw new InvalidArgumentException('已觀看堂數不能超過課程總堂數');
    }

    return [
        'name' => $name,
        'instructor' => $text['instructor'],
        'language' => $text['language'],
        'framework' => $text['framework'],
        'technology' => $text['technology'],
        'watchedLectures' => $watched,
        'totalLectures' => $total,
        'courseUpdatedAt' => fengbroUdemyCourseDate($input['courseUpdatedAt'] ?? ''),
        'totalHours' => fengbroUdemyHours($input['totalHours'] ?? 0),
        'completed' => fengbroUdemyCompleted($input['completed'] ?? 0),
    ];
}

/** 已觀看比重（0–100）；完整收看一律算 100%，未填總堂數算 0%。 */
function fengbroUdemyWatchedPercent(array $course): int
{
    if (!empty($course['completed'])) {
        return 100;
    }
    $total = (int) ($course['totalLectures'] ?? 0);
    if ($total <= 0) {
        return 0;
    }
    return (int) min(100, round(((int) ($course['watchedLectures'] ?? 0)) / $total * 100));
}

/** 合計：已觀看／總堂數與比重（已完整收看的課程以總堂數計）。 */
function fengbroUdemySummary(array $courses): array
{
    $watched = 0;
    $total = 0;
    $hours = 0.0;
    $completed = 0;
    foreach ($courses as $course) {
        $courseTotal = (int) ($course['totalLectures'] ?? 0);
        $courseWatched = (int) ($course['watchedLectures'] ?? 0);
        $isCompleted = !empty($course['completed']);
        $total += $courseTotal;
        $watched += $isCompleted ? $courseTotal : ($courseTotal > 0 ? min($courseWatched, $courseTotal) : $courseWatched);
        $hours += (float) ($course['totalHours'] ?? 0);
        $completed += $isCompleted ? 1 : 0;
    }
    return [
        'watched' => $watched,
        'total' => $total,
        'hours' => round($hours, 1),
        'percent' => $total > 0 ? (int) round($watched / $total * 100) : 0,
        'completedCount' => $completed,
    ];
}

/** CSV 匯入以課程名稱對應（忽略大小寫與前後空白）。 */
function fengbroFindUdemyImportId(PDO $pdo, array $data): ?string
{
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT id FROM udemy
         WHERE LOWER(TRIM(name)) = LOWER(?)
         LIMIT 1"
    );
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    return $id ? (string) $id : null;
}
