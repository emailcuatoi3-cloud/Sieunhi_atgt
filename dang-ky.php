<?php
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$name = '';
$email = '';
$selectedRole = $_POST['role'] ?? 'hocsinh';
$ageGroup = $_POST['age_group'] ?? '6-8';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $agree = isset($_POST['agree']);

    if ($name === '' || $email === '' || $password === '' || $passwordConfirm === '') {
        $error = 'Vui lòng điền đầy đủ thông tin.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif (!$agree) {
        $error = 'Bạn cần đồng ý với Điều khoản sử dụng để tiếp tục.';
    } else {
        try {
            $result = attemptRegister($name, $email, $password, $selectedRole, $ageGroup);
        } catch (\Throwable $ex) {
            $result = ['ok' => false, 'error' => 'Lỗi hệ thống: ' . $ex->getMessage()];
        }
        if ($result['ok']) {
            header('Location: dang-nhap.php?registered=1&role=' . urlencode($selectedRole));
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
    (function() {
        try {
            document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme") || "light");
        } catch (e) {}
    })();
    </script>
    <title>Đăng ký · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="stylesheet" href="assets/css/fonts.css?v=1">
    <link rel="stylesheet" href="assets/css/style.css?v=5">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=5">
    <link rel="stylesheet" href="assets/css/kid-components.css?v=1">
</head>

<body>

    <div class="auth-shell">
        <a class="auth-back" href="index.php">← Về trang chủ</a>
        <button class="icon-btn theme-toggle auth-theme-btn" aria-label="Chế độ tối">🌙</button>

        <div class="auth-card">
            <div class="auth-brand">
                <div class="logo-badge">🤖</div>
                <h1>Tạo tài khoản mới</h1>
                <p>Bắt đầu hành trình học an toàn giao thông cùng AI ngay hôm nay.</p>
            </div>

            <div class="role-tabs cols-3" id="roleTabs">
                <button type="button" class="role-tab<?= $selectedRole === 'hocsinh' ? ' active' : '' ?>"
                    data-role="hocsinh"><span class="rt-ic">🧒</span>Học sinh</button>
                <button type="button" class="role-tab<?= $selectedRole === 'phuhuynh' ? ' active' : '' ?>"
                    data-role="phuhuynh"><span class="rt-ic">👨‍👩‍👧</span>Phụ huynh</button>
                <button type="button" class="role-tab<?= $selectedRole === 'giaovien' ? ' active' : '' ?>"
                    data-role="giaovien"><span class="rt-ic">👩‍🏫</span>Giáo viên</button>
            </div>

            <?php if ($error): ?>
            <div class="auth-error show"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="auth-form" id="registerForm" method="post" novalidate>
                <input type="hidden" name="role" id="roleInput" value="<?= e($selectedRole) ?>">

                <div class="auth-field" id="ageGroupField">
                    <label for="ageGroup">Nhóm tuổi của học sinh</label>
                    <div class="auth-input-wrap"><span class="fi">🌱</span>
                        <select id="ageGroup" name="age_group"><option value="6-8"<?= $ageGroup === '6-8' ? ' selected' : '' ?>>6–8 tuổi · Học bằng hình và câu chuyện</option><option value="9-11"<?= $ageGroup === '9-11' ? ' selected' : '' ?>>9–11 tuổi · Thử thách và tình huống</option></select>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="regName">Họ và tên</label>
                    <div class="auth-input-wrap">
                        <span class="fi">🙋</span>
                        <input type="text" id="regName" name="name" value="<?= e($name) ?>" placeholder="Nguyễn Văn A"
                            autocomplete="name" required>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="regEmail">Email</label>
                    <div class="auth-input-wrap">
                        <span class="fi">📧</span>
                        <input type="email" id="regEmail" name="email" value="<?= e($email) ?>"
                            placeholder="ten@vidu.com" autocomplete="email" required>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="regPassword">Mật khẩu</label>
                    <div class="auth-input-wrap">
                        <span class="fi">🔒</span>
                        <input type="password" id="regPassword" name="password" placeholder="Tối thiểu 6 ký tự"
                            autocomplete="new-password" required minlength="6">
                        <button type="button" class="auth-toggle-pw">👁️</button>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="regPasswordConfirm">Xác nhận mật khẩu</label>
                    <div class="auth-input-wrap">
                        <span class="fi">🔒</span>
                        <input type="password" id="regPasswordConfirm" name="password_confirm"
                            placeholder="Nhập lại mật khẩu" autocomplete="new-password" required minlength="6">
                        <button type="button" class="auth-toggle-pw">👁️</button>
                    </div>
                </div>

                <label class="auth-check"><input type="checkbox" name="agree"> Tôi đồng ý với <a class="auth-link"
                        href="#">Điều khoản sử dụng</a> &amp; <a class="auth-link" href="#">Chính sách bảo
                        mật</a></label>

                <button type="submit" class="btn cta-primary auth-submit" id="registerSubmitBtn">✨ Tạo tài
                    khoản</button>
            </form>

            <div class="auth-divider">hoặc</div>

            <p class="auth-foot">Đã có tài khoản? <a class="auth-link" href="dang-nhap.php">Đăng nhập</a></p>
        </div>
    </div>

    <script src="assets/js/main.js?v=5"></script>
    <script src="assets/js/auth.js?v=5"></script>
</body>

</html>
