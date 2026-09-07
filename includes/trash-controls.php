<?php
$trashMode = isset($trashMode) ? (bool) $trashMode : (($_GET['trash'] ?? '') === '1');
$trashCount = 0;
try {
    $trashCount = (int) $pdo->query("SELECT COUNT(*) FROM `{$trashTable}` WHERE deleted_at IS NOT NULL")->fetchColumn();
} catch (Throwable $e) {}
?>
<div class="trash-toolbar" data-trash-mode="<?php echo $trashMode ? '1' : '0'; ?>">
    <a class="btn btn-sm <?php echo $trashMode ? 'btn-primary' : 'btn-ghost'; ?>"
       href="index.php?page=<?php echo urlencode($trashPage); ?><?php echo $trashMode ? '' : '&trash=1'; ?>">
        <i class="fa-solid fa-trash-can-arrow-up"></i>
        <?php echo $trashMode ? '&#22238;&#21040;&#20840;&#37096;&#36039;&#26009;' : '&#22403;&#22334;&#26742;'; ?>
        <span class="trash-count"><?php echo $trashCount; ?></span>
    </a>
    <?php if ($trashMode && $trashCount > 0): ?>
        <button type="button" class="btn btn-sm btn-danger" onclick="emptyTrash()">
            <i class="fa-solid fa-trash"></i> &#28165;&#31354;&#22403;&#22334;&#26742;
        </button>
    <?php endif; ?>
</div>
<?php if ($trashMode): ?>
<div class="trash-mode-notice">
    <i class="fa-solid fa-circle-info"></i>
    &#36889;&#20123;&#36039;&#26009;&#24050;&#31227;&#33267;&#22403;&#22334;&#26742;&#65292;&#21487;&#24489;&#21407;&#25110;&#27704;&#20037;&#21034;&#38500;&#12290;
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-id]').forEach(function (row) {
        const id = row.getAttribute('data-id');
        const actions = row.querySelector('.card-actions, .inline-actions, td:last-child');
        if (!id || !actions || actions.querySelector('.trash-restore-btn')) return;
        actions.querySelectorAll('[onclick*="deleteItem"], .card-delete-btn').forEach(el => el.style.display = 'none');
        const restore = document.createElement('button');
        restore.type = 'button'; restore.className = 'btn btn-sm btn-success trash-restore-btn';
        restore.innerHTML = '<i class="fa-solid fa-rotate-left"></i> &#24489;&#21407;';
        restore.onclick = function () { trashAction('restore', id, false); };
        const permanent = document.createElement('button');
        permanent.type = 'button'; permanent.className = 'btn btn-sm btn-danger trash-restore-btn';
        permanent.innerHTML = '<i class="fa-solid fa-trash"></i> &#27704;&#20037;&#21034;&#38500;';
        permanent.onclick = function () { trashAction('delete', id, true); };
        actions.appendChild(restore); actions.appendChild(permanent);
    });
});
function trashAction(action, id, permanent) {
    if (permanent && !confirm('\u6b64\u64cd\u4f5c\u7121\u6cd5\u5fa9\u539f\uff0c\u78ba\u5b9a\u6c38\u4e45\u522a\u9664\uff1f')) return;
    const extra = permanent ? '&permanent=1' : '';
    fetch('api.php?action=' + action + '&table=<?php echo rawurlencode($trashTable); ?>&id=' + encodeURIComponent(id) + extra)
        .then(r => r.json()).then(r => { if (!r.success) throw new Error(r.error || 'Failed'); location.reload(); })
        .catch(e => alert(e.message));
}
function emptyTrash() {
    if (!confirm('\u78ba\u5b9a\u6c38\u4e45\u522a\u9664\u5783\u573e\u6876\u5167\u7684\u6240\u6709\u8cc7\u6599\uff1f')) return;
    fetch('api.php?action=empty_trash&table=<?php echo rawurlencode($trashTable); ?>')
        .then(r => r.json()).then(r => { if (!r.success) throw new Error(r.error || 'Failed'); location.reload(); })
        .catch(e => alert(e.message));
}
</script>
<?php endif; ?>
