<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/upload.php';
header('Content-Type: application/json; charset=utf-8');
requireLogin(); requireCsrf();

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE); exit;
}

$db = new DB_UTILS();
$userId  = (int)$_SESSION['user_id'];
$placeId = (int)($_POST['place_id'] ?? 0);
$stars   = (int)($_POST['stars'] ?? 0);
$content = trim((string)($_POST['content'] ?? ''));

if (!$db->getOne('SELECT id FROM places WHERE id = ? AND status = "published"', [$placeId])) fail('Địa điểm không tồn tại');
if ($stars < 1 || $stars > 5) fail('Con hãy chọn mức mặt cười nhé!');
$len = mb_strlen($content, 'UTF-8');
if ($len < 5 || $len > 500) fail('Lời kể từ 5 đến 500 ký tự nhé!');
$today = (int)$db->getValue(
    'SELECT COUNT(*) FROM place_reviews WHERE user_id = ? AND created_at >= CURDATE()', [$userId]);
if ($today >= 5) fail('Hôm nay con kể đủ 5 chuyến rồi, mai kể tiếp nha! 🌙', 429);

$saved = [];
foreach (array_slice(array_keys($_FILES['photos']['name'] ?? []), 0, 3) as $i) {
    $file = ['tmp_name' => $_FILES['photos']['tmp_name'][$i],
             'error' => $_FILES['photos']['error'][$i], 'size' => $_FILES['photos']['size'][$i]];
    if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
    $v = validate_review_image($file);
    if (!$v['ok']) fail($v['error']);
    $name = bin2hex(random_bytes(16)) . '.' . $v['ext'];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/reviews/' . $name)) fail('Không lưu được ảnh, thử lại nhé!', 500);
    $saved[] = $name;
}

$db->execute('INSERT INTO place_reviews (place_id, user_id, stars, content, photos) VALUES (?,?,?,?,?)',
    [$placeId, $userId, $stars, $content, $saved !== [] ? json_encode($saved) : null]);
echo json_encode(['status' => 'success', 'message' => 'Cảm ơn con! Bài kể đang chờ cô duyệt 🕐'], JSON_UNESCAPED_UNICODE);
