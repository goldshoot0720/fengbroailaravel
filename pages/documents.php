<?php
$pageTitle = '文件管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM commondocument WHERE category != 'video' OR category IS NULL ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄文件</h1>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"
            style="background:#28a745;color:#fff;padding:12px 20px;border-radius:8px;margin-bottom:15px;">
            <i class="fa-solid fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger"
            style="background:#dc3545;color:#fff;padding:12px 20px;border-radius:8px;margin-bottom:15px;">
            <i class="fa-solid fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
    <button class="btn btn-primary" onclick="handleAdd()" title="新增文件"><i class="fas fa-plus"></i></button>
    <a href="export_zip_document.php" class="btn btn-success"><i class="fa-solid fa-download"></i> 匯出 ZIP</a>
    <button class="btn btn-info" onclick="document.getElementById('zipImport').click()"><i
            class="fa-solid fa-upload"></i> 匯入 ZIP</button>
    <input type="file" id="zipImport" accept=".zip" style="display:none;"
        onchange="previewAndImportZIP(this, 'document', 'import_zip_document_ajax.php', '文件')">

    <?php include 'includes/zip-preview.php'; ?>
    <?php include 'includes/batch-delete.php'; ?>

    <!-- 桌面版表格 -->
    <table class="table desktop-only" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAllCheckbox" class="select-checkbox"
                        onchange="toggleSelectAll(this)"></th>
                <th>名稱</th>
                <th>分類</th>
                <th>參考</th>
                <th>備註</th>
                <th>建立時間</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <tr id="inlineAddRow" class="inline-add-row">
                <td></td>
                <td>
                    <div class="inline-edit inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="name" placeholder="名稱">
                        <input type="text" class="form-control inline-input" data-field="file" placeholder="檔案路徑">
                        <input type="text" class="form-control inline-input" data-field="cover" placeholder="封面圖網址">
                        <div class="inline-actions">
                            <button type="button" class="btn btn-primary" onclick="saveInlineAdd()">儲存</button>
                            <button type="button" class="btn" onclick="cancelInlineAdd()">取消</button>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="category" placeholder="分類">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="ref" placeholder="參考">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="note" placeholder="備註">
                    </div>
                </td>
                <td>-</td>
                <td></td>
            </tr>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #999;">暫無文件</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr data-id="<?php echo $item['id']; ?>"
                        data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                        data-category="<?php echo htmlspecialchars($item['category'] ?? '', ENT_QUOTES); ?>"
                        data-ref="<?php echo htmlspecialchars($item['ref'] ?? '', ENT_QUOTES); ?>"
                        data-note="<?php echo htmlspecialchars($item['note'] ?? '', ENT_QUOTES); ?>"
                        data-file="<?php echo htmlspecialchars($item['file'] ?? '', ENT_QUOTES); ?>"
                        data-cover="<?php echo htmlspecialchars($item['cover'] ?? '', ENT_QUOTES); ?>">
                        <td><input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo $item['id']; ?>"
                                onchange="toggleSelectItem(this)"></td>
                        <td>
                            <div class="inline-view">
                                <?php echo htmlspecialchars($item['name']); ?>
                                <span class="card-edit-btn" onclick="startInlineEdit('<?php echo $item['id']; ?>')"
                                    style="cursor: pointer; margin-left: 8px;"><i class="fas fa-pen"></i></span>
                                <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')"
                                    style="margin-left: 6px; cursor: pointer;">&times;</span>
                            </div>
                            <div class="inline-edit">
                                <input type="text" class="form-control inline-input" data-field="name" placeholder="名稱">
                                <input type="text" class="form-control inline-input" data-field="file" placeholder="檔案路徑">
                                <input type="text" class="form-control inline-input" data-field="cover" placeholder="封面圖網址">
                                <div class="inline-actions">
                                    <button type="button" class="btn btn-primary" onclick="saveInlineEdit('<?php echo $item['id']; ?>')">儲存</button>
                                    <button type="button" class="btn" onclick="cancelInlineEdit('<?php echo $item['id']; ?>')">取消</button>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo htmlspecialchars($item['category'] ?? '-'); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="text" class="form-control inline-input" data-field="category" placeholder="分類">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo htmlspecialchars($item['ref'] ?? '-'); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="text" class="form-control inline-input" data-field="ref" placeholder="參考">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo htmlspecialchars($item['note'] ?? '-'); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="text" class="form-control inline-input" data-field="note" placeholder="備註">
                            </div>
                        </td>
                        <td><?php echo formatDateTime($item['created_at']); ?></td>
                        <td>
                            <div class="inline-view">
                                <?php if (!empty($item['file'])): ?>
                                    <button class="btn btn-sm btn-primary"
                                        onclick="previewDocument('<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['file']); ?>', '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')">
                                        <i class="fa-solid fa-eye"></i> 預覽
                                    </button>
                                <?php endif; ?>
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
            <div class="mobile-card" style="text-align: center; color: #999; padding: 40px;">暫無文件</div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="mobile-card" style="border-left: 4px solid #e67e22;">
                    <div class="mobile-card-actions">
                        <?php if (!empty($item['file'])): ?>
                            <button class="btn btn-sm btn-primary"
                                onclick="previewDocument('<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['file']); ?>', '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')"
                                style="padding: 5px 10px;">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        <?php endif; ?>
                        <span class="card-edit-btn" onclick="editItem('<?php echo $item['id']; ?>')"><i
                                class="fas fa-pen"></i></span>
                        <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                    </div>
                    <div class="mobile-card-header">
                        <div
                            style="width: 45px; height: 45px; background: linear-gradient(135deg, #e67e22, #d35400); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-file-alt" style="color: #fff; font-size: 1.2rem;"></i>
                        </div>
                        <div style="flex: 1;">
                            <div class="mobile-card-title"><?php echo htmlspecialchars($item['name']); ?></div>
                            <?php if (!empty($item['category'])): ?>
                                <span
                                    style="font-size: 0.75rem; background: #ffeaa7; color: #d35400; padding: 2px 8px; border-radius: 10px;"><?php echo htmlspecialchars($item['category']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($item['ref'])): ?>
                        <div style="margin-top: 8px; font-size: 0.85rem; color: #666;">
                            <i class="fas fa-link" style="color: #999;"></i> <?php echo htmlspecialchars($item['ref']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($item['note'])): ?>
                        <div class="mobile-card-note"><?php echo htmlspecialchars($item['note']); ?></div>
                    <?php endif; ?>
                    <div style="margin-top: 8px; font-size: 0.75rem; color: #999;">
                        <i class="fas fa-clock"></i> <?php echo formatDateTime($item['created_at']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


<?php include 'includes/upload-progress.php'; ?>

<script>
    const TABLE = 'commondocument';
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
            file: row.querySelector('[data-field="file"]').value.trim(),
            cover: row.querySelector('[data-field="cover"]').value.trim(),
            category: row.querySelector('[data-field="category"]').value.trim(),
            ref: row.querySelector('[data-field="ref"]').value.trim(),
            note: row.querySelector('[data-field="note"]').value.trim()
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
        const fileInput = row.querySelector('[data-field="file"]');
        if (fileInput) fileInput.value = data.file || '';
        const coverInput = row.querySelector('[data-field="cover"]');
        if (coverInput) coverInput.value = data.cover || '';
        const categoryInput = row.querySelector('[data-field="category"]');
        if (categoryInput) categoryInput.value = data.category || '';
        const refInput = row.querySelector('[data-field="ref"]');
        if (refInput) refInput.value = data.ref || '';
        const noteInput = row.querySelector('[data-field="note"]');
        if (noteInput) noteInput.value = data.note || '';
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
            file: row.querySelector('[data-field="file"]').value.trim(),
            cover: row.querySelector('[data-field="cover"]').value.trim(),
            category: row.querySelector('[data-field="category"]').value.trim(),
            ref: row.querySelector('[data-field="ref"]').value.trim(),
            note: row.querySelector('[data-field="note"]').value.trim()
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

    
    // 文件預覽功能
    function previewDocument(id, filePath, title) {
        const ext = filePath.split('.').pop().toLowerCase();
        const previewModal = document.getElementById('previewModal');
        const previewTitle = document.getElementById('previewTitle');
        const previewContent = document.getElementById('previewContent');

        previewTitle.textContent = title;
        previewContent.innerHTML = '<div style="text-align:center;padding:50px;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>載入中...</div>';
        previewModal.style.display = 'flex';

        // 根據檔案類型顯示不同預覽
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(ext)) {
            // 圖片
            previewContent.innerHTML = `<img src="${filePath}" style="max-width:100%;max-height:70vh;border-radius:8px;">`;
        } else if (ext === 'pdf') {
            // PDF
            previewContent.innerHTML = `<iframe src="${filePath}" style="width:100%;height:70vh;border:none;border-radius:8px;"></iframe>`;
        } else if (['pptx', 'ppt', 'docx', 'doc', 'xlsx', 'xls'].includes(ext)) {
            // Office 文件 - 本地伺服器無法使用 Office Online Viewer，直接提供下載
            const iconClass = ext.includes('ppt') ? 'fa-file-powerpoint' : (ext.includes('doc') ? 'fa-file-word' : 'fa-file-excel');
            const iconColor = ext.includes('ppt') ? '#e67e22' : (ext.includes('doc') ? '#3498db' : '#27ae60');
            previewContent.innerHTML = `
                <div style="text-align:center;padding:50px;">
                    <i class="fa-solid ${iconClass} fa-5x" style="color:${iconColor};margin-bottom:25px;"></i>
                    <h3 style="margin-bottom:15px;">${title}</h3>
                    <p style="color:#888;margin-bottom:25px;">Office 文件需要下載後使用本機軟體開啟</p>
                    <a href="${filePath}" download class="btn btn-primary" style="font-size:1.1rem;padding:12px 30px;">
                        <i class="fa-solid fa-download"></i> 下載檔案
                    </a>
                </div>
            `;
        } else if (['mp4', 'webm', 'ogg', 'mov'].includes(ext)) {
            // 影片
            previewContent.innerHTML = `<video src="${filePath}" controls style="max-width:100%;max-height:70vh;border-radius:8px;"></video>`;
        } else if (['mp3', 'wav', 'm4a', 'ogg', 'flac'].includes(ext)) {
            // 音訊
            previewContent.innerHTML = `<audio src="${filePath}" controls style="width:100%;"></audio>`;
        } else if (['txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php', 'py', 'sql'].includes(ext)) {
            // 文字檔案 - 可編輯
            fetch(filePath)
                .then(r => r.text())
                .then(text => {
                    previewContent.innerHTML = `
                        <textarea id="textEditor" style="width:100%;height:60vh;font-family:monospace;padding:15px;border-radius:8px;border:1px solid #ddd;resize:none;">${escapeHtml(text)}</textarea>
                        <div style="margin-top:15px;text-align:right;">
                            <button class="btn btn-primary" onclick="saveTextContent('${id}', '${filePath}')">
                                <i class="fa-solid fa-save"></i> 儲存變更
                            </button>
                        </div>
                    `;
                })
                .catch(err => {
                    previewContent.innerHTML = `<p style="color:#e74c3c;">無法載入檔案內容</p>`;
                });
        } else {
            // 其他 - 提供下載連結
            previewContent.innerHTML = `
                <div style="text-align:center;padding:50px;">
                    <i class="fa-solid fa-file fa-4x" style="color:#666;margin-bottom:20px;"></i>
                    <p>此檔案類型不支援預覽</p>
                    <a href="${filePath}" download class="btn btn-primary">
                        <i class="fa-solid fa-download"></i> 下載檔案
                    </a>
                </div>
            `;
        }
    }

    function closePreviewModal() {
        document.getElementById('previewModal').style.display = 'none';
        document.getElementById('previewContent').innerHTML = '';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 儲存文字內容
    function saveTextContent(id, filePath) {
        const content = document.getElementById('textEditor').value;

        fetch('save_text_file.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path: filePath, content: content })
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert('儲存成功！');
                } else {
                    alert('儲存失敗: ' + (res.error || '未知錯誤'));
                }
            })
            .catch(err => {
                alert('儲存失敗: 連線錯誤');
            });
    }
</script>

<!-- 文件預覽彈窗 -->
<div id="previewModal" class="modal" onclick="if(event.target===this)closePreviewModal()">
    <div class="modal-content" style="max-width:900px;width:95%;">
        <span class="modal-close" onclick="closePreviewModal()">&times;</span>
        <h2 id="previewTitle">文件預覽</h2>
        <div id="previewContent" style="margin-top:20px;"></div>
    </div>
</div>
