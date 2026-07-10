/* dashboard-phu-huynh.js — Tương tác cho Dashboard phụ huynh */
document.addEventListener('DOMContentLoaded', () => {
  const weekly = [
    { d: 'T2', v: 35 }, { d: 'T3', v: 50 }, { d: 'T4', v: 28 }, { d: 'T5', v: 62 },
    { d: 'T6', v: 45 }, { d: 'T7', v: 70 }, { d: 'CN', v: 40 }
  ];
  const chart = document.getElementById('barChart');
  const max = Math.max(...weekly.map(w => w.v));
  weekly.forEach(w => {
    const col = document.createElement('div');
    col.className = 'bar-col';
    const pct = Math.round((w.v / max) * 100);
    col.innerHTML = `<div class="bar-fill" style="height:0%;"><span class="bar-val">${w.v}p</span></div><span class="bar-label">${w.d}</span>`;
    chart.appendChild(col);
    requestAnimationFrame(() => {
      setTimeout(() => { col.querySelector('.bar-fill').style.height = pct + '%'; }, 80);
    });
  });

  window.exportPdf = function (e) {
    const btn = e.currentTarget;
    const original = btn.innerHTML;
    btn.innerHTML = '⏳ Đang tạo báo cáo...';
    setTimeout(() => {
      btn.innerHTML = '✅ Đã lưu report-minh-an.pdf';
      setTimeout(() => btn.innerHTML = original, 1800);
    }, 1000);
  };
});
