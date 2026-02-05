<?php
$pageTitle = '食品管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM food ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄食品</h1>
</div>

<div class="content-body">
    <button class="btn btn-primary" onclick="openModal()">新增食品</button>
    <?php $csvTable = 'food'; include 'includes/csv_buttons.php'; ?>

    <table class="table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>圖片</th>
                <th>食品名稱</th>
                <th>數量</th>
                <th>價格</th>
                <th>商店</th>
                <th>有效期限</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="7" style="text-align: center; color: #999;">暫無食品資料</td></tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?php if (!empty($item['photo'])): ?>
                                <img src="<?php echo htmlspecialchars($item['photo']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo $item['amount'] ?? 0; ?></td>
                        <td><?php echo formatMoney($item['price']); ?></td>
                        <td><?php echo htmlspecialchars($item['shop'] ?? '-'); ?></td>
                        <td><?php echo formatDate($item['todate']); ?></td>
                        <td>
                            <button class="btn btn-sm" onclick="editItem('<?php echo $item['id']; ?>')">編輯</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteItem('<?php echo $item['id']; ?>')">刪除</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
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

document.getElementById('itemForm').addEventListener('submit', function(e) {
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
        headers: {'Content-Type': 'application/json'},
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
        function(res) {
            document.getElementById('photo').value = res.file;
            updatePhotoPreview();
        },
        function(error) {
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
