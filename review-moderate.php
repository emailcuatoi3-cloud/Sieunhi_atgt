<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/moderation.php';
header('Content-Type: application/json; charset=utf-8');
requireRole(['giaovien', 'admin']); requireCsrf();

$ok = moderate_review(new DB_UTILS(), (int)($_POST['review_id'] ?? 0),
    (string)($_POST['action'] ?? ''), $_POST['reason'] ?? null, (int)$_SESSION['user_id']);
if (!$ok) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Review không hợp lệ hoặc đã duyệt rồi'], JSON_UNESCAPED_UNICODE); exit; }
echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
