<?php
$pageTitle = '鋒兄試用／首購';
$pdo = getConnection();
fengbroEnsureTrialPurchaseTable($pdo);

$items = $pdo->query("SELECT * FROM trialpurchase ORDER BY name ASC, account ASC, created_at ASC")->fetchAll();
$serviceNames = [];
$groups = [];
$untriedCount = 0;
$notPurchasedCount = 0;
$pendingIds = [];

foreach ($items as $item) {
    $name = trim((string) ($item['name'] ?? ''));
    $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    if (!isset($groups[$key])) {
        $groups[$key] = ['name' => $name, 'items' => []];
    }
    $groups[$key]['items'][] = $item;
    if ($name !== '') {
        $serviceNames[$name] = $name;
    }
    $trialStatus = fengbroNormalizeTrialStatus($item['trialStatus'] ?? 'untried');
    $purchaseStatus = fengbroNormalizePurchaseStatus($item['purchaseStatus'] ?? 'not_purchased');
    if ($trialStatus !== 'tried') {
        $untriedCount++;
        $pendingIds[$item['id']] = true;
    }
    if ($purchaseStatus === 'not_purchased') {
        $notPurchasedCount++;
        $pendingIds[$item['id']] = true;
    }
}

uasort($groups, function ($a, $b) {
    return strnatcasecmp($a['name'], $b['name']);
});
natcasesort($serviceNames);
$serviceNames = array_values($serviceNames);
$serviceCount = count($groups);
$pendingCount = count($pendingIds);
?>

<div class="content-header" style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="margin:0;">鋒兄試用／首購</h1>
        <p class="muted-copy">依服務集中追蹤每個帳號的試用、首購與試用／首購／到期日（扣款日）。點服務名稱可展開帳號。</p>
    </div>
    <span class="count-pill count-pill-trial"><?php echo count($items); ?> 筆帳號</span>
</div>

<div class="content-body">
    <div class="mgmt-stat-grid">
        <div class="food-stat-card">
            <span>服務</span>
            <strong><?php echo $serviceCount; ?></strong>
        </div>
        <div class="food-stat-card">
            <span>帳號紀錄</span>
            <strong><?php echo count($items); ?></strong>
        </div>
        <div class="food-stat-card food-stat-warning">
            <span>待處理帳號</span>
            <strong><?php echo $pendingCount; ?></strong>
            <small><?php echo $untriedCount; ?> 尚未試用 · <?php echo $notPurchasedCount; ?> 未首購</small>
        </div>
    </div>

    <div class="action-buttons-bar">
        <button class="btn btn-primary" type="button" onclick="openTrialForm()"><i class="fas fa-plus"></i> 新增紀錄</button>
        <a class="btn btn-success" href="export.php?table=trialpurchase"><i class="fa-solid fa-download"></i> 匯出 CSV</a>
        <?php $csvTable = 'trialpurchase'; include 'includes/csv_buttons.php'; ?>
        <?php include 'includes/batch-delete.php'; ?>
    </div>

    <form id="trialPurchaseForm" class="card mgmt-form" style="display:none;" onsubmit="return saveTrialPurchase(event)">
        <h3 class="card-title" id="trialFormTitle">新增帳號紀錄</h3>
        <p class="muted-copy">同一服務可建立多筆帳號，清單會自動歸在一起。</p>
        <input type="hidden" id="trialId" value="">
        <div class="mgmt-form-grid">
            <label>服務名稱 <span class="req">*</span>
                <input class="form-control" id="trialName" maxlength="100" list="trialServiceList" required placeholder="例如 ChatGPT">
                <datalist id="trialServiceList">
                    <?php foreach ($serviceNames as $serviceName): ?>
                        <option value="<?php echo htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endforeach; ?>
                </datalist>
            </label>
            <label>帳號
                <input class="form-control" id="trialAccount" maxlength="200" placeholder="Email、使用者名稱或辨識名稱">
            </label>
            <label>試用／首購／到期日（扣款日）
                <input class="form-control" id="trialEventDate" type="date">
            </label>
            <label>首購價格（NT$）
                <input class="form-control" id="trialFirstPrice" type="number" min="0" step="1" value="0">
            </label>
            <label>非首購價格（NT$）
                <input class="form-control" id="trialRegularPrice" type="number" min="0" step="1" value="0">
            </label>
            <label>試用狀態
                <select class="form-control" id="trialStatus">
                    <option value="untried">尚未試用</option>
                    <option value="tried">已試用</option>
                </select>
            </label>
            <label>首購狀態
                <select class="form-control" id="trialPurchaseStatus">
                    <option value="not_purchased">未首購</option>
                    <option value="purchased">已首購</option>
                    <option value="unavailable">無提供首購</option>
                </select>
            </label>
            <label class="mgmt-span-2">備註
                <textarea class="form-control" id="trialNote" maxlength="3337" rows="3" placeholder="方案限制、付款方式或其他提醒"></textarea>
            </label>
        </div>
        <div class="inline-actions" style="margin-top:16px;">
            <button type="submit" class="btn btn-primary" id="trialSaveBtn">新增紀錄</button>
            <button type="button" class="btn" onclick="closeTrialForm()">取消</button>
        </div>
    </form>

    <div class="mgmt-filter-bar">
        <label class="food-search-box">
            <i class="fas fa-search"></i>
            <input type="search" id="trialSearchInput" class="form-control" placeholder="搜尋服務、帳號或備註" oninput="filterTrialPurchase()">
        </label>
        <select id="trialAttentionFilter" class="form-control" onchange="filterTrialPurchase()">
            <option value="all">全部狀態</option>
            <option value="untried">尚未試用</option>
            <option value="not_purchased">未首購</option>
        </select>
        <span class="food-result-count" id="trialVisibleCount"><?php echo $serviceCount; ?> 個服務</span>
    </div>

    <?php if (empty($groups)): ?>
        <div class="card" style="text-align:center;color:#999;padding:40px;">尚無試用／首購紀錄。先新增第一個服務與帳號。</div>
    <?php else: ?>
        <div class="mgmt-group-list">
            <?php foreach ($groups as $groupKey => $group): ?>
                <?php
                $groupUntried = 0;
                $groupUnpurchased = 0;
                foreach ($group['items'] as $accountItem) {
                    if (fengbroNormalizeTrialStatus($accountItem['trialStatus'] ?? '') !== 'tried') {
                        $groupUntried++;
                    }
                    if (fengbroNormalizePurchaseStatus($accountItem['purchaseStatus'] ?? '') === 'not_purchased') {
                        $groupUnpurchased++;
                    }
                }
                $groupId = 'trial-group-' . substr(sha1($groupKey), 0, 12);
                ?>
                <section class="mgmt-group" data-trial-group="<?php echo htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mgmt-group-head">
                        <button type="button" class="mgmt-group-toggle" aria-expanded="false" aria-controls="<?php echo $groupId; ?>" onclick="toggleTrialGroup(this)">
                            <span class="mgmt-group-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="mgmt-group-copy">
                                <strong><?php echo htmlspecialchars($group['name']); ?></strong>
                                <small>
                                    <?php echo count($group['items']); ?> 個帳號
                                    <?php if ($groupUntried > 0): ?> · <?php echo $groupUntried; ?> 尚未試用<?php endif; ?>
                                    <?php if ($groupUnpurchased > 0): ?> · <?php echo $groupUnpurchased; ?> 未首購<?php endif; ?>
                                </small>
                            </span>
                            <i class="fa-solid fa-chevron-down mgmt-chevron"></i>
                        </button>
                        <button type="button" class="btn btn-sm" onclick="openTrialForm(null, <?php echo htmlspecialchars(json_encode($group['name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)">
                            <i class="fas fa-plus"></i> 新增帳號
                        </button>
                    </div>
                    <div id="<?php echo $groupId; ?>" class="mgmt-group-body">
                        <div class="mgmt-account-head desktop-only">
                            <span>帳號</span><span>試用／首購／到期日（扣款日）</span><span>價格</span><span>狀態</span><span>備註</span><span>操作</span>
                        </div>
                        <?php foreach ($group['items'] as $item): ?>
                            <?php
                            $trialStatus = fengbroNormalizeTrialStatus($item['trialStatus'] ?? 'untried');
                            $purchaseStatus = fengbroNormalizePurchaseStatus($item['purchaseStatus'] ?? 'not_purchased');
                            $accountLabel = trim((string) ($item['account'] ?? '')) !== '' ? $item['account'] : '未填帳號';
                            $eventDate = !empty($item['eventDate']) ? date('Y-m-d', strtotime($item['eventDate'])) : '';
                            $searchBlob = strtolower(($item['name'] ?? '') . ' ' . ($item['account'] ?? '') . ' ' . ($item['note'] ?? ''));
                            ?>
                            <article class="mgmt-account" data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-account="<?php echo htmlspecialchars($item['account'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-eventdate="<?php echo htmlspecialchars($eventDate, ENT_QUOTES, 'UTF-8'); ?>"
                                data-firstpurchaseprice="<?php echo (int) ($item['firstPurchasePrice'] ?? 0); ?>"
                                data-regularprice="<?php echo (int) ($item['regularPrice'] ?? 0); ?>"
                                data-note="<?php echo htmlspecialchars($item['note'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-trialstatus="<?php echo htmlspecialchars($trialStatus, ENT_QUOTES, 'UTF-8'); ?>"
                                data-purchasestatus="<?php echo htmlspecialchars($purchaseStatus, ENT_QUOTES, 'UTF-8'); ?>"
                                data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>" onchange="toggleSelectItem(this)">
                                <div>
                                    <span class="mgmt-mobile-label">帳號</span>
                                    <strong class="private-value"><?php echo htmlspecialchars($accountLabel); ?></strong>
                                </div>
                                <div>
                                    <span class="mgmt-mobile-label">試用／首購／到期日（扣款日）</span>
                                    <?php echo $eventDate !== '' ? htmlspecialchars($eventDate) : '未設定'; ?>
                                </div>
                                <div>
                                    <span class="mgmt-mobile-label">價格</span>
                                    <div>首購 <?php echo formatMoney((int) ($item['firstPurchasePrice'] ?? 0)); ?></div>
                                    <div>一般 <?php echo formatMoney((int) ($item['regularPrice'] ?? 0)); ?></div>
                                </div>
                                <div>
                                    <span class="mgmt-mobile-label">狀態</span>
                                    <span class="status-chip <?php echo $trialStatus === 'tried' ? 'chip-success' : 'chip-warning'; ?>"><?php echo fengbroTrialStatusLabel($trialStatus); ?></span>
                                    <span class="status-chip <?php echo $purchaseStatus === 'purchased' ? 'chip-success' : ($purchaseStatus === 'unavailable' ? 'chip-muted' : 'chip-warning'); ?>"><?php echo fengbroPurchaseStatusLabel($purchaseStatus); ?></span>
                                </div>
                                <div>
                                    <span class="mgmt-mobile-label">備註</span>
                                    <p class="mgmt-note"><?php echo trim((string) ($item['note'] ?? '')) !== '' ? nl2br(htmlspecialchars($item['note'])) : '—'; ?></p>
                                </div>
                                <div class="mgmt-row-actions">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openTrialForm('<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>')" title="編輯"><i class="fas fa-pen"></i></button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteTrialPurchase('<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>')" title="刪除"><i class="fas fa-trash"></i></button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .muted-copy { margin: 8px 0 0; color: var(--muted-text); line-height: 1.6; }
    .count-pill { color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
    .count-pill-trial { background: linear-gradient(135deg, #8e44ad, #9b59b6); }
    .mgmt-stat-grid, .food-ops-panel { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .food-stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 18px; padding: 14px 16px; box-shadow: 0 12px 26px var(--shadow); }
    .food-stat-card span { display: block; color: var(--muted-text); font-size: 0.82rem; margin-bottom: 6px; }
    .food-stat-card strong { font-size: 1.35rem; }
    .food-stat-card small { display: block; margin-top: 6px; color: var(--muted-text); }
    .food-stat-warning strong { color: #f39c12; }
    .mgmt-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
    .mgmt-form-grid label { display: grid; gap: 6px; font-weight: 600; }
    .mgmt-span-2 { grid-column: 1 / -1; }
    .req { color: #e74c3c; }
    .mgmt-filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 8px 0 18px; }
    .food-search-box { position: relative; flex: 1 1 260px; }
    .food-search-box i { position: absolute; top: 50%; left: 12px; transform: translateY(-50%); color: var(--muted-text); }
    .food-search-box input { padding-left: 38px; }
    .mgmt-filter-bar select { min-width: 160px; }
    .mgmt-group { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 18px; margin-bottom: 12px; overflow: hidden; }
    .mgmt-group-head { display: flex; gap: 8px; align-items: center; padding: 10px 12px; }
    .mgmt-group-toggle { flex: 1; display: flex; align-items: center; gap: 12px; border: 0; background: transparent; text-align: left; cursor: pointer; color: inherit; padding: 6px; border-radius: 12px; }
    .mgmt-group-toggle:hover { background: var(--table-header-bg, #f4f7fb); }
    .mgmt-group-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(142, 68, 173, 0.12); color: #8e44ad; }
    .mgmt-group-copy { min-width: 0; flex: 1; }
    .mgmt-group-copy strong { display: block; }
    .mgmt-group-copy small { color: var(--muted-text); }
    .mgmt-chevron { transition: transform .18s ease; }
    .mgmt-group.is-open .mgmt-chevron { transform: rotate(180deg); }
    .mgmt-group-body { display: none; border-top: 1px solid var(--border-color); }
    .mgmt-group.is-open .mgmt-group-body { display: block; }
    .mgmt-account-head, .mgmt-account { display: grid; grid-template-columns: 28px minmax(0,1.1fr) minmax(9rem,1.3fr) minmax(0,.8fr) minmax(0,1.1fr) minmax(0,.9fr) 88px; gap: 12px; align-items: center; padding: 12px 16px; }
    .mgmt-account-head { color: var(--muted-text); font-size: 0.78rem; font-weight: 700; border-bottom: 1px solid var(--border-color); }
    .mgmt-account { border-bottom: 1px solid var(--border-color); }
    .mgmt-account:last-child { border-bottom: 0; }
    .mgmt-mobile-label { display: none; }
    .mgmt-note { margin: 0; white-space: pre-wrap; color: var(--muted-text); font-size: 0.92rem; }
    .mgmt-row-actions { display: flex; gap: 6px; }
    .status-chip { display: inline-block; margin: 0 4px 4px 0; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
    .chip-success { background: #d4edda; color: #155724; }
    .chip-warning { background: #fff3cd; color: #856404; }
    .chip-muted { background: #eef2f7; color: #475569; }
    @media (max-width: 900px) {
        .mgmt-account-head { display: none; }
        .mgmt-account { grid-template-columns: 28px 1fr; }
        .mgmt-mobile-label { display: block; font-size: 0.72rem; color: var(--muted-text); margin-bottom: 4px; }
        .mgmt-row-actions { grid-column: 2; }
    }
</style>

<script>
    const TABLE = 'trialpurchase';
    initBatchDelete(TABLE);

    function trialFormEls() {
        return {
            form: document.getElementById('trialPurchaseForm'),
            id: document.getElementById('trialId'),
            title: document.getElementById('trialFormTitle'),
            save: document.getElementById('trialSaveBtn'),
            name: document.getElementById('trialName'),
            account: document.getElementById('trialAccount'),
            eventDate: document.getElementById('trialEventDate'),
            firstPrice: document.getElementById('trialFirstPrice'),
            regularPrice: document.getElementById('trialRegularPrice'),
            trialStatus: document.getElementById('trialStatus'),
            purchaseStatus: document.getElementById('trialPurchaseStatus'),
            note: document.getElementById('trialNote')
        };
    }

    function openTrialForm(id, presetName) {
        const els = trialFormEls();
        els.form.style.display = '';
        if (id) {
            const row = document.querySelector('.mgmt-account[data-id="' + id + '"]');
            els.id.value = id;
            els.title.textContent = '編輯帳號紀錄';
            els.save.textContent = '儲存變更';
            els.name.value = row ? (row.dataset.name || '') : '';
            els.account.value = row ? (row.dataset.account || '') : '';
            els.eventDate.value = row ? (row.dataset.eventdate || '') : '';
            els.firstPrice.value = row ? (row.dataset.firstpurchaseprice || '0') : '0';
            els.regularPrice.value = row ? (row.dataset.regularprice || '0') : '0';
            els.trialStatus.value = row ? (row.dataset.trialstatus || 'untried') : 'untried';
            els.purchaseStatus.value = row ? (row.dataset.purchasestatus || 'not_purchased') : 'not_purchased';
            els.note.value = row ? (row.dataset.note || '') : '';
        } else {
            els.id.value = '';
            els.title.textContent = '新增帳號紀錄';
            els.save.textContent = '新增紀錄';
            els.name.value = presetName || '';
            els.account.value = '';
            els.eventDate.value = '';
            els.firstPrice.value = '0';
            els.regularPrice.value = '0';
            els.trialStatus.value = 'untried';
            els.purchaseStatus.value = 'not_purchased';
            els.note.value = '';
        }
        els.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        els.name.focus();
    }

    function closeTrialForm() {
        document.getElementById('trialPurchaseForm').style.display = 'none';
        document.getElementById('trialId').value = '';
    }

    function saveTrialPurchase(event) {
        event.preventDefault();
        const els = trialFormEls();
        const payload = {
            name: els.name.value.trim(),
            account: els.account.value.trim(),
            eventDate: els.eventDate.value || null,
            firstPurchasePrice: Number(els.firstPrice.value || 0),
            regularPrice: Number(els.regularPrice.value || 0),
            trialStatus: els.trialStatus.value,
            purchaseStatus: els.purchaseStatus.value,
            note: els.note.value
        };
        if (!payload.name) {
            alert('請填寫服務名稱');
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
            if (res.success) {
                if (payload.name) localStorage.setItem('fengbro_trial_open_' + payload.name.trim().toLowerCase(), '1');
                location.reload();
            } else {
                alert(res.error || '儲存失敗');
            }
        }).catch(function (err) {
            alert('儲存失敗: ' + (err.message || err));
        });
        return false;
    }

    function deleteTrialPurchase(id) {
        const row = document.querySelector('.mgmt-account[data-id="' + id + '"]');
        const label = row ? ((row.dataset.name || '') + '／' + ((row.dataset.account || '').trim() || '未填帳號')) : '這筆紀錄';
        if (!confirm('確定要刪除「' + label + '」嗎？刪除不能復原。')) return;
        fetch('api.php?action=delete&table=' + encodeURIComponent(TABLE) + '&id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) location.reload();
                else alert(res.error || '刪除失敗');
            });
    }

    function toggleTrialGroup(button) {
        const group = button.closest('.mgmt-group');
        const open = !group.classList.contains('is-open');
        group.classList.toggle('is-open', open);
        button.setAttribute('aria-expanded', String(open));
        const key = group.dataset.trialGroup || '';
        if (key) localStorage.setItem('fengbro_trial_open_' + key, open ? '1' : '0');
    }

    function filterTrialPurchase() {
        const query = (document.getElementById('trialSearchInput')?.value || '').trim().toLowerCase();
        const attention = document.getElementById('trialAttentionFilter')?.value || 'all';
        let visibleGroups = 0;
        document.querySelectorAll('.mgmt-group').forEach(function (group) {
            let visibleAccounts = 0;
            group.querySelectorAll('.mgmt-account').forEach(function (row) {
                const haystack = (row.dataset.search || '') + ' ' + (row.dataset.name || '');
                const matchesQuery = !query || haystack.indexOf(query) !== -1;
                const matchesAttention = attention === 'all'
                    || (attention === 'untried' && row.dataset.trialstatus !== 'tried')
                    || (attention === 'not_purchased' && row.dataset.purchasestatus === 'not_purchased');
                const show = matchesQuery && matchesAttention;
                row.style.display = show ? '' : 'none';
                if (show) visibleAccounts++;
            });
            const showGroup = visibleAccounts > 0;
            group.style.display = showGroup ? '' : 'none';
            if (showGroup) {
                visibleGroups++;
                if (query) group.classList.add('is-open');
            }
        });
        const counter = document.getElementById('trialVisibleCount');
        if (counter) counter.textContent = visibleGroups + ' 個服務';
    }

    document.querySelectorAll('.mgmt-group').forEach(function (group) {
        const key = group.dataset.trialGroup || '';
        if (key && localStorage.getItem('fengbro_trial_open_' + key) === '1') {
            group.classList.add('is-open');
            const toggle = group.querySelector('.mgmt-group-toggle');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
        }
    });

    function handleAdd() {
        openTrialForm();
    }
</script>
