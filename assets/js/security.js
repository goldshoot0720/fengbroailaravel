(function () {
    'use strict';
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (!token) return;

    function isSameOrigin(url) {
        try { return new URL(url, location.href).origin === location.origin; }
        catch (_) { return true; }
    }

    // --- fetch() ---
    const nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        init = init || {};
        const url = typeof input === 'string' ? input : input.url;
        if (isSameOrigin(url)) {
            const headers = new Headers(init.headers || (typeof input !== 'string' ? input.headers : undefined));
            headers.set('X-CSRF-Token', token);
            headers.set('X-Requested-With', 'XMLHttpRequest');
            init.headers = headers;
        }
        return nativeFetch(input, init);
    };

    // --- XMLHttpRequest (uploads with progress use XHR, not fetch) ---
    const XHR = window.XMLHttpRequest;
    if (XHR && XHR.prototype) {
        const nativeOpen = XHR.prototype.open;
        const nativeSend = XHR.prototype.send;
        const nativeSetHeader = XHR.prototype.setRequestHeader;

        XHR.prototype.open = function (method, url) {
            this.__fengbroSameOrigin = isSameOrigin(url);
            this.__fengbroOwnHeaders = {};
            return nativeOpen.apply(this, arguments);
        };

        XHR.prototype.setRequestHeader = function (name, value) {
            if (this.__fengbroOwnHeaders && typeof name === 'string') {
                this.__fengbroOwnHeaders[name.toLowerCase()] = true;
            }
            return nativeSetHeader.apply(this, arguments);
        };

        XHR.prototype.send = function () {
            if (this.__fengbroSameOrigin) {
                const own = this.__fengbroOwnHeaders || {};
                try {
                    if (!own['x-csrf-token']) nativeSetHeader.call(this, 'X-CSRF-Token', token);
                    if (!own['x-requested-with']) nativeSetHeader.call(this, 'X-Requested-With', 'XMLHttpRequest');
                } catch (_) { /* headers already sent / state error */ }
            }
            return nativeSend.apply(this, arguments);
        };
    }

    // --- normal form posts ---
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
