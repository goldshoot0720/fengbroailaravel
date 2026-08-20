<?php
$pageTitle = '銀行管理';
$pdo = getConnection();
require_once __DIR__ . '/../includes/bank_helpers.php';
$items = $pdo->query("SELECT * FROM bank ORDER BY deposit DESC")->fetchAll();
$totalDeposit = (int) $pdo->query("SELECT COALESCE(SUM(deposit), 0) FROM bank")->fetchColumn();

$bankAccountItems = array_values(array_filter($items, 'isTaiwanBankAccount'));
$eTicketItems = array_values(array_filter($items, function ($item) {
    return !isTaiwanBankAccount($item);
}));
$bankAccountCount = count($bankAccountItems);
$eTicketCount = count($eTicketItems);
$bankTotalAsset = array_reduce($bankAccountItems, function ($sum, $item) {
    return $sum + (int) ($item['deposit'] ?? 0);
}, 0);
$eTicketTotalAsset = array_reduce($eTicketItems, function ($sum, $item) {
    return $sum + (int) ($item['deposit'] ?? 0);
}, 0);
?>

<div class="content-header">
    <h1>鋒兄銀行 (+電子票證) <span
            style="font-size:0.55em;background:#27ae60;color:#fff;padding:3px 10px;border-radius:20px;vertical-align:middle;font-weight:500;">銀行帳戶 <?php echo $bankAccountCount; ?></span>
        <span
            style="font-size:0.55em;background:#8e44ad;color:#fff;padding:3px 10px;border-radius:20px;vertical-align:middle;font-weight:500;">電子票證 <?php echo $eTicketCount; ?></span>
    </h1>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <div class="action-buttons-bar">
        <button class="btn btn-primary" onclick="handleAdd()" title="新增銀行(或電子票證)"><i class="fas fa-plus"></i> 新增銀行(或電子票證)</button>
        <button class="btn btn-success" type="button" onclick="openTransactionModal('income')">新增收入</button>
        <button class="btn btn-danger" type="button" onclick="openTransactionModal('expense')">新增支出</button>
        <button class="btn btn-warning" type="button" onclick="openBankBatchAdjust()">
            <i class="fas fa-layer-group"></i> 多選設定存款
        </button>
        <?php $csvTable = 'bank';
        include 'includes/csv_buttons.php'; ?>
        <?php include 'includes/batch-delete.php'; ?>
    </div>
    <div id="bankBatchAdjustPanel" class="card" style="display:none; margin-bottom: 24px; border-left: 4px solid #f39c12;">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <h3 class="card-title" style="margin-bottom:6px;">多選銀行存款數字</h3>
                <p style="color:var(--muted-text); margin:0;">先勾選銀行，可用固定存款數字套用全部，也可分別輸入每家銀行的存款數字。</p>
            </div>
            <button type="button" class="btn btn-sm" onclick="closeBankBatchAdjust()">關閉</button>
        </div>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-top:16px;">
            <label style="display:grid; gap:6px; font-weight:700;">
                調整方式
                <select id="bankBatchDirection" class="form-control" onchange="renderBankBatchAdjust()">
                    <option value="set">設定存款數字</option>
                    <option value="plus">＋ 增加存款</option>
                    <option value="minus">－ 扣除存款</option>
                </select>
            </label>
            <label style="display:grid; gap:6px; font-weight:700;">
                數字模式
                <select id="bankBatchMode" class="form-control" onchange="renderBankBatchAdjust()">
                    <option value="fixed">固定存款數字</option>
                    <option value="separate">分別輸入</option>
                </select>
            </label>
            <label style="display:grid; gap:6px; font-weight:700;">
                固定存款數字
                <input id="bankBatchFixedAmount" type="number" min="0" step="1" class="form-control" placeholder="例如 10000" oninput="renderBankBatchAdjust()">
            </label>
        </div>
        <div id="bankBatchSelectedList" style="display:grid; gap:10px; margin-top:16px;"></div>
        <div id="bankBatchPreview" style="margin-top:14px; padding:12px 14px; border-radius:8px; background:var(--table-header-bg); color:var(--text-color);">尚未選擇銀行。</div>
        <div style="display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; margin-top:16px;">
            <button type="button" class="btn" onclick="renderBankBatchAdjust()">重新整理選取</button>
            <button type="button" class="btn btn-primary" onclick="submitBankBatchAdjust()">套用調整</button>
        </div>
    </div>
    <style>
        .bank-select-checkbox {
            display: inline-block !important;
            accent-color: #f39c12;
        }
    </style>
    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="card" style="background: linear-gradient(135deg, #27ae60, #219a52); color: #fff;">
            <h3>所有資產</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo formatMoney($totalDeposit); ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #3498db, #2980b9); color: #fff;">
            <h3>銀行總資產</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo formatMoney($bankTotalAsset); ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #8e44ad, #6c3483); color: #fff;">
            <h3>電子票證總資產</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo formatMoney($eTicketTotalAsset); ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #3498db, #2980b9); color: #fff;">
            <h3>銀行帳戶總數</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $bankAccountCount; ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #8e44ad, #6c3483); color: #fff;">
            <h3>電子票證總數</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $eTicketCount; ?></p>
        </div>
    </div>

    <!-- 桌面版表格 -->
    <table class="table desktop-only" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAllCheckbox" class="select-checkbox bank-select-checkbox"
                        onchange="toggleSelectAll(this)"></th>
                <th>名稱</th>
                <th>存款</th>
                <th>提款</th>
                <th>轉帳</th>
                <th>帳號</th>
                <th>卡號</th>
                <th>網站</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <tr id="inlineAddRow" class="inline-add-row">
                <td></td>
                <td>
                    <div class="inline-edit inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="name" placeholder="名稱">
                        <input type="text" class="form-control inline-input" data-field="account" placeholder="帳號">
                        <input type="text" class="form-control inline-input" data-field="card" placeholder="卡號">
                        <input type="text" class="form-control inline-input" data-field="address" placeholder="地址">
                        <input type="url" class="form-control inline-input" data-field="site" placeholder="網站">
                        <input type="url" class="form-control inline-input" data-field="activity" placeholder="活動網址">
                        <div class="inline-actions">
                            <button type="button" class="btn btn-primary" onclick="saveInlineAdd()">儲存</button>
                            <button type="button" class="btn" onclick="cancelInlineAdd()">取消</button>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="number" class="form-control inline-input" data-field="deposit" placeholder="存款">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="number" class="form-control inline-input" data-field="withdrawals"
                            placeholder="提款">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="number" class="form-control inline-input" data-field="transfer" placeholder="轉帳">
                    </div>
                </td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: #999;">暫無銀行資料</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php $bankSiteUrl = bankDisplayUrl($item['site'] ?? ''); ?>
                    <tr data-id="<?php echo $item['id']; ?>"
                        data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                        data-deposit="<?php echo htmlspecialchars($item['deposit'] ?? '', ENT_QUOTES); ?>"
                        data-withdrawals="<?php echo htmlspecialchars($item['withdrawals'] ?? '', ENT_QUOTES); ?>"
                        data-transfer="<?php echo htmlspecialchars($item['transfer'] ?? '', ENT_QUOTES); ?>"
                        data-account="<?php echo htmlspecialchars($item['account'] ?? '', ENT_QUOTES); ?>"
                        data-card="<?php echo htmlspecialchars($item['card'] ?? '', ENT_QUOTES); ?>"
                        data-address="<?php echo htmlspecialchars($item['address'] ?? '', ENT_QUOTES); ?>"
                        data-site="<?php echo htmlspecialchars($item['site'] ?? '', ENT_QUOTES); ?>"
                        data-activity="<?php echo htmlspecialchars($item['activity'] ?? '', ENT_QUOTES); ?>">
                        <td><input type="checkbox" class="select-checkbox item-checkbox bank-select-checkbox" data-id="<?php echo $item['id']; ?>"
                                onchange="toggleSelectItem(this)"></td>
                        <td>
                            <div class="inline-view">
                                <?php if ($bankSiteUrl): ?>
                                    <?php $domain = parse_url($bankSiteUrl, PHP_URL_HOST); ?>
                                    <img src="https://www.google.com/s2/favicons?domain=<?php echo $domain; ?>&sz=16"
                                        style="width: 16px; height: 16px; vertical-align: middle; margin-right: 5px;">
                                <?php endif; ?>
                                <?php if ($bankSiteUrl): ?>
                                    <a href="<?php echo htmlspecialchars($bankSiteUrl); ?>" target="_blank" rel="noopener" class="bank-name-link">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($item['name']); ?>
                                <?php endif; ?>
                                <span class="card-edit-btn" onclick="startInlineEdit('<?php echo $item['id']; ?>')"
                                    style="cursor: pointer; margin-left: 8px;"><i class="fas fa-pen"></i></span>
                                <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')"
                                    style="margin-left: 6px; cursor: pointer;">&times;</span>
                            </div>
                            <div class="inline-edit">
                                <input type="text" class="form-control inline-input" data-field="name" placeholder="名稱">
                                <input type="text" class="form-control inline-input" data-field="account" placeholder="帳號">
                                <input type="text" class="form-control inline-input" data-field="card" placeholder="卡號">
                                <input type="text" class="form-control inline-input" data-field="address" placeholder="地址">
                                <input type="url" class="form-control inline-input" data-field="site" placeholder="網站">
                                <input type="url" class="form-control inline-input" data-field="activity" placeholder="活動網址">
                                <div class="inline-actions">
                                    <button type="button" class="btn btn-primary"
                                        onclick="saveInlineEdit('<?php echo $item['id']; ?>')">儲存</button>
                                    <button type="button" class="btn"
                                        onclick="cancelInlineEdit('<?php echo $item['id']; ?>')">取消</button>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="inline-view private-value"><?php echo formatMoney($item['deposit']); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="number" class="form-control inline-input" data-field="deposit" placeholder="存款">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo formatMoney($item['withdrawals']); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="number" class="form-control inline-input" data-field="withdrawals"
                                    placeholder="提款">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo formatMoney($item['transfer']); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="number" class="form-control inline-input" data-field="transfer" placeholder="轉帳">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view private-value"><?php echo htmlspecialchars($item['account'] ?? '-'); ?></span>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo htmlspecialchars($item['card'] ?? '-'); ?></span>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo $bankSiteUrl ? '已合併至名稱' : '-'; ?></span>
                        </td>
                        <td>
                            <div class="inline-view"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- 手機版卡片 -->
    <div class="mobile-only" style="margin-top: 20px;">
        <?php if (empty($items)): ?>
            <div class="mobile-card" style="text-align: center; color: #999; padding: 40px;">暫無銀行資料</div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <?php $bankSiteUrl = bankDisplayUrl($item['site'] ?? ''); ?>
                <div class="mobile-card" style="border-left: 4px solid #3498db;"
                    data-id="<?php echo $item['id']; ?>"
                    data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                    data-deposit="<?php echo htmlspecialchars($item['deposit'] ?? '', ENT_QUOTES); ?>"
                    data-withdrawals="<?php echo htmlspecialchars($item['withdrawals'] ?? '', ENT_QUOTES); ?>">
                    <div class="mobile-card-actions">
                        <input type="checkbox" class="select-checkbox item-checkbox bank-select-checkbox" data-id="<?php echo $item['id']; ?>"
                            onchange="toggleSelectItem(this)" style="margin-right: 8px;">
                        <span class="card-edit-btn" onclick="editItem('<?php echo $item['id']; ?>')"><i
                                class="fas fa-pen"></i></span>
                        <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                    </div>
                    <div class="mobile-card-header">
                        <?php if ($bankSiteUrl): ?>
                            <?php $domain = parse_url($bankSiteUrl, PHP_URL_HOST); ?>
                            <img src="https://www.google.com/s2/favicons?domain=<?php echo $domain; ?>&sz=32"
                                style="width: 32px; height: 32px; border-radius: 6px;">
                        <?php else: ?>
                            <i class="fas fa-university" style="font-size: 1.5rem; color: #3498db;"></i>
                        <?php endif; ?>
                        <div class="mobile-card-title">
                            <?php if ($bankSiteUrl): ?>
                                <a href="<?php echo htmlspecialchars($bankSiteUrl); ?>" target="_blank" rel="noopener" class="bank-name-link">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($item['name']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mobile-card-info">
                        <div class="mobile-card-item">
                            <span class="mobile-card-label">存款</span>
                            <span class="mobile-card-value private-value"
                                style="color: #27ae60;"><?php echo formatMoney($item['deposit']); ?></span>
                        </div>
                        <div class="mobile-card-item">
                            <span class="mobile-card-label">提款</span>
                            <span class="mobile-card-value"
                                style="color: #e74c3c;"><?php echo formatMoney($item['withdrawals']); ?></span>
                        </div>
                        <div class="mobile-card-item">
                            <span class="mobile-card-label">轉帳</span>
                            <span class="mobile-card-value"><?php echo formatMoney($item['transfer']); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($item['account']) || !empty($item['card'])): ?>
                        <div style="margin-top: 10px; font-size: 0.85rem; color: #666;">
                            <?php if (!empty($item['account'])): ?>
                                <div><i class="fas fa-id-card" style="width: 16px;"></i>
                                    <span class="private-value"><?php echo htmlspecialchars($item['account']); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($item['card'])): ?>
                                <div><i class="fas fa-credit-card" style="width: 16px;"></i>
                                    <?php echo htmlspecialchars($item['card']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="transactionModal" class="modal" onclick="if (event.target === this) closeTransactionModal()">
    <div class="modal-content" style="max-width: 520px;">
        <span class="modal-close" onclick="closeTransactionModal()">&times;</span>
        <h2>銀行收支調整</h2>
        <div style="display: grid; gap: 16px;">
            <div>
                <label for="transactionBank" style="display: block; margin-bottom: 8px; font-weight: 600;">1. 選擇銀行</label>
                <select id="transactionBank" class="form-control" onchange="updateTransactionPreview()">
                    <option value="">請選擇銀行</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?php echo htmlspecialchars($item['id'], ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($item['name']); ?>（目前 <?php echo formatMoney($item['deposit']); ?>）
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="transactionType" style="display: block; margin-bottom: 8px; font-weight: 600;">2. 選擇收入或支出</label>
                <select id="transactionType" class="form-control" onchange="updateTransactionPreview()">
                    <option value="income">收入</option>
                    <option value="expense">支出</option>
                </select>
            </div>
            <div>
                <label for="transactionAmount" style="display: block; margin-bottom: 8px; font-weight: 600;">3. 輸入金額</label>
                <input id="transactionAmount" type="number" min="0" step="1" class="form-control" placeholder="請輸入金額"
                    oninput="updateTransactionPreview()">
            </div>
            <div id="transactionPreview"
                style="padding: 14px 16px; border-radius: 8px; background: var(--table-header-bg); color: var(--text-color);">
                請先選擇銀行並輸入金額。
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" onclick="closeTransactionModal()">取消</button>
                <button type="button" class="btn btn-primary" onclick="submitTransaction()">完成</button>
            </div>
        </div>
    </div>
</div>


<script>
    const TABLE = 'bank';
    const BANK_ITEMS = <?php echo json_encode(array_map(function ($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'deposit' => (int) ($item['deposit'] ?? 0),
            'withdrawals' => (int) ($item['withdrawals'] ?? 0),
        ];
    }, $items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    initBatchDelete(TABLE);
    document.addEventListener('change', function (event) {
        if (event.target && event.target.matches('.item-checkbox, #selectAllCheckbox, #batchSelectAllCb')) {
            window.setTimeout(renderBankBatchAdjust, 0);
        }
    });

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
            alert('請輸入名稱');
            return;
        }
        const data = {
            name,
            deposit: row.querySelector('[data-field="deposit"]').value || 0,
            withdrawals: row.querySelector('[data-field="withdrawals"]').value || 0,
            transfer: row.querySelector('[data-field="transfer"]').value || 0,
            account: row.querySelector('[data-field="account"]').value.trim(),
            card: row.querySelector('[data-field="card"]').value.trim(),
            address: row.querySelector('[data-field="address"]').value.trim(),
            site: row.querySelector('[data-field="site"]').value.trim(),
            activity: row.querySelector('[data-field="activity"]').value.trim()
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
        // Use inline editing for all screen sizes
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
        const nameInput = row.querySelector('[data-field="name"]');
        if (nameInput) nameInput.value = data.name || '';
        const depositInput = row.querySelector('[data-field="deposit"]');
        if (depositInput) depositInput.value = data.deposit || '';
        const withdrawalsInput = row.querySelector('[data-field="withdrawals"]');
        if (withdrawalsInput) withdrawalsInput.value = data.withdrawals || '';
        const transferInput = row.querySelector('[data-field="transfer"]');
        if (transferInput) transferInput.value = data.transfer || '';
        const accountInput = row.querySelector('[data-field="account"]');
        if (accountInput) accountInput.value = data.account || '';
        const cardInput = row.querySelector('[data-field="card"]');
        if (cardInput) cardInput.value = data.card || '';
        const addressInput = row.querySelector('[data-field="address"]');
        if (addressInput) addressInput.value = data.address || '';
        const siteInput = row.querySelector('[data-field="site"]');
        if (siteInput) siteInput.value = data.site || '';
        const activityInput = row.querySelector('[data-field="activity"]');
        if (activityInput) activityInput.value = data.activity || '';
    }

    function saveInlineEdit(id) {
        const row = getRowById(id);
        if (!row) return;
        const name = row.querySelector('[data-field="name"]').value.trim();
        if (!name) {
            alert('請輸入名稱');
            return;
        }
        const data = {
            name,
            deposit: row.querySelector('[data-field="deposit"]').value || 0,
            withdrawals: row.querySelector('[data-field="withdrawals"]').value || 0,
            transfer: row.querySelector('[data-field="transfer"]').value || 0,
            account: row.querySelector('[data-field="account"]').value.trim(),
            card: row.querySelector('[data-field="card"]').value.trim(),
            address: row.querySelector('[data-field="address"]').value.trim(),
            site: row.querySelector('[data-field="site"]').value.trim(),
            activity: row.querySelector('[data-field="activity"]').value.trim()
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


    function deleteItem(id) {
        const item = getBankById(id);
        const name = (item && item.name) ? String(item.name) : '';
        const expected = name ? ('DELETE ' + name) : 'DELETE bank';
        const userInput = prompt(
            '刪除銀行/電子票證無法復原。\n\n' +
            (name ? ('即將刪除：「' + name + '」\n\n') : '') +
            '請輸入以下文字確認：\n' + expected
        );
        if (userInput === null) return;
        if (userInput !== expected) {
            alert('輸入不正確，已取消刪除。');
            return;
        }
        deleteInlineItem(id, {
            table: TABLE,
            confirmMessage: null,
            skipConfirm: true
        });
    }

    function formatAmount(amount) {
        const value = Number(amount) || 0;
        return new Intl.NumberFormat('zh-TW', {
            style: 'currency',
            currency: 'TWD',
            maximumFractionDigits: 0
        }).format(value);
    }

    function getBankById(id) {
        return BANK_ITEMS.find(item => item.id === id) || null;
    }

    function getSelectedBankIds() {
        return Array.from(document.querySelectorAll('.item-checkbox:checked'))
            .filter(cb => {
                const holder = cb.closest('tr, .mobile-card, .card, [data-id]');
                return !holder || holder.offsetParent !== null;
            })
            .map(cb => cb.dataset.id)
            .filter((id, index, ids) => id && ids.indexOf(id) === index);
    }

    function openBankBatchAdjust() {
        const panel = document.getElementById('bankBatchAdjustPanel');
        if (panel) panel.style.display = 'block';
        renderBankBatchAdjust();
        panel?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function closeBankBatchAdjust() {
        const panel = document.getElementById('bankBatchAdjustPanel');
        if (panel) panel.style.display = 'none';
    }

    function renderBankBatchAdjust() {
        const list = document.getElementById('bankBatchSelectedList');
        const preview = document.getElementById('bankBatchPreview');
        if (!list || !preview) return;

        const ids = getSelectedBankIds();
        const direction = document.getElementById('bankBatchDirection')?.value || 'plus';
        const mode = document.getElementById('bankBatchMode')?.value || 'fixed';
        const fixedAmount = Number(document.getElementById('bankBatchFixedAmount')?.value || 0);
        const sign = direction === 'minus' ? -1 : 1;
        const actionLabel = getBankBatchActionLabel(direction);

        if (!ids.length) {
            list.innerHTML = '<div style="color:var(--muted-text);">請先在表格或手機卡片勾選銀行。</div>';
            preview.textContent = '尚未選擇銀行。';
            return;
        }

        list.innerHTML = ids.map(id => {
            const bank = getBankById(id);
            if (!bank) return '';
            const current = Number(bank.deposit) || 0;
            const savedInput = document.querySelector(`.bank-batch-amount[data-id="${cssEscapeBank(id)}"]`);
            const value = savedInput ? savedInput.value : '';
            const inputStyle = mode === 'fixed' ? 'display:none;' : '';
            return `
                <div class="bank-batch-row" data-id="${escapeHtmlBank(id)}" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; align-items:center; padding:10px 12px; border:1px solid var(--border-color); border-radius:8px;">
                    <div>
                        <strong>${escapeHtmlBank(bank.name || '')}</strong>
                        <div style="font-size:0.85rem; color:var(--muted-text);">目前 ${formatAmount(current)}</div>
                    </div>
                    <div style="color:${direction === 'minus' ? '#e74c3c' : '#27ae60'}; font-weight:800;">${actionLabel}</div>
                    <input class="form-control bank-batch-amount" data-id="${escapeHtmlBank(id)}" type="number" min="0" step="1" placeholder="${direction === 'set' ? '存款數字' : '調整金額'}" value="${escapeHtmlBank(value)}" oninput="renderBankBatchPreview()" style="${inputStyle}">
                </div>
            `;
        }).join('');

        renderBankBatchPreview();
        if (mode === 'fixed' && fixedAmount <= 0) {
            preview.textContent = '已選擇 ' + ids.length + ' 家銀行。請輸入固定存款數字。';
        }
    }

    function renderBankBatchPreview() {
        const preview = document.getElementById('bankBatchPreview');
        if (!preview) return;
        const ids = getSelectedBankIds();
        const direction = document.getElementById('bankBatchDirection')?.value || 'plus';
        const mode = document.getElementById('bankBatchMode')?.value || 'fixed';
        const fixedAmount = Number(document.getElementById('bankBatchFixedAmount')?.value || 0);
        const sign = direction === 'minus' ? -1 : 1;
        const actionLabel = getBankBatchActionLabel(direction);

        if (!ids.length) {
            preview.textContent = '尚未選擇銀行。';
            return;
        }

        const lines = ids.map(id => {
            const bank = getBankById(id);
            if (!bank) return '';
            const input = document.querySelector(`.bank-batch-amount[data-id="${cssEscapeBank(id)}"]`);
            const amount = mode === 'fixed' ? fixedAmount : Number(input?.value || 0);
            const current = Number(bank.deposit) || 0;
            const next = direction === 'set' ? amount : current + sign * amount;
            const operator = direction === 'set' ? '=>' : (direction === 'minus' ? '-' : '+');
            return `${bank.name}: ${formatAmount(current)} ${operator} ${formatAmount(amount)} = ${formatAmount(next)}（${actionLabel}）`;
        }).filter(Boolean);

        preview.innerHTML = '<strong>預覽</strong><br>' + lines.map(escapeHtmlBank).join('<br>');
    }

    function submitBankBatchAdjust() {
        const ids = getSelectedBankIds();
        const direction = document.getElementById('bankBatchDirection')?.value || 'plus';
        const mode = document.getElementById('bankBatchMode')?.value || 'fixed';
        const fixedAmount = Number(document.getElementById('bankBatchFixedAmount')?.value || 0);
        const sign = direction === 'minus' ? -1 : 1;
        const actionLabel = getBankBatchActionLabel(direction);

        if (!ids.length) {
            alert('請先選擇銀行');
            return;
        }
        if (mode === 'fixed' && (!fixedAmount || fixedAmount <= 0)) {
            alert('請輸入固定存款數字');
            return;
        }

        const updates = ids.map(id => {
            const bank = getBankById(id);
            const input = document.querySelector(`.bank-batch-amount[data-id="${cssEscapeBank(id)}"]`);
            const amount = mode === 'fixed' ? fixedAmount : Number(input?.value || 0);
            if (!bank || !amount || amount <= 0) return null;
            const currentDeposit = Number(bank.deposit) || 0;
            const currentWithdrawals = Number(bank.withdrawals) || 0;
            const nextDeposit = direction === 'set' ? amount : currentDeposit + sign * amount;
            return {
                id,
                name: bank.name,
                amount,
                data: {
                    deposit: nextDeposit,
                    withdrawals: direction === 'minus' ? currentWithdrawals + amount : currentWithdrawals
                }
            };
        }).filter(Boolean);

        if (!updates.length) {
            alert('請輸入每家銀行的存款數字');
            return;
        }

        const confirmText = updates.map(item => `${item.name}: ${actionLabel} ${formatAmount(item.amount)}`).join('\n');
        if (!confirm('確定套用以下調整？\n\n' + confirmText)) return;

        Promise.all(updates.map(item =>
            fetch(`api.php?action=update&table=${TABLE}&id=${encodeURIComponent(item.id)}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(item.data)
            }).then(r => r.json())
        )).then(results => {
            const failed = results.filter(res => !res.success).length;
            if (failed) {
                alert(`批次調整完成，但有 ${failed} 筆失敗。`);
            }
            location.reload();
        }).catch(err => alert('批次調整失敗: ' + (err.message || '網路錯誤')));
    }

    function escapeHtmlBank(value) {
        return String(value || '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
    }

    function cssEscapeBank(value) {
        if (window.CSS && CSS.escape) return CSS.escape(String(value));
        return String(value || '').replace(/"/g, '\\"');
    }

    function getBankBatchActionLabel(direction) {
        if (direction === 'set') return '設定存款';
        if (direction === 'minus') return '－扣除存款';
        return '＋增加存款';
    }

    function openTransactionModal(defaultType = 'income') {
        const modal = document.getElementById('transactionModal');
        const typeInput = document.getElementById('transactionType');
        const bankInput = document.getElementById('transactionBank');
        const amountInput = document.getElementById('transactionAmount');

        if (!modal || !typeInput || !bankInput || !amountInput) return;

        typeInput.value = defaultType;
        bankInput.value = '';
        amountInput.value = '';
        modal.style.display = 'flex';
        updateTransactionPreview();
    }

    function closeTransactionModal() {
        const modal = document.getElementById('transactionModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function updateTransactionPreview() {
        const preview = document.getElementById('transactionPreview');
        const bankId = document.getElementById('transactionBank')?.value || '';
        const type = document.getElementById('transactionType')?.value || 'income';
        const amount = Number(document.getElementById('transactionAmount')?.value || 0);

        if (!preview) return;

        const bank = getBankById(bankId);
        if (!bank) {
            preview.textContent = '請先選擇銀行並輸入金額。';
            return;
        }

        const currentDeposit = Number(bank.deposit) || 0;
        const nextDeposit = type === 'income' ? currentDeposit + amount : currentDeposit - amount;
        const typeLabel = type === 'income' ? '收入' : '支出';

        if (!amount) {
            preview.innerHTML = `${bank.name} 目前金額：<strong>${formatAmount(currentDeposit)}</strong>`;
            return;
        }

        preview.innerHTML = `${bank.name} 目前金額：<strong>${formatAmount(currentDeposit)}</strong><br>${typeLabel}金額：<strong>${formatAmount(amount)}</strong><br>調整後金額：<strong>${formatAmount(nextDeposit)}</strong>`;
    }

    function submitTransaction() {
        const bankId = document.getElementById('transactionBank')?.value || '';
        const type = document.getElementById('transactionType')?.value || 'income';
        const amount = Number(document.getElementById('transactionAmount')?.value || 0);
        const bank = getBankById(bankId);

        if (!bank) {
            alert('請先選擇銀行');
            return;
        }

        if (!amount || amount <= 0) {
            alert('請輸入正確金額');
            return;
        }

        const currentDeposit = Number(bank.deposit) || 0;
        const currentWithdrawals = Number(bank.withdrawals) || 0;
        const data = {
            deposit: type === 'income' ? currentDeposit + amount : currentDeposit - amount,
            withdrawals: type === 'expense' ? currentWithdrawals + amount : currentWithdrawals
        };

        fetch(`api.php?action=update&table=${TABLE}&id=${bankId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    closeTransactionModal();
                    location.reload();
                } else {
                    alert('更新失敗: ' + (res.error || ''));
                }
            })
            .catch(err => alert('更新失敗: ' + (err.message || '網路錯誤')));
    }

</script>
