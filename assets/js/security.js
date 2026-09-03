(function () {
    'use strict';
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (!token) return;

    const nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        init = init || {};
        const url = typeof input === 'string' ? input : input.url;
        let sameOrigin = true;
        try { sameOrigin = new URL(url, location.href).origin === location.origin; } catch (_) {}
        if (sameOrigin) {
            const headers = new Headers(init.headers || (typeof input !== 'string' ? input.headers : undefined));
            headers.set('X-CSRF-Token', token);
            headers.set('X-Requested-With', 'XMLHttpRequest');
            init.headers = headers;
        }
        return nativeFetch(input, init);
    };

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || (form.method || 'get').toLowerCase() === 'get') return;
        if (form.querySelector('input[name="_csrf"]')) return;
        const field = document.createElement('input');
        field.type = 'hidden'; field.name = '_csrf'; field.value = token;
        form.appendChild(field);
        const submit = event.submitter;
        if (submit) {
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
            setTimeout(function () { submit.disabled = false; submit.removeAttribute('aria-busy'); }, 8000);
        }
    }, true);
})();
