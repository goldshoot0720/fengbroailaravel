<?php
$pageTitle = '筆記本';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM article ORDER BY created_at DESC")->fetchAll();

function parseNoteCategories($value)
{
    $parts = preg_split('/\s*,\s*/', (string) $value);
    $parts = array_filter(array_map('trim', $parts), fn($item) => $item !== '');
    return array_values(array_unique($parts));
}

$categories = [];
$hasUncategorized = false;
foreach ($items as $item) {
    $itemCategories = parseNoteCategories($item['category'] ?? '');
    if (!empty($itemCategories)) {
        foreach ($itemCategories as $category) {
            $categories[$category] = true;
        }
    } else {
        $hasUncategorized = true;
    }
}
$categories = array_keys($categories);
sort($categories);
?>

<div class="content-header notes-header">
    <div class="notes-header-main">
        <div class="notes-title-wrap">
            <h1 class="notes-title">鋒兄筆記</h1>
            <span class="notes-count-pill"><?php echo count($items); ?> 篇</span>
        </div>
        <p class="notes-subtitle">記錄想法、參考資料與附件內容，並支援批次分類整理。</p>
    </div>
    <div class="notes-filter-wrap">
        <i class="fas fa-layer-group"></i>
        <select id="categoryFilter" class="form-control notes-filter-select" onchange="filterNotes()">
            <option value="__all">全部分類</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?>
                </option>
            <?php endforeach; ?>
            <?php if ($hasUncategorized): ?>
                <option value="__uncategorized">未分類筆記</option>
            <?php endif; ?>
        </select>
    </div>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>

    <div class="notes-search-toolbar">
        <label class="notes-search-box" for="noteSearchInput">
            <i class="fas fa-search"></i>
            <input type="text" id="noteSearchInput" class="form-control" placeholder="搜尋標題、內容、分類或參考..."
                oninput="filterNotes()">
        </label>
        <div class="notes-toolbar-actions">
            <a href="export_zip_article.php" class="btn btn-success" title="匯出 Appwrite ZIP（含 CSV + 檔案）">
                <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
            </a>
            <button type="button" class="btn" onclick="document.getElementById('zipImportArticle').click()"
                title="匯入 Appwrite ZIP（含 CSV + 檔案）">
                <i class="fa-solid fa-file-zipper"></i> 匯入 ZIP
            </button>
            <input type="file" id="zipImportArticle" accept=".zip" style="display: none;"
                onchange="previewAndImportZIP(this, 'article', 'import_zip_article.php', '筆記')">
        </div>
    </div>

    <!-- 操作按鈕區 -->
    <div class="action-buttons">
        <div class="notes-primary-actions">
            <?php include 'includes/batch-delete.php'; ?>
            <button class="btn btn-primary notes-add-btn" onclick="handleAdd()" title="新增筆記">
                <i class="fas fa-plus"></i>
            </button>
            <span class="notes-total-count">共 <?php echo count($items); ?> 篇筆記</span>
        </div>
    </div>

    <datalist id="categoryOptions">
        <?php foreach ($categories as $category): ?>
            <option value="<?php echo htmlspecialchars($category); ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <div id="batchCategoryBar" class="batch-category-bar">
        <div class="batch-category-meta">
            <label class="batch-category-check">
                <input type="checkbox" id="batchCategorySelectAll" onchange="toggleSelectAll(this)">
                <span>全選 <strong id="batchCategoryCount">0</strong> 篇</span>
            </label>
        </div>
        <div class="batch-category-controls">
            <div class="category-picker batch-category-picker" data-category-picker>
                <input type="hidden" id="batchCategoryValue">
                <div class="category-selected-list" data-role="selected"></div>
                <div class="category-input-row">
                    <input type="text" class="form-control category-entry-input" list="categoryOptions"
                        placeholder="輸入要套用的分類">
                    <button type="button" class="btn btn-sm btn-primary" onclick="applyCategoryToSelected()">套用分類</button>
                    <button type="button" class="btn btn-sm" onclick="clearCategoriesFromSelected()">清除分類</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-grid" style="margin-top: 20px;">
        <div id="inlineAddCard" class="card inline-add-card">
            <div class="inline-edit inline-edit-always">
                <div class="form-group">
                    <label>標題 *</label>
                    <input type="text" class="form-control inline-input" data-field="title">
                </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>分類</label>
                    <div class="category-picker" data-category-picker>
                        <input type="hidden" class="inline-input" data-field="category">
                        <div class="category-selected-list" data-role="selected"></div>
                        <div class="category-input-row">
                            <input type="text" class="form-control category-entry-input" list="categoryOptions"
                                placeholder="輸入分類後按 Enter">
                            <button type="button" class="btn btn-sm" onclick="addCategoryFromPicker(this)">加入</button>
                        </div>
                        <?php if (!empty($categories)): ?>
                            <div class="category-option-group">
                                <?php foreach ($categories as $category): ?>
                                    <button type="button" class="category-option-chip"
                                        onclick="toggleCategoryOption(this, '<?php echo htmlspecialchars($category, ENT_QUOTES); ?>')"><?php echo htmlspecialchars($category); ?></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                    <div class="form-group" style="flex:1">
                        <label>參考</label>
                        <input type="text" class="form-control inline-input" data-field="ref">
                    </div>
                </div>
                <div class="form-group">
                    <label>內容</label>
                    <textarea class="form-control inline-input" data-field="content" rows="6"></textarea>
                </div>
                <div class="form-group">
                    <label>連結 1</label>
                    <input type="url" class="form-control inline-input" data-field="url1">
                </div>
                <div class="form-group">
                    <label>連結 2</label>
                    <input type="url" class="form-control inline-input" data-field="url2">
                </div>
                <div class="form-group">
                    <label>連結 3</label>
                    <input type="url" class="form-control inline-input" data-field="url3">
                </div>
                <?php for ($fi = 1; $fi <= 3; $fi++): ?>
                <div class="form-group">
                    <label>附件 <?php echo $fi; ?></label>
                    <div id="inlineFile<?php echo $fi; ?>Preview-add" style="margin-bottom:4px;font-size:0.85rem;color:#666;"></div>
                    <input type="file" class="form-control"
                        id="inlineFileInput<?php echo $fi; ?>-add"
                        onchange="inlineUploadFile(<?php echo $fi; ?>, 'add')">
                    <input type="hidden" id="inlineFile<?php echo $fi; ?>Val-add">
                    <input type="hidden" id="inlineFile<?php echo $fi; ?>Name-add">
                    <input type="hidden" id="inlineFile<?php echo $fi; ?>Type-add">
                </div>
                <?php endfor; ?>
                <div class="inline-actions">
                    <button type="button" class="btn btn-primary" onclick="saveInlineAdd()">儲存</button>
                    <button type="button" class="btn" onclick="cancelInlineAdd()">取消</button>
                </div>
            </div>
        </div>
        <?php if (empty($items)): ?>
            <div class="card">
                <p style="text-align: center; color: #999;">暫無筆記資料</p>
            </div>
        <?php else: ?>
            <?php
            // 分類色彩對應
            $categoryColors = [
                '#3498db',
                '#e74c3c',
                '#2ecc71',
                '#f39c12',
                '#9b59b6',
                '#1abc9c',
                '#e67e22',
                '#34495e',
                '#16a085',
                '#c0392b',
                '#8e44ad',
                '#d35400',
                '#27ae60',
                '#2980b9',
                '#f1c40f'
            ];
            $categoryColorMap = [];
            $colorIdx = 0;
            foreach ($categories as $cat) {
                $categoryColorMap[$cat] = $categoryColors[$colorIdx % count($categoryColors)];
                $colorIdx++;
            }
            ?>
            <?php foreach ($items as $item):
                $cardCategories = parseNoteCategories($item['category'] ?? '');
                $primaryCategory = $cardCategories[0] ?? '';
                $catColor = $categoryColorMap[$primaryCategory] ?? '#95a5a6';
                $hasRef = !empty($item['ref']);
                $hasUrls = !empty($item['url1']) || !empty($item['url2']) || !empty($item['url3']);
                ?>
                <div class="card note-card"
                    data-categories="<?php echo htmlspecialchars(!empty($cardCategories) ? '|' . implode('|', $cardCategories) . '|' : '__uncategorized', ENT_QUOTES); ?>"
                    data-id="<?php echo $item['id']; ?>"
                    data-title="<?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES); ?>"
                    data-category-value="<?php echo htmlspecialchars($item['category'] ?? '', ENT_QUOTES); ?>"
                    data-ref="<?php echo htmlspecialchars($item['ref'] ?? '', ENT_QUOTES); ?>"
                    data-content="<?php echo htmlspecialchars($item['content'] ?? '', ENT_QUOTES); ?>"
                    data-url1="<?php echo htmlspecialchars($item['url1'] ?? '', ENT_QUOTES); ?>"
                    data-url2="<?php echo htmlspecialchars($item['url2'] ?? '', ENT_QUOTES); ?>"
                    data-url3="<?php echo htmlspecialchars($item['url3'] ?? '', ENT_QUOTES); ?>"
                    data-file1="<?php echo htmlspecialchars($item['file1'] ?? '', ENT_QUOTES); ?>"
                    data-file1name="<?php echo htmlspecialchars($item['file1name'] ?? '', ENT_QUOTES); ?>"
                    data-file1type="<?php echo htmlspecialchars($item['file1type'] ?? '', ENT_QUOTES); ?>"
                    data-file2="<?php echo htmlspecialchars($item['file2'] ?? '', ENT_QUOTES); ?>"
                    data-file2name="<?php echo htmlspecialchars($item['file2name'] ?? '', ENT_QUOTES); ?>"
                    data-file2type="<?php echo htmlspecialchars($item['file2type'] ?? '', ENT_QUOTES); ?>"
                    data-file3="<?php echo htmlspecialchars($item['file3'] ?? '', ENT_QUOTES); ?>"
                    data-file3name="<?php echo htmlspecialchars($item['file3name'] ?? '', ENT_QUOTES); ?>"
                    data-file3type="<?php echo htmlspecialchars($item['file3type'] ?? '', ENT_QUOTES); ?>"
                    data-search="<?php echo htmlspecialchars(mb_strtolower(($item['title'] ?? '') . ' ' . ($item['content'] ?? '') . ' ' . ($item['category'] ?? '') . ' ' . ($item['ref'] ?? '')), ENT_QUOTES); ?>"
                    style="border-left: 4px solid <?php echo $catColor; ?>;">

                    <!-- 卡片頂部：checkbox + 操作 -->
                    <div class="card-header">
                        <input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo $item['id']; ?>"
                            onchange="toggleSelectItem(this)">
                        <div class="card-actions">
                            <span class="card-edit-btn" onclick="startInlineEdit('<?php echo $item['id']; ?>')"><i
                                    class="fas fa-pen"></i></span>
                            <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                        </div>
                    </div>

                    <!-- 分類標籤 -->
                    <div class="note-category-badges">
                        <?php if (!empty($cardCategories)): ?>
                            <?php foreach ($cardCategories as $category): ?>
                                <?php $badgeColor = $categoryColorMap[$category] ?? '#95a5a6'; ?>
                                <div class="note-category-badge"
                                    style="background: <?php echo $badgeColor; ?>15; color: <?php echo $badgeColor; ?>; border: 1px solid <?php echo $badgeColor; ?>40;">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($category); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="note-category-badge"
                                style="background: <?php echo $catColor; ?>15; color: <?php echo $catColor; ?>; border: 1px solid <?php echo $catColor; ?>40;">
                                <i class="fas fa-tag"></i> 未分類
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 標題 -->
                    <h3 class="note-title"><?php echo htmlspecialchars($item['title']); ?></h3>

                    <!-- 參考來源 -->
                    <?php if ($hasRef): ?>
                        <div class="note-ref">
                            <i class="fas fa-bookmark"></i> <?php echo htmlspecialchars($item['ref']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- 內容區 -->
                    <?php
                    $content = $item['content'] ?? '';
                    $shortContent = mb_substr($content, 0, 120);
                    $isLongContent = mb_strlen($content) > 120;
                    ?>
                    <?php if ($content): ?>
                        <div class="note-content">
                            <div id="contentShort-<?php echo $item['id']; ?>" class="note-text">
                                <?php echo nl2br(htmlspecialchars($shortContent)); ?>            <?php echo $isLongContent ? '...' : ''; ?>
                            </div>
                            <div id="contentFull-<?php echo $item['id']; ?>" class="note-text" style="display: none;">
                                <?php echo nl2br(htmlspecialchars($content)); ?></div>
                            <textarea id="contentRaw-<?php echo $item['id']; ?>"
                                style="display: none;"><?php echo htmlspecialchars($content); ?></textarea>
                        </div>
                    <?php endif; ?>

                    <!-- 連結區 -->
                    <?php if ($hasUrls): ?>
                        <div class="note-links">
                            <?php for ($u = 1; $u <= 3; $u++): ?>
                                <?php if (!empty($item["url{$u}"])): ?>
                                    <?php $linkDomain = parse_url($item["url{$u}"], PHP_URL_HOST); ?>
                                    <a href="<?php echo htmlspecialchars($item["url{$u}"]); ?>" target="_blank" class="note-link">
                                        <img src="https://www.google.com/s2/favicons?domain=<?php echo $linkDomain; ?>&sz=16"
                                            style="width: 16px; height: 16px; vertical-align: middle;"
                                            onerror="this.style.display='none'">
                                        <?php echo htmlspecialchars($linkDomain ?: "連結 {$u}"); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                    <!-- 附件區 -->
                    <?php
                    $hasFiles = false;
                    for ($i = 1; $i <= 3; $i++) {
                        if (!empty($item["file{$i}"])) {
                            $hasFiles = true;
                            break;
                        }
                    }
                    ?>
                    <?php if ($hasFiles): ?>
                        <div class="note-files">
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                <?php if (!empty($item["file{$i}"])): ?>
                                    <?php
                                    $filetype = $item["file{$i}type"] ?? '';
                                    $filename = $item["file{$i}name"] ?? '檔案';
                                    $filepath = $item["file{$i}"];
                                    ?>
                                    <div class="note-file-item">
                                        <a href="<?php echo htmlspecialchars($filepath); ?>" target="_blank" class="note-file-thumb"
                                            title="<?php echo htmlspecialchars($filename); ?>">
                                            <?php if (strpos($filetype, 'image/') === 0): ?>
                                                <img src="<?php echo htmlspecialchars($filepath); ?>"
                                                    alt="<?php echo htmlspecialchars($filename); ?>">
                                            <?php else: ?>
                                                <?php
                                                $iconClass = 'fa-file';
                                                $iconBg = '#3498db';
                                                if (strpos($filetype, 'video/') === 0) {
                                                    $iconClass = 'fa-video';
                                                    $iconBg = '#34495e';
                                                } elseif (strpos($filetype, 'audio/') === 0) {
                                                    $iconClass = 'fa-music';
                                                    $iconBg = '#9b59b6';
                                                } elseif ($filetype === 'application/pdf') {
                                                    $iconClass = 'fa-file-pdf';
                                                    $iconBg = '#e74c3c';
                                                }
                                                ?>
                                                <div class="note-file-icon" style="background: <?php echo $iconBg; ?>;">
                                                    <i class="fa-solid <?php echo $iconClass; ?>"></i>
                                                </div>
                                            <?php endif; ?>
                                            <span class="note-file-name"><?php echo htmlspecialchars(mb_substr($filename, 0, 12)); ?></span>
                                        </a>
                                        <a href="<?php echo htmlspecialchars($filepath); ?>"
                                            download="<?php echo htmlspecialchars($filename); ?>" class="note-file-download"
                                            title="下載 <?php echo htmlspecialchars($filename); ?>">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                    <!-- 底部：操作 + 時間 -->
                    <div class="note-footer">
                        <div class="note-actions-row">
                            <?php if ($isLongContent): ?>
                                <button id="contentToggle-<?php echo $item['id']; ?>" type="button" class="note-action-btn"
                                    onclick="toggleNoteContent('<?php echo $item['id']; ?>')">
                                    <i class="fas fa-expand-alt"></i> 展開
                                </button>
                            <?php endif; ?>
                            <?php if ($content): ?>
                                <button type="button" class="note-action-btn"
                                    onclick="copyNoteContent('<?php echo $item['id']; ?>')">
                                    <i class="fas fa-copy"></i> 複製
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="note-time">
                            <i class="far fa-clock"></i> <?php echo formatDateTime($item['created_at']); ?>
                        </div>
                    </div>

                    <!-- inline edit (hidden) -->
                    <div class="inline-edit">
                        <div class="form-group">
                            <label>標題 *</label>
                            <input type="text" class="form-control inline-input" data-field="title">
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="flex:1">
                                <label>分類</label>
                                <div class="category-picker" data-category-picker>
                                    <input type="hidden" class="inline-input" data-field="category">
                                    <div class="category-selected-list" data-role="selected"></div>
                                    <div class="category-input-row">
                                        <input type="text" class="form-control category-entry-input" list="categoryOptions"
                                            placeholder="輸入分類後按 Enter">
                                        <button type="button" class="btn btn-sm" onclick="addCategoryFromPicker(this)">加入</button>
                                    </div>
                                    <?php if (!empty($categories)): ?>
                                        <div class="category-option-group">
                                            <?php foreach ($categories as $category): ?>
                                                <button type="button" class="category-option-chip"
                                                    onclick="toggleCategoryOption(this, '<?php echo htmlspecialchars($category, ENT_QUOTES); ?>')"><?php echo htmlspecialchars($category); ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="form-group" style="flex:1">
                                <label>參考</label>
                                <input type="text" class="form-control inline-input" data-field="ref">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>內容</label>
                            <textarea class="form-control inline-input" data-field="content" rows="6"></textarea>
                        </div>
                        <div class="form-group">
                            <label>連結 1</label>
                            <input type="url" class="form-control inline-input" data-field="url1">
                        </div>
                        <div class="form-group">
                            <label>連結 2</label>
                            <input type="url" class="form-control inline-input" data-field="url2">
                        </div>
                        <div class="form-group">
                            <label>連結 3</label>
                            <input type="url" class="form-control inline-input" data-field="url3">
                        </div>
                        <?php for ($fi = 1; $fi <= 3; $fi++): ?>
                            <div class="form-group">
                                <label>附件 <?php echo $fi; ?></label>
                                <div id="inlineFile<?php echo $fi; ?>Preview-<?php echo $item['id']; ?>"
                                    style="margin-bottom:4px;font-size:0.85rem;color:#666;"></div>
                                <input type="file" class="form-control"
                                    id="inlineFileInput<?php echo $fi; ?>-<?php echo $item['id']; ?>"
                                    onchange="inlineUploadFile(<?php echo $fi; ?>, '<?php echo $item['id']; ?>')">
                                <input type="hidden" data-field="file<?php echo $fi; ?>"
                                    id="inlineFile<?php echo $fi; ?>Val-<?php echo $item['id']; ?>">
                                <input type="hidden" data-field="file<?php echo $fi; ?>name"
                                    id="inlineFile<?php echo $fi; ?>Name-<?php echo $item['id']; ?>">
                                <input type="hidden" data-field="file<?php echo $fi; ?>type"
                                    id="inlineFile<?php echo $fi; ?>Type-<?php echo $item['id']; ?>">
                            </div>
                        <?php endfor; ?>
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

<div id="modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">新增筆記</h2>
        <form id="itemForm">
            <input type="hidden" id="itemId" name="id">
            <div class="form-group">
                <label>標題 *</label>
                <input type="text" class="form-control" id="title" name="title" required>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>分類</label>
                    <div class="category-picker" data-category-picker>
                        <input type="hidden" class="form-control" id="category" name="category">
                        <div class="category-selected-list" data-role="selected"></div>
                        <div class="category-input-row">
                            <input type="text" class="form-control category-entry-input" list="categoryOptions"
                                placeholder="輸入分類後按 Enter">
                            <button type="button" class="btn btn-sm" onclick="addCategoryFromPicker(this)">加入</button>
                        </div>
                        <?php if (!empty($categories)): ?>
                            <div class="category-option-group">
                                <?php foreach ($categories as $category): ?>
                                    <button type="button" class="category-option-chip"
                                        onclick="toggleCategoryOption(this, '<?php echo htmlspecialchars($category, ENT_QUOTES); ?>')"><?php echo htmlspecialchars($category); ?></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group" style="flex:1">
                    <label>參考</label>
                    <input type="text" class="form-control" id="ref" name="ref">
                </div>
            </div>
            <div class="form-group">
                <label>內容</label>
                <textarea class="form-control" id="content" name="content" rows="6"></textarea>
            </div>
            <div class="form-group">
                <label>連結 1</label>
                <input type="url" class="form-control" id="url1" name="url1">
            </div>
            <div class="form-group">
                <label>連結 2</label>
                <input type="url" class="form-control" id="url2" name="url2">
            </div>
            <div class="form-group">
                <label>連結 3</label>
                <input type="url" class="form-control" id="url3" name="url3">
            </div>
            <div class="form-group">
                <label>檔案 1</label>
                <input type="file" class="form-control" id="fileInput1" onchange="uploadFile(1)">
                <input type="hidden" id="file1" name="file1">
                <input type="hidden" id="file1name" name="file1name">
                <input type="hidden" id="file1type" name="file1type">
                <div id="file1Preview" class="file-preview"></div>
            </div>
            <div class="form-group">
                <label>檔案 2</label>
                <input type="file" class="form-control" id="fileInput2" onchange="uploadFile(2)">
                <input type="hidden" id="file2" name="file2">
                <input type="hidden" id="file2name" name="file2name">
                <input type="hidden" id="file2type" name="file2type">
                <div id="file2Preview" class="file-preview"></div>
            </div>
            <div class="form-group">
                <label>檔案 3</label>
                <input type="file" class="form-control" id="fileInput3" onchange="uploadFile(3)">
                <input type="hidden" id="file3" name="file3">
                <input type="hidden" id="file3name" name="file3name">
                <input type="hidden" id="file3type" name="file3type">
                <div id="file3Preview" class="file-preview"></div>
            </div>
            <button type="submit" class="btn btn-primary">儲存</button>
        </form>
    </div>
</div>

<style>
    .notes-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
        padding: 8px 0 4px;
    }

    .notes-header-main {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .notes-title-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .notes-title {
        margin: 0;
        font-size: clamp(2rem, 4vw, 2.6rem);
        line-height: 1.05;
        letter-spacing: -0.03em;
    }

    .notes-count-pill {
        display: inline-flex;
        align-items: center;
        padding: 8px 14px;
        border-radius: 999px;
        background: linear-gradient(135deg, #8b5cf6, #d946ef);
        color: #fff;
        font-size: 0.9rem;
        font-weight: 700;
        box-shadow: 0 10px 24px rgba(139, 92, 246, 0.2);
    }

    .notes-subtitle {
        margin: 0;
        color: #6b7280;
        font-size: 1rem;
        line-height: 1.7;
    }

    .notes-filter-wrap {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.82);
        border: 1px solid rgba(226, 232, 240, 0.9);
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        color: #64748b;
    }

    .notes-filter-select {
        min-width: 150px;
        border: none;
        background: transparent;
        padding: 0;
        color: #111827;
        font-weight: 600;
    }

    .notes-filter-select:focus {
        box-shadow: none;
    }

    .notes-search-toolbar {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        margin: 16px 0 18px;
    }

    .notes-search-box {
        flex: 1;
        min-width: 280px;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0 20px;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid rgba(226, 232, 240, 0.9);
        box-shadow: 0 18px 34px rgba(15, 23, 42, 0.05);
    }

    .notes-search-box i {
        color: #94a3b8;
        font-size: 1.2rem;
    }

    .notes-search-box .form-control {
        border: none;
        background: transparent;
        padding: 18px 0;
        font-size: 1.05rem;
        box-shadow: none;
    }

    .notes-search-box .form-control:focus {
        box-shadow: none;
    }

    .notes-toolbar-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .file-preview {
        margin-top: 5px;
        font-size: 0.85rem;
        color: #666;
    }

    .file-preview a {
        color: #3498db;
    }

    .file-preview .remove-file {
        color: #e74c3c;
        cursor: pointer;
        margin-left: 10px;
    }

    /* 操作按鈕區 */
    .action-buttons {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        background: linear-gradient(135deg, rgba(226, 248, 255, 0.88), rgba(214, 239, 247, 0.78));
        padding: 18px 24px;
        border-radius: 22px;
        margin-bottom: 18px;
        border: 1px solid rgba(191, 219, 254, 0.5);
        box-shadow: 0 20px 34px rgba(148, 163, 184, 0.14);
    }

    .notes-primary-actions {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }

    .notes-add-btn {
        width: 54px;
        height: 54px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        font-size: 1.35rem;
        box-shadow: 0 16px 30px rgba(99, 102, 241, 0.26);
    }

    .notes-total-count {
        font-size: 1rem;
        color: #475569;
        font-weight: 600;
    }

    #selectModeBtn.btn-select-mode {
        margin-left: 0;
        min-height: 52px;
        border-radius: 18px;
        padding: 0 20px;
        background: linear-gradient(135deg, #7c3aed, #8b5cf6);
        box-shadow: 0 14px 24px rgba(124, 58, 237, 0.18);
    }

    #selectModeBtn.btn-select-mode.active {
        background: linear-gradient(135deg, #ea580c, #ef4444);
    }

    #batchSelectAllWrap {
        margin-left: 0 !important;
        padding: 0 6px;
    }

    /* ===== 筆記卡片美化 ===== */
    .note-card {
        border-radius: 12px;
        padding: 0;
        overflow: hidden;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .note-card>.card-header {
        padding: 12px 16px 0;
    }

    .note-card>.inline-edit {
        padding: 16px;
    }

    /* 分類標籤 */
    .note-category-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        margin: 8px 16px 0;
        width: fit-content;
    }

    .note-category-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 8px 16px 0;
    }

    .note-category-badges .note-category-badge {
        margin: 0;
    }

    .note-category-badge i {
        font-size: 0.65rem;
    }

    .category-picker {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .category-input-row {
        display: flex;
        gap: 8px;
    }

    .category-selected-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        min-height: 20px;
    }

    .category-selected-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        background: #eef4ff;
        border: 1px solid #c7d7fe;
        color: #3451b2;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .category-remove-chip {
        border: none;
        background: transparent;
        color: inherit;
        cursor: pointer;
        padding: 0;
        line-height: 1;
        font-size: 0.9rem;
    }

    .category-option-group {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .category-option-chip {
        border: 1px solid #d7deea;
        background: #fff;
        color: #5b6472;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .category-option-chip.is-active {
        background: #3451b2;
        border-color: #3451b2;
        color: #fff;
    }

    .batch-category-bar {
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 18px;
        padding: 14px 16px;
        border-radius: 18px;
        border: 1px solid #eadfce;
        background: linear-gradient(135deg, rgba(255, 250, 242, 0.96), rgba(250, 244, 255, 0.92));
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    }

    .batch-category-bar.show {
        display: flex;
    }

    .batch-category-meta {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .batch-category-check {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 0.95rem;
        color: #4b5563;
        font-weight: 600;
        cursor: pointer;
    }

    .batch-category-check input {
        width: 18px;
        height: 18px;
        accent-color: #8b5cf6;
    }

    .batch-category-controls {
        flex: 1;
        min-width: 280px;
    }

    .batch-category-picker .category-input-row .btn {
        white-space: nowrap;
    }

    /* 標題 */
    .note-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--header-color);
        margin: 10px 16px 0;
        padding: 0;
        border-bottom: none;
        line-height: 1.4;
    }

    /* 參考來源 */
    .note-ref {
        font-size: 0.8rem;
        color: #8e8e93;
        margin: 4px 16px 0;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .note-ref i {
        color: #f39c12;
        font-size: 0.7rem;
    }

    /* 內容區 */
    .note-content {
        margin: 12px 16px 0;
        padding: 12px;
        background: var(--bg-color);
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }

    .note-text {
        font-size: 0.9rem;
        line-height: 1.7;
        color: var(--text-color);
        word-break: break-word;
    }

    /* 連結區 */
    .note-links {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 10px 16px 0;
    }

    .note-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        background: #ebf5fb;
        color: #2980b9;
        border-radius: 6px;
        font-size: 0.78rem;
        text-decoration: none;
        transition: background 0.2s;
        border: 1px solid #bee5eb;
    }

    [data-theme="dark"] .note-link {
        background: #1a3a5c;
        color: #5dade2;
        border-color: #2e6da4;
    }

    .note-link:hover {
        background: #d4effc;
    }

    .note-link i {
        font-size: 0.65rem;
    }

    /* 附件區 */
    .note-files {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin: 12px 16px 0;
    }

    .note-file-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }

    .note-file-thumb {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        transition: transform 0.2s;
    }

    .note-file-thumb:hover {
        transform: scale(1.05);
    }

    .note-file-download {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 3px;
        font-size: 0.65rem;
        color: #3498db;
        text-decoration: none;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #bee5eb;
        background: #ebf5fb;
        transition: background 0.2s;
        white-space: nowrap;
    }

    .note-file-download:hover {
        background: #d4effc;
    }

    [data-theme="dark"] .note-file-download {
        background: #1a3a5c;
        color: #5dade2;
        border-color: #2e6da4;
    }

    .note-file-thumb img {
        width: 56px;
        height: 56px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid var(--border-color);
    }

    .note-file-icon {
        width: 56px;
        height: 56px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .note-file-icon i {
        color: #fff;
        font-size: 1.3rem;
    }

    .note-file-name {
        font-size: 0.65rem;
        color: #999;
        margin-top: 3px;
        text-align: center;
        max-width: 56px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* 底部 */
    .note-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
        padding: 10px 16px 14px;
        border-top: 1px solid var(--border-color);
        margin-top: 12px;
    }

    .note-actions-row {
        display: flex;
        gap: 4px;
    }

    .note-action-btn {
        background: none;
        border: 1px solid var(--border-color);
        color: #8e8e93;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .note-action-btn:hover {
        background: var(--bg-color);
        color: #3498db;
        border-color: #3498db;
    }

    .note-time {
        font-size: 0.73rem;
        color: #aaa;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* 卡片網格 - 筆記專用 */
    .card-grid .note-card {
        min-height: 180px;
    }

    @media (max-width: 768px) {
        .notes-header {
            align-items: stretch;
        }

        .notes-filter-wrap {
            width: 100%;
            justify-content: space-between;
        }

        .notes-filter-select {
            min-width: 0;
            width: 100%;
        }

        .notes-search-toolbar {
            align-items: stretch;
        }

        .notes-toolbar-actions {
            width: 100%;
        }

        .notes-toolbar-actions .btn,
        .notes-toolbar-actions a.btn {
            flex: 1 1 0;
            justify-content: center;
        }

        .action-buttons {
            padding: 16px;
            gap: 8px;
        }

        .notes-primary-actions {
            width: 100%;
        }

        .batch-category-bar {
            align-items: stretch;
        }

        .batch-category-controls {
            width: 100%;
        }

        .batch-category-picker .category-input-row {
            flex-direction: column;
        }

        .notes-search-box .form-control {
            font-size: 1rem;
        }

        .note-title {
            font-size: 1rem;
        }

        .note-footer {
            flex-direction: column;
            gap: 8px;
            align-items: flex-start;
        }
    }
</style>

<?php include 'includes/upload-progress.php'; ?>
<?php include 'includes/zip-preview.php'; ?>

<script>
    const TABLE = 'article';
    initBatchDelete(TABLE);

    function toggleNoteContent(id) {
        const shortEl = document.getElementById('contentShort-' + id);
        const fullEl = document.getElementById('contentFull-' + id);
        const toggleBtn = document.getElementById('contentToggle-' + id);
        if (!shortEl || !fullEl || !toggleBtn) return;

        const isShortVisible = shortEl.style.display !== 'none';
        if (isShortVisible) {
            shortEl.style.display = 'none';
            fullEl.style.display = 'block';
            toggleBtn.innerHTML = '<i class="fas fa-compress-alt"></i> 收起';
        } else {
            shortEl.style.display = 'block';
            fullEl.style.display = 'none';
            toggleBtn.innerHTML = '<i class="fas fa-expand-alt"></i> 展開';
        }
    }

    function filterNotes() {
        const select = document.getElementById('categoryFilter');
        if (!select) return;
        const value = select.value;
        const keyword = (document.getElementById('noteSearchInput')?.value || '').trim().toLowerCase();
        const cards = document.querySelectorAll('.card-grid .card');
        cards.forEach(card => {
            const categories = card.getAttribute('data-categories') || '';
            const searchText = (card.getAttribute('data-search') || '').toLowerCase();
            const matchesKeyword = !keyword || searchText.includes(keyword);
            let matchesCategory = false;
            if (value === '__all') {
                matchesCategory = true;
            } else if (value === '__uncategorized') {
                matchesCategory = categories === '__uncategorized';
            } else {
                matchesCategory = categories.includes(`|${value}|`);
            }
            card.style.display = matchesKeyword && matchesCategory ? '' : 'none';
        });
    }

    function copyNoteContent(id) {
        const rawEl = document.getElementById('contentRaw-' + id);
        if (!rawEl) return;
        const text = rawEl.value || '';
        if (!text) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text)
                .then(() => alert('已複製'))
                .catch(() => fallbackCopy(text));
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        const temp = document.createElement('textarea');
        temp.value = text;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        alert('已複製');
    }

    function parseCategoryValue(value) {
        return Array.from(new Set((value || '')
            .split(',')
            .map(item => item.trim())
            .filter(Boolean)));
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeJs(value) {
        return String(value)
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'");
    }

    function updateCategoryPicker(picker, categories) {
        if (!picker) return;
        const normalized = Array.from(new Set((categories || []).map(item => item.trim()).filter(Boolean)));
        const hiddenInput = picker.querySelector('[data-field="category"], #category');
        const selectedList = picker.querySelector('[data-role="selected"]');
        const entryInput = picker.querySelector('.category-entry-input');

        if (hiddenInput) hiddenInput.value = normalized.join(', ');
        if (selectedList) {
            selectedList.innerHTML = normalized.map(category => `
                <span class="category-selected-chip">
                    ${escapeHtml(category)}
                    <button type="button" class="category-remove-chip" onclick="removeCategory(this, '${escapeJs(category)}')">&times;</button>
                </span>
            `).join('');
        }

        picker.querySelectorAll('.category-option-chip').forEach(button => {
            button.classList.toggle('is-active', normalized.includes(button.textContent.trim()));
        });

        if (entryInput) entryInput.value = '';
    }

    function getPickerCategories(picker) {
        const hiddenInput = picker.querySelector('[data-field="category"], #category');
        return parseCategoryValue(hiddenInput ? hiddenInput.value : '');
    }

    function addCategoryToPicker(picker, value) {
        const category = (value || '').trim();
        if (!picker || !category) return;
        const categories = getPickerCategories(picker);
        if (!categories.includes(category)) categories.push(category);
        updateCategoryPicker(picker, categories);
    }

    function removeCategory(button, value) {
        const picker = button.closest('[data-category-picker]');
        if (!picker) return;
        const categories = getPickerCategories(picker).filter(category => category !== value);
        updateCategoryPicker(picker, categories);
    }

    function toggleCategoryOption(button, value) {
        const picker = button.closest('[data-category-picker]');
        if (!picker) return;
        const categories = getPickerCategories(picker);
        const next = categories.includes(value)
            ? categories.filter(category => category !== value)
            : categories.concat(value);
        updateCategoryPicker(picker, next);
    }

    function addCategoryFromPicker(button) {
        const picker = button.closest('[data-category-picker]');
        if (!picker) return;
        const entryInput = picker.querySelector('.category-entry-input');
        if (!entryInput) return;
        addCategoryToPicker(picker, entryInput.value);
    }

    function initCategoryPickers(scope = document) {
        scope.querySelectorAll('[data-category-picker]').forEach(picker => {
            const hiddenInput = picker.querySelector('[data-field="category"], #category');
            updateCategoryPicker(picker, parseCategoryValue(hiddenInput ? hiddenInput.value : ''));

            const entryInput = picker.querySelector('.category-entry-input');
            if (entryInput && !entryInput.dataset.categoryBound) {
                entryInput.dataset.categoryBound = '1';
                entryInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        addCategoryToPicker(picker, entryInput.value);
                    }
                });
            }
        });
    }

    function syncBatchCategoryBar() {
        const bar = document.getElementById('batchCategoryBar');
        const count = document.getElementById('batchCategoryCount');
        const selectAll = document.getElementById('batchCategorySelectAll');
        if (!bar || !count || !selectAll) return;

        const hasSelection = batchDeleteIds.size > 0;
        bar.classList.toggle('show', hasSelection);
        count.textContent = batchDeleteIds.size;

        const allCheckboxes = document.querySelectorAll('.item-checkbox');
        const allChecked = allCheckboxes.length > 0 && Array.from(allCheckboxes).every(cb => cb.checked);
        selectAll.checked = allChecked;
        selectAll.indeterminate = hasSelection && !allChecked;
    }

    function buildNotePayloadFromCard(card, overrides = {}) {
        const data = card.dataset;
        return {
            title: data.title || '',
            category: data.categoryValue || '',
            ref: data.ref || '',
            content: data.content || '',
            url1: data.url1 || '',
            url2: data.url2 || '',
            url3: data.url3 || '',
            file1: data.file1 || '',
            file1name: data.file1name || '',
            file1type: data.file1type || '',
            file2: data.file2 || '',
            file2name: data.file2name || '',
            file2type: data.file2type || '',
            file3: data.file3 || '',
            file3name: data.file3name || '',
            file3type: data.file3type || '',
            ...overrides
        };
    }

    function updateSelectedNotesCategories(resolver) {
        const ids = Array.from(batchDeleteIds);
        if (ids.length === 0) return Promise.resolve();

        let completed = 0;
        let failed = 0;

        return new Promise(resolve => {
            ids.forEach(id => {
                const card = getCardById(id);
                if (!card) {
                    failed++;
                    completed++;
                    if (completed === ids.length) resolve({ failed });
                    return;
                }

                const current = parseCategoryValue(card.dataset.categoryValue || '');
                const nextCategories = resolver(current, card);
                const payload = buildNotePayloadFromCard(card, {
                    category: Array.from(new Set((nextCategories || []).map(item => item.trim()).filter(Boolean))).join(', ')
                });

                fetch(`api.php?action=update&table=${TABLE}&id=${id}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                    .then(r => r.json())
                    .then(res => {
                        if (!res.success) failed++;
                    })
                    .catch(() => {
                        failed++;
                    })
                    .finally(() => {
                        completed++;
                        if (completed === ids.length) resolve({ failed });
                    });
            });
        });
    }

    function applyCategoryToSelected() {
        const picker = document.querySelector('.batch-category-picker');
        const selectedCategories = getPickerCategories(picker);
        if (!selectedCategories.length) {
            alert('請先選擇要套用的分類');
            return;
        }

        updateSelectedNotesCategories(current => current.concat(selectedCategories))
            .then(({ failed }) => {
                if (failed > 0) {
                    alert(`套用完成，但有 ${failed} 篇更新失敗`);
                }
                location.reload();
            });
    }

    function clearCategoriesFromSelected() {
        if (batchDeleteIds.size === 0) return;
        updateSelectedNotesCategories(() => [])
            .then(({ failed }) => {
                if (failed > 0) {
                    alert(`清除完成，但有 ${failed} 篇更新失敗`);
                }
                location.reload();
            });
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
        initCategoryPickers(card);
        const titleInput = card.querySelector('[data-field="title"]');
        if (titleInput) titleInput.focus();
    }

    function cancelInlineAdd() {
        const card = document.getElementById('inlineAddCard');
        if (!card) return;
        card.style.display = 'none';
        for (let fi = 1; fi <= 3; fi++) {
            clearInlineFile(fi, 'add');
            const fileInput = document.getElementById(`inlineFileInput${fi}-add`);
            if (fileInput) fileInput.value = '';
        }
    }

    function saveInlineAdd() {
        const card = document.getElementById('inlineAddCard');
        if (!card) return;
        const title = card.querySelector('[data-field="title"]').value.trim();
        if (!title) {
            alert('請輸入標題');
            return;
        }
        const data = {
            title,
            category: card.querySelector('[data-field="category"]').value.trim(),
            ref: card.querySelector('[data-field="ref"]').value.trim(),
            content: card.querySelector('[data-field="content"]').value,
            url1: card.querySelector('[data-field="url1"]').value.trim(),
            url2: card.querySelector('[data-field="url2"]').value.trim(),
            url3: card.querySelector('[data-field="url3"]').value.trim(),
            file1: document.getElementById('inlineFile1Val-add').value || '',
            file1name: document.getElementById('inlineFile1Name-add').value || '',
            file1type: document.getElementById('inlineFile1Type-add').value || '',
            file2: document.getElementById('inlineFile2Val-add').value || '',
            file2name: document.getElementById('inlineFile2Name-add').value || '',
            file2type: document.getElementById('inlineFile2Type-add').value || '',
            file3: document.getElementById('inlineFile3Val-add').value || '',
            file3name: document.getElementById('inlineFile3Name-add').value || '',
            file3type: document.getElementById('inlineFile3Type-add').value || ''
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
        const titleInput = card.querySelector('[data-field="title"]');
        if (titleInput) titleInput.value = data.title || '';
        const categoryInput = card.querySelector('[data-field="category"]');
        if (categoryInput) categoryInput.value = data.categoryValue || '';
        const categoryPicker = card.querySelector('[data-category-picker]');
        if (categoryPicker) updateCategoryPicker(categoryPicker, parseCategoryValue(data.categoryValue || ''));
        const refInput = card.querySelector('[data-field="ref"]');
        if (refInput) refInput.value = data.ref || '';
        const contentInput = card.querySelector('[data-field="content"]');
        if (contentInput) contentInput.value = data.content || '';
        const url1Input = card.querySelector('[data-field="url1"]');
        if (url1Input) url1Input.value = data.url1 || '';
        const url2Input = card.querySelector('[data-field="url2"]');
        if (url2Input) url2Input.value = data.url2 || '';
        const url3Input = card.querySelector('[data-field="url3"]');
        if (url3Input) url3Input.value = data.url3 || '';
        // 附件欄位
        const id = data.id;
        for (let fi = 1; fi <= 3; fi++) {
            const fileVal = data[`file${fi}`] || '';
            const fileName = data[`file${fi}name`] || '';
            const fileType = data[`file${fi}type`] || '';
            const valEl = document.getElementById(`inlineFile${fi}Val-${id}`);
            const nameEl = document.getElementById(`inlineFile${fi}Name-${id}`);
            const typeEl = document.getElementById(`inlineFile${fi}Type-${id}`);
            const prevEl = document.getElementById(`inlineFile${fi}Preview-${id}`);
            if (valEl) valEl.value = fileVal;
            if (nameEl) nameEl.value = fileName;
            if (typeEl) typeEl.value = fileType;
            if (prevEl) prevEl.innerHTML = fileVal
                ? `<a href="${fileVal}" target="_blank">${fileName || '檔案'}</a> <span style="color:#e74c3c;cursor:pointer;" onclick="clearInlineFile(${fi},'${id}')">&#10005; 移除</span>`
                : '';
        }
    }

    function saveInlineEdit(id) {
        const card = getCardById(id);
        if (!card) return;
        const title = card.querySelector('[data-field="title"]').value.trim();
        if (!title) {
            alert('請輸入標題');
            return;
        }
        const data = {
            title,
            category: card.querySelector('[data-field="category"]').value.trim(),
            ref: card.querySelector('[data-field="ref"]').value.trim(),
            content: card.querySelector('[data-field="content"]').value,
            url1: card.querySelector('[data-field="url1"]').value.trim(),
            url2: card.querySelector('[data-field="url2"]').value.trim(),
            url3: card.querySelector('[data-field="url3"]').value.trim(),
            file1: (document.getElementById(`inlineFile1Val-${id}`) || {}).value || '',
            file1name: (document.getElementById(`inlineFile1Name-${id}`) || {}).value || '',
            file1type: (document.getElementById(`inlineFile1Type-${id}`) || {}).value || '',
            file2: (document.getElementById(`inlineFile2Val-${id}`) || {}).value || '',
            file2name: (document.getElementById(`inlineFile2Name-${id}`) || {}).value || '',
            file2type: (document.getElementById(`inlineFile2Type-${id}`) || {}).value || '',
            file3: (document.getElementById(`inlineFile3Val-${id}`) || {}).value || '',
            file3name: (document.getElementById(`inlineFile3Name-${id}`) || {}).value || '',
            file3type: (document.getElementById(`inlineFile3Type-${id}`) || {}).value || ''
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

    function inlineUploadFile(fi, id) {
        const fileInput = document.getElementById(`inlineFileInput${fi}-${id}`);
        if (!fileInput || !fileInput.files[0]) return;
        const file = fileInput.files[0];
        uploadFileWithProgress(file,
            function (res) {
                const valEl = document.getElementById(`inlineFile${fi}Val-${id}`);
                const nameEl = document.getElementById(`inlineFile${fi}Name-${id}`);
                const typeEl = document.getElementById(`inlineFile${fi}Type-${id}`);
                const prevEl = document.getElementById(`inlineFile${fi}Preview-${id}`);
                if (valEl) valEl.value = res.file || '';
                if (nameEl) nameEl.value = file.name;
                const extMatch = file.name.match(/(\.[^.]+)$/);
                if (typeEl) typeEl.value = extMatch ? extMatch[1] : '';
                if (prevEl) prevEl.innerHTML = `<a href="${res.file}" target="_blank">${file.name}</a> <span style="color:#e74c3c;cursor:pointer;" onclick="clearInlineFile(${fi},'${id}')">&#10005; 移除</span>`;
                fileInput.value = '';
            },
            function (err) { alert('上傳失敗: ' + err); }
        );
    }

    function clearInlineFile(fi, id) {
        const valEl = document.getElementById(`inlineFile${fi}Val-${id}`);
        const nameEl = document.getElementById(`inlineFile${fi}Name-${id}`);
        const typeEl = document.getElementById(`inlineFile${fi}Type-${id}`);
        const prevEl = document.getElementById(`inlineFile${fi}Preview-${id}`);
        if (valEl) valEl.value = '';
        if (nameEl) nameEl.value = '';
        if (typeEl) typeEl.value = '';
        if (prevEl) prevEl.innerHTML = '';
    }

    function openModal() {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modalTitle').textContent = '新增筆記';
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '';
        initCategoryPickers(document.getElementById('modal'));
        // 清除檔案預覽
        for (let i = 1; i <= 3; i++) {
            document.getElementById('file' + i).value = '';
            document.getElementById('file' + i + 'name').value = '';
            document.getElementById('file' + i + 'type').value = '';
            document.getElementById('file' + i + 'Preview').innerHTML = '';
        }
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
                    document.getElementById('title').value = d.title || '';
                    document.getElementById('category').value = d.category || '';
                    initCategoryPickers(document.getElementById('modal'));
                    document.getElementById('ref').value = d.ref || '';
                    document.getElementById('content').value = d.content || '';
                    document.getElementById('url1').value = d.url1 || '';
                    document.getElementById('url2').value = d.url2 || '';
                    document.getElementById('url3').value = d.url3 || '';
                    // 載入檔案資訊
                    for (let i = 1; i <= 3; i++) {
                        document.getElementById('file' + i).value = d['file' + i] || '';
                        document.getElementById('file' + i + 'name').value = d['file' + i + 'name'] || '';
                        document.getElementById('file' + i + 'type').value = d['file' + i + 'type'] || '';
                        updateFilePreview(i);
                    }
                    document.getElementById('modalTitle').textContent = '編輯筆記';
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
            title: document.getElementById('title').value,
            category: document.getElementById('category').value,
            ref: document.getElementById('ref').value,
            content: document.getElementById('content').value,
            url1: document.getElementById('url1').value,
            url2: document.getElementById('url2').value,
            url3: document.getElementById('url3').value,
            file1: document.getElementById('file1').value,
            file1name: document.getElementById('file1name').value,
            file1type: document.getElementById('file1type').value,
            file2: document.getElementById('file2').value,
            file2name: document.getElementById('file2name').value,
            file2type: document.getElementById('file2type').value,
            file3: document.getElementById('file3').value,
            file3name: document.getElementById('file3name').value,
            file3type: document.getElementById('file3type').value
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

    initCategoryPickers();
    const originalUpdateBatchDeleteBar = typeof updateBatchDeleteBar === 'function' ? updateBatchDeleteBar : null;
    if (originalUpdateBatchDeleteBar) {
        updateBatchDeleteBar = function () {
            originalUpdateBatchDeleteBar();
            syncBatchCategoryBar();
        };
        updateBatchDeleteBar();
    }

    function uploadFile(num) {
        const input = document.getElementById('fileInput' + num);
        if (!input.files || !input.files[0]) return;

        uploadFileWithProgress(input.files[0],
            function (res) {
                document.getElementById('file' + num).value = res.file;
                document.getElementById('file' + num + 'name').value = res.filename;
                document.getElementById('file' + num + 'type').value = res.filetype;
                updateFilePreview(num);
            },
            function (error) {
                alert('上傳失敗: ' + error);
            }
        );
        input.value = '';
    }

    function updateFilePreview(num) {
        const file = document.getElementById('file' + num).value;
        const filename = document.getElementById('file' + num + 'name').value;
        const filetype = document.getElementById('file' + num + 'type').value;
        const preview = document.getElementById('file' + num + 'Preview');

        if (file && filename) {
            let previewHtml = '';

            // 根據檔案類型顯示預覽
            if (filetype && filetype.startsWith('image/')) {
                previewHtml = `<div style="margin-bottom: 5px;"><img src="${file}" style="max-width: 150px; max-height: 100px; border-radius: 5px;"></div>`;
            } else if (filetype && filetype.startsWith('video/')) {
                previewHtml = `<div style="margin-bottom: 5px;"><video src="${file}" style="max-width: 200px; max-height: 120px; border-radius: 5px;" controls></video></div>`;
            } else if (filetype && filetype.startsWith('audio/')) {
                previewHtml = `<div style="margin-bottom: 5px;"><audio src="${file}" controls style="max-width: 250px;"></audio></div>`;
            } else if (filetype === 'application/pdf') {
                previewHtml = `<div style="margin-bottom: 5px;"><i class="fa-solid fa-file-pdf" style="font-size: 2rem; color: #e74c3c;"></i></div>`;
            } else {
                previewHtml = `<div style="margin-bottom: 5px;"><i class="fa-solid fa-file" style="font-size: 2rem; color: #3498db;"></i></div>`;
            }

            previewHtml += `<a href="${file}" target="_blank">${filename}</a> <a href="${file}" download="${filename}" style="color:#27ae60;margin-left:8px;"><i class="fa-solid fa-download"></i> 下載</a> <span class="remove-file" onclick="removeFile(${num})">✕ 移除</span>`;
            preview.innerHTML = previewHtml;
        } else {
            preview.innerHTML = '';
        }
    }

    function removeFile(num) {
        document.getElementById('file' + num).value = '';
        document.getElementById('file' + num + 'name').value = '';
        document.getElementById('file' + num + 'type').value = '';
        document.getElementById('fileInput' + num).value = '';
        document.getElementById('file' + num + 'Preview').innerHTML = '';
    }
</script>
