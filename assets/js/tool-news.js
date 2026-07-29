/**
 * 鋒兄新聞 UI — 關鍵字搜尋 + 台鐵便當門市 + 來源管理（localStorage）。
 */
(function () {
  'use strict';

  const SITES_KEY = 'fengbro.tools.news.sites';
  const QUERY_KEY = 'fengbro.tools.news.query';

  function escapeHtml(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function loadSitesFallback(defaults) {
    try {
      const raw = localStorage.getItem(SITES_KEY);
      if (!raw) return defaults.slice();
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed) || !parsed.length) return defaults.slice();
      // merge locked flags by id
      const map = new Map(defaults.map((s) => [s.id, { ...s }]));
      parsed.forEach((s) => {
        if (!s || !s.id) return;
        if (map.has(s.id)) {
          map.set(s.id, { ...map.get(s.id), locked: !!s.locked, name: s.name || map.get(s.id).name });
        } else {
          map.set(s.id, {
            id: s.id,
            name: s.name || s.id,
            domain: s.domain || '',
            homeUrl: s.homeUrl || '',
            adapter: s.adapter || 'generic-keyword-url',
            searchUrlTemplate: s.searchUrlTemplate || null,
            locked: !!s.locked,
          });
        }
      });
      return Array.from(map.values());
    } catch (e) {
      return defaults.slice();
    }
  }

  function saveSites(sites) {
    localStorage.setItem(SITES_KEY, JSON.stringify(sites));
  }

  function formatTime(iso) {
    if (!iso) return '';
    try {
      return new Date(iso).toLocaleString('zh-TW');
    } catch (e) {
      return iso;
    }
  }

  function initNewsTool() {
    const root = document.getElementById('fengbroNewsTool');
    if (!root) return;

    let sites = [];
    let busy = false;
    let bentoFocusOnly = true;

    const els = {
      query: root.querySelector('[data-news-query]'),
      results: root.querySelector('[data-news-results]'),
      siteList: root.querySelector('[data-news-sites]'),
      bento: root.querySelector('[data-news-bento]'),
      status: root.querySelector('[data-news-status]'),
      error: root.querySelector('[data-news-error]'),
    };

    function setError(msg) {
      if (els.error) els.error.textContent = msg || '';
    }
    function setStatus(msg) {
      if (els.status) els.status.textContent = msg || '';
    }

    function renderSites() {
      if (!els.siteList) return;
      const locked = sites.filter((s) => s.locked).length;
      els.siteList.innerHTML =
        '<div class="news-sites-meta">焦點來源 <strong>' +
        locked +
        '</strong> / ' +
        sites.length +
        '</div>' +
        '<div class="news-sites-grid">' +
        sites
          .map((s) => {
            return (
              '<label class="news-site-item">' +
              '<input type="checkbox" data-news-lock="' +
              escapeHtml(s.id) +
              '"' +
              (s.locked ? ' checked' : '') +
              '>' +
              '<span><strong>' +
              escapeHtml(s.name) +
              '</strong><small>' +
              escapeHtml(s.domain || '') +
              '</small></span></label>'
            );
          })
          .join('') +
        '</div>';
    }

    function renderArticles(payload) {
      if (!els.results) return;
      const articles = (payload && payload.articles) || [];
      const siteRows = (payload && payload.sites) || [];
      if (!articles.length) {
        const errs = siteRows
          .filter((s) => s.error)
          .slice(0, 6)
          .map((s) => escapeHtml(s.siteName) + '：' + escapeHtml(s.error))
          .join('<br>');
        els.results.innerHTML =
          '<p class="tool-muted">沒有符合結果。</p>' + (errs ? '<div class="tool-muted" style="font-size:0.86rem;margin-top:8px;">' + errs + '</div>' : '');
        return;
      }
      const cards = articles
        .map((a) => {
          return (
            '<a class="news-article-card" href="' +
            escapeHtml(a.url) +
            '" target="_blank" rel="noopener">' +
            '<div class="news-article-meta">' +
            '<span>' +
            escapeHtml(a.siteName || a.domain || '') +
            '</span>' +
            (a.publishedAt ? '<span>' + escapeHtml(formatTime(a.publishedAt)) + '</span>' : '') +
            '</div>' +
            '<strong>' +
            escapeHtml(a.title) +
            '</strong>' +
            (a.snippet ? '<p>' + escapeHtml(String(a.snippet).slice(0, 160)) + '</p>' : '') +
            '</a>'
          );
        })
        .join('');
      els.results.innerHTML =
        '<div class="news-results-head">共 <strong>' +
        articles.length +
        '</strong> 則 · 命中來源 ' +
        Number(payload.matchedSites || 0) +
        ' / ' +
        Number(payload.siteCount || siteRows.length) +
        (payload.fetchedAt ? ' · ' + escapeHtml(formatTime(payload.fetchedAt)) : '') +
        '</div><div class="news-article-grid">' +
        cards +
        '</div>';
    }

    async function loadDefaults() {
      try {
        const res = await fetch('tools_api.php?action=news_sites');
        const data = await res.json();
        const defaults = data.success && Array.isArray(data.sites) ? data.sites : [];
        sites = loadSitesFallback(defaults);
        saveSites(sites);
        renderSites();
      } catch (e) {
        setError('無法載入預設新聞來源');
      }
    }

    async function search() {
      if (busy) return;
      const query = (els.query && els.query.value.trim()) || '';
      if (!query) {
        setError('請輸入關鍵字');
        return;
      }
      localStorage.setItem(QUERY_KEY, query);
      busy = true;
      setError('');
      setStatus('搜尋中（多來源，可能需十數秒）…');
      if (els.results) els.results.innerHTML = '<p class="tool-muted">搜尋中…</p>';
      try {
        const res = await fetch('tools_api.php?action=news_search', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            query,
            sites: sites,
          }),
        });
        const data = await res.json();
        if (!data.success && !data.articles) throw new Error(data.error || '搜尋失敗');
        renderArticles(data);
        setStatus(
          '完成 · ' +
            Number(data.total || (data.articles || []).length) +
            ' 則（焦點來源 ' +
            sites.filter((s) => s.locked).length +
            '）'
        );
      } catch (e) {
        setError(e.message || '搜尋失敗');
        if (els.results) els.results.innerHTML = '';
      } finally {
        busy = false;
      }
    }

    async function loadBento() {
      if (!els.bento) return;
      els.bento.innerHTML = '<p class="tool-muted">讀取台鐵便當門市…</p>';
      try {
        const res = await fetch('tools_api.php?action=news_bento&focus=' + (bentoFocusOnly ? '1' : '0'));
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '讀取失敗');
        const stores = data.stores || [];
        const cards = stores
          .map((s) => {
            return (
              '<div class="bento-store-card">' +
              '<strong>' +
              escapeHtml(s.name) +
              '</strong>' +
              (s.stationHint ? '<span class="bento-hint">' + escapeHtml(s.stationHint) + '</span>' : '') +
              '<p>' +
              escapeHtml(s.detail || '') +
              '</p></div>'
            );
          })
          .join('');
        els.bento.innerHTML =
          '<div class="bento-head">' +
          '<div><strong>' +
          escapeHtml(data.sourceLabel || '台鐵便當門市') +
          '</strong>' +
          '<div class="tool-muted" style="font-size:0.82rem;">' +
          (data.live ? '即時' : '備援') +
          (data.fetchedAt ? ' · ' + escapeHtml(formatTime(data.fetchedAt)) : '') +
          ' · ' +
          Number(data.count || stores.length) +
          ' 家</div></div>' +
          '<div class="bento-actions">' +
          '<button type="button" class="btn btn-ghost btn-sm" data-bento-focus>' +
          (bentoFocusOnly ? '顯示全部' : '只看桃園／中壢') +
          '</button>' +
          '<button type="button" class="btn btn-ghost btn-sm" data-bento-refresh><i class="fa-solid fa-rotate"></i> 更新</button>' +
          '<a class="btn btn-ghost btn-sm" href="' +
          escapeHtml(data.sourceUrl || '#') +
          '" target="_blank" rel="noopener">官方頁</a></div></div>' +
          (data.warning ? '<p style="color:#b45309;">' + escapeHtml(data.warning) + '</p>' : '') +
          '<div class="bento-grid">' +
          cards +
          '</div>';
      } catch (e) {
        els.bento.innerHTML = '<p style="color:#dc2626;">' + escapeHtml(e.message || '讀取失敗') + '</p>';
      }
    }

    root.addEventListener('change', (ev) => {
      const t = ev.target;
      if (t && t.matches('[data-news-lock]')) {
        const id = t.getAttribute('data-news-lock');
        sites = sites.map((s) => (s.id === id ? { ...s, locked: !!t.checked } : s));
        saveSites(sites);
        renderSites();
      }
    });

    root.addEventListener('click', (ev) => {
      const t = ev.target.closest(
        '[data-news-search], [data-news-lock-all], [data-news-unlock-all], [data-news-reset-sites], [data-bento-focus], [data-bento-refresh]'
      );
      if (!t) return;
      if (t.hasAttribute('data-news-search')) search();
      if (t.hasAttribute('data-news-lock-all')) {
        sites = sites.map((s) => ({ ...s, locked: true }));
        saveSites(sites);
        renderSites();
      }
      if (t.hasAttribute('data-news-unlock-all')) {
        sites = sites.map((s) => ({ ...s, locked: false }));
        saveSites(sites);
        renderSites();
      }
      if (t.hasAttribute('data-news-reset-sites')) {
        localStorage.removeItem(SITES_KEY);
        loadDefaults();
      }
      if (t.hasAttribute('data-bento-focus')) {
        bentoFocusOnly = !bentoFocusOnly;
        loadBento();
      }
      if (t.hasAttribute('data-bento-refresh')) loadBento();
    });

    if (els.query) {
      const saved = localStorage.getItem(QUERY_KEY);
      if (saved) els.query.value = saved;
      els.query.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          search();
        }
      });
    }

    loadDefaults().then(loadBento);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNewsTool);
  } else {
    initNewsTool();
  }
})();
