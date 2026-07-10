<?php
require_once __DIR__ . '/auth.php';
requireRole(['hocsinh', 'admin']);
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme")||"dark");}catch(e){}})();</script>
<title>Dashboard học sinh · Siêu Nhí An Toàn Giao Thông AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=5">
<link rel="stylesheet" href="assets/css/shared-pages.css?v=5">
</head>
<body>

<div class="app">
  <aside class="sidebar">
    <div>
      <div class="side-brand"><div class="mark">🤖</div>SIÊU NHÍ AI</div>
      <a class="side-back" href="sieu-nhi-atgt-ai.php">← Về trang chủ</a>
    </div>
    <a class="side-link active" href="#"><span class="ic">🏠</span> Trang chủ</a>
    <a class="side-link" href="#"><span class="ic">🎓</span> Khoá học AI</a>
    <a class="side-link" href="game-mini.php"><span class="ic">🏆</span> Thử thách</a>
    <a class="side-link" href="#"><span class="ic">🥇</span> Thành tích</a>
    <a class="side-link" href="#"><span class="ic">📜</span> Chứng chỉ</a>
    <a class="side-link" href="#"><span class="ic">👥</span> Bạn bè</a>
    <a class="side-link" href="#"><span class="ic">📊</span> Bảng xếp hạng</a>
    <div class="side-divider"></div>
    <a class="side-link" href="ai-gia-su.php"><span class="ic">💬</span> AI Gia sư</a>
    <a class="side-link" href="ai-camera.php"><span class="ic">📷</span> AI Camera</a>
    <a class="side-link" href="ai-mo-phong.php"><span class="ic">🚦</span> Mô phỏng</a>
    <div class="side-divider"></div>
    <a class="side-link" href="#"><span class="ic">⚙️</span> Cài đặt</a>
    <a class="side-link" href="logout.php"><span class="ic">🚪</span> Đăng xuất</a>

    <div class="sidebar-foot">
      <div class="av"><?= e($user['avatar']) ?></div>
      <div class="txt"><b><?= e($user['name']) ?></b><span>Lớp 3A · Trường TH Việt Nam</span></div>
    </div>
  </aside>

  <main class="main">
    <div class="top-row">
      <div class="greet">
        <h1>Chào buổi sáng, <?= e($user['name']) ?>! 👋</h1>
        <p>Hôm nay là thứ Năm, con đã sẵn sàng chinh phục thử thách mới chưa?</p>
      </div>
      <div class="top-actions">
        <div class="icon-btn">🔔</div>
        <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
        <div class="icon-btn">⚙️</div>
      </div>
    </div>

    <div class="stat-row">
      <div class="mini-stat-card"><div class="st-ic">⭐</div><b>680</b><span>Điểm XP</span></div>
      <div class="mini-stat-card"><div class="st-ic">🪙</div><b>1.240</b><span>Coin</span></div>
      <div class="mini-stat-card"><div class="st-ic">🔥</div><b>12 ngày</b><span>Streak học tập</span></div>
      <div class="mini-stat-card"><div class="st-ic">🏅</div><b>6</b><span>Huy hiệu đạt được</span></div>
    </div>

    <div class="grid2">
      <div>
        <div class="card">
          <div class="card-head"><h3>📈 Tiến độ học tập</h3><a href="#">Xem chi tiết</a></div>
          <div class="prog-item"><div class="prog-top"><span>Luật giao thông cơ bản</span><span>82%</span></div><div class="mini-bar full"><i style="width:82%;"></i></div></div>
          <div class="prog-item"><div class="prog-top"><span>Biển báo &amp; tín hiệu</span><span>64%</span></div><div class="mini-bar full"><i style="width:64%;"></i></div></div>
          <div class="prog-item"><div class="prog-top"><span>An toàn xe đạp</span><span>45%</span></div><div class="mini-bar full"><i style="width:45%;"></i></div></div>
          <div class="prog-item" style="margin-bottom:0;"><div class="prog-top"><span>Xử lý tình huống khẩn cấp</span><span>28%</span></div><div class="mini-bar full"><i style="width:28%;"></i></div></div>
        </div>

        <div class="card">
          <div class="card-head"><h3>✅ Nhiệm vụ hôm nay</h3><span style="font-size:12px; color:rgba(255,255,255,0.42);" id="missionCount">1/4 hoàn thành</span></div>
          <div id="missionList"></div>
        </div>

        <div class="card">
          <div class="card-head"><h3>🎯 Khoá học AI gợi ý</h3><a href="#">Xem tất cả</a></div>
          <div class="course-row">
            <div class="course-card"><div class="course-ic">🚦</div><div class="course-info"><b>Đèn tín hiệu nâng cao</b><span>8 bài học · Phù hợp với con</span></div><span class="course-tag">Đề xuất</span></div>
            <div class="course-card"><div class="course-ic">🚲</div><div class="course-info"><b>Đi xe đạp an toàn</b><span>6 bài học · Đang học dở 45%</span></div><span class="course-tag">Tiếp tục</span></div>
            <div class="course-card"><div class="course-ic">🚑</div><div class="course-info"><b>Xử lý tình huống khẩn cấp</b><span>5 bài học · Mới</span></div><span class="course-tag">Mới</span></div>
          </div>
        </div>
      </div>

      <div>
        <div class="card level-card">
          <div class="level-ring">Lv7</div>
          <div>
            <b style="display:block; font-size:14.5px; font-weight:700; margin-bottom:4px;">Chiến binh An toàn</b>
            <span style="font-size:12px; color:rgba(255,255,255,0.5);">680 / 1000 XP đến cấp 8</span>
            <div class="mini-bar full" style="margin-top:8px; width:160px;"><i style="width:68%;"></i></div>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>🗓️ Lịch học hôm nay</h3></div>
          <div class="sched-item"><div class="sched-time">08:00</div><div class="sched-info"><b>Bài học: Biển báo cấm</b><span>AI Gia sư · 15 phút</span></div></div>
          <div class="sched-item"><div class="sched-time">14:30</div><div class="sched-info"><b>Mô phỏng ngã tư</b><span>Thử thách hằng ngày · 10 phút</span></div></div>
          <div class="sched-item"><div class="sched-time">19:00</div><div class="sched-info"><b>Ôn tập cùng phụ huynh</b><span>Xem báo cáo tuần</span></div></div>
        </div>

        <div class="card">
          <div class="card-head"><h3>🔔 Thông báo</h3><a href="#">Đánh dấu đã đọc</a></div>
          <div class="notif-item"><div class="notif-ic">🏅</div><div><div class="notif-text">Con vừa đạt huy hiệu <b>"Đội mũ chuẩn"</b>!</div><div class="notif-time">10 phút trước</div></div></div>
          <div class="notif-item"><div class="notif-ic">👨‍👩‍👧</div><div><div class="notif-text">Phụ huynh đã xem báo cáo tuần của con.</div><div class="notif-time">1 giờ trước</div></div></div>
          <div class="notif-item"><div class="notif-ic">🎮</div><div><div class="notif-text">Bạn Na vừa vượt qua thử thách "Kéo thả biển báo".</div><div class="notif-time">Hôm qua</div></div></div>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="assets/js/main.js?v=5"></script>
<script src="assets/js/dashboard-hoc-sinh.js?v=5"></script>
</body>
</html>
