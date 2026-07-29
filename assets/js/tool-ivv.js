/**
 * 圖片 + 語音 = 影片（瀏覽器 speechSynthesis + canvas + MediaRecorder）
 * 可選：將封面圖 + 錄音上傳伺服器 ffmpeg 合成 MP4。
 */
(function () {
  'use strict';

  const DRAFT_KEY = 'fengbro.tools.ivv.draft';

  function init() {
    const root = document.getElementById('ivvTool');
    if (!root) return;

    const els = {
      drop: root.querySelector('[data-ivv-drop]'),
      file: root.querySelector('[data-ivv-file]'),
      preview: root.querySelector('[data-ivv-preview]'),
      script: root.querySelector('[data-ivv-script]'),
      rate: root.querySelector('[data-ivv-rate]'),
      rateLabel: root.querySelector('[data-ivv-rate-label]'),
      orient: root.querySelector('[data-ivv-orient]'),
      status: root.querySelector('[data-ivv-status]'),
      error: root.querySelector('[data-ivv-error]'),
      record: root.querySelector('[data-ivv-record]'),
      stop: root.querySelector('[data-ivv-stop]'),
      download: root.querySelector('[data-ivv-download]'),
      server: root.querySelector('[data-ivv-server]'),
      result: root.querySelector('[data-ivv-result]'),
      canvas: root.querySelector('[data-ivv-canvas]'),
      clear: root.querySelector('[data-ivv-clear]'),
    };

    let imageEl = null;
    let imageFile = null;
    let previewUrl = null;
    let resultUrl = null;
    let resultBlob = null;
    let recording = false;
    let abortRec = false;

    function setStatus(m) {
      if (els.status) els.status.textContent = m || '';
    }
    function setError(m) {
      if (els.error) els.error.textContent = m || '';
    }

    function saveDraft() {
      try {
        localStorage.setItem(
          DRAFT_KEY,
          JSON.stringify({
            script: els.script ? els.script.value : '',
            rate: els.rate ? els.rate.value : '0',
            orient: els.orient ? els.orient.value : 'auto',
          })
        );
      } catch (e) {
        /* ignore */
      }
    }

    function loadDraft() {
      try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return;
        const d = JSON.parse(raw);
        if (els.script && d.script != null) els.script.value = d.script;
        if (els.rate && d.rate != null) els.rate.value = d.rate;
        if (els.orient && d.orient) els.orient.value = d.orient;
        updateRateLabel();
      } catch (e) {
        /* ignore */
      }
    }

    function updateRateLabel() {
      if (els.rate && els.rateLabel) {
        const v = Number(els.rate.value || 0);
        els.rateLabel.textContent = (v >= 0 ? '+' : '') + v;
      }
    }

    function resolveSize() {
      const orient = els.orient ? els.orient.value : 'auto';
      const iw = imageEl ? imageEl.naturalWidth || 1080 : 1080;
      const ih = imageEl ? imageEl.naturalHeight || 1920 : 1920;
      if (orient === 'portrait') return { w: 1080, h: 1920 };
      if (orient === 'landscape') return { w: 1920, h: 1080 };
      if (iw >= ih) return { w: 1920, h: 1080 };
      return { w: 1080, h: 1920 };
    }

    function drawFrame(text) {
      if (!els.canvas || !imageEl) return;
      const { w, h } = resolveSize();
      els.canvas.width = w;
      els.canvas.height = h;
      const ctx = els.canvas.getContext('2d');
      ctx.fillStyle = '#000';
      ctx.fillRect(0, 0, w, h);
      const scale = Math.min(w / imageEl.naturalWidth, h / imageEl.naturalHeight);
      const dw = imageEl.naturalWidth * scale;
      const dh = imageEl.naturalHeight * scale;
      const dx = (w - dw) / 2;
      const dy = (h - dh) / 2;
      ctx.drawImage(imageEl, dx, dy, dw, dh);
      if (text) {
        const pad = Math.round(w * 0.06);
        const fontSize = Math.max(28, Math.round(w * 0.045));
        ctx.font = 'bold ' + fontSize + 'px "Microsoft JhengHei", "Noto Sans TC", sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        const lines = wrapText(ctx, text, w - pad * 2);
        const lineH = fontSize * 1.35;
        const blockH = lines.length * lineH + pad * 0.5;
        const baseY = h - pad;
        ctx.fillStyle = 'rgba(0,0,0,0.55)';
        ctx.fillRect(0, baseY - blockH, w, blockH + pad * 0.3);
        ctx.fillStyle = '#fff';
        ctx.strokeStyle = 'rgba(0,0,0,0.6)';
        ctx.lineWidth = 4;
        lines.forEach((line, i) => {
          const y = baseY - (lines.length - 1 - i) * lineH;
          ctx.strokeText(line, w / 2, y);
          ctx.fillText(line, w / 2, y);
        });
      }
    }

    function wrapText(ctx, text, maxWidth) {
      const chars = String(text || '').split('');
      const lines = [];
      let line = '';
      for (const ch of chars) {
        const test = line + ch;
        if (ctx.measureText(test).width > maxWidth && line) {
          lines.push(line);
          line = ch;
        } else {
          line = test;
        }
      }
      if (line) lines.push(line);
      return lines.slice(0, 4);
    }

    function parseLines(script) {
      return String(script || '')
        .split(/\r?\n/)
        .map((s) => s.trim())
        .filter(Boolean);
    }

    function pickVoice() {
      const voices = window.speechSynthesis ? speechSynthesis.getVoices() : [];
      const prefer = voices.find((v) => /zh(-|_)?TW|zh-CN|Chinese|漢語|中文/i.test(v.lang + v.name));
      return prefer || voices[0] || null;
    }

    function speakLine(text, rate) {
      return new Promise((resolve, reject) => {
        if (!window.speechSynthesis) {
          reject(new Error('瀏覽器不支援語音合成'));
          return;
        }
        const u = new SpeechSynthesisUtterance(text);
        const voice = pickVoice();
        if (voice) u.voice = voice;
        u.lang = (voice && voice.lang) || 'zh-TW';
        // rate: -1..1 UI → 0.7..1.4
        u.rate = Math.min(1.4, Math.max(0.7, 1 + Number(rate || 0) * 0.25));
        u.onend = () => resolve();
        u.onerror = () => resolve(); // continue even if one line fails
        speechSynthesis.speak(u);
      });
    }

    function chooseMime() {
      const types = [
        'video/webm;codecs=vp9,opus',
        'video/webm;codecs=vp8,opus',
        'video/webm',
        'video/mp4',
      ];
      for (const t of types) {
        if (window.MediaRecorder && MediaRecorder.isTypeSupported(t)) {
          return { mimeType: t, ext: t.includes('mp4') ? 'mp4' : 'webm' };
        }
      }
      return { mimeType: '', ext: 'webm' };
    }

    async function recordBrowser() {
      if (!imageEl) {
        setError('請先上傳封面圖片');
        return;
      }
      const lines = parseLines(els.script && els.script.value);
      if (!lines.length) {
        setError('請輸入語音稿（每行一句）');
        return;
      }
      if (!window.MediaRecorder) {
        setError('瀏覽器不支援 MediaRecorder');
        return;
      }

      abortRec = false;
      recording = true;
      setError('');
      setStatus('錄製中…');
      if (els.record) els.record.disabled = true;
      if (els.stop) els.stop.disabled = false;
      if (els.download) els.download.disabled = true;

      // warm voices
      if (window.speechSynthesis) speechSynthesis.getVoices();

      const { mimeType, ext } = chooseMime();
      drawFrame(lines[0]);
      const stream = els.canvas.captureStream(30);
      // capture speech via Web Audio is hard; MediaRecorder only gets canvas.
      // Use audio from speechSynthesis by capturing tab is not available.
      // Approach: generate silent video with subtitles only in browser,
      // OR use SpeechSynthesis and separately we can't mux easily without audio graph.
      // Better approach: use AudioContext + MediaStreamDestination isn't fed by speechSynthesis.
      // Practical approach: record canvas-only video with timed subtitles based on estimated speech duration,
      // then optional server path: user can use "伺服器合成" with pre-recorded audio.
      // For browser path with audio: use utterance and estimate duration, subtitles only OR
      // use SpeechRecognition alternative - no.
      // Chrome: speechSynthesis doesn't expose MediaStream.
      // We'll do timed subtitle video + optional server ffmpeg with uploaded TTS isn't available.
      // Actually implement: play TTS while recording canvas; audio won't be in video.
      // Then offer server merge if user records mic? Too complex.
      //
      // Improved: create offline audio with speech is not available.
      // Use estimateDuration and draw subtitles; note "瀏覽器版含字幕畫面；音訊請用伺服器 ffmpeg 路徑需音訊檔"
      //
      // Alternative used by many: HTML5 audio from free TTS API - skip.
      // I'll implement browser recording WITH audio using the Web Speech API workaround:
      // capture microphone while TTS plays (user hears TTS from speakers, mic picks it up) - bad UX.
      //
      // Best practical for PHP port without TTS server:
      // 1) Canvas video with subtitles, timed by speech rate estimate
      // 2) Server button: image + optional audio file upload
      // 3) Also try MediaRecorder on canvas only

      const chunks = [];
      const rec = new MediaRecorder(stream, mimeType ? { mimeType, videoBitsPerSecond: 4_000_000 } : undefined);
      rec.ondataavailable = (e) => {
        if (e.data && e.data.size) chunks.push(e.data);
      };
      const stopped = new Promise((resolve) => {
        rec.onstop = () => resolve();
      });
      rec.start(200);

      const rate = els.rate ? Number(els.rate.value || 0) : 0;
      for (let i = 0; i < lines.length; i++) {
        if (abortRec) break;
        const line = lines[i];
        setStatus('朗讀 ' + (i + 1) + '/' + lines.length + '：' + line.slice(0, 24));
        drawFrame(line);
        // animate frames during speech
        const speakP = speakLine(line, rate);
        const anim = setInterval(() => {
          if (!recording) return;
          drawFrame(line);
        }, 100);
        await speakP;
        clearInterval(anim);
        // pause between lines
        await sleep(280);
      }

      recording = false;
      if (rec.state !== 'inactive') rec.stop();
      stream.getTracks().forEach((t) => t.stop());
      await stopped;

      if (els.record) els.record.disabled = false;
      if (els.stop) els.stop.disabled = true;

      if (!chunks.length) {
        setError('沒有錄到影像');
        setStatus('就緒');
        return;
      }
      resultBlob = new Blob(chunks, { type: mimeType || 'video/webm' });
      if (resultUrl) URL.revokeObjectURL(resultUrl);
      resultUrl = URL.createObjectURL(resultBlob);
      if (els.result) {
        els.result.src = resultUrl;
        els.result.style.display = '';
      }
      if (els.download) {
        els.download.disabled = false;
        els.download.dataset.ext = ext;
      }
      setStatus(
        abortRec
          ? '已停止'
          : '完成（瀏覽器 WebM/MP4 畫面含字幕；語音由系統朗讀，若需嵌入音軌請改用下方伺服器合成並提供音訊）'
      );
      saveDraft();
    }

    function sleep(ms) {
      return new Promise((r) => setTimeout(r, ms));
    }

    function stopRec() {
      abortRec = true;
      if (window.speechSynthesis) speechSynthesis.cancel();
      setStatus('停止中…');
    }

    function downloadResult() {
      if (!resultBlob) return;
      const ext = (els.download && els.download.dataset.ext) || 'webm';
      const a = document.createElement('a');
      a.href = resultUrl;
      a.download = 'image-voice-video.' + ext;
      a.click();
    }

    async function serverCompose() {
      if (!imageFile) {
        setError('請先上傳圖片');
        return;
      }
      // Need audio file - prompt for file or use result as video only
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'audio/*,video/webm,video/mp4,.mp3,.wav,.m4a,.webm';
      input.onchange = async () => {
        const audio = input.files && input.files[0];
        if (!audio) return;
        setError('');
        setStatus('伺服器 ffmpeg 合成中…');
        const fd = new FormData();
        fd.append('image', imageFile, imageFile.name || 'cover.jpg');
        fd.append('audio', audio, audio.name || 'voice.webm');
        try {
          const res = await fetch('media_tools_api.php?action=image_voice_video', {
            method: 'POST',
            body: fd,
          });
          const ct = (res.headers.get('content-type') || '').toLowerCase();
          if (!res.ok) {
            let err = '合成失敗';
            if (ct.includes('json')) {
              const j = await res.json();
              err = j.error || err;
            }
            throw new Error(err);
          }
          const blob = await res.blob();
          if (resultUrl) URL.revokeObjectURL(resultUrl);
          resultBlob = blob;
          resultUrl = URL.createObjectURL(blob);
          if (els.result) {
            els.result.src = resultUrl;
            els.result.style.display = '';
          }
          if (els.download) {
            els.download.disabled = false;
            els.download.dataset.ext = 'mp4';
          }
          setStatus('伺服器合成完成，可下載 MP4');
        } catch (e) {
          setError(e.message || '伺服器合成失敗');
          setStatus('就緒');
        }
      };
      input.click();
    }

    function setImage(file) {
      if (!file || !file.type.startsWith('image/')) {
        setError('請選擇圖片檔');
        return;
      }
      imageFile = file;
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        imageEl = img;
        if (els.preview) {
          els.preview.src = previewUrl;
          els.preview.style.display = '';
        }
        drawFrame('');
        setError('');
        setStatus('已載入圖片，可輸入語音稿後開始錄製');
      };
      img.onerror = () => setError('圖片載入失敗');
      img.src = previewUrl;
    }

    function clearAll() {
      stopRec();
      imageEl = null;
      imageFile = null;
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      if (resultUrl) URL.revokeObjectURL(resultUrl);
      previewUrl = resultUrl = resultBlob = null;
      if (els.preview) {
        els.preview.removeAttribute('src');
        els.preview.style.display = 'none';
      }
      if (els.result) {
        els.result.removeAttribute('src');
        els.result.style.display = 'none';
      }
      if (els.script) els.script.value = '';
      if (els.download) els.download.disabled = true;
      setStatus('就緒 — 上傳圖片並輸入語音稿');
      setError('');
      try {
        localStorage.removeItem(DRAFT_KEY);
      } catch (e) {
        /* ignore */
      }
    }

    if (els.drop) {
      els.drop.addEventListener('click', () => els.file && els.file.click());
      els.drop.addEventListener('dragover', (e) => {
        e.preventDefault();
        els.drop.classList.add('is-dragover');
      });
      els.drop.addEventListener('dragleave', () => els.drop.classList.remove('is-dragover'));
      els.drop.addEventListener('drop', (e) => {
        e.preventDefault();
        els.drop.classList.remove('is-dragover');
        const f = e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) setImage(f);
      });
    }
    if (els.file) els.file.addEventListener('change', () => setImage(els.file.files[0]));
    if (els.rate) els.rate.addEventListener('input', () => { updateRateLabel(); saveDraft(); });
    if (els.script) els.script.addEventListener('change', saveDraft);
    if (els.orient) els.orient.addEventListener('change', () => { if (imageEl) drawFrame(''); saveDraft(); });
    if (els.record) els.record.addEventListener('click', () => recordBrowser());
    if (els.stop) {
      els.stop.disabled = true;
      els.stop.addEventListener('click', stopRec);
    }
    if (els.download) {
      els.download.disabled = true;
      els.download.addEventListener('click', downloadResult);
    }
    if (els.server) els.server.addEventListener('click', serverCompose);
    if (els.clear) els.clear.addEventListener('click', clearAll);

    if (window.speechSynthesis) {
      speechSynthesis.onvoiceschanged = () => {};
      speechSynthesis.getVoices();
    }

    loadDraft();
    setStatus('就緒 — 上傳圖片並輸入語音稿（每行一句）');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
