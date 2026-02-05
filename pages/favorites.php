<?php
$pageTitle = '常用項目';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM commonaccount ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄常用</h1>
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
            <?php foreach ($items as $item): ?>
                <div class="card">
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
                        <button class="btn btn-sm" onclick="viewItem('<?php echo $item['id']; ?>')">查看</button>
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

<script>
    const TABLE = 'commonaccount';
    let fieldCount = 0;

    function addField(note = '', site = '') {
        fieldCount++;
        if (fieldCount > 37) return;
        const idx = String(fieldCount).padStart(2, '0');
        const container = document.getElementById('fieldsContainer');
        const div = document.createElement('div');
        div.className = 'form-row';
        div.id = `field${idx}`;
        div.innerHTML = `
        <div class="form-group" style="flex:1">
            <label>網站名稱 ${fieldCount}</label>
            <input type="text" class="form-control" name="site${idx}" value="${site}">
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