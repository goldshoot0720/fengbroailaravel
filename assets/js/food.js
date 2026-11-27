const TABLE = 'food';
initBatchDelete(TABLE);

function handleAdd() {
    openModal();
}

function addDays(days) {
    const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei',
        year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
    const date = new Date(today + 'T00:00:00Z');
    date.setUTCDate(date.getUTCDate() + days);
    return date.toISOString().slice(0, 10);
}

function applyFoodPreset(name, amount, days, shop) {
    openModal();
    document.getElementById('name').value = name || '';
    document.getElementById('amount').value = amount || 1;
    document.getElementById('price').value = 0;
    document.getElementById('shop').value = shop || '';
    document.getElementById('todate').value = addDays(days);
    const nameInput = document.getElementById('name');
    if (nameInput) nameInput.focus();
}

const foodFilters = {
    q: document.getElementById('foodSearchInput'),
    status: document.getElementById('foodExpiryFilter'),
    year: document.getElementById('foodYearFilter'),
    month: document.getElementById('foodMonthFilter')
};

function clearFoodFilters() {
    Object.values(foodFilters).forEach(field => { field.value = ''; });
    foodFilters.status.value = 'all';
    filterFoods();
    foodFilters.q.focus();
}

function toggleFoodStatus(button) {
    const status = button.dataset.foodFilter;
    foodFilters.status.value = foodFilters.status.value === status ? 'all' : status;
    filterFoods();
}

function filterFoods() {
    const { q, status, year, month } = foodFilters;
    month.disabled = year.value === '__empty' || month.options.length === 1;
    for (const option of month.querySelectorAll('[data-years]')) {
        option.hidden = !!year.value && !option.dataset.years.split(' ').includes(year.value);
        option.disabled = option.hidden;
    }
    if (month.disabled || month.selectedOptions[0]?.disabled) month.value = '';
    const query = q.value.trim().toLowerCase();
    const rows = document.querySelectorAll('[data-food-item]');
    let visible = 0;
    let lowStock = 0;
    let value = 0;
    for (const row of rows) {
        const item = row.dataset;
        const low = Number(item.amount) <= 1;
        if (low) lowStock++;
        value += Number(item.amount) * Number(item.price);
        const expiryMatches = status.value === 'all' || status.value === item.expiry
            || (status.value === 'lowstock' && low)
            || (status.value === '7days' && ['today', '3days'].includes(item.expiry))
            || (status.value === '3days' && item.expiry === 'today');
        const yearMatches = !year.value || (year.value === '__empty' ? !item.year : item.year === year.value);
        row.hidden = !(expiryMatches && yearMatches && (!month.value || month.value === item.month)
            && (item.name + ' ' + item.shop).toLowerCase().includes(query));
        if (!row.hidden) visible++;
    }
    document.querySelector('[data-food-filter="lowstock"] strong').textContent = lowStock;
    document.getElementById('foodValue').textContent = '$' + value.toLocaleString('en-US');
    document.getElementById('foodVisibleCount').textContent = visible;
    document.getElementById('foodResetFilters').hidden = !(q.value || year.value || month.value || status.value !== 'all');
    document.getElementById('foodNoMatch').hidden = visible > 0 || rows.length === 0;
    document.querySelectorAll('[data-food-filter]').forEach(button => {
        button.setAttribute('aria-pressed', String(button.dataset.foodFilter === status.value));
    });
    if (document.body.classList.contains('select-mode')) reconcileSelectionWithVisibleItems();
    const url = new URL(location.href);
    for (const [key, field] of Object.entries(foodFilters)) {
        if (field.value && !(key === 'status' && field.value === 'all')) url.searchParams.set(key, field.value);
        else url.searchParams.delete(key);
    }
    if (url.href !== location.href) history.replaceState(history.state, '', url);
}

function restoreFoodFilters() {
    const params = new URLSearchParams(location.search);
    for (const [key, field] of Object.entries(foodFilters)) {
        field.value = params.get(key) || '';
        if (key === 'status' && !field.value) field.value = 'all';
    }
    filterFoods();
}

// 數量 +／−：畫面立即更新（Optimistic UI），連點會合併成一次請求；失敗就還原成伺服器上的數量。
const foodAmountSync = new Map();

function renderFoodAmount(id, amount) {
    const row = getRowById(id);
    if (!row) return;
    row.dataset.amount = String(amount);
    row.querySelector('.food-amount-value').textContent = amount;
    filterFoods();
}

function adjustFoodAmount(id, delta) {
    const source = getRowById(id);
    if (!source) return;
    const current = Number(source.dataset.amount || 0);
    const nextAmount = Math.max(0, current + delta);
    if (nextAmount === current) return;

    const state = foodAmountSync.get(id) || { confirmed: current, timer: null, request: null };
    state.target = nextAmount;
    foodAmountSync.set(id, state);
    renderFoodAmount(id, nextAmount);
    clearTimeout(state.timer);
    state.timer = setTimeout(() => flushFoodAmount(id), 350);
}

async function flushFoodAmount(id) {
    const state = foodAmountSync.get(id);
    if (!state) return;
    clearTimeout(state.timer);
    if (state.request) {
        await state.request;
        return flushFoodAmount(id);
    }
    const target = state.target;
    if (target === state.confirmed || !getRowById(id)) {
        foodAmountSync.delete(id);
        return;
    }
    state.request = fetch(`index.php?action=update&table=${TABLE}&id=${encodeURIComponent(id)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ amount: target }),
        fengbroQuiet: true
    }).then(r => r.json()).then(res => {
        if (!res.success) throw new Error(res.error || '');
        state.confirmed = target;
    }).catch(err => {
        if (state.target === target) {
            state.target = state.confirmed;
            renderFoodAmount(id, state.confirmed);
        }
        alert('更新數量失敗：' + (err.message || '網路錯誤'));
    }).finally(() => { state.request = null; });
    await state.request;
    return flushFoodAmount(id);
}

restoreFoodFilters();
document.addEventListener('fengbro:soft-reload', () => {
    for (const [id, state] of foodAmountSync) renderFoodAmount(id, state.target);
    restoreFoodFilters();
});

function startInlineAdd() {
    const row = document.getElementById('inlineAddRow');
    if (!row) {
        alert('找不到新增列，請重新整理頁面');
        return;
    }
    row.style.setProperty('display', 'table-row', 'important');
    row.querySelectorAll('[data-field]').forEach(input => {
        input.value = '';
    });
    const nameInput = row.querySelector('[data-field="name"]');
    if (nameInput) nameInput.focus();
}

function cancelInlineAdd() {
    const row = document.getElementById('inlineAddRow');
    if (!row) return;
    row.style.display = 'none';
}

function saveInlineAdd() {
    const row = document.getElementById('inlineAddRow');
    if (!row) return;
    const name = row.querySelector('[data-field="name"]').value.trim();
    if (!name) {
        alert('請輸入食品名稱');
        return;
    }
    const data = {
        name,
        amount: row.querySelector('[data-field="amount"]').value || 0,
        price: row.querySelector('[data-field="price"]').value || 0,
        shop: row.querySelector('[data-field="shop"]').value.trim(),
        todate: row.querySelector('[data-field="todate"]').value || null,
        photo: row.querySelector('[data-field="photo"]').value.trim()
    };
    fetch(`index.php?action=create&table=${TABLE}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) fengbroReload();
            else alert('儲存失敗: ' + (res.error || res.message || ''));
        })
        .catch(err => alert('儲存失敗: ' + (err.message || '網路錯誤')));
}

function getRowById(id) {
    return document.querySelector(`[data-food-item][data-id="${CSS.escape(id)}"]`);
}

function editFoodRow(button) {
    const id = button.closest('tr').dataset.id;
    if (matchMedia('(max-width: 1099.98px)').matches) editItem(id);
    else startInlineEdit(id);
}

document.querySelector('.food-table').addEventListener('keydown', event => {
    const row = event.target.closest('[data-food-item]');
    if (!row || !event.target.classList.contains('inline-input')) return;
    if (event.key === 'Tab') event.stopPropagation();
    if (event.key === 'Escape') cancelInlineEdit(row.dataset.id);
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) saveInlineEdit(row.dataset.id);
});

function startInlineEdit(id) {
    const row = getRowById(id);
    if (!row) return;
    row.classList.add('food-editing');
    row.querySelectorAll('.inline-view').forEach(el => el.style.display = 'none');
    row.querySelectorAll('.inline-edit').forEach(el => el.style.display = 'block');
    fillInlineInputs(row);
    row.querySelector('[data-field="name"]').focus();
}

function cancelInlineEdit(id) {
    const row = getRowById(id);
    if (!row) return;
    row.classList.remove('food-editing');
    row.querySelectorAll('.inline-view').forEach(el => el.style.display = '');
    row.querySelectorAll('.inline-edit').forEach(el => el.style.display = 'none');
    row.querySelector('.food-row-actions button').focus();
}

function fillInlineInputs(row) {
    const data = row.dataset;
    const todate = data.todate ? data.todate.split(' ')[0] : '';
    const nameInput = row.querySelector('[data-field="name"]');
    if (nameInput) nameInput.value = data.name || '';
    const amountInput = row.querySelector('[data-field="amount"]');
    if (amountInput) amountInput.value = data.amount || '';
    const priceInput = row.querySelector('[data-field="price"]');
    if (priceInput) priceInput.value = data.price || '';
    const shopInput = row.querySelector('[data-field="shop"]');
    if (shopInput) shopInput.value = data.shop || '';
    const todateInput = row.querySelector('[data-field="todate"]');
    if (todateInput) todateInput.value = todate || '';
    const photoInput = row.querySelector('[data-field="photo"]');
    if (photoInput) {
        photoInput.value = data.photo || '';
        updateInlinePhotoPreview(photoInput);
    }
}

async function saveInlineEdit(id) {
    const row = getRowById(id);
    if (!row) return;
    const name = row.querySelector('[data-field="name"]').value.trim();
    if (!name) {
        alert('請輸入食品名稱');
        return;
    }
    const data = {
        name,
        amount: row.querySelector('[data-field="amount"]').value || 0,
        price: row.querySelector('[data-field="price"]').value || 0,
        shop: row.querySelector('[data-field="shop"]').value.trim(),
        todate: row.querySelector('[data-field="todate"]').value || null,
        photo: row.querySelector('[data-field="photo"]').value.trim()
    };
    await flushFoodAmount(id);
    fetch(`index.php?action=update&table=${TABLE}&id=${encodeURIComponent(id)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                cancelInlineEdit(id);
                fengbroReload();
            } else alert('儲存失敗: ' + (res.error || ''));
        })
        .catch(err => alert('儲存失敗：' + (err.message || '網路錯誤')));
}

let foodDialogSession = 0;

function openModal() {
    foodDialogSession++;
    document.getElementById('modalTitle').textContent = '新增食品';
    document.getElementById('itemForm').reset();
    document.getElementById('foodFormError').textContent = '';
    document.getElementById('itemId').value = '';
    document.getElementById('photoPreview').replaceChildren();
    document.getElementById('modal').showModal();
}

function closeModal() {
    document.getElementById('modal').close();
}

function fillFoodModal(id, copy) {
    const row = getRowById(id);
    if (!row) return;
    openModal();
    for (const field of ['name', 'amount', 'price', 'shop', 'todate', 'photo']) {
        document.getElementById(field).value = row.dataset[field];
    }
    document.getElementById('itemId').value = copy ? '' : id;
    document.getElementById('modalTitle').textContent = copy ? '複製食品' : '編輯食品';
    updatePhotoPreview();
}

function editItem(id) {
    fillFoodModal(id, false);
}

function duplicateItem(id) {
    fillFoodModal(id, true);
}

function deleteItem(id) {
    deleteInlineItem(id, { table: TABLE });
}

document.getElementById('itemForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const session = foodDialogSession;
    const submit = this.querySelector('[type=submit]');
    if (submit.disabled) return;
    submit.disabled = true;
    const error = document.getElementById('foodFormError');
    error.textContent = '';
    const id = document.getElementById('itemId').value;
    const action = id ? 'update' : 'create';
    const url = id ? `index.php?action=${action}&table=${TABLE}&id=${encodeURIComponent(id)}` : `index.php?action=${action}&table=${TABLE}`;

    const data = {
        name: document.getElementById('name').value,
        amount: document.getElementById('amount').value || 0,
        price: document.getElementById('price').value || 0,
        shop: document.getElementById('shop').value,
        todate: document.getElementById('todate').value || null,
        photo: document.getElementById('photo').value
    };

    await flushFoodAmount(id);
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (foodDialogSession === session) closeModal();
                fengbroReload();
            } else if (foodDialogSession === session) {
                error.textContent = '儲存失敗：' + (res.error || '請重新嘗試');
            }
        })
        .catch(err => {
            if (foodDialogSession === session) error.textContent = '儲存失敗：' + (err.message || '網路錯誤');
        })
        .finally(() => { submit.disabled = false; });
});

function uploadInlinePhoto(fileInput) {
    if (!fileInput.files || !fileInput.files[0]) return;
    const photoInput = fileInput.closest('.inline-edit').querySelector('[data-field="photo"]');
    uploadFileWithProgress(fileInput.files[0],
        function (res) {
            photoInput.value = res.file;
            updateInlinePhotoPreview(photoInput);
        },
        function (error) {
            alert('上傳失敗: ' + error);
        }
    );
    fileInput.value = '';
}

function updateInlinePhotoPreview(input) {
    const preview = input.closest('.inline-edit').querySelector('.inline-photo-preview');
    if (!preview) return;
    setFoodPreview(preview, input.value.trim());
}

function setFoodPreview(preview, url) {
    preview.replaceChildren();
    if (!url) return;
    const image = new Image();
    image.alt = '食品圖片預覽';
    image.src = url;
    preview.appendChild(image);
}

function uploadPhoto() {
    const input = document.getElementById('photoFile');
    if (!input.files || !input.files[0]) return;

    const session = foodDialogSession;
    const dialog = document.getElementById('modal');
    dialog.append(document.getElementById('uploadProgressModal'));
    uploadFileWithProgress(input.files[0],
        function (res) {
            if (!dialog.open || foodDialogSession !== session) return;
            document.getElementById('photo').value = res.file;
            updatePhotoPreview();
        },
        function (error) {
            if (!dialog.open || foodDialogSession !== session) return;
            document.getElementById('foodFormError').textContent = '上傳失敗：' + error;
        }
    );
    input.value = '';
}

function updatePhotoPreview() {
    const photo = document.getElementById('photo').value;
    const preview = document.getElementById('photoPreview');

    setFoodPreview(preview, photo.trim());
}

document.getElementById('modal').addEventListener('close', function () {
    const progress = this.querySelector('#uploadProgressModal');
    if (progress) this.after(progress);
});

// 當圖片網址改變時更新預覽
document.getElementById('photo').addEventListener('change', updatePhotoPreview);
document.getElementById('photo').addEventListener('input', updatePhotoPreview);
