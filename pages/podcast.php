<?php
$pageTitle = '播客管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM podcast ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄播客</h1>
</div>

<div class="content-body">
    <button class="btn btn-primary" onclick="openModal()">新增播客</button>
    <div style="display: inline-block; margin-left: 10px;">
        <a href="export_zip_podcast.php" class="btn btn-success">
            <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
        </a>
        <button type="button" class="btn" onclick="document.getElementById('importZipFile').click()">
            <i class="fa-solid fa-file-zipper"></i> 匯入 ZIP
        </button>
        <input type="file" id="importZipFile" accept=".zip" style="display: none;" onchange="importZIP(this)">
    </div>

    <div class="card-grid" style="margin-top: 20px;">
        <?php if (empty($items)): ?>
            <div class="card">
                <p style="text-align: center; color: #999;">暫無播客</p>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="card">
                    <?php if ($item['cover']): ?>
                        <img src="<?php echo htmlspecialchars($item['cover']); ?>"
                            style="width: 100%; height: 150px; object-fit: cover; border-radius: 5px; margin-bottom: 10px;">
                    <?php endif; ?>
                    <h3 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p style="color: #666; font-size: 0.9rem;"><?php echo htmlspecialchars($item['category'] ?? '未分類'); ?></p>
                    <p style="font-size: 0.85rem; color: #999;"><?php echo htmlspecialchars($item['note'] ?? ''); ?></p>

                    <?php if ($item['file']): ?>
                        <div style="margin-top: 10px;">
                            <audio id="audio-<?php echo $item['id']; ?>" src="<?php echo htmlspecialchars($item['file']); ?>"
                                preload="none"></audio>
                            <button class="btn btn-sm btn-success" onclick="togglePlay('<?php echo $item['id']; ?>')"
                                id="playBtn-<?php echo $item['id']; ?>">
                                <i class="fa-solid fa-play"></i> 播放
                            </button>
                            <span id="time-<?php echo $item['id']; ?>"
                                style="font-size: 0.8rem; color: #888; margin-left: 8px;">00:00</span>
                        </div>
                    <?php endif; ?>

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
        <h2 id="modalTitle">新增播客</h2>
        <form id="itemForm">
            <input type="hidden" id="itemId" name="id">
            <div class="form-group">
                <label>名稱 *</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label>上傳播客</label>
                <input type="file" class="form-control" id="fileUpload" name="fileUpload"
                    accept="audio/*,video/*,.mp4,.mp3,.wav,.m4a">

                <!-- 播客預覽 -->
                <div id="podcastPreview"
                    style="display:none;margin-top:10px;padding:10px;background:#2a2a2a;border-radius:8px;">
                    <audio id="previewAudio" controls style="width:100%;display:none;"></audio>
                    <video id="previewVideo" controls
                        style="width:100%;max-height:200px;display:none;border-radius:5px;"></video>
                </div>

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
    const TABLE = 'podcast';
    let currentPlayingId = null;

    // 播放/暫停切換
    function togglePlay(id) {
        const audio = document.getElementById('audio-' + id);
        const btn = document.getElementById('playBtn-' + id);

        // 如果有其他正在播放的，先暫停
        if (currentPlayingId && currentPlayingId !== id) {
            const otherAudio = document.getElementById('audio-' + currentPlayingId);
            const otherBtn = document.getElementById('playBtn-' + currentPlayingId);
            if (otherAudio && !otherAudio.paused) {
                otherAudio.pause();
                otherBtn.innerHTML = '<i class="fa-solid fa-play"></i> 播放';
            }
        }

        if (audio.paused) {
            audio.play();
            btn.innerHTML = '<i class="fa-solid fa-pause"></i> 暫停';
            currentPlayingId = id;
        } else {
            audio.pause();
            btn.innerHTML = '<i class="fa-solid fa-play"></i> 播放';
            currentPlayingId = null;
        }
    }

    // 格式化時間
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    }

    // 初始化音頻事件監聽
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('audio').forEach(audio => {
            const id = audio.id.replace('audio-', '');
            const timeSpan = document.getElementById('time-' + id);
            const btn = document.getElementById('playBtn-' + id);

            audio.addEventListener('timeupdate', function () {
                timeSpan.textContent = formatTime(audio.currentTime) + ' / ' + formatTime(audio.duration || 0);
            });

            audio.addEventListener('ended', function () {
                btn.innerHTML = '<i class="fa-solid fa-play"></i> 播放';
                currentPlayingId = null;
            });
        });
    });
    // 播客檔案上傳處理
    document.getElementById('fileUpload').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            // 取得檔案名稱（去除副檔名）自動填入名稱欄位
            const fileName = file.name;
            const nameWithoutExt = fileName.replace(/\.[^/.]+$/, '');
            const nameInput = document.getElementById('name');
            if (!nameInput.value.trim()) {
                nameInput.value = nameWithoutExt;
            }

            // 顯示本地預覽
            const previewContainer = document.getElementById('podcastPreview');
            const previewAudio = document.getElementById('previewAudio');
            const previewVideo = document.getElementById('previewVideo');
            const url = URL.createObjectURL(file);

            previewContainer.style.display = 'block';
            if (file.type.startsWith('video/')) {
                previewAudio.style.display = 'none';
                previewVideo.style.display = 'block';
                previewVideo.src = url;
            } else {
                previewVideo.style.display = 'none';
                previewAudio.style.display = 'block';
                previewAudio.src = url;
            }

            // 使用共用上傳進度元件
            uploadFileWithProgress(file,
                function (res) {
                    document.getElementById('file').value = res.file;
                },
                function (error) {
                    alert('播客上傳失敗: ' + error);
                }
            );
        }
    });

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

    // 監聽網址輸入變化來更新預覽
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
        document.getElementById('modalTitle').textContent = '新增播客';
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '';
        document.getElementById('coverPreview').style.display = 'none';
        document.getElementById('coverPreviewImg').src = '';
        // 重置播客預覽
        document.getElementById('podcastPreview').style.display = 'none';
        document.getElementById('previewAudio').src = '';
        document.getElementById('previewVideo').src = '';
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

                    document.getElementById('modalTitle').textContent = '編輯播客';
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

    function importZIP(input) {
        if (!input.files || !input.files[0]) return;

        if (!confirm('確定要匯入 ZIP 嗎？播客將會新增到資料庫。')) {
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
        formData.append('file', file);

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressText.textContent = percent + '%';
                const loaded = formatFileSize(e.loaded);
                const total = formatFileSize(e.total);
                fileName.textContent = file.name + ' (' + loaded + ' / ' + total + ')';
            }
        });

        xhr.addEventListener('load', function () {
            modal.style.display = 'none';
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.success) {
                    alert('匯入完成！\n成功匯入: ' + res.imported + ' 個播客');
                    location.reload();
                } else {
                    alert('匯入失敗: ' + (res.error || '未知錯誤'));
                }
            } catch (e) {
                alert('匯入失敗: 回應格式錯誤');
            }
        });

        xhr.addEventListener('error', function () {
            modal.style.display = 'none';
            alert('匯入失敗: 網路錯誤');
        });

        xhr.open('POST', 'import_zip_podcast.php');
        xhr.send(formData);
        input.value = '';
    }
</script>