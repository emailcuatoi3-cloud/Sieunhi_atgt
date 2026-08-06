<?php
declare(strict_types=1);
require_once __DIR__ . '/../db_utils.php';

/* Duyệt/từ chối review. Chỉ tác động review đang pending — trả false nếu không còn pending. */
function moderate_review(DB_UTILS $db, int $reviewId, string $action, ?string $reason, int $moderatorId): bool {
    $review = $db->getOne('SELECT id, user_id, status FROM place_reviews WHERE id = ?', [$reviewId]);
    if (!$review || $review['status'] !== 'pending') return false;
    if ($action === 'approve') {
        $db->execute('UPDATE place_reviews SET status="approved", reviewed_by=?, reviewed_at=NOW() WHERE id=?',
                     [$moderatorId, $reviewId]);
        $db->execute('INSERT INTO student_progress (student_id, xp) VALUES (?, 20)
                      ON DUPLICATE KEY UPDATE xp = xp + 20', [(int)$review['user_id']]);
        return true;
    }
    if ($action === 'reject') {
        $db->execute('UPDATE place_reviews SET status="rejected", reject_reason=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?',
                     [$reason !== null && $reason !== '' ? mb_substr($reason, 0, 255) : null, $moderatorId, $reviewId]);
        return true;
    }
    return false;
}

function pending_reviews(DB_UTILS $db): array {
    return $db->getAll(
        'SELECT r.id, r.stars, r.content, r.photos, r.created_at, u.name AS author, p.name AS place_name
         FROM place_reviews r JOIN users u ON u.id = r.user_id JOIN places p ON p.id = r.place_id
         WHERE r.status = "pending" ORDER BY r.id ASC');
}
