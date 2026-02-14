<!-- 批量刪除元件 -->
<style>
    .batch-delete-bar {
        display: none;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: white;
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
        background: white;
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
        color: white;
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
        batchDeleteTable = tableName;
        batchDeleteIds = new Set();
        isSelectMode = false;
        updateBatchDeleteBar();
    }

    function toggleSelectMode() {
        isSelectMode = !isSelectMode;
        const body = document.body;
        const btn = document.getElementById('selectModeBtn');
        const selectAllWrap = document.getElementById('batchSelectAllWrap');

        if (isSelectMode) {
            body.classList.add('select-mode');
            btn.classList.add('active');
            btn.innerHTML = '<i class="fas fa-times"></i> 退出選擇';
            if (selectAllWrap) selectAllWrap.style.display = 'inline-flex';
        } else {
            body.classList.remove('select-mode');
            btn.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-check-square"></i> 全選模式';
            if (selectAllWrap) selectAllWrap.style.display = 'none';
            // 清除所有選擇
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

    function toggleSelectAll(checkbox) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
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

        const allCheckboxes = document.querySelectorAll('.item-checkbox');
        const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
        syncSelectAllCheckboxes(allChecked, batchDeleteIds.size > 0);
        updateBatchDeleteBar();
    }

    function updateBatchDeleteBar() {
        const bar = document.getElementById('batchDeleteBar');
        const countSpan = document.getElementById('batchSelectedCount');

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

    function confirmBatchDelete() {
        if (batchDeleteIds.size === 0) return;

        const confirmText = `DELETE ${batchDeleteTable}`;
        const userInput = prompt(
            `⚠️ 警告：此操作無法撤銷！\n\n` +
            `您即將刪除 ${batchDeleteIds.size} 筆資料。\n\n` +
            `請輸入以下文字確認刪除：\n${confirmText}`
        );

        if (userInput !== confirmText) {
            if (userInput !== null) {
                alert('輸入不正確，刪除已取消。');
            }
            return;
        }

        const ids = Array.from(batchDeleteIds);
        const bar = document.getElementById('batchDeleteBar');
        bar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 正在刪除...';

        // 逐一刪除
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
                            alert(`刪除完成，但有 ${errors} 個項目刪除失敗`);
                        }
                        location.reload();
                    }
                })
                .catch(() => {
                    completed++;
                    errors++;
                    if (completed === ids.length) {
                        alert(`刪除完成，但有 ${errors} 個項目刪除失敗`);
                        location.reload();
                    }
                });
        });
    }
</script>

<button id="selectModeBtn" class="btn btn-select-mode" onclick="toggleSelectMode()">
    <i class="fas fa-check-square"></i> 全選模式
</button>
<label id="batchSelectAllWrap" style="display: none; align-items: center; gap: 6px; cursor: pointer; color: #666; font-weight: 500; font-size: 0.9rem; margin-left: 4px;">
    <input type="checkbox" id="batchSelectAllCb" onchange="toggleSelectAll(this)" style="width: 16px; height: 16px; accent-color: #e74c3c;">
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