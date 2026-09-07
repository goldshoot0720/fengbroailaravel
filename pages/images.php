<?php
$pageTitle = '圖片管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM image ORDER BY created_at DESC")->fetchAll();

$imgCategories = [];
foreach ($items as $item) {
    $cat = trim((string) ($item['category'] ?? ''));
    if ($cat === '') { $cat = '未分類'; }
    if (!isset($imgCategories[$cat])) { $imgCategories[$cat] = ['count' => 0, 'cover' => '']; }
    $imgCategories[$cat]['count']++;
    if ($imgCategories[$cat]['cover'] === '') {
        $imgCategories[$cat]['cover'] = (string) (!empty($item['cover']) ? $item['cover'] : ($item['file'] ?? ''));
    }
}
ksort($imgCategories);
$imgWithFile = count(array_filter($items, fn($item) => !empty($item['file']) || !empty($item['cover'])));
?>

<div class="content-header ig-header">
    <div class="ig-profile">
        <div class="ig-avatar-ring">
            <div class="ig-avatar"><i class="fa-solid fa-camera-retro"></i></div>
        </div>
        <div class="ig-profile-main">
            <div class="ig-profile-top">
                <h1 class="ig-handle">鋒兄圖片</h1>
                <span class="ig-username">@fengbro.images</span>
            </div>
            <div class="ig-stats">
                <span><strong><?php echo count($items); ?></strong> 貼文</span>
                <span><strong><?php echo count($imgCategories); ?></strong> 分類</span>
                <span><strong><?php echo $imgWithFile; ?></strong> 有圖檔</span>
            </div>
            <p class="ig-bio">個人相簿牆 · 支援分類限動篩選、方格牆與貼文流兩種檢視，點圖可放大檢視。</p>
        </div>
    </div>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <div class="action-buttons ig-toolbar" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 15px;">
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

    <?php if (!empty($imgCategories)): ?>
    <div class="ig-stories" id="igStories">
        <button type="button" class="ig-story is-active" data-cat="__all" onclick="filterImageCategory('__all', this)">
            <span class="ig-story-ring"><span class="ig-story-thumb ig-story-all"><i class="fa-solid fa-layer-group"></i></span></span>
            <span class="ig-story-label">全部</span>
        </button>
        <?php foreach ($imgCategories as $catName => $catInfo): ?>
            <button type="button" class="ig-story" data-cat="<?php echo htmlspecialchars($catName, ENT_QUOTES); ?>"
                onclick="filterImageCategory('<?php echo htmlspecialchars(addslashes($catName), ENT_QUOTES); ?>', this)">
                <span class="ig-story-ring">
                    <?php if (!empty($catInfo['cover'])): ?>
                        <img class="ig-story-thumb" loading="lazy" alt="" src="<?php echo htmlspecialchars($catInfo['cover']); ?>">
                    <?php else: ?>
                        <span class="ig-story-thumb ig-story-all"><i class="fa-solid fa-image"></i></span>
                    <?php endif; ?>
                </span>
                <span class="ig-story-label"><?php echo htmlspecialchars($catName); ?></span>
                <span class="ig-story-count"><?php echo (int) $catInfo['count']; ?></span>
            </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="media-toolbar ig-viewbar">
        <div class="ig-viewbar-hint"><i class="fa-solid fa-hashtag"></i> <span id="igVisibleCount"><?php echo count($items); ?></span> 張顯示中</div>
        <div class="view-switch">
            <button type="button" class="view-btn" data-media-view-btn="grid" onclick="setMediaView('images', 'grid')"><i class="fa-solid fa-table-cells"></i> 方格牆</button>
            <button type="button" class="view-btn" data-media-view-btn="list" onclick="setMediaView('images', 'list')"><i class="fa-regular fa-square"></i> 貼文流</button>
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
                <?php $itemCat = trim((string) ($item['category'] ?? '')); if ($itemCat === '') { $itemCat = '未分類'; } ?>
                <div class="card ig-post" data-cat="<?php echo htmlspecialchars($itemCat, ENT_QUOTES); ?>"
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
                        <div class="ig-media">
                            <?php if (!empty($imageSrc)): ?>
                                <img class="image-thumb ig-img"
                                    data-image-id="<?php echo htmlspecialchars($item['id']); ?>"
                                    data-src="<?php echo htmlspecialchars($imageSrc); ?>"
                                    src="<?php echo htmlspecialchars($imageSrc); ?>"
                                    loading="lazy"
                                    alt="<?php echo htmlspecialchars($item['name'] ?? ''); ?>"
                                    onclick="openImageLightbox('<?php echo htmlspecialchars($item['id']); ?>', '<?php echo htmlspecialchars($imageSrc, ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($item['name'] ?? '')); ?>')">
                            <?php else: ?>
                                <div class="ig-img ig-img-empty"><i class="fa-regular fa-image"></i></div>
                            <?php endif; ?>
                            <div class="ig-overlay">
                                <span class="ig-overlay-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                <span class="ig-overlay-cat"><i class="fa-solid fa-hashtag"></i><?php echo htmlspecialchars($itemCat); ?></span>
                            </div>
                        </div>
                        <div class="ig-caption">
                            <div class="ig-caption-head">
                                <span class="ig-mini-avatar"><i class="fa-solid fa-camera-retro"></i></span>
                                <h3 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                            </div>
                            <span class="ig-cat-chip">#<?php echo htmlspecialchars($itemCat); ?></span>
                            <?php if (trim((string) ($item['note'] ?? '')) !== ''): ?>
                                <p class="ig-note"><?php echo htmlspecialchars($item['note']); ?></p>
                            <?php endif; ?>
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


<style>
    /* ==========================================================
       鋒兄圖片 · Instagram 版面
       ========================================================== */
    .ig-header {
        border-bottom: 1px solid var(--border-color, rgba(0, 0, 0, 0.08));
        padding-bottom: 22px;
        margin-bottom: 18px;
    }

    .ig-profile {
        display: flex;
        gap: 28px;
        align-items: center;
        flex-wrap: wrap;
    }

    .ig-avatar-ring {
        width: 122px;
        height: 122px;
        border-radius: 50%;
        padding: 4px;
        flex-shrink: 0;
        background: conic-gradient(from 210deg, #feda75, #fa7e1e, #d62976, #962fbf, #4f5bd5, #feda75);
    }

    .ig-avatar {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: var(--card-bg, #fff);
        border: 3px solid var(--body-bg, #faf9f5);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.4rem;
        color: #d62976;
    }

    .ig-profile-main { min-width: 240px; flex: 1; }

    .ig-profile-top {
        display: flex;
        align-items: baseline;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    .ig-handle { margin: 0; font-size: 1.6rem; font-weight: 700; }

    .ig-username {
        color: var(--muted-text, #8e8e8e);
        font-size: 0.95rem;
        letter-spacing: 0.02em;
    }

    .ig-stats {
        display: flex;
        gap: 26px;
        flex-wrap: wrap;
        margin-bottom: 10px;
        font-size: 0.95rem;
    }

    .ig-stats strong { font-weight: 700; }

    .ig-bio {
        margin: 0;
        color: var(--muted-text, #737373);
        font-size: 0.9rem;
        line-height: 1.6;
        max-width: 62ch;
    }

    /* ---------- 限動列（分類） ---------- */
    .ig-stories {
        display: flex;
        gap: 20px;
        overflow-x: auto;
        padding: 4px 2px 16px;
        margin-bottom: 6px;
        scrollbar-width: thin;
        border-bottom: 1px solid var(--border-color, rgba(0, 0, 0, 0.08));
    }

    .ig-story {
        border: none;
        background: transparent;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        width: 82px;
        flex: 0 0 auto;
        padding: 0;
        position: relative;
    }

    .ig-story-ring {
        width: 68px;
        height: 68px;
        border-radius: 50%;
        padding: 3px;
        background: linear-gradient(45deg, #d9d9d9, #c7c7c7);
        display: block;
        transition: transform 0.18s ease;
    }

    .ig-story:hover .ig-story-ring { transform: scale(1.06); }

    .ig-story.is-active .ig-story-ring {
        background: conic-gradient(from 210deg, #feda75, #fa7e1e, #d62976, #962fbf, #4f5bd5, #feda75);
    }

    .ig-story-thumb {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        display: block;
        border: 2px solid var(--body-bg, #faf9f5);
        background: var(--card-bg, #fff);
    }

    .ig-story-all {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #d62976;
        font-size: 1.2rem;
    }

    .ig-story-label {
        font-size: 0.76rem;
        max-width: 82px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: var(--text-color, #333);
    }

    .ig-story.is-active .ig-story-label { font-weight: 700; }

    .ig-story-count {
        position: absolute;
        top: 0;
        right: 4px;
        min-width: 20px;
        padding: 1px 5px;
        border-radius: 999px;
        background: #d62976;
        color: #fff;
        font-size: 0.66rem;
        font-weight: 700;
    }

    .ig-viewbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 14px;
    }

    .ig-viewbar-hint {
        font-size: 0.85rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--muted-text, #8e8e8e);
        font-weight: 600;
    }

    /* ---------- 貼文卡 ---------- */
    .media-browser-images .card-grid {
        margin-top: 16px !important;
    }

    .media-browser-images .card.ig-post {
        position: relative;
        padding: 0 !important;
        overflow: hidden;
        border-radius: 4px;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, rgba(0, 0, 0, 0.07));
        box-shadow: none;
    }

    .ig-media {
        position: relative;
        display: block;
        width: 100%;
        aspect-ratio: 1 / 1;
        overflow: hidden;
        background: #efefef;
    }

    .ig-img {
        width: 100% !important;
        height: 100% !important;
        max-height: none !important;
        object-fit: cover;
        display: block;
        margin: 0 !important;
        border-radius: 0 !important;
        cursor: zoom-in;
        background: #efefef;
        transition: transform 0.32s ease;
    }

    .ig-post:hover .ig-img { transform: scale(1.04); }

    .ig-img-empty {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #b9b9b9;
        font-size: 2rem;
    }

    .ig-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px;
        text-align: center;
        color: #fff;
        background: rgba(0, 0, 0, 0.42);
        opacity: 0;
        transition: opacity 0.22s ease;
        pointer-events: none;
    }

    .ig-post:hover .ig-overlay { opacity: 1; }

    .ig-overlay-name {
        font-weight: 700;
        font-size: 0.98rem;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .ig-overlay-cat {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.78rem;
        opacity: 0.86;
    }

    .ig-caption { padding: 12px 14px 14px; }

    .ig-caption-head {
        display: flex;
        align-items: center;
        gap: 9px;
        margin-bottom: 6px;
    }

    .ig-mini-avatar {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        color: #fff;
        background: linear-gradient(45deg, #fa7e1e, #d62976, #962fbf);
    }

    .ig-caption .card-title {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .ig-cat-chip {
        display: inline-block;
        font-size: 0.78rem;
        color: #0095f6;
        font-weight: 600;
    }

    .ig-note {
        margin: 6px 0 0;
        font-size: 0.84rem;
        line-height: 1.55;
        color: var(--muted-text, #737373);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .media-browser-images .ig-post .card-header {
        position: absolute;
        top: 8px;
        left: 8px;
        right: 8px;
        z-index: 4;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .media-browser-images .ig-post:hover .card-header,
    .media-browser-images .ig-post:focus-within .card-header,
    .media-browser-images .ig-post.is-selected .card-header { opacity: 1; }

    .media-browser-images .ig-post .card-edit-btn,
    .media-browser-images .ig-post .card-delete-btn {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.55);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
    }

    .media-browser-images .ig-post .select-checkbox {
        width: 18px;
        height: 18px;
        accent-color: #d62976;
    }

    /* ---------- 方格牆 ---------- */
    .media-browser-images.media-view-grid .card-grid {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 6px;
    }

    .media-browser-images.media-view-grid .ig-post { border-radius: 2px; }
    .media-browser-images.media-view-grid .ig-caption { display: none; }

    /* ---------- 貼文流 ---------- */
    .media-browser-images.media-view-list .card-grid {
        display: block !important;
        max-width: 512px;
        margin: 16px auto 0 !important;
    }

    .media-browser-images.media-view-list .card-grid > .card:not(.inline-add-card) {
        display: block !important;
        grid-template-columns: none !important;
        margin-bottom: 26px;
        border-radius: 10px;
    }

    .media-browser-images.media-view-list .card-grid > .card:not(.inline-add-card) .inline-view {
        display: block !important;
    }

    .media-browser-images.media-view-list .card-grid > .card:not(.inline-add-card) img {
        width: 100% !important;
        max-height: none !important;
        margin-bottom: 0 !important;
    }

    .media-browser-images.media-view-list .ig-media { aspect-ratio: 4 / 5; }
    .media-browser-images.media-view-list .ig-overlay { display: none; }
    .media-browser-images.media-view-list .ig-note { -webkit-line-clamp: 4; }
    .media-browser-images.media-view-list .ig-post .card-header { opacity: 1; }

    .media-browser-images.media-view-grid .card-grid > #inlineAddCard {
        grid-column: 1 / -1;
        padding: 18px !important;
        border-radius: 10px;
    }

    .media-browser-images .card-grid > #inlineAddCard { background: var(--card-bg, #fff); }

    .ig-post.ig-hidden { display: none !important; }

    /* ---------- RWD ---------- */
    @media (max-width: 768px) {
        .ig-profile { gap: 18px; }
        .ig-avatar-ring { width: 86px; height: 86px; }
        .ig-avatar { font-size: 1.7rem; }
        .ig-handle { font-size: 1.25rem; }
        .ig-stats { gap: 16px; font-size: 0.85rem; }
        .ig-story { width: 68px; }
        .ig-story-ring { width: 58px; height: 58px; }
        .media-browser-images.media-view-grid .card-grid { gap: 3px; }
        .media-browser-images .ig-post .card-header { opacity: 1; }
    }
</style>

<script>
const TABLE = 'image';
let currentImageCategory = '__all';

function filterImageCategory(cat, btn) {
    currentImageCategory = cat || '__all';
    document.querySelectorAll('#igStories .ig-story').forEach(function (el) {
        el.classList.toggle('is-active', el === btn);
    });
    let shown = 0;
    document.querySelectorAll('.card.ig-post').forEach(function (card) {
        const match = currentImageCategory === '__all' || card.dataset.cat === currentImageCategory;
        card.classList.toggle('ig-hidden', !match);
        if (match) shown++;
    });
    const counter = document.getElementById('igVisibleCount');
    if (counter) counter.textContent = shown;
}

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
