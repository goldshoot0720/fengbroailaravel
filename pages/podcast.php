<?php
$pageTitle = '播客管理';
$csvTable = 'podcast';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM podcast ORDER BY created_at DESC")->fetchAll();

$pcCategories = [];
foreach ($items as $item) {
    $cat = trim((string) ($item['category'] ?? ''));
    if ($cat === '') { $cat = '未分類'; }
    if (!in_array($cat, $pcCategories, true)) { $pcCategories[] = $cat; }
}
sort($pcCategories);
$pcCover = '';
foreach ($items as $item) { if (!empty($item['cover'])) { $pcCover = $item['cover']; break; } }
$pcPlayable = count(array_filter($items, fn($item) => !empty($item['file'])));
?>

<div class="content-header pc-hero">
    <div class="pc-hero-art">
        <?php if ($pcCover !== ''): ?>
            <img src="<?php echo htmlspecialchars($pcCover); ?>" alt="" loading="lazy">
        <?php else: ?>
            <div class="pc-hero-art-empty"><i class="fa-solid fa-podcast"></i></div>
        <?php endif; ?>
    </div>
    <div class="pc-hero-text">
        <span class="pc-hero-kicker">Podcast</span>
        <h1 class="pc-hero-title">鋒兄播客</h1>
        <p class="pc-hero-desc">個人播客庫，集數依建立時間排序，支援離線快取與行內編輯。</p>
        <div class="pc-hero-meta">
            <span class="pc-hero-owner"><i class="fa-solid fa-user"></i> 鋒兄</span>
            <span>·</span>
            <span><?php echo count($items); ?> 集</span>
            <span>·</span>
            <span><?php echo (int) $pcPlayable; ?> 集可播放</span>
            <span>·</span>
            <span><?php echo count($pcCategories); ?> 個分類</span>
        </div>
    </div>
</div>

<div class="content-body pc-shell">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <div class="action-buttons">
        <button class="btn btn-primary" onclick="handleAdd()" title="新增播客"><i class="fas fa-plus"></i></button>
        <a href="export.php?table=podcast&format=appwrite" class="btn btn-outline" title="欄位為 $id / $createdAt / $updatedAt">
            <i class="fa-solid fa-file-csv"></i> 匯出 Appwrite
        </a>
        <a href="export.php?table=podcast&format=laravel" class="btn btn-outline" title="欄位為 MySQL 原始名稱">
            <i class="fa-solid fa-file-csv"></i> 匯出 MySQL CSV
        </a>
        <a href="export_zip_podcast.php" class="btn btn-success">
            <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
        </a>
        <button type="button" class="btn" onclick="document.getElementById('zipImportPodcast').click()" title="匯入 Appwrite ZIP（含 CSV + 播客 + 封面）">
            <i class="fa-solid fa-file-zipper"></i> 匯入 ZIP
        </button>
        <input type="file" id="zipImportPodcast" accept=".zip" style="display: none;"
            onchange="previewAndImportZIP(this, 'podcast', 'import_zip_podcast.php', '播客')">
        <button type="button" class="btn btn-ghost" onclick="refreshPodcastCacheStats()" title="離線快取狀態">
            <i class="fa-solid fa-hard-drive"></i> <span id="podcastCacheStatsLabel">快取</span>
        </button>
    </div>

    <?php include 'includes/batch-delete.php'; ?>

    <div class="pc-controls">
        <button type="button" class="pc-bigplay" onclick="playFirstPodcast()" title="播放第一集"><i class="fa-solid fa-play"></i></button>
        <div class="pc-chips" id="pcChips">
            <button type="button" class="pc-chip is-active" data-cat="__all" onclick="filterPodcastCategory('__all', this)">全部</button>
            <?php foreach ($pcCategories as $cat): ?>
                <button type="button" class="pc-chip" data-cat="<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>"
                    onclick="filterPodcastCategory(this.dataset.cat, this)"><?php echo htmlspecialchars($cat); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="pc-viewtabs">
            <button type="button" class="pc-viewtab is-active" data-view="list" onclick="setPodcastView('list')"><i class="fa-solid fa-list"></i> 集數清單</button>
            <button type="button" class="pc-viewtab" data-view="grid" onclick="setPodcastView('grid')"><i class="fa-solid fa-table-cells-large"></i> 卡片牆</button>
        </div>
    </div>

    <div class="pc-listhead">
        <span>#</span><span>集數</span><span>播放</span>
    </div>

    <div class="card-grid podcast-library-grid pc-view-list" id="podcastLibrary" style="margin-top: 12px;">
        <div id="inlineAddCard" class="card inline-add-card">
            <div class="inline-edit inline-edit-always">
                <div class="form-group">
                    <label>名稱 *</label>
                    <input type="text" class="form-control inline-input" data-field="name">
                </div>
                <div class="form-group">
                    <label>檔案路徑</label>
                    <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入播客網址" oninput="updateInlinePodcastPreview(this)">
                    <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                        <input type="file" class="inline-podcast-file" accept="audio/*" style="display: none;" onchange="uploadInlinePodcast(this)">
                        <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳播客</button>
                    </div>
                    <div class="inline-podcast-preview" style="margin-top: 6px;"></div>
                </div>
                <div class="form-group">
                    <label>封面圖</label>
                    <input type="text" class="form-control inline-input" data-field="cover" placeholder="輸入封面圖網址" oninput="updateInlinePodcastCoverPreview(this)">
                    <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                        <input type="file" class="inline-cover-file" accept="image/*" style="display: none;" onchange="uploadInlinePodcastCover(this)">
                        <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳封面</button>
                        <div class="inline-podcast-cover-preview"></div>
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
            <?php $pcIndex = 0; ?>
            <?php foreach ($items as $item): ?>
                <?php $pcIndex++; $pcItemCat = trim((string) ($item['category'] ?? '')); if ($pcItemCat === '') { $pcItemCat = '未分類'; } ?>
                <div class="card pc-episode" data-cat="<?php echo htmlspecialchars($pcItemCat, ENT_QUOTES); ?>"
                    data-index="<?php echo $pcIndex; ?>"
                    data-id="<?php echo $item['id']; ?>"
                    data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                    data-file="<?php echo htmlspecialchars($item['file'] ?? '', ENT_QUOTES); ?>"
                    data-cover="<?php echo htmlspecialchars($item['cover'] ?? '', ENT_QUOTES); ?>"
                    data-category="<?php echo htmlspecialchars($item['category'] ?? '', ENT_QUOTES); ?>"
                    data-ref="<?php echo htmlspecialchars($item['ref'] ?? '', ENT_QUOTES); ?>"
                    data-note="<?php echo htmlspecialchars($item['note'] ?? '', ENT_QUOTES); ?>">
                    <div class="inline-view">
                        <input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo $item['id']; ?>"
                            onchange="toggleSelectItem(this)">
                        <div class="card-actions">
                            <span class="card-edit-btn" onclick="startInlineEdit('<?php echo $item['id']; ?>')"><i class="fas fa-pen"></i></span>
                            <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                        </div>
                        <div class="pc-cover-wrap">
                            <?php if ($item['cover']): ?>
                                <img src="<?php echo htmlspecialchars($item['cover']); ?>" class="podcast-cover-image" loading="lazy" alt="">
                            <?php else: ?>
                                <div class="podcast-cover-image pc-cover-empty"><i class="fa-solid fa-podcast"></i></div>
                            <?php endif; ?>
                            <span class="pc-episode-no"><?php echo $pcIndex; ?></span>
                        </div>
                        <h3 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p class="podcast-meta-line"><span class="pc-cat-chip"><?php echo htmlspecialchars($pcItemCat); ?></span><span class="pc-date"><?php echo !empty($item['created_at']) ? date('Y-m-d', strtotime($item['created_at'])) : '—'; ?></span></p>
                        <p class="podcast-note-preview"><?php echo htmlspecialchars($item['note'] ?? ''); ?></p>

                        <?php if ($item['file']): ?>
                            <div class="podcast-play-row" style="margin-top: 10px;">
                                <audio id="audio-<?php echo $item['id']; ?>" src="<?php echo htmlspecialchars($item['file']); ?>"
                                    preload="none"></audio>
                                <button class="btn btn-sm btn-success" onclick="togglePlay('<?php echo $item['id']; ?>')"
                                    id="playBtn-<?php echo $item['id']; ?>">
                                    <i class="fa-solid fa-play"></i> 播放
                                </button>
                                <button class="btn btn-sm btn-ghost podcast-cache-btn"
                                    data-cache-id="<?php echo htmlspecialchars($item['id']); ?>"
                                    onclick="cachePodcastOffline('<?php echo $item['id']; ?>')"
                                    title="離線快取（上限 500MB）">
                                    <i class="fa-solid fa-cloud-arrow-down"></i>
                                </button>
                                <span id="time-<?php echo $item['id']; ?>" class="podcast-time-label"
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
                            <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入播客網址" oninput="updateInlinePodcastPreview(this)">
                            <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                <input type="file" class="inline-podcast-file" accept="audio/*" style="display: none;" onchange="uploadInlinePodcast(this)">
                                <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳播客</button>
                            </div>
                            <div class="inline-podcast-preview" style="margin-top: 6px;"></div>
                        </div>
                        <div class="form-group">
                            <label>封面圖</label>
                            <input type="text" class="form-control inline-input" data-field="cover" placeholder="輸入封面圖網址" oninput="updateInlinePodcastCoverPreview(this)">
                            <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                <input type="file" class="inline-cover-file" accept="image/*" style="display: none;" onchange="uploadInlinePodcastCover(this)">
                                <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳封面</button>
                                <div class="inline-podcast-cover-preview"></div>
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
        if (fileInput) { fileInput.value = data.file || ''; updateInlinePodcastPreview(fileInput); }
        const coverInput = card.querySelector('[data-field="cover"]');
        if (coverInput) { coverInput.value = data.cover || ''; updateInlinePodcastCoverPreview(coverInput); }
        const categoryInput = card.querySelector('[data-field="category"]');
        if (categoryInput) categoryInput.value = data.category || '';
        const refInput = card.querySelector('[data-field="ref"]');
        if (refInput) refInput.value = data.ref || '';
        const noteInput = card.querySelector('[data-field="note"]');
        if (noteInput) noteInput.value = data.note || '';
    }

    function uploadInlinePodcast(fileInput) {
        if (!fileInput.files || !fileInput.files[0]) return;
        const file = fileInput.files[0];
        const formGroup = fileInput.closest('.form-group');
        const urlInput = formGroup.querySelector('[data-field="file"]');
        uploadFileWithProgress(file,
            function (res) {
                urlInput.value = res.file;
                updateInlinePodcastPreview(urlInput);
                const card = fileInput.closest('.inline-edit, .inline-edit-always');
                if (card) {
                    const nameInput = card.querySelector('[data-field="name"]');
                    if (nameInput && !nameInput.value) nameInput.value = res.filename || '';
                }
            },
            function (error) { alert('上傳失敗: ' + error); }
        );
        fileInput.value = '';
    }

    function updateInlinePodcastPreview(input) {
        const preview = input.closest('.form-group').querySelector('.inline-podcast-preview');
        if (!preview) return;
        const url = input.value.trim();
        preview.innerHTML = url
            ? `<audio src="${url}" controls style="width: 100%; margin-top: 4px;"></audio>`
            : '';
    }

    function uploadInlinePodcastCover(fileInput) {
        if (!fileInput.files || !fileInput.files[0]) return;
        const formGroup = fileInput.closest('.form-group');
        const urlInput = formGroup.querySelector('[data-field="cover"]');
        uploadFileWithProgress(fileInput.files[0],
            function (res) {
                urlInput.value = res.file;
                updateInlinePodcastCoverPreview(urlInput);
            },
            function (error) { alert('上傳失敗: ' + error); }
        );
        fileInput.value = '';
    }

    function updateInlinePodcastCoverPreview(input) {
        const preview = input.closest('.form-group').querySelector('.inline-podcast-cover-preview');
        if (!preview) return;
        const url = input.value.trim();
        preview.innerHTML = url
            ? `<img src="${url}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">`
            : '';
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

    async function resolvePodcastPlaySrc(id, fallbackSrc) {
        if (!window.FengbroMediaCache || !id) return fallbackSrc;
        try {
            const objectUrl = await window.FengbroMediaCache.getObjectUrl('podcast', id);
            return objectUrl || fallbackSrc;
        } catch (e) {
            return fallbackSrc;
        }
    }

    async function refreshPodcastCacheStats() {
        const label = document.getElementById('podcastCacheStatsLabel');
        if (!window.FengbroMediaCache) {
            if (label) label.textContent = '快取不可用';
            return;
        }
        try {
            const stats = await window.FengbroMediaCache.getStats('podcast');
            if (label) {
                label.textContent = window.FengbroMediaCache.formatBytes(stats.totalSize) + ' / 500MB · ' + stats.totalItems;
            }
            document.querySelectorAll('.podcast-cache-btn[data-cache-id]').forEach(async function (btn) {
                const id = btn.getAttribute('data-cache-id');
                const cached = await window.FengbroMediaCache.isCached('podcast', id);
                btn.classList.toggle('btn-success', cached);
                btn.innerHTML = cached
                    ? '<i class="fa-solid fa-check"></i>'
                    : '<i class="fa-solid fa-cloud-arrow-down"></i>';
            });
        } catch (e) {
            if (label) label.textContent = '快取';
        }
    }

    async function cachePodcastOffline(id) {
        const card = getCardById(id);
        const src = card ? (card.dataset.file || '') : '';
        const title = card ? (card.dataset.name || 'Podcast') : 'Podcast';
        if (!src) {
            alert('找不到可快取的播客檔案');
            return;
        }
        if (!window.FengbroMediaCache) {
            alert('瀏覽器不支援離線快取');
            return;
        }
        const btn = document.querySelector('.podcast-cache-btn[data-cache-id="' + id + '"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        }
        try {
            await window.FengbroMediaCache.cacheMedia('podcast', { id: id, title: title, url: src }, function (progress) {
                if (btn) btn.innerHTML = progress + '%';
            });
            await refreshPodcastCacheStats();
            alert('已快取到本機');
        } catch (err) {
            alert('快取失敗：' + (err.message || err));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down"></i>';
            }
        }
    }

    // 播放/暫停切換
    async function togglePlay(id) {
        const card = getCardById(id);
        if (card && window.FengbroMedia) {
            const src = card.dataset.file || '';
            const title = card.dataset.name || 'Podcast';
            const playSrc = await resolvePodcastPlaySrc(id, src);
            const state = window.FengbroMedia.getState();
            const isSame = state && state.kind === 'audio' && (state.src === src || state.src === playSrc || state.id === id);

            if (isSame) {
                window.FengbroMedia.toggle();
            } else {
                window.FengbroMedia.playAudio({
                    src: playSrc,
                    title: title,
                    id: id,
                    mediaType: 'podcast',
                    poster: card.dataset.cover || '',
                    meta: (card.dataset.category || 'Podcast') + (playSrc !== src ? ' · Offline' : ''),
                    downloadName: (title || 'podcast').replace(/[\\/:*?"<>|]+/g, '_') + '.mp3'
                });
            }

            document.querySelectorAll('[id^="playBtn-"]').forEach(function (button) {
                button.innerHTML = '<i class="fa-solid fa-play"></i> 播放';
            });
            const currentBtn = document.getElementById('playBtn-' + id);
            if (currentBtn) {
                currentBtn.innerHTML = isSame && state && !state.playing
                    ? '<i class="fa-solid fa-play"></i> 播放'
                    : '<i class="fa-solid fa-pause"></i> 暫停';
            }
            return;
        }

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

    window.batchCacheSelectedItems = async function (ids) {
        if (!window.FengbroMediaCache) throw new Error('瀏覽器不支援離線快取');
        if (!ids || !ids.length) return;
        let ok = 0, fail = 0;
        for (let i = 0; i < ids.length; i++) {
            const id = ids[i];
            const card = getCardById(id);
            const src = card ? (card.dataset.file || '') : '';
            const title = card ? (card.dataset.name || id) : id;
            if (!src) { fail++; continue; }
            try {
                await window.FengbroMediaCache.cacheMedia('podcast', {
                    id: id,
                    title: title,
                    url: src
                });
                ok++;
            } catch (e) {
                fail++;
            }
        }
        await refreshPodcastCacheStats();
        alert('批次快取完成：成功 ' + ok + ' 集' + (fail ? '，失敗 ' + fail + ' 集' : ''));
    };

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
        refreshPodcastCacheStats();
        if (typeof enableBatchCacheButton === 'function') {
            enableBatchCacheButton(true);
        }
    });

    function deleteItem(id) {
        deleteInlineItem(id, { table: TABLE });
    }
    /* ---------- Spotify Podcast 版面互動 ---------- */
    const PODCAST_VIEW_STORAGE_KEY = 'fengbro_podcast_view';

    function setPodcastView(view) {
        const mode = view === 'grid' ? 'grid' : 'list';
        const lib = document.getElementById('podcastLibrary');
        if (lib) {
            lib.classList.toggle('pc-view-list', mode === 'list');
            lib.classList.toggle('pc-view-grid', mode === 'grid');
        }
        const head = document.querySelector('.pc-listhead');
        if (head) head.style.display = mode === 'list' ? 'grid' : 'none';
        document.querySelectorAll('.pc-viewtab').forEach(function (tab) {
            tab.classList.toggle('is-active', tab.dataset.view === mode);
        });
        try { localStorage.setItem(PODCAST_VIEW_STORAGE_KEY, mode); } catch (e) {}
    }

    function filterPodcastCategory(cat, btn) {
        const value = cat || '__all';
        document.querySelectorAll('#pcChips .pc-chip').forEach(function (el) {
            el.classList.toggle('is-active', el === btn);
        });
        document.querySelectorAll('.card.pc-episode').forEach(function (card) {
            const match = value === '__all' || card.dataset.cat === value;
            card.classList.toggle('pc-hidden', !match);
        });
    }

    function playFirstPodcast() {
        const card = document.querySelector('.card.pc-episode:not(.pc-hidden)');
        if (!card) { alert('目前沒有可播放的集數'); return; }
        const btn = card.querySelector('[id^="playBtn-"]');
        if (btn) { btn.click(); return; }
        alert('這一集沒有音檔');
    }

    document.addEventListener('DOMContentLoaded', function () {
        let saved = 'list';
        try { saved = localStorage.getItem(PODCAST_VIEW_STORAGE_KEY) || 'list'; } catch (e) {}
        setPodcastView(saved);
    });

</script>

<style>
    .podcast-library-grid > .card:not(.inline-add-card) {
        position: relative;
    }

    .podcast-library-grid .card {
        overflow: hidden;
    }

    .podcast-cover-image {
        border-radius: 18px !important;
        height: 180px !important;
        object-fit: cover;
    }

    .podcast-meta-line {
        margin-bottom: 6px;
    }

    .podcast-note-preview {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.6;
        min-height: 3.2em;
    }

    .podcast-play-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        padding-top: 2px;
    }

    .podcast-play-row .btn {
        min-height: 42px;
    }

    .podcast-time-label {
        color: #7a756c !important;
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .podcast-library-grid > .card:not(.inline-add-card) {
            padding-top: 56px;
        }

        .podcast-library-grid .card-actions {
            top: 16px;
            right: 16px;
        }

        .podcast-play-row .btn {
            flex: 1 1 160px;
            justify-content: center;
        }

        .podcast-time-label {
            width: 100%;
            margin-left: 0 !important;
        }
    }

    @media (max-width: 560px) {
        .podcast-cover-image {
            height: 200px !important;
        }

        .podcast-play-row {
            display: grid;
            grid-template-columns: 1fr;
        }

        .podcast-time-label {
            margin-left: 0;
        }
    }
/* ==========================================================
   鋒兄播客 · Spotify Podcast 版面
   ========================================================== */
body[data-page="podcast"] {
    --pc-green: #1db954;
    --pc-bg: #121212;
    --pc-elev: #181818;
    --pc-elev-hi: #2a2a2a;
    --pc-text: #ffffff;
    --pc-sub: #b3b3b3;
}

.pc-hero {
    display: flex;
    align-items: flex-end;
    gap: 26px;
    flex-wrap: wrap;
    padding: 34px 30px 26px;
    border-radius: 16px 16px 0 0;
    background: linear-gradient(160deg, #4a3a6b 0%, #2b2340 46%, var(--pc-bg) 100%);
    color: var(--pc-text);
    margin-bottom: 0;
}

.pc-hero-art {
    width: 190px;
    height: 190px;
    flex-shrink: 0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.55);
    background: #333;
}

.pc-hero-art img { width: 100%; height: 100%; object-fit: cover; display: block; }

.pc-hero-art-empty {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3.2rem;
    color: rgba(255, 255, 255, 0.34);
    background: linear-gradient(135deg, #3d3550, #221d2e);
}

.pc-hero-text { min-width: 240px; flex: 1; }

.pc-hero-kicker {
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.86);
}

.pc-hero-title {
    margin: 6px 0 12px;
    font-size: clamp(2rem, 5vw, 3.9rem);
    line-height: 1.05;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: #fff;
}

.pc-hero-desc {
    margin: 0 0 12px;
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.92rem;
    line-height: 1.55;
    max-width: 60ch;
}

.pc-hero-meta {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    align-items: center;
    font-size: 0.86rem;
    color: rgba(255, 255, 255, 0.82);
}

.pc-hero-owner { font-weight: 700; color: #fff; }

.content-header.pc-hero .pc-hero-title,
.content-header.pc-hero h1 { color: #fff; }

body[data-page="podcast"] .content-body.pc-shell {
    background: linear-gradient(180deg, #241f33 0%, var(--pc-bg) 240px);
    color: var(--pc-text);
    border-radius: 0 0 16px 16px;
    padding: 22px 26px 34px;
}

body[data-page="podcast"] .pc-shell .btn {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.16);
}

body[data-page="podcast"] .pc-shell .btn:hover { background: rgba(255, 255, 255, 0.2); }
body[data-page="podcast"] .pc-shell .btn-primary,
body[data-page="podcast"] .pc-shell .btn-success { background: var(--pc-green); border-color: var(--pc-green); color: #06180d; font-weight: 700; }

body[data-page="podcast"] .pc-shell .form-control {
    background: #2a2a2a;
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.16);
}

body[data-page="podcast"] .pc-shell .form-control::placeholder { color: rgba(255, 255, 255, 0.42); }
body[data-page="podcast"] .pc-shell label { color: var(--pc-sub); }
body[data-page="podcast"] .pc-shell .select-checkbox { accent-color: var(--pc-green); }

.pc-controls {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.pc-bigplay {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    border: none;
    background: var(--pc-green);
    color: #06180d;
    font-size: 1.25rem;
    cursor: pointer;
    flex-shrink: 0;
    padding-left: 3px;
    box-shadow: 0 8px 20px rgba(29, 185, 84, 0.34);
    transition: transform 0.16s ease, background 0.16s ease;
}

.pc-bigplay:hover { transform: scale(1.06); background: #1ed760; }

.pc-chips { display: flex; gap: 8px; flex-wrap: wrap; flex: 1; min-width: 0; }

.pc-chip {
    border: none;
    border-radius: 999px;
    padding: 7px 15px;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.16s ease, color 0.16s ease;
}

.pc-chip:hover { background: rgba(255, 255, 255, 0.2); }
.pc-chip.is-active { background: #fff; color: #121212; font-weight: 700; }

.pc-viewtabs { display: flex; gap: 4px; }

.pc-viewtab {
    border: none;
    background: transparent;
    color: var(--pc-sub);
    padding: 7px 13px;
    border-radius: 999px;
    font-size: 0.85rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.pc-viewtab:hover { color: #fff; }
.pc-viewtab.is-active { color: #fff; background: rgba(255, 255, 255, 0.12); font-weight: 700; }

.pc-listhead {
    display: grid;
    grid-template-columns: 28px minmax(0, 1fr) 220px;
    gap: 14px;
    padding: 16px 12px 8px;
    margin-top: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 0.74rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--pc-sub);
}

/* ---------- 集數列 ---------- */
body[data-page="podcast"] .card.pc-episode {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    border-radius: 6px !important;
    padding: 10px 12px !important;
    color: var(--pc-text);
    transition: background 0.18s ease;
}

body[data-page="podcast"] .card.pc-episode:hover { background: rgba(255, 255, 255, 0.07) !important; }

.pc-cover-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: 6px;
    overflow: hidden;
    background: #2a2a2a;
}

.podcast-cover-image {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
    display: block;
    margin: 0 !important;
    border-radius: 0 !important;
}

.pc-cover-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: rgba(255, 255, 255, 0.32);
    background: linear-gradient(135deg, #3d3550, #221d2e);
}

.pc-episode-no {
    position: absolute;
    left: 4px;
    top: 4px;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.66);
    color: #fff;
    font-size: 0.68rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

body[data-page="podcast"] .card.pc-episode .card-title {
    margin: 0;
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.podcast-meta-line {
    margin: 4px 0 0 !important;
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
    color: var(--pc-sub) !important;
    font-size: 0.8rem !important;
}

.pc-cat-chip {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    font-size: 0.72rem;
}

.pc-date { font-variant-numeric: tabular-nums; }

.podcast-note-preview {
    margin: 5px 0 0 !important;
    color: var(--pc-sub) !important;
    font-size: 0.84rem !important;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.podcast-play-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px !important;
}

.podcast-time-label { color: var(--pc-sub) !important; font-variant-numeric: tabular-nums; }

body[data-page="podcast"] .pc-episode .card-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

body[data-page="podcast"] .pc-episode .card-edit-btn,
body[data-page="podcast"] .pc-episode .card-delete-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}

/* ---------- 集數清單版面 ---------- */
.podcast-library-grid.pc-view-list {
    display: flex !important;
    flex-direction: column;
    gap: 2px;
}

.pc-view-list .pc-episode .inline-view {
    display: grid;
    grid-template-columns: 24px 76px minmax(0, 1fr) auto auto;
    grid-template-areas:
        "check cover title play actions"
        "check cover meta  play actions"
        "check cover note  play actions";
    align-items: center;
    column-gap: 14px;
    row-gap: 0;
}

.pc-view-list .pc-episode .select-checkbox { grid-area: check; }
.pc-view-list .pc-episode .card-actions { grid-area: actions; }
.pc-view-list .pc-cover-wrap { grid-area: cover; width: 76px; }
.pc-view-list .pc-episode .card-title { grid-area: title; }
.pc-view-list .podcast-meta-line { grid-area: meta; }
.pc-view-list .podcast-note-preview { grid-area: note; }
.pc-view-list .podcast-play-row { grid-area: play; margin-top: 0 !important; }
.pc-view-list .pc-episode .inline-edit { grid-column: 1 / -1; }

/* ---------- 卡片牆版面 ---------- */
.podcast-library-grid.pc-view-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(184px, 1fr));
    gap: 20px 18px;
}

body[data-page="podcast"] .pc-view-grid .card.pc-episode {
    background: var(--pc-elev) !important;
    padding: 15px !important;
    border-radius: 8px !important;
}

body[data-page="podcast"] .pc-view-grid .card.pc-episode:hover { background: var(--pc-elev-hi) !important; }
.pc-view-grid .pc-cover-wrap { margin-bottom: 13px; }
.pc-view-grid .pc-episode .card-title { -webkit-line-clamp: 2; }

body[data-page="podcast"] #inlineAddCard {
    background: var(--pc-elev);
    color: var(--pc-text);
    grid-column: 1 / -1;
}

.pc-episode.pc-hidden { display: none !important; }

/* ---------- RWD ---------- */
@media (max-width: 900px) {
    .pc-hero { padding: 24px 18px 20px; gap: 18px; }
    .pc-hero-art { width: 128px; height: 128px; }
    body[data-page="podcast"] .content-body.pc-shell { padding: 18px 14px 28px; }
    .pc-listhead { display: none; }
    .pc-view-list .pc-episode .inline-view {
        grid-template-columns: 22px 64px minmax(0, 1fr);
        grid-template-areas:
            "check cover title"
            "check cover meta"
            "note  note  note"
            "play  play  actions";
        row-gap: 6px;
    }
    .pc-view-list .pc-cover-wrap { width: 64px; }
    .podcast-library-grid.pc-view-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 14px 12px; }
}

</style>
