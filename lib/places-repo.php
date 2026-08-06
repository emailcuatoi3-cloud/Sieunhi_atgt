<?php
declare(strict_types=1);
require_once __DIR__ . '/../db_utils.php';

/** Nhãn tiếng Việt cho loại địa điểm */
function place_type_label(string $type): string {
    $labels = ['bao-tang' => 'Bảo tàng & di tích', 'cong-vien' => 'Công viên',
               'vui-choi' => 'Vui chơi', 'thien-nhien' => 'Thiên nhiên'];
    return $labels[$type] ?? $type;
}

/** Danh sách địa điểm đã xuất bản, có thể lọc theo loại, sắp theo khoảng cách tăng dần */
function places_all(?string $type = null): array {
    $db = new DB_UTILS();
    if ($type !== null) {
        return $db->getAll("SELECT * FROM places WHERE status='published' AND type=? ORDER BY distance_km ASC", [$type]);
    }
    return $db->getAll("SELECT * FROM places WHERE status='published' ORDER BY distance_km ASC");
}

/** Lấy 1 địa điểm theo slug (chỉ đã xuất bản), null nếu không có */
function place_by_slug(string $slug): ?array {
    $row = (new DB_UTILS())->getOne("SELECT * FROM places WHERE slug=? AND status='published'", [$slug]);
    return $row ?: null;
}

/** Review đã duyệt của 1 địa điểm, kèm tên + avatar người viết, mới nhất trước */
function place_reviews_approved(int $placeId): array {
    return (new DB_UTILS())->getAll(
        "SELECT r.stars, r.content, r.photos, r.created_at, u.name, u.avatar_emoji
         FROM place_reviews r JOIN users u ON u.id = r.user_id
         WHERE r.place_id = ? AND r.status = 'approved' ORDER BY r.id DESC", [$placeId]);
}
