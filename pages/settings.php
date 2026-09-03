<?php
$pageTitle = '系統設定';
$pdo = getConnection();
require_once __DIR__ . '/../includes/resend_notifications.php';
fengbroResendEnsureTables($pdo);

$resendSettingsMessage = '';
$resendSettingsError = '';
$biggoSettingsMessage = '';
$biggoSettingsError = '';

// ── 通知設定密碼（對齊 Appwrite notification-settings）───────────────────────
$notifPasswordHash = fengbroResendGetSetting($pdo, 'notif_password_hash');
$notifPasswordSet = $notifPasswordHash !== '';
$notifPasswordAction = (string) ($_POST['notif_password_action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $notifPasswordAction !== '') {
    $enteredPassword = (string) ($_POST['notif_password'] ?? '');
    $enteredNewPassword = (string) ($_POST['notif_password_new'] ?? '');
    if ($notifPasswordAction === 'set' && !$notifPasswordSet) {
        if (strlen($enteredNewPassword) < 4) {
            $resendSettingsError = '通知密碼至少 4 碼。';
        } else {
            fengbroResendSaveSetting($pdo, 'notif_password_hash', password_hash($enteredNewPassword, PASSWORD_DEFAULT));
            $notifPasswordSet = true;
            $resendSettingsMessage = '通知密碼已建立。之後顯示或變更 RESEND／BigGo 金鑰前都需驗證此密碼。';
        }
    } elseif ($notifPasswordAction === 'change' && $notifPasswordSet) {
        if (!password_verify($enteredPassword, $notifPasswordHash)) {
            $resendSettingsError = '目前的通知密碼不正確。';
        } elseif (strlen($enteredNewPassword) < 4) {
            $resendSettingsError = '新密碼至少 4 碼。';
        } else {
            fengbroResendSaveSetting($pdo, 'notif_password_hash', password_hash($enteredNewPassword, PASSWORD_DEFAULT));
            $resendSettingsMessage = '通知密碼已更新。';
        }
    } elseif ($notifPasswordAction === 'clear' && $notifPasswordSet) {
        if (!password_verify($enteredPassword, $notifPasswordHash)) {
            $resendSettingsError = '目前的通知密碼不正確，無法移除密碼保護。';
        } else {
            fengbroResendSaveSetting($pdo, 'notif_password_hash', '');
            $notifPasswordSet = false;
            $resendSettingsMessage = '通知密碼已移除。';
        }
    }
}

// 已設定通知密碼時，儲存金鑰類設定（RESEND / BigGo）需先驗證密碼
$resendGatePassed = true;
$requestedKeyAction = in_array((string) ($_POST['settings_action'] ?? ''), ['save_resend', 'save_biggo'], true)
    && $_SERVER['REQUEST_METHOD'] === 'POST';
if ($requestedKeyAction) {
    if (!$notifPasswordSet) {
        $resendGatePassed = false;
        $resendSettingsError = '尚未設定通知密碼。請先在上方「通知設定密碼」建立至少 4 碼密碼，才能儲存 API 金鑰。';
    } elseif (!password_verify((string) ($_POST['notif_password'] ?? ''), $notifPasswordHash)) {
        $resendGatePassed = false;
        $resendSettingsError = '通知密碼不正確，無法儲存金鑰。';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resendGatePassed && ($_POST['settings_action'] ?? '') === 'save_resend') {
    try {
        for ($slot = 1; $slot <= 3; $slot++) {
            $suffix = $slot <= 1 ? '' : (string) $slot;
            $apiSetting = 'RESEND_API_KEY' . $suffix;
            $toSetting = 'RESEND_TO_EMAIL' . $suffix;
            if (!empty($_POST['clear_resend_api_key' . $suffix])) {
                fengbroResendSaveSetting($pdo, $apiSetting, '');
                if ($slot === 1) {
                    fengbroResendSaveSetting($pdo, 'resend_api_key', '');
                }
            } else {
                $newKey = trim((string) ($_POST['resend_api_key' . $suffix] ?? ''));
                if ($newKey !== '') {
                    fengbroResendSaveSetting($pdo, $apiSetting, $newKey);
                    if ($slot === 1) {
                        fengbroResendSaveSetting($pdo, 'resend_api_key', '');
                    }
                }
            }
            fengbroResendSaveSetting($pdo, $toSetting, trim((string) ($_POST['resend_to_email' . $suffix] ?? '')));
        }
        fengbroResendSaveSetting($pdo, 'resend_to_email', '');
        fengbroResendSaveSetting($pdo, 'resend_from_email', trim((string) ($_POST['resend_from_email'] ?? 'Fengbro AI <onboarding@resend.dev>')));
        $resendSettingsMessage = 'RESEND 設定已儲存。RESEND_API_KEY 會從瀏覽器設定頁讀取。';
    } catch (Throwable $e) {
        $resendSettingsError = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resendGatePassed && ($_POST['settings_action'] ?? '') === 'save_biggo') {
    try {
        $biggoKeys = [
            'BIGGO_API_KEY',
            'BIGGO_API_SECRET_KEY',
            'BIGGO_API_ENDPOINT',
            'BIGGO_API_REGION',
            'BIGGO_MCP_SERVER_CLIENT_ID',
            'BIGGO_MCP_SERVER_CLIENT_SECRET',
            'BIGGO_MCP_SERVER_REGION',
        ];
        foreach ($biggoKeys as $biggoKey) {
            $field = strtolower($biggoKey);
            $value = trim((string) ($_POST[$field] ?? ''));
            if ($value !== '' || !empty($_POST['clear_' . $field])) {
                fengbroResendSaveSetting($pdo, $biggoKey, $value);
            }
        }
        $biggoSettingsMessage = 'BigGo API / MCP 設定已儲存。';
    } catch (Throwable $e) {
        $biggoSettingsError = $e->getMessage();
    }
}

$resendApiKeySet = fengbroResendApiKey($pdo) !== '';
$resendToEmail = fengbroResendDefaultRecipient($pdo);
$resendSlots = fengbroResendCredentialSlots($pdo);
$resendFromEmail = fengbroResendGetSetting($pdo, 'resend_from_email', 'Fengbro AI <onboarding@resend.dev>');
$resendScriptPath = str_replace('\\', '/', __DIR__ . '/../resend_notify.php');
$biggoSettings = [
    'BIGGO_API_KEY' => fengbroResendGetSetting($pdo, 'BIGGO_API_KEY'),
    'BIGGO_API_SECRET_KEY' => fengbroResendGetSetting($pdo, 'BIGGO_API_SECRET_KEY'),
    'BIGGO_API_ENDPOINT' => fengbroResendGetSetting($pdo, 'BIGGO_API_ENDPOINT', 'https://api.biggo.com.tw/v1/search'),
    'BIGGO_API_REGION' => fengbroResendGetSetting($pdo, 'BIGGO_API_REGION', 'tw'),
    'BIGGO_MCP_SERVER_CLIENT_ID' => fengbroResendGetSetting($pdo, 'BIGGO_MCP_SERVER_CLIENT_ID'),
    'BIGGO_MCP_SERVER_CLIENT_SECRET' => fengbroResendGetSetting($pdo, 'BIGGO_MCP_SERVER_CLIENT_SECRET'),
    'BIGGO_MCP_SERVER_REGION' => fengbroResendGetSetting($pdo, 'BIGGO_MCP_SERVER_REGION', 'tw'),
];
?>

<div class="content-header">
    <h1>鋒兄設定</h1>
</div>

<div class="content-body">
    <div class="card">
        <h3 class="card-title">資料庫設定</h3>
        <table class="table">
            <tr>
                <th style="width: 200px;">目前環境</th>
                <td><span
                        class="badge <?php echo $GLOBALS['ENV'] === 'remote' ? 'badge-danger' : 'badge-success'; ?>"><?php echo strtoupper($GLOBALS['ENV']); ?></span>
                </td>
            </tr>
            <tr>
                <th>資料庫主機</th>
                <td><span class="sensitive-mask">已設定</span></td>
            </tr>
            <tr>
                <th>資料庫名稱</th>
                <td><span class="sensitive-mask">已隱藏</span></td>
            </tr>
            <tr>
                <th>資料庫使用者</th>
                <td><span class="sensitive-mask">已隱藏</span></td>
            </tr>
        </table>
        <div style="margin-top: 12px;">
            <a href="install.php" target="_blank" class="btn btn-warning">
                <i class="fa-solid fa-database"></i> 執行資料庫安裝／升級
            </a>
            <span style="font-size: 0.82em; color: #888; margin-left: 10px;">建立資料表 或 補齊新欄位</span>
        </div>
        <div style="margin-top: 10px;">
            <button type="button" class="btn btn-sm btn-primary" onclick="initSiteStatTables()">
                <i class="fa-solid fa-chart-column"></i> 初始化進站／選單統計表（sitevisit、menuusage）
            </button>
            <span id="siteStatInitResult" style="margin-left: 10px; font-size: 0.85em; color: var(--muted-text);"></span>
        </div>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">系統資訊</h3>
        <table class="table">
            <tr>
                <th style="width: 200px;">PHP 版本</th>
                <td><?php echo htmlspecialchars(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION); ?> 系列</td>
            </tr>
            <tr>
                <th>伺服器軟體</th>
                <td>已啟用安全網頁伺服器</td>
            </tr>
            <tr>
                <th>伺服器時間</th>
                <td><?php echo date('Y-m-d H:i:s'); ?></td>
            </tr>
        </table>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">通知設定密碼</h3>
        <p style="color: var(--muted-text); font-size: 0.9rem; margin-bottom: 12px;">
            對齊 Appwrite notification-settings：設定後，儲存或顯示 RESEND／BigGo API 金鑰都需先驗證此密碼。
            Cron 自動寄信／推播不受此密碼阻擋。
        </p>
        <form method="post" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <input type="hidden" name="notif_password_action" value="<?php echo $notifPasswordSet ? 'change' : 'set'; ?>">
            <?php if ($notifPasswordSet): ?>
                <label style="display:grid;gap:6px;font-weight:600;">目前密碼
                    <input class="form-control" type="password" name="notif_password" autocomplete="off" placeholder="輸入目前通知密碼">
                </label>
            <?php endif; ?>
            <label style="display:grid;gap:6px;font-weight:600;"><?php echo $notifPasswordSet ? '新密碼' : '建立密碼（至少 4 碼）'; ?>
                <input class="form-control" type="password" name="notif_password_new" autocomplete="new-password" minlength="4" required placeholder="至少 4 碼">
            </label>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-lock"></i> <?php echo $notifPasswordSet ? '更新密碼' : '建立通知密碼'; ?>
            </button>
            <?php if ($notifPasswordSet): ?>
                <button type="submit" class="btn btn-danger" name="notif_password_action" value="clear"
                    onclick="return confirm('確定移除通知密碼保護？需要輸入目前密碼。')">
                    <i class="fa-solid fa-unlock"></i> 移除密碼
                </button>
            <?php endif; ?>
        </form>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">RESEND Email 通知</h3>
        <?php if ($resendSettingsMessage): ?>
            <div class="alert alert-success" style="margin-bottom:12px;"><?php echo htmlspecialchars($resendSettingsMessage); ?></div>
        <?php endif; ?>
        <?php if ($resendSettingsError): ?>
            <div class="alert alert-danger" style="margin-bottom:12px;"><?php echo htmlspecialchars($resendSettingsError); ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="settings_action" value="save_resend">
            <?php if ($notifPasswordSet): ?>
                <div style="margin-bottom:12px;">
                    <label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;color:var(--muted-text);">
                        <i class="fa-solid fa-lock"></i> 通知密碼
                        <input class="form-control" type="password" name="notif_password" autocomplete="off" placeholder="輸入通知密碼後才能儲存金鑰" style="width:240px;" required>
                    </label>
                </div>
            <?php endif; ?>
            <table class="table">
                <tr>
                    <th style="width: 200px;"><code>RESEND_API_KEY</code></th>
                    <td>
                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <input id="resendApiKeyInput" type="password" class="form-control" name="resend_api_key" data-field="resend_api_key" placeholder="<?php echo $resendApiKeySet ? '已設定，留空保留既有 RESEND_API_KEY' : '請在瀏覽器輸入 re_...'; ?>" autocomplete="off" autocapitalize="off" spellcheck="false" <?php echo $resendApiKeySet ? '' : 'required'; ?> style="flex:1 1 320px;">
                            <button type="button" class="btn btn-sm" onclick="toggleResendApiKeyVisibility()">顯示/隱藏</button>
                        </div>
                        <div style="font-size:0.82em; color:var(--muted-text); margin-top:6px;">請由使用者在瀏覽器輸入 RESEND_API_KEY 後按「儲存 RESEND 設定」。此欄位不會顯示既有 Key；留空會保留目前設定。</div>
                        <div style="margin-top:8px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                            <?php if ($resendApiKeySet): ?>
                                <span class="badge badge-success">RESEND_API_KEY 已設定</span>
                                <label style="display:flex; gap:6px; align-items:center; color:var(--muted-text);">
                                    <input type="checkbox" name="clear_resend_api_key" value="1"> 清除 API Key
                                </label>
                            <?php else: ?>
                                <span class="badge badge-danger">RESEND_API_KEY 未設定，請先在瀏覽器輸入</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php for ($resendSlot = 2; $resendSlot <= 3; $resendSlot++): ?>
                    <?php $resendSuffix = (string) $resendSlot; $resendSlotData = $resendSlots[$resendSlot - 1]; ?>
                    <tr>
                        <th style="width: 200px;"><code>RESEND_API_KEY<?php echo $resendSuffix; ?></code></th>
                        <td>
                            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                <input id="resendApiKeyInput<?php echo $resendSuffix; ?>" type="password" class="form-control" name="resend_api_key<?php echo $resendSuffix; ?>" placeholder="<?php echo $resendSlotData['api_key'] !== '' ? '已設定，留空保留既有 Key' : 're_...'; ?>" autocomplete="off" autocapitalize="off" spellcheck="false" style="flex:1 1 320px;">
                                <button type="button" class="btn btn-sm" onclick="toggleResendApiKeyVisibility('resendApiKeyInput<?php echo $resendSuffix; ?>')">顯示/隱藏</button>
                            </div>
                            <div style="margin-top:8px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                                <?php if ($resendSlotData['api_key'] !== ''): ?>
                                    <span class="badge badge-success">RESEND_API_KEY<?php echo $resendSuffix; ?> 已設定</span>
                                    <label style="display:flex; gap:6px; align-items:center; color:var(--muted-text);">
                                        <input type="checkbox" name="clear_resend_api_key<?php echo $resendSuffix; ?>" value="1"> 清除 API Key
                                    </label>
                                <?php else: ?>
                                    <span class="badge badge-danger">RESEND_API_KEY<?php echo $resendSuffix; ?> 未設定</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endfor; ?>
                <tr>
                    <th><code>RESEND_TO_EMAIL</code></th>
                    <td>
                        <input type="email" class="form-control" name="resend_to_email" value="<?php echo htmlspecialchars($resendToEmail); ?>" placeholder="預設使用 users 第一筆 email">
                    </td>
                </tr>
                <?php for ($resendSlot = 2; $resendSlot <= 3; $resendSlot++): ?>
                    <?php $resendSuffix = (string) $resendSlot; $resendSlotData = $resendSlots[$resendSlot - 1]; ?>
                    <tr>
                        <th><code>RESEND_TO_EMAIL<?php echo $resendSuffix; ?></code></th>
                        <td>
                            <input type="email" class="form-control" name="resend_to_email<?php echo $resendSuffix; ?>" value="<?php echo htmlspecialchars($resendSlotData['recipient']); ?>" placeholder="you<?php echo $resendSuffix; ?>@example.com">
                        </td>
                    </tr>
                <?php endfor; ?>
                <tr>
                    <th>寄件 Email</th>
                    <td>
                        <input type="text" class="form-control" name="resend_from_email" value="<?php echo htmlspecialchars($resendFromEmail); ?>" placeholder="Fengbro AI <onboarding@resend.dev>">
                        <div style="font-size:0.82em; color:var(--muted-text); margin-top:4px;">正式寄送建議改成 Resend 已驗證網域，例如 Fengbro AI &lt;notify@example.com&gt;。</div>
                    </td>
                </tr>
                <tr>
                    <th>通知規則</th>
                    <td>
                        <div>鋒兄訂閱：到期前一天通知一次。</div>
                        <div>鋒兄食品：到期前一周通知一次。</div>
                        <div style="font-size:0.82em; color:var(--muted-text); margin-top:4px;">成功寄出後會寫入 resend_notification_log，避免同一筆項目重複寄送。</div>
                    </td>
                </tr>
                <tr>
                    <th>Cron 指令</th>
                    <td>
                        <code style="background:#f4f4f4; padding:6px 10px; border-radius:4px; display:inline-block; font-size:0.85em;">
                            CRON_TZ=Asia/Taipei<br>
                            0 9 * * * php /path/to/app/resend_notify.php
                        </code>
                    </td>
                </tr>
                <tr>
                    <th>手動檢查</th>
                    <td>
                        <button type="button" class="btn btn-sm btn-warning" onclick="runResendNotify()" <?php echo !$resendApiKeySet ? 'disabled' : ''; ?>>
                            執行 RESEND 通知
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="sendResendTestEmail()" <?php echo !$resendApiKeySet ? 'disabled' : ''; ?>>
                            測試寄發
                        </button>
                        <span id="resendNotifyResult" style="margin-left:12px; font-size:0.9em;"></span>
                    </td>
                </tr>
            </table>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> 儲存 RESEND 設定
            </button>
        </form>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">BigGo API / MCP 比價設定</h3>
        <?php if ($biggoSettingsMessage): ?>
            <div class="alert alert-success" style="margin-bottom:12px;"><?php echo htmlspecialchars($biggoSettingsMessage); ?></div>
        <?php endif; ?>
        <?php if ($biggoSettingsError): ?>
            <div class="alert alert-danger" style="margin-bottom:12px;"><?php echo htmlspecialchars($biggoSettingsError); ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="settings_action" value="save_biggo">
            <?php if ($notifPasswordSet): ?>
                <div style="margin-bottom:12px;">
                    <label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;color:var(--muted-text);">
                        <i class="fa-solid fa-lock"></i> 通知密碼
                        <input class="form-control" type="password" name="notif_password" autocomplete="off" placeholder="輸入通知密碼後才能儲存金鑰" style="width:240px;" required>
                    </label>
                </div>
            <?php endif; ?>
            <table class="table">
                <tr>
                    <th style="width: 240px;"><code>BIGGO_API_KEY</code></th>
                    <td>
                        <input type="password" class="form-control" name="biggo_api_key" placeholder="<?php echo $biggoSettings['BIGGO_API_KEY'] !== '' ? '已設定，留空保留原值' : 'BigGo API Key 或 Token'; ?>" autocomplete="off">
                        <?php if ($biggoSettings['BIGGO_API_KEY'] !== ''): ?>
                            <label style="display:flex; gap:6px; align-items:center; margin-top:8px; color:var(--muted-text);">
                                <input type="checkbox" name="clear_biggo_api_key" value="1"> 清除 BIGGO_API_KEY
                            </label>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><code>BIGGO_API_SECRET_KEY</code></th>
                    <td>
                        <input type="password" class="form-control" name="biggo_api_secret_key" placeholder="<?php echo $biggoSettings['BIGGO_API_SECRET_KEY'] !== '' ? '已設定，留空保留原值' : 'BigGo API Secret（若有）'; ?>" autocomplete="off">
                        <?php if ($biggoSettings['BIGGO_API_SECRET_KEY'] !== ''): ?>
                            <label style="display:flex; gap:6px; align-items:center; margin-top:8px; color:var(--muted-text);">
                                <input type="checkbox" name="clear_biggo_api_secret_key" value="1"> 清除 BIGGO_API_SECRET_KEY
                            </label>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><code>BIGGO_API_ENDPOINT</code></th>
                    <td>
                        <input type="url" class="form-control" name="biggo_api_endpoint" value="<?php echo htmlspecialchars($biggoSettings['BIGGO_API_ENDPOINT']); ?>" placeholder="https://api.biggo.com.tw/v1/search">
                        <div style="font-size:0.82em; color:var(--muted-text); margin-top:4px;">可用 <code>{query}</code> 作為關鍵字位置；未填時使用 q= 關鍵字參數。</div>
                    </td>
                </tr>
                <tr>
                    <th><code>BIGGO_API_REGION</code></th>
                    <td><input type="text" class="form-control" name="biggo_api_region" value="<?php echo htmlspecialchars($biggoSettings['BIGGO_API_REGION']); ?>" placeholder="tw"></td>
                </tr>
                <tr>
                    <th><code>BIGGO_MCP_SERVER_CLIENT_ID</code></th>
                    <td>
                        <input type="password" class="form-control" name="biggo_mcp_server_client_id" placeholder="<?php echo $biggoSettings['BIGGO_MCP_SERVER_CLIENT_ID'] !== '' ? '已設定，留空保留原值' : 'BigGo MCP Server Client ID'; ?>" autocomplete="off">
                        <?php if ($biggoSettings['BIGGO_MCP_SERVER_CLIENT_ID'] !== ''): ?>
                            <label style="display:flex; gap:6px; align-items:center; margin-top:8px; color:var(--muted-text);">
                                <input type="checkbox" name="clear_biggo_mcp_server_client_id" value="1"> 清除 BIGGO_MCP_SERVER_CLIENT_ID
                            </label>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><code>BIGGO_MCP_SERVER_CLIENT_SECRET</code></th>
                    <td>
                        <input type="password" class="form-control" name="biggo_mcp_server_client_secret" placeholder="<?php echo $biggoSettings['BIGGO_MCP_SERVER_CLIENT_SECRET'] !== '' ? '已設定，留空保留原值' : 'BigGo MCP Server Client Secret'; ?>" autocomplete="off">
                        <?php if ($biggoSettings['BIGGO_MCP_SERVER_CLIENT_SECRET'] !== ''): ?>
                            <label style="display:flex; gap:6px; align-items:center; margin-top:8px; color:var(--muted-text);">
                                <input type="checkbox" name="clear_biggo_mcp_server_client_secret" value="1"> 清除 BIGGO_MCP_SERVER_CLIENT_SECRET
                            </label>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><code>BIGGO_MCP_SERVER_REGION</code></th>
                    <td><input type="text" class="form-control" name="biggo_mcp_server_region" value="<?php echo htmlspecialchars($biggoSettings['BIGGO_MCP_SERVER_REGION']); ?>" placeholder="tw"></td>
                </tr>
            </table>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> 儲存 BigGo 設定
            </button>
        </form>
    </div>

    <?php
    // ── 推播通知管理卡片資料 ──────────────────────────────────────────────────
    require_once __DIR__ . '/../includes/notification_helpers.php';
    require_once __DIR__ . '/../push/WebPushHelper.php';
    $vapidPublicKeySet = WebPushHelper::getVapidPublicKey() !== '';
    $pushDeviceCount = notifCountPushDevices($pdo);
    $scriptPath = str_replace('\\', '/', __DIR__ . '/../push_send.php');
    ?>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">推播通知管理（Web Push）</h3>
        <table class="table">
            <tr>
                <th style="width: 200px;">VAPID 金鑰</th>
                <td>
                    <?php if ($vapidPublicKeySet): ?>
                        <span class="badge badge-success">已設定 ✓</span>
                    <?php else: ?>
                        <span class="badge badge-danger">未設定 ✗</span>
                        <button onclick="initVapid()" class="btn btn-sm btn-primary"
                            style="margin-left:12px;">初始化金鑰</button>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>已訂閱裝置數</th>
                <td><strong id="pushDeviceCount"><?php echo (int) $pushDeviceCount; ?></strong> 台</td>
            </tr>
            <tr>
                <th>立即發送</th>
                <td>
                    <button onclick="sendPush()" class="btn btn-sm btn-warning" <?php echo !$vapidPublicKeySet ? 'disabled' : ''; ?>>
                        立即發送到期提醒
                    </button>
                    <span id="pushSendResult" style="margin-left:12px; font-size:0.9em;"></span>
                </td>
            </tr>
            <tr>
                <th>Cron 排程</th>
                <td>
                    <code
                        style="background:#f4f4f4; padding:6px 10px; border-radius:4px; display:inline-block; font-size:0.85em;">
                        CRON_TZ=Asia/Taipei<br>
                        0 9 * * * php /path/to/app/push_send.php
                    </code>
                    <div style="font-size:0.8em; color:#888; margin-top:4px;">每天台灣時間上午 09:00 自動發送 3 天內到期訂閱提醒</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">通知自我檢測</h3>
        <p style="color:var(--muted-text); margin:0 0 12px;">
            只讀檢查：檔案是否部署、VAPID / Resend 設定、到期候選筆數、推播裝置。不會寄信或發推播。
        </p>
        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:12px;">
            <button type="button" class="btn btn-sm btn-primary" onclick="runNotificationSelfCheck()">
                <i class="fa-solid fa-stethoscope"></i> 執行自我檢測
            </button>
            <button type="button" class="btn btn-sm btn-ghost" onclick="runClientNotificationSelfCheck()">
                <i class="fa-solid fa-desktop"></i> 瀏覽器端檢測
            </button>
            <span id="notifSelfCheckSummary" style="font-size:0.9em;"></span>
        </div>
        <pre id="notifSelfCheckResult" style="display:none; background:#0f172a; color:#e2e8f0; padding:12px 14px; border-radius:8px; overflow:auto; max-height:420px; font-size:0.82rem; line-height:1.45; white-space:pre-wrap;"></pre>
        <div id="notifSelfCheckTableWrap" style="display:none; overflow:auto;">
            <table class="table" id="notifSelfCheckTable">
                <thead>
                    <tr>
                        <th style="width:90px;">狀態</th>
                        <th style="width:100px;">通道</th>
                        <th style="width:180px;">項目</th>
                        <th>說明</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleResendApiKeyVisibility(id) {
            const input = document.getElementById(id || 'resendApiKeyInput');
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            input.focus();
        }

        function runResendNotify() {
            const result = document.getElementById('resendNotifyResult');
            result.textContent = '檢查中...';
            fetch('resend_notify.php', { method: 'POST' })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        result.innerHTML = '<span style="color:green;">已寄出 ' + (d.sent || 0) + ' 筆，略過 ' + (d.skipped || 0) + ' 筆。</span>';
                    } else {
                        result.innerHTML = '<span style="color:red;">失敗：' + (d.error || 'RESEND 通知失敗') + '</span>';
                    }
                })
                .catch(() => {
                    result.innerHTML = '<span style="color:red;">請求失敗</span>';
                });
        }

        function sendResendTestEmail() {
            const result = document.getElementById('resendNotifyResult');
            result.textContent = '測試寄發中...';
            fetch('resend_notify.php?action=test', { method: 'POST' })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        result.innerHTML = '<span style="color:green;">測試信已寄出 ' + (d.sent || 0) + ' 封' + (d.recipient ? '：' + d.recipient : '') + '。</span>';
                    } else {
                        result.innerHTML = '<span style="color:red;">測試寄發失敗：' + (d.error || 'RESEND 測試寄發失敗') + '</span>';
                    }
                })
                .catch(() => {
                    result.innerHTML = '<span style="color:red;">測試寄發請求失敗</span>';
                });
        }

        function initVapid() {
            if (!confirm('確定要產生 VAPID 金鑰？這將覆蓋現有金鑰（若有），已訂閱裝置需重新訂閱。')) return;
            fetch('push_send.php?action=init_vapid&force=1')
                .then(r => r.json())
                .then(d => {
                    alert(d.success ? '金鑰已產生，請重新整理頁面。' : ('錯誤：' + d.error));
                    if (d.success) location.reload();
                })
                .catch(() => alert('請求失敗'));
        }

        function sendPush() {
            var btn = event.target;
            btn.disabled = true;
            btn.textContent = '發送中…';
            document.getElementById('pushSendResult').textContent = '';

            fetch('push_send.php', { method: 'POST' })
                .then(r => r.json())
                .then(d => {
                    btn.disabled = false;
                    btn.textContent = '立即發送到期提醒';
                    if (d.success) {
                        document.getElementById('pushSendResult').innerHTML =
                            '<span style="color:green;">發送 ' + d.sent + ' 則，失敗 ' + d.failed + ' 則</span>' +
                            (d.message ? '（' + d.message + '）' : '');
                    } else {
                        document.getElementById('pushSendResult').innerHTML =
                            '<span style="color:red;">錯誤：' + (d.error || '未知') + '</span>';
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.textContent = '立即發送到期提醒';
                    document.getElementById('pushSendResult').innerHTML = '<span style="color:red;">請求失敗</span>';
                });
        }

        function notifLevelBadge(level) {
            if (level === 'ok') return '<span class="badge badge-success">OK</span>';
            if (level === 'fail') return '<span class="badge badge-danger">FAIL</span>';
            return '<span class="badge badge-warning">WARN</span>';
        }

        function renderNotificationSelfCheck(d, extraClient) {
            const summaryEl = document.getElementById('notifSelfCheckSummary');
            const tableWrap = document.getElementById('notifSelfCheckTableWrap');
            const tbody = document.querySelector('#notifSelfCheckTable tbody');
            const pre = document.getElementById('notifSelfCheckResult');
            const s = d.summary || {};
            const overall = d.overall || (d.success ? 'ok' : 'fail');
            const color = overall === 'ok' ? 'green' : (overall === 'fail' ? 'red' : '#b45309');
            summaryEl.innerHTML = '<span style="color:' + color + ';font-weight:600;">overall=' + overall +
                '</span> · OK ' + (s.ok || 0) + ' / WARN ' + (s.warn || 0) + ' / FAIL ' + (s.fail || 0) +
                ' · ' + (d.checked_at || '');

            const rows = Array.isArray(d.checks) ? d.checks.slice() : [];
            if (extraClient && Array.isArray(extraClient.checks)) {
                extraClient.checks.forEach(function (c) { rows.push(c); });
            }

            tbody.innerHTML = rows.map(function (c) {
                return '<tr>' +
                    '<td>' + notifLevelBadge(c.level || (c.ok ? 'ok' : 'warn')) + '</td>' +
                    '<td>' + (c.channel || '') + '</td>' +
                    '<td>' + (c.label || c.id || '') + '</td>' +
                    '<td style="word-break:break-word;">' + String(c.detail || '').replace(/</g, '&lt;') + '</td>' +
                    '</tr>';
            }).join('');
            tableWrap.style.display = 'block';
            pre.style.display = 'block';
            pre.textContent = JSON.stringify({ server: d, client: extraClient || null }, null, 2);
        }

        function runNotificationSelfCheck() {
            const summaryEl = document.getElementById('notifSelfCheckSummary');
            summaryEl.textContent = '伺服器檢測中…';
            fetch('notif_diag.php', { method: 'GET', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (d) { renderNotificationSelfCheck(d); })
                .catch(function () {
                    summaryEl.innerHTML = '<span style="color:red;">notif_diag.php 請求失敗（可能尚未部署）</span>';
                });
        }

        function runClientNotificationSelfCheck() {
            const checks = [];
            function add(id, label, ok, detail, level) {
                checks.push({
                    id: id,
                    channel: 'browser-client',
                    label: label,
                    ok: !!ok,
                    level: level || (ok ? 'ok' : 'warn'),
                    detail: detail
                });
            }
            add('notif_api', 'Notification API', 'Notification' in window,
                'Notification' in window ? ('permission=' + Notification.permission) : '不支援',
                'Notification' in window ? 'ok' : 'warn');
            add('sw', 'Service Worker', 'serviceWorker' in navigator,
                'serviceWorker' in navigator ? '可用' : '不支援',
                'serviceWorker' in navigator ? 'ok' : 'warn');
            add('push_manager', 'PushManager', 'PushManager' in window,
                'PushManager' in window ? '可用' : '不支援',
                'PushManager' in window ? 'ok' : 'warn');
            add('secure', 'isSecureContext', window.isSecureContext === true,
                window.isSecureContext ? 'secure context' : '非 secure context（HTTPS/localhost 才完整支援）',
                window.isSecureContext ? 'ok' : 'warn');
            add('fengbro_js', 'FengbroNotifications 模組', !!(window.FengbroNotifications),
                window.FengbroNotifications ? '已載入' : '未載入 assets/js/notifications.js',
                window.FengbroNotifications ? 'ok' : 'fail');
            if (window.FengbroNotifications && typeof FengbroNotifications.todayKey === 'function') {
                add('today_key', 'todayKey()', true, FengbroNotifications.todayKey(), 'ok');
            }
            const fail = checks.filter(function (c) { return c.level === 'fail'; }).length;
            const warn = checks.filter(function (c) { return c.level === 'warn'; }).length;
            const ok = checks.filter(function (c) { return c.level === 'ok'; }).length;
            const client = {
                overall: fail ? 'fail' : (warn ? 'warn' : 'ok'),
                summary: { ok: ok, warn: warn, fail: fail, total: checks.length },
                checks: checks
            };
            // Merge with server report when available
            fetch('notif_diag.php', { method: 'GET', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (d) { renderNotificationSelfCheck(d, client); })
                .catch(function () {
                    renderNotificationSelfCheck({
                        success: fail === 0,
                        overall: client.overall,
                        checked_at: new Date().toISOString(),
                        summary: client.summary,
                        checks: [],
                        due: {},
                        client_hints: {}
                    }, client);
                });
        }
    </script>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">資料庫統計</h3>
        <?php
        $pdo = getConnection();
        $tables = [
            'subscription' => '訂閱',
            'trialpurchase' => '試用/首購',
            'reinstall' => '重灌',
            'quota' => '額度',
            'shoppinglist' => '購物清單',
            'manualprice' => '手動價格',
            'tubechannel' => 'Tube 頻道',
            'financeinstrument' => '金融標的',
            'food' => '食品',
            'article' => '筆記/文章',
            'commonaccount' => '常用帳號',
            'image' => '圖片',
            'music' => '音樂',
            'podcast' => '播客',
            'commondocument' => '文件',
            'bank' => '銀行',
            'routine' => '例行事項',
            'sitevisit' => '進站統計',
            'menuusage' => '選單使用'
        ];
        ?>
        <table class="table">
            <thead>
                <tr>
                    <th>資料表</th>
                    <th>名稱</th>
                    <th>筆數</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables as $table => $name): ?>
                    <?php
                    try {
                        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
                    } catch (Exception $e) {
                        $count = '表格不存在';
                    }
                    ?>
                    <tr>
                        <td><code><?php echo $table; ?></code></td>
                        <td><?php echo $name; ?></td>
                        <td><?php echo $count; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">Storage 檔案管理</h3>
        <p style="color: var(--muted-text); margin-bottom: 12px;">掃描本機 uploads 目錄，找出資料庫欄位未引用的檔案。這是 PHP/MySQL 版對應 Appwrite/Supabase Storage 清理的實作。</p>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
            <button type="button" class="btn btn-primary" onclick="scanStorageFiles()">
                <i class="fa-solid fa-magnifying-glass"></i> 掃描 uploads
            </button>
            <button type="button" class="btn btn-danger" id="deleteUnusedStorageBtn" onclick="deleteUnusedStorageFiles()" disabled>
                <i class="fa-solid fa-trash"></i> 刪除未引用檔案
            </button>
        </div>
        <div id="storageScanResult" style="color: var(--muted-text);">尚未掃描。</div>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">本機離線快取（IndexedDB）</h3>
        <p style="color: var(--muted-text); margin-bottom: 12px;">
            對齊 Appwrite 版媒體快取：影片 / 音樂 / 播客 / 文件 / 圖片各有獨立資料庫，單一類型上限 500MB。
            快取只存在目前瀏覽器，清除後需重新下載。
        </p>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
            <button type="button" class="btn btn-primary" onclick="refreshOfflineCacheStats()">
                <i class="fa-solid fa-rotate"></i> 重新整理統計
            </button>
            <button type="button" class="btn btn-danger" onclick="clearAllOfflineCaches()">
                <i class="fa-solid fa-broom"></i> 清除全部離線快取
            </button>
        </div>
        <div id="offlineCacheResult" style="color: var(--muted-text);">載入中...</div>
    </div>
</div>

<script>
    let unusedStorageFiles = [];

    function initSiteStatTables() {
        const result = document.getElementById('siteStatInitResult');
        if (result) result.textContent = '初始化中…';
        fetch('stats_api.php?action=init_site_tables', { method: 'POST' })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || '初始化失敗');
                if (result) {
                    result.innerHTML = '<span style="color:#059669;">✓ sitevisit、menuusage 已建立／補齊</span>';
                }
                setTimeout(() => window.location.reload(), 900);
            })
            .catch(err => {
                if (result) {
                    result.innerHTML = '<span style="color:#e11d48;">✗ ' + String(err.message || err) + '</span>';
                }
            });
    }

    function formatStorageSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let value = Number(bytes || 0);
        let unit = 0;
        while (value >= 1024 && unit < units.length - 1) {
            value /= 1024;
            unit++;
        }
        return value.toFixed(unit === 0 ? 0 : 1) + ' ' + units[unit];
    }

    function scanStorageFiles() {
        const box = document.getElementById('storageScanResult');
        const deleteBtn = document.getElementById('deleteUnusedStorageBtn');
        box.innerHTML = '掃描中...';
        deleteBtn.disabled = true;
        fetch('storage_api.php?action=scan')
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || '掃描失敗');
                unusedStorageFiles = res.unusedFiles || [];
                deleteBtn.disabled = unusedStorageFiles.length === 0;
                const rows = unusedStorageFiles.slice(0, 120).map(file => `
                    <tr>
                        <td><code>${file.path}</code></td>
                        <td>${formatStorageSize(file.size)}</td>
                        <td>${file.modified || ''}</td>
                    </tr>
                `).join('');
                box.innerHTML = `
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:12px;">
                        <div class="card" style="margin:0;"><strong>${res.totalFiles}</strong><br><span>Storage 檔案</span></div>
                        <div class="card" style="margin:0;"><strong>${res.referencedCount}</strong><br><span>已引用</span></div>
                        <div class="card" style="margin:0;"><strong>${unusedStorageFiles.length}</strong><br><span>未引用</span></div>
                    </div>
                    ${unusedStorageFiles.length ? `
                        <table class="table">
                            <thead><tr><th>檔案</th><th>大小</th><th>修改時間</th></tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                        ${unusedStorageFiles.length > 120 ? '<p>僅顯示前 120 筆。</p>' : ''}
                    ` : '<p>目前沒有未引用檔案。</p>'}
                `;
            })
            .catch(err => box.innerHTML = '<span style="color:#e74c3c;">' + err.message + '</span>');
    }

    function deleteUnusedStorageFiles() {
        if (!unusedStorageFiles.length) return;
        if (!confirm('確定刪除 ' + unusedStorageFiles.length + ' 個未引用檔案？此操作不可復原。')) return;
        fetch('storage_api.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ paths: unusedStorageFiles.map(file => file.path) })
        })
            .then(r => r.json())
            .then(res => {
                alert('已刪除 ' + (res.deleted || 0) + ' 個檔案' + (res.errors && res.errors.length ? '\\n錯誤：' + res.errors.join('\\n') : ''));
                scanStorageFiles();
            })
            .catch(err => alert('刪除失敗: ' + err.message));
    }

    const OFFLINE_CACHE_LABELS = {
        video: '影片',
        music: '音樂',
        podcast: '播客',
        document: '文件',
        image: '圖片'
    };

    async function refreshOfflineCacheStats() {
        const box = document.getElementById('offlineCacheResult');
        if (!box) return;
        if (!window.FengbroMediaCache || !window.FengbroMediaCache.getAllStats) {
            box.innerHTML = '<span style="color:#e74c3c;">目前瀏覽器不支援 IndexedDB 離線快取。</span>';
            return;
        }
        box.textContent = '讀取中...';
        try {
            const summary = await window.FengbroMediaCache.getAllStats();
            const rows = (summary.kinds || []).map(function (row) {
                const ratio = Math.min(100, Math.round((row.usageRatio || 0) * 100));
                return `
                    <tr>
                        <td>${OFFLINE_CACHE_LABELS[row.kind] || row.kind}</td>
                        <td>${row.totalItems || 0}</td>
                        <td>${window.FengbroMediaCache.formatBytes(row.totalSize || 0)}</td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:8px;border-radius:999px;background:var(--table-header-bg);overflow:hidden;">
                                    <div style="width:${ratio}%;height:100%;background:var(--accent);"></div>
                                </div>
                                <span style="min-width:42px;text-align:right;">${ratio}%</span>
                            </div>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="clearOfflineCacheKind('${row.kind}')">
                                清除
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
            box.innerHTML = `
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:12px;">
                    <div class="card" style="margin:0;"><strong>${summary.totalItems || 0}</strong><br><span>快取項目</span></div>
                    <div class="card" style="margin:0;"><strong>${window.FengbroMediaCache.formatBytes(summary.totalSize || 0)}</strong><br><span>合計用量</span></div>
                    <div class="card" style="margin:0;"><strong>500MB</strong><br><span>每類型上限</span></div>
                </div>
                <div style="overflow-x:auto;">
                    <table class="table" style="min-width:560px;">
                        <thead>
                            <tr><th>類型</th><th>數量</th><th>大小</th><th>用量</th><th>操作</th></tr>
                        </thead>
                        <tbody>${rows || '<tr><td colspan="5">目前沒有離線快取。</td></tr>'}</tbody>
                    </table>
                </div>
            `;
        } catch (err) {
            box.innerHTML = '<span style="color:#e74c3c;">讀取失敗：' + (err.message || err) + '</span>';
        }
    }

    async function clearOfflineCacheKind(kind) {
        if (!window.FengbroMediaCache) return;
        const label = OFFLINE_CACHE_LABELS[kind] || kind;
        if (!confirm('確定清除「' + label + '」離線快取？')) return;
        try {
            const result = await window.FengbroMediaCache.clearKind(kind);
            alert('已清除 ' + (result.cleared || 0) + ' 筆「' + label + '」快取');
            refreshOfflineCacheStats();
        } catch (err) {
            alert('清除失敗：' + (err.message || err));
        }
    }

    async function clearAllOfflineCaches() {
        if (!window.FengbroMediaCache) return;
        if (!confirm('確定清除全部離線快取（影片/音樂/播客/文件/圖片）？此操作不可復原。')) return;
        try {
            const results = await window.FengbroMediaCache.clearAll();
            const total = (results || []).reduce(function (sum, row) {
                return sum + (row.cleared || 0);
            }, 0);
            alert('已清除全部離線快取，共 ' + total + ' 筆');
            refreshOfflineCacheStats();
        } catch (err) {
            alert('清除失敗：' + (err.message || err));
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        refreshOfflineCacheStats();
    });
</script>
