<?php
/**
 * Shared notification domain helpers.
 *
 * Centralizes due-date queries, payload formatting, and push table setup
 * so footer / dashboard / push_send / resend stay consistent.
 */

/**
 * Human-readable remaining days for a target date (relative to today).
 */
function notifDaysText($date): string
{
    $days = (int) round((strtotime((string) $date) - strtotime(date('Y-m-d'))) / 86400);
    if ($days === 0) {
        return '今天到期';
    }
    if ($days === 1) {
        return '明天到期';
    }
    if ($days < 0) {
        return abs($days) . ' 天前已到期';
    }
    return $days . ' 天後到期';
}

/**
 * Active subscriptions due within N days (inclusive of today).
 *
 * @return list<array<string,mixed>>
 */
function notifGetExpiringSubscriptions(PDO $pdo, int $withinDays = 3): array
{
    $withinDays = max(0, $withinDays);
    $stmt = $pdo->prepare(
        "SELECT id, name, nextdate, site, account, note, price, currency
         FROM subscription
         WHERE `continue` = 1
           AND deleted_at IS NULL
           AND nextdate IS NOT NULL
           AND nextdate >= CURDATE()
           AND nextdate <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
         ORDER BY nextdate ASC, name ASC"
    );
    $stmt->execute([$withinDays]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Active subscriptions due on a specific offset day (e.g. 1 = tomorrow only).
 *
 * @return list<array<string,mixed>>
 */
function notifGetSubscriptionsDueInDays(PDO $pdo, int $daysAhead): array
{
    $daysAhead = max(0, $daysAhead);
    $stmt = $pdo->prepare(
        "SELECT id, name, nextdate AS target_date, site, account, note
         FROM subscription
         WHERE `continue` = 1
           AND deleted_at IS NULL
           AND nextdate IS NOT NULL
           AND DATE(nextdate) = DATE_ADD(CURDATE(), INTERVAL ? DAY)
         ORDER BY nextdate ASC, name ASC"
    );
    $stmt->execute([$daysAhead]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Food items expiring within N days (inclusive of today, not yet expired).
 *
 * @return list<array<string,mixed>>
 */
function notifGetExpiringFood(PDO $pdo, int $withinDays = 7): array
{
    $withinDays = max(0, $withinDays);
    $stmt = $pdo->prepare(
        "SELECT id, name, todate, amount, shop
         FROM food
         WHERE todate IS NOT NULL
           AND todate >= CURDATE()
           AND todate <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
         ORDER BY todate ASC, name ASC"
    );
    $stmt->execute([$withinDays]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Food items due on a specific offset day (e.g. 7 = exactly one week later).
 *
 * @return list<array<string,mixed>>
 */
function notifGetFoodDueInDays(PDO $pdo, int $daysAhead): array
{
    $daysAhead = max(0, $daysAhead);
    $stmt = $pdo->prepare(
        "SELECT id, name, todate AS target_date, amount, shop
         FROM food
         WHERE todate IS NOT NULL
           AND DATE(todate) = DATE_ADD(CURDATE(), INTERVAL ? DAY)
         ORDER BY todate ASC, name ASC"
    );
    $stmt->execute([$daysAhead]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Already-expired food items (newest expired first by date).
 *
 * @return list<array<string,mixed>>
 */
function notifGetExpiredFood(PDO $pdo, int $limit = 5): array
{
    $limit = max(1, min(100, (int) $limit));
    // Inline LIMIT: some MySQL PDO setups reject bound LIMIT parameters.
    $stmt = $pdo->query(
        "SELECT id, name, todate, amount, shop
         FROM food
         WHERE todate IS NOT NULL
           AND todate < CURDATE()
         ORDER BY todate ASC
         LIMIT {$limit}"
    );
    return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}

/**
 * Normalize a subscription row for browser / banner UI.
 *
 * @param array<string,mixed> $row
 * @return array{name:string,date:string,date_full:string,daysText:string,id:?string}
 */
function notifFormatSubscriptionAlert(array $row): array
{
    $nextdate = (string) ($row['nextdate'] ?? $row['target_date'] ?? '');
    $ts = $nextdate !== '' ? strtotime($nextdate) : false;
    return [
        'id' => isset($row['id']) ? (string) $row['id'] : null,
        'name' => (string) ($row['name'] ?? ''),
        'date' => $ts ? date('m/d', $ts) : '',
        'date_full' => $ts ? date('Y-m-d', $ts) : '',
        'daysText' => $nextdate !== '' ? notifDaysText($nextdate) : '',
    ];
}

/**
 * Normalize a food row for browser UI.
 *
 * @param array<string,mixed> $row
 * @return array{name:string,date:string,date_full:string,daysText:string,id:?string}
 */
function notifFormatFoodAlert(array $row): array
{
    $todate = (string) ($row['todate'] ?? $row['target_date'] ?? '');
    $ts = $todate !== '' ? strtotime($todate) : false;
    $dateLabel = '';
    if ($ts && function_exists('formatDate')) {
        $dateLabel = formatDate($todate);
    } elseif ($ts) {
        $dateLabel = date('Y-m-d', $ts);
    }
    return [
        'id' => isset($row['id']) ? (string) $row['id'] : null,
        'name' => (string) ($row['name'] ?? ''),
        'date' => $dateLabel,
        'date_full' => $ts ? date('Y-m-d', $ts) : '',
        'daysText' => $todate !== '' ? notifDaysText($todate) : '',
    ];
}

/**
 * Dashboard / browser alert payload (subscriptions + food).
 *
 * @return array{
 *   subscriptions3: list<array<string,mixed>>,
 *   foods7: list<array<string,mixed>>,
 *   expiredFoods: list<array<string,mixed>>
 * }
 */
function notifBuildDashboardAlerts(PDO $pdo): array
{
    // Dashboard Notification bodies use full Y-m-d dates (same as formatDate()).
    $forDashboard = static function (array $alert): array {
        if (!empty($alert['date_full'])) {
            $alert['date'] = $alert['date_full'];
        }
        return $alert;
    };

    return [
        'subscriptions3' => array_map(
            $forDashboard,
            array_map('notifFormatSubscriptionAlert', notifGetExpiringSubscriptions($pdo, 3))
        ),
        'foods7' => array_map(
            $forDashboard,
            array_map('notifFormatFoodAlert', notifGetExpiringFood($pdo, 7))
        ),
        'expiredFoods' => array_map(
            $forDashboard,
            array_map('notifFormatFoodAlert', notifGetExpiredFood($pdo, 5))
        ),
    ];
}

/**
 * 對齊 Appwrite 通知窗口：
 * - 訂閱／試用首購／購物清單／額度非 AI：剩 0~3 天（含當天）
 * - 食品：剩 0~7 天
 * - 額度 AI：一週／一月到期只提醒前一天與當天（剩 0~1 天）
 * 各查詢在表不存在時安全回退空陣列。
 */

/** 試用／首購 eventDate 在 0~N 天內（含當天）。 */
function notifGetExpiringTrialPurchases(PDO $pdo, int $withinDays = 3): array
{
    $withinDays = max(0, $withinDays);
    try {
        $stmt = $pdo->prepare(
            "SELECT id, name, eventDate, account
             FROM trialpurchase
             WHERE eventDate IS NOT NULL
               AND eventDate >= CURDATE()
               AND eventDate <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY eventDate ASC, name ASC"
        );
        $stmt->execute([$withinDays]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * 額度到期項目：
 * - 一般（general）：quotaExpiry 0~3 天
 * - AI：expiryWeek / expiryMonth 只取 0~1 天
 * @return list<array<string,mixed>>
 */
function notifGetExpiringQuotas(PDO $pdo): array
{
    try {
        $rows = $pdo->query(
            "SELECT id, name, serviceType, account, quotaExpiry, expiryWeek, expiryMonth
             FROM quota"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $isAi = fengbroNormalizeQuotaServiceType($row['serviceType'] ?? 'general') === 'ai';
        if ($isAi) {
            foreach (['week' => 'expiryWeek', 'month' => 'expiryMonth'] as $kind => $col) {
                $raw = trim((string) ($row[$col] ?? ''));
                if ($raw === '') {
                    continue;
                }
                $days = notifDayDelta($raw);
                if ($days !== null && $days >= 0 && $days <= 1) {
                    $out[] = [
                        'id' => (string) $row['id'],
                        'name' => (string) ($row['name'] ?? ''),
                        'kind' => $kind,
                        'label' => $kind === 'week' ? '一週到期' : '一月到期',
                        'expiryDate' => $raw,
                        'account' => (string) ($row['account'] ?? ''),
                        'serviceType' => 'ai',
                        'daysLeft' => $days,
                    ];
                }
            }
        } else {
            $raw = trim((string) ($row['quotaExpiry'] ?? ''));
            if ($raw === '') {
                continue;
            }
            $days = notifDayDelta($raw);
            if ($days !== null && $days >= 0 && $days <= 3) {
                $out[] = [
                    'id' => (string) $row['id'],
                    'name' => (string) ($row['name'] ?? ''),
                    'kind' => 'quotaExpiry',
                    'label' => '額度到期',
                    'expiryDate' => $raw,
                    'account' => (string) ($row['account'] ?? ''),
                    'serviceType' => 'general',
                    'daysLeft' => $days,
                ];
            }
        }
    }
    return $out;
}

/** 購物清單 plannedDate 在 0~N 天內（含當天）。 */
function notifGetExpiringShoppingItems(PDO $pdo, int $withinDays = 3): array
{
    $withinDays = max(0, $withinDays);
    try {
        $stmt = $pdo->prepare(
            "SELECT id, name, plannedDate, account
             FROM shoppinglist
             WHERE plannedDate IS NOT NULL
               AND plannedDate >= CURDATE()
               AND plannedDate <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY plannedDate ASC, name ASC"
        );
        $stmt->execute([$withinDays]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** 計算目標日期距今天（台北日曆日）的整天差；格式不符回 null。 */
function notifDayDelta(string $dateStr): ?int
{
    $clean = trim($dateStr);
    if ($clean === '') {
        return null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $clean, $m)) {
        $target = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            $m[1] . '-' . $m[2] . '-' . $m[3],
            new DateTimeZone('Asia/Taipei')
        );
        if (!$target) {
            return null;
        }
    } else {
        $ts = strtotime($clean);
        if ($ts === false) {
            return null;
        }
        $target = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('Asia/Taipei'));
    }
    $today = new DateTimeImmutable('now', new DateTimeZone('Asia/Taipei'));
    $targetDay = $target->setTime(0, 0);
    $todayDay = $today->setTime(0, 0);
    return (int) $todayDay->diff($targetDay)->format('%r%a');
}

/** 格式化試用／首購提醒列。 */
function notifFormatTrialPurchaseAlert(array $row): array
{
    $date = (string) ($row['eventDate'] ?? $row['target_date'] ?? '');
    $ts = $date !== '' ? strtotime($date) : false;
    return [
        'id' => isset($row['id']) ? (string) $row['id'] : null,
        'name' => (string) ($row['name'] ?? ''),
        'date' => $ts ? date('Y-m-d', $ts) : '',
        'date_full' => $ts ? date('Y-m-d', $ts) : '',
        'daysText' => $date !== '' ? notifDaysText($date) : '',
    ];
}

/** 格式化購物清單提醒列。 */
function notifFormatShoppingAlert(array $row): array
{
    $date = (string) ($row['plannedDate'] ?? $row['target_date'] ?? '');
    $ts = $date !== '' ? strtotime($date) : false;
    return [
        'id' => isset($row['id']) ? (string) $row['id'] : null,
        'name' => (string) ($row['name'] ?? ''),
        'date' => $ts ? date('Y-m-d', $ts) : '',
        'date_full' => $ts ? date('Y-m-d', $ts) : '',
        'daysText' => $date !== '' ? notifDaysText($date) : '',
    ];
}

/**
 * 完整儀表 / 每日本機通知用的五模組到期彙整。
 * @return array{
 *   subscriptions: list<array<string,mixed>>,
 *   foods: list<array<string,mixed>>,
 *   expiredFoods: list<array<string,mixed>>,
 *   trialPurchases: list<array<string,mixed>>,
 *   quotas: list<array<string,mixed>>,
 *   shoppingItems: list<array<string,mixed>>
 * }
 */
function notifBuildUnifiedDashboardAlerts(PDO $pdo): array
{
    $forDashboard = static function (array $alert): array {
        if (!empty($alert['date_full'])) {
            $alert['date'] = $alert['date_full'];
        }
        return $alert;
    };

    $quotas = [];
    foreach (notifGetExpiringQuotas($pdo) as $q) {
        $quotas[] = $q;
    }

    return [
        'subscriptions' => array_map(
            $forDashboard,
            array_map('notifFormatSubscriptionAlert', notifGetExpiringSubscriptions($pdo, 3))
        ),
        'foods' => array_map(
            $forDashboard,
            array_map('notifFormatFoodAlert', notifGetExpiringFood($pdo, 7))
        ),
        'expiredFoods' => array_map(
            $forDashboard,
            array_map('notifFormatFoodAlert', notifGetExpiredFood($pdo, 5))
        ),
        'trialPurchases' => array_map(
            $forDashboard,
            array_map('notifFormatTrialPurchaseAlert', notifGetExpiringTrialPurchases($pdo, 3))
        ),
        'quotas' => $quotas,
        'shoppingItems' => array_map(
            $forDashboard,
            array_map('notifFormatShoppingAlert', notifGetExpiringShoppingItems($pdo, 3))
        ),
    ];
}

/**
 * Build Web Push title/body for expiring subscriptions.
 *
 * @param list<array<string,mixed>> $rows
 * @return array{title:string,body:string,count:int}
 */
function notifBuildSubscriptionPushMessage(array $rows): array
{
    $lines = [];
    foreach ($rows as $row) {
        $date = (string) ($row['nextdate'] ?? $row['target_date'] ?? '');
        $name = (string) ($row['name'] ?? '');
        if ($name === '' || $date === '') {
            continue;
        }
        $lines[] = $name . '（' . notifDaysText($date) . '）';
    }

    return [
        'title' => '訂閱到期提醒 — 鋒兄AI',
        'body' => $lines ? implode('、', $lines) : '無到期訂閱',
        'count' => count($lines),
    ];
}

/**
 * Ensure push_subscriptions table exists.
 */
function notifEnsurePushSubscriptionsTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            endpoint TEXT NOT NULL,
            auth VARCHAR(255) NOT NULL,
            p256dh VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_endpoint (endpoint(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/**
 * Read VAPID public key from settings (empty string if missing).
 */
function notifGetVapidPublicKey(PDO $pdo): string
{
    try {
        $stmt = $pdo->query(
            "SELECT setting_value FROM settings
             WHERE setting_key = 'vapid_public_key' AND user_id IS NULL
             LIMIT 1"
        );
        $value = $stmt ? $stmt->fetchColumn() : false;
        return $value ? (string) $value : '';
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Count registered push devices.
 */
function notifCountPushDevices(PDO $pdo): int
{
    try {
        notifEnsurePushSubscriptionsTable($pdo);
        return (int) $pdo->query('SELECT COUNT(*) FROM push_subscriptions')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Load all push subscription rows.
 *
 * @return list<array{endpoint:string,auth:string,p256dh:string}>
 */
function notifGetPushSubscriptions(PDO $pdo): array
{
    notifEnsurePushSubscriptionsTable($pdo);
    $rows = $pdo->query('SELECT endpoint, auth, p256dh FROM push_subscriptions')->fetchAll(PDO::FETCH_ASSOC);
    return $rows ?: [];
}

/**
 * Remove a dead push subscription endpoint (e.g. HTTP 410).
 */
function notifDeletePushSubscription(PDO $pdo, string $endpoint): void
{
    $stmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?');
    $stmt->execute([$endpoint]);
}

/**
 * Build a single self-check row.
 *
 * @return array{id:string,channel:string,label:string,ok:bool,level:string,detail:string}
 */
function notifSelfCheckItem(string $id, string $channel, string $label, bool $ok, string $detail, string $level = ''): array
{
    if ($level === '') {
        $level = $ok ? 'ok' : 'warn';
    }
    return [
        'id' => $id,
        'channel' => $channel,
        'label' => $label,
        'ok' => $ok,
        'level' => $level,
        'detail' => $detail,
    ];
}

/**
 * Notification self-diagnostic (read-only; does not send mail/push).
 *
 * @return array{
 *   success:bool,
 *   overall:string,
 *   checked_at:string,
 *   timezone:string,
 *   summary:array{ok:int,warn:int,fail:int,total:int},
 *   checks:list<array<string,mixed>>,
 *   due:array<string,mixed>,
 *   client_hints:array<string,mixed>
 * }
 */
function notifRunSelfCheck(PDO $pdo): array
{
    $checks = [];
    $root = dirname(__DIR__);

    // ── Runtime ────────────────────────────────────────────────────────────
    $checks[] = notifSelfCheckItem(
        'php_version',
        'runtime',
        'PHP 版本',
        version_compare(PHP_VERSION, '8.0.0', '>='),
        PHP_VERSION,
        version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'fail'
    );
    $checks[] = notifSelfCheckItem(
        'ext_curl',
        'runtime',
        'cURL 擴充',
        function_exists('curl_init'),
        function_exists('curl_init') ? '可用（Resend / Web Push 需要）' : '缺少 cURL',
        function_exists('curl_init') ? 'ok' : 'fail'
    );
    $checks[] = notifSelfCheckItem(
        'ext_openssl',
        'runtime',
        'OpenSSL 擴充',
        extension_loaded('openssl'),
        extension_loaded('openssl') ? '可用（VAPID / 推播加密需要）' : '缺少 OpenSSL',
        extension_loaded('openssl') ? 'ok' : 'fail'
    );
    $checks[] = notifSelfCheckItem(
        'timezone',
        'runtime',
        '時區',
        true,
        date_default_timezone_get() . ' / 今天 ' . date('Y-m-d H:i:s')
    );

    // ── Deployed files ─────────────────────────────────────────────────────
    $files = [
        'file_helpers' => ['includes/notification_helpers.php', '共用 helpers'],
        'file_js' => ['assets/js/notifications.js', '前端通知模組'],
        'file_sw' => ['sw.js', 'Service Worker'],
        'file_push_send' => ['push_send.php', 'Web Push 發送腳本'],
        'file_push_sub' => ['push_subscribe.php', 'Web Push 訂閱 API'],
        'file_resend' => ['includes/resend_notifications.php', 'Resend 郵件模組'],
        'file_resend_cli' => ['resend_notify.php', 'Resend 排程入口'],
        'file_webpush' => ['push/WebPushHelper.php', 'WebPushHelper'],
    ];
    foreach ($files as $id => [$rel, $label]) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $exists = is_file($path);
        $checks[] = notifSelfCheckItem(
            $id,
            'deploy',
            $label,
            $exists,
            $exists ? $rel . ' 存在' : $rel . ' 不存在（尚未部署？）',
            $exists ? 'ok' : 'fail'
        );
    }

    // ── Due-date data ──────────────────────────────────────────────────────
    $due = [
        'subscriptions_3d' => 0,
        'subscriptions_1d' => 0,
        'food_7d_window' => 0,
        'food_7d_exact' => 0,
        'food_expired' => 0,
        'trial_3d' => 0,
        'quota_soon' => 0,
        'shopping_3d' => 0,
        'subscription_samples' => [],
        'food_samples' => [],
    ];
    try {
        $sub3 = notifGetExpiringSubscriptions($pdo, 3);
        $sub1 = notifGetSubscriptionsDueInDays($pdo, 1);
        $foodWin = notifGetExpiringFood($pdo, 7);
        $foodExact = notifGetFoodDueInDays($pdo, 7);
        $foodExpired = notifGetExpiredFood($pdo, 5);
        $trial3 = notifGetExpiringTrialPurchases($pdo, 3);
        $quotaSoon = notifGetExpiringQuotas($pdo);
        $shopping3 = notifGetExpiringShoppingItems($pdo, 3);
        $due['subscriptions_3d'] = count($sub3);
        $due['subscriptions_1d'] = count($sub1);
        $due['food_7d_window'] = count($foodWin);
        $due['food_7d_exact'] = count($foodExact);
        $due['food_expired'] = count($foodExpired);
        $due['trial_3d'] = count($trial3);
        $due['quota_soon'] = count($quotaSoon);
        $due['shopping_3d'] = count($shopping3);
        $due['subscription_samples'] = array_map(static function ($row) {
            return [
                'name' => (string) ($row['name'] ?? ''),
                'nextdate' => (string) ($row['nextdate'] ?? $row['target_date'] ?? ''),
                'daysText' => notifDaysText($row['nextdate'] ?? $row['target_date'] ?? ''),
            ];
        }, array_slice($sub3, 0, 5));
        $due['food_samples'] = array_map(static function ($row) {
            return [
                'name' => (string) ($row['name'] ?? ''),
                'todate' => (string) ($row['todate'] ?? $row['target_date'] ?? ''),
                'daysText' => notifDaysText($row['todate'] ?? $row['target_date'] ?? ''),
            ];
        }, array_slice($foodWin, 0, 5));

        $checks[] = notifSelfCheckItem(
            'db_due_queries',
            'data',
            '到期查詢',
            true,
            sprintf(
                '訂閱3天內 %d / 訂閱明天 %d / 食品7天內 %d / 食品正好7天後 %d / 已過期 %d / 試用3天內 %d / 額度 %d / 購物3天內 %d',
                $due['subscriptions_3d'],
                $due['subscriptions_1d'],
                $due['food_7d_window'],
                $due['food_7d_exact'],
                $due['food_expired'],
                $due['trial_3d'],
                $due['quota_soon'],
                $due['shopping_3d']
            )
        );
        $checks[] = notifSelfCheckItem(
            'banner_candidates',
            'browser',
            '全站橫幅 / 系統通知候選',
            true,
            $due['subscriptions_3d'] > 0
                ? '有 ' . $due['subscriptions_3d'] . ' 筆會觸發全站提醒（需瀏覽器權限）'
                : '目前無 3 天內到期訂閱，全站橫幅不會顯示（正常）',
            'ok'
        );
        $checks[] = notifSelfCheckItem(
            'dashboard_candidates',
            'browser',
            '儀表板提醒候選',
            true,
            sprintf(
                '訂閱 %d、食品7天 %d、過期 %d、試用 %d、額度 %d、購物 %d（需權限 + 每日去重）',
                $due['subscriptions_3d'],
                $due['food_7d_window'],
                $due['food_expired'],
                $due['trial_3d'],
                $due['quota_soon'],
                $due['shopping_3d']
            )
        );
    } catch (Throwable $e) {
        $checks[] = notifSelfCheckItem(
            'db_due_queries',
            'data',
            '到期查詢',
            false,
            '查詢失敗：' . $e->getMessage(),
            'fail'
        );
    }

    // ── Web Push ───────────────────────────────────────────────────────────
    $vapidPublic = notifGetVapidPublicKey($pdo);
    $vapidPrivate = '';
    try {
        $stmt = $pdo->query(
            "SELECT setting_value FROM settings
             WHERE setting_key = 'vapid_private_key' AND user_id IS NULL
             LIMIT 1"
        );
        $vapidPrivate = $stmt ? (string) ($stmt->fetchColumn() ?: '') : '';
    } catch (Throwable $e) {
        $vapidPrivate = '';
    }
    $pushDevices = notifCountPushDevices($pdo);
    $checks[] = notifSelfCheckItem(
        'vapid_public',
        'push',
        'VAPID 公鑰',
        $vapidPublic !== '',
        $vapidPublic !== '' ? '已設定（' . strlen($vapidPublic) . ' chars）' : '未設定，請在設定頁初始化',
        $vapidPublic !== '' ? 'ok' : 'warn'
    );
    $checks[] = notifSelfCheckItem(
        'vapid_private',
        'push',
        'VAPID 私鑰',
        $vapidPrivate !== '',
        $vapidPrivate !== '' ? '已設定' : '未設定',
        $vapidPrivate !== '' ? 'ok' : 'warn'
    );
    $checks[] = notifSelfCheckItem(
        'push_devices',
        'push',
        '推播訂閱裝置',
        $pushDevices > 0,
        $pushDevices . ' 台' . ($pushDevices === 0 ? '（需在瀏覽器允許通知後自動訂閱）' : ''),
        $pushDevices > 0 ? 'ok' : 'warn'
    );
    $pushReady = $vapidPublic !== '' && $vapidPrivate !== '' && $pushDevices > 0;
    $checks[] = notifSelfCheckItem(
        'push_ready',
        'push',
        'Web Push 就緒',
        $pushReady,
        $pushReady
            ? ($due['subscriptions_3d'] > 0
                ? '可立即發送 3 天內訂閱提醒'
                : '通道就緒，但目前無 3 天內到期訂閱可推')
            : '尚缺 VAPID 或訂閱裝置',
        $pushReady ? 'ok' : 'warn'
    );

    // ── Resend email ───────────────────────────────────────────────────────
    if (!function_exists('fengbroResendCredentialSlots')) {
        require_once __DIR__ . '/resend_notifications.php';
    }
    $slots = fengbroResendCredentialSlots($pdo);
    $configuredSlots = array_values(array_filter($slots, static function ($slot) {
        return $slot['api_key'] !== '' && $slot['recipient'] !== '';
    }));
    $slotSummary = [];
    foreach ($slots as $slot) {
        $slotSummary[] = sprintf(
            '#%d key=%s to=%s',
            $slot['slot'],
            $slot['api_key'] !== '' ? '已設' : '空',
            $slot['recipient'] !== '' ? $slot['recipient'] : '空'
        );
    }
    $checks[] = notifSelfCheckItem(
        'resend_credentials',
        'email',
        'Resend 憑證',
        count($configuredSlots) > 0,
        count($configuredSlots) > 0
            ? ('可用 ' . count($configuredSlots) . ' 組：' . implode('；', $slotSummary))
            : ('尚未設定 RESEND_API_KEY / RESEND_TO_EMAIL（' . implode('；', $slotSummary) . '）'),
        count($configuredSlots) > 0 ? 'ok' : 'warn'
    );

    $resendLogOk = true;
    $resendLogDetail = '';
    try {
        if (function_exists('fengbroResendEnsureTables')) {
            fengbroResendEnsureTables($pdo);
        }
        $logCount = (int) $pdo->query('SELECT COUNT(*) FROM resend_notification_log')->fetchColumn();
        $resendLogDetail = 'resend_notification_log 共 ' . $logCount . ' 筆';
    } catch (Throwable $e) {
        $resendLogOk = false;
        $resendLogDetail = 'log 表不可用：' . $e->getMessage();
    }
    $checks[] = notifSelfCheckItem(
        'resend_log',
        'email',
        'Resend 去重 log',
        $resendLogOk,
        $resendLogDetail,
        $resendLogOk ? 'ok' : 'fail'
    );
    $emailDue = $due['subscriptions_1d'] + $due['food_7d_exact'];
    $checks[] = notifSelfCheckItem(
        'resend_due',
        'email',
        'Resend 今日應寄候選',
        true,
        sprintf(
            '訂閱明天 %d + 食品正好7天後 %d = %d 筆（已寄過會 skipped）',
            $due['subscriptions_1d'],
            $due['food_7d_exact'],
            $emailDue
        )
    );

    // ── Secure context hint for browser/push ───────────────────────────────
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $isLocal = $host === 'localhost' || str_starts_with($host, '127.0.0.1');
    $secureOk = $https || $isLocal || $host === '';
    $checks[] = notifSelfCheckItem(
        'secure_context',
        'browser',
        'Secure context（瀏覽器通知 / Push）',
        $secureOk,
        $secureOk
            ? ($https ? 'HTTPS 可用' : ($isLocal ? 'localhost 可用' : 'CLI / 未知 host'))
            : '目前非 HTTPS，部分瀏覽器會封鎖 Notification / Push',
        $secureOk ? 'ok' : 'warn'
    );

    $ok = 0;
    $warn = 0;
    $fail = 0;
    foreach ($checks as $c) {
        if ($c['level'] === 'fail' || (!$c['ok'] && $c['level'] !== 'warn')) {
            $fail++;
        } elseif ($c['level'] === 'warn' || !$c['ok']) {
            $warn++;
        } else {
            $ok++;
        }
    }
    $overall = $fail > 0 ? 'fail' : ($warn > 0 ? 'warn' : 'ok');

    return [
        'success' => $fail === 0,
        'overall' => $overall,
        'checked_at' => date('c'),
        'timezone' => date_default_timezone_get(),
        'summary' => [
            'ok' => $ok,
            'warn' => $warn,
            'fail' => $fail,
            'total' => count($checks),
        ],
        'checks' => $checks,
        'due' => $due,
        'client_hints' => [
            'notifications_js' => 'assets/js/notifications.js',
            'service_worker' => 'sw.js',
            'permission_api' => 'Notification.permission',
            'push_manager' => 'serviceWorker + PushManager',
            'session_dedupe_key' => 'sub_notified_YYYY-MM-DD',
            'dashboard_dedupe_prefix' => 'fengbro.dashboard.notifications.',
        ],
    ];
}
