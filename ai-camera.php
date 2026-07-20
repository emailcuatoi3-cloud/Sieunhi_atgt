<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php'; // for e() helper

$aiEnabled = AI_CAMERA_ENABLED && ROBOFLOW_KEY !== '' && ROBOFLOW_MODEL !== '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme")||"dark");}catch(e){}})();</script>
<script>
window.__AI_CAMERA__ = {
  enabled: <?= $aiEnabled ? 'true' : 'false' ?>,
  key:     "<?= $aiEnabled ? e(ROBOFLOW_KEY)   : '' ?>",
  model:   "<?= $aiEnabled ? e(ROBOFLOW_MODEL) : '' ?>"
};
</script>
<title>AI Camera · Siêu Nhí An Toàn Giao Thông AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=5">
<link rel="stylesheet" href="assets/css/shared-pages.css?v=5">
</head>
<body>

<nav class="navbar static" id="navbar">
  <div class="nav-inner">
    <a href="index.php" class="logo"><span class="logo-badge">🤖</span>SIÊU NHÍ <span class="logo-text-en">AI</span></a>
    <a class="back-link" href="index.php">← Về trang chủ</a>
    <div class="nav-actions">
      <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
      <a class="btn btn-ghost" href="ai-gia-su.php">🎓 AI Gia sư</a>
      <a class="btn btn-ghost" href="ai-mo-phong.php">🚦 Mô phỏng</a>
      <a class="btn btn-ghost" href="game-mini.php">🎮 Game Mini</a>
    </div>
  </div>
</nav>

<div class="page-head wrap">
  <span class="eyebrow-pill"><span class="dot"></span> AI Camera Vision</span>
  <h1>AI nhìn thấy những gì con nhìn thấy</h1>
  <p>Bật camera hoặc tải ảnh lên — AI sẽ nhận diện mũ bảo hiểm, biển báo, đèn tín hiệu và đưa ra lời khuyên an toàn ngay lập tức.</p>
</div>

<div class="wrap cam-layout">
  <div class="cam-panel">
    <div class="cam-tabs">
      <div class="cam-tab active">📷 Camera trực tiếp</div>
      <div class="cam-tab">🖼️ Tải ảnh lên</div>
    </div>
    <div class="cam-view">
      <div class="cam-hud-top">
        <span class="rec-dot"><i></i> AI ĐANG PHÂN TÍCH</span>
        <span>1280×720 · 30fps</span>
      </div>
      <div class="cam-scene">
        <div class="street"></div>
        <div class="lane"></div>
        <div class="kid">🧒</div>
        <div class="helmet-box"></div>
        <div class="sign-icon">🚸</div>
        <div class="sign-box"></div>
        <div class="scan-line"></div>
      </div>
    </div>
    <div class="cam-controls">
      <button class="ctrl-btn" title="Đổi camera">🔄</button>
      <button class="ctrl-btn shutter" title="Chụp ảnh" onclick="rescan()">📸</button>
      <button class="ctrl-btn" title="Tải ảnh lên">🖼️</button>
    </div>
    <div class="upload-hint">Hỗ trợ JPG, PNG · Hoặc kéo thả ảnh vào khung camera</div>
  </div>

  <div class="result-panel">
    <div class="card-head" style="margin-bottom:0;">
      <h3>Kết quả nhận diện</h3>
      <span class="accuracy-badge" id="accBadge">✓ Độ chính xác 96%</span>
    </div>

    <div class="detect-item ok">
      <div class="d-icon">⛑️</div>
      <div class="d-info"><b>Mũ bảo hiểm — Đạt chuẩn</b><span>Cài quai đúng cách, vừa khít đầu</span></div>
      <div class="detect-bar"><i></i></div>
    </div>
    <div class="detect-item ok">
      <div class="d-icon">🚸</div>
      <div class="d-info"><b>Biển báo khu vực trường học</b><span>Nhận diện rõ ràng, vị trí chính xác</span></div>
      <div class="detect-bar"><i></i></div>
    </div>
    <div class="detect-item warn">
      <div class="d-icon">🚶</div>
      <div class="d-info"><b>Vị trí đứng — Cần chú ý</b><span>Hơi lệch khỏi vạch qua đường an toàn</span></div>
      <div class="detect-bar"><i></i></div>
    </div>

    <div class="advice-box">
      <h4>💡 Lời khuyên từ AI</h4>
      <p>Con đội mũ bảo hiểm rất chuẩn rồi! Tuy nhiên hãy đứng đúng trong vạch kẻ dành cho người đi bộ trước khi qua đường, và luôn quan sát hai bên trước khi bước xuống lòng đường nhé.</p>
    </div>

    <div class="result-actions">
      <button class="btn btn-primary-sm" style="flex:1; justify-content:center;" onclick="rescan()">🔁 Quét lại</button>
      <button class="btn btn-ghost" style="flex:1; justify-content:center;">📄 Lưu kết quả</button>
    </div>

    <div>
      <h4 style="font-size:12.5px; text-transform:uppercase; letter-spacing:.06em; color:rgba(255,255,255,0.42); margin-bottom:10px;">Lịch sử quét gần đây</h4>
      <div class="history-strip">
        <div class="hist-thumb ok">⛑️<span class="hist-status">✓</span></div>
        <div class="hist-thumb ok">🚦<span class="hist-status">✓</span></div>
        <div class="hist-thumb warn">🚲<span class="hist-status">!</span></div>
        <div class="hist-thumb ok">🚸<span class="hist-status">✓</span></div>
        <div class="hist-thumb ok">🚏<span class="hist-status">✓</span></div>
      </div>
    </div>
  </div>
</div>

<section class="catalog">
  <div class="wrap">
    <div class="section-head">
      <span class="kicker">Phạm vi nhận diện</span>
      <h2>AI Camera nhận diện được những gì?</h2>
      <p>Được huấn luyện chuyên biệt cho bối cảnh giao thông Việt Nam, từ biển báo đến hành vi an toàn hằng ngày.</p>
    </div>
    <div class="cat-grid">
      <div class="cat-card"><div class="ic">⛑️</div><h5>Mũ bảo hiểm</h5><span>Đội đúng / sai cách</span></div>
      <div class="cat-card"><div class="ic">🚸</div><h5>Biển báo</h5><span>Cấm, nguy hiểm, chỉ dẫn</span></div>
      <div class="cat-card"><div class="ic">🚦</div><h5>Đèn giao thông</h5><span>Xanh, vàng, đỏ</span></div>
      <div class="cat-card"><div class="ic">🚶</div><h5>Người đi bộ</h5><span>Vị trí, hành vi qua đường</span></div>
      <div class="cat-card"><div class="ic">🚲</div><h5>Xe đạp</h5><span>Làn đường, tốc độ</span></div>
      <div class="cat-card"><div class="ic">🏍️</div><h5>Xe máy</h5><span>Khoảng cách an toàn</span></div>
      <div class="cat-card"><div class="ic">🦓</div><h5>Vạch qua đường</h5><span>Đi đúng vạch kẻ</span></div>
      <div class="cat-card"><div class="ic">🚑</div><h5>Xe ưu tiên</h5><span>Cứu thương, cứu hoả</span></div>
    </div>
  </div>
</section>

<script src="assets/js/main.js?v=5"></script>
<script src="assets/js/ai-camera.js?v=5"></script>
</body>
</html>
