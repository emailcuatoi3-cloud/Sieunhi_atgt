<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
requireRole(['phuhuynh', 'giaovien', 'admin']);
$user = currentUser();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $province = trim((string)($_POST['province'] ?? ''));
    $imagePath = null;
    if ($title === '' || mb_strlen($title) > 200 || $description === '' || mb_strlen($description) > 2000) {
        $error = 'Vui lòng nhập tiêu đề và mô tả ngắn (tối đa 2.000 ký tự).';
    } elseif (!isset($_POST['safe_content'])) {
        $error = 'Chỉ gửi ảnh đường phố, biển báo hoặc tình huống giao thông không có thông tin nhận dạng trẻ em.';
    } else {
        try {
            if (!empty($_FILES['image']['tmp_name'])) {
                $file = $_FILES['image'];
                if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) throw new RuntimeException('Ảnh phải nhỏ hơn 5MB.');
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($extensions[$mime])) throw new RuntimeException('Chỉ hỗ trợ ảnh JPG, PNG hoặc WebP.');
                $dir = __DIR__ . '/uploads/community';
                if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Không thể tạo thư mục lưu ảnh.');
                $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Không thể lưu ảnh.');
                $imagePath = 'uploads/community/' . $name;
            }
            $db = new DB_UTILS();
            $db->execute('INSERT INTO community_reports (author_id, title, description, province, image_path, moderation_status) VALUES (?, ?, ?, ?, ?, "pending_review")', [$user['id'], $title, $description, $province ?: null, $imagePath]);
            $message = 'Đã gửi đóng góp. Nội dung sẽ xuất hiện sau khi được kiểm duyệt.';
        } catch (Throwable $e) {
            $error = APP_DEBUG ? $e->getMessage() : 'Không thể gửi đóng góp lúc này.';
        }
    }
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Đóng góp tình huống · Siêu Nhí AI</title><link rel="stylesheet" href="assets/css/style.css?v=12"><link rel="stylesheet" href="assets/css/shared-pages.css?v=28"></head>
<body><nav class="navbar static"><div class="nav-inner"><a href="index.php" class="logo"><span class="logo-badge">🤖</span>SIÊU NHÍ AI</a><a class="back-link" href="index.php">← Về trang chủ</a><a class="btn btn-ghost" href="community-admin.php">🛡️ Kiểm duyệt</a></div></nav>
<main class="wrap community-page"><div class="page-head"><span class="eyebrow-pill"><span class="dot"></span> Cộng đồng học an toàn</span><h1>Chia sẻ một tình huống trên đường</h1><p>Chỉ gửi ảnh đường phố, biển báo hoặc góc qua đường. AI sẽ lọc bước đầu và quản trị viên duyệt trước khi chia sẻ cho học sinh.</p><a class="btn btn-ghost" href="community-feed.php">👀 Xem tình huống đã duyệt</a></div>
<?php if ($message): ?><div class="inline-success"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="inline-error"><?= e($error) ?></div><?php endif; ?>
<form class="card community-form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><label>Tiêu đề tình huống<input name="title" maxlength="200" required placeholder="Ví dụ: Vạch qua đường trước cổng trường"></label><label>Mô tả điều con quan sát được<textarea name="description" maxlength="2000" rows="5" required placeholder="Điều gì an toàn hoặc cần chú ý?"></textarea></label><label>Tỉnh/thành (không ghi địa chỉ cụ thể)<input name="province" maxlength="120" placeholder="Ví dụ: Đà Nẵng"></label><label>Ảnh minh họa <input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>Ảnh tối đa 5MB. Không có mặt trẻ em, biển số, địa chỉ nhà hoặc thông tin riêng tư.</small></label><label class="consent-check"><input type="checkbox" name="safe_content" value="1" required> Tôi xác nhận ảnh không chứa thông tin nhận dạng và đồng ý để quản trị viên kiểm duyệt.</label><button class="btn btn-primary-sm" type="submit">📤 Gửi để duyệt</button></form></main><script src="assets/js/main.js?v=8"></script></body></html>
