<?php
require_once __DIR__ . '/game-progress.php';
$user = currentUser();
$isStudent = $user && $user['role'] === 'hocsinh';
$gameId = 'cityHero';

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
    (function() {
        try {
            document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme") || "light");
        } catch (e) {}
    })();
    </script>
    <title>Siêu nhí xử lý tình huống · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=21">
</head>

<body>

    <nav class="navbar static" id="navbar">
        <div class="nav-inner">
            <a href="index.php" class="logo"><span class="logo-badge">🤖</span>SIÊU NHÍ <span
                    class="logo-text-en">AI</span></a>
            <a class="back-link" href="game-mini.php">← Về Game Mini</a>
            <div class="nav-actions">
                <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
                <div class="status-item" style="font-size:12.5px;"><span class="s-ic">⭐</span> <span
                        id="xpVal"><?= (int)$progress['xp'] ?></span> XP</div>
                <div class="status-item" style="font-size:12.5px;"><span class="s-ic">🪙</span> <span
                        id="coinVal"><?= (int)$progress['coin'] ?></span></div>
                <div class="status-item" style="font-size:12.5px;"><span class="s-ic">🏅</span> <span
                        id="badgeVal"><?= (int)$badgeCount ?></span></div>
                <div class="status-item" style="font-size:12.5px;">🎖️ Cấp <span
                        id="levelVal"><?= $currentLevel ?></span></div>
            </div>
        </div>
    </nav>

    <div class="page-head wrap">
        <span class="eyebrow-pill"><span class="dot"></span> 🦸 Game 5 · Siêu nhí xử lý tình huống</span>
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
    <script src="assets/js/game-city-hero.js?v=5"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>