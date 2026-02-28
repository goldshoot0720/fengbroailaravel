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
    <h1>鋒兄音樂</h1>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <button class="btn btn-primary" onclick="handleAdd()" title="新增音樂"><i class="fas fa-plus"></i></button>
    <?php $csvTable = 'music'; include 'includes/csv_buttons.php'; ?>
    <div style="display: inline-block; margin-left: 10px;">
        <a href="export_zip_music.php" class="btn btn-success">
            <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
        </a>
        <button type="button" class="btn" onclick="document.getElementById('importZipFile').click()">
            <i class="fa-solid fa-file-zipper"></i> 匯入 ZIP
        </button>
        <input type="file" id="importZipFile" accept=".zip" style="display: none;" onchange="importZIP(this)">
    </div>

    <div class="card-grid" style="margin-top: 20px;">
        <div id="inlineAddCard" class="card inline-add-card">
            <div class="inline-edit inline-edit-always">
                <div class="form-group">
                    <label>名稱 *</label>
                    <input type="text" class="form-control inline-input" data-field="name" required>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:1">
                        <label>分類</label>
                        <input type="text" class="form-control inline-input" data-field="category" list="categoryOptions" placeholder="選擇或輸入分類">
                        <datalist id="categoryOptions">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>語言</label>
                        <input type="text" class="form-control inline-input" data-field="language" list="languageOptions" placeholder="選擇或輸入語言">
                        <datalist id="languageOptions">
                            <?php foreach ($allLanguages as $lang): ?>
                                <option value="<?php echo htmlspecialchars($lang); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                <div class="form-group">
                    <label>檔案路徑</label>
                    <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入音樂網址" oninput="updateInlineAudioPreview(this)">
                    <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                        <input type="file" class="inline-audio-file" accept="audio/*" style="display: none;" onchange="uploadInlineAudio(this)">
                        <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳音樂</button>
                    </div>
                    <div class="inline-audio-preview" style="margin-top: 6px;"></div>
                </div>
                <div class="form-group">
                    <label>封面圖</label>
                    <input type="text" class="form-control inline-input" data-field="cover" placeholder="輸入封面圖網址" oninput="updateInlineMusicCoverPreview(this)">
                    <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                        <input type="file" class="inline-cover-file" accept="image/*" style="display: none;" onchange="uploadInlineMusicCover(this)">
                        <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳封面</button>
                        <div class="inline-music-cover-preview"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>參考</label>
                    <input type="text" class="form-control inline-input" data-field="ref">
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
                <div class="card" data-id="<?php echo $group['items'][0]['id']; ?>"
                    data-name="<?php echo htmlspecialchars($group['name'], ENT_QUOTES); ?>"
                    data-category="<?php echo htmlspecialchars($group['category'] ?? '', ENT_QUOTES); ?>"
                    data-language="<?php echo htmlspecialchars($group['items'][0]['language'] ?? '', ENT_QUOTES); ?>"
                    data-file="<?php echo htmlspecialchars($group['items'][0]['file'] ?? '', ENT_QUOTES); ?>"
                    data-cover="<?php echo htmlspecialchars($group['cover'] ?? '', ENT_QUOTES); ?>"
                    data-ref="<?php echo htmlspecialchars($group['ref'] ?? '', ENT_QUOTES); ?>"
                    data-note="<?php echo htmlspecialchars($group['note'] ?? '', ENT_QUOTES); ?>"
                    data-lyrics="<?php echo htmlspecialchars($group['lyrics'] ?? '', ENT_QUOTES); ?>">
                    
                    <div class="inline-view">
                        <?php if (!empty($group['cover'])): ?>
                            <div style="text-align: center; margin-bottom: 15px;">
                                <img src="<?php echo htmlspecialchars($group['cover']); ?>"
                                    style="width: 120px; height: 120px; object-fit: cover; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                            </div>
                        <?php endif; ?>
                        
                        <h3 style="margin: 0 0 10px 0; color: #333;">
                            <?php echo htmlspecialchars($group['name']); ?>
                            <?php if (count($group['items']) > 1): ?>
                                <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; margin-left: 8px;">
                                    <?php echo count($group['items']); ?> 版本
                                </span>
                            <?php endif; ?>
                        </h3>
                        
                        <div style="color: #666; font-size: 0.9rem; margin-bottom: 10px;">
                            <?php if (!empty($group['category'])): ?>
                                <span style="background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 4px; margin-right: 5px;">
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
                                <?php echo nl2br(htmlspecialchars(mb_substr($group['note'], 0, 100))); ?><?php echo mb_strlen($group['note']) > 100 ? '...' : ''; ?>
                            </p>
                        <?php endif; ?>
                        
                        <div style="margin-top: 15px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php if (!empty($group['languageGroups'])): ?>
                                <?php $playerId = 'player_' . md5($group['name']); ?>
                                <button class="btn btn-sm btn-primary"
                                    onclick="openTwoLayerPlayer('<?php echo $playerId; ?>', <?php echo htmlspecialchars(json_encode($group['languageGroups'], JSON_UNESCAPED_UNICODE)); ?>, '<?php echo htmlspecialchars($group['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($group['cover'] ?? '', ENT_QUOTES); ?>')">
                                    <i class="fa-solid fa-play"></i> 播放
                                </button>
                            <?php endif; ?>
                            
                            <?php if (!empty($group['lyrics'])): ?>
                                <button class="btn btn-sm btn-info" onclick="viewLyrics('<?php echo $group['items'][0]['id']; ?>')">
                                    <i class="fa-solid fa-file-lines"></i> 歌詞
                                </button>
                            <?php endif; ?>
                            
                            <?php if (!empty($group['ref'])): ?>
                                <a href="<?php echo htmlspecialchars($group['ref']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                                    <i class="fa-solid fa-external-link-alt"></i> 參考
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-sm btn-warning" onclick="startInlineEdit('<?php echo $group['items'][0]['id']; ?>')">
                                <i class="fa-solid fa-edit"></i> 編輯
                            </button>
                            
                            <button class="btn btn-sm btn-danger" onclick="deleteItem('<?php echo $group['items'][0]['id']; ?>')">
                                <i class="fa-solid fa-trash"></i> 刪除
                            </button>
                        </div>
                    </div>
                    
                    <div class="inline-edit">
                        <div class="form-group">
                            <label>名稱 *</label>
                            <input type="text" class="form-control inline-input" data-field="name" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="flex:1">
                                <label>分類</label>
                                <input type="text" class="form-control inline-input" data-field="category" list="categoryOptions" placeholder="選擇或輸入分類">
                            </div>
                            <div class="form-group" style="flex:1">
                                <label>語言</label>
                                <input type="text" class="form-control inline-input" data-field="language" list="languageOptions" placeholder="選擇或輸入語言">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>檔案路徑</label>
                            <input type="text" class="form-control inline-input" data-field="file" placeholder="輸入音樂網址" oninput="updateInlineAudioPreview(this)">
                            <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                <input type="file" class="inline-audio-file" accept="audio/*" style="display: none;" onchange="uploadInlineAudio(this)">
                                <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳音樂</button>
                            </div>
                            <div class="inline-audio-preview" style="margin-top: 6px;"></div>
                        </div>
                        <div class="form-group">
                            <label>封面圖</label>
                            <input type="text" class="form-control inline-input" data-field="cover" placeholder="輸入封面圖網址" oninput="updateInlineMusicCoverPreview(this)">
                            <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                <input type="file" class="inline-cover-file" accept="image/*" style="display: none;" onchange="uploadInlineMusicCover(this)">
                                <button type="button" class="btn" onclick="this.previousElementSibling.click()" style="padding: 2px 10px; font-size: 0.75rem;"><i class="fas fa-upload"></i> 上傳封面</button>
                                <div class="inline-music-cover-preview"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>參考</label>
                            <input type="text" class="form-control inline-input" data-field="ref">
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
                            <button type="button" class="btn btn-primary" onclick="saveInlineEdit('<?php echo $group['items'][0]['id']; ?>')">儲存</button>
                            <button type="button" class="btn" onclick="cancelInlineEdit('<?php echo $group['items'][0]['id']; ?>')">取消</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Lyrics Panel -->
<div id="lyricsPanel"
    style="display: none; position: fixed; top: 0; right: 0; width: 350px; height: 100%; background: #fff; box-shadow: -2px 0 10px rgba(0,0,0,0.2); z-index: 9998; overflow-y: auto;">
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
            ? `<audio src="${url}" controls style="width: 100%; margin-top: 4px;"></audio>`
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
            ? `<img src="${url}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">`
            : '';
    }

    function fillInlineInputs(card) {
        const data = card.dataset;
        card.querySelectorAll('[data-field]').forEach(input => {
            const field = input.dataset.field;
            input.value = data[field] || data[field + 'Value'] || '';
            input.classList.remove('error', 'success');
        });
        const fileInput = card.querySelector('[data-field="file"]');
        if (fileInput) updateInlineAudioPreview(fileInput);
        const coverInput = card.querySelector('[data-field="cover"]');
        if (coverInput) updateInlineMusicCoverPreview(coverInput);
    }

    function closeLyricsModal() {
        document.getElementById('lyricsPanel').style.display = 'none';
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
                        location.reload();
                    } else {
                        alert('刪除失敗: ' + (res.error || ''));
                    }
                });
        }
    }

    function importZIP(input) {
        if (!input.files || !input.files[0]) return;

        if (!confirm('確定要匯入 ZIP 嗎？音樂將會新增到資料庫。')) {
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
                    let msg = '匯入完成！\n成功匯入: ' + res.imported + ' 首音樂';
                    if (res.errors && res.errors.length > 0) {
                        msg += '\n\n錯誤明細:\n' + res.errors.join('\n');
                    }
                    alert(msg);
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

        xhr.open('POST', 'import_zip_music.php');
        xhr.send(formData);
        input.value = '';
    }
</script>
