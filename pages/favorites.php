<?php
$pageTitle = '常用項目';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM commonaccount ORDER BY created_at DESC")->fetchAll();

// 收集所有已存在的網站名稱（去重）
$existingSites = [];
foreach ($items as $item) {
    for ($i = 1; $i <= 37; $i++) {
        $siteKey = 'site' . str_pad($i, 2, '0', STR_PAD_LEFT);
        if (!empty($item[$siteKey])) {
            $siteName = trim($item[$siteKey]);
            if (!in_array($siteName, $existingSites)) {
                $existingSites[] = $siteName;
            }
        }
    }
}
sort($existingSites);
?>

<?php
// 收集所有網站名稱及其出現的帳號
$siteAccounts = [];
foreach ($items as $item) {
    for ($i = 1; $i <= 37; $i++) {
        $siteKey = 'site' . str_pad($i, 2, '0', STR_PAD_LEFT);
        if (!empty($item[$siteKey])) {
            $siteName = trim($item[$siteKey]);
            if (!isset($siteAccounts[$siteName])) {
                $siteAccounts[$siteName] = [];
            }
            if (!in_array($item['id'], $siteAccounts[$siteName])) {
                $siteAccounts[$siteName][] = $item['id'];
            }
        }
    }
}
// 只保留出現在多個帳號中的網站
$commonSites = array_filter($siteAccounts, function ($accounts) {
    return count($accounts) >= 2;
});
// 按出現次數排序
uasort($commonSites, function ($a, $b) {
    return count($b) - count($a);
});
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
    <h1>鋒兄常用</h1>
    <?php if (!empty($commonSites)): ?>
    <div class="common-site-filters" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
        <span style="color: #666; font-size: 0.9rem;"><i class="fas fa-filter"></i> 篩選:</span>
        <button class="btn btn-sm filter-btn active" onclick="filterBySite('')" data-site="">全部</button>
        <?php foreach ($commonSites as $siteName => $accountIds): ?>
            <button class="btn btn-sm filter-btn" onclick="filterBySite('<?php echo htmlspecialchars($siteName, ENT_QUOTES); ?>')" data-site="<?php echo htmlspecialchars($siteName, ENT_QUOTES); ?>">
                <?php echo htmlspecialchars($siteName); ?> (<?php echo count($accountIds); ?>)
            </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="content-body">
    <button class="btn btn-primary" onclick="openModal()">新增常用網站與備註</button>
    <?php $csvTable = 'commonaccount';
    include 'includes/csv_buttons.php'; ?>

    <div class="card-grid" style="margin-top: 20px;">
        <?php if (empty($items)): ?>
            <div class="card">
                <p style="text-align: center; color: #999;">暫無常用網站與備註</p>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item):
                // 收集此帳號的所有網站
                $itemSites = [];
                for ($i = 1; $i <= 37; $i++) {
                    $sk = 'site' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    if (!empty($item[$sk])) {
                        $itemSites[] = trim($item[$sk]);
                    }
                }
            ?>
                <div class="card" data-sites="<?php echo htmlspecialchars(implode('|', $itemSites), ENT_QUOTES); ?>">
                    <h3 class="card-title" style="word-break: break-all;"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <?php for ($i = 1; $i <= 37; $i++): ?>
                        <?php $siteKey = 'site' . str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                        <?php $noteKey = 'note' . str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                        <?php if (!empty($item[$siteKey]) || !empty($item[$noteKey])): ?>
                            <div style="margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
                                <?php if (!empty($item[$siteKey])): ?>
                                    <div style="font-weight: 600; color: #2c3e50; margin-bottom: 4px;">
                                        <?php echo htmlspecialchars($item[$siteKey]); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($item[$noteKey])): ?>
                                    <div style="font-size: 0.85rem; color: #666; word-break: break-word;">
                                        <?php echo htmlspecialchars($item[$noteKey]); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
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
    <div class="modal-content" style="max-width: 600px; max-height: 80vh;">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">新增常用網站與備註</h2>
        <p style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">最多 37 個欄位</p>
        <form id="itemForm">
            <input type="hidden" id="itemId" name="id">
            <div class="form-group">
                <label>帳號名稱 *</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div id="fieldsContainer"></div>
            <button type="button" class="btn" onclick="addField()" style="margin-bottom: 15px;">+ 新增欄位</button>
            <br>
            <button type="submit" class="btn btn-primary">儲存</button>
        </form>
    </div>
</div>

<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="modal-close" onclick="closeViewModal()">&times;</span>
        <h2 id="viewTitle">查看詳情</h2>
        <div id="viewContent"></div>
    </div>
</div>

<div id="commonSitesModal" class="modal">
    <div class="modal-content" style="max-width: 700px; max-height: 80vh;">
        <span class="modal-close" onclick="closeCommonSitesModal()">&times;</span>
        <h2><i class="fas fa-link"></i> 共同網站分析</h2>
        <p style="color: #666; margin-bottom: 15px;">以下網站出現在多個帳號中：</p>
        <div id="commonSitesContent">
            <?php if (empty($commonSites)): ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <i class="fas fa-search" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <p>沒有找到共同的網站</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">網站名稱</th>
                            <th style="width: 15%;">出現次數</th>
                            <th style="width: 55%;">相關帳號</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commonSites as $siteName => $accounts): ?>
                            <tr>
                                <td style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($siteName); ?></td>
                                <td>
                                    <span
                                        style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2px 10px; border-radius: 12px; font-size: 0.85rem;">
                                        <?php echo count($accounts); ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.9rem; color: #555;">
                                    <?php echo htmlspecialchars(implode('、', $accounts)); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.filter-btn {
    background: #f0f0f0;
    border: 1px solid #ddd;
    color: #555;
    transition: all 0.2s;
}
.filter-btn:hover {
    background: #e0e0e0;
}
.filter-btn.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-color: transparent;
}
</style>

<script>
    const TABLE = 'commonaccount';
    let fieldCount = 0;
    const existingSites = <?php echo json_encode($existingSites, JSON_UNESCAPED_UNICODE); ?>;

    function showCommonSites() {
        document.getElementById('commonSitesModal').style.display = 'flex';
    }

    function closeCommonSitesModal() {
        document.getElementById('commonSitesModal').style.display = 'none';
    }

    function filterBySite(siteName) {
        // 更新按鈕狀態
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.site === siteName) {
                btn.classList.add('active');
            }
        });

        // 篩選卡片
        document.querySelectorAll('.card-grid .card').forEach(card => {
            const sites = card.dataset.sites || '';
            if (!siteName || sites.split('|').includes(siteName)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function toggleSiteInput(idx) {
        const select = document.getElementById(`siteSelect${idx}`);
        const input = document.getElementById(`siteInput${idx}`);
        const hidden = document.getElementById(`site${idx}`);

        if (select.value === '__custom__') {
            input.style.display = 'block';
            input.focus();
            hidden.value = input.value;
        } else {
            input.style.display = 'none';
            hidden.value = select.value;
        }
    }

    function updateSiteValue(idx) {
        const input = document.getElementById(`siteInput${idx}`);
        const hidden = document.getElementById(`site${idx}`);
        hidden.value = input.value;
    }

    function addField(note = '', site = '') {
        fieldCount++;
        if (fieldCount > 37) return;
        const idx = String(fieldCount).padStart(2, '0');
        const container = document.getElementById('fieldsContainer');
        const div = document.createElement('div');
        div.className = 'form-row';
        div.id = `field${idx}`;

        // 判斷是否為已存在的網站
        const isExisting = existingSites.includes(site);
        const selectValue = site && isExisting ? site : (site ? '__custom__' : '');
        const showInput = site && !isExisting;

        let optionsHtml = '<option value="">-- 選擇網站 --</option>';
        existingSites.forEach(s => {
            const selected = (s === site) ? 'selected' : '';
            optionsHtml += `<option value="${s}" ${selected}>${s}</option>`;
        });
        optionsHtml += `<option value="__custom__" ${showInput ? 'selected' : ''}>自行輸入...</option>`;

        div.innerHTML = `
        <div class="form-group" style="flex:1">
            <label>網站名稱 ${fieldCount}</label>
            <select id="siteSelect${idx}" class="form-control" onchange="toggleSiteInput('${idx}')" style="margin-bottom: 5px;">
                ${optionsHtml}
            </select>
            <input type="text" class="form-control" id="siteInput${idx}" placeholder="輸入網站名稱"
                   value="${showInput ? site : ''}"
                   style="display: ${showInput ? 'block' : 'none'};"
                   oninput="updateSiteValue('${idx}')">
            <input type="hidden" id="site${idx}" name="site${idx}" value="${site}">
        </div>
        <div class="form-group" style="flex:2">
            <label>備註 ${fieldCount}</label>
            <textarea class="form-control" name="note${idx}" rows="2">${note}</textarea>
        </div>
    `;
        container.appendChild(div);
    }

    function openModal() {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modalTitle').textContent = '新增常用網站與備註';
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '';
        document.getElementById('fieldsContainer').innerHTML = '';
        fieldCount = 0;
        addField();
        addField();
        addField();
    }

    function closeModal() {
        document.getElementById('modal').style.display = 'none';
    }

    function closeViewModal() {
        document.getElementById('viewModal').style.display = 'none';
    }

    function viewItem(id) {
        fetch(`api.php?action=get&table=${TABLE}&id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    const d = res.data;
                    document.getElementById('viewTitle').textContent = d.name;
                    let html = '<table class="table"><thead><tr><th>網站名稱</th><th>備註</th></tr></thead><tbody>';
                    for (let i = 1; i <= 37; i++) {
                        const idx = String(i).padStart(2, '0');
                        if (d['site' + idx] || d['note' + idx]) {
                            const noteHtml = (d['note' + idx] || '').replace(/\n/g, '<br>');
                            html += `<tr><td>${d['site' + idx] || '-'}</td><td>${noteHtml || '-'}</td></tr>`;
                        }
                    }
                    html += '</tbody></table>';
                    document.getElementById('viewContent').innerHTML = html;
                    document.getElementById('viewModal').style.display = 'flex';
                }
            });
    }

    function editItem(id) {
        fetch(`api.php?action=get&table=${TABLE}&id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    const d = res.data;
                    document.getElementById('itemId').value = d.id;
                    document.getElementById('name').value = d.name || '';
                    document.getElementById('fieldsContainer').innerHTML = '';
                    fieldCount = 0;
                    for (let i = 1; i <= 37; i++) {
                        const idx = String(i).padStart(2, '0');
                        if (d['site' + idx] || d['note' + idx]) {
                            addField(d['note' + idx] || '', d['site' + idx] || '');
                        }
                    }
                    if (fieldCount === 0) { addField(); addField(); addField(); }
                    document.getElementById('modalTitle').textContent = '編輯常用網站與備註';
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

        const formData = new FormData(this);
        const data = { name: formData.get('name') };
        for (let i = 1; i <= 37; i++) {
            const idx = String(i).padStart(2, '0');
            data['site' + idx] = formData.get('site' + idx) || '';
            data['note' + idx] = formData.get('note' + idx) || '';
        }

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
</script>