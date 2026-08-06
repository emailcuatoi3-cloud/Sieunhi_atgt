<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/places-repo.php';
$user = currentUser();

$slotOptions = [
    ['value' => 'sang',      'emoji' => '🌅', 'label' => 'Buổi sáng'],
    ['value' => 'ca-ngay',   'emoji' => '☀️', 'label' => 'Cả ngày'],
    ['value' => 'cuoi-tuan', 'emoji' => '🎉', 'label' => 'Cuối tuần'],
];
$vehicleOptions = [
    ['value' => 'di-bo',     'emoji' => '🚶', 'label' => 'Đi bộ'],
    ['value' => 'xe-dap',    'emoji' => '🚲', 'label' => 'Xe đạp'],
    ['value' => 'bo-me-cho', 'emoji' => '🛵', 'label' => 'Bố mẹ chở'],
];
$typeEmoji = ['bao-tang' => '🏛️', 'cong-vien' => '🌳', 'vui-choi' => '🎡', 'thien-nhien' => '🏞️'];
$typeOptions = ['bao-tang', 'cong-vien', 'vui-choi', 'thien-nhien'];
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
    <title>Lịch trình AI · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="stylesheet" href="assets/css/fonts.css?v=1">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=16">
    <link rel="stylesheet" href="assets/css/kid-components.css?v=1">
    <style>
    body.lich-trinh-page { background: var(--kid-cream); }

    .lt-top { display: flex; align-items: center; gap: 14px; padding: 14px 24px; background: #fff;
      border-bottom: 3px solid var(--glass-border); flex-wrap: wrap; }
    .lt-top span { font-family: "Baloo 2", sans-serif; font-size: 18px; margin: 0; color: var(--kid-ink); }

    .lt-hero { padding: 22px 24px 6px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .lt-hero-mascot { width: 76px; height: 76px; flex: 0 0 auto; }
    .lt-hero h1 { font-family: "Baloo 2", sans-serif; font-size: 26px; margin: 0 0 6px; color: var(--kid-ink); }
    .lt-hero p { margin: 0; color: var(--kid-ink-soft); font-size: 15px; max-width: 560px; }

    .lt-wizard { max-width: 720px; margin: 0 auto; padding: 18px 24px; display: flex; flex-direction: column; gap: 18px; }
    .lt-step h2 { font-family: "Baloo 2", sans-serif; font-size: 19px; margin: 0 0 14px; color: var(--kid-ink); }
    .lt-sticker-row { display: flex; flex-wrap: wrap; gap: 12px; }
    .lt-sticker { display: flex; flex-direction: column; align-items: center; gap: 6px; min-width: 108px;
      padding: 16px 14px; border-radius: var(--radius-lg); border: 3px solid var(--glass-border);
      background: #fff; cursor: pointer; font-family: "Baloo 2", sans-serif; font-weight: 700; font-size: 14px;
      color: var(--kid-ink); transition: transform .12s ease, border-color .12s ease, background .12s ease; }
    .lt-sticker .lt-sticker-emoji { font-size: 30px; line-height: 1; }
    .lt-sticker:hover { transform: translateY(-3px); }
    .lt-sticker.active, .lt-sticker[aria-pressed="true"] { border-color: var(--kid-sky); background: #EAF7FC; }

    #btn-go { display: block; margin: 6px auto 0; }

    .lt-result { max-width: 720px; margin: 0 auto; padding: 6px 24px 40px; }
    .ticket { border-color: var(--kid-yellow); border-width: 4px; position: relative; }
    .ticket-head { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
    .ticket-mascot { width: 56px; height: 56px; flex: 0 0 auto; }
    .ticket-head h3 { font-family: "Baloo 2", sans-serif; font-size: 20px; margin: 0; color: var(--kid-ink); }

    .leg-row { display: flex; gap: 14px; padding: 12px 0; border-top: 2px dashed var(--glass-border); }
    .leg-row:first-of-type { border-top: none; }
    .leg-time { flex: 0 0 auto; display: flex; flex-direction: column; align-items: center; width: 58px; }
    .leg-dot { width: 14px; height: 14px; border-radius: 50%; background: var(--kid-sky); margin-bottom: 6px; }
    .leg-time span { font-weight: 800; font-size: 13px; color: var(--kid-sky-deep); }
    .leg-body { flex: 1; min-width: 0; }
    .leg-body a { font-family: "Baloo 2", sans-serif; font-size: 17px; color: var(--kid-ink); text-decoration: none; }
    .leg-body a:hover { text-decoration: underline; }
    .leg-activity { margin: 4px 0 8px; color: var(--kid-ink-soft); font-size: 14px; }
    .leg-safety { display: block; }

    .ticket-actions { display: flex; align-items: center; gap: 12px; margin-top: 18px; flex-wrap: wrap; }

    @media (max-width: 480px) {
      .lt-top, .lt-hero, .lt-wizard, .lt-result { padding-left: 14px; padding-right: 14px; }
      .lt-hero-mascot { width: 60px; height: 60px; }
      .lt-hero h1 { font-size: 21px; }
      .lt-sticker { min-width: 90px; }
    }

    @media print {
      .lt-top, .lt-hero, .lt-wizard, .ticket-actions { display: none !important; }
      body { background: #fff !important; }
    }
    </style>
</head>

<body class="lich-trinh-page">
<header class="lt-top">
    <a class="kid-btn kid-btn--sky" href="index.php">← Trang chủ</a>
    <span>🗓️ Lịch trình AI</span>
</header>

<main>
    <section class="lt-hero">
        <div id="lt-hero-mascot" class="lt-hero-mascot"></div>
        <div>
            <h1>🗓️ Lịch trình AI</h1>
            <p>Trả lời 3 câu hỏi nhỏ, Siêu Nhí sẽ lên vé hành trình đi chơi an toàn cho con!</p>
        </div>
    </section>

    <form class="lt-wizard" id="lt-form">
        <div class="kid-card lt-step">
            <h2>1️⃣ Đi khi nào?</h2>
            <div class="lt-sticker-row" id="lt-step-slot" data-name="time_slot">
                <?php foreach ($slotOptions as $i => $o): ?>
                <button type="button" class="lt-sticker<?= $i === 0 ? ' active' : '' ?>" data-value="<?= e($o['value']) ?>"
                        aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
                    <span class="lt-sticker-emoji"><?= $o['emoji'] ?></span><?= e($o['label']) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="kid-card lt-step">
            <h2>2️⃣ Đi bằng gì?</h2>
            <div class="lt-sticker-row" id="lt-step-vehicle" data-name="vehicle">
                <?php foreach ($vehicleOptions as $i => $o): ?>
                <button type="button" class="lt-sticker<?= $i === 0 ? ' active' : '' ?>" data-value="<?= e($o['value']) ?>"
                        aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
                    <span class="lt-sticker-emoji"><?= $o['emoji'] ?></span><?= e($o['label']) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="kid-card lt-step">
            <h2>3️⃣ Thích gì?</h2>
            <div class="lt-sticker-row" id="lt-step-types" data-name="types">
                <?php foreach ($typeOptions as $t): ?>
                <button type="button" class="lt-sticker" data-value="<?= e($t) ?>" aria-pressed="false">
                    <span class="lt-sticker-emoji"><?= $typeEmoji[$t] ?></span><?= e(place_type_label($t)) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="button" id="btn-go" class="kid-btn kid-btn--green">Lên lịch thôi! 🚀</button>
    </form>

    <section class="lt-result" id="result"></section>
</main>

<script src="assets/js/mascot.js?v=2"></script>
<script>
(function () {
    var CSRF = document.querySelector('meta[name="csrf"]').content;

    var heroMascot = document.getElementById('lt-hero-mascot');
    if (heroMascot && window.MascotSVG) heroMascot.innerHTML = MascotSVG.pose('wave');

    function toggleSingle(row) {
        row.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.lt-sticker');
            if (!btn || !row.contains(btn)) return;
            row.querySelectorAll('.lt-sticker').forEach(function (b) {
                b.classList.remove('active');
                b.setAttribute('aria-pressed', 'false');
            });
            btn.classList.add('active');
            btn.setAttribute('aria-pressed', 'true');
        });
    }

    function toggleMulti(row) {
        row.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.lt-sticker');
            if (!btn || !row.contains(btn)) return;
            var active = btn.classList.toggle('active');
            btn.setAttribute('aria-pressed', String(active));
        });
    }

    var slotRow = document.getElementById('lt-step-slot');
    var vehicleRow = document.getElementById('lt-step-vehicle');
    var typesRow = document.getElementById('lt-step-types');
    toggleSingle(slotRow);
    toggleSingle(vehicleRow);
    toggleMulti(typesRow);

    /* Mặc định chọn sẵn loại địa điểm yêu thích của bé (nếu đã đăng nhập có sở thích) */
    fetch('preferences.php', { headers: { 'X-CSRF-Token': CSRF } })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data || data.guest || !data.prefs) return;
            var favTypes = data.prefs.fav_place_types || [];
            favTypes.forEach(function (t) {
                var btn = typesRow.querySelector('.lt-sticker[data-value="' + t + '"]');
                if (btn) { btn.classList.add('active'); btn.setAttribute('aria-pressed', 'true'); }
            });
        })
        .catch(function () { /* khách / lỗi mạng — bỏ qua, giữ mặc định trống */ });

    function selectedValue(row) {
        var btn = row.querySelector('.lt-sticker.active');
        return btn ? btn.getAttribute('data-value') : '';
    }
    function selectedValues(row) {
        return Array.prototype.map.call(row.querySelectorAll('.lt-sticker.active'), function (b) {
            return b.getAttribute('data-value');
        });
    }

    function engineBadge(engine) {
        if (engine === 'gemini') return '<span class="kid-badge kid-badge--green">● AI thật</span>';
        return '<span class="kid-badge kid-badge--yellow">● Chế độ offline</span>';
    }

    function renderTicket(data) {
        var result = document.getElementById('result');
        result.innerHTML = '';

        var ticket = document.createElement('div');
        ticket.className = 'kid-card ticket';

        var head = document.createElement('div');
        head.className = 'ticket-head';
        var mascotBox = document.createElement('div');
        mascotBox.className = 'ticket-mascot';
        if (window.MascotSVG) mascotBox.innerHTML = MascotSVG.pose('cheer');
        var title = document.createElement('h3');
        title.textContent = 'Vé hành trình của con 🎫';
        head.appendChild(mascotBox);
        head.appendChild(title);
        ticket.appendChild(head);

        var badgeWrap = document.createElement('div');
        badgeWrap.innerHTML = engineBadge(data.engine);
        ticket.appendChild(badgeWrap.firstChild);

        (data.legs || []).forEach(function (leg) {
            var row = document.createElement('div');
            row.className = 'leg-row';

            var timeCol = document.createElement('div');
            timeCol.className = 'leg-time';
            var dot = document.createElement('span');
            dot.className = 'leg-dot';
            var timeText = document.createElement('span');
            timeText.textContent = leg.time || '';
            timeCol.appendChild(dot);
            timeCol.appendChild(timeText);

            var body = document.createElement('div');
            body.className = 'leg-body';
            var link = document.createElement('a');
            link.href = 'dia-diem.php?slug=' + encodeURIComponent(leg.slug || '');
            link.textContent = leg.name || '';
            var activity = document.createElement('p');
            activity.className = 'leg-activity';
            activity.textContent = leg.activity || '';
            var safety = document.createElement('span');
            safety.className = 'kid-badge kid-badge--yellow leg-safety';
            safety.textContent = '⚠️ ' + (leg.safety_tip || '');

            body.appendChild(link);
            body.appendChild(activity);
            body.appendChild(safety);

            row.appendChild(timeCol);
            row.appendChild(body);
            ticket.appendChild(row);
        });

        if (!data.legs || data.legs.length === 0) {
            var empty = document.createElement('p');
            empty.style.color = 'var(--kid-ink-soft)';
            empty.textContent = 'Chưa tìm được điểm phù hợp, con thử chọn lại nhé! 🌟';
            ticket.appendChild(empty);
        }

        var actions = document.createElement('div');
        actions.className = 'ticket-actions';
        var printBtn = document.createElement('button');
        printBtn.type = 'button';
        printBtn.className = 'kid-btn kid-btn--sky';
        printBtn.textContent = '🖨️ In vé';
        printBtn.addEventListener('click', function () { window.print(); });
        actions.appendChild(printBtn);
        ticket.appendChild(actions);

        result.appendChild(ticket);
    }

    document.getElementById('btn-go').addEventListener('click', function () {
        var btnGo = document.getElementById('btn-go');
        if (btnGo.disabled) return;
        btnGo.disabled = true;

        var fd = new FormData();
        fd.append('time_slot', selectedValue(slotRow));
        fd.append('vehicle', selectedValue(vehicleRow));
        selectedValues(typesRow).forEach(function (t) { fd.append('types[]', t); });

        fetch('lich-trinh-api.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-Token': CSRF }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.status === 'success') renderTicket(data);
            })
            .catch(function () {
                var result = document.getElementById('result');
                result.innerHTML = '<p class="kid-card" style="color:var(--kid-red);">Có lỗi mạng, con thử lại nhé! 😢</p>';
            })
            .finally(function () {
                btnGo.disabled = false;
            });
    });
})();
</script>
</body>
</html>
