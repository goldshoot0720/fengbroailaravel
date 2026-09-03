<?php
$pageTitle = '鋒兄額度';
$pdo = getConnection();
fengbroEnsureQuotaTable($pdo);

$items = $pdo->query("SELECT * FROM quota ORDER BY name ASC, account ASC, created_at ASC")->fetchAll();
$serviceNames = [];
$groups = [];
$aiCount = 0;

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
    if (fengbroNormalizeQuotaServiceType($item['serviceType'] ?? 'general') === 'ai') {
        $aiCount++;
    }
}

uasort($groups, function ($a, $b) {
    return strnatcasecmp($a['name'], $b['name']);
});
natcasesort($serviceNames);
$serviceNames = array_values($serviceNames);
$serviceCount = count($groups);
$itemCount = count($items);

function fengbroQuotaRatioLabel($value): string
{
    $value = fengbroNonNegativeInt($value);
    return $value > 0 ? $value . '%' : '';
}

function fengbroQuotaExpiry5hLabel($value): string
{
    $value = trim((string) $value);
    return in_array($value, ['上午', '下午'], true) ? $value : '';
}

function fengbroQuotaExpiryWeekLabel($value): string
{
    $value = trim((string) $value);
    return preg_match('/^(0?[1-9]|1[0-2])-(0?[1-9]|[12]\d|3[01])$/', $value) ? $value : '';
}

function fengbroQuotaExpiryMonthLabel($value): string
{
    $value = trim((string) $value);
    return preg_match('/^\d{4}-(0?[1-9]|1[0-2])-(0?[1-9]|[12]\d|3[01])$/', $value) ? $value : '';
}

function fengbroFormatQuotaDate($value): string
{
    if (empty($value)) {
        return '未設定';
    }
    $ts = strtotime((string) $value);
    if ($ts === false) {
        return '日期格式錯誤';
    }
    return date('Y-m-d', $ts);
}
?>

<div class="content-header" style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="margin:0;">鋒兄額度</h1>
        <p class="muted-copy">依服務集中追蹤每個帳號的剩餘額度、比例與到期日；AI 服務可再記錄 5 小時／一週／一月方案的比例與到期。點擊服務名稱即可展開帳號清單。</p>
    </div>
    <span class="count-pill count-pill-quota"><?php echo $itemCount; ?> 筆帳號</span>
</div>

<div class="content-body">
    <div class="mgmt-stat-grid">
        <div class="food-stat-card">
            <span>服務</span>
            <strong><?php echo $serviceCount; ?></strong>
        </div>
        <div class="food-stat-card">
            <span>帳號紀錄</span>
            <strong><?php echo $itemCount; ?></strong>
        </div>
        <div class="food-stat-card food-stat-highlight">
            <span>AI 服務帳號</span>
            <strong><?php echo $aiCount; ?></strong>
        </div>
    </div>

    <div class="action-buttons-bar">
        <button class="btn btn-primary" type="button" onclick="openQuotaForm()"><i class="fas fa-plus"></i> 新增紀錄</button>
        <a class="btn btn-success" href="export.php?table=quota"><i class="fa-solid fa-download"></i> 匯出 CSV</a>
        <?php $csvTable = 'quota'; include 'includes/csv_buttons.php'; ?>
        <?php include 'includes/batch-delete.php'; ?>
    </div>

    <form id="quotaForm" class="card mgmt-form" style="display:none;" onsubmit="return saveQuota(event)">
        <h3 class="card-title" id="quotaFormTitle">新增帳號紀錄</h3>
        <p class="muted-copy">同一服務可建立多筆帳號，清單會自動歸在一起。</p>
        <input type="hidden" id="quotaId" value="">
        <div class="mgmt-form-grid">
            <label>服務名稱 <span class="req">*</span>
                <input class="form-control" id="quotaName" maxlength="100" list="quotaServiceList" required placeholder="例如 ChatGPT">
                <datalist id="quotaServiceList">
                    <?php foreach ($serviceNames as $serviceName): ?>
                        <option value="<?php echo htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endforeach; ?>
                </datalist>
            </label>
            <label>服務類型
                <select class="form-control" id="quotaServiceType" onchange="syncQuotaAiFields()">
                    <option value="general">一般</option>
                    <option value="ai">AI 服務</option>
                </select>
            </label>
            <label>帳號
                <input class="form-control" id="quotaAccount" maxlength="200" placeholder="Email、使用者名稱或辨識名稱">
            </label>
            <label>額度剩餘次數
                <input class="form-control" id="quotaRemaining" type="number" min="0" step="1" value="0">
            </label>
            <label>額度剩餘比例（%）
                <input class="form-control" id="quotaRatio" type="number" min="0" max="100" step="1" value="0">
            </label>
            <label>額度到期日
                <input class="form-control" id="quotaExpiry" type="date">
            </label>
        </div>
        <div id="quotaAiFields" style="display:none;">
            <h3 class="card-title" style="margin-top:18px;">AI 服務方案（比例＋到期）</h3>
            <div class="mgmt-form-grid">
                <label>5 小時比例（%）
                    <input class="form-control" id="quotaRatio5h" type="number" min="0" max="100" step="1" value="0">
                </label>
                <label>5 小時到期（上午／下午）
                    <select class="form-control" id="quotaExpiry5h">
                        <option value="">未填</option>
                        <option value="上午">上午</option>
                        <option value="下午">下午</option>
                    </select>
                </label>
                <label>一週比例（%）
                    <input class="form-control" id="quotaRatioWeek" type="number" min="0" max="100" step="1" value="0">
                </label>
                <label>一週到期（月／日）
                    <input class="form-control" id="quotaExpiryWeek" maxlength="5" placeholder="例如 09-30">
                </label>
                <label>一月比例（%）
                    <input class="form-control" id="quotaRatioMonth" type="number" min="0" max="100" step="1" value="0">
                </label>
                <label>一月到期（西元年／月／日）
                    <input class="form-control" id="quotaExpiryMonth" type="date">
                </label>
            </div>
        </div>
        <div class="mgmt-form-grid" style="margin-top:14px;">
            <label class="mgmt-span-2">備註
                <textarea class="form-control" id="quotaNote" maxlength="3337" rows="3" placeholder="方案限制、計費週期或其他提醒"></textarea>
            </label>
        </div>
        <div class="inline-actions" style="margin-top:16px;">
            <button type="submit" class="btn btn-primary" id="quotaSaveBtn">新增紀錄</button>
            <button type="button" class="btn" onclick="closeQuotaForm()">取消</button>
        </div>
    </form>

    <div class="mgmt-filter-bar">
        <label class="food-search-box">
            <i class="fas fa-search"></i>
            <input type="search" id="quotaSearchInput" class="form-control" placeholder="搜尋服務、帳號或備註" oninput="filterQuota()">
        </label>
        <select id="quotaTypeFilter" class="form-control" onchange="filterQuota()">
            <option value="all">全部類型</option>
            <option value="general">一般服務</option>
            <option value="ai">AI 服務</option>
        </select>
        <span class="food-result-count" id="quotaVisibleCount"><?php echo $serviceCount; ?> 個服務</span>
    </div>

    <?php if (empty($groups)): ?>
        <div class="card" style="text-align:center;color:#999;padding:40px;">尚無額度紀錄。先新增第一個服務與帳號，之後可在服務底下持續加入帳號。</div>
    <?php else: ?>
        <div class="mgmt-group-list">
            <?php foreach ($groups as $groupKey => $group): ?>
                <?php
                $groupAiCount = 0;
                foreach ($group['items'] as $accountItem) {
                    if (fengbroNormalizeQuotaServiceType($accountItem['serviceType'] ?? 'general') === 'ai') {
                        $groupAiCount++;
                    }
                }
                $groupId = 'quota-group-' . substr(sha1($groupKey), 0, 12);
                ?>
                <section class="mgmt-group" data-quota-group="<?php echo htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mgmt-group-head">
                        <button type="button" class="mgmt-group-toggle" aria-expanded="false" aria-controls="<?php echo $groupId; ?>" onclick="toggleQuotaGroup(this)">
                            <span class="mgmt-group-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="mgmt-group-copy">
                                <strong><?php echo htmlspecialchars($group['name']); ?></strong>
                                <small>
                                    <?php echo count($group['items']); ?> 個帳號
                                    <?php if ($groupAiCount > 0): ?> · <?php echo $groupAiCount; ?> 個 AI 帳號<?php endif; ?>
                                </small>
                            </span>
                            <i class="fa-solid fa-chevron-down mgmt-chevron"></i>
                        </button>
                        <button type="button" class="btn btn-sm" onclick="openQuotaForm(null, <?php echo htmlspecialchars(json_encode($group['name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)">
                            <i class="fas fa-plus"></i> 新增帳號
                        </button>
                    </div>
                    <div id="<?php echo $groupId; ?>" class="mgmt-group-body">
                        <div class="mgmt-account-head desktop-only">
                            <span>帳號</span><span>剩餘額度</span><span>到期</span><span>類型</span><span>備註</span><span>操作</span>
                        </div>
                        <?php foreach ($group['items'] as $item): ?>
                            <?php
                            $serviceType = fengbroNormalizeQuotaServiceType($item['serviceType'] ?? 'general');
                            $quotaRemaining = fengbroNonNegativeInt($item['quotaRemaining'] ?? 0);
                            $quotaRatio = fengbroQuotaRatioLabel($item['quotaRatio'] ?? 0);
                            $quotaExpiry = fengbroFormatQuotaDate($item['quotaExpiry'] ?? '');
                            $isAi = $serviceType === 'ai';
                            $aiPlans = [];
                            if ($isAi) {
                                $plans = [
                                    ['key' => '5h', 'prefix' => '5 小時', 'ratio' => fengbroQuotaRatioLabel($item['ratio5h'] ?? 0), 'expiry' => fengbroQuotaExpiry5hLabel($item['expiry5h'] ?? '')],
                                    ['key' => 'week', 'prefix' => '一週', 'ratio' => fengbroQuotaRatioLabel($item['ratioWeek'] ?? 0), 'expiry' => fengbroQuotaExpiryWeekLabel($item['expiryWeek'] ?? '')],
                                    ['key' => 'month', 'prefix' => '一月', 'ratio' => fengbroQuotaRatioLabel($item['ratioMonth'] ?? 0), 'expiry' => fengbroQuotaExpiryMonthLabel($item['expiryMonth'] ?? '')],
                                ];
                                foreach ($plans as $plan) {
                                    if ($plan['ratio'] !== '' || $plan['expiry'] !== '') {
                                        $aiPlans[] = $plan;
                                    }
                                }
                            }
                            $accountLabel = trim((string) ($item['account'] ?? '')) !== '' ? $item['account'] : '未填帳號';
                            $searchBlob = strtolower(($item['name'] ?? '') . ' ' . ($item['account'] ?? '') . ' ' . ($item['note'] ?? ''));
                            ?>
                            <article class="mgmt-account" data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-servicetype="<?php echo htmlspecialchars($serviceType, ENT_QUOTES, 'UTF-8'); ?>"
                                data-account="<?php echo htmlspecialchars($item['account'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-quotaremaining="<?php echo (int) $quotaRemaining; ?>"
                                data-quotaratio="<?php echo (int) ($item['quotaRatio'] ?? 0); ?>"
                                data-quotaexpiry="<?php echo !empty($item['quotaExpiry']) ? date('Y-m-d', strtotime($item['quotaExpiry'])) : ''; ?>"
                                data-ratio5h="<?php echo (int) ($item['ratio5h'] ?? 0); ?>"
                                data-expiry5h="<?php echo htmlspecialchars($item['expiry5h'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-ratioweek="<?php echo (int) ($item['ratioWeek'] ?? 0); ?>"
                                data-expiryweek="<?php echo htmlspecialchars($item['expiryWeek'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-ratiomonth="<?php echo (int) ($item['ratioMonth'] ?? 0); ?>"
                                data-expirymonth="<?php echo htmlspecialchars($item['expiryMonth'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-note="<?php echo htmlspecialchars($item['note'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>" onchange="toggleSelectItem(this)">
                                <div>
                                    <span class="mgmt-mobile-label">帳號</span>
                                    <strong class="private-value"><?php echo htmlspecialchars($accountLabel); ?></strong>
                                </div>
                                <div>
                                    <span class="mgmt-mobile-label">剩餘額度</span>
                                    <div class="quota-remaining"><?php echo number_format($quotaRemaining); ?> 次</div>
                                    <?php if ($quotaRatio !== ''): ?>
                                        <div class="quota-ratio">比例 <?php echo htmlspecialchars($quotaRatio); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="mgmt-mobile-label">到期</span>
                                    <div class="quota-expiry"><i class="fa-regular fa-calendar"></i> <?php echo htmlspecialchars($quotaExpiry); ?></div>
                                    <?php if (!empty($aiPlans)): ?>
                                        <div class="quota-plan-chips">
                                            <?php foreach ($aiPlans as $plan): ?>
                                                <span class="quota-plan-chip"><?php echo htmlspecialchars($plan['prefix'] . ($plan['ratio'] !== '' ? ' ' . $plan['ratio'] : '') . ($plan['ratio'] !== '' && $plan['expiry'] !== '' ? ' · ' : '') . ($plan['expiry'] !== '' ? ' ' . $plan['expiry'] : '')); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="mgmt-mobile-label">類型</span>
                                    <span class="status-chip <?php echo $isAi ? 'chip-info' : 'chip-success'; ?>"><?php echo $isAi ? 'AI 服務' : '一般'; ?></span>
                                </div>
                                <div>
                                    <span class="mgmt-mobile-label">備註</span>
                                    <p class="mgmt-note"><?php echo trim((string) ($item['note'] ?? '')) !== '' ? nl2br(htmlspecialchars($item['note'])) : '—'; ?></p>
                                </div>
                                <div class="mgmt-row-actions">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openQuotaForm('<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>')" title="編輯"><i class="fas fa-pen"></i></button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteQuota('<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>')" title="刪除"><i class="fas fa-trash"></i></button>
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
    .count-pill-quota { background: linear-gradient(135deg, #7c3aed, #2563eb); }
    .mgmt-stat-grid, .food-ops-panel { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .food-stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 18px; padding: 14px 16px; box-shadow: 0 12px 26px var(--shadow); }
    .food-stat-card span { display: block; color: var(--muted-text); font-size: 0.82rem; margin-bottom: 6px; }
    .food-stat-card strong { font-size: 1.35rem; }
    .food-stat-highlight strong { color: #7c3aed; }
    .mgmt-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
    .mgmt-form-grid label { display: grid; gap: 6px; font-weight: 600; }
    .mgmt-span-2 { grid-column: 1 / -1; }
    .req { color: #e74c3c; }
    .mgmt-filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 8px 0 18px; }
    .food-search-box { position: relative; flex: 1 1 260px; }
    .food-search-box i { position: absolute; top: 50%; left: 12px; transform: translateY(-50%); color: var(--muted-text); }
    .food-search-box input { padding-left: 38px; }
    .mgmt-filter-bar select { min-width: 150px; }
    .mgmt-group { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 18px; margin-bottom: 12px; overflow: hidden; }
    .mgmt-group-head { display: flex; gap: 8px; align-items: center; padding: 10px 12px; }
    .mgmt-group-toggle { flex: 1; display: flex; align-items: center; gap: 12px; border: 0; background: transparent; text-align: left; cursor: pointer; color: inherit; padding: 6px; border-radius: 12px; }
    .mgmt-group-toggle:hover { background: var(--table-header-bg, #f4f7fb); }
    .mgmt-group-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(124, 58, 237, 0.12); color: #7c3aed; }
    .mgmt-group-copy { min-width: 0; flex: 1; }
    .mgmt-group-copy strong { display: block; }
    .mgmt-group-copy small { color: var(--muted-text); }
    .mgmt-chevron { transition: transform .18s ease; }
    .mgmt-group.is-open .mgmt-chevron { transform: rotate(180deg); }
    .mgmt-group-body { display: none; border-top: 1px solid var(--border-color); }
    .mgmt-group.is-open .mgmt-group-body { display: block; }
    .mgmt-account-head, .mgmt-account { display: grid; grid-template-columns: 28px minmax(0,1.1fr) minmax(8rem,1fr) minmax(10rem,1.2fr) minmax(0,.8fr) minmax(0,1fr) 88px; gap: 12px; align-items: center; padding: 12px 16px; }
    .mgmt-account-head { color: var(--muted-text); font-size: 0.78rem; font-weight: 700; border-bottom: 1px solid var(--border-color); }
    .mgmt-account { border-bottom: 1px solid var(--border-color); }
    .mgmt-account:last-child { border-bottom: 0; }
    .mgmt-mobile-label { display: none; }
    .mgmt-note { margin: 0; white-space: pre-wrap; color: var(--muted-text); font-size: 0.92rem; }
    .mgmt-row-actions { display: flex; gap: 6px; }
    .status-chip { display: inline-block; margin: 0 4px 4px 0; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
    .chip-success { background: #d4edda; color: #155724; }
    .chip-warning { background: #fff3cd; color: #856404; }
    .chip-info { background: #dbeafe; color: #1e40af; }
    .chip-muted { background: #eef2f7; color: #475569; }
    .quota-remaining { font-weight: 800; color: #2563eb; font-size: 1.05rem; }
    .quota-ratio { color: var(--muted-text); font-size: 0.85rem; margin-top: 2px; }
    .quota-expiry { color: var(--text-color); font-size: 0.9rem; }
    .quota-plan-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
    .quota-plan-chip { background: rgba(124, 58, 237, 0.1); color: #6d28d9; border-radius: 999px; padding: 2px 8px; font-size: 0.75rem; font-weight: 600; }
    @media (max-width: 900px) {
        .mgmt-account-head { display: none; }
        .mgmt-account { grid-template-columns: 28px 1fr; }
        .mgmt-mobile-label { display: block; font-size: 0.72rem; color: var(--muted-text); margin-bottom: 4px; }
        .mgmt-row-actions { grid-column: 2; }
    }
</style>

<script>
    const TABLE = 'quota';
    initBatchDelete(TABLE);

    function quotaFormEls() {
        return {
            form: document.getElementById('quotaForm'),
            id: document.getElementById('quotaId'),
            title: document.getElementById('quotaFormTitle'),
            save: document.getElementById('quotaSaveBtn'),
            name: document.getElementById('quotaName'),
            serviceType: document.getElementById('quotaServiceType'),
            account: document.getElementById('quotaAccount'),
            remaining: document.getElementById('quotaRemaining'),
            ratio: document.getElementById('quotaRatio'),
            expiry: document.getElementById('quotaExpiry'),
            ratio5h: document.getElementById('quotaRatio5h'),
            expiry5h: document.getElementById('quotaExpiry5h'),
            ratioWeek: document.getElementById('quotaRatioWeek'),
            expiryWeek: document.getElementById('quotaExpiryWeek'),
            ratioMonth: document.getElementById('quotaRatioMonth'),
            expiryMonth: document.getElementById('quotaExpiryMonth'),
            note: document.getElementById('quotaNote')
        };
    }

    function syncQuotaAiFields() {
        const els = quotaFormEls();
        const isAi = els.serviceType.value === 'ai';
        document.getElementById('quotaAiFields').style.display = isAi ? '' : 'none';
        if (!isAi) {
            els.ratio5h.value = '0';
            els.expiry5h.value = '';
            els.ratioWeek.value = '0';
            els.expiryWeek.value = '';
            els.ratioMonth.value = '0';
            els.expiryMonth.value = '';
        }
    }

    function openQuotaForm(id, presetName) {
        const els = quotaFormEls();
        els.form.style.display = '';
        if (id) {
            const row = document.querySelector('.mgmt-account[data-id="' + id + '"]');
            els.id.value = id;
            els.title.textContent = '編輯帳號紀錄';
            els.save.textContent = '儲存變更';
            els.name.value = row ? (row.dataset.name || '') : '';
            els.serviceType.value = row ? (row.dataset.servicetype || 'general') : 'general';
            els.account.value = row ? (row.dataset.account || '') : '';
            els.remaining.value = row ? (row.dataset.quotaremaining || '0') : '0';
            els.ratio.value = row ? (row.dataset.quotaratio || '0') : '0';
            els.expiry.value = row ? (row.dataset.quotaexpiry || '') : '';
            els.ratio5h.value = row ? (row.dataset.ratio5h || '0') : '0';
            els.expiry5h.value = row ? (row.dataset.expiry5h || '') : '';
            els.ratioWeek.value = row ? (row.dataset.ratioweek || '0') : '0';
            els.expiryWeek.value = row ? (row.dataset.expiryweek || '') : '';
            els.ratioMonth.value = row ? (row.dataset.ratiomonth || '0') : '0';
            els.expiryMonth.value = row ? (row.dataset.expirymonth || '') : '';
            els.note.value = row ? (row.dataset.note || '') : '';
        } else {
            els.id.value = '';
            els.title.textContent = '新增帳號紀錄';
            els.save.textContent = '新增紀錄';
            els.name.value = presetName || '';
            els.serviceType.value = 'general';
            els.account.value = '';
            els.remaining.value = '0';
            els.ratio.value = '0';
            els.expiry.value = '';
            els.ratio5h.value = '0';
            els.expiry5h.value = '';
            els.ratioWeek.value = '0';
            els.expiryWeek.value = '';
            els.ratioMonth.value = '0';
            els.expiryMonth.value = '';
            els.note.value = '';
        }
        syncQuotaAiFields();
        els.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        els.name.focus();
    }

    function closeQuotaForm() {
        document.getElementById('quotaForm').style.display = 'none';
        document.getElementById('quotaId').value = '';
    }

    function saveQuota(event) {
        event.preventDefault();
        const els = quotaFormEls();
        const payload = {
            name: els.name.value.trim(),
            serviceType: els.serviceType.value,
            account: els.account.value.trim(),
            quotaRemaining: Number(els.remaining.value || 0),
            quotaRatio: Number(els.ratio.value || 0),
            quotaExpiry: els.expiry.value || null,
            note: els.note.value
        };
        if (payload.serviceType === 'ai') {
            payload.ratio5h = Number(els.ratio5h.value || 0);
            payload.expiry5h = els.expiry5h.value;
            payload.ratioWeek = Number(els.ratioWeek.value || 0);
            payload.expiryWeek = els.expiryWeek.value.trim();
            payload.ratioMonth = Number(els.ratioMonth.value || 0);
            payload.expiryMonth = els.expiryMonth.value;
        }
        if (!payload.name) {
            alert('請填寫服務名稱');
            return false;
        }
        if (payload.expiryWeek && !/^(0?[1-9]|1[0-2])-(0?[1-9]|[12]\d|3[01])$/.test(payload.expiryWeek)) {
            alert('一週到期格式需為 月-日（例如 09-30）');
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
                if (payload.name) localStorage.setItem('fengbro_quota_open_' + payload.name.trim().toLowerCase(), '1');
                location.reload();
            } else {
                alert(res.error || '儲存失敗');
            }
        }).catch(function (err) {
            alert('儲存失敗: ' + (err.message || err));
        });
        return false;
    }

    function deleteQuota(id) {
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

    function toggleQuotaGroup(button) {
        const group = button.closest('.mgmt-group');
        const open = !group.classList.contains('is-open');
        group.classList.toggle('is-open', open);
        button.setAttribute('aria-expanded', String(open));
        const key = group.dataset.quotaGroup || '';
        if (key) localStorage.setItem('fengbro_quota_open_' + key, open ? '1' : '0');
    }

    function filterQuota() {
        const query = (document.getElementById('quotaSearchInput')?.value || '').trim().toLowerCase();
        const type = document.getElementById('quotaTypeFilter')?.value || 'all';
        let visibleGroups = 0;
        document.querySelectorAll('.mgmt-group').forEach(function (group) {
            let visibleAccounts = 0;
            group.querySelectorAll('.mgmt-account').forEach(function (row) {
                const haystack = (row.dataset.search || '') + ' ' + (row.dataset.name || '');
                const matchesQuery = !query || haystack.indexOf(query) !== -1;
                const matchesType = type === 'all' || row.dataset.servicetype === type;
                const show = matchesQuery && matchesType;
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
        const counter = document.getElementById('quotaVisibleCount');
        if (counter) counter.textContent = visibleGroups + ' 個服務';
    }

    document.querySelectorAll('.mgmt-group').forEach(function (group) {
        const key = group.dataset.quotaGroup || '';
        if (key && localStorage.getItem('fengbro_quota_open_' + key) === '1') {
            group.classList.add('is-open');
            const toggle = group.querySelector('.mgmt-group-toggle');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
        }
    });

    function handleAdd() {
        openQuotaForm();
    }
</script>
