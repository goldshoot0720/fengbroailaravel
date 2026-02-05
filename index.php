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
    'bgata',
    'documents',
    'podcast',
    'bank',
    'routine',
    'settings',
    'about'
];

if (!in_array($page, $allowedPages)) {
    $page = 'home';
}

$pageFile = "pages/{$page}.php";

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