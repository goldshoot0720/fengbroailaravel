<?php
$pageTitle = '筆記本';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM article ORDER BY created_at DESC")->fetchAll();
$categories = [];
$hasUncategorized = false;
foreach ($items as $item) {
    $category = trim($item['category'] ?? '');
    if ($category !== '') {
        $categories[$category] = true;
    } else {
        $hasUncategorized = true;
    }
}
$categories = array_keys($categories);
sort($categories);
?>

<div class="content-header" style="display: flex; align-items: center; justify-content: space-between;">
    <h1>鋒兄筆記</h1>
    <div>
        <select id="categoryFilter" class="form-control" onchange="filterNotes()">
            <option value="__all">全部分類</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
            <?php endforeach; ?>
            <?php if ($hasUncategorized): ?>
                <option value="__uncategorized">未分類筆記</option>
            <?php endif; ?>
        </select>
    </div>
</div>

<div class="content-body">
    <button class="btn btn-primary" onclick="openModal()">新增筆記</button>
    <?php $csvTable = 'article'; include 'includes/csv_buttons.php'; ?>

    <div class="card-grid" style="margin-top: 20px;">
        <?php if (empty($items)): ?>
            <div class="card"><p style="text-align: center; color: #999;">暫無筆記資料</p></div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="card" data-category="<?php echo htmlspecialchars(!empty($item['category']) ? $item['category'] : '__uncategorized'); ?>">
                    <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                    <p style="color: #666; margin-bottom: 10px;"><?php echo htmlspecialchars(!empty($item['category']) ? $item['category'] : '未分類筆記'); ?></p>
                    <?php
                    $content = $item['content'] ?? '';
                    $shortContent = mb_substr($content, 0, 100);
                    $isLongContent = mb_strlen($content) > 100;
                    ?>
                    <p id="contentShort-<?php echo $item['id']; ?>" style="margin-bottom: 15px;"><?php echo nl2br(htmlspecialchars($shortContent)); ?><?php echo $isLongContent ? '...' : ''; ?></p>
                    <p id="contentFull-<?php echo $item['id']; ?>" style="margin-bottom: 15px; display: none;"><?php echo nl2br(htmlspecialchars($content)); ?></p>
                    <textarea id="contentRaw-<?php echo $item['id']; ?>" style="display: none;"><?php echo htmlspecialchars($content); ?></textarea>
                    <div style="margin-bottom: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php if ($isLongContent): ?>
                            <button id="contentToggle-<?php echo $item['id']; ?>" type="button" class="btn btn-sm" onclick="toggleNoteContent('<?php echo $item['id']; ?>')">完整顯示</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm" onclick="copyNoteContent('<?php echo $item['id']; ?>')">複製內容</button>
                    </div>
                    <?php
                    // 顯示檔案
                    $hasFiles = false;
                    for ($i = 1; $i <= 3; $i++) {
                        if (!empty($item["file{$i}"])) {
                            $hasFiles = true;
                            break;
                        }
                    }
                    if ($hasFiles):
                    ?>
                    <div style="margin: 10px 0; display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <?php if (!empty($item["file{$i}"])): ?>
                                <?php
                                $filetype = $item["file{$i}type"] ?? '';
                                $filename = $item["file{$i}name"] ?? '檔案';
                                $filepath = $item["file{$i}"];
                                ?>
                                <a href="<?php echo htmlspecialchars($filepath); ?>" target="_blank" style="text-decoration: none;">
                                    <?php if (strpos($filetype, 'image/') === 0): ?>
                                        <img src="<?php echo htmlspecialchars($filepath); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;">
                                    <?php elseif (strpos($filetype, 'video/') === 0): ?>
                                        <div style="width: 60px; height: 60px; background: #34495e; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fa-solid fa-video" style="color: #fff; font-size: 1.5rem;"></i>
                                        </div>
                                    <?php elseif (strpos($filetype, 'audio/') === 0): ?>
                                        <div style="width: 60px; height: 60px; background: #9b59b6; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fa-solid fa-music" style="color: #fff; font-size: 1.5rem;"></i>
                                        </div>
                                    <?php elseif ($filetype === 'application/pdf'): ?>
                                        <div style="width: 60px; height: 60px; background: #e74c3c; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fa-solid fa-file-pdf" style="color: #fff; font-size: 1.5rem;"></i>
                                        </div>
                                    <?php else: ?>
                                        <div style="width: 60px; height: 60px; background: #3498db; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fa-solid fa-file" style="color: #fff; font-size: 1.5rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    <p style="font-size: 0.8rem; color: #999;"><?php echo formatDateTime($item['created_at']); ?></p>
                    <div style="margin-top: 15px;">
                        <button class="btn btn-sm" onclick="editItem('<?php echo $item['id']; ?>')">編輯</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteItem('<?php echo $item['id']; ?>')">刪除</button>
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
                    <input type="text" class="form-control" id="category" name="category" list="categoryOptions" placeholder="可選既有分類或自行輸入">
                    <datalist id="categoryOptions">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <?php if (!empty($categories)): ?>
                        <div style="margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap;">
                            <?php foreach ($categories as $category): ?>
                                <button type="button" class="btn btn-sm" onclick="setCategory('<?php echo htmlspecialchars($category); ?>')"><?php echo htmlspecialchars($category); ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
</style>

<?php include 'includes/upload-progress.php'; ?>

<script>
const TABLE = 'article';

function toggleNoteContent(id) {
    const shortEl = document.getElementById('contentShort-' + id);
    const fullEl = document.getElementById('contentFull-' + id);
    const toggleBtn = document.getElementById('contentToggle-' + id);
    if (!shortEl || !fullEl || !toggleBtn) return;

    const isShortVisible = shortEl.style.display !== 'none';
    if (isShortVisible) {
        shortEl.style.display = 'none';
        fullEl.style.display = 'block';
        toggleBtn.textContent = '簡要顯示';
    } else {
        shortEl.style.display = 'block';
        fullEl.style.display = 'none';
        toggleBtn.textContent = '完整顯示';
    }
}

function filterNotes() {
    const select = document.getElementById('categoryFilter');
    if (!select) return;
    const value = select.value;
    const cards = document.querySelectorAll('.card-grid .card');
    cards.forEach(card => {
        const category = card.getAttribute('data-category') || '';
        if (value === '__all') {
            card.style.display = '';
        } else if (value === '__uncategorized') {
            card.style.display = category === '__uncategorized' ? '' : 'none';
        } else {
            card.style.display = category === value ? '' : 'none';
        }
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

function setCategory(value) {
    const input = document.getElementById('category');
    if (!input) return;
    input.value = value;
}

function openModal() {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = '新增筆記';
    document.getElementById('itemForm').reset();
    document.getElementById('itemId').value = '';
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

document.getElementById('itemForm').addEventListener('submit', function(e) {
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
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) location.reload();
        else alert('儲存失敗: ' + (res.error || ''));
    });
});

function uploadFile(num) {
    const input = document.getElementById('fileInput' + num);
    if (!input.files || !input.files[0]) return;

    uploadFileWithProgress(input.files[0],
        function(res) {
            document.getElementById('file' + num).value = res.file;
            document.getElementById('file' + num + 'name').value = res.filename;
            document.getElementById('file' + num + 'type').value = res.filetype;
            updateFilePreview(num);
        },
        function(error) {
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

        previewHtml += `<a href="${file}" target="_blank">${filename}</a> <span class="remove-file" onclick="removeFile(${num})">✕ 移除</span>`;
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
