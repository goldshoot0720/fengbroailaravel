/**
 * 全站使用者體驗加強（不需改動各頁程式即可生效）：
 *
 *  1. 頂部進度條：同源 fetch / XHR 進行中超過 120ms 就顯示，讓使用者知道「有在跑」。
 *  2. 防重複送出：點按鈕後 400ms 內發出的寫入請求，會把該按鈕暫時停用＋轉圈，直到請求結束。
 *  3. 捲動位置記憶：各頁新增／編輯後常用 location.reload()，重新整理後自動回到原本位置。
 *  4. 操作結果提示：寫入成功（新增／更新／刪除／復原）後，即使頁面重新整理也會顯示 toast。
 *  5. alert() 改為非阻斷 toast：不再卡住畫面；若 toast 還沒看完頁面就重新整理，會在新頁面補顯示。
 *  6. 離線／恢復連線提示。
 *
 * 全部包在 try/catch 內，任何一項失敗都不影響原本功能。
 */
(function () {
    'use strict';

    if (window.__fengbroUxBoost) return;
    window.__fengbroUxBoost = true;

    var FLASH_KEY = 'fengbroUxFlash';
    var SCROLL_KEY = 'fengbroUxScroll';

    function ssGet(key) {
        try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch (_) { return null; }
    }
    function ssSet(key, value) {
        try { sessionStorage.setItem(key, JSON.stringify(value)); } catch (_) { /* ignore */ }
    }
    function ssDel(key) {
        try { sessionStorage.removeItem(key); } catch (_) { /* ignore */ }
    }

    /* ---------------------------------------------------------
     * 樣式
     * --------------------------------------------------------- */
    var css = '' +
        '#fengbroUxBar{position:fixed;top:0;left:0;height:3px;width:0;z-index:100001;pointer-events:none;' +
        'background:var(--primary-color,var(--accent,#c96442));box-shadow:0 0 8px var(--primary-color,var(--accent,#c96442));' +
        'opacity:0;transition:width .25s ease,opacity .3s ease;}' +
        '#fengbroUxBar.on{opacity:1;}' +
        '#fengbroUxToasts{position:fixed;left:50%;bottom:calc(env(safe-area-inset-bottom,0px) + 84px);transform:translateX(-50%);' +
        'z-index:100002;display:flex;flex-direction:column;align-items:center;gap:8px;width:min(92vw,460px);pointer-events:none;}' +
        '@media (min-width:900px){#fengbroUxToasts{bottom:28px;}}' +
        '.fengbro-ux-toast{pointer-events:auto;display:flex;align-items:flex-start;gap:10px;width:100%;box-sizing:border-box;' +
        'padding:12px 14px;border-radius:12px;font-size:.95rem;line-height:1.5;white-space:pre-wrap;word-break:break-word;' +
        'background:var(--card-bg,#fff);color:var(--text-color,#1f1e1d);border:1px solid var(--border-color,rgba(20,19,18,.12));' +
        'box-shadow:0 10px 30px rgba(0,0,0,.18);opacity:0;transform:translateY(8px);transition:opacity .2s ease,transform .2s ease;cursor:pointer;}' +
        '.fengbro-ux-toast.show{opacity:1;transform:translateY(0);}' +
        '.fengbro-ux-toast .ux-ico{flex:0 0 auto;font-weight:700;}' +
        '.fengbro-ux-toast.ok .ux-ico{color:#2e7d32;}' +
        '.fengbro-ux-toast.err .ux-ico{color:#c62828;}' +
        '.fengbro-ux-toast.info .ux-ico{color:var(--primary-color,#c96442);}' +
        '.fengbro-ux-toast .ux-msg{flex:1 1 auto;max-height:40vh;overflow:auto;}' +
        '.fengbro-ux-toast .ux-act{flex:0 0 auto;align-self:center;border:0;border-radius:8px;padding:4px 10px;font:inherit;font-weight:700;' +
        'cursor:pointer;background:transparent;color:var(--primary-color,var(--accent,#c96442));}' +
        '.fengbro-ux-toast .ux-act:hover{background:rgba(201,100,66,.12);}' +
        '.fengbro-ux-busy{position:relative;pointer-events:none;opacity:.7;}' +
        '.fengbro-ux-busy::after{content:"";display:inline-block;width:.9em;height:.9em;margin-left:.45em;vertical-align:-.12em;' +
        'border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:fengbroUxSpin .7s linear infinite;}' +
        '@keyframes fengbroUxSpin{to{transform:rotate(360deg);}}' +
        '@media (prefers-reduced-motion:reduce){#fengbroUxBar,.fengbro-ux-toast{transition:none;}.fengbro-ux-busy::after{animation-duration:2s;}}';

    function injectStyle() {
        if (document.getElementById('fengbroUxStyle')) return;
        var style = document.createElement('style');
        style.id = 'fengbroUxStyle';
        style.textContent = css;
        (document.head || document.documentElement).appendChild(style);
    }
    injectStyle();

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    /* ---------------------------------------------------------
     * Toast
     * --------------------------------------------------------- */
    var liveToasts = [];

    function toastHost() {
        var host = document.getElementById('fengbroUxToasts');
        if (!host && document.body) {
            host = document.createElement('div');
            host.id = 'fengbroUxToasts';
            host.setAttribute('role', 'status');
            host.setAttribute('aria-live', 'polite');
            document.body.appendChild(host);
        }
        return host;
    }

    /**
     * toast(訊息, 'ok' | 'err' | 'info', 毫秒, { label: '復原', onClick: fn })
     * 第四個參數可加一顆動作按鈕（例如刪除後的「復原」）。回傳 { close }。
     */
    function toast(message, type, duration, action) {
        message = message == null ? '' : String(message);
        type = type || 'info';
        if (!document.body) {
            onReady(function () { toast(message, type, duration, action); });
            return { close: function () {} };
        }
        var host = toastHost();
        if (!host) return { close: function () {} };
        if (!duration) {
            duration = Math.min(9000, Math.max(2600, 1800 + message.length * 60));
        }

        var el = document.createElement('div');
        el.className = 'fengbro-ux-toast ' + type;
        var ico = document.createElement('span');
        ico.className = 'ux-ico';
        ico.textContent = type === 'ok' ? '✓' : (type === 'err' ? '!' : 'i');
        var msg = document.createElement('span');
        msg.className = 'ux-msg';
        msg.textContent = message;
        el.appendChild(ico);
        el.appendChild(msg);
        if (action && action.label && typeof action.onClick === 'function') {
            var act = document.createElement('button');
            act.type = 'button';
            act.className = 'ux-act';
            act.textContent = action.label;
            act.addEventListener('click', function (e) {
                e.stopPropagation();
                close();
                try { action.onClick(); } catch (_) { /* ignore */ }
            });
            el.appendChild(act);
        }
        host.appendChild(el);

        var record = { message: message, type: type, until: Date.now() + duration };
        liveToasts.push(record);

        function close() {
            el.classList.remove('show');
            var i = liveToasts.indexOf(record);
            if (i >= 0) liveToasts.splice(i, 1);
            setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 250);
        }
        el.addEventListener('click', close);
        requestAnimationFrame(function () { el.classList.add('show'); });
        setTimeout(close, duration);

        // 最多同時 4 則
        while (host.children.length > 4) host.removeChild(host.firstChild);
        return { close: close };
    }

    window.fengbroToast = toast;

    function looksLikeError(message) {
        return /失敗|錯誤|無法|error|fail|invalid|不存在|請先|請輸入|請選擇|不可|拒絕|逾時|timeout/i.test(message);
    }

    /* ---------------------------------------------------------
     * alert() → toast（非阻斷）
     * --------------------------------------------------------- */
    try {
        var nativeAlert = window.alert ? window.alert.bind(window) : null;
        window.fengbroNativeAlert = nativeAlert;
        window.alert = function (message) {
            try {
                var text = message == null ? '' : String(message);
                toast(text, looksLikeError(text) ? 'err' : 'info');
            } catch (_) {
                if (nativeAlert) nativeAlert(message);
            }
        };
    } catch (_) { /* ignore */ }

    /* ---------------------------------------------------------
     * 頂部進度條
     * --------------------------------------------------------- */
    var pending = 0;
    var barTimer = null;
    var barGrowTimer = null;
    var barWidth = 0;

    function bar() {
        var el = document.getElementById('fengbroUxBar');
        if (!el && document.body) {
            el = document.createElement('div');
            el.id = 'fengbroUxBar';
            document.body.appendChild(el);
        }
        return el;
    }

    function barStart() {
        pending++;
        if (pending !== 1) return;
        clearTimeout(barTimer);
        barTimer = setTimeout(function () {
            var el = bar();
            if (!el || pending === 0) return;
            barWidth = 12;
            el.style.width = barWidth + '%';
            el.classList.add('on');
            clearInterval(barGrowTimer);
            barGrowTimer = setInterval(function () {
                barWidth = Math.min(92, barWidth + (92 - barWidth) * 0.12);
                el.style.width = barWidth + '%';
            }, 300);
        }, 120);
    }

    function barDone() {
        pending = Math.max(0, pending - 1);
        if (pending !== 0) return;
        clearTimeout(barTimer);
        clearInterval(barGrowTimer);
        var el = document.getElementById('fengbroUxBar');
        if (!el || !el.classList.contains('on')) return;
        el.style.width = '100%';
        setTimeout(function () {
            if (pending !== 0) return;
            el.classList.remove('on');
            setTimeout(function () { if (pending === 0) el.style.width = '0'; }, 300);
        }, 180);
    }

    /* ---------------------------------------------------------
     * 防重複送出：記住最近點擊的按鈕
     * --------------------------------------------------------- */
    var lastButton = null;
    var lastButtonAt = 0;

    document.addEventListener('click', function (e) {
        var target = e.target && e.target.closest ? e.target.closest('button, .btn, input[type="submit"]') : null;
        if (!target) return;
        lastButton = target;
        lastButtonAt = Date.now();
    }, true);

    function claimBusyButton() {
        var btn = lastButton;
        if (!btn || Date.now() - lastButtonAt > 400) return null;
        if (btn.classList.contains('fengbro-ux-busy')) return null;
        lastButton = null;
        btn.classList.add('fengbro-ux-busy');
        btn.setAttribute('aria-busy', 'true');
        var wasDisabled = !!btn.disabled;
        if ('disabled' in btn) btn.disabled = true;
        var released = false;
        return function release() {
            if (released) return;
            released = true;
            btn.classList.remove('fengbro-ux-busy');
            btn.removeAttribute('aria-busy');
            if ('disabled' in btn && !wasDisabled) btn.disabled = false;
        };
    }

    /* ---------------------------------------------------------
     * 寫入請求判斷與成功提示
     * --------------------------------------------------------- */
    function isSameOrigin(url) {
        try { return new URL(url, location.href).origin === location.origin; } catch (_) { return true; }
    }

    function actionOf(url) {
        try { return (new URL(url, location.href).searchParams.get('action') || '').toLowerCase(); } catch (_) { return ''; }
    }

    function isWrite(method, url) {
        method = (method || 'GET').toUpperCase();
        if (method !== 'GET' && method !== 'HEAD') return true;
        return /^(create|update|delete|restore|empty_trash|save|remove)/.test(actionOf(url));
    }

    function successLabel(url) {
        var action = actionOf(url);
        if (/^create|^add|^insert/.test(action)) return '已新增';
        if (/^update|^save|^edit/.test(action)) return '已儲存';
        if (/^delete|^remove|^empty_trash/.test(action)) return '已刪除';
        if (/^restore/.test(action)) return '已復原';
        return '已完成';
    }

    function isQuietUrl(url) {
        // 背景請求（統計、推播、CSRF 換發）不顯示進度與提示。
        return /(stats_api|csrf|push_subscribe|notif_diag)\.php/i.test(url);
    }

    function rememberFlash(message, type) {
        ssSet(FLASH_KEY, { message: message, type: type || 'ok', at: Date.now() });
    }

    /* ---------------------------------------------------------
     * fetch 包裝
     * --------------------------------------------------------- */
    try {
        var prevFetch = window.fetch ? window.fetch.bind(window) : null;
        if (prevFetch) {
            window.fetch = function (input, init) {
                var url = typeof input === 'string' ? input : (input && input.url) || '';
                var method = (init && init.method) || (input && typeof input !== 'string' && input.method) || 'GET';
                if (!isSameOrigin(url) || isQuietUrl(url)) {
                    return prevFetch(input, init);
                }
                var write = isWrite(method, url);
                // init.fengbroQuiet：呼叫端自己會顯示結果（例如刪除後附「復原」的提示），不另外跳「已完成」。
                var quiet = !!(init && init.fengbroQuiet);
                var release = write ? claimBusyButton() : null;
                barStart();
                var promise;
                try {
                    promise = prevFetch(input, init);
                } catch (err) {
                    barDone();
                    if (release) release();
                    throw err;
                }
                return promise.then(function (response) {
                    barDone();
                    if (release) release();
                    if (write && !quiet) {
                        try {
                            var ct = response.headers.get('content-type') || '';
                            if (response.ok && ct.indexOf('application/json') !== -1) {
                                response.clone().json().then(function (data) {
                                    if (data && data.success) {
                                        // 先記下來：若接著 location.reload()，新頁面會補顯示。
                                        rememberFlash(successLabel(url), 'ok');
                                    }
                                }).catch(function () { /* ignore */ });
                            }
                        } catch (_) { /* ignore */ }
                    }
                    return response;
                }, function (err) {
                    barDone();
                    if (release) release();
                    if (write && !navigator.onLine) {
                        toast('目前離線，請恢復連線後再試一次', 'err');
                    }
                    throw err;
                });
            };
        }
    } catch (_) { /* ignore */ }

    /* ---------------------------------------------------------
     * XHR（上傳進度條那條）只掛進度條，不動按鈕
     * --------------------------------------------------------- */
    try {
        var XHR = window.XMLHttpRequest;
        if (XHR && XHR.prototype) {
            var origOpen = XHR.prototype.open;
            var origSend = XHR.prototype.send;
            XHR.prototype.open = function (method, url) {
                this.__fengbroUxTrack = isSameOrigin(url) && !isQuietUrl(String(url || ''));
                return origOpen.apply(this, arguments);
            };
            XHR.prototype.send = function () {
                if (this.__fengbroUxTrack && !this.__fengbroUxHooked) {
                    this.__fengbroUxHooked = true;
                    barStart();
                    var done = false;
                    var finish = function () { if (!done) { done = true; barDone(); } };
                    this.addEventListener('loadend', finish);
                }
                return origSend.apply(this, arguments);
            };
        }
    } catch (_) { /* ignore */ }

    /* ---------------------------------------------------------
     * 捲動位置記憶（只在「重新整理同一頁」時還原）
     * --------------------------------------------------------- */
    function pageKey() {
        return location.pathname + location.search;
    }

    function navigationType() {
        try {
            var entries = performance.getEntriesByType && performance.getEntriesByType('navigation');
            if (entries && entries[0] && entries[0].type) return entries[0].type;
        } catch (_) { /* ignore */ }
        try {
            if (performance.navigation && performance.navigation.type === 1) return 'reload';
        } catch (_) { /* ignore */ }
        return 'navigate';
    }

    window.addEventListener('pagehide', function () {
        ssSet(SCROLL_KEY, { key: pageKey(), y: window.scrollY || window.pageYOffset || 0, at: Date.now() });
        // 還沒看完的 toast 帶到下一頁（常見：alert('完成') 後立刻 reload）。
        var now = Date.now();
        var carry = liveToasts.filter(function (t) { return t.until - now > 600; });
        if (carry.length) {
            var last = carry[carry.length - 1];
            ssSet(FLASH_KEY, { message: last.message, type: last.type, at: now });
        }
    });

    function restoreScroll() {
        var saved = ssGet(SCROLL_KEY);
        if (!saved || saved.key !== pageKey() || Date.now() - saved.at > 5 * 60 * 1000) return;
        if (location.hash) return;
        if (navigationType() !== 'reload') return;
        // 新增／編輯後要捲到該筆資料（optimistic-ui.js），不還原舊位置。
        if (typeof window.fengbroHasPendingFocus === 'function' && window.fengbroHasPendingFocus()) return;
        try { if ('scrollRestoration' in history) history.scrollRestoration = 'manual'; } catch (_) { /* ignore */ }
        var y = saved.y || 0;
        if (y <= 0) return;
        var tries = 0;
        (function attempt() {
            window.scrollTo(0, y);
            tries++;
            // 內容可能還在長高（圖片、延遲渲染），再追幾次。
            if (Math.abs((window.scrollY || 0) - y) > 4 && tries < 20) {
                setTimeout(attempt, 100);
            }
        })();
    }

    // 局部更新（fengbroReload）完成後立刻顯示，不必等輪詢。
    window.fengbroFlushFlash = function () {
        try { showFlash(); } catch (_) { /* ignore */ }
    };

    function showFlash() {
        var flash = ssGet(FLASH_KEY);
        if (!flash) return;
        ssDel(FLASH_KEY);
        if (Date.now() - (flash.at || 0) > 8000) return;
        toast(flash.message, flash.type || 'ok');
    }

    onReady(function () {
        try { restoreScroll(); } catch (_) { /* ignore */ }
        try { showFlash(); } catch (_) { /* ignore */ }
    });

    // 同頁操作成功但沒有重新整理時，也即時提示一次。
    try {
        var flashPoll = setInterval(function () {
            var flash = ssGet(FLASH_KEY);
            if (!flash) return;
            // 給 location.reload() 一點時間：若 700ms 內沒有離開頁面，就在本頁直接顯示。
            if (Date.now() - (flash.at || 0) < 700) return;
            ssDel(FLASH_KEY);
            if (Date.now() - (flash.at || 0) < 8000) toast(flash.message, flash.type || 'ok');
        }, 350);
        window.addEventListener('pagehide', function () { clearInterval(flashPoll); });
    } catch (_) { /* ignore */ }

    /* ---------------------------------------------------------
     * 離線提示
     * --------------------------------------------------------- */
    window.addEventListener('offline', function () { toast('網路已中斷，操作可能無法儲存', 'err', 5000); });
    window.addEventListener('online', function () { toast('已恢復連線', 'ok', 2500); });
})();
