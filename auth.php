<?php
/**
 * auth.php — Xác thực & phân quyền dùng chung cho toàn site
 * -----------------------------------------------------------------------
 * require_once file này ở đầu MỌI trang .php (trước khi xuất HTML) để có
 * session và các hàm kiểm tra quyền bên dưới.
 *
 * Dùng lớp DB_UTILS (db_utils.php) để thao tác với MySQL — đồng bộ với
 * cấu trúc config.php / database.php / db_utils.php của dự án.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/db_utils.php'; // db_utils.php đã tự require database.php (và session_start() ở đó)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const ROLE_LABELS = [
    'hocsinh'  => 'Học sinh',
    'phuhuynh' => 'Phụ huynh',
    'giaovien' => 'Giáo viên',
    'admin'    => 'Quản trị viên',
];

const ROLE_DASHBOARDS = [
    'hocsinh'  => 'dashboard-hoc-sinh.php',
    'phuhuynh' => 'dashboard-phu-huynh.php',
    'giaovien' => 'dashboard-giao-vien.php',
    'admin'    => 'dashboard-admin.php',
];

/** Người dùng hiện tại đã đăng nhập hay chưa */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/** Trả về mảng thông tin người dùng đang đăng nhập, hoặc null */
function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'     => $_SESSION['user_id'],
        'name'   => $_SESSION['user_name'],
        'email'  => $_SESSION['user_email'],
        'role'   => $_SESSION['user_role'],
        'avatar' => $_SESSION['user_avatar'] ?? '🙂',
    ];
}

/** Bắt buộc đã đăng nhập, nếu chưa thì đưa về trang đăng nhập */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: dang-nhap.php?next=' . $redirect);
        exit;
    }
}

/**
 * Bắt buộc đã đăng nhập VÀ đúng vai trò được phép truy cập trang này.
 * Dùng ở đầu mỗi dashboard, ví dụ: requireRole(['hocsinh']);
 */
function requireRole(array $allowedRoles): void {
    requireLogin();
    $role = $_SESSION['user_role'] ?? null;
    if (!in_array($role, $allowedRoles, true)) {
        // Đăng nhập rồi nhưng sai vai trò -> đưa về đúng dashboard của họ
        $ownDashboard = ROLE_DASHBOARDS[$role] ?? 'index.php';
        header('Location: ' . $ownDashboard . '?error=khong_du_quyen');
        exit;
    }
}

/** Đăng nhập: kiểm tra email + mật khẩu, trả về mảng user nếu đúng, false nếu sai */
function attemptLogin(string $email, string $password) {
    $db = new DB_UTILS();
    $user = $db->getOne('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    if ($user['status'] !== 'active') {
        return false;
    }

    session_regenerate_id(true); // chống session fixation
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user_name']   = $user['name'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['user_role']   = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar_emoji'];

    return $user;
}

/** Đăng ký tài khoản mới. Trả về ['ok'=>true] hoặc ['ok'=>false,'error'=>...] */
function attemptRegister(string $name, string $email, string $password, string $role): array {
    // Bảo mật: KHÔNG cho phép tự đăng ký với vai trò admin qua form công khai.
    // Tài khoản admin chỉ được tạo bởi admin khác, qua trang Quản lý người dùng.
    if (!in_array($role, ['hocsinh', 'phuhuynh', 'giaovien'], true)) {
        return ['ok' => false, 'error' => 'Vai trò không hợp lệ.'];
    }
    if (strlen($password) < 6) {
        return ['ok' => false, 'error' => 'Mật khẩu cần ít nhất 6 ký tự.'];
    }

    $db = new DB_UTILS();

    $exists = $db->getOne('SELECT id FROM users WHERE email = ?', [$email]);
    if ($exists) {
        return ['ok' => false, 'error' => 'Email này đã được đăng ký trước đó.'];
    }

    $avatarByRole = ['hocsinh' => '🧒', 'phuhuynh' => '👨‍👩‍👧', 'giaovien' => '👩‍🏫', 'admin' => '🛡️'];
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $db->execute(
        'INSERT INTO users (name, email, password_hash, role, avatar_emoji) VALUES (?, ?, ?, ?, ?)',
        [$name, $email, $hash, $role, $avatarByRole[$role]]
    );
    $userId = $db->getLastInsertId();

    if ($role === 'hocsinh') {
        $db->execute(
            'INSERT INTO student_progress (student_id, xp, coin, streak_days, level) VALUES (?, 0, 0, 0, 1)',
            [$userId]
        );
    }

    return ['ok' => true, 'user_id' => $userId];
}

/* =======================================================================
   QUẢN LÝ NGƯỜI DÙNG (CHỈ ADMIN) — dùng trong admin-users.php
   Khác với attemptRegister(): các hàm này KHÔNG giới hạn vai trò, vì chỉ
   admin đã đăng nhập mới gọi được (trang gọi các hàm này luôn bọc bởi
   requireRole(['admin']) ở đầu file).
   ======================================================================= */

/** Lấy danh sách người dùng, có thể lọc theo từ khoá / vai trò */
function adminListUsers(string $search = '', string $roleFilter = ''): array {
    $db = new DB_UTILS();
    $sql = 'SELECT id, name, email, role, avatar_emoji, status, created_at FROM users WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (name LIKE ? OR email LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if ($roleFilter !== '' && in_array($roleFilter, ['hocsinh', 'phuhuynh', 'giaovien', 'admin'], true)) {
        $sql .= ' AND role = ?';
        $params[] = $roleFilter;
    }
    $sql .= ' ORDER BY id DESC';

    return $db->getAll($sql, $params);
}

/** Lấy 1 người dùng theo id */
function adminGetUser(int $id): ?array {
    $db = new DB_UTILS();
    $user = $db->getOne('SELECT * FROM users WHERE id = ?', [$id]);
    return $user ?: null;
}

/** Admin tạo tài khoản mới — CHO PHÉP tạo cả vai trò admin */
function adminCreateUser(string $name, string $email, string $password, string $role, string $status = 'active'): array {
    if (!in_array($role, ['hocsinh', 'phuhuynh', 'giaovien', 'admin'], true)) {
        return ['ok' => false, 'error' => 'Vai trò không hợp lệ.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Email không hợp lệ.'];
    }
    if (strlen($password) < 6) {
        return ['ok' => false, 'error' => 'Mật khẩu cần ít nhất 6 ký tự.'];
    }

    $db = new DB_UTILS();
    $exists = $db->getOne('SELECT id FROM users WHERE email = ?', [$email]);
    if ($exists) {
        return ['ok' => false, 'error' => 'Email này đã tồn tại.'];
    }

    $avatarByRole = ['hocsinh' => '🧒', 'phuhuynh' => '👨‍👩‍👧', 'giaovien' => '👩‍🏫', 'admin' => '🛡️'];
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $db->execute(
        'INSERT INTO users (name, email, password_hash, role, avatar_emoji, status) VALUES (?, ?, ?, ?, ?, ?)',
        [$name, $email, $hash, $role, $avatarByRole[$role], $status]
    );
    $userId = $db->getLastInsertId();

    if ($role === 'hocsinh') {
        $db->execute('INSERT INTO student_progress (student_id, xp, coin, streak_days, level) VALUES (?, 0, 0, 0, 1)', [$userId]);
    }

    return ['ok' => true, 'user_id' => $userId];
}

/** Admin cập nhật thông tin người dùng (tên, email, vai trò, trạng thái). Đổi mật khẩu là tuỳ chọn (để trống nếu không đổi) */
function adminUpdateUser(int $id, string $name, string $email, string $role, string $status, string $newPassword = ''): array {
    if (!in_array($role, ['hocsinh', 'phuhuynh', 'giaovien', 'admin'], true)) {
        return ['ok' => false, 'error' => 'Vai trò không hợp lệ.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Email không hợp lệ.'];
    }

    $db = new DB_UTILS();

    $dup = $db->getOne('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $id]);
    if ($dup) {
        return ['ok' => false, 'error' => 'Email này đã được dùng bởi tài khoản khác.'];
    }

    $avatarByRole = ['hocsinh' => '🧒', 'phuhuynh' => '👨‍👩‍👧', 'giaovien' => '👩‍🏫', 'admin' => '🛡️'];

    if ($newPassword !== '') {
        if (strlen($newPassword) < 6) {
            return ['ok' => false, 'error' => 'Mật khẩu mới cần ít nhất 6 ký tự.'];
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->execute(
            'UPDATE users SET name=?, email=?, role=?, status=?, avatar_emoji=?, password_hash=? WHERE id=?',
            [$name, $email, $role, $status, $avatarByRole[$role], $hash, $id]
        );
    } else {
        $db->execute(
            'UPDATE users SET name=?, email=?, role=?, status=?, avatar_emoji=? WHERE id=?',
            [$name, $email, $role, $status, $avatarByRole[$role], $id]
        );
    }

    return ['ok' => true];
}

/** Admin xoá người dùng (không cho tự xoá chính mình để tránh khoá hệ thống) */
function adminDeleteUser(int $id): array {
    if (isLoggedIn() && (int)$_SESSION['user_id'] === $id) {
        return ['ok' => false, 'error' => 'Bạn không thể tự xoá chính tài khoản đang đăng nhập.'];
    }
    $db = new DB_UTILS();
    $db->execute('DELETE FROM users WHERE id = ?', [$id]);
    return ['ok' => true];
}

/** Đăng xuất */
function doLogout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/** Escape ngắn gọn khi in dữ liệu người dùng ra HTML (chống XSS) */
function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}