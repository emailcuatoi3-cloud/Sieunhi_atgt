<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme")||"dark");}catch(e){}})();</script>
<title>AI Gia sư · Siêu Nhí An Toàn Giao Thông AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=5">
<link rel="stylesheet" href="assets/css/shared-pages.css?v=5">
<style>
  html,body{ height:100%; overflow:hidden; }
</style>
</head>
<body>

<div class="app">
  <aside class="sidebar">
    <div>
      <div class="side-brand"><div class="mark">🤖</div>AI Gia sư</div>
      <a class="side-back" href="sieu-nhi-atgt-ai.php">← Về trang chủ</a>
    </div>
    <button class="btn btn-primary-sm" style="width:100%; justify-content:center; margin-bottom:14px;" onclick="newChat()">＋ Cuộc trò chuyện mới</button>

    <div>
      <div class="side-link" style="color:rgba(255,255,255,0.4); font-size:11px; text-transform:uppercase; letter-spacing:.06em; pointer-events:none;">Hôm nay</div>
      <div class="side-link active" style="cursor:pointer;">🟢 Đèn vàng có được đi?</div>
      <div class="side-link" style="cursor:pointer;">⛑️ Đội mũ bảo hiểm đúng cách</div>
    </div>
    <div>
      <div class="side-link" style="color:rgba(255,255,255,0.4); font-size:11px; text-transform:uppercase; letter-spacing:.06em; pointer-events:none; margin-top:10px;">7 ngày qua</div>
      <div class="side-link" style="cursor:pointer;">🚑 Gặp xe cứu thương</div>
      <div class="side-link" style="cursor:pointer;">🚸 Biển báo trường học</div>
      <div class="side-link" style="cursor:pointer;">🚲 Đi xe đạp an toàn</div>
      <div class="side-link" style="cursor:pointer;">🛑 Ý nghĩa biển STOP</div>
    </div>

    <div class="side-divider"></div>
    <a class="side-link" href="ai-camera.php"><span class="ic">📷</span> AI Camera</a>
    <a class="side-link" href="ai-mo-phong.php"><span class="ic">🚦</span> Mô phỏng</a>
    <a class="side-link" href="ai-truyen-tranh.php"><span class="ic">📖</span> Truyện tranh</a>
    <a class="side-link" href="game-mini.php"><span class="ic">🎮</span> Game Mini</a>
    <a class="side-link" href="dashboard-hoc-sinh.php"><span class="ic">🎒</span> Dashboard học sinh</a>

    <div class="sidebar-foot">
      <div class="av">🧒</div>
      <div class="txt"><b>Bé Minh An</b><span>Lớp 3 · Cấp độ 7</span></div>
    </div>
  </aside>

  <div class="chat-col">
    <div class="chat-top">
      <h2><span class="status-dot"></span> AI Gia sư — luôn sẵn sàng lắng nghe</h2>
      <div class="top-actions">
        <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
        <div class="icon-btn" title="Video call AI">🎥</div>
        <div class="icon-btn" title="Xuất báo cáo">📄</div>
        <div class="icon-btn" title="Cài đặt">⚙️</div>
      </div>
    </div>

    <div class="chat-scroll" id="chatScroll">
      <div class="chat-inner" id="chatInner">

        <div class="msg bot">
          <div class="msg-avatar">🤖</div>
          <div class="msg-body"><div class="msg-bubble">Chào Minh An! Mình là AI Gia sư 🤖 — hôm nay con muốn học về điều gì trên đường phố nào?</div></div>
        </div>

        <div class="msg user">
          <div class="msg-avatar">🧒</div>
          <div class="msg-body"><div class="msg-bubble">Con có được sang đường khi đèn vàng không ạ?</div></div>
        </div>

        <div class="msg bot">
          <div class="msg-avatar">🤖</div>
          <div class="msg-body">
            <div class="msg-bubble">Đèn vàng nghĩa là chuẩn bị dừng lại, không phải để đi nhanh qua đâu nhé. Nếu con đang đứng chờ ở vỉa hè, hãy đợi đèn xanh dành cho người đi bộ. An toàn luôn là ưu tiên số một! 🚦</div>
            <div class="voice-note">
              <span>🔊</span>
              <div class="voice-bars"><i></i><i></i><i></i><i></i><i></i><i></i></div>
              <span style="font-size:11px; color:rgba(255,255,255,0.5);">0:07</span>
            </div>
            <div class="msg-tools">
              <div class="msg-tool">🔁 Nghe lại</div>
              <div class="msg-tool">👍 Hữu ích</div>
              <div class="msg-tool">👎 Chưa rõ</div>
            </div>
          </div>
        </div>

        <div class="msg user">
          <div class="msg-avatar">🧒</div>
          <div class="msg-body"><div class="msg-bubble">📷 [Đã gửi ảnh biển báo] Đây là biển gì vậy AI?</div></div>
        </div>

        <div class="msg bot">
          <div class="msg-avatar">🤖</div>
          <div class="msg-body">
            <div class="msg-bubble">Mình nhận diện được rồi! Đây là biển báo nguy hiểm hình tam giác viền đỏ, cảnh báo phía trước có khúc cua gấp. Con nhớ giảm tốc độ khi thấy biển này nhé.</div>
            <div class="illus-card">
              <div class="illus-visual">⚠️<span class="badge">Độ chính xác 95%</span></div>
              <div class="illus-caption">Biển báo nguy hiểm — Khúc cua gấp</div>
            </div>
          </div>
        </div>

      </div>
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
          <button class="icon-btn-sm" title="Đính kèm ảnh">📎</button>
          <button class="icon-btn-sm" title="Mở camera">📷</button>
          <input id="chatText" type="text" placeholder="Hỏi AI Gia sư điều gì đó về giao thông..." onkeydown="if(event.key==='Enter')sendMsg();">
          <button class="icon-btn-sm" title="Emoji">😊</button>
          <button class="icon-btn-sm" title="Ghi âm giọng nói">🎤</button>
          <button class="icon-btn-sm send" title="Gửi" onclick="sendMsg()">➤</button>
        </div>
        <div class="input-hint">AI Gia sư có thể trả lời bằng văn bản, giọng nói và hình minh hoạ.</div>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/main.js?v=5"></script>
<script src="assets/js/ai-gia-su.js?v=5"></script>
</body>
</html>
