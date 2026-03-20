<?php
$menuItems = [
    'home' => ['label' => '首頁', 'icon' => 'fa-house'],
    'dashboard' => ['label' => '儀表板', 'icon' => 'fa-gauge-high'],
    'subscription' => ['label' => '訂閱', 'icon' => 'fa-credit-card'],
    'food' => ['label' => '食品', 'icon' => 'fa-utensils'],
    'notes' => ['label' => '筆記', 'icon' => 'fa-note-sticky'],
    'favorites' => ['label' => '收藏', 'icon' => 'fa-star'],
    'images' => ['label' => '圖片', 'icon' => 'fa-image'],
    'videos' => ['label' => '影片', 'icon' => 'fa-video'],
    'music' => ['label' => '音樂', 'icon' => 'fa-music'],
    'documents' => ['label' => '文件', 'icon' => 'fa-file-lines'],
    'podcast' => ['label' => 'Podcast', 'icon' => 'fa-podcast'],
    'bank' => ['label' => '銀行', 'icon' => 'fa-building-columns'],
    'routine' => ['label' => '例行', 'icon' => 'fa-clock-rotate-left'],
    'settings' => ['label' => '設定', 'icon' => 'fa-gear'],
    'about' => ['label' => '關於', 'icon' => 'fa-circle-info']
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
                    <span class="menu-label"><?php echo $item['label']; ?></span>
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
