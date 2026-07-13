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
