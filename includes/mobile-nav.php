<?php
/**
 * 行動版導覽（≤768px 顯示）
 * ── 頂部 app bar（選單／頁名／主題／語音）＋ 底部分頁列（首頁・生活・媒體・工具・更多）
 * ── 桌面與平板一律隱藏，樣式集中在 assets/css/style.css 的「行動與平板介面」區塊。
 */
$mCurPage = $_GET['page'] ?? 'home';

$mLifeSet  = ['subscription', 'trialpurchase', 'reinstall', 'quota', 'shoppinglist', 'food', 'bank', 'routine'];
$mMediaSet = ['images', 'videos', 'music', 'podcast'];
$mMoreSet  = ['notes', 'favorites', 'documents', 'settings', 'about', 'service'];

$mActive = 'home';
if (in_array($mCurPage, $mLifeSet, true)) {
    $mActive = 'life';
} elseif (in_array($mCurPage, $mMediaSet, true)) {
    $mActive = 'media';
} elseif ($mCurPage === 'tools') {
    $mActive = 'tools';
} elseif (in_array($mCurPage, $mMoreSet, true)) {
    $mActive = 'more';
}

$mTabs = [
    ['key' => 'home',  'label' => '首頁', 'icon' => 'fa-house',           'href' => 'index.php?page=home',              'menu' => 'page=home'],
    ['key' => 'life',  'label' => '生活', 'icon' => 'fa-calendar-check',  'href' => 'index.php?page=subscription',      'menu' => 'page=subscription'],
    ['key' => 'media', 'label' => '媒體', 'icon' => 'fa-photo-film',      'href' => 'index.php?page=images',            'menu' => 'page=images'],
    ['key' => 'tools', 'label' => '工具', 'icon' => 'fa-wrench',          'href' => 'index.php?page=tools&tool=price',  'menu' => 'page=tools&tool=price'],
];
?>
<header class="m-appbar" aria-label="頁面標題列">
    <button class="m-appbar-btn" type="button" onclick="toggleMobileMenu()" aria-label="開啟導覽選單" aria-controls="primarySidebar">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <div class="m-appbar-title">
        <span class="m-appbar-kicker">Fengbro AI</span>
        <span class="m-appbar-name"><?php echo htmlspecialchars($pageTitle ?? '鋒兄首頁', ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="m-appbar-actions">
        <button class="m-appbar-btn" type="button" id="mDarkToggle" onclick="toggleDarkMode()" aria-label="切換主題">
            <i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i>
        </button>
        <button class="m-appbar-btn" type="button" aria-label="語音操作"
            onclick="window.FengbroVoiceInput ? window.FengbroVoiceInput.open() : document.getElementById('fengbroVoiceFab')?.click()">
            <i class="fa-solid fa-microphone-lines" aria-hidden="true"></i>
        </button>
    </div>
</header>

<nav class="m-tabbar" aria-label="主要分頁">
    <?php foreach ($mTabs as $t): $on = ($mActive === $t['key']); ?>
        <a class="m-tab <?php echo $on ? 'is-active' : ''; ?>" href="<?php echo $t['href']; ?>"
            data-menu-url="<?php echo htmlspecialchars($t['menu'], ENT_QUOTES, 'UTF-8'); ?>" onclick="handleMenuNav(this)"
            <?php echo $on ? 'aria-current="page"' : ''; ?>>
            <span class="m-tab-icon"><i class="fa-solid <?php echo $t['icon']; ?>" aria-hidden="true"></i></span>
            <span class="m-tab-label"><?php echo $t['label']; ?></span>
        </a>
    <?php endforeach; ?>
    <button class="m-tab <?php echo $mActive === 'more' ? 'is-active' : ''; ?>" type="button" onclick="toggleMobileMenu()" aria-label="更多與全部功能">
        <span class="m-tab-icon"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></span>
        <span class="m-tab-label">更多</span>
    </button>
</nav>

<button class="m-drawer-close" type="button" onclick="closeMobileMenu()" aria-label="關閉導覽選單">
    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
</button>

<script>
    (function () {
        var src = document.getElementById('darkModeToggle'),
            dst = document.getElementById('mDarkToggle');
        if (!src || !dst) return;
        function sync() { dst.innerHTML = src.innerHTML; dst.title = src.title || ''; }
        sync();
        try {
            new MutationObserver(sync).observe(src, { childList: true, subtree: true, attributes: true, attributeFilter: ['title'] });
        } catch (e) { }
    })();
</script>
