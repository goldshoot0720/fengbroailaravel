<?php
$pageTitle = '文件管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM commondocument WHERE category != 'video' OR category IS NULL ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄文件</h1>
</div>

<div class="content-body">
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
    <button class="btn btn-primary" onclick="openModal()">新增文件</button>
    <a href="export_zip_document.php" class="btn btn-success"><i class="fa-solid fa-download"></i> 匯出 ZIP</a>
    <button class="btn btn-info" onclick="document.getElementById('zipImport').click()"><i
            class="fa-solid fa-upload"></i> 匯入 ZIP</button>
    <form id="zipImportForm" action="import_zip_document.php" method="POST" enctype="multipart/form-data"
        style="display:none;">
        <input type="file" name="zipfile" id="zipImport" accept=".zip" onchange="this.form.submit()">
    </form>

    <table class="table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>名稱</th>
                <th>分類</th>
                <th>參考</th>
                <th>備註</th>
                <th>建立時間</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #999;">暫無文件</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($item['ref'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($item['note'] ?? '-'); ?></td>
                        <td><?php echo formatDateTime($item['created_at']); ?></td>
                        <td>
                            <?php if (!empty($item['file'])): ?>
                                <button class="btn btn-sm btn-primary"
                                    onclick="previewDocument('<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['file']); ?>', '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')">
                                    <i class="fa-solid fa-eye"></i> 預覽
                                </button>
                            <?php endif; ?>
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
        <h2 id="modalTitle">新增文件</h2>
        <form id="itemForm">
            <input type="hidden" id="itemId" name="id">
            <div class="form-group">
                <label>名稱 *</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label>上傳檔案</label>
                <input type="file" class="form-control" id="fileUpload" name="fileUpload">
                <small style="color:#666;">或手動輸入路徑：</small>
                <input type="text" class="form-control" id="file" name="file" style="margin-top:5px;">
            </div>
            <div class="form-group">
                <label>封面圖</label>
                <input type="file" class="form-control" id="coverUpload" name="coverUpload" accept="image/*">
                <div id="coverPreview" style="margin-top:10px;display:none;">
                    <img id="coverPreviewImg" src="" alt="封面預覽"
                        style="max-width:200px;max-height:150px;border-radius:8px;border:1px solid #444;">
                </div>
                <small style="color:#666;">或手動輸入網址：</small>
                <input type="text" class="form-control" id="cover" name="cover" style="margin-top:5px;"
                    placeholder="上傳圖片或輸入網址">
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
                <input type="text" class="form-control" id="note" name="note">
            </div>
            <button type="submit" class="btn btn-primary">儲存</button>
        </form>
    </div>
</div>

<?php include 'includes/upload-progress.php'; ?>

<script>
    const TABLE = 'commondocument';

    // 封面圖上傳處理
    document.getElementById('coverUpload').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            // 顯示本地預覽
            const reader = new FileReader();
            reader.onload = function (re) {
                document.getElementById('coverPreviewImg').src = re.target.result;
                document.getElementById('coverPreview').style.display = 'block';
            };
            reader.readAsDataURL(file);

            // 使用共用上傳進度元件
            uploadFileWithProgress(file,
                function (res) {
                    document.getElementById('cover').value = res.file;
                },
                function (error) {
                    alert('封面圖上傳失敗: ' + error);
                }
            );
        }
    });

    // 上傳檔案處理
    document.getElementById('fileUpload').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            // 取得檔案名稱（去除副檔名）
            const fileName = file.name;
            const nameWithoutExt = fileName.replace(/\.[^/.]+$/, '');

            // 只有在名稱欄位為空時才自動填入
            const nameInput = document.getElementById('name');
            if (!nameInput.value.trim()) {
                nameInput.value = nameWithoutExt;
            }

            // 使用共用上傳進度元件
            uploadFileWithProgress(file,
                function (res) {
                    document.getElementById('file').value = res.file;
                },
                function (error) {
                    alert('檔案上傳失敗: ' + error);
                }
            );
        }
    });

    // 監聯網址輸入變化來更新預覽
    document.getElementById('cover').addEventListener('input', function (e) {
        const url = e.target.value.trim();
        if (url) {
            document.getElementById('coverPreviewImg').src = url;
            document.getElementById('coverPreview').style.display = 'block';
        } else {
            document.getElementById('coverPreview').style.display = 'none';
        }
    });

    function openModal() {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modalTitle').textContent = '新增文件';
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '';
        document.getElementById('coverPreview').style.display = 'none';
        document.getElementById('coverPreviewImg').src = '';
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
                    document.getElementById('category').value = d.category || '';
                    document.getElementById('ref').value = d.ref || '';
                    document.getElementById('note').value = d.note || '';

                    // 顯示現有封面圖預覽
                    if (d.cover) {
                        document.getElementById('coverPreviewImg').src = d.cover;
                        document.getElementById('coverPreview').style.display = 'block';
                    } else {
                        document.getElementById('coverPreview').style.display = 'none';
                    }

                    document.getElementById('modalTitle').textContent = '編輯文件';
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
            file: document.getElementById('file').value,
            cover: document.getElementById('cover').value,
            category: document.getElementById('category').value,
            ref: document.getElementById('ref').value,
            note: document.getElementById('note').value
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
            // Office 文件 - 使用 Microsoft Office Online Viewer
            const fullUrl = window.location.origin + '/' + filePath;
            const viewerUrl = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(fullUrl);
            previewContent.innerHTML = `
                <iframe src="${viewerUrl}" style="width:100%;height:70vh;border:none;border-radius:8px;"></iframe>
                <div style="margin-top:15px;text-align:center;">
                    <small style="color:#888;">如無法預覽，請確認伺服器為公開可存取，或</small>
                    <a href="${filePath}" download class="btn btn-sm btn-primary" style="margin-left:10px;">
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