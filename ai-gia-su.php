<?php
require_once __DIR__ . '/auth.php';
$user = currentUser();
$fullname = $user['name'] ?? 'Bé Minh An';
$avatar = $user['avatar'] ?? '🧒';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme")||"dark");}catch(e){}})();</script>
<title>AI Gia sư · Siêu Nhí An Toàn Giao Thông AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=9">
<link rel="stylesheet" href="assets/css/shared-pages.css?v=25">
<style>
  html,body{ height:100%; overflow:hidden; }

  /* ===== Lịch sử trò chuyện (sidebar) ===== */
  .session-list{ display:flex; flex-direction:column; }
  .group-label{
    color:rgba(255,255,255,0.4) !important; font-size:11px;
    text-transform:uppercase; letter-spacing:.06em;
    pointer-events:none; margin-top:10px;
  }
  .session-item{ display:flex; align-items:center; gap:6px; cursor:pointer; }
  .session-item .s-title{ flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .session-item .del{
    opacity:0; font-size:11px; padding:2px 6px; border-radius:6px; transition:.15s;
  }
  .session-item:hover .del{ opacity:.75; }
  .session-item .del:hover{ opacity:1; background:rgba(255,255,255,.15); }

  /* ===== Hiệu ứng AI đang gõ ===== */
  .typing-dots{ display:inline-flex; gap:5px; align-items:center; padding:5px 2px; }
  .typing-dots i{
    width:7px; height:7px; border-radius:50%; background:currentColor;
    opacity:.35; animation:tdots 1s infinite;
  }
  .typing-dots i:nth-child(2){ animation-delay:.15s; }
  .typing-dots i:nth-child(3){ animation-delay:.3s; }
  @keyframes tdots{
    0%,100%{ opacity:.25; transform:translateY(0); }
    50%{ opacity:1; transform:translateY(-3px); }
  }

  /* ===== Nút mic đang ghi âm ===== */
  #micBtn.rec{
    background:var(--pink) !important; color:#fff !important;
    animation:pulse 1s infinite;
  }
  @keyframes pulse{ 0%,100%{ transform:scale(1); } 50%{ transform:scale(1.12); } }

  /* ===== Bảng chọn emoji ===== */
  .chat-input-inner{ position:relative; }
  .emoji-pop{
    position:absolute; right:8px; bottom:calc(100% + 8px);
    background:#fff; border-radius:14px; box-shadow:0 8px 30px rgba(0,0,0,.18);
    padding:8px; display:none; gap:2px; flex-wrap:wrap; width:232px; z-index:50;
  }
  .emoji-pop.open{ display:flex; }
  .emoji-pop span{ font-size:20px; padding:6px; cursor:pointer; border-radius:8px; }
  .emoji-pop span:hover{ background:#f0f4ff; }

  /* ===== Thông báo nhỏ (toast) ===== */
  .toast{
    position:fixed; left:50%; bottom:26px;
    transform:translateX(-50%) translateY(20px);
    background:var(--bg-deep-2); color:#fff; padding:10px 18px; border-radius:999px;
    font-size:13px; opacity:0; pointer-events:none; transition:.25s; z-index:999;
    border:1px solid var(--glass-border);
  }
  .toast.show{ opacity:1; transform:translateX(-50%) translateY(0); }

  /* ===== Nút đánh giá đã chọn ===== */
  .msg-tool.active{ background:var(--yellow) !important; color:var(--ink) !important; }
</style>
</head>
<body>

<div class="app">
  <aside class="sidebar">
    <div>
      <div class="side-brand"><div class="mark">🤖</div>AI Gia sư</div>
      <a class="side-back" href="index.php">← Về trang chủ</a>
    </div>
    <button class="btn btn-primary-sm" style="width:100%; justify-content:center; margin-bottom:14px;" onclick="newChat()">＋ Cuộc trò chuyện mới</button>

    <!-- Lịch sử trò chuyện: tải từ CSDL bằng JavaScript -->
    <div id="sessionList" class="session-list"></div>

    <div class="side-divider"></div>
    <a class="side-link" href="ai-camera.php"><span class="ic">📷</span> AI Camera</a>
    <a class="side-link" href="ai-mo-phong.php"><span class="ic">🚦</span> Mô phỏng</a>
    <a class="side-link" href="ai-truyen-tranh.php"><span class="ic">📖</span> Truyện tranh</a>
    <a class="side-link" href="game-mini.php"><span class="ic">🎮</span> Game Mini</a>
    <?php if ($user && $user['role'] === 'hocsinh'): ?>
    <a class="side-link" href="dashboard-hoc-sinh.php"><span class="ic">🎒</span> Dashboard học sinh</a>
    <?php endif; ?>

    <div class="sidebar-foot">
      <div class="av"><?= e($avatar) ?></div>
      <div class="txt"><b><?= e($fullname) ?></b><span><?= $user ? e(ROLE_LABELS[$user['role']] ?? '') : 'Khách (chưa đăng nhập)' ?></span></div>
    </div>
  </aside>

  <div class="chat-col">
    <div class="chat-top">
      <h2><span class="status-dot"></span> AI Gia sư — luôn sẵn sàng lắng nghe</h2>
      <div class="top-actions">
        <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
        <div class="icon-btn" title="Video call AI" onclick="toast('Video call AI sắp ra mắt 🎥')">🎥</div>
        <div class="icon-btn" title="Xuất báo cáo trò chuyện" onclick="exportChat()">📄</div>
        <div class="icon-btn" title="Cài đặt" onclick="toast('Cài đặt đang được phát triển ⚙️')">⚙️</div>
      </div>
    </div>

    <div class="chat-scroll" id="chatScroll">
      <!-- Tin nhắn được hiển thị bằng JavaScript -->
      <div class="chat-inner" id="chatInner"></div>
    </div>

    <div class="suggest-row chat" id="suggestRow">
      <div class="suggest-chip" onclick="askSuggested(this)">Đội mũ như thế nào là đúng?</div>
      <div class="suggest-chip" onclick="askSuggested(this)">Con nên làm gì khi gặp xe cứu thương?</div>
      <div class="suggest-chip" onclick="askSuggested(this)">Vạch kẻ đường dành cho ai?</div>
      <div class="suggest-chip" onclick="askSuggested(this)">Đi xe đạp cần đội mũ không?</div>
    </div>

    <div class="chat-input-wrap">
      <div class="chat-input-inner">
        <div class="tutor-input">
          <button class="icon-btn-sm" title="Đính kèm ảnh" onclick="toast('Tính năng đính kèm ảnh đang phát triển 🚧')">📎</button>
          <button class="icon-btn-sm" title="Mở AI Camera" onclick="location.href='ai-camera.php'">📷</button>
          <input id="chatText" type="text" placeholder="Hỏi AI Gia sư điều gì đó về giao thông..." onkeydown="if(event.key==='Enter')sendMsg();">
          <button class="icon-btn-sm" title="Emoji" data-emoji-btn onclick="toggleEmoji(event)">😊</button>
          <button class="icon-btn-sm" id="micBtn" title="Nói chuyện với AI" onclick="toggleMic(this)">🎤</button>
          <button class="icon-btn-sm send" title="Gửi" onclick="sendMsg()">➤</button>
        </div>
        <div id="emojiPop" class="emoji-pop"></div>
        <div class="input-hint">AI Gia sư có thể trả lời bằng văn bản và giọng nói — bấm 🔊 dưới mỗi câu trả lời để nghe.</div>
      </div>
    </div>
  </div>
</div>

<script>
  // Tên học sinh truyền từ PHP sang JavaScript
  const STUDENT_NAME = <?php echo json_encode($fullname, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/main.js?v=5"></script>
<script src="assets/js/ai-gia-su.js?v=1"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
