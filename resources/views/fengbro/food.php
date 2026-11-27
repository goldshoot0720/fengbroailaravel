<?php
$pageTitle = '鋒兄食品 （＋商品庫存）';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM food ORDER BY CASE WHEN todate IS NULL THEN 1 ELSE 0 END, todate ASC, created_at DESC")->fetchAll();
$zone = new DateTimeZone('Asia/Taipei');
$today = new DateTimeImmutable('today', $zone);
$expiredCount = 0;
$todayCount = 0;
$threeDayCount = 0;
$lowStockCount = 0;
$totalValue = 0;
$years = [];
$monthYears = [];

foreach ($items as $index => $item) {
    $amount = (int) ($item['amount'] ?? 0);
    $price = (int) ($item['price'] ?? 0);
    $totalValue += $amount * $price;
    if ($amount <= 1) {
        $lowStockCount++;
    }
    $dateText = substr($item['todate'] ?? '', 0, 10);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateText, $zone);
    $days = null;
    $bucket = 'nodate';
    if ($date && $date->format('Y-m-d') === $dateText) {
        $days = (int) $today->diff($date)->format('%r%a');
        $years[] = $date->format('Y');
        $monthYears[(int) $date->format('n')][$date->format('Y')] = true;
        $bucket = match (true) {
            $days < 0 => 'expired',
            $days === 0 => 'today',
            $days <= 3 => '3days',
            $days <= 7 => '7days',
            default => 'normal',
        };
        $expiredCount += (int) ($days < 0);
        $todayCount += (int) ($days === 0);
        $threeDayCount += (int) ($days >= 0 && $days <= 3);
    }
    $items[$index]['expiry'] = $bucket;
    $items[$index]['days'] = $days;
    $items[$index]['date'] = $days === null ? '' : $dateText;
}
ksort($monthYears);
$years = array_values(array_unique($years));
$currentYear = (int) $today->format('Y');
usort($years, function ($a, $b) use ($currentYear) {
    $distanceA = abs((int) $a - $currentYear);
    $distanceB = abs((int) $b - $currentYear);
    if ($distanceA === $distanceB) {
        return (int) $a <=> (int) $b;
    }
    return $distanceA <=> $distanceB;
});
?>

<div class="content-header food-heading">
    <h1>鋒兄食品</h1>
    <span>食品與商品庫存 · <?php echo count($items); ?> 項</span>
</div>

<div class="content-body food-inventory">
    <div class="food-ops-panel" role="group" aria-label="庫存摘要">
        <?php foreach (['expired' => ['已過期', $expiredCount], 'today' => ['今天到期', $todayCount],
            '3days' => ['3 天內到期', $threeDayCount], 'lowstock' => ['低庫存', $lowStockCount]] as $bucket => [$label, $count]): ?>
            <button type="button" class="food-stat" data-food-filter="<?= $bucket ?>" aria-pressed="false" onclick="toggleFoodStatus(this)">
                <span><?= $label ?></span>
                <strong><?= $count ?></strong>
            </button>
        <?php endforeach; ?>
        <div class="food-stat food-valuation"><span>庫存估值</span><strong id="foodValue"><?= formatMoney($totalValue) ?></strong></div>
    </div>

    <div class="food-filter-panel">
        <div class="food-search">
            <form role="search">
                <input type="search" id="foodSearchInput" class="form-control" aria-label="搜尋食品或商店" placeholder="搜尋食品或商店" enterkeyhint="search" oninput="filterFoods()">
            </form>
        </div>
        <select id="foodExpiryFilter" class="form-control" aria-label="庫存狀態" onchange="filterFoods()">
            <option value="all">全部狀態</option>
            <option value="expired">已過期</option>
            <option value="today">今天到期</option>
            <option value="3days">3 天內</option>
            <option value="7days">7 天內</option>
            <option value="normal">超過 7 天到期</option>
            <option value="lowstock">低庫存（剩 1 件以下）</option>
            <option value="nodate">無日期</option>
        </select>
        <select id="foodYearFilter" class="form-control" aria-label="有效期限年份" onchange="filterFoods()">
            <option value="">全部年份</option>
            <option value="__empty">無日期</option>
            <?php foreach ($years as $year): ?>
                <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?> 年</option>
            <?php endforeach; ?>
        </select>
        <select id="foodMonthFilter" aria-label="有效期限月份" class="form-control" onchange="filterFoods()">
            <option value="">全部月份</option>
            <?php foreach ($monthYears as $month => $availableYears): ?>
                <option value="<?= $month ?>" data-years="<?= implode(' ', array_keys($availableYears)) ?>"><?= $month ?> 月</option>
            <?php endforeach; ?>
        </select>
        <div class="food-results">
            <span class="food-result-count" role="status">顯示 <strong id="foodVisibleCount"><?= count($items) ?></strong> / <?= count($items) ?> 項</span>
            <button type="button" id="foodResetFilters" hidden onclick="clearFoodFilters()">清除篩選</button>
        </div>
    </div>

    <details class="food-quick-add">
        <summary>快速新增常用食品</summary>
        <div class="food-preset-row">
            <button type="button" class="btn btn-ghost" onclick="applyFoodPreset('牛奶', 1, 7, '冷藏')">牛奶 +7天</button>
            <button type="button" class="btn btn-ghost" onclick="applyFoodPreset('雞蛋', 10, 14, '冷藏')">雞蛋 +14天</button>
            <button type="button" class="btn btn-ghost" onclick="applyFoodPreset('吐司', 1, 3, '常溫')">吐司 +3天</button>
            <button type="button" class="btn btn-ghost" onclick="applyFoodPreset('優格', 1, 7, '冷藏')">優格 +7天</button>
            <button type="button" class="btn btn-ghost" onclick="applyFoodPreset('即食飯', 1, 30, '常溫')">即食飯 +30天</button>
        </div>
    </details>

    <div class="action-buttons-bar">
        <button class="btn btn-primary" onclick="handleAdd()" title="新增食品"><i class="fas fa-plus"></i> 新增食品</button>
        <?php include 'includes/batch-delete.php'; ?>
        <details class="food-transfer">
            <summary>匯入與匯出</summary>
            <div class="food-transfer-links">
                <a class="btn" href="export.php?table=food" download>匯出 CSV</a>
                <?php $csvTable = 'food';
                include 'includes/csv_buttons.php'; ?>
            </div>
        </details>
    </div>

    <table class="table food-table" aria-label="食品庫存">
        <thead>
            <tr>
                <th scope="col" class="food-select"><input type="checkbox" id="selectAllCheckbox" aria-label="選取全部顯示項目" class="select-checkbox"
                        onchange="toggleSelectAll(this)"></th>
                <th scope="col">圖片</th>
                <th scope="col">食品名稱</th>
                <th scope="col">數量</th>
                <th scope="col">價格</th>
                <th scope="col">商店</th>
                <th scope="col">有效期限</th>
            </tr>
        </thead>
        <tbody>
            <tr id="inlineAddRow" class="inline-add-row">
                <td></td>
                <td>
                    <div class="inline-edit inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="photo" aria-label="圖片網址" placeholder="圖片網址" oninput="updateInlinePhotoPreview(this)">
                        <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                            <input type="file" class="inline-photo-file" accept="image/*" style="display: none;" onchange="uploadInlinePhoto(this)">
                            <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳</button>
                            <div class="inline-photo-preview"></div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-always">
                        <input type="text" class="form-control inline-input" list="foodNames" data-field="name" aria-label="食品名稱" placeholder="食品名稱">
                        <div class="inline-actions">
                            <button type="button" class="btn btn-primary" onclick="saveInlineAdd()">儲存</button>
                            <button type="button" class="btn" onclick="cancelInlineAdd()">取消</button>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="number" class="form-control inline-input" data-field="amount" aria-label="數量" placeholder="數量">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="number" class="form-control inline-input" data-field="price" aria-label="單價" placeholder="價格">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="text" class="form-control inline-input" list="foodShops" data-field="shop" aria-label="商店" placeholder="商店">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="date" class="form-control inline-input" data-field="todate" aria-label="有效期限">
                    </div>
                </td>
            </tr>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #999;">暫無食品資料</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr data-food-item data-id="<?= htmlspecialchars($item['id'], ENT_QUOTES) ?>"
                        data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                        data-amount="<?php echo htmlspecialchars($item['amount'] ?? '', ENT_QUOTES); ?>"
                        data-price="<?php echo htmlspecialchars($item['price'] ?? '', ENT_QUOTES); ?>"
                        data-shop="<?php echo htmlspecialchars($item['shop'] ?? '', ENT_QUOTES); ?>"
                        data-todate="<?= $item['date'] ?>"
                        data-year="<?= substr($item['date'], 0, 4) ?>"
                        data-month="<?= $item['date'] ? (int) substr($item['date'], 5, 2) : '' ?>"
                        data-expiry="<?= $item['expiry'] ?>"
                        data-photo="<?php echo htmlspecialchars($item['photo'] ?? '', ENT_QUOTES); ?>">
                        <td class="food-select"><input type="checkbox" class="select-checkbox item-checkbox" data-id="<?= htmlspecialchars($item['id'], ENT_QUOTES) ?>"
                                aria-label="選取 <?= htmlspecialchars($item['name'], ENT_QUOTES) ?>" onchange="toggleSelectItem(this)"></td>
                        <td class="food-photo">
                            <div class="inline-view">
                                <?php if (!empty($item['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['photo']); ?>"
                                        alt="" width="50" height="50" loading="lazy">
                                <?php else: ?>
                                    <span class="food-no-photo" aria-label="無圖片">—</span>
                                <?php endif; ?>
                            </div>
                            <div class="inline-edit">
                                <input type="text" class="form-control inline-input" data-field="photo" aria-label="圖片網址" placeholder="圖片網址" oninput="updateInlinePhotoPreview(this)">
                                <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                    <input type="file" class="inline-photo-file" accept="image/*" style="display: none;" onchange="uploadInlinePhoto(this)">
                                    <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳</button>
                                    <div class="inline-photo-preview"></div>
                                </div>
                            </div>
                        </td>
                        <td class="food-name">
                            <div class="inline-view">
                                <strong><?= htmlspecialchars($item['name']) ?></strong>
                                <div class="food-row-actions">
                                    <button type="button" onclick="editFoodRow(this)">編輯</button>
                                    <button type="button" onclick="duplicateItem(this.closest('tr').dataset.id)">複製</button>
                                    <button type="button" onclick="deleteItem(this.closest('tr').dataset.id)">刪除</button>
                                </div>
                            </div>
                            <div class="inline-edit">
                                <input type="text" class="form-control inline-input" list="foodNames" data-field="name" aria-label="食品名稱" placeholder="食品名稱">
                                <div class="inline-actions">
                                    <button type="button" class="btn btn-primary" onclick="saveInlineEdit(this.closest('tr').dataset.id)">儲存</button>
                                    <button type="button" class="btn" onclick="cancelInlineEdit(this.closest('tr').dataset.id)">取消</button>
                                </div>
                            </div>
                        </td>
                        <td class="food-amount">
                            <span class="food-field-label">數量</span>
                            <span class="inline-view food-amount-value"><?= (int) ($item['amount'] ?? 0) ?></span>
                            <div class="food-amount-controls inline-view">
                                <button type="button" onclick="adjustFoodAmount(this.closest('tr').dataset.id, -1)" title="減少數量">-</button>
                                <button type="button" onclick="adjustFoodAmount(this.closest('tr').dataset.id, 1)" title="增加數量">+</button>
                            </div>
                            <div class="inline-edit inline-edit-row">
                                <input type="number" class="form-control inline-input" data-field="amount" aria-label="數量" placeholder="數量">
                            </div>
                        </td>
                        <td class="food-price">
                            <span class="food-field-label">單價</span>
                            <span class="inline-view"><?php echo formatMoney($item['price']); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="number" class="form-control inline-input" data-field="price" aria-label="單價" placeholder="價格">
                            </div>
                        </td>
                        <td class="food-shop">
                            <span class="food-field-label">商店</span>
                            <span class="inline-view"><?php echo htmlspecialchars($item['shop'] ?? '-'); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="text" class="form-control inline-input" list="foodShops" data-field="shop" aria-label="商店" placeholder="商店">
                            </div>
                        </td>
                        <td class="food-date">
                            <span class="food-field-label">有效期限</span>
                            <span class="inline-view"><?= $item['date'] ?: '無日期' ?>
                                <?php if ($item['days'] !== null): ?>
                                    <small class="food-days"><?= match (true) {
                                        $item['days'] < 0 => '已過期 ' . abs($item['days']) . ' 天',
                                        $item['days'] === 0 => '今天到期',
                                        default => '剩 ' . $item['days'] . ' 天',
                                    } ?></small>
                                <?php endif; ?>
                            </span>
                            <div class="inline-edit inline-edit-row">
                                <input type="date" class="form-control inline-input" data-field="todate" aria-label="有效期限">
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div id="foodNoMatch" class="food-no-match" hidden>
        <p>沒有符合條件的食品</p>
    </div>
</div>

<?php foreach (['name' => 'foodNames', 'shop' => 'foodShops'] as $field => $listId): ?>
    <datalist id="<?= $listId ?>">
        <?php
        $suggestions = array_unique(array_column($items, $field));
        sort($suggestions, SORT_NATURAL);
        foreach ($suggestions as $suggestion):
            if ($suggestion === null || trim($suggestion) === '') continue;
        ?>
            <option value="<?= htmlspecialchars($suggestion, ENT_QUOTES) ?>"></option>
        <?php endforeach; ?>
    </datalist>
<?php endforeach; ?>

<dialog id="modal" class="food-dialog" aria-labelledby="modalTitle">
    <div class="food-dialog-body">
        <button type="button" class="food-close" aria-label="關閉" onclick="closeModal()">&times;</button>
        <h2 id="modalTitle">新增食品</h2>
        <form id="itemForm">
            <input type="hidden" id="itemId" name="id">
            <div class="form-group">
                <label for="name">食品名稱 *</label>
                <input type="text" class="form-control" id="name" list="foodNames" name="name" autofocus required>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label for="amount">數量</label>
                    <input type="number" class="form-control" id="amount" name="amount">
                </div>
                <div class="form-group" style="flex:1">
                    <label for="price">價格</label>
                    <input type="number" class="form-control" id="price" name="price">
                </div>
            </div>
            <div class="form-group">
                <label for="shop">商店</label>
                <input type="text" class="form-control" id="shop" list="foodShops" name="shop">
            </div>
            <div class="form-group">
                <label for="todate">有效期限</label>
                <input type="date" class="form-control" id="todate" name="todate">
            </div>
            <div class="form-group">
                <label for="photo">圖片（網址或上傳）</label>
                <input type="text" class="form-control" id="photo" name="photo" placeholder="輸入圖片網址">
                <div style="margin-top: 8px;">
                    <input type="file" id="photoFile" accept="image/*" onchange="uploadPhoto()" style="display: none;">
                    <button type="button" class="btn" onclick="document.getElementById('photoFile').click()">
                        <i class="fa-solid fa-upload"></i> 上傳圖片
                    </button>
                </div>
                <div id="photoPreview" style="margin-top: 10px;"></div>
            </div>
            <p id="foodFormError" role="alert"></p>
            <button type="submit" class="btn btn-primary">儲存</button>
        </form>
    </div>
</dialog>

<?php include 'includes/upload-progress.php'; ?>

<script src="assets/js/food.js?v=20261127"></script>
