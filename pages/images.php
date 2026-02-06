<?php
$pageTitle = '圖片管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM image ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄圖片</h1>
</div>

<div class="content-body">
    <button class="btn btn-primary" onclick="openModal()" title="新增圖片"><i class="fas fa-plus"></i></button>
    <div style="display: inline-block; margin-left: 10px;">
        <a href="export_zip.php?table=image" class="btn btn-success">
            <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
        </a>
        <button type="button" class="btn" onclick="document.getElementById('importZipFile').click()">
            <i class="fa-solid fa-file-zipper"></i> 匯入 ZIP
        </button>
        <input type="file" id="importZipFile" accept=".zip" style="display: none;" onchange="importZIP(this)">
    </div>

    <div class="card-grid" style="margin-top: 20px;">
        <?php if (empty($items)): ?>
            <div class="card"><p style="text-align: center; color: #999;">暫無圖片</p></div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="card">
                    <div class="card-actions">
                        <span class="card-edit-btn" onclick="editItem('<?php echo $item['id']; ?>')"><i class="fas fa-pen"></i></span>
                        <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                    </div>
                    <?php if (!empty($item['cover'])): ?>
                        <img src="<?php echo htmlspecialchars($item['cover']); ?>" style="width: 100%; height: 150px; object-fit: cover; border-radius: 5px; margin-bottom: 10px;">
                    <?php elseif (!empty($item['file'])): ?>
                        <img src="<?php echo htmlspecialchars($item['file']); ?>" style="width: 100%; height: 150px; object-fit: cover; border-radius: 5px; margin-bottom: 10px;">
                    <?php endif; ?>
                    <h3 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p style="color: #666; font-size: 0.9rem;"><?php echo htmlspecialchars($item['category'] ?? '未分類'); ?></p>
                    <p style="font-size: 0.85rem; color: #999;"><?php echo htmlspecialchars($item['note'] ?? ''); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="modal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">新增圖片</h2>
        <form id="itemForm">
            <input type="hidden" id="itemId" name="id">
            <div class="form-group">
                <label>名稱 *</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label>檔案路徑</label>
                <input type="text" class="form-control" id="file" name="file" placeholder="輸入圖片網址或上傳">
                <div style="margin-top: 8px;">
                    <input type="file" id="imageFile" accept="image/*" onchange="uploadImage()" style="display: none;">
                    <button type="button" class="btn" onclick="document.getElementById('imageFile').click()">
                        <i class="fa-solid fa-upload"></i> 上傳圖片
                    </button>
                </div>
                <div id="imagePreview" style="margin-top: 10px;"></div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>分類</label>
                    <input type="text" class="form-control" id="category" name="category">
                </div>
                <div class="form-group" style="flex:1">
                    <label>參考</label>
                    <input type="text" class="form-control" id="ref" name="ref">
                </div>
            </div>
            <div class="form-group">
                <label>備註</label>
                <textarea class="form-control" id="note" name="note" rows="4"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">儲存</button>
        </form>
    </div>
</div>

<?php include 'includes/upload-progress.php'; ?>

<script>
const TABLE = 'image';

function openModal() {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = '新增圖片';
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
                document.getElementById('file').value = d.file || '';
                document.getElementById('category').value = d.category || '';
                document.getElementById('ref').value = d.ref || '';
                document.getElementById('note').value = d.note || '';
                updateImagePreview();
                document.getElementById('modalTitle').textContent = '編輯圖片';
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
        file: document.getElementById('file').value,
        cover: document.getElementById('file').value,
        category: document.getElementById('category').value,
        ref: document.getElementById('ref').value,
        note: document.getElementById('note').value
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

function uploadImage() {
    const input = document.getElementById('imageFile');
    if (!input.files || !input.files[0]) return;

    uploadFileWithProgress(input.files[0],
        function(res) {
            document.getElementById('file').value = res.file;
            const nameInput = document.getElementById('name');
            if (nameInput && !nameInput.value) {
                nameInput.value = res.filename || '';
            }
            updateImagePreview();
        },
        function(error) {
            alert('上傳失敗: ' + error);
        }
    );
    input.value = '';
}

function updateImagePreview() {
    const file = document.getElementById('file').value;
    const preview = document.getElementById('imagePreview');

    if (file) {
        preview.innerHTML = `<img src="${file}" style="max-width: 150px; max-height: 150px; border-radius: 5px;">`;
    } else {
        preview.innerHTML = '';
    }
}

document.getElementById('file').addEventListener('change', updateImagePreview);
document.getElementById('file').addEventListener('input', updateImagePreview);

function importZIP(input) {
    if (!input.files || !input.files[0]) return;

    if (!confirm('確定要匯入 ZIP 嗎？圖片將會新增到資料庫。')) {
        input.value = '';
        return;
    }

    const file = input.files[0];
    const modal = document.getElementById('uploadProgressModal');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressText = document.getElementById('uploadProgressText');
    const fileName = document.getElementById('uploadFileName');

    modal.style.display = 'flex';
    progressBar.style.width = '0%';
    progressText.textContent = '0%';
    fileName.textContent = file.name;

    const xhr = new XMLHttpRequest();
    const formData = new FormData();
    formData.append('table', 'image');
    formData.append('file', file);

    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            progressText.textContent = percent + '%';
            const loaded = formatFileSize(e.loaded);
            const total = formatFileSize(e.total);
            fileName.textContent = file.name + ' (' + loaded + ' / ' + total + ')';
        }
    });

    xhr.addEventListener('load', function() {
        modal.style.display = 'none';
        try {
            const res = JSON.parse(xhr.responseText);
            if (res.success) {
                alert('匯入完成！\n成功匯入: ' + res.imported + ' 張圖片');
                location.reload();
            } else {
                alert('匯入失敗: ' + (res.error || '未知錯誤'));
            }
        } catch (e) {
            alert('匯入失敗: 回應格式錯誤');
        }
    });

    xhr.addEventListener('error', function() {
        modal.style.display = 'none';
        alert('匯入失敗: 網路錯誤');
    });

    xhr.open('POST', 'import_zip.php');
    xhr.send(formData);
    input.value = '';
}
</script>
