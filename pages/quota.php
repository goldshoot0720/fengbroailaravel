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
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '';
}

function fengbroQuotaExpiryWeekLabel($value): string
{
    $value = trim((string) $value);
    return preg_match('/^\d{4}-(0?[1-9]|1[0-2])-(0?[1-9]|[12]\d|3[01])$/', $value) ? $value : '';
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

// 前端匯入用的既有紀錄清單（name + account 小寫鍵 → id）
$existingIndex = [];
foreach ($items as $item) {
    $existingIndex[strtolower(trim((string) ($item['name'] ?? ''))) . "\0" . strtolower(trim((string) ($item['account'] ?? '')))] = $item['id'];
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
        <button class="btn btn-success" type="button" onclick="exportQuotaCsv()" title="匯出目前全部額度紀錄為 CSV"><i class="fa-solid fa-download"></i> 匯出 CSV</button>
        <button class="btn" type="button" onclick="document.getElementById('quotaCsvFile').click()" title="從 CSV 匯入額度紀錄（相同服務與帳號會更新）"><i class="fa-solid fa-upload"></i> 匯入 CSV</button>
        <input type="file" id="quotaCsvFile" accept=".csv,text/csv" style="display:none;" onchange="handleQuotaCsvFile(this)">
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
                <label>5 小時到期
                    <input class="form-control" id="quotaExpiry5h" type="time" step="60" placeholder="14:30">
                </label>
                <label>一週比例（%）
                    <input class="form-control" id="quotaRatioWeek" type="number" min="0" max="100" step="1" value="0">
                </label>
                <label>一週到期（西元年／月／日）
                    <input class="form-control" id="quotaExpiryWeek" type="date">
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
                                    <button type="button" class="btn btn-sm" onclick="copyQuota('<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>')" title="複製此帳號紀錄（預先填好欄位，供你確認後新增）"><i class="fa-solid fa-copy"></i></button>
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

    <div id="quotaImportOverlay" class="quota-import-overlay" style="display:none;">
        <div class="quota-import-panel" role="dialog" aria-modal="true" aria-labelledby="quotaImportTitle">
            <h3 class="card-title" id="quotaImportTitle">匯入 CSV 預覽</h3>
            <p class="muted-copy">相同服務名稱與帳號會更新既有紀錄，其餘新增。有格式錯誤時不會寫入。</p>
            <div id="quotaImportResult" class="quota-import-result" style="display:none;"></div>
            <div id="quotaImportErrors" class="quota-import-errors" style="display:none;"></div>
            <div id="quotaImportRows" class="quota-import-rows"></div>
            <div class="inline-actions" style="margin-top:16px;display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn" id="quotaImportCancelBtn" onclick="closeQuotaImport()">取消</button>
                <button type="button" class="btn btn-primary" id="quotaImportConfirmBtn" onclick="executeQuotaImport()">確認匯入</button>
            </div>
        </div>
    </div>
</div>

<style>
    .muted-copy { margin: 8px 0 0; color: var(--muted-text); line-height: 1.6; }
    .count-pill { color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
    .count-pill-quota { background: #b4552f; }
    .mgmt-stat-grid, .food-ops-panel { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .food-stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 18px; padding: 14px 16px; box-shadow: 0 12px 26px var(--shadow); }
    .food-stat-card span { display: block; color: var(--muted-text); font-size: 0.82rem; margin-bottom: 6px; }
    .food-stat-card strong { font-size: 1.35rem; }
    .food-stat-highlight strong { color: #b4552f; }
    .mgmt-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
    .mgmt-form-grid label { display: grid; gap: 6px; font-weight: 600; }
    .mgmt-span-2 { grid-column: 1 / -1; }
    .req { color: #c1554a; }
    .mgmt-filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 8px 0 18px; }
    .food-search-box { position: relative; flex: 1 1 260px; }
    .food-search-box i { position: absolute; top: 50%; left: 12px; transform: translateY(-50%); color: var(--muted-text); }
    .food-search-box input { padding-left: 38px; }
    .mgmt-filter-bar select { min-width: 150px; }
    .mgmt-group { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 18px; margin-bottom: 12px; overflow: hidden; }
    .mgmt-group-head { display: flex; gap: 8px; align-items: center; padding: 10px 12px; }
    .mgmt-group-toggle { flex: 1; display: flex; align-items: center; gap: 12px; border: 0; background: transparent; text-align: left; cursor: pointer; color: inherit; padding: 6px; border-radius: 12px; }
    .mgmt-group-toggle:hover { background: var(--table-header-bg, #faf9f5); }
    .mgmt-group-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(180, 85, 47, 0.12); color: #b4552f; }
    .mgmt-group-copy { min-width: 0; flex: 1; }
    .mgmt-group-copy strong { display: block; }
    .mgmt-group-copy small { color: var(--muted-text); }
    .mgmt-chevron { transition: transform .18s ease; }
    .mgmt-group.is-open .mgmt-chevron { transform: rotate(180deg); }
    .mgmt-group-body { display: none; border-top: 1px solid var(--border-color); }
    .mgmt-group.is-open .mgmt-group-body { display: block; }
    .mgmt-account-head, .mgmt-account { display: grid; grid-template-columns: 28px minmax(0,1.1fr) minmax(8rem,1fr) minmax(10rem,1.2fr) minmax(0,.8fr) minmax(0,1fr) 132px; gap: 12px; align-items: center; padding: 12px 16px; }
    .mgmt-account-head { color: var(--muted-text); font-size: 0.78rem; font-weight: 700; border-bottom: 1px solid var(--border-color); }
    .mgmt-account { border-bottom: 1px solid var(--border-color); }
    .mgmt-account:last-child { border-bottom: 0; }
    .mgmt-mobile-label { display: none; }
    .mgmt-note { margin: 0; white-space: pre-wrap; color: var(--muted-text); font-size: 0.92rem; }
    .mgmt-row-actions { display: flex; gap: 6px; }
    .status-chip { display: inline-block; margin: 0 4px 4px 0; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
    .chip-success { background: #e3efe5; color: #2b5c40; }
    .chip-warning { background: #f7ecd9; color: #6f5518; }
    .chip-info { background: #f6e5db; color: #9c4726; }
    .chip-muted { background: #f0eee6; color: #57534a; }
    .quota-remaining { font-weight: 800; color: #c1613d; font-size: 1.05rem; }
    .quota-ratio { color: var(--muted-text); font-size: 0.85rem; margin-top: 2px; }
    .quota-expiry { color: var(--text-color); font-size: 0.9rem; }
    .quota-plan-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
    .quota-plan-chip { background: rgba(180, 85, 47, 0.1); color: #b4552f; border-radius: 999px; padding: 2px 8px; font-size: 0.75rem; font-weight: 600; }
    .quota-import-overlay { position: fixed; inset: 0; z-index: 120; background: rgba(30, 26, 20, 0.55); display: flex; align-items: center; justify-content: center; padding: 16px; }
    .quota-import-panel { width: min(560px, 100%); max-height: 85vh; overflow-y: auto; background: var(--card-bg); border-radius: 18px; padding: 22px 24px; box-shadow: 0 24px 60px rgba(0,0,0,.28); }
    .quota-import-result { margin-top: 10px; padding: 8px 12px; border-radius: 10px; background: #e3efe5; color: #2b5c40; font-weight: 600; }
    .quota-import-errors { margin-top: 10px; padding: 8px 12px; border-radius: 10px; background: #f6e0dd; color: #6e2a23; max-height: 140px; overflow-y: auto; font-size: 0.85rem; }
    .quota-import-rows { margin-top: 10px; }
    .quota-import-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 7px 10px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; }
    .quota-import-row:last-child { border-bottom: 0; }
    .quota-import-row .qname { font-weight: 600; }
    .quota-import-row .qstatus-new { color: #2b5c40; font-size: 0.75rem; font-weight: 700; background: #e3efe5; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
    .quota-import-row .qstatus-update { color: #6f5518; font-size: 0.75rem; font-weight: 700; background: #f7ecd9; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
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

    const QUOTA_EXISTING_INDEX = <?php echo json_encode($existingIndex); ?>;
    const QUOTA_HEADER_ALIASES = {
        'name': 'name', '服務': 'name', '服務名稱': 'name',
        'servicetype': 'serviceType', 'service_type': 'serviceType', '服務類型': 'serviceType', '類型': 'serviceType',
        'account': 'account', '帳號': 'account',
        'quotaremaining': 'quotaRemaining', 'quota_remaining': 'quotaRemaining', 'remaining': 'quotaRemaining',
        '額度剩餘次數': 'quotaRemaining', '剩餘次數': 'quotaRemaining', '剩餘額度': 'quotaRemaining',
        'quotaratio': 'quotaRatio', 'quota_ratio': 'quotaRatio', 'ratio': 'quotaRatio',
        '額度剩餘比例': 'quotaRatio', '剩餘比例': 'quotaRatio',
        'quotaexpiry': 'quotaExpiry', 'quota_expiry': 'quotaExpiry', '額度到期日': 'quotaExpiry', '到期日': 'quotaExpiry',
        'ratio5h': 'ratio5h', '5h': 'ratio5h', '5小時比例': 'ratio5h', '5h比例': 'ratio5h',
        'expiry5h': 'expiry5h', '5h到期': 'expiry5h', '5小時到期': 'expiry5h',
        'ratioweek': 'ratioWeek', 'ratio_week': 'ratioWeek', '一週比例': 'ratioWeek', '週比例': 'ratioWeek',
        'expiryweek': 'expiryWeek', 'expiry_week': 'expiryWeek', '一週到期': 'expiryWeek', '週到期': 'expiryWeek',
        'ratiomonth': 'ratioMonth', 'ratio_month': 'ratioMonth', '一月比例': 'ratioMonth', '月比例': 'ratioMonth',
        'expirymonth': 'expiryMonth', 'expiry_month': 'expiryMonth', '一月到期': 'expiryMonth', '月到期': 'expiryMonth',
        'note': 'note', '備註': 'note'
    };
    const QUOTA_CSV_HEADERS = ['name', 'serviceType', 'account', 'quotaRemaining', 'quotaRatio', 'quotaExpiry', 'ratio5h', 'expiry5h', 'ratioWeek', 'expiryWeek', 'ratioMonth', 'expiryMonth', 'note'];

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

    function fillQuotaFormFromRow(row, editing) {
        const els = quotaFormEls();
        els.id.value = editing ? row.dataset.id : '';
        els.title.textContent = editing ? '編輯帳號紀錄' : '新增帳號紀錄';
        els.save.textContent = editing ? '儲存變更' : '新增紀錄';
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
        syncQuotaAiFields();
        els.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        els.name.focus();
    }

    function openQuotaForm(id, presetName) {
        const els = quotaFormEls();
        els.form.style.display = '';
        const row = id ? document.querySelector('.mgmt-account[data-id="' + id + '"]') : null;
        if (row) {
            fillQuotaFormFromRow(row, true);
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
            syncQuotaAiFields();
            els.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            els.name.focus();
        }
    }

    function copyQuota(id) {
        const row = document.querySelector('.mgmt-account[data-id="' + id + '"]');
        if (!row) return;
        const els = quotaFormEls();
        els.form.style.display = '';
        els.id.value = '';
        els.title.textContent = '新增帳號紀錄（複製）';
        els.save.textContent = '新增紀錄';
        els.name.value = (row.dataset.name || '') + ' (複製)';
        els.serviceType.value = row.dataset.servicetype || 'general';
        els.account.value = row.dataset.account || '';
        els.remaining.value = row.dataset.quotaremaining || '0';
        els.ratio.value = row.dataset.quotaratio || '0';
        els.expiry.value = row.dataset.quotaexpiry || '';
        els.ratio5h.value = row.dataset.ratio5h || '0';
        els.expiry5h.value = row.dataset.expiry5h || '';
        els.ratioWeek.value = row.dataset.ratioweek || '0';
        els.expiryWeek.value = row.dataset.expiryweek || '';
        els.ratioMonth.value = row.dataset.ratiomonth || '0';
        els.expiryMonth.value = row.dataset.expirymonth || '';
        els.note.value = row.dataset.note || '';
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
            payload.expiryWeek = els.expiryWeek.value || null;
            payload.ratioMonth = Number(els.ratioMonth.value || 0);
            payload.expiryMonth = els.expiryMonth.value || null;
        }
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

    // ---- CSV 匯出 ----
    function quotaCsvEscape(value) {
        const s = value == null ? '' : String(value);
        if (s.indexOf(',') !== -1 || s.indexOf('"') !== -1 || s.indexOf('\n') !== -1) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    function exportQuotaCsv() {
        const rows = document.querySelectorAll('.mgmt-account');
        if (rows.length === 0) {
            alert('尚無可匯出的額度紀錄');
            return;
        }
        const lines = [QUOTA_CSV_HEADERS.join(',')];
        rows.forEach(function (row) {
            lines.push([
                row.dataset.name || '',
                row.dataset.servicetype || 'general',
                row.dataset.account || '',
                row.dataset.quotaremaining || '0',
                row.dataset.quotaratio || '0',
                row.dataset.quotaexpiry || '',
                row.dataset.ratio5h || '0',
                row.dataset.expiry5h || '',
                row.dataset.ratioweek || '0',
                row.dataset.expiryweek || '',
                row.dataset.ratiomonth || '0',
                row.dataset.expirymonth || '',
                row.dataset.note || ''
            ].map(quotaCsvEscape).join(','));
        });
        const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'fengbro-quota-' + new Date().toISOString().slice(0, 10) + '.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    }

    // ---- CSV 匯入 ----
    let quotaImportData = [];
    let quotaImportErrors = [];

    function quotaNormalizeKey(text) {
        return (text || '').trim().toLowerCase();
    }

    function quotaMapHeader(raw) {
        const key = quotaNormalizeKey(raw).replace(/\s+/g, '').replace(/_/g, '');
        return QUOTA_HEADER_ALIASES[key] || QUOTA_HEADER_ALIASES[quotaNormalizeKey(raw)] || null;
    }

    function quotaParseNonNegative(value) {
        const s = (value || '').trim();
        if (s === '') return 0;
        if (!/^\d+$/.test(s)) return null;
        return Number(s);
    }

    function quotaNormalizeCsvDate(value) {
        const s = (value || '').trim();
        if (!s) return '';
        let iso = '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
            iso = s;
        } else {
            const m = s.match(/^(\d{4})[\/.](\d{1,2})[\/.](\d{1,2})$/);
            if (!m) return null;
            iso = m[1] + '-' + String(Number(m[2])).padStart(2, '0') + '-' + String(Number(m[3])).padStart(2, '0');
        }
        const d = new Date(iso + 'T00:00:00.000Z');
        if (isNaN(d.getTime()) || d.toISOString().slice(0, 10) !== iso) return null;
        return iso;
    }

    function handleQuotaCsvFile(input) {
        const file = input.files && input.files[0];
        input.value = '';
        if (!file) return;
        if (!/\.csv$/i.test(file.name)) {
            alert('請選擇 CSV 檔案');
            return;
        }
        const reader = new FileReader();
        reader.onload = function () {
            const preview = parseQuotaCsvText(String(reader.result || ''));
            quotaImportData = preview.data;
            quotaImportErrors = preview.errors;
            renderQuotaImportPreview();
        };
        reader.onerror = function () { alert('讀取 CSV 檔案失敗'); };
        reader.readAsText(file, 'UTF-8');
    }

    function parseQuotaCsvText(text) {
        const errors = [];
        const data = [];
        const clean = String(text).replace(/^\uFEFF/, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        const lines = clean.split('\n').filter(function (line) { return line.trim() !== ''; });
        if (lines.length < 2) {
            errors.push('CSV 檔案至少需要表頭和一行資料');
            return { data: data, errors: errors };
        }
        const parseLine = function (line) {
            const out = [];
            let cur = '';
            let inQ = false;
            for (let i = 0; i < line.length; i++) {
                const ch = line[i];
                if (inQ) {
                    if (ch === '"') {
                        if (line[i + 1] === '"') { cur += '"'; i++; }
                        else inQ = false;
                    } else cur += ch;
                } else if (ch === '"') {
                    inQ = true;
                } else if (ch === ',') {
                    out.push(cur); cur = '';
                } else {
                    cur += ch;
                }
            }
            out.push(cur);
            return out;
        };

        const headers = parseLine(lines[0]);
        const colIndex = {};
        headers.forEach(function (h, i) {
            const mapped = quotaMapHeader(h);
            if (mapped && colIndex[mapped] == null) colIndex[mapped] = i;
        });
        if (colIndex.name == null) {
            errors.push('表頭缺少必要欄位 name（服務名稱）');
            return { data: data, errors: errors };
        }

        for (let i = 1; i < lines.length; i++) {
            const values = parseLine(lines[i]);
            const cell = function (header) {
                const idx = colIndex[header];
                return idx == null ? '' : (values[idx] || '');
            };
            const lineNo = i + 1;
            const fail = function (msg) { errors.push('第 ' + lineNo + ' 行: ' + msg); };

            const name = cell('name').trim();
            if (!name) { fail('name 欄位不能為空'); continue; }
            if (name.length > 100) { fail('服務名稱最多 100 個字元'); continue; }

            const typeRaw = cell('serviceType').trim();
            let serviceType = 'general';
            if (typeRaw) {
                const t = quotaNormalizeKey(typeRaw).replace(/\s+/g, '').replace(/_/g, '');
                if (t === 'ai' || t === 'ai服務' || t === 'ai服务') serviceType = 'ai';
                else if (t === 'general' || t === '一般' || t === '一般服務') serviceType = 'general';
                else { fail('服務類型不正確'); continue; }
            }

            const account = cell('account').trim();
            if (account.length > 200) { fail('帳號最多 200 個字元'); continue; }

            const quotaRemaining = quotaParseNonNegative(cell('quotaRemaining'));
            if (quotaRemaining === null) { fail('額度剩餘次數必須是 0 以上的整數'); continue; }
            const quotaRatio = quotaParseNonNegative(cell('quotaRatio'));
            if (quotaRatio === null) { fail('額度剩餘比例必須是 0 以上的整數'); continue; }

            const quotaExpiry = quotaNormalizeCsvDate(cell('quotaExpiry'));
            if (quotaExpiry === null) { fail('額度到期日格式不正確'); continue; }

            const note = cell('note').trim();
            if (note.length > 3337) { fail('備註最多 3337 個字元'); continue; }

            const row = {
                name: name, serviceType: serviceType, account: account,
                quotaRemaining: quotaRemaining, quotaRatio: quotaRatio,
                quotaExpiry: quotaExpiry, note: note,
                ratio5h: 0, expiry5h: '', ratioWeek: 0, expiryWeek: '', ratioMonth: 0, expiryMonth: ''
            };

            if (serviceType === 'ai') {
                const ratio5h = quotaParseNonNegative(cell('ratio5h'));
                if (ratio5h === null) { fail('5 小時比例必須是 0 以上的整數'); continue; }
                const ratioWeek = quotaParseNonNegative(cell('ratioWeek'));
                if (ratioWeek === null) { fail('一週比例必須是 0 以上的整數'); continue; }
                const ratioMonth = quotaParseNonNegative(cell('ratioMonth'));
                if (ratioMonth === null) { fail('一月比例必須是 0 以上的整數'); continue; }

                const expiry5h = cell('expiry5h').trim();
                if (expiry5h && !/^([01]\d|2[0-3]):[0-5]\d$/.test(expiry5h)) {
                    fail('5 小時到期需為 HH:mm（24 小時制，例如 14:30）'); continue;
                }

                const expiryWeekRaw = cell('expiryWeek').trim();
                let expiryWeek = '';
                if (expiryWeekRaw) {
                    const wd = quotaNormalizeCsvDate(expiryWeekRaw);
                    if (wd === null) { fail('一週到期格式需為 西元年-月-日（例如 2026-09-30）'); continue; }
                    expiryWeek = wd;
                }

                const expiryMonthRaw = cell('expiryMonth').trim();
                let expiryMonth = '';
                if (expiryMonthRaw) {
                    const md = quotaNormalizeCsvDate(expiryMonthRaw);
                    if (md === null) { fail('一月到期格式需為 西元年-月-日（例如 2026-12-31）'); continue; }
                    expiryMonth = md;
                }

                row.ratio5h = ratio5h; row.expiry5h = expiry5h;
                row.ratioWeek = ratioWeek; row.expiryWeek = expiryWeek;
                row.ratioMonth = ratioMonth; row.expiryMonth = expiryMonth;
            }
            data.push(row);
        }
        return { data: data, errors: errors };
    }

    function quotaRowKey(row) {
        return quotaNormalizeKey(row.name) + '\u0000' + quotaNormalizeKey(row.account);
    }

    function renderQuotaImportPreview() {
        const overlay = document.getElementById('quotaImportOverlay');
        overlay.style.display = 'flex';
        document.getElementById('quotaImportResult').style.display = 'none';
        const errorsEl = document.getElementById('quotaImportErrors');
        if (quotaImportErrors.length > 0) {
            errorsEl.style.display = '';
            errorsEl.innerHTML = '<strong>格式錯誤（不會寫入）</strong><br>' + quotaImportErrors.map(function (e) { return '• ' + e; }).join('<br>');
        } else {
            errorsEl.style.display = 'none';
        }
        const rowsEl = document.getElementById('quotaImportRows');
        if (quotaImportData.length === 0) {
            rowsEl.innerHTML = '<p style="color:var(--muted-text);margin:10px 0;">沒有可匯入的資料列。</p>';
        } else {
            rowsEl.innerHTML = '<p style="font-weight:600;margin:10px 0 4px;">將匯入 ' + quotaImportData.length + ' 筆</p>' +
                quotaImportData.map(function (row) {
                    const key = quotaRowKey(row);
                    const existing = QUOTA_EXISTING_INDEX[key] != null;
                    return '<div class="quota-import-row"><span class="qname">' + quotaHtmlEscape(row.name) + '</span>' +
                        '<span style="color:var(--muted-text);font-size:0.82rem;overflow:hidden;text-overflow:ellipsis;">' + quotaHtmlEscape(row.account || '未填帳號') + '</span>' +
                        '<span class="' + (existing ? 'qstatus-update' : 'qstatus-new') + '">' + (existing ? '更新' : '新增') + '</span></div>';
                }).join('');
        }
        document.getElementById('quotaImportCancelBtn').style.display = '';
        document.getElementById('quotaImportConfirmBtn').style.display = quotaImportErrors.length === 0 && quotaImportData.length > 0 ? '' : 'none';
    }

    function quotaHtmlEscape(value) {
        return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function closeQuotaImport() {
        document.getElementById('quotaImportOverlay').style.display = 'none';
        quotaImportData = [];
        quotaImportErrors = [];
    }

    async function executeQuotaImport() {
        if (!quotaImportData || quotaImportData.length === 0) return;
        document.getElementById('quotaImportCancelBtn').style.display = 'none';
        document.getElementById('quotaImportConfirmBtn').style.display = 'none';
        const confirmBtn = document.getElementById('quotaImportConfirmBtn');
        confirmBtn.disabled = true;
        const resultEl = document.getElementById('quotaImportResult');
        resultEl.style.display = '';
        resultEl.textContent = '匯入中…';
        let successCount = 0;
        let failCount = 0;
        const index = Object.assign({}, QUOTA_EXISTING_INDEX);
        for (const row of quotaImportData) {
            const key = quotaRowKey(row);
            const existingId = index[key];
            try {
                const payload = {
                    name: row.name, serviceType: row.serviceType, account: row.account,
                    quotaRemaining: row.quotaRemaining, quotaRatio: row.quotaRatio,
                    quotaExpiry: row.quotaExpiry || null, note: row.note,
                    ratio5h: row.ratio5h, expiry5h: row.expiry5h,
                    ratioWeek: row.ratioWeek, expiryWeek: row.expiryWeek || null,
                    ratioMonth: row.ratioMonth, expiryMonth: row.expiryMonth || null
                };
                const url = existingId
                    ? 'api.php?action=update&table=' + encodeURIComponent(TABLE) + '&id=' + encodeURIComponent(existingId)
                    : 'api.php?action=create&table=' + encodeURIComponent(TABLE);
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(function (r) { return r.json(); });
                if (res.success) {
                    successCount++;
                    if (!existingId && res.id) index[key] = res.id;
                } else {
                    failCount++;
                }
            } catch (e) {
                failCount++;
            }
        }
        confirmBtn.disabled = false;
        resultEl.textContent = '匯入完成：成功 ' + successCount + ' 筆 · 失敗 ' + failCount + ' 筆';
        if (failCount === 0) {
            setTimeout(function () {
                location.reload();
            }, 1200);
        } else {
            document.getElementById('quotaImportCancelBtn').style.display = '';
        }
    }
</script>
