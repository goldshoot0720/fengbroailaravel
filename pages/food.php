<?php
$pageTitle = '食品管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM food ORDER BY CASE WHEN todate IS NULL THEN 1 ELSE 0 END, todate ASC, created_at DESC")->fetchAll();
?>

<div class="content-header" style="display: flex; align-items: center; gap: 12px;">
    <h1 style="margin: 0;">鋒兄食品</h1>
    <span style="background: linear-gradient(135deg, #27ae60, #2ecc71); color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
        <?php echo count($items); ?> 項
    </span>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
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
                        <input type="text" class="form-control inline-input" data-field="photo" placeholder="圖片網址">
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
                    <tr data-id="<?php echo $item['id']; ?>"
                        data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                        data-amount="<?php echo htmlspecialchars($item['amount'] ?? '', ENT_QUOTES); ?>"
                        data-price="<?php echo htmlspecialchars($item['price'] ?? '', ENT_QUOTES); ?>"
                        data-shop="<?php echo htmlspecialchars($item['shop'] ?? '', ENT_QUOTES); ?>"
                        data-todate="<?php echo htmlspecialchars($item['todate'] ?? '', ENT_QUOTES); ?>"
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
                                <input type="text" class="form-control inline-input" data-field="photo" placeholder="圖片網址">
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
    <div class="mobile-only" style="margin-top: 20px;">
        <?php if (empty($items)): ?>
            <div class="mobile-card" style="text-align: center; color: #999; padding: 40px;">暫無食品資料</div>
        <?php else: ?>
            <?php foreach ($items as $item):
                $isExpired = !empty($item['todate']) && strtotime($item['todate']) < time();
                $isExpiringSoon = !empty($item['todate']) && !$isExpired && strtotime($item['todate']) < strtotime('+7 days');
                ?>
                <div class="mobile-card"
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
                            <span class="mobile-card-value"><?php echo $item['amount'] ?? 0; ?></span>
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

<script>
    const TABLE = 'food';
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
        if (photoInput) photoInput.value = data.photo || '';
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
