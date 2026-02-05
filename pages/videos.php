<?php
$pageTitle = '影片管理';
$pdo = getConnection();
// 使用 commondocument 表來存放影片
$items = $pdo->query("SELECT * FROM commondocument WHERE category = 'video' ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄影片</h1>
</div>

<div class="content-body">
    <button class="btn btn-primary" onclick="openModal()">新增影片</button>
    <?php $csvTable = 'commondocument'; include 'includes/csv_buttons.php'; ?>

    <div class="card-grid" style="margin-top: 20px;">
        <?php if (empty($items)): ?>
            <div class="card"><p style="text-align: center; color: #999;">暫無影片</p></div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="card">
                    <?php if ($item['cover']): ?>
                        <img src="<?php echo htmlspecialchars($item['cover']); ?>" style="width: 100%; height: 150px; object-fit: cover; border-radius: 5px; margin-bottom: 10px;">
                    <?php endif; ?>
                    <h3 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p style="font-size: 0.85rem; color: #999;"><?php echo htmlspecialchars($item['note'] ?? ''); ?></p>
                    <div style="margin-top: 15px;">
                        <button class="btn btn-sm" onclick="editItem('<?php echo $item['id']; ?>')">編輯</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteItem('<?php echo $item['id']; ?>')">刪除</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="modal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">新增影片</h2>
        <form id="itemForm">
            <input type="hidden" id="itemId" name="id">
            <div class="form-group">
                <label>名稱 *</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label>檔案路徑</label>
                <input type="text" class="form-control" id="file" name="file" placeholder="輸入影片網址或上傳">
                <div style="margin-top: 8px;">
                    <input type="file" id="videoFile" accept="video/*" onchange="uploadVideo()" style="display: none;">
                    <button type="button" class="btn" onclick="document.getElementById('videoFile').click()">
                        <i class="fa-solid fa-upload"></i> 上傳影片
                    </button>
                </div>
                <div id="videoPreview" style="margin-top: 10px;"></div>
            </div>
            <div class="form-group">
                <label>封面圖</label>
                <input type="text" class="form-control" id="cover" name="cover" placeholder="輸入封面圖網址或上傳">
                <div style="margin-top: 8px;">
                    <input type="file" id="coverFile" accept="image/*" onchange="uploadCover()" style="display: none;">
                    <button type="button" class="btn" onclick="document.getElementById('coverFile').click()">
                        <i class="fa-solid fa-upload"></i> 上傳封面圖
                    </button>
                </div>
                <div id="coverPreview" style="margin-top: 10px;"></div>
            </div>
            <div class="form-group">
                <label>參考</label>
                <input type="text" class="form-control" id="ref" name="ref">
            </div>
            <div class="form-group">
                <label>備註</label>
                <textarea class="form-control" id="note" name="note" rows="4"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">儲存</button>
        </form>
    </div>
</div>

<script>
const TABLE = 'commondocument';

function openModal() {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = '新增影片';
    document.getElementById('itemForm').reset();
    document.getElementById('itemId').value = '';
    updateVideoPreview();
    updateCoverPreview();
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
                document.getElementById('file').value = d.file || '';
                document.getElementById('cover').value = d.cover || '';
                document.getElementById('ref').value = d.ref || '';
                document.getElementById('note').value = d.note || '';
                document.getElementById('modalTitle').textContent = '編輯影片';
                document.getElementById('modal').style.display = 'flex';
                updateVideoPreview();
                updateCoverPreview();
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
        file: document.getElementById('file').value,
        cover: document.getElementById('cover').value,
        ref: document.getElementById('ref').value,
        note: document.getElementById('note').value,
        category: 'video'
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

function uploadVideo() {
    const input = document.getElementById('videoFile');
    if (!input.files || !input.files[0]) return;

    const formData = new FormData();
    formData.append('file', input.files[0]);

    fetch('upload.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById('file').value = res.file;
            const nameInput = document.getElementById('name');
            if (nameInput && !nameInput.value) {
                nameInput.value = res.filename || '';
            }
            updateVideoPreview();
        } else {
            alert('上傳失敗: ' + (res.error || ''));
        }
    })
    .catch(err => {
        alert('上傳失敗: ' + err.message);
    });
}

function updateVideoPreview() {
    const file = document.getElementById('file').value;
    const preview = document.getElementById('videoPreview');

    if (file) {
        preview.innerHTML = `<video src="${file}" controls style="max-width: 100%; max-height: 200px; border-radius: 5px;"></video>`;
    } else {
        preview.innerHTML = '';
    }
}

document.getElementById('file').addEventListener('change', updateVideoPreview);
document.getElementById('file').addEventListener('input', updateVideoPreview);

function uploadCover() {
    const input = document.getElementById('coverFile');
    if (!input.files || !input.files[0]) return;

    const formData = new FormData();
    formData.append('file', input.files[0]);

    fetch('upload.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById('cover').value = res.file;
            updateCoverPreview();
        } else {
            alert('上傳失敗: ' + (res.error || ''));
        }
    })
    .catch(err => {
        alert('上傳失敗: ' + err.message);
    });
}

function updateCoverPreview() {
    const file = document.getElementById('cover').value;
    const preview = document.getElementById('coverPreview');

    if (file) {
        preview.innerHTML = `<img src="${file}" style="max-width: 100%; max-height: 150px; border-radius: 5px;">`;
    } else {
        preview.innerHTML = '';
    }
}

document.getElementById('cover').addEventListener('change', updateCoverPreview);
document.getElementById('cover').addEventListener('input', updateCoverPreview);
</script>
