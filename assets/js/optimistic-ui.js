/**
 * Optimistic UI + 無閃爍局部更新（在 header 同步載入，需在 ux-boost.js 之後）。
 *
 *  1. fengbroReload()：取代寫入後的 location.reload()。
 *     在背景抓同一頁的新 HTML，只把「伺服器端有變的部分」套進目前畫面（三方合併），
 *     不重新下載 CSS / JS / 字型、不白屏、不跳捲動位置，
 *     而且保留 JS 產生的狀態（檢視模式、篩選、展開的群組、事件監聽、播放中的媒體）。
 *     頁面內嵌資料的 <script> 有變、找不到主內容、或任何錯誤時，自動退回整頁重新整理。
 *
 *  2. fengbroOptimistic.hide(id) / .restore(token)：刪除時先把該筆從畫面拿掉，
 *     請求失敗再放回來（deleteInlineItem、批量刪除、各頁刪除都走這裡）。
 *
 *  3. 寫入（update）進行中，該筆資料列會淡淡閃爍，表示「儲存中」。
 *
 *  4. 滑鼠停在站內頁面連結上時先預先轉譯（Speculation Rules，Chrome / Edge），點下去幾乎瞬開。
 *
 * 只有 SOFT_PAGES 列出的頁面啟用局部更新；其他頁面 fengbroReload() 等同 location.reload()。
 */
(function () {
    'use strict';

    if (window.__fengbroOptimistic) return;
    window.__fengbroOptimistic = true;

    // 媒體播放器、工具、設定頁邏輯較多，維持整頁重新整理。
    var SOFT_PAGES = {
        subscription: 1, trialpurchase: 1, reinstall: 1, quota: 1, shoppinglist: 1, food: 1,
        notes: 1, favorites: 1, images: 1, documents: 1, bank: 1, routine: 1
    };

    // 局部更新後要回到伺服器狀態的暫時性 UI（彈窗、新增列、行內編輯切換、展開的表單）。
    var TRANSIENT_SELECTOR = '.modal,[id$="Modal"],[id$="Overlay"],[id$="Form"],[id$="Suggestions"],' +
        '[id^="inlineAdd"],[id$="AddCard"],[id$="AddRow"],.inline-add-row,.inline-add-card,' +
        '.inline-edit,.inline-view,[data-soft-reset]';

    var currentPage = (function () {
        try { return new URLSearchParams(location.search).get('page') || 'home'; } catch (_) { return ''; }
    })();

    var supported = !!(window.Map && window.MutationObserver && window.DOMParser && window.fetch &&
        document.documentElement && document.documentElement.cloneNode);
    var softEnabled = supported && !!SOFT_PAGES[currentPage];

    function hardReload() {
        try { location.reload(); } catch (_) { /* ignore */ }
    }

    /* ---------------------------------------------------------
     * 樣式
     * --------------------------------------------------------- */
    try {
        var style = document.createElement('style');
        style.id = 'fengbroOptimisticStyle';
        style.textContent = '' +
            '.fengbro-opt-pending{animation:fengbroOptPulse 1.1s ease-in-out infinite;}' +
            '@keyframes fengbroOptPulse{0%,100%{opacity:1;}50%{opacity:.55;}}' +
            '.fengbro-opt-leaving{transition:opacity .18s ease,transform .18s ease;opacity:0!important;transform:scale(.98);}' +
            '.fengbro-opt-hidden{display:none!important;}' +
            '.fengbro-opt-fresh{animation:fengbroOptFresh 1.2s ease-out;}' +
            '@keyframes fengbroOptFresh{0%{background-color:rgba(201,100,66,.18);}100%{background-color:transparent;}}' +
            '@media (prefers-reduced-motion:reduce){.fengbro-opt-pending,.fengbro-opt-fresh{animation:none;}' +
            '.fengbro-opt-leaving{transition:none;}}';
        (document.head || document.documentElement).appendChild(style);
    } catch (_) { /* ignore */ }

    /* ---------------------------------------------------------
     * 伺服器基準快照
     *
     * 三方合併需要「伺服器當初送來的樣子」。footer 一開頭會呼叫 fengbroSnapshotMain()，
     * 此時主內容剛解析完、DOMContentLoaded 的初始化還沒跑。頁面內 <script> 在解析期間
     * 改過的屬性（例如依 localStorage 展開群組）由 MutationObserver 記下原值並還原到快照，
     * 這樣 JS 的狀態就不會被誤判成「伺服器改了」。
     * --------------------------------------------------------- */
    var attrOrig = new Map();   // 目前節點 -> { 屬性名: 最初的值 }
    var recorder = null;
    var base = null;            // 伺服器版本的 #mainContent（不在畫面上）
    var baseMap = null;         // 伺服器版本節點 -> 畫面上的對應節點

    function recordMutations(records) {
        if (!attrOrig) return;
        for (var i = 0; i < records.length; i++) {
            var r = records[i];
            if (r.type !== 'attributes') continue;
            var o = attrOrig.get(r.target);
            if (!o) {
                o = Object.create(null);
                attrOrig.set(r.target, o);
            }
            if (!(r.attributeName in o)) o[r.attributeName] = r.oldValue;
        }
    }

    if (softEnabled) {
        try {
            recorder = new MutationObserver(recordMutations);
            recorder.observe(document.documentElement, { attributes: true, attributeOldValue: true, subtree: true });
        } catch (_) {
            recorder = null;
        }
    }

    function pairWalk(b, c, map) {
        map.set(b, c);
        var bc = b.firstChild;
        var cc = c.firstChild;
        while (bc && cc) {
            pairWalk(bc, cc, map);
            bc = bc.nextSibling;
            cc = cc.nextSibling;
        }
    }

    function snapshot() {
        if (!softEnabled || base) return;
        try {
            var main = document.getElementById('mainContent');
            if (!main) return;
            if (recorder) {
                recordMutations(recorder.takeRecords());
                recorder.disconnect();
                recorder = null;
            }
            var clone = main.cloneNode(true);
            var map = new Map();
            pairWalk(clone, main, map);
            map.forEach(function (current, snap) {
                var o = attrOrig.get(current);
                if (!o || snap.nodeType !== 1) return;
                Object.keys(o).forEach(function (name) {
                    if (o[name] === null) snap.removeAttribute(name);
                    else snap.setAttribute(name, o[name]);
                });
            });
            base = clone;
            baseMap = map;
        } catch (_) {
            base = null;
            baseMap = null;
        } finally {
            attrOrig = null;
        }
    }

    /* ---------------------------------------------------------
     * 三方合併：base（舊伺服器版）→ next（新伺服器版），只把差異套到畫面
     * --------------------------------------------------------- */
    function keyOf(node) {
        if (node.nodeType !== 1) return null;
        var id = node.getAttribute('id');
        if (id) return 'i:' + id;
        var dataId = node.getAttribute('data-id');
        if (dataId) return 'd:' + node.tagName + ':' + dataId;
        return null;
    }

    function compatible(a, b) {
        return a.nodeType === b.nodeType && (a.nodeType !== 1 || a.tagName === b.tagName);
    }

    function childList(node) {
        var list = [];
        for (var n = node.firstChild; n; n = n.nextSibling) list.push(n);
        return list;
    }

    function setAttr(el, name, value) {
        el.setAttribute(name, value);
        if (name === 'value' && 'value' in el && el !== document.activeElement) {
            try { el.value = value; } catch (_) { /* ignore */ }
        } else if (name === 'checked' && 'checked' in el) {
            el.checked = true;
        } else if (name === 'selected' && 'selected' in el) {
            el.selected = true;
        }
    }

    function removeAttr(el, name) {
        el.removeAttribute(name);
        if (name === 'checked' && 'checked' in el) el.checked = false;
        else if (name === 'selected' && 'selected' in el) el.selected = false;
    }

    function syncAttrs(b, n, c) {
        var i, a;
        for (i = 0; i < n.attributes.length; i++) {
            a = n.attributes[i];
            if (b.getAttribute(a.name) !== a.value) setAttr(c, a.name, a.value);
        }
        for (i = 0; i < b.attributes.length; i++) {
            a = b.attributes[i];
            if (!n.hasAttribute(a.name)) removeAttr(c, a.name);
        }
    }

    function mapEqual(b, n, map) {
        var c = baseMap.get(b);
        if (c) map.set(n, c);
        var bc = b.firstChild;
        var nc = n.firstChild;
        while (bc && nc) {
            mapEqual(bc, nc, map);
            bc = bc.nextSibling;
            nc = nc.nextSibling;
        }
    }

    function morphNode(b, n, c, map, fresh) {
        map.set(n, c);
        if (n.nodeType !== 1) {
            if (b.nodeValue !== n.nodeValue) c.nodeValue = n.nodeValue;
            return;
        }
        if (b.isEqualNode(n)) {
            mapEqual(b, n, map);
            return;
        }
        syncAttrs(b, n, c);
        if (n.tagName === 'SCRIPT') return;
        diffChildren(b, n, c, map, fresh);
    }

    function diffChildren(b, n, c, map, fresh) {
        var bKids = childList(b);
        var nKids = childList(n);
        var keyed = Object.create(null);
        var used = [];
        var i, j, k;
        for (i = 0; i < bKids.length; i++) {
            k = keyOf(bKids[i]);
            if (k && !(k in keyed)) keyed[k] = i;
        }
        var cursor = 0;
        var prev = null;
        for (i = 0; i < nKids.length; i++) {
            var nn = nKids[i];
            var bi = -1;
            k = keyOf(nn);
            if (k) {
                if (k in keyed && !used[keyed[k]] && compatible(bKids[keyed[k]], nn)) bi = keyed[k];
            } else {
                for (j = cursor; j < bKids.length; j++) {
                    if (!used[j] && !keyOf(bKids[j]) && compatible(bKids[j], nn)) {
                        bi = j;
                        break;
                    }
                }
                if (bi >= 0) cursor = bi + 1;
            }

            if (bi >= 0) {
                used[bi] = true;
                var bb = bKids[bi];
                var cc = baseMap.get(bb);
                // JS 已經接手（移走或換掉）這個節點：尊重 JS 的狀態，不去動它。
                if (!cc || cc.parentNode !== c) continue;
                if (prev && !(prev.compareDocumentPosition(cc) & 4 /* FOLLOWING */)) {
                    c.insertBefore(cc, prev.nextSibling);
                }
                morphNode(bb, nn, cc, map, fresh);
                prev = cc;
            } else {
                var node = document.importNode(nn, true);
                c.insertBefore(node, prev ? prev.nextSibling : c.firstChild);
                pairWalk(nn, node, map);
                if (node.nodeType === 1 && node.getAttribute('data-id')) fresh.push(node);
                prev = node;
            }
        }
        for (j = 0; j < bKids.length; j++) {
            if (used[j]) continue;
            var dead = baseMap.get(bKids[j]);
            if (dead && dead.parentNode === c) c.removeChild(dead);
        }
    }

    function scriptSignature(root) {
        var parts = [];
        var scripts = root.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            parts.push((scripts[i].getAttribute('src') || '') + '\u0000' + scripts[i].textContent);
        }
        return parts.join('\u0001');
    }

    function resetTransient(next, map) {
        var list = next.querySelectorAll(TRANSIENT_SELECTOR);
        for (var i = 0; i < list.length; i++) {
            var n = list[i];
            var c = map.get(n);
            if (!c || c.nodeType !== 1 || !c.isConnected) continue;
            ['class', 'style', 'hidden'].forEach(function (name) {
                var v = n.getAttribute(name);
                if (v === null) {
                    if (c.hasAttribute(name)) c.removeAttribute(name);
                } else if (c.getAttribute(name) !== v) {
                    c.setAttribute(name, v);
                }
            });
        }
    }

    function merge(next) {
        var main = document.getElementById('mainContent');
        var map = new Map();
        var fresh = [];
        morphNode(base, next, main, map, fresh);
        resetTransient(next, map);
        base = next;
        baseMap = map;
        reapplyHidden();
        fresh.forEach(function (el) {
            el.classList.add('fengbro-opt-fresh');
            setTimeout(function () { el.classList.remove('fengbro-opt-fresh'); }, 1300);
        });
    }

    function pageUrl() {
        var u = new URL(location.href);
        u.hash = '';
        // 路徑以 / 結尾時補上 index.php：Service Worker 不快取 .php，確保拿到的是最新頁面。
        if (/\/$/.test(u.pathname)) u.pathname += 'index.php';
        return u.toString();
    }

    var inflight = null;
    var again = false;

    function softReload() {
        if (!softEnabled || !base) {
            hardReload();
            return Promise.resolve(false);
        }
        if (inflight) {
            again = true;
            return inflight;
        }
        var expected = new URL(pageUrl());
        inflight = fetch(expected.toString(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Fengbro-Soft': '1' }
        }).then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            if (response.redirected && new URL(response.url).search !== expected.search) {
                throw new Error('redirected');
            }
            return response.text();
        }).then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var next = doc.getElementById('mainContent');
            if (!next || !document.getElementById('mainContent')) throw new Error('main not found');
            // 頁面內嵌資料（const ITEMS = [...]）變了就沒辦法局部更新，整頁重新整理。
            if (scriptSignature(base) !== scriptSignature(next)) throw new Error('scripts changed');
            merge(next);
            return true;
        }).catch(function () {
            hardReload();
            return false;
        }).then(function (ok) {
            inflight = null;
            if (!ok) return false;
            try { document.dispatchEvent(new CustomEvent('fengbro:soft-reload')); } catch (_) { /* ignore */ }
            if (typeof window.fengbroFlushFlash === 'function') window.fengbroFlushFlash();
            if (again) {
                again = false;
                return softReload();
            }
            return true;
        });
        return inflight;
    }

    /* ---------------------------------------------------------
     * 樂觀刪除：先從畫面拿掉，失敗再放回來
     * --------------------------------------------------------- */
    var hiddenIds = Object.create(null);   // id -> 還在處理中的次數

    function cssEscape(value) {
        value = String(value);
        if (window.CSS && CSS.escape) return CSS.escape(value);
        return value.replace(/["\\]/g, '\\$&');
    }

    var CONTROL_TAGS = /^(INPUT|BUTTON|A|SELECT|OPTION|LABEL|TEXTAREA|I|SPAN|IMG|SVG|AUDIO|VIDEO)$/;

    /** 找出代表這筆資料的最外層元素（桌面表格列、手機卡片等），排除按鈕、勾選框等控制項。 */
    function itemsFor(id) {
        var root = document.getElementById('mainContent') || document.body;
        if (!root || id === undefined || id === null || id === '') return [];
        var sid = String(id);
        var esc = cssEscape(sid);
        var nodes = root.querySelectorAll('[data-id="' + esc + '"],[data-point-id="' + esc + '"]');
        var out = [];
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            if (CONTROL_TAGS.test(el.tagName)) continue;
            var nested = false;
            for (var p = el.parentElement; p && p !== root; p = p.parentElement) {
                if (p.getAttribute('data-id') === sid || p.getAttribute('data-point-id') === sid) {
                    nested = true;
                    break;
                }
            }
            if (!nested) out.push(el);
        }
        return out;
    }

    function hide(id) {
        var els = itemsFor(id);
        if (!els.length) return null;
        var sid = String(id);
        hiddenIds[sid] = (hiddenIds[sid] || 0) + 1;
        var reduce = false;
        try { reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (_) { /* ignore */ }
        els.forEach(function (el) {
            el.classList.add('fengbro-opt-leaving');
            setTimeout(function () {
                el.classList.add('fengbro-opt-hidden');
                el.classList.remove('fengbro-opt-leaving');
            }, reduce ? 0 : 180);
        });
        return { id: sid, els: els, done: false };
    }

    /** 請求失敗：把畫面上拿掉的資料放回來。 */
    function restore(token) {
        if (!token || token.done) return;
        token.done = true;
        if (hiddenIds[token.id] && --hiddenIds[token.id] <= 0) delete hiddenIds[token.id];
        token.els.forEach(function (el) {
            el.classList.remove('fengbro-opt-leaving', 'fengbro-opt-hidden');
        });
    }

    /** 請求成功：從隱藏清單移除（元素會在局部更新時被伺服器版本拿掉）。 */
    function commit(token) {
        if (!token || token.done) return;
        token.done = true;
        if (hiddenIds[token.id] && --hiddenIds[token.id] <= 0) delete hiddenIds[token.id];
        if (!softEnabled) {
            token.els.forEach(function (el) {
                if (el.parentNode) el.parentNode.removeChild(el);
            });
        }
    }

    function reapplyHidden() {
        Object.keys(hiddenIds).forEach(function (id) {
            itemsFor(id).forEach(function (el) { el.classList.add('fengbro-opt-hidden'); });
        });
    }

    /* ---------------------------------------------------------
     * 更新中的資料列：淡淡閃爍
     * --------------------------------------------------------- */
    function markPending(id) {
        var els = itemsFor(id);
        els.forEach(function (el) { el.classList.add('fengbro-opt-pending'); });
        return function () {
            els.forEach(function (el) { el.classList.remove('fengbro-opt-pending'); });
        };
    }

    /* ---------------------------------------------------------
     * 資料變更記號：上一頁／下一頁（bfcache）還原時，只有資料真的變過才更新
     * --------------------------------------------------------- */
    var CHANGED_KEY = 'fengbroDataChangedAt';
    var renderedAt = Date.now();

    function markDataChanged() {
        try { localStorage.setItem(CHANGED_KEY, String(Date.now())); } catch (_) { /* ignore */ }
    }

    function dataChangedSince(time) {
        try { return (parseInt(localStorage.getItem(CHANGED_KEY) || '0', 10) || 0) > time; } catch (_) { return false; }
    }

    var WRITE_ACTION = /^(create|update|delete|restore|empty|save|import|remove|clean|send|init|set|add|toggle|sync|reset|clear|upload|merge|rename|move)/i;

    try {
        var prevFetch = window.fetch ? window.fetch.bind(window) : null;
        if (prevFetch) {
            window.fetch = function (input, init) {
                var url = typeof input === 'string' ? input : (input && input.url) || '';
                var method = String((init && init.method) || (input && typeof input !== 'string' && input.method) || 'GET').toUpperCase();
                var unmark = null;
                var write = false;
                try {
                    var u = new URL(url, location.href);
                    if (u.origin === location.origin && !/(stats_api|csrf|notif_diag)\.php$/.test(u.pathname)) {
                        var action = u.searchParams.get('action') || '';
                        write = (method !== 'GET' && method !== 'HEAD') || WRITE_ACTION.test(action);
                        if (/api\.php$/.test(u.pathname) && action === 'update' && u.searchParams.get('id')) {
                            unmark = markPending(u.searchParams.get('id'));
                        }
                    }
                } catch (_) { /* ignore */ }
                var promise = prevFetch(input, init);
                if (unmark || write) {
                    promise.then(function (response) {
                        if (unmark) unmark();
                        if (write && response && response.ok) markDataChanged();
                    }, function () {
                        if (unmark) unmark();
                    });
                }
                return promise;
            };
        }
    } catch (_) { /* ignore */ }

    try {
        var XHRp = window.XMLHttpRequest && window.XMLHttpRequest.prototype;
        if (XHRp) {
            var xhrOpen = XHRp.open;
            XHRp.open = function (method, url) {
                try {
                    var same = new URL(String(url || ''), location.href).origin === location.origin;
                    if (same && String(method || 'GET').toUpperCase() !== 'GET') {
                        this.addEventListener('load', function () {
                            if (this.status >= 200 && this.status < 300) markDataChanged();
                        });
                    }
                } catch (_) { /* ignore */ }
                return xhrOpen.apply(this, arguments);
            };
        }
    } catch (_) { /* ignore */ }

    // 從上一頁／下一頁快取瞬間還原；期間若在別頁改過資料，就局部更新（或整頁重新整理）。
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        if (dataChangedSince(renderedAt)) {
            renderedAt = Date.now();
            softReload();
        }
    });
    document.addEventListener('fengbro:soft-reload', function () { renderedAt = Date.now(); });

    /* ---------------------------------------------------------
     * 預先轉譯站內頁面（Speculation Rules：滑鼠停留 / 按下時開始）
     * --------------------------------------------------------- */
    function installSpeculationRules() {
        try {
            if (!HTMLScriptElement.supports || !HTMLScriptElement.supports('speculationrules')) return;
            var rules = {
                prerender: [{
                    source: 'document',
                    where: {
                        and: [
                            { href_matches: '/*\\?page=*' },
                            { not: { href_matches: '/*\\?page=' + currentPage + '*' } },
                            { not: { selector_matches: '[target], [download], [data-no-prerender]' } }
                        ]
                    },
                    eagerness: 'moderate'
                }]
            };
            var s = document.createElement('script');
            s.type = 'speculationrules';
            s.textContent = JSON.stringify(rules);
            document.head.appendChild(s);
        } catch (_) { /* ignore */ }
    }
    installSpeculationRules();

    /* ---------------------------------------------------------
     * 對外 API
     * --------------------------------------------------------- */
    window.fengbroSnapshotMain = snapshot;
    window.fengbroReload = softReload;
    window.fengbroOptimistic = {
        enabled: softEnabled,
        hide: hide,
        restore: restore,
        commit: commit,
        items: itemsFor,
        reload: softReload
    };
})();
