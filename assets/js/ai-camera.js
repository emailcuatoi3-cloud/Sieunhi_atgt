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
      runRealMode(cfg);
    } else {
      runMockMode();
    }
    wireTabs();
  });

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

  // ---------- HelmetDetector: wrapper for Roboflow inferencejs ----------

  const HelmetDetector = {
    /**
     * Load a model. modelSlugWithVersion e.g. "helmet-detection-abcxyz/3".
     * Rejects if SDK missing or startWorker throws — callers must handle.
     */
    async load(publishableKey, modelSlugWithVersion) {
      if (!window.inferencejs) {
        throw new Error('inferencejs SDK not loaded');
      }
      const slash = modelSlugWithVersion.lastIndexOf('/');
      if (slash < 1) {
        throw new Error('ROBOFLOW_MODEL must be "<slug>/<version>"');
      }
      const slug = modelSlugWithVersion.slice(0, slash);
      const version = modelSlugWithVersion.slice(slash + 1);

      const { InferenceEngine, CVImage } = window.inferencejs;
      const engine = new InferenceEngine();
      const workerId = await engine.startWorker(slug, version, publishableKey);

      return {
        async detect(mediaElement) {
          const image = new CVImage(mediaElement);
          const raw = await engine.infer(workerId, image);
          // Normalize Roboflow's shape to our Detection interface.
          return (raw || []).map(p => ({
            className: p.class || 'helmet',
            confidence: typeof p.confidence === 'number' ? p.confidence : (p.score || 0),
            bbox: {
              x: p.bbox?.x ?? p.x ?? 0,
              y: p.bbox?.y ?? p.y ?? 0,
              width:  p.bbox?.width  ?? p.width  ?? 0,
              height: p.bbox?.height ?? p.height ?? 0,
            },
          }));
        },
        async destroy() {
          try { await engine.stopWorker(workerId); } catch (_) { /* ignore */ }
        },
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

    function tierFor(conf) {
      if (conf >= CONF_HIGH) return 'ok';
      if (conf >= CONF_MID)  return 'warn';
      return 'idle';
    }

    function colorFor(tier) {
      switch (tier) {
        case 'ok':   return getComputedStyle(document.documentElement).getPropertyValue('--green').trim() || '#2ecc71';
        case 'warn': return getComputedStyle(document.documentElement).getPropertyValue('--yellow').trim() || '#f5b642';
        default:     return 'rgba(255,255,255,0.35)';
      }
    }

    return {
      updateBadge(text, tier) {
        const badge = document.getElementById('accBadge');
        if (!badge) return;
        badge.textContent = text;
        const map = {
          ok:        { color: 'var(--green)',  bg: 'rgba(46,204,113,0.15)' },
          warn:      { color: 'var(--yellow)', bg: 'rgba(245,182,66,0.15)' },
          idle:      { color: 'rgba(255,255,255,0.55)', bg: 'rgba(255,255,255,0.06)' },
          analyzing: { color: 'var(--cyan)',   bg: 'rgba(79,195,247,0.15)' },
        };
        const s = map[tier] || map.idle;
        badge.style.color = s.color;
        badge.style.background = s.bg;
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

      updateHelmetCard(top) {
        // First .detect-item in .result-panel is the helmet card.
        const card = document.querySelector('.result-panel .detect-item');
        if (!card) return;
        const title = card.querySelector('.d-info b');
        const sub   = card.querySelector('.d-info span');
        const advice = document.querySelector('.advice-box p');

        if (!top) {
          card.classList.remove('ok', 'warn');
          card.classList.add('warn');
          if (title) title.childNodes[0].nodeValue = 'Chưa thấy mũ bảo hiểm ';
          if (sub)   sub.textContent = 'Hãy đưa mũ vào giữa khung camera';
          if (advice) advice.textContent = 'Chưa thấy mũ bảo hiểm trong khung. Con hãy đội mũ hoặc đưa mũ vào giữa khung camera nhé.';
          return;
        }
        const tier = tierFor(top.confidence);
        card.classList.remove('ok', 'warn');
        if (tier === 'ok') {
          card.classList.add('ok');
          if (title) title.childNodes[0].nodeValue = 'Mũ bảo hiểm — Đạt chuẩn ';
          if (sub)   sub.textContent = `Model tự tin ${Math.round(top.confidence * 100)}%`;
          if (advice) advice.textContent = 'Con đội mũ bảo hiểm rất chuẩn rồi! Nhớ luôn cài quai và quan sát hai bên trước khi qua đường nhé.';
        } else {
          card.classList.add('warn');
          if (title) title.childNodes[0].nodeValue = 'Mũ bảo hiểm — Chưa rõ ';
          if (sub)   sub.textContent = `Chỉ ${Math.round(top.confidence * 100)}% chắc chắn — hãy đưa mũ vào chính giữa khung`;
          if (advice) advice.textContent = 'Model chưa nhìn rõ mũ bảo hiểm. Con thử đưa mũ vào giữa khung, cách camera 1 sải tay nhé.';
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

  // ---------- REAL MODE: orchestrate camera + detector + rendering ----------

  function runRealMode(cfg) {
    const scene    = document.getElementById('camScene');
    const videoEl  = document.getElementById('camVideo');
    const canvasEl = document.getElementById('camCanvas');
    const shutter  = document.querySelector('.cam-controls .ctrl-btn.shutter');

    let detector = null;
    let camHandle = null;
    let loop = null;

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
        await ensureDetector();
        camHandle = await CameraStream.start(videoEl);
        scene.classList.add('real');
        ResultRenderer.updateBadge('🔴 AI đang phân tích', 'analyzing');
        loop = FrameLoop.start(async () => {
          const dets = await detector.detect(videoEl);
          ResultRenderer.drawBoxes(canvasEl, videoEl, dets);
          const top = dets.length
            ? dets.reduce((a, b) => (a.confidence >= b.confidence ? a : b))
            : null;
          if (top && top.confidence >= 0.60) {
            ResultRenderer.updateBadge(`✓ Độ chính xác ${Math.round(top.confidence * 100)}%`, 'ok');
          } else if (top) {
            ResultRenderer.updateBadge(`⚠ Chưa rõ ${Math.round(top.confidence * 100)}%`, 'warn');
          } else {
            ResultRenderer.updateBadge('⏸ Chưa thấy mũ', 'idle');
          }
          ResultRenderer.updateHelmetCard(top);
        });
      } catch (err) {
        stopCamera();
        handleCameraError(err);
      }
    }

    function stopCamera() {
      if (loop)      { loop.stop();    loop = null; }
      if (camHandle) { camHandle.stop(); camHandle = null; }
      scene.classList.remove('real');
      const ctx = canvasEl.getContext('2d');
      ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);
    }

    function handleCameraError(err) {
      const map = {
        NotAllowedError: 'Bạn chưa cho phép camera. Bấm biểu tượng ổ khoá ở thanh địa chỉ để bật lại.',
        NotFoundError:   'Không tìm thấy camera trên thiết bị này.',
        NotSecureError:  'Trình duyệt yêu cầu HTTPS. Mở qua http://localhost hoặc bật HTTPS.',
      };
      const msg = map[err.name] || `Không mở được camera: ${err.message}`;
      ResultRenderer.setStatus(msg);
      ResultRenderer.updateBadge('⚠ Camera lỗi', 'warn');
    }

    if (shutter) {
      shutter.addEventListener('click', () => {
        if (camHandle) stopCamera(); else startCamera();
      });
    }

    window.rescan = function () {
      if (!camHandle) startCamera();
    };

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
