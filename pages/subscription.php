<?php
$pageTitle = '訂閱管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM subscription ORDER BY nextdate IS NULL, nextdate ASC")->fetchAll();
$availableYears = [];
foreach ($items as $item) {
    if (!empty($item['nextdate'])) {
        $year = date('Y', strtotime($item['nextdate']));
        $availableYears[$year] = $year;
    }
}
krsort($availableYears);

// 取得已有的服務名稱、網站、帳號（去重複）
$existingNames = $pdo->query("SELECT DISTINCT name FROM subscription WHERE name IS NOT NULL AND name != '' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$existingSites = $pdo->query("SELECT DISTINCT site FROM subscription WHERE site IS NOT NULL AND site != '' ORDER BY site")->fetchAll(PDO::FETCH_COLUMN);
$existingAccounts = $pdo->query("SELECT DISTINCT account FROM subscription WHERE account IS NOT NULL AND account != '' ORDER BY account")->fetchAll(PDO::FETCH_COLUMN);

// 匯率轉換 (轉為新台幣)
$exchangeRates = [
    'TWD' => 1,
    'USD' => 35,
    'EUR' => 40,
    'JPY' => 0.35,
    'CNY' => 4.5,
    'HKD' => 4
];

function convertToTWD($price, $currency, $rates)
{
    $currency = strtoupper($currency ?? 'TWD');
    $rate = $rates[$currency] ?? 1;
    return round($price * $rate);
}

function formatDaysFromToday($date)
{
    if (empty($date)) {
        return '-';
    }

    $today = new DateTime('today');
    $target = new DateTime(date('Y-m-d', strtotime($date)));
    $days = (int) $today->diff($target)->format('%r%a');

    if ($days === 0) {
        return '&#20170;&#22825;';
    }

    if ($days > 0) {
        return $days . ' &#22825;';
    }

    return abs($days) . ' &#22825;&#21069;';
}

function getDaysUntil($date)
{
    if (empty($date)) {
        return '';
    }

    $today = new DateTime('today');
    $target = new DateTime(date('Y-m-d', strtotime($date)));
    return (int) $today->diff($target)->format('%r%a');
}
?>

<div class="content-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <h1 style="margin: 0;">鋒兄訂閱</h1>
        <span style="background: linear-gradient(135deg, #e67e22, #f39c12); color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
            <?php echo count($items); ?> 項
        </span>
    </div>
    <div class="subscription-filters" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
        <select id="yearFilter" class="form-control form-control-sm subscription-select-filter" onchange="applyFilters()">
            <option value="">全部年份</option>
            <?php foreach ($availableYears as $year): ?>
                <option value="<?php echo $year; ?>"><?php echo $year; ?> 年</option>
            <?php endforeach; ?>
        </select>
        <select id="monthFilter" class="form-control form-control-sm subscription-select-filter" onchange="applyFilters()">
            <option value="">全部月份</option>
            <option value="__none">無月份</option>
            <?php for ($month = 1; $month <= 12; $month++): ?>
                <option value="<?php echo str_pad((string) $month, 2, '0', STR_PAD_LEFT); ?>"><?php echo $month; ?> 月</option>
            <?php endfor; ?>
        </select>
        <button class="btn btn-sm filter-btn" id="within7Btn" onclick="toggleWithin7()" data-within="7">&#55;&#32;&#22825;&#20839;</button>
        <button class="btn btn-sm filter-btn active" onclick="filterByContinue('')" data-continue="">&#20840;&#37096;</button>
        <button class="btn btn-sm filter-btn" onclick="filterByContinue('1')" data-continue="1">&#32396;&#35330;</button>
        <button class="btn btn-sm filter-btn" onclick="filterByContinue('0')" data-continue="0">&#19981;&#32396;</button>
    </div>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <div class="action-buttons-bar">
        <button class="btn btn-primary" onclick="handleAdd()" title="新增訂閱"><i class="fas fa-plus"></i></button>
        <?php $csvTable = 'subscription';
        include 'includes/csv_buttons.php'; ?>
        <?php include 'includes/batch-delete.php'; ?>
    </div>

    <!-- 桌面版表格 -->
    <table class="table desktop-only" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAllCheckbox" class="select-checkbox"
                        onchange="toggleSelectAll(this)"></th>
                <th>服務名稱</th>
                <th>價格 (TWD)</th>
                <th>下次付款日期</th>
                <th>續訂</th>
            </tr>
        </thead>
        <tbody>
            <tr id="inlineAddRow" class="inline-add-row">
                <td></td>
                <td>
                    <div class="inline-edit inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="name" placeholder="服務名稱">
                        <input type="url" class="form-control inline-input" data-field="site" placeholder="網站">
                        <input type="text" class="form-control inline-input" data-field="account" placeholder="帳號">
                        <textarea class="form-control inline-input" data-field="note" rows="2" placeholder="備註"></textarea>
                        <div class="inline-actions">
                            <button type="button" class="btn btn-primary" onclick="saveInlineAdd()">儲存</button>
                            <button type="button" class="btn" onclick="cancelInlineAdd()">取消</button>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="number" class="form-control inline-input" data-field="price" placeholder="價格">
                        <select class="form-control inline-input" data-field="currency">
                            <option value="TWD">TWD 新台幣</option>
                            <option value="USD">USD 美元</option>
                            <option value="EUR">EUR 歐元</option>
                            <option value="JPY">JPY 日圓</option>
                            <option value="CNY">CNY 人民幣</option>
                            <option value="HKD">HKD 港幣</option>
                        </select>
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="date" class="form-control inline-input" data-field="nextdate">
                        <div class="date-shift-btns">
                            <button type="button" class="btn btn-sm btn-date-shift btn-minus" onclick="shiftDate(this.closest('.inline-edit-row').querySelector('[data-field=\'nextdate\']'), -30)">-30天</button>
                            <button type="button" class="btn btn-sm btn-date-shift btn-plus" onclick="shiftDate(this.closest('.inline-edit-row').querySelector('[data-field=\'nextdate\']'), 30)">+30天</button>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" data-field="continue" checked> 續訂
                        </label>
                    </div>
                </td>
            </tr>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #999;">暫無訂閱資料</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr data-id="<?php echo $item['id']; ?>"
                        data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                        data-site="<?php echo htmlspecialchars($item['site'] ?? '', ENT_QUOTES); ?>"
                        data-price="<?php echo htmlspecialchars($item['price'] ?? '', ENT_QUOTES); ?>"
                        data-currency="<?php echo htmlspecialchars($item['currency'] ?? 'TWD', ENT_QUOTES); ?>"
                        data-nextdate="<?php echo htmlspecialchars($item['nextdate'] ?? '', ENT_QUOTES); ?>"
                        data-year="<?php echo !empty($item['nextdate']) ? date('Y', strtotime($item['nextdate'])) : ''; ?>"
                        data-month="<?php echo !empty($item['nextdate']) ? date('m', strtotime($item['nextdate'])) : ''; ?>"
                        data-days="<?php echo getDaysUntil($item['nextdate'] ?? ''); ?>"
                        data-account="<?php echo htmlspecialchars($item['account'] ?? '', ENT_QUOTES); ?>"
                        data-note="<?php echo htmlspecialchars($item['note'] ?? '', ENT_QUOTES); ?>"
                        data-continue="<?php echo htmlspecialchars($item['continue'] ?? 0, ENT_QUOTES); ?>">
                        <td><input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo $item['id']; ?>"
                                onchange="toggleSelectItem(this)"></td>
                        <td>
                            <div class="inline-view">
                                <?php if ($item['site']): ?>
                                    <?php $domain = parse_url($item['site'], PHP_URL_HOST); ?>
                                    <img src="https://www.google.com/s2/favicons?domain=<?php echo $domain; ?>&sz=16"
                                        style="width: 16px; height: 16px; vertical-align: middle; margin-right: 5px;">
                                    <a href="<?php echo htmlspecialchars($item['site']); ?>"
                                        target="_blank"><?php echo htmlspecialchars($item['name']); ?></a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($item['name']); ?>
                                <?php endif; ?>
                                <span class="card-edit-btn" onclick="startInlineEdit('<?php echo $item['id']; ?>')"
                                    style="cursor: pointer; margin-left: 8px;"><i class="fas fa-pen"></i></span>
                                <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')"
                                    style="margin-left: 6px; cursor: pointer;">&times;</span>
                                <?php if (!empty($item['account'])): ?>
                                    <br><span
                                        style="font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($item['account']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['note'])): ?>
                                    <br><span
                                        style="font-size: 0.8rem; color: #999;"><?php echo htmlspecialchars($item['note']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="inline-edit">
                                <input type="text" class="form-control inline-input" data-field="name" placeholder="服務名稱">
                                <input type="url" class="form-control inline-input" data-field="site" placeholder="網站">
                                <input type="text" class="form-control inline-input" data-field="account" placeholder="帳號">
                                <textarea class="form-control inline-input" data-field="note" rows="2" placeholder="備註"></textarea>
                                <div class="inline-actions">
                                    <button type="button" class="btn btn-primary" onclick="saveInlineEdit('<?php echo $item['id']; ?>')">儲存</button>
                                    <button type="button" class="btn" onclick="cancelInlineEdit('<?php echo $item['id']; ?>')">取消</button>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo formatMoney(convertToTWD($item['price'], $item['currency'], $exchangeRates)); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="number" class="form-control inline-input" data-field="price" placeholder="價格">
                                <select class="form-control inline-input" data-field="currency">
                                    <option value="TWD">TWD 新台幣</option>
                                    <option value="USD">USD 美元</option>
                                    <option value="EUR">EUR 歐元</option>
                                    <option value="JPY">JPY 日圓</option>
                                    <option value="CNY">CNY 人民幣</option>
                                    <option value="HKD">HKD 港幣</option>
                                </select>
                            </div>
                        </td>
                        <td>
                            <span class="inline-view subscription-date-cell">
                                <span class="subscription-date-primary"><?php echo formatDate($item['nextdate']); ?></span>
                                <span class="subscription-date-secondary"><?php echo formatDaysFromToday($item['nextdate']); ?></span>
                            </span>
                            <div class="inline-edit inline-edit-row">
                                <input type="date" class="form-control inline-input" data-field="nextdate">
                                <div class="date-shift-btns">
                                    <button type="button" class="btn btn-sm btn-date-shift btn-minus" onclick="shiftDate(this.closest('.inline-edit-row').querySelector('[data-field=\'nextdate\']'), -30)">-30天</button>
                                    <button type="button" class="btn btn-sm btn-date-shift btn-plus" onclick="shiftDate(this.closest('.inline-edit-row').querySelector('[data-field=\'nextdate\']'), 30)">+30天</button>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="inline-view">
                                <span class="badge <?php echo $item['continue'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $item['continue'] ? '✓ 續訂' : '✗ 不續'; ?></span>
                            </span>
                            <div class="inline-edit inline-edit-row">
                                <label style="display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox" data-field="continue"> 續訂
                                </label>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- 手機版卡片 -->
    <div class="mobile-cards mobile-only" style="margin-top: 20px;">
        <?php if (empty($items)): ?>
            <div class="sub-card" style="text-align: center; color: #999; padding: 40px;">暫無訂閱資料</div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="sub-card <?php echo $item['continue'] ? '' : 'sub-card-inactive'; ?>"
                    data-continue="<?php echo htmlspecialchars($item['continue'] ?? 0, ENT_QUOTES); ?>"
                    data-year="<?php echo !empty($item['nextdate']) ? date('Y', strtotime($item['nextdate'])) : ''; ?>"
                    data-month=\"<?php echo !empty($item['nextdate']) ? date('m', strtotime($item['nextdate'])) : ''; ?>"
                    data-days="<?php echo getDaysUntil($item['nextdate'] ?? ''); ?>">
                    <div class="sub-card-actions">
                        <span class="card-edit-btn" onclick="editItem('<?php echo $item['id']; ?>')"><i
                                class="fas fa-pen"></i></span>
                        <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                    </div>
                    <div class="sub-card-header">
                        <?php if ($item['site']): ?>
                            <?php $domain = parse_url($item['site'], PHP_URL_HOST); ?>
                            <img src="https://www.google.com/s2/favicons?domain=<?php echo $domain; ?>&sz=32" class="sub-card-icon">
                        <?php else: ?>
                            <div class="sub-card-icon-placeholder"><i class="fas fa-globe"></i></div>
                        <?php endif; ?>
                        <div class="sub-card-title">
                            <?php if ($item['site']): ?>
                                <a href="<?php echo htmlspecialchars($item['site']); ?>"
                                    target="_blank"><?php echo htmlspecialchars($item['name']); ?></a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($item['name']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="sub-card-badge <?php echo $item['continue'] ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $item['continue'] ? '續訂' : '不續'; ?>
                        </div>
                    </div>
                    <?php if (!empty($item['account'])): ?>
                        <div class="sub-card-account"><i class="fas fa-user"></i> <?php echo htmlspecialchars($item['account']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="sub-card-info">
                        <div class="sub-card-price">
                            <span class="sub-card-label">價格</span>
                            <span
                                class="sub-card-value"><?php echo formatMoney(convertToTWD($item['price'], $item['currency'], $exchangeRates)); ?></span>
                        </div>
                        <div class="sub-card-date">
                            <span class="sub-card-label">下次付款</span>
                            <span class="sub-card-value"><?php echo formatDate($item['nextdate']) ?: '-'; ?></span>
                            <span class="sub-card-label sub-card-label-secondary">&#36317;&#20170;&#22825;&#25976;</span>
                            <span class="sub-card-value sub-card-value-secondary"><?php echo formatDaysFromToday($item['nextdate']); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($item['note'])): ?>
                        <div class="sub-card-note"><?php echo htmlspecialchars($item['note']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="modal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">新增訂閱</h2>
        <form id="itemForm">
            <input type="hidden" id="itemId" name="id">
            <div class="form-group" style="position: relative;">
                <label>服務名稱 *</label>
                <input type="text" class="form-control" id="name" name="name" autocomplete="off" required
                    onfocus="showNameSuggestions()" oninput="filterNameSuggestions()">
                <div id="nameSuggestions" class="suggestions-dropdown" style="display: none;">
                    <?php foreach ($existingNames as $existingName): ?>
                        <div class="suggestion-item"
                            onclick="selectName('<?php echo htmlspecialchars($existingName, ENT_QUOTES); ?>')">
                            <?php echo htmlspecialchars($existingName); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group" style="position: relative;">
                <label>網站</label>
                <input type="url" class="form-control" id="site" name="site" autocomplete="off"
                    onfocus="showSiteSuggestions()" oninput="filterSiteSuggestions()">
                <div id="siteSuggestions" class="suggestions-dropdown" style="display: none;">
                    <?php foreach ($existingSites as $existingSite): ?>
                        <div class="suggestion-item"
                            onclick="selectSite('<?php echo htmlspecialchars($existingSite, ENT_QUOTES); ?>')">
                            <?php echo htmlspecialchars($existingSite); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>價格</label>
                    <input type="number" class="form-control" id="price" name="price">
                </div>
                <div class="form-group" style="flex:1">
                    <label>幣別</label>
                    <select class="form-control" id="currency" name="currency">
                        <option value="TWD">TWD 新台幣</option>
                        <option value="USD">USD 美元</option>
                        <option value="EUR">EUR 歐元</option>
                        <option value="JPY">JPY 日圓</option>
                        <option value="CNY">CNY 人民幣</option>
                        <option value="HKD">HKD 港幣</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>下次付款日</label>
                <input type="date" class="form-control" id="nextdate" name="nextdate">
                <div class="date-shift-btns">
                    <button type="button" class="btn btn-sm btn-date-shift btn-minus" onclick="shiftDate(document.getElementById('nextdate'), -30)">-30天</button>
                    <button type="button" class="btn btn-sm btn-date-shift btn-plus" onclick="shiftDate(document.getElementById('nextdate'), 30)">+30天</button>
                </div>
            </div>
            <div class="form-group" style="position: relative;">
                <label>帳號</label>
                <input type="text" class="form-control" id="account" name="account" autocomplete="off"
                    onfocus="showAccountSuggestions()" oninput="filterAccountSuggestions()">
                <div id="accountSuggestions" class="suggestions-dropdown" style="display: none;">
                    <?php foreach ($existingAccounts as $existingAccount): ?>
                        <div class="suggestion-item"
                            onclick="selectAccount('<?php echo htmlspecialchars($existingAccount, ENT_QUOTES); ?>')">
                            <?php echo htmlspecialchars($existingAccount); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label>備註</label>
                <textarea class="form-control" id="note" name="note" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label><input type="checkbox" id="continue" name="continue" checked> 續訂</label>
            </div>
            <button type="submit" class="btn btn-primary">儲存</button>
        </form>
    </div>
</div>

<style>
    .suggestions-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--card-bg, #fff);
        border: 1px solid var(--input-border, #ddd);
        border-top: none;
        border-radius: 0 0 5px 5px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .suggestion-item {
        padding: 10px 15px;
        cursor: pointer;
    }

    .suggestion-item:hover {
        background: var(--table-header-bg, #f8f9fa);
    }

    .filter-btn.active {
        background: #3498db;
        color: #fff;
        border-color: transparent;
    }

    .filter-btn {
        background: rgba(255, 255, 255, 0.86);
        color: #1e293b;
        border: 1px solid rgba(100, 116, 139, 0.24);
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
    }

    .filter-btn:hover {
        background: #ffffff;
        border-color: rgba(52, 152, 219, 0.34);
        color: #0f172a;
    }

    .inline-add-row {
        display: none;
    }

    .inline-edit.inline-edit-always {
        display: block;
    }

    .inline-edit {
        display: none;
    }

    .inline-edit .form-control {
        margin-top: 6px;
    }

    .inline-edit-row {
        margin-top: 6px;
    }

    .subscription-date-cell {
        display: flex;
        flex-direction: column;
        gap: 4px;
        line-height: 1.35;
    }

    .subscription-date-primary {
        font-weight: 600;
    }

    .subscription-date-secondary {
        font-size: 0.85rem;
        color: #6b7280;
    }

    .inline-actions {
        margin-top: 8px;
        display: flex;
        gap: 8px;
    }

    .inline-actions .btn {
        padding: 4px 10px;
        font-size: 0.85rem;
    }

    /* 手機版/桌面版切換 */
    .mobile-only {
        display: none;
    }

    .desktop-only {
        display: table;
    }

    @media (max-width: 768px) {
        .mobile-only {
            display: block;
        }

        .desktop-only {
            display: none !important;
        }
    }

    /* 手機版訂閱卡片 */
    .sub-card {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        position: relative;
        border-left: 4px solid #27ae60;
    }

    .sub-card-inactive {
        border-left-color: #e74c3c;
        opacity: 0.8;
    }

    .sub-card-actions {
        position: absolute;
        top: 12px;
        right: 12px;
        display: flex;
        gap: 12px;
    }

    .sub-card-actions .card-edit-btn,
    .sub-card-actions .card-delete-btn {
        font-size: 18px;
        padding: 5px;
    }

    .sub-card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
        padding-right: 60px;
    }

    .sub-card-icon {
        width: 32px;
        height: 32px;
        border-radius: 6px;
    }

    .sub-card-icon-placeholder {
        width: 32px;
        height: 32px;
        background: #ecf0f1;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #95a5a6;
    }

    .sub-card-title {
        flex: 1;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--header-color, #2c3e50);
    }

    .sub-card-title a {
        color: inherit;
        text-decoration: none;
    }

    .sub-card-title a:hover {
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .sub-card-badge {
        font-size: 0.75rem;
        padding: 3px 8px;
        border-radius: 10px;
        font-weight: 500;
    }

    .sub-card-badge.badge-success {
        background: #d4edda;
        color: #155724;
    }

    .sub-card-badge.badge-danger {
        background: #f8d7da;
        color: #721c24;
    }

    .sub-card-account {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 12px;
        padding-left: 44px;
    }

    .sub-card-account i {
        margin-right: 5px;
        color: #999;
    }

    .sub-card-info {
        display: flex;
        gap: 20px;
        background: var(--bg-color, #f5f5f5);
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .sub-card-price,
    .sub-card-date {
        flex: 1;
    }

    .sub-card-label {
        display: block;
        font-size: 0.75rem;
        color: #999;
        margin-bottom: 4px;
    }

    .sub-card-value {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-color, #333);
        line-height: 1.45;
    }

    .sub-card-label-secondary {
        margin-top: 10px;
    }

    .sub-card-value-secondary {
        font-size: 0.95rem;
    }

    .sub-card-note {
        font-size: 0.85rem;
        color: #888;
        padding: 8px 0;
        border-top: 1px dashed #eee;
    }

    .date-shift-btns {
        display: flex;
        gap: 6px;
        margin-top: 6px;
    }

    .btn-date-shift {
        flex: 1;
        padding: 4px 0;
        font-size: 0.82rem;
        font-weight: 600;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: opacity 0.15s;
    }

    .btn-date-shift:hover { opacity: 0.85; }

    .btn-date-shift.btn-minus {
        background: #e74c3c;
        color: #fff;
    }

    .btn-date-shift.btn-plus {
        background: #27ae60;
        color: #fff;
    }

    .subscription-filters {
        width: 100%;
        justify-content: flex-end;
    }

    .subscription-select-filter {
        min-width: 120px;
        border-radius: 999px;
        padding: 10px 16px;
        border: 1px solid rgba(148, 163, 184, 0.35);
        background: rgba(255, 255, 255, 0.88);
    }

    .sub-card {
        border-radius: 20px;
        padding: 18px;
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
        border-left-width: 5px;
    }

    .sub-card-title {
        line-height: 1.35;
    }

    .sub-card-info {
        border-radius: 14px;
        gap: 14px;
    }

    .sub-card-note {
        line-height: 1.6;
    }

    @media (max-width: 1024px) {
        .subscription-filters {
            justify-content: flex-start;
        }

        .subscription-select-filter,
        .subscription-filters .btn {
            flex: 1 1 120px;
        }

        .subscription-filters .btn {
            justify-content: center;
        }
    }

    @media (max-width: 768px) {
        .sub-card {
            padding: 18px 16px;
        }

        .sub-card-header {
            align-items: flex-start;
            padding-right: 72px;
        }

        .sub-card-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .sub-card-account {
            padding-left: 0;
        }

        .sub-card-actions {
            top: 14px;
            right: 14px;
        }
    }

    @media (max-width: 560px) {
        .subscription-filters {
            display: grid !important;
            grid-template-columns: 1fr 1fr;
            width: 100%;
        }

        .subscription-select-filter,
        .subscription-filters .btn {
            width: 100%;
            min-width: 0;
        }

        .sub-card {
            padding: 16px 14px;
        }

        .sub-card-header {
            display: grid;
            grid-template-columns: 40px 1fr;
            gap: 10px;
            padding-right: 56px;
        }

        .sub-card-badge {
            grid-column: 1 / -1;
            justify-self: start;
            margin-left: 50px;
        }

        .sub-card-info {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    // +30天 / -30天 日期位移
    function shiftDate(input, days) {
        if (!input) return;
        var val = input.value;
        var d = val ? new Date(val) : new Date();
        d.setDate(d.getDate() + days);
        input.value = d.toISOString().slice(0, 10);
    }

    const TABLE = 'subscription';
    initBatchDelete(TABLE);
    const allNames = <?php echo json_encode($existingNames, JSON_UNESCAPED_UNICODE); ?>;
    const allSites = <?php echo json_encode($existingSites, JSON_UNESCAPED_UNICODE); ?>;
    const allAccounts = <?php echo json_encode($existingAccounts, JSON_UNESCAPED_UNICODE); ?>;

    // 服務名稱
    function showNameSuggestions() {
        if (allNames.length > 0) {
            document.getElementById('nameSuggestions').style.display = 'block';
        }
    }

    function filterNameSuggestions() {
        filterSuggestions('name', 'nameSuggestions');
    }

    function selectName(name) {
        document.getElementById('name').value = name;
        document.getElementById('nameSuggestions').style.display = 'none';
    }

    // 網站
    function showSiteSuggestions() {
        if (allSites.length > 0) {
            document.getElementById('siteSuggestions').style.display = 'block';
        }
    }

    function filterSiteSuggestions() {
        filterSuggestions('site', 'siteSuggestions');
    }

    function selectSite(site) {
        document.getElementById('site').value = site;
        document.getElementById('siteSuggestions').style.display = 'none';
    }

    // 帳號
    function showAccountSuggestions() {
        if (allAccounts.length > 0) {
            document.getElementById('accountSuggestions').style.display = 'block';
        }
    }

    function filterAccountSuggestions() {
        filterSuggestions('account', 'accountSuggestions');
    }

    function selectAccount(account) {
        document.getElementById('account').value = account;
        document.getElementById('accountSuggestions').style.display = 'none';
    }

    // 通用篩選函數
    function filterSuggestions(inputId, containerId) {
        const input = document.getElementById(inputId).value.toLowerCase();
        const container = document.getElementById(containerId);
        const items = container.querySelectorAll('.suggestion-item');
        let hasVisible = false;

        items.forEach(item => {
            if (item.textContent.toLowerCase().includes(input)) {
                item.style.display = 'block';
                hasVisible = true;
            } else {
                item.style.display = 'none';
            }
        });

        container.style.display = hasVisible ? 'block' : 'none';
    }

    function toggleWithin7() {
        const btn = document.getElementById('within7Btn');
        if (!btn) return;
        btn.classList.toggle('active');
        applyFilters();
    }

    function filterByContinue(value) {
        document.querySelectorAll('.filter-btn[data-continue]').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.continue === value);
        });

        applyFilters();
    }

    function getActiveContinueFilter() {
        const activeButton = document.querySelector('.filter-btn[data-continue].active');
        return activeButton ? activeButton.dataset.continue : '';
    }

    function matchesSubscriptionFilters(element, continueValue, yearValue, monthValue, within7Only) {
        const matchesContinue = !continueValue || element.dataset.continue === continueValue;
        const isNoMonth = monthValue === '__none';
        const matchesYear = isNoMonth ? true : (!yearValue || element.dataset.year === yearValue);
        const matchesMonth = isNoMonth
            ? element.dataset.month === ''
            : (!monthValue || element.dataset.month === monthValue);
        const daysValue = parseInt(element.dataset.days || '', 10);
        const matchesWithin7 = !within7Only || (!Number.isNaN(daysValue) && daysValue >= 0 && daysValue <= 7);
        return matchesContinue && matchesYear && matchesMonth && matchesWithin7;
    }

    function applyFilters() {
        const continueValue = getActiveContinueFilter();
        const yearValue = document.getElementById('yearFilter')?.value || '';
        const monthValue = document.getElementById('monthFilter')?.value || '';
        const within7Only = document.getElementById('within7Btn')?.classList.contains('active');

        document.querySelectorAll('table.desktop-only tbody tr[data-id]').forEach(row => {
            const match = matchesSubscriptionFilters(row, continueValue, yearValue, monthValue, within7Only);
            row.style.display = match ? '' : 'none';
        });

        document.querySelectorAll('.mobile-cards .sub-card').forEach(card => {
            const match = matchesSubscriptionFilters(card, continueValue, yearValue, monthValue, within7Only);
            card.style.display = match ? '' : 'none';
        });
    }

    function handleAdd() {
        // Use inline editing for all screen sizes
        startInlineAdd();
    }

    function startInlineAdd() {
        const row = document.getElementById('inlineAddRow');
        if (!row) {
            alert('找不到新增列，請重新整理頁面');
            return;
        }
        row.style.setProperty('display', 'table-row', 'important');
        row.querySelectorAll('[data-field]').forEach(input => {
            if (input.type === 'checkbox') {
                input.checked = true;
            } else {
                input.value = '';
            }
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
            alert('請輸入服務名稱');
            return;
        }

        const data = {
            name,
            site: row.querySelector('[data-field="site"]').value.trim(),
            price: row.querySelector('[data-field="price"]').value || 0,
            currency: row.querySelector('[data-field="currency"]').value || 'TWD',
            nextdate: row.querySelector('[data-field="nextdate"]').value || null,
            account: row.querySelector('[data-field="account"]').value.trim(),
            note: row.querySelector('[data-field="note"]').value.trim(),
            continue: row.querySelector('[data-field="continue"]').checked ? 1 : 0
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

    // 點擊其他地方關閉所有下拉選單
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#name') && !e.target.closest('#nameSuggestions')) {
            document.getElementById('nameSuggestions').style.display = 'none';
        }
        if (!e.target.closest('#site') && !e.target.closest('#siteSuggestions')) {
            document.getElementById('siteSuggestions').style.display = 'none';
        }
        if (!e.target.closest('#account') && !e.target.closest('#accountSuggestions')) {
            document.getElementById('accountSuggestions').style.display = 'none';
        }
    });

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
        const nextdate = data.nextdate ? data.nextdate.split(' ')[0] : '';
        const continueValue = data.continue == 1;

        const nameInput = row.querySelector('[data-field="name"]');
        if (nameInput) nameInput.value = data.name || '';
        const siteInput = row.querySelector('[data-field="site"]');
        if (siteInput) siteInput.value = data.site || '';
        const accountInput = row.querySelector('[data-field="account"]');
        if (accountInput) accountInput.value = data.account || '';
        const noteInput = row.querySelector('[data-field="note"]');
        if (noteInput) noteInput.value = data.note || '';
        const priceInput = row.querySelector('[data-field="price"]');
        if (priceInput) priceInput.value = data.price || '';
        const currencySelect = row.querySelector('[data-field="currency"]');
        if (currencySelect) currencySelect.value = data.currency || 'TWD';
        const nextdateInput = row.querySelector('[data-field="nextdate"]');
        if (nextdateInput) nextdateInput.value = nextdate || '';
        const continueCheckbox = row.querySelector('[data-field="continue"]');
        if (continueCheckbox) continueCheckbox.checked = continueValue;
    }

    function saveInlineEdit(id) {
        const row = getRowById(id);
        if (!row) return;
        const name = row.querySelector('[data-field="name"]').value.trim();
        if (!name) {
            alert('請輸入服務名稱');
            return;
        }

        const data = {
            name,
            site: row.querySelector('[data-field="site"]').value.trim(),
            price: row.querySelector('[data-field="price"]').value || 0,
            currency: row.querySelector('[data-field="currency"]').value || 'TWD',
            nextdate: row.querySelector('[data-field="nextdate"]').value || null,
            account: row.querySelector('[data-field="account"]').value.trim(),
            note: row.querySelector('[data-field="note"]').value.trim(),
            continue: row.querySelector('[data-field="continue"]').checked ? 1 : 0
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
        document.getElementById('modalTitle').textContent = '新增訂閱';
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '';
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
                    document.getElementById('site').value = d.site || '';
                    document.getElementById('price').value = d.price || '';
                    document.getElementById('currency').value = d.currency || 'TWD';
                    document.getElementById('nextdate').value = d.nextdate ? d.nextdate.split(' ')[0] : '';
                    document.getElementById('account').value = d.account || '';
                    document.getElementById('note').value = d.note || '';
                    document.getElementById('continue').checked = d.continue == 1;
                    document.getElementById('modalTitle').textContent = '編輯訂閱';
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
            site: document.getElementById('site').value,
            price: document.getElementById('price').value || 0,
            currency: document.getElementById('currency').value,
            nextdate: document.getElementById('nextdate').value || null,
            account: document.getElementById('account').value,
            note: document.getElementById('note').value,
            continue: document.getElementById('continue').checked ? 1 : 0
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
</script>
