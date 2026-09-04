<?php
$pageTitle = '鋒兄購物清單';
$pdo = getConnection();
fengbroEnsureShoppingListTable($pdo);

$items = $pdo->query("SELECT * FROM shoppinglist ORDER BY (plannedDate IS NULL) ASC, plannedDate ASC, created_at ASC")->fetchAll();

$shopNames = [];
$totalCount = count($items);
$expiredCount = 0;
$upcomingCount = 0;
$todayCount = 0;
$totalPlanned = 0;
$existingIndex = [];
$todayKey = date('Y-m-d');
$dateRates = ['TWD' => 1, 'USD' => 35, 'JPY' => 0.35, 'CNY' => 4.5];
$currencySymbols = ['TWD' => 'NT$', 'USD' => '$', 'JPY' => '¥', 'CNY' => '¥'];

function fengbroShoppingDaysLeft($dateStr): ?int
{
    if (empty($dateStr)) {
        return null;
    }
    $ts = strtotime((string) $dateStr);
    if ($ts === false) {
        return null;
    }
    $today = strtotime(date('Y-m-d'));
    return (int) round(($ts - $today) / 86400);
}

function fengbroShoppingDateLabel($dateStr): string
{
    if (empty($dateStr)) {
        return '未設定';
    }
    $ts = strtotime((string) $dateStr);
    if ($ts === false) {
        return '日期格式錯誤';
    }
    return date('Y-m-d', $ts);
}

function fengbroShoppingMoney(int $amount, string $currency): string
{
    global $dateRates, $currencySymbols;
    $currency = in_array($currency, ['TWD', 'USD', 'JPY', 'CNY'], true) ? $currency : 'TWD';
    $symbol = $currencySymbols[$currency];
    if ($currency === 'TWD') {
        return 'NT$ ' . number_format($amount);
    }
    $twd = (int) round($amount * ($dateRates[$currency] ?? 1));
    return 'NT$ ' . number_format($twd) . '（' . $symbol . ' ' . number_format($amount) . '）';
}

foreach ($items as $item) {
    $name = trim((string) ($item['name'] ?? ''));
    $existingIndex[strtolower($name)] = $item['id'];
    $shop = trim((string) ($item['shop'] ?? ''));
    if ($shop !== '') {
        $shopNames[$shop] = $shop;
    }
    $days = fengbroShoppingDaysLeft($item['plannedDate'] ?? '');
    if ($days !== null) {
        if ($days < 0) {
            $expiredCount++;
        } elseif ($days === 0) {
            $todayCount++;
        } elseif ($days <= 3) {
            $upcomingCount++;
        }
    }
    $price = fengbroNonNegativeInt($item['price'] ?? 0);
    $quantity = max(1, fengbroNonNegativeInt($item['quantity'] ?? 1));
    $currency = in_array(trim((string) ($item['currency'] ?? 'TWD')), ['TWD', 'USD', 'JPY', 'CNY'], true) ? $item['currency'] : 'TWD';
    $twdPrice = (int) round($price * ($dateRates[$currency] ?? 1));
    $totalPlanned += $twdPrice * $quantity;
}

natcasesort($shopNames);
$shopNames = array_values($shopNames);
$pickupPresets = ['門市購買', '超商取貨付款', '蝦皮取貨付款', '宅配/郵寄', '超商取貨', '蝦皮取貨', '門市取貨'];
?>

<div class="content-header" style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="margin:0;">鋒兄購物清單</h1>
        <p class="muted-copy">記錄「想買的商品 × 一次預定購買」。有預定購買日的項目，到期前 3 天會進入提醒窗口；已超過購買日不重複追蹤。</p>
    </div>
    <span class="count-pill count-pill-shopping"><?php echo $totalCount; ?> 筆項目</span>
</div>

<div class="content-body">
    <div class="mgmt-stat-grid">
        <div class="food-stat-card">
            <span>商品項目</span>
            <strong><?php echo $totalCount; ?></strong>
        </div>
        <div class="food-stat-card food-stat-warning">
            <span>3 天內要買</span>
            <strong><?php echo $upcomingCount; ?></strong>
            <small><?php echo $todayCount; ?> 項今天預定</small>
        </div>
        <div class="food-stat-card food-stat-danger">
            <span>已過購買日</span>
            <strong><?php echo $expiredCount; ?></strong>
        </div>
        <div class="food-stat-card food-stat-highlight">
            <span>預估總金額</span>
            <strong style="font-size:1.05rem;"><?php echo 'NT$ ' . number_format($totalPlanned); ?></strong>
            <small>台幣概估（數量 × 單價）</small>
        </div>
    </div>

    <div class="action-buttons-bar">
        <button class="btn btn-primary" type="button" onclick="openShoppingForm()"><i class="fas fa-plus"></i> 新增商品</button>
        <button class="btn btn-success" type="button" onclick="exportShoppingCsv()" title="匯出目前全部購物清單為 CSV"><i class="fa-solid fa-download"></i> 匯出 CSV</button>
        <button class="btn" type="button" onclick="document.getElementById('shoppingCsvFile').click()" title="從 CSV 匯入購物清單（相同購物名稱會更新）"><i class="fa-solid fa-upload"></i> 匯入 CSV</button>
        <input type="file" id="shoppingCsvFile" accept=".csv,text/csv" style="display:none;" onchange="handleShoppingCsvFile(this)">
        <?php include 'includes/batch-delete.php'; ?>
    </div>

    <form id="shoppingForm" class="card mgmt-form" style="display:none;" onsubmit="return saveShoppingItem(event)">
        <h3 class="card-title" id="shoppingFormTitle">新增商品</h3>
        <p class="muted-copy">每筆代表「一個想買的商品 × 一次預定購買」。</p>
        <input type="hidden" id="shoppingId" value="">
        <div class="mgmt-form-grid">
            <label>購物名稱 <span class="req">*</span>
                <input class="form-control" id="shoppingName" maxlength="100" required placeholder="例如 洗衣精補充包">
            </label>
            <label>預定購買日
                <input class="form-control" id="shoppingPlannedDate" type="date">
            </label>
            <label>預定價格
                <input class="form-control" id="shoppingPrice" type="number" min="0" step="1" value="0">
            </label>
            <label>幣別
                <select class="form-control" id="shoppingCurrency">
                    <option value="TWD">台幣</option>
                    <option value="USD">美元</option>
                    <option value="JPY">日圓</option>
                    <option value="CNY">人民幣</option>
                </select>
            </label>
            <label>預定數量
                <input class="form-control" id="shoppingQuantity" type="number" min="1" step="1" value="1">
            </label>
            <label>預定商店
                <input class="form-control" id="shoppingShop" maxlength="100" list="shoppingShopList" placeholder="例如 全聯">
                <datalist id="shoppingShopList">
                    <?php foreach ($shopNames as $shopName): ?>
                        <option value="<?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endforeach; ?>
                </datalist>
            </label>
            <label>預定取貨方式
                <select class="form-control" id="shoppingPickupMethod" onchange="toggleShoppingPickupCustom(this)">
                    <option value="">未設定</option>
                    <?php foreach ($pickupPresets as $pickup): ?>
                        <option value="<?php echo htmlspecialchars($pickup, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($pickup); ?></option>
                    <?php endforeach; ?>
                    <option value="__custom__">自行輸入…</option>
                </select>
                <input class="form-control" id="shoppingPickupCustom" maxlength="30" placeholder="自行輸入取貨方式" style="display:none;margin-top:6px;">
            </label>
            <label class="mgmt-span-2">商品圖片網址
                <input class="form-control" id="shoppingImageUrl" maxlength="2000" type="url" placeholder="https://…（選填）" oninput="previewShoppingImageUrl()">
                <span id="shoppingImagePreviewWrap" style="display:none;margin-top:8px;">
                    <img id="shoppingImagePreview" alt="商品圖片預覽" style="max-width:140px;max-height:140px;border-radius:12px;border:1px solid var(--border-color);">
                </span>
            </label>
            <label>帳號
                <input class="form-control" id="shoppingAccount" maxlength="200" placeholder="付款或會員帳號（選填）">
            </label>
            <label class="mgmt-span-2">備註
                <textarea class="form-control" id="shoppingNote" maxlength="3337" rows="3" placeholder="規格、網址或其他提醒"></textarea>
            </label>
        </div>
        <div class="inline-actions" style="margin-top:16px;">
            <button type="submit" class="btn btn-primary" id="shoppingSaveBtn">新增商品</button>
            <button type="button" class="btn" onclick="closeShoppingForm()">取消</button>
        </div>
    </form>

    <div class="mgmt-filter-bar">
        <label class="food-search-box">
            <i class="fas fa-search"></i>
            <input type="search" id="shoppingSearchInput" class="form-control" placeholder="搜尋名稱、商店、取貨方式、帳號或備註" oninput="filterShopping()">
        </label>
        <select id="shoppingStatusFilter" class="form-control" onchange="filterShopping()">
            <option value="all">全部狀態</option>
            <option value="upcoming">3 天內要買</option>
            <option value="today">今天要買</option>
            <option value="expired">已過購買日</option>
            <option value="nodate">未設定日期</option>
        </select>
        <span class="food-result-count" id="shoppingVisibleCount"><?php echo $totalCount; ?> 筆</span>
    </div>

    <?php if (empty($items)): ?>
        <div class="card" style="text-align:center;color:#999;padding:40px;">尚無購物清單項目。先新增第一個想買的商品。</div>
    <?php else: ?>
        <div class="shopping-table-wrap">
            <div class="shopping-row shopping-head desktop-only">
                <span></span>
                <span>購物名稱</span>
                <span>預定購買日</span>
                <span>價格</span>
                <span>小計</span>
                <span>商店／取貨方式</span>
                <span>帳號</span>
                <span>備註</span>
                <span>操作</span>
            </div>
            <?php foreach ($items as $item): ?>
                <?php
                $name = (string) ($item['name'] ?? '');
                $plannedDate = (string) ($item['plannedDate'] ?? '');
                $days = fengbroShoppingDaysLeft($plannedDate);
                $price = fengbroNonNegativeInt($item['price'] ?? 0);
                $quantity = max(1, fengbroNonNegativeInt($item['quantity'] ?? 1));
                $currency = in_array(trim((string) ($item['currency'] ?? 'TWD')), ['TWD', 'USD', 'JPY', 'CNY'], true) ? $item['currency'] : 'TWD';
                $twdPrice = (int) round($price * ($dateRates[$currency] ?? 1));
                $subtotal = $twdPrice * $quantity;
                $dateLabel = fengbroShoppingDateLabel($plannedDate);
                $shop = trim((string) ($item['shop'] ?? ''));
                $pickup = trim((string) ($item['pickupMethod'] ?? ''));
                $account = trim((string) ($item['account'] ?? ''));
                $note = trim((string) ($item['note'] ?? ''));
                $imageUrl = trim((string) ($item['imageUrl'] ?? ''));
                $statusChip = '';
                $statusTone = '';
                if ($days !== null && $days < 0) {
                    $statusChip = '已過 ' . abs($days) . ' 天';
                    $statusTone = 'chip-danger';
                } elseif ($days === 0) {
                    $statusChip = '今天要買';
                    $statusTone = 'chip-warning';
                } elseif ($days <= 3) {
                    $statusChip = $days . ' 天內';
                    $statusTone = 'chip-info';
                }
                $searchBlob = strtolower($name . ' ' . $shop . ' ' . $pickup . ' ' . $account . ' ' . $note);
                $plannedDateIso = $plannedDate !== '' ? date('Y-m-d', strtotime($plannedDate)) : '';
                ?>
                <article class="shopping-row shopping-item"
                    data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                    data-planneddate="<?php echo htmlspecialchars($plannedDateIso, ENT_QUOTES, 'UTF-8'); ?>"
                    data-price="<?php echo (int) $price; ?>"
                    data-currency="<?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>"
                    data-quantity="<?php echo (int) $quantity; ?>"
                    data-shop="<?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?>"
                    data-pickup="<?php echo htmlspecialchars($pickup, ENT_QUOTES, 'UTF-8'); ?>"
                    data-imageurl="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    data-account="<?php echo htmlspecialchars($account, ENT_QUOTES, 'UTF-8'); ?>"
                    data-note="<?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?>"
                    data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>" onchange="toggleSelectItem(this)">
                    <div class="shopping-cell-main">
                        <?php if ($imageUrl !== ''): ?>
                            <img class="shopping-thumb" src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" onerror="this.style.display='none';">
                        <?php endif; ?>
                        <span class="mgmt-mobile-label">購物名稱</span>
                        <strong><?php echo htmlspecialchars($name); ?></strong>
                        <?php if ($shop !== ''): ?><small class="shopping-cell-sub"><?php echo htmlspecialchars($shop); ?></small><?php endif; ?>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">預定購買日</span>
                        <div class="shopping-date"><i class="fa-regular fa-calendar"></i> <?php echo htmlspecialchars($dateLabel); ?></div>
                        <?php if ($statusChip !== ''): ?><span class="status-chip <?php echo $statusTone; ?>"><?php echo $statusChip; ?></span><?php endif; ?>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">價格</span>
                        <div><?php echo fengbroShoppingMoney($price, $currency); ?></div>
                        <?php if ($quantity > 1): ?><small class="shopping-cell-sub">數量 <?php echo $quantity; ?></small><?php endif; ?>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">小計</span>
                        <strong class="shopping-subtotal"><?php echo 'NT$ ' . number_format($subtotal); ?></strong>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">取貨方式</span>
                        <?php if ($pickup !== ''): ?><span class="chip chip-muted shopping-chip"><?php echo htmlspecialchars($pickup); ?></span><?php else: ?><span class="muted-dash">—</span><?php endif; ?>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">帳號</span>
                        <?php if ($account !== ''): ?><span class="private-value"><?php echo htmlspecialchars($account); ?></span><?php else: ?><span class="muted-dash">—</span><?php endif; ?>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">備註</span>
                        <?php if ($note !== ''): ?><p class="mgmt-note"><?php echo nl2br(htmlspecialchars($note)); ?></p><?php else: ?><span class="muted-dash">—</span><?php endif; ?>
                    </div>
                    <div class="mgmt-row-actions">
                        <button type="button" class="btn btn-sm" onclick="copyShoppingItem('<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>')" title="複製此商品（預先填好欄位，供你確認後新增）"><i class="fa-solid fa-copy"></i></button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="openShoppingForm('<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>')" title="編輯"><i class="fas fa-pen"></i></button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteShoppingItem('<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>')" title="刪除"><i class="fas fa-trash"></i></button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div id="shoppingImportOverlay" class="quota-import-overlay" style="display:none;">
        <div class="quota-import-panel" role="dialog" aria-modal="true" aria-labelledby="shoppingImportTitle">
            <h3 class="card-title" id="shoppingImportTitle">匯入 CSV 預覽</h3>
            <p class="muted-copy">以「購物名稱」對應：相同名稱更新既有紀錄，其餘新增。有任何格式錯誤時不會寫入。</p>
            <div id="shoppingImportResult" class="quota-import-result" style="display:none;"></div>
            <div id="shoppingImportErrors" class="quota-import-errors" style="display:none;"></div>
            <div id="shoppingImportRows" class="quota-import-rows"></div>
            <div class="inline-actions" style="margin-top:16px;display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn" id="shoppingImportCancelBtn" onclick="closeShoppingImport()">取消</button>
                <button type="button" class="btn btn-primary" id="shoppingImportConfirmBtn" onclick="executeShoppingImport()">確認匯入</button>
            </div>
        </div>
    </div>
</div>

<style>
    .muted-copy { margin: 8px 0 0; color: var(--muted-text); line-height: 1.6; }
    .count-pill { color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; white-space: nowrap; }
    .count-pill-shopping { background: #c1613d; }
    .mgmt-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .food-stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 18px; padding: 14px 16px; box-shadow: 0 12px 26px var(--shadow); }
    .food-stat-card span { display: block; color: var(--muted-text); font-size: 0.82rem; margin-bottom: 6px; }
    .food-stat-card strong { font-size: 1.35rem; }
    .food-stat-card small { color: var(--muted-text); font-size: 0.78rem; }
    .food-stat-warning strong { color: #96601f; }
    .food-stat-danger strong { color: #992e24; }
    .food-stat-highlight strong { color: #b06a3f; }
    .action-buttons-bar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
    .mgmt-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
    .mgmt-form-grid label { display: grid; gap: 6px; font-weight: 600; }
    .mgmt-span-2 { grid-column: 1 / -1; }
    .req { color: #c1554a; }
    .mgmt-filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 8px 0 18px; }
    .food-search-box { position: relative; flex: 1 1 260px; }
    .food-search-box i { position: absolute; top: 50%; left: 12px; transform: translateY(-50%); color: var(--muted-text); }
    .food-search-box input { padding-left: 38px; }
    .mgmt-filter-bar select { min-width: 150px; }
    .shopping-table-wrap { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 18px; overflow: hidden; }
    .shopping-row { display: grid; grid-template-columns: 28px minmax(11rem, 1.3fr) minmax(8.5rem, 0.9fr) minmax(8.5rem, 1fr) minmax(6rem, 0.7fr) minmax(9rem, 1fr) minmax(8rem, 0.9fr) minmax(9rem, 1fr) 116px; gap: 12px; align-items: center; padding: 12px 16px; }
    .shopping-head { color: var(--muted-text); font-size: 0.78rem; font-weight: 700; border-bottom: 1px solid var(--border-color); background: var(--table-header-bg, #faf9f5); }
    .shopping-item { border-bottom: 1px solid var(--border-color); }
    .shopping-item:last-child { border-bottom: 0; }
    .shopping-cell-main { min-width: 0; }
    .shopping-thumb { width: 52px; height: 52px; object-fit: cover; border-radius: 10px; border: 1px solid var(--border-color); float: left; margin-right: 10px; }
    .shopping-cell-sub { display: block; color: var(--muted-text); font-size: 0.8rem; margin-top: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .shopping-date { color: var(--text-color); font-size: 0.9rem; margin-bottom: 4px; }
    .shopping-subtotal { font-weight: 800; color: #b06a3f; }
    .shopping-chip { white-space: nowrap; }
    .status-chip { display: inline-block; margin: 2px 4px 2px 0; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
    .chip-info { background: #f6e5db; color: #9c4726; }
    .chip-warning { background: #f7ecd9; color: #6f5518; }
    .chip-danger { background: #f6e0dd; color: #6e2a23; }
    .chip-muted { background: #f0eee6; color: #57534a; }
    .muted-dash { color: var(--muted-text); }
    .mgmt-note { margin: 0; white-space: pre-wrap; color: var(--muted-text); font-size: 0.9rem; }
    .mgmt-mobile-label { display: none; }
    .mgmt-row-actions { display: flex; gap: 6px; }
    .quota-import-overlay { position: fixed; inset: 0; z-index: 120; background: rgba(30, 26, 20, 0.55); display: flex; align-items: center; justify-content: center; padding: 16px; }
    .quota-import-panel { width: min(560px, 100%); max-height: 85vh; overflow-y: auto; background: var(--card-bg); border-radius: 18px; padding: 22px 24px; box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28); }
    .quota-import-result { margin-top: 10px; padding: 8px 12px; border-radius: 10px; background: #e3efe5; color: #2b5c40; font-weight: 600; }
    .quota-import-errors { margin-top: 10px; padding: 8px 12px; border-radius: 10px; background: #f6e0dd; color: #6e2a23; max-height: 140px; overflow-y: auto; font-size: 0.85rem; }
    .quota-import-rows { margin-top: 10px; }
    .quota-import-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 7px 10px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; }
    .quota-import-row:last-child { border-bottom: 0; }
    .quota-import-row .qname { font-weight: 600; }
    .quota-import-row .qstatus-new { color: #2b5c40; font-size: 0.75rem; font-weight: 700; background: #e3efe5; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
    .quota-import-row .qstatus-update { color: #6f5518; font-size: 0.75rem; font-weight: 700; background: #f7ecd9; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
    @media (max-width: 1100px) {
        .shopping-row { grid-template-columns: 28px minmax(10rem, 1.2fr) minmax(8rem, 0.9fr) minmax(7rem, 0.8fr) minmax(9rem, 1fr) minmax(8rem, 0.9fr) 116px; }
        .shopping-head { display: none; }
        .shopping-item > *:nth-child(2) { display: none; }
        .shopping-item > *:nth-child(6) { display: none; }
    }
    @media (max-width: 860px) {
        .shopping-item { grid-template-columns: 28px 1fr; gap: 10px 12px; }
        .shopping-item > * { grid-column: 2; }
        .shopping-item > .select-checkbox { grid-column: 1; grid-row: 1; }
        .shopping-item > .shopping-cell-main { grid-column: 2; grid-row: 1; }
        .mgmt-mobile-label { display: block; font-size: 0.72rem; color: var(--muted-text); margin-bottom: 4px; }
        .shopping-item > *:nth-child(2), .shopping-item > *:nth-child(6) { display: block; }
        .mgmt-row-actions { grid-column: 2; }
    }
</style>

<script>
    const TABLE = 'shoppinglist';
    initBatchDelete(TABLE);

    const SHOPPING_EXISTING_INDEX = <?php echo json_encode($existingIndex); ?>;
    const SHOPPING_HEADER_ALIASES = {
        'name': 'name', '購物名稱': 'name', '商品名稱': 'name', '名稱': 'name',
        'planneddate': 'plannedDate', 'planned_date': 'plannedDate',
        '預定購買日': 'plannedDate', '購買日': 'plannedDate', '預定日期': 'plannedDate',
        'price': 'price', '預定價格': 'price', '價格': 'price', '金額': 'price',
        'currency': 'currency', '幣別': 'currency', '幣種': 'currency', '貨幣': 'currency',
        'quantity': 'quantity', '預定數量': 'quantity', '數量': 'quantity',
        'shop': 'shop', '預定商店': 'shop', '商店': 'shop', '店家': 'shop',
        'pickupmethod': 'pickupMethod', 'pickup_method': 'pickupMethod',
        '預定取貨方式': 'pickupMethod', '取貨方式': 'pickupMethod', '取貨': 'pickupMethod',
        'imageurl': 'imageUrl', 'image_url': 'imageUrl', 'image': 'imageUrl',
        '圖片': 'imageUrl', '圖片網址': 'imageUrl', '商品圖片': 'imageUrl', '商品圖片網址': 'imageUrl',
        'account': 'account', '帳號': 'account',
        'note': 'note', '備註': 'note'
    };
    const SHOPPING_CSV_HEADERS = ['name', 'plannedDate', 'price', 'currency', 'quantity', 'shop', 'pickupMethod', 'imageUrl', 'account', 'note'];
    const CURRENCY_ALIASES = {
        'TWD': 'TWD', 'twd': 'TWD', '台幣': 'TWD', '新台幣': 'TWD',
        'USD': 'USD', 'usd': 'USD', '美元': 'USD', '美金': 'USD',
        'JPY': 'JPY', 'jpy': 'JPY', '日圓': 'JPY', '日幣': 'JPY',
        'CNY': 'CNY', 'cny': 'CNY', '人民幣': 'CNY', 'RMB': 'CNY', 'rmb': 'CNY'
    };

    function shoppingFormEls() {
        return {
            form: document.getElementById('shoppingForm'),
            id: document.getElementById('shoppingId'),
            title: document.getElementById('shoppingFormTitle'),
            save: document.getElementById('shoppingSaveBtn'),
            name: document.getElementById('shoppingName'),
            plannedDate: document.getElementById('shoppingPlannedDate'),
            price: document.getElementById('shoppingPrice'),
            currency: document.getElementById('shoppingCurrency'),
            quantity: document.getElementById('shoppingQuantity'),
            shop: document.getElementById('shoppingShop'),
            pickupMethod: document.getElementById('shoppingPickupMethod'),
            pickupCustom: document.getElementById('shoppingPickupCustom'),
            imageUrl: document.getElementById('shoppingImageUrl'),
            account: document.getElementById('shoppingAccount'),
            note: document.getElementById('shoppingNote')
        };
    }

    const SHOPPING_PICKUP_PRESETS = <?php echo json_encode($pickupPresets, JSON_UNESCAPED_UNICODE); ?>;
    const PICKUP_CUSTOM_VALUE = '__custom__';

    function shoppingResolvePickupValue(els) {
        if (els.pickupMethod.value === PICKUP_CUSTOM_VALUE) {
            return (els.pickupCustom.value || '').trim();
        }
        return els.pickupMethod.value;
    }

    function setShoppingPickupValue(els, value) {
        const v = (value || '').trim();
        if (!v) {
            els.pickupMethod.value = '';
            els.pickupCustom.value = '';
            els.pickupCustom.style.display = 'none';
            return;
        }
        if (SHOPPING_PICKUP_PRESETS.indexOf(v) !== -1) {
            els.pickupMethod.value = v;
            els.pickupCustom.value = '';
            els.pickupCustom.style.display = 'none';
        } else {
            els.pickupMethod.value = PICKUP_CUSTOM_VALUE;
            els.pickupCustom.value = v;
            els.pickupCustom.style.display = '';
        }
    }

    function toggleShoppingPickupCustom(select) {
        const custom = document.getElementById('shoppingPickupCustom');
        if (!custom) return;
        custom.style.display = select.value === PICKUP_CUSTOM_VALUE ? '' : 'none';
        if (select.value !== PICKUP_CUSTOM_VALUE) custom.value = '';
    }

    function previewShoppingImageUrl() {
        const input = document.getElementById('shoppingImageUrl');
        const wrap = document.getElementById('shoppingImagePreviewWrap');
        const img = document.getElementById('shoppingImagePreview');
        if (!input || !wrap || !img) return;
        const url = (input.value || '').trim();
        if (/^https?:\/\/.+/i.test(url)) {
            img.src = url;
            wrap.style.display = '';
        } else {
            img.removeAttribute('src');
            wrap.style.display = 'none';
        }
    }

    function setShoppingImageUrl(els, value) {
        if (els.imageUrl) els.imageUrl.value = value || '';
        previewShoppingImageUrl();
    }

    function fillShoppingFormFromRow(row, editing) {
        const els = shoppingFormEls();
        els.id.value = editing ? row.dataset.id : '';
        els.title.textContent = editing ? '編輯商品' : '新增商品';
        els.save.textContent = editing ? '儲存變更' : '新增商品';
        els.name.value = row ? (row.dataset.name || '') : '';
        els.plannedDate.value = row ? (row.dataset.planneddate || '') : '';
        els.price.value = row ? (row.dataset.price || '0') : '0';
        els.currency.value = row ? (row.dataset.currency || 'TWD') : 'TWD';
        els.quantity.value = row ? (row.dataset.quantity || '1') : '1';
        els.shop.value = row ? (row.dataset.shop || '') : '';
        setShoppingPickupValue(els, row ? (row.dataset.pickup || '') : '');
        setShoppingImageUrl(els, row ? (row.dataset.imageurl || '') : '');
        els.account.value = row ? (row.dataset.account || '') : '';
        els.note.value = row ? (row.dataset.note || '') : '';
        els.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        els.name.focus();
    }

    function openShoppingForm(id) {
        const els = shoppingFormEls();
        els.form.style.display = '';
        const row = id ? document.querySelector('.shopping-item[data-id="' + id + '"]') : null;
        if (row) {
            fillShoppingFormFromRow(row, true);
        } else {
            els.id.value = '';
            els.title.textContent = '新增商品';
            els.save.textContent = '新增商品';
            els.name.value = '';
            els.plannedDate.value = '';
            els.price.value = '0';
            els.currency.value = 'TWD';
            els.quantity.value = '1';
            els.shop.value = '';
            setShoppingPickupValue(els, '');
            setShoppingImageUrl(els, '');
            els.account.value = '';
            els.note.value = '';
            els.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            els.name.focus();
        }
    }

    function copyShoppingItem(id) {
        const row = document.querySelector('.shopping-item[data-id="' + id + '"]');
        if (!row) return;
        const els = shoppingFormEls();
        els.form.style.display = '';
        els.id.value = '';
        els.title.textContent = '新增商品（複製）';
        els.save.textContent = '新增商品';
        els.name.value = (row.dataset.name || '') + ' (複製)';
        els.plannedDate.value = row.dataset.planneddate || '';
        els.price.value = row.dataset.price || '0';
        els.currency.value = row.dataset.currency || 'TWD';
        els.quantity.value = row.dataset.quantity || '1';
        els.shop.value = row.dataset.shop || '';
        setShoppingPickupValue(els, row.dataset.pickup || '');
        setShoppingImageUrl(els, row.dataset.imageurl || '');
        els.account.value = row.dataset.account || '';
        els.note.value = row.dataset.note || '';
        els.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        els.name.focus();
    }

    function closeShoppingForm() {
        document.getElementById('shoppingForm').style.display = 'none';
        document.getElementById('shoppingId').value = '';
    }

    function saveShoppingItem(event) {
        event.preventDefault();
        const els = shoppingFormEls();
        const payload = {
            name: els.name.value.trim(),
            plannedDate: els.plannedDate.value || null,
            price: Number(els.price.value || 0),
            currency: els.currency.value,
            quantity: Number(els.quantity.value || 1),
            shop: els.shop.value.trim(),
            pickupMethod: shoppingResolvePickupValue(els),
            imageUrl: els.imageUrl ? els.imageUrl.value.trim() : '',
            account: els.account.value.trim(),
            note: els.note.value
        };
        if (!payload.name) {
            alert('請填寫購物名稱');
            return false;
        }
        if (!Number.isInteger(payload.quantity) || payload.quantity < 1) {
            alert('預定數量必須是 1 以上的整數');
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
                location.reload();
            } else {
                alert(res.error || '儲存失敗');
            }
        }).catch(function (err) {
            alert('儲存失敗: ' + (err.message || err));
        });
        return false;
    }

    function deleteShoppingItem(id) {
        const row = document.querySelector('.shopping-item[data-id="' + id + '"]');
        const label = row ? ((row.dataset.name || '') + '') : '這筆商品';
        if (!confirm('確定要刪除「' + label + '」嗎？刪除不能復原。')) return;
        fetch('api.php?action=delete&table=' + encodeURIComponent(TABLE) + '&id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) location.reload();
                else alert(res.error || '刪除失敗');
            });
    }

    function filterShopping() {
        const query = (document.getElementById('shoppingSearchInput')?.value || '').trim().toLowerCase();
        const status = document.getElementById('shoppingStatusFilter')?.value || 'all';
        let visible = 0;
        document.querySelectorAll('.shopping-item').forEach(function (row) {
            const matchesQuery = !query || (row.dataset.search || '').indexOf(query) !== -1;
            const planned = row.dataset.planneddate || '';
            const days = shoppingDayDelta(planned);
            let matchesStatus = true;
            if (status === 'upcoming') matchesStatus = days !== null && days >= 0 && days <= 3;
            else if (status === 'today') matchesStatus = days === 0;
            else if (status === 'expired') matchesStatus = days !== null && days < 0;
            else if (status === 'nodate') matchesStatus = planned === '';
            const show = matchesQuery && matchesStatus;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        const counter = document.getElementById('shoppingVisibleCount');
        if (counter) counter.textContent = visible + ' 筆';
    }

    function shoppingDayDelta(isoDate) {
        if (!isoDate) return null;
        const target = new Date(isoDate + 'T00:00:00');
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        return Math.round((target - today) / 86400000);
    }

    // ---- CSV 匯出 ----
    function shoppingCsvEscape(value) {
        const s = value == null ? '' : String(value);
        if (s.indexOf(',') !== -1 || s.indexOf('"') !== -1 || s.indexOf('\n') !== -1) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    function exportShoppingCsv() {
        const rows = document.querySelectorAll('.shopping-item');
        if (rows.length === 0) {
            alert('尚無可匯出的購物清單');
            return;
        }
        const lines = [SHOPPING_CSV_HEADERS.join(',')];
        rows.forEach(function (row) {
            lines.push([
                row.dataset.name || '',
                row.dataset.planneddate || '',
                row.dataset.price || '0',
                row.dataset.currency || 'TWD',
                row.dataset.quantity || '1',
                row.dataset.shop || '',
                row.dataset.pickup || '',
                row.dataset.imageurl || '',
                row.dataset.account || '',
                row.dataset.note || ''
            ].map(shoppingCsvEscape).join(','));
        });
        const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'fengbro-shopping-' + new Date().toISOString().slice(0, 10) + '.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    }

    // ---- CSV 匯入 ----
    let shoppingImportData = [];
    let shoppingImportErrors = [];

    function shoppingNormalizeKey(text) {
        return (text || '').trim().toLowerCase();
    }

    function shoppingMapHeader(raw) {
        const key = shoppingNormalizeKey(raw).replace(/\s+/g, '').replace(/_/g, '');
        return SHOPPING_HEADER_ALIASES[key] || SHOPPING_HEADER_ALIASES[shoppingNormalizeKey(raw)] || null;
    }

    function shoppingParseNonNegative(value, emptyTo) {
        const s = (value || '').trim();
        if (s === '') return emptyTo;
        if (!/^\d+$/.test(s)) return null;
        return Number(s);
    }

    function shoppingNormalizeCsvDate(value) {
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

    function handleShoppingCsvFile(input) {
        const file = input.files && input.files[0];
        input.value = '';
        if (!file) return;
        if (!/\.csv$/i.test(file.name)) {
            alert('請選擇 CSV 檔案');
            return;
        }
        const reader = new FileReader();
        reader.onload = function () {
            const preview = parseShoppingCsvText(String(reader.result || ''));
            shoppingImportData = preview.data;
            shoppingImportErrors = preview.errors;
            renderShoppingImportPreview();
        };
        reader.onerror = function () { alert('讀取 CSV 檔案失敗'); };
        reader.readAsText(file, 'UTF-8');
    }

    function parseCsvLines(text) {
        const clean = String(text).replace(/^\uFEFF/, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        const lines = clean.split('\n').filter(function (line) { return line.trim() !== ''; });
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
        return { lines: lines, parseLine: parseLine };
    }

    function parseShoppingCsvText(text) {
        const errors = [];
        const data = [];
        const { lines, parseLine } = parseCsvLines(text);
        if (lines.length < 2) {
            errors.push('CSV 檔案至少需要表頭和一行資料');
            return { data: data, errors: errors };
        }
        const headers = parseLine(lines[0]);
        const colIndex = {};
        headers.forEach(function (h, i) {
            const mapped = shoppingMapHeader(h);
            if (mapped && colIndex[mapped] == null) colIndex[mapped] = i;
        });
        if (colIndex.name == null) {
            errors.push('表頭缺少必要欄位 name（購物名稱）');
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
            if (name.length > 100) { fail('購物名稱最多 100 個字元'); continue; }

            const price = shoppingParseNonNegative(cell('price'), 0);
            if (price === null) { fail('預定價格必須是 0 以上的整數'); continue; }
            const quantity = shoppingParseNonNegative(cell('quantity'), 1);
            if (quantity === null) { fail('預定數量必須是 0 以上的整數'); continue; }
            if (quantity < 1) { fail('預定數量必須是 1 以上的整數'); continue; }

            const currencyRaw = cell('currency').trim();
            let currency = 'TWD';
            if (currencyRaw) {
                currency = CURRENCY_ALIASES[currencyRaw] || CURRENCY_ALIASES[shoppingNormalizeKey(currencyRaw)] || CURRENCY_ALIASES[currencyRaw.replace(/\s+/g, '').replace(/_/g, '')];
                if (!currency) { fail('幣別需為 台幣／美元／日圓／人民幣（TWD/USD/JPY/CNY）'); continue; }
            }

            const plannedDate = shoppingNormalizeCsvDate(cell('plannedDate'));
            if (plannedDate === null) { fail('預定購買日格式不正確'); continue; }

            const shop = cell('shop').trim();
            if (shop.length > 100) { fail('預定商店最多 100 個字元'); continue; }
            const pickupMethod = cell('pickupMethod').trim();
            if (pickupMethod.length > 30) { fail('預定取貨方式最多 30 個字元'); continue; }
            const imageUrlRaw = cell('imageUrl').trim();
            let imageUrl = imageUrlRaw;
            if (imageUrlRaw) {
                if (imageUrlRaw.length > 2000) { fail('商品圖片網址最多 2000 個字元'); continue; }
                if (!/^https?:\/\/.+/i.test(imageUrlRaw)) { fail('商品圖片網址需為完整 http 或 https 網址'); continue; }
            }
            const account = cell('account').trim();
            if (account.length > 200) { fail('帳號最多 200 個字元'); continue; }
            const note = cell('note').trim();
            if (note.length > 3337) { fail('備註最多 3337 個字元'); continue; }

            data.push({
                name: name,
                plannedDate: plannedDate,
                price: price,
                currency: currency,
                quantity: quantity,
                shop: shop,
                pickupMethod: pickupMethod,
                imageUrl: imageUrl,
                account: account,
                note: note
            });
        }
        return { data: data, errors: errors };
    }

    function renderShoppingImportPreview() {
        const overlay = document.getElementById('shoppingImportOverlay');
        overlay.style.display = 'flex';
        document.getElementById('shoppingImportResult').style.display = 'none';
        const errorsEl = document.getElementById('shoppingImportErrors');
        if (shoppingImportErrors.length > 0) {
            errorsEl.style.display = '';
            errorsEl.innerHTML = '<strong>格式錯誤（不會寫入）</strong><br>' + shoppingImportErrors.map(function (e) { return '• ' + e; }).join('<br>');
        } else {
            errorsEl.style.display = 'none';
        }
        const rowsEl = document.getElementById('shoppingImportRows');
        if (shoppingImportData.length === 0) {
            rowsEl.innerHTML = '<p style="color:var(--muted-text);margin:10px 0;">沒有可匯入的資料列。</p>';
        } else {
            rowsEl.innerHTML = '<p style="font-weight:600;margin:10px 0 4px;">將匯入 ' + shoppingImportData.length + ' 筆</p>' +
                shoppingImportData.map(function (row) {
                    const key = shoppingNormalizeKey(row.name);
                    const existing = SHOPPING_EXISTING_INDEX[key] != null;
                    return '<div class="quota-import-row"><span class="qname">' + shoppingHtmlEscape(row.name) + '</span>' +
                        '<span style="color:var(--muted-text);font-size:0.82rem;">' + shoppingHtmlEscape(row.shop || '') + '</span>' +
                        '<span class="' + (existing ? 'qstatus-update' : 'qstatus-new') + '">' + (existing ? '更新' : '新增') + '</span></div>';
                }).join('');
        }
        document.getElementById('shoppingImportCancelBtn').style.display = '';
        document.getElementById('shoppingImportConfirmBtn').style.display = shoppingImportErrors.length === 0 && shoppingImportData.length > 0 ? '' : 'none';
    }

    function shoppingHtmlEscape(value) {
        return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function closeShoppingImport() {
        document.getElementById('shoppingImportOverlay').style.display = 'none';
        shoppingImportData = [];
        shoppingImportErrors = [];
    }

    async function executeShoppingImport() {
        if (!shoppingImportData || shoppingImportData.length === 0) return;
        document.getElementById('shoppingImportCancelBtn').style.display = 'none';
        document.getElementById('shoppingImportConfirmBtn').style.display = 'none';
        const resultEl = document.getElementById('shoppingImportResult');
        resultEl.style.display = '';
        resultEl.textContent = '匯入中…';
        let successCount = 0;
        let failCount = 0;
        const index = Object.assign({}, SHOPPING_EXISTING_INDEX);
        for (const row of shoppingImportData) {
            const key = shoppingNormalizeKey(row.name);
            const existingId = index[key];
            try {
                const payload = {
                    name: row.name,
                    plannedDate: row.plannedDate || null,
                    price: row.price,
                    currency: row.currency,
                    quantity: row.quantity,
                    shop: row.shop,
                    pickupMethod: row.pickupMethod,
                    imageUrl: row.imageUrl || '',
                    account: row.account,
                    note: row.note
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
        resultEl.textContent = '匯入完成：成功 ' + successCount + ' 筆 · 失敗 ' + failCount + ' 筆';
        if (failCount === 0) {
            setTimeout(function () {
                location.reload();
            }, 1200);
        } else {
            document.getElementById('shoppingImportCancelBtn').style.display = '';
        }
    }
</script>
