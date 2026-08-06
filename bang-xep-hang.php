<?php
require_once __DIR__ . '/game-progress.php';
requireLogin(); // cần đăng nhập (bất kỳ vai trò nào) để xem bảng xếp hạng
$user = currentUser();
$isStudent = $user['role'] === 'hocsinh';

$leaderboard = getLeaderboard(20);
$myRank = $isStudent ? getStudentRank($user['id']) : null;

$medal = ['🥇', '🥈', '🥉'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme")||"light");}catch(e){}})();</script>
<title>Bảng xếp hạng · Siêu Nhí An Toàn Giao Thông AI</title>
<link rel="stylesheet" href="assets/css/fonts.css?v=1">
<link rel="stylesheet" href="assets/css/style.css?v=9">
<link rel="stylesheet" href="assets/css/shared-pages.css?v=25">
<link rel="stylesheet" href="assets/css/kid-components.css?v=1">
</head>
<body>

<nav class="navbar static" id="navbar">
  <div class="nav-inner">
    <a href="index.php" class="logo"><span class="logo-badge">🤖</span>SIÊU NHÍ <span class="logo-text-en">AI</span></a>
    <a class="back-link" href="index.php">← Về trang chủ</a>
    <div class="nav-actions">
      <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
      <a class="btn btn-ghost" href="game-mini.php">🎮 Game Mini</a>
      <?php if ($isStudent): ?>
      <a class="btn btn-ghost" href="dashboard-hoc-sinh.php">🧒 Dashboard</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="page-head wrap">
  <span class="eyebrow-pill"><span class="dot"></span> Thi đua học tập</span>
  <h1>🏆 Bảng xếp hạng học sinh</h1>
  <p>Xếp hạng theo tổng XP tích luỹ từ tất cả bài học và trò chơi. Cùng cố gắng để leo hạng mỗi ngày nhé!</p>
</div>

<section style="padding:20px 0 90px;">
  <div class="wrap" style="max-width:760px;">

    <?php if ($isStudent): ?>
    <div class="card" style="display:flex; align-items:center; gap:16px; border-color:rgba(34,211,238,0.4);">
      <div class="level-ring">#<?= (int)$myRank ?></div>
      <div>
        <b style="display:block; font-size:15px; font-weight:700;">Hạng của bạn: #<?= (int)$myRank ?></b>
        <span style="font-size:12.5px; color:rgba(255,255,255,0.55);">Tiếp tục chơi các mini-game để tăng XP và leo hạng cao hơn!</span>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h3>📋 Top <?= count($leaderboard) ?> học sinh xuất sắc nhất</h3></div>

      <?php if (!$leaderboard): ?>
        <p style="text-align:center; color:rgba(255,255,255,0.4); padding:30px 10px;">Chưa có dữ liệu xếp hạng. Hãy là người đầu tiên chơi game và ghi điểm!</p>
      <?php endif; ?>

      <div style="display:flex; flex-direction:column; gap:10px;">
        <?php foreach ($leaderboard as $i => $row): ?>
        <?php $isMe = $isStudent && (int)$row['id'] === (int)$user['id']; ?>
        <div style="display:flex; align-items:center; gap:14px; padding:14px 16px; border-radius:14px;
                    background:<?= $isMe ? 'rgba(34,211,238,0.1)' : 'rgba(255,255,255,0.03)' ?>;
                    border:1px solid <?= $isMe ? 'rgba(34,211,238,0.4)' : 'var(--glass-border)' ?>;">
          <div style="width:36px; text-align:center; font-size:<?= $i < 3 ? '22px' : '15px' ?>; font-weight:800; color:<?= $i < 3 ? '#fff' : 'rgba(255,255,255,0.4)' ?>;">
            <?= $i < 3 ? $medal[$i] : ('#' . ($i + 1)) ?>
          </div>
          <div class="stu-avatar" style="width:40px; height:40px; font-size:18px;"><?= e($row['avatar_emoji']) ?></div>
          <div style="flex:1;">
            <b style="display:block; font-size:14px;"><?= e($row['name']) ?><?= $isMe ? ' <span style="color:var(--cyan); font-size:11px;">(bạn)</span>' : '' ?></b>
            <span style="font-size:11.5px; color:rgba(255,255,255,0.5);">Cấp <?= (int)$row['level'] ?> · 🔥 Streak <?= (int)$row['streak_days'] ?> ngày</span>
          </div>
          <div style="text-align:right;">
            <b style="display:block; font-family:'Baloo 2',sans-serif; font-size:17px; color:var(--yellow);">⭐ <?= (int)$row['xp'] ?></b>
            <span style="font-size:11px; color:rgba(255,255,255,0.4);">🪙 <?= (int)$row['coin'] ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</section>

<script src="assets/js/main.js?v=5"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
