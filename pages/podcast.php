<?php
$pageTitle = '播客管理';
$csvTable = 'podcast';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM podcast ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄播客 <span style="display: inline-block; background: linear-gradient(135deg, #8e44ad, #9b59b6); color: #fff; font-size: 0.5em; padding: 3px 12px; border-radius: 20px; vertical-align: middle; margin-left: 8px;"><?php echo count($items); ?> 集</span></h1>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <div class="action-buttons">
        <button class="btn btn-primary" onclick="handleAdd()" title="新增播客"><i class="fas fa-plus"></i></button>
        <a href="export.php?table=podcast&format=appwrite" class="btn btn-outline">
            <i class="fa-solid fa-file-csv"></i> 匯出 Appwrite
        </a>
        <a href="export.php?table=podcast&format=laravel" class="btn btn-outline">
            <i class="fa-solid fa-file-csv"></i> 匯出 Laravel
        </a>
        <a href="export_zip_podcast.php" class="btn btn-success">
            <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
        </a>
        <button type="button" class="btn" onclick="document.getElementById('zipImportPodcast').click()" title="匯入 Appwrite ZIP（含 CSV + 播客 + 封面）">
            <i class="fa-solid fa-file-zipper"></i> 匯入 ZIP
        </button>
        <input type="file" id="zipImportPodcast" accept=".zip" style="display: none;"
            onchange="previewAndImportZIP(this, 'podcast', 'import_zip_podcast.php', '播客')">
    </div>

    <?php include 'includes/batch-delete.php'; ?>

    <div class="card-grid" style="margin-top: 20px;">
        <div id="inlineAddCard" class="card inline-add-card">
            <div class="inline-edit inline-edit-always">
                <div class="form-group">
                    <label>名稱 *</label>
                    <input type="text" class="form-control inline-input" data-field="name">
                </div>
                <div class="form-group">
                    <label>檔案路徑</label>
                    <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入播客網址">
                </div>
                <div class="form-group">
                    <label>封面圖</label>
                    <input type="text" class="form-control inline-input" data-field="cover" placeholder="輸入封面圖網址">
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
                    <input type="text" class="form-control inline-input" data-field="note">
                </div>
                <div class="inline-actions">
                    <button type="button" class="btn btn-primary" onclick="saveInlineAdd()">儲存</button>
                    <button type="button" class="btn" onclick="cancelInlineAdd()">取消</button>
                </div>
            </div>
        </div>
        <?php if (empty($items)): ?>
            <div class="card">
                <p style="text-align: center; color: #999;">暫無播客</p>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="card"
                    data-id="<?php echo $item['id']; ?>"
                    data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                    data-file="<?php echo htmlspecialchars($item['file'] ?? '', ENT_QUOTES); ?>"
                    data-cover="<?php echo htmlspecialchars($item['cover'] ?? '', ENT_QUOTES); ?>"
                    data-category="<?php echo htmlspecialchars($item['category'] ?? '', ENT_QUOTES); ?>"
                    data-ref="<?php echo htmlspecialchars($item['ref'] ?? '', ENT_QUOTES); ?>"
                    data-note="<?php echo htmlspecialchars($item['note'] ?? '', ENT_QUOTES); ?>">
                    <div class="inline-view">
                        <input type="checkbox" class="batch-checkbox" value="<?php echo $item['id']; ?>" style="display:none;" onclick="toggleSelectItem(this)">
                        <div class="card-actions">
                            <span class="card-edit-btn" onclick="startInlineEdit('<?php echo $item['id']; ?>')"><i class="fas fa-pen"></i></span>
                            <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                        </div>
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
                    </div>
                    <div class="inline-edit">
                        <div class="form-group">
                            <label>名稱 *</label>
                            <input type="text" class="form-control inline-input" data-field="name">
                        </div>
                        <div class="form-group">
                            <label>檔案路徑</label>
                            <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入播客網址">
                        </div>
                        <div class="form-group">
                            <label>封面圖</label>
                            <input type="text" class="form-control inline-input" data-field="cover" placeholder="輸入封面圖網址">
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
                            <input type="text" class="form-control inline-input" data-field="note">
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


<?php include 'includes/upload-progress.php'; ?>
<?php include 'includes/zip-preview.php'; ?>

<script>
    const TABLE = 'podcast';
    let currentPlayingId = null;

    initBatchDelete(TABLE);

    function handleAdd() {
        startInlineAdd();
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
                if (res.success) location.reload();
                else alert('儲存失敗: ' + (res.error || ''));
            });
    }

    function getCardById(id) {
        return document.querySelector(`.card[data-id="${id}"]`);
    }

    function startInlineEdit(id) {
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
        if (fileInput) fileInput.value = data.file || '';
        const coverInput = card.querySelector('[data-field="cover"]');
        if (coverInput) coverInput.value = data.cover || '';
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
            cover: card.querySelector('[data-field="cover"]').value.trim(),
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
</script>
