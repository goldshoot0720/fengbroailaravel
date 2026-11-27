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
$productNames = [];
$pickupPresets = ['門市購買', '超商取貨付款', '蝦皮取貨付款', '宅配/郵寄', '超商取貨', '蝦皮取貨', '門市取貨'];
$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Taipei'));
$dateRates = ['TWD' => 1, 'USD' => 35, 'JPY' => 0.35, 'CNY' => 4.5];
$currencySymbols = ['TWD' => 'NT$', 'USD' => '$', 'JPY' => '¥', 'CNY' => '¥'];
foreach ($items as $index => $item) {
    $name = trim((string) ($item['name'] ?? ''));
    if ($name !== '') $productNames[$name] = $name;
    $pickup = trim((string) ($item['pickupMethod'] ?? ''));
    if ($pickup !== '' && !in_array($pickup, $pickupPresets, true)) $pickupPresets[] = $pickup;
    $shop = trim((string) ($item['shop'] ?? ''));
    if ($shop !== '') {
        $shopNames[$shop] = $shop;
    }
    $dateKey = substr((string) ($item['plannedDate'] ?? ''), 0, 10);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateKey, $today->getTimezone());
    $days = $date && $date->format('Y-m-d') === $dateKey ? (int) $today->diff($date)->format('%r%a') : null;
    $items[$index]['dateKey'] = $days === null ? '' : $dateKey;
    $items[$index]['daysLeft'] = $days;
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
    $currency = strtoupper(trim((string) ($item['currency'] ?? 'TWD')));
    $currency = isset($dateRates[$currency]) ? $currency : 'TWD';
    $items[$index]['currency'] = $currency;
    $items[$index]['subtotal'] = (int) round($price * $quantity * $dateRates[$currency]);
    $totalPlanned += $items[$index]['subtotal'];
}

natcasesort($shopNames);
natcasesort($productNames);
?>

<div class="content-header shopping-heading">
    <h1>鋒兄購物清單</h1>
    <span><?= $totalCount ?> 筆商品</span>
</div>

<div class="content-body shopping-workspace">
    <div class="shopping-summary" role="group" aria-label="購買日摘要">
        <?php foreach (['today' => ['今天要買', $todayCount], 'upcoming' => ['未來 3 天', $upcomingCount], 'expired' => ['已過購買日', $expiredCount]] as $status => [$label, $count]): ?>
            <button type="button" data-shopping-filter="<?= $status ?>" aria-pressed="false" onclick="toggleShoppingStatus(this)">
                <span><?= $label ?></span><strong><?= $count ?></strong>
            </button>
        <?php endforeach; ?>
        <div class="shopping-budget"><span>預估總金額</span><strong>NT$ <?= number_format($totalPlanned) ?></strong></div>
    </div>

    <div class="action-buttons-bar shopping-tools">
        <button class="btn btn-primary" type="button" onclick="openShoppingForm()">新增商品</button>
        <?php include 'includes/batch-delete.php'; ?>
        <details class="shopping-transfer">
            <summary>匯入與匯出</summary>
            <button class="btn" type="button" onclick="exportShoppingCsv()">匯出 CSV</button>
            <button class="btn" type="button" onclick="document.getElementById('shoppingCsvFile').click()">匯入 CSV</button>
            <input type="file" id="shoppingCsvFile" accept=".csv,text/csv" hidden onchange="handleShoppingCsvFile(this)">
        </details>
    </div>

    <dialog id="shoppingEditor" aria-labelledby="shoppingFormTitle">
        <form id="shoppingForm" onsubmit="saveShoppingItem(event)">
        <h2 id="shoppingFormTitle">新增商品</h2>
        <input type="hidden" id="shoppingId" value="">
        <div class="mgmt-form-grid">
            <label>購物名稱 <span class="req">*</span>
                <input class="form-control" id="shoppingName" list="shoppingNames" autofocus maxlength="100" required placeholder="例如 洗衣精補充包">
            </label>
            <datalist id="shoppingNames">
                <?php foreach ($productNames as $name): ?><option value="<?= htmlspecialchars($name, ENT_QUOTES) ?>"></option><?php endforeach; ?>
            </datalist>
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
                <input class="form-control" id="shoppingPickupCustom" aria-label="自訂取貨方式" maxlength="30" placeholder="自行輸入取貨方式" style="display:none;margin-top:6px;">
            </label>
            <div class="shopping-picture mgmt-span-2">
                <label for="shoppingImageUrl">商品圖片</label>
                <input class="form-control" id="shoppingImageUrl" maxlength="2000" type="url" placeholder="貼上圖片網址" oninput="shoppingUseImageUrl()">
                <div class="shopping-photo-actions">
                    <input id="shoppingImageFile" type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden onchange="shoppingChooseImage(this)">
                    <button class="btn" type="button" onclick="document.getElementById('shoppingImageFile').click()">選擇照片</button>
                    <button class="btn" type="button" id="shoppingRemoveImage" hidden onclick="resetShoppingImage('')">移除照片</button>
                </div>
                <img id="shoppingImagePreview" alt="商品圖片預覽" hidden width="80" height="80">
            </div>
            <label>帳號
                <input class="form-control" id="shoppingAccount" maxlength="200" placeholder="付款或會員帳號（選填）">
            </label>
            <label class="mgmt-span-2">備註
                <textarea class="form-control" id="shoppingNote" maxlength="3337" rows="3" placeholder="規格、網址或其他提醒"></textarea>
            </label>
        </div>
        <div class="inline-actions" style="margin-top:16px;">
            <span id="shoppingFormMessage" role="status" aria-live="polite"></span>
            <button type="submit" class="btn btn-primary" id="shoppingSaveBtn">新增商品</button>
            <button type="button" class="btn" onclick="closeShoppingForm()">取消</button>
        </div>
        </form>
    </dialog>

    <div class="mgmt-filter-bar">
        <div class="shopping-search"><form role="search">
            <input type="search" id="shoppingSearchInput" class="form-control" aria-label="搜尋購物清單" placeholder="搜尋商品、商店、取貨方式、帳號或備註" oninput="filterShopping()">
        </form></div>
        <select id="shoppingStatusFilter" aria-label="購買日狀態" class="form-control" onchange="filterShopping()">
            <option value="all">全部狀態</option>
            <option value="upcoming">未來 3 天</option>
            <option value="today">今天要買</option>
            <option value="expired">已過購買日</option>
            <option value="nodate">未設定日期</option>
        </select>
        <button class="shopping-reset" type="button" id="shoppingReset" onclick="clearShoppingFilters()" hidden>清除篩選</button>
        <span class="food-result-count" id="shoppingVisibleCount" role="status"><?php echo $totalCount; ?> 筆</span>
    </div>

    <?php if (empty($items)): ?>
        <div class="card" style="text-align:center;color:#999;padding:40px;">尚無購物清單項目。先新增第一個想買的商品。</div>
    <?php else: ?>
        <div class="shopping-table-wrap">
            <div class="shopping-row shopping-head">
                <span></span>
                <span>購物名稱</span>
                <span>預定購買日</span>
                <span>價格</span>
                <span>小計</span>
                <span>取貨方式</span>
                <span>帳號</span>
                <span>備註</span>
                <span>操作</span>
            </div>
            <?php foreach ($items as $item): ?>
                <?php
                $name = (string) ($item['name'] ?? '');
                $days = $item['daysLeft'];
                $price = fengbroNonNegativeInt($item['price'] ?? 0);
                $quantity = max(1, fengbroNonNegativeInt($item['quantity'] ?? 1));
                $currency = $item['currency'];
                $subtotal = $item['subtotal'];
                $dateLabel = $item['dateKey'] ?: '未設定';
                $shop = trim((string) ($item['shop'] ?? ''));
                $pickup = trim((string) ($item['pickupMethod'] ?? ''));
                $account = trim((string) ($item['account'] ?? ''));
                $note = (string) ($item['note'] ?? '');
                $imageUrl = trim((string) ($item['imageUrl'] ?? ''));
                $statusChip = '';
                $statusTone = '';
                if ($days !== null && $days < 0) {
                    $statusChip = '已過 ' . abs($days) . ' 天';
                    $statusTone = 'chip-danger';
                } elseif ($days === 0) {
                    $statusChip = '今天要買';
                    $statusTone = 'chip-warning';
                } elseif ($days !== null && $days <= 3) {
                    $statusChip = '還有 ' . $days . ' 天';
                    $statusTone = 'chip-info';
                }
                $searchBlob = strtolower($name . ' ' . $shop . ' ' . $pickup . ' ' . $account . ' ' . $note);
                $plannedDateIso = $item['dateKey'];
                ?>
                <article class="shopping-row shopping-item" aria-label="<?= htmlspecialchars($name, ENT_QUOTES) ?>" data-status="<?= match (true) { $days === null => 'nodate', $days < 0 => 'expired', $days === 0 => 'today', $days <= 3 => 'upcoming', default => 'later' } ?>"
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
                    <input type="checkbox" aria-label="選取 <?= htmlspecialchars($name, ENT_QUOTES) ?>" class="select-checkbox item-checkbox" data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>" onchange="toggleSelectItem(this)">
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
                        <div><?= $currencySymbols[$currency] . ' ' . number_format($price) ?><?php if ($currency !== 'TWD'): ?> <small><?= $currency ?></small><?php endif; ?></div>
                        <?php if ($quantity > 1): ?><small class="shopping-cell-sub">數量 <?php echo $quantity; ?></small><?php endif; ?>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">小計</span>
                        <strong class="shopping-subtotal">NT$ <?= number_format($subtotal) ?></strong>
                        <?php if ($currency !== 'TWD'): ?><small class="shopping-cell-sub"><?= $currency . ' ' . number_format($price * $quantity) ?></small><?php endif; ?>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">取貨方式</span>
                        <?php if ($pickup !== ''): ?><span class="chip chip-muted shopping-chip"><?php echo htmlspecialchars($pickup); ?></span><?php else: ?><span class="muted-dash">—</span><?php endif; ?>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">帳號</span>
                        <?php if ($account !== ''): ?><span><?php echo htmlspecialchars($account); ?></span><?php else: ?><span class="muted-dash">—</span><?php endif; ?>
                    </div>
                    <div>
                        <span class="mgmt-mobile-label">備註</span>
                        <?php if ($note !== ''): ?><p class="mgmt-note"><?= htmlspecialchars($note) ?></p><?php else: ?><span class="muted-dash">—</span><?php endif; ?>
                    </div>
                    <div class="mgmt-row-actions">
                        <button type="button" class="btn btn-sm" onclick="copyShoppingItem(this.closest('article').dataset.id)" title="複製此商品（預先填好欄位，供你確認後新增）">複製</button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="openShoppingForm(this.closest('article').dataset.id)" title="編輯">編輯</button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteShoppingItem(this.closest('article').dataset.id)" title="刪除">刪除</button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p id="shoppingNoMatch" hidden>沒有符合目前篩選條件的商品。</p>
    <dialog id="shoppingImportOverlay" aria-labelledby="shoppingImportTitle">
        <div class="quota-import-panel">
            <h3 class="card-title" id="shoppingImportTitle">匯入 CSV 預覽</h3>
            <p class="muted-copy">以「購物名稱」對應：相同名稱更新既有紀錄，其餘新增。有任何格式錯誤時不會寫入。</p>
            <div id="shoppingImportResult" role="status" class="quota-import-result" style="display:none;"></div>
            <div id="shoppingImportErrors" class="quota-import-errors" style="display:none;"></div>
            <div id="shoppingImportRows" class="quota-import-rows"></div>
            <div class="inline-actions" style="margin-top:16px;display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn" id="shoppingImportCancelBtn" onclick="closeShoppingImport()">取消</button>
                <button type="button" class="btn btn-primary" id="shoppingImportConfirmBtn" onclick="executeShoppingImport()">確認匯入</button>
            </div>
        </div>
    </dialog>
</div>
<?php include 'includes/upload-progress.php'; ?>

<script src="assets/js/shoppinglist.js?v=20261127"></script>
