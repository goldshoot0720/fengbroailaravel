(function () {
    'use strict';

    const meta = document.querySelector('meta[name="csrf-token"]');
    let token = meta?.content || '';
    if (!token) return;

    function isSameOrigin(url) {
        try { return new URL(url, location.href).origin === location.origin; }
        catch (_) { return true; }
    }

    const nativeFetch = window.fetch.bind(window);

    /**
     * 向伺服器換一組新的 CSRF token。
     * session 被 GC 回收（例如大檔上傳跑太久）之後，舊 token 會失效並回 419；
     * 呼叫這支就能拿到新 session 的 token，不必叫使用者重新整理頁面。
     */
    let refreshing = null;
    function refreshCsrfToken() {
        if (refreshing) return refreshing;
        refreshing = nativeFetch('csrf.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && data.token) {
                    token = data.token;
                    if (meta) meta.content = token;
                    document.querySelectorAll('input[name="_csrf"]').forEach(function (el) { el.value = token; });
                }
                return token;
            })
            .catch(function () { return token; })
            .finally(function () { refreshing = null; });
        return refreshing;
    }

    window.fengbroCsrf = {
        get token() { return token; },
        refresh: refreshCsrfToken
    };

    // --- fetch() ---
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

    // --- XMLHttpRequest（帶進度條的上傳走這條，不是 fetch）---
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
                } catch (_) { /* 狀態不對就略過 */ }
            }
            return nativeSend.apply(this, arguments);
        };
    }

    // --- 一般表單送出 ---
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
