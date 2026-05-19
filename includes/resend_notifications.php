<?php

function fengbroResendEnsureTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        setting_key VARCHAR(50) NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_setting (user_id, setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS resend_notification_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_key VARCHAR(191) NOT NULL,
        event_type VARCHAR(50) NOT NULL,
        table_name VARCHAR(50) NOT NULL,
        record_id VARCHAR(64) NOT NULL,
        target_date DATE NOT NULL,
        recipient_email VARCHAR(191) NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_resend_event (event_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function fengbroResendGetSetting(PDO $pdo, string $key, string $default = ''): string
{
    fengbroResendEnsureTables($pdo);
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND user_id IS NULL LIMIT 1");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false || $value === null ? $default : (string) $value;
}

function fengbroResendSaveSetting(PDO $pdo, string $key, string $value): void
{
    fengbroResendEnsureTables($pdo);
    $stmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? AND user_id IS NULL LIMIT 1");
    $stmt->execute([$key]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $update = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update->execute([$value, $id]);
        return;
    }

    $insert = $pdo->prepare("INSERT INTO settings (user_id, setting_key, setting_value) VALUES (NULL, ?, ?)");
    $insert->execute([$key, $value]);
}

function fengbroResendApiKey(PDO $pdo): string
{
    $apiKey = trim(fengbroResendGetSetting($pdo, 'RESEND_API_KEY'));
    if ($apiKey !== '') {
        return $apiKey;
    }

    $legacyKey = trim(fengbroResendGetSetting($pdo, 'resend_api_key'));
    if ($legacyKey !== '') {
        return $legacyKey;
    }

    $envKey = getenv('RESEND_API_KEY');
    return is_string($envKey) ? trim($envKey) : '';
}

function fengbroResendDefaultRecipient(PDO $pdo): string
{
    $configured = trim(fengbroResendGetSetting($pdo, 'resend_to_email'));
    if ($configured !== '') {
        return $configured;
    }

    try {
        $email = $pdo->query("SELECT email FROM users WHERE email IS NOT NULL AND email != '' ORDER BY id ASC LIMIT 1")->fetchColumn();
        return $email ? (string) $email : '';
    } catch (Throwable $e) {
        return '';
    }
}

function fengbroResendBuildBaseUrl(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '' || $host === 'localhost') {
        return '';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    return ($https ? 'https://' : 'http://') . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
}

function fengbroResendSendEmail(string $apiKey, string $from, string $to, string $subject, string $html, string $text): array
{
    if ($apiKey === '') {
        return ['success' => false, 'error' => 'RESEND API Key is empty', 'http_code' => 0];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Recipient email is invalid', 'http_code' => 0];
    }
    if ($from === '') {
        $from = 'Fengbro AI <onboarding@resend.dev>';
    }

    $payload = json_encode([
        'from' => $from,
        'to' => [$to],
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'cURL is not available', 'http_code' => 0];
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $success = $httpCode >= 200 && $httpCode < 300;
    return [
        'success' => $success,
        'http_code' => $httpCode,
        'body' => is_string($body) ? $body : '',
        'error' => $success ? '' : ($error ?: (is_string($body) ? $body : 'RESEND request failed')),
    ];
}

function fengbroResendLogExists(PDO $pdo, string $eventKey): bool
{
    $stmt = $pdo->prepare("SELECT id FROM resend_notification_log WHERE event_key = ? LIMIT 1");
    $stmt->execute([$eventKey]);
    return (bool) $stmt->fetchColumn();
}

function fengbroResendMarkSent(PDO $pdo, string $eventKey, string $eventType, string $table, string $recordId, string $targetDate, string $recipient): void
{
    $stmt = $pdo->prepare("INSERT IGNORE INTO resend_notification_log (event_key, event_type, table_name, record_id, target_date, recipient_email) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$eventKey, $eventType, $table, $recordId, $targetDate, $recipient]);
}

function fengbroResendRunDueNotifications(PDO $pdo): array
{
    fengbroResendEnsureTables($pdo);

    $apiKey = fengbroResendApiKey($pdo);
    $recipient = fengbroResendDefaultRecipient($pdo);
    $from = trim(fengbroResendGetSetting($pdo, 'resend_from_email', 'Fengbro AI <onboarding@resend.dev>'));
    if ($apiKey === '') {
        return ['success' => true, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'message' => '請先在瀏覽器開啟「鋒兄設定」並輸入 RESEND_API_KEY', 'details' => []];
    }
    if ($recipient === '') {
        return ['success' => false, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'error' => 'Recipient email not configured', 'details' => []];
    }

    $baseUrl = fengbroResendBuildBaseUrl();
    $rules = [
        [
            'type' => 'subscription_due_1d',
            'table' => 'subscription',
            'dateField' => 'nextdate',
            'days' => 1,
            'label' => '鋒兄訂閱',
            'subject' => '鋒兄訂閱：明天到期提醒',
            'sql' => "SELECT id, name, nextdate AS target_date, site, account, note
                      FROM subscription
                      WHERE `continue` = 1
                        AND nextdate IS NOT NULL
                        AND DATE(nextdate) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                      ORDER BY nextdate ASC, name ASC",
        ],
        [
            'type' => 'food_due_7d',
            'table' => 'food',
            'dateField' => 'todate',
            'days' => 7,
            'label' => '鋒兄食品',
            'subject' => '鋒兄食品：一周後到期提醒',
            'sql' => "SELECT id, name, todate AS target_date, amount, shop
                      FROM food
                      WHERE todate IS NOT NULL
                        AND DATE(todate) = DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                      ORDER BY todate ASC, name ASC",
        ],
    ];

    $sent = 0;
    $failed = 0;
    $skipped = 0;
    $details = [];

    foreach ($rules as $rule) {
        $rows = $pdo->query($rule['sql'])->fetchAll(PDO::FETCH_ASSOC);
        $pending = [];
        foreach ($rows as $row) {
            $targetDate = date('Y-m-d', strtotime((string) $row['target_date']));
            $eventKey = $rule['type'] . ':' . $row['id'] . ':' . $targetDate;
            if (fengbroResendLogExists($pdo, $eventKey)) {
                $skipped++;
                continue;
            }
            $row['_event_key'] = $eventKey;
            $row['_target_date'] = $targetDate;
            $pending[] = $row;
        }

        if (!$pending) {
            continue;
        }

        $lines = [];
        foreach ($pending as $row) {
            $extra = [];
            if (!empty($row['account'])) {
                $extra[] = '帳號：' . $row['account'];
            }
            if (!empty($row['amount'])) {
                $extra[] = '數量：' . $row['amount'];
            }
            if (!empty($row['shop'])) {
                $extra[] = '商店：' . $row['shop'];
            }
            if (!empty($row['note'])) {
                $extra[] = '備註：' . $row['note'];
            }
            $lines[] = [
                'name' => (string) $row['name'],
                'date' => $row['_target_date'],
                'extra' => implode(' / ', $extra),
            ];
        }

        $pageUrl = $baseUrl !== '' ? $baseUrl . '/index.php?page=' . ($rule['table'] === 'food' ? 'food' : 'subscription') : '';
        $htmlItems = '';
        $textItems = '';
        foreach ($lines as $line) {
            $htmlItems .= '<li><strong>' . htmlspecialchars($line['name'], ENT_QUOTES, 'UTF-8') . '</strong> - ' . htmlspecialchars($line['date'], ENT_QUOTES, 'UTF-8');
            if ($line['extra'] !== '') {
                $htmlItems .= '<br><small>' . htmlspecialchars($line['extra'], ENT_QUOTES, 'UTF-8') . '</small>';
            }
            $htmlItems .= '</li>';
            $textItems .= '- ' . $line['name'] . ' - ' . $line['date'] . ($line['extra'] !== '' ? ' (' . $line['extra'] . ')' : '') . "\n";
        }
        $html = '<h2>' . htmlspecialchars($rule['subject'], ENT_QUOTES, 'UTF-8') . '</h2><p>' . htmlspecialchars($rule['label'], ENT_QUOTES, 'UTF-8') . ' 有 ' . count($pending) . ' 筆需要提醒。</p><ul>' . $htmlItems . '</ul>';
        if ($pageUrl !== '') {
            $html .= '<p><a href="' . htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') . '">開啟 Fengbro AI</a></p>';
        }
        $text = $rule['subject'] . "\n\n" . $textItems . ($pageUrl !== '' ? "\n" . $pageUrl : '');

        $result = fengbroResendSendEmail($apiKey, $from, $recipient, $rule['subject'], $html, $text);
        if ($result['success']) {
            foreach ($pending as $row) {
                fengbroResendMarkSent($pdo, $row['_event_key'], $rule['type'], $rule['table'], (string) $row['id'], $row['_target_date'], $recipient);
            }
            $sent += count($pending);
        } else {
            $failed += count($pending);
        }

        $details[] = [
            'type' => $rule['type'],
            'count' => count($pending),
            'success' => $result['success'],
            'http_code' => $result['http_code'] ?? 0,
            'error' => $result['error'] ?? '',
        ];
    }

    return [
        'success' => $failed === 0,
        'sent' => $sent,
        'failed' => $failed,
        'skipped' => $skipped,
        'details' => $details,
    ];
}
