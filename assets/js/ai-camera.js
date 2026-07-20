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

  // ---------- REAL MODE (stub — filled in by Task 4+) ----------

  function runRealMode(_cfg) {
    // Task 4-9 will replace this. For now, fail loudly if it's ever reached
    // in a state Task 3 didn't expect.
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
