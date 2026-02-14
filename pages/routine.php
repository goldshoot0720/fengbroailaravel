<?php
$pageTitle = '例行事項';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM routine ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄例行</h1>
</div>

<div class="content-body">
    <?php include 'includes/inline-edit-hint.php'; ?>
    <div class="action-buttons-bar">
        <button class="btn btn-primary" onclick="handleAdd()" title="新增例行事項"><i class="fas fa-plus"></i></button>
        <?php $csvTable = 'routine';
        include 'includes/csv_buttons.php'; ?>
        <?php include 'includes/batch-delete.php'; ?>
    </div>

    <!-- 桌面版表格 -->
    <table class="table desktop-only" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAllCheckbox" class="select-checkbox"
                        onchange="toggleSelectAll(this)"></th>
                <th>名稱</th>
                <th>備註</th>
                <th>圖片</th>
                <th>最近例行之一</th>
                <th>最近例行之二</th>
                <th>相距天數</th>
                <th>最近例行之三</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <tr id="inlineAddRow" class="inline-add-row">
                <td></td>
                <td>
                    <div class="inline-edit inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="name" placeholder="名稱">
                        <input type="url" class="form-control inline-input" data-field="link" placeholder="連結">
                        <div class="inline-actions">
                            <button type="button" class="btn btn-primary" onclick="saveInlineAdd()">儲存</button>
                            <button type="button" class="btn" onclick="cancelInlineAdd()">取消</button>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="text" class="form-control inline-input" data-field="note" placeholder="備註">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="url" class="form-control inline-input" data-field="photo" placeholder="圖片網址">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="date" class="form-control inline-input" data-field="lastdate1">
                    </div>
                </td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="date" class="form-control inline-input" data-field="lastdate2">
                    </div>
                </td>
                <td>-</td>
                <td>
                    <div class="inline-edit inline-edit-row inline-edit-always">
                        <input type="date" class="form-control inline-input" data-field="lastdate3">
                    </div>
                </td>
                <td></td>
            </tr>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: #999;">暫無例行事項</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $daysDiff = '-';
                    if (!empty($item['lastdate1']) && !empty($item['lastdate2'])) {
                        $date1 = new DateTime($item['lastdate1']);
                        $date2 = new DateTime($item['lastdate2']);
                        $diff = $date1->diff($date2);
                        $daysDiff = $diff->days . ' 天';
                    }
                    ?>
                    <tr data-id="<?php echo $item['id']; ?>"
                        data-name="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?>"
                        data-note="<?php echo htmlspecialchars($item['note'] ?? '', ENT_QUOTES); ?>"
                        data-link="<?php echo htmlspecialchars($item['link'] ?? '', ENT_QUOTES); ?>"
                        data-photo="<?php echo htmlspecialchars($item['photo'] ?? '', ENT_QUOTES); ?>"
                        data-lastdate1="<?php echo htmlspecialchars($item['lastdate1'] ?? '', ENT_QUOTES); ?>"
                        data-lastdate2="<?php echo htmlspecialchars($item['lastdate2'] ?? '', ENT_QUOTES); ?>"
                        data-lastdate3="<?php echo htmlspecialchars($item['lastdate3'] ?? '', ENT_QUOTES); ?>">
                        <td><input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo $item['id']; ?>"
                                onchange="toggleSelectItem(this)"></td>
                        <td>
                            <div class="inline-view">
                                <?php echo htmlspecialchars($item['name']); ?>
                                <span class="card-edit-btn" onclick="startInlineEdit('<?php echo $item['id']; ?>')"
                                    style="cursor: pointer; margin-left: 8px;"><i class="fas fa-pen"></i></span>
                                <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')"
                                    style="margin-left: 6px; cursor: pointer;">&times;</span>
                            </div>
                            <div class="inline-edit">
                                <input type="text" class="form-control inline-input" data-field="name" placeholder="名稱">
                                <input type="url" class="form-control inline-input" data-field="link" placeholder="連結">
                                <div class="inline-actions">
                                    <button type="button" class="btn btn-primary" onclick="saveInlineEdit('<?php echo $item['id']; ?>')">儲存</button>
                                    <button type="button" class="btn" onclick="cancelInlineEdit('<?php echo $item['id']; ?>')">取消</button>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo htmlspecialchars($item['note'] ?? '-'); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="text" class="form-control inline-input" data-field="note" placeholder="備註">
                            </div>
                        </td>
                        <td>
                            <div class="inline-view">
                                <?php if (!empty($item['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['photo']); ?>"
                                        style="max-width:60px;max-height:40px;border-radius:4px;">
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                            <div class="inline-edit inline-edit-row">
                                <input type="url" class="form-control inline-input" data-field="photo" placeholder="圖片網址">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo formatDate($item['lastdate1']); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="date" class="form-control inline-input" data-field="lastdate1">
                            </div>
                        </td>
                        <td>
                            <span class="inline-view"><?php echo formatDate($item['lastdate2']); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="date" class="form-control inline-input" data-field="lastdate2">
                            </div>
                        </td>
                        <td style="font-weight:600;color:#3498db;"><?php echo $daysDiff; ?></td>
                        <td>
                            <span class="inline-view"><?php echo formatDate($item['lastdate3']); ?></span>
                            <div class="inline-edit inline-edit-row">
                                <input type="date" class="form-control inline-input" data-field="lastdate3">
                            </div>
                        </td>
                        <td>
                            <div class="inline-view">
                                <button class="btn btn-sm btn-primary" onclick="shiftDates('<?php echo $item['id']; ?>')"
                                    title="日期遞移">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- 手機版卡片 -->
    <div class="mobile-only" style="margin-top: 20px;">
        <?php if (empty($items)): ?>
            <div class="mobile-card" style="text-align: center; color: #999; padding: 40px;">暫無例行事項</div>
        <?php else: ?>
            <?php foreach ($items as $item):
                $daysDiff = '-';
                $daysDiffNum = 0;
                if (!empty($item['lastdate1']) && !empty($item['lastdate2'])) {
                    $date1 = new DateTime($item['lastdate1']);
                    $date2 = new DateTime($item['lastdate2']);
                    $diff = $date1->diff($date2);
                    $daysDiffNum = $diff->days;
                    $daysDiff = $daysDiffNum . ' 天';
                }
                ?>
                <div class="mobile-card" style="border-left: 4px solid #9b59b6;">
                    <div class="mobile-card-actions">
                        <button class="btn btn-sm btn-primary" onclick="shiftDates('<?php echo $item['id']; ?>')" title="日期遞移"
                            style="padding: 5px 10px;">
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                        <span class="card-edit-btn" onclick="editItem('<?php echo $item['id']; ?>')"><i
                                class="fas fa-pen"></i></span>
                        <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')">&times;</span>
                    </div>
                    <div class="mobile-card-header">
                        <?php if (!empty($item['photo'])): ?>
                            <img src="<?php echo htmlspecialchars($item['photo']); ?>"
                                style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                        <?php else: ?>
                            <div
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #9b59b6, #8e44ad); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-redo" style="color: #fff; font-size: 1.2rem;"></i>
                            </div>
                        <?php endif; ?>
                        <div style="flex: 1;">
                            <div class="mobile-card-title"><?php echo htmlspecialchars($item['name']); ?></div>
                            <?php if (!empty($item['note'])): ?>
                                <div style="font-size: 0.8rem; color: #888;"><?php echo htmlspecialchars($item['note']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div
                            style="text-align: center; background: linear-gradient(135deg, #3498db, #2980b9); color: #fff; padding: 8px 12px; border-radius: 8px; min-width: 60px;">
                            <div style="font-size: 1.2rem; font-weight: 700;"><?php echo $daysDiffNum ?: '-'; ?></div>
                            <div style="font-size: 0.7rem;">天</div>
                        </div>
                    </div>
                    <div class="mobile-card-info" style="margin-top: 12px;">
                        <div class="mobile-card-item">
                            <span class="mobile-card-label">例行之一</span>
                            <span class="mobile-card-value"><?php echo formatDate($item['lastdate1']) ?: '-'; ?></span>
                        </div>
                        <div class="mobile-card-item">
                            <span class="mobile-card-label">例行之二</span>
                            <span class="mobile-card-value"><?php echo formatDate($item['lastdate2']) ?: '-'; ?></span>
                        </div>
                        <div class="mobile-card-item">
                            <span class="mobile-card-label">例行之三</span>
                            <span class="mobile-card-value"><?php echo formatDate($item['lastdate3']) ?: '-'; ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


<script>
    const TABLE = 'routine';
    initBatchDelete(TABLE);

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
            note: row.querySelector('[data-field="note"]').value.trim(),
            link: row.querySelector('[data-field="link"]').value.trim(),
            photo: row.querySelector('[data-field="photo"]').value.trim(),
            lastdate1: row.querySelector('[data-field="lastdate1"]').value || null,
            lastdate2: row.querySelector('[data-field="lastdate2"]').value || null,
            lastdate3: row.querySelector('[data-field="lastdate3"]').value || null
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
        // Use inline editing for all screen sizes
        const row = getRowById(id);
        if (!row) return;
        row.querySelectorAll('.inline-view').forEach(el => el.style.display = 'none');
        row.querySelectorAll('.inline-edit').forEach(el => el.style.display = 'block');
        fillInlineInputs(row);
    }

    function cancelInlineEdit(id) {
        const row = getRowById(id);
        if (!row) return;
        row.querySelectorAll('.inline-view').forEach(el => el.style.display = '');
        row.querySelectorAll('.inline-edit').forEach(el => el.style.display = 'none');
    }

    function fillInlineInputs(row) {
        const data = row.dataset;
        const nameInput = row.querySelector('[data-field="name"]');
        if (nameInput) nameInput.value = data.name || '';
        const noteInput = row.querySelector('[data-field="note"]');
        if (noteInput) noteInput.value = data.note || '';
        const linkInput = row.querySelector('[data-field="link"]');
        if (linkInput) linkInput.value = data.link || '';
        const photoInput = row.querySelector('[data-field="photo"]');
        if (photoInput) photoInput.value = data.photo || '';
        const lastdate1Input = row.querySelector('[data-field="lastdate1"]');
        if (lastdate1Input) lastdate1Input.value = data.lastdate1 ? data.lastdate1.split(' ')[0] : '';
        const lastdate2Input = row.querySelector('[data-field="lastdate2"]');
        if (lastdate2Input) lastdate2Input.value = data.lastdate2 ? data.lastdate2.split(' ')[0] : '';
        const lastdate3Input = row.querySelector('[data-field="lastdate3"]');
        if (lastdate3Input) lastdate3Input.value = data.lastdate3 ? data.lastdate3.split(' ')[0] : '';
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
            note: row.querySelector('[data-field="note"]').value.trim(),
            link: row.querySelector('[data-field="link"]').value.trim(),
            photo: row.querySelector('[data-field="photo"]').value.trim(),
            lastdate1: row.querySelector('[data-field="lastdate1"]').value || null,
            lastdate2: row.querySelector('[data-field="lastdate2"]').value || null,
            lastdate3: row.querySelector('[data-field="lastdate3"]').value || null
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
        if (confirm('確定要刪除嗎？')) {
            fetch(`api.php?action=delete&table=${TABLE}&id=${id}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success) location.reload();
                    else alert('刪除失敗');
                });
        }
    }

    </script>
