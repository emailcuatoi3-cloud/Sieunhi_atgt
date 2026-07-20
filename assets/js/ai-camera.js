/* ai-camera.js — Tương tác cho trang AI Camera
 *
 * Hai chế độ:
 *   - Mock (khi AI_CAMERA_ENABLED=false hoặc thiếu key/model)
 *     → giữ nguyên hành vi cũ: bấm "Quét lại" → random 94-98%.
 *   - Real (khi flag bật + có Roboflow key/model)
 *     → mở camera, load model, chạy inference thật.
 *
 * Server đã quyết định chế độ trong window.__AI_CAMERA__.
 * Client KHÔNG được phép tự bật real mode.
 */
(function () {
  'use strict';

  const cfg = window.__AI_CAMERA__ || { enabled: false, key: '', model: '' };

  document.addEventListener('DOMContentLoaded', () => {
    if (cfg.enabled) {
      try {
        runRealMode(cfg);
      } catch (e) {
        console.warn('AI camera real mode failed to init, falling back to mock', e);
        runMockMode();
        showFallbackBanner();
      }
    } else {
      runMockMode();
    }
    wireTabs();
  });

  function showFallbackBanner() {
    const el = document.getElementById('camStatusBanner');
    if (!el) return;
    el.hidden = false;
    el.textContent = 'Đang chạy chế độ demo — chưa kết nối được model AI.';
  }

  // ---------- MOCK MODE (byte-identical to trước khi refactor) ----------

  function runMockMode() {
    window.rescan = function () {
      const badge = document.getElementById('accBadge');
      const scanLine = document.querySelector('.scan-line');
      badge.textContent = '⏳ Đang phân tích...';
      badge.style.color = 'var(--cyan)';
      badge.style.background = 'rgba(34,211,238,0.15)';
      if (scanLine) scanLine.style.animationDuration = '0.6s';
      setTimeout(() => {
        const values = [94, 95, 96, 97, 98];
        const v = values[Math.floor(Math.random() * values.length)];
        badge.textContent = '✓ Độ chính xác ' + v + '%';
        badge.style.color = 'var(--green)';
        badge.style.background = 'rgba(52,211,153,0.15)';
        if (scanLine) scanLine.style.animationDuration = '2.6s';
      }, 1300);
    };
  }

  // ---------- HelmetDetector: Roboflow Hosted API (no client-side model) ----------
  //
  // Vi sao KHONG dung inferencejs: da so public model tren Roboflow Universe
  // KHONG duoc owner export cho browser -> inferencejs "Model init failed".
  // Hosted API (detect.roboflow.com) chay tren server Roboflow, ho tro moi
  // public model. Trade-off: frame anh phai POST len server (Roboflow claim
  // khong luu). Xem README section "AI Camera" de biet privacy caveat.

  const HelmetDetector = {
    async load(publishableKey, modelSlugWithVersion) {
      const slash = modelSlugWithVersion.lastIndexOf('/');
      if (slash < 1) {
        const e = new Error('ROBOFLOW_MODEL must be "<slug>/<version>"');
        e.name = 'RoboflowInitError';
        throw e;
      }
      const slug = modelSlugWithVersion.slice(0, slash);
      const version = modelSlugWithVersion.slice(slash + 1);
      const endpoint =
        `https://detect.roboflow.com/${encodeURIComponent(slug)}/${encodeURIComponent(version)}` +
        `?api_key=${encodeURIComponent(publishableKey)}`;

      // --- Preflight probe: fail fast if key/model wrong ---
      // Send a 1x1 transparent PNG so we get a decisive HTTP status without
      // spending user bandwidth on a real frame.
      const TINY_PNG_B64 =
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
      let probeRes;
      try {
        probeRes = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: TINY_PNG_B64,
        });
      } catch (netErr) {
        const e = new Error(
          'Không kết nối được Roboflow (network / CORS lỗi). Kiểm tra internet.'
        );
        e.name = 'RoboflowInitError';
        e.cause = netErr;
        throw e;
      }
      if (!probeRes.ok) {
        const text = await probeRes.text().catch(() => '');
        console.error('[HelmetDetector] Preflight failed', {
          slug, version, status: probeRes.status, body: text,
        });
        let hint = '';
        if (probeRes.status === 401 || probeRes.status === 403) {
          hint = 'Publishable Key sai hoặc hết hạn.';
        } else if (probeRes.status === 404) {
          hint = `Model "${slug}/${version}" không tồn tại hoặc chưa public.`;
        } else if (probeRes.status === 429) {
          hint = 'Quota Roboflow đã hết cho hôm nay.';
        } else {
          hint = `Roboflow trả HTTP ${probeRes.status}.`;
        }
        const e = new Error(hint + ' Chi tiết trong Console.');
        e.name = 'RoboflowInitError';
        throw e;
      }

      // Reusable capture canvas — avoids allocating one per frame.
      const captureCanvas = document.createElement('canvas');
      const captureCtx = captureCanvas.getContext('2d');

      return {
        async detect(mediaElement) {
          const w = mediaElement.videoWidth  || mediaElement.naturalWidth  || mediaElement.width;
          const h = mediaElement.videoHeight || mediaElement.naturalHeight || mediaElement.height;
          if (!w || !h) return [];
          captureCanvas.width = w;
          captureCanvas.height = h;
          captureCtx.drawImage(mediaElement, 0, 0, w, h);
          const base64 = captureCanvas.toDataURL('image/jpeg', 0.6).split(',', 2)[1];

          const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: base64,
          });
          if (!res.ok) {
            console.warn('[HelmetDetector] detect() non-OK', res.status);
            return [];
          }
          const json = await res.json();
          return (json.predictions || []).map(p => ({
            className: p.class || 'helmet',
            confidence: typeof p.confidence === 'number' ? p.confidence : (p.score || 0),
            bbox: {
              x: p.x ?? 0,
              y: p.y ?? 0,
              width:  p.width  ?? 0,
              height: p.height ?? 0,
            },
          }));
        },
        async destroy() { /* nothing to destroy — no worker */ },
      };
    },
  };

  // ---------- CameraStream: getUserMedia wrapper ----------

  const CameraStream = {
    async start(videoEl) {
      if (!navigator.mediaDevices?.getUserMedia) {
        const err = new Error('getUserMedia unavailable — dùng localhost hoặc HTTPS');
        err.name = 'NotSecureError';
        throw err;
      }

      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
      });

      videoEl.srcObject = stream;
      await new Promise(resolve => {
        if (videoEl.readyState >= 2) return resolve();
        videoEl.onloadedmetadata = () => resolve();
      });
      await videoEl.play().catch(() => { /* autoplay policies — muted+playsinline should be fine */ });

      return {
        stop() {
          stream.getTracks().forEach(t => t.stop());
          videoEl.srcObject = null;
        },
      };
    },
  };

  // ---------- FrameLoop: throttled tick, self-paces to detector latency ----------

  const FrameLoop = {
    start(callback, intervalMs = 500) {
      let stopped = false;
      let timer = null;

      async function tick() {
        if (stopped) return;
        try { await callback(); } catch (e) { console.warn('frame callback error', e); }
        if (stopped) return;
        timer = setTimeout(() => requestAnimationFrame(tick), intervalMs);
      }

      requestAnimationFrame(tick);

      return {
        stop() {
          stopped = true;
          if (timer) clearTimeout(timer);
        },
      };
    },
  };

  // ---------- ResultRenderer: draw bboxes + update side panel + banner ----------

  const ResultRenderer = (function () {
    const CONF_HIGH = 0.60;
    const CONF_MID  = 0.30;
    // Classes tra ve tu hard-hat-workers/12 va cac model tuong tu.
    // Groupd theo semantic — de swap model sau nay khong phai sua nhieu cho.
    const HELMET_CLASSES = ['helmet', 'hardhat', 'hard-hat', 'mu', 'mu-bao-hiem'];
    const PERSON_CLASSES = ['person', 'head', 'no-helmet', 'no-hardhat', 'nohelmet'];

    function tierFor(conf) {
      if (conf >= CONF_HIGH) return 'ok';
      if (conf >= CONF_MID)  return 'warn';
      return 'idle';
    }

    function colorFor(tier) {
      const cs = getComputedStyle(document.documentElement);
      switch (tier) {
        case 'ok':    return cs.getPropertyValue('--green').trim()  || '#2ecc71';
        case 'warn':  return cs.getPropertyValue('--yellow').trim() || '#f5b642';
        case 'alarm': return cs.getPropertyValue('--pink').trim()   || '#ef4444';
        default:      return 'rgba(255,255,255,0.35)';
      }
    }

    /**
     * Analyze detections and return a semantic state.
     *   { state: 'ok'   | 'warn' | 'alarm' | 'idle',
     *     topHelmet: Detection | null,
     *     personSeen: boolean }
     *
     * - 'ok'    = helmet detected with high confidence
     * - 'warn'  = helmet detected with mid confidence
     * - 'alarm' = person/head detected but NO helmet → cần đội mũ ngay
     * - 'idle'  = nothing meaningful in frame
     */
    function analyze(dets) {
      const isHelmet = c => HELMET_CLASSES.includes(String(c || '').toLowerCase());
      const isPerson = c => PERSON_CLASSES.includes(String(c || '').toLowerCase());
      let topHelmet = null;
      let personSeen = false;
      for (const d of dets) {
        if (d.confidence < CONF_MID) continue;
        if (isHelmet(d.className) && (!topHelmet || d.confidence > topHelmet.confidence)) {
          topHelmet = d;
        }
        if (isPerson(d.className) && d.confidence >= CONF_HIGH) {
          personSeen = true;
        }
      }
      if (topHelmet && topHelmet.confidence >= CONF_HIGH) return { state: 'ok',    topHelmet, personSeen };
      if (topHelmet)                                      return { state: 'warn',  topHelmet, personSeen };
      if (personSeen)                                     return { state: 'alarm', topHelmet: null, personSeen: true };
      return { state: 'idle', topHelmet: null, personSeen: false };
    }

    return {
      analyze,

      updateBadge(text, tier) {
        const badge = document.getElementById('accBadge');
        if (!badge) return;
        badge.textContent = text;
        const map = {
          ok:        { color: 'var(--green)',  bg: 'rgba(46,204,113,0.15)' },
          warn:      { color: 'var(--yellow)', bg: 'rgba(245,182,66,0.15)' },
          alarm:     { color: 'var(--pink)',   bg: 'rgba(239,68,68,0.18)'  },
          idle:      { color: 'rgba(255,255,255,0.55)', bg: 'rgba(255,255,255,0.06)' },
          analyzing: { color: 'var(--cyan)',   bg: 'rgba(79,195,247,0.15)' },
        };
        const s = map[tier] || map.idle;
        badge.style.color = s.color;
        badge.style.background = s.bg;
      },

      /**
       * Toggle alarm visuals on the whole camera view (red pulsing border + banner).
       * Called on every frame with the current state.
       */
      setAlarm(on, message) {
        const camView = document.querySelector('.cam-view');
        if (camView) camView.classList.toggle('alarm', !!on);
        if (on) {
          this.setStatus(message || '⛔ CHƯA ĐỘI MŨ BẢO HIỂM — HÃY ĐỘI MŨ NGAY!');
        }
        // Note: don't clear status when off — other callers (low-light hint,
        // preflight error) may have set it. Only the caller that set it clears it.
      },

      drawBoxes(canvas, videoEl, detections) {
        const w = videoEl.videoWidth  || videoEl.naturalWidth  || canvas.clientWidth;
        const h = videoEl.videoHeight || videoEl.naturalHeight || canvas.clientHeight;
        if (canvas.width !== w)  canvas.width  = w;
        if (canvas.height !== h) canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, w, h);
        for (const d of detections) {
          const tier = tierFor(d.confidence);
          if (tier === 'idle') continue;
          const stroke = colorFor(tier);
          const x = d.bbox.x - d.bbox.width / 2;
          const y = d.bbox.y - d.bbox.height / 2;
          ctx.lineWidth = 3;
          ctx.strokeStyle = stroke;
          ctx.strokeRect(x, y, d.bbox.width, d.bbox.height);

          const label = `⛑️ ${d.className} · ${Math.round(d.confidence * 100)}%`;
          ctx.font = 'bold 16px "Be Vietnam Pro", sans-serif';
          const pad = 6;
          const tw = ctx.measureText(label).width;
          ctx.fillStyle = 'rgba(7,27,58,0.85)';
          ctx.fillRect(x, y - 26, tw + pad * 2, 22);
          ctx.fillStyle = stroke;
          ctx.fillText(label, x + pad, y - 10);
        }
      },

      /**
       * Update the helmet card + advice text based on the semantic state,
       * not raw detection presence. state: 'ok' | 'warn' | 'alarm' | 'idle'.
       */
      updateHelmetCard(state, top) {
        const card = document.querySelector('.result-panel .detect-item');
        if (!card) return;
        const title = card.querySelector('.d-info b');
        const sub   = card.querySelector('.d-info span');
        const advice = document.querySelector('.advice-box p');
        card.classList.remove('ok', 'warn', 'alarm');

        if (state === 'ok') {
          card.classList.add('ok');
          if (title)  title.childNodes[0].nodeValue = 'Mũ bảo hiểm — Đạt chuẩn ';
          if (sub)    sub.textContent = `Model tự tin ${Math.round(top.confidence * 100)}%`;
          if (advice) advice.textContent = 'Con đội mũ bảo hiểm rất chuẩn rồi! Nhớ luôn cài quai và quan sát hai bên trước khi qua đường nhé.';
        } else if (state === 'warn') {
          card.classList.add('warn');
          if (title)  title.childNodes[0].nodeValue = 'Mũ bảo hiểm — Chưa rõ ';
          if (sub)    sub.textContent = `Chỉ ${Math.round(top.confidence * 100)}% chắc chắn — hãy đưa mũ vào chính giữa khung`;
          if (advice) advice.textContent = 'Model chưa nhìn rõ mũ bảo hiểm. Con thử đưa mũ vào giữa khung, cách camera 1 sải tay nhé.';
        } else if (state === 'alarm') {
          card.classList.add('alarm');
          if (title)  title.childNodes[0].nodeValue = '⛔ CHƯA ĐỘI MŨ BẢO HIỂM! ';
          if (sub)    sub.textContent = 'Camera thấy có người trong khung nhưng KHÔNG có mũ — hãy đội mũ ngay trước khi tham gia giao thông.';
          if (advice) advice.textContent = 'Tham gia giao thông mà không đội mũ bảo hiểm là VI PHẠM luật giao thông và rất nguy hiểm. Hãy đội mũ bảo hiểm ngay và cài chặt quai trước khi ra đường!';
        } else {
          // idle
          card.classList.add('warn');
          if (title)  title.childNodes[0].nodeValue = 'Đang chờ... ';
          if (sub)    sub.textContent = 'Chưa thấy ai trong khung camera';
          if (advice) advice.textContent = 'Camera đang chờ — hãy đứng trước khung camera để AI kiểm tra mũ bảo hiểm của con.';
        }
      },

      setStatus(text) {
        const el = document.getElementById('camStatusBanner');
        if (!el) return;
        if (!text) { el.hidden = true; el.textContent = ''; return; }
        el.hidden = false;
        el.textContent = text;
      },
    };
  })();

  // ---------- ScanHistory: last-5 tier log in localStorage ----------

  const ScanHistory = (function () {
    const KEY = 'sieunhi.aicam.history';
    const MAX = 5;

    function read() {
      try {
        const raw = localStorage.getItem(KEY);
        if (!raw) return [];
        const arr = JSON.parse(raw);
        return Array.isArray(arr) ? arr.slice(0, MAX) : [];
      } catch (_) { return []; }
    }
    function write(arr) {
      try { localStorage.setItem(KEY, JSON.stringify(arr.slice(0, MAX))); } catch (_) { /* quota */ }
    }

    return {
      push(det) {
        const tier = det.confidence >= 0.60 ? 'ok' : (det.confidence >= 0.30 ? 'warn' : null);
        if (!tier) return;
        const arr = read();
        const now = Date.now();
        if (arr[0] && arr[0].tier === tier && (now - arr[0].ts) < 3000) return;
        arr.unshift({ tier, confidence: det.confidence, ts: now });
        write(arr);
        this.render();
      },
      render() {
        const strip = document.querySelector('.history-strip');
        if (!strip) return;
        const arr = read();
        if (arr.length === 0) return; // keep the demo thumbs on first visit
        strip.innerHTML = arr.map(e => `
          <div class="hist-thumb ${e.tier}">
            ⛑️<span class="hist-status">${e.tier === 'ok' ? '✓' : '!'}</span>
          </div>
        `).join('');
      },
    };
  })();

  // ---------- REAL MODE: orchestrate camera + detector + rendering ----------

  function runRealMode(cfg) {
    const scene    = document.getElementById('camScene');
    const videoEl  = document.getElementById('camVideo');
    const canvasEl = document.getElementById('camCanvas');
    const shutter  = document.querySelector('.cam-controls .ctrl-btn.shutter');

    let detector = null;
    let camHandle = null;
    let loop = null;

    ScanHistory.render();

    // Preflight: verify key + model on load, not on first shutter click.
    // Fires in the background; falls back to mock loudly on failure.
    (async () => {
      ResultRenderer.setStatus('Đang kiểm tra kết nối AI...');
      try {
        detector = await HelmetDetector.load(cfg.key, cfg.model);
        ResultRenderer.setStatus(null);
        ResultRenderer.updateBadge('✓ Sẵn sàng — bấm 📸 để bật camera', 'idle');
      } catch (err) {
        console.warn('[AI Camera] Preflight failed, falling back to mock', err);
        // Hide the mode pill's green state to reflect reality.
        const pill = document.getElementById('camModePill');
        if (pill) { pill.dataset.mode = 'demo'; pill.querySelector('.mode-dot').nextSibling.textContent = ' Demo (config lỗi)'; }
        // Show the specific reason.
        ResultRenderer.setStatus(err.message || 'Cấu hình AI không hợp lệ — chuyển sang mock.');
        ResultRenderer.updateBadge('⚠ Config lỗi', 'warn');
        // Rebind rescan to mock behavior so shutter still does SOMETHING.
        runMockMode();
      }
    })();

    async function ensureDetector() {
      if (detector) return detector;
      ResultRenderer.updateBadge('⏳ Đang tải model...', 'analyzing');
      ResultRenderer.setStatus('Đang tải model AI (chỉ lần đầu)...');
      detector = await HelmetDetector.load(cfg.key, cfg.model);
      ResultRenderer.setStatus(null);
      return detector;
    }

    async function startCamera() {
      try {
        videoEl.style.display = '';   // undo hide from upload path
        await ensureDetector();
        camHandle = await CameraStream.start(videoEl);
        scene.classList.add('real');
        ResultRenderer.updateBadge('🔴 AI đang phân tích', 'analyzing');

        let noDetSince = null;
        loop = FrameLoop.start(async () => {
          const dets = await detector.detect(videoEl);
          ResultRenderer.drawBoxes(canvasEl, videoEl, dets);
          const { state, topHelmet, personSeen } = ResultRenderer.analyze(dets);

          if (state === 'ok') {
            ResultRenderer.updateBadge(`✓ Độ chính xác ${Math.round(topHelmet.confidence * 100)}%`, 'ok');
            ResultRenderer.setAlarm(false);
            ResultRenderer.setStatus(null);
            noDetSince = null;
          } else if (state === 'warn') {
            ResultRenderer.updateBadge(`⚠ Chưa rõ ${Math.round(topHelmet.confidence * 100)}%`, 'warn');
            ResultRenderer.setAlarm(false);
            ResultRenderer.setStatus(null);
            noDetSince = null;
          } else if (state === 'alarm') {
            ResultRenderer.updateBadge('⛔ CHƯA ĐỘI MŨ', 'alarm');
            ResultRenderer.setAlarm(true);
            noDetSince = null;
          } else {
            // idle — nothing in frame
            ResultRenderer.updateBadge('⏸ Chưa thấy ai', 'idle');
            ResultRenderer.setAlarm(false);
            if (!noDetSince) noDetSince = Date.now();
            if (Date.now() - noDetSince >= 5000) {
              ResultRenderer.setStatus('Hãy đứng vào giữa khung camera để AI kiểm tra mũ bảo hiểm.');
            }
          }
          ResultRenderer.updateHelmetCard(state, topHelmet);
          if (topHelmet) ScanHistory.push(topHelmet);
        });
      } catch (err) {
        stopCamera();
        if (err.message && err.message.includes('inferencejs')) {
          console.warn('detector unavailable, falling back to mock', err);
          showFallbackBanner();
          runMockMode();
          return;
        }
        handleCameraError(err);
      }
    }

    function stopCamera() {
      if (loop)      { loop.stop();    loop = null; }
      if (camHandle) { camHandle.stop(); camHandle = null; }
      scene.classList.remove('real');
      ResultRenderer.setAlarm(false);
      ResultRenderer.setStatus(null);
      const ctx = canvasEl.getContext('2d');
      ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);
    }

    function handleCameraError(err) {
      const map = {
        NotAllowedError:   'Bạn chưa cho phép camera. Bấm biểu tượng ổ khoá ở thanh địa chỉ để bật lại.',
        NotFoundError:     'Không tìm thấy camera trên thiết bị này.',
        NotSecureError:    'Trình duyệt yêu cầu HTTPS. Mở qua http://localhost hoặc bật HTTPS.',
        RoboflowInitError: err.message, // đã có message tiếng Việt sẵn từ HelmetDetector
      };
      const msg = map[err.name] || `Không mở được camera: ${err.message}`;
      ResultRenderer.setStatus(msg);
      ResultRenderer.updateBadge('⚠ ' + (err.name === 'RoboflowInitError' ? 'Model lỗi' : 'Camera lỗi'), 'warn');
    }

    if (shutter) {
      shutter.addEventListener('click', () => {
        if (camHandle) stopCamera(); else startCamera();
      });
    }

    window.rescan = function () {
      if (!camHandle) startCamera();
    };

    // ---------- Upload tab: single-shot detect ----------

    const fileInput = document.getElementById('camFileInput');
    const uploadBtn = document.getElementById('camUploadBtn');
    const uploadTab = document.querySelectorAll('.cam-tab')[1];
    const camView   = document.querySelector('.cam-view');

    async function detectFromFile(file) {
      if (!file || !file.type.startsWith('image/')) return;
      try {
        await ensureDetector();
      } catch (err) {
        handleCameraError(err); return;
      }
      if (camHandle) stopCamera();

      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = async () => {
        scene.classList.add('real');
        canvasEl.width  = img.naturalWidth;
        canvasEl.height = img.naturalHeight;
        const ctx = canvasEl.getContext('2d');
        ctx.drawImage(img, 0, 0);
        videoEl.style.display = 'none';
        try {
          const dets = await detector.detect(img);
          ResultRenderer.drawBoxes(canvasEl, img, dets);
          const { state, topHelmet } = ResultRenderer.analyze(dets);
          if (state === 'ok') {
            ResultRenderer.updateBadge(`✓ Độ chính xác ${Math.round(topHelmet.confidence * 100)}%`, 'ok');
            ResultRenderer.setAlarm(false);
          } else if (state === 'warn') {
            ResultRenderer.updateBadge(`⚠ Chưa rõ ${Math.round(topHelmet.confidence * 100)}%`, 'warn');
            ResultRenderer.setAlarm(false);
          } else if (state === 'alarm') {
            ResultRenderer.updateBadge('⛔ CHƯA ĐỘI MŨ', 'alarm');
            ResultRenderer.setAlarm(true, '⛔ Ảnh có người nhưng KHÔNG đội mũ bảo hiểm!');
          } else {
            ResultRenderer.updateBadge('⏸ Không thấy ai trong ảnh', 'idle');
            ResultRenderer.setAlarm(false);
          }
          ResultRenderer.updateHelmetCard(state, topHelmet);
          if (topHelmet) ScanHistory.push(topHelmet);
        } finally {
          URL.revokeObjectURL(url);
        }
      };
      img.src = url;
    }

    if (fileInput) {
      if (uploadBtn) uploadBtn.addEventListener('click', () => fileInput.click());
      if (uploadTab) uploadTab.addEventListener('click', () => {
        if (uploadTab.classList.contains('active')) fileInput.click();
      });
      fileInput.addEventListener('change', e => detectFromFile(e.target.files[0]));
    }

    if (camView) {
      ['dragover', 'dragenter'].forEach(ev => camView.addEventListener(ev, e => {
        e.preventDefault(); camView.classList.add('drop-hover');
      }));
      ['dragleave', 'drop'].forEach(ev => camView.addEventListener(ev, e => {
        e.preventDefault(); camView.classList.remove('drop-hover');
      }));
      camView.addEventListener('drop', e => {
        const f = e.dataTransfer?.files?.[0];
        if (f) detectFromFile(f);
      });
    }

    window.__aiCam = { startCamera, stopCamera };
  }

  // ---------- Shared: tab switcher ----------

  function wireTabs() {
    document.querySelectorAll('.cam-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.cam-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
      });
    });
  }
})();
