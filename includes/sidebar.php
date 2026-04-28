<?php
$menuItems = [
    'home' => ['label' => '鋒兄首頁', 'icon' => 'fa-house'],
    'dashboard' => ['label' => '鋒兄儀表', 'icon' => 'fa-gauge-high'],
    'subscription' => ['label' => '鋒兄訂閱', 'icon' => 'fa-credit-card'],
    'food' => ['label' => '鋒兄食品', 'hint' => '（＋商品庫存）', 'icon' => 'fa-boxes-stacked'],
    'notes' => ['label' => '鋒兄筆記', 'icon' => 'fa-note-sticky'],
    'favorites' => ['label' => '鋒兄常用', 'icon' => 'fa-star'],
    'images' => ['label' => '鋒兄圖片', 'icon' => 'fa-image'],
    'videos' => ['label' => '鋒兄影片', 'icon' => 'fa-video'],
    'music' => ['label' => '鋒兄音樂', 'icon' => 'fa-music'],
    'documents' => ['label' => '鋒兄文件', 'icon' => 'fa-folder-open'],
    'podcast' => ['label' => '鋒兄播客', 'icon' => 'fa-podcast'],
    'bank' => ['label' => '鋒兄銀行', 'icon' => 'fa-building-columns'],
    'routine' => ['label' => '鋒兄例行', 'icon' => 'fa-clock-rotate-left'],
    'tools' => ['label' => '鋒兄工具', 'hint' => '（＋比價）', 'icon' => 'fa-wrench'],
    'settings' => ['label' => '鋒兄設定', 'icon' => 'fa-gear'],
    'about' => ['label' => '鋒兄關於', 'icon' => 'fa-circle-info']
];

$currentPage = $_GET['page'] ?? 'home';
?>

<button class="mobile-menu-btn" onclick="toggleMobileMenu()">
    <i class="fa-solid fa-bars"></i>
</button>

<div class="sidebar-overlay" onclick="closeMobileMenu()"></div>

<nav class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand-mark">
            <i class="fa-solid fa-wave-square"></i>
        </div>
        <div class="sidebar-brand-copy">
            <span class="sidebar-kicker">Personal Ops System</span>
            <h2><i class="fa-solid fa-dragon"></i> Fengbro AI</h2>
            <p>Laravel + MySQL workspace</p>
        </div>
        <button id="darkModeToggle" class="dark-mode-btn" onclick="toggleDarkMode()">
            <i class="fa-solid fa-moon"></i>
        </button>
    </div>
    <div class="sidebar-section-label">Workspace</div>
    <ul class="menu">
        <?php foreach ($menuItems as $key => $item): ?>
            <li class="menu-item <?php echo $currentPage === $key ? 'active' : ''; ?>">
                <a href="index.php?page=<?php echo $key; ?>" onclick="closeMobileMenu()">
                    <i class="fa-solid <?php echo $item['icon']; ?>"></i>
                    <span class="menu-label">
                        <span class="menu-label-main"><?php echo $item['label']; ?></span>
                        <?php if (!empty($item['hint'])): ?>
                            <span class="menu-label-hint"><?php echo $item['hint']; ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="menu-arrow"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
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
