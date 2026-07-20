<!DOCTYPE html>
<html lang="vi">

<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
    (function() {
        try {
            document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme") || "dark");
        } catch (e) {}
    })();
    </script>
    <title>AI Mô phỏng giao thông · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=24">
</head>

<body>

    <nav class="navbar static" id="navbar">
        <div class="nav-inner">
            <a href="index.php" class="logo"><span class="logo-badge">🤖</span>SIÊU NHÍ <span
                    class="logo-text-en">AI</span></a>
            <a class="back-link" href="index.php">← Về trang chủ</a>
            <div class="nav-actions">
                <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
                <a class="btn btn-ghost" href="ai-gia-su.php">🎓 AI Gia sư</a>
                <a class="btn btn-ghost" href="ai-camera.php">📷 AI Camera</a>
                <a class="btn btn-ghost" href="game-mini.php">🎮 Game Mini</a>
            </div>
        </div>
    </nav>

    <div class="page-head wrap">
        <span class="eyebrow-pill"><span class="dot"></span> AI Mô phỏng giao thông</span>
        <h1>Ngã tư ảo, tình huống thật</h1>
        <p>AI tạo ra ngã tư với thời tiết và phương tiện ngẫu nhiên mỗi lượt chơi. Con hãy chọn hành động đúng để qua
            đường an toàn!</p>
        <div class="cond-row" id="condRow"></div>
    </div>

    <div class="wrap sim-layout">
        <div class="board-panel">
            <div class="board-top">
                <h3>🗺️ Ngã tư mô phỏng #<span id="roundNum">1</span></h3>
                <div class="score-pill">⭐ <span id="scoreVal">0</span> điểm</div>
            </div>

            <div class="board" id="board">
                <canvas id="simCanvas"
                    style="position:absolute; inset:0; width:100%; height:100%; display:block;"></canvas>
                <div class="signal" id="signal">
                    <i class="red" id="sigRed"></i><i class="yellow" id="sigYellow"></i><i class="green"
                        id="sigGreen"></i>
                </div>
                <div class="ai-callout" id="aiCallout">🤖 AI: Quan sát đèn tín hiệu và phương tiện trước khi quyết định
                    nhé.</div>
            </div>

            <div class="action-row" id="actionRow">
                <button class="action-btn" data-action="di" onclick="chooseAction('di')"><span
                        class="a-ic">🚶</span>Đi</button>
                <button class="action-btn" data-action="dung" onclick="chooseAction('dung')"><span
                        class="a-ic">✋</span>Dừng</button>
                <button class="action-btn" data-action="quansat" onclick="chooseAction('quansat')"><span
                        class="a-ic">👀</span>Quan sát</button>
                <button class="action-btn" data-action="re" onclick="chooseAction('re')"><span
                        class="a-ic">↪️</span>Rẽ</button>
                <button class="action-btn" data-action="qua" onclick="chooseAction('qua')"><span
                        class="a-ic">🦓</span>Qua đường</button>
            </div>
        </div>

        <div class="info-panel" style="display:flex; flex-direction:column; gap:18px;">
            <div class="card">
                <h4 style="font-size:14px; font-weight:700; margin-bottom:12px; font-family:'Baloo 2',sans-serif;">📋
                    Tình huống hiện tại</h4>
                <p class="scenario-desc" id="scenarioDesc">Con đang đứng ở vỉa hè trước ngã tư. Đèn tín hiệu dành cho
                    người đi bộ đang đỏ. Có xe cộ qua lại. Con sẽ làm gì?</p>
                <ul class="scenario-list" id="scenarioList"></ul>
            </div>

            <div class="card">
                <h4 style="font-size:14px; font-weight:700; margin-bottom:12px; font-family:'Baloo 2',sans-serif;">🤖
                    Phản hồi từ AI</h4>
                <div class="feedback-box empty" id="feedbackBox">Chọn một hành động để xem AI chấm điểm và giải thích.
                </div>
                <div class="fb-actions" id="fbActions" style="display:none;">
                    <button class="btn btn-ghost" onclick="replay()">🔁 Chơi lại</button>
                    <button class="btn btn-primary-sm" onclick="nextRound()">➡️ Tình huống tiếp theo</button>
                </div>
            </div>

            <div class="card">
                <h4 style="font-size:14px; font-weight:700; margin-bottom:12px; font-family:'Baloo 2',sans-serif;">📜
                    Lịch sử lượt chơi</h4>
                <div class="history-row" id="historyRow">
                    <div class="history-item"><span class="h-txt" style="color:rgba(255,255,255,0.4)">Chưa có lượt chơi
                            nào</span></div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js?v=5"></script>
    <script src="assets/js/ai-mo-phong.js?v=11"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>