<?php
declare(strict_types=1);
/* ============================================================
   API SỞ THÍCH CÁ NHÂN HOÁ — lưu/đọc khối lớp, chủ đề & loại
   địa điểm yêu thích của học sinh, dùng cho chip gợi ý AI.
     GET  → đọc sở thích hiện tại (khách → {guest:true})
     POST → upsert sở thích (CSRF + đăng nhập)
   ============================================================ */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/personalize.php';
header('Content-Type: application/json; charset=utf-8');
$db = new DB_UTILS();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isLoggedIn()) { echo json_encode(['status' => 'success', 'guest' => true]); exit; }
    $row = $db->getOne('SELECT grade_band, fav_topics, fav_place_types FROM user_preferences WHERE user_id = ?',
                       [(int)$_SESSION['user_id']]);
    echo json_encode([
        'status' => 'success', 'has_prefs' => (bool)$row,
        'prefs' => [
            'grade_band'      => $row['grade_band'] ?? 'tieu-hoc',
            'fav_topics'      => array_values(array_filter(explode(',', $row['fav_topics'] ?? ''))),
            'fav_place_types' => array_values(array_filter(explode(',', $row['fav_place_types'] ?? ''))),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

requireLogin(); requireCsrf();
$band   = ($_POST['grade_band'] ?? '') === 'thcs' ? 'thcs' : 'tieu-hoc';
$topics = array_values(array_intersect((array)($_POST['fav_topics'] ?? []), pref_topic_codes()));
$types  = array_values(array_intersect((array)($_POST['fav_place_types'] ?? []), pref_place_types()));
$db->execute(
    'INSERT INTO user_preferences (user_id, grade_band, fav_topics, fav_place_types)
     VALUES (?,?,?,?)
     ON DUPLICATE KEY UPDATE grade_band = VALUES(grade_band),
       fav_topics = VALUES(fav_topics), fav_place_types = VALUES(fav_place_types)',
    [(int)$_SESSION['user_id'], $band, implode(',', $topics), implode(',', $types)]);
echo json_encode(['status' => 'success']);
