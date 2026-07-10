<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme")||"dark");}catch(e){}})();</script>
<title>Game Mini · Siêu Nhí An Toàn Giao Thông AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=5">
<link rel="stylesheet" href="assets/css/shared-pages.css?v=5">
</head>
<body>

<nav class="navbar static" id="navbar">
  <div class="nav-inner">
    <a href="sieu-nhi-atgt-ai.php" class="logo"><span class="logo-badge">🤖</span>SIÊU NHÍ <span class="logo-text-en">AI</span></a>
    <a class="back-link" href="sieu-nhi-atgt-ai.php">← Về trang chủ</a>
    <div class="nav-actions">
      <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
      <a class="btn btn-ghost" href="ai-truyen-tranh.php">📖 Truyện tranh</a>
      <a class="btn btn-ghost" href="dashboard-hoc-sinh.php">🧒 Dashboard</a>
    </div>
  </div>
</nav>

<div class="page-head wrap">
  <span class="eyebrow-pill"><span class="dot"></span> Thử thách &amp; Trò chơi</span>
  <h1>Học mà chơi, chơi mà học</h1>
  <p>Mỗi trò chơi đều tích XP, Coin và mở khoá huy hiệu mới — biến kiến thức giao thông thành một hành trình phiêu lưu thú vị.</p>

  <div class="status-bar">
    <div class="status-item"><span class="s-ic">⭐</span> <span id="xpVal">680</span> XP</div>
    <div class="status-div"></div>
    <div class="status-item"><span class="s-ic">🪙</span> <span id="coinVal">1240</span> Coin</div>
    <div class="status-div"></div>
    <div class="status-item"><span class="s-ic">🏅</span> <span id="badgeVal">6</span> Huy hiệu</div>
    <div class="status-div"></div>
    <div class="status-item"><span class="s-ic">🔥</span> Streak 12 ngày</div>
  </div>
</div>

<section class="games">
  <div class="wrap">
    <div class="game-grid">

      <div class="game-card featured" onclick="openGame(event)">
        <div class="game-visual">🚸<span class="game-badge">🎮 Chơi ngay</span></div>
        <div class="featured-info">
          <div class="tag">⭐ Trò chơi nổi bật</div>
          <h3>Kéo thả biển báo giao thông</h3>
          <p>Kéo từng biển báo vào đúng ô ý nghĩa của nó. Hoàn thành càng nhanh, điểm thưởng càng cao!</p>
          <div class="reward-row" style="margin-bottom:16px;">
            <div class="reward-chip">⭐ +30 XP</div>
            <div class="reward-chip">🪙 +50 Coin</div>
            <div class="reward-chip">🏅 Huy hiệu "Chuyên gia biển báo"</div>
          </div>
          <button class="play-btn" onclick="openGame(event)">▶ Bắt đầu chơi</button>
        </div>
      </div>

      <div class="game-card">
        <div class="game-visual">🧩<span class="game-badge">Ghép hình</span></div>
        <div class="game-info">
          <h4>Ghép hình an toàn</h4>
          <p>Ghép các mảnh ghép để hoàn thành bức tranh về hành vi giao thông đúng.</p>
          <div class="reward-row"><div class="reward-chip">⭐ +20 XP</div><div class="reward-chip">🪙 +30 Coin</div></div>
        </div>
      </div>

      <div class="game-card">
        <div class="game-visual">🔍<span class="game-badge">Tìm lỗi</span></div>
        <div class="game-info">
          <h4>Tìm lỗi sai trong tranh</h4>
          <p>Quan sát bức tranh đường phố và tìm ra những hành vi không an toàn.</p>
          <div class="reward-row"><div class="reward-chip">⭐ +25 XP</div><div class="reward-chip">🪙 +35 Coin</div></div>
          <div class="lock-tag">🔒 Mở khoá ở cấp độ 4</div>
        </div>
      </div>

      <div class="game-card">
        <div class="game-visual">🌀<span class="game-badge">Mê cung</span></div>
        <div class="game-info">
          <h4>Vượt mê cung an toàn</h4>
          <p>Dẫn đường cho nhân vật đi qua mê cung, tránh các điểm nguy hiểm trên đường.</p>
          <div class="reward-row"><div class="reward-chip">⭐ +35 XP</div><div class="reward-chip">🪙 +45 Coin</div></div>
          <div class="lock-tag">🔒 Mở khoá ở cấp độ 5</div>
        </div>
      </div>

      <div class="game-card">
        <div class="game-visual">🏁<span class="game-badge">Đua xe</span></div>
        <div class="game-info">
          <h4>Đua xe đạp an toàn</h4>
          <p>Điều khiển xe đạp tuân thủ luật giao thông để về đích nhanh và an toàn nhất.</p>
          <div class="reward-row"><div class="reward-chip">⭐ +40 XP</div><div class="reward-chip">🪙 +60 Coin</div></div>
          <div class="lock-tag">🔒 Mở khoá ở cấp độ 6</div>
        </div>
      </div>

      <div class="game-card">
        <div class="game-visual">🚦<span class="game-badge">Ghép đèn</span></div>
        <div class="game-info">
          <h4>Ghép đèn giao thông</h4>
          <p>Sắp xếp đúng thứ tự đèn xanh — vàng — đỏ để điều khiển ngã tư an toàn.</p>
          <div class="reward-row"><div class="reward-chip">⭐ +20 XP</div><div class="reward-chip">🪙 +25 Coin</div></div>
          <div class="lock-tag">🔒 Mở khoá ở cấp độ 3</div>
        </div>
      </div>

    </div>

    <div class="game-panel" id="gamePanel">
      <div class="gp-top">
        <h3>🚸 Kéo thả biển báo giao thông</h3>
        <div class="gp-close" onclick="closeGame()">✕ Đóng</div>
      </div>
      <p style="font-size:13px; color:rgba(255,255,255,0.5); margin-bottom:20px;">Kéo mỗi biển báo bên trái vào đúng ô ý nghĩa của nó bên phải.</p>

      <div class="drag-area">
        <div class="sign-pool" id="signPool"></div>
        <div class="drop-list" id="dropList"></div>
      </div>

      <div class="gp-footer">
        <div class="gp-score">✅ <span id="correctCount">0</span> / <span id="totalCount">5</span> đúng</div>
        <div class="gp-actions">
          <button class="btn btn-ghost" onclick="resetGame()">🔁 Chơi lại</button>
          <button class="btn btn-primary-sm" onclick="closeGame()">Hoàn tất</button>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="toast" id="toast"></div>

<script src="assets/js/main.js?v=5"></script>
<script src="assets/js/game-mini.js?v=5"></script>
</body>
</html>
