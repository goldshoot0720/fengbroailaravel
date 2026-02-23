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
                <td><span class="badge <?php echo $GLOBALS['ENV'] === 'remote' ? 'badge-danger' : 'badge-success'; ?>"><?php echo strtoupper($GLOBALS['ENV']); ?></span></td>
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
                        <button onclick="initVapid()" class="btn btn-sm btn-primary" style="margin-left:12px;">初始化金鑰</button>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>已訂閱裝置數</th>
                <td><strong id="pushDeviceCount"><?php echo (int)$pushDeviceCount; ?></strong> 台</td>
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
                    <code style="background:#f4f4f4; padding:6px 10px; border-radius:4px; display:inline-block; font-size:0.85em;">
                        0 9 * * * php <?php echo htmlspecialchars($scriptPath); ?> &gt;&gt; /var/log/push_send.log 2&gt;&amp;1
                    </code>
                    <div style="font-size:0.8em; color:#888; margin-top:4px;">每天 09:00 自動發送 3 天內到期訂閱提醒</div>
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
</div>
