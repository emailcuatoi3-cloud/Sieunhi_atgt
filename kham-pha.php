<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/places-repo.php';
require_once __DIR__ . '/lib/svg-lib.php';
$user = currentUser();
$type = $_GET['type'] ?? null;
if (!in_array($type, ['bao-tang', 'cong-vien', 'vui-choi', 'thien-nhien'], true)) $type = null;
$places = places_all($type);
$allPlaces = $type === null ? $places : places_all();

$typeEmoji = ['bao-tang' => '🏛️', 'cong-vien' => '🌳', 'vui-choi' => '🎡', 'thien-nhien' => '🏞️'];
$typeFilters = ['bao-tang', 'cong-vien', 'vui-choi', 'thien-nhien'];
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
    <title>Khám phá Buôn Ma Thuột · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="stylesheet" href="assets/css/fonts.css?v=1">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=16">
    <link rel="stylesheet" href="assets/css/kid-components.css?v=1">
    <style>
    body.kham-pha-page { background: var(--kid-cream); }

    .kp-top { display: flex; align-items: center; gap: 14px; padding: 14px 24px; background: #fff;
      border-bottom: 3px solid var(--glass-border); flex-wrap: wrap; }
    .kp-top span { font-family: "Baloo 2", sans-serif; font-size: 18px; margin: 0; color: var(--kid-ink); }

    .kp-hero { padding: 22px 24px 6px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .kp-hero-mascot { width: 76px; height: 76px; flex: 0 0 auto; }
    .kp-hero h1 { font-family: "Baloo 2", sans-serif; font-size: 26px; margin: 0 0 6px; color: var(--kid-ink); }
    .kp-hero p { margin: 0; color: var(--kid-ink-soft); font-size: 15px; max-width: 560px; }

    .kp-section { padding: 18px 24px; }

    .map-wrap { position: relative; max-width: 720px; margin: 0 auto; }
    .map-wrap svg { display: block; width: 100%; height: auto; border-radius: var(--radius-lg); }
    .map-pin { position: absolute; transform: translate(-50%, -50%); font-size: 26px;
      transition: transform .15s ease; text-decoration: none; line-height: 1; }
    .map-pin:hover, .map-pin:focus-visible { transform: translate(-50%, -50%) scale(1.35); }
    @media (prefers-reduced-motion: reduce) {
      .map-pin { transition: none; }
    }

    .kp-filters { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }

    .kp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px;
      max-width: 1080px; margin: 0 auto; }
    .kp-card img { display: block; width: 100%; border-radius: 14px; margin-bottom: 10px; }
    .kp-card h3 { font-family: "Baloo 2", sans-serif; font-size: 18px; margin: 0 0 8px; color: var(--kid-ink); }
    .kp-card .kp-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }

    @media (max-width: 480px) {
      .kp-top, .kp-hero, .kp-section { padding-left: 14px; padding-right: 14px; }
      .kp-hero-mascot { width: 60px; height: 60px; }
      .kp-hero h1 { font-size: 21px; }
    }
    </style>
</head>

<body class="kham-pha-page">
<header class="kp-top">
    <a class="kid-btn kid-btn--sky" href="index.php">← Trang chủ</a>
    <span>🗺️ Khám phá Buôn Ma Thuột</span>
</header>

<main>
    <section class="kp-hero">
        <div id="kp-hero-mascot" class="kp-hero-mascot"></div>
        <div>
            <h1>🗺️ Khám phá Buôn Ma Thuột</h1>
            <p>Cùng Siêu Nhí đi chơi khắp thành phố — an toàn trên từng con đường!</p>
        </div>
    </section>

    <section class="kp-section">
        <div class="map-wrap">
            <?= svg_art('map-bmt') ?>
            <?php foreach ($allPlaces as $p): ?>
            <a class="map-pin" href="dia-diem.php?slug=<?= e($p['slug']) ?>"
               style="left:<?= (int)$p['map_x'] ?>%; top:<?= (int)$p['map_y'] ?>%"
               title="<?= e($p['name']) ?>"><?= $typeEmoji[$p['type']] ?? '📍' ?></a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="kp-section">
        <nav class="kp-filters" aria-label="Lọc theo loại địa điểm">
            <a class="kid-chip<?= $type === null ? ' active' : '' ?>" href="kham-pha.php"<?= $type === null ? ' aria-pressed="true"' : '' ?>>✨ Tất cả</a>
            <?php foreach ($typeFilters as $tf): ?>
            <a class="kid-chip<?= $type === $tf ? ' active' : '' ?>" href="kham-pha.php?type=<?= e($tf) ?>"<?= $type === $tf ? ' aria-pressed="true"' : '' ?>>
                <?= $typeEmoji[$tf] ?> <?= e(place_type_label($tf)) ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </section>

    <section class="kp-section">
        <div class="kp-grid">
            <?php foreach ($places as $p): ?>
            <div class="kid-card sticker-tilt kp-card">
                <img src="art.php?code=<?= e($p['art_code']) ?>" alt="<?= e($p['name']) ?>" width="220" height="154" loading="lazy">
                <h3><?= e($p['name']) ?></h3>
                <div class="kp-badges">
                    <span class="kid-badge"><?= $typeEmoji[$p['type']] ?? '📍' ?> <?= e(place_type_label($p['type'])) ?></span>
                    <span class="kid-badge">📍 <?= e((string)$p['distance_km']) ?>km</span>
                    <span class="kid-badge kid-badge--yellow">🧒 <?= e($p['age_note']) ?></span>
                </div>
                <a class="kid-btn kid-btn--sky" href="dia-diem.php?slug=<?= e($p['slug']) ?>">Đến xem! →</a>
            </div>
            <?php endforeach; ?>
            <?php if (!$places): ?>
            <p style="grid-column:1/-1; text-align:center; color:var(--kid-ink-soft);">Chưa có địa điểm nào ở loại này. Con thử loại khác nhé! 🌟</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="kp-section">
        <div class="kid-card" style="max-width:720px; margin:0 auto; text-align:center;">
            <h3 style="margin:0 0 8px;">🗓️ Đã chọn được điểm đến chưa?</h3>
            <p style="margin:0 0 14px; color:var(--kid-ink-soft);">Để Siêu Nhí lên lịch trình đi chơi an toàn cho con!</p>
            <a class="kid-btn kid-btn--green" href="lich-trinh-ai.php">Lên lịch trình AI 🚀</a>
        </div>
    </section>
</main>

<script src="assets/js/mascot.js?v=2"></script>
<script>
    var kpMascot = document.getElementById('kp-hero-mascot');
    if (kpMascot && window.MascotSVG) {
        kpMascot.innerHTML = MascotSVG.pose('point');
    }
</script>
</body>
</html>
