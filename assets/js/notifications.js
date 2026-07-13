/**
 * Fengbro browser / Web Push client helpers.
 * Used by footer banners, dashboard alerts, and push subscription registration.
 */
(function (global) {
    'use strict';

    var DEFAULT_ICON = 'icon-192x192.png';
    var FAVICON = 'favicon.ico';

    function todayKey() {
        return new Date().toISOString().slice(0, 10);
    }

    function supportsNotification() {
        return 'Notification' in global;
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = global.atob(base64);
        var output = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; i++) {
            output[i] = rawData.charCodeAt(i);
        }
        return output;
    }

    function showSystemNotification(title, options) {
        options = options || {};
        if (!supportsNotification() || Notification.permission !== 'granted') {
            return Promise.resolve(false);
        }

        if ('serviceWorker' in navigator) {
            return navigator.serviceWorker.ready
                .then(function (reg) {
                    return reg.showNotification(title, options);
                })
                .then(function () { return true; })
                .catch(function () {
                    try {
                        new Notification(title, options);
                        return true;
                    } catch (e) {
                        return false;
                    }
                });
        }

        try {
            new Notification(title, options);
            return Promise.resolve(true);
        } catch (e) {
            return Promise.resolve(false);
        }
    }

    /**
     * Page banner for subscription expiry (once per session/day).
     * @param {Array<{name:string,date:string,daysText:string}>} items
     */
    function showExpiringBanner(items) {
        if (!items || !items.length) return;

        var notifiedKey = 'sub_notified_' + todayKey();
        if (sessionStorage.getItem(notifiedKey)) return;
        sessionStorage.setItem(notifiedKey, '1');

        var banner = document.getElementById('subExpiringBanner');
        var list = document.getElementById('subExpiringList');
        if (!banner || !list) return;

        items.forEach(function (sub, i) {
            var item = document.createElement('div');
            item.className = 'notif-banner-item';
            item.style.cssText =
                'background:#e74c3c;color:#fff;padding:12px 16px;border-radius:8px;' +
                'display:flex;justify-content:space-between;align-items:center;' +
                'box-shadow:0 2px 12px rgba(0,0,0,0.2);font-size:0.9rem;' +
                'animation:slideDown 0.3s ease ' + (i * 0.1) + 's both;';
            item.innerHTML =
                '<span><i class="fa-solid fa-bell" style="margin-right:8px;"></i><strong>' +
                escapeHtml(sub.name) + '</strong> — ' + escapeHtml(sub.date) +
                '（' + escapeHtml(sub.daysText) + '）</span>' +
                '<span class="notif-banner-close" style="cursor:pointer;font-size:1.3rem;padding:2px 6px;min-width:24px;text-align:center;">&times;</span>';

            item.querySelector('.notif-banner-close').addEventListener('click', function () {
                item.remove();
                if (!list.children.length) {
                    banner.style.display = 'none';
                }
            });
            list.appendChild(item);
        });

        banner.style.display = 'block';
        setTimeout(function () {
            banner.style.transition = 'opacity 0.5s';
            banner.style.opacity = '0';
            setTimeout(function () {
                banner.style.display = 'none';
            }, 500);
        }, 8000);
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * System notifications for expiring subscriptions (once per session/day via banner key).
     */
    function showExpiringSystemNotifications(items) {
        if (!items || !items.length || !supportsNotification()) return;

        function fire() {
            items.forEach(function (sub, i) {
                setTimeout(function () {
                    showSystemNotification('訂閱到期提醒', {
                        body: sub.name + ' - ' + sub.date + '（' + sub.daysText + '）',
                        icon: FAVICON,
                        tag: 'sub-expiring-' + i,
                        vibrate: [200, 100, 200],
                        requireInteraction: false
                    });
                }, i * 500);
            });
        }

        if (Notification.permission === 'granted') {
            fire();
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(function (p) {
                if (p === 'granted') fire();
            });
        }
    }

    /**
     * Global page load: banner + optional system notifications for 3-day subscriptions.
     */
    function initExpiringSubscriptionAlerts(items) {
        if (!items || !items.length) return;
        showExpiringBanner(items);
        showExpiringSystemNotifications(items);
    }

    /**
     * Dashboard multi-type alerts (once per item per day via localStorage).
     */
    function sendDashboardNotifications(alerts) {
        if (!supportsNotification() || Notification.permission !== 'granted') return;
        alerts = alerts || {};

        var storageKey = 'fengbro.dashboard.notifications.' + todayKey();
        var sent = {};
        try {
            sent = JSON.parse(localStorage.getItem(storageKey) || '{}') || {};
        } catch (e) {
            sent = {};
        }

        function notifyOnce(key, title, body) {
            if (sent[key]) return;
            sent[key] = true;
            try {
                new Notification(title, { body: body, icon: DEFAULT_ICON });
            } catch (e) { /* ignore */ }
        }

        (alerts.subscriptions3 || []).slice(0, 3).forEach(function (item, index) {
            notifyOnce('sub-' + index + '-' + item.name, '訂閱 3 天內到期', item.name + '：' + item.date);
        });
        (alerts.foods7 || []).slice(0, 3).forEach(function (item, index) {
            notifyOnce('food-' + index + '-' + item.name, '食品 7 天內到期', item.name + '：' + item.date);
        });
        (alerts.expiredFoods || []).slice(0, 3).forEach(function (item, index) {
            notifyOnce('expired-' + index + '-' + item.name, '食品已過期', item.name + '：' + item.date);
        });

        localStorage.setItem(storageKey, JSON.stringify(sent));
    }

    function requestDashboardNotifications(alerts) {
        if (!supportsNotification()) {
            alert('此瀏覽器不支援通知。');
            return Promise.resolve(false);
        }
        return Notification.requestPermission().then(function (permission) {
            if (permission === 'granted') {
                sendDashboardNotifications(alerts);
                alert('提醒已啟用。');
                return true;
            }
            return false;
        });
    }

    /**
     * Register Web Push after notification permission is granted.
     */
    function registerPush(vapidPublicKey) {
        if (!vapidPublicKey) return Promise.resolve(false);
        if (!('serviceWorker' in navigator) || !('PushManager' in global)) {
            return Promise.resolve(false);
        }
        if (!supportsNotification() || Notification.permission !== 'granted') {
            return Promise.resolve(false);
        }

        return navigator.serviceWorker.ready
            .then(function (reg) {
                return reg.pushManager.getSubscription().then(function (existing) {
                    if (existing) return existing;
                    return reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
                    });
                });
            })
            .then(function (sub) {
                if (!sub) return false;
                return fetch('push_subscribe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(sub)
                }).then(function () { return true; });
            })
            .catch(function () {
                return false;
            });
    }

    /**
     * Ensure push subscription when permission is already granted or just obtained.
     */
    function ensurePushRegistration(vapidPublicKey) {
        if (!vapidPublicKey || !supportsNotification()) return;

        if (Notification.permission === 'granted') {
            registerPush(vapidPublicKey);
            return;
        }
        if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(function (p) {
                if (p === 'granted') registerPush(vapidPublicKey);
            });
        }
    }

    global.FengbroNotifications = {
        todayKey: todayKey,
        supportsNotification: supportsNotification,
        showSystemNotification: showSystemNotification,
        showExpiringBanner: showExpiringBanner,
        showExpiringSystemNotifications: showExpiringSystemNotifications,
        initExpiringSubscriptionAlerts: initExpiringSubscriptionAlerts,
        sendDashboardNotifications: sendDashboardNotifications,
        requestDashboardNotifications: requestDashboardNotifications,
        registerPush: registerPush,
        ensurePushRegistration: ensurePushRegistration
    };
})(typeof window !== 'undefined' ? window : this);
