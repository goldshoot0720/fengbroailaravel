<?php
$pageTitle = '影片管理';
$pdo = getConnection();
// 使用 commondocument 表來存放影片
$items = $pdo->query("SELECT * FROM video ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄影片 <span
            style="font-size:0.55em;background:#e74c3c;color:#fff;padding:3px 10px;border-radius:20px;vertical-align:middle;font-weight:500;"><?php echo count($items); ?></span>
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
        <div id="videoInterfaceSwitch" class="video-interface-switch">
            <button type="button" class="video-mode-btn active" data-mode="youtube" onclick="setVideoInterface('youtube')">Like YouTube</button>
            <button type="button" class="video-mode-btn" data-mode="bilibili" onclick="setVideoInterface('bilibili')">Like Bilibili</button>
        </div>
    </div>
    <?php include 'includes/batch-delete.php'; ?>

    <div id="videoExperience" class="video-experience video-experience-youtube">
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
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <?php if (!empty($item['cover'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['cover']); ?>"
                                        style="width: 80px; height: 60px; object-fit: cover; border-radius: 5px;">
                                <?php elseif (!empty($item['file'])): ?>
                                    <video
                                        src="<?php echo htmlspecialchars($item['file']); ?>"
                                        preload="metadata"
                                        muted
                                        playsinline
                                        style="width: 80px; height: 60px; object-fit: cover; border-radius: 5px; background: #34495e;">
                                    </video>
                                <?php else: ?>
                                    <div class="video-thumb-placeholder"
                                        style="width: 80px; height: 60px; background: #34495e; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa-solid fa-video" style="color: #fff; font-size: 1.5rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h3 style="margin: 0 0 5px 0; font-size: 1.1rem;">
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
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <?php if (!empty($item['file'])): ?>
                                    <button class="btn btn-primary btn-sm"
                                        onclick="playVideo('<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['file']); ?>', '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')">
                                        <i class="fa-solid fa-play"></i> 播放
                                    </button>
                                    <button class="btn btn-success btn-sm"
                                        onclick="downloadVideo('<?php echo $item['id']; ?>')">
                                        <i class="fa-solid fa-download"></i> 下載
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
            --video-shell-bg: linear-gradient(135deg, #f7f7f7 0%, #ffffff 55%, #eef4ff 100%);
            --video-shell-border: rgba(0, 0, 0, 0.06);
            --video-shell-shadow: 0 18px 40px rgba(32, 45, 72, 0.08);
            --video-accent: #ff0033;
            --video-secondary: #111827;
            --video-chip-bg: rgba(17, 24, 39, 0.06);
            --video-chip-text: #374151;
            --video-modal-bg: #0f1115;
            --video-side-bg: rgba(255, 255, 255, 0.06);
        }

        .video-experience-bilibili {
            --video-shell-bg: linear-gradient(135deg, #ecfaff 0%, #ffffff 45%, #ffeef7 100%);
            --video-shell-border: rgba(57, 191, 255, 0.18);
            --video-shell-shadow: 0 20px 44px rgba(58, 170, 220, 0.18);
            --video-accent: #00a1d6;
            --video-secondary: #1f2937;
            --video-chip-bg: rgba(0, 161, 214, 0.12);
            --video-chip-text: #0369a1;
            --video-modal-bg: #101923;
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
            color: #5b6474;
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
            color: #6b7280;
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
            color: #475569;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .video-mode-btn.active {
            background: #fff;
            color: var(--video-secondary);
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.1);
        }

        .video-list .video-item {
            background: var(--video-shell-bg) !important;
            border: 1px solid var(--video-shell-border);
            box-shadow: var(--video-shell-shadow) !important;
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
            color: #64748b;
        }

        .video-note-preview {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
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
            background-color: #4CAF50;
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
            }

            .video-player-layout {
                grid-template-columns: 1fr;
            }

            .video-player-side {
                border-left: none;
                border-top: 1px solid rgba(255, 255, 255, 0.08);
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

            .video-player-main,
            .video-player-side {
                padding: 16px;
            }

            .video-player-title-wrap h3 {
                font-size: 1.18rem;
            }

            .video-queue-cover {
                width: 88px;
                height: 52px;
            }
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

    function setVideoInterface(mode) {
        currentVideoInterface = mode === 'bilibili' ? 'bilibili' : 'youtube';
        const container = document.getElementById('videoExperience');
        const title = document.getElementById('videoExperienceTitle');
        const description = document.getElementById('videoExperienceDescription');
        const metaTitle = document.getElementById('videoPlayerMetaTitle');
        const queueTitle = document.getElementById('videoPlayerQueueTitle');

        if (container) {
            container.classList.toggle('video-experience-youtube', currentVideoInterface === 'youtube');
            container.classList.toggle('video-experience-bilibili', currentVideoInterface === 'bilibili');
        }

        document.querySelectorAll('.video-mode-btn').forEach(button => {
            button.classList.toggle('active', button.dataset.mode === currentVideoInterface);
        });

        if (title && description && metaTitle && queueTitle) {
            if (currentVideoInterface === 'bilibili') {
                title.textContent = '資訊更密、右欄更強、像追番站的看片節奏。';
                description.textContent = 'Bilibili 介面會把影片資訊、接續播放與備註放得更靠近主播放器，適合快速切片切片地看。';
                metaTitle.textContent = '稿件資訊';
                queueTitle.textContent = '推薦連播';
            } else {
                title.textContent = 'Theater feed, clean controls, playlist rhythm.';
                description.textContent = 'YouTube 介面強調主播放器與清楚縮圖，右欄像播放清單，適合長時間連續播放。';
                metaTitle.textContent = '影片資訊';
                queueTitle.textContent = '接續播放';
            }
        }

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
            }
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

    function playVideo(id, src, title) {
        const modal = document.getElementById('videoPlayerModal');
        const titleEl = document.getElementById('videoPlayerTitle');
        const item = getVideoItem(id);
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
        player.src({ type: 'video/mp4', src: src });
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

    document.addEventListener('DOMContentLoaded', function () {
        const savedMode = localStorage.getItem(VIDEO_INTERFACE_STORAGE_KEY);
        setVideoInterface(savedMode || 'youtube');
        renderVideoQueue(null);
        renderVideoMeta(null);
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
