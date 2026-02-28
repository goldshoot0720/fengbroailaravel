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
    <?php include 'includes/inline-edit-hint.php'; ?>
    <button class="btn btn-primary" onclick="handleAdd()" title="新增影片"><i class="fas fa-plus"></i></button>
    <div style="display: inline-block; margin-left: 10px;">
        <a href="export_zip_video.php" class="btn btn-success">
            <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
        </a>
        <button type="button" class="btn" onclick="document.getElementById('importZipFile').click()">
            <i class="fa-solid fa-file-zipper"></i> 匯入 ZIP
        </button>
        <input type="file" id="importZipFile" accept=".zip" style="display: none;" onchange="importZIP(this)">
    </div>

    <div class="video-list" style="margin-top: 20px;">
        <div id="inlineAddCard" class="video-item inline-add-card" style="background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: relative;">
            <div class="inline-edit inline-edit-always">
                <div class="form-group">
                    <label>名稱 *</label>
                    <input type="text" class="form-control inline-input" data-field="name">
                </div>
                <div class="form-group">
                    <label>檔案路徑</label>
                    <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入影片網址" oninput="updateInlineVideoPreview(this)">
                    <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                        <input type="file" class="inline-video-file" accept="video/*" style="display: none;" onchange="uploadInlineVideo(this)">
                        <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳影片</button>
                    </div>
                    <div class="inline-video-preview" style="margin-top: 6px;"></div>
                </div>
                <div class="form-group">
                    <label>封面圖</label>
                    <input type="text" class="form-control inline-input" data-field="cover" placeholder="輸入封面圖網址" oninput="updateInlineCoverPreview(this)">
                    <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                        <input type="file" class="inline-cover-file" accept="image/*" style="display: none;" onchange="uploadInlineCover(this)">
                        <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳封面</button>
                        <div class="inline-cover-preview"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>參考</label>
                    <input type="text" class="form-control inline-input" data-field="ref">
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
            <div class="card"><p style="text-align: center; color: #999;">暫無影片</p></div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="video-item" style="background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: relative;"
                    data-id="<?php echo $item['id']; ?>"
                    data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                    data-file="<?php echo htmlspecialchars($item['file'] ?? '', ENT_QUOTES); ?>"
                    data-cover="<?php echo htmlspecialchars($item['cover'] ?? '', ENT_QUOTES); ?>"
                    data-ref="<?php echo htmlspecialchars($item['ref'] ?? '', ENT_QUOTES); ?>"
                    data-note="<?php echo htmlspecialchars($item['note'] ?? '', ENT_QUOTES); ?>">
                    <div class="inline-view">
                        <div class="card-actions">
                            <span class="card-edit-btn" onclick="startInlineEdit('<?php echo $item['id']; ?>')"><i class="fas fa-pen"></i></span>
                            <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <?php if (!empty($item['cover'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['cover']); ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 5px;">
                                <?php else: ?>
                                    <div style="width: 80px; height: 60px; background: #34495e; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa-solid fa-video" style="color: #fff; font-size: 1.5rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h3 style="margin: 0 0 5px 0; font-size: 1.1rem;"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <?php if (!empty($item['note'])): ?>
                                        <p style="margin: 0; color: #666; font-size: 0.85rem;"><?php echo htmlspecialchars($item['note']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <?php if (!empty($item['file'])): ?>
                                    <button class="btn btn-primary btn-sm" onclick="playVideo('<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['file']); ?>', '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')">
                                        <i class="fa-solid fa-play"></i> 播放
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="inline-edit">
                        <div class="form-group">
                            <label>名稱 *</label>
                            <input type="text" class="form-control inline-input" data-field="name">
                        </div>
                        <div class="form-group">
                            <label>檔案路徑</label>
                            <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入影片網址" oninput="updateInlineVideoPreview(this)">
                            <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                <input type="file" class="inline-video-file" accept="video/*" style="display: none;" onchange="uploadInlineVideo(this)">
                                <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳影片</button>
                            </div>
                            <div class="inline-video-preview" style="margin-top: 6px;"></div>
                        </div>
                        <div class="form-group">
                            <label>封面圖</label>
                            <input type="text" class="form-control inline-input" data-field="cover" placeholder="輸入封面圖網址" oninput="updateInlineCoverPreview(this)">
                            <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                <input type="file" class="inline-cover-file" accept="image/*" style="display: none;" onchange="uploadInlineCover(this)">
                                <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳封面</button>
                                <div class="inline-cover-preview"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>參考</label>
                            <input type="text" class="form-control inline-input" data-field="ref">
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

    <!-- Video.js CSS -->
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet">
    <style>
        /* Force 16:9 aspect ratio for video player */
        .video-container {
            position: relative;
            width: 100%;
            padding-top: 56.25%; /* 16:9 aspect ratio */
            background: #000;
            border-radius: 10px;
            overflow: hidden;
        }
        .video-container .video-js {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        /* Ensure vertical videos are centered with black bars */
        .video-js .vjs-tech {
            object-fit: contain !important;
        }
        /* Make progress bar easier to click */
        .video-js .vjs-progress-control {
            flex: auto;
        }
        .video-js .vjs-progress-holder {
            height: 8px;
        }
        .video-js .vjs-progress-holder:hover {
            height: 12px;
        }
        .video-js .vjs-play-progress {
            background-color: #4CAF50;
        }
        .video-js .vjs-load-progress {
            background: rgba(255,255,255,0.3);
        }
        /* Tooltip for time preview */
        .video-js .vjs-mouse-display {
            display: block !important;
        }
    </style>

    <!-- Video Player Modal -->
    <div id="videoPlayerModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.9); z-index: 9999; justify-content: center; align-items: center;">
        <div style="width: 90%; max-width: 900px; position: relative;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 id="videoPlayerTitle" style="color: #fff; margin: 0;"></h3>
                <button onclick="closeVideoPlayer()" style="background: none; border: none; color: #fff; font-size: 2rem; cursor: pointer;">&times;</button>
            </div>
            <div class="video-container">
                <video id="videoPlayer" class="video-js vjs-big-play-centered" controls preload="auto">
                    <p class="vjs-no-js">您的瀏覽器不支援影片播放</p>
                </video>
            </div>
        </div>
    </div>

    <!-- Video.js JS -->
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
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

<?php include 'includes/upload-progress.php'; ?>

<script>
const TABLE = 'commondocument';

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
        cover: card.querySelector('[data-field="cover"]').value.trim(),
        ref: card.querySelector('[data-field="ref"]').value.trim(),
        note: card.querySelector('[data-field="note"]').value.trim(),
        category: 'video'
    };
    fetch(`api.php?action=create&table=${TABLE}`, {
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

function getCardById(id) {
    return document.querySelector(`.video-item[data-id="${id}"]`);
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
        updateInlineVideoPreview(fileInput);
    }
    const coverInput = card.querySelector('[data-field="cover"]');
    if (coverInput) {
        coverInput.value = data.cover || '';
        updateInlineCoverPreview(coverInput);
    }
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
        cover: card.querySelector('[data-field="cover"]').value.trim(),
        ref: card.querySelector('[data-field="ref"]').value.trim(),
        note: card.querySelector('[data-field="note"]').value.trim(),
        category: 'video'
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

function uploadInlineVideo(fileInput) {
    if (!fileInput.files || !fileInput.files[0]) return;
    const file = fileInput.files[0];
    const formGroup = fileInput.closest('.form-group');
    const urlInput = formGroup.querySelector('[data-field="file"]');
    uploadFileWithProgress(file,
        function (res) {
            urlInput.value = res.file;
            updateInlineVideoPreview(urlInput);
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

function updateInlineVideoPreview(input) {
    const preview = input.closest('.form-group').querySelector('.inline-video-preview');
    if (!preview) return;
    const url = input.value.trim();
    preview.innerHTML = url
        ? `<video src="${url}" controls style="max-width: 100%; max-height: 160px; border-radius: 5px;"></video>`
        : '';
}

function uploadInlineCover(fileInput) {
    if (!fileInput.files || !fileInput.files[0]) return;
    const formGroup = fileInput.closest('.form-group');
    const urlInput = formGroup.querySelector('[data-field="cover"]');
    uploadFileWithProgress(fileInput.files[0],
        function (res) {
            urlInput.value = res.file;
            updateInlineCoverPreview(urlInput);
        },
        function (error) { alert('上傳失敗: ' + error); }
    );
    fileInput.value = '';
}

function updateInlineCoverPreview(input) {
    const preview = input.closest('.form-group').querySelector('.inline-cover-preview');
    if (!preview) return;
    const url = input.value.trim();
    preview.innerHTML = url
        ? `<img src="${url}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">`
        : '';
}

function uploadVideo() {
    const input = document.getElementById('videoFile');
    if (!input.files || !input.files[0]) return;

    uploadFileWithProgress(input.files[0],
        function(res) {
            document.getElementById('file').value = res.file;
            const nameInput = document.getElementById('name');
            if (nameInput && !nameInput.value) {
                nameInput.value = res.filename || '';
            }
            updateVideoPreview();
        },
        function(error) {
            alert('上傳失敗: ' + error);
        }
    );
    input.value = '';
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

    uploadFileWithProgress(input.files[0],
        function(res) {
            document.getElementById('cover').value = res.file;
            updateCoverPreview();
        },
        function(error) {
            alert('上傳失敗: ' + error);
        }
    );
    input.value = '';
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

let vjsPlayer = null;

function initVideoJS() {
    if (!vjsPlayer) {
        vjsPlayer = videojs('videoPlayer', {
            controls: true,
            autoplay: true,
            preload: 'auto',
            fill: true,
            playbackRates: [0.5, 1, 1.25, 1.5, 2],
            userActions: {
                hotkeys: true
            },
            controlBar: {
                progressControl: {
                    seekBar: true
                },
                children: [
                    'playToggle',
                    'volumePanel',
                    'currentTimeDisplay',
                    'timeDivider',
                    'durationDisplay',
                    'progressControl',
                    'playbackRateMenuButton',
                    'fullscreenToggle'
                ]
            }
        });

        // Enable keyboard shortcuts for seeking
        vjsPlayer.on('keydown', function(e) {
            const currentTime = vjsPlayer.currentTime();
            const duration = vjsPlayer.duration();

            switch(e.which) {
                case 37: // Left arrow - back 5 seconds
                    vjsPlayer.currentTime(Math.max(0, currentTime - 5));
                    e.preventDefault();
                    break;
                case 39: // Right arrow - forward 5 seconds
                    vjsPlayer.currentTime(Math.min(duration, currentTime + 5));
                    e.preventDefault();
                    break;
                case 74: // J - back 10 seconds
                    vjsPlayer.currentTime(Math.max(0, currentTime - 10));
                    e.preventDefault();
                    break;
                case 76: // L - forward 10 seconds
                    vjsPlayer.currentTime(Math.min(duration, currentTime + 10));
                    e.preventDefault();
                    break;
                case 32: // Space - play/pause
                    if (vjsPlayer.paused()) {
                        vjsPlayer.play();
                    } else {
                        vjsPlayer.pause();
                    }
                    e.preventDefault();
                    break;
            }
        });
    }
    return vjsPlayer;
}

function playVideo(id, src, title) {
    const modal = document.getElementById('videoPlayerModal');
    const titleEl = document.getElementById('videoPlayerTitle');

    titleEl.textContent = title;
    modal.style.display = 'flex';

    const player = initVideoJS();
    player.src({ type: 'video/mp4', src: src });
    player.play();
}

function closeVideoPlayer() {
    const modal = document.getElementById('videoPlayerModal');

    if (vjsPlayer) {
        vjsPlayer.pause();
        vjsPlayer.src('');
    }
    modal.style.display = 'none';
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeVideoPlayer();
    }
});

// Close modal on background click
document.getElementById('videoPlayerModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeVideoPlayer();
    }
});

function importZIP(input) {
    if (!input.files || !input.files[0]) return;

    if (!confirm('確定要匯入 ZIP 嗎？影片將會新增到資料庫。')) {
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
                alert('匯入完成！\n成功匯入: ' + res.imported + ' 部影片');
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

    xhr.open('POST', 'import_zip_video.php');
    xhr.send(formData);
    input.value = '';
}
</script>
