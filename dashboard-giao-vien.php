<?php
require_once __DIR__ . '/auth.php';
requireRole(['giaovien', 'admin']);
$user = currentUser();
$db = new DB_UTILS();
$classCount = 0; $studentCount = 0; $lessonCount = 0; $pendingReviewCount = 0;
try {
  $classCount = (int)$db->getValue('SELECT COUNT(*) FROM classes WHERE teacher_id = ?', [$user['id']]);
  $studentCount = (int)$db->getValue('SELECT COUNT(DISTINCT cs.student_id) FROM class_students cs JOIN classes c ON c.id = cs.class_id WHERE c.teacher_id = ?', [$user['id']]);
  $lessonCount = (int)$db->getValue('SELECT COUNT(*) FROM lessons WHERE status = "published"');
  $pendingReviewCount = (int)$db->getValue('SELECT COUNT(*) FROM place_reviews WHERE status = "pending"');
} catch (Throwable $ignored) { }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf" content="<?= e(csrfToken()) ?>">
<script>(function(){try{document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme")||"light");}catch(e){}})();</script>
<title>Dashboard giáo viên · Siêu Nhí An Toàn Giao Thông AI</title>
<link rel="stylesheet" href="assets/css/fonts.css?v=1">
<link rel="stylesheet" href="assets/css/style.css?v=9">
<link rel="stylesheet" href="assets/css/shared-pages.css?v=25">
<link rel="stylesheet" href="assets/css/kid-components.css?v=1">
</head>
<body>

<div class="app">
  <aside class="sidebar">
    <div>
      <div class="side-brand"><div class="mark">🤖</div>SIÊU NHÍ AI</div>
      <a class="side-back" href="index.php">← Về trang chủ</a>
    </div>
    <a class="side-link active" href="dashboard-giao-vien.php"><span class="ic">🏠</span> Tổng quan</a>
    <a class="side-link" href="dashboard-giao-vien.php"><span class="ic">🏫</span> Lớp học</a>
    <a class="side-link" href="dashboard-giao-vien.php"><span class="ic">🧒</span> Học sinh</a>
    <a class="side-link" href="ai-gia-su.php"><span class="ic">📘</span> Bài học</a>
    <a class="side-link" href="game-mini.php"><span class="ic">🏆</span> Cuộc thi</a>
    <a class="side-link" href="dashboard-giao-vien.php"><span class="ic">📊</span> Báo cáo</a>
    <a class="side-link" href="#duyet-review"><span class="ic">📝</span> Duyệt review<?php if ($pendingReviewCount > 0): ?> <span class="kid-badge kid-badge--red"><?= $pendingReviewCount ?></span><?php endif; ?></a>
    <div class="side-divider"></div>
    <a class="side-link" href="dang-ky.php"><span class="ic">⚙️</span> Cài đặt hồ sơ</a>
    <a class="side-link" href="logout.php"><span class="ic">🚪</span> Đăng xuất</a>

    <div class="sidebar-foot">
      <div class="av"><?= e($user['avatar']) ?></div>
      <div class="txt"><b><?= e($user['name']) ?></b><span>Lớp 3A · 32 học sinh</span></div>
    </div>
  </aside>

  <main class="main">
    <div class="top-row">
      <div class="greet">
        <h1>Chào <?= e($user['name']) ?> 👋</h1>
        <p>Dữ liệu lớp học được đồng bộ từ cơ sở dữ liệu.</p>
      </div>
      <div class="top-actions">
        <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
        <div class="select-pill">🏫 <?= $classCount ?> lớp</div>
        <button class="btn btn-ghost" onclick="exportExcel(event)">📊 Xuất Excel</button>
        <button class="btn btn-primary-sm" onclick="toggleLessonForm()">＋ Tạo bài học</button>
      </div>
    </div>

    <div class="stat-row">
      <div class="mini-stat-card"><div class="st-ic">🧒</div><b><?= $studentCount ?></b><span>Học sinh đã nối lớp</span></div>
      <div class="mini-stat-card"><div class="st-ic">📘</div><b><?= $lessonCount ?></b><span>Bài học đã xuất bản</span></div>
      <div class="mini-stat-card"><div class="st-ic">📈</div><b>—</b><span>Chờ dữ liệu hoàn thành</span></div>
      <div class="mini-stat-card"><div class="st-ic">⚠️</div><b>—</b><span>Chưa đủ dữ liệu cảnh báo</span></div>
    </div>

    <div class="card" id="lessonForm" style="display:none;">
      <div class="card-head"><h3>📘 Tạo bài học mới</h3></div>
      <div style="display:flex; flex-direction:column; gap:12px;">
        <div class="form-row">
          <div class="form-field"><label>Tên bài học</label><input type="text" placeholder="VD: Nhận biết biển báo cấm"></div>
          <div class="form-field"><label>Chủ đề</label><select><option>Luật giao thông cơ bản</option><option>Biển báo &amp; tín hiệu</option><option>An toàn xe đạp</option><option>Tình huống khẩn cấp</option></select></div>
        </div>
        <div class="form-row">
          <div class="form-field"><label>Áp dụng cho lớp</label><select><option>Lớp 3A (32 học sinh)</option><option>Lớp 3B (29 học sinh)</option><option>Toàn khối 3</option></select></div>
          <div class="form-field"><label>Hạn hoàn thành</label><input type="date"></div>
        </div>
        <div style="display:flex; gap:10px; margin-top:6px;">
          <button class="btn btn-primary-sm" onclick="submitLesson()">✅ Giao bài học</button>
          <button class="btn btn-ghost" onclick="toggleLessonForm()">Huỷ</button>
        </div>
      </div>
    </div>

    <div class="grid2">
      <div>
        <div class="card">
          <div class="card-head"><h3>🧒 Danh sách học sinh</h3><a href="dashboard-giao-vien.php">Cập nhật danh sách</a></div>
          <table class="data-table">
            <thead><tr><th>Học sinh</th><th>Tiến độ</th><th>Điểm AI</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
              <tr><td><div class="stu-name"><div class="stu-avatar">🧒</div><b>Trần Minh An</b></div></td><td><div class="mini-bar"><i style="width:82%;"></i></div>82%</td><td>680 XP</td><td><span class="status-badge good">Tốt</span></td><td class="row-action">Xem →</td></tr>
              <tr><td><div class="stu-name"><div class="stu-avatar">👧</div><b>Lê Thị Na</b></div></td><td><div class="mini-bar"><i style="width:91%;"></i></div>91%</td><td>820 XP</td><td><span class="status-badge good">Xuất sắc</span></td><td class="row-action">Xem →</td></tr>
              <tr><td><div class="stu-name"><div class="stu-avatar">🧑</div><b>Phạm Đức Huy</b></div></td><td><div class="mini-bar"><i style="width:38%;"></i></div>38%</td><td>210 XP</td><td><span class="status-badge watch">Cần chú ý</span></td><td class="row-action">Xem →</td></tr>
              <tr><td><div class="stu-name"><div class="stu-avatar">👦</div><b>Nguyễn Gia Bảo</b></div></td><td><div class="mini-bar"><i style="width:65%;"></i></div>65%</td><td>540 XP</td><td><span class="status-badge good">Tốt</span></td><td class="row-action">Xem →</td></tr>
              <tr><td><div class="stu-name"><div class="stu-avatar">👧</div><b>Vũ Hồng Anh</b></div></td><td><div class="mini-bar"><i style="width:29%;"></i></div>29%</td><td>150 XP</td><td><span class="status-badge watch">Cần chú ý</span></td><td class="row-action">Xem →</td></tr>
            </tbody>
          </table>
        </div>

        <div class="card">
          <div class="card-head"><h3>🔥 Heatmap hoạt động lớp học</h3><a href="dashboard-giao-vien.php">30 ngày qua</a></div>
          <div class="heatmap" id="heatmap"></div>
          <div class="heat-legend">
            <span>Ít</span>
            <span class="lg-cell" style="background:rgba(255,255,255,0.06);"></span>
            <span class="lg-cell" style="background:rgba(59,130,246,0.35);"></span>
            <span class="lg-cell" style="background:rgba(59,130,246,0.65);"></span>
            <span class="lg-cell" style="background:#6E9CFF;"></span>
            <span>Nhiều</span>
          </div>
        </div>
      </div>

      <div>
        <div class="card">
          <div class="card-head"><h3>📊 Tiến độ theo chủ đề</h3></div>
          <div style="display:flex; flex-direction:column; gap:12px;">
            <div><div style="display:flex; justify-content:space-between; font-size:12.5px; color:rgba(255,255,255,0.68); margin-bottom:6px;"><span>Luật giao thông cơ bản</span><span>88%</span></div><div class="mini-bar full"><i style="width:88%;"></i></div></div>
            <div><div style="display:flex; justify-content:space-between; font-size:12.5px; color:rgba(255,255,255,0.68); margin-bottom:6px;"><span>Biển báo &amp; tín hiệu</span><span>76%</span></div><div class="mini-bar full"><i style="width:76%;"></i></div></div>
            <div><div style="display:flex; justify-content:space-between; font-size:12.5px; color:rgba(255,255,255,0.68); margin-bottom:6px;"><span>An toàn xe đạp</span><span>61%</span></div><div class="mini-bar full"><i style="width:61%;"></i></div></div>
            <div><div style="display:flex; justify-content:space-between; font-size:12.5px; color:rgba(255,255,255,0.68); margin-bottom:6px;"><span>Tình huống khẩn cấp</span><span>44%</span></div><div class="mini-bar full"><i style="width:44%;"></i></div></div>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>🔔 Thông báo</h3></div>
          <div class="notif-item"><div class="notif-ic">⚠️</div><div><div class="notif-text">3 học sinh chưa hoàn thành bài học tuần này.</div><div class="notif-time">30 phút trước</div></div></div>
          <div class="notif-item"><div class="notif-ic">🏆</div><div><div class="notif-text">Lớp 3A xếp hạng #2 toàn khối tuần này.</div><div class="notif-time">2 giờ trước</div></div></div>
          <div class="notif-item"><div class="notif-ic">📩</div><div><div class="notif-text">2 phụ huynh vừa gửi câu hỏi cho giáo viên.</div><div class="notif-time">Hôm qua</div></div></div>
        </div>

        <div class="card">
          <div class="card-head"><h3>🏫 Các lớp của tôi</h3></div>
          <div style="display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px;"><span>Lớp 3A</span><span style="color:rgba(255,255,255,0.42); font-size:12px;">32 HS · 84%</span></div>
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px;"><span>Lớp 3B</span><span style="color:rgba(255,255,255,0.42); font-size:12px;">29 HS · 77%</span></div>
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px;"><span>Lớp 4A</span><span style="color:rgba(255,255,255,0.42); font-size:12px;">30 HS · 69%</span></div>
          </div>
        </div>
      </div>
    </div>

    <section id="duyet-review"><?php require __DIR__ . '/partials/review-moderation-section.php'; ?></section>
  </main>
</div>

<script src="assets/js/main.js?v=5"></script>
<script src="assets/js/dashboard-giao-vien.js?v=5"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
