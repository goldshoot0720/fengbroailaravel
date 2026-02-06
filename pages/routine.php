<?php
$pageTitle = '例行事項';
$pdo = getConnection();
$items = $pdo->query("SELECT * FROM routine ORDER BY created_at DESC")->fetchAll();
?>

<div class="content-header">
    <h1>鋒兄例行</h1>
</div>

<div class="content-body">
    <button class="btn btn-primary" onclick="openModal()" title="新增例行事項"><i class="fas fa-plus"></i></button>
    <?php $csvTable = 'routine';
    include 'includes/csv_buttons.php'; ?>

    <?php include 'includes/batch-delete.php'; ?>

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
                    <tr>
                        <td><input type="checkbox" class="select-checkbox item-checkbox" data-id="<?php echo $item['id']; ?>"
                                onchange="toggleSelectItem(this)"></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['note'] ?? '-'); ?></td>
                        <td>
                            <?php if (!empty($item['photo'])): ?>
                                <img src="<?php echo htmlspecialchars($item['photo']); ?>"
                                    style="max-width:60px;max-height:40px;border-radius:4px;">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatDate($item['lastdate1']); ?></td>
                        <td><?php echo formatDate($item['lastdate2']); ?></td>
                        <td style="font-weight:600;color:#3498db;"><?php echo $daysDiff; ?></td>
                        <td><?php echo formatDate($item['lastdate3']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="shiftDates('<?php echo $item['id']; ?>')"
                                title="日期遞移">
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                            <span class="card-edit-btn" onclick="editItem('<?php echo $item['id']; ?>')"
                                style="cursor: pointer;"><i class="fas fa-pen"></i></span>
                            <span class="card-delete-btn" onclick="deleteItem('<?php echo $item['id']; ?>')"
                                style="margin-left: 10px; cursor: pointer;">&times;</span>
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

<div id="modal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">新增例行事項</h2>
        <form id="itemForm">
            <input type="hidden" id="itemId" name="id">
            <div class="form-group">
                <label>名稱 *</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label>備註</label>
                <input type="text" class="form-control" id="note" name="note">
            </div>
            <div class="form-group">
                <label>連結</label>
                <input type="url" class="form-control" id="link" name="link">
            </div>
            <div class="form-group">
                <label>圖片網址</label>
                <input type="url" class="form-control" id="photo" name="photo">
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>最近例行之一</label>
                    <input type="date" class="form-control" id="lastdate1" name="lastdate1">
                </div>
                <div class="form-group" style="flex:1">
                    <label>最近例行之二</label>
                    <input type="date" class="form-control" id="lastdate2" name="lastdate2">
                </div>
                <div class="form-group" style="flex:1">
                    <label>最近例行之三</label>
                    <input type="date" class="form-control" id="lastdate3" name="lastdate3">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">儲存</button>
        </form>
    </div>
</div>

<script>
    const TABLE = 'routine';
    initBatchDelete(TABLE);

    function openModal() {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modalTitle').textContent = '新增例行事項';
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '';
    }

    function closeModal() {
        document.getElementById('modal').style.display = 'none';
    }

    function shiftDates(id) {
        const confirmMsg = `確定要執行日期遞移嗎？

最近例行之一 → 最近例行之二
最近例行之二 → 最近例行之三
最近例行之一 → 清空`;

        if (!confirm(confirmMsg)) return;

        fetch(`api.php?action=get&table=${TABLE}&id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    const d = res.data;
                    const data = {
                        lastdate3: d.lastdate2,
                        lastdate2: d.lastdate1,
                        lastdate1: null
                    };
                    fetch(`api.php?action=update&table=${TABLE}&id=${id}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) location.reload();
                            else alert('更新失敗');
                        });
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
                    document.getElementById('note').value = d.note || '';
                    document.getElementById('link').value = d.link || '';
                    document.getElementById('photo').value = d.photo || '';
                    document.getElementById('lastdate1').value = d.lastdate1 ? d.lastdate1.slice(0, 10) : '';
                    document.getElementById('lastdate2').value = d.lastdate2 ? d.lastdate2.slice(0, 10) : '';
                    document.getElementById('lastdate3').value = d.lastdate3 ? d.lastdate3.slice(0, 10) : '';
                    document.getElementById('modalTitle').textContent = '編輯例行事項';
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
            name: document.getElementById('name').value,
            note: document.getElementById('note').value,
            link: document.getElementById('link').value,
            photo: document.getElementById('photo').value,
            lastdate1: document.getElementById('lastdate1').value || null,
            lastdate2: document.getElementById('lastdate2').value || null,
            lastdate3: document.getElementById('lastdate3').value || null
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
</script>