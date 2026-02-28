<?php
$pageTitle = '圖片管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM image ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header" style="display: flex; align-items: center; gap: 12px;">
    <h1 style="margin: 0;">鋒兄圖片</h1>
    <span style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
        <?php echo count($items); ?> 張
    </span>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <div class="action-buttons" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 15px;">
        <button class="btn btn-primary" onclick="handleAdd()" title="新增圖片"><i class="fas fa-plus"></i> 新增圖片</button>

        <a href="export.php?table=image&format=appwrite" class="btn btn-success">
            <i class="fa-solid fa-download"></i> 匯出 Appwrite
        </a>
        <a href="export.php?table=image&format=laravel" class="btn btn-success">
            <i class="fa-solid fa-download"></i> 匯出 Laravel
        </a>

        <a href="export_zip_image.php" class="btn btn-success" title="匯出 Appwrite ZIP（含 CSV + 圖片）">
            <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
        </a>
        <button type="button" class="btn" onclick="document.getElementById('zipImportImage').click()" title="匯入 Appwrite ZIP（含 CSV + 圖片）">
            <i class="fa-solid fa-file-zipper"></i> 匯入 ZIP
        </button>
        <input type="file" id="zipImportImage" accept=".zip" style="display: none;"
            onchange="previewAndImportZIP(this, 'image', 'import_zip_image.php', '圖片')">

        <?php include 'includes/batch-delete.php'; ?>
    </div>

    <div class="card-grid" style="margin-top: 20px;">
        <div id="inlineAddCard" class="card inline-add-card">
            <div class="inline-edit inline-edit-always">
                <div class="form-group">
                    <label>名稱 *</label>
                    <input type="text" class="form-control inline-input" data-field="name">
                </div>
                <div class="form-group">
                    <label>檔案路徑</label>
                    <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入圖片網址" oninput="updateInlineImagePreview(this)">
                    <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                        <input type="file" class="inline-image-file" accept="image/*" style="display: none;" onchange="uploadInlineImage(this)">
                        <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳</button>
                        <div class="inline-image-preview"></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:1">
                        <label>分類</label>
                        <input type="text" class="form-control inline-input" data-field="category">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>參考</label>
                        <input type="text" class="form-control inline-input" data-field="ref">
                    </div>
                </div>
                <div class="form-group">
                    <label>備註</label>
                    <textarea class="form-control inline-input" data-field="note" rows="4"></textarea>
                </div>
                <div class="inline-actions">
                    <button type="button" class="btn btn-primary" onclick="saveInlineAdd()">儲存</button>
                    <button type="button" class="btn" onclick="cancelInlineAdd()">取消</button>
                </div>
            </div>
        </div>
        <?php if (empty($items)): ?>
            <div class="card"><p style="text-align: center; color: #999;">暫無圖片</p></div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="card"
                    data-id="<?php echo $item['id']; ?>"
                    data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                    data-file="<?php echo htmlspecialchars($item['file'] ?? '', ENT_QUOTES); ?>"
                    data-category="<?php echo htmlspecialchars($item['category'] ?? '', ENT_QUOTES); ?>"
                    data-ref="<?php echo htmlspecialchars($item['ref'] ?? '', ENT_QUOTES); ?>"
                    data-note="<?php echo htmlspecialchars($item['note'] ?? '', ENT_QUOTES); ?>">
                    <div class="inline-view">
                        <div class="card-header">
                            <input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo $item['id']; ?>"
                                    onchange="toggleSelectItem(this)">
                            <div class="card-actions">
                                <span class="card-edit-btn" onclick="startInlineEdit('<?php echo $item['id']; ?>')"><i class="fas fa-pen"></i></span>
                                <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                            </div>
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
                    <div class="inline-edit">
                        <div class="form-group">
                            <label>名稱 *</label>
                            <input type="text" class="form-control inline-input" data-field="name">
                        </div>
                        <div class="form-group">
                            <label>檔案路徑</label>
                            <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入圖片網址" oninput="updateInlineImagePreview(this)">
                            <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                <input type="file" class="inline-image-file" accept="image/*" style="display: none;" onchange="uploadInlineImage(this)">
                                <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳</button>
                                <div class="inline-image-preview"></div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="flex:1">
                                <label>分類</label>
                                <input type="text" class="form-control inline-input" data-field="category">
                            </div>
                            <div class="form-group" style="flex:1">
                                <label>參考</label>
                                <input type="text" class="form-control inline-input" data-field="ref">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>備註</label>
                            <textarea class="form-control inline-input" data-field="note" rows="4"></textarea>
                        </div>
                        <div class="inline-actions">
                            <button type="button" class="btn btn-primary" onclick="saveInlineEdit('<?php echo $item['id']; ?>')">儲存</button>
                            <button type="button" class="btn" onclick="cancelInlineEdit('<?php echo $item['id']; ?>')">取消</button>
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
<?php include 'includes/zip-preview.php'; ?>

<script>
const TABLE = 'image';
initBatchDelete(TABLE);

function handleAdd() {
    if (window.matchMedia('(max-width: 768px)').matches) {
        openModal();
    } else {
        startInlineAdd();
    }
}

function startInlineAdd() {
    const card = document.getElementById('inlineAddCard');
    if (!card) return;
    card.style.display = 'block';
    card.querySelectorAll('[data-field]').forEach(input => {
        input.value = '';
    });
    const nameInput = card.querySelector('[data-field="name"]');
    if (nameInput) nameInput.focus();
}

function cancelInlineAdd() {
    const card = document.getElementById('inlineAddCard');
    if (!card) return;
    card.style.display = 'none';
}

function saveInlineAdd() {
    const card = document.getElementById('inlineAddCard');
    if (!card) return;
    const name = card.querySelector('[data-field="name"]').value.trim();
    if (!name) {
        alert('請輸入名稱');
        return;
    }
    const data = {
        name,
        file: card.querySelector('[data-field="file"]').value.trim(),
        cover: card.querySelector('[data-field="file"]').value.trim(),
        category: card.querySelector('[data-field="category"]').value.trim(),
        ref: card.querySelector('[data-field="ref"]').value.trim(),
        note: card.querySelector('[data-field="note"]').value.trim()
    };
    fetch(`api.php?action=create&table=${TABLE}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                addCardToGrid(res.id, data);
                cancelInlineAdd();
            } else alert('儲存失敗: ' + (res.error || ''));
        });
}

function addCardToGrid(id, data) {
    function esc(s) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }
    const imgHtml = data.file
        ? `<img src="${esc(data.file)}" style="width: 100%; height: 150px; object-fit: cover; border-radius: 5px; margin-bottom: 10px;">`
        : '';
    const newCard = document.createElement('div');
    newCard.className = 'card';
    newCard.dataset.id = id;
    newCard.dataset.name = data.name || '';
    newCard.dataset.file = data.file || '';
    newCard.dataset.category = data.category || '';
    newCard.dataset.ref = data.ref || '';
    newCard.dataset.note = data.note || '';
    newCard.innerHTML = `
        <div class="inline-view">
            <div class="card-header">
                <input type="checkbox" class="select-checkbox item-checkbox" data-id="${id}" onchange="toggleSelectItem(this)">
                <div class="card-actions">
                    <span class="card-edit-btn" onclick="startInlineEdit('${id}')"><i class="fas fa-pen"></i></span>
                    <span class="card-delete-btn" onclick="deleteItem('${id}')">&times;</span>
                </div>
            </div>
            ${imgHtml}
            <h3 class="card-title">${esc(data.name)}</h3>
            <p style="color: #666; font-size: 0.9rem;">${esc(data.category || '未分類')}</p>
            <p style="font-size: 0.85rem; color: #999;">${esc(data.note || '')}</p>
        </div>
        <div class="inline-edit" style="display: none;">
            <div class="form-group">
                <label>名稱 *</label>
                <input type="text" class="form-control inline-input" data-field="name">
            </div>
            <div class="form-group">
                <label>檔案路徑</label>
                <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入圖片網址" oninput="updateInlineImagePreview(this)">
                <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                    <input type="file" class="inline-image-file" accept="image/*" style="display: none;" onchange="uploadInlineImage(this)">
                    <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳</button>
                    <div class="inline-image-preview"></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>分類</label>
                    <input type="text" class="form-control inline-input" data-field="category">
                </div>
                <div class="form-group" style="flex:1">
                    <label>參考</label>
                    <input type="text" class="form-control inline-input" data-field="ref">
                </div>
            </div>
            <div class="form-group">
                <label>備註</label>
                <textarea class="form-control inline-input" data-field="note" rows="4"></textarea>
            </div>
            <div class="inline-actions">
                <button type="button" class="btn btn-primary" onclick="saveInlineEdit('${id}')">儲存</button>
                <button type="button" class="btn" onclick="cancelInlineEdit('${id}')">取消</button>
            </div>
        </div>`;
    const grid = document.querySelector('.card-grid');
    // 移除「暫無圖片」空狀態
    const emptyCard = grid.querySelector('.card:not(#inlineAddCard)');
    if (emptyCard && emptyCard.querySelector('p[style*="text-align"]')) emptyCard.remove();
    const addCard = document.getElementById('inlineAddCard');
    addCard ? addCard.insertAdjacentElement('afterend', newCard) : grid.appendChild(newCard);
    // 更新張數徽章
    const badge = document.querySelector('.content-header span');
    if (badge) badge.textContent = grid.querySelectorAll('.card[data-id]').length + ' 張';
}

function getCardById(id) {
    return document.querySelector(`.card[data-id="${id}"]`);
}

function startInlineEdit(id) {
    if (window.matchMedia('(max-width: 768px)').matches) {
        editItem(id);
        return;
    }
    const card = getCardById(id);
    if (!card) return;
    card.querySelectorAll('.inline-view').forEach(el => el.style.display = 'none');
    card.querySelectorAll('.inline-edit').forEach(el => el.style.display = 'block');
    fillInlineInputs(card);
}

function cancelInlineEdit(id) {
    const card = getCardById(id);
    if (!card) return;
    card.querySelectorAll('.inline-view').forEach(el => el.style.display = '');
    card.querySelectorAll('.inline-edit').forEach(el => el.style.display = 'none');
}

function fillInlineInputs(card) {
    const data = card.dataset;
    const nameInput = card.querySelector('[data-field="name"]');
    if (nameInput) nameInput.value = data.name || '';
    const fileInput = card.querySelector('[data-field="file"]');
    if (fileInput) {
        fileInput.value = data.file || '';
        updateInlineImagePreview(fileInput);
    }
    const categoryInput = card.querySelector('[data-field="category"]');
    if (categoryInput) categoryInput.value = data.category || '';
    const refInput = card.querySelector('[data-field="ref"]');
    if (refInput) refInput.value = data.ref || '';
    const noteInput = card.querySelector('[data-field="note"]');
    if (noteInput) noteInput.value = data.note || '';
}

function saveInlineEdit(id) {
    const card = getCardById(id);
    if (!card) return;
    const name = card.querySelector('[data-field="name"]').value.trim();
    if (!name) {
        alert('請輸入名稱');
        return;
    }
    const data = {
        name,
        file: card.querySelector('[data-field="file"]').value.trim(),
        cover: card.querySelector('[data-field="file"]').value.trim(),
        category: card.querySelector('[data-field="category"]').value.trim(),
        ref: card.querySelector('[data-field="ref"]').value.trim(),
        note: card.querySelector('[data-field="note"]').value.trim()
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

function uploadInlineImage(fileInput) {
    if (!fileInput.files || !fileInput.files[0]) return;
    const file = fileInput.files[0];
    const formGroup = fileInput.closest('.form-group');
    const urlInput = formGroup.querySelector('[data-field="file"]');
    uploadFileWithProgress(file,
        function (res) {
            urlInput.value = res.file;
            updateInlineImagePreview(urlInput);
            // 自動填入名稱（僅新增卡片且名稱空白時）
            const card = fileInput.closest('.inline-edit, .inline-add-card');
            if (card) {
                const nameInput = card.querySelector('[data-field="name"]');
                if (nameInput && !nameInput.value) nameInput.value = res.filename || '';
            }
        },
        function (error) { alert('上傳失敗: ' + error); }
    );
    fileInput.value = '';
}

function updateInlineImagePreview(input) {
    const preview = input.closest('.form-group').querySelector('.inline-image-preview');
    if (!preview) return;
    const url = input.value.trim();
    preview.innerHTML = url
        ? `<img src="${url}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">`
        : '';
}

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

</script>
