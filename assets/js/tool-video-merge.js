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
      subtitle: root.querySelector('[data-vm-subtitle]'),
      status: root.querySelector('[data-vm-status]'),
      error: root.querySelector('[data-vm-error]'),
      merge: root.querySelector('[data-vm-merge]'),
      clear: root.querySelector('[data-vm-clear]'),
      env: root.querySelector('[data-vm-env]'),
      whisper: root.querySelector('[data-vm-whisper]'),
      whisperStatus: root.querySelector('[data-vm-whisper-status]'),
      whisperLang: root.querySelector('[data-vm-whisper-lang]'),
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
        if (els.subtitle && els.subtitle.value.trim()) {
          fd.append('subtitle', els.subtitle.value.trim());
        }
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

    async function decodeWavOrAudioToMono16k(arrayBuffer) {
      const AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) throw new Error('瀏覽器不支援 AudioContext');
      const ctx = new AC({ sampleRate: 16000 });
      let audio;
      try {
        audio = await ctx.decodeAudioData(arrayBuffer.slice(0));
      } catch (e) {
        await ctx.close();
        throw e;
      }
      const ch0 = audio.getChannelData(0);
      let mono;
      if (audio.numberOfChannels > 1) {
        const ch1 = audio.getChannelData(1);
        mono = new Float32Array(ch0.length);
        for (let i = 0; i < ch0.length; i++) mono[i] = (ch0[i] + ch1[i]) / 2;
      } else {
        mono = ch0;
      }
      if (audio.sampleRate !== 16000) {
        const ratio = audio.sampleRate / 16000;
        const len = Math.floor(mono.length / ratio);
        const out = new Float32Array(len);
        for (let i = 0; i < len; i++) out[i] = mono[Math.floor(i * ratio)];
        mono = out;
      }
      const max = 16000 * 600;
      if (mono.length > max) mono = mono.slice(0, max);
      await ctx.close();
      return mono;
    }

    async function serverExtractToMono16k(file) {
      if (els.whisperStatus) els.whisperStatus.textContent = '伺服器 ffmpeg 抽音中…';
      const fd = new FormData();
      fd.append('media', file, file.name || 'clip.bin');
      fd.append('maxSeconds', '600');
      const res = await fetch('media_tools_api.php?action=extract_audio', { method: 'POST', body: fd });
      const ct = (res.headers.get('content-type') || '').toLowerCase();
      if (!res.ok) {
        let err = '抽音失敗';
        if (ct.includes('json')) {
          const j = await res.json();
          err = j.error || err;
        }
        throw new Error(err);
      }
      const buf = await res.arrayBuffer();
      return decodeWavOrAudioToMono16k(buf);
    }

    async function fileToMono16k(file) {
      // Prefer client decode for audio/*; for video always try server extract first
      const isAudio = (file.type || '').startsWith('audio/') || /\.(mp3|wav|m4a|ogg|flac)$/i.test(file.name || '');
      if (isAudio) {
        try {
          return await decodeWavOrAudioToMono16k(await file.arrayBuffer());
        } catch (e) {
          return serverExtractToMono16k(file);
        }
      }
      try {
        return await serverExtractToMono16k(file);
      } catch (e1) {
        try {
          return await decodeWavOrAudioToMono16k(await file.arrayBuffer());
        } catch (e2) {
          throw new Error(e1.message || e2.message || '無法取得音訊');
        }
      }
    }

    function chunksToLines(chunks) {
      if (!chunks || !chunks.length) return [];
      return chunks
        .map((c) => String(c.text || '').trim())
        .filter(Boolean);
    }

    async function runWhisper() {
      if (!files.length) {
        setError('請先選擇至少一個片段');
        return;
      }
      const file = files[0];
      if (els.whisper) els.whisper.disabled = true;
      setError('');
      try {
        if (els.whisperStatus) els.whisperStatus.textContent = '準備音訊…';
        const mono = await fileToMono16k(file);
        if (els.whisperStatus) els.whisperStatus.textContent = '載入 Whisper tiny（首次較久）…';
        const mod = await import('https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.5.1');
        const pipeline = mod.pipeline;
        const transcriber = await pipeline('automatic-speech-recognition', 'Xenova/whisper-tiny', {
          dtype: 'fp32',
          device: 'wasm',
        });
        if (els.whisperStatus) els.whisperStatus.textContent = '辨識中…';
        const lang = (els.whisperLang && els.whisperLang.value) || 'chinese';
        const asrOpts = {
          task: 'transcribe',
          return_timestamps: true,
          chunk_length_s: 15,
          stride_length_s: 2,
        };
        if (lang && lang !== 'auto') {
          asrOpts.language = lang;
        }
        const result = await transcriber(mono, asrOpts);
        let lines = [];
        if (result && Array.isArray(result.chunks)) {
          lines = chunksToLines(result.chunks);
        } else if (result && result.text) {
          lines = String(result.text)
            .split(/[。！？!?\n]+/)
            .map((s) => s.trim())
            .filter(Boolean);
        }
        if (!lines.length) throw new Error('未辨識到內容');
        if (els.subtitle) els.subtitle.value = lines.join('\n');
        if (els.whisperStatus) els.whisperStatus.textContent = '完成 ' + lines.length + ' 句（含伺服器抽音備援）';
        setStatus('Whisper 字幕已就緒，可開始合併並燒錄');
      } catch (e) {
        setError(e.message || 'Whisper 失敗');
        if (els.whisperStatus) els.whisperStatus.textContent = '';
      } finally {
        if (els.whisper) els.whisper.disabled = false;
      }
    }

    root.addEventListener('click', (ev) => {
      const t = ev.target.closest('[data-vm-pick], [data-vm-merge], [data-vm-clear], [data-vm-remove], [data-vm-whisper]');
      if (!t) return;
      if (t.hasAttribute('data-vm-pick') && els.file) els.file.click();
      if (t.hasAttribute('data-vm-merge')) merge();
      if (t.hasAttribute('data-vm-whisper')) runWhisper();
      if (t.hasAttribute('data-vm-clear')) {
        files = [];
        render();
        setStatus('');
        setError('');
        if (els.whisperStatus) els.whisperStatus.textContent = '';
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
