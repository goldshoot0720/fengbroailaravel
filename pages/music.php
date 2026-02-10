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
    <button class="btn btn-primary" onclick="openModal()" title="新增音樂"><i class="fas fa-plus"></i></button>
    <div style="display: inline-block; margin-left: 10px;">
        <a href="export_zip_music.php" class="btn btn-success">
            <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
        </a>
        <button type="button" class="btn" onclick="document.getElementById('importZipFile').click()">
            <i class="fa-solid fa-file-zipper"></i> 匯入 ZIP
        </button>
        <input type="file" id="importZipFile" accept=".zip" style="display: none;" onchange="importZIP(this)">
    </div>

    <table class="table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>封面</th>
                <th>名稱</th>
                <th>分類</th>
                <th>語言版本</th>
                <th>備註</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #999;">暫無音樂</td>
                </tr>
            <?php else: ?>
                <?php foreach ($groupedItems as $groupKey => $group): ?>
                    <?php
                    $versionsData = [];
                    foreach ($group['items'] as $song) {
                        $versionsData[] = [
                            'id' => $song['id'],
                            'name' => $song['name'] ?? '',
                            'language' => $song['language'] ?? '',
                            'file' => $song['file'] ?? '',
                            'cover' => $song['cover'] ?? '',
                            'category' => $song['category'] ?? '',
                            'note' => $song['note'] ?? '',
                            'lyrics' => $song['lyrics'] ?? '',
                            'ref' => $song['ref'] ?? ''
                        ];
                    }
                    $versionsJson = json_encode($versionsData, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                    $versionCount = count($group['items']);
                    ?>
                    <tr>
                        <td>
                            <?php if (!empty($group['cover'])): ?>
                                <img src="<?php echo htmlspecialchars($group['cover']); ?>"
                                    style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($group['name']); ?></strong>
                            <?php if ($versionCount > 1): ?>
                                <span
                                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; margin-left: 8px;">
                                    <?php echo $versionCount; ?> 版本
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($group['category'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($group['languageSummary'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($group['note'] ?? '-'); ?></td>
                        <td>
                            <?php if (!empty($group['languageGroups'])): ?>
                                <?php $playerId = 'player_' . md5($group['name']); ?>
                                <button class="btn btn-sm btn-primary"
                                    onclick="openTwoLayerPlayer('<?php echo $playerId; ?>', <?php echo htmlspecialchars(json_encode($group['languageGroups'], JSON_UNESCAPED_UNICODE)); ?>, '<?php echo htmlspecialchars($group['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($group['cover'] ?? '', ENT_QUOTES); ?>')">
                                    <i class="fa-solid fa-play"></i>
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-success"
                                data-versions='<?php echo htmlspecialchars($versionsJson, ENT_QUOTES); ?>'
                                data-name="<?php echo htmlspecialchars($group['name'], ENT_QUOTES); ?>"
                                onclick="openVersionsModalFromBtn(this)">
                                <i class="fa-solid fa-list"></i> 管理
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="modal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">新增音樂</h2>
        <form id="itemForm">
            <input type="hidden" id="itemId" name="id">
            <div class="form-group">
                <label>名稱 *</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label>檔案路徑</label>
                <input type="text" class="form-control" id="file" name="file" placeholder="輸入音樂網址或上傳">
                <div style="margin-top: 8px;">
                    <input type="file" id="musicFile" accept="audio/*" onchange="uploadMusic()" style="display: none;">
                    <button type="button" class="btn" onclick="document.getElementById('musicFile').click()">
                        <i class="fa-solid fa-upload"></i> 上傳音樂
                    </button>
                </div>
                <div id="musicPreview" style="margin-top: 10px;"></div>
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
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>分類</label>
                    <input type="text" class="form-control" id="category" name="category" list="categoryList"
                        placeholder="選擇或輸入分類">
                    <datalist id="categoryList">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                    </datalist>
                    <?php if (!empty($categories)): ?>
                        <div style="margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap;">
                            <?php foreach ($categories as $cat): ?>
                                <button type="button" class="btn btn-sm"
                                    onclick="setCategory('<?php echo htmlspecialchars($cat); ?>')"><?php echo htmlspecialchars($cat); ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group" style="flex:1">
                    <label>語言</label>
                    <input type="text" class="form-control" id="language" name="language" list="languageList"
                        placeholder="選擇或輸入語言">
                    <datalist id="languageList">
                        <?php foreach ($allLanguages as $lang): ?>
                            <option value="<?php echo htmlspecialchars($lang); ?>">
                            <?php endforeach; ?>
                    </datalist>
                    <div style="margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap;">
                        <?php foreach ($defaultLanguages as $lang): ?>
                            <button type="button" class="btn btn-sm btn-primary"
                                onclick="setLanguage('<?php echo htmlspecialchars($lang); ?>')"><?php echo htmlspecialchars($lang); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    // 顯示資料庫中已有但不在預設列表中的語言
                    $customLanguages = array_diff($existingLanguages, $defaultLanguages);
                    if (!empty($customLanguages)):
                        ?>
                        <div style="margin-top: 6px;">
                            <small style="color: #666;">已有語言：</small>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 4px;">
                                <?php foreach ($customLanguages as $lang): ?>
                                    <button type="button" class="btn btn-sm"
                                        onclick="setLanguage('<?php echo htmlspecialchars($lang); ?>')"><?php echo htmlspecialchars($lang); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group">
                <label>參考</label>
                <input type="text" class="form-control" id="ref" name="ref">
            </div>
            <div class="form-group">
                <label>備註</label>
                <textarea class="form-control" id="note" name="note" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>歌詞</label>
                <textarea class="form-control" id="lyrics" name="lyrics" rows="6"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">儲存</button>
        </form>
    </div>
</div>

<!-- Fixed Music Player Bar -->
<div id="musicPlayerBar"
    style="display: none; position: fixed; bottom: 0; left: 0; right: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px 20px; z-index: 9999; box-shadow: 0 -2px 10px rgba(0,0,0,0.3);">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; align-items: center; gap: 15px;">
        <button onclick="closeMusicPlayer()"
            style="background: rgba(255,255,255,0.2); border: none; color: #fff; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; font-size: 1.2rem;">
            &times;
        </button>
        <div style="color: #fff; font-weight: bold; min-width: 150px; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
            id="musicPlayerTitle"></div>
        <audio id="musicPlayer" controls style="flex: 1; height: 40px;">
            您的瀏覽器不支援音樂播放
        </audio>
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

    function openModal() {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modalTitle').textContent = '新增音樂';
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '';
        document.getElementById('musicPreview').innerHTML = '';
        document.getElementById('coverPreview').innerHTML = '';
    }

    function closeModal() {
        document.getElementById('modal').style.display = 'none';
    }

    function closeLyricsModal() {
        document.getElementById('lyricsPanel').style.display = 'none';
    }

    function setCategory(value) {
        document.getElementById('category').value = value;
    }

    function setLanguage(value) {
        document.getElementById('language').value = value;
    }

    function viewLyrics(id) {
        console.log('viewLyrics called with id:', id);
        fetch(`api.php?action=get&table=${TABLE}&id=${id}`)
            .then(r => r.json())
            .then(res => {
                console.log('viewLyrics response:', res);
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

    function editItem(id) {
        console.log('editItem called with id:', id);
        fetch(`api.php?action=get&table=${TABLE}&id=${id}`)
            .then(r => r.json())
            .then(res => {
                console.log('editItem response:', res);
                if (res.success && res.data) {
                    const d = res.data;
                    document.getElementById('itemId').value = d.id;
                    document.getElementById('name').value = d.name || '';
                    document.getElementById('file').value = d.file || '';
                    document.getElementById('cover').value = d.cover || '';
                    document.getElementById('category').value = d.category || '';
                    document.getElementById('language').value = d.language || '';
                    document.getElementById('ref').value = d.ref || '';
                    document.getElementById('note').value = d.note || '';
                    document.getElementById('lyrics').value = d.lyrics || '';
                    updateMusicPreview();
                    updateCoverPreview();
                    document.getElementById('modalTitle').textContent = '編輯音樂';
                    document.getElementById('modal').style.display = 'flex';
                } else {
                    alert('無法載入音樂資料: ' + (res.error || '未知錯誤'));
                }
            })
            .catch(err => {
                console.error('editItem error:', err);
                alert('載入失敗: ' + err.message);
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
            category: document.getElementById('category').value,
            language: document.getElementById('language').value,
            ref: document.getElementById('ref').value,
            note: document.getElementById('note').value,
            lyrics: document.getElementById('lyrics').value
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

    function uploadMusic() {
        const input = document.getElementById('musicFile');
        if (!input.files || !input.files[0]) return;

        uploadFileWithProgress(input.files[0],
            function (res) {
                document.getElementById('file').value = res.file;
                const nameInput = document.getElementById('name');
                if (nameInput && !nameInput.value) {
                    // Remove file extension from filename for name
                    let filename = res.filename || '';
                    filename = filename.replace(/\.[^/.]+$/, '');
                    nameInput.value = filename;
                }
                updateMusicPreview();
            },
            function (error) {
                alert('上傳失敗: ' + error);
            }
        );
        input.value = '';
    }

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

    function updateMusicPreview() {
        const file = document.getElementById('file').value;
        const preview = document.getElementById('musicPreview');

        if (file) {
            preview.innerHTML = `<audio src="${file}" controls style="width: 100%;"></audio>`;
        } else {
            preview.innerHTML = '';
        }
    }

    function updateCoverPreview() {
        const file = document.getElementById('cover').value;
        const preview = document.getElementById('coverPreview');

        if (file) {
            preview.innerHTML = `<img src="${file}" style="max-width: 150px; max-height: 150px; border-radius: 5px;">`;
        } else {
            preview.innerHTML = '';
        }
    }

    document.getElementById('file').addEventListener('input', updateMusicPreview);
    document.getElementById('cover').addEventListener('input', updateCoverPreview);

    function playMusic(src, title, musicId) {
        const bar = document.getElementById('musicPlayerBar');
        const player = document.getElementById('musicPlayer');
        const titleEl = document.getElementById('musicPlayerTitle');

        titleEl.textContent = title;
        player.src = src;
        bar.style.display = 'block';
        player.play();

        // 自動載入並顯示歌詞（支持回退查找）
        if (musicId) {
            fetch(`api.php?action=get&table=${TABLE}&id=${musicId}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data) {
                        const songData = res.data;
                        if (songData.lyrics && songData.lyrics.trim() !== '') {
                            // 當前版本有歌詞
                            document.getElementById('lyricsTitle').textContent = songData.name + ' - 歌詞';
                            document.getElementById('lyricsContent').textContent = songData.lyrics;
                            document.getElementById('lyricsPanel').style.display = 'block';
                        } else {
                            // 當前版本沒有歌詞，嘗試查找同歌曲其他版本（優先同語言）
                            findFallbackLyrics(songData.name, songData.language);
                        }
                    }
                })
                .catch(err => console.error('載入歌詞失敗:', err));
        }
    }

    // 查找回退歌詞（優先同語言類別）
    function findFallbackLyrics(songName, currentLanguage) {
        fetch(`api.php?action=list&table=${TABLE}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    // 過濾出同名歌曲的所有版本
                    const sameSongVersions = res.data.filter(item =>
                        item.name === songName &&
                        item.lyrics &&
                        item.lyrics.trim() !== ''
                    );

                    // 只查找同語言類別的版本，不跨語言回退
                    const baseLanguage = getBaseLanguage(currentLanguage);
                    const sameLangVersion = sameSongVersions.find(item =>
                        getBaseLanguage(item.language || '') === baseLanguage
                    );

                    if (sameLangVersion) {
                        document.getElementById('lyricsTitle').textContent = songName + ' - 歌詞 (' + (sameLangVersion.language || baseLanguage) + ')';
                        document.getElementById('lyricsContent').textContent = sameLangVersion.lyrics;
                        document.getElementById('lyricsPanel').style.display = 'block';
                    } else {
                        // 同語言類別沒有歌詞
                        document.getElementById('lyricsTitle').textContent = songName + ' - 歌詞';
                        document.getElementById('lyricsContent').textContent = '暫無' + baseLanguage + '歌詞';
                        document.getElementById('lyricsPanel').style.display = 'block';
                    }
                }
            })
            .catch(err => {
                console.error('查找回退歌詞失敗:', err);
                document.getElementById('lyricsTitle').textContent = songName + ' - 歌詞';
                document.getElementById('lyricsContent').textContent = '暫無歌詞';
                document.getElementById('lyricsPanel').style.display = 'block';
            });
    }

    // 取得基礎語言類別
    function getBaseLanguage(lang) {
        if (!lang) return '其他';
        const mainLanguages = ['中文', '英語', '日語', '韓語', '粵語'];
        for (const main of mainLanguages) {
            if (lang.indexOf(main) === 0) return main;
        }
        return '其他';
    }

    function playSelected(selectId) {
        const select = document.getElementById(selectId);
        if (!select || select.selectedIndex < 0) return;
        const option = select.options[select.selectedIndex];
        const file = option.getAttribute('data-file') || '';
        const title = option.getAttribute('data-title') || '';
        if (file) playMusic(file, title);
    }

    function closeMusicPlayer() {
        const bar = document.getElementById('musicPlayerBar');
        const player = document.getElementById('musicPlayer');

        player.pause();
        player.src = '';
        bar.style.display = 'none';
    }

    // Close on ESC key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeLyricsModal();
        }
    });

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

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressText.textContent = percent + '%';
                const loaded = formatFileSize(e.loaded);
                const total = formatFileSize(e.total);
                fileName.textContent = file.name + ' (' + loaded + ' / ' + total + ')';
            }
        });

        xhr.addEventListener('load', function () {
            modal.style.display = 'none';
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.success) {
                    alert('匯入完成！\n成功匯入: ' + res.imported + ' 首音樂');
                    location.reload();
                } else {
                    alert('匯入失敗: ' + (res.error || '未知錯誤'));
                }
            } catch (e) {
                alert('匯入失敗: 回應格式錯誤');
            }
        });

        xhr.addEventListener('error', function () {
            modal.style.display = 'none';
            alert('匯入失敗: 網路錯誤');
        });

        xhr.open('POST', 'import_zip_music.php');
        xhr.send(formData);
        input.value = '';
    }

    // ========== 兩層分類播放器 ==========
    let twoLayerData = null;
    let twoLayerCurrentLang = null;
    let twoLayerCurrentFile = null;
    let twoLayerCurrentId = null;

    function openTwoLayerPlayer(playerId, languageGroups, songName, cover) {
        twoLayerData = languageGroups;

        // 更新標題和封面
        document.getElementById('twoLayerTitle').textContent = songName;
        const coverEl = document.getElementById('twoLayerCover');
        if (cover) {
            coverEl.src = cover;
            coverEl.style.display = 'block';
        } else {
            coverEl.style.display = 'none';
        }

        // 渲染第一層語言按鈕
        const langContainer = document.getElementById('twoLayerLangBtns');
        const langs = Object.keys(languageGroups);
        langContainer.innerHTML = langs.map((lang, i) => `
            <button type="button" class="two-layer-lang-btn ${i === 0 ? 'active' : ''}" 
                    data-lang="${lang}" onclick="selectTwoLayerLang('${lang}')">
                ${getLangIcon(lang)} ${lang}
            </button>
        `).join('');

        // 選擇第一個語言
        if (langs.length > 0) {
            selectTwoLayerLang(langs[0]);
        }

        // 顯示彈窗
        document.getElementById('twoLayerModal').style.display = 'flex';
    }

    function getLangIcon(lang) {
        const icons = {
            '中文': '🇨🇳',
            '英語': '🇺🇸',
            '日語': '🇯🇵',
            '韓語': '🇰🇷',
            '粵語': '🇭🇰',
            '其他': '🌐'
        };
        return icons[lang] || '🎵';
    }

    function selectTwoLayerLang(lang) {
        twoLayerCurrentLang = lang;

        // 更新語言按鈕樣式
        document.querySelectorAll('.two-layer-lang-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.lang === lang);
        });

        // 渲染第二層子分類
        renderTwoLayerSubs(lang);
    }

    function renderTwoLayerSubs(lang) {
        const container = document.getElementById('twoLayerSubBtns');
        const songs = twoLayerData[lang] || [];

        if (songs.length === 0) {
            container.innerHTML = '<span style="color: #999;">此語言暫無版本</span>';
            return;
        }

        container.innerHTML = songs.map((song, i) => `
            <button type="button" class="two-layer-sub-btn ${i === 0 ? 'active' : ''}" 
                    data-file="${song.file}" data-label="${song.label}" data-id="${song.id}"
                    onclick="selectTwoLayerTrack('${song.file}', '${song.label}', '${song.id}')">
                ${song.label}
            </button>
        `).join('');

        // 自動選擇第一個
        if (songs.length > 0 && songs[0].file) {
            selectTwoLayerTrack(songs[0].file, songs[0].label, songs[0].id);
        }
    }

    function selectTwoLayerTrack(file, label, id) {
        twoLayerCurrentFile = file;
        twoLayerCurrentId = id;
        document.getElementById('twoLayerTrackName').textContent = label;

        // 更新按鈕樣式
        document.querySelectorAll('.two-layer-sub-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.file === file);
        });
    }

    function playTwoLayerTrack() {
        if (!twoLayerCurrentFile) {
            alert('請選擇版本');
            return;
        }

        const title = document.getElementById('twoLayerTitle').textContent + ' - ' +
            document.getElementById('twoLayerTrackName').textContent;

        closeTwoLayerModal();
        playMusic(twoLayerCurrentFile, title, twoLayerCurrentId);
    }

    function closeTwoLayerModal() {
        document.getElementById('twoLayerModal').style.display = 'none';
    }
</script>

<!-- 兩層分類播放器彈窗 -->
<div id="twoLayerModal" class="modal" onclick="if(event.target===this)closeTwoLayerModal()">
    <div class="modal-content"
        style="max-width: 500px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border-radius: 20px;">
        <span class="modal-close" onclick="closeTwoLayerModal()" style="color: #fff;">&times;</span>

        <!-- 封面和標題 -->
        <div style="text-align: center; margin-bottom: 20px;">
            <img id="twoLayerCover" src="" alt=""
                style="width: 120px; height: 120px; object-fit: cover; border-radius: 15px; margin-bottom: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.3); display: none;">
            <h2 id="twoLayerTitle" style="margin: 0; font-size: 1.4rem;">歌曲名稱</h2>
        </div>

        <!-- 第一層：語言選擇 -->
        <div style="margin-bottom: 20px;">
            <div style="font-size: 0.85rem; opacity: 0.8; margin-bottom: 10px;">選擇語言：</div>
            <div id="twoLayerLangBtns" style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
                <!-- 動態填充 -->
            </div>
        </div>

        <!-- 第二層：子分類選擇 -->
        <div style="background: rgba(255,255,255,0.15); border-radius: 12px; padding: 15px; margin-bottom: 20px;">
            <div style="font-size: 0.85rem; opacity: 0.8; margin-bottom: 10px;">選擇版本：</div>
            <div id="twoLayerSubBtns" style="display: flex; gap: 8px; flex-wrap: wrap;">
                <!-- 動態填充 -->
            </div>
        </div>

        <!-- 當前選擇和播放按鈕 -->
        <div
            style="display: flex; align-items: center; gap: 15px; background: rgba(0,0,0,0.2); border-radius: 15px; padding: 15px;">
            <div style="flex: 1;">
                <div style="font-size: 0.85rem; opacity: 0.8;">已選版本：</div>
                <div id="twoLayerTrackName" style="font-weight: 600; font-size: 1.1rem;">請選擇</div>
            </div>
            <button onclick="playTwoLayerTrack()"
                style="width: 60px; height: 60px; border-radius: 50%; border: none; background: #fff; color: #764ba2; font-size: 1.5rem; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
                <i class="fas fa-play"></i>
            </button>
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
</style>

<!-- 版本管理彈窗 -->
<div id="versionsModal" class="modal" onclick="if(event.target===this)closeVersionsModal()">
    <div class="modal-content" style="max-width: 700px;">
        <span class="modal-close" onclick="closeVersionsModal()">&times;</span>
        <h2 id="versionsModalTitle">管理版本</h2>

        <div style="margin-bottom: 15px;">
            <button class="btn btn-primary btn-sm" onclick="addNewVersion()">
                <i class="fa-solid fa-plus"></i> 新增版本
            </button>
        </div>

        <div id="versionsTableContainer">
            <table class="table">
                <thead>
                    <tr>
                        <th>語言/版本</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="versionsTableBody">
                    <!-- 動態填充 -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let currentVersions = [];
    let currentSongName = '';

    function openVersionsModal(versions, songName) {
        currentVersions = versions;
        currentSongName = songName;

        document.getElementById('versionsModalTitle').textContent = songName + ' - 版本管理';

        const tbody = document.getElementById('versionsTableBody');
        tbody.innerHTML = versions.map(v => {
            const langIcon = getLangIconForVersion(v.language);
            return `
            <tr>
                <td>
                    ${langIcon} <strong>${v.language || '未設定語言'}</strong>
                    ${v.file ? '<span style="color: #27ae60; margin-left: 8px;"><i class="fa-solid fa-music"></i></span>' : '<span style="color: #999; margin-left: 8px;">無檔案</span>'}
                </td>
                <td>
                    ${v.file ? `<button class="btn btn-sm btn-primary" type="button" data-action="play" data-file="${escapeHtml(v.file)}" data-label="${escapeHtml(songName + ' - ' + (v.language || ''))}" data-id="${v.id}"><i class="fa-solid fa-play"></i></button>` : ''}
                    <button class="btn btn-sm" type="button" data-action="lyrics" data-id="${v.id}">歌詞</button>
                    <span class="card-edit-btn" style="cursor: pointer;" data-action="edit" data-id="${v.id}"><i class="fas fa-pen"></i></span>
                    <span class="card-delete-btn" style="margin-left: 10px; cursor: pointer;" data-action="delete" data-id="${v.id}">&times;</span>
                </td>
            </tr>
        `;
        }).join('');

        // 綁定按鈕事件（含 span 編輯/刪除按鈕）
        tbody.querySelectorAll('[data-action]').forEach(btn => {
            btn.onclick = function (e) {
                e.preventDefault();
                e.stopPropagation();
                const action = this.dataset.action;
                const id = this.dataset.id;
                const file = this.dataset.file;
                const label = this.dataset.label;

                if (action === 'play') {
                    closeVersionsModal();
                    playMusic(file, label, id);
                } else if (action === 'lyrics') {
                    viewLyrics(id);
                } else if (action === 'edit') {
                    editVersionItem(id);
                } else if (action === 'delete') {
                    deleteVersionItem(id);
                }
            };
        });

        document.getElementById('versionsModal').style.display = 'flex';
    }

    function getLangIconForVersion(lang) {
        if (!lang) return '🌐';
        if (lang.indexOf('中文') === 0) return '🇨🇳';
        if (lang.indexOf('英') === 0) return '🇺🇸';
        if (lang.indexOf('日') === 0) return '🇯🇵';
        if (lang.indexOf('韓') === 0) return '🇰🇷';
        if (lang.indexOf('粵') === 0) return '🇭🇰';
        return '🌐';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    function closeVersionsModal() {
        document.getElementById('versionsModal').style.display = 'none';
    }

    function addNewVersion() {
        closeVersionsModal();
        openModal();
        // 預填名稱
        document.getElementById('name').value = currentSongName;
    }

    function deleteVersionItem(id) {
        if (confirm('確定要刪除此版本嗎？')) {
            fetch('api.php?action=delete&table=music&id=' + id)
                .then(r => r.json())
                .then(res => {
                    if (res.success) location.reload();
                    else alert('刪除失敗');
                });
        }
    }

    function editVersionItem(id) {
        closeVersionsModal();
        // 使用 setTimeout 確保彈窗完全關閉後再打開編輯
        setTimeout(function () {
            editItem(id);
        }, 100);
    }

    function openVersionsModalFromBtn(btn) {
        const versionsData = JSON.parse(btn.getAttribute('data-versions'));
        const songName = btn.getAttribute('data-name');
        openVersionsModal(versionsData, songName);
    }
</script>