<?php
require_once __DIR__ . '/auth.php';
requireRole(['admin']);
$user = currentUser();

$flashOk = '';
$flashError = '';
$editUser = null; // user đang được sửa (nếu có)

// ---------------- XỬ LÝ CÁC HÀNH ĐỘNG (POST) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $result = adminCreateUser(
            trim($_POST['name'] ?? ''),
            trim($_POST['email'] ?? ''),
            $_POST['password'] ?? '',
            $_POST['role'] ?? 'hocsinh',
            $_POST['status'] ?? 'active'
        );
        if ($result['ok']) {
            $flashOk = 'Đã tạo tài khoản mới thành công.';
        } else {
            $flashError = $result['error'];
        }
    } elseif ($action === 'update') {
        $result = adminUpdateUser(
            (int)($_POST['id'] ?? 0),
            trim($_POST['name'] ?? ''),
            trim($_POST['email'] ?? ''),
            $_POST['role'] ?? 'hocsinh',
            $_POST['status'] ?? 'active',
            $_POST['password'] ?? ''
        );
        if ($result['ok']) {
            $flashOk = 'Đã cập nhật thông tin tài khoản.';
        } else {
            $flashError = $result['error'];
        }
    } elseif ($action === 'delete') {
        $result = adminDeleteUser((int)($_POST['id'] ?? 0));
        if ($result['ok']) {
            $flashOk = 'Đã xoá tài khoản.';
        } else {
            $flashError = $result['error'];
        }
    }
}

// ---------------- Đang sửa user nào? (?edit=id) ----------------
if (isset($_GET['edit'])) {
    $editUser = adminGetUser((int)$_GET['edit']);
}

// ---------------- Lọc / tìm kiếm ----------------
$search = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$users = adminListUsers($search, $roleFilter);

$roleLabelMap = ROLE_LABELS;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme")||"dark");}catch(e){}})();</script>
<title>Quản lý người dùng · Siêu Nhí An Toàn Giao Thông AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=5">
<link rel="stylesheet" href="assets/css/shared-pages.css?v=5">
</head>
<body>

<div class="app">
  <aside class="sidebar">
    <div>
      <div class="side-brand"><div class="mark">🤖</div>SIÊU NHÍ AI</div>
      <a class="side-back" href="index.php">← Về trang chủ</a>
    </div>
    <a class="side-link" href="dashboard-admin.php"><span class="ic">🏠</span> Tổng quan</a>
    <a class="side-link active" href="admin-users.php"><span class="ic">👥</span> Người dùng</a>
    <a class="side-link" href="#"><span class="ic">🗂️</span> Nội dung</a>
    <a class="side-link" href="#"><span class="ic">🤖</span> AI</a>
    <a class="side-link" href="#"><span class="ic">📘</span> Bài học</a>
    <a class="side-link" href="#"><span class="ic">🏆</span> Cuộc thi</a>
    <a class="side-link" href="#"><span class="ic">📊</span> Thống kê</a>
    <div class="side-divider"></div>
    <a class="side-link" href="dashboard-hoc-sinh.php"><span class="ic">🎒</span> Dashboard học sinh</a>
    <a class="side-link" href="dashboard-phu-huynh.php"><span class="ic">👨‍👩‍👧</span> Dashboard phụ huynh</a>
    <a class="side-link" href="dashboard-giao-vien.php"><span class="ic">👩‍🏫</span> Dashboard giáo viên</a>
    <div class="side-divider"></div>
    <a class="side-link" href="logout.php"><span class="ic">🚪</span> Đăng xuất</a>

    <div class="sidebar-foot">
      <div class="av"><?= e($user['avatar']) ?></div>
      <div class="txt"><b><?= e($user['name']) ?></b><span>Toàn quyền quản trị</span></div>
    </div>
  </aside>

  <main class="main">
    <div class="top-row">
      <div class="greet">
        <h1>👥 Quản lý người dùng</h1>
        <p>Tạo, sửa, xoá tài khoản — kể cả tài khoản Admin (chỉ admin mới tạo được admin khác).</p>
      </div>
      <div class="top-actions">
        <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
        <button class="btn btn-primary-sm" onclick="document.getElementById('createPanel').scrollIntoView({behavior:'smooth'}); document.getElementById('createPanel').style.display='block';">＋ Thêm người dùng</button>
      </div>
    </div>

    <?php if ($flashOk): ?><div class="auth-success show" style="margin-bottom:18px;"><?= e($flashOk) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="auth-error show" style="margin-bottom:18px;"><?= e($flashError) ?></div><?php endif; ?>

    <div class="stat-row">
      <div class="mini-stat-card"><div class="st-ic">👥</div><b><?= count($users) ?></b><span>Kết quả đang hiển thị</span></div>
      <div class="mini-stat-card"><div class="st-ic">🧒</div><b><?= count(array_filter($users, fn($u) => $u['role'] === 'hocsinh')) ?></b><span>Học sinh</span></div>
      <div class="mini-stat-card"><div class="st-ic">👨‍👩‍👧</div><b><?= count(array_filter($users, fn($u) => $u['role'] === 'phuhuynh')) ?></b><span>Phụ huynh</span></div>
      <div class="mini-stat-card"><div class="st-ic">🛡️</div><b><?= count(array_filter($users, fn($u) => $u['role'] === 'admin')) ?></b><span>Admin</span></div>
    </div>

    <!-- FORM THÊM MỚI -->
    <div class="card" id="createPanel" style="<?= $editUser ? 'display:none;' : '' ?>">
      <div class="card-head"><h3>＋ Thêm người dùng mới</h3></div>
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="form-row" style="margin-bottom:12px;">
          <div class="form-field"><label>Họ và tên</label><input type="text" name="name" placeholder="Nguyễn Văn A" required></div>
          <div class="form-field"><label>Email</label><input type="email" name="email" placeholder="ten@vidu.com" required></div>
        </div>
        <div class="form-row" style="margin-bottom:12px;">
          <div class="form-field"><label>Mật khẩu</label><input type="text" name="password" placeholder="Tối thiểu 6 ký tự" required minlength="6"></div>
          <div class="form-field">
            <label>Vai trò</label>
            <select name="role">
              <option value="hocsinh">Học sinh</option>
              <option value="phuhuynh">Phụ huynh</option>
              <option value="giaovien">Giáo viên</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <div class="form-row" style="margin-bottom:16px;">
          <div class="form-field">
            <label>Trạng thái</label>
            <select name="status">
              <option value="active">Hoạt động</option>
              <option value="pending">Chờ duyệt</option>
              <option value="disabled">Vô hiệu hoá</option>
            </select>
          </div>
          <div></div>
        </div>
        <button type="submit" class="btn btn-primary-sm">✅ Tạo tài khoản</button>
      </form>
    </div>

    <!-- FORM SỬA (chỉ hiện khi có ?edit=id) -->
    <?php if ($editUser): ?>
    <div class="card" style="border-color:rgba(34,211,238,0.4);">
      <div class="card-head"><h3>✏️ Sửa tài khoản: <?= e($editUser['name']) ?></h3><a href="admin-users.php">✕ Huỷ</a></div>
      <form method="post">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>">
        <div class="form-row" style="margin-bottom:12px;">
          <div class="form-field"><label>Họ và tên</label><input type="text" name="name" value="<?= e($editUser['name']) ?>" required></div>
          <div class="form-field"><label>Email</label><input type="email" name="email" value="<?= e($editUser['email']) ?>" required></div>
        </div>
        <div class="form-row" style="margin-bottom:12px;">
          <div class="form-field"><label>Mật khẩu mới (để trống nếu không đổi)</label><input type="text" name="password" placeholder="Để trống = giữ nguyên"></div>
          <div class="form-field">
            <label>Vai trò</label>
            <select name="role">
              <?php foreach (['hocsinh','phuhuynh','giaovien','admin'] as $r): ?>
              <option value="<?= $r ?>" <?= $editUser['role'] === $r ? 'selected' : '' ?>><?= e($roleLabelMap[$r]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row" style="margin-bottom:16px;">
          <div class="form-field">
            <label>Trạng thái</label>
            <select name="status">
              <option value="active" <?= $editUser['status'] === 'active' ? 'selected' : '' ?>>Hoạt động</option>
              <option value="pending" <?= $editUser['status'] === 'pending' ? 'selected' : '' ?>>Chờ duyệt</option>
              <option value="disabled" <?= $editUser['status'] === 'disabled' ? 'selected' : '' ?>>Vô hiệu hoá</option>
            </select>
          </div>
          <div></div>
        </div>
        <button type="submit" class="btn btn-primary-sm">💾 Lưu thay đổi</button>
      </form>
    </div>
    <?php endif; ?>

    <!-- TÌM KIẾM / LỌC -->
    <div class="card">
      <form method="get" style="display:flex; gap:10px; flex-wrap:wrap;">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="🔍 Tìm theo tên hoặc email..." style="flex:1; min-width:200px; padding:11px 14px; border-radius:10px; background:rgba(255,255,255,0.05); border:1px solid var(--glass-border); color:#fff; font-family:inherit; font-size:13px;">
        <select name="role" style="padding:11px 14px; border-radius:10px; background:rgba(255,255,255,0.05); border:1px solid var(--glass-border); color:#fff; font-family:inherit; font-size:13px;">
          <option value="">Tất cả vai trò</option>
          <option value="hocsinh" <?= $roleFilter === 'hocsinh' ? 'selected' : '' ?>>Học sinh</option>
          <option value="phuhuynh" <?= $roleFilter === 'phuhuynh' ? 'selected' : '' ?>>Phụ huynh</option>
          <option value="giaovien" <?= $roleFilter === 'giaovien' ? 'selected' : '' ?>>Giáo viên</option>
          <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
        <button type="submit" class="btn btn-ghost">Lọc</button>
        <?php if ($search || $roleFilter): ?><a href="admin-users.php" class="btn btn-ghost">Xoá lọc</a><?php endif; ?>
      </form>
    </div>

    <!-- BẢNG DANH SÁCH -->
    <div class="card">
      <div class="card-head"><h3>📋 Danh sách tài khoản (<?= count($users) ?>)</h3></div>
      <table class="data-table">
        <thead><tr><th>Người dùng</th><th>Email</th><th>Vai trò</th><th>Trạng thái</th><th>Ngày tạo</th><th></th></tr></thead>
        <tbody>
          <?php if (!$users): ?>
          <tr><td colspan="6" style="text-align:center; color:rgba(255,255,255,0.4); padding:24px 10px;">Không tìm thấy tài khoản nào.</td></tr>
          <?php endif; ?>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><div class="stu-name"><div class="stu-avatar"><?= e($u['avatar_emoji']) ?></div><b><?= e($u['name']) ?></b><?php if ((int)$u['id'] === (int)$user['id']): ?> <span style="font-size:10.5px; color:var(--cyan);">(bạn)</span><?php endif; ?></div></td>
            <td><?= e($u['email']) ?></td>
            <td><span class="role-tag <?= $u['role'] === 'hocsinh' ? 'student' : ($u['role'] === 'phuhuynh' ? 'parent' : ($u['role'] === 'giaovien' ? 'teacher' : 'admin')) ?>"><?= e($roleLabelMap[$u['role']] ?? $u['role']) ?></span></td>
            <td>
              <?php if ($u['status'] === 'active'): ?><div class="status-dot-row active"><span class="sdot"></span>Hoạt động</div>
              <?php elseif ($u['status'] === 'pending'): ?><div class="status-dot-row pending"><span class="sdot"></span>Chờ duyệt</div>
              <?php else: ?><div class="status-dot-row" style="color:var(--red);"><span class="sdot" style="background:var(--red);"></span>Vô hiệu hoá</div>
              <?php endif; ?>
            </td>
            <td><?= e(date('d/m/Y', strtotime($u['created_at']))) ?></td>
            <td style="display:flex; gap:8px;">
              <a class="row-action" href="admin-users.php?edit=<?= (int)$u['id'] ?>">Sửa</a>
              <?php if ((int)$u['id'] !== (int)$user['id']): ?>
              <form method="post" onsubmit="return confirm('Xoá tài khoản này? Hành động không thể hoàn tác.');" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="row-action" style="background:none; border:none; color:var(--red); cursor:pointer; font-size:11px; font-weight:600;">Xoá</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<script src="assets/js/main.js?v=5"></script>
</body>
</html>
