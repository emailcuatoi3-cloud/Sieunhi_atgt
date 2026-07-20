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

  // ---------- REAL MODE (stub — filled in by Task 6+) ----------

  function runRealMode(_cfg) {
    throw new Error('runRealMode not implemented yet');
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
