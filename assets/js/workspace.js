(() => {
    const picker = document.getElementById('modulePicker');
    if (picker && typeof picker.showModal === 'function') {
        const search = picker.querySelector('input');
        const results = picker.querySelector('.module-results');
        const count = picker.querySelector('[role="status"]');
        const normalize = value => value.normalize('NFKC').trim().toLocaleLowerCase();
        const clear = picker.querySelector('.module-clear');
        let opener;
        let matches = [];

        function filter() {
            const query = normalize(search.value);
            matches = [];
            for (const group of results.children) {
                for (const link of group.querySelectorAll('a')) {
                    link.hidden = !link.dataset.keywords.includes(query);
                    if (!link.hidden) matches.push(link);
                }
                group.hidden = !group.querySelector('a:not([hidden])');
            }
            clear.hidden = !query || matches.length > 0;
            count.textContent = !query ? '' : matches.length ? '找到 ' + matches.length + ' 個功能' : '找不到符合的功能，請換個關鍵字。';
            results.scrollTop = 0;
        }

        function open(trigger) {
            if (picker.open) return;
            opener = trigger || document.activeElement;
            results.replaceChildren();
            for (const group of document.querySelectorAll('#primarySidebar .menu-group')) {
                const section = document.createElement('section');
                const heading = document.createElement('h3');
                heading.textContent = group.querySelector('.menu-group-toggle').textContent.trim();
                section.append(heading);
                for (const source of group.querySelectorAll('a[data-menu-url]')) {
                    const link = document.createElement('a');
                    link.href = source.href;
                    link.dataset.menuUrl = source.dataset.menuUrl;
                    const label = source.querySelector('.menu-label').cloneNode(true);
                    link.append(source.querySelector('i').cloneNode(true), label);
                    link.dataset.keywords = normalize([heading.textContent, label.textContent, source.dataset.voiceMenu, source.dataset.menuUrl].join(' '));
                    if (source.matches('[aria-current="page"]')) link.setAttribute('aria-current', 'page');
                    section.append(link);
                }
                results.append(section);
            }
            search.value = '';
            filter();
            picker.showModal();
            (matchMedia('(pointer: fine)').matches ? search : picker.querySelector('h2')).focus();
        }

        for (const trigger of document.querySelectorAll('[data-module-picker]')) {
            trigger.hidden = false;
            trigger.setAttribute('aria-haspopup', 'dialog');
            trigger.setAttribute('aria-controls', picker.id);
            trigger.onclick = () => open(trigger);
        }
        document.addEventListener('keydown', event => {
            if (event.isComposing || event.keyCode === 229) return;
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k' &&
                !event.target.closest('input, textarea, select, [contenteditable="true"]')) {
                event.preventDefault();
                open();
            }
        });
        picker.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !event.isComposing && event.keyCode !== 229) {
                event.preventDefault();
                picker.close();
            }
        });
        clear.addEventListener('click', () => {
            search.value = '';
            filter();
            search.focus();
        });
        search.addEventListener('input', filter);
        search.addEventListener('keydown', event => {
            if (!event.isComposing && event.keyCode !== 229 && event.key === 'Enter' && normalize(search.value) && matches.length === 1) {
                event.preventDefault();
                matches[0].click();
            }
        });
        results.addEventListener('click', event => {
            const link = event.target.closest('a');
            if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            window.handleMenuNav(link);
            picker.close();
        });
        picker.addEventListener('click', event => {
            if (event.target !== picker) return;
            const box = picker.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) picker.close();
        });
        picker.addEventListener('close', () => opener?.focus());
    }

    // An expanded note still needs measuring when the column becomes wider.
    const previews = new Map();
    function measure(note, button) {
        if (!note.clientWidth) return;
        note.classList.add('is-clamped');
        const overflows = note.scrollHeight > note.clientHeight + 1;
        const expanded = overflows && button.getAttribute('aria-expanded') === 'true';
        note.classList.toggle('is-clamped', !expanded);
        button.hidden = !overflows;
        button.setAttribute('aria-expanded', String(expanded));
        button.textContent = expanded ? '收合全文' : '展開全文';
    }
    const observer = typeof ResizeObserver === 'function' && new ResizeObserver(entries => {
        for (const { target } of entries) {
            if (previews.has(target)) measure(target, previews.get(target));
        }
    });
    function bindNotes() {
        if (observer) observer.disconnect();
        previews.clear();
        for (const button of document.querySelectorAll('[data-note-toggle]')) {
            const note = document.getElementById(button.getAttribute('aria-controls'));
            if (!note) continue;
            previews.set(note, button);
            measure(note, button);
            if (observer) observer.observe(note);
        }
    }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-note-toggle]');
        if (!button) return;
        const note = document.getElementById(button.getAttribute('aria-controls'));
        button.setAttribute('aria-expanded', String(button.getAttribute('aria-expanded') !== 'true'));
        measure(note, button);
    });
    if (!observer) window.addEventListener('resize', bindNotes);
    document.addEventListener('fengbro:soft-nav', bindNotes);
    document.addEventListener('fengbro:soft-reload', bindNotes);
    bindNotes();
})();
