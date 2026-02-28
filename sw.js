// Service Worker for 鋒兄AI Laravel MySQL PWA
// v2.0 - 支援背景定期同步、推播通知

const CACHE_NAME = 'fengxiong-ai-v2';
const OFFLINE_URL = 'index.php?page=dashboard';

// ── 安裝：快取核心頁面 ──────────────────────────────────────────────────────
self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll([
                OFFLINE_URL,
                'assets/css/style.css',
                'assets/js/main.js'
            ]).catch(function () { });
        })
    );
});

// ── 啟動：接管所有頁面 ─────────────────────────────────────────────────────
self.addEventListener('activate', function (event) {
    event.waitUntil(
        Promise.all([
            self.clients.claim(),
            // 清除舊快取
            caches.keys().then(function (keys) {
                return Promise.all(
                    keys.filter(function (k) { return k !== CACHE_NAME; })
                        .map(function (k) { return caches.delete(k); })
                );
            })
        ])
    );
});

// ── Fetch：網路優先，離線時走快取 ─────────────────────────────────────────
self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') return;
    if (!event.request.url.startsWith(self.location.origin)) return;

    // 跳過 uploads/ 媒體檔案（音樂、影片、圖片等），讓瀏覽器直接處理 Range Request
    var url = event.request.url;
    if (url.includes('/uploads/')) return;
    // 跳過 Range Request（音樂串流）
    if (event.request.headers.get('range')) return;

    event.respondWith(
        fetch(event.request).then(function (response) {
            if (response && response.status === 200) {
                var clone = response.clone();
                caches.open(CACHE_NAME).then(function (cache) {
                    cache.put(event.request, clone);
                });
            }
            return response;
        }).catch(function () {
            return caches.match(event.request).then(function (cached) {
                return cached || caches.match(OFFLINE_URL);
            });
        })
    );
});

// ── Periodic Background Sync：定期背景執行 ────────────────────────────────
// 手機安裝 PWA 後，即使 App 關閉，瀏覽器也會定期喚醒此 Service Worker
// 支援平台：Chrome Android 80+
self.addEventListener('periodicsync', function (event) {
    if (event.tag === 'fengxiong-heartbeat') {
        event.waitUntil(handlePeriodicSync());
    }
});

async function handlePeriodicSync() {
    try {
        // 背景靜默更新快取（保持資料最新）
        var response = await fetch(OFFLINE_URL + '&sw_bg=1', { credentials: 'include' });
        if (response.ok) {
            var cache = await caches.open(CACHE_NAME);
            await cache.put(OFFLINE_URL, response);
        }
    } catch (e) {
        // 離線時靜默忽略
    }
}

// ── Background Sync：網路恢復後自動補送 ──────────────────────────────────
self.addEventListener('sync', function (event) {
    if (event.tag === 'fengxiong-sync') {
        event.waitUntil(handleBackgroundSync());
    }
});

async function handleBackgroundSync() {
    try {
        await fetch(OFFLINE_URL + '&sw_sync=1', { credentials: 'include' });
    } catch (e) { }
}

// ── Push Notifications：接收伺服器推播 ───────────────────────────────────
self.addEventListener('push', function (event) {
    var data = {};
    if (event.data) {
        try { data = event.data.json(); } catch (e) { data = { title: event.data.text() }; }
    }

    var title = data.title || '鋒兄AI Laravel MySQL';
    var options = {
        body: data.body || '有新的通知',
        icon: data.icon || '/icon-192x192.png',
        badge: '/icon-96x96.png',
        tag: data.tag || 'fengxiong-push',
        vibrate: [200, 100, 200],
        requireInteraction: data.requireInteraction || false,
        data: { url: data.url || 'index.php?page=dashboard' }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// ── 通知點擊：開啟或聚焦 App ─────────────────────────────────────────────
self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var targetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : 'index.php?page=dashboard';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            // 若 App 已開啟，直接聚焦
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url.includes('index.php') && 'focus' in client) {
                    return client.focus();
                }
            }
            // App 未開啟，自動開啟
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

// ── 通知關閉事件 ──────────────────────────────────────────────────────────
self.addEventListener('notificationclose', function (event) {
    // 可在此記錄通知關閉事件
});
