(function () {
    'use strict';
    const page = new URLSearchParams(location.search).get('page') || 'home';
    const key = 'fengbro_recent_searches_' + page;
    const inputs = Array.from(document.querySelectorAll('input[type="search"], input[id*="search" i]'));
    if (!inputs.length) return;

    function read() {
        try { return JSON.parse(localStorage.getItem(key) || '[]').filter(Boolean).slice(0, 8); }
        catch (_) { return []; }
    }
    function write(items) { localStorage.setItem(key, JSON.stringify(items.slice(0, 8))); }
    function dispatch(input) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function wrapSearchForm(input) {
        if (input.closest('form[role="search"]')) return input.closest('form[role="search"]');
        const host = input.closest('.food-search-box, .subscription-search-box, label') || input;
        const form = document.createElement('form');
        form.setAttribute('role', 'search');
        form.className = 'fengbro-search-form';
        host.parentElement.insertBefore(form, host);
        form.appendChild(host);
        input.setAttribute('enterkeyhint', 'search');
        const btn = document.createElement('button');
        btn.type = 'submit';
        btn.className = 'btn btn-sm fengbro-search-submit';
        btn.setAttribute('aria-label', '提交搜尋');
        btn.innerHTML = '<i class="fas fa-search" aria-hidden="true"></i> <span>提交</span>';
        form.appendChild(btn);
        return form;
    }

    inputs.forEach(function (input) {
        const form = wrapSearchForm(input);
        const skipChips = !!document.getElementById('subscriptionSearchHistory');
        const box = skipChips ? null : document.createElement('div');
        if (box) {
            box.className = 'recent-search-controls';
            form.insertAdjacentElement('afterend', box);
        }
        function render() {
            if (!box) return;
            const items = read();
            box.innerHTML = items.map(term => '<button type="button" class="recent-search-chip">' +
                term.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])) + '</button>').join('') +
                (items.length ? '<button type="button" class="recent-search-clear">\u6e05\u9664</button>' : '');
            box.querySelectorAll('.recent-search-chip').forEach(btn => btn.onclick = function () {
                input.value = btn.textContent; dispatch(input); input.focus();
            });
            const clear = box.querySelector('.recent-search-clear');
            if (clear) clear.onclick = function () { write([]); input.value = ''; dispatch(input); render(); };
        }
        function submit() {
            const query = (input.value || '').trim();
            if (query !== input.value) input.value = query;
            dispatch(input);
            if (query) write([query].concat(read().filter(item => item !== query)));
            render();
        }
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            event.stopPropagation();
            submit();
        });
        input.addEventListener('keydown', function (e) {
            if (e.isComposing || e.keyCode === 229) return;
            if (e.key === 'Escape' && box) box.style.display = 'none';
        });
        render();
    });
})();
