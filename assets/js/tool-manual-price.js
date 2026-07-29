/**
 * 手動價格紀錄 — 對齊 Appwrite ManualPriceTracker（localStorage + CSV）。
 */
(function () {
  'use strict';

  const STORAGE_KEY = 'fengbro.tools.manualPrice.products';
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

  function loadProducts() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      const list = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list : [];
    } catch (e) {
      return [];
    }
  }

  function saveProducts(list) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list.slice(0, MAX_PRODUCTS)));
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

  function initManualPriceTool() {
    const root = document.getElementById('manualPriceTool');
    if (!root) return;

    let products = loadProducts();
    let selectedId = products[0] ? products[0].id : '';

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

    function render() {
      products = loadProducts();
      if (selectedId && !products.some((p) => p.id === selectedId)) {
        selectedId = products[0] ? products[0].id : '';
      }
      if (els.list) {
        if (!products.length) {
          els.list.innerHTML = '<p class="tool-muted">尚未新增商品。左側輸入名稱後按「新增商品」。</p>';
        } else {
          els.list.innerHTML = products
            .map((p) => {
              const last = (p.records || []).slice().sort((a, b) => b.date.localeCompare(a.date))[0];
              const active = p.id === selectedId ? ' is-active' : '';
              return (
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
                '</span></button>'
              );
            })
            .join('');
        }
      }
      const p = selected();
      if (!els.detail) return;
      if (!p) {
        els.detail.innerHTML = '<p class="tool-muted">請選擇或新增商品以登錄價格。</p>';
        return;
      }
      const records = (p.records || []).slice().sort((a, b) => b.date.localeCompare(a.date));
      const points = records
        .slice()
        .reverse()
        .map((r) => Number(r.price));
      const rows = records
        .map(
          (r) =>
            '<tr><td>' +
            escapeHtml(r.date) +
            '</td><td>' +
            escapeHtml(formatPrice(r.price, p.currency)) +
            '</td><td>' +
            escapeHtml(r.note || '') +
            '</td><td><button type="button" class="btn btn-ghost btn-sm" data-del-record="' +
            escapeHtml(r.id) +
            '">刪</button></td></tr>'
        )
        .join('');
      els.detail.innerHTML =
        '<div class="mp-detail-head"><div><h3 style="margin:0;">' +
        escapeHtml(p.name) +
        '</h3><p class="tool-muted" style="margin:4px 0 0;">幣別 ' +
        escapeHtml(p.currency || 'TWD') +
        ' · ' +
        records.length +
        ' 筆紀錄</p></div>' +
        '<button type="button" class="btn btn-ghost" data-del-product="' +
        escapeHtml(p.id) +
        '"><i class="fa-solid fa-trash"></i> 刪除商品</button></div>' +
        sparkline(points) +
        (rows
          ? '<div style="overflow-x:auto;margin-top:12px;"><table class="table"><thead><tr><th>日期</th><th>價格</th><th>備註</th><th></th></tr></thead><tbody>' +
            rows +
            '</tbody></table></div>'
          : '<p class="tool-muted" style="margin-top:12px;">尚無價格紀錄，請於上方表單新增。</p>');
    }

    root.addEventListener('click', (ev) => {
      const t = ev.target.closest('[data-id], [data-del-record], [data-del-product], [data-mp-add-product], [data-mp-add-record], [data-mp-export], [data-mp-import]');
      if (!t) return;
      if (t.hasAttribute('data-id')) {
        selectedId = t.getAttribute('data-id');
        render();
        return;
      }
      if (t.hasAttribute('data-del-product')) {
        if (!confirm('刪除此商品與所有價格紀錄？')) return;
        products = products.filter((p) => p.id !== t.getAttribute('data-del-product'));
        saveProducts(products);
        selectedId = products[0] ? products[0].id : '';
        render();
        return;
      }
      if (t.hasAttribute('data-del-record')) {
        const p = selected();
        if (!p) return;
        p.records = (p.records || []).filter((r) => r.id !== t.getAttribute('data-del-record'));
        p.updatedAt = Date.now();
        saveProducts(products);
        render();
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
        products.unshift(product);
        saveProducts(products);
        selectedId = product.id;
        if (els.name) els.name.value = '';
        setError('');
        render();
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
        p.records = p.records || [];
        p.records.push({
          id: createId(),
          price,
          date,
          note: els.note && els.note.value.trim() ? els.note.value.trim() : undefined,
        });
        if (p.records.length > MAX_RECORDS) p.records = p.records.slice(-MAX_RECORDS);
        p.updatedAt = Date.now();
        saveProducts(products);
        if (els.price) els.price.value = '';
        if (els.note) els.note.value = '';
        setError('');
        render();
        return;
      }
      if (t.hasAttribute('data-mp-export')) {
        exportCsv(loadProducts());
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
            products = mergeProducts(loadProducts(), incoming);
            saveProducts(products);
            selectedId = products[0] ? products[0].id : '';
            setError('');
            render();
          } catch (e) {
            setError('CSV 匯入失敗');
          }
          els.file.value = '';
        };
        reader.readAsText(file, 'UTF-8');
      });
    }

    render();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initManualPriceTool);
  } else {
    initManualPriceTool();
  }
})();
