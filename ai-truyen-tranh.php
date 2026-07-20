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
    <title>AI Truyện tranh · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=25">
</head>

<body>

    <nav class="navbar static" id="navbar">
        <div class="nav-inner">
            <a href="sieu-nhi-atgt-ai.php" class="logo"><span class="logo-badge">🤖</span>SIÊU NHÍ <span
                    class="logo-text-en">AI</span></a>
            <a class="back-link" href="sieu-nhi-atgt-ai.php">← Về trang chủ</a>
            <div class="nav-actions">
                <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
                <a class="btn btn-ghost" href="ai-mo-phong.php">🚦 Mô phỏng</a>
                <a class="btn btn-ghost" href="game-mini.php">🎮 Game Mini</a>
            </div>
        </div>
    </nav>

    <div class="page-head wrap">
        <span class="eyebrow-pill"><span class="dot"></span> AI Truyện tương tác</span>
        <h1>Truyện tranh AI — mỗi lựa chọn, một kết thúc</h1>
        <p>Cùng bạn Bo trên đường đến trường. Con hãy chọn hành động cho Bo — mỗi lựa chọn sẽ dẫn tới một câu chuyện
            khác nhau!</p>
    </div>

    <div class="wrap">
        <div class="path-row" id="pathRow"></div>
    </div>

    <div class="wrap story-wrap">
        <div class="story-card">
            <div class="scene-visual">
                <span class="scene-tag" id="sceneTag">📍 Cổng trường Tiểu học Việt Nam</span>
                <span class="chapter-tag" id="chapterTag">Chương 1</span>
                <div class="scene-stage scene-in" id="sceneStage"></div>
            </div>
            <div class="story-body">
                <div class="speaker">
                    <div class="sp-avatar" id="speakerAvatar">🧒</div>
                    <b id="speakerName">Bo</b>
                </div>
                <div class="narration" id="narrationText"></div>
                <div class="choice-grid" id="choiceGrid"></div>
                <div id="endBlock" style="display:none;"></div>
            </div>
        </div>
    </div>

    <section class="library">
        <div class="wrap">
            <div class="section-head">
                <span class="kicker">Thư viện truyện</span>
                <h2>Nhiều câu chuyện đang chờ con khám phá</h2>
                <p>Mỗi truyện có nhân vật, bối cảnh Việt Nam riêng và nhiều kết thúc khác nhau tuỳ theo lựa chọn của
                    con.</p>
            </div>
            <div class="lib-grid">
                <div class="lib-card">
                    <div class="lib-visual">🏫</div>
                    <div class="lib-info">
                        <h5>Bo đến trường</h5><span>Chương 1 · Đang chơi</span>
                    </div>
                </div>
                <div class="lib-card">
                    <div class="lib-visual">🚲</div>
                    <div class="lib-info">
                        <h5>Chuyến xe đạp của Na</h5><span>3 chương</span>
                        <div class="lib-lock">🔒 Mở khoá ở cấp độ 5</div>
                    </div>
                </div>
                <div class="lib-card">
                    <div class="lib-visual">👮</div>
                    <div class="lib-info">
                        <h5>Một ngày cùng chú Công an</h5><span>4 chương</span>
                        <div class="lib-lock">🔒 Mở khoá ở cấp độ 8</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="assets/js/main.js?v=5"></script>
    <script src="assets/js/comic-scenes.js?v=1"></script>
    <script src="assets/js/ai-truyen-tranh.js?v=6"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>