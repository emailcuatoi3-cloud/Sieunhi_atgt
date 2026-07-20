<?php
require_once __DIR__ . '/game-progress.php';
$user = currentUser();
$isStudent = $user && $user['role'] === 'hocsinh';
$gameId = 'safeRoute';

$currentLevel = 1; $badgeCount = 0; $progress = ['xp' => 0, 'coin' => 0];
if ($isStudent) {
    $progress = getStudentProgress($user['id']);
    $currentLevel = (int)$progress['level'];
    $badgeCount = countStudentBadges($user['id']);
    if (!isGameUnlocked($gameId, true, $currentLevel)) {
        header('Location: game-mini.php?locked=' . $gameId);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme")||"dark");}catch(e){}})();</script>
<title>Đường đến trường an toàn · Siêu Nhí An Toàn Giao Thông AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=5">
<link rel="stylesheet" href="assets/css/shared-pages.css?v=7">
</head>
<body>

<nav class="navbar static" id="navbar">
  <div class="nav-inner">
    <a href="sieu-nhi-atgt-ai.php" class="logo"><span class="logo-badge">🤖</span>SIÊU NHÍ <span class="logo-text-en">AI</span></a>
    <a class="back-link" href="game-mini.php">← Về Game Mini</a>
    <div class="nav-actions">
      <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
      <div class="status-item" style="font-size:12.5px;"><span class="s-ic">⭐</span> <span id="xpVal"><?= (int)$progress['xp'] ?></span> XP</div>
      <div class="status-item" style="font-size:12.5px;"><span class="s-ic">🪙</span> <span id="coinVal"><?= (int)$progress['coin'] ?></span></div>
      <div class="status-item" style="font-size:12.5px;"><span class="s-ic">🏅</span> <span id="badgeVal"><?= (int)$badgeCount ?></span></div>
      <div class="status-item" style="font-size:12.5px;">🎖️ Cấp <span id="levelVal"><?= $currentLevel ?></span></div>
    </div>
  </div>
</nav>

<div class="page-head wrap">
  <span class="eyebrow-pill"><span class="dot"></span> 🗺️ Game 4 · Đường đến trường an toàn</span>
</div>

<section style="padding:0 0 90px;">
  <div class="wrap ge-shell">
    <div id="stage"></div>
  </div>
</section>

<script>
  window.IS_STUDENT = <?= $isStudent ? 'true' : 'false' ?>;
</script>
<script src="assets/js/main.js?v=5"></script>
<script src="assets/js/sound-fx.js?v=1"></script>
<script src="assets/js/game-engine.js?v=1"></script>
<script src="assets/js/game-safe-route.js?v=1"></script>
</body>
</html>
