<?php
declare(strict_types=1);
require_once __DIR__ . '/../db_utils.php';

/* Duyệt/từ chối review. Chỉ tác động review đang pending — trả false nếu không còn pending.
   Atomic: UPDATE luôn kèm "AND status='pending'" trong WHERE; chỉ coi là thành công (và chỉ
   cộng XP) khi UPDATE thực sự đổi được đúng 1 dòng (rowCount===1). Tránh race condition khi
   2 request duyệt cùng lúc đều vượt qua bước kiểm tra pending phía trên (SELECT-then-UPDATE
   không atomic) — nếu chỉ dựa vào SELECT ban đầu, cả 2 request có thể cùng cộng XP (đúp XP). */
function moderate_review(DB_UTILS $db, int $reviewId, string $action, ?string $reason, int $moderatorId): bool {
    $review = $db->getOne('SELECT id, user_id, status FROM place_reviews WHERE id = ?', [$reviewId]);
    if (!$review || $review['status'] !== 'pending') return false;

    if ($action === 'approve') {
        $stmt = $db->connection->prepare(
            'UPDATE place_reviews SET status="approved", reviewed_by=?, reviewed_at=NOW() WHERE id=? AND status="pending"');
        $stmt->execute([$moderatorId, $reviewId]);
        if ($stmt->rowCount() !== 1) return false; // đã bị duyệt/từ chối bởi request khác trong lúc này
        $db->execute('INSERT INTO student_progress (student_id, xp) VALUES (?, 20)
                      ON DUPLICATE KEY UPDATE xp = xp + 20', [(int)$review['user_id']]);
        return true;
    }
    if ($action === 'reject') {
        $stmt = $db->connection->prepare(
            'UPDATE place_reviews SET status="rejected", reject_reason=?, reviewed_by=?, reviewed_at=NOW() WHERE id=? AND status="pending"');
        $stmt->execute([$reason !== null && $reason !== '' ? mb_substr($reason, 0, 255) : null, $moderatorId, $reviewId]);
        if ($stmt->rowCount() !== 1) return false; // đã bị duyệt/từ chối bởi request khác trong lúc này
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
