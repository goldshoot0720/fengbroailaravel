/**
 * 影片合併 — 上傳多段影片，伺服器 ffmpeg concat。
 */
(function () {
  'use strict';

  function init() {
    const root = document.getElementById('videoMergeTool');
    if (!root) return;

    const els = {
      file: root.querySelector('[data-vm-file]'),
      list: root.querySelector('[data-vm-list]'),
      format: root.querySelector('[data-vm-format]'),
      status: root.querySelector('[data-vm-status]'),
      error: root.querySelector('[data-vm-error]'),
      merge: root.querySelector('[data-vm-merge]'),
      clear: root.querySelector('[data-vm-clear]'),
      env: root.querySelector('[data-vm-env]'),
    };

    /** @type {File[]} */
    let files = [];
    let busy = false;

    function setError(m) {
      if (els.error) els.error.textContent = m || '';
    }
    function setStatus(m) {
      if (els.status) els.status.textContent = m || '';
    }

    function render() {
      if (!els.list) return;
      if (!files.length) {
        els.list.innerHTML = '<p class="tool-muted">尚未選擇片段。請依播放順序選取 2～12 個影片。</p>';
        return;
      }
      els.list.innerHTML = files
        .map((f, i) => {
          return (
            '<div class="vm-item"><span class="vm-idx">' +
            (i + 1) +
            '</span><div><strong>' +
            escapeHtml(f.name) +
            '</strong><div class="tool-muted" style="font-size:0.82rem;">' +
            formatBytes(f.size) +
            '</div></div>' +
            '<button type="button" class="btn btn-ghost btn-sm" data-vm-remove="' +
            i +
            '">移除</button></div>'
          );
        })
        .join('');
    }

    function escapeHtml(v) {
      return String(v)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function formatBytes(n) {
      if (n < 1024) return n + ' B';
      if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
      return (n / (1024 * 1024)).toFixed(2) + ' MB';
    }

    async function checkEnv() {
      try {
        const res = await fetch('media_tools_api.php?action=status');
        const data = await res.json();
        if (els.env) {
          els.env.textContent = data.ffmpeg
            ? 'ffmpeg 就緒' + (data.ffmpegPath ? '（' + data.ffmpegPath + '）' : '')
            : '未偵測到 ffmpeg — 合併無法使用';
          els.env.classList.toggle('is-ready', !!data.ffmpeg);
          els.env.classList.toggle('is-missing', !data.ffmpeg);
        }
        if (els.merge) els.merge.disabled = !data.ffmpeg;
      } catch (e) {
        if (els.env) {
          els.env.textContent = '無法檢查環境';
          els.env.classList.add('is-missing');
        }
      }
    }

    function addFiles(list) {
      const arr = Array.from(list || []).filter((f) => f.type.startsWith('video/') || f.type.startsWith('audio/') || /\.(mp4|webm|mov|mkv|m4a|mp3)$/i.test(f.name));
      files = files.concat(arr).slice(0, 12);
      setError('');
      setStatus('已選 ' + files.length + ' 個片段');
      render();
    }

    async function merge() {
      if (busy) return;
      if (files.length < 2) {
        setError('請至少選擇 2 個片段');
        return;
      }
      busy = true;
      setError('');
      setStatus('合併中，請稍候…');
      if (els.merge) els.merge.disabled = true;
      try {
        const fd = new FormData();
        files.forEach((f) => fd.append('clips[]', f, f.name));
        fd.append('format', els.format ? els.format.value : 'mp4');
        const res = await fetch('media_tools_api.php?action=video_merge', { method: 'POST', body: fd });
        const ct = (res.headers.get('content-type') || '').toLowerCase();
        if (!res.ok) {
          let err = '合併失敗 HTTP ' + res.status;
          if (ct.includes('json')) {
            const j = await res.json();
            err = j.error || err;
          }
          throw new Error(err);
        }
        const blob = await res.blob();
        let filename = 'merged.mp4';
        const disp = res.headers.get('content-disposition') || '';
        const m = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"/i.exec(disp);
        if (m) filename = decodeURIComponent((m[1] || m[2] || filename).trim());
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 2000);
        setStatus('完成，已下載 ' + filename);
      } catch (e) {
        setError(e.message || '合併失敗');
        setStatus('');
      } finally {
        busy = false;
        checkEnv();
      }
    }

    root.addEventListener('click', (ev) => {
      const t = ev.target.closest('[data-vm-pick], [data-vm-merge], [data-vm-clear], [data-vm-remove]');
      if (!t) return;
      if (t.hasAttribute('data-vm-pick') && els.file) els.file.click();
      if (t.hasAttribute('data-vm-merge')) merge();
      if (t.hasAttribute('data-vm-clear')) {
        files = [];
        render();
        setStatus('');
        setError('');
      }
      if (t.hasAttribute('data-vm-remove')) {
        const i = Number(t.getAttribute('data-vm-remove'));
        files.splice(i, 1);
        render();
      }
    });
    if (els.file) els.file.addEventListener('change', () => {
      addFiles(els.file.files);
      els.file.value = '';
    });

    render();
    checkEnv();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
