<?php
$pageTitle = '食品管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM food ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄食品</h1>
</div>

<div class="content-body">
    <button class="btn btn-primary" onclick="openModal()" title="新增食品"><i class="fas fa-plus"></i></button>
    <?php $csvTable = 'food';
    include 'includes/csv_buttons.php'; ?>

    <?php include 'includes/batch-delete.php'; ?>

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
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #999;">暫無食品資料</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo $item['id']; ?>"
                                onchange="toggleSelectItem(this)"></td>
                        <td>
                            <?php if (!empty($item['photo'])): ?>
                                <img src="<?php echo htmlspecialchars($item['photo']); ?>"
                                    style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($item['name']); ?>
                            <span class="card-edit-btn" onclick="editItem('<?php echo $item['id']; ?>')"
                                style="cursor: pointer; margin-left: 8px;"><i class="fas fa-pen"></i></span>
                            <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')"
                                style="margin-left: 6px; cursor: pointer;">&times;</span>
                        </td>
                        <td><?php echo $item['amount'] ?? 0; ?></td>
                        <td><?php echo formatMoney($item['price']); ?></td>
                        <td><?php echo htmlspecialchars($item['shop'] ?? '-'); ?></td>
                        <td><?php echo formatDate($item['todate']); ?></td>
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