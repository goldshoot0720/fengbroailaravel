/**
 * 鋒兄新聞 UI — 關鍵字搜尋 + 台鐵便當門市 + 人口統計 + 來源管理（localStorage）。
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

  function formatInt(n) {
    if (n == null || n === '' || Number.isNaN(Number(n))) return '—';
    return Number(n).toLocaleString('zh-TW');
  }

  function formatDelta(n) {
    if (n == null || n === '' || Number.isNaN(Number(n))) {
      return { text: '—', cls: 'pop-delta-flat' };
    }
    const v = Number(n);
    if (v > 0) return { text: '+' + v.toLocaleString('zh-TW'), cls: 'pop-delta-up' };
    if (v < 0) return { text: v.toLocaleString('zh-TW'), cls: 'pop-delta-down' };
    return { text: '0', cls: 'pop-delta-flat' };
  }

  function buildYearChartSvg(years, accent) {
    const rows = Array.isArray(years) ? years.filter((y) => y && y.population != null) : [];
    if (rows.length < 2) {
      return '<p class="tool-muted">近十年資料不足，無法繪圖。</p>';
    }
    const w = 640;
    const h = 220;
    const padL = 54;
    const padR = 16;
    const padT = 18;
    const padB = 36;
    const vals = rows.map((r) => Number(r.population));
    const minV = Math.min.apply(null, vals);
    const maxV = Math.max.apply(null, vals);
    const span = Math.max(1, maxV - minV);
    const plotW = w - padL - padR;
    const plotH = h - padT - padB;
    const color = accent || '#2563eb';

    function xAt(i) {
      return padL + (rows.length === 1 ? plotW / 2 : (i / (rows.length - 1)) * plotW);
    }
    function yAt(v) {
      return padT + (1 - (v - minV) / span) * plotH;
    }

    const points = rows
      .map((r, i) => xAt(i).toFixed(1) + ',' + yAt(Number(r.population)).toFixed(1))
      .join(' ');
    const areaPoints =
      points +
      ' ' +
      xAt(rows.length - 1).toFixed(1) +
      ',' +
      (padT + plotH).toFixed(1) +
      ' ' +
      xAt(0).toFixed(1) +
      ',' +
      (padT + plotH).toFixed(1);

    const grid = [];
    for (let g = 0; g < 4; g++) {
      const t = g / 3;
      const y = padT + t * plotH;
      const val = Math.round(maxV - t * span);
      grid.push(
        '<line x1="' +
          padL +
          '" y1="' +
          y.toFixed(1) +
          '" x2="' +
          (w - padR) +
          '" y2="' +
          y.toFixed(1) +
          '" stroke="rgba(148,163,184,0.35)" stroke-width="1" />' +
          '<text x="' +
          (padL - 8) +
          '" y="' +
          (y + 4).toFixed(1) +
          '" text-anchor="end" font-size="11" fill="currentColor" opacity="0.65">' +
          escapeHtml(formatInt(val)) +
          '</text>'
      );
    }

    const dots = rows
      .map((r, i) => {
        const cx = xAt(i);
        const cy = yAt(Number(r.population));
        return (
          '<circle cx="' +
          cx.toFixed(1) +
          '" cy="' +
          cy.toFixed(1) +
          '" r="3.5" fill="' +
          color +
          '">' +
          '<title>' +
          escapeHtml(String(r.year) + '年：' + formatInt(r.population) + ' 人') +
          '</title></circle>' +
          '<text x="' +
          cx.toFixed(1) +
          '" y="' +
          (h - 12) +
          '" text-anchor="middle" font-size="11" fill="currentColor" opacity="0.75">' +
          escapeHtml(String(r.year)) +
          '</text>'
        );
      })
      .join('');

    return (
      '<svg class="pop-chart-svg" viewBox="0 0 ' +
      w +
      ' ' +
      h +
      '" role="img" aria-label="近十年人口走勢">' +
      '<rect x="0" y="0" width="' +
      w +
      '" height="' +
      h +
      '" fill="transparent" />' +
      grid.join('') +
      '<polygon points="' +
      areaPoints +
      '" fill="' +
      color +
      '" opacity="0.12" />' +
      '<polyline points="' +
      points +
      '" fill="none" stroke="' +
      color +
      '" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />' +
      dots +
      '</svg>'
    );
  }

  function renderPopulationRegion(region, options) {
    options = options || {};
    if (!region) {
      return '<p class="tool-muted">尚無資料。</p>';
    }
    const months = region.recentMonths || [];
    const years = region.years || [];
    const latest = region.latest || (months.length ? months[months.length - 1] : null);
    const delta = formatDelta(latest && latest.change);

    const monthRows = months
      .map((m) => {
        const d = formatDelta(m.change);
        return (
          '<tr>' +
          '<td>' +
          escapeHtml(m.label || m.period || '') +
          '</td>' +
          '<td>' +
          escapeHtml(formatInt(m.population)) +
          '</td>' +
          '<td class="' +
          d.cls +
          '">' +
          escapeHtml(d.text) +
          '</td></tr>'
        );
      })
      .join('');

    let gapNote = '';
    if (months.length >= 2) {
      let consecutive = true;
      for (let i = 1; i < months.length; i++) {
        const a = String(months[i - 1].period || '');
        const b = String(months[i].period || '');
        const ma = a.match(/^(\d{4})-(\d{2})$/);
        const mb = b.match(/^(\d{4})-(\d{2})$/);
        if (!ma || !mb) {
          consecutive = false;
          break;
        }
        const ia = Number(ma[1]) * 12 + Number(ma[2]);
        const ib = Number(mb[1]) * 12 + Number(mb[2]);
        if (ib - ia !== 1) {
          consecutive = false;
          break;
        }
      }
      if (!consecutive) {
        gapNote =
          '<p class="tool-muted" style="font-size:0.82rem;margin:0 0 10px;">部分月份公開資料尚未齊全，改顯示最近可得之月底人口；無連續上月時「新增人口數」以 — 表示。</p>';
      }
    }

    const sources = (region.sourceUrls || [])
      .slice(0, 3)
      .map((u) => {
        let label = u.replace(/^https?:\/\//, '').split('/')[0];
        if (u.indexOf('ris.gov') >= 0) label = '內政部戶政司';
        else if (u.indexOf('cab.tycg') >= 0) label = '桃園市民政局';
        else if (u.indexOf('zhongli-hro') >= 0) label = '中壢戶政';
        else if (u.indexOf('wikipedia') >= 0) label = '維基百科';
        return (
          '<a href="' +
          escapeHtml(u) +
          '" target="_blank" rel="noopener">' +
          escapeHtml(label) +
          '</a>'
        );
      })
      .join('');

    return (
      '<div class="pop-meta">' +
      '<div><strong>' +
      escapeHtml(region.name || '') +
      '</strong>' +
      (region.scope ? '<span class="tool-muted"> · ' + escapeHtml(region.scope) + '</span>' : '') +
      (latest
        ? '<div class="tool-muted" style="font-size:0.82rem;margin-top:4px;">最新 ' +
          escapeHtml(latest.label || latest.period || '') +
          ' · ' +
          escapeHtml(formatInt(latest.population)) +
          ' 人 · 增減 <span class="' +
          delta.cls +
          '">' +
          escapeHtml(delta.text) +
          '</span></div>'
        : '') +
      '</div></div>' +
      gapNote +
      '<div class="pop-table-wrap"><table class="pop-table"><thead><tr>' +
      '<th>月份</th><th>人口數</th><th>新增人口數</th>' +
      '</tr></thead><tbody>' +
      (monthRows || '<tr><td colspan="3" class="tool-muted">尚無近三個月資料</td></tr>') +
      '</tbody></table></div>' +
      '<div class="pop-chart-card"><h4>近十年走勢（年底人口）</h4>' +
      buildYearChartSvg(years, options.accent || '#2563eb') +
      '<p class="pop-chart-footnote">單位：人。新增人口數＝當月人口相對上月消長（正值增加、負值減少）。</p></div>' +
      (sources ? '<div class="pop-sources">' + sources + '</div>' : '')
    );
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
      popTy: root.querySelector('[data-news-pop-taoyuan]'),
      popZl: root.querySelector('[data-news-pop-zhongli]'),
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

    async function loadPopulation() {
      if (!els.popTy && !els.popZl) return;
      if (els.popTy) els.popTy.innerHTML = '<p class="tool-muted">讀取桃園人口統計…</p>';
      if (els.popZl) els.popZl.innerHTML = '<p class="tool-muted">讀取中壢人口統計…</p>';
      try {
        const res = await fetch('tools_api.php?action=news_population');
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '讀取失敗');
        const regions = data.regions || {};
        const headNote =
          '<div class="tool-muted" style="font-size:0.82rem;margin-bottom:10px;">' +
          (data.live ? '即時彙整' : '備援') +
          (data.fetchedAt ? ' · ' + escapeHtml(formatTime(data.fetchedAt)) : '') +
          (data.sourceLabel ? ' · ' + escapeHtml(data.sourceLabel) : '') +
          '</div>' +
          (data.warning ? '<p style="color:#b45309;margin:0 0 10px;">' + escapeHtml(data.warning) + '</p>' : '');

        if (els.popTy) {
          els.popTy.innerHTML =
            headNote + renderPopulationRegion(regions.taoyuan, { accent: '#2563eb' });
        }
        if (els.popZl) {
          els.popZl.innerHTML =
            headNote + renderPopulationRegion(regions.zhongli, { accent: '#0d9488' });
        }
      } catch (e) {
        const err = '<p style="color:#dc2626;">' + escapeHtml(e.message || '讀取失敗') + '</p>';
        if (els.popTy) els.popTy.innerHTML = err;
        if (els.popZl) els.popZl.innerHTML = err;
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
        '[data-news-search], [data-news-lock-all], [data-news-unlock-all], [data-news-reset-sites], [data-bento-focus], [data-bento-refresh], [data-news-pop-refresh]'
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
      if (t.hasAttribute('data-news-pop-refresh')) loadPopulation();
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

    loadDefaults().then(() => {
      loadBento();
      loadPopulation();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNewsTool);
  } else {
    initNewsTool();
  }
})();
