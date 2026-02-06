<?php
$pageTitle = '儀表板';
$pdo = getConnection();

// 匯率轉換
$exchangeRates = [
    'TWD' => 1,
    'USD' => 35,
    'EUR' => 40,
    'JPY' => 0.35,
    'CNY' => 4.5,
    'HKD' => 4
];

$subscriptionCount = $pdo->query("SELECT COUNT(*) FROM subscription")->fetchColumn();

// 計算訂閱總額 (轉換為新台幣)
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

// 訂閱到期提醒 (3天、7天內)
$subExpiring3Days = $pdo->query("SELECT * FROM subscription WHERE `continue` = 1 AND nextdate IS NOT NULL AND nextdate <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND nextdate >= CURDATE() ORDER BY nextdate ASC")->fetchAll();
$subExpiring7Days = $pdo->query("SELECT * FROM subscription WHERE `continue` = 1 AND nextdate IS NOT NULL AND nextdate > DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND nextdate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY nextdate ASC")->fetchAll();

// 食品到期提醒 (7天、30天內)
$foodExpiring7Days = $pdo->query("SELECT * FROM food WHERE todate IS NOT NULL AND todate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND todate >= CURDATE() ORDER BY todate ASC")->fetchAll();
$foodExpiring30Days = $pdo->query("SELECT * FROM food WHERE todate IS NOT NULL AND todate > DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND todate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY todate ASC")->fetchAll();

$recentSubscriptions = $pdo->query("SELECT * FROM subscription ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recentFood = $pdo->query("SELECT * FROM food ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Calculate uploads folder size
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

// Count files in uploads
$uploadsFileCount = 0;
if (is_dir($uploadsDir)) {
    $uploadsFileCount = count(glob($uploadsDir . '/*'));
}
?>

<div class="content-header">
    <h1>鋒兄儀表</h1>
</div>

<div class="content-body">
    <!-- 11個資料表統計 -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 15px;">
        <a href="index.php?page=subscription" class="card"
            style="background: linear-gradient(135deg, #3498db, #2980b9); color: #fff; text-decoration: none;">
            <h3><i class="fa-solid fa-credit-card"></i> 訂閱</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $subscriptionCount; ?></p>
            <p style="font-size: 0.85rem;">月支出: <?php echo formatMoney($subscriptionTotal); ?></p>
        </a>
        <a href="index.php?page=food" class="card"
            style="background: linear-gradient(135deg, #27ae60, #219a52); color: #fff; text-decoration: none;">
            <h3><i class="fa-solid fa-utensils"></i> 食品</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $foodCount; ?></p>
        </a>
        <a href="index.php?page=notes" class="card"
            style="background: linear-gradient(135deg, #f1c40f, #d4ac0d); color: #fff; text-decoration: none;">
            <h3><i class="fa-solid fa-note-sticky"></i> 筆記</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $noteCount; ?></p>
        </a>
        <a href="index.php?page=favorites" class="card"
            style="background: linear-gradient(135deg, #e67e22, #cf6d17); color: #fff; text-decoration: none;">
            <h3><i class="fa-solid fa-star"></i> 常用</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $favoriteCount; ?></p>
        </a>
        <a href="index.php?page=images" class="card"
            style="background: linear-gradient(135deg, #f39c12, #d68910); color: #fff; text-decoration: none;">
            <h3><i class="fa-solid fa-image"></i> 圖片</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $imageCount; ?></p>
        </a>
        <a href="index.php?page=videos" class="card"
            style="background: linear-gradient(135deg, #c0392b, #a93226); color: #fff; text-decoration: none;">
            <h3><i class="fa-solid fa-video"></i> 影片</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $videoCount; ?></p>
        </a>
        <a href="index.php?page=music" class="card"
            style="background: linear-gradient(135deg, #1abc9c, #16a085); color: #fff; text-decoration: none;">
            <h3><i class="fa-solid fa-music"></i> 音樂</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $musicCount; ?></p>
        </a>
        <a href="index.php?page=documents" class="card"
            style="background: linear-gradient(135deg, #34495e, #2c3e50); color: #fff; text-decoration: none;">
            <h3><i class="fa-solid fa-file-lines"></i> 文件</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $documentCount; ?></p>
        </a>
        <a href="index.php?page=podcast" class="card"
            style="background: linear-gradient(135deg, #8e44ad, #7d3c98); color: #fff; text-decoration: none;">
            <h3><i class="fa-solid fa-podcast"></i> 播客</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $podcastCount; ?></p>
        </a>
        <a href="index.php?page=bank" class="card"
            style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; text-decoration: none;">
            <h3><i class="fa-solid fa-building-columns"></i> 銀行</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $bankCount; ?></p>
            <p style="font-size: 0.85rem;">總額: <?php echo formatMoney($bankTotal); ?></p>
        </a>
        <a href="index.php?page=routine" class="card"
            style="background: linear-gradient(135deg, #9b59b6, #8e44ad); color: #fff; text-decoration: none;">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> 例行</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $routineCount; ?></p>
        </a>
        <div class="card" style="background: linear-gradient(135deg, #95a5a6, #7f8c8d); color: #fff;">
            <h3><i class="fa-solid fa-hard-drive"></i> 儲存</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $uploadsFolderSizeFormatted; ?></p>
            <p style="font-size: 0.85rem;">檔案: <?php echo $uploadsFileCount; ?> 個</p>
        </div>
    </div>

    <!-- 到期提醒區塊 -->
    <?php if (!empty($subExpiring3Days) || !empty($subExpiring7Days) || !empty($foodExpiring7Days) || !empty($foodExpiring30Days)): ?>
        <div style="margin-top: 25px;">
            <h3 style="margin-bottom: 15px;"><i class="fa-solid fa-bell"></i> 到期提醒</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">

                <?php if (!empty($subExpiring3Days)): ?>
                    <div class="card" style="border-left: 4px solid #e74c3c; background: rgba(231, 76, 60, 0.1);">
                        <h4 style="color: #e74c3c;"><i class="fa-solid fa-credit-card"></i> 訂閱即將到期 (3天內)</h4>
                        <ul style="list-style: none; padding: 0; margin-top: 10px;">
                            <?php foreach ($subExpiring3Days as $sub): ?>
                                <li
                                    style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between;">
                                    <span><strong><?php echo htmlspecialchars($sub['name']); ?></strong></span>
                                    <span style="color: #e74c3c;"><?php echo date('m/d', strtotime($sub['nextdate'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($subExpiring7Days)): ?>
                    <div class="card" style="border-left: 4px solid #f39c12; background: rgba(243, 156, 18, 0.1);">
                        <h4 style="color: #f39c12;"><i class="fa-solid fa-credit-card"></i> 訂閱即將到期 (7天內)</h4>
                        <ul style="list-style: none; padding: 0; margin-top: 10px;">
                            <?php foreach ($subExpiring7Days as $sub): ?>
                                <li
                                    style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between;">
                                    <span><strong><?php echo htmlspecialchars($sub['name']); ?></strong></span>
                                    <span style="color: #f39c12;"><?php echo date('m/d', strtotime($sub['nextdate'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($foodExpiring7Days)): ?>
                    <div class="card" style="border-left: 4px solid #e74c3c; background: rgba(231, 76, 60, 0.1);">
                        <h4 style="color: #e74c3c;"><i class="fa-solid fa-utensils"></i> 食品即將過期 (7天內)</h4>
                        <ul style="list-style: none; padding: 0; margin-top: 10px;">
                            <?php foreach ($foodExpiring7Days as $food): ?>
                                <li
                                    style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between;">
                                    <span><strong><?php echo htmlspecialchars($food['name']); ?></strong></span>
                                    <span style="color: #e74c3c;"><?php echo date('m/d', strtotime($food['todate'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($foodExpiring30Days)): ?>
                    <div class="card" style="border-left: 4px solid #f39c12; background: rgba(243, 156, 18, 0.1);">
                        <h4 style="color: #f39c12;"><i class="fa-solid fa-utensils"></i> 食品即將過期 (30天內)</h4>
                        <ul style="list-style: none; padding: 0; margin-top: 10px;">
                            <?php foreach ($foodExpiring30Days as $food): ?>
                                <li
                                    style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between;">
                                    <span><strong><?php echo htmlspecialchars($food['name']); ?></strong></span>
                                    <span style="color: #f39c12;"><?php echo date('m/d', strtotime($food['todate'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
        <div class="card">
            <h3 class="card-title">最近訂閱</h3>
            <?php if (empty($recentSubscriptions)): ?>
                <p style="color: #999;">暫無資料</p>
            <?php else: ?>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($recentSubscriptions as $item): ?>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            <span style="float: right;"><?php echo formatMoney($item['price']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="card">
            <h3 class="card-title">最近食品</h3>
            <?php if (empty($recentFood)): ?>
                <p style="color: #999;">暫無資料</p>
            <?php else: ?>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($recentFood as $item): ?>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            <span style="float: right;">數量: <?php echo $item['amount'] ?? 0; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>