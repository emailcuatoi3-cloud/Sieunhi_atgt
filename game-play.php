<?php
require_once __DIR__ . '/game-progress.php';
$user = currentUser();
$isStudent = $user && $user['role'] === 'hocsinh';

$gameId = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['game'] ?? '');

$gameMeta = [
    'signMatch'  => ['icon' => '🚸', 'title' => 'Kéo thả biển báo giao thông', 'desc' => 'Kéo mỗi biển báo bên trái vào đúng ô ý nghĩa của nó bên phải.'],
    'puzzle'     => ['icon' => '🎒', 'title' => 'Hành trình đến trường an toàn', 'desc' => 'Sắp xếp đúng thứ tự các bước để đến trường an toàn mỗi ngày.'],
    'lightOrder' => ['icon' => '🧠', 'title' => 'Đố vui an toàn giao thông', 'desc' => 'Trả lời nhanh các tình huống thực tế mà con có thể gặp trên đường đi học.'],
    'spotError'  => ['icon' => '🔍', 'title' => 'Tìm lỗi sai trong tranh', 'desc' => 'Quan sát và bấm vào những hành vi KHÔNG an toàn trong tranh.'],
    'maze'       => ['icon' => '🚦', 'title' => 'Băng qua ngã tư an toàn', 'desc' => 'Quan sát đèn tín hiệu và xe cộ tại mỗi ngã tư trên đường tới trường, chọn đúng hành động để qua an toàn.'],
    'bikeRace'   => ['icon' => '🏁', 'title' => 'Đua xe đạp an toàn', 'desc' => 'Phản xạ nhanh: chọn đúng hành động an toàn trước khi hết giờ.'],
];

if (!$gameId || !isset($gameMeta[$gameId])) {
    header('Location: game-mini.php');
    exit;
}

$currentLevel = 1;
$badgeCount = 0;
if ($isStudent) {
    $progress = getStudentProgress($user['id']);
    $currentLevel = (int)$progress['level'];
    $badgeCount = countStudentBadges($user['id']);
    if (!isGameUnlocked($gameId, true, $currentLevel)) {
        header('Location: game-mini.php?locked=' . $gameId);
        exit;
    }
} else {
    $progress = ['xp' => 0, 'coin' => 0];
}

$meta = $gameMeta[$gameId];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
    (function() {
        try {
            document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme") || "dark");
        } catch (e) {}
    })();
    </script>
    <title><?= e($meta['title']) ?> · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=5">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=6">
</head>

<body>

    <nav class="navbar static" id="navbar">
        <div class="nav-inner">
            <a href="sieu-nhi-atgt-ai.php" class="logo"><span class="logo-badge">🤖</span>SIÊU NHÍ <span
                    class="logo-text-en">AI</span></a>
            <a class="back-link" href="game-mini.php">← Về Game Mini</a>
            <div class="nav-actions">
                <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
                <div class="status-item" style="font-size:12.5px;"><span class="s-ic">⭐</span> <span
                        id="xpVal"><?= (int)($progress['xp'] ?? 0) ?></span> XP</div>
                <div class="status-item" style="font-size:12.5px;"><span class="s-ic">🪙</span> <span
                        id="coinVal"><?= (int)($progress['coin'] ?? 0) ?></span></div>
                <div class="status-item" style="font-size:12.5px;"><span class="s-ic">🏅</span> <span
                        id="badgeVal"><?= (int)$badgeCount ?></span></div>
                <div class="status-item" style="font-size:12.5px;">🎖️ Cấp <span
                        id="levelVal"><?= $currentLevel ?></span></div>
            </div>
        </div>
    </nav>

    <div class="page-head wrap">
        <span class="eyebrow-pill"><span class="dot"></span> <?= $meta['icon'] ?> Đang chơi</span>
        <h1><?= e($meta['title']) ?></h1>
        <p><?= e($meta['desc']) ?></p>
    </div>

    <section style="padding:10px 0 90px;">
        <div class="wrap" style="max-width:1180px;">
            <div class="game-panel active" id="gamePanel" style="position:static;">
                <div class="gp-top">
                    <h3 id="gpTitle"><?= $meta['icon'] ?> <?= e($meta['title']) ?> — <span id="gpLevelLabel">Cấp
                            1/5</span></h3>
                    <a class="gp-close" href="game-mini.php">✕ Thoát</a>
                </div>
                <p id="gpDesc" style="font-size:13px; color:rgba(255,255,255,0.5); margin-bottom:20px;">
                    <?= e($meta['desc']) ?></p>

                <div id="gpBody"></div>

                <div class="gp-footer">
                    <div class="gp-score" id="gpScore"></div>
                    <div class="gp-actions">
                        <button class="btn btn-ghost" onclick="resetGame()">🔁 Chơi lại cấp này</button>
                        <a class="btn btn-primary-sm" href="game-mini.php">Về danh sách game</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="toast" id="toast"></div>

    <script>
    window.IS_STUDENT = <?= $isStudent ? 'true' : 'false' ?>;
    window.GAME_ID = <?= json_encode($gameId) ?>;
    window.STUDENT_LEVEL = <?= (int)$currentLevel ?>;
    </script>
    <script src="assets/js/main.js?v=5"></script>
    <script src="assets/js/sound-fx.js?v=1"></script>
    <script src="assets/js/game-mini.js?v=8"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.openGame) openGame(window.GAME_ID);
    });
    </script>
</body>

</html>