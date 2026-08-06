<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/moderation.php';

/** @var DB_UTILS $db biến $db được dashboard gọi partial này cung cấp sẵn */
$pendingReviews = [];
$reviewLoadError = false;
try {
    $pendingReviews = pending_reviews($db);
} catch (Throwable $ignored) {
    $reviewLoadError = true;
}
?>
<div class="kid-card" style="margin-bottom:16px;">
    <h2 style="margin:0 0 6px;">📝 Duyệt review từ học sinh</h2>
    <p style="margin:0; color:var(--kid-ink-soft, #6b7280);">Xem lại các bài kể chuyện đi chơi của học sinh trước khi hiển thị công khai. Duyệt sẽ tự động cộng 20 XP cho bạn nhỏ đã viết.</p>
</div>

<div id="review-moderation-list" style="display:flex; flex-direction:column; gap:14px;">
    <?php if ($reviewLoadError): ?>
    <p class="kid-card" id="review-moderation-empty" style="text-align:center; color:var(--kid-ink-soft, #6b7280);">Chưa lấy được hàng chờ duyệt (dữ liệu Khám phá có thể chưa được cài đặt). Thử lại sau nhé! 🔧</p>
    <?php elseif (!$pendingReviews): ?>
    <p class="kid-card" id="review-moderation-empty" style="text-align:center; color:var(--kid-ink-soft, #6b7280);">Không có review nào đang chờ duyệt. Tuyệt vời! 🎉</p>
    <?php else: ?>
        <?php foreach ($pendingReviews as $r): ?>
        <div class="kid-card review-mod-card" data-review-id="<?= (int)$r['id'] ?>">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                <div>
                    <b><?= e($r['place_name']) ?></b>
                    <span style="color:var(--kid-ink-soft, #6b7280);"> · kể bởi <?= e($r['author']) ?></span>
                </div>
                <span><?= str_repeat('⭐', max(0, min(5, (int)$r['stars']))) ?></span>
            </div>
            <p style="margin:10px 0;"><?= nl2br(e($r['content'])) ?></p>
            <?php
            $photos = [];
            if (!empty($r['photos'])) {
                $decoded = json_decode((string)$r['photos'], true);
                if (is_array($decoded)) $photos = $decoded;
            }
            ?>
            <?php if ($photos): ?>
            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
                <?php foreach ($photos as $f): ?>
                <?php
                if (!is_string($f) || $f === '') continue;
                if (strpos($f, '..') !== false || strpos($f, '/') !== false || strpos($f, '\\') !== false) continue;
                $f = basename($f);
                if ($f === '' || $f === '.' || $f === '..' || $f[0] === '.') continue;
                ?>
                <img src="uploads/reviews/<?= e($f) ?>" alt="Ảnh của <?= e($r['author']) ?>" width="90" height="90" style="object-fit:cover; border-radius:12px;" loading="lazy">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div style="display:flex; gap:10px;">
                <button type="button" class="kid-btn kid-btn--green" data-action="approve" data-review-id="<?= (int)$r['id'] ?>">✅ Duyệt</button>
                <button type="button" class="kid-btn kid-btn--red" data-action="reject" data-review-id="<?= (int)$r['id'] ?>">🚫 Từ chối</button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    var list = document.getElementById('review-moderation-list');
    if (!list) return;

    var csrfMeta = document.querySelector('meta[name="csrf"]');
    var CSRF = csrfMeta ? csrfMeta.content : '';

    function showEmptyIfNeeded() {
        if (list.querySelector('.review-mod-card')) return;
        if (document.getElementById('review-moderation-empty')) return;
        var empty = document.createElement('p');
        empty.className = 'kid-card';
        empty.id = 'review-moderation-empty';
        empty.style.textAlign = 'center';
        empty.style.color = 'var(--kid-ink-soft, #6b7280)';
        empty.textContent = 'Không có review nào đang chờ duyệt. Tuyệt vời! 🎉';
        list.appendChild(empty);
    }

    list.addEventListener('click', function (evt) {
        var btn = evt.target.closest('button[data-action]');
        if (!btn || !list.contains(btn)) return;

        var action = btn.getAttribute('data-action');
        var reviewId = btn.getAttribute('data-review-id');
        var reason = null;

        if (action === 'reject') {
            reason = window.prompt('Vì sao review này chưa phù hợp?', '');
            if (reason === null) return; // huỷ
        }

        var card = btn.closest('.review-mod-card');
        var buttons = card ? card.querySelectorAll('button[data-action]') : [];
        buttons.forEach(function (b) { b.disabled = true; });

        var body = new URLSearchParams();
        body.set('review_id', reviewId);
        body.set('action', action);
        if (reason !== null) body.set('reason', reason);

        fetch('review-moderate.php', {
            method: 'POST',
            body: body,
            headers: { 'X-CSRF-Token': CSRF }
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (result.ok && result.data && result.data.status === 'success') {
                    if (card) card.remove();
                    showEmptyIfNeeded();
                } else {
                    buttons.forEach(function (b) { b.disabled = false; });
                    window.alert((result.data && result.data.message) || 'Có lỗi xảy ra, thử lại nhé!');
                }
            })
            .catch(function () {
                buttons.forEach(function (b) { b.disabled = false; });
                window.alert('Không kết nối được máy chủ, thử lại nhé!');
            });
    });
})();
</script>
