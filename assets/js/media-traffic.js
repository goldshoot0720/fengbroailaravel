(function () {
    'use strict';
    const PREFIX = 'fengbro_media_traffic_';
    const monthKey = () => PREFIX + new Date().toISOString().slice(0, 7);
    const seen = new Set();

    function read() {
        try { return JSON.parse(localStorage.getItem(monthKey()) || '{"bytes":0,"requests":0}') || {}; }
        catch (_) { return { bytes: 0, requests: 0 }; }
    }
    function record(bytes, url) {
        bytes = Math.max(0, Number(bytes) || 0);
        if (!bytes || !url || seen.has(url)) return;
        seen.add(url);
        const data = read();
        data.bytes = (Number(data.bytes) || 0) + bytes;
        data.requests = (Number(data.requests) || 0) + 1;
        data.updatedAt = new Date().toISOString();
        localStorage.setItem(monthKey(), JSON.stringify(data));
        window.dispatchEvent(new CustomEvent('fengbro:media-traffic', { detail: data }));
    }
    function isMedia(url) {
        return /(?:uploads\/|media_proxy\.php|\.(?:mp3|m4a|wav|ogg|mp4|webm|mov|jpg|jpeg|png|webp|pdf)(?:\?|$))/i.test(url || '');
    }
    if ('PerformanceObserver' in window) {
        try {
            new PerformanceObserver(list => list.getEntries().forEach(entry => {
                if (isMedia(entry.name)) record(entry.transferSize || entry.encodedBodySize, entry.name);
            })).observe({ type: 'resource', buffered: true });
        } catch (_) {}
    }
    window.FengbroMediaTraffic = { read: read, record: record, monthKey: monthKey };
})();
