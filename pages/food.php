<?php
$pageTitle = '鋒兄食品 （＋商品庫存）';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM food ORDER BY CASE WHEN todate IS NULL THEN 1 ELSE 0 END, todate ASC, created_at DESC")->fetchAll();
$today = new DateTimeImmutable('today');
$expiredCount = 0;
$todayCount = 0;
$threeDayCount = 0;
$sevenDayCount = 0;
$lowStockCount = 0;
$totalValue = 0;
$years = [];

foreach ($items as $item) {
    $amount = (int) ($item['amount'] ?? 0);
    $price = (int) ($item['price'] ?? 0);
    $totalValue += $amount * $price;
    if ($amount <= 1) {
        $lowStockCount++;
    }
    if (!empty($item['todate'])) {
        $date = new DateTimeImmutable(date('Y-m-d', strtotime($item['todate'])));
        $days = (int) $today->diff($date)->format('%r%a');
        $years[] = $date->format('Y');
        if ($days < 0) {
            $expiredCount++;
        } elseif ($days === 0) {
            $todayCount++;
        } elseif ($days <= 3) {
            $threeDayCount++;
        } elseif ($days <= 7) {
            $sevenDayCount++;
        }
    }
}
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

<div class="content-header" style="display: flex; align-items: center; gap: 12px;">
    <h1 style="margin: 0;">鋒兄食品 <span style="font-size: 0.48em; color: var(--muted-text); font-weight: 600;">（＋商品庫存）</span></h1>
    <span style="background: linear-gradient(135deg, #27ae60, #2ecc71); color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
        <?php echo count($items); ?> 項
    </span>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <div class="food-ops-panel">
        <div class="food-stat-card food-stat-danger">
            <span>已過期</span>
            <strong><?php echo $expiredCount; ?></strong>
        </div>
        <div class="food-stat-card food-stat-warning">
            <span>今天到期</span>
            <strong><?php echo $todayCount; ?></strong>
        </div>
        <div class="food-stat-card">
            <span>3 天內</span>
            <strong><?php echo $threeDayCount; ?></strong>
        </div>
        <div class="food-stat-card">
            <span>低庫存</span>
            <strong><?php echo $lowStockCount; ?></strong>
        </div>
        <div class="food-stat-card">
            <span>庫存估值</span>
            <strong><?php echo formatMoney($totalValue); ?></strong>
        </div>
    </div>

    <div class="food-filter-panel">
        <label class="food-search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="foodSearchInput" class="form-control" placeholder="搜尋食品或商店..." oninput="filterFoods()">
        </label>
        <select id="foodExpiryFilter" class="form-control" onchange="filterFoods()">
            <option value="all">全部到期狀態</option>
            <option value="expired">已過期</option>
            <option value="today">今天到期</option>
            <option value="3days">3 天內</option>
            <option value="7days">7 天內</option>
            <option value="normal">正常</option>
            <option value="nodate">無日期</option>
        </select>
        <select id="foodYearFilter" class="form-control" onchange="filterFoods(); updateMonthOptions();">
            <option value="">全部年份</option>
            <option value="__empty">無日期</option>
            <?php foreach ($years as $year): ?>
                <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?> 年</option>
            <?php endforeach; ?>
        </select>
        <select id="foodMonthFilter" class="form-control" onchange="filterFoods()">
            <option value="">全部月份</option>
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>"><?php echo $m; ?> 月</option>
            <?php endfor; ?>
        </select>
        <span class="food-result-count">顯示 <strong id="foodVisibleCount"><?php echo count($items); ?></strong> / <?php echo count($items); ?> 項</span>
    </div>

    <div class="food-quick-add card">
        <div>
            <h3 class="card-title">快速新增</h3>
            <p style="color: var(--muted-text); margin-top: 4px;">套用常用食品與預設到期日，也可以直接輸入自訂項目。</p>
        </div>
        <div class="food-preset-row">
            <button type="button" class="btn btn-ghost" onclick="applyFoodPreset('牛奶', 1, 7, '冷藏')">牛奶 +7天</button>
            <button type="button" class="btn btn-ghost" onclick="applyFoodPreset('雞蛋', 10, 14, '冷藏')">雞蛋 +14天</button>
            <button type="button" class="btn btn-ghost" onclick="applyFoodPreset('吐司', 1, 3, '常溫')">吐司 +3天</button>
            <button type="button" class="btn btn-ghost" onclick="applyFoodPreset('優格', 1, 7, '冷藏')">優格 +7天</button>
            <button type="button" class="btn btn-ghost" onclick="applyFoodPreset('即食飯', 1, 30, '常溫')">即食飯 +30天</button>
        </div>
    </div>

    <div class="action-buttons-bar">
        <button class="btn btn-primary" onclick="handleAdd()" title="新增食品"><i class="fas fa-plus"></i></button>
        <?php $csvTable = 'food';
        include 'includes/csv_buttons.php'; ?>
        <?php include 'includes/batch-delete.php'; ?>
    </div>

    <!-- 桌面版表格 -->
    <table class="table desktop-only" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAllCheckbox" class="select-checkbox"
                        onchange="toggleSelectAll(this)"></th>
                <th>圖片</th>
                <th>食品名稱</th>
                <th>數量</th>
                <th>價格</th>
                <th>商店</th>
                <th>有效期限</th>
            </tr>
        </thead>
        <tbody>
            <tr id="inlineAddRow" class="inline-add-row">
                <td></td>
                <td>
                    <div class="inline-edit inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="photo" placeholder="圖片網址" oninput="updateInlinePhotoPreview(this)">
                        <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                            <input type="file" class="inline-photo-file" accept="image/*" style="display: none;" onchange="uploadInlinePhoto(this)">
                            <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳</button>
                            <div class="inline-photo-preview"></div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="name" placeholder="食品名稱">
                        <div class="inline-actions">
                            <button type="button" class="btn btn-primary" onclick="saveInlineAdd()">儲存</button>
                            <button type="button" class="btn" onclick="cancelInlineAdd()">取消</button>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="number" class="form-control inline-input" data-field="amount" placeholder="數量">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="number" class="form-control inline-input" data-field="price" placeholder="價格">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="shop" placeholder="商店">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="date" class="form-control inline-input" data-field="todate">
                    </div>
                </td>
            </tr>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #999;">暫無食品資料</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $daysRemaining = null;
                    $expiryBucket = 'nodate';
                    if (!empty($item['todate'])) {
                        $foodDate = new DateTimeImmutable(date('Y-m-d', strtotime($item['todate'])));
                        $daysRemaining = (int) $today->diff($foodDate)->format('%r%a');
                        if ($daysRemaining < 0) {
                            $expiryBucket = 'expired';
                        } elseif ($daysRemaining === 0) {
                            $expiryBucket = 'today';
                        } elseif ($daysRemaining <= 3) {
                            $expiryBucket = '3days';
                        } elseif ($daysRemaining <= 7) {
                            $expiryBucket = '7days';
                        } else {
                            $expiryBucket = 'normal';
                        }
                    }
                    ?>
                    <tr data-food-item data-id="<?php echo $item['id']; ?>"
                        data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                        data-amount="<?php echo htmlspecialchars($item['amount'] ?? '', ENT_QUOTES); ?>"
                        data-price="<?php echo htmlspecialchars($item['price'] ?? '', ENT_QUOTES); ?>"
                        data-shop="<?php echo htmlspecialchars($item['shop'] ?? '', ENT_QUOTES); ?>"
                        data-todate="<?php echo htmlspecialchars($item['todate'] ?? '', ENT_QUOTES); ?>"
                        data-year="<?php echo !empty($item['todate']) ? date('Y', strtotime($item['todate'])) : ''; ?>"
                        data-month="<?php echo !empty($item['todate']) ? (int) date('n', strtotime($item['todate'])) : ''; ?>"
                        data-expiry="<?php echo $expiryBucket; ?>"
                        data-photo="<?php echo htmlspecialchars($item['photo'] ?? '', ENT_QUOTES); ?>">
                        <td><input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo $item['id']; ?>"
                                onchange="toggleSelectItem(this)"></td>
                        <td>
                            <div class="inline-view">
                                <?php if (!empty($item['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['photo']); ?>"
                                        style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </div>
                            <div class="inline-edit">
                                <input type="text" class="form-control inline-input" data-field="photo" placeholder="圖片網址" oninput="updateInlinePhotoPreview(this)">
                                <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                    <input type="file" class="inline-photo-file" accept="image/*" style="display: none;" onchange="uploadInlinePhoto(this)">
                                    <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳</button>
                                    <div class="inline-photo-preview"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="inline-view">
                                <?php echo htmlspecialchars($item['name']); ?>
                                <span class="card-edit-btn" onclick="startInlineEdit('<?php echo $item['id']; ?>')"
                                    style="cursor: pointer; margin-left: 8px;"><i class="fas fa-pen"></i></span>
                                <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')"
                                    style="margin-left: 6px; cursor: pointer;">&times;</span>
                            </div>
                            <div class="inline-edit">
                                <input type="text" class="form-control inline-input" data-field="name" placeholder="食品名稱">
                                <div class="inline-actions">
                                    <button type="button" class="btn btn-primary" onclick="saveInlineEdit('<?php echo $item['id']; ?>')">儲存</button>
                                    <button type="button" class="btn" onclick="cancelInlineEdit('<?php echo $item['id']; ?>')">取消</button>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo $item['amount'] ?? 0; ?></span>
                            <div class="food-amount-controls inline-view">
                                <button type="button" onclick="adjustFoodAmount('<?php echo $item['id']; ?>', -1)" title="減少數量">-</button>
                                <button type="button" onclick="adjustFoodAmount('<?php echo $item['id']; ?>', 1)" title="增加數量">+</button>
                            </div>
                            <div class="inline-edit inline-edit-row">
                                <input type="number" class="form-control inline-input" data-field="amount" placeholder="數量">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo formatMoney($item['price']); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="number" class="form-control inline-input" data-field="price" placeholder="價格">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo htmlspecialchars($item['shop'] ?? '-'); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="text" class="form-control inline-input" data-field="shop" placeholder="商店">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo formatDate($item['todate']); ?></span>
                            <div class="food-quick-actions inline-view">
                                <button type="button" class="btn btn-sm" onclick="cleanupFood('<?php echo $item['id']; ?>', 'eat')">吃完</button>
                                <button type="button" class="btn btn-sm" onclick="cleanupFood('<?php echo $item['id']; ?>', 'discard')">丟棄</button>
                            </div>
                            <div class="inline-edit inline-edit-row">
                                <input type="date" class="form-control inline-input" data-field="todate">
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- 手機版卡片 -->
    <div class="mobile-only food-mobile-list" style="margin-top: 20px;">
        <?php if (empty($items)): ?>
            <div class="mobile-card" style="text-align: center; color: #999; padding: 40px;">暫無食品資料</div>
        <?php else: ?>
            <?php foreach ($items as $item):
                $isExpired = !empty($item['todate']) && strtotime($item['todate']) < time();
                $isExpiringSoon = !empty($item['todate']) && !$isExpired && strtotime($item['todate']) < strtotime('+7 days');
                ?>
                <div class="mobile-card" data-food-item
                    data-id="<?php echo $item['id']; ?>"
                    data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                    data-shop="<?php echo htmlspecialchars($item['shop'] ?? '', ENT_QUOTES); ?>"
                    data-year="<?php echo !empty($item['todate']) ? date('Y', strtotime($item['todate'])) : ''; ?>"
                    data-month="<?php echo !empty($item['todate']) ? (int) date('n', strtotime($item['todate'])) : ''; ?>"
                    data-expiry="<?php echo $isExpired ? 'expired' : ($isExpiringSoon ? '7days' : (!empty($item['todate']) ? 'normal' : 'nodate')); ?>"
                    style="border-left: 4px solid <?php echo $isExpired ? '#e74c3c' : ($isExpiringSoon ? '#f39c12' : '#27ae60'); ?>;">
                    <div class="mobile-card-actions">
                        <span class="card-edit-btn" onclick="editItem('<?php echo $item['id']; ?>')"><i
                                class="fas fa-pen"></i></span>
                        <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                    </div>
                    <div class="mobile-card-header">
                        <?php if (!empty($item['photo'])): ?>
                            <img src="<?php echo htmlspecialchars($item['photo']); ?>"
                                style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                        <?php else: ?>
                            <div
                                style="width: 50px; height: 50px; background: #ecf0f1; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-utensils" style="color: #95a5a6; font-size: 1.2rem;"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="mobile-card-title"><?php echo htmlspecialchars($item['name']); ?></div>
                            <?php if (!empty($item['shop'])): ?>
                                <div style="font-size: 0.8rem; color: #888;"><i class="fas fa-store"></i>
                                    <?php echo htmlspecialchars($item['shop']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mobile-card-info">
                        <div class="mobile-card-item">
                            <span class="mobile-card-label">數量</span>
                            <span class="mobile-card-value">
                                <?php echo $item['amount'] ?? 0; ?>
                                <span class="food-amount-controls">
                                    <button type="button" onclick="adjustFoodAmount('<?php echo $item['id']; ?>', -1)">-</button>
                                    <button type="button" onclick="adjustFoodAmount('<?php echo $item['id']; ?>', 1)">+</button>
                                </span>
                            </span>
                        </div>
                        <div class="mobile-card-item">
                            <span class="mobile-card-label">價格</span>
                            <span class="mobile-card-value"><?php echo formatMoney($item['price']); ?></span>
                        </div>
                        <div class="mobile-card-item">
                            <span class="mobile-card-label">有效期限</span>
                            <span class="mobile-card-value"
                                style="color: <?php echo $isExpired ? '#e74c3c' : ($isExpiringSoon ? '#f39c12' : 'inherit'); ?>;">
                                <?php echo formatDate($item['todate']) ?: '-'; ?>
                                <?php if ($isExpired): ?><span style="font-size: 0.75rem;"> (已過期)</span><?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
                        <button type="button" class="btn btn-sm" onclick="cleanupFood('<?php echo $item['id']; ?>', 'eat')">吃完</button>
                        <button type="button" class="btn btn-sm" onclick="cleanupFood('<?php echo $item['id']; ?>', 'discard')">丟棄</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="modal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">新增食品</h2>
        <form id="itemForm">
            <input type="hidden" id="itemId" name="id">
            <div class="form-group">
                <label>食品名稱 *</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>數量</label>
                    <input type="number" class="form-control" id="amount" name="amount">
                </div>
                <div class="form-group" style="flex:1">
                    <label>價格</label>
                    <input type="number" class="form-control" id="price" name="price">
                </div>
            </div>
            <div class="form-group">
                <label>商店</label>
                <input type="text" class="form-control" id="shop" name="shop">
            </div>
            <div class="form-group">
                <label>有效期限</label>
                <input type="date" class="form-control" id="todate" name="todate">
            </div>
            <div class="form-group">
                <label>圖片（網址或上傳）</label>
                <input type="text" class="form-control" id="photo" name="photo" placeholder="輸入圖片網址">
                <div style="margin-top: 8px;">
                    <input type="file" id="photoFile" accept="image/*" onchange="uploadPhoto()" style="display: none;">
                    <button type="button" class="btn" onclick="document.getElementById('photoFile').click()">
                        <i class="fa-solid fa-upload"></i> 上傳圖片
                    </button>
                </div>
                <div id="photoPreview" style="margin-top: 10px;"></div>
            </div>
            <button type="submit" class="btn btn-primary">儲存</button>
        </form>
    </div>
</div>

<?php include 'includes/upload-progress.php'; ?>

<style>
    .food-mobile-list {
        display: grid;
        gap: 14px;
    }

    .food-ops-panel,
    .food-filter-panel {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .food-stat-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 14px 16px;
        box-shadow: 0 12px 26px var(--shadow);
    }

    .food-stat-card span {
        display: block;
        color: var(--muted-text);
        font-size: 0.82rem;
        margin-bottom: 6px;
    }

    .food-stat-card strong {
        font-size: 1.35rem;
    }

    .food-stat-danger strong {
        color: #e74c3c;
    }

    .food-stat-warning strong {
        color: #f39c12;
    }

    .food-filter-panel {
        grid-template-columns: minmax(220px, 1.4fr) repeat(3, minmax(130px, 0.7fr)) auto;
        align-items: center;
    }

    .food-search-box {
        position: relative;
        display: block;
    }

    .food-search-box i {
        position: absolute;
        top: 50%;
        left: 12px;
        transform: translateY(-50%);
        color: var(--muted-text);
    }

    .food-search-box input {
        padding-left: 38px;
    }

    .food-result-count {
        color: var(--muted-text);
        white-space: nowrap;
    }

    .food-quick-add {
        margin-bottom: 16px;
        display: grid;
        gap: 12px;
    }

    .food-preset-row,
    .food-quick-actions,
    .food-amount-controls {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .food-amount-controls button {
        width: 26px;
        height: 26px;
        border: 1px solid var(--border-color);
        background: var(--input-bg);
        color: var(--text-color);
        border-radius: 8px;
        cursor: pointer;
        font-weight: 800;
    }

    .food-mobile-list .mobile-card {
        border-radius: 20px;
        padding: 18px 16px;
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
    }

    .food-mobile-list .mobile-card-header {
        align-items: flex-start;
        gap: 14px;
        padding-right: 64px;
    }

    .food-mobile-list .mobile-card-info {
        gap: 12px;
        border-radius: 14px;
    }

    .food-mobile-list .mobile-card-value {
        line-height: 1.45;
    }

    .food-mobile-list .mobile-card-title {
        line-height: 1.35;
    }

    @media (max-width: 768px) {
        .food-mobile-list .mobile-card-info {
            grid-template-columns: 1fr 1fr;
        }

        .food-filter-panel {
            grid-template-columns: 1fr 1fr;
        }

        .food-mobile-list .mobile-card-actions {
            top: 14px;
            right: 14px;
        }
    }

    @media (max-width: 560px) {
        .food-mobile-list .mobile-card {
            padding: 16px 14px;
        }

        .food-mobile-list .mobile-card-header {
            grid-template-columns: 50px 1fr;
            display: grid;
            padding-right: 54px;
        }

        .food-mobile-list .mobile-card-info {
            grid-template-columns: 1fr;
        }

        .food-filter-panel,
        .food-ops-panel {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    const TABLE = 'food';
    initBatchDelete(TABLE);

    function handleAdd() {
        // Use inline editing for all screen sizes
        startInlineAdd();
    }

    function addDays(days) {
        const date = new Date();
        date.setHours(0, 0, 0, 0);
        date.setDate(date.getDate() + Number(days || 0));
        return date.toISOString().slice(0, 10);
    }

    function applyFoodPreset(name, amount, days, shop) {
        startInlineAdd();
        const row = document.getElementById('inlineAddRow');
        if (!row) return;
        row.querySelector('[data-field="name"]').value = name;
        row.querySelector('[data-field="amount"]').value = amount || 1;
        row.querySelector('[data-field="price"]').value = 0;
        row.querySelector('[data-field="shop"]').value = shop || '';
        row.querySelector('[data-field="todate"]').value = addDays(days);
    }

    function filterFoods() {
        const query = (document.getElementById('foodSearchInput')?.value || '').trim().toLowerCase();
        const expiry = document.getElementById('foodExpiryFilter')?.value || 'all';
        const year = document.getElementById('foodYearFilter')?.value || '';
        const month = document.getElementById('foodMonthFilter')?.value || '';
        let visible = 0;
        document.querySelectorAll('[data-food-item]').forEach(item => {
            const haystack = ((item.dataset.name || '') + ' ' + (item.dataset.shop || '')).toLowerCase();
            const matchesQuery = !query || haystack.includes(query);
            const itemExpiry = item.dataset.expiry || 'nodate';
            const matchesExpiry = expiry === 'all'
                || itemExpiry === expiry
                || (expiry === '7days' && ['today', '3days', '7days'].includes(itemExpiry))
                || (expiry === '3days' && ['today', '3days'].includes(itemExpiry));
            const matchesYear = !year || (year === '__empty' ? !item.dataset.year : item.dataset.year === year);
            const matchesMonth = !month || item.dataset.month === month;
            const show = matchesQuery && matchesExpiry && matchesYear && matchesMonth;
            item.style.display = show ? '' : 'none';
            if (show && item.tagName !== 'TR') visible++;
            if (show && item.tagName === 'TR') visible++;
        });
        const desktopVisible = document.querySelectorAll('table.desktop-only [data-food-item]:not([style*="display: none"])').length;
        const mobileVisible = document.querySelectorAll('.food-mobile-list [data-food-item]:not([style*="display: none"])').length;
        const counter = document.getElementById('foodVisibleCount');
        if (counter) counter.textContent = Math.max(desktopVisible, mobileVisible, visible);
    }

    function updateMonthOptions() {
        const year = document.getElementById('foodYearFilter')?.value || '';
        const monthSelect = document.getElementById('foodMonthFilter');
        if (!monthSelect) return;
        monthSelect.disabled = year === '__empty';
        if (year === '__empty') monthSelect.value = '';
    }

    function adjustFoodAmount(id, delta) {
        const row = getRowById(id);
        const current = row ? parseInt(row.dataset.amount || '0', 10) || 0 : 0;
        const nextAmount = Math.max(0, current + delta);
        fetch(`api.php?action=update&table=${TABLE}&id=${id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ amount: nextAmount })
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) location.reload();
                else alert('更新數量失敗: ' + (res.error || ''));
            });
    }

    function cleanupFood(id, action) {
        const label = action === 'eat' ? '標記吃完' : '標記丟棄';
        if (!confirm(`確定要${label}並移除這筆食品嗎？`)) return;
        deleteItem(id);
    }

    function startInlineAdd() {
        const row = document.getElementById('inlineAddRow');
        if (!row) {
            alert('找不到新增列，請重新整理頁面');
            return;
        }
        row.style.setProperty('display', 'table-row', 'important');
        row.querySelectorAll('[data-field]').forEach(input => {
            input.value = '';
        });
        const nameInput = row.querySelector('[data-field="name"]');
        if (nameInput) nameInput.focus();
    }

    function cancelInlineAdd() {
        const row = document.getElementById('inlineAddRow');
        if (!row) return;
        row.style.display = 'none';
    }

    function saveInlineAdd() {
        const row = document.getElementById('inlineAddRow');
        if (!row) return;
        const name = row.querySelector('[data-field="name"]').value.trim();
        if (!name) {
            alert('請輸入食品名稱');
            return;
        }
        const data = {
            name,
            amount: row.querySelector('[data-field="amount"]').value || 0,
            price: row.querySelector('[data-field="price"]').value || 0,
            shop: row.querySelector('[data-field="shop"]').value.trim(),
            todate: row.querySelector('[data-field="todate"]').value || null,
            photo: row.querySelector('[data-field="photo"]').value.trim()
        };
        fetch(`api.php?action=create&table=${TABLE}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) location.reload();
                else alert('儲存失敗: ' + (res.error || res.message || ''));
            })
            .catch(err => alert('儲存失敗: ' + (err.message || '網路錯誤')));
    }

    function getRowById(id) {
        return document.querySelector(`tr[data-id="${id}"]`);
    }

    function startInlineEdit(id) {
        const row = getRowById(id);
        if (!row) return;
        row.querySelectorAll('.inline-view').forEach(el => el.style.display = 'none');
        row.querySelectorAll('.inline-edit').forEach(el => el.style.display = 'block');
        fillInlineInputs(row);
    }

    function cancelInlineEdit(id) {
        const row = getRowById(id);
        if (!row) return;
        row.querySelectorAll('.inline-view').forEach(el => el.style.display = '');
        row.querySelectorAll('.inline-edit').forEach(el => el.style.display = 'none');
    }

    function fillInlineInputs(row) {
        const data = row.dataset;
        const todate = data.todate ? data.todate.split(' ')[0] : '';
        const nameInput = row.querySelector('[data-field="name"]');
        if (nameInput) nameInput.value = data.name || '';
        const amountInput = row.querySelector('[data-field="amount"]');
        if (amountInput) amountInput.value = data.amount || '';
        const priceInput = row.querySelector('[data-field="price"]');
        if (priceInput) priceInput.value = data.price || '';
        const shopInput = row.querySelector('[data-field="shop"]');
        if (shopInput) shopInput.value = data.shop || '';
        const todateInput = row.querySelector('[data-field="todate"]');
        if (todateInput) todateInput.value = todate || '';
        const photoInput = row.querySelector('[data-field="photo"]');
        if (photoInput) {
            photoInput.value = data.photo || '';
            updateInlinePhotoPreview(photoInput);
        }
    }

    function saveInlineEdit(id) {
        const row = getRowById(id);
        if (!row) return;
        const name = row.querySelector('[data-field="name"]').value.trim();
        if (!name) {
            alert('請輸入食品名稱');
            return;
        }
        const data = {
            name,
            amount: row.querySelector('[data-field="amount"]').value || 0,
            price: row.querySelector('[data-field="price"]').value || 0,
            shop: row.querySelector('[data-field="shop"]').value.trim(),
            todate: row.querySelector('[data-field="todate"]').value || null,
            photo: row.querySelector('[data-field="photo"]').value.trim()
        };
        fetch(`api.php?action=update&table=${TABLE}&id=${id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) location.reload();
                else alert('儲存失敗: ' + (res.error || ''));
            });
    }

    function openModal() {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modalTitle').textContent = '新增食品';
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '';
        document.getElementById('photoPreview').innerHTML = '';
    }

    function closeModal() {
        document.getElementById('modal').style.display = 'none';
    }

    function editItem(id) {
        fetch(`api.php?action=get&table=${TABLE}&id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    const d = res.data;
                    document.getElementById('itemId').value = d.id;
                    document.getElementById('name').value = d.name || '';
                    document.getElementById('amount').value = d.amount || '';
                    document.getElementById('price').value = d.price || '';
                    document.getElementById('shop').value = d.shop || '';
                    document.getElementById('todate').value = d.todate ? d.todate.split(' ')[0] : '';
                    document.getElementById('photo').value = d.photo || '';
                    updatePhotoPreview();
                    document.getElementById('modalTitle').textContent = '編輯食品';
                    document.getElementById('modal').style.display = 'flex';
                }
            });
    }

    function deleteItem(id) {
        if (confirm('確定要刪除嗎？')) {
            fetch(`api.php?action=delete&table=${TABLE}&id=${id}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success) location.reload();
                    else alert('刪除失敗');
                });
        }
    }

    document.getElementById('itemForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const id = document.getElementById('itemId').value;
        const action = id ? 'update' : 'create';
        const url = id ? `api.php?action=${action}&table=${TABLE}&id=${id}` : `api.php?action=${action}&table=${TABLE}`;

        const data = {
            name: document.getElementById('name').value,
            amount: document.getElementById('amount').value || 0,
            price: document.getElementById('price').value || 0,
            shop: document.getElementById('shop').value,
            todate: document.getElementById('todate').value || null,
            photo: document.getElementById('photo').value
        };

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) location.reload();
                else alert('儲存失敗: ' + (res.error || ''));
            });
    });

    function uploadInlinePhoto(fileInput) {
        if (!fileInput.files || !fileInput.files[0]) return;
        const photoInput = fileInput.closest('.inline-edit').querySelector('[data-field="photo"]');
        uploadFileWithProgress(fileInput.files[0],
            function (res) {
                photoInput.value = res.file;
                updateInlinePhotoPreview(photoInput);
            },
            function (error) {
                alert('上傳失敗: ' + error);
            }
        );
        fileInput.value = '';
    }

    function updateInlinePhotoPreview(input) {
        const preview = input.closest('.inline-edit').querySelector('.inline-photo-preview');
        if (!preview) return;
        const url = input.value.trim();
        preview.innerHTML = url
            ? `<img src="${url}" style="width: 36px; height: 36px; object-fit: cover; border-radius: 4px;">`
            : '';
    }

    function uploadPhoto() {
        const input = document.getElementById('photoFile');
        if (!input.files || !input.files[0]) return;

        uploadFileWithProgress(input.files[0],
            function (res) {
                document.getElementById('photo').value = res.file;
                updatePhotoPreview();
            },
            function (error) {
                alert('上傳失敗: ' + error);
            }
        );
        input.value = '';
    }

    function updatePhotoPreview() {
        const photo = document.getElementById('photo').value;
        const preview = document.getElementById('photoPreview');

        if (photo) {
            preview.innerHTML = `<img src="${photo}" style="max-width: 150px; max-height: 150px; border-radius: 5px;">`;
        } else {
            preview.innerHTML = '';
        }
    }

    // 當圖片網址改變時更新預覽
    document.getElementById('photo').addEventListener('change', updatePhotoPreview);
    document.getElementById('photo').addEventListener('input', updatePhotoPreview);
</script>
