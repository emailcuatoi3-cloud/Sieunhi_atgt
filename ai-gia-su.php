<?php
require_once __DIR__ . '/auth.php';
$user = currentUser();
$fullname = $user['name'] ?? 'Bé Minh An';
$avatar = $user['avatar'] ?? '🧒';
$ageGroup = (string)($_GET['age_group'] ?? ($user['age_group'] ?? '6-8'));
if (!in_array($ageGroup, ['6-8', '9-11'], true)) $ageGroup = '6-8';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf" content="<?= e(csrfToken()) ?>">
    <script>
    (function() {
        try {
            document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme") || "light");
        } catch (e) {}
    })();
    </script>
    <title>AI Gia sư · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="stylesheet" href="assets/css/fonts.css?v=1">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=16">
    <link rel="stylesheet" href="assets/css/kid-components.css?v=1">
    <style>
    html, body { height: 100%; }
    body.chat-page { display: flex; flex-direction: column; height: 100dvh; overflow: hidden; background: var(--kid-cream); }

    /* ===== Thanh trên cùng ===== */
    .chat-top { display: flex; align-items: center; gap: 14px; padding: 14px 24px; background: #fff;
      border-bottom: 3px solid var(--glass-border); flex: 0 0 auto; }
    .chat-title { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
    .chat-title h1 { font-family: "Baloo 2", sans-serif; font-size: 20px; margin: 0; color: var(--kid-ink); }
    #chat-mascot-mini { width: 48px; height: 48px; flex: 0 0 auto; }
    #btn-toggle-sidebar { display: none; }

    /* ===== Khung chính: sidebar + khu chat ===== */
    .chat-shell { flex: 1; min-height: 0; display: grid; grid-template-columns: 260px 1fr; gap: 16px;
      padding: 16px 24px; overflow: hidden; }

    .chat-sidebar { display: flex; flex-direction: column; gap: 4px; overflow-y: auto; }
    .sidebar-heading { font-family: "Baloo 2", sans-serif; font-weight: 800; color: var(--kid-ink);
      font-size: 14px; margin: 0 0 6px; }
    .sidebar-empty { color: var(--kid-ink-soft); font-size: 13px; margin: 0; }
    .session-item { display: flex; align-items: center; gap: 6px; padding: 10px 12px; border-radius: 14px;
      cursor: pointer; }
    .session-item:hover, .session-item.active { background: var(--kid-cream-2); }
    .session-item .s-title { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis;
      white-space: nowrap; font-size: 13.5px; color: var(--kid-ink); }
    .session-item .s-del { opacity: 0; border: none; background: transparent; font-size: 12px;
      color: var(--kid-red); padding: 3px 7px; border-radius: 8px; cursor: pointer; transition: opacity .12s ease; }
    .session-item:hover .s-del, .session-item .s-del:focus-visible { opacity: 1; }
    .session-item .s-del:hover { background: #FDE8EA; }

    .chat-main { display: flex; flex-direction: column; min-height: 0; padding: 0; overflow: hidden; }
    #chat-log { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column;
      padding: 20px; gap: 14px; }

    .msg { max-width: 82%; }
    .msg.bot { display: flex; gap: 10px; align-self: flex-start; }
    .msg.bot .msg-avatar { width: 42px; height: 42px; flex: 0 0 42px; }
    .msg.bot .msg-body { background: #fff; border: 3px solid var(--kid-sky); border-radius: 20px 20px 20px 6px;
      padding: 12px 16px; font-size: 15px; line-height: 1.55; color: var(--kid-ink); }
    .msg.user { align-self: flex-end; background: var(--kid-yellow); color: var(--kid-ink);
      border-radius: 20px 20px 6px 20px; padding: 12px 16px; font-size: 15px; font-weight: 600; line-height: 1.5; }

    .msg-art { margin-top: 10px; }
    .msg-art img { display: block; max-width: 220px; width: 100%; border-radius: 14px; }

    .chip-row { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 20px 14px; flex: 0 0 auto; }
    .chip-row .kid-chip--login { text-decoration: none; background: var(--kid-yellow);
      border-color: var(--kid-yellow); color: var(--kid-ink); }

    .chat-input-row { display: flex; gap: 10px; padding: 14px 20px; border-top: 3px solid var(--glass-border);
      background: #fff; flex: 0 0 auto; }
    .chat-input-row .kid-input { flex: 1; }

    /* ===== Onboarding ===== */
    .onboard-overlay { position: fixed; inset: 0; background: rgba(75,51,37,.45); display: flex;
      align-items: center; justify-content: center; padding: 20px; z-index: 100; }
    .onboard-box { max-width: 480px; width: 100%; max-height: 88vh; overflow-y: auto; }
    .onboard-step h2 { font-family: "Baloo 2", sans-serif; font-size: 18px; color: var(--kid-ink); margin: 0 0 12px; }
    .onboard-grade-row { display: flex; gap: 12px; flex-wrap: wrap; }
    .onboard-sticker-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
    #onboard-done { margin-top: 4px; }

    @media (max-width: 768px) {
      #btn-toggle-sidebar { display: inline-flex; }
      .chat-shell { grid-template-columns: 1fr; padding: 12px; }
      .chat-sidebar { position: fixed; top: 0; bottom: 0; left: 0; width: 82%; max-width: 320px; z-index: 90;
        transform: translateX(-100%); transition: transform .2s ease; padding: 20px;
        border-radius: 0 var(--radius-lg) var(--radius-lg) 0; }
      .chat-sidebar.open { transform: translateX(0); }
      #chat-log { padding: 14px; }
      .chip-row { padding: 0 14px 12px; }
      .chat-input-row { padding: 12px 14px; padding-bottom: max(12px, env(safe-area-inset-bottom)); }
    }
    @media (max-width: 480px) {
      .chat-top { padding: 10px 12px; gap: 8px; }
      .chat-top .kid-btn { padding: 8px 14px; font-size: 14px; min-height: 40px; }
      .chat-title h1 { font-size: 16px; }
    }
    @media (prefers-reduced-motion: reduce) {
      .chat-sidebar, .session-item { transition: none; }
    }
    </style>
</head>

<body class="chat-page">
<header class="chat-top">
  <button id="btn-toggle-sidebar" class="kid-btn" aria-label="Danh sách trò chuyện" aria-expanded="false" type="button">☰</button>
  <a class="kid-btn kid-btn--sky" href="index.php">← Trang chủ</a>
  <div class="chat-title">
    <div id="chat-mascot-mini"></div>
    <div><h1>AI Gia sư 🤖</h1><span id="engine-label" class="kid-badge">đang kết nối…</span></div>
  </div>
  <button id="btn-new-chat" class="kid-btn" type="button">✨ Chuyện mới</button>
</header>
<main class="chat-shell">
  <aside id="chat-sessions" class="kid-card chat-sidebar" aria-label="Lịch sử trò chuyện"></aside>
  <section class="chat-main kid-card">
    <div id="chat-log" aria-live="polite"></div>
    <div id="chip-row" class="chip-row" aria-label="Câu hỏi gợi ý"></div>
    <form id="chat-form" class="chat-input-row">
      <input id="chat-input" class="kid-input" type="text" maxlength="1000"
             placeholder="Con muốn hỏi gì về giao thông nào?" autocomplete="off">
      <button class="kid-btn kid-btn--green" type="submit" aria-label="Gửi">🚀</button>
    </form>
  </section>
</main>
<div id="onboard-modal" class="onboard-overlay" hidden><!-- JS đổ nội dung --></div>
<script src="assets/js/mascot.js"></script>
<script src="assets/js/ai-gia-su.js?v=2"></script>
</body>
</html>
