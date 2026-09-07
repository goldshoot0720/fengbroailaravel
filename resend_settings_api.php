<?php
/**
 * Resend 通知設定 CSV 匯出／匯入 API — 對齊 Appwrite notification-settings CSV。
 *
 *   POST resend_settings_api.php  body JSON:
 *     { "action": "export",         "password": "..." }
 *       → 驗證通知密碼後回傳明文 slots（含 fromEmail）
 *     { "action": "import_merge",   "password": "...", "slots": [ { apiKey, toEmail }, ... ] }
 *       → 驗證密碼後依收件 Email 合併寫回（最多 FENGBRO_RESEND_MAX_SLOTS 組），回傳 added/updated/skipped
 *
 * 未設定通知密碼時回傳錯誤（需先建立密碼才能以 CSV 匯出/匯入金鑰）。
 */
require_once 'includes/functions.php';
require_once 'includes/resend_notifications.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => '請使用 POST'], 405);
}

$raw = file_get_contents('php://input');
$input = $raw ? json_decode($raw, true) : null;
if (!is_array($input)) {
    jsonResponse(['error' => 'JSON 格式錯誤'], 400);
}

$action = (string) ($input['action'] ?? '');
$password = (string) ($input['password'] ?? '');

$pdo = getConnection();
fengbroResendEnsureTables($pdo);

$storedHash = fengbroResendGetSetting($pdo, 'notif_password_hash');
if ($storedHash === '') {
    jsonResponse(['error' => '尚未設定通知密碼。請先在設定頁建立密碼，才能匯出／匯入 Resend 金鑰。'], 400);
}
if (!password_verify($password, $storedHash)) {
    jsonResponse(['error' => '通知密碼不正確'], 401);
}

function resendSettingsReadSlots(PDO $pdo): array
{
    $slots = [];
    for ($slot = 1; $slot <= FENGBRO_RESEND_MAX_SLOTS; $slot++) {
        $apiKey = fengbroResendApiKey($pdo, $slot);
        $toEmail = fengbroResendRecipient($pdo, $slot);
        if ($apiKey !== '' && $toEmail !== '') {
            $slots[] = ['apiKey' => $apiKey, 'toEmail' => $toEmail];
        }
    }
    return $slots;
}

function resendSettingsWriteSlots(PDO $pdo, array $slots): void
{
    // 先清掉既有 RESEND_TO_EMAIL* 與 RESEND_API_KEY*（保留 env fallback 邏輯不受影響）
    for ($slot = 1; $slot <= FENGBRO_RESEND_MAX_SLOTS; $slot++) {
        $suffix = $slot <= 1 ? '' : (string) $slot;
        fengbroResendSaveSetting($pdo, 'RESEND_API_KEY' . $suffix, '');
        fengbroResendSaveSetting($pdo, 'RESEND_TO_EMAIL' . $suffix, '');
    }
    fengbroResendSaveSetting($pdo, 'resend_api_key', '');
    fengbroResendSaveSetting($pdo, 'resend_to_email', '');

    foreach (array_slice($slots, 0, FENGBRO_RESEND_MAX_SLOTS) as $index => $slot) {
        $num = $index + 1;
        $suffix = $num <= 1 ? '' : (string) $num;
        $apiKey = trim((string) ($slot['apiKey'] ?? ''));
        $toEmail = trim((string) ($slot['toEmail'] ?? ''));
        if ($apiKey === '' || $toEmail === '') {
            continue;
        }
        fengbroResendSaveSetting($pdo, 'RESEND_API_KEY' . $suffix, $apiKey);
        fengbroResendSaveSetting($pdo, 'RESEND_TO_EMAIL' . $suffix, $toEmail);
    }
}

if ($action === 'export') {
    jsonResponse([
        'success' => true,
        'slots' => resendSettingsReadSlots($pdo),
        'fromEmail' => fengbroResendGetSetting($pdo, 'resend_from_email'),
    ]);
}

if ($action === 'import_merge') {
    $incoming = $input['slots'] ?? null;
    if (!is_array($incoming)) {
        jsonResponse(['error' => 'slots 格式錯誤'], 400);
    }

    $incomingSlots = [];
    foreach ($incoming as $item) {
        if (!is_array($item)) {
            continue;
        }
        $apiKey = trim((string) ($item['apiKey'] ?? ''));
        $toEmail = trim((string) ($item['toEmail'] ?? ''));
        if ($apiKey === '' || $toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $incomingSlots[] = ['apiKey' => $apiKey, 'toEmail' => $toEmail];
    }

    $current = resendSettingsReadSlots($pdo);
    // 依收件 Email 合併：同 Email 覆蓋、新 Email 補位。
    $byEmail = [];
    $slots = [];
    foreach ($current as $slot) {
        $byEmail[strtolower($slot['toEmail'])] = count($slots);
        $slots[] = $slot;
    }
    $added = 0;
    $updated = 0;
    $skipped = 0;
    foreach ($incomingSlots as $item) {
        $emailKey = strtolower($item['toEmail']);
        if (isset($byEmail[$emailKey])) {
            $slots[$byEmail[$emailKey]] = $item;
            $updated++;
        } elseif (count($slots) < FENGBRO_RESEND_MAX_SLOTS) {
            $byEmail[$emailKey] = count($slots);
            $slots[] = $item;
            $added++;
        } else {
            $skipped++;
        }
    }

    resendSettingsWriteSlots($pdo, $slots);

    // 若 form 有傳 fromEmail 一併更新
    $fromEmail = trim((string) ($input['fromEmail'] ?? ''));
    if ($fromEmail !== '') {
        fengbroResendSaveSetting($pdo, 'resend_from_email', $fromEmail);
    }

    jsonResponse([
        'success' => true,
        'added' => $added,
        'updated' => $updated,
        'skipped' => $skipped,
        'slots' => resendSettingsReadSlots($pdo),
    ]);
}

jsonResponse(['error' => '無效的操作'], 400);
