<?php
require_once __DIR__ . '/game-progress.php';
$user = currentUser();
$isStudent = $user && $user['role'] === 'hocsinh';

if ($isStudent) {
    $progress = getStudentProgress($user['id']);
    $badgeCount = countStudentBadges($user['id']);
} else {
    $progress = ['xp' => 0, 'coin' => 0, 'streak_days' => 0, 'level' => 1];
    $badgeCount = 0;
}
$currentLevel = (int)$progress['level'];

$games = [
    ['id' => 'pedestrian',    'icon' => '🚶', 'title' => 'Người qua đường thông minh', 'page' => 'game-pedestrian.php',
     'desc' => 'AI sinh ngẫu nhiên nhiều bối cảnh sang đường thực tế: cổng trường, ngã tư, trời mưa, buổi tối... Thực hiện đúng thứ tự các bước an toàn.', 'xp' => 35, 'coin' => 50],
    ['id' => 'helmet',        'icon' => '🪖', 'title' => 'Chiếc mũ thần kỳ', 'page' => 'game-helmet.php',
     'desc' => 'Giúp nhân vật đội mũ bảo hiểm đúng cách qua từng bước, sau đó dùng AI Camera thật để kiểm tra mũ ngoài đời.', 'xp' => 30, 'coin' => 40],
    ['id' => 'signDetective', 'icon' => '🕵️', 'title' => 'Thám tử biển báo', 'page' => 'game-sign-detective.php',
     'desc' => 'Khám phá bản đồ thành phố, tìm đúng các biển báo giao thông Việt Nam theo yêu cầu của AI và xây dựng bộ sưu tập.', 'xp' => 25, 'coin' => 35],
    ['id' => 'safeRoute',     'icon' => '🗺️', 'title' => 'Đường đến trường an toàn', 'page' => 'game-safe-route.php',
     'desc' => 'So sánh nhiều tuyến đường với mức độ an toàn khác nhau và chọn ra tuyến đường an toàn nhất tới trường.', 'xp' => 30, 'coin' => 45],
    ['id' => 'cityHero',      'icon' => '🦸', 'title' => 'Siêu nhí xử lý tình huống', 'page' => 'game-city-hero.php',
     'desc' => 'Gặp xe cứu thương, tai nạn giao thông, trời mưa, công trường... trên đường thì phải làm gì? Cùng xử lý các tình huống thực tế nhé!', 'xp' => 40, 'coin' => 60],
];
$PAGE = [
    'title' => 'Game Mini · Siêu Nhí An Toàn Giao Thông AI',
    'nav'   => 'thu-thach',
];
require __DIR__ . '/partials/site-head.php';
require __DIR__ . '/partials/site-nav.php';
?>

    <div class="page-head wrap">
        <span class="eyebrow-pill"><span class="dot"></span> Thử thách &amp; Trò chơi</span>
        <h1>5 hành trình học an toàn giao thông cùng AI</h1>
        <p>Mỗi trò chơi mô phỏng một tình huống giao thông thực tế mà học sinh gặp hằng ngày — AI luôn đồng hành hướng
            dẫn, giải thích và động viên em từng bước.</p>

        <?php if (!$isStudent): ?>
        <div class="cond-row" style="margin-top:20px;">
            <div class="cond-chip" style="color:var(--yellow); border-color:rgba(251,191,36,0.4);">
                🔒 <?= $user ? 'Đăng nhập bằng tài khoản Học sinh' : 'Đăng nhập' ?> để lưu XP/Coin/Huy hiệu thật và mở
                khoá đầy đủ 5 trò chơi
            </div>
        </div>
        <?php endif; ?>

        <div class="status-bar">
            <div class="status-item"><span class="s-ic">⭐</span> <span id="xpVal"><?= (int)$progress['xp'] ?></span> XP
            </div>
            <div class="status-div"></div>
            <div class="status-item"><span class="s-ic">🪙</span> <span
                    id="coinVal"><?= (int)$progress['coin'] ?></span> Coin</div>
            <div class="status-div"></div>
            <div class="status-item"><span class="s-ic">🏅</span> <span id="badgeVal"><?= (int)$badgeCount ?></span> Huy
                hiệu</div>
            <div class="status-div"></div>
            <div class="status-item"><span class="s-ic">🎖️</span> Cấp <span id="levelVal"><?= $currentLevel ?></span>
            </div>
            <?php if ($isStudent): ?>
            <div class="status-div"></div>
            <div class="status-item"><span class="s-ic">🔥</span> Streak <?= (int)$progress['streak_days'] ?> ngày</div>
            <?php endif; ?>
        </div>
    </div>

    <section class="games">
        <div class="wrap">
            <div class="game-grid">
                <?php foreach ($games as $i => $g):
          $unlocked = isGameUnlocked($g['id'], $isStudent, $currentLevel);
          $needLevel = GAME_UNLOCK_LEVEL[$g['id']];
      ?>
                <div class="game-card<?= $i === 0 ? ' featured' : '' ?><?= $unlocked ? '' : ' locked' ?>"
                    <?= $unlocked ? "onclick=\"location.href='{$g['page']}'\"" : '' ?>>
                    <?php if ($i === 0): ?>
                    <div class="game-visual"><?= $g['icon'] ?><span class="game-badge">🎮 Chơi ngay</span></div>
                    <div class="featured-info">
                        <div class="tag">⭐ Bắt đầu từ đây</div>
                        <h3><?= e($g['title']) ?></h3>
                        <p><?= e($g['desc']) ?></p>
                        <div class="reward-row" style="margin-bottom:16px;">
                            <div class="reward-chip">⭐ +<?= $g['xp'] ?> XP</div>
                            <div class="reward-chip">🪙 +<?= $g['coin'] ?> Coin</div>
                            <div class="reward-chip">🏅 Có huy hiệu riêng</div>
                        </div>
                        <a class="play-btn" href="<?= $g['page'] ?>">▶ Bắt đầu chơi</a>
                    </div>
                    <?php else: ?>
                    <div class="game-visual"><?= $g['icon'] ?><span
                            class="game-badge"><?= $unlocked ? '🎮 Chơi ngay' : '🔒 Khoá' ?></span></div>
                    <div class="game-info">
                        <h4><?= e($g['title']) ?></h4>
                        <p><?= e($g['desc']) ?></p>
                        <div class="reward-row">
                            <div class="reward-chip">⭐ +<?= $g['xp'] ?> XP</div>
                            <div class="reward-chip">🪙 +<?= $g['coin'] ?> Coin</div>
                        </div>
                        <?php if (!$unlocked): ?>
                        <div class="lock-tag">🔒 Mở khoá ở cấp độ <?= $needLevel ?> (bạn đang ở cấp
                            <?= $currentLevel ?>)</div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php require __DIR__ . '/partials/site-footer.php'; ?>