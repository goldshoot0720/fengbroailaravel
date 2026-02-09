<?php if (isset($csvTable)): ?>
<div class="csv-buttons" style="display: inline-block; margin-left: 10px;">
    <div class="btn-group" style="display: inline-block; position: relative;">
        <a href="export.php?table=<?php echo $csvTable; ?>&format=appwrite" class="btn btn-success">
            <i class="fa-solid fa-download"></i> 匯出 Appwrite
        </a>
        <a href="export.php?table=<?php echo $csvTable; ?>&format=laravel" class="btn btn-success" style="margin-left: 4px;">
            <i class="fa-solid fa-download"></i> 匯出 Laravel
        </a>
    </div>
    <button type="button" class="btn" onclick="document.getElementById('importFile_<?php echo $csvTable; ?>').click()">
        <i class="fa-solid fa-upload"></i> 匯入 CSV
    </button>
    <input type="file" id="importFile_<?php echo $csvTable; ?>" accept=".csv" style="display: none;" onchange="importCSV_<?php echo $csvTable; ?>(this)">
</div>

<script>
function importCSV_<?php echo $csvTable; ?>(input) {
    if (!input.files || !input.files[0]) return;

    if (!confirm('確定要匯入 CSV 嗎？\n支援 LaravelMySQL 和 Appwrite 雙格式。\n已存在的資料將會被更新。')) {
        input.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('table', '<?php echo $csvTable; ?>');
    formData.append('file', input.files[0]);

    fetch('import.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            let msg = '匯入完成！\n成功: ' + res.imported + ' 筆';
            if (res.skipped > 0) msg += '\n跳過: ' + res.skipped + ' 筆';
            if (res.errors && res.errors.length > 0) {
                msg += '\n\n錯誤明細:\n' + res.errors.join('\n');
            }
            alert(msg);
            location.reload();
        } else {
            alert('匯入失敗: ' + (res.error || '未知錯誤'));
        }
    })
    .catch(err => {
        alert('匯入失敗: ' + err.message);
    });

    input.value = '';
}
</script>
<?php endif; ?>
