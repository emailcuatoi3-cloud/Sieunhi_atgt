<?php
require_once __DIR__ . '/auth.php';

// Nếu đã đăng nhập rồi thì đưa về trang chủ
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$prefillEmail = '';
$selectedRole = $_GET['role'] ?? 'hocsinh';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $prefillEmail = $email;

    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        try {
            $user = attemptLogin($email, $password);
        } catch (\Throwable $ex) {
            $user = false;
            $error = 'Lỗi hệ thống: ' . $ex->getMessage();
        }
        if ($user) {
            $next = $_GET['next'] ?? null;
            $target = $next ? urldecode($next) : 'index.php';
            header('Location: ' . $target);
            exit;
        } elseif ($error === '') {
            $error = 'Email hoặc mật khẩu không đúng, hoặc tài khoản chưa được kích hoạt.';
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
    <title>Đăng nhập · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=5">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=5">
</head>

<body>

    <div class="auth-shell">
        <a class="auth-back" href="index.php">← Về trang chủ</a>
        <button class="icon-btn theme-toggle auth-theme-btn" aria-label="Chế độ tối">🌙</button>

        <div class="auth-card">
            <div class="auth-brand">
                <div class="logo-badge">🤖</div>
                <h1>Chào mừng trở lại!</h1>
                <p>Đăng nhập để tiếp tục hành trình học an toàn giao thông cùng AI.</p>
            </div>

            <div class="role-tabs cols-3" id="roleTabs">
                <button type="button" class="role-tab<?= $selectedRole === 'hocsinh' ? ' active' : '' ?>"
                    data-role="hocsinh"><span class="rt-ic">🧒</span>Học sinh</button>
                <button type="button" class="role-tab<?= $selectedRole === 'phuhuynh' ? ' active' : '' ?>"
                    data-role="phuhuynh"><span class="rt-ic">👨‍👩‍👧</span>Phụ huynh</button>
                <button type="button" class="role-tab<?= $selectedRole === 'giaovien' ? ' active' : '' ?>"
                    data-role="giaovien"><span class="rt-ic">👩‍🏫</span>Giáo viên</button>
            </div>
            <p style="font-size:11.5px; color:rgba(255,255,255,0.42); text-align:center; margin:-14px 0 20px;">Hệ thống
                sẽ tự nhận diện đúng vai trò tài khoản của bạn sau khi đăng nhập.</p>

            <?php if ($error): ?>
            <div class="auth-error show"><?= e($error) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['registered'])): ?>
            <div class="auth-success show">🎉 Đăng ký thành công! Hãy đăng nhập để tiếp tục.</div>
            <?php endif; ?>

            <form class="auth-form" id="loginForm" method="post" novalidate>
                <div class="auth-field">
                    <label for="loginEmail">Email</label>
                    <div class="auth-input-wrap">
                        <span class="fi">📧</span>
                        <input type="text" id="loginEmail" name="email" value="<?= e($prefillEmail) ?>"
                            placeholder="ten@vidu.com" autocomplete="username" required>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="loginPassword">Mật khẩu</label>
                    <div class="auth-input-wrap">
                        <span class="fi">🔒</span>
                        <input type="password" id="loginPassword" name="password" placeholder="Nhập mật khẩu"
                            autocomplete="current-password" required>
                        <button type="button" class="auth-toggle-pw">👁️</button>
                    </div>
                </div>

                <div class="auth-row-between">
                    <label class="auth-check"><input type="checkbox" name="remember"> Ghi nhớ đăng nhập</label>
                    <a class="auth-link" href="#">Quên mật khẩu?</a>
                </div>

                <button type="submit" class="btn cta-primary auth-submit" id="loginSubmitBtn">🚀 Đăng nhập</button>
            </form>

            <div class="auth-divider">hoặc</div>

            <p class="auth-foot">Chưa có tài khoản? <a class="auth-link" href="dang-ky.php">Đăng ký ngay</a></p>
            <p class="auth-foot" style="margin-top:10px; font-size:11.5px; color:rgba(255,255,255,0.4);">Tài khoản demo
                (sau khi chạy seed.php): hocsinh@demo.com · phuhuynh@demo.com · giaovien@demo.com · admin@demo.com — mật
                khẩu <code>123456</code></p>
        </div>
    </div>

    <script src="assets/js/main.js?v=5"></script>
    <script src="assets/js/auth.js?v=5"></script>
</body>

</html>