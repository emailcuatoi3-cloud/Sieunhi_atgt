<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$path = basename((string)($_GET['file'] ?? ''));
$file = __DIR__ . '/uploads/community/' . $path;
if ($path === '' || !is_file($file)) { http_response_code(404); exit; }
$db = new DB_UTILS();
$report = $db->getOne('SELECT moderation_status FROM community_reports WHERE image_path = ? LIMIT 1', ['uploads/community/' . $path]);
if (($report['moderation_status'] ?? '') !== 'approved' && (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'admin')) { http_response_code(403); exit; }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) { http_response_code(415); exit; }
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
readfile($file);
