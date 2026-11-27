(() => {
    const workspace = document.getElementById('routineWorkspace');
    if (!workspace) return;
    const editor = document.getElementById('routineEditor');
    const form = document.getElementById('routineForm');
    const fields = form.elements;
    const submit = form.querySelector('[type="submit"]');
    const errorBox = document.getElementById('routineError');
    const attachment = form.querySelector('.routine-attachment');
    const preview = document.getElementById('routinePreview');
    const dateFields = ['lastdate1', 'lastdate2', 'lastdate3'];
    const textFields = ['name', 'note', 'link', 'photo'];
    const filterButtons = [...workspace.querySelectorAll('[data-filter]')];
    const search = document.getElementById('routineSearch');
    const records = document.getElementById('routineRecords');
    const params = new URLSearchParams(location.search);
    const normalize = value => (value || '').normalize('NFKC').trim().toLocaleLowerCase();
    let filter = filterButtons.some(b => b.dataset.filter === params.get('filter')) ? params.get('filter') : 'all';
    let view = params.get('view') === 'cards' ? 'cards' : 'list';
    let editing = null;
    let initial = {};
    let previewUrl = '';
    let saving = false;
    let index = [];

    function indexRecords() {
        index = [...(records?.children || [])].map(row => {
            const data = JSON.parse(row.dataset.record);
            return { row, text: normalize(data.name + '\n' + (data.note || '')), dated: Boolean(row.querySelector('.routine-history > div:first-child time')),
                linked: Boolean(data.link?.trim()), photo: Boolean(data.photo?.trim()) };
        });
    }

    function applyFilters() {
        if (!records) return;
        const term = normalize(search.value);
        let visible = 0;
        for (const entry of index) {
            const show = entry.text.includes(term) && (filter === 'all' || entry[filter]);
            entry.row.hidden = !show;
            if (show) visible++;
        }
        records.dataset.view = view;
        filterButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.filter === filter)));
        workspace.querySelectorAll('button[data-view]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.view === view)));
        document.getElementById('routineCount').textContent = (term || filter !== 'all' ? visible + ' / ' : '') + index.length + ' 項';
        const empty = document.getElementById('routineEmpty');
        empty.hidden = visible > 0;
        empty.querySelector('span').textContent = index.length ? '找不到符合的例行事項。' : '尚無例行事項。';
        const action = empty.querySelector('button');
        action.dataset.routineAction = index.length ? 'clear' : 'add';
        action.textContent = index.length ? '清除篩選' : '新增第一筆事項';
        reconcileSelectionWithVisibleItems();
    }

    function updateLocation() {
        const url = new URL(location.href);
        for (const [key, value] of Object.entries({q: search.value.trim(), filter: filter === 'all' ? '' : filter, view: view === 'list' ? '' : view})) {
            if (value) url.searchParams.set(key, value);
            else url.searchParams.delete(key);
        }
        history.replaceState(history.state, '', url);
    }

    function showPreview() {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        const file = fields.attachment.files[0];
        previewUrl = file ? URL.createObjectURL(file) : '';
        const source = previewUrl || fields.photo.value.trim();
        preview.hidden = !source;
        if (source) preview.src = source;
        else preview.removeAttribute('src');
    }

    function openEditor(row) {
        form.reset();
        editing = row?.dataset.id || null;
        const data = row ? JSON.parse(row.dataset.record) : {};
        for (const field of textFields) fields[field].value = data[field] || '';
        for (const [i, field] of dateFields.entries()) {
            fields[field].value = row?.querySelectorAll('dl > div')[i]?.querySelector('time')?.dateTime || '';
            form.querySelector('[data-clear-date="' + field + '"]').disabled = !fields[field].value;
        }
        initial = Object.fromEntries([...textFields, ...dateFields].map(field => [field, fields[field].value]));
        attachment.open = false;
        errorBox.textContent = '';
        showPreview();
        document.getElementById('routineEditorTitle').textContent = editing ? '編輯例行事項' : '新增例行事項';
        editor.showModal();
        fields.name.focus();
    }

    async function write(action, id, data) {
        const query = new URLSearchParams({ action, table: 'routine' });
        if (id) query.set('id', id);
        const response = await fetch('index.php?' + query, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || '無法完成操作，請重試。');
        return result;
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        if (!fields.name.value.trim()) {
            fields.name.setCustomValidity('請輸入事項名稱');
            fields.name.reportValidity();
            return;
        }
        saving = true;
        const controls = [...fields].filter(el => !el.disabled && el.matches('input, textarea, button'));
        controls.forEach(el => el.disabled = true);
        errorBox.textContent = '';
        submit.textContent = '儲存中…';
        try {
            const file = fields.attachment.files[0];
            if (file) {
                submit.textContent = '上傳圖片中…';
                const uploaded = await new Promise((resolve, reject) =>
                    uploadFileWithProgress(file, resolve, message => reject(new Error(message)), {showModal: false}));
                fields.photo.value = uploaded.file;
                fields.attachment.value = '';
                showPreview();
                submit.textContent = '儲存中…';
            }
            const data = {};
            for (const field of [...textFields, ...dateFields]) {
                const value = fields[field].value;
                if (!editing || value !== initial[field]) data[field] = dateFields.includes(field) ? value || null : value.trim();
            }
            if (Object.keys(data).length) {
                await write(editing ? 'update' : 'create', editing, data);
                editor.close();
                await window.fengbroReload();
            } else {
                editor.close();
            }
        } catch (error) {
            errorBox.textContent = error.message || '儲存失敗，請重試。';
        } finally {
            saving = false;
            controls.forEach(el => el.disabled = false);
            submit.textContent = '儲存';
        }
    });
    for (const field of dateFields) {
        const clear = form.querySelector('[data-clear-date="' + field + '"]');
        fields[field].addEventListener('input', () => { clear.disabled = !fields[field].value; });
        clear.addEventListener('click', () => {
            fields[field].value = '';
            clear.disabled = true;
            fields[field].focus();
        });
    }
    fields.name.addEventListener('input', () => fields.name.setCustomValidity(''));
    editor.addEventListener('cancel', event => { if (saving) event.preventDefault(); });
    editor.addEventListener('close', () => {
        fields.name.setCustomValidity('');
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = '';
    });
    fields.attachment.addEventListener('change', showPreview);
    fields.photo.addEventListener('input', () => { fields.attachment.value = ''; });
    fields.photo.addEventListener('change', showPreview);

    async function shift(button, row) {
        const data = JSON.parse(row.dataset.record);
        if (!confirm('將「' + data.name + '」的最近一次移到前一次、前一次移到再前一次。原本第三筆將被取代，最近一次會留白。確定遞移？')) return;
        button.disabled = true;
        try {
            await write('shift_dates', row.dataset.id, Object.fromEntries(dateFields.map(key => [key, data[key]])));
            window.fengbroToast?.('已遞移日期', 'ok');
        } catch (error) {
            alert(error.message || '日期遞移失敗，請重試。');
        } finally {
            button.disabled = false;
            await window.fengbroReload();
        }
    }

    workspace.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.dataset.filter || button.dataset.view) {
            if (button.dataset.filter) filter = button.dataset.filter;
            if (button.dataset.view) view = button.dataset.view;
            applyFilters();
            updateLocation();
            return;
        }
        const row = button.closest('.routine-record');
        switch (button.dataset.routineAction) {
            case 'add': openEditor(null); break;
            case 'edit': openEditor(row); break;
            case 'close': if (!saving) editor.close(); break;
            case 'shift': shift(button, row); break;
            case 'delete': deleteInlineItem(row.dataset.id, { table: 'routine', confirmMessage: '確定要刪除「' + JSON.parse(row.dataset.record).name + '」嗎？' }); break;
            case 'clear':
                filter = 'all';
                search.value = '';
                applyFilters();
                updateLocation();
                search.focus();
                break;
        }
    });
    window.handleAdd = () => { if (!editor.open) openEditor(null); };
    // The voice toolbar can invoke the same save action as the visible form.
    window.saveInlineAdd = () => { if (editor.open && !saving) form.requestSubmit(); };
    initBatchDelete('routine');
    if (search) search.value = params.get('q') || '';
    search?.addEventListener('input', () => { applyFilters(); updateLocation(); });
    document.addEventListener('fengbro:soft-reload', () => { indexRecords(); applyFilters(); });
    indexRecords();
    applyFilters();
})();
