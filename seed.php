<?php
/**
 * seed.php — Tạo 4 tài khoản demo (mỗi vai trò một tài khoản)
 * -----------------------------------------------------------------------
 * CÁCH DÙNG:
 *   1. Import schema.sql trước (tạo database duanmau_atgt).
 *   2. Mở file này trên trình duyệt: http://localhost/.../seed.php
 *   3. Sau khi thấy thông báo thành công, XOÁ FILE NÀY ĐI (không để lại
 *      trên server thật vì ai cũng có thể chạy lại nếu còn tồn tại).
 *
 * Mật khẩu demo cho cả 4 tài khoản: 123456
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/db_utils.php';

$demoUsers = [
    ['name' => 'Bé Minh An',      'email' => 'hocsinh@demo.com',  'role' => 'hocsinh',  'avatar' => '🧒'],
    ['name' => 'Anh Tuấn Nguyễn', 'email' => 'phuhuynh@demo.com', 'role' => 'phuhuynh', 'avatar' => '👨‍👩‍👧'],
    ['name' => 'Cô Lan Anh',      'email' => 'giaovien@demo.com', 'role' => 'giaovien', 'avatar' => '👩‍🏫'],
    ['name' => 'Admin Hệ thống',  'email' => 'admin@demo.com',    'role' => 'admin',    'avatar' => '🛡️'],
];

$db = new DB_UTILS();
$created = [];
$skipped = [];

foreach ($demoUsers as $u) {
    $exists = $db->getOne('SELECT id FROM users WHERE email = ?', [$u['email']]);
    if ($exists) {
        $skipped[] = $u['email'];
        continue;
    }

    $hash = password_hash('123456', PASSWORD_DEFAULT);
    $db->execute(
        'INSERT INTO users (name, email, password_hash, role, avatar_emoji) VALUES (?, ?, ?, ?, ?)',
        [$u['name'], $u['email'], $hash, $u['role'], $u['avatar']]
    );
    $userId = $db->getLastInsertId();

    if ($u['role'] === 'hocsinh') {
        $db->execute(
            'INSERT INTO student_progress (student_id, xp, coin, streak_days, level) VALUES (?, 680, 1240, 12, 7)',
            [$userId]
        );
    }

    $created[] = $u['email'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Seed dữ liệu demo</title>
<style>
  body{ font-family:'Be Vietnam Pro',sans-serif; background:#0A0F3D; color:#fff; padding:40px; line-height:1.7; }
  .box{ max-width:560px; margin:0 auto; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); border-radius:16px; padding:28px 32px; }
  h1{ font-size:20px; margin-bottom:16px; }
  code{ background:rgba(255,255,255,0.1); padding:2px 6px; border-radius:6px; }
  .warn{ color:#FBBF24; margin-top:20px; font-size:13.5px; }
  li{ margin-bottom:6px; }
</style>
</head>
<body>
  <div class="box">
    <h1>🌱 Kết quả tạo tài khoản demo</h1>
    <?php if ($created): ?>
      <p>✅ Đã tạo thành công (mật khẩu: <code>123456</code>):</p>
      <ul><?php foreach ($created as $e) echo "<li>$e</li>"; ?></ul>
    <?php endif; ?>
    <?php if ($skipped): ?>
      <p>⏭️ Đã tồn tại từ trước, bỏ qua:</p>
      <ul><?php foreach ($skipped as $e) echo "<li>$e</li>"; ?></ul>
    <?php endif; ?>
    <p class="warn">⚠️ Hãy xoá file <code>seed.php</code> khỏi server ngay sau khi dùng xong để đảm bảo an toàn.</p>
  </div>
</body>
</html>
