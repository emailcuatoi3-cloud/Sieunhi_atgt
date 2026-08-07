<?php
/**
 * Master layout — đóng </head>, mở <body>, navbar chung + crumb-bar tuỳ chọn.
 * Require SAU site-head.php (và sau khối <style> riêng của trang nếu có).
 */
$user = $user ?? currentUser();
$navItems = [
    'trang-chu'    => ['index.php', 'Trang chủ'],
    'ai-gia-su'    => ['ai-gia-su.php', 'AI Gia sư'],
    'kham-pha'     => ['kham-pha.php', '🗺️ Khám phá'],
    'lich-trinh'   => ['lich-trinh-ai.php', '🗓️ Lịch trình AI'],
    'ai-camera'    => ['ai-camera.php', 'AI Camera'],
    'mo-phong'     => ['ai-mo-phong.php', 'Mô phỏng'],
    'truyen-tranh' => ['ai-truyen-tranh.php', 'Truyện tranh'],
    'thu-thach'    => ['game-mini.php', 'Thử thách'],
];
$roleLabels = ['hocsinh' => '🧒 Học sinh', 'phuhuynh' => '👨‍👩‍👧 Phụ huynh', 'giaovien' => '🧑‍🏫 Giáo viên', 'admin' => '🛡️ Quản trị'];
$activeNav = $PAGE['nav'] ?? '';
$dashboardHref = $user ? (ROLE_DASHBOARDS[$user['role']] ?? 'index.php') : null;
?>
</head>

<body class="<?= e($PAGE['body_class'] ?? '') ?>">
    <nav class="navbar<?= empty($PAGE['hero_nav']) ? ' static' : '' ?>" id="navbar" aria-label="Điều hướng chính">
        <div class="nav-inner">
            <a href="index.php" class="logo">
                <img src="assets/images/sieu-nhi-logo.png" alt="SIÊU NHÍ AI" class="site-logo-img" />
            </a>

            <ul class="nav-menu">
                <?php foreach ($navItems as $key => [$href, $label]): ?>
                <li><a href="<?= $href ?>"<?= $key === $activeNav ? ' class="active" aria-current="page"' : '' ?>><?= $label ?></a></li>
                <?php endforeach; ?>
                <?php if ($user && isset($roleLabels[$user['role']])): ?>
                <li><a href="<?= e($dashboardHref) ?>"<?= $activeNav === 'dashboard' ? ' class="active" aria-current="page"' : '' ?>><?= $roleLabels[$user['role']] ?></a></li>
                <?php endif; ?>
            </ul>

            <div class="nav-actions">
                <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
                <?php if ($user): ?>
                <a href="<?= e($dashboardHref) ?>" class="btn btn-ghost desktop-only"><?= e($user['avatar']) ?> <?= e($user['name']) ?></a>
                <a href="logout.php" class="btn btn-primary-sm">Đăng xuất</a>
                <?php else: ?>
                <a href="dang-nhap.php" class="btn btn-ghost desktop-only">Đăng nhập</a>
                <a href="dang-ky.php" class="btn btn-primary-sm">Đăng ký</a>
                <?php endif; ?>
                <button class="icon-btn nav-burger" id="navBurger" aria-label="Mở menu" aria-expanded="false" aria-controls="mobileMenu">☰</button>
            </div>
        </div>

        <div class="mobile-menu" id="mobileMenu" hidden>
            <?php foreach ($navItems as $key => [$href, $label]): ?>
            <a href="<?= $href ?>"<?= $key === $activeNav ? ' class="active" aria-current="page"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
            <?php if ($user && isset($roleLabels[$user['role']])): ?>
            <a href="<?= e($dashboardHref) ?>"<?= $activeNav === 'dashboard' ? ' class="active"' : '' ?>><?= $roleLabels[$user['role']] ?></a>
            <?php endif; ?>
        </div>
    </nav>

    <?php if (!empty($PAGE['crumb'])): ?>
    <div class="crumb-bar">
        <a class="kid-btn kid-btn--sky" href="<?= e($PAGE['crumb']['href']) ?>"><?= e($PAGE['crumb']['label']) ?></a>
        <?php if (!empty($PAGE['crumb']['title'])): ?>
        <span class="crumb-title"><?= e($PAGE['crumb']['title']) ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
