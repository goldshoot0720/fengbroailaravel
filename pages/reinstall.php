<?php
$pageTitle = '鋒兄重灌';
$pdo = getConnection();
fengbroEnsureReinstallTable($pdo);

$items = $pdo->query("SELECT * FROM reinstall ORDER BY name ASC, `system` ASC, created_at ASC")->fetchAll();
$windowsCount = 0;
$macCount = 0;
$serialCount = 0;
foreach ($items as $item) {
    if (($item['system'] ?? 'win') === 'mac') {
        $macCount++;
    } else {
        $windowsCount++;
    }
    if (($item['licenseType'] ?? 'none') === 'paid_serial') {
        $serialCount++;
    }
}
?>

<div class="content-header" style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="margin:0;">鋒兄重灌</h1>
        <p class="muted-copy">整理 Windows 與 Mac 重灌時需要的軟體、網站、授權與訂閱資訊；付費序號預設保持隱藏，可另設查看密碼。</p>
    </div>
    <span class="count-pill count-pill-reinstall"><?php echo count($items); ?> 套軟體</span>
</div>

<div class="content-body">
    <div class="mgmt-stat-grid">
        <div class="food-stat-card">
            <span>全部軟體</span>
            <strong><?php echo count($items); ?></strong>
        </div>
        <div class="food-stat-card">
            <span>Windows</span>
            <strong><?php echo $windowsCount; ?></strong>
        </div>
        <div class="food-stat-card">
            <span>Mac</span>
            <strong><?php echo $macCount; ?></strong>
        </div>
        <div class="food-stat-card food-stat-warning">
            <span>付費序號</span>
            <strong><?php echo $serialCount; ?></strong>
        </div>
    </div>

    <div class="action-buttons-bar">
        <button class="btn btn-primary" type="button" onclick="openReinstallForm()"><i class="fas fa-plus"></i> 新增軟體</button>
        <a class="btn btn-success" href="export.php?table=reinstall"><i class="fa-solid fa-download"></i> 匯出 CSV</a>
        <?php $csvTable = 'reinstall'; include 'includes/csv_buttons.php'; ?>
        <?php include 'includes/batch-delete.php'; ?>
    </div>

    <form id="reinstallForm" class="card mgmt-form" style="display:none;" onsubmit="return saveReinstall(event)">
        <h3 class="card-title" id="reinstallFormTitle">新增重灌軟體</h3>
        <p class="muted-copy">儲存安裝時真正需要找到的資訊。付費序號與查看密碼都不會預設攤開。</p>
        <input type="hidden" id="reinstallId" value="">
        <div class="mgmt-form-grid">
            <label>服務名稱 <span class="req">*</span>
                <input class="form-control" id="reinstallName" maxlength="100" required placeholder="例如 7-Zip、Adobe Acrobat">
            </label>
            <label>使用系統
                <select class="form-control" id="reinstallSystem">
                    <option value="win">Windows</option>
                    <option value="mac">Mac</option>
                </select>
            </label>
            <label>軟體類型
                <select class="form-control" id="reinstallSoftwareType">
                    <option value="free">免費軟體</option>
                    <option value="trial">試用軟體</option>
                    <option value="paid">付費軟體</option>
                </select>
            </label>
            <label>訂閱制軟體
                <select class="form-control" id="reinstallSubscription" onchange="syncSubscriptionFields()">
                    <option value="no">否</option>
                    <option value="yes">是</option>
                </select>
            </label>
            <label id="reinstallSubPeriodWrap">訂閱週期
                <div class="pair-input">
                    <input class="form-control" id="reinstallSubPeriodCount" type="number" min="1" step="1" value="1">
                    <select class="form-control" id="reinstallSubPeriodUnit">
                        <option value="month">月</option>
                        <option value="year">年</option>
                    </select>
                </div>
            </label>
            <label id="reinstallSubPriceWrap">訂閱費用
                <div class="pair-input">
                    <input class="form-control" id="reinstallSubPrice" type="number" min="0" step="1" value="0">
                    <select class="form-control" id="reinstallSubCurrency">
                        <option value="TWD">台幣</option>
                        <option value="USD">美元</option>
                        <option value="JPY">日圓</option>
                        <option value="CNY">人民幣</option>
                    </select>
                </div>
            </label>
            <label>授權方式
                <select class="form-control" id="reinstallLicenseType" onchange="syncLicenseFields()">
                    <option value="none">無序號</option>
                    <option value="paid_serial">付費序號</option>
                </select>
            </label>
            <label id="reinstallSerialWrap">付費序號
                <div class="secret-input">
                    <input class="form-control" id="reinstallSerial" maxlength="500" type="password" autocomplete="off" placeholder="輸入序號">
                    <button type="button" class="secret-toggle" onclick="toggleSecret('reinstallSerial', this)" aria-label="顯示付費序號"><i class="fa-solid fa-eye"></i></button>
                </div>
            </label>
            <label id="reinstallPasswordWrap">查看密碼
                <div class="secret-input">
                    <input class="form-control" id="reinstallViewPassword" maxlength="100" type="password" autocomplete="off" placeholder="選填，清單顯示序號時需輸入">
                    <button type="button" class="secret-toggle" onclick="toggleSecret('reinstallViewPassword', this)" aria-label="顯示查看密碼"><i class="fa-solid fa-eye"></i></button>
                </div>
            </label>
            <label>軟體網站
                <input class="form-control" id="reinstallSite" type="url" maxlength="2000" placeholder="https://example.com">
            </label>
            <label class="mgmt-span-2">備註
                <textarea class="form-control" id="reinstallNote" maxlength="3337" rows="3" placeholder="安裝順序、登入方式、下載版本或其他提醒"></textarea>
            </label>
        </div>
        <div class="inline-actions" style="margin-top:16px;">
            <button type="submit" class="btn btn-primary" id="reinstallSaveBtn">新增軟體</button>
            <button type="button" class="btn" onclick="closeReinstallForm()">取消</button>
        </div>
    </form>

    <div class="mgmt-filter-bar">
        <label class="food-search-box">
            <i class="fas fa-search"></i>
            <input type="search" id="reinstallSearchInput" class="form-control" placeholder="搜尋服務、網站或備註" oninput="filterReinstall()">
        </label>
        <select id="reinstallSystemFilter" class="form-control" onchange="filterReinstall()">
            <option value="all">全部系統</option>
            <option value="win">Windows</option>
            <option value="mac">Mac</option>
        </select>
        <select id="reinstallTypeFilter" class="form-control" onchange="filterReinstall()">
            <option value="all">全部軟體類型</option>
            <option value="trial">試用軟體</option>
            <option value="free">免費軟體</option>
            <option value="paid">付費軟體</option>
        </select>
        <select id="reinstallSubFilter" class="form-control" onchange="filterReinstall()">
            <option value="all">全部訂閱狀態</option>
            <option value="yes">訂閱制</option>
            <option value="no">非訂閱制</option>
        </select>
        <span class="food-result-count" id="reinstallVisibleCount"><?php echo count($items); ?> 套</span>
    </div>

    <?php if (empty($items)): ?>
        <div class="card" style="text-align:center;color:#999;padding:40px;">尚無重灌軟體。先加入第一套軟體。</div>
    <?php else: ?>
        <div class="card" style="padding:0;overflow:hidden;">
            <div class="reinstall-head desktop-only">
                <span></span><span>服務名稱</span><span>系統</span><span>軟體類型</span><span>序號</span><span>網站／備註</span><span>操作</span>
            </div>
            <?php foreach ($items as $item): ?>
                <?php
                $system = fengbroNormalizeReinstallSystem($item['system'] ?? 'win');
                $softwareType = fengbroNormalizeSoftwareType($item['softwareType'] ?? 'free');
                $licenseType = fengbroNormalizeLicenseType($item['licenseType'] ?? 'none');
                $isSubscription = fengbroParseBoolean($item['subscriptionSoftware'] ?? 0);
                $subscriptionPeriod = $isSubscription ? (string) ($item['subscriptionPeriod'] ?? '') : '';
                $subscriptionPrice = $isSubscription ? fengbroNonNegativeInt($item['subscriptionPrice'] ?? 0) : 0;
                $subscriptionCurrency = $isSubscription ? (string) ($item['subscriptionCurrency'] ?? 'TWD') : 'TWD';
                $site = fengbroSafeHttpUrl($item['site'] ?? '');
                $hasSerial = $licenseType === 'paid_serial';
                $searchBlob = strtolower(($item['name'] ?? '') . ' ' . ($item['site'] ?? '') . ' ' . ($item['note'] ?? ''));
                ?>
                <article class="reinstall-row"
                    data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    data-system="<?php echo htmlspecialchars($system, ENT_QUOTES, 'UTF-8'); ?>"
                    data-softwaretype="<?php echo htmlspecialchars($softwareType, ENT_QUOTES, 'UTF-8'); ?>"
                    data-licensetype="<?php echo htmlspecialchars($licenseType, ENT_QUOTES, 'UTF-8'); ?>"
                    data-serial="<?php echo htmlspecialchars($item['serial'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    data-viewpassword="<?php echo htmlspecialchars($item['viewPassword'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    data-subscriptionsoftware="<?php echo $isSubscription ? '1' : '0'; ?>"
                    data-subscriptionperiod="<?php echo htmlspecialchars($subscriptionPeriod, ENT_QUOTES, 'UTF-8'); ?>"
                    data-subscriptionprice="<?php echo htmlspecialchars((string) $subscriptionPrice, ENT_QUOTES, 'UTF-8'); ?>"
                    data-subscriptioncurrency="<?php echo htmlspecialchars($subscriptionCurrency, ENT_QUOTES, 'UTF-8'); ?>"
                    data-site="<?php echo htmlspecialchars($item['site'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    data-note="<?php echo htmlspecialchars($item['note'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>">
                    <div><input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>" onchange="toggleSelectItem(this)"></div>
                    <div>
                        <span class="mgmt-mobile-label">服務名稱</span>
                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">系統</span>
                        <span class="status-chip chip-info"><?php echo fengbroReinstallSystemLabel($system); ?></span>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">軟體類型</span>
                        <span class="status-chip <?php echo $softwareType === 'free' ? 'chip-success' : ($softwareType === 'trial' ? 'chip-warning' : 'chip-info'); ?>"><?php echo fengbroSoftwareTypeLabel($softwareType); ?></span>
                        <?php if ($isSubscription): ?>
                            <span class="status-chip chip-warning">訂閱制</span>
                            <p class="mgmt-note"><?php echo htmlspecialchars(fengbroReinstallSubscriptionPeriodLabel($subscriptionPeriod)); ?> · <?php echo htmlspecialchars(fengbroFormatReinstallMoney($subscriptionPrice, $subscriptionCurrency)); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">序號</span>
                        <?php if ($hasSerial): ?>
                            <div class="serial-cell">
                                <span class="status-chip chip-warning">付費序號</span>
                                <?php if (trim((string) ($item['viewPassword'] ?? '')) !== ''): ?>
                                    <span class="status-chip chip-info">需查看密碼</span>
                                <?php endif; ?>
                                <div class="serial-line">
                                    <code class="serial-code private-value" data-masked="1"><?php echo trim((string) ($item['serial'] ?? '')) !== '' ? '•••• •••• ••••' : '尚未填序號'; ?></code>
                                    <?php if (trim((string) ($item['serial'] ?? '')) !== ''): ?>
                                        <button type="button" class="btn btn-sm" onclick="toggleSerial('<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>')" aria-label="顯示序號"><i class="fa-solid fa-eye"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="status-chip chip-muted">無序號</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">網站／備註</span>
                        <?php if ($site): ?>
                            <a href="<?php echo htmlspecialchars($site); ?>" target="_blank" rel="noopener">開啟網站</a>
                        <?php else: ?>
                            <span class="muted-copy">未填網站</span>
                        <?php endif; ?>
                        <p class="mgmt-note"><?php echo trim((string) ($item['note'] ?? '')) !== '' ? nl2br(htmlspecialchars($item['note'])) : '—'; ?></p>
                    </div>
                    <div class="mgmt-row-actions">
                        <button type="button" class="btn btn-sm btn-primary" onclick="openReinstallForm('<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>')" title="編輯"><i class="fas fa-pen"></i></button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteReinstall('<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>')" title="刪除"><i class="fas fa-trash"></i></button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="muted-copy" style="margin-top:16px;"><i class="fa-solid fa-shield-halved"></i> 付費序號只在主動點擊眼睛按鈕後顯示；若有設定查看密碼，需先輸入正確密碼。重新整理後會再次隱藏。這只是畫面遮罩，不是加密保管庫。</p>
    <?php endif; ?>
</div>

<div id="reinstallRevealDialog" class="mgmt-dialog" hidden>
    <div class="mgmt-dialog-card" role="dialog" aria-modal="true" aria-labelledby="reinstallRevealTitle">
        <h3 id="reinstallRevealTitle">輸入查看密碼</h3>
        <p id="reinstallRevealCopy" class="muted-copy"></p>
        <form onsubmit="return confirmRevealSerial(event)">
            <label>查看密碼
                <input class="form-control" id="reinstallRevealInput" type="password" autocomplete="off">
            </label>
            <p id="reinstallRevealError" class="form-error" hidden>查看密碼不正確</p>
            <div class="inline-actions" style="margin-top:16px;">
                <button type="button" class="btn" onclick="closeRevealDialog()">取消</button>
                <button type="submit" class="btn btn-primary">顯示序號</button>
            </div>
        </form>
    </div>
</div>

<style>
    .muted-copy { margin: 8px 0 0; color: var(--muted-text); line-height: 1.6; }
    .count-pill { color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
    .count-pill-reinstall { background: #b4552f; }
    .mgmt-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .food-stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 18px; padding: 14px 16px; box-shadow: 0 12px 26px var(--shadow); }
    .food-stat-card span { display: block; color: var(--muted-text); font-size: 0.82rem; margin-bottom: 6px; }
    .food-stat-card strong { font-size: 1.35rem; }
    .food-stat-warning strong { color: #c8873a; }
    .mgmt-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
    .mgmt-form-grid label { display: grid; gap: 6px; font-weight: 600; }
    .mgmt-span-2 { grid-column: 1 / -1; }
    .req { color: #c1554a; }
    .mgmt-filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 8px 0 18px; }
    .food-search-box { position: relative; flex: 1 1 260px; }
    .food-search-box i { position: absolute; top: 50%; left: 12px; transform: translateY(-50%); color: var(--muted-text); }
    .food-search-box input { padding-left: 38px; }
    .secret-input { position: relative; }
    .secret-input input { padding-right: 42px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .secret-toggle { position: absolute; right: 0; top: 0; width: 40px; height: 100%; border: 0; background: transparent; color: var(--muted-text); cursor: pointer; }
    .pair-input { display: grid; grid-template-columns: minmax(0,1fr) 6.5rem; gap: 8px; }
    .reinstall-head, .reinstall-row { display: grid; grid-template-columns: 36px minmax(0,1.1fr) 80px 130px minmax(0,1.3fr) minmax(0,1fr) 88px; gap: 12px; align-items: center; padding: 14px 16px; }
    .reinstall-head { color: var(--muted-text); font-size: 0.78rem; font-weight: 700; border-bottom: 1px solid var(--border-color); }
    .reinstall-row { border-bottom: 1px solid var(--border-color); }
    .reinstall-row:last-child { border-bottom: 0; }
    .mgmt-mobile-label { display: none; }
    .mgmt-note { margin: 6px 0 0; white-space: pre-wrap; color: var(--muted-text); font-size: 0.92rem; }
    .mgmt-row-actions { display: flex; gap: 6px; }
    .status-chip { display: inline-block; margin: 0 4px 4px 0; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
    .chip-success { background: #e3efe5; color: #2b5c40; }
    .chip-warning { background: #f7ecd9; color: #6f5518; }
    .chip-info { background: #f6e5db; color: #9c4726; }
    .chip-muted { background: #f0eee6; color: #57534a; }
    .serial-line { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
    .serial-code { display: block; flex: 1; min-width: 0; word-break: break-all; background: var(--table-header-bg, #faf9f5); padding: 6px 8px; border-radius: 8px; }
    .serial-code[data-masked="0"] { filter: none !important; user-select: text !important; }
    .serial-code[data-masked="1"] { filter: blur(6px); user-select: none; }
    .mgmt-dialog { position: fixed; inset: 0; z-index: 120; background: rgba(30, 26, 20, 0.4); display: flex; align-items: center; justify-content: center; padding: 16px; }
    .mgmt-dialog[hidden] { display: none !important; }
    .mgmt-dialog-card { width: min(420px, 100%); background: var(--card-bg); border-radius: 18px; padding: 24px; box-shadow: 0 24px 60px rgba(0,0,0,.28); }
    .form-error { color: #a63e34; font-weight: 600; margin-top: 8px; }
    @media (max-width: 900px) {
        .reinstall-head { display: none; }
        .reinstall-row { grid-template-columns: 28px 1fr; }
        .mgmt-mobile-label { display: block; font-size: 0.72rem; color: var(--muted-text); margin-bottom: 4px; }
        .mgmt-row-actions { grid-column: 2; }
    }
</style>

<script>
    const TABLE = 'reinstall';
    initBatchDelete(TABLE);
    let pendingRevealId = '';

    function reinstallEls() {
        return {
            form: document.getElementById('reinstallForm'),
            id: document.getElementById('reinstallId'),
            title: document.getElementById('reinstallFormTitle'),
            save: document.getElementById('reinstallSaveBtn'),
            name: document.getElementById('reinstallName'),
            system: document.getElementById('reinstallSystem'),
            softwareType: document.getElementById('reinstallSoftwareType'),
            subscription: document.getElementById('reinstallSubscription'),
            periodCount: document.getElementById('reinstallSubPeriodCount'),
            periodUnit: document.getElementById('reinstallSubPeriodUnit'),
            price: document.getElementById('reinstallSubPrice'),
            currency: document.getElementById('reinstallSubCurrency'),
            licenseType: document.getElementById('reinstallLicenseType'),
            serial: document.getElementById('reinstallSerial'),
            viewPassword: document.getElementById('reinstallViewPassword'),
            site: document.getElementById('reinstallSite'),
            note: document.getElementById('reinstallNote')
        };
    }

    function parsePeriod(value) {
        const match = String(value || '').trim().match(/^([1-9]\d{0,3})(年|月)$/);
        if (!match) return { count: 1, unit: 'month' };
        return { count: Number(match[1]), unit: match[2] === '年' ? 'year' : 'month' };
    }

    function syncLicenseFields() {
        const paid = document.getElementById('reinstallLicenseType').value === 'paid_serial';
        document.getElementById('reinstallSerialWrap').style.display = paid ? '' : 'none';
        document.getElementById('reinstallPasswordWrap').style.display = paid ? '' : 'none';
        if (!paid) {
            document.getElementById('reinstallSerial').value = '';
            document.getElementById('reinstallViewPassword').value = '';
        }
    }

    function syncSubscriptionFields() {
        const enabled = document.getElementById('reinstallSubscription').value === 'yes';
        document.getElementById('reinstallSubPeriodWrap').style.display = enabled ? '' : 'none';
        document.getElementById('reinstallSubPriceWrap').style.display = enabled ? '' : 'none';
        if (!enabled) {
            document.getElementById('reinstallSubPeriodCount').value = '1';
            document.getElementById('reinstallSubPeriodUnit').value = 'month';
            document.getElementById('reinstallSubPrice').value = '0';
            document.getElementById('reinstallSubCurrency').value = 'TWD';
        }
    }

    function toggleSecret(id, button) {
        const input = document.getElementById(id);
        const hide = input.type === 'text';
        input.type = hide ? 'password' : 'text';
        button.setAttribute('aria-label', hide ? button.getAttribute('aria-label').replace('隱藏', '顯示') : button.getAttribute('aria-label').replace('顯示', '隱藏'));
        button.innerHTML = hide ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-solid fa-eye-slash"></i>';
    }

    function openReinstallForm(id) {
        const els = reinstallEls();
        els.form.style.display = '';
        els.serial.type = 'password';
        els.viewPassword.type = 'password';
        if (id) {
            const row = document.querySelector('.reinstall-row[data-id="' + id + '"]');
            els.id.value = id;
            els.title.textContent = '編輯重灌軟體';
            els.save.textContent = '儲存變更';
            els.name.value = row ? (row.dataset.name || '') : '';
            els.system.value = row ? (row.dataset.system || 'win') : 'win';
            els.softwareType.value = row ? (row.dataset.softwaretype || 'free') : 'free';
            els.subscription.value = row && row.dataset.subscriptionsoftware === '1' ? 'yes' : 'no';
            const period = parsePeriod(row ? row.dataset.subscriptionperiod : '');
            els.periodCount.value = String(period.count);
            els.periodUnit.value = period.unit;
            els.price.value = row ? (row.dataset.subscriptionprice || '0') : '0';
            els.currency.value = row ? (row.dataset.subscriptioncurrency || 'TWD') : 'TWD';
            els.licenseType.value = row ? (row.dataset.licensetype || 'none') : 'none';
            els.serial.value = row ? (row.dataset.serial || '') : '';
            els.viewPassword.value = row ? (row.dataset.viewpassword || '') : '';
            els.site.value = row ? (row.dataset.site || '') : '';
            els.note.value = row ? (row.dataset.note || '') : '';
        } else {
            els.id.value = '';
            els.title.textContent = '新增重灌軟體';
            els.save.textContent = '新增軟體';
            els.name.value = '';
            els.system.value = 'win';
            els.softwareType.value = 'free';
            els.subscription.value = 'no';
            els.periodCount.value = '1';
            els.periodUnit.value = 'month';
            els.price.value = '0';
            els.currency.value = 'TWD';
            els.licenseType.value = 'none';
            els.serial.value = '';
            els.viewPassword.value = '';
            els.site.value = '';
            els.note.value = '';
        }
        syncLicenseFields();
        syncSubscriptionFields();
        els.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        els.name.focus();
    }

    function closeReinstallForm() {
        document.getElementById('reinstallForm').style.display = 'none';
        document.getElementById('reinstallId').value = '';
    }

    function saveReinstall(event) {
        event.preventDefault();
        const els = reinstallEls();
        const subscribed = els.subscription.value === 'yes';
        const periodCount = Number(els.periodCount.value || 0);
        if (subscribed && (!Number.isInteger(periodCount) || periodCount < 1)) {
            alert('訂閱週期必須是 1 以上的整數');
            return false;
        }
        const payload = {
            name: els.name.value.trim(),
            system: els.system.value,
            softwareType: els.softwareType.value,
            licenseType: els.licenseType.value,
            serial: els.licenseType.value === 'paid_serial' ? els.serial.value : '',
            viewPassword: els.licenseType.value === 'paid_serial' ? els.viewPassword.value : '',
            subscriptionSoftware: subscribed,
            subscriptionPeriodCount: subscribed ? periodCount : 1,
            subscriptionPeriodUnit: subscribed ? els.periodUnit.value : 'month',
            subscriptionPrice: subscribed ? Number(els.price.value || 0) : 0,
            subscriptionCurrency: subscribed ? els.currency.value : 'TWD',
            site: els.site.value.trim(),
            note: els.note.value
        };
        if (!payload.name) {
            alert('請填寫服務名稱');
            return false;
        }
        if (payload.site && !/^https?:\/\//i.test(payload.site)) {
            alert('軟體網站必須是完整 http 或 https 網址');
            return false;
        }
        const id = els.id.value;
        const url = id
            ? 'api.php?action=update&table=' + encodeURIComponent(TABLE) + '&id=' + encodeURIComponent(id)
            : 'api.php?action=create&table=' + encodeURIComponent(TABLE);
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.success) location.reload();
            else alert(res.error || '儲存失敗');
        }).catch(function (err) {
            alert('儲存失敗: ' + (err.message || err));
        });
        return false;
    }

    function deleteReinstall(id) {
        const row = document.querySelector('.reinstall-row[data-id="' + id + '"]');
        const systemLabel = row && row.dataset.system === 'mac' ? 'Mac' : 'Windows';
        const label = row ? ((row.dataset.name || '') + '／' + systemLabel) : '這套軟體';
        if (!confirm('確定要刪除「' + label + '」嗎？刪除不能復原。')) return;
        fetch('api.php?action=delete&table=' + encodeURIComponent(TABLE) + '&id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) location.reload();
                else alert(res.error || '刪除失敗');
            });
    }

    function revealSerial(id) {
        const row = document.querySelector('.reinstall-row[data-id="' + id + '"]');
        if (!row) return;
        const code = row.querySelector('.serial-code');
        const btn = row.querySelector('.serial-line button');
        if (!code) return;
        const masked = code.getAttribute('data-masked') === '1';
        if (masked) {
            code.textContent = row.dataset.serial || '';
            code.setAttribute('data-masked', '0');
            if (btn) btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
        } else {
            code.textContent = '•••• •••• ••••';
            code.setAttribute('data-masked', '1');
            if (btn) btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
        }
    }

    function toggleSerial(id) {
        const row = document.querySelector('.reinstall-row[data-id="' + id + '"]');
        if (!row) return;
        const code = row.querySelector('.serial-code');
        if (code && code.getAttribute('data-masked') !== '1') {
            revealSerial(id);
            return;
        }
        const needPassword = (row.dataset.viewpassword || '').trim();
        if (!needPassword) {
            revealSerial(id);
            return;
        }
        pendingRevealId = id;
        document.getElementById('reinstallRevealCopy').textContent = '顯示「' + (row.dataset.name || '') + '」的付費序號前，請輸入這筆紀錄的查看密碼。';
        document.getElementById('reinstallRevealInput').value = '';
        document.getElementById('reinstallRevealError').hidden = true;
        document.getElementById('reinstallRevealDialog').hidden = false;
        document.getElementById('reinstallRevealInput').focus();
    }

    function closeRevealDialog() {
        pendingRevealId = '';
        document.getElementById('reinstallRevealDialog').hidden = true;
    }

    function confirmRevealSerial(event) {
        event.preventDefault();
        const row = document.querySelector('.reinstall-row[data-id="' + pendingRevealId + '"]');
        const typed = (document.getElementById('reinstallRevealInput').value || '').trim();
        const expected = row ? (row.dataset.viewpassword || '').trim() : '';
        if (typed !== expected) {
            document.getElementById('reinstallRevealError').hidden = false;
            return false;
        }
        const id = pendingRevealId;
        closeRevealDialog();
        revealSerial(id);
        return false;
    }

    function filterReinstall() {
        const query = (document.getElementById('reinstallSearchInput')?.value || '').trim().toLowerCase();
        const system = document.getElementById('reinstallSystemFilter')?.value || 'all';
        const type = document.getElementById('reinstallTypeFilter')?.value || 'all';
        const subscription = document.getElementById('reinstallSubFilter')?.value || 'all';
        let visible = 0;
        document.querySelectorAll('.reinstall-row').forEach(function (row) {
            const matchesQuery = !query || (row.dataset.search || '').indexOf(query) !== -1;
            const matchesSystem = system === 'all' || row.dataset.system === system;
            const matchesType = type === 'all' || row.dataset.softwaretype === type;
            const matchesSub = subscription === 'all'
                || (subscription === 'yes' && row.dataset.subscriptionsoftware === '1')
                || (subscription === 'no' && row.dataset.subscriptionsoftware !== '1');
            const show = matchesQuery && matchesSystem && matchesType && matchesSub;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        const counter = document.getElementById('reinstallVisibleCount');
        if (counter) counter.textContent = visible + ' 套';
    }

    function handleAdd() {
        openReinstallForm();
    }

    syncLicenseFields();
    syncSubscriptionFields();
</script>
