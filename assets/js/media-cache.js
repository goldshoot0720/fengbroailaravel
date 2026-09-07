/**
 * Fengbro 媒體離線快取（IndexedDB）
 * 對齊 Appwrite 版：每種類型上限 500MB，超出時清除最舊項目。
 */
(function (global) {
    'use strict';

    var MAX_CACHE_SIZE = 500 * 1024 * 1024;
    var configs = {
        video: { dbName: 'FengbroVideoCache', storeName: 'videos' },
        music: { dbName: 'FengbroMusicCache', storeName: 'music' },
        podcast: { dbName: 'FengbroPodcastCache', storeName: 'podcasts' },
        document: { dbName: 'FengbroDocumentCache', storeName: 'documents' },
        image: { dbName: 'FengbroImageCache', storeName: 'images' }
    };

    function openDb(kind) {
        var cfg = configs[kind] || configs.video;
        return new Promise(function (resolve, reject) {
            var request = indexedDB.open(cfg.dbName, 1);
            request.onerror = function () {
                reject(new Error('無法開啟快取資料庫'));
            };
            request.onsuccess = function (event) {
                resolve(event.target.result);
            };
            request.onupgradeneeded = function (event) {
                var db = event.target.result;
                if (!db.objectStoreNames.contains(cfg.storeName)) {
                    var store = db.createObjectStore(cfg.storeName, { keyPath: 'id' });
                    store.createIndex('cachedAt', 'cachedAt', { unique: false });
                    store.createIndex('size', 'size', { unique: false });
                }
            };
        });
    }

    function storeName(kind) {
        return (configs[kind] || configs.video).storeName;
    }

    function txDone(tx) {
        return new Promise(function (resolve, reject) {
            tx.oncomplete = function () { resolve(); };
            tx.onerror = function () { reject(tx.error || new Error('交易失敗')); };
            tx.onabort = function () { reject(tx.error || new Error('交易中止')); };
        });
    }

    function getAll(kind) {
        return openDb(kind).then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction([storeName(kind)], 'readonly');
                var req = tx.objectStore(storeName(kind)).getAll();
                req.onsuccess = function () { resolve(req.result || []); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function get(kind, id) {
        return openDb(kind).then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction([storeName(kind)], 'readonly');
                var req = tx.objectStore(storeName(kind)).get(id);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function put(kind, record) {
        return openDb(kind).then(function (db) {
            var tx = db.transaction([storeName(kind)], 'readwrite');
            tx.objectStore(storeName(kind)).put(record);
            return txDone(tx);
        });
    }

    function remove(kind, id) {
        return openDb(kind).then(function (db) {
            var tx = db.transaction([storeName(kind)], 'readwrite');
            tx.objectStore(storeName(kind)).delete(id);
            return txDone(tx);
        });
    }

    function getStats(kind) {
        return getAll(kind).then(function (items) {
            var totalSize = items.reduce(function (sum, item) {
                return sum + (item.size || 0);
            }, 0);
            return {
                totalSize: totalSize,
                totalItems: items.length,
                maxSize: MAX_CACHE_SIZE,
                usageRatio: totalSize / MAX_CACHE_SIZE
            };
        });
    }

    function cleanOldCache(kind) {
        return getAll(kind).then(function (items) {
            items.sort(function (a, b) {
                return String(a.cachedAt || '').localeCompare(String(b.cachedAt || ''));
            });
            var total = items.reduce(function (sum, item) {
                return sum + (item.size || 0);
            }, 0);
            var chain = Promise.resolve();
            items.forEach(function (item) {
                if (total <= MAX_CACHE_SIZE * 0.8) {
                    return;
                }
                chain = chain.then(function () {
                    total -= item.size || 0;
                    return remove(kind, item.id);
                });
            });
            return chain;
        });
    }

    function formatBytes(bytes) {
        if (!bytes) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = 0;
        var n = bytes;
        while (n >= 1024 && i < units.length - 1) {
            n /= 1024;
            i++;
        }
        return n.toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
    }

    function fetchBlobWithProgress(url, onProgress) {
        return fetch(url).then(function (response) {
            if (!response.ok) {
                throw new Error('下載失敗 HTTP ' + response.status);
            }
            if (!response.body || !response.body.getReader) {
                return response.blob();
            }
            var contentLength = Number(response.headers.get('Content-Length') || 0);
            var reader = response.body.getReader();
            var chunks = [];
            var received = 0;

            function pump() {
                return reader.read().then(function (result) {
                    if (result.done) {
                        return new Blob(chunks);
                    }
                    chunks.push(result.value);
                    received += result.value.byteLength || result.value.length || 0;
                    if (typeof onProgress === 'function' && contentLength > 0) {
                        onProgress(Math.min(99, Math.round((received / contentLength) * 100)));
                    }
                    return pump();
                });
            }
            return pump().then(function (blob) {
                if (typeof onProgress === 'function') onProgress(100);
                return blob;
            });
        });
    }

    function cacheMedia(kind, item, onProgress) {
        var id = String(item.id || '');
        var url = item.url || item.file || '';
        if (!id || !url) {
            return Promise.reject(new Error('缺少媒體 id 或網址'));
        }

        return getStats(kind).then(function (stats) {
            if (stats.totalSize > MAX_CACHE_SIZE) {
                return cleanOldCache(kind);
            }
            return null;
        }).then(function () {
            return fetchBlobWithProgress(url, onProgress);
        }).then(function (blob) {
            var record = {
                id: id,
                title: item.title || item.name || id,
                url: url,
                mime: blob.type || item.mime || '',
                size: blob.size || 0,
                blob: blob,
                cachedAt: new Date().toISOString()
            };
            return put(kind, record).then(function () {
                return cleanOldCache(kind).then(function () {
                    return record;
                });
            });
        });
    }

    function getObjectUrl(kind, id) {
        return get(kind, id).then(function (record) {
            if (!record || !record.blob) return null;
            return URL.createObjectURL(record.blob);
        });
    }

    function isCached(kind, id) {
        return get(kind, id).then(function (record) {
            return !!(record && record.blob);
        });
    }

    function listKinds() {
        return Object.keys(configs);
    }

    function clearKind(kind) {
        return getAll(kind).then(function (items) {
            var chain = Promise.resolve();
            items.forEach(function (item) {
                chain = chain.then(function () {
                    return remove(kind, item.id);
                });
            });
            return chain.then(function () {
                return { kind: kind, cleared: items.length };
            });
        });
    }

    function clearAll() {
        var kinds = listKinds();
        var results = [];
        var chain = Promise.resolve();
        kinds.forEach(function (kind) {
            chain = chain.then(function () {
                return clearKind(kind).then(function (result) {
                    results.push(result);
                });
            });
        });
        return chain.then(function () {
            return results;
        });
    }

    function getAllStats() {
        var kinds = listKinds();
        return Promise.all(kinds.map(function (kind) {
            return getStats(kind).then(function (stats) {
                return {
                    kind: kind,
                    totalSize: stats.totalSize,
                    totalItems: stats.totalItems,
                    maxSize: stats.maxSize,
                    usageRatio: stats.usageRatio
                };
            });
        })).then(function (rows) {
            var totalSize = rows.reduce(function (sum, row) {
                return sum + (row.totalSize || 0);
            }, 0);
            var totalItems = rows.reduce(function (sum, row) {
                return sum + (row.totalItems || 0);
            }, 0);
            return {
                kinds: rows,
                totalSize: totalSize,
                totalItems: totalItems,
                maxSizePerKind: MAX_CACHE_SIZE
            };
        });
    }

    global.FengbroMediaCache = {
        MAX_CACHE_SIZE: MAX_CACHE_SIZE,
        formatBytes: formatBytes,
        getStats: getStats,
        getAllStats: getAllStats,
        listKinds: listKinds,
        get: get,
        isCached: isCached,
        cacheMedia: cacheMedia,
        remove: remove,
        getObjectUrl: getObjectUrl,
        cleanOldCache: cleanOldCache,
        clearKind: clearKind,
        clearAll: clearAll
    };
})(window);
