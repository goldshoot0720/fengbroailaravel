<!-- 批量刪除 -->
<style>
    .batch-delete-bar {
        display: none;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: #fff;
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 15px;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .batch-delete-bar.show {
        display: flex;
    }

    .batch-delete-bar .count {
        font-weight: 600;
    }

    .batch-delete-bar .btn {
        background: #fff;
        color: #e74c3c;
        border: none;
    }

    .batch-delete-bar .btn:hover {
        background: #f8f8f8;
    }

    .select-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #e74c3c;
        display: none;
    }

    .select-mode .select-checkbox {
        display: inline-block;
    }

    .btn-select-mode {
        background: linear-gradient(135deg, #9b59b6, #8e44ad);
        color: #fff;
        border: none;
        margin-left: 8px;
    }

    .btn-select-mode:hover {
        background: linear-gradient(135deg, #8e44ad, #7d3c98);
    }

    .btn-select-mode.active {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
    }
</style>

<script>
    let batchDeleteTable = null;
    let batchDeleteIds = new Set();
    let isSelectMode = false;

    function initBatchDelete(tableName) {
        batchDeleteTable = tableName || null;
        batchDeleteIds = new Set();
        isSelectMode = false;
        updateBatchDeleteBar();
    }

    function toggleSelectMode() {
        isSelectMode = !isSelectMode;
        const body = document.body;
        const btn = document.getElementById('selectModeBtn');
        const selectAllWrap = document.getElementById('batchSelectAllWrap');

        if (!btn) return;

        if (isSelectMode) {
            body.classList.add('select-mode');
            btn.classList.add('active');
            btn.innerHTML = '<i class="fas fa-times"></i> 取消選取';
            if (selectAllWrap) selectAllWrap.style.display = 'inline-flex';
        } else {
            body.classList.remove('select-mode');
            btn.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-check-square"></i> 全選模式';
            if (selectAllWrap) selectAllWrap.style.display = 'none';
            cancelBatchSelect();
        }
    }

    function syncSelectAllCheckboxes(allChecked, hasSelection) {
        document.querySelectorAll('#selectAllCheckbox, #batchSelectAllCb').forEach(cb => {
            if (!cb) return;
            cb.checked = allChecked;
            cb.indeterminate = hasSelection && !allChecked;
        });
    }

    function getSelectableItemCheckboxes() {
        return Array.from(document.querySelectorAll('.item-checkbox')).filter(cb => {
            if (!cb) return false;
            const row = cb.closest('tr, .card, .sub-card, [data-id]');
            if (!row) return true;
            return row.offsetParent !== null;
        });
    }

    function reconcileSelectionWithVisibleItems() {
        const visibleIds = new Set(getSelectableItemCheckboxes().map(cb => cb.dataset.id));
        document.querySelectorAll('.item-checkbox').forEach(cb => {
            if (!visibleIds.has(cb.dataset.id)) {
                cb.checked = false;
                batchDeleteIds.delete(cb.dataset.id);
            }
        });

        const visibleCheckboxes = getSelectableItemCheckboxes();
        const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
        syncSelectAllCheckboxes(allChecked, batchDeleteIds.size > 0);
        updateBatchDeleteBar();
    }

    function toggleSelectAll(checkbox) {
        const checkboxes = getSelectableItemCheckboxes();
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
            const id = cb.dataset.id;
            if (checkbox.checked) {
                batchDeleteIds.add(id);
            } else {
                batchDeleteIds.delete(id);
            }
        });
        syncSelectAllCheckboxes(checkbox.checked, checkbox.checked);
        updateBatchDeleteBar();
    }

    function toggleSelectItem(checkbox) {
        const id = checkbox.dataset.id;
        if (checkbox.checked) {
            batchDeleteIds.add(id);
        } else {
            batchDeleteIds.delete(id);
        }

        const allCheckboxes = getSelectableItemCheckboxes();
        const allChecked = allCheckboxes.length > 0 && Array.from(allCheckboxes).every(cb => cb.checked);
        syncSelectAllCheckboxes(allChecked, batchDeleteIds.size > 0);
        updateBatchDeleteBar();
    }

    function updateBatchDeleteBar() {
        const bar = document.getElementById('batchDeleteBar');
        const countSpan = document.getElementById('batchSelectedCount');

        if (!bar || !countSpan) return;

        if (batchDeleteIds.size > 0) {
            bar.classList.add('show');
            countSpan.textContent = batchDeleteIds.size;
        } else {
            bar.classList.remove('show');
        }
    }

    function cancelBatchSelect() {
        batchDeleteIds.clear();
        document.querySelectorAll('.item-checkbox, #selectAllCheckbox, #batchSelectAllCb').forEach(cb => {
            cb.checked = false;
            cb.indeterminate = false;
        });
        updateBatchDeleteBar();
    }

    function getBatchDeleteKeyword() {
        return batchDeleteTable ? `DELETE ${batchDeleteTable}` : 'DELETE';
    }

    function confirmBatchDelete() {
        if (batchDeleteIds.size === 0) return;

        if (!batchDeleteTable) {
            alert('批量刪除尚未初始化，請重新整理頁面後再試。');
            return;
        }

        const confirmText = getBatchDeleteKeyword();
        const userInput = prompt(
            `警告：此操作無法復原！\n\n` +
            `您即將刪除 ${batchDeleteIds.size} 筆資料。\n\n` +
            `請輸入以下文字確認刪除：\n${confirmText}`
        );

        if (userInput !== confirmText) {
            if (userInput !== null) {
                alert('輸入內容不符，已取消批量刪除。');
            }
            return;
        }

        const ids = Array.from(batchDeleteIds);
        const bar = document.getElementById('batchDeleteBar');
        if (bar) {
            bar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 正在刪除...';
        }

        let completed = 0;
        let errors = 0;

        ids.forEach(id => {
            fetch(`api.php?action=delete&table=${batchDeleteTable}&id=${id}`)
                .then(r => r.json())
                .then(res => {
                    completed++;
                    if (!res.success) errors++;

                    if (completed === ids.length) {
                        if (errors > 0) {
                            alert(`批量刪除完成，但有 ${errors} 筆失敗。`);
                        }
                        location.reload();
                    }
                })
                .catch(() => {
                    completed++;
                    errors++;
                    if (completed === ids.length) {
                        alert(`批量刪除完成，但有 ${errors} 筆失敗。`);
                        location.reload();
                    }
                });
        });
    }
</script>

<button id="selectModeBtn" class="btn btn-select-mode" onclick="toggleSelectMode()">
    <i class="fas fa-check-square"></i> 全選模式
</button>
<label id="batchSelectAllWrap"
    style="display: none; align-items: center; gap: 6px; cursor: pointer; color: #666; font-weight: 500; font-size: 0.9rem; margin-left: 4px;">
    <input type="checkbox" id="batchSelectAllCb" onchange="toggleSelectAll(this)"
        style="width: 16px; height: 16px; accent-color: #e74c3c;">
    全選
</label>

<div id="batchDeleteBar" class="batch-delete-bar">
    <div>
        <i class="fas fa-check-square"></i>
        已選擇 <span id="batchSelectedCount" class="count">0</span> 個項目
    </div>
    <div>
        <button class="btn btn-sm" onclick="cancelBatchSelect()">
            <i class="fas fa-times"></i> 取消選擇
        </button>
        <button class="btn btn-sm" onclick="confirmBatchDelete()"
            style="margin-left: 8px; background: #fff; color: #c0392b;">
            <i class="fas fa-trash"></i> 批量刪除
        </button>
    </div>
</div>
