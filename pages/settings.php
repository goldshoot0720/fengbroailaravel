<?php $pageTitle = '系統設定'; ?>

<div class="content-header">
    <h1>鋒兄設定</h1>
</div>

<div class="content-body">
    <div class="card">
        <h3 class="card-title">資料庫設定</h3>
        <table class="table">
            <tr>
                <th style="width: 200px;">目前環境</th>
                <td><span
                        class="badge <?php echo $GLOBALS['ENV'] === 'remote' ? 'badge-danger' : 'badge-success'; ?>"><?php echo strtoupper($GLOBALS['ENV']); ?></span>
                </td>
            </tr>
            <tr>
                <th>資料庫主機</th>
                <td><?php echo DB_HOST; ?></td>
            </tr>
            <tr>
                <th>資料庫名稱</th>
                <td><?php echo DB_NAME; ?></td>
            </tr>
            <tr>
                <th>資料庫使用者</th>
                <td><?php echo DB_USER; ?></td>
            </tr>
        </table>
        <div style="margin-top: 12px;">
            <a href="install.php" target="_blank" class="btn btn-warning">
                <i class="fa-solid fa-database"></i> 執行資料庫安裝／升級
            </a>
            <span style="font-size: 0.82em; color: #888; margin-left: 10px;">建立資料表 或 補齊新欄位</span>
        </div>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">系統資訊</h3>
        <table class="table">
            <tr>
                <th style="width: 200px;">PHP 版本</th>
                <td><?php echo phpversion(); ?></td>
            </tr>
            <tr>
                <th>伺服器軟體</th>
                <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
            </tr>
            <tr>
                <th>伺服器時間</th>
                <td><?php echo date('Y-m-d H:i:s'); ?></td>
            </tr>
        </table>
    </div>

    <?php
    // ── 推播通知管理卡片資料 ──────────────────────────────────────────────────
    require_once __DIR__ . '/../push/WebPushHelper.php';
    $vapidPublicKeySet = WebPushHelper::getVapidPublicKey() !== '';
    try {
        $pushDeviceCount = $pdo->query("SELECT COUNT(*) FROM push_subscriptions")->fetchColumn();
    } catch (Exception $e) {
        $pushDeviceCount = 0;
    }
    $scriptPath = str_replace('\\', '/', __DIR__ . '/../push_send.php');
    ?>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">推播通知管理（Web Push）</h3>
        <table class="table">
            <tr>
                <th style="width: 200px;">VAPID 金鑰</th>
                <td>
                    <?php if ($vapidPublicKeySet): ?>
                        <span class="badge badge-success">已設定 ✓</span>
                    <?php else: ?>
                        <span class="badge badge-danger">未設定 ✗</span>
                        <button onclick="initVapid()" class="btn btn-sm btn-primary"
                            style="margin-left:12px;">初始化金鑰</button>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>已訂閱裝置數</th>
                <td><strong id="pushDeviceCount"><?php echo (int) $pushDeviceCount; ?></strong> 台</td>
            </tr>
            <tr>
                <th>立即發送</th>
                <td>
                    <button onclick="sendPush()" class="btn btn-sm btn-warning" <?php echo !$vapidPublicKeySet ? 'disabled' : ''; ?>>
                        立即發送到期提醒
                    </button>
                    <span id="pushSendResult" style="margin-left:12px; font-size:0.9em;"></span>
                </td>
            </tr>
            <tr>
                <th>Cron 排程</th>
                <td>
                    <code
                        style="background:#f4f4f4; padding:6px 10px; border-radius:4px; display:inline-block; font-size:0.85em;">
                        CRON_TZ=Asia/Taipei<br>
                        0 9 * * * php <?php echo htmlspecialchars($scriptPath); ?> &gt;&gt; /var/log/push_send.log 2&gt;&amp;1
                    </code>
                    <div style="font-size:0.8em; color:#888; margin-top:4px;">每天台灣時間上午 09:00 自動發送 3 天內到期訂閱提醒</div>
                </td>
            </tr>
        </table>
    </div>

    <script>
        function initVapid() {
            if (!confirm('確定要產生 VAPID 金鑰？這將覆蓋現有金鑰（若有），已訂閱裝置需重新訂閱。')) return;
            fetch('push_send.php?action=init_vapid&force=1')
                .then(r => r.json())
                .then(d => {
                    alert(d.success ? '金鑰已產生，請重新整理頁面。' : ('錯誤：' + d.error));
                    if (d.success) location.reload();
                })
                .catch(() => alert('請求失敗'));
        }

        function sendPush() {
            var btn = event.target;
            btn.disabled = true;
            btn.textContent = '發送中…';
            document.getElementById('pushSendResult').textContent = '';

            fetch('push_send.php', { method: 'POST' })
                .then(r => r.json())
                .then(d => {
                    btn.disabled = false;
                    btn.textContent = '立即發送到期提醒';
                    if (d.success) {
                        document.getElementById('pushSendResult').innerHTML =
                            '<span style="color:green;">發送 ' + d.sent + ' 則，失敗 ' + d.failed + ' 則</span>' +
                            (d.message ? '（' + d.message + '）' : '');
                    } else {
                        document.getElementById('pushSendResult').innerHTML =
                            '<span style="color:red;">錯誤：' + (d.error || '未知') + '</span>';
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.textContent = '立即發送到期提醒';
                    document.getElementById('pushSendResult').innerHTML = '<span style="color:red;">請求失敗</span>';
                });
        }
    </script>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">資料庫統計</h3>
        <?php
        $pdo = getConnection();
        $tables = [
            'subscription' => '訂閱',
            'food' => '食品',
            'article' => '筆記/文章',
            'commonaccount' => '常用帳號',
            'image' => '圖片',
            'music' => '音樂',
            'podcast' => '播客',
            'commondocument' => '文件',
            'bank' => '銀行',
            'routine' => '例行事項'
        ];
        ?>
        <table class="table">
            <thead>
                <tr>
                    <th>資料表</th>
                    <th>名稱</th>
                    <th>筆數</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables as $table => $name): ?>
                    <?php
                    try {
                        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
                    } catch (Exception $e) {
                        $count = '表格不存在';
                    }
                    ?>
                    <tr>
                        <td><code><?php echo $table; ?></code></td>
                        <td><?php echo $name; ?></td>
                        <td><?php echo $count; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">Storage 檔案管理</h3>
        <p style="color: var(--muted-text); margin-bottom: 12px;">掃描本機 uploads 目錄，找出資料庫欄位未引用的檔案。這是 PHP/MySQL 版對應 Appwrite/Supabase Storage 清理的實作。</p>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
            <button type="button" class="btn btn-primary" onclick="scanStorageFiles()">
                <i class="fa-solid fa-magnifying-glass"></i> 掃描 uploads
            </button>
            <button type="button" class="btn btn-danger" id="deleteUnusedStorageBtn" onclick="deleteUnusedStorageFiles()" disabled>
                <i class="fa-solid fa-trash"></i> 刪除未引用檔案
            </button>
        </div>
        <div id="storageScanResult" style="color: var(--muted-text);">尚未掃描。</div>
    </div>
</div>

<script>
    let unusedStorageFiles = [];

    function formatStorageSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let value = Number(bytes || 0);
        let unit = 0;
        while (value >= 1024 && unit < units.length - 1) {
            value /= 1024;
            unit++;
        }
        return value.toFixed(unit === 0 ? 0 : 1) + ' ' + units[unit];
    }

    function scanStorageFiles() {
        const box = document.getElementById('storageScanResult');
        const deleteBtn = document.getElementById('deleteUnusedStorageBtn');
        box.innerHTML = '掃描中...';
        deleteBtn.disabled = true;
        fetch('storage_api.php?action=scan')
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || '掃描失敗');
                unusedStorageFiles = res.unusedFiles || [];
                deleteBtn.disabled = unusedStorageFiles.length === 0;
                const rows = unusedStorageFiles.slice(0, 120).map(file => `
                    <tr>
                        <td><code>${file.path}</code></td>
                        <td>${formatStorageSize(file.size)}</td>
                        <td>${file.modified || ''}</td>
                    </tr>
                `).join('');
                box.innerHTML = `
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:12px;">
                        <div class="card" style="margin:0;"><strong>${res.totalFiles}</strong><br><span>Storage 檔案</span></div>
                        <div class="card" style="margin:0;"><strong>${res.referencedCount}</strong><br><span>已引用</span></div>
                        <div class="card" style="margin:0;"><strong>${unusedStorageFiles.length}</strong><br><span>未引用</span></div>
                    </div>
                    ${unusedStorageFiles.length ? `
                        <table class="table">
                            <thead><tr><th>檔案</th><th>大小</th><th>修改時間</th></tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                        ${unusedStorageFiles.length > 120 ? '<p>僅顯示前 120 筆。</p>' : ''}
                    ` : '<p>目前沒有未引用檔案。</p>'}
                `;
            })
            .catch(err => box.innerHTML = '<span style="color:#e74c3c;">' + err.message + '</span>');
    }

    function deleteUnusedStorageFiles() {
        if (!unusedStorageFiles.length) return;
        if (!confirm('確定刪除 ' + unusedStorageFiles.length + ' 個未引用檔案？此操作不可復原。')) return;
        fetch('storage_api.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ paths: unusedStorageFiles.map(file => file.path) })
        })
            .then(r => r.json())
            .then(res => {
                alert('已刪除 ' + (res.deleted || 0) + ' 個檔案' + (res.errors && res.errors.length ? '\\n錯誤：' + res.errors.join('\\n') : ''));
                scanStorageFiles();
            })
            .catch(err => alert('刪除失敗: ' + err.message));
    }
</script>
