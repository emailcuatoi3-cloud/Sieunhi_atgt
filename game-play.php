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

$PAGE = [
    'title' => $meta['title'] . ' · Siêu Nhí An Toàn Giao Thông AI',
    'nav'   => 'thu-thach',
    'crumb' => ['href' => 'game-mini.php', 'label' => '← Thử thách'],
];
require __DIR__ . '/partials/site-head.php';
require __DIR__ . '/partials/site-nav.php';
?>

    <div class="page-head wrap">
        <span class="eyebrow-pill"><span class="dot"></span> <?= $meta['icon'] ?> Đang chơi</span>
        <h1><?= e($meta['title']) ?></h1>
        <p><?= e($meta['desc']) ?></p>
        <div class="status-bar">
            <div class="status-item"><span class="s-ic">⭐</span> <span id="xpVal"><?= (int)($progress['xp'] ?? 0) ?></span> XP</div>
            <div class="status-div"></div>
            <div class="status-item"><span class="s-ic">🪙</span> <span id="coinVal"><?= (int)($progress['coin'] ?? 0) ?></span> Coin</div>
            <div class="status-div"></div>
            <div class="status-item"><span class="s-ic">🏅</span> <span id="badgeVal"><?= (int)$badgeCount ?></span> Huy hiệu</div>
            <div class="status-div"></div>
            <div class="status-item"><span class="s-ic">🎖️</span> Cấp <span id="levelVal"><?= $currentLevel ?></span></div>
        </div>
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
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.openGame) openGame(window.GAME_ID);
    });
    </script>
    <?php
    $PAGE['scripts'] = ['assets/js/sound-fx.js', 'assets/js/game-mini.js'];
    require __DIR__ . '/partials/site-footer.php';
    ?>