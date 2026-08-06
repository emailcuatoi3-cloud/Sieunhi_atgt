<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['status' => 'error']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['status' => 'error']); exit; }
requireCsrf();
$ageGroup = (string)($_POST['age_group'] ?? '');
if (!in_array($ageGroup, ['6-8', '9-11'], true)) { http_response_code(422); echo json_encode(['status' => 'error', 'message' => 'Nhóm tuổi không hợp lệ.']); exit; }
try {
    $db = new DB_UTILS();
    $db->execute('UPDATE users SET age_group = ? WHERE id = ?', [$ageGroup, $_SESSION['user_id']]);
    $_SESSION['user_age_group'] = $ageGroup;
    echo json_encode(['status' => 'success', 'age_group' => $ageGroup]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Chưa thể lưu nhóm tuổi. Hãy chạy migration-learning.sql.']);
}
