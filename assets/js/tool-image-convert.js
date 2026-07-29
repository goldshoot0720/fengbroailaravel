/**
 * PNG / JPEG 轉換 — 瀏覽器 Canvas，對齊 Appwrite ImageFormatConvertTool。
 */
(function () {
  'use strict';

  const ACCEPT =
    'image/png,image/jpeg,image/webp,image/gif,image/bmp,image/avif,image/tiff,image/heic,image/heif,.png,.jpg,.jpeg,.jpe,.jfif,.webp,.gif,.bmp,.avif,.tif,.tiff,.heic,.heif';

  function newId() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
    return 'img-' + Date.now() + '-' + Math.random().toString(36).slice(2, 9);
  }

  function escapeHtml(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatBytes(n) {
    if (!n || n < 1024) return (n || 0) + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
    return (n / (1024 * 1024)).toFixed(2) + ' MB';
  }

  function getExt(name) {
    const m = /\.([a-z0-9]+)$/i.exec(name || '');
    return (m && m[1] ? m[1] : '').toLowerCase();
  }

  function detectKind(file) {
    const type = (file.type || '').toLowerCase();
    const map = {
      'image/png': 'png',
      'image/jpeg': 'jpg',
      'image/jpg': 'jpg',
      'image/webp': 'webp',
      'image/gif': 'gif',
      'image/bmp': 'bmp',
      'image/avif': 'avif',
      'image/tiff': 'tiff',
      'image/heic': 'heic',
      'image/heif': 'heic',
    };
    if (map[type]) return map[type];
    const ext = getExt(file.name);
    const emap = {
      png: 'png',
      jpg: 'jpg',
      jpeg: 'jpg',
      webp: 'webp',
      gif: 'gif',
      bmp: 'bmp',
      avif: 'avif',
      tif: 'tiff',
      tiff: 'tiff',
      heic: 'heic',
      heif: 'heic',
    };
    return emap[ext] || 'other';
  }

  function isConvertible(file) {
    const kind = detectKind(file);
    if (kind !== 'other') return true;
    const type = (file.type || '').toLowerCase();
    return type.startsWith('image/') && type !== 'image/svg+xml';
  }

  function renameTarget(filename, target) {
    const base = (filename || 'image').replace(/\.(png|jpe?g|jpe|jfif|webp|gif|bmp|avif|tiff?|heic|heif)$/i, '').trim() || 'image';
    const safe = base.replace(/[<>:"/\\|?*\u0000-\u001f]/g, '_').trim() || 'image';
    return safe + '.' + (target === 'png' ? 'png' : 'jpg');
  }

  function uniqueName(desired, used) {
    const lower = desired.toLowerCase();
    if (!used.has(lower)) {
      used.add(lower);
      return desired;
    }
    const dot = desired.lastIndexOf('.');
    const base = dot >= 0 ? desired.slice(0, dot) : desired;
    const ext = dot >= 0 ? desired.slice(dot) : '';
    let i = 1;
    while (used.has((base + '-' + i + ext).toLowerCase())) i++;
    const name = base + '-' + i + ext;
    used.add(name.toLowerCase());
    return name;
  }

  function loadImageFromFile(file) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        URL.revokeObjectURL(url);
        resolve(img);
      };
      img.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('瀏覽器無法解碼此圖片（可能是 HEIC/TIFF）'));
      };
      img.src = url;
    });
  }

  function canvasToBlob(canvas, mime, quality) {
    return new Promise((resolve, reject) => {
      canvas.toBlob(
        (blob) => {
          if (!blob) reject(new Error('轉換失敗'));
          else resolve(blob);
        },
        mime,
        quality
      );
    });
  }

  async function convertFile(file, target, quality, bg) {
    const img = await loadImageFromFile(file);
    const canvas = document.createElement('canvas');
    canvas.width = img.naturalWidth || img.width;
    canvas.height = img.naturalHeight || img.height;
    const ctx = canvas.getContext('2d');
    if (target === 'jpg') {
      ctx.fillStyle = bg || '#ffffff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
    }
    ctx.drawImage(img, 0, 0);
    const mime = target === 'png' ? 'image/png' : 'image/jpeg';
    const q = target === 'png' ? undefined : Math.min(1, Math.max(0.01, quality));
    const blob = await canvasToBlob(canvas, mime, q);
    return {
      blob,
      width: canvas.width,
      height: canvas.height,
      name: renameTarget(file.name, target),
    };
  }

  function downloadBlob(blob, filename) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1500);
  }

  function initImageConvertTool() {
    const root = document.getElementById('imageConvertTool');
    if (!root) return;

    /** @type {Array<any>} */
    let items = [];
    let target = 'jpg';
    let quality = 1;
    let bg = '#ffffff';
    let busy = false;

    const els = {
      list: root.querySelector('[data-ic-list]'),
      file: root.querySelector('[data-ic-file]'),
      folder: root.querySelector('[data-ic-folder]'),
      url: root.querySelector('[data-ic-url]'),
      q: root.querySelector('[data-ic-quality]'),
      qLabel: root.querySelector('[data-ic-quality-label]'),
      bg: root.querySelector('[data-ic-bg]'),
      error: root.querySelector('[data-ic-error]'),
      status: root.querySelector('[data-ic-status]'),
      drop: root.querySelector('[data-ic-drop]'),
    };

    if (els.folder) {
      try {
        els.folder.setAttribute('webkitdirectory', '');
        els.folder.setAttribute('directory', '');
      } catch (e) {
        /* ignore */
      }
    }

    function setError(msg) {
      if (els.error) els.error.textContent = msg || '';
    }

    function setStatus(msg) {
      if (els.status) els.status.textContent = msg || '';
    }

    function render() {
      if (!els.list) return;
      if (!items.length) {
        els.list.innerHTML = '<p class="tool-muted">尚未加入圖片。拖放、選擇檔案／資料夾，或貼網址。</p>';
        return;
      }
      els.list.innerHTML = items
        .map((it) => {
          const badge =
            it.status === 'done'
              ? '<span class="badge badge-success">完成</span>'
              : it.status === 'error'
                ? '<span class="badge badge-danger">失敗</span>'
                : it.status === 'converting'
                  ? '<span class="badge badge-warning">轉換中</span>'
                  : '<span class="badge">佇列</span>';
          return (
            '<div class="ic-item">' +
            (it.previewUrl
              ? '<img src="' +
                escapeHtml(it.previewUrl) +
                '" alt="" class="ic-thumb">'
              : '<div class="ic-thumb ic-thumb-empty"></div>') +
            '<div class="ic-item-body"><strong>' +
            escapeHtml(it.file && it.file.name ? it.file.name : it.sourceLabel || 'image') +
            '</strong>' +
            '<div class="tool-muted" style="font-size:0.82rem;">' +
            escapeHtml(String(it.kind || '').toUpperCase()) +
            (it.width ? ' · ' + it.width + '×' + it.height : '') +
            (it.resultBlob ? ' · ' + formatBytes(it.resultBlob.size) : '') +
            '</div>' +
            (it.error ? '<div style="color:#dc2626;font-size:0.82rem;">' + escapeHtml(it.error) + '</div>' : '') +
            '</div><div class="ic-item-actions">' +
            badge +
            (it.resultUrl
              ? '<a class="btn btn-ghost btn-sm" href="' +
                escapeHtml(it.resultUrl) +
                '" download="' +
                escapeHtml(it.resultName || 'out.jpg') +
                '">下載</a>'
              : '') +
            '<button type="button" class="btn btn-ghost btn-sm" data-ic-remove="' +
            escapeHtml(it.id) +
            '">移除</button></div></div>'
          );
        })
        .join('');
    }

    function addFiles(fileList) {
      const files = Array.from(fileList || []).filter(isConvertible);
      if (!files.length) {
        setError('沒有可轉換的圖片');
        return;
      }
      files.forEach((file) => {
        items.push({
          id: newId(),
          file,
          kind: detectKind(file),
          sourceLabel: file.name,
          previewUrl: URL.createObjectURL(file),
          status: 'queued',
        });
      });
      setError('');
      setStatus('已選 ' + items.length + ' 張');
      render();
    }

    async function addFromUrl() {
      const url = els.url && els.url.value.trim();
      if (!url) return;
      setError('');
      setStatus('讀取網址中…');
      try {
        const proxy = 'media_proxy.php?url=' + encodeURIComponent(url);
        const res = await fetch(proxy);
        if (!res.ok) throw new Error('讀取失敗 HTTP ' + res.status);
        const blob = await res.blob();
        const nameGuess = (url.split('?')[0].split('/').pop() || 'image.jpg').replace(/[^\w.\-]+/g, '_') || 'image.jpg';
        const file = new File([blob], nameGuess, { type: blob.type || 'image/jpeg' });
        if (!isConvertible(file)) throw new Error('不是可轉換的圖片');
        items.push({
          id: newId(),
          file,
          kind: detectKind(file),
          sourceLabel: url,
          previewUrl: URL.createObjectURL(file),
          status: 'queued',
        });
        if (els.url) els.url.value = '';
        setStatus('已選 ' + items.length + ' 張');
        render();
      } catch (e) {
        setError(e.message || '網址讀取失敗');
        setStatus('');
      }
    }

    async function convertAll() {
      if (busy || !items.length) return;
      busy = true;
      setError('');
      const used = new Set();
      let done = 0;
      for (const it of items) {
        it.status = 'converting';
        render();
        setStatus('轉換中 ' + (done + 1) + '/' + items.length + '…');
        try {
          const result = await convertFile(it.file, target, quality, bg);
          if (it.resultUrl) URL.revokeObjectURL(it.resultUrl);
          it.resultBlob = result.blob;
          it.resultName = uniqueName(result.name, used);
          it.resultUrl = URL.createObjectURL(result.blob);
          it.width = result.width;
          it.height = result.height;
          it.status = 'done';
          it.error = undefined;
        } catch (e) {
          it.status = 'error';
          it.error = e.message || '轉換失敗';
        }
        done++;
        render();
      }
      const ok = items.filter((i) => i.status === 'done').length;
      setStatus('完成 ' + ok + '/' + items.length);
      busy = false;
    }

    function downloadAll() {
      items.filter((i) => i.resultBlob).forEach((i) => downloadBlob(i.resultBlob, i.resultName || 'out.jpg'));
    }

    async function downloadZip() {
      const ready = items.filter((i) => i.resultBlob);
      if (!ready.length) return;
      if (typeof JSZip === 'undefined') {
        // fallback: sequential download
        downloadAll();
        setError('未載入 JSZip，已改為逐一下載');
        return;
      }
      setStatus('打包 ZIP…');
      try {
        const zip = new JSZip();
        ready.forEach((i) => zip.file(i.resultName || 'out.jpg', i.resultBlob));
        const blob = await zip.generateAsync({ type: 'blob' });
        downloadBlob(blob, 'images-' + (target === 'png' ? 'png' : 'jpg') + '.zip');
        setStatus('ZIP 已下載');
      } catch (e) {
        setError('ZIP 打包失敗');
      }
    }

    root.addEventListener('click', (ev) => {
      const t = ev.target.closest(
        '[data-ic-pick], [data-ic-folder-btn], [data-ic-add-url], [data-ic-convert], [data-ic-download], [data-ic-zip], [data-ic-clear], [data-ic-remove], [data-ic-target]'
      );
      if (!t) return;
      if (t.hasAttribute('data-ic-pick') && els.file) els.file.click();
      if (t.hasAttribute('data-ic-folder-btn') && els.folder) els.folder.click();
      if (t.hasAttribute('data-ic-add-url')) addFromUrl();
      if (t.hasAttribute('data-ic-convert')) convertAll();
      if (t.hasAttribute('data-ic-download')) downloadAll();
      if (t.hasAttribute('data-ic-zip')) downloadZip();
      if (t.hasAttribute('data-ic-clear')) {
        items.forEach((i) => {
          if (i.previewUrl) URL.revokeObjectURL(i.previewUrl);
          if (i.resultUrl) URL.revokeObjectURL(i.resultUrl);
        });
        items = [];
        setStatus('');
        setError('');
        render();
      }
      if (t.hasAttribute('data-ic-remove')) {
        const id = t.getAttribute('data-ic-remove');
        const it = items.find((x) => x.id === id);
        if (it) {
          if (it.previewUrl) URL.revokeObjectURL(it.previewUrl);
          if (it.resultUrl) URL.revokeObjectURL(it.resultUrl);
        }
        items = items.filter((x) => x.id !== id);
        render();
      }
      if (t.hasAttribute('data-ic-target')) {
        target = t.getAttribute('data-ic-target') === 'png' ? 'png' : 'jpg';
        root.querySelectorAll('[data-ic-target]').forEach((btn) => {
          btn.classList.toggle('active', btn.getAttribute('data-ic-target') === target);
        });
        const qWrap = root.querySelector('[data-ic-jpg-only]');
        if (qWrap) qWrap.style.display = target === 'jpg' ? '' : 'none';
      }
    });

    if (els.file) els.file.addEventListener('change', () => addFiles(els.file.files));
    if (els.folder) els.folder.addEventListener('change', () => addFiles(els.folder.files));
    if (els.q) {
      els.q.addEventListener('input', () => {
        quality = Number(els.q.value) / 100;
        if (els.qLabel) els.qLabel.textContent = Math.round(quality * 100) + '%';
      });
    }
    if (els.bg) {
      els.bg.addEventListener('change', () => {
        bg = els.bg.value || '#ffffff';
      });
    }
    if (els.drop) {
      els.drop.addEventListener('dragover', (e) => {
        e.preventDefault();
        els.drop.classList.add('is-dragover');
      });
      els.drop.addEventListener('dragleave', () => els.drop.classList.remove('is-dragover'));
      els.drop.addEventListener('drop', (e) => {
        e.preventDefault();
        els.drop.classList.remove('is-dragover');
        addFiles(e.dataTransfer.files);
      });
      els.drop.addEventListener('click', () => els.file && els.file.click());
    }
    if (els.url) {
      els.url.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          addFromUrl();
        }
      });
    }

    // expose accept for inputs
    if (els.file) els.file.setAttribute('accept', ACCEPT);
    render();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initImageConvertTool);
  } else {
    initImageConvertTool();
  }
})();
