/* dashboard-giao-vien.js — Tương tác cho Dashboard giáo viên */
document.addEventListener('DOMContentLoaded', () => {
  const heat = document.getElementById('heatmap');
  const days = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
  days.forEach(d => {
    const h = document.createElement('div');
    h.className = 'heat-head';
    h.textContent = d;
    heat.appendChild(h);
  });
  const levels = ['rgba(255,255,255,0.06)', 'rgba(59,130,246,0.35)', 'rgba(59,130,246,0.6)', 'rgba(139,92,246,0.7)', 'rgba(34,211,238,0.85)'];
  for (let i = 0; i < 35; i++) {
    const cell = document.createElement('div');
    cell.className = 'heat-cell';
    cell.style.background = levels[Math.floor(Math.random() * levels.length)];
    heat.appendChild(cell);
  }

  window.toggleLessonForm = function () {
    const form = document.getElementById('lessonForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') form.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };
  window.submitLesson = function () {
    alert('✅ Bài học đã được giao cho lớp thành công!');
    window.toggleLessonForm();
  };
  window.exportExcel = function (e) {
    const btn = e.currentTarget;
    const original = btn.innerHTML;
    btn.innerHTML = '⏳ Đang xuất...';
    setTimeout(() => {
      btn.innerHTML = '✅ Đã lưu lop-3a-baocao.xlsx';
      setTimeout(() => btn.innerHTML = original, 1800);
    }, 1000);
  };
});
