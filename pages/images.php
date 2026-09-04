<?php
$pageTitle = '圖片管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM image ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header" style="display: flex; align-items: center; gap: 12px;">
    <h1 style="margin: 0;">鋒兄圖片</h1>
    <span style="background: #a63e34; color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
        <?php echo count($items); ?> 張
    </span>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <div class="action-buttons" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 15px;">
        <button class="btn btn-primary" onclick="handleAdd()" title="新增圖片"><i class="fas fa-plus"></i> 新增圖片</button>
        <button type="button" class="btn" onclick="document.getElementById('multiImageFiles').click()" title="一次上傳多張圖片">
            <i class="fa-solid fa-images"></i> 多圖片上傳
        </button>
        <input type="file" id="multiImageFiles" accept="image/*" multiple style="display: none;" onchange="uploadMultipleImages(this.files)">
        <a href="export_zip_image.php" class="btn btn-success" title="匯出 Appwrite ZIP（含 CSV + 圖片）">
            <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
        </a>
        <button type="button" class="btn" onclick="document.getElementById('zipImportImage').click()" title="匯入 Appwrite ZIP（含 CSV + 圖片）">
            <i class="fa-solid fa-file-zipper"></i> 匯入 ZIP
        </button>
        <input type="file" id="zipImportImage" accept=".zip" style="display: none;"
            onchange="previewAndImportZIP(this, 'image', 'import_zip_image.php', '圖片')">
        <button type="button" class="btn btn-ghost" onclick="refreshImageCacheStats()" title="離線快取狀態">
            <i class="fa-solid fa-hard-drive"></i> <span id="imageCacheStatsLabel">快取</span>
        </button>

        <?php include 'includes/batch-delete.php'; ?>
    </div>
    <div id="imageCacheBanner" style="display:none;margin:0 0 12px;padding:10px 14px;border-radius:12px;background:var(--table-header-bg);color:var(--muted-text);font-size:0.9rem;"></div>

    <div class="media-toolbar">
        <div></div>
        <div class="view-switch">
            <button type="button" class="view-btn" data-media-view-btn="grid" onclick="setMediaView('images', 'grid')"><i class="fa-solid fa-table-cells-large"></i> 卡片</button>
            <button type="button" class="view-btn" data-media-view-btn="list" onclick="setMediaView('images', 'list')"><i class="fa-solid fa-list"></i> 列表</button>
        </div>
    </div>

    <div class="media-browser media-browser-images media-view-grid" data-media-scope="images">
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
                        <?php
                        $imageSrc = !empty($item['cover']) ? $item['cover'] : ($item['file'] ?? '');
                        $cacheFile = $item['file'] ?? $imageSrc;
                        ?>
                        <?php if (!empty($imageSrc)): ?>
                            <img class="image-thumb"
                                data-image-id="<?php echo htmlspecialchars($item['id']); ?>"
                                data-src="<?php echo htmlspecialchars($imageSrc); ?>"
                                src="<?php echo htmlspecialchars($imageSrc); ?>"
                                alt="<?php echo htmlspecialchars($item['name'] ?? ''); ?>"
                                style="width: 100%; max-height: 320px; object-fit: contain; border-radius: 5px; margin-bottom: 10px; background: #faf9f5; cursor: zoom-in;"
                                onclick="openImageLightbox('<?php echo htmlspecialchars($item['id']); ?>', '<?php echo htmlspecialchars($imageSrc, ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($item['name'] ?? '')); ?>')">
                        <?php endif; ?>
                        <h3 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p style="color: #666; font-size: 0.9rem;"><?php echo htmlspecialchars($item['category'] ?? '未分類'); ?></p>
                        <p style="font-size: 0.85rem; color: #999;"><?php echo htmlspecialchars($item['note'] ?? ''); ?></p>
                        <?php if (!empty($cacheFile)): ?>
                            <div style="margin-top:8px;">
                                <button type="button" class="btn btn-sm btn-ghost image-cache-btn"
                                    data-cache-id="<?php echo htmlspecialchars($item['id']); ?>"
                                    data-cache-src="<?php echo htmlspecialchars($cacheFile, ENT_QUOTES); ?>"
                                    data-cache-title="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                                    onclick="cacheImageOffline('<?php echo htmlspecialchars($item['id']); ?>')"
                                    title="離線快取（上限 500MB）">
                                    <i class="fa-solid fa-cloud-arrow-down"></i> 快取
                                </button>
                            </div>
                        <?php endif; ?>
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
                    <input type="file" id="modalMultiImageFiles" accept="image/*" multiple onchange="uploadMultipleImages(this.files)" style="display: none;">
                    <button type="button" class="btn" onclick="document.getElementById('modalMultiImageFiles').click()">
                        <i class="fa-solid fa-images"></i> 多圖片上傳
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
        ? `<img src="${esc(data.file)}" style="width: 100%; max-height: 320px; object-fit: contain; border-radius: 5px; margin-bottom: 10px; background: #faf9f5;">`
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
    deleteInlineItem(id, { table: TABLE });
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
        function (error) { alert('上傳失敗: ' + error); },
        { title: '圖片上傳中...', completeTitle: '圖片上傳完成' }
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
        },
        { title: '圖片上傳中...', completeTitle: '圖片上傳完成' }
    );
    input.value = '';
}

function uploadFileWithProgressPromise(file) {
    return new Promise((resolve, reject) => {
        uploadFileWithProgress(file, resolve, reject);
    });
}

function createImageRecord(data) {
    return fetch(`api.php?action=create&table=${TABLE}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json());
}

function baseName(filename) {
    return String(filename || '').replace(/\.[^.]+$/, '');
}

async function uploadMultipleImages(fileList) {
    const files = Array.from(fileList || []).filter(file => file && String(file.type || '').startsWith('image/'));
    if (!files.length) return;

    const triggerInputs = ['multiImageFiles', 'modalMultiImageFiles'];
    let successCount = 0;
    const failedFiles = [];
    const totalBytes = files.reduce((sum, file) => sum + (file.size || 0), 0);
    let completedBytes = 0;

    if (document.getElementById('modal')?.style.display === 'flex') {
        closeModal();
    }

    showUploadProgressModal(
        0,
        `0% (${successCount}/${files.length})`,
        `準備上傳 0 / ${files.length} 張`,
        '多圖片上傳中...'
    );

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        try {
            const uploadRes = await new Promise((resolve, reject) => {
                uploadFileWithProgress(file, resolve, reject, {
                    showModal: false,
                    onProgress: function (progress) {
                        const aggregateLoaded = completedBytes + progress.loaded;
                        const aggregatePercent = totalBytes > 0
                            ? Math.round((aggregateLoaded / totalBytes) * 100)
                            : Math.round(((i + (progress.percent / 100)) / files.length) * 100);
                        showUploadProgressModal(
                            aggregatePercent,
                            `${aggregatePercent}% (${i + 1}/${files.length})`,
                            `第 ${i + 1} / ${files.length} 張：${file.name} (${progress.loadedText} / ${progress.totalText})`,
                            '多圖片上傳中...'
                        );
                    }
                });
            });
            completedBytes += file.size || 0;
            const data = {
                name: baseName(uploadRes.filename || file.name) || '未命名圖片',
                file: uploadRes.file,
                cover: uploadRes.file,
                category: '',
                ref: '',
                note: ''
            };
            const createRes = await createImageRecord(data);
            if (!createRes.success) {
                throw new Error(createRes.error || '建立圖片資料失敗');
            }
            successCount++;
            const aggregatePercent = totalBytes > 0
                ? Math.round((completedBytes / totalBytes) * 100)
                : Math.round((successCount / files.length) * 100);
            showUploadProgressModal(
                aggregatePercent,
                `${aggregatePercent}% (${successCount}/${files.length})`,
                `已完成 ${successCount} / ${files.length} 張`,
                '多圖片上傳中...'
            );
        } catch (error) {
            completedBytes += file.size || 0;
            failedFiles.push(`${file.name}: ${error && error.message ? error.message : error}`);
        }
    }

    showUploadProgressModal(
        100,
        `100% (${successCount}/${files.length})`,
        failedFiles.length ? `完成，失敗 ${failedFiles.length} 張` : `全部完成 ${successCount} / ${files.length} 張`,
        '多圖片上傳完成'
    );
    await new Promise(resolve => setTimeout(resolve, 450));
    hideUploadProgressModal();

    triggerInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) input.value = '';
    });

    if (successCount > 0 && failedFiles.length === 0) {
        alert(`已成功上傳 ${successCount} 張圖片`);
        location.reload();
        return;
    }

    if (successCount > 0) {
        alert(`成功 ${successCount} 張，失敗 ${failedFiles.length} 張：\n${failedFiles.join('\n')}`);
        location.reload();
        return;
    }

    alert('多圖片上傳失敗：\n' + failedFiles.join('\n'));
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

async function resolveImageSrc(id, fallbackSrc) {
    if (!window.FengbroMediaCache || !id) return fallbackSrc;
    try {
        const objectUrl = await window.FengbroMediaCache.getObjectUrl('image', id);
        return objectUrl || fallbackSrc;
    } catch (e) {
        return fallbackSrc;
    }
}

async function refreshImageCacheStats() {
    const label = document.getElementById('imageCacheStatsLabel');
    const banner = document.getElementById('imageCacheBanner');
    if (!window.FengbroMediaCache) {
        if (label) label.textContent = '快取不可用';
        return;
    }
    try {
        const stats = await window.FengbroMediaCache.getStats('image');
        const text = window.FengbroMediaCache.formatBytes(stats.totalSize) + ' / 500MB · ' + stats.totalItems + ' 張';
        if (label) label.textContent = text;
        if (banner) {
            banner.style.display = 'block';
            banner.textContent = '離線圖片快取：' + text + '（超過 500MB 會自動清除最舊項目）';
        }
        document.querySelectorAll('.image-cache-btn[data-cache-id]').forEach(async function (btn) {
            const id = btn.getAttribute('data-cache-id');
            const cached = await window.FengbroMediaCache.isCached('image', id);
            btn.classList.toggle('btn-success', cached);
            btn.innerHTML = cached
                ? '<i class="fa-solid fa-check"></i> 已快取'
                : '<i class="fa-solid fa-cloud-arrow-down"></i> 快取';
        });
        await hydrateImageThumbsFromCache();
    } catch (e) {
        if (label) label.textContent = '快取';
    }
}

async function hydrateImageThumbsFromCache() {
    if (!window.FengbroMediaCache) return;
    const imgs = document.querySelectorAll('img.image-thumb[data-image-id]');
    for (const img of imgs) {
        const id = img.getAttribute('data-image-id');
        const fallback = img.getAttribute('data-src') || img.src;
        const src = await resolveImageSrc(id, fallback);
        if (src && src !== img.src) {
            img.src = src;
            img.dataset.offline = '1';
            img.title = (img.alt || '') + ' · Offline';
        }
    }
}

async function cacheImageOffline(id) {
    const btn = document.querySelector('.image-cache-btn[data-cache-id="' + id + '"]');
    const src = btn ? (btn.getAttribute('data-cache-src') || '') : '';
    const title = btn ? (btn.getAttribute('data-cache-title') || id) : id;
    if (!src) {
        alert('找不到可快取的圖片');
        return;
    }
    if (!window.FengbroMediaCache) {
        alert('瀏覽器不支援離線快取');
        return;
    }
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 0%';
    }
    try {
        await window.FengbroMediaCache.cacheMedia('image', {
            id: id,
            title: title,
            url: src
        }, function (progress) {
            if (btn) btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + progress + '%';
        });
        await refreshImageCacheStats();
        alert('已快取到本機，可離線瀏覽');
    } catch (err) {
        alert('快取失敗：' + (err.message || err));
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down"></i> 快取';
        }
    }
}

async function openImageLightbox(id, filePath, title) {
    const src = await resolveImageSrc(id, filePath);
    const offline = src !== filePath;
    let modal = document.getElementById('imageLightboxModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'imageLightboxModal';
        modal.className = 'modal';
        modal.style.display = 'none';
        modal.onclick = function (e) {
            if (e.target === modal) closeImageLightbox();
        };
        modal.innerHTML = `
            <div class="modal-content" style="max-width:960px;width:95%;background:rgba(30, 26, 20,0.96);color:#fff;">
                <span class="modal-close" onclick="closeImageLightbox()" style="color:#fff;">&times;</span>
                <h3 id="imageLightboxTitle" style="margin:0 0 12px;"></h3>
                <div style="text-align:center;">
                    <img id="imageLightboxImg" src="" alt="" style="max-width:100%;max-height:75vh;border-radius:12px;object-fit:contain;">
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    document.getElementById('imageLightboxTitle').textContent = (title || '圖片') + (offline ? ' · Offline' : '');
    document.getElementById('imageLightboxImg').src = src;
    modal.style.display = 'flex';
}

function closeImageLightbox() {
    const modal = document.getElementById('imageLightboxModal');
    if (modal) modal.style.display = 'none';
}

window.batchCacheSelectedItems = async function (ids) {
    if (!window.FengbroMediaCache) {
        throw new Error('瀏覽器不支援離線快取');
    }
    if (!ids || !ids.length) return;
    let ok = 0;
    let fail = 0;
    for (let i = 0; i < ids.length; i++) {
        const id = ids[i];
        const btn = document.querySelector('.image-cache-btn[data-cache-id="' + id + '"]');
        const src = btn ? (btn.getAttribute('data-cache-src') || '') : '';
        const title = btn ? (btn.getAttribute('data-cache-title') || id) : id;
        if (!src) {
            fail++;
            continue;
        }
        try {
            await window.FengbroMediaCache.cacheMedia('image', {
                id: id,
                title: title,
                url: src
            });
            ok++;
            if (btn) {
                btn.classList.add('btn-success');
                btn.innerHTML = '<i class="fa-solid fa-check"></i> 已快取';
            }
        } catch (e) {
            fail++;
        }
    }
    await refreshImageCacheStats();
    alert('批次快取完成：成功 ' + ok + ' 張' + (fail ? '，失敗 ' + fail + ' 張' : ''));
};

document.addEventListener('DOMContentLoaded', function () {
    if (window.initMediaView) initMediaView('images', 'grid');
    refreshImageCacheStats();
    if (typeof enableBatchCacheButton === 'function') {
        enableBatchCacheButton(true);
    }
});

</script>
