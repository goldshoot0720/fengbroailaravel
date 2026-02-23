<?php
$pageTitle = '銀行管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM bank ORDER BY deposit DESC")->fetchAll();
$totalDeposit = $pdo->query("SELECT COALESCE(SUM(deposit), 0) FROM bank")->fetchColumn();
$totalWithdrawals = $pdo->query("SELECT COALESCE(SUM(withdrawals), 0) FROM bank")->fetchColumn();
?>

<div class="content-header">
    <h1>鋒兄銀行</h1>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <div class="action-buttons-bar">
        <button class="btn btn-primary" onclick="handleAdd()" title="新增銀行"><i class="fas fa-plus"></i></button>
        <?php $csvTable = 'bank';
        include 'includes/csv_buttons.php'; ?>
        <?php include 'includes/batch-delete.php'; ?>
    </div>
    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="card" style="background: linear-gradient(135deg, #27ae60, #219a52); color: #fff;">
            <h3>總存款</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo formatMoney($totalDeposit); ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff;">
            <h3>總提款</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo formatMoney($totalWithdrawals); ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #3498db, #2980b9); color: #fff;">
            <h3>銀行數量</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo count($items); ?></p>
        </div>
    </div>

    <!-- 桌面版表格 -->
    <table class="table desktop-only" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAllCheckbox" class="select-checkbox"
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
                        <input type="number" class="form-control inline-input" data-field="withdrawals" placeholder="提款">
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
                        <td><input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo $item['id']; ?>"
                                onchange="toggleSelectItem(this)"></td>
                        <td>
                            <div class="inline-view">
                                <?php if ($item['site']): ?>
                                    <?php $domain = parse_url($item['site'], PHP_URL_HOST); ?>
                                    <img src="https://www.google.com/s2/favicons?domain=<?php echo $domain; ?>&sz=16"
                                        style="width: 16px; height: 16px; vertical-align: middle; margin-right: 5px;">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($item['name']); ?>
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
                                    <button type="button" class="btn btn-primary" onclick="saveInlineEdit('<?php echo $item['id']; ?>')">儲存</button>
                                    <button type="button" class="btn" onclick="cancelInlineEdit('<?php echo $item['id']; ?>')">取消</button>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo formatMoney($item['deposit']); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="number" class="form-control inline-input" data-field="deposit" placeholder="存款">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo formatMoney($item['withdrawals']); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="number" class="form-control inline-input" data-field="withdrawals" placeholder="提款">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo formatMoney($item['transfer']); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="number" class="form-control inline-input" data-field="transfer" placeholder="轉帳">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo htmlspecialchars($item['account'] ?? '-'); ?></span>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo htmlspecialchars($item['card'] ?? '-'); ?></span>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo $item['site'] ? '<a href="' . htmlspecialchars($item['site']) . '" target="_blank">連結</a>' : '-'; ?></span>
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
                <div class="mobile-card" style="border-left: 4px solid #3498db;">
                    <div class="mobile-card-actions">
                        <span class="card-edit-btn" onclick="editItem('<?php echo $item['id']; ?>')"><i
                                class="fas fa-pen"></i></span>
                        <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                    </div>
                    <div class="mobile-card-header">
                        <?php if ($item['site']): ?>
                            <?php $domain = parse_url($item['site'], PHP_URL_HOST); ?>
                            <img src="https://www.google.com/s2/favicons?domain=<?php echo $domain; ?>&sz=32"
                                style="width: 32px; height: 32px; border-radius: 6px;">
                        <?php else: ?>
                            <i class="fas fa-university" style="font-size: 1.5rem; color: #3498db;"></i>
                        <?php endif; ?>
                        <div class="mobile-card-title"><?php echo htmlspecialchars($item['name']); ?></div>
                    </div>
                    <?php if ($item['site']): ?>
                        <div style="margin-bottom: 8px;"><a href="<?php echo htmlspecialchars($item['site']); ?>" target="_blank"
                                style="color: #3498db; font-size: 0.85rem;"><i class="fas fa-external-link-alt"></i> 前往網站</a></div>
                    <?php endif; ?>
                    <div class="mobile-card-info">
                        <div class="mobile-card-item">
                            <span class="mobile-card-label">存款</span>
                            <span class="mobile-card-value"
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
                                    <?php echo htmlspecialchars($item['account']); ?></div>
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


<script>
    const TABLE = 'bank';
    initBatchDelete(TABLE);

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
        if (confirm('確定要刪除嗎？')) {
            fetch(`api.php?action=delete&table=${TABLE}&id=${id}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success) location.reload();
                    else alert('刪除失敗');
                });
        }
    }

    </script>
