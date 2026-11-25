<?php
require_once fengbroRootPath() . '/includes/notification_helpers.php';
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

try { fengbroEnsureSoftDeleteColumn($pdo, 'subscription'); } catch (Throwable $e) {}
try { fengbroEnsureSoftDeleteColumn($pdo, 'article'); } catch (Throwable $e) {}
// 效能：所有統計合併成一次往返；任何一張表缺漏導致失敗時，退回下方原本逐一查詢的寫法。
$dashboardFastCounts = null;
try {
    fengbroEnsureTrialPurchaseTable($pdo);
    fengbroEnsureReinstallTable($pdo);
    fengbroEnsureQuotaTable($pdo);
    fengbroEnsureShoppingListTable($pdo);
    $dashboardFastCounts = $pdo->query("SELECT
        (SELECT COUNT(*) FROM subscription WHERE deleted_at IS NULL) AS subscriptionCount,
        (SELECT COUNT(*) FROM food) AS foodCount,
        (SELECT COUNT(*) FROM article WHERE deleted_at IS NULL) AS noteCount,
        (SELECT COUNT(*) FROM commonaccount) AS favoriteCount,
        (SELECT COUNT(*) FROM image) AS imageCount,
        (SELECT COUNT(*) FROM video) AS videoCount,
        (SELECT COUNT(*) FROM music) AS musicCount,
        (SELECT COUNT(*) FROM podcast) AS podcastCount,
        (SELECT COUNT(*) FROM commondocument WHERE category != 'video' OR category IS NULL) AS documentCount,
        (SELECT COUNT(*) FROM bank) AS bankCount,
        (SELECT COALESCE(SUM(deposit), 0) FROM bank) AS bankTotal,
        (SELECT COUNT(*) FROM routine) AS routineCount,
        (SELECT COUNT(*) FROM trialpurchase) AS trialPurchaseCount,
        (SELECT COUNT(*) FROM reinstall) AS reinstallCount,
        (SELECT COUNT(*) FROM quota) AS quotaCount,
        (SELECT COUNT(*) FROM shoppinglist) AS shoppingCount")->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    $dashboardFastCounts = null;
}

if (is_array($dashboardFastCounts)) {
    $subscriptionCount = (int) $dashboardFastCounts['subscriptionCount'];
    $foodCount = (int) $dashboardFastCounts['foodCount'];
    $noteCount = (int) $dashboardFastCounts['noteCount'];
    $favoriteCount = (int) $dashboardFastCounts['favoriteCount'];
    $imageCount = (int) $dashboardFastCounts['imageCount'];
    $videoCount = (int) $dashboardFastCounts['videoCount'];
    $musicCount = (int) $dashboardFastCounts['musicCount'];
    $podcastCount = (int) $dashboardFastCounts['podcastCount'];
    $documentCount = (int) $dashboardFastCounts['documentCount'];
    $bankCount = (int) $dashboardFastCounts['bankCount'];
    $bankTotal = $dashboardFastCounts['bankTotal'];
    $routineCount = (int) $dashboardFastCounts['routineCount'];
    $trialPurchaseCount = (int) $dashboardFastCounts['trialPurchaseCount'];
    $reinstallCount = (int) $dashboardFastCounts['reinstallCount'];
    $quotaCount = (int) $dashboardFastCounts['quotaCount'];
    $shoppingCount = (int) $dashboardFastCounts['shoppingCount'];

    $subscriptionTotal = 0;
    foreach ($pdo->query("SELECT price, currency FROM subscription WHERE `continue` = 1 AND deleted_at IS NULL")->fetchAll() as $sub) {
        $currency = strtoupper($sub['currency'] ?? 'TWD');
        $rate = $exchangeRates[$currency] ?? 1;
        $subscriptionTotal += round($sub['price'] * $rate);
    }
} else {
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
    $shoppingCount = 0;
    try {
        fengbroEnsureShoppingListTable($pdo);
        $shoppingCount = (int) $pdo->query("SELECT COUNT(*) FROM shoppinglist")->fetchColumn();
    } catch (Throwable $e) {
        $shoppingCount = 0;
    }

}

$dashboardAlerts = $homeAlerts ?? notifBuildUnifiedDashboardAlerts($pdo);
$subExpiring3Days = $dashboardAlerts['subscriptions'];
$trialExpiring3 = $dashboardAlerts['trialPurchases'];
$quotaExpiringSoon = $dashboardAlerts['quotas'];
$shoppingExpiring3 = $dashboardAlerts['shoppingItems'];
$foodExpiring7Days = $dashboardAlerts['foods'];
$expiredFoods = $dashboardAlerts['expiredFoods'];
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
    $subExpiring7Days = $pdo->query(
        "SELECT * FROM subscription
         WHERE `continue` = 1
           AND deleted_at IS NULL
           AND nextdate IS NOT NULL
           AND date(nextdate) > date('now', '+3 days')
           AND date(nextdate) <= date('now', '+7 days')
         ORDER BY nextdate ASC"
    )->fetchAll();
    $foodExpiring30Days = $pdo->query(
        "SELECT * FROM food
         WHERE todate IS NOT NULL
           AND date(todate) > date('now', '+7 days')
           AND date(todate) <= date('now', '+30 days')
         ORDER BY todate ASC"
    )->fetchAll();
} else {
    $subExpiring7Days = $pdo->query(
        "SELECT * FROM subscription
         WHERE `continue` = 1
           AND deleted_at IS NULL
           AND nextdate IS NOT NULL
           AND nextdate > DATE_ADD(CURDATE(), INTERVAL 3 DAY)
           AND nextdate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         ORDER BY nextdate ASC"
    )->fetchAll();
    $foodExpiring30Days = $pdo->query(
        "SELECT * FROM food
         WHERE todate IS NOT NULL
           AND todate > DATE_ADD(CURDATE(), INTERVAL 7 DAY)
           AND todate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
         ORDER BY todate ASC"
    )->fetchAll();
}
$toolSnapshotCount = 0;
try {
    $toolSnapshotCount = $pdo->query("SELECT COUNT(*) FROM tool_price_history")->fetchColumn();
} catch (Exception $e) {
    $toolSnapshotCount = 0;
}

$recentSubscriptions = $pdo->query("SELECT * FROM subscription WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recentFood = $pdo->query("SELECT * FROM food ORDER BY created_at DESC LIMIT 5")->fetchAll();

if (!function_exists('formatBytes')) {
function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
}

$uploadsDir = fengbroRootPath() . '/uploads';

/**
 * 效能：uploads 只掃一次（原本總大小與分類各掃一次），結果快取 120 秒，
 * 首頁完整儀表連續切換或重新整理時不用每次都遞迴讀整個 uploads。
 */
if (!function_exists('fengbroDashboardUploadStats')) {
function fengbroDashboardUploadStats(string $uploadsDir): array
{
    $cacheFile = function_exists('fengbroSchemaCacheDir') && fengbroSchemaCacheDir() !== null
        ? fengbroSchemaCacheDir() . DIRECTORY_SEPARATOR . 'dashboard_upload_stats.json'
        : null;
    if ($cacheFile !== null) {
        $mtime = @filemtime($cacheFile);
        if ($mtime !== false && (time() - $mtime) < 120) {
            $cached = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['buckets'], $cached['counts'])) {
                return $cached;
            }
        }
    }

    $buckets = ['images' => 0, 'videos' => 0, 'music' => 0, 'podcasts' => 0, 'documents' => 0, 'other' => 0];
    $counts = $buckets;
    $total = 0;
    $topLevel = 0;
    if (is_dir($uploadsDir)) {
        $topLevel = count((array) glob($uploadsDir . '/*'));
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
                $total += $size;
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
                $buckets[$bucket] += $size;
                $counts[$bucket]++;
            }
        } catch (Throwable $e) {
            // ignore scan errors
        }
    }

    $stats = ['total' => $total, 'topLevel' => $topLevel, 'buckets' => $buckets, 'counts' => $counts];
    if ($cacheFile !== null) {
        @file_put_contents($cacheFile, json_encode($stats), LOCK_EX);
    }
    return $stats;
}
}

$dashboardUploadStats = fengbroDashboardUploadStats($uploadsDir);
$uploadsFolderSize = (int) $dashboardUploadStats['total'];
$uploadsFolderSizeFormatted = formatBytes($uploadsFolderSize);
$storageCapacity = max(1, (int) (getenv('STORAGE_CAPACITY_BYTES') ?: (1024 * 1024 * 1024)));
$storageUsagePercent = min(100, round(($uploadsFolderSize / $storageCapacity) * 100, 1));
$storageStatus = $storageUsagePercent >= 90 ? 'critical' : ($storageUsagePercent >= 75 ? 'warning' : 'healthy');
$uploadsFileCount = (int) $dashboardUploadStats['topLevel'];

// 伺服器 uploads 子目錄分類（對齊 Appwrite storage-stats 的分類概念）
$uploadBuckets = $dashboardUploadStats['buckets'];
$uploadBucketCounts = $dashboardUploadStats['counts'];
$uploadBucketLabels = [
    'images' => '圖片',
    'videos' => '影片',
    'music' => '音樂',
    'podcasts' => '播客',
    'documents' => '文件',
    'other' => '其他',
];
?>

<div class="content-body" data-dashboard>
    <?php if (!empty($subExpiring3Days) || !empty($subExpiring7Days) || !empty($foodExpiring7Days) || !empty($foodExpiring30Days) || !empty($expiredFoods) || !empty($trialExpiring3) || !empty($quotaExpiringSoon) || !empty($shoppingExpiring3)): ?>
        <div class="dashboard-section">
            <div class="section-heading">
                <h2><i class="fa-solid fa-bell" aria-hidden="true"></i> 到期提醒</h2>
            </div>
            <div class="alert-grid">
                <?php if (!empty($subExpiring3Days)): ?>
                    <div class="alert-card alert-card-critical">
                        <h4><i class="fa-solid fa-credit-card"></i> 訂閱即將到期（3 天內）</h4>
                        <ul class="alert-list">
                            <?php foreach ($subExpiring3Days as $sub): ?>
                                <li>
                                    <span><strong><?php echo htmlspecialchars($sub['name']); ?></strong></span>
                                    <span><?php echo date('m/d', strtotime($sub['date'])); ?></span>
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
                                    <span><?php echo date('m/d', strtotime($food['date'])); ?></span>
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
                                    <span><?php echo date('m/d', strtotime($food['date'])); ?></span>
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

                <?php if (!empty($trialExpiring3)): ?>
                    <div class="alert-card alert-card-warning">
                        <h4><i class="fa-solid fa-flask"></i> 試用／首購 3 天內到期</h4>
                        <ul class="alert-list">
                            <?php foreach ($trialExpiring3 as $row): ?>
                                <li>
                                    <span><strong><?php echo htmlspecialchars($row['name']); ?></strong></span>
                                    <span><?php echo date('m/d', strtotime($row['date'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($quotaExpiringSoon)): ?>
                    <div class="alert-card alert-card-warning">
                        <h4><i class="fa-solid fa-gauge-high"></i> 額度即將到期</h4>
                        <ul class="alert-list">
                            <?php foreach ($quotaExpiringSoon as $row): ?>
                                <?php $quotaDateLabel = (string) ($row['expiryDate'] ?? ''); $quotaDateTs = strtotime($quotaDateLabel); ?>
                                <li>
                                    <span><strong><?php echo htmlspecialchars($row['name']); ?><?php if (!empty($row['label'])): ?>（<?php echo htmlspecialchars($row['label']); ?>）<?php endif; ?></strong></span>
                                    <span><?php echo $quotaDateTs !== false ? date('Y-m-d', $quotaDateTs) : htmlspecialchars($quotaDateLabel); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($shoppingExpiring3)): ?>
                    <div class="alert-card alert-card-warning">
                        <h4><i class="fa-solid fa-cart-shopping"></i> 購物清單 3 天內要買</h4>
                        <ul class="alert-list">
                            <?php foreach ($shoppingExpiring3 as $row): ?>
                                <li>
                                    <span><strong><?php echo htmlspecialchars($row['name']); ?></strong></span>
                                    <span><?php echo date('m/d', strtotime($row['date'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <h2>資料總覽</h2>
    <section class="dashboard-metrics-grid" aria-label="各功能統計">
        <a href="index.php?page=subscription" class="metric-card metric-card-featured">
            <span class="metric-icon"><i class="fa-solid fa-credit-card"></i></span>
            <span class="metric-label">訂閱</span>
            <strong><?php echo $subscriptionCount; ?></strong>
            <small>續訂估算 <?php echo formatMoney($subscriptionTotal); ?></small>
        </a>
        <a href="index.php?page=trialpurchase" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-flask"></i></span>
            <span class="metric-label">試用／首購</span>
            <strong><?php echo $trialPurchaseCount; ?></strong>
        </a>
        <a href="index.php?page=reinstall" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-laptop"></i></span>
            <span class="metric-label">重灌軟體</span>
            <strong><?php echo $reinstallCount; ?></strong>
            <small>Windows / Mac</small>
        </a>
        <a href="index.php?page=quota" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-gauge-high"></i></span>
            <span class="metric-label">額度</span>
            <strong><?php echo $quotaCount; ?></strong>
        </a>
        <a href="index.php?page=shoppinglist" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-cart-shopping"></i></span>
            <span class="metric-label">購物清單</span>
            <strong><?php echo $shoppingCount; ?></strong>
        </a>
        <a href="index.php?page=food" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-utensils"></i></span>
            <span class="metric-label">食品庫存</span>
            <strong><?php echo $foodCount; ?></strong>
        </a>
        <a href="index.php?page=notes" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-note-sticky"></i></span>
            <span class="metric-label">筆記</span>
            <strong><?php echo $noteCount; ?></strong>
        </a>
        <a href="index.php?page=favorites" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-star"></i></span>
            <span class="metric-label">常用資料</span>
            <strong><?php echo $favoriteCount; ?></strong>
        </a>
        <a href="index.php?page=images" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-image"></i></span>
            <span class="metric-label">圖片</span>
            <strong><?php echo $imageCount; ?></strong>
        </a>
        <a href="index.php?page=videos" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-video"></i></span>
            <span class="metric-label">影片</span>
            <strong><?php echo $videoCount; ?></strong>
        </a>
        <a href="index.php?page=music" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-music"></i></span>
            <span class="metric-label">音樂</span>
            <strong><?php echo $musicCount; ?></strong>
        </a>
        <a href="index.php?page=documents" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-file-lines"></i></span>
            <span class="metric-label">文件</span>
            <strong><?php echo $documentCount; ?></strong>
        </a>
        <a href="index.php?page=podcast" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-podcast"></i></span>
            <span class="metric-label">播客</span>
            <strong><?php echo $podcastCount; ?></strong>
        </a>
        <a href="index.php?page=bank" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-building-columns"></i></span>
            <span class="metric-label">銀行與票證</span>
            <strong><?php echo $bankCount; ?></strong>
            <small>合計 <?php echo formatMoney($bankTotal); ?></small>
        </a>
        <a href="index.php?page=routine" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <span class="metric-label">例行事項</span>
            <strong><?php echo $routineCount; ?></strong>
        </a>
        <div class="metric-card metric-card-storage">
            <span class="metric-icon"><i class="fa-solid fa-hard-drive"></i></span>
            <span class="metric-label">已用空間</span>
            <strong><?php echo $uploadsFolderSizeFormatted; ?></strong>
            <small><?php echo $uploadsFileCount; ?> 個檔案</small>
        </div>
        <div class="metric-card metric-card-storage storage-threshold-<?php echo $storageStatus; ?>">
            <span class="metric-icon"><i class="fa-solid fa-gauge-high"></i></span>
            <span class="metric-label">容量使用率</span>
            <strong><?php echo $storageUsagePercent; ?>%</strong>
            <small><?php echo formatBytes($uploadsFolderSize); ?> / <?php echo formatBytes($storageCapacity); ?></small>
        </div>
        <div class="metric-card metric-card-storage" id="mediaTrafficMetric">
            <span class="metric-icon"><i class="fa-solid fa-chart-line"></i></span>
            <span class="metric-label">本月媒體流量</span>
            <strong id="mediaTrafficValue">--</strong>
            <small id="mediaTrafficHint">此瀏覽器估計值</small>
        </div>
        <a href="index.php?page=settings" class="metric-card" id="offlineCacheMetricCard">
            <span class="metric-icon"><i class="fa-solid fa-database"></i></span>
            <span class="metric-label">離線快取</span>
            <strong id="offlineCacheMetricValue">…</strong>
            <small id="offlineCacheMetricHint">IndexedDB 媒體快取</small>
        </a>
        <a href="index.php?page=tools" class="metric-card">
            <span class="metric-icon"><i class="fa-solid fa-wrench"></i></span>
            <span class="metric-label">比價紀錄</span>
            <strong><?php echo $toolSnapshotCount; ?></strong>
            <small>BigGo 與手機比價</small>
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
                    <span><?php echo (int) $uploadsFileCount; ?> 個檔案</span>
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
                <h4>本機離線快取</h4>
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
