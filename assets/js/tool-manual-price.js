/**
 * 手動價格紀錄 — 對齊 Appwrite ManualPriceTracker。
 *
 * v2（雲端同步）：資料以伺服器 manualprice 表為主（跨瀏覽器、跨裝置），
 * localStorage 降為離線快取與「首次遷移」來源。首次載入若伺服器為空且
 * 本機有舊資料，會把本機資料整批推上伺服器；之後一律讀寫伺服器。
 */
(function () {
  'use strict';

  const STORAGE_KEY = 'fengbro.tools.manualPrice.products';
  const MIGRATION_FLAG = 'fengbro.tools.manualPrice.migrated.v2';
  const MAX_PRODUCTS = 50;
  const MAX_RECORDS = 200;
  const CURRENCIES = ['TWD', 'USD', 'JPY'];

  function createId() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
    return 'id-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
  }

  function todayIso() {
    const n = new Date();
    return n.getFullYear() + '-' + String(n.getMonth() + 1).padStart(2, '0') + '-' + String(n.getDate()).padStart(2, '0');
  }

  function loadLocalCache() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      const list = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list : [];
    } catch (e) {
      return [];
    }
  }

  function saveLocalCache(list) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(list.slice(0, MAX_PRODUCTS)));
    } catch (e) { /* ignore quota errors */ }
  }

  function clearLocalCache() {
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch (e) { /* ignore */ }
  }

  function serverPayload(product) {
    return {
      name: product.name,
      currency: product.currency || 'TWD',
      records: (product.records || []).map((r) => ({
        id: r.id,
        price: Number(r.price),
        date: r.date,
        ...(r.note ? { note: r.note } : {}),
      })),
      localId: product.localId || product.id || undefined,
    };
  }

  async function fetchServerList() {
    const res = await fetch('manual_price_api.php', { cache: 'no-store' });
    const json = await res.json();
    if (!Array.isArray(json)) throw new Error((json && json.error) || '讀取伺服器資料失敗');
    return json;
  }

  async function createServerProduct(payload) {
    const res = await fetch('manual_price_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (!json || typeof json.id !== 'string') {
      throw new Error((json && json.error) || '伺服器新增失敗');
    }
    return json;
  }

  async function updateServerProduct(id, payload) {
    const res = await fetch('manual_price_api.php?id=' + encodeURIComponent(id), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (!json || typeof json.id !== 'string') {
      throw new Error((json && json.error) || '伺服器儲存失敗');
    }
    return json;
  }

  async function deleteServerProduct(id) {
    const res = await fetch('manual_price_api.php?action=delete&id=' + encodeURIComponent(id), { cache: 'no-store' });
    const json = await res.json();
    if (!json || json.success !== true) {
      throw new Error((json && json.error) || '伺服器刪除失敗');
    }
  }

  function formatPrice(price, currency) {
    if (price == null || Number.isNaN(Number(price))) return '--';
    return new Intl.NumberFormat('zh-TW').format(Number(price)) + ' ' + (currency || 'TWD');
  }

  function escapeHtml(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function csvEscape(v) {
    const s = v == null ? '' : String(v);
    if (/[",\n]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
    return s;
  }

  function exportCsv(products) {
    const rows = [['name', 'currency', 'price', 'date', 'note', 'productId', 'recordId']];
    products.forEach((p) => {
      if (!p.records || !p.records.length) {
        rows.push([p.name, p.currency || 'TWD', '', '', '', p.id, '']);
        return;
      }
      p.records.forEach((r) => {
        rows.push([p.name, p.currency || 'TWD', r.price, r.date, r.note || '', p.id, r.id]);
      });
    });
    const text = rows.map((r) => r.map(csvEscape).join(',')).join('\n');
    const blob = new Blob(['\ufeff' + text], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'manual-price-' + todayIso() + '.csv';
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1500);
  }

  function parseCsv(text) {
    const lines = String(text || '').replace(/^\ufeff/, '').split(/\r?\n/).filter((l) => l.trim());
    if (lines.length < 2) return [];
    const split = (line) => {
      const out = [];
      let cur = '';
      let q = false;
      for (let i = 0; i < line.length; i++) {
        const ch = line[i];
        if (q) {
          if (ch === '"' && line[i + 1] === '"') {
            cur += '"';
            i++;
          } else if (ch === '"') q = false;
          else cur += ch;
        } else if (ch === '"') q = true;
        else if (ch === ',') {
          out.push(cur);
          cur = '';
        } else cur += ch;
      }
      out.push(cur);
      return out;
    };
    const headers = split(lines[0]).map((h) => h.trim().toLowerCase());
    const idx = (names) => {
      for (const n of names) {
        const i = headers.indexOf(n);
        if (i >= 0) return i;
      }
      return -1;
    };
    const iName = idx(['name', '商品', '商品名稱']);
    const iCur = idx(['currency', '幣別', '貨幣']);
    const iPrice = idx(['price', '價錢', '價格']);
    const iDate = idx(['date', '日期']);
    const iNote = idx(['note', '備註', '註記']);
    const iPid = idx(['productid', 'product_id', '商品id']);
    const iRid = idx(['recordid', 'record_id', '紀錄id']);
    if (iName < 0) return [];

    const map = new Map();
    for (let li = 1; li < lines.length; li++) {
      const cols = split(lines[li]);
      const name = (cols[iName] || '').trim();
      if (!name) continue;
      const currency = CURRENCIES.includes((cols[iCur] || 'TWD').trim().toUpperCase())
        ? (cols[iCur] || 'TWD').trim().toUpperCase()
        : 'TWD';
      const pid = (iPid >= 0 && cols[iPid] ? cols[iPid].trim() : '') || createId();
      const key = pid || name + '|' + currency;
      if (!map.has(key)) {
        map.set(key, {
          id: pid || createId(),
          name,
          currency,
          createdAt: Date.now(),
          updatedAt: Date.now(),
          records: [],
        });
      }
      const product = map.get(key);
      product.name = name;
      product.currency = currency;
      const priceRaw = iPrice >= 0 ? String(cols[iPrice] || '').replace(/,/g, '').trim() : '';
      if (priceRaw !== '') {
        const price = Number(priceRaw);
        if (Number.isFinite(price) && price >= 0) {
          product.records.push({
            id: (iRid >= 0 && cols[iRid] ? cols[iRid].trim() : '') || createId(),
            price,
            date: (iDate >= 0 && cols[iDate] ? cols[iDate].trim() : '') || todayIso(),
            note: iNote >= 0 && cols[iNote] ? cols[iNote].trim() : undefined,
          });
        }
      }
      product.updatedAt = Date.now();
      if (product.records.length > MAX_RECORDS) {
        product.records = product.records.slice(-MAX_RECORDS);
      }
    }
    return Array.from(map.values()).slice(0, MAX_PRODUCTS);
  }

  function mergeProducts(existing, incoming) {
    const byId = new Map(existing.map((p) => [p.id, { ...p, records: [...(p.records || [])] }]));
    const byName = new Map(existing.map((p) => [p.name + '|' + (p.currency || 'TWD'), p.id]));
    incoming.forEach((p) => {
      let id = p.id;
      if (!byId.has(id)) {
        const alias = byName.get(p.name + '|' + (p.currency || 'TWD'));
        if (alias) id = alias;
      }
      if (byId.has(id)) {
        const cur = byId.get(id);
        cur.name = p.name || cur.name;
        cur.currency = p.currency || cur.currency;
        const rmap = new Map((cur.records || []).map((r) => [r.id, r]));
        (p.records || []).forEach((r) => rmap.set(r.id, r));
        cur.records = Array.from(rmap.values())
          .sort((a, b) => a.date.localeCompare(b.date))
          .slice(-MAX_RECORDS);
        cur.updatedAt = Date.now();
      } else {
        byId.set(p.id, p);
        byName.set(p.name + '|' + (p.currency || 'TWD'), p.id);
      }
    });
    return Array.from(byId.values()).slice(0, MAX_PRODUCTS);
  }

  function sparkline(points) {
    if (!points || points.length < 2) return '<p class="tool-muted">至少 2 筆紀錄後顯示走勢。</p>';
    const width = 320;
    const height = 80;
    const min = Math.min(...points);
    const max = Math.max(...points);
    const span = Math.max(1, max - min);
    const coords = points
      .map((v, i) => {
        const x = (i / (points.length - 1)) * width;
        const y = height - ((v - min) / span) * (height - 16) - 8;
        return x.toFixed(1) + ',' + y.toFixed(1);
      })
      .join(' ');
    return (
      '<svg viewBox="0 0 ' +
      width +
      ' ' +
      height +
      '" style="width:100%;height:80px;border:1px solid var(--border-color);border-radius:12px;background:var(--input-bg);">' +
      '<polyline points="' +
      coords +
      '" fill="none" stroke="var(--accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>'
    );
  }

  async function migrateLocalToServer() {
    let migrated = false;
    try {
      migrated = localStorage.getItem(MIGRATION_FLAG) === '1';
    } catch (e) { /* ignore */ }
    if (migrated) return;
    const local = loadLocalCache();
    let pushed = false;
    try {
      const serverList = await fetchServerList();
      if (serverList.length === 0 && local.length > 0) {
        for (const product of local) {
          try {
            await createServerProduct(serverPayload(product));
            pushed = true;
          } catch (e) { /* keep going */ }
        }
      }
    } catch (e) {
      // 伺服器暫時不可用：保留本機資料，下次載入再遷移
      return;
    }
    if (pushed || local.length === 0) {
      clearLocalCache();
      try {
        localStorage.setItem(MIGRATION_FLAG, '1');
      } catch (e) { /* ignore */ }
    }
  }

  function initManualPriceTool() {
    const root = document.getElementById('manualPriceTool');
    if (!root) return;

    let products = [];
    let selectedId = '';
    let serverError = '';
    let productSelectMode = false;
    let recordSelectMode = false;
    let selectedProductIds = new Set();
    let selectedRecordIds = new Set();

    const els = {
      list: root.querySelector('[data-mp-list]'),
      detail: root.querySelector('[data-mp-detail]'),
      name: root.querySelector('[data-mp-name]'),
      currency: root.querySelector('[data-mp-currency]'),
      price: root.querySelector('[data-mp-price]'),
      date: root.querySelector('[data-mp-date]'),
      note: root.querySelector('[data-mp-note]'),
      error: root.querySelector('[data-mp-error]'),
      file: root.querySelector('[data-mp-file]'),
    };

    if (els.date && !els.date.value) els.date.value = todayIso();

    function selected() {
      return products.find((p) => p.id === selectedId) || null;
    }

    function setError(msg) {
      if (els.error) els.error.textContent = msg || '';
    }

    function sortRecords(records) {
      return (records || []).slice().sort((a, b) => a.date.localeCompare(b.date) || String(a.id).localeCompare(String(b.id)));
    }

    function upsertProduct(serverProduct) {
      serverProduct.records = sortRecords(serverProduct.records);
      const idx = products.findIndex((p) => p.id === serverProduct.id);
      if (idx >= 0) products[idx] = serverProduct;
      else products.unshift(serverProduct);
      products = products.slice(0, MAX_PRODUCTS);
    }

    function refreshCache() {
      saveLocalCache(products);
    }

    function render() {
      if (els.error) {
        els.error.textContent = serverError ? '伺服器連線失敗：' + serverError + '（顯示本機快取，恢復連線後會自動同步）' : '';
      }
      if (selectedId && !products.some((p) => p.id === selectedId)) {
        selectedId = products.length ? products[0].id : '';
      }
      if (els.list) {
        const productBar =
          '<div class="mp-bulk-bar">' +
          '<button type="button" class="btn btn-ghost btn-sm" data-mp-toggle-products>' +
          (productSelectMode ? '完成選取' : '全選模式') +
          '</button>' +
          (productSelectMode
            ? '<button type="button" class="btn btn-ghost btn-sm" data-mp-select-all-products>全選</button>' +
              '<button type="button" class="btn btn-danger btn-sm" data-mp-bulk-del-products' +
              (selectedProductIds.size ? '' : ' disabled') +
              '>刪除選取（' +
              selectedProductIds.size +
              '）</button>'
            : '') +
          '</div>';
        if (!products.length) {
          els.list.innerHTML = productBar + '<p class="tool-muted">尚未新增商品。左側輸入名稱後按「新增商品」。</p>';
        } else {
          els.list.innerHTML =
            productBar +
            products
              .map((p) => {
                const last = (p.records || []).slice().sort((a, b) => b.date.localeCompare(a.date))[0];
                const active = p.id === selectedId ? ' is-active' : '';
                const checked = selectedProductIds.has(p.id) ? ' checked' : '';
                const check = productSelectMode
                  ? '<label class="mp-select"><input type="checkbox" data-mp-pick-product="' +
                    escapeHtml(p.id) +
                    '"' +
                    checked +
                    '> 選取</label>'
                  : '';
                return (
                  '<div class="mp-product-wrap">' +
                  check +
                  '<button type="button" class="mp-product-item' +
                  active +
                  '" data-id="' +
                  escapeHtml(p.id) +
                  '">' +
                  '<strong>' +
                  escapeHtml(p.name) +
                  '</strong>' +
                  '<span>' +
                  escapeHtml(p.currency || 'TWD') +
                  ' · ' +
                  (last ? formatPrice(last.price, p.currency) + ' · ' + escapeHtml(last.date) : '尚無紀錄') +
                  '</span></button></div>'
                );
              })
              .join('');
        }
      }
      if (!els.detail) return;
      const detailProduct = selected();
      if (!detailProduct) {
        els.detail.innerHTML = '<p class="tool-muted">請選擇或新增商品以登錄價格。</p>';
        return;
      }
      const records = (detailProduct.records || []).slice().sort((a, b) => b.date.localeCompare(a.date));
      const points = records.slice().reverse().map((r) => Number(r.price));
      const rows = records
        .map(
          (r) =>
            '<tr>' +
            (recordSelectMode
              ? '<td><input type="checkbox" data-mp-pick-record="' +
                escapeHtml(r.id) +
                '"' +
                (selectedRecordIds.has(r.id) ? ' checked' : '') +
                '></td>'
              : '') +
            '<td>' +
            escapeHtml(r.date) +
            '</td><td>' +
            escapeHtml(formatPrice(r.price, detailProduct.currency)) +
            '</td><td>' +
            escapeHtml(r.note || '') +
            '</td><td><button type="button" class="btn btn-ghost btn-sm" data-del-record="' +
            escapeHtml(r.id) +
            '">刪</button></td></tr>'
        )
        .join('');
      els.detail.innerHTML =
        '<div class="mp-detail-head"><div><h3 style="margin:0;">' +
        escapeHtml(detailProduct.name) +
        '</h3><p class="tool-muted" style="margin:4px 0 0;">幣別 ' +
        escapeHtml(detailProduct.currency || 'TWD') +
        ' · ' +
        records.length +
        ' 筆紀錄</p></div>' +
        '<div style="display:flex;gap:8px;flex-wrap:wrap;">' +
        '<button type="button" class="btn btn-ghost btn-sm" data-mp-toggle-records">' +
        (recordSelectMode ? '完成選取' : '全選模式') +
        '</button>' +
        (recordSelectMode
          ? '<button type="button" class="btn btn-ghost btn-sm" data-mp-select-all-records>全選</button>' +
            '<button type="button" class="btn btn-danger btn-sm" data-mp-bulk-del-records' +
            (selectedRecordIds.size ? '' : ' disabled') +
            '>刪除選取（' +
            selectedRecordIds.size +
            '）</button>'
          : '') +
        '<button type="button" class="btn btn-ghost" data-del-product="' +
        escapeHtml(detailProduct.id) +
        '"><i class="fa-solid fa-trash"></i> 刪除商品</button></div></div>' +
        sparkline(points) +
        (rows
          ? '<div style="overflow-x:auto;margin-top:12px;"><table class="table"><thead><tr>' +
            (recordSelectMode ? '<th></th>' : '') +
            '<th>日期</th><th>價格</th><th>備註</th><th></th></tr></thead><tbody>' +
            rows +
            '</tbody></table></div>'
          : '<p class="tool-muted" style="margin-top:12px;">尚無價格紀錄，請於上方表單新增。</p>');
    }

    async function loadFromServer() {
      try {
        await migrateLocalToServer();
        products = await fetchServerList();
        serverError = '';
      } catch (e) {
        serverError = (e && e.message) || '讀取失敗';
        products = loadLocalCache();
      }
      if (!selectedId && products.length) selectedId = products[0].id;
      refreshCache();
      render();
    }

    root.addEventListener('click', (ev) => {
      const t = ev.target.closest(
        '[data-id], [data-del-record], [data-del-product], [data-mp-add-product], [data-mp-add-record], [data-mp-export], [data-mp-import], [data-mp-toggle-products], [data-mp-select-all-products], [data-mp-bulk-del-products], [data-mp-pick-product], [data-mp-toggle-records], [data-mp-select-all-records], [data-mp-bulk-del-records], [data-mp-pick-record]'
      );
      if (!t) return;
      if (t.hasAttribute('data-mp-toggle-products')) {
        productSelectMode = !productSelectMode;
        if (!productSelectMode) selectedProductIds = new Set();
        render();
        return;
      }
      if (t.hasAttribute('data-mp-select-all-products')) {
        if (selectedProductIds.size === products.length) selectedProductIds = new Set();
        else selectedProductIds = new Set(products.map((p) => p.id));
        render();
        return;
      }
      if (t.hasAttribute('data-mp-pick-product')) {
        const id = t.getAttribute('data-mp-pick-product');
        if (selectedProductIds.has(id)) selectedProductIds.delete(id);
        else selectedProductIds.add(id);
        render();
        return;
      }
      if (t.hasAttribute('data-mp-bulk-del-products')) {
        const ids = Array.from(selectedProductIds);
        if (!ids.length) return;
        if (!confirm('確定刪除選取的 ' + ids.length + ' 個商品與價格紀錄？請輸入 DELETE 確認。\n\n請在下一則對話框輸入 DELETE。')) return;
        const typed = window.prompt('請輸入 DELETE 以確認批次刪除');
        if (typed !== 'DELETE') return;
        Promise.all(ids.map((id) => deleteServerProduct(id).then(() => id).catch(() => null)))
          .then((done) => {
            const removed = new Set(done.filter(Boolean));
            products = products.filter((p) => !removed.has(p.id));
            if (selectedId && removed.has(selectedId)) selectedId = products.length ? products[0].id : '';
            selectedProductIds = new Set();
            productSelectMode = false;
            refreshCache();
            render();
          });
        return;
      }
      if (t.hasAttribute('data-mp-toggle-records')) {
        recordSelectMode = !recordSelectMode;
        if (!recordSelectMode) selectedRecordIds = new Set();
        render();
        return;
      }
      if (t.hasAttribute('data-mp-select-all-records')) {
        const p = selected();
        const ids = (p && p.records ? p.records : []).map((r) => r.id);
        if (selectedRecordIds.size === ids.length) selectedRecordIds = new Set();
        else selectedRecordIds = new Set(ids);
        render();
        return;
      }
      if (t.hasAttribute('data-mp-pick-record')) {
        const id = t.getAttribute('data-mp-pick-record');
        if (selectedRecordIds.has(id)) selectedRecordIds.delete(id);
        else selectedRecordIds.add(id);
        render();
        return;
      }
      if (t.hasAttribute('data-mp-bulk-del-records')) {
        const p = selected();
        if (!p) return;
        const ids = selectedRecordIds;
        if (!ids.size) return;
        if (!confirm('確定刪除選取的 ' + ids.size + ' 筆價格紀錄？')) return;
        const nextRecords = (p.records || []).filter((r) => !ids.has(r.id));
        updateServerProduct(p.id, { records: nextRecords })
          .then((updated) => {
            upsertProduct(updated);
            selectedRecordIds = new Set();
            recordSelectMode = false;
            refreshCache();
            render();
          })
          .catch((e) => {
            serverError = (e && e.message) || '批次刪除失敗';
            render();
          });
        return;
      }
      if (t.hasAttribute('data-id')) {
        selectedId = t.getAttribute('data-id');
        render();
        return;
      }
      if (t.hasAttribute('data-del-product')) {
        const id = t.getAttribute('data-del-product');
        const row = products.find((p) => p.id === id);
        if (!row) return;
        if (!confirm('刪除此商品與所有價格紀錄？')) return;
        setError('');
        deleteServerProduct(id)
          .then(() => {
            products = products.filter((p) => p.id !== id);
            if (selectedId === id) selectedId = products.length ? products[0].id : '';
            refreshCache();
            render();
          })
          .catch((e) => {
            serverError = (e && e.message) || '刪除失敗';
            render();
          });
        return;
      }
      if (t.hasAttribute('data-del-record')) {
        const p = selected();
        if (!p) return;
        const recordId = t.getAttribute('data-del-record');
        const nextRecords = (p.records || []).filter((r) => r.id !== recordId);
        setError('');
        updateServerProduct(p.id, { records: nextRecords })
          .then((updated) => {
            upsertProduct(updated);
            refreshCache();
            render();
          })
          .catch((e) => {
            serverError = (e && e.message) || '刪除紀錄失敗';
            render();
          });
        return;
      }
      if (t.hasAttribute('data-mp-add-product')) {
        const name = (els.name && els.name.value.trim()) || '';
        if (!name) {
          setError('請輸入商品名稱');
          return;
        }
        if (products.length >= MAX_PRODUCTS) {
          setError('最多 ' + MAX_PRODUCTS + ' 個商品');
          return;
        }
        const currency = els.currency ? els.currency.value : 'TWD';
        const product = {
          id: createId(),
          name,
          currency,
          createdAt: Date.now(),
          updatedAt: Date.now(),
          records: [],
        };
        setError('');
        createServerProduct(serverPayload(product))
          .then((created) => {
            products.unshift(created);
            selectedId = created.id;
            if (els.name) els.name.value = '';
            refreshCache();
            render();
          })
          .catch((e) => {
            serverError = (e && e.message) || '新增失敗';
            render();
          });
        return;
      }
      if (t.hasAttribute('data-mp-add-record')) {
        const p = selected();
        if (!p) {
          setError('請先選擇或新增商品');
          return;
        }
        const raw = els.price ? String(els.price.value).replace(/,/g, '').trim() : '';
        const price = Number(raw);
        if (!Number.isFinite(price) || price < 0) {
          setError('請輸入有效價錢（0 或正數）');
          return;
        }
        const date = (els.date && els.date.value) || todayIso();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
          setError('請選擇有效日期');
          return;
        }
        const nextRecords = (p.records || []).slice();
        nextRecords.push({
          id: createId(),
          price,
          date,
          note: els.note && els.note.value.trim() ? els.note.value.trim() : undefined,
        });
        if (nextRecords.length > MAX_RECORDS) nextRecords.splice(0, nextRecords.length - MAX_RECORDS);
        setError('');
        updateServerProduct(p.id, { records: nextRecords })
          .then((updated) => {
            upsertProduct(updated);
            if (els.price) els.price.value = '';
            if (els.note) els.note.value = '';
            refreshCache();
            render();
          })
          .catch((e) => {
            serverError = (e && e.message) || '登錄失敗';
            render();
          });
        return;
      }
      if (t.hasAttribute('data-mp-export')) {
        exportCsv(products.length ? products : loadLocalCache());
        return;
      }
      if (t.hasAttribute('data-mp-import')) {
        if (els.file) els.file.click();
      }
    });

    if (els.file) {
      els.file.addEventListener('change', () => {
        const file = els.file.files && els.file.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = () => {
          try {
            const incoming = parseCsv(String(reader.result || ''));
            if (!incoming.length) {
              setError('CSV 無有效資料');
              return;
            }
            const currentBase = serverError ? loadLocalCache() : products;
            const merged = mergeProducts(currentBase, incoming);
            const currentById = new Map(currentBase.map((p) => [p.id, p]));
            setError('');
            Promise.all(
              merged.map((p) => {
                const existing = currentById.get(p.id);
                return existing
                  ? updateServerProduct(p.id, {
                      name: p.name,
                      currency: p.currency,
                      records: p.records,
                    }).catch(() => null)
                  : createServerProduct(serverPayload(p)).catch(() => null);
              })
            )
              .then((results) => {
                const ok = results.filter(Boolean);
                if (ok.length) {
                  ok.forEach(upsertProduct);
                  if (ok.length === merged.length) serverError = '';
                  selectedId = ok[0] ? ok[0].id : selectedId;
                  refreshCache();
                  render();
                }
                if (ok.length < merged.length) setError('部分 CSV 項目寫入失敗，請重試。');
              })
              .catch((e) => {
                setError('CSV 匯入失敗：' + ((e && e.message) || e));
              });
          } catch (e) {
            setError('CSV 匯入失敗');
          }
          els.file.value = '';
        };
        reader.readAsText(file, 'UTF-8');
      });
    }

    loadFromServer();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initManualPriceTool);
  } else {
    initManualPriceTool();
  }
})();
