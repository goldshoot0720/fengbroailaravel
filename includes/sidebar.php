<?php
$menuGroups = [
    'overview' => ['label' => '總覽', 'icon' => 'fa-compass', 'items' => ['home' => ['label' => '鋒兄首頁', 'icon' => 'fa-house']]],
    'personal' => ['label' => '生活管理', 'icon' => 'fa-calendar-check', 'items' => ['subscription' => ['label' => '鋒兄訂閱', 'icon' => 'fa-credit-card'], 'trialpurchase' => ['label' => '鋒兄試用/首購', 'icon' => 'fa-flask'], 'reinstall' => ['label' => '鋒兄重灌', 'icon' => 'fa-laptop'], 'quota' => ['label' => '鋒兄額度', 'hint' => '剩餘次數', 'icon' => 'fa-gauge-high'], 'shoppinglist' => ['label' => '鋒兄購物清單', 'icon' => 'fa-cart-shopping'], 'food' => ['label' => '鋒兄食品', 'hint' => '商品庫存', 'icon' => 'fa-boxes-stacked'], 'bank' => ['label' => '鋒兄銀行', 'hint' => '電子票證', 'icon' => 'fa-building-columns'], 'routine' => ['label' => '鋒兄例行', 'icon' => 'fa-clock-rotate-left']]],
    'knowledge' => ['label' => '知識管理', 'icon' => 'fa-book-open', 'items' => ['notes' => ['label' => '鋒兄筆記', 'icon' => 'fa-note-sticky'], 'favorites' => ['label' => '鋒兄常用', 'icon' => 'fa-star'], 'documents' => ['label' => '鋒兄文件', 'icon' => 'fa-folder-open']]],
    'media' => ['label' => '媒體庫', 'icon' => 'fa-photo-film', 'items' => ['images' => ['label' => '鋒兄圖片', 'icon' => 'fa-image'], 'videos' => ['label' => '鋒兄影片', 'icon' => 'fa-video'], 'music' => ['label' => '鋒兄音樂', 'icon' => 'fa-music'], 'podcast' => ['label' => '鋒兄播客', 'icon' => 'fa-podcast']]],
    'tools-group' => ['label' => '鋒兄工具', 'icon' => 'fa-wrench', 'items' => [
        'tool:price' => ['label' => '鋒兄比價', 'icon' => 'fa-tags', 'page' => 'tools', 'tool' => 'price'],
        'tool:manual' => ['label' => '手動價格', 'icon' => 'fa-clipboard-list', 'page' => 'tools', 'tool' => 'manual'],
        'tool:phone' => ['label' => '手機比價', 'icon' => 'fa-mobile-screen-button', 'page' => 'tools', 'tool' => 'phone'],
        'tool:tube' => ['label' => '鋒兄tube', 'icon' => 'fa-brands fa-youtube', 'page' => 'tools', 'tool' => 'tube'],
        'tool:finance' => ['label' => '鋒兄金融', 'icon' => 'fa-chart-line', 'page' => 'tools', 'tool' => 'finance'],
        'tool:news' => ['label' => '鋒兄新聞', 'icon' => 'fa-newspaper', 'page' => 'tools', 'tool' => 'news'],
        'tool:image-convert' => ['label' => 'PNG / JPEG', 'icon' => 'fa-image', 'page' => 'tools', 'tool' => 'image-convert'],
        'tool:image-voice' => ['label' => '圖+語音', 'icon' => 'fa-clapperboard', 'page' => 'tools', 'tool' => 'image-voice'],
        'tool:video-merge' => ['label' => '影片合併', 'icon' => 'fa-film', 'page' => 'tools', 'tool' => 'video-merge'],
        'tool:yt-bili' => ['label' => 'YT / B站', 'icon' => 'fa-brands fa-youtube', 'page' => 'tools', 'tool' => 'yt-bili'],
    ]],
    'system' => ['label' => '系統', 'icon' => 'fa-sliders', 'items' => ['settings' => ['label' => '鋒兄設定', 'icon' => 'fa-gear'], 'about' => ['label' => '鋒兄關於', 'icon' => 'fa-circle-info']]],
];
$currentPage = $_GET['page'] ?? 'home';
$currentTool = (string) ($_GET['tool'] ?? '');
function fengbroSidebarIsActive($key, $item): bool
{
    global $currentPage, $currentTool;
    $itemPage = $item['page'] ?? ($key === 'home' ? 'home' : $key);
    if (str_starts_with((string) $key, 'tool:')) {
        return $currentPage === 'tools' && $currentTool === ($item['tool'] ?? '');
    }
    return $currentPage === $itemPage;
}
?>
<button class="mobile-menu-btn" type="button" onclick="toggleMobileMenu()" aria-label="開啟導覽選單" aria-controls="primarySidebar" aria-expanded="false"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
<div class="sidebar-overlay" onclick="closeMobileMenu()" aria-hidden="true"></div>
<nav class="sidebar" id="primarySidebar" aria-label="主要導覽">
<div class="sidebar-header"><a class="sidebar-home-link" href="index.php?page=home" data-menu-url="page=home" onclick="handleMenuNav(this)" aria-label="前往鋒兄首頁"><span class="sidebar-brand-mark" aria-hidden="true"><i class="fa-solid fa-wave-square"></i></span><span class="sidebar-brand-copy"><span class="sidebar-kicker">Personal Ops System</span><h2><i class="fa-solid fa-dragon" aria-hidden="true"></i> Fengbro AI</h2><p>私人作業中樞</p></span></a><button id="darkModeToggle" class="dark-mode-btn" type="button" onclick="toggleDarkMode()" aria-label="切換深色模式" title="切換深色模式"><i class="fa-solid fa-moon" aria-hidden="true"></i></button></div>
<div class="sidebar-groups">
<?php foreach ($menuGroups as $groupKey => $group): $groupActive = false; foreach ($group['items'] as $k => $it) { if (fengbroSidebarIsActive($k, $it)) { $groupActive = true; break; } } ?>
<section class="menu-group <?php echo $groupActive ? 'is-open' : ''; ?>" data-menu-group="<?php echo $groupKey; ?>"><button class="menu-group-toggle" type="button" aria-expanded="<?php echo $groupActive ? 'true' : 'false'; ?>" onclick="toggleMenuGroup(this)"><span><i class="fa-solid <?php echo $group['icon']; ?>" aria-hidden="true"></i><?php echo $group['label']; ?></span><i class="fa-solid fa-chevron-down menu-group-chevron" aria-hidden="true"></i></button><ul class="menu">
<?php foreach ($group['items'] as $key => $item):
    $itemPage = $item['page'] ?? ($key === 'home' ? 'home' : $key);
    $itemTool = isset($item['tool']) ? '&tool=' . rawurlencode($item['tool']) : '';
    $itemHref = 'index.php?page=' . rawurlencode($itemPage) . $itemTool;
    $itemUrlData = 'page=' . rawurlencode($itemPage) . $itemTool;
    $itemActive = fengbroSidebarIsActive($key, $item);
    $voiceMenu = str_starts_with((string) $key, 'tool:') ? ($item['tool'] ?? '') : $key;
    ?><li class="menu-item <?php echo $itemActive ? 'active' : ''; ?>"><a href="<?php echo $itemHref; ?>" <?php echo $itemActive ? 'aria-current="page"' : ''; ?> data-voice-menu="<?php echo htmlspecialchars($voiceMenu, ENT_QUOTES, 'UTF-8'); ?>" data-voice-label="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>" data-menu-url="<?php echo htmlspecialchars($itemUrlData, ENT_QUOTES, 'UTF-8'); ?>" onclick="handleMenuNav(this)"><i class="fa-solid <?php echo $item['icon']; ?>" aria-hidden="true"></i><span class="menu-label"><span class="menu-label-main"><?php echo $item['label']; ?></span><?php if (!empty($item['hint'])): ?><span class="menu-label-hint"><?php echo $item['hint']; ?></span><?php endif; ?></span></a></li><?php endforeach; ?>
</ul></section><?php endforeach; ?>
</div>
<div class="sidebar-quick-actions"><button type="button" class="sidebar-voice-btn" onclick="window.FengbroVoiceInput ? window.FengbroVoiceInput.open() : document.getElementById('fengbroVoiceFab')?.click()"><i class="fa-solid fa-microphone-lines" aria-hidden="true"></i><span>語音操作</span></button><button type="button" class="sidebar-privacy-btn" id="privacyToggle" onclick="togglePrivateValues()" aria-pressed="false"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i><span>顯示私密資料</span></button></div></nav>
<script>
function toggleMobileMenu(){const s=document.querySelector('.sidebar'),o=document.querySelector('.sidebar-overlay'),b=document.querySelector('.mobile-menu-btn');const open=!s.classList.contains('open');s.classList.toggle('open',open);o.classList.toggle('show',open);b.setAttribute('aria-expanded',String(open));}
function closeMobileMenu(){document.querySelector('.sidebar')?.classList.remove('open');document.querySelector('.sidebar-overlay')?.classList.remove('show');document.querySelector('.mobile-menu-btn')?.setAttribute('aria-expanded','false');}
function toggleMenuGroup(button){const w=window.innerWidth;if(w>1024)return;const group=button.closest('.menu-group');if(w>768){const open=!group.classList.contains('is-hover');document.querySelectorAll('.sidebar .menu-group.is-hover').forEach(g=>{if(g!==group){g.classList.remove('is-hover');g.querySelector('.menu-group-toggle')?.setAttribute('aria-expanded','false');}});group.classList.toggle('is-hover',open);button.setAttribute('aria-expanded',String(open));return;}const open=!group.classList.contains('is-open');group.classList.toggle('is-open',open);button.setAttribute('aria-expanded',String(open));localStorage.setItem('fengbro_menu_'+group.dataset.menuGroup,open?'1':'0');}
document.querySelectorAll('.menu-group:not(.is-open)').forEach(group=>{if(localStorage.getItem('fengbro_menu_'+group.dataset.menuGroup)==='1'){group.classList.add('is-open');group.querySelector('.menu-group-toggle').setAttribute('aria-expanded','true');}});
function rememberLastMenu(){const page=document.body.dataset.page||'';if(!page||page==='home')return;let tool=document.body.dataset.tool||'';if(!/^[a-z0-9-]+$/i.test(tool))tool='';const url=(tool?'page='+page+'&tool='+encodeURIComponent(tool):'page='+page);localStorage.setItem('fengbro_last_menu',url);}
function restoreLastMenu(){const page=document.body.dataset.page||'';if(page&&page!=='home')return;let url=localStorage.getItem('fengbro_last_menu');if(!url||url==='page=home')return;if(url==='page=dashboard'){localStorage.removeItem('fengbro_last_menu');return;}const target='index.php?'+url;const current=window.location.href.split('#')[0];if(current.indexOf('index.php?'+url)>-1||current.indexOf('/?'+url)>-1||current.indexOf('?'+url)>-1)return;window.location.replace(target);}
function handleMenuNav(link){closeMobileMenu();const m=link.dataset.menuUrl;if(!m)return;if(m==='page=home'){localStorage.removeItem('fengbro_last_menu');}else{localStorage.setItem('fengbro_last_menu',m);}recordMenuUsage(fengbroModuleIdFromUrl(m));}
restoreLastMenu();
rememberLastMenu();
function syncTopbarVar(){const s=document.querySelector('.sidebar');if(!s)return;document.documentElement.style.setProperty('--topbar-h',Math.round(s.getBoundingClientRect().height)+'px');}
if(window.innerWidth>768){syncTopbarVar();window.addEventListener('resize',syncTopbarVar);document.querySelectorAll('.sidebar .menu-group').forEach(g=>{g.addEventListener('mouseenter',()=>g.classList.add('is-hover'));g.addEventListener('mouseleave',()=>g.classList.remove('is-hover'));});}
document.addEventListener('click',function(e){if(window.innerWidth<769||window.innerWidth>1024)return;if(e.target.closest('.sidebar .menu-group'))return;document.querySelectorAll('.sidebar .menu-group.is-hover').forEach(g=>{g.classList.remove('is-hover');g.querySelector('.menu-group-toggle')?.setAttribute('aria-expanded','false');});});
function togglePrivateValues(){const show=!document.body.classList.contains('show-private-values');document.body.classList.toggle('show-private-values',show);const b=document.getElementById('privacyToggle');b.setAttribute('aria-pressed',String(show));b.querySelector('span').textContent=show?'隱藏私密資料':'顯示私密資料';b.querySelector('i').className=show?'fa-solid fa-eye':'fa-solid fa-eye-slash';}

// ── 網站統計（對齊 Appwrite /api/site-visit 與 /api/menu-usage）──────────────
const SITE_VISIT_SESSION_KEY = 'fengbro-site-visit-logged';
function fengbroModuleIdFromUrl(url){const m=String(url||'').match(/page=([a-z0-9-]+)/i);if(!m)return '';const page=m[1].toLowerCase();if(page==='home')return 'home';const tool=String(url||'').match(/tool=([a-z0-9-]+)/i);if(page==='tools'&&tool)return 'tool:'+tool[1].toLowerCase();return page;}
function recordSiteVisit(){try{if(sessionStorage.getItem(SITE_VISIT_SESSION_KEY))return;sessionStorage.setItem(SITE_VISIT_SESSION_KEY,'1');}catch(e){}fetch('stats_api.php?action=site_visit',{method:'POST'}).catch(function(){});}
function recordMenuUsage(moduleId){if(!moduleId||moduleId==='home')return;fetch('stats_api.php?action=menu_usage',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({moduleId:moduleId})}).catch(function(){});}
recordSiteVisit();
</script>
