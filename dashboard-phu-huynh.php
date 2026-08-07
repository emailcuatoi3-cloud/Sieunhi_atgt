<?php
require_once __DIR__ . '/auth.php';
requireRole(['phuhuynh', 'admin']);
$user = currentUser();
$db = new DB_UTILS();
$child = null; $childProgress = null; $childGames = 0;
try {
    $child = $db->getOne('SELECT u.id, u.name FROM parent_student ps JOIN users u ON u.id = ps.student_id WHERE ps.parent_id = ? LIMIT 1', [$user['id']]);
    if ($child) {
        $childProgress = $db->getOne('SELECT * FROM student_progress WHERE student_id = ?', [$child['id']]);
        $childGames = (int)$db->getValue('SELECT COUNT(*) FROM game_sessions WHERE student_id = ?', [$child['id']]);
    }
} catch (Throwable $ignored) { }
$childName = $child['name'] ?? 'Chưa liên kết học sinh';
$childXp = (int)($childProgress['xp'] ?? 0);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme")||"light");}catch(e){}})();</script>
<title>Dashboard phụ huynh · Siêu Nhí An Toàn Giao Thông AI</title>
<link rel="stylesheet" href="assets/css/fonts.css?v=1">
<link rel="stylesheet" href="assets/css/style.css?v=9">
<link rel="stylesheet" href="assets/css/shared-pages.css?v=25">
<link rel="stylesheet" href="assets/css/kid-components.css?v=1">
</head>
<body>

<div class="app">
  <aside class="sidebar">
    <div>
      <a href="index.php" class="side-brand"><img src="assets/images/sieu-nhi-logo.png" alt="SIÊU NHÍ AI" class="side-brand-img"></a>
      <a class="side-back" href="index.php">← Về trang chủ</a>
    </div>
    <a class="side-link active" href="dashboard-phu-huynh.php"><span class="ic">🏠</span> Tổng quan</a>
    <a class="side-link" href="dashboard-phu-huynh.php"><span class="ic">🧒</span> Con của tôi</a>
    <a class="side-link" href="dashboard-phu-huynh.php"><span class="ic">📊</span> Báo cáo chi tiết</a>
    <a class="side-link" href="dashboard-phu-huynh.php"><span class="ic">🗓️</span> Lịch sử học</a>
    <a class="side-link" href="dashboard-phu-huynh.php"><span class="ic">🔔</span> Thông báo</a>
    <div class="side-divider"></div>
    <a class="side-link" href="dang-ky.php"><span class="ic">⚙️</span> Cài đặt hồ sơ</a>
    <a class="side-link" href="logout.php"><span class="ic">🚪</span> Đăng xuất</a>

    <div class="sidebar-foot">
      <div class="av"><?= e($user['avatar']) ?></div>
      <div class="txt"><b><?= e($user['name']) ?> (Phụ huynh)</b><span>Con: Bé Minh An</span></div>
    </div>
  </aside>

  <main class="main">
    <div class="top-row">
      <div class="greet">
        <h1>Chào <?= e($user['name']) ?> 👋</h1>
        <p>Đây là tổng quan tiến bộ học an toàn giao thông của <?= e($childName) ?>.</p>
      </div>
      <div class="top-actions">
        <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
        <div class="select-pill">🧒 <?= e($childName) ?></div>
        <button class="btn btn-primary-sm" onclick="exportPdf(event)">📄 Xuất báo cáo PDF</button>
      </div>
    </div>

    <div class="stat-row">
      <div class="mini-stat-card"><div class="st-ic">⭐</div><b><?= number_format($childXp, 0, ',', '.') ?></b><span>XP của con</span></div>
      <div class="mini-stat-card"><div class="st-ic">🎮</div><b><?= $childGames ?></b><span>Lượt chơi đã lưu</span></div>
      <div class="mini-stat-card"><div class="st-ic">🔥</div><b><?= (int)($childProgress['streak_days'] ?? 0) ?></b><span>Ngày học liên tiếp</span></div>
      <div class="mini-stat-card"><div class="st-ic">🧭</div><b><?= $child ? 'Đã nối' : 'Chưa nối' ?></b><span>Trạng thái hồ sơ</span></div>
    </div>

    <div class="grid2">
      <div>
        <div class="card">
          <div class="card-head"><h3>⏱️ Thời gian học theo ngày</h3><a href="dashboard-phu-huynh.php">7 ngày</a></div>
          <div class="bar-chart" id="barChart"></div>
        </div>

        <div class="card">
          <div class="card-head"><h3>💪 Điểm mạnh &amp; Điểm cần cải thiện</h3></div>
          <div class="sw-grid">
            <div class="sw-box strong">
              <h5>✅ Điểm mạnh</h5>
              <ul class="sw-list">
                <li>🟢 Nhận biết đèn tín hiệu rất tốt</li>
                <li>⛑️ Luôn đội mũ bảo hiểm đúng cách</li>
                <li>🦓 Đi đúng vạch qua đường</li>
              </ul>
            </div>
            <div class="sw-box weak">
              <h5>⚠️ Cần cải thiện</h5>
              <ul class="sw-list">
                <li>🚑 Xử lý tình huống xe ưu tiên</li>
                <li>🚲 Quan sát khi rẽ bằng xe đạp</li>
                <li>🌧️ Xử lý tình huống trời mưa</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>🗓️ Lịch sử học tập gần đây</h3><a href="dashboard-phu-huynh.php">Cập nhật lại</a></div>
          <table class="data-table hist-table">
            <thead><tr><th>Ngày</th><th>Hoạt động</th><th>Thời gian</th><th>Kết quả</th></tr></thead>
            <tbody>
              <tr><td>Hôm nay</td><td><b>AI Mô phỏng giao thông</b></td><td>12 phút</td><td><span class="score-tag high">92%</span></td></tr>
              <tr><td>Hôm qua</td><td><b>Trò chơi kéo thả biển báo</b></td><td>8 phút</td><td><span class="score-tag high">100%</span></td></tr>
              <tr><td>2 ngày trước</td><td><b>AI Gia sư — Hỏi đáp</b></td><td>15 phút</td><td><span class="score-tag mid">78%</span></td></tr>
              <tr><td>3 ngày trước</td><td><b>Truyện tương tác "Bo đến trường"</b></td><td>10 phút</td><td><span class="score-tag high">85%</span></td></tr>
              <tr><td>4 ngày trước</td><td><b>AI Camera — Nhận diện mũ bảo hiểm</b></td><td>5 phút</td><td><span class="score-tag high">96%</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div>
        <div class="card">
          <div class="card-head"><h3>🛡️ Mức độ nguy hiểm</h3></div>
          <div class="risk-wrap">
            <div class="gauge">
              <svg width="120" height="120" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="12"/>
                <circle cx="60" cy="60" r="52" fill="none" stroke="url(#riskGrad)" stroke-width="12" stroke-linecap="round" stroke-dasharray="326.7" stroke-dashoffset="65" />
                <defs>
                  <linearGradient id="riskGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#34D399"/>
                    <stop offset="100%" stop-color="#22D3EE"/>
                  </linearGradient>
                </defs>
              </svg>
              <div class="gauge-label"><b>Thấp</b><span>Mức độ nguy hiểm</span></div>
            </div>
            <p class="risk-desc"><?= $child ? 'Dựa trên các lượt học đã lưu, AI sẽ tổng hợp kỹ năng mạnh và kỹ năng cần luyện thêm của con.' : 'Hãy liên kết hồ sơ học sinh để nhận phân tích và khuyến nghị cá nhân hóa.' ?></p>
          </div>
        </div>

        <div class="card">
          <div class="advice-box">
            <h4>🤖 AI tư vấn cho phụ huynh</h4>
            <p><?= $child ? 'AI sẽ dựa trên kết quả thật của con để gợi ý bài luyện tiếp theo. Các khuyến nghị chỉ xuất hiện khi có đủ dữ liệu học tập.' : 'Chưa có dữ liệu để đưa ra lời khuyên. Hãy liên kết tài khoản học sinh với hồ sơ phụ huynh.' ?></p>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>🏅 Chứng nhận đã đạt</h3></div>
          <div style="display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; align-items:center; gap:10px; font-size:12.5px; color:rgba(255,255,255,0.68);"><span style="font-size:18px;">🏅</span> Chứng nhận "Người đi bộ an toàn" — Cấp 1</div>
            <div style="display:flex; align-items:center; gap:10px; font-size:12.5px; color:rgba(255,255,255,0.68);"><span style="font-size:18px;">🥇</span> Huy chương "12 ngày liên tục học tập"</div>
            <div style="display:flex; align-items:center; gap:10px; font-size:12.5px; color:rgba(255,255,255,0.68);"><span style="font-size:18px;">⛑️</span> Huy hiệu "Chuyên gia đội mũ bảo hiểm"</div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="assets/js/main.js?v=5"></script>
<script src="assets/js/dashboard-phu-huynh.js?v=5"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
