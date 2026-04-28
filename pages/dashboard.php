<?php
$pageTitle = '儀表板';
$pdo = getConnection();

$exchangeRates = [
    'TWD' => 1,
    'USD' => 35,
    'EUR' => 40,
    'JPY' => 0.35,
    'CNY' => 4.5,
    'HKD' => 4
];

$subscriptionCount = $pdo->query("SELECT COUNT(*) FROM subscription")->fetchColumn();
$subscriptions = $pdo->query("SELECT price, currency FROM subscription WHERE `continue` = 1")->fetchAll();
$subscriptionTotal = 0;
foreach ($subscriptions as $sub) {
    $currency = strtoupper($sub['currency'] ?? 'TWD');
    $rate = $exchangeRates[$currency] ?? 1;
    $subscriptionTotal += round($sub['price'] * $rate);
}

$foodCount = $pdo->query("SELECT COUNT(*) FROM food")->fetchColumn();
$noteCount = $pdo->query("SELECT COUNT(*) FROM article")->fetchColumn();
$favoriteCount = $pdo->query("SELECT COUNT(*) FROM commonaccount")->fetchColumn();
$imageCount = $pdo->query("SELECT COUNT(*) FROM image")->fetchColumn();
$videoCount = $pdo->query("SELECT COUNT(*) FROM commondocument WHERE category = 'video'")->fetchColumn();
$musicCount = $pdo->query("SELECT COUNT(*) FROM music")->fetchColumn();
$podcastCount = $pdo->query("SELECT COUNT(*) FROM podcast")->fetchColumn();
$documentCount = $pdo->query("SELECT COUNT(*) FROM commondocument")->fetchColumn();
$bankCount = $pdo->query("SELECT COUNT(*) FROM bank")->fetchColumn();
$bankTotal = $pdo->query("SELECT COALESCE(SUM(deposit), 0) FROM bank")->fetchColumn();
$routineCount = $pdo->query("SELECT COUNT(*) FROM routine")->fetchColumn();

$subExpiring3Days = $pdo->query("SELECT * FROM subscription WHERE `continue` = 1 AND nextdate IS NOT NULL AND nextdate <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND nextdate >= CURDATE() ORDER BY nextdate ASC")->fetchAll();
$subExpiring7Days = $pdo->query("SELECT * FROM subscription WHERE `continue` = 1 AND nextdate IS NOT NULL AND nextdate > DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND nextdate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY nextdate ASC")->fetchAll();
$foodExpiring7Days = $pdo->query("SELECT * FROM food WHERE todate IS NOT NULL AND todate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND todate >= CURDATE() ORDER BY todate ASC")->fetchAll();
$foodExpiring30Days = $pdo->query("SELECT * FROM food WHERE todate IS NOT NULL AND todate > DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND todate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY todate ASC")->fetchAll();
$expiredFoods = $pdo->query("SELECT * FROM food WHERE todate IS NOT NULL AND todate < CURDATE() ORDER BY todate ASC LIMIT 5")->fetchAll();
$toolSnapshotCount = 0;
try {
    $toolSnapshotCount = $pdo->query("SELECT COUNT(*) FROM tool_price_history")->fetchColumn();
} catch (Exception $e) {
    $toolSnapshotCount = 0;
}

$recentSubscriptions = $pdo->query("SELECT * FROM subscription ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recentFood = $pdo->query("SELECT * FROM food ORDER BY created_at DESC LIMIT 5")->fetchAll();

function getFolderSize($dir)
{
    $size = 0;
    if (is_dir($dir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
    }
    return $size;
}

function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$uploadsDir = __DIR__ . '/../uploads';
$uploadsFolderSize = getFolderSize($uploadsDir);
$uploadsFolderSizeFormatted = formatBytes($uploadsFolderSize);
$uploadsFileCount = 0;
if (is_dir($uploadsDir)) {
    $uploadsFileCount = count(glob($uploadsDir . '/*'));
}
?>

<div class="content-header">
    <div class="page-intro">
        <span class="eyebrow">Overview</span>
        <h1>儀表板</h1>
        <p>集中查看訂閱與食品的節奏、成本、數量與近期變化，讓每日決策更快。</p>
    </div>
    <div class="header-pillset">
        <span class="header-pill"><i class="fa-solid fa-bolt"></i> Tech workflow ready</span>
        <span class="header-pill"><i class="fa-solid fa-shield-heart"></i> Alerts first</span>
        <button type="button" class="btn btn-sm btn-ghost" onclick="requestDashboardNotifications()">
            <i class="fa-solid fa-bell"></i> 啟用提醒
        </button>
    </div>
</div>

<div class="content-body">
    <section class="hero-panel dashboard-hero">
        <div class="hero-copy">
            <span class="eyebrow">Daily Ops Status</span>
            <h2>先看風險，再看容量與節奏。</h2>
            <p>新的 dashboard 用更清楚的視覺層級整理提醒、成本與資料規模，減少在大量舊式卡片之間掃描的負擔。</p>
        </div>
        <div class="hero-stack hero-stack-metrics">
            <article class="signal-card signal-card-primary">
                <span class="signal-label">Active subscriptions</span>
                <strong><?php echo $subscriptionCount; ?></strong>
                <p>目前估算支出 <?php echo formatMoney($subscriptionTotal); ?></p>
            </article>
            <article class="signal-card">
                <span class="signal-label">Storage footprint</span>
                <strong><?php echo $uploadsFolderSizeFormatted; ?></strong>
                <p><?php echo $uploadsFileCount; ?> files in uploads</p>
            </article>
        </div>
    </section>

    <section class="dashboard-metrics-grid">
        <a href="index.php?page=subscription" class="metric-card metric-card-featured">
            <span class="metric-icon"><i class="fa-solid fa-credit-card"></i></span>
            <span class="metric-label">Subscriptions</span>
            <strong><?php echo $subscriptionCount; ?></strong>
            <small>Estimated <?php echo formatMoney($subscriptionTotal); ?></small>
        </a>
        <a href="index.php?page=food" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-utensils"></i></span>
            <span class="metric-label">Food</span>
            <strong><?php echo $foodCount; ?></strong>
            <small>Inventory and expiry tracking</small>
        </a>
        <a href="index.php?page=notes" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-note-sticky"></i></span>
            <span class="metric-label">Notes</span>
            <strong><?php echo $noteCount; ?></strong>
            <small>Knowledge capture</small>
        </a>
        <a href="index.php?page=favorites" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-star"></i></span>
            <span class="metric-label">Favorites</span>
            <strong><?php echo $favoriteCount; ?></strong>
            <small>Quick access records</small>
        </a>
        <a href="index.php?page=images" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-image"></i></span>
            <span class="metric-label">Images</span>
            <strong><?php echo $imageCount; ?></strong>
            <small>Visual library</small>
        </a>
        <a href="index.php?page=videos" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-video"></i></span>
            <span class="metric-label">Videos</span>
            <strong><?php echo $videoCount; ?></strong>
            <small>Media archive</small>
        </a>
        <a href="index.php?page=music" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-music"></i></span>
            <span class="metric-label">Music</span>
            <strong><?php echo $musicCount; ?></strong>
            <small>Audio collection</small>
        </a>
        <a href="index.php?page=documents" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-file-lines"></i></span>
            <span class="metric-label">Documents</span>
            <strong><?php echo $documentCount; ?></strong>
            <small>Reference files</small>
        </a>
        <a href="index.php?page=podcast" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-podcast"></i></span>
            <span class="metric-label">Podcast</span>
            <strong><?php echo $podcastCount; ?></strong>
            <small>Listening queue</small>
        </a>
        <a href="index.php?page=bank" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-building-columns"></i></span>
            <span class="metric-label">Bank</span>
            <strong><?php echo $bankCount; ?></strong>
            <small>Total <?php echo formatMoney($bankTotal); ?></small>
        </a>
        <a href="index.php?page=routine" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <span class="metric-label">Routine</span>
            <strong><?php echo $routineCount; ?></strong>
            <small>Recurring patterns</small>
        </a>
        <div class="metric-card metric-card-storage">
            <span class="metric-icon"><i class="fa-solid fa-hard-drive"></i></span>
            <span class="metric-label">Storage</span>
            <strong><?php echo $uploadsFolderSizeFormatted; ?></strong>
            <small><?php echo $uploadsFileCount; ?> files available</small>
        </div>
        <a href="index.php?page=tools" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-wrench"></i></span>
            <span class="metric-label">Tools snapshots</span>
            <strong><?php echo $toolSnapshotCount; ?></strong>
            <small>BigGo and phone compare history</small>
        </a>
    </section>

    <?php if (!empty($subExpiring3Days) || !empty($subExpiring7Days) || !empty($foodExpiring7Days) || !empty($foodExpiring30Days) || !empty($expiredFoods)): ?>
        <div class="dashboard-section">
            <div class="section-heading">
                <h3><i class="fa-solid fa-bell"></i> 到期提醒</h3>
                <p>優先看到接近到期的訂閱與食品。</p>
            </div>
            <div class="alert-grid">
                <?php if (!empty($subExpiring3Days)): ?>
                    <div class="alert-card alert-card-critical">
                        <h4><i class="fa-solid fa-credit-card"></i> 訂閱即將到期（3 天內）</h4>
                        <ul class="alert-list">
                            <?php foreach ($subExpiring3Days as $sub): ?>
                                <li>
                                    <span><strong><?php echo htmlspecialchars($sub['name']); ?></strong></span>
                                    <span><?php echo date('m/d', strtotime($sub['nextdate'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($subExpiring7Days)): ?>
                    <div class="alert-card alert-card-warning">
                        <h4><i class="fa-solid fa-credit-card"></i> 訂閱即將到期（7 天內）</h4>
                        <ul class="alert-list">
                            <?php foreach ($subExpiring7Days as $sub): ?>
                                <li>
                                    <span><strong><?php echo htmlspecialchars($sub['name']); ?></strong></span>
                                    <span><?php echo date('m/d', strtotime($sub['nextdate'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($foodExpiring7Days)): ?>
                    <div class="alert-card alert-card-critical">
                        <h4><i class="fa-solid fa-utensils"></i> 食品即將到期（7 天內）</h4>
                        <ul class="alert-list">
                            <?php foreach ($foodExpiring7Days as $food): ?>
                                <li>
                                    <span><strong><?php echo htmlspecialchars($food['name']); ?></strong></span>
                                    <span><?php echo date('m/d', strtotime($food['todate'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($expiredFoods)): ?>
                    <div class="alert-card alert-card-critical">
                        <h4><i class="fa-solid fa-triangle-exclamation"></i> 食品已過期</h4>
                        <ul class="alert-list">
                            <?php foreach ($expiredFoods as $food): ?>
                                <li>
                                    <span><strong><?php echo htmlspecialchars($food['name']); ?></strong></span>
                                    <span><?php echo date('m/d', strtotime($food['todate'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($foodExpiring30Days)): ?>
                    <div class="alert-card alert-card-warning">
                        <h4><i class="fa-solid fa-utensils"></i> 食品即將到期（30 天內）</h4>
                        <ul class="alert-list">
                            <?php foreach ($foodExpiring30Days as $food): ?>
                                <li>
                                    <span><strong><?php echo htmlspecialchars($food['name']); ?></strong></span>
                                    <span><?php echo date('m/d', strtotime($food['todate'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="dashboard-columns">
        <div class="card dashboard-list-card">
            <h3 class="card-title">最近新增的訂閱</h3>
            <?php if (empty($recentSubscriptions)): ?>
                <p class="empty-copy">目前沒有資料。</p>
            <?php else: ?>
                <ul class="dashboard-list">
                    <?php foreach ($recentSubscriptions as $item): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            <span><?php echo formatMoney($item['price']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="card dashboard-list-card">
            <h3 class="card-title">最近新增的食品</h3>
            <?php if (empty($recentFood)): ?>
                <p class="empty-copy">目前沒有資料。</p>
            <?php else: ?>
                <ul class="dashboard-list">
                    <?php foreach ($recentFood as $item): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            <span>數量: <?php echo $item['amount'] ?? 0; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const dashboardAlerts = {
        subscriptions3: <?php echo json_encode(array_map(fn($item) => ['name' => $item['name'], 'date' => formatDate($item['nextdate'])], $subExpiring3Days), JSON_UNESCAPED_UNICODE); ?>,
        foods7: <?php echo json_encode(array_map(fn($item) => ['name' => $item['name'], 'date' => formatDate($item['todate'])], $foodExpiring7Days), JSON_UNESCAPED_UNICODE); ?>,
        expiredFoods: <?php echo json_encode(array_map(fn($item) => ['name' => $item['name'], 'date' => formatDate($item['todate'])], $expiredFoods), JSON_UNESCAPED_UNICODE); ?>
    };

    function sendDashboardNotifications() {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        const today = new Date().toISOString().slice(0, 10);
        const storageKey = 'fengbro.dashboard.notifications.' + today;
        const sent = JSON.parse(localStorage.getItem(storageKey) || '{}');
        const notifyOnce = (key, title, body) => {
            if (sent[key]) return;
            sent[key] = true;
            new Notification(title, { body, icon: 'icon-192x192.png' });
        };
        dashboardAlerts.subscriptions3.slice(0, 3).forEach((item, index) => {
            notifyOnce('sub-' + index + '-' + item.name, '訂閱 3 天內到期', item.name + '：' + item.date);
        });
        dashboardAlerts.foods7.slice(0, 3).forEach((item, index) => {
            notifyOnce('food-' + index + '-' + item.name, '食品 7 天內到期', item.name + '：' + item.date);
        });
        dashboardAlerts.expiredFoods.slice(0, 3).forEach((item, index) => {
            notifyOnce('expired-' + index + '-' + item.name, '食品已過期', item.name + '：' + item.date);
        });
        localStorage.setItem(storageKey, JSON.stringify(sent));
    }

    function requestDashboardNotifications() {
        if (!('Notification' in window)) {
            alert('此瀏覽器不支援通知。');
            return;
        }
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                sendDashboardNotifications();
                alert('提醒已啟用。');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', sendDashboardNotifications);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') sendDashboardNotifications();
    });
</script>
