<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/places-repo.php';
require_once __DIR__ . '/lib/svg-lib.php';
$p = place_by_slug((string)($_GET['slug'] ?? ''));
$user = currentUser();

if ($p === null) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
    (function() {
        try {
            document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme") || "light");
        } catch (e) {}
    })();
    </script>
    <title>Không tìm thấy địa điểm · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="stylesheet" href="assets/css/fonts.css?v=1">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=16">
    <link rel="stylesheet" href="assets/css/kid-components.css?v=1">
    <style>
    body.dd-404-page { background: var(--kid-cream); min-height: 100dvh; display: flex;
      align-items: center; justify-content: center; padding: 24px; }
    .dd-404-box { max-width: 420px; text-align: center; }
    .dd-404-mascot { width: 120px; height: 120px; margin: 0 auto 14px; }
    .dd-404-box h1 { font-family: "Baloo 2", sans-serif; font-size: 22px; margin: 0 0 10px; color: var(--kid-ink); }
    .dd-404-box p { margin: 0 0 20px; color: var(--kid-ink-soft); font-size: 15px; }
    </style>
</head>

<body class="dd-404-page">
    <div class="kid-card dd-404-box">
        <div id="dd-404-mascot" class="dd-404-mascot"></div>
        <h1>Ơ, không thấy nơi này! 🧭</h1>
        <p>Con thử tìm lại trên bản đồ Khám phá nhé, chắc chắn sẽ có nhiều nơi thú vị đang chờ!</p>
        <a class="kid-btn kid-btn--sky" href="kham-pha.php">← Về trang Khám phá</a>
    </div>
    <script src="assets/js/mascot.js?v=2"></script>
    <script>
        var m = document.getElementById('dd-404-mascot');
        if (m && window.MascotSVG) { m.innerHTML = MascotSVG.pose('worry'); }
    </script>
</body>

</html>
<?php
    exit;
}

$reviews = place_reviews_approved((int)$p['id']);
$typeEmoji = ['bao-tang' => '🏛️', 'cong-vien' => '🌳', 'vui-choi' => '🎡', 'thien-nhien' => '🏞️'];
$smileys = ['☹️' => 1, '🙁' => 2, '😐' => 3, '🙂' => 4, '😍' => 5];
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
    <title><?= e($p['name']) ?> · Khám phá Buôn Ma Thuột</title>
    <link rel="stylesheet" href="assets/css/fonts.css?v=1">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=16">
    <link rel="stylesheet" href="assets/css/kid-components.css?v=1">
    <style>
    body.dd-page { background: var(--kid-cream); }

    .dd-top { display: flex; align-items: center; gap: 14px; padding: 14px 24px; background: #fff;
      border-bottom: 3px solid var(--glass-border); flex-wrap: wrap; }
    .dd-wrap { max-width: 780px; margin: 0 auto; padding: 22px 20px 60px; display: flex;
      flex-direction: column; gap: 20px; }

    .dd-head h1 { font-family: "Baloo 2", sans-serif; font-size: 26px; margin: 10px 0 8px; color: var(--kid-ink); }

    .dd-hero-art img { display: block; width: 100%; max-width: 420px; margin: 0 auto; border-radius: 18px; }

    .dd-card h2 { font-family: "Baloo 2", sans-serif; font-size: 19px; margin: 0 0 10px; color: var(--kid-ink); }
    .dd-card p { line-height: 1.65; color: var(--kid-ink); margin: 0; }

    .dd-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; }
    .dd-info-cell { background: #fff; border: 3px solid var(--glass-border); border-radius: var(--radius-lg);
      padding: 14px; text-align: center; }
    .dd-info-cell .dd-info-label { display: block; font-size: 12.5px; color: var(--kid-ink-soft); margin-bottom: 4px; }
    .dd-info-cell .dd-info-value { display: block; font-family: "Baloo 2", sans-serif; font-size: 16px; color: var(--kid-ink); }

    .dd-safety { border-color: var(--kid-yellow); }
    .dd-safety img { display: block; max-width: 200px; margin: 14px auto 0; border-radius: 14px; }

    .dd-review { display: flex; flex-direction: column; gap: 8px; }
    .dd-review-head { display: flex; align-items: center; gap: 10px; }
    .dd-review-avatar { font-size: 24px; }
    .dd-review-name { font-weight: 800; color: var(--kid-ink); font-family: "Baloo 2", sans-serif; }
    .dd-review-stars { color: var(--kid-yellow); }
    .dd-review-photos { display: flex; flex-wrap: wrap; gap: 8px; }
    .dd-review-photos img { width: 90px; height: 90px; object-fit: cover; border-radius: 12px; }

    .dd-smiley-row { display: flex; gap: 10px; flex-wrap: wrap; }
    .dd-smiley-btn { font-size: 26px; background: #fff; border: 3px solid var(--glass-border);
      border-radius: 16px; padding: 8px 14px; cursor: pointer; }
    .dd-smiley-btn[aria-pressed="true"] { border-color: var(--kid-yellow); background: #FFF3D6; }
    .dd-form-row { display: flex; flex-direction: column; gap: 12px; }

    .dd-review-error { display: flex; align-items: center; gap: 8px; color: var(--kid-red);
      font-size: 14px; font-weight: 700; }
    .dd-review-error[hidden] { display: none; }
    .dd-review-error-mascot { width: 32px; height: 32px; flex: 0 0 auto; }
    .dd-review-success { text-align: center; padding: 6px 0; }
    .dd-review-success-mascot { width: 90px; height: 90px; margin: 0 auto 10px; }
    .dd-review-success p { color: var(--kid-ink); font-size: 15px; margin: 0; }

    @media (max-width: 480px) {
      .dd-top, .dd-wrap { padding-left: 14px; padding-right: 14px; }
      .dd-head h1 { font-size: 21px; }
    }
    </style>
</head>

<body class="dd-page">
<header class="dd-top">
    <a class="kid-btn kid-btn--sky" href="kham-pha.php">← Khám phá</a>
</header>

<main class="dd-wrap">
    <!-- 1. Breadcrumb + tiêu đề + badge loại -->
    <div class="dd-head">
        <h1><?= e($p['name']) ?></h1>
        <span class="kid-badge"><?= $typeEmoji[$p['type']] ?? '📍' ?> <?= e(place_type_label($p['type'])) ?></span>
    </div>

    <!-- 2. Hình minh hoạ lớn -->
    <div class="kid-card dd-hero-art">
        <img src="art.php?code=<?= e($p['art_code']) ?>" alt="<?= e($p['name']) ?>" width="420" height="294" loading="eager" fetchpriority="high">
    </div>

    <!-- 3. Chuyện kể -->
    <div class="kid-card dd-card">
        <h2>📖 Chuyện kể</h2>
        <p><?= nl2br(e($p['story'])) ?></p>
    </div>

    <!-- 4. Lưới 4 ô thông tin -->
    <div class="dd-info-grid">
        <div class="dd-info-cell">
            <span class="dd-info-label">🕐 Giờ mở cửa</span>
            <span class="dd-info-value"><?= e($p['open_hours']) ?></span>
        </div>
        <div class="dd-info-cell">
            <span class="dd-info-label">🎟️ Vé vào cửa</span>
            <span class="dd-info-value"><?= e($p['ticket']) ?></span>
        </div>
        <div class="dd-info-cell">
            <span class="dd-info-label">📍 Khoảng cách</span>
            <span class="dd-info-value"><?= e((string)$p['distance_km']) ?> km từ trường</span>
        </div>
        <div class="dd-info-cell">
            <span class="dd-info-label">🧒 Độ tuổi</span>
            <span class="dd-info-value"><?= e($p['age_note']) ?></span>
        </div>
    </div>

    <!-- 5. Đường đến an toàn -->
    <div class="kid-card dd-card dd-safety">
        <h2>🚸 Đường đến an toàn</h2>
        <p><?= nl2br(e($p['safety_note'])) ?></p>
        <img src="art.php?code=qua-duong" alt="Qua đường an toàn" width="200" height="140" loading="lazy">
    </div>

    <!-- 6. Review đã duyệt -->
    <div class="dd-card">
        <h2>💬 Bạn nhỏ đã kể gì?</h2>
        <?php if (!$reviews): ?>
        <p class="kid-card" style="text-align:center; color:var(--kid-ink-soft);">Chưa có bạn nào kể về nơi này. Con đi rồi kể đầu tiên nha! 🌟</p>
        <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:14px; margin-top:12px;">
            <?php foreach ($reviews as $r): ?>
            <div class="kid-card dd-review">
                <div class="dd-review-head">
                    <span class="dd-review-avatar"><?= e($r['avatar_emoji']) ?></span>
                    <span class="dd-review-name"><?= e($r['name']) ?></span>
                    <span class="dd-review-stars"><?= str_repeat('⭐', max(0, min(5, (int)$r['stars']))) ?></span>
                </div>
                <p><?= nl2br(e($r['content'])) ?></p>
                <?php
                $photos = [];
                if (!empty($r['photos'])) {
                    $decoded = json_decode((string)$r['photos'], true);
                    if (is_array($decoded)) $photos = $decoded;
                }
                ?>
                <?php if ($photos): ?>
                <div class="dd-review-photos">
                    <?php foreach ($photos as $f): ?>
                    <?php
                    if (!is_string($f) || $f === '') continue;
                    if (strpos($f, '..') !== false || strpos($f, '/') !== false || strpos($f, '\\') !== false) continue;
                    $f = basename($f);
                    if ($f === '' || $f === '.' || $f === '..' || $f[0] === '.') continue;
                    ?>
                    <img src="uploads/reviews/<?= e($f) ?>" alt="Ảnh của <?= e($r['name']) ?>" loading="lazy">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 7. Form gửi review -->
    <?php if ($user): ?>
    <div class="kid-card dd-card" id="review-card">
        <h2>✍️ Kể chuyện của con</h2>
        <form id="review-form" class="dd-form-row">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="place_id" value="<?= (int)$p['id'] ?>">
            <div class="dd-smiley-row" role="group" aria-label="Chọn số sao">
                <?php foreach ($smileys as $face => $stars): ?>
                <button type="button" class="dd-smiley-btn" data-stars="<?= $stars ?>" aria-pressed="false"><?= $face ?></button>
                <?php endforeach; ?>
            </div>
            <textarea class="kid-input" name="content" rows="3" maxlength="500" placeholder="Con thấy nơi này thế nào?"></textarea>
            <input class="kid-input" type="file" name="photos[]" accept="image/*" multiple>
            <button class="kid-btn kid-btn--green" type="submit">Gửi cho Siêu Nhí 🚀</button>
            <div id="review-error" class="dd-review-error" role="alert" hidden>
                <span id="review-error-mascot" class="dd-review-error-mascot"></span>
                <span id="review-error-text"></span>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="kid-card dd-card" style="text-align:center;">
        <h2>✍️ Kể chuyện của con</h2>
        <p style="margin-bottom:14px;">Đăng nhập để kể lại chuyến đi và chia sẻ ảnh cho các bạn nhỏ khác nhé!</p>
        <a class="kid-btn kid-btn--sky" href="dang-nhap.php?next=<?= urlencode('dia-diem.php?slug=' . $p['slug']) ?>">Đăng nhập ngay</a>
    </div>
    <?php endif; ?>
</main>

<script src="assets/js/mascot.js?v=2"></script>
<script>
(function () {
    var form = document.getElementById('review-form');
    if (!form) return;

    var CSRF = document.querySelector('meta[name="csrf"]').content;
    var smileyBtns = form.querySelectorAll('.dd-smiley-btn');
    var selectedStars = null;

    smileyBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            selectedStars = btn.getAttribute('data-stars');
            smileyBtns.forEach(function (b) { b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'); });
        });
    });

    var errorBox = document.getElementById('review-error');
    var errorMascot = document.getElementById('review-error-mascot');
    var errorText = document.getElementById('review-error-text');

    function showError(message) {
        errorText.textContent = message;
        if (window.MascotSVG) errorMascot.innerHTML = MascotSVG.pose('worry');
        errorBox.hidden = false;
    }

    function showSuccess(message) {
        var card = document.getElementById('review-card');
        card.innerHTML = '';
        var wrap = document.createElement('div');
        wrap.className = 'dd-review-success';
        var mascotBox = document.createElement('div');
        mascotBox.className = 'dd-review-success-mascot';
        if (window.MascotSVG) mascotBox.innerHTML = MascotSVG.pose('cheer');
        var text = document.createElement('p');
        text.textContent = message;
        wrap.appendChild(mascotBox);
        wrap.appendChild(text);
        card.appendChild(wrap);
    }

    form.addEventListener('submit', function (evt) {
        evt.preventDefault();
        errorBox.hidden = true;

        if (!selectedStars) {
            showError('Con hãy chọn mức mặt cười nhé!');
            return;
        }

        var fd = new FormData(form);
        fd.append('stars', selectedStars);

        var submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        fetch('review-submit.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-Token': CSRF }
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (result.ok && result.data && result.data.status === 'success') {
                    showSuccess(result.data.message || 'Cảm ơn con! Bài kể đang chờ cô duyệt 🕐');
                } else {
                    submitBtn.disabled = false;
                    showError((result.data && result.data.message) || 'Có lỗi xảy ra, con thử lại nhé! 😢');
                }
            })
            .catch(function () {
                submitBtn.disabled = false;
                showError('Có lỗi mạng, con thử lại nhé! 😢');
            });
    });
})();
</script>
</body>

</html>
