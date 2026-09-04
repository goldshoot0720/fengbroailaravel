<?php
$pageTitle = '文件管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM commondocument WHERE category != 'video' OR category IS NULL ORDER BY created_at DESC")->fetchAll();
$categories = array_values(array_unique(array_filter(array_column($items, 'category'))));
sort($categories);

$docWithFile = count(array_filter($items, fn($item) => !empty($item['file'])));
$docTotal = count($items);
$docFillPercent = $docTotal > 0 ? (int) round($docWithFile / $docTotal * 100) : 0;
?>

<div class="content-header doc-header">
    <h1>鋒兄文件 <span class="doc-count-pill"><?php echo count($items); ?></span></h1>
</div>

<div class="content-body doc-experience doc-ui-drive" id="docExperience">
    <div class="doc-topbar">
        <div class="doc-crumb">
            <span class="doc-crumb-root"><i class="doc-crumb-icon"></i><span class="doc-crumb-root-text">我的雲端硬碟</span></span>
            <i class="fa-solid fa-chevron-right doc-crumb-sep"></i>
            <strong>鋒兄文件</strong>
        </div>
        <div class="doc-ui-switch" role="tablist" aria-label="文件介面風格">
            <button type="button" role="tab" class="doc-ui-btn is-active" data-ui="drive" onclick="setDocumentInterface('drive')">
                <i class="fa-brands fa-google-drive"></i><span>雲端硬碟</span>
            </button>
            <button type="button" role="tab" class="doc-ui-btn" data-ui="mega" onclick="setDocumentInterface('mega')">
                <i class="fa-solid fa-cloud"></i><span>MEGA</span>
            </button>
            <button type="button" role="tab" class="doc-ui-btn" data-ui="dropbox" onclick="setDocumentInterface('dropbox')">
                <i class="fa-brands fa-dropbox"></i><span>Dropbox</span>
            </button>
        </div>
    </div>

    <div class="doc-storage">
        <div class="doc-storage-head">
            <span class="doc-storage-title"><i class="fa-solid fa-hard-drive"></i> 檔案完成度</span>
            <strong><?php echo (int) $docWithFile; ?> / <?php echo (int) $docTotal; ?> 份已有檔案</strong>
        </div>
        <div class="doc-storage-bar"><div class="doc-storage-fill" style="width: <?php echo (int) $docFillPercent; ?>%;"></div></div>
        <div class="doc-storage-meta">
            <span><?php echo (int) $docTotal; ?> 份文件</span>
            <span><?php echo count($categories); ?> 個分類</span>
            <span id="docStorageCacheHint">離線快取見上方「快取」按鈕</span>
        </div>
    </div>

    <?php include 'includes/inline-edit-hint.php'; ?>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"
            style="background:#28a745;color:#fff;padding:12px 20px;border-radius:8px;margin-bottom:15px;">
            <i class="fa-solid fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger"
            style="background:#bd4034;color:#fff;padding:12px 20px;border-radius:8px;margin-bottom:15px;">
            <i class="fa-solid fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
    <button class="btn btn-primary" onclick="handleAdd()" title="新增文件"><i class="fas fa-plus"></i></button>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('multiDocumentFiles').click()">
        <i class="fa-solid fa-upload"></i> 多選上傳
    </button>
    <input type="file" id="multiDocumentFiles" multiple="multiple" style="display:none;"
        onchange="uploadMultipleDocuments(this.files)">
    <a href="export_zip_document.php" class="btn btn-success"><i class="fa-solid fa-download"></i> 匯出 ZIP</a>
    <button class="btn btn-info" onclick="document.getElementById('zipImport').click()"><i
            class="fa-solid fa-upload"></i> 匯入 ZIP</button>
    <input type="file" id="zipImport" accept=".zip" style="display:none;"
        onchange="previewAndImportZIP(this, 'document', 'import_zip_document_ajax.php', '文件')">
    <button type="button" class="btn btn-ghost" onclick="refreshDocumentCacheStats()" title="離線快取狀態">
        <i class="fa-solid fa-hard-drive"></i> <span id="documentCacheStatsLabel">快取</span>
    </button>

    <?php include 'includes/zip-preview.php'; ?>
    <?php include 'includes/batch-delete.php'; ?>

    <div class="desktop-only doc-viewbar" style="margin-top:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span style="font-size:0.85rem;color:#888;"><i class="fas fa-table-columns"></i> 檢視：</span>
        <button class="btn btn-sm document-view-btn active" data-view="list" onclick="setDocumentView('list')"><i class="fa-solid fa-list"></i> 列表</button>
        <button class="btn btn-sm document-view-btn" data-view="card" onclick="setDocumentView('card')"><i class="fa-solid fa-table-cells-large"></i> 格狀</button>
    </div>

    <!-- 分類篩選 -->
    <?php if (!empty($categories)): ?>
        <div style="margin-top:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="font-size:0.85rem;color:#888;"><i class="fas fa-filter"></i> 分類：</span>
            <button class="btn btn-sm category-filter-btn active" data-cat=""
                onclick="filterByCategory(this, '')">全部</button>
            <?php foreach ($categories as $cat): ?>
                <button class="btn btn-sm category-filter-btn" data-cat="<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>"
                    onclick="filterByCategory(this, '<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>') "><?php echo htmlspecialchars($cat); ?></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <style>
        .category-filter-btn {
            background: #f2f0e9;
            color: #555;
            border: none;
            border-radius: 20px;
            padding: 4px 14px;
            cursor: pointer;
            transition: all .2s;
        }

        .category-filter-btn.active {
            background: #d97757;
            color: #fff;
        }

        .category-filter-btn:hover:not(.active) {
            background: #d0e8f8;
            color: #2a2724;
        }

        .document-view-btn {
            background: #f2f0e9;
            color: #555;
            border: none;
            border-radius: 20px;
            padding: 4px 14px;
            cursor: pointer;
            transition: all .2s;
        }

        .document-view-btn.active {
            background: #2a2724;
            color: #fff;
        }

        .document-view-btn:hover:not(.active) {
            background: #dde6ed;
            color: #292826;
        }

        .document-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 18px;
            margin-top: 20px;
        }

        .document-card {
            background: #fff;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 10px 24px rgba(44, 62, 80, 0.08);
            border: 1px solid rgba(230, 126, 34, 0.12);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .document-card-thumb {
            width: 100%;
            height: 160px;
            border-radius: 12px;
            object-fit: cover;
            background: #f2f0e9;
        }

        .document-card-fallback {
            width: 100%;
            height: 160px;
            border-radius: 12px;
            background: #c07a3d;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .document-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .document-card-badge {
            font-size: 0.75rem;
            background: #fff3e8;
            color: #a85a2a;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 700;
        }

        .document-card-time {
            font-size: 0.8rem;
            color: #6f6c65;
        }

        .document-card-note {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 3.9em;
        }

        .document-card-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: auto;
        }

        /* ==========================================================
           鋒兄文件 · 三介面（Google 雲端硬碟 / MEGA / Dropbox）
           ========================================================== */
        .doc-header h1 { display: flex; align-items: center; gap: 10px; }

        .doc-count-pill {
            font-size: 0.55em;
            background: #c07a3d;
            color: #fff;
            padding: 3px 12px;
            border-radius: 999px;
            font-weight: 600;
        }

        .doc-experience {
            --doc-accent: #1a73e8;
            --doc-surface: #ffffff;
            --doc-surface-2: #f8f9fa;
            --doc-border: #dadce0;
            --doc-text: #202124;
            --doc-sub: #5f6368;
            --doc-radius: 8px;
        }

        .doc-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 4px 0 14px;
            margin-bottom: 14px;
            border-bottom: 1px solid var(--doc-border);
        }

        .doc-crumb {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 1.05rem;
            color: var(--doc-sub);
            min-width: 0;
        }

        .doc-crumb-root { display: inline-flex; align-items: center; gap: 8px; }
        .doc-crumb strong { color: var(--doc-text); font-weight: 600; }
        .doc-crumb-sep { font-size: 0.68rem; opacity: 0.55; }

        .doc-crumb-icon {
            font-family: "Font Awesome 6 Brands";
            font-weight: 400;
            font-style: normal;
            color: var(--doc-accent);
            font-size: 1.15rem;
        }

        .doc-crumb-icon::before { content: "\f3aa"; }

        .doc-ui-switch {
            display: inline-flex;
            gap: 4px;
            padding: 4px;
            border-radius: 999px;
            background: var(--doc-surface-2);
            border: 1px solid var(--doc-border);
        }

        .doc-ui-btn {
            border: none;
            background: transparent;
            color: var(--doc-sub);
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .doc-ui-btn:hover { background: rgba(0, 0, 0, 0.06); }

        .doc-ui-btn.is-active {
            background: var(--doc-surface);
            color: var(--doc-accent);
            font-weight: 700;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.14);
        }

        /* ---------- 空間列 ---------- */
        .doc-storage {
            padding: 14px 16px;
            border-radius: var(--doc-radius);
            background: var(--doc-surface-2);
            border: 1px solid var(--doc-border);
            margin-bottom: 16px;
        }

        .doc-storage-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 0.88rem;
            margin-bottom: 9px;
            color: var(--doc-text);
        }

        .doc-storage-title { color: var(--doc-sub); display: inline-flex; align-items: center; gap: 7px; }

        .doc-storage-bar {
            height: 6px;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .doc-storage-fill {
            height: 100%;
            border-radius: 999px;
            background: var(--doc-accent);
            transition: width 0.4s ease;
        }

        .doc-storage-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 8px;
            font-size: 0.78rem;
            color: var(--doc-sub);
        }

        /* ---------- 篩選 / 檢視鈕跟隨介面色 ---------- */
        .doc-experience .category-filter-btn {
            background: var(--doc-surface-2);
            color: var(--doc-sub);
            border: 1px solid var(--doc-border);
        }

        .doc-experience .category-filter-btn.active {
            background: var(--doc-accent);
            color: #fff;
            border-color: var(--doc-accent);
        }

        .doc-experience .category-filter-btn:hover:not(.active) {
            background: rgba(0, 0, 0, 0.06);
            color: var(--doc-text);
        }

        .doc-experience .document-view-btn {
            background: var(--doc-surface-2);
            color: var(--doc-sub);
            border: 1px solid var(--doc-border);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .doc-experience .document-view-btn.active {
            background: var(--doc-accent);
            color: #fff;
            border-color: var(--doc-accent);
        }

        /* ---------- 表格 ---------- */
        /* inline-edit.css 全域把 .inline-view 設成 block，會讓 <td class="inline-view"> 脫離表格排版 */
        .doc-experience table.table td.inline-view { display: table-cell; }

        .doc-experience table.table {
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--doc-border);
            border-radius: var(--doc-radius);
            overflow: hidden;
            background: var(--doc-surface);
        }

        .doc-experience table.table thead tr { background: var(--doc-surface-2); }

        .doc-experience table.table thead th {
            color: var(--doc-sub);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            border-bottom: 1px solid var(--doc-border);
        }

        .doc-experience table.table tbody tr:hover { background: var(--doc-surface-2); }
        .doc-experience table.table td { border-bottom: 1px solid var(--doc-border); color: var(--doc-text); }

        /* ---------- 卡片 ---------- */
        .doc-experience .document-card {
            background: var(--doc-surface);
            border: 1px solid var(--doc-border);
            border-radius: var(--doc-radius);
            box-shadow: none;
            transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
        }

        .doc-experience .document-card:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
            border-color: var(--doc-accent);
        }

        .doc-experience .document-card-thumb,
        .doc-experience .document-card-fallback { border-radius: calc(var(--doc-radius) - 2px); }
        .doc-experience .document-card-fallback { background: var(--doc-accent); }

        .doc-experience .document-card h3 { color: var(--doc-text) !important; }
        .doc-experience .document-card-note { color: var(--doc-sub); }

        .doc-experience .document-card-badge {
            background: color-mix(in srgb, var(--doc-accent) 14%, transparent);
            color: var(--doc-accent);
        }

        /* 主要動作鈕跟隨介面主色 */
        .doc-ui-drive .btn-primary,
        .doc-ui-dropbox .btn-primary {
            background: var(--doc-accent);
            border-color: var(--doc-accent);
            color: #fff;
        }

        .doc-ui-drive .btn-success,
        .doc-ui-dropbox .btn-success {
            background: transparent;
            border: 1px solid var(--doc-accent);
            color: var(--doc-accent);
        }

        .doc-ui-drive .btn-info,
        .doc-ui-dropbox .btn-info {
            background: var(--doc-surface-2);
            border: 1px solid var(--doc-border);
            color: var(--doc-text);
        }

        /* ============================================================
           MEGA
           ============================================================ */
        .doc-ui-mega {
            --doc-accent: #d9272e;
            --doc-surface: #2b2b2b;
            --doc-surface-2: #1f1f1f;
            --doc-border: rgba(255, 255, 255, 0.12);
            --doc-text: #f2f2f2;
            --doc-sub: #a8a8a8;
            --doc-radius: 6px;
            background: #171717;
            color: var(--doc-text);
            border-radius: 14px;
            padding: 20px 22px 30px;
        }

        .doc-ui-mega .doc-crumb-icon {
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
        }

        .doc-ui-mega .doc-crumb-icon::before { content: "\f0c2"; }

        .doc-ui-mega .doc-ui-btn.is-active { background: #3a3a3a; color: #ff5a5f; }
        .doc-ui-mega .doc-ui-btn:hover { background: rgba(255, 255, 255, 0.08); }

        .doc-ui-mega table.table tbody tr:hover { background: #333; }
        .doc-ui-mega table.table td,
        .doc-ui-mega table.table th { color: var(--doc-text); }

        .doc-ui-mega .document-card {
            border-top: 3px solid var(--doc-accent);
            background: var(--doc-surface);
        }

        .doc-ui-mega .document-card:hover { box-shadow: 0 10px 26px rgba(217, 39, 46, 0.28); }
        .doc-ui-mega .document-card-fallback { background: linear-gradient(135deg, #d9272e, #8b1216); }
        .doc-ui-mega .document-card-badge { background: rgba(217, 39, 46, 0.18); color: #ff8a8f; }
        .doc-ui-mega .document-card-time,
        .doc-ui-mega .document-card-note { color: var(--doc-sub); }

        .doc-ui-mega .btn {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.16);
        }

        .doc-ui-mega .btn-primary { background: var(--doc-accent); border-color: var(--doc-accent); }
        .doc-ui-mega .form-control {
            background: #333;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .doc-ui-mega .doc-storage-bar { background: rgba(255, 255, 255, 0.12); }
        .doc-ui-mega .mobile-card { background: var(--doc-surface); color: var(--doc-text); }

        /* ============================================================
           Dropbox
           ============================================================ */
        .doc-ui-dropbox {
            --doc-accent: #0061ff;
            --doc-surface: #ffffff;
            --doc-surface-2: #f7f9fc;
            --doc-border: #e6e8eb;
            --doc-text: #1e1919;
            --doc-sub: #637282;
            --doc-radius: 4px;
        }

        .doc-ui-dropbox .doc-crumb-icon::before { content: "\f16b"; }
        .doc-ui-dropbox .doc-crumb { font-size: 1.25rem; font-weight: 700; color: var(--doc-text); }
        .doc-ui-dropbox .doc-crumb-root-text { font-weight: 400; color: var(--doc-sub); }

        .doc-ui-dropbox .doc-ui-switch { border-radius: 4px; }
        .doc-ui-dropbox .doc-ui-btn { border-radius: 4px; }

        .doc-ui-dropbox table.table {
            border-left: none;
            border-right: none;
            border-radius: 0;
        }

        .doc-ui-dropbox table.table thead tr { background: transparent; }

        .doc-ui-dropbox table.table thead th {
            text-transform: none;
            font-size: 0.82rem;
            color: var(--doc-sub);
            padding-top: 6px;
            padding-bottom: 10px;
        }

        .doc-ui-dropbox table.table td { padding-top: 14px; padding-bottom: 14px; }
        .doc-ui-dropbox table.table tbody tr:hover { background: #f5f8ff; }

        .doc-ui-dropbox .document-card { box-shadow: none; border-radius: 4px; }
        .doc-ui-dropbox .document-card:hover { box-shadow: 0 2px 10px rgba(0, 97, 255, 0.14); }
        .doc-ui-dropbox .document-card-fallback { background: #0061ff; }
        .doc-ui-dropbox .document-card-badge { background: #e8f0ff; color: #0047c2; }
        .doc-ui-dropbox .doc-storage { background: #f7f9fc; }

        /* ---------- RWD ---------- */
        @media (max-width: 768px) {
            .doc-topbar { align-items: flex-start; flex-direction: column; }
            .doc-ui-switch { width: 100%; justify-content: space-between; }
            .doc-ui-btn { flex: 1; justify-content: center; padding: 8px 6px; }
            .doc-ui-btn span { display: none; }
            .doc-ui-btn i { font-size: 1.05rem; }
            .doc-ui-mega { padding: 14px 12px 24px; }
        }

    </style>

    <!-- 桌面版表格 -->
    <table class="table desktop-only" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAllCheckbox" class="select-checkbox"
                        onchange="toggleSelectAll(this)"></th>
                <th>名稱</th>
                <th>分類</th>
                <th>參考</th>
                <th>建立時間</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <tr id="inlineAddRow" class="inline-add-row">
                <td></td>
                <td colspan="5">
                    <div class="inline-edit inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="name" placeholder="名稱">
                        <input type="text" class="form-control inline-input" data-field="ref" placeholder="參考">
                        <textarea class="form-control inline-input" data-field="note" placeholder="備註" rows="3"
                            style="resize:vertical;"></textarea>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <input type="text" class="form-control inline-input" data-field="file" placeholder="檔案路徑"
                                style="flex:1;">
                            <button type="button" class="btn btn-secondary" style="white-space:nowrap;"
                                onclick="triggerFileUpload(this.closest('div').querySelector('[data-field=file]'))"><i
                                    class="fas fa-upload"></i></button>
                        </div>
                        <input type="text" class="form-control inline-input" data-field="cover" placeholder="封面圖網址">
                        <input type="text" class="form-control inline-input" data-field="category" placeholder="分類">
                        <div class="inline-actions">
                            <button type="button" class="btn btn-primary" onclick="saveInlineAdd()">儲存</button>
                            <button type="button" class="btn" onclick="cancelInlineAdd()">取消</button>
                        </div>
                    </div>
                </td>
            </tr>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #999;">暫無文件</td>
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
                        <td rowspan="<?php echo !empty($item['note']) ? 2 : 1; ?>">
                            <input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo $item['id']; ?>"
                                onchange="toggleSelectItem(this)">
                        </td>
                        <td>
                            <div class="inline-view" style="display:flex;align-items:center;gap:6px;">
                                <?php echo htmlspecialchars($item['name']); ?>
                                <span class="card-edit-btn" onclick="startInlineEdit('<?php echo $item['id']; ?>')"
                                    style="cursor: pointer; margin-left: 8px;"><i class="fas fa-pen"></i></span>
                                <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')"
                                    style="margin-left: 6px; cursor: pointer;">&times;</span>
                            </div>
                            <div class="inline-edit">
                                <input type="text" class="form-control inline-input" data-field="name" placeholder="名稱">
                                <input type="text" class="form-control inline-input" data-field="ref" placeholder="參考">
                                <textarea class="form-control inline-input" data-field="note" placeholder="備註" rows="3"
                                    style="resize:vertical;"></textarea>
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <input type="text" class="form-control inline-input" data-field="file" placeholder="檔案路徑"
                                        style="flex:1;">
                                    <button type="button" class="btn btn-secondary" style="white-space:nowrap;"
                                        onclick="triggerFileUpload(this.closest('div').querySelector('[data-field=file]'))"><i
                                            class="fas fa-upload"></i></button>
                                </div>
                                <input type="text" class="form-control inline-input" data-field="cover" placeholder="封面圖網址">
                                <input type="text" class="form-control inline-input" data-field="category" placeholder="分類">
                                <div class="inline-actions">
                                    <button type="button" class="btn btn-primary"
                                        onclick="saveInlineEdit('<?php echo $item['id']; ?>')">儲存</button>
                                    <button type="button" class="btn"
                                        onclick="cancelInlineEdit('<?php echo $item['id']; ?>')">取消</button>
                                </div>
                            </div>
                        </td>
                        <td class="inline-view"><?php echo htmlspecialchars($item['category'] ?? ''); ?></td>
                        <td class="inline-view"><?php echo htmlspecialchars($item['ref'] ?? ''); ?></td>
                        <td class="inline-view"><?php echo formatDateTime($item['created_at']); ?></td>
                        <td>
                            <div class="inline-view">
                                <?php if (!empty($item['file'])): ?>
                                    <button class="btn btn-sm btn-primary"
                                        onclick="previewDocument('<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['file']); ?>', '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')">
                                        <i class="fa-solid fa-eye"></i> 預覽
                                    </button>
                                    <button class="btn btn-sm btn-ghost document-cache-btn"
                                        data-cache-id="<?php echo htmlspecialchars($item['id']); ?>"
                                        onclick="cacheDocumentOffline('<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['file'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')"
                                        title="離線快取（上限 500MB）">
                                        <i class="fa-solid fa-cloud-arrow-down"></i>
                                    </button>
                                    <a href="<?php echo htmlspecialchars($item['file']); ?>" download="<?php
                                       $ext = pathinfo($item['file'], PATHINFO_EXTENSION);
                                       echo htmlspecialchars($item['name'] . ($ext ? '.' . $ext : ''), ENT_QUOTES);
                                       ?>" class="btn btn-sm btn-success">
                                        <i class="fa-solid fa-download"></i> 下載
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php if (!empty($item['note'])): ?>
                        <tr data-note-row="<?php echo $item['id']; ?>">
                            <td colspan="5" style="padding-top:2px;padding-bottom:8px;border-top:none;">
                                <div style="font-size:0.85rem;color:#666;white-space:pre-line;padding-left:4px;">
                                    <i class="fas fa-sticky-note" style="color:#aaa;font-size:0.75rem;"></i>
                                    <?php echo nl2br(htmlspecialchars($item['note'])); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div id="documentCardView" class="document-card-grid desktop-only" style="display:none;">
        <?php foreach ($items as $item): ?>
            <?php
                $fileExt = strtolower(pathinfo($item['file'] ?? '', PATHINFO_EXTENSION));
                $isImagePreview = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'], true);
            ?>
            <div class="document-card"
                data-id="<?php echo $item['id']; ?>"
                data-category="<?php echo htmlspecialchars($item['category'] ?? '', ENT_QUOTES); ?>">
                <?php if (!empty($item['cover'])): ?>
                    <img class="document-card-thumb" src="<?php echo htmlspecialchars($item['cover']); ?>" alt="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>">
                <?php elseif ($isImagePreview && !empty($item['file'])): ?>
                    <img class="document-card-thumb" src="<?php echo htmlspecialchars($item['file']); ?>" alt="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>">
                <?php else: ?>
                    <div class="document-card-fallback">
                        <i class="fa-solid fa-file-alt"></i>
                    </div>
                <?php endif; ?>

                <div>
                    <h3 style="margin:0 0 8px 0;color:#2a2724;line-height:1.4;"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <div class="document-card-meta">
                        <?php if (!empty($item['category'])): ?>
                            <span class="document-card-badge"><?php echo htmlspecialchars($item['category']); ?></span>
                        <?php endif; ?>
                        <span class="document-card-time"><?php echo formatDateTime($item['created_at']); ?></span>
                    </div>
                </div>

                <?php if (!empty($item['ref'])): ?>
                    <div style="font-size:0.85rem;color:#666;word-break:break-all;">
                        <i class="fas fa-link" style="color:#999;"></i> <?php echo htmlspecialchars($item['ref']); ?>
                    </div>
                <?php endif; ?>

                <div class="document-card-note"><?php echo htmlspecialchars($item['note'] ?? ''); ?></div>

                <div class="document-card-actions">
                    <?php if (!empty($item['file'])): ?>
                        <button class="btn btn-sm btn-primary"
                            onclick="previewDocument('<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['file']); ?>', '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')">
                            <i class="fa-solid fa-eye"></i> 預覽
                        </button>
                        <a href="<?php echo htmlspecialchars($item['file']); ?>" download="<?php
                           $ext = pathinfo($item['file'], PATHINFO_EXTENSION);
                           echo htmlspecialchars($item['name'] . ($ext ? '.' . $ext : ''), ENT_QUOTES);
                           ?>" class="btn btn-sm btn-success">
                            <i class="fa-solid fa-download"></i> 下載
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-warning" onclick="editItem('<?php echo $item['id']; ?>')">
                        <i class="fa-solid fa-pen"></i> 編輯
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteItem('<?php echo $item['id']; ?>')">
                        <i class="fa-solid fa-trash"></i> 刪除
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- 手機版卡片 -->
    <div class="mobile-only" style="margin-top: 20px;">
        <?php if (empty($items)): ?>
            <div class="mobile-card" style="text-align: center; color: #999; padding: 40px;">暫無文件</div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="mobile-card" data-id="<?php echo $item['id']; ?>"
                    data-category="<?php echo htmlspecialchars($item['category'] ?? '', ENT_QUOTES); ?>"
                    data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                    data-ref="<?php echo htmlspecialchars($item['ref'] ?? '', ENT_QUOTES); ?>"
                    data-note="<?php echo htmlspecialchars($item['note'] ?? '', ENT_QUOTES); ?>"
                    data-file="<?php echo htmlspecialchars($item['file'] ?? '', ENT_QUOTES); ?>"
                    data-cover="<?php echo htmlspecialchars($item['cover'] ?? '', ENT_QUOTES); ?>"
                    style="border-left: 4px solid #c07a3d;">
                    <div class="mobile-card-actions">
                        <?php if (!empty($item['file'])): ?>
                            <button class="btn btn-sm btn-primary"
                                onclick="previewDocument('<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['file']); ?>', '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')"
                                style="padding: 5px 10px;">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <a href="<?php echo htmlspecialchars($item['file']); ?>" download="<?php
                               $ext = pathinfo($item['file'], PATHINFO_EXTENSION);
                               echo htmlspecialchars($item['name'] . ($ext ? '.' . $ext : ''), ENT_QUOTES);
                               ?>" class="btn btn-sm btn-success" style="padding: 5px 10px;">
                                <i class="fa-solid fa-download"></i>
                            </a>
                        <?php endif; ?>
                        <span class="card-edit-btn" onclick="editItem('<?php echo $item['id']; ?>')"><i
                                class="fas fa-pen"></i></span>
                        <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                    </div>
                    <div class="mobile-card-header">
                        <div
                            style="width: 45px; height: 45px; background: #a85a2a; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-file-alt" style="color: #fff; font-size: 1.2rem;"></i>
                        </div>
                        <div style="flex: 1;">
                            <div class="mobile-card-title"><?php echo htmlspecialchars($item['name']); ?></div>
                            <?php if (!empty($item['category'])): ?>
                                <span
                                    style="font-size: 0.75rem; background: #ffeaa7; color: #a85a2a; padding: 2px 8px; border-radius: 10px;"><?php echo htmlspecialchars($item['category']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($item['ref'])): ?>
                        <div style="margin-top: 8px; font-size: 0.85rem; color: #666;">
                            <i class="fas fa-link" style="color: #999;"></i> <?php echo htmlspecialchars($item['ref']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($item['note'])): ?>
                        <div class="mobile-card-note" style="white-space:pre-line;">
                            <?php echo nl2br(htmlspecialchars($item['note'])); ?>
                        </div>
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
    const DOCUMENT_VIEW_STORAGE_KEY = 'documentViewMode';
    initBatchDelete(TABLE);

    function setDocumentView(view) {
        const table = document.querySelector('table.desktop-only');
        const cardView = document.getElementById('documentCardView');
        const isCard = view === 'card';

        if (table) table.style.display = isCard ? 'none' : 'table';
        if (cardView) cardView.style.display = isCard ? 'grid' : 'none';

        document.querySelectorAll('.document-view-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });

        try {
            localStorage.setItem(DOCUMENT_VIEW_STORAGE_KEY, view);
        } catch (_) {}
    }

    function filterByCategory(btn, cat) {
        document.querySelectorAll('.category-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        // 桌面版：篩選 tr（跳過新增列與 thead）
        document.querySelectorAll('table.desktop-only tbody tr[data-id]').forEach(tr => {
            const rowCat = tr.dataset.category || '';
            const visible = !cat || rowCat === cat;
            tr.style.display = visible ? '' : 'none';
            const noteRow = document.querySelector(`tr[data-note-row="${tr.dataset.id}"]`);
            if (noteRow) noteRow.style.display = visible ? '' : 'none';
        });
        document.querySelectorAll('#documentCardView .document-card[data-category]').forEach(card => {
            const cardCat = card.dataset.category || '';
            card.style.display = (!cat || cardCat === cat) ? '' : 'none';
        });
        // 手機版：篩選 mobile-card
        document.querySelectorAll('.mobile-only .mobile-card[data-category]').forEach(card => {
            const cardCat = card.dataset.category || '';
            card.style.display = (!cat || cardCat === cat) ? '' : 'none';
        });
    }


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
        const row = getRowById(id);
        if (!row) return;
        // 隱藏同一 tr 錄 inline-view 元素（含名稱 div 及分類/參考/時間 td）
        row.querySelectorAll('.inline-view').forEach(el => el.style.display = 'none');
        row.querySelectorAll('.inline-edit').forEach(el => el.style.display = 'block');
        // 隱藏備註列
        const noteRow = document.querySelector(`tr[data-note-row="${id}"]`);
        if (noteRow) noteRow.style.display = 'none';
        fillInlineInputs(row);
    }

    function cancelInlineEdit(id) {
        const row = getRowById(id);
        if (!row) return;
        row.querySelectorAll('.inline-view').forEach(el => el.style.display = '');
        row.querySelectorAll('.inline-edit').forEach(el => el.style.display = 'none');
        // 還原備註列
        const noteRow = document.querySelector(`tr[data-note-row="${id}"]`);
        if (noteRow) noteRow.style.display = '';
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
        deleteInlineItem(id, { table: TABLE });
    }

    // 手機版編輯 - 開啟 Modal
    function editItem(id) {
        const row = document.querySelector(`tr[data-id="${id}"]`);
        const data = row ? row.dataset : null;
        if (!data) {
            // 從 mobile-card 取得資料
            const card = document.querySelector(`.mobile-card[data-id="${id}"]`);
            if (!card) { alert('找不到資料'); return; }
        }
        // 優先從 tr[data-id] 取，若無就從 mobile-card[data-id]
        const srcEl = document.querySelector(`[data-id="${id}"]`);
        const d = srcEl ? srcEl.dataset : {};
        document.getElementById('mobileEditId').value = id;
        document.getElementById('mobileEditName').value = d.name || '';
        document.getElementById('mobileEditCategory').value = d.category || '';
        document.getElementById('mobileEditRef').value = d.ref || '';
        document.getElementById('mobileEditNote').value = d.note || '';
        document.getElementById('mobileEditFile').value = d.file || '';
        document.getElementById('mobileEditCover').value = d.cover || '';
        document.getElementById('mobileEditModal').style.display = 'flex';
    }

    function closeMobileEditModal() {
        document.getElementById('mobileEditModal').style.display = 'none';
    }

    function saveMobileEdit() {
        const id = document.getElementById('mobileEditId').value;
        const name = document.getElementById('mobileEditName').value.trim();
        if (!name) { alert('請輸入名稱'); return; }
        const data = {
            name,
            category: document.getElementById('mobileEditCategory').value.trim(),
            ref: document.getElementById('mobileEditRef').value.trim(),
            note: document.getElementById('mobileEditNote').value.trim(),
            file: document.getElementById('mobileEditFile').value.trim(),
            cover: document.getElementById('mobileEditCover').value.trim()
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

    function documentBaseName(filename) {
        return String(filename || '').replace(/\.[^.]+$/, '');
    }

    function uploadDocumentFile(file, options) {
        return new Promise((resolve, reject) => {
            uploadFileWithProgress(file, resolve, reject, options || {});
        });
    }

    function createDocumentRecord(data) {
        return fetch(`api.php?action=create&table=${TABLE}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json());
    }

    async function uploadMultipleDocuments(fileList) {
        const files = Array.from(fileList || []).filter(Boolean);
        if (!files.length) return;

        let successCount = 0;
        const failedFiles = [];
        const totalBytes = files.reduce((sum, file) => sum + (file.size || 0), 0);
        let completedBytes = 0;

        showUploadProgressModal(
            0,
            `0% (${successCount}/${files.length})`,
            `準備上傳 0 / ${files.length} 個檔案`,
            '多選文件上傳中...'
        );

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            try {
                const uploadRes = await uploadDocumentFile(file, {
                    showModal: false,
                    onProgress: function (progress) {
                        const aggregateLoaded = completedBytes + progress.loaded;
                        const aggregatePercent = totalBytes > 0
                            ? Math.round((aggregateLoaded / totalBytes) * 100)
                            : Math.round(((i + (progress.percent / 100)) / files.length) * 100);
                        showUploadProgressModal(
                            aggregatePercent,
                            `${aggregatePercent}% (${i + 1}/${files.length})`,
                            `第 ${i + 1} / ${files.length} 個：${file.name} (${progress.loadedText} / ${progress.totalText})`,
                            '多選文件上傳中...'
                        );
                    }
                });

                completedBytes += file.size || 0;
                const data = {
                    name: documentBaseName(uploadRes.filename || file.name) || '未命名文件',
                    file: uploadRes.file,
                    cover: '',
                    category: '',
                    ref: '',
                    note: ''
                };
                const createRes = await createDocumentRecord(data);
                if (!createRes.success) {
                    throw new Error(createRes.error || '建立文件資料失敗');
                }

                successCount++;
                const aggregatePercent = totalBytes > 0
                    ? Math.round((completedBytes / totalBytes) * 100)
                    : Math.round((successCount / files.length) * 100);
                showUploadProgressModal(
                    aggregatePercent,
                    `${aggregatePercent}% (${successCount}/${files.length})`,
                    `已完成 ${successCount} / ${files.length} 個檔案`,
                    '多選文件上傳中...'
                );
            } catch (error) {
                completedBytes += file.size || 0;
                failedFiles.push(`${file.name}: ${error && error.message ? error.message : error}`);
            }
        }

        hideUploadProgressModal();

        const input = document.getElementById('multiDocumentFiles');
        if (input) input.value = '';

        if (successCount > 0 && failedFiles.length === 0) {
            alert(`已完成上傳 ${successCount} 個文件`);
            location.reload();
            return;
        }

        if (successCount > 0) {
            alert(`成功 ${successCount} 個，失敗 ${failedFiles.length} 個\n${failedFiles.join('\n')}`);
            location.reload();
            return;
        }

        alert('多選文件上傳失敗\n' + failedFiles.join('\n'));
    }


    // 文件上傳功能
    (function () {
        const _uploadInput = document.createElement('input');
        _uploadInput.type = 'file';
        _uploadInput.multiple = true;
        _uploadInput.style.display = 'none';
        document.body.appendChild(_uploadInput);
        let _uploadTargetInput = null;

        _uploadInput.addEventListener('change', function () {
            const files = Array.from(this.files || []).filter(Boolean);
            if (!files.length) return;
            if (files.length > 1) {
                _uploadTargetInput = null;
                uploadMultipleDocuments(files);
                this.value = '';
                return;
            }

            const file = files[0];
            uploadFileWithProgress(file,
                function (res) {
                    if (_uploadTargetInput) {
                        _uploadTargetInput.value = res.file;
                        // 自動填入名稱（原始檔名去掉副檔名）
                        const baseName = (res.filename || file.name).replace(/\.[^/.]+$/, '');
                        const container = _uploadTargetInput.closest('.inline-edit, .modal-content');
                        if (container) {
                            const nameInput = container.querySelector('[data-field="name"], #mobileEditName');
                            if (nameInput && !nameInput.value.trim()) {
                                nameInput.value = baseName;
                            }
                        }
                        _uploadTargetInput = null;
                    }
                },
                function (err) {
                    alert('上傳失敗: ' + err);
                }
            );
            this.value = '';
        });

        window.triggerFileUpload = function (targetInput) {
            _uploadTargetInput = targetInput;
            _uploadInput.click();
        };
    })();

    // 手機版 Modal 上傳
    window.triggerMobileFileUpload = function () {
        const targetInput = document.getElementById('mobileEditFile');
        window.triggerFileUpload(targetInput);
    };

    async function resolveDocumentSrc(id, fallbackSrc) {
        if (!window.FengbroMediaCache || !id) return fallbackSrc;
        try {
            const objectUrl = await window.FengbroMediaCache.getObjectUrl('document', id);
            return objectUrl || fallbackSrc;
        } catch (e) {
            return fallbackSrc;
        }
    }

    async function refreshDocumentCacheStats() {
        const label = document.getElementById('documentCacheStatsLabel');
        if (!window.FengbroMediaCache) {
            if (label) label.textContent = '快取不可用';
            return;
        }
        try {
            const stats = await window.FengbroMediaCache.getStats('document');
            if (label) {
                label.textContent = window.FengbroMediaCache.formatBytes(stats.totalSize) + ' / 500MB · ' + stats.totalItems;
            }
            document.querySelectorAll('.document-cache-btn[data-cache-id]').forEach(async function (btn) {
                const id = btn.getAttribute('data-cache-id');
                const cached = await window.FengbroMediaCache.isCached('document', id);
                btn.classList.toggle('btn-success', cached);
                btn.innerHTML = cached
                    ? '<i class="fa-solid fa-check"></i>'
                    : '<i class="fa-solid fa-cloud-arrow-down"></i>';
            });
        } catch (e) {
            if (label) label.textContent = '快取';
        }
    }

    async function cacheDocumentOffline(id, filePath, title) {
        if (!window.FengbroMediaCache) {
            alert('瀏覽器不支援離線快取');
            return;
        }
        if (!id || !filePath) {
            alert('找不到可快取的檔案');
            return;
        }
        const btn = document.querySelector('.document-cache-btn[data-cache-id="' + id + '"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        }
        try {
            await window.FengbroMediaCache.cacheMedia('document', {
                id: id,
                title: title || id,
                url: filePath
            }, function (progress) {
                if (btn) btn.innerHTML = progress + '%';
            });
            await refreshDocumentCacheStats();
            alert('已快取到本機，可離線預覽');
        } catch (err) {
            alert('快取失敗：' + (err.message || err));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down"></i>';
            }
        }
    }

    // 文件預覽功能（優先使用 IndexedDB 離線快取）
    async function previewDocument(id, filePath, title) {
        const src = await resolveDocumentSrc(id, filePath);
        const offline = src !== filePath;
        const ext = filePath.split('.').pop().toLowerCase();
        const previewModal = document.getElementById('previewModal');
        const previewTitle = document.getElementById('previewTitle');
        const previewContent = document.getElementById('previewContent');
        const downloadBtn = document.getElementById('previewDownloadBtn');

        previewTitle.textContent = title + (offline ? ' · Offline' : '');
        downloadBtn.href = filePath;
        downloadBtn.download = filePath.split('/').pop();
        downloadBtn.style.display = '';
        previewContent.innerHTML = '<div style="text-align:center;padding:50px;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>載入中...</div>';
        previewModal.style.display = 'flex';

        // 根據檔案類型顯示不同預覽
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(ext)) {
            previewContent.innerHTML = `<img src="${src}" style="max-width:100%;max-height:70vh;border-radius:8px;">`;
        } else if (ext === 'pdf') {
            previewContent.innerHTML = `<iframe src="${src}" style="width:100%;height:70vh;border:none;border-radius:8px;"></iframe>`;
        } else if (['pptx', 'ppt', 'docx', 'doc', 'xlsx', 'xls'].includes(ext)) {
            const iconClass = ext.includes('ppt') ? 'fa-file-powerpoint' : (ext.includes('doc') ? 'fa-file-word' : 'fa-file-excel');
            const iconColor = ext.includes('ppt') ? '#c07a3d' : (ext.includes('doc') ? '#d97757' : '#4a8f63');
            previewContent.innerHTML = `
                <div style="text-align:center;padding:50px;">
                    <i class="fa-solid ${iconClass} fa-5x" style="color:${iconColor};margin-bottom:25px;"></i>
                    <h3 style="margin-bottom:15px;">${title}</h3>
                    <p style="color:#888;margin-bottom:25px;">Office 文件需要下載後使用本機軟體開啟${offline ? '（已離線快取）' : ''}</p>
                    <a href="${filePath}" download class="btn btn-primary" style="font-size:1.1rem;padding:12px 30px;">
                        <i class="fa-solid fa-download"></i> 下載檔案
                    </a>
                </div>
            `;
        } else if (['mp4', 'webm', 'ogg', 'mov'].includes(ext)) {
            previewContent.innerHTML = `<video src="${src}" controls style="max-width:100%;max-height:70vh;border-radius:8px;"></video>`;
        } else if (['mp3', 'wav', 'm4a', 'ogg', 'flac'].includes(ext)) {
            previewContent.innerHTML = `<audio src="${src}" controls style="width:100%;"></audio>`;
        } else if (['txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php', 'py', 'sql', 'csv', 'log', 'vsc'].includes(ext)) {
            fetch(src)
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
                    previewContent.innerHTML = `<p style="color:#c1554a;">無法載入檔案內容</p>`;
                });
        } else {
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
        document.getElementById('previewDownloadBtn').style.display = 'none';
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

    window.batchCacheSelectedItems = async function (ids) {
        if (!window.FengbroMediaCache) throw new Error('瀏覽器不支援離線快取');
        if (!ids || !ids.length) return;
        let ok = 0, fail = 0;
        for (let i = 0; i < ids.length; i++) {
            const id = ids[i];
            const row = document.querySelector('tr[data-id="' + id + '"], .card[data-id="' + id + '"]');
            const filePath = row ? (row.dataset.file || '') : '';
            const title = row ? (row.dataset.name || id) : id;
            if (!filePath) { fail++; continue; }
            try {
                await window.FengbroMediaCache.cacheMedia('document', {
                    id: id,
                    title: title,
                    url: filePath
                });
                ok++;
            } catch (e) {
                fail++;
            }
        }
        await refreshDocumentCacheStats();
        alert('批次快取完成：成功 ' + ok + ' 筆' + (fail ? '，失敗 ' + fail + ' 筆' : ''));
    };

    document.addEventListener('DOMContentLoaded', function () {
        let preferredView = 'list';
        try {
            preferredView = localStorage.getItem(DOCUMENT_VIEW_STORAGE_KEY) || 'list';
        } catch (_) {}
        setDocumentView(preferredView === 'card' ? 'card' : 'list');
        refreshDocumentCacheStats();
        if (typeof enableBatchCacheButton === 'function') {
            enableBatchCacheButton(true);
        }
    });
    /* ---------- 三介面切換（Google 雲端硬碟 / MEGA / Dropbox） ---------- */
    const DOCUMENT_UI_STORAGE_KEY = 'documentInterfaceMode';
    const DOCUMENT_UI_PRESETS = {
        drive: { root: '我的雲端硬碟', view: 'list' },
        mega: { root: '雲端空間', view: 'card' },
        dropbox: { root: '所有檔案', view: 'list' }
    };

    function setDocumentInterface(mode, keepView) {
        const ui = DOCUMENT_UI_PRESETS[mode] ? mode : 'drive';
        const shell = document.getElementById('docExperience');
        if (shell) {
            Object.keys(DOCUMENT_UI_PRESETS).forEach(function (key) {
                shell.classList.toggle('doc-ui-' + key, key === ui);
            });
        }
        document.querySelectorAll('.doc-ui-btn').forEach(function (btn) {
            const on = btn.dataset.ui === ui;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        const rootText = document.querySelector('.doc-crumb-root-text');
        if (rootText) rootText.textContent = DOCUMENT_UI_PRESETS[ui].root;
        try { localStorage.setItem(DOCUMENT_UI_STORAGE_KEY, ui); } catch (_) {}
        if (!keepView && typeof setDocumentView === 'function') {
            setDocumentView(DOCUMENT_UI_PRESETS[ui].view);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        let savedUi = 'drive';
        try { savedUi = localStorage.getItem(DOCUMENT_UI_STORAGE_KEY) || 'drive'; } catch (_) {}
        setDocumentInterface(savedUi, true);
    });

</script>

<!-- 文件預覽彈窗 -->
<div id="previewModal" class="modal" onclick="if(event.target===this)closePreviewModal()">
    <div class="modal-content" style="max-width:900px;width:95%;">
        <span class="modal-close" onclick="closePreviewModal()">&times;</span>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <h2 id="previewTitle" style="margin:0;flex:1;">文件預覽</h2>
            <a id="previewDownloadBtn" href="#" download class="btn btn-success" style="display:none;">
                <i class="fa-solid fa-download"></i> 下載
            </a>
        </div>
        <div id="previewContent" style="margin-top:20px;"></div>
    </div>
</div>

<!-- 手機版編輯 Modal -->
<div id="mobileEditModal" class="modal" onclick="if(event.target===this)closeMobileEditModal()" style="display:none;">
    <div class="modal-content" style="max-width:500px;width:95%;">
        <span class="modal-close" onclick="closeMobileEditModal()">&times;</span>
        <h2 style="margin:0 0 20px 0;"><i class="fas fa-edit"></i> 編輯文件</h2>
        <input type="hidden" id="mobileEditId">
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div>
                <label style="font-size:0.85rem;color:#666;margin-bottom:4px;display:block;">名稱 *</label>
                <input type="text" id="mobileEditName" class="form-control" placeholder="名稱">
            </div>
            <div>
                <label style="font-size:0.85rem;color:#666;margin-bottom:4px;display:block;">分類</label>
                <input type="text" id="mobileEditCategory" class="form-control" placeholder="分類">
            </div>
            <div>
                <label style="font-size:0.85rem;color:#666;margin-bottom:4px;display:block;">參考</label>
                <input type="text" id="mobileEditRef" class="form-control" placeholder="參考">
            </div>
            <div>
                <label style="font-size:0.85rem;color:#666;margin-bottom:4px;display:block;">備註</label>
                <textarea id="mobileEditNote" class="form-control" placeholder="備註" rows="3"
                    style="resize:vertical;"></textarea>
            </div>
            <div>
                <label style="font-size:0.85rem;color:#666;margin-bottom:4px;display:block;">檔案</label>
                <div style="display:flex;gap:6px;align-items:center;">
                    <input type="text" id="mobileEditFile" class="form-control" placeholder="檔案路徑" style="flex:1;">
                    <button type="button" class="btn btn-secondary" style="white-space:nowrap;"
                        onclick="triggerMobileFileUpload()">
                        <i class="fas fa-upload"></i>
                    </button>
                </div>
            </div>
            <div>
                <label style="font-size:0.85rem;color:#666;margin-bottom:4px;display:block;">封面圖網址</label>
                <input type="text" id="mobileEditCover" class="form-control" placeholder="封面圖網址">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
                <button type="button" class="btn" onclick="closeMobileEditModal()">取消</button>
                <button type="button" class="btn btn-primary" onclick="saveMobileEdit()"><i class="fas fa-save"></i>
                    儲存</button>
            </div>
        </div>
    </div>
</div>
