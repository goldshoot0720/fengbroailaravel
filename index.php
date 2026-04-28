<?php
require_once 'includes/functions.php';

$page = $_GET['page'] ?? 'home';

$allowedPages = [
    'home',
    'dashboard',
    'subscription',
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
    'about'
];

if (!in_array($page, $allowedPages)) {
    $page = 'home';
}

$pageFile = "pages/{$page}.php";
$pageTitles = [
    'home' => '鋒兄首頁',
    'dashboard' => '鋒兄儀表',
    'subscription' => '鋒兄訂閱',
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
    'about' => '鋒兄關於'
];
$pageTitle = $pageTitles[$page] ?? '鋒兄首頁';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="content">
    <?php
    if (file_exists($pageFile)) {
        include $pageFile;
    } else {
        echo '<div class="content-body"><p>頁面不存在</p></div>';
    }
    ?>
</main>

<?php include 'includes/footer.php'; ?>
