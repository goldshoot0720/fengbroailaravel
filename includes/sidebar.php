<?php
$menuItems = [
    'home' => ['label' => '鋒兄首頁', 'icon' => 'fa-house'],
    'dashboard' => ['label' => '鋒兄儀表', 'icon' => 'fa-gauge-high'],
    'subscription' => ['label' => '鋒兄訂閱', 'icon' => 'fa-credit-card'],
    'food' => ['label' => '鋒兄食品', 'icon' => 'fa-utensils'],
    'notes' => ['label' => '鋒兄筆記', 'icon' => 'fa-note-sticky'],
    'favorites' => ['label' => '鋒兄常用', 'icon' => 'fa-star'],
    'images' => ['label' => '鋒兄圖片', 'icon' => 'fa-image'],
    'videos' => ['label' => '鋒兄影片', 'icon' => 'fa-video'],
    'music' => ['label' => '鋒兄音樂', 'icon' => 'fa-music'],
    'documents' => ['label' => '鋒兄文件', 'icon' => 'fa-file-lines'],
    'podcast' => ['label' => '鋒兄播客', 'icon' => 'fa-podcast'],
    'bank' => ['label' => '鋒兄銀行', 'icon' => 'fa-building-columns'],
    'routine' => ['label' => '鋒兄例行', 'icon' => 'fa-clock-rotate-left'],
    'settings' => ['label' => '鋒兄設定', 'icon' => 'fa-gear'],
    'about' => ['label' => '鋒兄關於', 'icon' => 'fa-circle-info']
];

$currentPage = $_GET['page'] ?? 'home';
?>

<!-- 手機版漢堡選單按鈕 -->
<button class="mobile-menu-btn" onclick="toggleMobileMenu()">
    <i class="fa-solid fa-bars"></i>
</button>

<!-- 側邊欄遮罩 -->
<div class="sidebar-overlay" onclick="closeMobileMenu()"></div>

<nav class="sidebar">
    <div class="sidebar-header">
        <h2><i class="fa-solid fa-dragon"></i> 鋒兄AI</h2>
        <p style="font-size: 0.75rem; opacity: 0.7; margin-top: 5px;">Laravel+Mysql</p>
        <button id="darkModeToggle" class="dark-mode-btn" onclick="toggleDarkMode()">
            <i class="fa-solid fa-moon"></i>
        </button>
    </div>
    <ul class="menu">
        <?php foreach ($menuItems as $key => $item): ?>
            <li class="menu-item <?php echo $currentPage === $key ? 'active' : ''; ?>">
                <a href="index.php?page=<?php echo $key; ?>" onclick="closeMobileMenu()">
                    <i class="fa-solid <?php echo $item['icon']; ?>"></i>
                    <span><?php echo $item['label']; ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<script>
    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    }

    function closeMobileMenu() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    }
</script>