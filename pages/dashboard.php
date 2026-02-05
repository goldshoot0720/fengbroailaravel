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
$bankTotal = $pdo->query("SELECT COALESCE(SUM(deposit), 0) FROM bank")->fetchColumn();
$imageCount = $pdo->query("SELECT COUNT(*) FROM image")->fetchColumn();
$musicCount = $pdo->query("SELECT COUNT(*) FROM music")->fetchColumn();
$podcastCount = $pdo->query("SELECT COUNT(*) FROM podcast")->fetchColumn();
$documentCount = $pdo->query("SELECT COUNT(*) FROM commondocument")->fetchColumn();
$routineCount = $pdo->query("SELECT COUNT(*) FROM routine")->fetchColumn();

$recentSubscriptions = $pdo->query("SELECT * FROM subscription ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recentFood = $pdo->query("SELECT * FROM food ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Calculate uploads folder size
function getFolderSize($dir) {
    $size = 0;
    if (is_dir($dir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
    }
    return $size;
}

function formatBytes($bytes, $precision = 2) {
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
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div class="card" style="background: linear-gradient(135deg, #3498db, #2980b9); color: #fff;">
            <h3>訂閱服務</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $subscriptionCount; ?></p>
            <p>每月支出: <?php echo formatMoney($subscriptionTotal); ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #27ae60, #219a52); color: #fff;">
            <h3>銀行總額</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo formatMoney($bankTotal); ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff;">
            <h3>食品紀錄</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $foodCount; ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #9b59b6, #8e44ad); color: #fff;">
            <h3>例行事項</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $routineCount; ?></p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
        <div class="card" style="background: linear-gradient(135deg, #f39c12, #d68910); color: #fff;">
            <h3>圖片</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $imageCount; ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #1abc9c, #16a085); color: #fff;">
            <h3>音樂</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $musicCount; ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #e67e22, #cf6d17); color: #fff;">
            <h3>播客</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $podcastCount; ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #34495e, #2c3e50); color: #fff;">
            <h3>文件</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $documentCount; ?></p>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #95a5a6, #7f8c8d); color: #fff;">
            <h3>儲存空間</h3>
            <p style="font-size: 2rem; margin-top: 10px;"><?php echo $uploadsFolderSizeFormatted; ?></p>
            <p>檔案數量: <?php echo $uploadsFileCount; ?></p>
        </div>
    </div>

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
