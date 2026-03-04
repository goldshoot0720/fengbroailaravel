<?php
$pageTitle = '音樂管理';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM music ORDER BY created_at DESC")->fetchAll();

// Get existing categories
$categories = [];
foreach ($items as $item) {
    $cat = trim($item['category'] ?? '');
    if ($cat !== '' && !in_array($cat, $categories)) {
        $categories[] = $cat;
    }
}
sort($categories);

$groupedItems = [];
foreach ($items as $item) {
    $name = trim($item['name'] ?? '');
    $key = $name !== '' ? mb_strtolower($name) : $item['id'];
    if (!isset($groupedItems[$key])) {
        $groupedItems[$key] = [
            'name' => $name !== '' ? $name : ($item['name'] ?? ''),
            'items' => [],
            'cover' => $item['cover'] ?? '',
            'category' => $item['category'] ?? '',
            'note' => $item['note'] ?? '',
            'ref' => $item['ref'] ?? '',
            'lyrics' => $item['lyrics'] ?? ''
        ];
    }
    $groupedItems[$key]['items'][] = $item;
    $fields = ['cover', 'category', 'note', 'ref', 'lyrics'];
    foreach ($fields as $field) {
        if (empty($groupedItems[$key][$field]) && !empty($item[$field])) {
            $groupedItems[$key][$field] = $item[$field];
        }
    }
}

foreach ($groupedItems as $key => $group) {
    $languageGroups = [];
    $languageSummary = [];
    foreach ($group['items'] as $item) {
        $lang = trim($item['language'] ?? '');
        $baseLang = $lang !== '' ? $lang : '其他';

        // 將帶括號的語言變體歸類到主語言
        $mainLanguages = ['中文', '英語', '日語', '韓語', '粵語'];
        $matched = false;
        foreach ($mainLanguages as $mainLang) {
            if (mb_strpos($baseLang, $mainLang) === 0) {
                $baseLang = $mainLang;
                $matched = true;
                break;
            }
        }
        if (!$matched && !in_array($baseLang, $mainLanguages, true)) {
            $baseLang = '其他';
        }
        $label = $lang !== '' ? $lang : $baseLang;
        $languageGroups[$baseLang][] = [
            'label' => $label,
            'file' => $item['file'] ?? '',
            'title' => $group['name'],
            'id' => $item['id']
        ];
        $languageSummary[$baseLang] = true;
    }
    $groupedItems[$key]['languageGroups'] = $languageGroups;
    $groupedItems[$key]['languageSummary'] = implode(' / ', array_keys($languageSummary));
}

// Predefined languages
$defaultLanguages = ['中文', '英語', '日語', '韓語', '粵語', '其他'];

// Get existing languages from database
$existingLanguages = [];
foreach ($items as $item) {
    $lang = trim($item['language'] ?? '');
    if ($lang !== '' && !in_array($lang, $existingLanguages)) {
        $existingLanguages[] = $lang;
    }
}
sort($existingLanguages);

// Merge default and existing languages (remove duplicates)
$allLanguages = array_unique(array_merge($defaultLanguages, $existingLanguages));
$languages = $defaultLanguages; // Keep default for quick buttons
?>

<div class="content-header">
    <h1>鋒兄音樂 <span
            style="font-size:0.55em;background:#3498db;color:#fff;padding:3px 10px;border-radius:20px;vertical-align:middle;font-weight:500;"><?php echo count($items); ?></span>
    </h1>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <button class="btn btn-primary" onclick="handleAdd()" title="新增音樂"><i class="fas fa-plus"></i></button>

    <div style="display: inline-block; margin-left: 10px;">
        <a href="export_zip_music.php" class="btn btn-success">
            <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
        </a>
        <button type="button" class="btn" onclick="document.getElementById('importZipFile').click()">
            <i class="fa-solid fa-file-zipper"></i> 匯入 ZIP
        </button>
        <input type="file" id="importZipFile" accept=".zip" style="display: none;" onchange="importZIP(this)">
    </div>
    <?php include 'includes/batch-delete.php'; ?>

    <div class="card-grid" style="margin-top: 20px;">
        <div id="inlineAddCard" class="card inline-add-card">
            <div class="inline-edit inline-edit-always">
                <!-- 防止瀏覽器自動填入：隱藏假帳密欄位 -->
                <input type="text" style="display:none" autocomplete="username" tabindex="-1" aria-hidden="true">
                <input type="password" style="display:none" autocomplete="new-password" tabindex="-1"
                    aria-hidden="true">
                <div class="form-group">
                    <label>名稱 *</label>
                    <input type="text" class="form-control inline-input" data-field="name" autocomplete="off" required>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:1">
                        <label>分類</label>
                        <input type="text" class="form-control inline-input" data-field="category"
                            list="categoryOptions" placeholder="選擇或輸入分類" autocomplete="off">
                        <datalist id="categoryOptions">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>語言</label>
                        <input type="text" class="form-control inline-input" data-field="language"
                            list="languageOptions" placeholder="選擇或輸入語言" autocomplete="off">
                        <datalist id="languageOptions">
                            <?php foreach ($allLanguages as $lang): ?>
                                <option value="<?php echo htmlspecialchars($lang); ?>">
                                <?php endforeach; ?>
                        </datalist>
                        <div style="margin-top: 5px; display: flex; gap: 4px; flex-wrap: wrap;">
                            <?php foreach ($defaultLanguages as $lang): ?>
                                <button type="button" class="btn"
                                    onclick="setInlineLanguage(this, '<?php echo htmlspecialchars($lang); ?>')"
                                    style="padding: 2px 8px; font-size: 0.72rem;"><?php echo htmlspecialchars($lang); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>檔案路徑</label>
                    <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入音樂網址"
                        autocomplete="off" oninput="updateInlineAudioPreview(this)">
                    <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                        <input type="file" class="inline-audio-file" accept="audio/*" style="display: none;"
                            onchange="uploadInlineAudio(this)">
                        <button type="button" class="btn" onclick="this.previousElementSibling.click()"
                            style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳音樂</button>
                    </div>
                    <div class="inline-audio-preview" style="margin-top: 6px;"></div>
                </div>
                <div class="form-group">
                    <label>封面圖</label>
                    <input type="text" class="form-control inline-input" data-field="cover" placeholder="輸入封面圖網址"
                        autocomplete="off" oninput="updateInlineMusicCoverPreview(this)">
                    <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                        <input type="file" class="inline-cover-file" accept="image/*" style="display: none;"
                            onchange="uploadInlineMusicCover(this)">
                        <button type="button" class="btn" onclick="this.previousElementSibling.click()"
                            style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳封面</button>
                    </div>
                    <div class="inline-music-cover-preview" style="margin-top: 6px;"></div>
                </div>
                <div class="form-group">
                    <label>參考</label>
                    <input type="text" class="form-control inline-input" data-field="ref" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>備註</label>
                    <textarea class="form-control inline-input" data-field="note" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>歌詞</label>
                    <textarea class="form-control inline-input" data-field="lyrics" rows="4"></textarea>
                </div>
                <div class="inline-actions">
                    <button type="button" class="btn btn-primary" onclick="saveInlineAdd()">儲存</button>
                    <button type="button" class="btn" onclick="cancelInlineAdd()">取消</button>
                </div>
            </div>
        </div>

        <?php if (empty($groupedItems)): ?>
            <div class="card" style="text-align: center; color: #999; padding: 40px;">
                <i class="fas fa-music" style="font-size: 3rem; margin-bottom: 15px;"></i>
                <p>暫無音樂</p>
            </div>
        <?php else: ?>
            <?php foreach ($groupedItems as $groupKey => $group): ?>
                <?php
                    // 準備各版本的 JSON 供 JS 切換用
                    $itemsJson = array_map(function($it) {
                        return [
                            'id'       => $it['id'],
                            'language' => $it['language'] ?? '',
                            'file'     => $it['file'] ?? '',
                        ];
                    }, $group['items']);
                ?>
                <div class="card" data-id="<?php echo $group['items'][0]['id']; ?>"
                    data-name="<?php echo htmlspecialchars($group['name'], ENT_QUOTES); ?>"
                    data-category="<?php echo htmlspecialchars($group['category'] ?? '', ENT_QUOTES); ?>"
                    data-language="<?php echo htmlspecialchars($group['items'][0]['language'] ?? '', ENT_QUOTES); ?>"
                    data-file="<?php echo htmlspecialchars($group['items'][0]['file'] ?? '', ENT_QUOTES); ?>"
                    data-cover="<?php echo htmlspecialchars($group['cover'] ?? '', ENT_QUOTES); ?>"
                    data-ref="<?php echo htmlspecialchars($group['ref'] ?? '', ENT_QUOTES); ?>"
                    data-note="<?php echo htmlspecialchars($group['note'] ?? '', ENT_QUOTES); ?>"
                    data-lyrics="<?php echo htmlspecialchars($group['lyrics'] ?? '', ENT_QUOTES); ?>"
                    data-editing-id="<?php echo $group['items'][0]['id']; ?>"
                    data-items="<?php echo htmlspecialchars(json_encode($itemsJson, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>">

                    <div class="inline-view">
                        <div class="card-header">
                            <input type="checkbox" class="select-checkbox item-checkbox"
                                data-id="<?php echo $group['items'][0]['id']; ?>"
                                data-all-ids="<?php echo htmlspecialchars(implode(',', array_column($group['items'], 'id')), ENT_QUOTES); ?>"
                                onchange="toggleSelectItem(this)">
                        </div>
                        <?php if (!empty($group['cover'])): ?>
                            <div style="text-align: center; margin-bottom: 15px;">
                                <img src="<?php echo htmlspecialchars($group['cover']); ?>"
                                    style="width: 120px; height: 120px; object-fit: cover; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                            </div>
                        <?php endif; ?>

                        <h3 style="margin: 0 0 10px 0; color: #333;">
                            <?php echo htmlspecialchars($group['name']); ?>
                            <?php if (count($group['items']) > 1): ?>
                                <span
                                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; margin-left: 8px;">
                                    <?php echo count($group['items']); ?> 版本
                                </span>
                            <?php endif; ?>
                        </h3>

                        <div style="color: #666; font-size: 0.9rem; margin-bottom: 10px;">
                            <?php if (!empty($group['category'])): ?>
                                <span
                                    style="background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 4px; margin-right: 5px;">
                                    <?php echo htmlspecialchars($group['category']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($group['languageSummary'])): ?>
                                <span style="background: #f3e5f5; color: #7b1fa2; padding: 2px 6px; border-radius: 4px;">
                                    <?php echo htmlspecialchars($group['languageSummary']); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($group['note'])): ?>
                            <p style="color: #666; font-size: 0.9rem; margin: 10px 0; line-height: 1.4;">
                                <?php echo nl2br(htmlspecialchars(mb_substr($group['note'], 0, 100))); ?>
                                <?php echo mb_strlen($group['note']) > 100 ? '...' : ''; ?>
                            </p>
                        <?php endif; ?>

                        <div style="margin-top: 15px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php if (!empty($group['languageGroups'])): ?>
                                <?php $playerId = 'player_' . md5($group['name']); ?>
                                <button class="btn btn-sm btn-primary"
                                    onclick="openTwoLayerPlayer('<?php echo $playerId; ?>', <?php echo htmlspecialchars(json_encode($group['languageGroups'], JSON_UNESCAPED_UNICODE)); ?>, '<?php echo htmlspecialchars($group['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($group['cover'] ?? '', ENT_QUOTES); ?>', <?php echo htmlspecialchars(json_encode($group['lyrics'] ?? '', JSON_UNESCAPED_UNICODE)); ?>)">
                                    <i class="fa-solid fa-play"></i> 播放
                                </button>
                            <?php endif; ?>

                            <?php if (!empty($group['items'][0]['file'])): ?>
                                <a href="<?php echo htmlspecialchars($group['items'][0]['file']); ?>" class="btn btn-sm btn-success" download target="_blank" rel="noopener noreferrer">
                                    <i class="fa-solid fa-download"></i> 下載
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($group['lyrics'])): ?>
                                <button class="btn btn-sm btn-info" onclick="viewLyrics('<?php echo $group['items'][0]['id']; ?>')">
                                    <i class="fa-solid fa-file-lines"></i> 歌詞
                                </button>
                            <?php endif; ?>

                            <?php if (!empty($group['ref'])): ?>
                                <a href="<?php echo htmlspecialchars($group['ref']); ?>" target="_blank"
                                    class="btn btn-sm btn-secondary">
                                    <i class="fa-solid fa-external-link-alt"></i> 參考
                                </a>
                            <?php endif; ?>

                            <button class="btn btn-sm btn-warning"
                                onclick="startInlineEdit('<?php echo $group['items'][0]['id']; ?>')">
                                <i class="fa-solid fa-edit"></i> 編輯
                            </button>

                            <button class="btn btn-sm btn-danger"
                                onclick="deleteItem('<?php echo $group['items'][0]['id']; ?>')">
                                <i class="fa-solid fa-trash"></i> 刪除
                            </button>
                        </div>
                    </div>

                    <div class="inline-edit">
                        <!-- 多語言版本切換 tabs（≥2版本才顯示） -->
                        <?php if (count($group['items']) > 1): ?>
                        <div class="music-version-tabs" style="margin-bottom: 14px;">
                            <div style="font-size:0.8rem; color:#888; margin-bottom:6px;">選擇要編輯的版本：</div>
                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <?php foreach ($group['items'] as $vi => $vItem): ?>
                                <button type="button" class="music-edit-tab <?php echo $vi === 0 ? 'active' : ''; ?>"
                                    data-vid="<?php echo $vItem['id']; ?>"
                                    data-vlang="<?php echo htmlspecialchars($vItem['language'] ?? '其他', ENT_QUOTES); ?>"
                                    data-vfile="<?php echo htmlspecialchars($vItem['file'] ?? '', ENT_QUOTES); ?>"
                                    onclick="switchMusicEditVersion(this)">
                                    <?php echo htmlspecialchars($vItem['language'] ?: '其他'); ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <div style="font-size:0.78rem; color:#aaa; margin-top:6px;">* 語言、檔案路徑為各版本獨立欄位；名稱、封面、參考、備註、歌詞為共用欄位（會同步更新所有版本）</div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label>名稱 *</label>
                            <input type="text" class="form-control inline-input" data-field="name" autocomplete="off" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="flex:1">
                                <label>分類</label>
                                <input type="text" class="form-control inline-input" data-field="category"
                                    list="categoryOptions" placeholder="選擇或輸入分類" autocomplete="off">
                            </div>
                            <div class="form-group" style="flex:1">
                                <label>語言 <span style="font-size:0.78rem;color:#aaa;">（此版本）</span></label>
                                <input type="text" class="form-control inline-input" data-field="language"
                                    list="languageOptions" placeholder="選擇或輸入語言" autocomplete="off">
                                <div style="margin-top: 5px; display: flex; gap: 4px; flex-wrap: wrap;">
                                    <?php foreach ($defaultLanguages as $lang): ?>
                                        <button type="button" class="btn"
                                            onclick="setInlineLanguage(this, '<?php echo htmlspecialchars($lang); ?>')"
                                            style="padding: 2px 8px; font-size: 0.72rem;"><?php echo htmlspecialchars($lang); ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>檔案路徑 <span style="font-size:0.78rem;color:#aaa;">（此版本）</span></label>
                            <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入音樂網址"
                                autocomplete="off" oninput="updateInlineAudioPreview(this)">
                            <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                <input type="file" class="inline-audio-file" accept="audio/*" style="display: none;"
                                    onchange="uploadInlineAudio(this)">
                                <button type="button" class="btn" onclick="this.previousElementSibling.click()"
                                    style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳音樂</button>
                            </div>
                            <div class="inline-audio-preview" style="margin-top: 6px;"></div>
                        </div>
                        <div class="form-group">
                            <label>封面圖</label>
                            <input type="text" class="form-control inline-input" data-field="cover" placeholder="輸入封面圖網址"
                                autocomplete="off" oninput="updateInlineMusicCoverPreview(this)">
                            <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                <input type="file" class="inline-cover-file" accept="image/*" style="display: none;"
                                    onchange="uploadInlineMusicCover(this)">
                                <button type="button" class="btn" onclick="this.previousElementSibling.click()"
                                    style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳封面</button>
                            </div>
                            <div class="inline-music-cover-preview" style="margin-top: 6px;"></div>
                        </div>
                        <div class="form-group">
                            <label>參考</label>
                            <input type="text" class="form-control inline-input" data-field="ref" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label>備註</label>
                            <textarea class="form-control inline-input" data-field="note" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>歌詞</label>
                            <textarea class="form-control inline-input" data-field="lyrics" rows="4"></textarea>
                        </div>
                        <div class="inline-actions">
                            <button type="button" class="btn btn-primary" onclick="saveMusicVersionEdit(this)">儲存</button>
                            <button type="button" class="btn" onclick="cancelMusicVersionEdit(this)">取消</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Lyrics Panel -->
<div id="lyricsPanel"
    style="display: none; position: fixed; bottom: 72px; right: 0; width: 350px; max-height: calc(100vh - 80px); background: #fff; box-shadow: -2px 0 10px rgba(0,0,0,0.2); z-index: 9998; overflow-y: auto; border-radius: 12px 0 0 0; transition: all 0.3s ease;">
    <div style="padding: 20px;">
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h3 id="lyricsTitle" style="margin: 0;">歌詞</h3>
            <button onclick="closeLyricsModal()"
                style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <pre id="lyricsContent" style="white-space: pre-wrap; font-family: inherit; line-height: 1.8; margin: 0;"></pre>
    </div>
</div>

<?php include 'includes/upload-progress.php'; ?>

<script>
    const TABLE = 'music';
    initBatchDelete(TABLE);

    // 覆寫：勾選音樂時，把同名所有版本 ID 都納入批量刪除
    function toggleSelectItem(checkbox) {
        const allIds = (checkbox.dataset.allIds || checkbox.dataset.id).split(',').filter(Boolean);
        if (checkbox.checked) {
            allIds.forEach(id => batchDeleteIds.add(id));
        } else {
            allIds.forEach(id => batchDeleteIds.delete(id));
        }
        const allCheckboxes = document.querySelectorAll('.item-checkbox');
        const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
        syncSelectAllCheckboxes(allChecked, batchDeleteIds.size > 0);
        updateBatchDeleteBar();
    }

    // 覆寫：全選時也把所有版本 ID 都加入
    function toggleSelectAll(checkbox) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
            const allIds = (cb.dataset.allIds || cb.dataset.id).split(',').filter(Boolean);
            if (checkbox.checked) {
                allIds.forEach(id => batchDeleteIds.add(id));
            } else {
                allIds.forEach(id => batchDeleteIds.delete(id));
            }
        });
        syncSelectAllCheckboxes(checkbox.checked, checkbox.checked);
        updateBatchDeleteBar();
    }

    function setInlineLanguage(btn, lang) {
        const input = btn.closest('.form-group').querySelector('[data-field="language"]');
        if (input) input.value = lang;
    }

    function uploadInlineAudio(fileInput) {
        if (!fileInput.files || !fileInput.files[0]) return;
        const file = fileInput.files[0];
        const formGroup = fileInput.closest('.form-group');
        const urlInput = formGroup.querySelector('[data-field="file"]');
        uploadFileWithProgress(file,
            function (res) {
                urlInput.value = res.file;
                updateInlineAudioPreview(urlInput);
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

    function updateInlineAudioPreview(input) {
        const preview = input.closest('.form-group').querySelector('.inline-audio-preview');
        if (!preview) return;
        const url = input.value.trim();
        preview.innerHTML = url
            ? `<audio src="${url}" controls preload="none" style="width: 100%; margin-top: 4px;"></audio>`
            : '';
    }

    function uploadInlineMusicCover(fileInput) {
        if (!fileInput.files || !fileInput.files[0]) return;
        const formGroup = fileInput.closest('.form-group');
        const urlInput = formGroup.querySelector('[data-field="cover"]');
        uploadFileWithProgress(fileInput.files[0],
            function (res) {
                urlInput.value = res.file;
                updateInlineMusicCoverPreview(urlInput);
            },
            function (error) { alert('上傳失敗: ' + error); }
        );
        fileInput.value = '';
    }

    function updateInlineMusicCoverPreview(input) {
        const preview = input.closest('.form-group').querySelector('.inline-music-cover-preview');
        if (!preview) return;
        const url = input.value.trim();
        preview.innerHTML = url
            ? `<img src="${url}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">`
            : '';
    }

    function fillInlineInputs(card) {
        const data = card.dataset;
        // 先填入卡片 dataset 的預設值（共用欄位）
        card.querySelectorAll('[data-field]').forEach(input => {
            const field = input.dataset.field;
            input.value = data[field] || data[field + 'Value'] || '';
            input.classList.remove('error', 'success');
        });
        // 若有多語言 tabs，以第一個 tab 的版本資料覆蓋 language/file
        const firstTab = card.querySelector('.music-edit-tab.active') || card.querySelector('.music-edit-tab');
        if (firstTab) {
            const langInput = card.querySelector('[data-field="language"]');
            const fileInput = card.querySelector('[data-field="file"]');
            if (langInput) langInput.value = firstTab.dataset.vlang || '';
            if (fileInput) { fileInput.value = firstTab.dataset.vfile || ''; }
            card.dataset.editingId = firstTab.dataset.vid;
        } else {
            card.dataset.editingId = data.id;
        }
        const fileInput = card.querySelector('[data-field="file"]');
        if (fileInput) updateInlineAudioPreview(fileInput);
        const coverInput = card.querySelector('[data-field="cover"]');
        if (coverInput) updateInlineMusicCoverPreview(coverInput);
    }

    // 切換版本 tab
    function switchMusicEditVersion(tabBtn) {
        const card = tabBtn.closest('.card');
        card.querySelectorAll('.music-edit-tab').forEach(t => t.classList.remove('active'));
        tabBtn.classList.add('active');
        // 更新 editingId
        card.dataset.editingId = tabBtn.dataset.vid;
        // 只覆蓋 language / file（版本獨立欄位）
        const langInput = card.querySelector('[data-field="language"]');
        const fileInput = card.querySelector('[data-field="file"]');
        if (langInput) langInput.value = tabBtn.dataset.vlang || '';
        if (fileInput) {
            fileInput.value = tabBtn.dataset.vfile || '';
            updateInlineAudioPreview(fileInput);
        }
    }

    // 儲存目前版本（先更新此版本的 language/file，再同步共用欄位到所有同名版本）
    function saveMusicVersionEdit(btn) {
        const card = btn.closest('.card');
        const editingId = card.dataset.editingId || card.dataset.id;
        const allItems = card.dataset.items ? JSON.parse(card.dataset.items) : [{ id: editingId }];

        // 讀取表單值
        const getVal = field => {
            const el = card.querySelector(`[data-field="${field}"]`);
            return el ? el.value : '';
        };

        // 版本獨立欄位：language + file
        const versionData = {
            language: getVal('language'),
            file: getVal('file'),
        };

        // 共用欄位：name / category / cover / ref / note / lyrics
        const sharedData = {
            name: getVal('name'),
            category: getVal('category'),
            cover: getVal('cover'),
            ref: getVal('ref'),
            note: getVal('note'),
            lyrics: getVal('lyrics'),
        };

        if (!sharedData.name) { alert('名稱不能為空'); return; }

        // 建立全部更新請求
        const requests = allItems.map(item => {
            const payload = { ...sharedData };
            if (item.id === editingId) {
                // 目前版本：版本獨立欄位一起儲存
                payload.language = versionData.language;
                payload.file = versionData.file;
            }
            return fetch(`api.php?action=update&table=${TABLE}&id=${item.id}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(r => r.json());
        });

        Promise.all(requests).then(results => {
            const failed = results.filter(r => !r.success);
            if (failed.length) {
                alert('部分儲存失敗: ' + JSON.stringify(failed));
            } else {
                const url = new URL(location.href);
                url.searchParams.set('_t', Date.now());
                location.replace(url.toString());
            }
        }).catch(err => alert('儲存失敗: ' + err.message));
    }

    // 取消編輯
    function cancelMusicVersionEdit(btn) {
        const card = btn.closest('.card');
        cancelInlineEdit(card.dataset.id);
    }

    function closeLyricsModal() {
        document.getElementById('lyricsPanel').style.display = 'none';
        const btn = document.getElementById('lyricsToggleBtn');
        if (btn) { btn.style.background = 'rgba(255,255,255,0.2)'; btn.title = '顯示歌詞'; }
    }

    let _currentLyrics = '';

    function setLyricsButtonState(hasLyrics, opened) {
        const btn = document.getElementById('lyricsToggleBtn');
        if (!btn) return;
        btn.style.opacity = hasLyrics ? '1' : '0.4';
        btn.disabled = !hasLyrics;
        btn.style.background = opened ? 'rgba(255,255,255,0.45)' : 'rgba(255,255,255,0.2)';
        btn.title = opened ? '隱藏歌詞' : '顯示歌詞';
    }

    function toggleLyricsPanel() {
        const panel = document.getElementById('lyricsPanel');
        const btn = document.getElementById('lyricsToggleBtn');
        const isHidden = panel.style.display === 'none' || panel.style.display === '';
        if (isHidden) {
            if (!_currentLyrics) { alert('目前歌曲沒有歌詞'); return; }
            panel.style.display = 'block';
            setLyricsButtonState(true, true);
        } else {
            panel.style.display = 'none';
            setLyricsButtonState(true, false);
        }
    }

    function viewLyrics(id) {
        fetch(`api.php?action=get&table=${TABLE}&id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    document.getElementById('lyricsTitle').textContent = res.data.name + ' - 歌詞';
                    document.getElementById('lyricsContent').textContent = res.data.lyrics || '暫無歌詞';
                    document.getElementById('lyricsPanel').style.display = 'block';
                } else {
                    alert('無法載入歌詞: ' + (res.error || '未知錯誤'));
                }
            })
            .catch(err => {
                console.error('viewLyrics error:', err);
                alert('載入歌詞失敗: ' + err.message);
            });
    }

    function deleteItem(id) {
        if (confirm('確定要刪除這個音樂嗎？')) {
            fetch(`api.php?action=delete&table=${TABLE}&id=${id}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        // 加 _t 參數繞過 Service Worker 快取
                        const url = new URL(location.href);
                        url.searchParams.set('_t', Date.now());
                        location.replace(url.toString());
                    } else {
                        alert('刪除失敗: ' + (res.error || ''));
                    }
                });
        }
    }

    function importZIP(input) {
        if (!input.files || !input.files[0]) return;

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
            // onDone: tempFile ready, now trigger import
            function (tempFile) {
                fileName.textContent = file.name + ' — 正在匯入...';
                progressBar.style.width = '100%';
                progressText.textContent = '100%';

                const fd = new FormData();
                fd.append('tempFile', tempFile);

                fetch('import_zip_music.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        modal.style.display = 'none';
                        if (res.success) {
                            let msg = '匯入完成！\n成功匯入: ' + res.imported + ' 首音樂';
                            if (res.errors && res.errors.length > 0) {
                                msg += '\n\n錯誤明細:\n' + res.errors.join('\n');
                            }
                            alert(msg);
                            location.reload();
                        } else {
                            let msg = '匯入失敗: ' + (res.error || '未知錯誤');
                            if (res.debug && res.debug.length > 0) {
                                msg += '\n\n--- Debug ---\n' + res.debug.slice(-5).join('\n');
                            }
                            alert(msg);
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

    function sanitizeMusicFilename(name) {
        const safe = (name || 'music').replace(/[\\/:*?"<>|]+/g, '_').trim();
        return safe || 'music';
    }

    function setMusicDownloadLink(src, title) {
        const btn = document.getElementById('musicDownloadBtn');
        if (!btn) return;
        if (src) {
            btn.href = src;
            btn.setAttribute('download', sanitizeMusicFilename(title) + '.mp3');
            btn.style.display = 'inline-flex';
            return;
        }
        btn.removeAttribute('href');
        btn.removeAttribute('download');
        btn.style.display = 'none';
    }

    // ========== 底部播放列 ==========
    function playMusic(src, title, musicId) {
        const bar = document.getElementById('musicPlayerBar');
        const player = document.getElementById('musicPlayer');
        const titleEl = document.getElementById('musicPlayerTitle');
        titleEl.textContent = title;
        titleEl.style.color = '#fff';
        player.src = src;
        setMusicDownloadLink(src, title);
        player.volume = parseFloat(localStorage.getItem('musicVolume') ?? '1.0');
        bar.style.display = 'block';

        // 錯誤處理
        player.onerror = function () {
            const code = player.error ? player.error.code : '?';
            const msgs = { 1: '已中止', 2: '網路錯誤', 3: '解碼失敗（格式不支援？）', 4: '找不到檔案或格式不支援' };
            const reason = msgs[code] || '未知錯誤';
            titleEl.innerHTML = `<span style="color:#ffcccc;">⚠ 無法播放：${reason}</span><br><small style="font-size:0.75rem;opacity:0.8;">${src.split('/').pop()}</small>`;
        };

        player.play().catch(function (err) {
            titleEl.innerHTML = `<span style="color:#ffcccc;">⚠ 播放失敗：${err.message}</span>`;
        });

        if (musicId) {
            fetch(`api.php?action=get&table=${TABLE}&id=${musicId}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data) {
                        const lyrics = (res.data.lyrics || '').trim();
                        _currentLyrics = lyrics;
                        if (lyrics) {
                            document.getElementById('lyricsTitle').textContent = res.data.name + ' - 歌詞';
                            document.getElementById('lyricsContent').textContent = lyrics;
                            // 預設開啟歌詞面板
                            document.getElementById('lyricsPanel').style.display = 'block';
                            setLyricsButtonState(true, true);
                        } else {
                            document.getElementById('lyricsPanel').style.display = 'none';
                            setLyricsButtonState(false, false);
                        }
                    }
                });
        } else {
            _currentLyrics = '';
            document.getElementById('lyricsPanel').style.display = 'none';
            setLyricsButtonState(false, false);
        }
    }

    function closeMusicPlayer() {
        const player = document.getElementById('musicPlayer');
        player.pause();
        player.src = '';
        setMusicDownloadLink('', '');
        document.getElementById('musicPlayerBar').style.display = 'none';
    }

    // ========== 兩層分類播放器 ==========
    let twoLayerData = null;
    let twoLayerCurrentFile = null;
    let twoLayerCurrentId = null;

    function openTwoLayerPlayer(playerId, languageGroups, songName, cover, lyrics) {
        twoLayerData = languageGroups;
        document.getElementById('twoLayerTitle').textContent = songName;
        const coverEl = document.getElementById('twoLayerCover');
        if (cover) { coverEl.src = cover; coverEl.style.display = 'block'; }
        else { coverEl.style.display = 'none'; }
        const langs = Object.keys(languageGroups);
        document.getElementById('twoLayerLangBtns').innerHTML = langs.map((lang, i) =>
            `<button type="button" class="two-layer-lang-btn ${i === 0 ? 'active' : ''}" data-lang="${lang}" onclick="selectTwoLayerLang('${lang}')">${getLangIcon(lang)} ${lang}</button>`
        ).join('');
        if (langs.length > 0) selectTwoLayerLang(langs[0]);
        // 預載歌詞（不自動顯示，由播放列按鈕控制）
        const lyricsStr = (lyrics || '').trim();
        _currentLyrics = lyricsStr;
        if (lyricsStr) {
            document.getElementById('lyricsTitle').textContent = songName + ' - 歌詞';
            document.getElementById('lyricsContent').textContent = lyricsStr;
        }
        document.getElementById('lyricsPanel').style.display = 'none';
        document.getElementById('twoLayerModal').style.display = 'flex';
    }

    function getLangIcon(lang) {
        const icons = { '中文': '🇨🇳', '英語': '🇺🇸', '日語': '🇯🇵', '韓語': '🇰🇷', '粵語': '🇭🇰', '其他': '🌐' };
        return icons[lang] || '🎵';
    }

    function selectTwoLayerLang(lang) {
        document.querySelectorAll('.two-layer-lang-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.lang === lang));
        const songs = twoLayerData[lang] || [];
        const container = document.getElementById('twoLayerSubBtns');
        if (!songs.length) { container.innerHTML = '<span style="color:#999;">此語言暫無版本</span>'; return; }
        container.innerHTML = songs.map((song, i) =>
            `<button type="button" class="two-layer-sub-btn ${i === 0 ? 'active' : ''}" data-file="${song.file}" onclick="selectTwoLayerTrack('${song.file}','${song.label}','${song.id}')">${song.label}</button>`
        ).join('');
        if (songs[0] && songs[0].file) selectTwoLayerTrack(songs[0].file, songs[0].label, songs[0].id);
    }

    function selectTwoLayerTrack(file, label, id) {
        twoLayerCurrentFile = file; twoLayerCurrentId = id;
        document.getElementById('twoLayerTrackName').textContent = label;
        document.querySelectorAll('.two-layer-sub-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.file === file));
    }

    function playTwoLayerTrack() {
        if (!twoLayerCurrentFile) { alert('請選擇版本'); return; }
        const title = document.getElementById('twoLayerTitle').textContent + ' - ' + document.getElementById('twoLayerTrackName').textContent;
        closeTwoLayerModal();
        playMusic(twoLayerCurrentFile, title, twoLayerCurrentId);
    }

    function downloadTwoLayerTrack() {
        if (!twoLayerCurrentFile) { alert('請選擇版本'); return; }
        const title = document.getElementById('twoLayerTitle').textContent + ' - ' + document.getElementById('twoLayerTrackName').textContent;
        const a = document.createElement('a');
        a.href = twoLayerCurrentFile;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.setAttribute('download', sanitizeMusicFilename(title) + '.mp3');
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

    function closeTwoLayerModal() {
        document.getElementById('twoLayerModal').style.display = 'none';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeLyricsModal(); closeTwoLayerModal(); }
    });
</script>

<!-- 底部播放列 -->
<div id="musicPlayerBar"
    style="display:none; position:fixed; bottom:0; left:0; right:0; background:linear-gradient(135deg,#667eea,#764ba2); padding:12px 20px; z-index:9999; box-shadow:0 -2px 10px rgba(0,0,0,0.3);">
    <div style="max-width:1200px; margin:0 auto; display:flex; align-items:center; gap:12px;">
        <button onclick="closeMusicPlayer()"
            style="background:rgba(255,255,255,0.2); border:none; color:#fff; width:35px; height:35px; border-radius:50%; cursor:pointer; font-size:1.2rem; flex-shrink:0;">&times;</button>
        <div id="musicPlayerTitle"
            style="color:#fff; font-weight:bold; min-width:120px; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex-shrink:0;">
        </div>
        <audio id="musicPlayer" controls style="flex:1; height:40px; min-width:0;">您的瀏覽器不支援音樂播放</audio>
        <a id="musicDownloadBtn" href="#" title="下載目前音樂"
            style="display:none; align-items:center; justify-content:center; background:rgba(255,255,255,0.2); border:none; color:#fff; width:38px; height:38px; border-radius:50%; cursor:pointer; font-size:1.1rem; flex-shrink:0; text-decoration:none;">
            <i class="fa-solid fa-download"></i>
        </a>
        <button id="lyricsToggleBtn" onclick="toggleLyricsPanel()" title="顯示歌詞"
            style="background:rgba(255,255,255,0.2); border:none; color:#fff; width:38px; height:38px; border-radius:50%; cursor:pointer; font-size:1.1rem; flex-shrink:0; opacity:0.4; transition:all 0.2s;"
            disabled>
            <i class="fa-solid fa-file-lines"></i>
        </button>
    </div>
</div>


<!-- 兩層分類播放器彈窗 -->
<div id="twoLayerModal" class="modal" onclick="if(event.target && event.target===this)closeTwoLayerModal()">
    <div class="modal-content"
        style="max-width:500px; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border-radius:20px;">
        <span class="modal-close" onclick="closeTwoLayerModal()" style="color:#fff;">&times;</span>
        <div style="text-align:center; margin-bottom:20px;">
            <img id="twoLayerCover" src="" alt=""
                style="width:120px; height:120px; object-fit:cover; border-radius:15px; margin-bottom:15px; box-shadow:0 8px 25px rgba(0,0,0,0.3); display:none;">
            <h2 id="twoLayerTitle" style="margin:0; font-size:1.4rem;"></h2>
        </div>
        <div style="margin-bottom:20px;">
            <div style="font-size:0.85rem; opacity:0.8; margin-bottom:10px;">選擇語言：</div>
            <div id="twoLayerLangBtns" style="display:flex; gap:8px; flex-wrap:wrap; justify-content:center;"></div>
        </div>
        <div style="background:rgba(255,255,255,0.15); border-radius:12px; padding:15px; margin-bottom:20px;">
            <div style="font-size:0.85rem; opacity:0.8; margin-bottom:10px;">選擇版本：</div>
            <div id="twoLayerSubBtns" style="display:flex; gap:8px; flex-wrap:wrap;"></div>
        </div>
        <div
            style="display:flex; align-items:center; gap:15px; background:rgba(0,0,0,0.2); border-radius:15px; padding:15px;">
            <div style="flex:1;">
                <div style="font-size:0.85rem; opacity:0.8;">已選版本：</div>
                <div id="twoLayerTrackName" style="font-weight:600; font-size:1.1rem;">請選擇</div>
            </div>
            <button onclick="downloadTwoLayerTrack()"
                style="width:52px; height:52px; border-radius:50%; border:none; background:#f3f4f6; color:#764ba2; font-size:1.2rem; cursor:pointer; box-shadow:0 4px 15px rgba(0,0,0,0.3);"><i
                    class="fas fa-download"></i></button>
            <button onclick="playTwoLayerTrack()"
                style="width:60px; height:60px; border-radius:50%; border:none; background:#fff; color:#764ba2; font-size:1.5rem; cursor:pointer; box-shadow:0 4px 15px rgba(0,0,0,0.3);"><i
                    class="fas fa-play"></i></button>
        </div>
    </div>
</div>

<style>
    .two-layer-lang-btn {
        padding: 10px 18px;
        border-radius: 25px;
        border: 2px solid rgba(255, 255, 255, 0.5);
        background: transparent;
        color: #fff;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .two-layer-lang-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .two-layer-lang-btn.active {
        background: #fff;
        color: #764ba2;
        border-color: #fff;
    }

    .two-layer-sub-btn {
        padding: 8px 16px;
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.4);
        background: transparent;
        color: #fff;
        cursor: pointer;
        transition: all 0.3s;
    }

    .two-layer-sub-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .two-layer-sub-btn.active {
        background: rgba(255, 255, 255, 0.3);
        border-color: #fff;
        font-weight: 600;
    }
    /* 音樂編輯版本 tabs */
    .music-version-tabs {
        background: #f8f4ff;
        border: 1px solid #e4d8ff;
        border-radius: 10px;
        padding: 12px 14px;
    }

    .music-edit-tab {
        padding: 5px 14px;
        border-radius: 20px;
        border: 1.5px solid #b39ddb;
        background: transparent;
        color: #7b1fa2;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .music-edit-tab:hover {
        background: #f3e5f5;
    }

    .music-edit-tab.active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: #fff;
        border-color: #764ba2;
    }
</style>
