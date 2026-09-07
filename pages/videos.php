<?php
$pageTitle = '影片管理';
$pdo = getConnection();
// 使用 commondocument 表來存放影片
$items = $pdo->query("SELECT * FROM video ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄影片 <span
            style="font-size:0.55em;background:#c1554a;color:#fff;padding:3px 10px;border-radius:20px;vertical-align:middle;font-weight:500;"><?php echo count($items); ?></span>
    </h1>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <div class="action-buttons-bar">
        <button class="btn btn-primary" onclick="handleAdd()" title="新增影片"><i class="fas fa-plus"></i></button>
        <button type="button" class="btn" onclick="document.getElementById('multiVideoFiles').click()">
            <i class="fa-solid fa-photo-film"></i> 多影片上傳
        </button>
        <input type="file" id="multiVideoFiles" accept="video/*" multiple style="display: none;" onchange="uploadMultipleVideos(this.files)">
        <a href="export_zip_video.php" class="btn btn-success">
            <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
        </a>
        <button type="button" class="btn" onclick="document.getElementById('importZipFile').click()">
            <i class="fa-solid fa-file-zipper"></i> 匯入 ZIP
        </button>
        <input type="file" id="importZipFile" accept=".zip" style="display: none;" onchange="importZIP(this)">
        <div id="videoInterfaceSwitch" class="video-interface-switch" role="tablist" aria-label="影片介面風格">
            <button type="button" role="tab" class="video-mode-btn active" data-mode="youtube" onclick="setVideoInterface('youtube')"><i class="fa-brands fa-youtube"></i><span>YouTube</span></button>
            <button type="button" role="tab" class="video-mode-btn" data-mode="bilibili" onclick="setVideoInterface('bilibili')"><i class="fa-brands fa-bilibili"></i><span>Bilibili</span></button>
            <button type="button" role="tab" class="video-mode-btn" data-mode="netflix" onclick="setVideoInterface('netflix')"><i class="fa-solid fa-clapperboard"></i><span>Netflix</span></button>
        </div>
        <button type="button" class="btn btn-ghost" onclick="refreshVideoCacheStats()" title="離線快取狀態">
            <i class="fa-solid fa-hard-drive"></i> <span id="videoCacheStatsLabel">快取</span>
        </button>
    </div>
    <div id="videoCacheBanner" class="video-cache-banner" style="display:none;margin:10px 0 0;padding:10px 14px;border-radius:12px;background:var(--table-header-bg);color:var(--muted-text);font-size:0.9rem;"></div>
    <?php include 'includes/batch-delete.php'; ?>

    <div id="videoExperience" class="video-experience video-experience-youtube media-browser media-browser-video" data-media-scope="videos">
        <?php $featured = $items[0] ?? null; ?>
        <div class="video-billboard" id="videoBillboard"<?php if ($featured && !empty($featured['cover'])): ?> style="--vb-image:url('<?php echo htmlspecialchars($featured['cover'], ENT_QUOTES); ?>');"<?php endif; ?>>
            <div class="video-billboard-scrim"></div>
            <div class="video-billboard-inner">
                <div class="video-billboard-badge"><span>F</span> 鋒兄原創影片</div>
                <h2 class="video-billboard-title"><?php echo htmlspecialchars($featured['name'] ?? '片庫還是空的'); ?></h2>
                <p class="video-billboard-desc"><?php echo htmlspecialchars($featured['note'] ?? '') !== '' ? htmlspecialchars($featured['note']) : '新增影片後，最新一部會出現在這裡作為主打影片。'; ?></p>
                <div class="video-billboard-actions">
                    <?php if ($featured && !empty($featured['file'])): ?>
                        <button type="button" class="vb-btn vb-btn-play"
                            onclick="playVideo('<?php echo $featured['id']; ?>', '<?php echo htmlspecialchars($featured['file']); ?>', '<?php echo htmlspecialchars(addslashes($featured['name'])); ?>')">
                            <i class="fa-solid fa-play"></i> 播放
                        </button>
                    <?php endif; ?>
                    <button type="button" class="vb-btn vb-btn-info" onclick="document.getElementById('videoLibraryRail').scrollIntoView({behavior:'smooth',block:'start'})">
                        <i class="fa-solid fa-circle-info"></i> 瀑布式片庫
                    </button>
                </div>
                <div class="video-billboard-meta">
                    <span><?php echo count($items); ?> 部影片</span>
                    <span><?php echo count(array_filter($items, fn($item) => !empty($item['cover']))); ?> 部已有封面</span>
                    <span><?php echo $featured && !empty($featured['created_at']) ? date('Y', strtotime($featured['created_at'])) : date('Y'); ?></span>
                </div>
            </div>
        </div>

        <div class="video-hero">
            <div>
                <div class="video-hero-kicker">鋒兄影片</div>
                <h2 id="videoExperienceTitle">Theater feed, clean controls, playlist rhythm.</h2>
                <p id="videoExperienceDescription">切成 YouTube 介面時，列表偏向縮圖與標題優先；切成 Bilibili 介面時，資訊層次會更密集。</p>
            </div>
            <div class="video-hero-stats">
                <div class="video-hero-stat">
                    <strong><?php echo count($items); ?></strong>
                    <span>影片數量</span>
                </div>
                <div class="video-hero-stat">
                    <strong><?php echo count(array_filter($items, fn($item) => !empty($item['cover']))); ?></strong>
                    <span>已有封面</span>
                </div>
            </div>
        </div>

    <div class="video-rail-head" id="videoLibraryRail">
        <h3>最近新增</h3>
        <span class="video-rail-count"><?php echo count($items); ?> 部</span>
    </div>

    <div class="video-list" style="margin-top: 20px;">
        <div id="inlineAddCard" class="video-item card inline-add-card"
            style="background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: relative;">
            <div class="inline-edit inline-edit-always">
                <div class="form-group">
                    <label>名稱 *</label>
                    <input type="text" class="form-control inline-input" data-field="name">
                </div>
                <div class="form-group">
                    <label>檔案路徑</label>
                    <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入影片網址"
                        oninput="updateInlineVideoPreview(this)">
                    <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                        <input type="file" class="inline-video-file" accept="video/*" style="display: none;"
                            onchange="uploadInlineVideo(this)">
                        <button type="button" class="btn" onclick="this.previousElementSibling.click()"
                            style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳影片</button>
                    </div>
                    <div class="inline-video-preview" style="margin-top: 6px;"></div>
                </div>
                <div class="form-group">
                    <label>封面圖</label>
                    <input type="text" class="form-control inline-input" data-field="cover" placeholder="輸入封面圖網址"
                        oninput="updateInlineCoverPreview(this)">
                    <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                        <input type="file" class="inline-cover-file" accept="image/*" style="display: none;"
                            onchange="uploadInlineCover(this)">
                        <button type="button" class="btn" onclick="this.previousElementSibling.click()"
                            style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳封面</button>
                    </div>
                    <div class="inline-cover-preview" style="margin-top: 6px;"></div>
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
            <div class="card">
                <p style="text-align: center; color: #999;">暫無影片</p>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="video-item card"
                    style="background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: relative;"
                    data-id="<?php echo $item['id']; ?>"
                    data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                    data-file="<?php echo htmlspecialchars($item['file'] ?? '', ENT_QUOTES); ?>"
                    data-cover="<?php echo htmlspecialchars($item['cover'] ?? '', ENT_QUOTES); ?>"
                    data-ref="<?php echo htmlspecialchars($item['ref'] ?? '', ENT_QUOTES); ?>"
                    data-note="<?php echo htmlspecialchars($item['note'] ?? '', ENT_QUOTES); ?>"
                    data-created="<?php echo htmlspecialchars($item['created_at'] ?? '', ENT_QUOTES); ?>">
                    <div class="inline-view">
                        <div class="card-header">
                            <input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo $item['id']; ?>"
                                onchange="toggleSelectItem(this)">
                            <div class="card-actions">
                                <span class="card-edit-btn" onclick="startInlineEdit('<?php echo $item['id']; ?>')"><i
                                        class="fas fa-pen"></i></span>
                                <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                            </div>
                        </div>
                        <div class="video-summary" style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="video-summary-main" style="display: flex; align-items: center; gap: 15px;">
                                <div class="video-summary-media">
                                    <div class="video-thumb-frame"<?php if (!empty($item['file'])): ?> role="button" tabindex="0" title="播放"
                                        onclick="playVideo('<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['file']); ?>', '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')"<?php endif; ?>>
                                        <?php if (!empty($item['cover'])): ?>
                                            <img class="video-thumb" loading="lazy" alt="" src="<?php echo htmlspecialchars($item['cover']); ?>">
                                        <?php elseif (!empty($item['file'])): ?>
                                            <video class="video-thumb" src="<?php echo htmlspecialchars($item['file']); ?>" preload="metadata" muted playsinline></video>
                                        <?php else: ?>
                                            <div class="video-thumb video-thumb-placeholder"><i class="fa-solid fa-video"></i></div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['file'])): ?>
                                            <span class="video-thumb-play"><i class="fa-solid fa-play"></i></span>
                                        <?php endif; ?>
                                        <span class="video-thumb-title"><?php echo htmlspecialchars($item['name']); ?></span>
                                    </div>
                                </div>
                                <div class="video-summary-text">
                                    <h3 class="video-card-title">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </h3>
                                    <div class="video-meta-row">
                                        <span class="video-badge"><?php echo !empty($item['ref']) ? htmlspecialchars($item['ref']) : '本機影片'; ?></span>
                                        <span class="video-created-at"><?php echo !empty($item['created_at']) ? date('Y-m-d', strtotime($item['created_at'])) : '未記錄'; ?></span>
                                    </div>
                                    <?php if (!empty($item['note'])): ?>
                                        <p class="video-note-preview" style="margin: 0; color: #666; font-size: 0.85rem;">
                                            <?php echo htmlspecialchars($item['note']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="video-actions" style="display: flex; gap: 8px; align-items: center;">
                                <?php if (!empty($item['file'])): ?>
                                    <button class="btn btn-primary btn-sm"
                                        onclick="playVideo('<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['file']); ?>', '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')">
                                        <i class="fa-solid fa-play"></i> 播放
                                    </button>
                                    <button class="btn btn-success btn-sm"
                                        onclick="downloadVideo('<?php echo $item['id']; ?>')">
                                        <i class="fa-solid fa-download"></i> 下載
                                    </button>
                                    <button class="btn btn-ghost btn-sm video-cache-btn"
                                        data-cache-id="<?php echo htmlspecialchars($item['id']); ?>"
                                        onclick="cacheVideoOffline('<?php echo $item['id']; ?>')"
                                        title="快取到本機（IndexedDB，上限 500MB）">
                                        <i class="fa-solid fa-cloud-arrow-down"></i> 快取
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
                            <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入影片網址"
                                oninput="updateInlineVideoPreview(this)">
                            <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                <input type="file" class="inline-video-file" accept="video/*" style="display: none;"
                                    onchange="uploadInlineVideo(this)">
                                <button type="button" class="btn" onclick="this.previousElementSibling.click()"
                                    style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳影片</button>
                            </div>
                            <div class="inline-video-preview" style="margin-top: 6px;"></div>
                        </div>
                        <div class="form-group">
                            <label>封面圖</label>
                            <input type="text" class="form-control inline-input" data-field="cover" placeholder="輸入封面圖網址"
                                oninput="updateInlineCoverPreview(this)">
                            <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                <input type="file" class="inline-cover-file" accept="image/*" style="display: none;"
                                    onchange="uploadInlineCover(this)">
                                <button type="button" class="btn" onclick="this.previousElementSibling.click()"
                                    style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳封面</button>
                            </div>
                            <div class="inline-cover-preview" style="margin-top: 6px;"></div>
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
                            <button type="button" class="btn btn-primary"
                                onclick="saveInlineEdit('<?php echo $item['id']; ?>')">儲存</button>
                            <button type="button" class="btn"
                                onclick="cancelInlineEdit('<?php echo $item['id']; ?>')">取消</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    </div>

    <!-- Video.js CSS -->
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet">
    <style>
        .video-experience {
            --video-shell-bg: #f5f3ec;
            --video-shell-border: rgba(0, 0, 0, 0.06);
            --video-shell-shadow: 0 18px 40px rgba(32, 45, 72, 0.08);
            --video-accent: #ff0033;
            --video-secondary: #1f1e1d;
            --video-chip-bg: rgba(17, 24, 39, 0.06);
            --video-chip-text: #3d3a35;
            --video-modal-bg: #141413;
            --video-side-bg: rgba(255, 255, 255, 0.06);
        }

        .video-experience-bilibili {
            --video-shell-bg: #f5f3ec;
            --video-shell-border: rgba(57, 191, 255, 0.18);
            --video-shell-shadow: 0 20px 44px rgba(58, 170, 220, 0.18);
            --video-accent: #00a1d6;
            --video-secondary: #292826;
            --video-chip-bg: rgba(0, 161, 214, 0.12);
            --video-chip-text: #0369a1;
            --video-modal-bg: #1f1e1d;
            --video-side-bg: rgba(0, 161, 214, 0.12);
        }

        .video-hero {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: center;
            padding: 24px 28px;
            margin-top: 18px;
            border-radius: 22px;
            background: var(--video-shell-bg);
            border: 1px solid var(--video-shell-border);
            box-shadow: var(--video-shell-shadow);
        }

        .video-hero-kicker {
            font-size: 0.78rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--video-accent);
            margin-bottom: 8px;
            font-weight: 700;
        }

        .video-hero h2 {
            margin: 0 0 8px;
            font-size: 1.6rem;
            color: var(--video-secondary);
        }

        .video-hero p {
            margin: 0;
            color: #57534a;
            max-width: 680px;
            line-height: 1.6;
        }

        .video-hero-stats {
            display: flex;
            gap: 14px;
        }

        .video-hero-stat {
            min-width: 108px;
            padding: 14px 18px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid var(--video-shell-border);
            text-align: center;
        }

        .video-hero-stat strong {
            display: block;
            font-size: 1.35rem;
            color: var(--video-secondary);
        }

        .video-hero-stat span {
            font-size: 0.85rem;
            color: #6f6c65;
        }

        .video-interface-switch {
            display: inline-flex;
            padding: 4px;
            border-radius: 999px;
            background: rgba(17, 24, 39, 0.08);
            gap: 4px;
            margin-left: auto;
        }

        .video-mode-btn {
            border: none;
            background: transparent;
            color: #57534a;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .video-mode-btn.active {
            background: #fff;
            color: var(--video-secondary);
            box-shadow: 0 4px 14px rgba(30, 26, 20, 0.1);
        }


        .video-summary {
            gap: 18px;
        }

        .video-summary-main {
            min-width: 0;
            flex: 1 1 auto;
        }

        .video-summary-media {
            flex-shrink: 0;
        }

        .video-summary-main > div:last-child {
            min-width: 0;
        }

        .video-experience-bilibili .video-list .video-item {
            border-radius: 18px !important;
        }

        .video-experience-bilibili .video-list .inline-view>div:nth-child(2) {
            align-items: flex-start !important;
        }

        .video-meta-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .video-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            background: var(--video-chip-bg);
            color: var(--video-chip-text);
        }

        .video-created-at {
            font-size: 0.78rem;
            color: #7a756c;
        }

        .video-note-preview {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }

        .video-actions {
            flex-wrap: wrap;
        }

        .video-actions .btn {
            min-height: 42px;
        }

        /* Force 16:9 aspect ratio for video player */
        .video-container {
            position: relative;
            width: 100%;
            padding-top: 56.25%;
            /* 16:9 aspect ratio */
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
            background-color: #5a9367;
        }

        .video-js .vjs-load-progress {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Tooltip for time preview */
        .video-js .vjs-mouse-display {
            display: block !important;
        }

        .video-player-shell {
            width: 94%;
            max-width: 1320px;
            position: relative;
            border-radius: 28px;
            overflow: hidden;
            background: var(--video-modal-bg);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
        }

        .video-player-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(280px, 0.9fr);
            min-height: 70vh;
        }

        .video-player-main {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .video-player-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .video-player-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .video-player-title-wrap h3 {
            color: #fff;
            margin: 0 0 8px;
            font-size: 1.5rem;
        }

        .video-player-title-wrap p {
            margin: 0;
            color: rgba(255, 255, 255, 0.68);
            line-height: 1.6;
            max-width: 68ch;
        }

        .video-player-close {
            background: rgba(255, 255, 255, 0.08);
            border: none;
            color: #fff;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .video-player-side {
            background: var(--video-side-bg);
            border-left: 1px solid rgba(255, 255, 255, 0.08);
            padding: 24px 20px;
            overflow-y: auto;
        }

        .video-side-section + .video-side-section {
            margin-top: 22px;
        }

        .video-side-section h4 {
            margin: 0 0 12px;
            color: #fff;
            font-size: 0.95rem;
            letter-spacing: 0.04em;
        }

        .video-detail-card {
            border-radius: 18px;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.6;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        .video-queue-item {
            display: flex;
            gap: 12px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            padding: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease;
            background: rgba(255, 255, 255, 0.03);
        }

        .video-queue-item:hover,
        .video-queue-item.active {
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.22);
            background: rgba(255, 255, 255, 0.08);
        }

        .video-queue-item > div:last-child {
            min-width: 0;
        }

        .video-queue-cover {
            width: 112px;
            height: 64px;
            border-radius: 12px;
            object-fit: cover;
            background: rgba(255, 255, 255, 0.08);
            flex-shrink: 0;
        }

        .video-queue-title {
            color: #fff;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .video-queue-meta {
            color: rgba(255, 255, 255, 0.56);
            font-size: 0.8rem;
            line-height: 1.5;
        }

        .video-experience-bilibili .video-player-layout {
            grid-template-columns: minmax(0, 1.55fr) minmax(320px, 1fr);
        }

        .video-experience-bilibili .video-player-main {
            gap: 14px;
        }

        .video-experience-bilibili .video-player-shell {
            border-radius: 24px;
        }

        .video-experience-bilibili .video-detail-card {
            background: rgba(0, 161, 214, 0.16);
        }

        .video-experience-bilibili .video-queue-item.active {
            border-color: rgba(0, 161, 214, 0.55);
            background: rgba(0, 161, 214, 0.14);
        }

        @media (max-width: 960px) {
            .video-hero {
                flex-direction: column;
                align-items: flex-start;
            }

            .video-hero-stats {
                width: 100%;
                flex-wrap: wrap;
            }

            .video-hero-stat {
                flex: 1 1 180px;
            }

            .video-player-layout {
                grid-template-columns: 1fr;
            }

            .video-player-side {
                border-left: none;
                border-top: 1px solid rgba(255, 255, 255, 0.08);
            }

            .video-summary {
                display: block !important;
            }

            .video-actions {
                margin-top: 14px;
                justify-content: flex-start !important;
            }
        }

        @media (max-width: 768px) {
            .video-interface-switch {
                width: 100%;
                margin-left: 0;
                justify-content: space-between;
            }

            .video-mode-btn {
                flex: 1;
            }

            .video-list {
                margin-top: 16px !important;
            }

            .video-summary {
                display: block !important;
            }

            .video-summary-main {
                display: block !important;
            }

            .video-summary-media {
                margin-bottom: 14px;
            }

            .video-summary-media img,
            .video-summary-media video,
            .video-summary-media .video-thumb-placeholder {
                width: 100% !important;
                height: auto !important;
                max-height: 220px;
            }

            .video-actions {
                width: 100%;
                margin-top: 14px;
                flex-wrap: wrap;
                justify-content: flex-start !important;
            }

            .video-actions .btn {
                flex: 1 1 160px;
                justify-content: center;
            }

            .video-player-main,
            .video-player-side {
                padding: 16px;
            }

            .video-player-actions {
                flex-wrap: wrap;
                width: 100%;
                justify-content: flex-start;
            }

            .video-player-title-wrap h3 {
                font-size: 1.18rem;
            }

            .video-player-title-wrap p {
                font-size: 0.92rem;
            }

            .video-queue-cover {
                width: 88px;
                height: 52px;
            }
        }

        @media (max-width: 560px) {
            .video-hero {
                padding: 18px 18px;
                border-radius: 18px;
            }

            .video-hero h2 {
                font-size: 1.32rem;
            }

            .video-hero p {
                font-size: 0.92rem;
            }

            .video-hero-stats {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .video-hero-stat {
                min-width: 0;
                padding: 12px 14px;
            }

            .video-interface-switch {
                padding: 3px;
            }

            .video-mode-btn {
                padding: 8px 10px;
                font-size: 0.8rem;
            }

            .video-player-shell {
                width: calc(100% - 16px);
                border-radius: 20px;
            }

            .video-player-topbar {
                flex-direction: column;
            }

            .video-player-actions {
                width: 100%;
                justify-content: space-between;
            }

            .video-player-actions .btn {
                flex: 1 1 150px;
            }

            .video-player-side {
                padding-top: 14px;
            }

            .video-queue-item {
                border-radius: 16px;
                padding: 9px;
            }

            .video-queue-cover {
                width: 78px;
                height: 48px;
            }
        }

        /* ============================================================
           三介面版面重建：YouTube / Bilibili / Netflix
           ============================================================ */
        .video-experience { position: relative; }

        .video-experience-youtube {
            --video-accent: #ff0033;
            --video-chip-bg: rgba(255, 0, 51, 0.10);
            --video-chip-text: #b3122f;
        }

        .video-experience-bilibili {
            --video-accent: #fb7299;
            --video-chip-bg: rgba(251, 114, 153, 0.14);
            --video-chip-text: #c2185b;
        }

        .video-experience-netflix {
            --video-accent: #e50914;
            --video-secondary: #ffffff;
            --video-chip-bg: rgba(255, 255, 255, 0.14);
            --video-chip-text: rgba(255, 255, 255, 0.86);
            --video-modal-bg: #141414;
            --video-side-bg: rgba(255, 255, 255, 0.04);
            background: linear-gradient(180deg, #141414 0%, #0b0b0b 100%);
            color: #fff;
            border-radius: 24px;
            padding: 0 0 26px;
            margin-top: 18px;
            overflow: hidden;
        }

        /* ---------- 共用縮圖框 ---------- */
        .video-thumb-frame {
            position: relative;
            display: block;
            width: 100%;
            aspect-ratio: 16 / 9;
            border-radius: 12px;
            overflow: hidden;
            background: #221f1c;
            cursor: pointer;
            isolation: isolate;
        }

        .video-thumb {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover;
            display: block;
            border-radius: 0 !important;
            margin: 0 !important;
            background: #221f1c;
        }

        .video-thumb-placeholder {
            display: flex !important;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.6rem;
        }

        .video-thumb-play {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.05rem;
            background: rgba(0, 0, 0, 0.28);
            opacity: 0;
            transition: opacity 0.22s ease;
        }

        .video-thumb-play i {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.62);
            border: 2px solid rgba(255, 255, 255, 0.85);
            padding-left: 4px;
        }

        .video-thumb-frame:hover .video-thumb-play,
        .video-thumb-frame:focus-visible .video-thumb-play { opacity: 1; }

        .video-thumb-title { display: none; }

        .video-summary-text { min-width: 0; }

        .video-card-title {
            margin: 0 0 6px;
            font-size: 1.02rem;
            line-height: 1.4;
            color: var(--video-secondary);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ============================ YouTube ============================ */
        .video-experience-youtube .video-billboard { display: none; }

        .video-experience-youtube .video-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(288px, 1fr));
            gap: 26px 18px;
        }

        .video-experience-youtube .video-list .video-item {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin-bottom: 0 !important;
            border-radius: 14px !important;
        }

        .video-experience-youtube .video-summary,
        .video-experience-youtube .video-summary-main {
            display: block !important;
        }

        .video-experience-youtube .video-summary-media { margin-bottom: 12px; }

        .video-experience-youtube .video-thumb-frame { border-radius: 14px; }

        .video-experience-youtube .video-card-title {
            font-size: 1.05rem;
            font-weight: 700;
        }

        .video-experience-youtube .video-actions {
            margin-top: 10px;
            gap: 6px;
            opacity: 0.35;
            transition: opacity 0.2s ease;
        }

        .video-experience-youtube .video-item:hover .video-actions,
        .video-experience-youtube .video-item:focus-within .video-actions { opacity: 1; }

        .video-experience-youtube .video-actions .btn {
            min-height: 34px;
            padding: 6px 12px;
            font-size: 0.8rem;
            border-radius: 999px;
        }

        .video-experience-youtube .video-badge {
            background: var(--video-chip-bg);
            color: var(--video-chip-text);
        }

        /* ============================ Bilibili ============================ */
        .video-experience-bilibili .video-billboard { display: none; }

        .video-experience-bilibili .video-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(224px, 1fr));
            gap: 20px 14px;
        }

        .video-experience-bilibili .video-list .video-item {
            background: var(--card-bg, #fff) !important;
            border: 1px solid rgba(251, 114, 153, 0.16) !important;
            box-shadow: 0 2px 10px rgba(31, 30, 29, 0.06) !important;
            padding: 0 0 12px !important;
            margin-bottom: 0 !important;
            border-radius: 12px !important;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .video-experience-bilibili .video-list .video-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(251, 114, 153, 0.22) !important;
        }

        .video-experience-bilibili .video-summary,
        .video-experience-bilibili .video-summary-main {
            display: block !important;
        }

        .video-experience-bilibili .video-thumb-frame { border-radius: 12px 12px 0 0; }

        .video-experience-bilibili .video-summary-text { padding: 10px 12px 0; }

        .video-experience-bilibili .video-card-title {
            font-size: 0.94rem;
            font-weight: 600;
            min-height: 2.6em;
        }

        .video-experience-bilibili .video-meta-row {
            gap: 6px;
            font-size: 0.74rem;
        }

        .video-experience-bilibili .video-badge {
            background: var(--video-chip-bg);
            color: var(--video-chip-text);
            font-size: 0.7rem;
            padding: 2px 8px;
        }

        .video-experience-bilibili .video-note-preview {
            font-size: 0.78rem !important;
            -webkit-line-clamp: 1;
        }

        .video-experience-bilibili .video-actions {
            padding: 0 12px;
            margin-top: 10px;
            gap: 6px;
        }

        .video-experience-bilibili .video-actions .btn {
            min-height: 30px;
            padding: 4px 10px;
            font-size: 0.75rem;
            border-radius: 6px;
        }

        .video-experience-bilibili .video-list .video-item { position: relative; }

        .video-experience-bilibili .card-header {
            position: absolute;
            top: 8px;
            left: 8px;
            right: 8px;
            z-index: 3;
            margin: 0 !important;
            padding: 0;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .video-experience-bilibili .video-item:hover .card-header,
        .video-experience-bilibili .video-item:focus-within .card-header { opacity: 1; }

        .video-experience-bilibili .card-edit-btn,
        .video-experience-bilibili .card-delete-btn {
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .video-experience-youtube .video-item { position: relative; }

        .video-experience-youtube .card-header {
            position: absolute;
            top: 8px;
            left: 8px;
            right: 8px;
            z-index: 3;
            margin: 0 !important;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .video-experience-youtube .video-item:hover .card-header,
        .video-experience-youtube .video-item:focus-within .card-header { opacity: 1; }

        .video-experience-youtube .card-edit-btn,
        .video-experience-youtube .card-delete-btn {
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* ============================ Netflix ============================ */
        .video-billboard {
            display: none;
            position: relative;
            min-height: 420px;
            padding: 0;
            background-image: var(--vb-image, none);
            background-size: cover;
            background-position: center 28%;
            background-color: #1c1c1c;
        }

        .video-experience-netflix .video-billboard { display: block; }
        .video-experience-netflix .video-hero { display: none; }

        .video-billboard-scrim {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(10, 10, 10, 0.94) 0%, rgba(10, 10, 10, 0.72) 42%, rgba(10, 10, 10, 0.1) 78%),
                linear-gradient(0deg, #141414 2%, rgba(20, 20, 20, 0) 46%);
        }

        .video-billboard-inner {
            position: relative;
            padding: 64px 44px 52px;
            max-width: 640px;
        }

        .video-billboard-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.78);
            margin-bottom: 14px;
        }

        .video-billboard-badge span {
            color: #e50914;
            font-weight: 800;
            font-size: 1.15rem;
            letter-spacing: 0;
        }

        .video-billboard-title {
            margin: 0 0 14px;
            font-size: clamp(1.9rem, 4vw, 3.1rem);
            line-height: 1.1;
            color: #fff;
            font-weight: 800;
            text-shadow: 0 2px 14px rgba(0, 0, 0, 0.55);
        }

        .video-billboard-desc {
            margin: 0 0 22px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 1rem;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .video-billboard-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .vb-btn {
            border: none;
            border-radius: 6px;
            padding: 12px 24px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .vb-btn:hover { opacity: 0.82; transform: translateY(-1px); }
        .vb-btn-play { background: #fff; color: #141414; }
        .vb-btn-info { background: rgba(109, 109, 110, 0.7); color: #fff; }

        .video-billboard-meta {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            color: rgba(255, 255, 255, 0.62);
            font-size: 0.85rem;
        }

        .video-rail-head {
            display: none;
            align-items: baseline;
            gap: 12px;
            padding: 0 44px;
            margin: 6px 0 -6px;
        }

        .video-experience-netflix .video-rail-head { display: flex; }

        .video-rail-head h3 {
            margin: 0;
            color: #fff;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .video-rail-count { color: rgba(255, 255, 255, 0.5); font-size: 0.85rem; }

        .video-experience-netflix .video-list {
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            overflow-x: auto;
            overflow-y: visible;
            scroll-snap-type: x proximity;
            padding: 34px 44px 40px;
            margin-top: 0 !important;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.28) transparent;
        }

        .video-experience-netflix .video-list::-webkit-scrollbar { height: 8px; }
        .video-experience-netflix .video-list::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.24);
            border-radius: 999px;
        }

        .video-experience-netflix .video-list .video-item {
            flex: 0 0 clamp(210px, 21vw, 300px);
            scroll-snap-align: start;
            background: #181818 !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin-bottom: 0 !important;
            border-radius: 8px !important;
            overflow: visible;
            transition: transform 0.28s cubic-bezier(0.2, 0.7, 0.3, 1), box-shadow 0.28s ease;
        }

        .video-experience-netflix .video-list .video-item:hover,
        .video-experience-netflix .video-list .video-item:focus-within {
            transform: scale(1.11);
            z-index: 6;
            box-shadow: 0 20px 44px rgba(0, 0, 0, 0.72) !important;
        }

        .video-experience-netflix .video-summary,
        .video-experience-netflix .video-summary-main {
            display: block !important;
        }

        .video-experience-netflix .video-thumb-frame { border-radius: 8px; }

        .video-experience-netflix .video-thumb-title {
            display: block;
            position: absolute;
            left: 12px;
            right: 12px;
            bottom: 10px;
            color: #fff;
            font-weight: 700;
            font-size: 0.92rem;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.85);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .video-experience-netflix .video-thumb-frame::after {
            content: '';
            position: absolute;
            inset: auto 0 0 0;
            height: 52%;
            background: linear-gradient(0deg, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0));
            pointer-events: none;
        }

        .video-experience-netflix .video-thumb-play { z-index: 2; }

        .video-experience-netflix .video-summary-text,
        .video-experience-netflix .video-actions {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transition: max-height 0.28s ease, opacity 0.22s ease, padding 0.28s ease;
            padding-left: 12px;
            padding-right: 12px;
        }

        .video-experience-netflix .video-item:hover .video-summary-text,
        .video-experience-netflix .video-item:hover .video-actions,
        .video-experience-netflix .video-item:focus-within .video-summary-text,
        .video-experience-netflix .video-item:focus-within .video-actions {
            max-height: 200px;
            opacity: 1;
            padding-top: 10px;
            padding-bottom: 10px;
        }

        .video-experience-netflix .video-card-title {
            color: #fff;
            font-size: 0.9rem;
            -webkit-line-clamp: 1;
        }

        .video-experience-netflix .video-created-at { color: rgba(255, 255, 255, 0.5); }
        .video-experience-netflix .video-note-preview { color: rgba(255, 255, 255, 0.62) !important; }

        .video-experience-netflix .video-badge {
            background: var(--video-chip-bg);
            color: var(--video-chip-text);
        }

        .video-experience-netflix .video-actions .btn {
            min-height: 32px;
            padding: 5px 10px;
            font-size: 0.75rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .video-experience-netflix .video-actions .btn:hover { background: rgba(255, 255, 255, 0.24); }

        .video-experience-netflix .card-header {
            position: absolute;
            top: 8px;
            left: 8px;
            right: 8px;
            z-index: 3;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .video-experience-netflix .video-item:hover .card-header,
        .video-experience-netflix .video-item:focus-within .card-header { opacity: 1; }

        .video-experience-netflix .video-item { position: relative; }

        .video-experience-netflix .card-edit-btn,
        .video-experience-netflix .card-delete-btn {
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .video-experience-netflix #inlineAddCard,
        .video-experience-netflix .video-item .inline-edit {
            background: #1f1f1f;
            color: #fff;
        }

        .video-experience-netflix #inlineAddCard { flex: 0 0 min(560px, 90vw); padding: 18px !important; }

        /* ---------- 介面切換列 ---------- */
        .video-mode-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-weight: 600;
        }

        .video-mode-btn[data-mode="youtube"].active { color: #ff0033; }
        .video-mode-btn[data-mode="bilibili"].active { color: #fb7299; }
        .video-mode-btn[data-mode="netflix"].active { background: #141414; color: #e50914; }

        /* ---------- RWD ---------- */
        @media (max-width: 900px) {
            .video-billboard { min-height: 320px; }
            .video-billboard-inner { padding: 40px 22px 34px; }
            .video-rail-head { padding: 0 22px; }
            .video-experience-netflix .video-list { padding: 24px 22px 30px; }
        }

        @media (max-width: 768px) {
            .video-experience-youtube .video-list,
            .video-experience-bilibili .video-list {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 18px 12px;
            }

            .video-experience-youtube .video-summary-media,
            .video-experience-bilibili .video-summary-media,
            .video-experience-netflix .video-summary-media { margin-bottom: 8px; }

            .video-experience-youtube .video-summary-media img,
            .video-experience-youtube .video-summary-media video,
            .video-experience-bilibili .video-summary-media img,
            .video-experience-bilibili .video-summary-media video,
            .video-experience-netflix .video-summary-media img,
            .video-experience-netflix .video-summary-media video {
                height: 100% !important;
                max-height: none;
            }

            .video-experience-youtube .video-actions { opacity: 1; }
            .video-experience-netflix .video-list .video-item { flex: 0 0 62vw; }
            .video-experience-netflix .video-list .video-item:hover { transform: none; }
            .video-experience-netflix .video-summary-text,
            .video-experience-netflix .video-actions { max-height: 200px; opacity: 1; padding-top: 10px; padding-bottom: 10px; }
            .video-experience-netflix .card-header,
            .video-experience-youtube .card-header,
            .video-experience-bilibili .card-header { opacity: 1; }
            .video-mode-btn span { display: none; }
            .video-mode-btn i { font-size: 1.05rem; }
        }

    </style>

    <!-- Video Player Modal -->
    <div id="videoPlayerModal"
        style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.9); z-index: 9999; justify-content: center; align-items: center;">
        <div class="video-player-shell">
            <div class="video-player-layout">
                <div class="video-player-main">
                    <div class="video-player-topbar">
                        <div class="video-player-title-wrap">
                            <h3 id="videoPlayerTitle"></h3>
                            <p id="videoPlayerSummary"></p>
                        </div>
                        <div class="video-player-actions">
                            <button id="videoPlayerScreenshotBtn" type="button" class="btn btn-ghost"
                                onclick="captureVideoScreenshot()" title="擷取目前畫面為圖片">
                                <i class="fa-solid fa-camera"></i> 截圖
                            </button>
                            <button id="videoPlayerDownloadBtn" type="button" class="btn btn-success"
                                onclick="downloadCurrentVideo()" style="display: none;">
                                <i class="fa-solid fa-download"></i> 下載影片
                            </button>
                            <button class="video-player-close" onclick="closeVideoPlayer()">&times;</button>
                        </div>
                    </div>
                    <div class="video-container">
                        <video id="videoPlayer" class="video-js vjs-big-play-centered" controls preload="auto">
                            <p class="vjs-no-js">您的瀏覽器不支援影片播放</p>
                        </video>
                    </div>
                </div>
                <aside class="video-player-side">
                    <section class="video-side-section">
                        <h4 id="videoPlayerMetaTitle">影片資訊</h4>
                        <div id="videoPlayerMeta" class="video-detail-card">尚未選擇影片</div>
                    </section>
                    <section class="video-side-section">
                        <h4 id="videoPlayerQueueTitle">接續播放</h4>
                        <div id="videoPlayerQueue"></div>
                    </section>
                </aside>
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
                    <input type="file" id="modalMultiVideoFiles" accept="video/*" multiple onchange="uploadMultipleVideos(this.files)" style="display: none;">
                    <button type="button" class="btn" onclick="document.getElementById('modalMultiVideoFiles').click()">
                        <i class="fa-solid fa-photo-film"></i> 多影片上傳
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
    const TABLE = 'video';
    const VIDEO_INTERFACE_STORAGE_KEY = 'videoInterfaceMode';
    const VIDEO_ITEMS = <?php echo json_encode(array_map(function ($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'] ?? '',
            'file' => $item['file'] ?? '',
            'cover' => $item['cover'] ?? '',
            'ref' => $item['ref'] ?? '',
            'note' => $item['note'] ?? '',
            'created_at' => $item['created_at'] ?? '',
        ];
    }, $items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    let currentVideoInterface = 'youtube';
    let currentPlayingVideoId = null;
    initBatchDelete(TABLE);

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getVideoItem(id) {
        return VIDEO_ITEMS.find(item => item.id === id) || null;
    }

    function getVideoDownloadName(item) {
        const fallback = 'video';
        const rawName = String(item?.name || fallback).trim() || fallback;
        const cleanName = rawName.replace(/[\\\\/:*?"<>|]+/g, '-');

        try {
            const pathname = new URL(item.file, window.location.href).pathname || '';
            const lastSegment = pathname.split('/').pop() || '';
            const extMatch = lastSegment.match(/(\.[a-z0-9]{2,5})$/i);
            return extMatch ? `${cleanName}${extMatch[1]}` : cleanName;
        } catch (error) {
            return cleanName;
        }
    }

    function downloadVideo(id) {
        const item = getVideoItem(id);
        if (!item || !item.file) {
            alert('找不到可下載的影片檔案');
            return;
        }

        const link = document.createElement('a');
        link.href = item.file;
        link.download = getVideoDownloadName(item);
        link.target = '_blank';
        link.rel = 'noopener';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function downloadCurrentVideo() {
        if (!currentPlayingVideoId) {
            alert('目前沒有播放中的影片');
            return;
        }

        downloadVideo(currentPlayingVideoId);
    }

    /** 擷取播放器目前畫面（對齊 Appwrite video-screenshot-button） */
    function captureVideoScreenshot() {
        try {
            let videoEl = null;
            if (typeof vjsPlayer !== 'undefined' && vjsPlayer && typeof vjsPlayer.el === 'function') {
                videoEl = vjsPlayer.el().querySelector('video');
            }
            if (!videoEl) {
                videoEl = document.querySelector('#videoPlayer video, #videoPlayer_html5_api, video#videoPlayer');
            }
            if (!videoEl || !videoEl.videoWidth) {
                alert('目前沒有可截圖的影片畫面');
                return;
            }
            const canvas = document.createElement('canvas');
            canvas.width = videoEl.videoWidth;
            canvas.height = videoEl.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(videoEl, 0, 0, canvas.width, canvas.height);
            const name = (document.getElementById('videoPlayerTitle')?.textContent || 'video').replace(/[\\/:*?"<>|]+/g, '_').slice(0, 60);
            const a = document.createElement('a');
            a.href = canvas.toDataURL('image/png');
            a.download = name + '-shot-' + Date.now() + '.png';
            document.body.appendChild(a);
            a.click();
            a.remove();
        } catch (e) {
            alert('截圖失敗（可能因跨域影片限制）：' + (e.message || e));
        }
    }

    const VIDEO_INTERFACE_PRESETS = {
        youtube: {
            title: 'Theater feed, clean controls, playlist rhythm.',
            description: 'YouTube 介面把片庫排成大縮圖網格，標題兩行、操作按鈕在滑過時才淡入，適合長時間連續瀏覽。',
            metaTitle: '影片資訊',
            queueTitle: '接續播放'
        },
        bilibili: {
            title: '資訊更密、卡片更小、像追番站的看片節奏。',
            description: 'Bilibili 介面把卡片縮到六欄以上，資訊層次密集、封面下方直接接標題與備註，適合快速掃過大量影片。',
            metaTitle: '稿件資訊',
            queueTitle: '推薦連播'
        },
        netflix: {
            title: '主打影片 + 橫向片庫走廊。',
            description: 'Netflix 介面用暗色劇院背景，最新一部影片變成主打看板，其餘影片排成可橫向捲動的片庫走廊，滑過會放大展開資訊。',
            metaTitle: '本集資訊',
            queueTitle: '接著看'
        }
    };

    function setVideoInterface(mode) {
        currentVideoInterface = VIDEO_INTERFACE_PRESETS[mode] ? mode : 'youtube';
        const preset = VIDEO_INTERFACE_PRESETS[currentVideoInterface];
        const container = document.getElementById('videoExperience');
        const title = document.getElementById('videoExperienceTitle');
        const description = document.getElementById('videoExperienceDescription');
        const metaTitle = document.getElementById('videoPlayerMetaTitle');
        const queueTitle = document.getElementById('videoPlayerQueueTitle');

        if (container) {
            Object.keys(VIDEO_INTERFACE_PRESETS).forEach(function (key) {
                container.classList.toggle('video-experience-' + key, key === currentVideoInterface);
            });
        }

        document.querySelectorAll('.video-mode-btn').forEach(button => {
            const on = button.dataset.mode === currentVideoInterface;
            button.classList.toggle('active', on);
            button.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        if (title) title.textContent = preset.title;
        if (description) description.textContent = preset.description;
        if (metaTitle) metaTitle.textContent = preset.metaTitle;
        if (queueTitle) queueTitle.textContent = preset.queueTitle;

        localStorage.setItem(VIDEO_INTERFACE_STORAGE_KEY, currentVideoInterface);
        renderVideoQueue(currentPlayingVideoId);
        renderVideoMeta(currentPlayingVideoId);
    }

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
        deleteInlineItem(id, { table: TABLE });
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
            ref: document.getElementById('ref').value,
            note: document.getElementById('note').value,
            category: 'video'
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
            function (error) { alert('上傳失敗: ' + error); },
            { title: '影片上傳中...', completeTitle: '影片上傳完成' }
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
            ? `<img src="${url}" style="width: 120px; height: 90px; object-fit: cover; border-radius: 5px;">`
            : '';
    }

    function uploadVideo() {
        const input = document.getElementById('videoFile');
        if (!input.files || !input.files[0]) return;

        uploadFileWithProgress(input.files[0],
            function (res) {
                document.getElementById('file').value = res.file;
                const nameInput = document.getElementById('name');
                if (nameInput && !nameInput.value) {
                    nameInput.value = res.filename || '';
                }
                updateVideoPreview();
            },
            function (error) {
                alert('上傳失敗: ' + error);
            },
            { title: '影片上傳中...', completeTitle: '影片上傳完成' }
        );
        input.value = '';
    }

    function uploadFileWithProgressPromise(file) {
        return new Promise((resolve, reject) => {
            uploadFileWithProgress(file, resolve, reject);
        });
    }

    function createVideoRecord(data) {
        return fetch(`api.php?action=create&table=${TABLE}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json());
    }

    function baseName(filename) {
        return String(filename || '').replace(/\.[^.]+$/, '');
    }

    async function uploadMultipleVideos(fileList) {
        const files = Array.from(fileList || []).filter(file => file && String(file.type || '').startsWith('video/'));
        if (!files.length) return;

        const triggerInputs = ['multiVideoFiles', 'modalMultiVideoFiles'];
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
            `準備上傳 0 / ${files.length} 部`,
            '多影片上傳中...'
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
                                `第 ${i + 1} / ${files.length} 部：${file.name} (${progress.loadedText} / ${progress.totalText})`,
                                '多影片上傳中...'
                            );
                        }
                    });
                });
                completedBytes += file.size || 0;
                const data = {
                    name: baseName(uploadRes.filename || file.name) || '未命名影片',
                    file: uploadRes.file,
                    cover: '',
                    ref: '',
                    note: '',
                    category: 'video'
                };
                const createRes = await createVideoRecord(data);
                if (!createRes.success) {
                    throw new Error(createRes.error || '建立影片資料失敗');
                }
                successCount++;
                const aggregatePercent = totalBytes > 0
                    ? Math.round((completedBytes / totalBytes) * 100)
                    : Math.round((successCount / files.length) * 100);
                showUploadProgressModal(
                    aggregatePercent,
                    `${aggregatePercent}% (${successCount}/${files.length})`,
                    `已完成 ${successCount} / ${files.length} 部`,
                    '多影片上傳中...'
                );
            } catch (error) {
                completedBytes += file.size || 0;
                failedFiles.push(`${file.name}: ${error && error.message ? error.message : error}`);
            }
        }

        showUploadProgressModal(
            100,
            `100% (${successCount}/${files.length})`,
            failedFiles.length ? `完成，失敗 ${failedFiles.length} 部` : `全部完成 ${successCount} / ${files.length} 部`,
            '多影片上傳完成'
        );
        await new Promise(resolve => setTimeout(resolve, 450));
        hideUploadProgressModal();

        triggerInputs.forEach(id => {
            const input = document.getElementById(id);
            if (input) input.value = '';
        });

        if (successCount > 0 && failedFiles.length === 0) {
            alert(`已成功上傳 ${successCount} 部影片`);
            location.reload();
            return;
        }

        if (successCount > 0) {
            alert(`成功 ${successCount} 部，失敗 ${failedFiles.length} 部：\n${failedFiles.join('\n')}`);
            location.reload();
            return;
        }

        alert('多影片上傳失敗：\n' + failedFiles.join('\n'));
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
            function (res) {
                document.getElementById('cover').value = res.file;
                updateCoverPreview();
            },
            function (error) {
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
            vjsPlayer.on('keydown', function (e) {
                const currentTime = vjsPlayer.currentTime();
                const duration = vjsPlayer.duration();

                switch (e.which) {
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

    async function resolveVideoPlaySrc(id, fallbackSrc) {
        if (!window.FengbroMediaCache) return fallbackSrc;
        try {
            const objectUrl = await window.FengbroMediaCache.getObjectUrl('video', id);
            return objectUrl || fallbackSrc;
        } catch (e) {
            return fallbackSrc;
        }
    }

    async function refreshVideoCacheStats() {
        const label = document.getElementById('videoCacheStatsLabel');
        const banner = document.getElementById('videoCacheBanner');
        if (!window.FengbroMediaCache) {
            if (label) label.textContent = '快取不可用';
            return;
        }
        try {
            const stats = await window.FengbroMediaCache.getStats('video');
            const text = window.FengbroMediaCache.formatBytes(stats.totalSize) + ' / 500MB · ' + stats.totalItems + ' 部';
            if (label) label.textContent = text;
            if (banner) {
                banner.style.display = 'block';
                banner.textContent = '離線影片快取：' + text + '（超過 500MB 會自動清除最舊項目）';
            }
            document.querySelectorAll('.video-cache-btn[data-cache-id]').forEach(async function (btn) {
                const id = btn.getAttribute('data-cache-id');
                const cached = await window.FengbroMediaCache.isCached('video', id);
                btn.classList.toggle('btn-success', cached);
                btn.innerHTML = cached
                    ? '<i class="fa-solid fa-check"></i> 已快取'
                    : '<i class="fa-solid fa-cloud-arrow-down"></i> 快取';
            });
        } catch (e) {
            if (label) label.textContent = '快取';
        }
    }

    async function cacheVideoOffline(id) {
        const item = getVideoItem(id);
        if (!item || !item.file) {
            alert('找不到可快取的影片檔案');
            return;
        }
        if (!window.FengbroMediaCache) {
            alert('瀏覽器不支援離線快取');
            return;
        }
        const btn = document.querySelector('.video-cache-btn[data-cache-id="' + id + '"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 0%';
        }
        try {
            await window.FengbroMediaCache.cacheMedia('video', {
                id: item.id,
                title: item.name,
                url: item.file
            }, function (progress) {
                if (btn) btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + progress + '%';
            });
            await refreshVideoCacheStats();
            alert('已快取到本機，可離線播放');
        } catch (err) {
            alert('快取失敗：' + (err.message || err));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down"></i> 快取';
            }
        }
    }

    async function playVideo(id, src, title) {
        const item = getVideoItem(id);
        const playSrc = await resolveVideoPlaySrc(id, src);
        if (window.FengbroMedia) {
            window.FengbroMedia.playVideo({
                src: playSrc,
                title: title,
                id: id,
                mediaType: 'video',
                poster: item && item.cover ? item.cover : '',
                meta: item && item.ref ? item.ref : (playSrc !== src ? 'Video · Offline' : 'Video'),
                downloadName: item ? getVideoDownloadName(item) : ''
            });
            return;
        }

        const modal = document.getElementById('videoPlayerModal');
        const titleEl = document.getElementById('videoPlayerTitle');
        const downloadBtn = document.getElementById('videoPlayerDownloadBtn');

        currentPlayingVideoId = id;
        titleEl.textContent = title;
        modal.style.display = 'flex';
        if (downloadBtn) {
            downloadBtn.style.display = item && item.file ? 'inline-flex' : 'none';
        }
        renderVideoMeta(id);
        renderVideoQueue(id);
        const summary = document.getElementById('videoPlayerSummary');
        if (summary) {
            if (currentVideoInterface === 'bilibili') {
                summary.textContent = item && item.ref
                    ? `分區 / 來源：${item.ref}`
                    : '本機稿件播放中';
            } else {
                summary.textContent = item && item.note
                    ? item.note
                    : '沉浸播放模式已開啟';
            }
        }

        const player = initVideoJS();
        player.src({ type: 'video/mp4', src: playSrc });
        player.play();
    }

    function closeVideoPlayer() {
        const modal = document.getElementById('videoPlayerModal');
        const downloadBtn = document.getElementById('videoPlayerDownloadBtn');

        if (vjsPlayer) {
            vjsPlayer.pause();
            vjsPlayer.src('');
        }
        if (downloadBtn) {
            downloadBtn.style.display = 'none';
        }
        currentPlayingVideoId = null;
        modal.style.display = 'none';
    }

    function renderVideoMeta(id) {
        const meta = document.getElementById('videoPlayerMeta');
        const item = getVideoItem(id);
        if (!meta) return;

        if (!item) {
            meta.textContent = '尚未選擇影片';
            return;
        }

        const created = item.created_at ? escapeHtml(item.created_at.replace('T', ' ')) : '未記錄';
        const source = item.ref ? escapeHtml(item.ref) : '本機影片';
        const note = item.note ? escapeHtml(item.note) : '沒有額外備註';

        if (currentVideoInterface === 'bilibili') {
            meta.innerHTML = `
                <div style="display:grid;gap:10px;">
                    <div><strong style="color:#fff;">標題</strong><div>${escapeHtml(item.name)}</div></div>
                    <div><strong style="color:#fff;">分區 / 來源</strong><div>${source}</div></div>
                    <div><strong style="color:#fff;">建立時間</strong><div>${created}</div></div>
                    <div><strong style="color:#fff;">簡介</strong><div>${note}</div></div>
                </div>
            `;
            return;
        }

        meta.innerHTML = `
            <div style="display:grid;gap:10px;">
                <div><strong style="color:#fff;">Now Playing</strong><div>${escapeHtml(item.name)}</div></div>
                <div><strong style="color:#fff;">Source</strong><div>${source}</div></div>
                <div><strong style="color:#fff;">Uploaded</strong><div>${created}</div></div>
                <div><strong style="color:#fff;">Notes</strong><div>${note}</div></div>
            </div>
        `;
    }

    function renderVideoQueue(activeId) {
        const queue = document.getElementById('videoPlayerQueue');
        if (!queue) return;

        queue.innerHTML = VIDEO_ITEMS
            .filter(item => item.file)
            .map(item => {
                const isActive = item.id === activeId;
                const cover = item.cover
                    ? `<img class="video-queue-cover" src="${escapeHtml(item.cover)}" alt="${escapeHtml(item.name)}">`
                    : `<div class="video-queue-cover" style="display:flex;align-items:center;justify-content:center;color:#fff;"><i class="fa-solid fa-video"></i></div>`;
                const meta = currentVideoInterface === 'bilibili'
                    ? escapeHtml(item.ref || '稿件未分類')
                    : escapeHtml(item.created_at ? item.created_at.slice(0, 10) : '最近加入');

                return `
                    <div class="video-queue-item${isActive ? ' active' : ''}" onclick="playVideo('${escapeHtml(item.id)}', '${escapeHtml(item.file)}', '${escapeHtml(item.name)}')">
                        ${cover}
                        <div style="min-width:0;">
                            <div class="video-queue-title">${escapeHtml(item.name)}</div>
                            <div class="video-queue-meta">${meta}</div>
                        </div>
                    </div>
                `;
            })
            .join('');
    }

    // Close modal on ESC key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeVideoPlayer();
        }
    });

    // Close modal on background click
    document.getElementById('videoPlayerModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeVideoPlayer();
        }
    });

    window.batchCacheSelectedItems = async function (ids) {
        if (!window.FengbroMediaCache) throw new Error('瀏覽器不支援離線快取');
        if (!ids || !ids.length) return;
        let ok = 0, fail = 0;
        for (let i = 0; i < ids.length; i++) {
            const id = ids[i];
            const item = getVideoItem(id);
            if (!item || !item.file) { fail++; continue; }
            try {
                await window.FengbroMediaCache.cacheMedia('video', {
                    id: item.id,
                    title: item.name,
                    url: item.file
                });
                ok++;
            } catch (e) {
                fail++;
            }
        }
        await refreshVideoCacheStats();
        alert('批次快取完成：成功 ' + ok + ' 部' + (fail ? '，失敗 ' + fail + ' 部' : ''));
    };

    document.addEventListener('DOMContentLoaded', function () {
        const savedMode = localStorage.getItem(VIDEO_INTERFACE_STORAGE_KEY);
        setVideoInterface(savedMode || 'youtube');
        renderVideoQueue(null);
        renderVideoMeta(null);
        refreshVideoCacheStats();
        if (typeof enableBatchCacheButton === 'function') {
            enableBatchCacheButton(true);
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
        fileName.textContent = file.name + ' — 準備分段上傳...';

        uploadChunked(
            file,
            // onProgress
            function (done, total, percent) {
                progressBar.style.width = percent + '%';
                progressText.textContent = percent + '%';
                fileName.textContent = file.name + ' — 上傳第 ' + done + ' / ' + total + ' 片';
            },
            // onDone
            function (tempFile) {
                fileName.textContent = file.name + ' — 正在匯入...';
                progressBar.style.width = '100%';
                progressText.textContent = '100%';

                const fd = new FormData();
                fd.append('tempFile', tempFile);

                fetch('import_zip_video.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        modal.style.display = 'none';
                        if (res.success) {
                            alert('匯入完成！\n成功匯入: ' + res.imported + ' 部影片');
                            location.reload();
                        } else {
                            alert('匯入失敗: ' + (res.error || '未知錯誤'));
                        }
                    })
                    .catch(function (e) {
                        modal.style.display = 'none';
                        alert('匯入失敗: 網路錯誤 — ' + e.message);
                    });
            },
            // onError
            function (errMsg) {
                modal.style.display = 'none';
                alert('上傳失敗: ' + errMsg);
            }
        );

        input.value = '';
    }
</script>
