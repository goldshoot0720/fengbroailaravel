/**
 * YouTube / Bilibili → MP3/MP4（伺服器 yt-dlp + ffmpeg）
 */
(function () {
  'use strict';

  const COOKIES_KEY = 'fengbro.tools.ytbili.cookies';

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function init() {
    const root = document.getElementById('ytbiliTool');
    if (!root) return;

    const els = {
      status: $('[data-yb-status]', root),
      statusNote: $('[data-yb-status-note]', root),
      urls: $('[data-yb-urls]', root),
      format: $('[data-yb-format]', root),
      qualityWrap: $('[data-yb-quality-wrap]', root),
      quality: $('[data-yb-quality]', root),
      cookies: $('[data-yb-cookies]', root),
      log: $('[data-yb-log]', root),
      error: $('[data-yb-error]', root),
      convert: $('[data-yb-convert]', root),
      refresh: $('[data-yb-refresh]', root),
    };

    let busy = false;
    try {
      const saved = localStorage.getItem(COOKIES_KEY);
      if (saved && els.cookies) els.cookies.value = saved;
    } catch (e) {
      /* ignore */
    }

    function setError(msg) {
      if (els.error) els.error.textContent = msg || '';
    }
    function setLog(msg) {
      if (els.log) els.log.textContent = msg || '';
    }

    async function refreshStatus() {
      if (els.status) els.status.textContent = '檢查中…';
      try {
        const res = await fetch('media_tools_api.php?action=status');
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '狀態失敗');
        if (els.status) {
          els.status.textContent = data.available ? '就緒' : '缺少工具';
          els.status.classList.toggle('is-ready', !!data.available);
          els.status.classList.toggle('is-missing', !data.available);
        }
        if (els.statusNote) {
          const hints = (data.installHint || []).join(' ');
          els.statusNote.textContent =
            (data.note || '') +
            (data.ytDlpPath ? ' · yt-dlp: ' + data.ytDlpPath : '') +
            (data.ffmpegPath ? ' · ffmpeg: ' + data.ffmpegPath : '') +
            (hints ? ' · ' + hints : '');
        }
        if (els.convert) els.convert.disabled = !data.available;
      } catch (e) {
        if (els.status) {
          els.status.textContent = '無法檢查';
          els.status.classList.add('is-missing');
        }
        if (els.statusNote) els.statusNote.textContent = e.message || '狀態 API 失敗';
      }
    }

    function collectUrls() {
      const raw = (els.urls && els.urls.value) || '';
      return raw
        .split(/\r?\n/)
        .map((s) => s.trim())
        .filter(Boolean)
        .slice(0, 7);
    }

    function toggleQuality() {
      if (!els.qualityWrap || !els.format) return;
      els.qualityWrap.style.display = els.format.value === 'mp4' ? '' : 'none';
    }

    async function convert() {
      if (busy) return;
      const urls = collectUrls();
      if (!urls.length) {
        setError('請至少貼上一個 YouTube 或 Bilibili 網址');
        return;
      }
      const format = els.format ? els.format.value : 'mp3';
      const mp4Quality = els.quality ? els.quality.value : '1080p';
      const cookies = els.cookies ? els.cookies.value.trim() : '';
      try {
        if (cookies) localStorage.setItem(COOKIES_KEY, cookies);
      } catch (e) {
        /* ignore */
      }

      busy = true;
      setError('');
      setLog('轉檔中，請稍候（可能需數分鐘）…');
      if (els.convert) els.convert.disabled = true;

      try {
        const res = await fetch('media_tools_api.php?action=ytbili_convert', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: '*/*' },
          body: JSON.stringify({ urls, format, mp4Quality, cookies: cookies || null }),
        });
        const ct = (res.headers.get('content-type') || '').toLowerCase();
        if (!res.ok) {
          let err = '轉檔失敗 HTTP ' + res.status;
          if (ct.includes('application/json')) {
            const j = await res.json();
            err = j.error || err;
          } else {
            err = (await res.text()) || err;
          }
          throw new Error(err);
        }
        if (ct.includes('application/json')) {
          const j = await res.json();
          throw new Error(j.error || '伺服器回傳 JSON 而非檔案');
        }
        const blob = await res.blob();
        let filename = 'download.' + (format === 'mp4' ? 'mp4' : 'mp3');
        const disp = res.headers.get('content-disposition') || '';
        const m = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"/i.exec(disp);
        if (m) {
          filename = decodeURIComponent((m[1] || m[2] || filename).trim());
        }
        const xf = res.headers.get('x-fengbro-filename');
        if (xf) {
          try {
            filename = decodeURIComponent(xf);
          } catch (e) {
            /* keep */
          }
        }
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 2000);
        const sc = res.headers.get('x-fengbro-success-count') || '';
        const tot = res.headers.get('x-fengbro-total') || '';
        setLog('完成，已開始下載 ' + filename + (sc ? '（成功 ' + sc + '/' + tot + '）' : ''));
      } catch (e) {
        setError(e.message || '轉檔失敗');
        setLog('');
      } finally {
        busy = false;
        if (els.convert) els.convert.disabled = false;
        refreshStatus();
      }
    }

    if (els.refresh) els.refresh.addEventListener('click', refreshStatus);
    if (els.convert) els.convert.addEventListener('click', convert);
    if (els.format) els.format.addEventListener('change', toggleQuality);
    toggleQuality();
    refreshStatus();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
