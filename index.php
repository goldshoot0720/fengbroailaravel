<?php
require_once 'includes/functions.php';

$page = $_GET['page'] ?? 'home';

// 對齊 Appwrite：儀表已合併進首頁（精簡／完整儀表同一頁），舊網址一律落到首頁完整儀表。
$homeInitialFullView = false;
if ($page === 'dashboard') {
    $page = 'home';
    $homeInitialFullView = true;
}

$allowedPages = [
    'home',
    'subscription',
    'trialpurchase',
    'reinstall',
    'quota',
    'food',
    'notes',
    'favorites',
    'images',
    'videos',
    'music',
    'documents',
    'podcast',
    'bank',
    'routine',
    'tools',
    'settings',
    'about',
    'service'
];

if (!in_array($page, $allowedPages)) {
    $page = 'home';
}

$pageFile = "pages/{$page}.php";
$pageTitles = [
    'home' => '鋒兄首頁',
    'subscription' => '鋒兄訂閱',
    'trialpurchase' => '鋒兄試用／首購',
    'reinstall' => '鋒兄重灌',
    'quota' => '鋒兄額度',
    'food' => '鋒兄食品 （＋商品庫存）',
    'notes' => '鋒兄筆記',
    'favorites' => '鋒兄常用',
    'images' => '鋒兄圖片',
    'videos' => '鋒兄影片',
    'music' => '鋒兄音樂',
    'documents' => '鋒兄文件',
    'podcast' => '鋒兄播客',
    'bank' => '鋒兄銀行',
    'routine' => '鋒兄例行',
    'tools' => '鋒兄工具 （＋比價）',
    'settings' => '鋒兄設定',
    'about' => '鋒兄關於',
    'service' => '服務資訊'
];
$pageTitle = $pageTitles[$page] ?? '鋒兄首頁';
$bodyDataTool = '';
if (($page ?? '') === 'tools') {
    $requestedTool = (string) ($_GET['tool'] ?? '');
    $bodyDataTool = in_array($requestedTool, [
        'price', 'phone', 'manual', 'tube', 'finance', 'news',
        'image-convert', 'image-voice', 'video-merge', 'yt-bili',
    ], true) ? $requestedTool : '';
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="content" id="mainContent" tabindex="-1">
    <?php
    if (file_exists($pageFile)) {
        include $pageFile;
    } else {
        echo '<div class="content-body"><p>頁面不存在</p></div>';
    }
    ?>
</main>

<?php include 'includes/footer.php'; ?>
