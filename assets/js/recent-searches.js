(function () {
    'use strict';
    const page = new URLSearchParams(location.search).get('page') || 'home';
    const key = 'fengbro_recent_searches_' + page;
    const inputs = Array.from(document.querySelectorAll('input[type="search"], input[id*="search" i]'));
    if (!inputs.length || document.getElementById('subscriptionSearchHistory')) return;

    function read() {
        try { return JSON.parse(localStorage.getItem(key) || '[]').filter(Boolean).slice(0, 8); }
        catch (_) { return []; }
    }
    function write(items) { localStorage.setItem(key, JSON.stringify(items.slice(0, 8))); }
    function dispatch(input) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
    inputs.forEach(function (input, index) {
        const box = document.createElement('div');
        box.className = 'recent-search-controls';
        input.parentElement.insertAdjacentElement('afterend', box);
        function render() {
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
        function save() {
            const term = input.value.trim(); if (!term) return;
            write([term].concat(read().filter(item => item !== term))); render();
        }
        input.addEventListener('change', save);
        input.addEventListener('keydown', e => { if (e.key === 'Enter') save(); });
        render();
    });
})();
