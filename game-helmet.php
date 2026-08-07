<?php
require_once __DIR__ . '/game-progress.php';
$user = currentUser();
$isStudent = $user && $user['role'] === 'hocsinh';
$gameId = 'helmet';

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
$PAGE = [
    'title' => 'Chiếc mũ thần kỳ · Siêu Nhí An Toàn Giao Thông AI',
    'nav'   => 'thu-thach',
    'crumb' => ['href' => 'game-mini.php', 'label' => '← Thử thách'],
];
require __DIR__ . '/partials/site-head.php';
require __DIR__ . '/partials/site-nav.php';
?>

    <div class="page-head wrap">
        <span class="eyebrow-pill"><span class="dot"></span> 🪖 Game 2 · Chiếc mũ thần kỳ</span>
        <div class="status-bar">
            <div class="status-item"><span class="s-ic">⭐</span> <span id="xpVal"><?= (int)$progress['xp'] ?></span> XP</div>
            <div class="status-div"></div>
            <div class="status-item"><span class="s-ic">🪙</span> <span id="coinVal"><?= (int)$progress['coin'] ?></span> Coin</div>
            <div class="status-div"></div>
            <div class="status-item"><span class="s-ic">🏅</span> <span id="badgeVal"><?= (int)$badgeCount ?></span> Huy hiệu</div>
            <div class="status-div"></div>
            <div class="status-item"><span class="s-ic">🎖️</span> Cấp <span id="levelVal"><?= $currentLevel ?></span></div>
        </div>
    </div>

    <section style="padding:0 0 90px;">
        <div class="wrap ge-shell">
            <div id="stage"></div>
        </div>
    </section>

    <script>
    window.IS_STUDENT = <?= $isStudent ? 'true' : 'false' ?>;
    </script>
    <?php
    $PAGE['scripts'] = ['assets/js/sound-fx.js', 'assets/js/game-engine.js', 'assets/js/mascot.js', 'assets/js/game-helmet.js'];
    require __DIR__ . '/partials/site-footer.php';
    ?>