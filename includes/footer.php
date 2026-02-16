    </div>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/inline-edit.js"></script>

    <!-- 註冊 Service Worker (PWA 手機通知必要) -->
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').catch(function(){});
    }
    </script>

    <!-- PWA 安裝提示 (iOS + Android) -->
    <div id="pwaInstallPrompt" style="display:none; position:fixed; bottom:0; left:0; right:0; z-index:99998; padding:16px; background:linear-gradient(135deg,#2c3e50,#3498db); color:#fff; box-shadow:0 -2px 16px rgba(0,0,0,0.3); animation:slideUp 0.4s ease;">
        <div style="max-width:600px; margin:0 auto; display:flex; align-items:center; gap:14px;">
            <i class="fa-solid fa-mobile-screen-button" style="font-size:2rem;"></i>
            <div style="flex:1;">
                <strong style="font-size:1rem;">安裝鋒兄AI到主畫面</strong>
                <p id="pwaInstallText" style="font-size:0.82rem; margin-top:4px; opacity:0.9;"></p>
            </div>
            <button id="pwaInstallBtn" onclick="pwaInstallAction()" style="background:#fff; color:#2c3e50; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; white-space:nowrap;">安裝</button>
            <span onclick="dismissPwaPrompt()" style="cursor:pointer; font-size:1.3rem; padding:4px 8px;">&times;</span>
        </div>
    </div>
    <script>
    (function() {
        if (localStorage.getItem('pwa_prompt_dismissed')) return;

        // 判斷是否已經是 PWA 模式
        var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
        if (isStandalone) return;

        var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
        var isAndroid = /android/i.test(navigator.userAgent);
        if (!isIos && !isAndroid) return;

        var prompt = document.getElementById('pwaInstallPrompt');
        var text = document.getElementById('pwaInstallText');
        var btn = document.getElementById('pwaInstallBtn');

        if (isIos) {
            text.innerHTML = '點擊 Safari 底部 <i class="fa-solid fa-arrow-up-from-bracket"></i> 分享按鈕，然後選「加入主畫面」即可收到通知';
            btn.textContent = '知道了';
        } else {
            text.textContent = '安裝到主畫面可收到訂閱到期推播通知';
        }

        // 延遲 2 秒顯示
        setTimeout(function() { prompt.style.display = 'block'; }, 2000);

        // Android: 攔截安裝事件
        window._deferredPwaPrompt = null;
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            window._deferredPwaPrompt = e;
        });
    })();

    function pwaInstallAction() {
        if (window._deferredPwaPrompt) {
            window._deferredPwaPrompt.prompt();
            window._deferredPwaPrompt.userChoice.then(function() {
                window._deferredPwaPrompt = null;
                dismissPwaPrompt();
            });
        } else {
            dismissPwaPrompt();
        }
    }

    function dismissPwaPrompt() {
        document.getElementById('pwaInstallPrompt').style.display = 'none';
        localStorage.setItem('pwa_prompt_dismissed', '1');
    }
    </script>
    <style>
    @keyframes slideUp { from { opacity:0; transform:translateY(60px); } to { opacity:1; transform:translateY(0); } }
    </style>

    <?php
    // 訂閱到期通知 (3天內)
    $notifPdo = getConnection();
    $expiringSubscriptions = $notifPdo->query("SELECT name, nextdate FROM subscription WHERE `continue` = 1 AND nextdate IS NOT NULL AND nextdate <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND nextdate >= CURDATE() ORDER BY nextdate ASC")->fetchAll();
    ?>
    <?php if (!empty($expiringSubscriptions)): ?>
    <!-- 頁面內通知橫幅 -->
    <div id="subExpiringBanner" style="display:none; position:fixed; top:0; left:0; right:0; z-index:99999; padding:10px 15px; background:rgba(0,0,0,0.15);">
        <div style="max-width:600px; margin:0 auto; display:flex; flex-direction:column; gap:8px;" id="subExpiringList"></div>
    </div>
    <script>
    (function() {
        const expiring = <?php echo json_encode(array_map(function($sub) {
            $days = round((strtotime($sub['nextdate']) - strtotime(date('Y-m-d'))) / 86400);
            $daysText = $days == 0 ? '今天到期' : ($days == 1 ? '明天到期' : $days . '天後到期');
            return ['name' => $sub['name'], 'date' => date('m/d', strtotime($sub['nextdate'])), 'daysText' => $daysText];
        }, $expiringSubscriptions), JSON_UNESCAPED_UNICODE); ?>;

        const today = new Date().toISOString().slice(0, 10);
        const notifiedKey = 'sub_notified_' + today;
        if (sessionStorage.getItem(notifiedKey)) return;

        // 頁面內橫幅通知（保底，電腦手機都會顯示）
        function showBannerNotifications() {
            const banner = document.getElementById('subExpiringBanner');
            const list = document.getElementById('subExpiringList');
            expiring.forEach(function(sub, i) {
                const item = document.createElement('div');
                item.style.cssText = 'background:#e74c3c; color:#fff; padding:12px 16px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 12px rgba(0,0,0,0.2); font-size:0.9rem; animation:slideDown 0.3s ease ' + (i * 0.1) + 's both;';
                item.innerHTML = '<span><i class="fa-solid fa-bell" style="margin-right:8px;"></i><strong>' + sub.name + '</strong> — ' + sub.date + '（' + sub.daysText + '）</span>' +
                    '<span onclick="this.parentElement.remove(); if(!document.getElementById(\'subExpiringList\').children.length) document.getElementById(\'subExpiringBanner\').style.display=\'none\';" style="cursor:pointer; font-size:1.3rem; padding:2px 6px; min-width:24px; text-align:center;">&times;</span>';
                list.appendChild(item);
            });
            banner.style.display = 'block';

            // 8秒後自動關閉
            setTimeout(function() {
                banner.style.transition = 'opacity 0.5s';
                banner.style.opacity = '0';
                setTimeout(function() { banner.style.display = 'none'; }, 500);
            }, 8000);
        }

        // 透過 Service Worker 發送通知（手機＋電腦都支援）
        function showSwNotifications(reg) {
            expiring.forEach(function(sub, i) {
                setTimeout(function() {
                    reg.showNotification('訂閱到期提醒', {
                        body: sub.name + ' - ' + sub.date + '（' + sub.daysText + '）',
                        icon: 'favicon.ico',
                        tag: 'sub-expiring-' + i,
                        vibrate: [200, 100, 200],
                        requireInteraction: false
                    });
                }, i * 500);
            });
        }

        // 直接用 Notification API（電腦瀏覽器）
        function showDirectNotifications() {
            expiring.forEach(function(sub, i) {
                setTimeout(function() {
                    new Notification('訂閱到期提醒', {
                        body: sub.name + ' - ' + sub.date + '（' + sub.daysText + '）',
                        icon: 'favicon.ico',
                        tag: 'sub-expiring-' + i
                    });
                }, i * 500);
            });
        }

        // 顯示頁面橫幅
        showBannerNotifications();
        sessionStorage.setItem(notifiedKey, '1');

        // 嘗試系統通知
        if ('Notification' in window) {
            function triggerNotifications() {
                // 優先用 Service Worker（手機必須用這種方式）
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.ready.then(function(reg) {
                        showSwNotifications(reg);
                    }).catch(function() {
                        showDirectNotifications();
                    });
                } else {
                    showDirectNotifications();
                }
            }

            if (Notification.permission === 'granted') {
                triggerNotifications();
            } else if (Notification.permission !== 'denied') {
                Notification.requestPermission().then(function(p) {
                    if (p === 'granted') triggerNotifications();
                });
            }
        }
    })();
    </script>
    <style>
    @keyframes slideDown { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
    </style>
    <?php endif; ?>
</body>
</html>
