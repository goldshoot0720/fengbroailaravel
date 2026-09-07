/**
 * 圖片 + 語音 = 影片
 * - 瀏覽器預覽錄製（系統朗讀 + 字幕畫面）
 * - 伺服器一鍵生成：Windows SAPI TTS + ffmpeg（嵌音軌 + 燒錄字幕）
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
      gender: root.querySelector('[data-ivv-gender]'),
      genderHint: root.querySelector('[data-ivv-gender-hint]'),
      detectGender: root.querySelector('[data-ivv-detect-gender]'),
      lang: root.querySelector('[data-ivv-lang]'),
      translate: root.querySelector('[data-ivv-translate]'),
      doTranslate: root.querySelector('[data-ivv-do-translate]'),
      status: root.querySelector('[data-ivv-status]'),
      error: root.querySelector('[data-ivv-error]'),
      env: root.querySelector('[data-ivv-env]'),
      record: root.querySelector('[data-ivv-record]'),
      stop: root.querySelector('[data-ivv-stop]'),
      download: root.querySelector('[data-ivv-download]'),
      generate: root.querySelector('[data-ivv-generate]'),
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
    let env = { ffmpeg: false, tts: false };
    let autoGender = 'male';
    let faceModelsReady = null;

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
            gender: els.gender ? els.gender.value : 'auto',
            lang: els.lang ? els.lang.value : 'zh-TW',
            translateTo: els.translate ? els.translate.value : '',
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
        if (els.gender && d.gender) els.gender.value = d.gender;
        if (els.lang && d.lang) els.lang.value = d.lang;
        if (els.translate && d.translateTo != null) els.translate.value = d.translateTo;
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

    function setGenderHint(msg) {
      if (els.genderHint) els.genderHint.textContent = msg || '';
    }

    function resolvedGender() {
      const mode = els.gender ? els.gender.value : 'auto';
      if (mode === 'auto') return autoGender === 'female' ? 'female' : 'male';
      return mode === 'male' ? 'male' : 'female';
    }

    async function ensureFaceApi() {
      if (faceModelsReady) return faceModelsReady;
      faceModelsReady = (async () => {
        const faceapi = await import('https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/+esm');
        const modelUrl = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model';
        await Promise.all([
          faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl),
          faceapi.nets.ageGenderNet.loadFromUri(modelUrl),
        ]);
        return faceapi;
      })();
      return faceModelsReady;
    }

    /**
     * Align Appwrite detectImageGender:
     * single face → model gender; else default male.
     */
    async function detectCoverGender(opts) {
      const silent = opts && opts.silent;
      if (!imageEl) {
        autoGender = 'male';
        setGenderHint(silent ? '' : '無封面圖，預設男聲');
        return { gender: 'male', message: '無封面圖，預設男聲' };
      }
      if (!silent) setGenderHint('人臉偵測中…');
      try {
        const faceapi = await ensureFaceApi();
        const options = new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.4 });
        const detections = await faceapi.detectAllFaces(imageEl, options).withAgeAndGender();
        const n = detections.length;
        if (n === 0) {
          autoGender = 'male';
          const msg = '未偵測到人臉，預設男聲';
          setGenderHint(msg);
          return { gender: 'male', message: msg };
        }
        if (n > 1) {
          autoGender = 'male';
          const msg = '偵測到 ' + n + ' 張人臉，預設男聲';
          setGenderHint(msg);
          return { gender: 'male', message: msg };
        }
        const det = detections[0];
        const raw = String(det.gender || '').toLowerCase();
        const gender = raw === 'female' ? 'female' : 'male';
        const conf = Number(det.genderProbability) || 0;
        autoGender = gender;
        const msg =
          '單一人臉 → ' +
          (gender === 'female' ? '女聲' : '男聲') +
          (conf ? '（' + Math.round(conf * 100) + '%）' : '');
        setGenderHint(msg);
        return { gender, message: msg, confidence: conf };
      } catch (e) {
        autoGender = 'male';
        const msg = '人臉模型載入失敗，預設男聲';
        setGenderHint(msg);
        return { gender: 'male', message: msg, error: e.message };
      }
    }

    async function refreshEnv() {
      try {
        const res = await fetch('media_tools_api.php?action=status');
        const data = await res.json();
        env = { ffmpeg: !!data.ffmpeg, tts: !!data.tts };
        if (els.env) {
          els.env.textContent = data.ffmpeg
            ? 'ffmpeg 就緒 · TTS：' + (data.ttsEngine || 'google') + '（多語）'
            : '缺少 ffmpeg（伺服器一鍵生成不可用）';
          els.env.classList.toggle('is-ready', !!(data.ffmpeg && data.tts));
          els.env.classList.toggle('is-missing', !data.ffmpeg);
        }
        if (els.generate) els.generate.disabled = !(data.ffmpeg && data.tts);
      } catch (e) {
        if (els.env) {
          els.env.textContent = '無法檢查媒體環境';
          els.env.classList.add('is-missing');
        }
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
      ctx.drawImage(imageEl, (w - dw) / 2, (h - dh) / 2, dw, dh);
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
        } else line = test;
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
      return voices.find((v) => /zh(-|_)?TW|zh-CN|Chinese|漢語|中文/i.test(v.lang + v.name)) || voices[0] || null;
    }

    function speakLine(text, rate) {
      return new Promise((resolve) => {
        if (!window.speechSynthesis) {
          resolve();
          return;
        }
        const u = new SpeechSynthesisUtterance(text);
        const voice = pickVoice();
        if (voice) u.voice = voice;
        u.lang = (voice && voice.lang) || 'zh-TW';
        u.rate = Math.min(1.4, Math.max(0.7, 1 + Number(rate || 0) * 0.25));
        u.onend = () => resolve();
        u.onerror = () => resolve();
        speechSynthesis.speak(u);
      });
    }

    function chooseMime() {
      const types = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'];
      for (const t of types) {
        if (window.MediaRecorder && MediaRecorder.isTypeSupported(t)) {
          return { mimeType: t, ext: t.includes('mp4') ? 'mp4' : 'webm' };
        }
      }
      return { mimeType: '', ext: 'webm' };
    }

    function sleep(ms) {
      return new Promise((r) => setTimeout(r, ms));
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
      setStatus('瀏覽器錄製中…');
      if (els.record) els.record.disabled = true;
      if (els.stop) els.stop.disabled = false;
      if (els.download) els.download.disabled = true;

      if (window.speechSynthesis) speechSynthesis.getVoices();
      const { mimeType, ext } = chooseMime();
      drawFrame(lines[0]);
      const stream = els.canvas.captureStream(30);
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
        setStatus('朗讀 ' + (i + 1) + '/' + lines.length);
        drawFrame(line);
        const speakP = speakLine(line, rate);
        const anim = setInterval(() => {
          if (recording) drawFrame(line);
        }, 100);
        await speakP;
        clearInterval(anim);
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
      applyResult(new Blob(chunks, { type: mimeType || 'video/webm' }), ext);
      setStatus(abortRec ? '已停止' : '瀏覽器預覽完成（音軌可能未嵌入）。完整 MP4 請用「伺服器一鍵生成」。');
      saveDraft();
    }

    function applyResult(blob, ext) {
      resultBlob = blob;
      if (resultUrl) URL.revokeObjectURL(resultUrl);
      resultUrl = URL.createObjectURL(blob);
      if (els.result) {
        els.result.src = resultUrl;
        els.result.style.display = '';
      }
      if (els.download) {
        els.download.disabled = false;
        els.download.dataset.ext = ext || 'mp4';
      }
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

    async function serverGenerate() {
      if (!imageFile) {
        setError('請先上傳封面圖片');
        return;
      }
      const script = els.script ? els.script.value.trim() : '';
      if (!script) {
        setError('請輸入語音稿');
        return;
      }
      if (!env.ffmpeg || !env.tts) {
        setError('伺服器環境不足（需要 ffmpeg + 可連外網 TTS）');
        return;
      }
      setError('');
      if (els.gender && els.gender.value === 'auto') {
        setStatus('自動偵測人臉聲線…');
        await detectCoverGender({ silent: false });
      }
      setStatus('伺服器 TTS + ffmpeg 合成中（可能需數十秒）…');
      if (els.generate) els.generate.disabled = true;
      try {
        const fd = new FormData();
        fd.append('image', imageFile, imageFile.name || 'cover.jpg');
        fd.append('script', script);
        fd.append('gender', resolvedGender());
        fd.append('rate', els.rate ? els.rate.value : '0');
        fd.append('orientation', els.orient ? els.orient.value : 'auto');
        fd.append('lang', els.lang ? els.lang.value : 'zh-TW');
        if (els.translate && els.translate.value) fd.append('translateTo', els.translate.value);
        const res = await fetch('media_tools_api.php?action=ivv_generate', { method: 'POST', body: fd });
        const ct = (res.headers.get('content-type') || '').toLowerCase();
        if (!res.ok) {
          let err = '生成失敗';
          if (ct.includes('json')) {
            const j = await res.json();
            err = j.error || err;
          }
          throw new Error(err);
        }
        applyResult(await res.blob(), 'mp4');
        setStatus('伺服器生成完成：已嵌入語音與字幕，可下載 MP4');
        saveDraft();
      } catch (e) {
        setError(e.message || '生成失敗');
        setStatus('就緒');
      } finally {
        refreshEnv();
      }
    }

    async function serverWithAudioFile() {
      if (!imageFile) {
        setError('請先上傳圖片');
        return;
      }
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
          const res = await fetch('media_tools_api.php?action=image_voice_video', { method: 'POST', body: fd });
          const ct = (res.headers.get('content-type') || '').toLowerCase();
          if (!res.ok) {
            let err = '合成失敗';
            if (ct.includes('json')) {
              const j = await res.json();
              err = j.error || err;
            }
            throw new Error(err);
          }
          applyResult(await res.blob(), 'mp4');
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
        const hint = root.querySelector('[data-ivv-drop-hint]');
        if (hint) hint.style.display = 'none';
        drawFrame('');
        setError('');
        setStatus('已載入圖片');
        if (els.gender && els.gender.value === 'auto') {
          detectCoverGender({ silent: false });
        }
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
      const hint = root.querySelector('[data-ivv-drop-hint]');
      if (hint) hint.style.display = '';
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
    if (els.gender) {
      els.gender.addEventListener('change', () => {
        saveDraft();
        if (els.gender.value === 'auto' && imageEl) detectCoverGender({ silent: false });
        else if (els.gender.value !== 'auto') {
          setGenderHint(els.gender.value === 'female' ? '手動：女聲' : '手動：男聲');
        }
      });
    }
    if (els.detectGender) {
      els.detectGender.addEventListener('click', () => detectCoverGender({ silent: false }));
    }
    if (els.lang) els.lang.addEventListener('change', saveDraft);
    if (els.translate) els.translate.addEventListener('change', saveDraft);
    if (els.doTranslate) {
      els.doTranslate.addEventListener('click', async () => {
        const target = els.translate && els.translate.value;
        if (!target) {
          setError('請先選擇「朗讀語言（可翻譯）」');
          return;
        }
        const lines = parseLines(els.script && els.script.value);
        if (!lines.length) {
          setError('請先輸入語音稿');
          return;
        }
        setError('');
        setStatus('翻譯中…');
        try {
          const res = await fetch('media_tools_api.php?action=translate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              lines,
              language: target,
              source_language: els.lang ? els.lang.value : 'auto',
            }),
          });
          const data = await res.json();
          if (!data.success) throw new Error(data.error || '翻譯失敗');
          if (els.script) els.script.value = (data.lines || []).join('\n');
          if (els.lang) els.lang.value = target;
          if (els.translate) els.translate.value = '';
          saveDraft();
          setStatus('已寫入翻譯結果（並將稿件語言改為目標語）');
        } catch (e) {
          setError(e.message || '翻譯失敗');
          setStatus('就緒');
        }
      });
    }
    if (els.record) els.record.addEventListener('click', () => recordBrowser());
    if (els.stop) {
      els.stop.disabled = true;
      els.stop.addEventListener('click', stopRec);
    }
    if (els.download) {
      els.download.disabled = true;
      els.download.addEventListener('click', downloadResult);
    }
    if (els.generate) els.generate.addEventListener('click', serverGenerate);
    if (els.server) els.server.addEventListener('click', serverWithAudioFile);
    if (els.clear) els.clear.addEventListener('click', clearAll);

    if (window.speechSynthesis) {
      speechSynthesis.onvoiceschanged = () => {};
      speechSynthesis.getVoices();
    }

    loadDraft();
    refreshEnv();
    setStatus('就緒 — 建議使用「伺服器一鍵生成」取得嵌音軌 MP4');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
