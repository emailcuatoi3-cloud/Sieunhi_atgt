/* dashboard-admin.js — Tương tác cho Dashboard Admin */
document.addEventListener('DOMContentLoaded', () => {
  const logTemplates = [
    { lvl: 'info', text: 'AI Gia sư xử lý câu hỏi mới — student_id: 88213' },
    { lvl: 'ok', text: 'Sao lưu dữ liệu hệ thống hoàn tất thành công' },
    { lvl: 'info', text: 'AI Camera nhận diện ảnh mới — độ chính xác 97%' },
    { lvl: 'warn', text: 'Độ trễ AI Truyện tương tác tăng nhẹ (bảo trì)' },
    { lvl: 'ok', text: 'Người dùng mới đăng ký: Trường TH Lê Lợi' },
    { lvl: 'info', text: 'Phiên Mô phỏng giao thông mới được khởi tạo' },
    { lvl: 'ok', text: 'Đồng bộ báo cáo phụ huynh hoàn tất' }
  ];

  function nowTime() { return new Date().toTimeString().slice(0, 8); }

  function pushLog() {
    const consoleEl = document.getElementById('logConsole');
    const t = logTemplates[Math.floor(Math.random() * logTemplates.length)];
    const line = document.createElement('div');
    line.className = 'log-line ' + t.lvl;
    line.innerHTML = `<span class="lt">[${nowTime()}]</span><span class="lv">${t.lvl.toUpperCase()}</span><span>${t.text}</span>`;
    consoleEl.prepend(line);
    while (consoleEl.children.length > 12) consoleEl.removeChild(consoleEl.lastChild);
  }

  for (let i = 0; i < 6; i++) pushLog();
  setInterval(pushLog, 5000);
  window.refreshLogs = pushLog;

  window.moderate = function (el) {
    const item = el.closest('.mod-item');
    item.style.transition = 'opacity .3s, transform .3s';
    item.style.opacity = '0';
    item.style.transform = 'translateX(20px)';
    setTimeout(() => item.remove(), 300);
  };
});
