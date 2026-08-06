<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
requireRole(['admin']);
$db = new DB_UTILS();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
    $status = (string)($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, ['approved', 'rejected'], true)) {
        $db->execute('UPDATE community_reports SET moderation_status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?', [$status, $_SESSION['user_id'], $id]);
    }
}
$reports = [];
try { $reports = $db->getAll('SELECT cr.*, u.name AS author_name FROM community_reports cr JOIN users u ON u.id = cr.author_id WHERE cr.moderation_status IN ("pending_review", "ai_flagged") ORDER BY cr.created_at DESC'); } catch (Throwable $ignored) { }
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kiểm duyệt cộng đồng · Siêu Nhí AI</title><link rel="stylesheet" href="assets/css/style.css?v=12"><link rel="stylesheet" href="assets/css/shared-pages.css?v=28"></head><body><main class="wrap community-page"><div class="page-head"><h1>Hàng chờ kiểm duyệt</h1><p>Chỉ nội dung được duyệt mới xuất hiện trong thư viện học sinh.</p></div><div class="community-queue"><?php if (!$reports): ?><div class="card empty-state">Chưa có nội dung chờ duyệt.</div><?php endif; ?><?php foreach ($reports as $report): ?><article class="card queue-item"><div><small><?= e($report['author_name']) ?> · <?= e($report['province'] ?? '') ?></small><h3><?= e($report['title']) ?></h3><p><?= nl2br(e($report['description'])) ?></p><?php if (!empty($report['image_path'])): ?><img src="community-image.php?file=<?= e(basename($report['image_path'])) ?>" alt="Ảnh tình huống giao thông" loading="lazy"><?php endif; ?></div><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$report['id'] ?>"><button class="btn btn-primary-sm" name="status" value="approved">✓ Duyệt</button><button class="btn btn-ghost" name="status" value="rejected">Từ chối</button></form></article><?php endforeach; ?></div></main></body></html>
