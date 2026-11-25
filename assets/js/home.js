(() => {
    const home = document.querySelector('.home-workspace');
    if (!home) return;

    const compact = home.querySelector('#homeViewCompact');
    const full = home.querySelector('#homeViewFull');
    const content = full.querySelector('.home-dashboard-content');
    const loadState = full.querySelector('.home-load-state');
    const controls = home.querySelector('.home-view-toggle');
    const alerts = JSON.parse(home.querySelector('#homeAlerts').textContent);
    const preferenceKey = 'fengbro:home-view';
    let loading = false;

    function readPreference(key) {
        try { return localStorage.getItem(key); } catch { return null; }
    }

    function remember(key, value) {
        try { localStorage.setItem(key, value); } catch { /* Private browsing */ }
    }

    function refreshMetrics() {
        if (!home.isConnected || full.hidden || !content.firstElementChild) return;
        loadOfflineCacheMetric();
        loadMediaTrafficMetric();
    }

    async function showDashboard() {
        if (content.firstElementChild) {
            refreshMetrics();
            return;
        }
        if (loading) return;
        loading = true;
        loadState.hidden = false;
        loadState.querySelector('[role="status"]').textContent = '正在載入完整儀表…';
        loadState.querySelector('a').hidden = true;
        content.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch('home_panel.php?panel=dashboard');
            if (!response.ok) throw new Error('dashboard');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const dashboard = doc.querySelector('[data-dashboard]');
            if (!dashboard) throw new Error('dashboard');
            content.replaceChildren(dashboard);
            loadState.hidden = true;
            refreshMetrics();
        } catch {
            loadState.querySelector('[role="status"]').textContent = '完整儀表暫時無法載入。';
            loadState.querySelector('a').hidden = false;
        } finally {
            content.removeAttribute('aria-busy');
            loading = false;
        }
    }

    function selectView(view) {
        const showFull = view === 'full';
        compact.hidden = showFull;
        full.hidden = !showFull;
        for (const link of controls.querySelectorAll('a')) {
            if (link.dataset.homeView === view) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        }
        if (showFull) showDashboard();
    }

    controls.addEventListener('click', event => {
        const link = event.target.closest('a[data-home-view]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        const view = link.dataset.homeView;
        remember(preferenceKey, view);
        history.replaceState(history.state, '', link.href);
        selectView(view);
        window.dispatchEvent(new CustomEvent('fengbro:home-view-change', { detail: { view } }));
    });

    const initial = home.dataset.initialView === 'full' || readPreference(preferenceKey) === 'full' ? 'full' : 'compact';
    selectView(initial);

    function sendNotifications() {
        if (home.isConnected) window.FengbroNotifications?.sendDashboardNotifications(alerts);
    }
    sendNotifications();
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            sendNotifications();
            refreshMetrics();
        }
    });
    window.addEventListener('fengbro:media-traffic', () => {
        if (home.isConnected && !full.hidden) loadMediaTrafficMetric();
    });

    const noticeKeys = { tube: 'fengbroTubeNoticeDismissed', finance: 'fengbroFinanceAlertsDismissed' };
    const taipeiDay = new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'Asia/Taipei', year: 'numeric', month: '2-digit', day: '2-digit'
    });
    async function loadNotice(notice) {
        const kind = notice.dataset.notice;
        const key = noticeKeys[kind];
        if (readPreference(key) === taipeiDay.format(new Date())) return;
        try {
            const response = await fetch('home_panel.php?panel=' + kind);
            if (!response.ok) return;
            const message = await response.json();
            if (!message?.title || !home.isConnected || readPreference(key) === taipeiDay.format(new Date())) return;
            notice.querySelector('strong').textContent = message.title;
            notice.querySelector('a span').textContent = message.summary;
            notice.hidden = false;
            notice.querySelector('button').addEventListener('click', () => {
                remember(key, taipeiDay.format(new Date()));
                notice.hidden = true;
                controls.querySelector('[aria-current]').focus({ preventScroll: true });
            });
        } catch { /* Optional feed. Keep the workspace available offline. */ }
    }
    home.querySelectorAll('[data-notice]').forEach(loadNotice);

    function loadMediaTrafficMetric() {
        const value = document.getElementById('mediaTrafficValue');
        const hint = document.getElementById('mediaTrafficHint');
        if (!value || !window.FengbroMediaTraffic) return;
        const data = window.FengbroMediaTraffic.read();
        const bytes = Number(data.bytes) || 0;
        const units = ['B', 'KB', 'MB', 'GB'];
        let amount = bytes, unit = 0;
        while (amount >= 1024 && unit < units.length - 1) { amount /= 1024; unit++; }
        value.textContent = amount.toFixed(unit === 0 ? 0 : 1) + ' ' + units[unit];
        hint.textContent = (Number(data.requests) || 0) + ' 次媒體請求';
        const limit = 100 * 1024 * 1024 * 1024;
        if (bytes >= limit * .9) hint.textContent += ' · 接近上限';
        else if (bytes >= limit * .75) hint.textContent += ' · 用量偏高';
    }
    async function loadOfflineCacheMetric() {
        const valueEl = document.getElementById('offlineCacheMetricValue');
        const hintEl = document.getElementById('offlineCacheMetricHint');
        const totalEl = document.getElementById('offlineBreakdownTotal');
        const listEl = document.getElementById('offlineBreakdownList');
        const listHint = document.getElementById('offlineBreakdownHint');
        const labels = {
            video: '影片',
            music: '音樂',
            podcast: '播客',
            document: '文件',
            image: '圖片'
        };

        if (!window.FengbroMediaCache || !window.FengbroMediaCache.getAllStats) {
            if (valueEl) valueEl.textContent = 'N/A';
            if (hintEl) hintEl.textContent = '此瀏覽器不支援 IndexedDB';
            if (totalEl) totalEl.textContent = 'N/A';
            if (listEl) listEl.innerHTML = '<p style="color:var(--muted-text);margin:0;">此瀏覽器不支援 IndexedDB</p>';
            return;
        }
        try {
            const summary = await window.FengbroMediaCache.getAllStats();
            if (valueEl) valueEl.textContent = window.FengbroMediaCache.formatBytes(summary.totalSize || 0);
            if (hintEl) hintEl.textContent = (summary.totalItems || 0) + ' 項 · 點此管理快取';
            if (totalEl) totalEl.textContent = window.FengbroMediaCache.formatBytes(summary.totalSize || 0);
            if (listHint) listHint.textContent = (summary.totalItems || 0) + ' 項';
            if (listEl) {
                const maxSize = summary.maxSizePerKind || (500 * 1024 * 1024);
                const rows = (summary.kinds || []).map(function (row) {
                    const ratio = Math.min(100, Math.round(((row.totalSize || 0) / maxSize) * 100));
                    return `
                        <div class="storage-breakdown-row">
                            <div class="storage-breakdown-meta">
                                <span>${labels[row.kind] || row.kind}</span>
                                <small>${row.totalItems || 0} · ${window.FengbroMediaCache.formatBytes(row.totalSize || 0)}</small>
                            </div>
                            <div class="storage-breakdown-bar">
                                <div style="width:${ratio}%;"></div>
                            </div>
                        </div>
                    `;
                }).join('');
                listEl.innerHTML = rows || '<p style="color:var(--muted-text);margin:0;">尚無離線快取</p>';
            }
        } catch (e) {
            if (valueEl) valueEl.textContent = '--';
            if (hintEl) hintEl.textContent = '讀取失敗';
            if (totalEl) totalEl.textContent = '--';
            if (listEl) listEl.innerHTML = '<p style="color:#c1554a;margin:0;">讀取失敗</p>';
        }
    }

})();
