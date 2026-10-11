/**
 * 軟導覽（Soft Navigation）：站內導覽不整頁重載。
 * 在 header 同步載入，需在 optimistic-ui.js 之後。
 *
 *  點站內連結 → 抓目標頁 → 只換掉 #mainContent → 重跑該頁 <script>
 *  → 更新 title / body[data-page] / 側邊欄與行動分頁 active → pushState 更新網址。
 *  上一頁 / 下一頁（popstate）用 HTML 快取還原；任何失敗一律退回整頁導覽。
 *
 *  ── 為什麼要隔離 script 的作用域 ──────────────────────────────
 *  各頁在「全域」用 const/let 宣告同一批名稱（batchDeleteTable、TABLE…），
 *  例如 subscription、notes、bank 都各有一份。整頁載入時每個頁面都是全新
 *  document，不會衝突；但軟導覽是同一個 document，直接重跑就會
 *  「Identifier 'batchDeleteTable' has already been declared」而整個 script 不執行。
 *
 *  把 const/let 改寫成 var 也救不了：var 不能重新宣告既有的 let/const 綁定。
 *  因此改為把該頁所有 inline script 串成「一個 IIFE」執行 —— 每次都是全新的
 *  函式作用域，不與前一頁、也不與自己衝突；再把頂層宣告的名稱選擇性發佈到
 *  window，讓 HTML 上的 inline 事件屬性（onclick="..."）與外層腳本找得到。
 *
 *  ── 其他設計要點 ────────────────────────────────────────────
 *   - 與 fengbroReload()（同頁局部更新）是兩件事。後者會比對頁面內嵌 <script> 簽章，
 *     跨頁必變，因此必定 fallback；軟導覽必須自成一模組。
 *   - 換頁後 #mainContent 全換，optimistic-ui.js 內的基準快照會失效，
 *     因此在「頁面 script 執行前」先 fengbroResetSnapshot() + fengbroSnapshotMain()
 *     重建基準，否則之後寫入觸發的 fengbroReload() 會拿舊 base 合併導致畫面錯亂。
 *   - 頁面 script 以 DOMContentLoaded 初始化者，換頁後不會再觸發；
 *     重跑期間臨時包裝 addEventListener，讓回呼立即執行。
 *   - 內含 src 外部 script 的頁面一律不軟導覽（執行順序無法保證）。
 *
 *  停用：localStorage.setItem('fengbroSoftNav', '0')，重新整理即整站停用。
 */
(function () {
    'use strict';

    if (window.__fengbroSoftNav) return;
    window.__fengbroSoftNav = true;

    // 全域 kill switch
    try { if (localStorage.getItem('fengbroSoftNav') === '0') return; } catch (_) { /* ignore */ }

    /*
     * 白名單：只有列在這裡的頁面才走軟導覽，其餘維持整頁導覽。
     * Phase 1 試點：notes、bank。擴大時「一次加一頁」，並逐頁跑 SOFT_NAV_PLAN.md §8 的驗證清單。
     * 遇到無法確信的頁面（外部 script 等）會自動退回整頁導覽，不會壞掉。
     */
    var WHITELIST = {
        notes: 1,
        bank: 1
    };

    var supported = !!(window.fetch && window.DOMParser && window.history &&
        history.pushState && window.URLSearchParams && window.CustomEvent);
    if (!supported) return;

    var CACHE_MAX = 6;
    var cache = new Map();
    var inflight = null;
    var dbg = null;

    /* =========================================================
     * 頁面監聽器追蹤
     *
     * 頁面 inline script 常對 document / window 註冊事件（例：subscription 註冊
     * document click 來關閉建議下拉）。整頁載入時這些監聽會隨文件消失，但軟導覽
     * 是同一個 document：離開該頁後監聽仍在，之後每次點擊都會因為找不到舊頁元素
     * 而丟錯（Cannot read properties of null (reading 'style')）。
     * 因此在註冊時記下來，換頁前移除。
     *
     * 只追蹤「頁面自己的 inline script」註冊的監聽：以呼叫堆疊判斷來源，
     * 堆疊中含 assets/js/ 的（共用腳本 main.js、ux-boost.js 等）一律不追蹤。
     * ========================================================= */
    var pageListeners = [];
    var trackEnabled = true;

    function stackIsPageScript() {
        try {
            var stack = new Error().stack;
            if (!stack) return false;
            var base = location.origin + location.pathname;
            var lines = stack.split('\n');
            var sawDocument = false;
            for (var i = 0; i < lines.length; i++) {
                var ln = lines[i];
                if (ln.indexOf('soft-nav.js') !== -1) continue;
                if (ln.indexOf('assets/js/') !== -1) return false;
                if (ln.indexOf(base) !== -1) sawDocument = true;
            }
            return sawDocument;
        } catch (_) { return false; }
    }

    function installListenerTracker() {
        var pairs = [
            [document, 'addEventListener', 'removeEventListener'],
            [window, 'addEventListener', 'removeEventListener']
        ];
        for (var p = 0; p < pairs.length; p++) {
            (function (obj, addName, removeName) {
                var origAdd = obj[addName];
                if (typeof origAdd !== 'function') return;
                obj[addName] = function (type, fn, opts) {
                    if (trackEnabled && typeof fn === 'function' && stackIsPageScript()) {
                        pageListeners.push({ obj: obj, remove: removeName, type: type, fn: fn, opts: opts });
                    }
                    return origAdd.call(obj, type, fn, opts);
                };
            })(pairs[p][0], pairs[p][1], pairs[p][2]);
        }
    }

    /** 換頁前移除上一頁註冊在 document / window 的監聽器。 */
    function dropPageListeners() {
        for (var i = 0; i < pageListeners.length; i++) {
            var l = pageListeners[i];
            try { l.obj[l.remove](l.type, l.fn, l.opts); } catch (_) { /* ignore */ }
        }
        pageListeners.length = 0;
    }

    installListenerTracker();
    try {
        if (document.readyState === 'complete') trackEnabled = false;
        else window.addEventListener('load', function () { trackEnabled = false; }, { once: true });
    } catch (_) { /* ignore */ }

    function keyOf(url) { return url.pathname + url.search; }
    function pageOf(url) { return (url.searchParams.get('page') || '').toLowerCase(); }
    function isWhitelisted(page) { return !!WHITELIST[String(page || '').toLowerCase()]; }

    /* =========================================================
     * 收集頂層宣告的名稱（用於發佈到 window）
     *
     * 這是過度取樣（也會抓到縮排的區塊／函式內宣告），但發佈時有 typeof 守衛，
     * 不在作用域內的名稱會被略過，因此無害。
     * ========================================================= */
    function collectDeclaredNames(src) {
        var seen = Object.create(null);
        var order = [];
        function add(n) {
            if (n && !seen[n]) { seen[n] = 1; order.push(n); }
        }

        var re = /(?:^|[\n;{}])\s*(?:async\s+)?(?:function\s*\*?|const|let|var|class)\s+([A-Za-z_$][\w$]*)/g;
        var m;
        while ((m = re.exec(src)) !== null) add(m[1]);

        // 解構宣告：const { a, b: c } = … / const [x, y] = …
        var dre = /(?:const|let|var)\s*([\[{])/g;
        while ((m = dre.exec(src)) !== null) {
            var open = m[1], close = open === '{' ? '}' : ']';
            var i = dre.lastIndex, depth = 1, start = i;
            while (i < src.length && depth > 0) {
                var c = src.charAt(i);
                if (c === open) depth++;
                else if (c === close) { depth--; if (depth === 0) break; }
                i++;
            }
            var parts = src.slice(start, i).split(',');
            for (var k = 0; k < parts.length; k++) {
                var pm = /^(?:\.\.\.)?\s*([A-Za-z_$][\w$]*)\s*(?::\s*([A-Za-z_$][\w$]*))?/.exec(parts[k]);
                if (pm) add(pm[2] || pm[1]);
            }
        }

        return order;
    }

    /** 把該頁所有 inline script 串成一個 IIFE，並在結尾把頂層名稱發佈到 window。 */
    function buildWrappedScript(bodies) {
        var joined = bodies.join('\n;\n');
        var names = collectDeclaredNames(joined);

        // 開頭放 void 0; 是為了讓 script 自己的 "use strict" 不成為 directive。
        var out = '(function () {\nvoid 0;\n' + joined + '\n;';
        for (var i = 0; i < names.length; i++) {
            var n = names[i];
            out += '\ntry { if (typeof ' + n + ' !== "undefined") window[' + JSON.stringify(n) + '] = ' + n + '; } catch (e) {}';
        }
        out += '\n})();';

        return { code: out, names: names.length, inLen: joined.length };
    }

    /* ---------------------------------------------------------
     * 分析待重跑的 script（在動到畫面前先做完，確信才換頁）
     * 回傳 null 表示無法確信 → 呼叫端退回整頁導覽。
     * --------------------------------------------------------- */
    function analyzeScripts(root) {
        var nodes = root.querySelectorAll('script');
        var inlineNodes = [];
        var bodies = [];

        for (var i = 0; i < nodes.length; i++) {
            var s = nodes[i];
            // 外部 script 的執行順序無法在串接後保證 → 不軟導覽。
            if (s.src) { dbg = { bail: 'external-script', src: s.src }; return null; }
            inlineNodes.push(s);
            bodies.push(s.textContent || '');
        }

        if (!inlineNodes.length) { dbg = { bail: 'no-scripts' }; return { nodes: [], code: null }; }

        var built = buildWrappedScript(bodies);
        dbg = { scripts: inlineNodes.length, names: built.names, inLen: built.inLen, outLen: built.code.length };
        return { nodes: inlineNodes, code: built.code };
    }

    /* ---------------------------------------------------------
     * 執行計畫：以一個新的 <script> 取代原本所有 inline script
     * 期間臨時包裝 addEventListener，讓 DOMContentLoaded 回呼立即執行一次。
     * --------------------------------------------------------- */
    function executePlan(plan) {
        if (!plan || !plan.nodes.length || !plan.code) return;

        var first = plan.nodes[0];
        if (!first.parentNode) return;

        var docAdd = document.addEventListener;
        var winAdd = window.addEventListener;
        var ready = document.readyState !== 'loading';

        function wrap(add, thisArg) {
            if (!ready) return add;
            return function (type, fn, opts) {
                if (type === 'DOMContentLoaded' && typeof fn === 'function') {
                    try { fn.call(thisArg, new Event('DOMContentLoaded')); } catch (_) { /* ignore */ }
                    return;
                }
                return add.call(thisArg, type, fn, opts);
            };
        }

        try { document.addEventListener = wrap(docAdd, document); } catch (_) { /* ignore */ }
        try { window.addEventListener = wrap(winAdd, window); } catch (_) { /* ignore */ }

        var prevTrack = trackEnabled;
        trackEnabled = true;

        try {
            var s = document.createElement('script');
            s.setAttribute('data-fengbro-soft-ran', '1');
            s.textContent = plan.code;
            first.parentNode.insertBefore(s, first);

            for (var i = 0; i < plan.nodes.length; i++) {
                var n = plan.nodes[i];
                if (n.parentNode) n.parentNode.removeChild(n);
            }
        } finally {
            trackEnabled = prevTrack;
            try { document.addEventListener = docAdd; } catch (_) { /* ignore */ }
            try { window.addEventListener = winAdd; } catch (_) { /* ignore */ }
        }
    }

    /* ---------------------------------------------------------
     * 判斷連結是否可軟導覽
     * --------------------------------------------------------- */
    function resolveTarget(a) {
        if (!a || a.tagName !== 'A') return null;
        if (a.hasAttribute('download') || a.hasAttribute('data-no-soft-nav')) return null;
        var target = a.getAttribute('target');
        if (target && target !== '_self') return null;
        var href = a.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#') return null;
        if (/^(mailto:|tel:|javascript:|blob:|data:)/i.test(href)) return null;
        var url;
        try { url = new URL(a.href, location.href); } catch (_) { return null; }
        if (url.origin !== location.origin) return null;
        if (!isWhitelisted(pageOf(url))) return null;
        return url;
    }

    /* ---------------------------------------------------------
     * 同步外殼狀態（側邊欄 / 行動分頁的 active、群組展開、頁名）
     *
     * 不重寫 PHP 的 active 判斷邏輯，直接從「伺服器回傳的新頁面」複製狀態，
     * 以 data-menu-url 為鍵對應，避免邏輯兩份而漂移。
     * --------------------------------------------------------- */
    function syncLinkScope(doc, scopeSelector) {
        var liveScope = document.querySelector(scopeSelector);
        var nextScope = doc.querySelector(scopeSelector);
        if (!liveScope || !nextScope) return;

        var map = Object.create(null);
        var nextLinks = nextScope.querySelectorAll('a[data-menu-url]');
        var i, k;
        for (i = 0; i < nextLinks.length; i++) {
            k = nextLinks[i].getAttribute('data-menu-url');
            if (k && !(k in map)) map[k] = nextLinks[i];
        }

        var liveLinks = liveScope.querySelectorAll('a[data-menu-url]');
        for (i = 0; i < liveLinks.length; i++) {
            var live = liveLinks[i];
            var src = map[live.getAttribute('data-menu-url')];
            if (!src) continue;

            if (src.hasAttribute('aria-current')) live.setAttribute('aria-current', 'page');
            else live.removeAttribute('aria-current');

            live.classList.toggle('active', src.classList.contains('active'));
            live.classList.toggle('is-active', src.classList.contains('is-active'));

            var liveItem = live.closest('.menu-item');
            var srcItem = src.closest('.menu-item');
            if (liveItem && srcItem) liveItem.classList.toggle('active', srcItem.classList.contains('active'));
        }
    }

    function syncChrome(doc) {
        syncLinkScope(doc, '.sidebar');
        syncLinkScope(doc, '.m-tabbar');

        var nextGroups = doc.querySelectorAll('.menu-group[data-menu-group]');
        var groupMap = Object.create(null);
        var i;
        for (i = 0; i < nextGroups.length; i++) {
            var gk = nextGroups[i].getAttribute('data-menu-group');
            if (gk) groupMap[gk] = nextGroups[i];
        }
        var liveGroups = document.querySelectorAll('.menu-group[data-menu-group]');
        for (i = 0; i < liveGroups.length; i++) {
            var lg = liveGroups[i];
            var sg = groupMap[lg.getAttribute('data-menu-group')];
            if (!sg) continue;
            var open = sg.classList.contains('is-open');
            lg.classList.toggle('is-open', open);
            var toggle = lg.querySelector('.menu-group-toggle');
            if (toggle) toggle.setAttribute('aria-expanded', String(open));
        }

        var liveName = document.querySelector('.m-appbar-name');
        var nextName = doc.querySelector('.m-appbar-name');
        if (liveName && nextName) liveName.textContent = nextName.textContent;
    }

    /* ---------------------------------------------------------
     * 套用新頁面
     * --------------------------------------------------------- */
    function applyHtml(html, url, push) {
        var doc;
        try { doc = new DOMParser().parseFromString(html, 'text/html'); } catch (_) { return false; }

        var nextMain = doc.getElementById('mainContent');
        var curMain = document.getElementById('mainContent');
        if (!nextMain || !curMain) return false;

        var imported;
        try { imported = document.importNode(nextMain, true); } catch (_) { return false; }

        // 先分析，確信可重跑才動畫面；否則退回整頁導覽（此時 DOM 尚未變動）。
        var plan = analyzeScripts(imported);
        if (plan === null) return false;

        // 先移除上一頁註冊在 document / window 的監聽器，再換內容。
        dropPageListeners();

        curMain.replaceWith(imported);

        // 先重建基準快照（此時 DOM 等於伺服器版本、頁面 script 還沒跑），再執行 script。
        try { if (typeof window.fengbroResetSnapshot === 'function') window.fengbroResetSnapshot(); } catch (_) { /* ignore */ }
        try { if (typeof window.fengbroSnapshotMain === 'function') window.fengbroSnapshotMain(); } catch (_) { /* ignore */ }

        try { executePlan(plan); } catch (_) { /* ignore */ }

        if (doc.title) document.title = doc.title;

        try {
            var nb = doc.body;
            if (nb) {
                document.body.setAttribute('data-page', nb.getAttribute('data-page') || '');
                document.body.setAttribute('data-tool', nb.getAttribute('data-tool') || '');
            }
        } catch (_) { /* ignore */ }

        syncChrome(doc);

        if (push) {
            try { history.pushState({ fengbroSoftNav: true, key: keyOf(url) }, '', url.toString()); } catch (_) { /* ignore */ }
        }

        try { window.scrollTo(0, 0); } catch (_) { /* ignore */ }

        try {
            document.dispatchEvent(new CustomEvent('fengbro:soft-nav', {
                detail: { url: url.toString(), page: pageOf(url) }
            }));
        } catch (_) { /* ignore */ }

        return true;
    }

    function trimCache() {
        while (cache.size > CACHE_MAX) {
            var first = cache.keys().next();
            if (first.done) break;
            cache.delete(first.value);
        }
    }

    function fallback(url) {
        try { location.href = url.toString(); } catch (_) { /* ignore */ }
        return false;
    }

    /* ---------------------------------------------------------
     * 導覽
     * --------------------------------------------------------- */
    function navigate(url, push) {
        if (inflight) return inflight;

        var key = keyOf(url);
        var cached = cache.get(key);
        if (cached !== undefined) {
            if (applyHtml(cached, url, push)) return Promise.resolve(true);
            cache.delete(key);
        }

        inflight = fetch(url.toString(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Fengbro-Soft': '1' }
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.text();
        }).then(function (text) {
            if (text.indexOf('id="mainContent"') === -1 && text.indexOf("id='mainContent'") === -1) {
                throw new Error('mainContent not found');
            }
            cache.set(key, text);
            trimCache();
            if (!applyHtml(text, url, push)) throw new Error('apply failed');
            return true;
        }).catch(function () {
            return fallback(url);
        }).then(function (ok) {
            inflight = null;
            return ok;
        });

        return inflight;
    }

    /* ---------------------------------------------------------
     * 事件
     * --------------------------------------------------------- */
    document.addEventListener('click', function (e) {
        // handleGroupTabNav 等既有 inline handler 若已 preventDefault，就交給它。
        if (e.defaultPrevented) return;
        if (e.button !== 0) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        var a = e.target && e.target.closest ? e.target.closest('a') : null;
        var url = resolveTarget(a);
        if (!url) return;

        e.preventDefault();

        var here;
        try { here = keyOf(new URL(location.href)); } catch (_) { here = ''; }

        // 點目前這一頁：就地更新，等同重新整理但不白屏。
        if (keyOf(url) === here) {
            if (typeof window.fengbroReload === 'function') window.fengbroReload();
            return;
        }

        navigate(url, true);
    }, false);

    // 上一頁 / 下一頁：目標頁在白名單內才接管，否則交給瀏覽器整頁處理。
    window.addEventListener('popstate', function () {
        var url;
        try { url = new URL(location.href); } catch (_) { return; }
        if (!isWhitelisted(pageOf(url))) return;
        navigate(url, false);
    });

    /* ---------------------------------------------------------
     * 對外 API
     * --------------------------------------------------------- */
    window.fengbroSoftNav = {
        enabled: true,
        pages: WHITELIST,
        cache: cache,
        debug: function () { return dbg; },
        /** go('/index.php?page=notes') 或 go(urlObject, { push: false }) */
        go: function (url, options) {
            var u;
            try { u = (url instanceof URL) ? url : new URL(String(url), location.href); } catch (_) { return Promise.resolve(false); }
            if (u.origin !== location.origin || !isWhitelisted(pageOf(u))) return Promise.resolve(fallback(u));
            var push = !options || options.push !== false;
            return navigate(u, push);
        }
    };
})();
