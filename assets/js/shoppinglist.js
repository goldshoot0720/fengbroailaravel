const TABLE = 'shoppinglist';
initBatchDelete(TABLE);

function shoppingExistingIndex() {
    const index = new Map();
    for (const row of document.querySelectorAll('.shopping-item')) {
        index.set(shoppingNormalizeKey(row.dataset.name), row.dataset.id);
    }
    return index;
}
const SHOPPING_HEADER_ALIASES = {
    'name': 'name', '購物名稱': 'name', '商品名稱': 'name', '名稱': 'name',
    'planneddate': 'plannedDate', 'planned_date': 'plannedDate',
    '預定購買日': 'plannedDate', '購買日': 'plannedDate', '預定日期': 'plannedDate',
    'price': 'price', '預定價格': 'price', '價格': 'price', '金額': 'price',
    'currency': 'currency', '幣別': 'currency', '幣種': 'currency', '貨幣': 'currency',
    'quantity': 'quantity', '預定數量': 'quantity', '數量': 'quantity',
    'shop': 'shop', '預定商店': 'shop', '商店': 'shop', '店家': 'shop',
    'pickupmethod': 'pickupMethod', 'pickup_method': 'pickupMethod',
    '預定取貨方式': 'pickupMethod', '取貨方式': 'pickupMethod', '取貨': 'pickupMethod',
    'imageurl': 'imageUrl', 'image_url': 'imageUrl', 'image': 'imageUrl',
    '圖片': 'imageUrl', '圖片網址': 'imageUrl', '商品圖片': 'imageUrl', '商品圖片網址': 'imageUrl',
    'account': 'account', '帳號': 'account',
    'note': 'note', '備註': 'note'
};
const SHOPPING_CSV_HEADERS = ['name', 'plannedDate', 'price', 'currency', 'quantity', 'shop', 'pickupMethod', 'imageUrl', 'account', 'note'];
const CURRENCY_ALIASES = {
    'TWD': 'TWD', 'twd': 'TWD', '台幣': 'TWD', '新台幣': 'TWD',
    'USD': 'USD', 'usd': 'USD', '美元': 'USD', '美金': 'USD',
    'JPY': 'JPY', 'jpy': 'JPY', '日圓': 'JPY', '日幣': 'JPY',
    'CNY': 'CNY', 'cny': 'CNY', '人民幣': 'CNY', 'RMB': 'CNY', 'rmb': 'CNY'
};

function shoppingFormEls() {
    return {
        form: document.getElementById('shoppingForm'),
        id: document.getElementById('shoppingId'),
        title: document.getElementById('shoppingFormTitle'),
        save: document.getElementById('shoppingSaveBtn'),
        name: document.getElementById('shoppingName'),
        plannedDate: document.getElementById('shoppingPlannedDate'),
        price: document.getElementById('shoppingPrice'),
        currency: document.getElementById('shoppingCurrency'),
        quantity: document.getElementById('shoppingQuantity'),
        shop: document.getElementById('shoppingShop'),
        pickupMethod: document.getElementById('shoppingPickupMethod'),
        pickupCustom: document.getElementById('shoppingPickupCustom'),
        imageUrl: document.getElementById('shoppingImageUrl'),
        account: document.getElementById('shoppingAccount'),
        note: document.getElementById('shoppingNote')
    };
}

const PICKUP_CUSTOM_VALUE = '__custom__';

function shoppingResolvePickupValue(els) {
    if (els.pickupMethod.value === PICKUP_CUSTOM_VALUE) {
        return (els.pickupCustom.value || '').trim();
    }
    return els.pickupMethod.value;
}

function setShoppingPickupValue(els, value) {
    const v = (value || '').trim();
    if (!v) {
        els.pickupMethod.value = '';
        els.pickupCustom.value = '';
        els.pickupCustom.style.display = 'none';
        return;
    }
    if (v !== PICKUP_CUSTOM_VALUE && Array.from(els.pickupMethod.options).some(option => option.value === v)) {
        els.pickupMethod.value = v;
        els.pickupCustom.value = '';
        els.pickupCustom.style.display = 'none';
    } else {
        els.pickupMethod.value = PICKUP_CUSTOM_VALUE;
        els.pickupCustom.value = v;
        els.pickupCustom.style.display = '';
    }
}

function toggleShoppingPickupCustom(select) {
    const custom = document.getElementById('shoppingPickupCustom');
    if (!custom) return;
    custom.style.display = select.value === PICKUP_CUSTOM_VALUE ? '' : 'none';
    if (select.value !== PICKUP_CUSTOM_VALUE) custom.value = '';
}

let shoppingPhotoUrl = '';
let shoppingSaving = false;

function renderShoppingImage(url) {
    const img = document.getElementById('shoppingImagePreview');
    img.hidden = !url;
    if (url) img.src = url;
    else img.removeAttribute('src');
    document.getElementById('shoppingRemoveImage').hidden = !url;
}

function resetShoppingImage(url) {
    if (shoppingPhotoUrl) URL.revokeObjectURL(shoppingPhotoUrl);
    shoppingPhotoUrl = '';
    document.getElementById('shoppingImageFile').value = '';
    document.getElementById('shoppingImageUrl').value = url;
    renderShoppingImage(/^https?:\/\//i.test(url) ? url : '');
}

function shoppingUseImageUrl() {
    resetShoppingImage(document.getElementById('shoppingImageUrl').value);
}

const shoppingImageTypes = new Map([
    ['image/jpeg', 'jpg'], ['image/png', 'png'], ['image/gif', 'gif'], ['image/webp', 'webp']
]);

function shoppingChooseImage(input) {
    const file = input.files[0];
    if (!file) return;
    const message = document.getElementById('shoppingFormMessage');
    if (!shoppingImageTypes.has(file.type) || file.size > 50 * 1024 * 1024) {
        shoppingUseImageUrl();
        message.textContent = '請選擇 50 MB 以內的 JPG、PNG、GIF 或 WebP 圖片。';
        return;
    }
    message.textContent = file.name;
    if (shoppingPhotoUrl) URL.revokeObjectURL(shoppingPhotoUrl);
    shoppingPhotoUrl = URL.createObjectURL(file);
    document.getElementById('shoppingImageUrl').value = '';
    renderShoppingImage(shoppingPhotoUrl);
}

function openShoppingForm(id, copy = false) {
    if (shoppingSaving) return;
    const row = id ? document.querySelector('.shopping-item[data-id="' + CSS.escape(id) + '"]') : null;
    if (id && !row) return;
    const data = row ? row.dataset : {};
    const els = shoppingFormEls();
    els.form.reset();
    els.id.value = row && !copy ? id : '';
    els.title.textContent = copy ? '複製商品' : row ? '編輯商品' : '新增商品';
    els.save.textContent = row && !copy ? '儲存變更' : '新增商品';
    els.name.value = (data.name || '') + (copy ? ' (複製)' : '');
    els.plannedDate.value = data.planneddate || '';
    els.price.value = data.price || '0';
    els.currency.value = data.currency || 'TWD';
    els.quantity.value = data.quantity || '1';
    els.shop.value = data.shop || '';
    setShoppingPickupValue(els, data.pickup);
    resetShoppingImage(data.imageurl || '');
    els.account.value = data.account || '';
    els.note.value = data.note || '';
    document.getElementById('shoppingFormMessage').textContent = '';
    document.getElementById('shoppingEditor').showModal();
}

function copyShoppingItem(id) {
    openShoppingForm(id, true);
}

function closeShoppingForm() {
    if (!shoppingSaving) document.getElementById('shoppingEditor').close();
}

const shoppingEditor = document.getElementById('shoppingEditor');
shoppingEditor.addEventListener('cancel', event => { if (shoppingSaving) event.preventDefault(); });
shoppingEditor.addEventListener('close', () => resetShoppingImage(''));

async function saveShoppingItem(event) {
    event.preventDefault();
    if (shoppingSaving) return;
    const els = shoppingFormEls();
    const message = document.getElementById('shoppingFormMessage');
    if (!els.name.value.trim()) {
        message.textContent = '請填寫購物名稱';
        els.name.focus();
        return;
    }
    if (els.imageUrl.value && !/^https?:\/\//i.test(els.imageUrl.value)) {
        message.textContent = '請使用 http 或 https 圖片網址。';
        els.imageUrl.focus();
        return;
    }
    const file = document.getElementById('shoppingImageFile').files[0];
    const id = els.id.value;
    const controls = Array.from(els.form.elements).filter(control => !control.disabled);
    shoppingSaving = true;
    controls.forEach(control => { control.disabled = true; });
    try {
        if (file) {
            message.textContent = '照片上傳中…';
            const image = new File([file], 'shopping.' + shoppingImageTypes.get(file.type), { type: file.type });
            const result = await new Promise((resolve, reject) => uploadFileWithProgress(image, resolve, reject, {
                showModal: false,
                onProgress: progress => { message.textContent = '照片上傳中 ' + progress.percent + '%'; }
            }));
            resetShoppingImage(new URL(result.file, document.baseURI).href);
        }
        message.textContent = '儲存中…';
        const url = 'index.php?table=shoppinglist&action=' + (id ? 'update&id=' + encodeURIComponent(id) : 'create');
        const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: els.name.value.trim(), plannedDate: els.plannedDate.value || null,
                price: Number(els.price.value || 0), currency: els.currency.value, quantity: Number(els.quantity.value || 1),
                shop: els.shop.value.trim(), pickupMethod: shoppingResolvePickupValue(els), imageUrl: els.imageUrl.value.trim(),
                account: els.account.value.trim(), note: els.note.value }) });
        const res = await response.json();
        if (!res.success) throw new Error(res.error || '請再試一次');
        shoppingEditor.close();
        fengbroReload();
    } catch (error) {
        message.textContent = '儲存失敗：' + (error.message || error);
    } finally {
        controls.forEach(control => { control.disabled = false; });
        shoppingSaving = false;
    }
}

function deleteShoppingItem(id) {
    const row = document.querySelector('.shopping-item[data-id="' + CSS.escape(id) + '"]');
    const label = row ? ((row.dataset.name || '') + '') : '這筆商品';
    if (!confirm('確定要刪除「' + label + '」嗎？刪除不能復原。')) return;
    // 樂觀刪除：畫面立即移除，失敗才放回來（assets/js/inline-edit.js）。
    deleteInlineItem(id, { table: TABLE, skipConfirm: true });
}

function toggleShoppingStatus(button) {
    const select = document.getElementById('shoppingStatusFilter');
    select.value = select.value === button.dataset.shoppingFilter ? 'all' : button.dataset.shoppingFilter;
    filterShopping();
}

function clearShoppingFilters() {
    const input = document.getElementById('shoppingSearchInput');
    input.value = '';
    document.getElementById('shoppingStatusFilter').value = 'all';
    filterShopping();
    input.focus();
}

function filterShopping() {
    const text = document.getElementById('shoppingSearchInput').value.trim();
    const query = text.toLowerCase();
    const status = document.getElementById('shoppingStatusFilter').value;
    const rows = document.querySelectorAll('.shopping-item');
    let visible = 0;
    for (const row of rows) {
        row.hidden = !(row.dataset.search.toLowerCase().includes(query) && (status === 'all' || status === row.dataset.status));
        if (!row.hidden) visible++;
    }
    document.getElementById('shoppingVisibleCount').textContent = visible + ' 筆';
    document.getElementById('shoppingReset').hidden = !query && status === 'all';
    document.getElementById('shoppingNoMatch').hidden = visible > 0 || rows.length === 0;
    document.querySelectorAll('[data-shopping-filter]').forEach(button => {
        button.setAttribute('aria-pressed', String(button.dataset.shoppingFilter === status));
    });
    if (document.body.classList.contains('select-mode')) reconcileSelectionWithVisibleItems();
    const url = new URL(location.href);
    if (text) url.searchParams.set('q', text);
    else url.searchParams.delete('q');
    if (status !== 'all') url.searchParams.set('status', status);
    else url.searchParams.delete('status');
    if (url.href !== location.href) history.replaceState(history.state, '', url);
}
function restoreShoppingFilters() {
    const params = new URLSearchParams(location.search);
    const select = document.getElementById('shoppingStatusFilter');
    select.value = params.get('status') || 'all';
    if (!select.value) select.value = 'all';
    document.getElementById('shoppingSearchInput').value = params.get('q') || '';
    filterShopping();
}
restoreShoppingFilters();
document.addEventListener('fengbro:soft-reload', restoreShoppingFilters);

function shoppingCsvEscape(value) {
    const s = value == null ? '' : String(value);
    if (s.indexOf(',') !== -1 || s.indexOf('"') !== -1 || /[\r\n]/.test(s)) {
        return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
}

function exportShoppingCsv() {
    const rows = document.querySelectorAll('.shopping-item');
    if (rows.length === 0) {
        alert('尚無可匯出的購物清單');
        return;
    }
    const lines = [SHOPPING_CSV_HEADERS.join(',')];
    rows.forEach(function (row) {
        lines.push([
            row.dataset.name || '',
            row.dataset.planneddate || '',
            row.dataset.price || '0',
            row.dataset.currency || 'TWD',
            row.dataset.quantity || '1',
            row.dataset.shop || '',
            row.dataset.pickup || '',
            row.dataset.imageurl || '',
            row.dataset.account || '',
            row.dataset.note || ''
        ].map(shoppingCsvEscape).join(','));
    });
    const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'fengbro-shopping-' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

// ---- CSV 匯入 ----
let shoppingImportData = [];
let shoppingImportErrors = [];

function shoppingNormalizeKey(text) {
    return (text || '').trim().toLowerCase();
}

function shoppingMapHeader(raw) {
    const key = shoppingNormalizeKey(raw).replace(/\s+/g, '').replace(/_/g, '');
    return SHOPPING_HEADER_ALIASES[key] || SHOPPING_HEADER_ALIASES[shoppingNormalizeKey(raw)] || null;
}

function shoppingParseNonNegative(value, emptyTo) {
    const s = (value || '').trim();
    if (s === '') return emptyTo;
    if (!/^\d+$/.test(s)) return null;
    return Number(s);
}

function shoppingNormalizeCsvDate(value) {
    const s = (value || '').trim();
    if (!s) return '';
    let iso = '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        iso = s;
    } else {
        const m = s.match(/^(\d{4})[\/.](\d{1,2})[\/.](\d{1,2})$/);
        if (!m) return null;
        iso = m[1] + '-' + String(Number(m[2])).padStart(2, '0') + '-' + String(Number(m[3])).padStart(2, '0');
    }
    const d = new Date(iso + 'T00:00:00.000Z');
    if (isNaN(d.getTime()) || d.toISOString().slice(0, 10) !== iso) return null;
    return iso;
}

function handleShoppingCsvFile(input) {
    const file = input.files && input.files[0];
    input.value = '';
    if (!file) return;
    if (!/\.csv$/i.test(file.name)) {
        alert('請選擇 CSV 檔案');
        return;
    }
    const reader = new FileReader();
    reader.onload = function () {
        const preview = parseShoppingCsvText(String(reader.result || ''));
        shoppingImportData = preview.data;
        shoppingImportErrors = preview.errors;
        renderShoppingImportPreview();
    };
    reader.onerror = function () { alert('讀取 CSV 檔案失敗'); };
    reader.readAsText(file, 'UTF-8');
}

function parseShoppingCsvRecords(text) {
    const source = text.replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n');
    const records = [];
    let row = [], field = '', quoted = false;
    for (let i = 0; i < source.length; i++) {
        const ch = source[i];
        if (ch === '"') {
            if (quoted && source[i + 1] === '"') {
                field += '"';
                i++;
            } else {
                quoted = !quoted;
            }
        } else if (!quoted && (ch === ',' || ch === '\n')) {
            row.push(field);
            field = '';
            if (ch === '\n') {
                if (row.length > 1 || row[0].trim()) records.push(row);
                row = [];
            }
        } else {
            field += ch;
        }
    }
    if (quoted) throw new Error('CSV 引號未關閉');
    if (row.length || field.trim()) records.push([...row, field]);
    return records;
}

function parseShoppingCsvText(text) {
    const errors = [];
    const data = [];
    let lines;
    try {
        lines = parseShoppingCsvRecords(text);
    } catch (error) {
        return { data, errors: [error.message] };
    }
    if (lines.length < 2) {
        errors.push('CSV 檔案至少需要表頭和一行資料');
        return { data: data, errors: errors };
    }
    const headers = lines[0];
    const colIndex = {};
    headers.forEach(function (h, i) {
        const mapped = shoppingMapHeader(h);
        if (mapped && colIndex[mapped] == null) colIndex[mapped] = i;
    });
    if (colIndex.name == null) {
        errors.push('表頭缺少必要欄位 name（購物名稱）');
        return { data: data, errors: errors };
    }

    for (let i = 1; i < lines.length; i++) {
        const values = lines[i];
        const cell = function (header) {
            const idx = colIndex[header];
            return idx == null ? '' : (values[idx] || '');
        };
        const lineNo = i + 1;
        const fail = function (msg) { errors.push('第 ' + lineNo + ' 列：' + msg); };

        const name = cell('name').trim();
        if (!name) { fail('name 欄位不能為空'); continue; }
        if (name.length > 100) { fail('購物名稱最多 100 個字元'); continue; }

        const price = shoppingParseNonNegative(cell('price'), 0);
        if (price === null) { fail('預定價格必須是 0 以上的整數'); continue; }
        const quantity = shoppingParseNonNegative(cell('quantity'), 1);
        if (quantity === null) { fail('預定數量必須是 0 以上的整數'); continue; }
        if (quantity < 1) { fail('預定數量必須是 1 以上的整數'); continue; }

        const currencyRaw = cell('currency').trim();
        let currency = 'TWD';
        if (currencyRaw) {
            currency = CURRENCY_ALIASES[currencyRaw] || CURRENCY_ALIASES[shoppingNormalizeKey(currencyRaw)] || CURRENCY_ALIASES[currencyRaw.replace(/\s+/g, '').replace(/_/g, '')];
            if (!currency) { fail('幣別需為 台幣／美元／日圓／人民幣（TWD/USD/JPY/CNY）'); continue; }
        }

        const plannedDate = shoppingNormalizeCsvDate(cell('plannedDate'));
        if (plannedDate === null) { fail('預定購買日格式不正確'); continue; }

        const shop = cell('shop').trim();
        if (shop.length > 100) { fail('預定商店最多 100 個字元'); continue; }
        const pickupMethod = cell('pickupMethod').trim();
        if (pickupMethod.length > 30) { fail('預定取貨方式最多 30 個字元'); continue; }
        const imageUrlRaw = cell('imageUrl').trim();
        let imageUrl = imageUrlRaw;
        if (imageUrlRaw) {
            if (imageUrlRaw.length > 2000) { fail('商品圖片網址最多 2000 個字元'); continue; }
            if (!/^https?:\/\/.+/i.test(imageUrlRaw)) { fail('商品圖片網址需為完整 http 或 https 網址'); continue; }
        }
        const account = cell('account').trim();
        if (account.length > 200) { fail('帳號最多 200 個字元'); continue; }
        const note = cell('note');
        if (note.length > 3337) { fail('備註最多 3337 個字元'); continue; }

        data.push({
            name: name,
            plannedDate: plannedDate,
            price: price,
            currency: currency,
            quantity: quantity,
            shop: shop,
            pickupMethod: pickupMethod,
            imageUrl: imageUrl,
            account: account,
            note: note
        });
    }
    return { data: data, errors: errors };
}

function renderShoppingImportPreview() {
    const existingIndex = shoppingExistingIndex();
    const overlay = document.getElementById('shoppingImportOverlay');
    if (!overlay.open) overlay.showModal();
    document.getElementById('shoppingImportResult').style.display = 'none';
    const errorsEl = document.getElementById('shoppingImportErrors');
    if (shoppingImportErrors.length > 0) {
        errorsEl.style.display = '';
        errorsEl.innerHTML = '<strong>格式錯誤（不會寫入）</strong><br>' + shoppingImportErrors.map(function (e) { return '• ' + e; }).join('<br>');
    } else {
        errorsEl.style.display = 'none';
    }
    const rowsEl = document.getElementById('shoppingImportRows');
    if (shoppingImportData.length === 0) {
        rowsEl.innerHTML = '<p style="color:var(--muted-text);margin:10px 0;">沒有可匯入的資料列。</p>';
    } else {
        rowsEl.innerHTML = '<p style="font-weight:600;margin:10px 0 4px;">將匯入 ' + shoppingImportData.length + ' 筆</p>' +
            shoppingImportData.map(function (row) {
                const key = shoppingNormalizeKey(row.name);
                const existing = existingIndex.has(key);
                return '<div class="quota-import-row"><span class="qname">' + shoppingHtmlEscape(row.name) + '</span>' +
                    '<span style="color:var(--muted-text);font-size:0.82rem;">' + shoppingHtmlEscape(row.shop || '') + '</span>' +
                    '<span class="' + (existing ? 'qstatus-update' : 'qstatus-new') + '">' + (existing ? '更新' : '新增') + '</span></div>';
            }).join('');
    }
    document.getElementById('shoppingImportCancelBtn').style.display = '';
    document.getElementById('shoppingImportConfirmBtn').style.display = shoppingImportErrors.length === 0 && shoppingImportData.length > 0 ? '' : 'none';
}

function shoppingHtmlEscape(value) {
    return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function closeShoppingImport() {
    document.getElementById('shoppingImportOverlay').close();
    shoppingImportData = [];
    shoppingImportErrors = [];
}

let shoppingImporting = false;
document.getElementById('shoppingImportOverlay').addEventListener('cancel', event => {
    if (shoppingImporting) event.preventDefault();
});

async function executeShoppingImport() {
    if (shoppingImporting || !shoppingImportData.length) return;
    shoppingImporting = true;
    document.getElementById('shoppingImportCancelBtn').style.display = 'none';
    document.getElementById('shoppingImportConfirmBtn').style.display = 'none';
    const resultEl = document.getElementById('shoppingImportResult');
    resultEl.style.display = '';
    resultEl.textContent = '匯入中…';
    let successCount = 0;
    let failCount = 0;
    const index = shoppingExistingIndex();
    for (const row of shoppingImportData) {
        const key = shoppingNormalizeKey(row.name);
        const existingId = index.get(key);
        try {
            const payload = {
                name: row.name,
                plannedDate: row.plannedDate || null,
                price: row.price,
                currency: row.currency,
                quantity: row.quantity,
                shop: row.shop,
                pickupMethod: row.pickupMethod,
                imageUrl: row.imageUrl || '',
                account: row.account,
                note: row.note
            };
            const url = existingId
                ? 'index.php?action=update&table=' + encodeURIComponent(TABLE) + '&id=' + encodeURIComponent(existingId)
                : 'index.php?action=create&table=' + encodeURIComponent(TABLE);
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function (r) { return r.json(); });
            if (res.success) {
                successCount++;
                if (!existingId && res.id) index.set(key, res.id);
            } else {
                failCount++;
            }
        } catch (e) {
            failCount++;
        }
    }
    resultEl.textContent = '匯入完成：成功 ' + successCount + ' 筆 · 失敗 ' + failCount + ' 筆';
    if (failCount === 0) {
        setTimeout(function () {
            shoppingImporting = false;
            closeShoppingImport();
            fengbroReload();
        }, 1200);
    } else {
        shoppingImporting = false;
        document.getElementById('shoppingImportOverlay').addEventListener('close', () => fengbroReload(), { once: true });
        document.getElementById('shoppingImportCancelBtn').style.display = '';
    }
}
