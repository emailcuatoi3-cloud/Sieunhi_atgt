/* ai-camera.js — Tương tác cho trang AI Camera */
document.addEventListener('DOMContentLoaded', () => {
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

  document.querySelectorAll('.cam-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.cam-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
    });
  });
});
