<?php
$pageTitle = '儀表板';
require_once __DIR__ . '/../includes/notification_helpers.php';
$pdo = getConnection();

$exchangeRates = [
    'TWD' => 1,
    'USD' => 35,
    'EUR' => 40,
    'JPY' => 0.35,
    'CNY' => 4.5,
    'HKD' => 4,
    'GBP' => 44,
    'KRW' => 0.025,
    'SGD' => 26,
    'AUD' => 23,
];

try { $pdo->exec("ALTER TABLE subscription ADD COLUMN deleted_at DATETIME NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE article ADD COLUMN deleted_at DATETIME NULL"); } catch (Throwable $e) {}
$subscriptionCount = $pdo->query("SELECT COUNT(*) FROM subscription WHERE deleted_at IS NULL")->fetchColumn();
$subscriptions = $pdo->query("SELECT price, currency FROM subscription WHERE `continue` = 1 AND deleted_at IS NULL")->fetchAll();
$subscriptionTotal = 0;
foreach ($subscriptions as $sub) {
    $currency = strtoupper($sub['currency'] ?? 'TWD');
    $rate = $exchangeRates[$currency] ?? 1;
    $subscriptionTotal += round($sub['price'] * $rate);
}

$foodCount = $pdo->query("SELECT COUNT(*) FROM food")->fetchColumn();
$noteCount = $pdo->query("SELECT COUNT(*) FROM article WHERE deleted_at IS NULL")->fetchColumn();
$favoriteCount = $pdo->query("SELECT COUNT(*) FROM commonaccount")->fetchColumn();
$imageCount = $pdo->query("SELECT COUNT(*) FROM image")->fetchColumn();
$videoCount = 0;
try {
    $videoCount = (int) $pdo->query("SELECT COUNT(*) FROM video")->fetchColumn();
} catch (Throwable $e) {
    try {
        $videoCount = (int) $pdo->query("SELECT COUNT(*) FROM commondocument WHERE category = 'video'")->fetchColumn();
    } catch (Throwable $e2) {
        $videoCount = 0;
    }
}
$musicCount = $pdo->query("SELECT COUNT(*) FROM music")->fetchColumn();
$podcastCount = $pdo->query("SELECT COUNT(*) FROM podcast")->fetchColumn();
$documentCount = 0;
try {
    // 文件頁排除 category=video 的舊資料
    $documentCount = (int) $pdo->query("SELECT COUNT(*) FROM commondocument WHERE category != 'video' OR category IS NULL")->fetchColumn();
} catch (Throwable $e) {
    $documentCount = (int) $pdo->query("SELECT COUNT(*) FROM commondocument")->fetchColumn();
}
$bankCount = $pdo->query("SELECT COUNT(*) FROM bank")->fetchColumn();
$bankTotal = $pdo->query("SELECT COALESCE(SUM(deposit), 0) FROM bank")->fetchColumn();
$routineCount = $pdo->query("SELECT COUNT(*) FROM routine")->fetchColumn();
$trialPurchaseCount = 0;
$reinstallCount = 0;
try {
    fengbroEnsureTrialPurchaseTable($pdo);
    $trialPurchaseCount = (int) $pdo->query("SELECT COUNT(*) FROM trialpurchase")->fetchColumn();
} catch (Throwable $e) {
    $trialPurchaseCount = 0;
}
try {
    fengbroEnsureReinstallTable($pdo);
    $reinstallCount = (int) $pdo->query("SELECT COUNT(*) FROM reinstall")->fetchColumn();
} catch (Throwable $e) {
    $reinstallCount = 0;
}
$quotaCount = 0;
try {
    fengbroEnsureQuotaTable($pdo);
    $quotaCount = (int) $pdo->query("SELECT COUNT(*) FROM quota")->fetchColumn();
} catch (Throwable $e) {
    $quotaCount = 0;
}

$subExpiring3Days = notifGetExpiringSubscriptions($pdo, 3);
$subExpiring7Days = $pdo->query(
    "SELECT * FROM subscription
     WHERE `continue` = 1
       AND deleted_at IS NULL
       AND nextdate IS NOT NULL
       AND nextdate > DATE_ADD(CURDATE(), INTERVAL 3 DAY)
       AND nextdate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY nextdate ASC"
)->fetchAll();
$foodExpiring7Days = notifGetExpiringFood($pdo, 7);
$foodExpiring30Days = $pdo->query(
    "SELECT * FROM food
     WHERE todate IS NOT NULL
       AND todate > DATE_ADD(CURDATE(), INTERVAL 7 DAY)
       AND todate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY todate ASC"
)->fetchAll();
$expiredFoods = notifGetExpiredFood($pdo, 5);
$dashboardNotificationAlerts = notifBuildDashboardAlerts($pdo);
$toolSnapshotCount = 0;
try {
    $toolSnapshotCount = $pdo->query("SELECT COUNT(*) FROM tool_price_history")->fetchColumn();
} catch (Exception $e) {
    $toolSnapshotCount = 0;
}

$recentSubscriptions = $pdo->query("SELECT * FROM subscription WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 5")->fetchAll();
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
$storageCapacity = max(1, (int) (getenv('STORAGE_CAPACITY_BYTES') ?: (1024 * 1024 * 1024)));
$storageUsagePercent = min(100, round(($uploadsFolderSize / $storageCapacity) * 100, 1));
$storageStatus = $storageUsagePercent >= 90 ? 'critical' : ($storageUsagePercent >= 75 ? 'warning' : 'healthy');
$uploadsFileCount = 0;
if (is_dir($uploadsDir)) {
    $uploadsFileCount = count(glob($uploadsDir . '/*'));
}

// 伺服器 uploads 子目錄分類（對齊 Appwrite storage-stats 的分類概念）
$uploadBuckets = [
    'images' => 0,
    'videos' => 0,
    'music' => 0,
    'podcasts' => 0,
    'documents' => 0,
    'other' => 0,
];
$uploadBucketCounts = [
    'images' => 0,
    'videos' => 0,
    'music' => 0,
    'podcasts' => 0,
    'documents' => 0,
    'other' => 0,
];
if (is_dir($uploadsDir)) {
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            $size = (int) $file->getSize();
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'], true)) {
                $bucket = 'images';
            } elseif (in_array($ext, ['mp4', 'webm', 'mov', 'mkv', 'avi', 'm4v'], true)) {
                $bucket = 'videos';
            } elseif (in_array($ext, ['mp3', 'wav', 'm4a', 'flac', 'aac', 'ogg', 'oga'], true)) {
                // 粗分：路徑含 podcast 才算播客
                $path = strtolower(str_replace('\\', '/', $file->getPathname()));
                $bucket = (str_contains($path, 'podcast')) ? 'podcasts' : 'music';
            } elseif (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md', 'csv', 'zip', 'json', 'xml'], true)) {
                $bucket = 'documents';
            } else {
                $bucket = 'other';
            }
            $uploadBuckets[$bucket] += $size;
            $uploadBucketCounts[$bucket]++;
        }
    } catch (Throwable $e) {
        // ignore scan errors
    }
}
$uploadBucketLabels = [
    'images' => '圖片',
    'videos' => '影片',
    'music' => '音樂',
    'podcasts' => '播客',
    'documents' => '文件',
    'other' => '其他',
];
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
        <a href="index.php?page=trialpurchase" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-flask"></i></span>
            <span class="metric-label">Trial / first purchase</span>
            <strong><?php echo $trialPurchaseCount; ?></strong>
            <small>Services and account trials</small>
        </a>
        <a href="index.php?page=reinstall" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-laptop"></i></span>
            <span class="metric-label">Reinstall</span>
            <strong><?php echo $reinstallCount; ?></strong>
            <small>Windows / Mac software list</small>
        </a>
        <a href="index.php?page=quota" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-gauge-high"></i></span>
            <span class="metric-label">Quota</span>
            <strong><?php echo $quotaCount; ?></strong>
            <small>AI service points and daily quota</small>
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
        <div class="metric-card metric-card-storage storage-threshold-<?php echo $storageStatus; ?>">
            <span class="metric-icon"><i class="fa-solid fa-gauge-high"></i></span>
            <span class="metric-label">Storage capacity</span>
            <strong><?php echo $storageUsagePercent; ?>%</strong>
            <small><?php echo formatBytes($uploadsFolderSize); ?> / <?php echo formatBytes($storageCapacity); ?></small>
        </div>
        <div class="metric-card metric-card-storage" id="mediaTrafficMetric">
            <span class="metric-icon"><i class="fa-solid fa-chart-line"></i></span>
            <span class="metric-label">Monthly media traffic</span>
            <strong id="mediaTrafficValue">--</strong>
            <small id="mediaTrafficHint">Browser-side estimate</small>
        </div>
        <a href="index.php?page=settings" class="metric-card" id="offlineCacheMetricCard">
            <span class="metric-icon"><i class="fa-solid fa-database"></i></span>
            <span class="metric-label">Offline cache</span>
            <strong id="offlineCacheMetricValue">…</strong>
            <small id="offlineCacheMetricHint">IndexedDB 媒體快取</small>
        </a>
        <a href="index.php?page=tools" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-wrench"></i></span>
            <span class="metric-label">Tools snapshots</span>
            <strong><?php echo $toolSnapshotCount; ?></strong>
            <small>BigGo and phone compare history</small>
        </a>
    </section>

    <section class="dashboard-section storage-breakdown-section">
        <div class="section-heading">
            <h3><i class="fa-solid fa-chart-pie"></i> 儲存空間分類</h3>
            <p>伺服器 uploads 依副檔名粗分；本機 IndexedDB 快取由瀏覽器即時讀取（上限每類型 500MB）。</p>
        </div>
        <div class="storage-breakdown-grid">
            <article class="card storage-breakdown-card">
                <h4>伺服器 uploads</h4>
                <div class="storage-breakdown-total">
                    <strong><?php echo htmlspecialchars($uploadsFolderSizeFormatted); ?></strong>
                    <span><?php echo (int) $uploadsFileCount; ?> files</span>
                </div>
                <div class="storage-breakdown-list">
                    <?php foreach ($uploadBucketLabels as $key => $label): ?>
                        <?php
                        $size = (int) ($uploadBuckets[$key] ?? 0);
                        $count = (int) ($uploadBucketCounts[$key] ?? 0);
                        $ratio = $uploadsFolderSize > 0 ? min(100, round(($size / $uploadsFolderSize) * 100)) : 0;
                        ?>
                        <div class="storage-breakdown-row">
                            <div class="storage-breakdown-meta">
                                <span><?php echo htmlspecialchars($label); ?></span>
                                <small><?php echo $count; ?> · <?php echo htmlspecialchars(formatBytes($size)); ?></small>
                            </div>
                            <div class="storage-breakdown-bar">
                                <div style="width:<?php echo $ratio; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
            <article class="card storage-breakdown-card">
                <h4>本機 Offline cache</h4>
                <div class="storage-breakdown-total">
                    <strong id="offlineBreakdownTotal">…</strong>
                    <span id="offlineBreakdownHint">IndexedDB</span>
                </div>
                <div id="offlineBreakdownList" class="storage-breakdown-list">
                    <p style="color:var(--muted-text);margin:0;">讀取中…</p>
                </div>
                <div style="margin-top:12px;">
                    <a class="btn btn-sm btn-ghost" href="index.php?page=settings">管理快取</a>
                </div>
            </article>
        </div>
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
    const dashboardAlerts = <?php echo json_encode($dashboardNotificationAlerts, JSON_UNESCAPED_UNICODE); ?>;

    function sendDashboardNotifications() {
        if (window.FengbroNotifications) {
            FengbroNotifications.sendDashboardNotifications(dashboardAlerts);
        }
    }

    function loadMediaTrafficMetric() {
        const value = document.getElementById('mediaTrafficValue');
        const hint = document.getElementById('mediaTrafficHint');
        if (!value || !window.FengbroMediaTraffic) return;
        const data = window.FengbroMediaTraffic.read();
        const bytes = Number(data.bytes) || 0;
        const units = ['B', 'KB', 'MB', 'GB'];
        let amount = bytes, unit = 0;
        while (amount >= 1024 && unit < units.length - 1) { amount /= 1024; unit++; }
        value.textContent = amount.toFixed(unit === 0 ? 0 : 1) + ' ' + units[unit];
        hint.textContent = (Number(data.requests) || 0) + ' media requests this month';
        const limit = 100 * 1024 * 1024 * 1024;
        if (bytes >= limit * .9) hint.textContent += ' · critical';
        else if (bytes >= limit * .75) hint.textContent += ' · warning';
    }
    window.addEventListener('fengbro:media-traffic', loadMediaTrafficMetric);

    function requestDashboardNotifications() {
        if (window.FengbroNotifications) {
            FengbroNotifications.requestDashboardNotifications(dashboardAlerts);
            return;
        }
        alert('通知模組尚未載入。');
    }

    async function loadOfflineCacheMetric() {
        const valueEl = document.getElementById('offlineCacheMetricValue');
        const hintEl = document.getElementById('offlineCacheMetricHint');
        const totalEl = document.getElementById('offlineBreakdownTotal');
        const listEl = document.getElementById('offlineBreakdownList');
        const listHint = document.getElementById('offlineBreakdownHint');
        const labels = {
            video: '影片',
            music: '音樂',
            podcast: '播客',
            document: '文件',
            image: '圖片'
        };

        if (!window.FengbroMediaCache || !window.FengbroMediaCache.getAllStats) {
            if (valueEl) valueEl.textContent = 'N/A';
            if (hintEl) hintEl.textContent = '此瀏覽器不支援 IndexedDB';
            if (totalEl) totalEl.textContent = 'N/A';
            if (listEl) listEl.innerHTML = '<p style="color:var(--muted-text);margin:0;">此瀏覽器不支援 IndexedDB</p>';
            return;
        }
        try {
            const summary = await window.FengbroMediaCache.getAllStats();
            if (valueEl) valueEl.textContent = window.FengbroMediaCache.formatBytes(summary.totalSize || 0);
            if (hintEl) hintEl.textContent = (summary.totalItems || 0) + ' 項 · 點此管理快取';
            if (totalEl) totalEl.textContent = window.FengbroMediaCache.formatBytes(summary.totalSize || 0);
            if (listHint) listHint.textContent = (summary.totalItems || 0) + ' 項';
            if (listEl) {
                const maxSize = summary.maxSizePerKind || (500 * 1024 * 1024);
                const rows = (summary.kinds || []).map(function (row) {
                    const ratio = Math.min(100, Math.round(((row.totalSize || 0) / maxSize) * 100));
                    return `
                        <div class="storage-breakdown-row">
                            <div class="storage-breakdown-meta">
                                <span>${labels[row.kind] || row.kind}</span>
                                <small>${row.totalItems || 0} · ${window.FengbroMediaCache.formatBytes(row.totalSize || 0)}</small>
                            </div>
                            <div class="storage-breakdown-bar">
                                <div style="width:${ratio}%;"></div>
                            </div>
                        </div>
                    `;
                }).join('');
                listEl.innerHTML = rows || '<p style="color:var(--muted-text);margin:0;">尚無離線快取</p>';
            }
        } catch (e) {
            if (valueEl) valueEl.textContent = '--';
            if (hintEl) hintEl.textContent = '讀取失敗';
            if (totalEl) totalEl.textContent = '--';
            if (listEl) listEl.innerHTML = '<p style="color:#e74c3c;margin:0;">讀取失敗</p>';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        sendDashboardNotifications();
        loadOfflineCacheMetric();
        loadMediaTrafficMetric();
    });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            sendDashboardNotifications();
            loadOfflineCacheMetric();
        }
    });
</script>
