<?php
/* =====================================================================
   AI GIA SƯ — BẢN 1 FILE, CHỐNG LỖI
   Giao diện + CSS + JavaScript + API + Bộ não AI: TẤT CẢ trong file này.

   CÁCH DÙNG:
   1. Chép file này vào  C:\xampp\htdocs\ten-du-an\
   2. Bật XAMPP: Apache + MySQL
   3. Mở: http://localhost/ten-du-an/ai-gia-su.php

   KHÔNG cần thư mục assets/, KHÔNG cần api/, KHÔNG cần import SQL.
   Database + bảng sẽ TỰ ĐỘNG tạo lần chạy đầu tiên.
   ===================================================================== */

if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------------- CẤU HÌNH CSDL (mặc định XAMPP) ---------------- */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'duanmau_atg');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP mặc định để trống

/* ---------------- CẤU HÌNH AI ----------------
   Để trống '' = chạy offline bằng kho kiến thức có sẵn (vẫn chat được bình thường).
   Muốn AI thật trả lời mọi câu hỏi: lấy key MIỄN PHÍ tại
   https://aistudio.google.com/apikey  rồi dán vào giữa 2 dấu nháy dưới đây. */
/* ---------------- CẤU HÌNH AI ----------------
   CÁCH 1 (dễ nhất): mở web → bấm nút ⚙️ → dán API key vào → Lưu.
   CÁCH 2: dán thẳng key vào giữa 2 dấu nháy ở dòng dưới.

   Lấy API key MIỄN PHÍ tại: https://aistudio.google.com/apikey
   (đăng nhập Google → Create API key → sao chép) */

$__key = '';   // ← hoặc dán key vào đây

// Ưu tiên key đã lưu qua nút ⚙️ trên web (file aigs-config.php)
$__cfgFile = __DIR__ . '/aigs-config.php';
if (is_file($__cfgFile)) {
    $__cfg = @include $__cfgFile;
    if (is_array($__cfg) && !empty($__cfg['gemini_key'])) {
        $__key = $__cfg['gemini_key'];
    }
}

define('GEMINI_API_KEY', $__key);
define('CONFIG_FILE', $__cfgFile);
define('GEMINI_MODEL', 'gemini-2.5-flash');
define('AI_SYSTEM_PROMPT',
    "Bạn là 'AI Gia sư' của ứng dụng Siêu Nhí An Toàn Giao Thông AI. " .
    "Nhiệm vụ: dạy các bé học sinh tiểu học Việt Nam (6-11 tuổi) về an toàn giao thông. " .
    "Cách trả lời: thân thiện, xưng 'mình', gọi bé là 'con', câu ngắn gọn dễ hiểu, " .
    "có emoji sinh động, chính xác theo luật giao thông Việt Nam. " .
    "TRẢ LỜI CHI TIẾT VÀ ĐẦY ĐỦ theo bố cục sau:\n" .
    "1. Giải thích chính (3-4 câu, dùng **in đậm** cho ý quan trọng).\n" .
    "2. Phần '📝 Con nhớ nhé:' — liệt kê 3-4 gạch đầu dòng ngắn gọn.\n" .
    "3. Phần '💡 Mẹo nhớ:' — một mẹo hoặc câu vè dễ nhớ cho trẻ em.\n" .
    "4. Phần '⚠️ Điều KHÔNG nên làm:' — 2-3 lỗi sai hay gặp.\n" .
    "5. Kết thúc bằng một câu hỏi nhỏ để bé suy nghĩ và trả lời.\n" .
    "Chỉ nói về chủ đề giao thông và an toàn; nếu bé hỏi lạc đề, hãy nhẹ nhàng " .
    "hướng bé quay lại chủ đề giao thông."
);

/* ---------------- CẤU HÌNH ẢNH MINH HOẠ ----------------
   true  = lấy ẢNH THẬT từ Wikimedia Commons (cần mạng internet)
   false = chỉ dùng hình vẽ SVG có sẵn (chạy được cả khi không có mạng) */
define('IMG_ENABLED', true);
define('IMG_COUNT', 8);        // số ảnh mỗi câu trả lời (1 ảnh lớn + 7 ảnh nhỏ)

/* ---------------- Hỗ trợ PHP 7 (XAMPP đời cũ) ---------------- */
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || mb_strpos($haystack, $needle) !== false;
    }
}

/* =====================================================================
   KẾT NỐI CSDL — tự tạo database và bảng nếu chưa có
   Trả về mảng: ['pdo' => PDO|null, 'error' => string]
   ===================================================================== */
function db_connect(): array
{
    try {
        // Bước 1: kết nối tới MySQL (chưa chọn database)
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        // Bước 2: tạo database nếu chưa có, rồi chọn nó
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        /* Bước 3: nếu bảng aigs_sessions đã tồn tại nhưng SAI cấu trúc
           (thiếu cột title) → xoá đi để tạo lại cho đúng.
           An toàn: 2 bảng aigs_* chỉ dùng riêng cho AI Gia sư,
           không đụng tới bảng nào khác của dự án. */
        $exists = $pdo->query("SHOW TABLES LIKE 'aigs_sessions'")->fetch();
        if ($exists) {
            $hasTitle = $pdo->query("SHOW COLUMNS FROM aigs_sessions LIKE 'title'")->fetch();
            if (!$hasTitle) {
                $pdo->exec("DROP TABLE IF EXISTS aigs_messages");
                $pdo->exec("DROP TABLE IF EXISTS aigs_sessions");
            }
        }

        // Bước 4: tạo 2 bảng cho AI Gia sư nếu chưa có
        $pdo->exec("CREATE TABLE IF NOT EXISTS aigs_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 1,
            title VARCHAR(255) NOT NULL DEFAULT 'Cuộc trò chuyện mới',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS aigs_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            role ENUM('user','bot') NOT NULL,
            content TEXT NOT NULL,
            illus VARCHAR(40) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_aigs_msg FOREIGN KEY (session_id)
                REFERENCES aigs_sessions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Bảng đã tạo từ bản cũ → thêm cột còn thiếu
        $hasIllus = $pdo->query("SHOW COLUMNS FROM aigs_messages LIKE 'illus'")->fetch();
        if (!$hasIllus) {
            $pdo->exec("ALTER TABLE aigs_messages ADD COLUMN illus VARCHAR(40) NULL AFTER content");
        }
        $hasPhotos = $pdo->query("SHOW COLUMNS FROM aigs_messages LIKE 'photos'")->fetch();
        if (!$hasPhotos) {
            $pdo->exec("ALTER TABLE aigs_messages ADD COLUMN photos TEXT NULL AFTER illus");
        }

        // Bộ nhớ đệm ảnh — tìm 1 lần, dùng mãi (lần sau hiện ngay, không phải chờ)
        $pdo->exec("CREATE TABLE IF NOT EXISTS aigs_img_cache (
            q VARCHAR(190) PRIMARY KEY,
            data TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Tiến độ học — mỗi chủ đề bé đã hỏi qua = 1 dòng
        $pdo->exec("CREATE TABLE IF NOT EXISTS aigs_progress (
            user_id INT NOT NULL,
            topic VARCHAR(40) NOT NULL,
            learned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, topic)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Kết quả bài kiểm tra — lưu điểm cao nhất của mỗi chủ đề
        $pdo->exec("CREATE TABLE IF NOT EXISTS aigs_quiz (
            user_id INT NOT NULL,
            topic VARCHAR(40) NOT NULL,
            best_score INT NOT NULL DEFAULT 0,
            total INT NOT NULL DEFAULT 0,
            tries INT NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, topic)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Huy hiệu bé đã đạt được
        $pdo->exec("CREATE TABLE IF NOT EXISTS aigs_badges (
            user_id INT NOT NULL,
            badge VARCHAR(40) NOT NULL,
            earned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, badge)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Kết quả các TÌNH HUỐNG tương tác (game Đường đến trường)
        $pdo->exec("CREATE TABLE IF NOT EXISTS aigs_situations (
            user_id INT NOT NULL,
            sid VARCHAR(10) NOT NULL,
            passed TINYINT(1) NOT NULL DEFAULT 0,
            first_try TINYINT(1) NOT NULL DEFAULT 0,
            tries INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, sid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ĐIỂM YẾU — mỗi lần bé sai, ghi nhận lại để AI tạo bài ôn tập riêng
        $pdo->exec("CREATE TABLE IF NOT EXISTS aigs_weak (
            user_id INT NOT NULL,
            topic VARCHAR(40) NOT NULL,
            wrong_count INT NOT NULL DEFAULT 0,
            last_wrong DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, topic)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Bài kiểm tra ĐẦU VÀO và ĐẦU RA — đo mức tiến bộ của bé
        $pdo->exec("CREATE TABLE IF NOT EXISTS aigs_test (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            phase ENUM('pre','post') NOT NULL,
            score INT NOT NULL,
            total INT NOT NULL,
            detail TEXT NULL,
            taken_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_phase (user_id, phase)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        return ['pdo' => $pdo, 'error' => ''];

    } catch (PDOException $e) {
        return ['pdo' => null, 'error' => $e->getMessage()];
    }
}

/* =====================================================================
   PHẦN 1 — API (chạy khi URL có ?action=... , rồi dừng)
   Luôn trả về JSON, kể cả khi lỗi → JavaScript đọc được lỗi thật
   ===================================================================== */
if (isset($_REQUEST['action'])) {

    header('Content-Type: application/json; charset=utf-8');

    // Bắt mọi lỗi PHP và biến thành JSON (không để in ra HTML)
    set_error_handler(function ($no, $str) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi PHP: ' . $str], JSON_UNESCAPED_UNICODE);
        exit;
    });

    function json_out(array $data): void {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $conn = db_connect();
        if ($conn['pdo'] === null) {
            json_out([
                'status'  => 'error',
                'message' => 'Không kết nối được MySQL. Hãy mở XAMPP và bấm Start ở dòng MySQL. (Chi tiết: '
                             . $conn['error'] . ')'
            ]);
        }
        $pdo    = $conn['pdo'];
        $userId = $_SESSION['user_id'] ?? 1;   // chưa đăng nhập → tài khoản demo id = 1

        /* --- Kiểm tra cuộc trò chuyện có thuộc về người dùng này không --- */
        $own = function (int $sid) use ($pdo, $userId): bool {
            $st = $pdo->prepare("SELECT id FROM aigs_sessions WHERE id = ? AND user_id = ?");
            $st->execute([$sid, $userId]);
            return (bool) $st->fetch();
        };

        switch ($_REQUEST['action']) {

            /* ---------- Kiểm tra hệ thống ---------- */
            case 'ping':
                $imgTest = IMG_ENABLED ? img_search_commons('traffic light signal road', 1) : [];
                json_out([
                    'status' => 'success',
                    'db'     => 'OK — đã kết nối ' . DB_NAME,
                    'ai'     => (GEMINI_API_KEY !== '')
                                ? 'Gemini (AI thật)'
                                : 'Offline (kho kiến thức có sẵn)',
                    'img'    => !IMG_ENABLED ? 'Đã tắt (chỉ dùng hình vẽ)'
                                : ($imgTest ? 'OK — lấy được ảnh thật từ Wikimedia Commons'
                                            : 'Không tải được ảnh (mất mạng?) → sẽ dùng hình vẽ thay thế'),
                    'php'      => PHP_VERSION,
                    'personas' => personas(),
                    'vision'   => (GEMINI_API_KEY !== '')
                                  ? 'Bật (nhận diện ảnh biển báo được)'
                                  : 'Tắt — cần API key Gemini để nhận diện ảnh',
                ]);

            /* ---------- Danh sách cuộc trò chuyện (sidebar) ---------- */
            case 'sessions':
                $st = $pdo->prepare("SELECT id, title, updated_at FROM aigs_sessions
                                     WHERE user_id = ? ORDER BY updated_at DESC LIMIT 50");
                $st->execute([$userId]);
                json_out(['status' => 'success', 'sessions' => $st->fetchAll()]);

            /* ---------- Tin nhắn của 1 cuộc trò chuyện ---------- */
            case 'messages':
                $sid = (int) ($_GET['session_id'] ?? 0);
                if (!$own($sid)) json_out(['status' => 'error', 'message' => 'Không tìm thấy cuộc trò chuyện']);

                $st = $pdo->prepare("SELECT role, content, illus, photos FROM aigs_messages
                                     WHERE session_id = ? ORDER BY id ASC");
                $st->execute([$sid]);
                $rows = $st->fetchAll();
                foreach ($rows as &$r) {
                    $r['photos'] = $r['photos'] ? json_decode($r['photos'], true) : [];
                }
                json_out(['status' => 'success', 'messages' => $rows]);

            /* ---------- Gửi tin nhắn + nhận trả lời của AI ---------- */
            case 'send':
                $message = trim($_POST['message'] ?? '');
                $persona = $_POST['persona'] ?? 'gia-su';
                if (!isset(personas()[$persona])) $persona = 'gia-su';

                // Ảnh bé gửi (nếu có) — dạng base64 từ trình duyệt
                $imgData = $_POST['image'] ?? '';
                $hasImage = ($imgData !== '');

                if ($message === '' && !$hasImage) {
                    json_out(['status' => 'error', 'message' => 'Tin nhắn trống']);
                }
                if (mb_strlen($message, 'UTF-8') > 1000) {
                    json_out(['status' => 'error', 'message' => 'Tin nhắn quá dài (tối đa 1000 ký tự)']);
                }

                $sid = (int) ($_POST['session_id'] ?? 0);

                // Chưa có cuộc trò chuyện → tạo mới
                if ($sid === 0) {
                    $base  = $message !== '' ? $message : '📷 Ảnh biển báo';
                    $title = mb_substr($base, 0, 40, 'UTF-8');
                    if (mb_strlen($base, 'UTF-8') > 40) $title .= '…';
                    $st = $pdo->prepare("INSERT INTO aigs_sessions (user_id, title) VALUES (?, ?)");
                    $st->execute([$userId, $title]);
                    $sid = (int) $pdo->lastInsertId();
                } elseif (!$own($sid)) {
                    json_out(['status' => 'error', 'message' => 'Không tìm thấy cuộc trò chuyện']);
                }

                /* ===== Trường hợp 1: bé gửi ẢNH → AI nhận diện ===== */
                if ($hasImage) {
                    // Tách "data:image/jpeg;base64,xxxx" thành mime + dữ liệu
                    $mime = 'image/jpeg';
                    if (preg_match('#^data:(image/[a-z]+);base64,(.+)$#i', $imgData, $m)) {
                        $mime    = $m[1];
                        $imgData = $m[2];
                    }
                    if (strlen($imgData) > 8 * 1024 * 1024) {   // ~6MB ảnh
                        json_out(['status' => 'error', 'message' => 'Ảnh quá lớn, con chọn ảnh nhỏ hơn nhé']);
                    }

                    $reply = ai_vision($imgData, $mime, $persona);
                    if ($reply === null) {
                        $reply = "📷 Mình nhận được ảnh của con rồi, nhưng tính năng **nhận diện ảnh** cần khoá API Gemini.\n\n"
                               . "Bạn hãy lấy khoá **miễn phí** tại `aistudio.google.com/apikey` rồi dán vào dòng "
                               . "`define('GEMINI_API_KEY', '')` ở đầu file `ai-gia-su.php` nhé!\n\n"
                               . "Trong lúc chờ, con cứ hỏi mình bằng chữ nhé — mình biết rất nhiều về biển báo đó! 🚸";
                    }

                    $userText = $message !== '' ? $message : '📷 [Con gửi một tấm ảnh]';
                    $st = $pdo->prepare("INSERT INTO aigs_messages (session_id, role, content) VALUES (?, 'user', ?)");
                    $st->execute([$sid, $userText]);
                    $st = $pdo->prepare("INSERT INTO aigs_messages (session_id, role, content, illus) VALUES (?, 'bot', ?, ?)");
                    $st->execute([$sid, $reply, 'bien-bao']);
                    $pdo->prepare("UPDATE aigs_sessions SET updated_at = NOW() WHERE id = ?")->execute([$sid]);

                    json_out([
                        'status'     => 'success',
                        'session_id' => $sid,
                        'reply'      => $reply,
                        'illus'      => null,
                        'photos'     => [],
                        'quiz'       => 'bien-bao',
                        'next'       => next_questions('bien-bao'),
                        'signs'      => sign_preview(),
                    ]);
                }

                /* ===== Trường hợp 2: chat bằng chữ ===== */
                $reply  = ai_get_reply($pdo, $sid, $message, $persona, $userId);
                $illus  = ai_match_illus($message);              // chủ đề

                // Câu hỏi tiếp ("vì sao ạ?") → dùng chủ đề của câu trước
                if ($illus === null && ai_is_followup($message)) {
                    $illus = ai_last_topic($pdo, $sid);
                }

                $photos = img_fetch_for($pdo, $message, $illus); // ảnh thật từ Commons

                // Ghi nhận chủ đề bé vừa học vào bảng tiến độ
                if ($illus !== null) {
                    $st = $pdo->prepare("INSERT IGNORE INTO aigs_progress (user_id, topic) VALUES (?, ?)");
                    $st->execute([$userId, $illus]);
                }

                // Chủ đề này có bài kiểm tra không? (để hiện nút "Làm bài kiểm tra")
                $bank    = quiz_bank();
                $hasQuiz = ($illus !== null && isset($bank[$illus]));

                // Lưu tin nhắn của bé và của AI
                $st = $pdo->prepare("INSERT INTO aigs_messages (session_id, role, content) VALUES (?, 'user', ?)");
                $st->execute([$sid, $message]);
                $st = $pdo->prepare("INSERT INTO aigs_messages (session_id, role, content, illus, photos)
                                     VALUES (?, 'bot', ?, ?, ?)");
                $st->execute([$sid, $reply, $illus, $photos ? json_encode($photos, JSON_UNESCAPED_UNICODE) : null]);
                $pdo->prepare("UPDATE aigs_sessions SET updated_at = NOW() WHERE id = ?")->execute([$sid]);

                json_out([
                    'status'     => 'success',
                    'session_id' => $sid,
                    'reply'      => $reply,
                    'illus'      => $illus,
                    'photos'     => $photos,
                    'quiz'       => $hasQuiz ? $illus : null,
                    'next'       => next_questions($illus),
                    // Hỏi về biển báo → gửi luôn bộ biển báo CHUẨN để hiện trong chat
                    'signs'      => ($illus === 'bien-bao') ? sign_preview() : null,
                ]);

            /* ---------- Trả lời lại cách khác ---------- */
            case 'regen':
                $sid     = (int) ($_POST['session_id'] ?? 0);
                $persona = $_POST['persona'] ?? 'gia-su';
                if (!isset(personas()[$persona])) $persona = 'gia-su';
                if (!$own($sid)) json_out(['status' => 'error', 'message' => 'Không tìm thấy cuộc trò chuyện']);

                // Lấy câu hỏi cuối cùng của bé
                $st = $pdo->prepare("SELECT id, content FROM aigs_messages
                                     WHERE session_id = ? AND role = 'user' ORDER BY id DESC LIMIT 1");
                $st->execute([$sid]);
                $lastUser = $st->fetch();
                if (!$lastUser) json_out(['status' => 'error', 'message' => 'Chưa có câu hỏi nào']);

                // Xoá câu trả lời cũ của AI để thay bằng câu mới
                $pdo->prepare("DELETE FROM aigs_messages WHERE session_id = ? AND role = 'bot' AND id > ?")
                    ->execute([$sid, $lastUser['id']]);

                $msg    = $lastUser['content'];
                $reply  = ai_get_reply($pdo, $sid, $msg, $persona, $userId);
                $illus  = ai_match_illus($msg);
                $photos = img_fetch_for($pdo, $msg, $illus);

                $st = $pdo->prepare("INSERT INTO aigs_messages (session_id, role, content, illus, photos)
                                     VALUES (?, 'bot', ?, ?, ?)");
                $st->execute([$sid, $reply, $illus, $photos ? json_encode($photos, JSON_UNESCAPED_UNICODE) : null]);

                $bank = quiz_bank();
                json_out([
                    'status' => 'success', 'reply' => $reply, 'illus' => $illus, 'photos' => $photos,
                    'quiz'   => ($illus && isset($bank[$illus])) ? $illus : null,
                    'next'   => next_questions($illus),
                    'signs'  => ($illus === 'bien-bao') ? sign_preview() : null,
                ]);

            /* ---------- Tìm kiếm trong lịch sử trò chuyện ---------- */
            case 'search':
                $kw = trim($_GET['q'] ?? '');
                if ($kw === '') json_out(['status' => 'success', 'sessions' => []]);

                $like = '%' . $kw . '%';
                $st = $pdo->prepare(
                    "SELECT DISTINCT s.id, s.title, s.updated_at
                     FROM aigs_sessions s
                     LEFT JOIN aigs_messages m ON m.session_id = s.id
                     WHERE s.user_id = ? AND (s.title LIKE ? OR m.content LIKE ?)
                     ORDER BY s.updated_at DESC LIMIT 30"
                );
                $st->execute([$userId, $like, $like]);
                json_out(['status' => 'success', 'sessions' => $st->fetchAll()]);

            /* ---------- Đổi tên cuộc trò chuyện ---------- */
            case 'rename':
                $sid   = (int) ($_POST['session_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                if (!$own($sid)) json_out(['status' => 'error', 'message' => 'Không tìm thấy cuộc trò chuyện']);
                if ($title === '') json_out(['status' => 'error', 'message' => 'Tên không được để trống']);

                $title = mb_substr($title, 0, 100, 'UTF-8');
                $pdo->prepare("UPDATE aigs_sessions SET title = ? WHERE id = ?")->execute([$title, $sid]);
                json_out(['status' => 'success', 'title' => $title]);

            /* ---------- Lấy đề kiểm tra của 1 chủ đề ---------- */
            case 'quiz':
                $topic = $_GET['topic'] ?? '';
                $bank  = quiz_bank();
                if (!isset($bank[$topic])) {
                    json_out(['status' => 'error', 'message' => 'Chủ đề này chưa có bài kiểm tra']);
                }
                $names = topic_names();
                // KHÔNG gửi đáp án đúng về trình duyệt — chấm điểm ở máy chủ cho công bằng
                $questions = [];
                foreach ($bank[$topic] as $i => $q) {
                    $questions[] = ['i' => $i, 'q' => $q['q'], 'o' => $q['o']];
                }
                json_out([
                    'status'     => 'success',
                    'topic'      => $topic,
                    'topic_name' => $names[$topic] ?? $topic,
                    'questions'  => $questions,
                ]);

            /* ---------- Chấm 1 câu trả lời ---------- */
            case 'quiz_check':
                $topic = $_POST['topic'] ?? '';
                $i     = (int) ($_POST['i'] ?? -1);
                $pick  = (int) ($_POST['pick'] ?? -1);
                $bank  = quiz_bank();
                if (!isset($bank[$topic][$i])) {
                    json_out(['status' => 'error', 'message' => 'Câu hỏi không hợp lệ']);
                }
                $q = $bank[$topic][$i];
                $correct = ($pick === $q['a']);

                // CÁ NHÂN HOÁ: sai → ghi nhận điểm yếu để tạo bài ôn tập riêng
                if (!$correct) record_weak($pdo, $userId, $topic);

                json_out([
                    'status'  => 'success',
                    'correct' => $correct,
                    'answer'  => $q['a'],
                    'explain' => $q['e'],
                ]);

            /* ---------- Nộp bài, lưu điểm ---------- */
            case 'quiz_submit':
                $topic = $_POST['topic'] ?? '';
                $score = (int) ($_POST['score'] ?? 0);
                $bank  = quiz_bank();
                if (!isset($bank[$topic])) {
                    json_out(['status' => 'error', 'message' => 'Chủ đề không hợp lệ']);
                }
                $total = count($bank[$topic]);
                $score = max(0, min($score, $total));   // chống gian lận: điểm không vượt quá số câu

                // Chỉ lưu điểm CAO NHẤT, đếm số lần làm bài
                $st = $pdo->prepare(
                    "INSERT INTO aigs_quiz (user_id, topic, best_score, total, tries)
                     VALUES (?, ?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE
                        best_score = GREATEST(best_score, VALUES(best_score)),
                        total = VALUES(total),
                        tries = tries + 1"
                );
                $st->execute([$userId, $topic, $score, $total]);

                json_out(['status' => 'success', 'score' => $score, 'total' => $total]);

            /* ---------- Thư viện biển báo ---------- */
            /* ---------- Thư viện luật giao thông ---------- */
            case 'laws':
                json_out(['status' => 'success', 'library' => law_library(),
                          'total' => law_count()]);

            case 'signs':
                json_out(['status' => 'success', 'library' => sign_library()]);

            /* ---------- Ảnh thật của các biển trong 1 nhóm ---------- */
            case 'sign_photos':
                $group = $_GET['group'] ?? '';
                json_out([
                    'status' => 'success',
                    'photos' => sign_photos_for_group($pdo, $group),
                ]);

            /* ---------- Xem trạng thái AI ---------- */
            case 'get_config':
                $k = GEMINI_API_KEY;
                json_out([
                    'status'  => 'success',
                    'has_key' => ($k !== ''),
                    'masked'  => $k !== '' ? substr($k, 0, 6) . str_repeat('•', 12) . substr($k, -4) : '',
                    'model'   => GEMINI_MODEL,
                    'writable'=> is_writable(__DIR__),
                ]);

            /* ---------- Lưu API key (dán từ web, không cần sửa code) ---------- */
            case 'save_key':
                $k = trim($_POST['key'] ?? '');

                // Xoá key → quay về chế độ offline
                if ($k === '') {
                    if (is_file(CONFIG_FILE)) @unlink(CONFIG_FILE);
                    json_out(['status' => 'success', 'msg' => 'Đã xoá key — quay về chế độ offline']);
                }

                if (!preg_match('/^[A-Za-z0-9_\-]{20,100}$/', $k)) {
                    json_out(['status' => 'error', 'message' => 'Key không đúng định dạng. Key Gemini thường bắt đầu bằng "AIza..."']);
                }

                // Thử gọi API xem key có chạy thật không rồi mới lưu
                $test = gemini_request_with_key($k, [
                    'contents' => [['role' => 'user', 'parts' => [['text' => 'Xin chào']]]],
                    'generationConfig' => ['maxOutputTokens' => 20],
                ]);
                if ($test === null) {
                    json_out(['status' => 'error',
                              'message' => 'Key không dùng được (sai key, hết hạn mức, hoặc máy không vào được mạng). Kiểm tra lại giúp mình nhé!']);
                }

                $php = "<?php\n// File này do nút ⚙️ trên web tạo ra — chứa API key của bạn.\n"
                     . "// KHÔNG chia sẻ file này cho ai, và không đưa lên GitHub!\n"
                     . "return " . var_export(['gemini_key' => $k], true) . ";\n";

                if (@file_put_contents(CONFIG_FILE, $php) === false) {
                    json_out(['status' => 'error',
                              'message' => 'Không ghi được file. Bạn hãy dán key thẳng vào dòng $__key = \'\'; ở đầu file ai-gia-su.php nhé.']);
                }

                json_out(['status' => 'success',
                          'msg' => '🎉 Đã bật AI THẬT! Tải lại trang để bắt đầu trò chuyện.']);

            /* ---------- Huy hiệu (tự trao khi đủ điều kiện) ---------- */
            case 'badges':
                $data = user_stats($pdo, $userId);
                $got  = badge_check($data['learned'], $data['quiz'], $data['stars'],
                                    $data['totalTopics'], $data['pre'], $data['post'], $data['certified'],
                                    $data['sitPassed'], $data['sitPerfect'], $data['sitTotal']);

                // Huy hiệu nào MỚI đạt → lưu lại và báo cho bé biết
                $st = $pdo->prepare("SELECT badge FROM aigs_badges WHERE user_id = ?");
                $st->execute([$userId]);
                $had = array_column($st->fetchAll(), 'badge');
                $new = array_values(array_diff($got, $had));

                foreach ($new as $b) {
                    $pdo->prepare("INSERT IGNORE INTO aigs_badges (user_id, badge) VALUES (?, ?)")
                        ->execute([$userId, $b]);
                }

                $all = [];
                foreach (badge_list() as $key => $b) {
                    $all[] = [
                        'key'    => $key,
                        'icon'   => $b['icon'],
                        'name'   => $b['name'],
                        'desc'   => $b['desc'],
                        'hint'   => $b['hint'],
                        'earned' => in_array($key, $got, true),
                    ];
                }

                json_out([
                    'status'  => 'success',
                    'badges'  => $all,
                    'earned'  => count($got),
                    'total'   => count(badge_list()),
                    'new'     => $new,   // huy hiệu vừa mới đạt → hiện thông báo chúc mừng
                ]);

            /* ---------- Bài kiểm tra: xem trạng thái ---------- */
            case 'test_status':
                $d = user_stats($pdo, $userId);
                json_out([
                    'status'   => 'success',
                    'pre'      => $d['pre'],
                    'post'     => $d['post'],
                    'learned'  => count($d['learned']),
                    'total'    => $d['totalTopics'],
                    // Cho làm bài đầu ra khi đã học ít nhất 10 chủ đề
                    'can_post' => (count($d['learned']) >= 10),
                ]);

            /* ---------- Bài kiểm tra: lấy đề ---------- */
            case 'test_start':
                json_out([
                    'status'    => 'success',
                    'questions' => test_questions(),
                ]);

            /* ---------- Bài kiểm tra: nộp bài, chấm điểm ---------- */
            case 'test_submit':
                $phase = ($_POST['phase'] ?? 'pre') === 'post' ? 'post' : 'pre';
                $raw   = json_decode($_POST['answers'] ?? '[]', true);
                if (!is_array($raw)) $raw = [];

                $answers = [];
                foreach ($raw as $k => $v) $answers[(int)$k] = (int)$v;

                $r = test_grade($answers);   // chấm ở MÁY CHỦ — không tin dữ liệu từ trình duyệt

                // CÁ NHÂN HOÁ: ghi nhận các chủ đề bé làm sai
                foreach ($r['wrong'] as $wt) record_weak($pdo, $userId, $wt);

                $pdo->prepare(
                    "INSERT INTO aigs_test (user_id, phase, score, total, detail)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        score = VALUES(score), total = VALUES(total),
                        detail = VALUES(detail), taken_at = NOW()"
                )->execute([
                    $userId, $phase, $r['score'], $r['total'],
                    json_encode(['skills' => $r['skills'], 'wrong' => $r['wrong']], JSON_UNESCAPED_UNICODE),
                ]);

                // Nếu là bài ĐẦU RA → so sánh với bài đầu vào để thấy mức tiến bộ
                $d = user_stats($pdo, $userId);
                json_out([
                    'status'  => 'success',
                    'phase'   => $phase,
                    'score'   => $r['score'],
                    'total'   => $r['total'],
                    'skills'  => $r['skills'],
                    'wrong'   => $r['wrong'],
                    'pre'     => $d['pre'],
                    'post'    => $d['post'],
                ]);

            /* ---------- GAME "ĐƯỜNG ĐẾN TRƯỜNG": bản đồ các chặng ---------- */
            case 'map':
                $st = $pdo->prepare("SELECT sid, passed, first_try, tries FROM aigs_situations WHERE user_id = ?");
                $st->execute([$userId]);
                $done = [];
                foreach ($st->fetchAll() as $r) $done[$r['sid']] = $r;

                $stops = [];
                $prevPassed = true;   // chặng 1 luôn mở
                foreach (situations() as $i => $sit) {
                    $d = $done[$sit['id']] ?? null;
                    $passed = $d && $d['passed'];
                    $stops[] = [
                        'id'        => $sit['id'],
                        'no'        => $i + 1,
                        'title'     => $sit['title'],
                        'skill'     => $sit['skill'],
                        'passed'    => (bool) $passed,
                        'first_try' => (bool) ($d && $d['first_try']),
                        'tries'     => $d ? (int) $d['tries'] : 0,
                        'locked'    => !$prevPassed && !$passed,   // phải qua chặng trước mới mở
                    ];
                    $prevPassed = $passed;
                }

                $passedCount = count(array_filter($stops, fn($s) => $s['passed']));
                $perfect     = count(array_filter($stops, fn($s) => $s['first_try']));

                json_out([
                    'status'  => 'success',
                    'stops'   => $stops,
                    'passed'  => $passedCount,
                    'total'   => count($stops),
                    'perfect' => $perfect,
                ]);

            /* ---------- Lấy 1 tình huống (không kèm đáp án) ---------- */
            case 'situation':
                $sit = situation_by_id($_GET['id'] ?? '');
                if (!$sit) json_out(['status' => 'error', 'message' => 'Không tìm thấy tình huống']);

                json_out([
                    'status' => 'success',
                    'id'     => $sit['id'],
                    'title'  => $sit['title'],
                    'skill'  => $sit['skill'],
                    'scene'  => $sit['scene'],
                    'q'      => $sit['q'],
                    // chỉ gửi NỘI DUNG lựa chọn, KHÔNG gửi đáp án đúng
                    'o'      => array_map(fn($o) => $o['t'], $sit['o']),
                ]);

            /* ---------- Bé chọn cách xử lý → chấm và giải thích ---------- */
            case 'situation_answer':
                $sid  = $_POST['id'] ?? '';
                $pick = (int) ($_POST['pick'] ?? -1);
                $sit  = situation_by_id($sid);
                if (!$sit || !isset($sit['o'][$pick])) {
                    json_out(['status' => 'error', 'message' => 'Lựa chọn không hợp lệ']);
                }

                $chosen  = $sit['o'][$pick];
                $correct = (bool) $chosen['ok'];

                // Tìm đáp án đúng (để chỉ ra cho bé khi chọn sai)
                $rightIdx = 0;
                foreach ($sit['o'] as $i => $o) if ($o['ok']) $rightIdx = $i;

                // Ghi nhận kết quả
                $st = $pdo->prepare("SELECT tries, passed FROM aigs_situations WHERE user_id = ? AND sid = ?");
                $st->execute([$userId, $sid]);
                $old = $st->fetch();
                $tries = ($old ? (int) $old['tries'] : 0) + 1;
                // "first_try" = đúng ngay lần thử ĐẦU TIÊN → được ⭐
                $firstTry = ($correct && $tries === 1) ? 1 : 0;
                if ($old && !$firstTry) {
                    $stf = $pdo->prepare("SELECT first_try FROM aigs_situations WHERE user_id = ? AND sid = ?");
                    $stf->execute([$userId, $sid]);
                    $firstTry = (int) $stf->fetchColumn();
                }

                $pdo->prepare(
                    "INSERT INTO aigs_situations (user_id, sid, passed, first_try, tries)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        passed = GREATEST(passed, VALUES(passed)),
                        first_try = GREATEST(first_try, VALUES(first_try)),
                        tries = VALUES(tries)"
                )->execute([$userId, $sid, $correct ? 1 : 0, $firstTry, $tries]);

                // CÁ NHÂN HOÁ: chọn sai → ghi nhận chủ đề này là điểm yếu
                if (!$correct) {
                    record_weak($pdo, $userId, $sit['topic']);
                } else {
                    // Làm đúng → giảm bớt mức "yếu" của chủ đề đó
                    $pdo->prepare("UPDATE aigs_weak SET wrong_count = GREATEST(0, wrong_count - 1)
                                   WHERE user_id = ? AND topic = ?")
                        ->execute([$userId, $sit['topic']]);
                }

                // Làm đúng cũng coi như đã học chủ đề đó
                if ($correct) {
                    $pdo->prepare("INSERT IGNORE INTO aigs_progress (user_id, topic) VALUES (?, ?)")
                        ->execute([$userId, $sit['topic']]);
                }

                json_out([
                    'status'    => 'success',
                    'correct'   => $correct,
                    'right'     => $rightIdx,
                    'explain'   => $chosen['e'],
                    'right_explain' => $sit['o'][$rightIdx]['e'],
                    'first_try' => (bool) $firstTry,
                    'topic'     => $sit['topic'],
                    'lesson_q'  => 'Giải thích thêm về ' . (topic_names()[$sit['topic']] ?? 'chủ đề này'),
                ]);

            /* ---------- CÁ NHÂN HOÁ: bài ôn tập riêng theo điểm yếu ---------- */
            case 'personalized':
                $st = $pdo->prepare(
                    "SELECT topic, wrong_count FROM aigs_weak
                     WHERE user_id = ? AND wrong_count > 0
                     ORDER BY wrong_count DESC, last_wrong DESC LIMIT 5"
                );
                $st->execute([$userId]);
                $weak = $st->fetchAll();

                $names = topic_names();
                $bank  = quiz_bank();
                $out   = [];
                foreach ($weak as $w) {
                    $t = $w['topic'];
                    $out[] = [
                        'topic'    => $t,
                        'name'     => $names[$t] ?? $t,
                        'wrong'    => (int) $w['wrong_count'],
                        'has_quiz' => isset($bank[$t]),
                        'ask'      => topic_question($t),
                    ];
                }
                json_out(['status' => 'success', 'weak' => $out]);

            /* ---------- BÁO CÁO CHO GIÁO VIÊN / PHỤ HUYNH ---------- */
            case 'report':
                $d = user_stats($pdo, $userId);
                $names = $d['names'];

                // Năng lực theo 6 nhóm kỹ năng (gộp từ bài kiểm tra + tình huống + quiz)
                $skills = [];
                if ($d['post'] && !empty($d['post']['detail']['skills'])) {
                    $skills = $d['post']['detail']['skills'];
                } elseif ($d['pre'] && !empty($d['pre']['detail']['skills'])) {
                    $skills = $d['pre']['detail']['skills'];
                }

                // Chi tiết từng chủ đề: đã học chưa, điểm quiz bao nhiêu, sai mấy lần
                $weakMap = [];
                foreach ($d['weak'] as $w) $weakMap[$w['topic']] = (int) $w['wrong_count'];

                $rows = [];
                foreach ($names as $key => $label) {
                    $q = $d['quiz'][$key] ?? null;
                    $rows[] = [
                        'topic'   => $key,
                        'name'    => $label,
                        'learned' => in_array($key, $d['learned'], true),
                        'quiz'    => $q,
                        'wrong'   => $weakMap[$key] ?? 0,
                    ];
                }

                // Gợi ý hoạt động thực hành ngoài đời cho phụ huynh
                $tips = [];
                foreach (array_slice($d['weak'], 0, 3) as $w) {
                    $tips[] = [
                        'topic' => $names[$w['topic']] ?? $w['topic'],
                        'tip'   => practice_tip($w['topic']),
                    ];
                }

                json_out([
                    'status'     => 'success',
                    'student'    => $_SESSION['fullname'] ?? 'Bé Minh An',
                    'learned'    => count($d['learned']),
                    'total'      => $d['totalTopics'],
                    'stars'      => $d['stars'],
                    'points'     => $d['points'],
                    'level'      => $d['level'],
                    'sit_passed' => $d['sitPassed'],
                    'sit_total'  => $d['sitTotal'],
                    'certified'  => $d['certified'],
                    'pre'        => $d['pre'],
                    'post'       => $d['post'],
                    'skills'     => $skills,
                    'rows'       => $rows,
                    'weak'       => $d['weak'],
                    'tips'       => $tips,
                ]);

            /* ---------- Tiến độ học tập ---------- */
            case 'progress':
                $d = user_stats($pdo, $userId);

                // Đếm huy hiệu đã đạt (để hiện trên thanh tiến độ)
                $got = badge_check($d['learned'], $d['quiz'], $d['stars'], $d['totalTopics'],
                                   $d['pre'], $d['post'], $d['certified'],
                                   $d['sitPassed'], $d['sitPerfect'], $d['sitTotal']);

                // Danh sách đầy đủ các chủ đề (để vẽ bảng tiến độ)
                $topics = [];
                foreach ($d['names'] as $key => $label) {
                    $topics[] = [
                        'key'      => $key,
                        'name'     => $label,
                        'learned'  => in_array($key, $d['learned'], true),
                        'quiz'     => $d['quiz'][$key] ?? null,
                        'has_quiz' => isset($d['bank'][$key]),
                    ];
                }

                json_out([
                    'status'    => 'success',
                    'topics'    => $topics,
                    'learned'   => count($d['learned']),
                    'total'     => $d['totalTopics'],
                    'stars'     => $d['stars'],
                    'points'    => $d['points'],
                    'level'     => $d['level'],
                    'certified' => $d['certified'],
                    'badges'    => count($got),
                    'badges_total' => count(badge_list()),
                    'pre'       => $d['pre'],
                    'post'      => $d['post'],
                    'sit_passed'=> $d['sitPassed'],
                    'sit_total' => $d['sitTotal'],
                    'weak'      => $d['weak'],
                ]);

            /* ---------- Xoá cuộc trò chuyện ---------- */
            case 'delete':
                $sid = (int) ($_POST['session_id'] ?? 0);
                if (!$own($sid)) json_out(['status' => 'error', 'message' => 'Không tìm thấy cuộc trò chuyện']);
                $pdo->prepare("DELETE FROM aigs_sessions WHERE id = ?")->execute([$sid]);
                json_out(['status' => 'success']);

            default:
                json_out(['status' => 'error', 'message' => 'Action không hợp lệ']);
        }

    } catch (Throwable $e) {
        // Bất kỳ lỗi nào cũng trả về JSON để hiện được lên màn hình chat
        json_out(['status' => 'error', 'message' => 'Lỗi máy chủ: ' . $e->getMessage()]);
    }
}

/* =====================================================================
   BỘ NÃO AI
   ===================================================================== */
function ai_get_reply(PDO $pdo, int $sessionId, string $userMessage,
                      string $persona = 'gia-su', int $userId = 1): string
{
    if (GEMINI_API_KEY !== '' && function_exists('curl_init')) {
        $reply = ai_call_gemini($pdo, $sessionId, $userMessage, $persona, $userId);
        if ($reply !== null && trim($reply) !== '') return trim($reply);
        // Gemini lỗi (hết quota, mất mạng...) → tự chuyển sang offline
    }

    /* ----- Chế độ OFFLINE ----- */
    // Câu hỏi tiếp ("vì sao ạ?") → mượn chủ đề của lượt trước
    if (ai_is_followup($userMessage)) {
        $last = ai_last_topic($pdo, $sessionId);
        if ($last !== null) {
            return persona_wrap($persona, ai_rule_based(topic_keyword($last)));
        }
    }

    // Dùng bộ truy xuất: câu hỏi GHÉP nhiều chủ đề thì ghép nhiều bài học
    $topics = ai_retrieve($userMessage, 2);
    $keys   = array_keys($topics);

    if (count($keys) >= 2) {
        // Chỉ ghép khi chủ đề thứ 2 thật sự liên quan (điểm ≥ 55% chủ đề chính)
        $vals = array_values($topics);
        if ($vals[1] >= $vals[0] * 0.55) {
            $names = topic_names();
            $reply = "Câu hỏi của con hay lắm — nó liên quan tới **2 điều** cùng lúc đó! 🤔\n\n"
                   . "━━━━━━━━━━━━━━━━━━━━\n"
                   . "## " . ($names[$keys[0]] ?? '') . "\n\n"
                   . ai_rule_based(topic_keyword($keys[0])) . "\n\n"
                   . "━━━━━━━━━━━━━━━━━━━━\n"
                   . "## " . ($names[$keys[1]] ?? '') . "\n\n"
                   . ai_rule_based(topic_keyword($keys[1])) . "\n\n"
                   . "━━━━━━━━━━━━━━━━━━━━\n"
                   . "💡 **Con nhớ áp dụng CẢ HAI điều trên cùng lúc nhé!**";
            return persona_wrap($persona, $reply);
        }
    }

    return persona_wrap($persona, ai_rule_based($userMessage));
}

/* Từ khoá đại diện của mỗi chủ đề — dùng để nối ngữ cảnh cho câu hỏi tiếp */
function topic_keyword(string $topic): string
{
    $k = [
        'den-3-mau'=>'den giao thong', 'den-do'=>'den do', 'den-vang'=>'den vang',
        'den-xanh'=>'den xanh', 'mu-bao-hiem'=>'mu bao hiem', 'vach-ke'=>'sang duong',
        'bien-bao'=>'bien bao', 'bien-stop'=>'stop', 'xe-dap'=>'xe dap',
        'day-an-toan'=>'day an toan', 'cuu-thuong'=>'cuu thuong', 'canh-sat'=>'canh sat giao thong',
        'duong-sat'=>'duong sat', 'diem-mu'=>'diem mu', 'xe-buyt'=>'xe buyt',
        'troi-mua'=>'troi mua', 'ban-dem'=>'ban dem', 'nga-tu'=>'nga tu',
        'via-he'=>'via he', 'choi-duong'=>'choi tren duong', 'lac-duong'=>'bi lac',
        'toc-do'=>'toc do', 'an-toan-chung'=>'an toan giao thong',
        // [MỞ RỘNG]
        'ghe-tre-em'=>'ghe an toan', 'cong-truong'=>'cong truong',
        'gap-tai-nan'=>'gap tai nan', 'ngap-nuoc'=>'ngap nuoc',
        'cho-khuat'=>'cho khuat', 'nhuong-duong'=>'nhuong duong',
    ];
    return $k[$topic] ?? '';
}

/* ---------- Chế độ 1: Google Gemini (nhớ ngữ cảnh hội thoại) ---------- */
/* =====================================================================
   BỘ TRUY XUẤT TRI THỨC (RAG Retriever)
   Nhiệm vụ: đọc câu hỏi của bé → tìm ĐÚNG những mẩu kiến thức liên quan
   để đưa cho AI. Khác với ai_match_illus (chỉ chọn 1 chủ đề để vẽ hình),
   bộ này CHẤM ĐIỂM và lấy NHIỀU chủ đề, vì câu hỏi thật thường ghép
   nhiều chủ đề: "đi xe buýt mà trời mưa thì làm sao ạ?"
   ===================================================================== */

/* Từ ngữ MỞ RỘNG — cách trẻ em thật sự diễn đạt, không phải thuật ngữ.
   Chỉ dùng cho việc TRUY XUẤT, không dùng để chọn hình (tránh chọn nhầm). */
function ai_topic_context_words(): array
{
    return [
        // [MỞ RỘNG] 6 chủ đề mới
        'ghe-tre-em'  => ['ghe an toan', 'ghe tre em', 'ghe rieng', 'dem nang', 'tui khi',
                          'ngoi truoc', 'ngoi sau xe hoi', 'chieu cao', 'duoi 10 tuoi'],
        'cong-truong' => ['cong truong', 'tan hoc', 'tan truong', 'don con', 'dua don',
                          'trong truong', 'ra ve', 'dong nguoi'],
        'gap-tai-nan' => ['tai nan', 'dam nhau', 'va cham', 'bi thuong', 'chay mau',
                          'cap cuu', 'giup nguoi', 'nga xe'],
        'ngap-nuoc'   => ['ngap', 'nuoc dang', 'loi qua', 'cong', 'nap cong', 'vung nuoc',
                          'nuoc sau', 'mua ngap'],
        'cho-khuat'   => ['khong nhin thay', 'bi che', 'xe do', 'do xe', 'khuc cua',
                          'ngo hem', 'buoc ra', 'nho ra'],
        'nhuong-duong'=> ['nhuong', 'lich su', 'le phep', 'nguoi gia', 'cu gia', 'em be',
                          'khuyet tat', 'cam on', 'rac', 'chen lan'],
        'den-3-mau'   => ['den', 'tin hieu', 'nga tu co den', 'cot den'],
        'den-do'      => ['den do', 'dung lai', 'vuot den'],
        'den-vang'    => ['den vang', 'sap doi den', 'den chuyen mau'],
        'den-xanh'    => ['den xanh', 'duoc di'],
        'mu-bao-hiem' => ['mu', 'non', 'bao hiem', 'quai mu', 'doi mu', 'quen mu', 'dau'],
        'vach-ke'     => ['sang duong', 'qua duong', 'bang qua', 'vach', 'ngua van', 'di bo qua'],
        'bien-bao'    => ['bien', 'bien bao', 'bang hieu', 'hinh tam giac', 'hinh tron', 'bien la'],
        'bien-stop'   => ['stop', 'bat giac', 'dung han'],
        'xe-dap'      => ['xe dap', 'dap xe', 'ghi dong', 'phanh xe', 'xe dap dien'],
        'day-an-toan' => ['o to', 'xe hoi', 'day an toan', 'ghe sau', 'ghe truoc', 'tui khi',
                          'ngoi sau', 'ngoi xe may', 'ba cho', 'me cho', 'cho di hoc'],
        'cuu-thuong'  => ['cuu thuong', 'coi hu', 'xe uu tien', 'cuu hoa', 'cap cuu', 'benh vien'],
        'canh-sat'    => ['canh sat', 'cong an', 'chu csgt', 'hieu lenh', 'gio tay'],
        'duong-sat'   => ['tau', 'duong sat', 'duong ray', 'rao chan', 'chuong reo', 'xe lua'],
        'diem-mu'     => ['xe tai', 'xe container', 'xe to', 'diem mu', 'khong nhin thay',
                          'ben hong xe', 'xe lon', 'guong chieu hau'],
        'xe-buyt'     => ['xe buyt', 'xe bus', 'tram cho', 'xuong xe', 'len xe', 'ben xe'],
        'troi-mua'    => ['mua', 'uot', 'tron truot', 'ao mua', 'che o', 'ngap', 'duong tron'],
        'ban-dem'     => ['toi', 'ban dem', 'buoi toi', 'den duong', 'phan quang',
                          'ao sang mau', 'khong nhin ro', 'den pha'],
        'nga-tu'      => ['nga tu', 'nga ba', 'giao lo', 'vong xuyen', 'bung binh', 're trai', 're phai'],
        'via-he'      => ['via he', 'le duong', 'di bo', 'mep duong', 'khong co via he'],
        'choi-duong'  => ['choi', 'da bong', 'qua bong', 'chay ra duong', 'duoi bat',
                          'tha dieu', 'truot patin', 'nghich'],
        'lac-duong'   => ['lac', 'khong tim thay', 'nguoi la', 'so dien thoai', 'goi giup',
                          '113', '114', '115', 'khan cap', 'so cuu'],
        'toc-do'      => ['toc do', 'nhanh', 'phong', 'chay nhanh', 'gan truong hoc'],
        'luat-giao-thong' => ['luat', 'quy dinh', 'phat', 'vi pham', 'cam',
                              'bao nhieu tuoi', 'du tuoi', 'bang lai'],
        'an-toan-chung' => ['an toan', 'di duong', 'den truong', 'tu nha den truong'],
    ];
}

/* Chấm điểm liên quan → trả về [chủ đề => điểm], sắp xếp giảm dần.
   Ghép 2 nguồn: từ khoá CHÍNH XÁC (điểm cao) + từ ngữ MỞ RỘNG (điểm vừa). */
function ai_retrieve(string $msg, int $k = 3): array
{
    $t = ai_normalize($msg);
    $scores = [];

    $add = function (string $topic, float $pts) use (&$scores) {
        $scores[$topic] = ($scores[$topic] ?? 0) + $pts;
    };

    // Nguồn 1: từ khoá chính xác — tin cậy cao
    foreach (ai_topic_keywords() as $topic => $kws) {
        foreach ($kws as $kw) {
            $pos = mb_strpos($t, $kw);
            if ($pos !== false) {
                // Từ khoá càng DÀI (càng cụ thể) và xuất hiện càng SỚM thì điểm càng cao
                $add($topic, mb_strlen($kw) * 2 + max(0, 25 - $pos));
            } elseif (mb_strlen($kw) >= 6) {
                $d = ai_fuzzy_distance($t, $kw);   // sai chính tả vẫn tính, nhưng trừ điểm
                if ($d !== null) $add($topic, max(1, mb_strlen($kw) * 2 - $d * 6));
            }
        }
    }

    // Nguồn 2: từ ngữ trẻ em hay dùng — điểm thấp hơn, chỉ để bổ sung ngữ cảnh
    foreach (ai_topic_context_words() as $topic => $kws) {
        foreach ($kws as $kw) {
            if (mb_strlen($kw) <= 4) {
                // Từ NGẮN ("toi", "mua", "mu") phải khớp trọn vẹn cả từ,
                // nếu không "toi" sẽ dính vào "toi nay", "buoi", "moi"... gây nhiễu
                if (preg_match('/(^|\s)' . preg_quote($kw, '/') . '($|\s)/u', $t)) {
                    $add($topic, 9);   // khớp trọn cả từ → đáng tin, cho điểm khá
                }
            } elseif (str_contains($t, $kw)) {
                $add($topic, mb_strlen($kw) * 0.8 + 3);
            }
        }
    }

    arsort($scores);

    // Bỏ chủ đề điểm quá thấp so với chủ đề đầu bảng (nhiễu)
    if ($scores) {
        $top = reset($scores);
        $scores = array_filter($scores, fn($s) => $s >= $top * 0.18);
    }
    return array_slice($scores, 0, $k, true);
}

/* Tìm các TÌNH HUỐNG THỰC TẾ liên quan — dùng làm ví dụ minh hoạ cho AI */
function ai_retrieve_situations(string $msg, array $topics, int $k = 2): array
{
    $t = ai_normalize($msg);
    $hits = [];

    foreach (situations() as $sit) {
        $score = 0;
        // Tình huống thuộc đúng chủ đề đang hỏi
        if (isset($topics[$sit['topic']])) $score += 10;
        // Hoặc câu hỏi của bé trùng ý với tiêu đề tình huống
        foreach (explode(' ', ai_normalize($sit['title'])) as $w) {
            if (mb_strlen($w) >= 4 && str_contains($t, $w)) $score += 3;
        }
        if ($score > 0) $hits[$sit['id']] = ['s' => $score, 'sit' => $sit];
    }

    uasort($hits, fn($a, $b) => $b['s'] <=> $a['s']);
    return array_map(fn($h) => $h['sit'], array_slice($hits, 0, $k));
}

/* Câu hỏi có liên quan đến BIỂN BÁO không? (để quyết định có nhồi cả thư viện không) */
function ai_asks_about_signs(string $msg): bool
{
    $t = ai_normalize($msg);
    foreach (['bien bao', 'bien cam', 'bien nguy hiem', 'bien chi dan', 'bien hieu lenh',
              'stop', 'hinh tam giac', 'hinh tron', 'bien gi', 'bien nay', 'bien do',
              'bang hieu', 'y nghia bien'] as $kw) {
        if (str_contains($t, $kw)) return true;
    }
    // Hỏi đúng mã biển: "P.102", "W.225"...
    return (bool) preg_match('/\b[pwri]\s?\d{3}/u', $t);
}

/* =====================================================================
   "MỚM" KIẾN THỨC CHO AI THẬT (kỹ thuật RAG rút gọn)
   Gemini thông minh nhưng KHÔNG biết app này dạy gì.
   → Ta đưa kho kiến thức + thư viện biển báo vào lời dặn,
     để AI trả lời tự nhiên như người thật NHƯNG vẫn chính xác
     theo luật giao thông Việt Nam và đúng nội dung bài học.
   ===================================================================== */
function ai_knowledge_context(string $userMessage, array $prevTopics = [], array $weak = []): string
{
    // Bước 1 — TRUY XUẤT: chấm điểm, lấy tối đa 3 chủ đề liên quan nhất
    $topics = ai_retrieve($userMessage, 3);

    // Câu hỏi tiếp ("vì sao ạ?") không có từ khoá → mượn chủ đề của lượt trước
    if (!$topics && $prevTopics) {
        foreach (array_slice($prevTopics, 0, 2) as $pt) $topics[$pt] = 10;
    }

    $names = topic_names();
    $ctx = "\n\n=== KIẾN THỨC CHUẨN CỦA ỨNG DỤNG (bám sát khi trả lời) ===\n";

    // Bước 2 — NẠP BÀI HỌC: chủ đề điểm cao nhất nạp đầy đủ,
    // các chủ đề phụ nạp kèm để trả lời được câu GHÉP NHIỀU Ý
    if ($topics) {
        $i = 0;
        foreach ($topics as $tk => $score) {
            $label = $names[$tk] ?? $tk;
            if ($i === 0) {
                // Bài học CHÍNH: ưu tiên khớp trực tiếp theo CÂU BÉ HỎI —
                // ví dụ hỏi "ngồi sau xe máy" phải ra bài xe máy,
                // chứ không phải bài chung của mã chủ đề (bài ô tô).
                $lesson = ai_rule_based($userMessage);
                if (mb_strpos($lesson, 'chưa chắc chắn câu trả lời') !== false) {
                    $lesson = ai_rule_based(topic_keyword($tk));   // không khớp → dùng bài theo chủ đề
                }
                $ctx .= "\n[BÀI HỌC CHÍNH — {$label}]\n{$lesson}\n";
            } else {
                // Chủ đề phụ: rút gọn để tiết kiệm chỗ, vẫn đủ ý
                $short = mb_substr(ai_rule_based(topic_keyword($tk)), 0, 700);
                $ctx .= "\n[KIẾN THỨC LIÊN QUAN — {$label}]\n{$short}...\n";
            }
            $i++;
        }
        if (count($topics) > 1) {
            $ctx .= "\n⚠️ Câu hỏi của bé liên quan ĐỒNG THỜI nhiều chủ đề trên. "
                  . "Hãy trả lời GỘP các kiến thức đó lại thành một lời khuyên liền mạch, "
                  . "chứ không nói tách rời từng phần.\n";
        }
    }

    // Bước 3 — VÍ DỤ THỰC TẾ: lấy tình huống trong app làm minh hoạ,
    // giúp AI trả lời bằng ví dụ cụ thể thay vì lý thuyết suông
    $sits = ai_retrieve_situations($userMessage, $topics, 2);
    if ($sits) {
        $ctx .= "\n[VÍ DỤ TÌNH HUỐNG THỰC TẾ TRONG ỨNG DỤNG]\n";
        foreach ($sits as $s) {
            $right = '';
            foreach ($s['o'] as $o) if ($o['ok']) $right = $o['t'];
            $ctx .= "• Tình huống \"{$s['title']}\": {$s['q']}\n"
                  . "  → Cách xử lý ĐÚNG: {$right}\n";
        }
        $ctx .= "Nếu phù hợp, hãy dùng ví dụ này để giải thích cho bé dễ hình dung.\n";
    }

    // Bước 4 — BIỂN BÁO: chỉ nạp đầy đủ khi bé thật sự hỏi về biển báo.
    // Hỏi chuyện khác thì chỉ đưa bảng tóm tắt → ngữ cảnh gọn, AI tập trung hơn.
    if (ai_asks_about_signs($userMessage) || isset($topics['bien-bao']) || isset($topics['bien-stop'])) {
        $ctx .= "\n[THƯ VIỆN BIỂN BÁO VIỆT NAM CỦA ỨNG DỤNG — dùng đúng mã và tên này]\n";
        foreach (sign_library() as $g) {
            $ctx .= "\n* " . strip_tags($g['label']) . " — " . $g['note'] . "\n";
            foreach ($g['signs'] as $s) {
                $ctx .= "  - {$s['code']} ({$s['name']}): {$s['mean']}\n";
            }
        }
    } else {
        $ctx .= "\n[TÓM TẮT NHÓM BIỂN BÁO]\n"
              . "Tròn viền đỏ = CẤM · Tam giác viền đỏ = NGUY HIỂM · "
              . "Tròn nền xanh = HIỆU LỆNH · Vuông nền xanh = CHỈ DẪN.\n"
              . "(Ứng dụng có thư viện 28 biển báo — nếu bé hỏi biển cụ thể, "
              . "hãy mời bé mở mục 🚸 Thư viện biển báo.)\n";
    }

    // Bước 4b — LUẬT GIAO THÔNG: nạp đúng những điều luật liên quan câu hỏi,
    // để AI trả lời có CĂN CỨ PHÁP LÝ thật thay vì nói chung chung.
    $lawHits = ai_law_match($userMessage, 3);
    /* [NÂNG CẤP V2 — THÊM MỚI] gộp thêm kết quả từ bộ so khớp 38 điều luật mới */
    foreach (ai2_law_match($userMessage, 3) as $v2id => $v2sc) {
        if (!isset($lawHits[$v2id])) $lawHits[$v2id] = $v2sc;
    }
    if ($lawHits || ai_asks_about_law($userMessage)) {
        $ctx .= "\n[LUẬT GIAO THÔNG VIỆT NAM LIÊN QUAN — trích dẫn đúng số điều, đừng bịa]\n";
        if ($lawHits) {
            foreach (array_keys($lawHits) as $lid) {
                $l = law_by_id($lid);
                if (!$l) continue;
                $ctx .= "\n• {$l['title']}\n"
                      . "  Quy định: {$l['rule']}\n"
                      . "  Căn cứ: {$l['base']}\n";
                if ($l['fine'] !== '') {
                    $ctx .= "  Mức phạt: {$l['fine']}";
                    if ($l['fbase'] !== '') $ctx .= " ({$l['fbase']})";
                    $ctx .= "\n";
                }
            }
        } else {
            foreach (law_library() as $g) {
                $ctx .= "\n* " . strip_tags($g['label']) . "\n";
                foreach ($g['laws'] as $l) {
                    $ctx .= "  - {$l['title']} ({$l['base']})"
                          . ($l['fine'] !== '' ? " — phạt: {$l['fine']}" : '') . "\n";
                }
            }
        }
        /* [NÂNG CẤP V2 — THÊM MỚI] bảng số liệu phạt chi tiết đã kiểm chứng cho AI */
        $ctx .= ai2_law_context($userMessage);

        $ctx .= "\n⚠️ Khi nói về mức phạt với bé tiểu học: nói NGẮN GỌN, nhấn mạnh rằng "
              . "luật sinh ra để BẢO VỆ mọi người chứ không phải để doạ nạt. "
              . "Đừng kể lể con số tiền phạt dài dòng — bé cần hiểu VÌ SAO có luật đó.\n";
    }

    // Bước 5 — CÁ NHÂN HOÁ: cho AI biết bé đang yếu chỗ nào để nhắc khéo
    if ($weak) {
        $list = [];
        foreach (array_slice($weak, 0, 3) as $w) {
            $list[] = ($names[$w['topic']] ?? $w['topic']) . " (sai {$w['wrong_count']} lần)";
        }
        $ctx .= "\n[BÉ NÀY ĐANG CÒN YẾU]\n" . implode(', ', $list) . "\n"
              . "Nếu câu hỏi có liên quan, hãy giải thích kỹ hơn bình thường một chút "
              . "và nhắc lại điểm mấu chốt. Đừng nói thẳng là bé 'yếu' hay 'hay sai' — "
              . "bé sẽ tự ti. Chỉ cần giảng kỹ hơn thôi.\n";
    }

    $ctx .= "\n=== QUY TẮC BẮT BUỘC ===\n"
          . "1. Trả lời TỰ NHIÊN như một gia sư người thật đang trò chuyện — "
          . "không đọc thuộc lòng, không lặp máy móc. Hiểu ý bé rồi giải thích lại theo cách của mình.\n"
          . "2. Nội dung phải CHÍNH XÁC theo kiến thức trên và luật giao thông Việt Nam. "
          . "Nếu không chắc, hãy nói thật là chưa chắc — TUYỆT ĐỐI KHÔNG BỊA, vì bé làm theo sẽ nguy hiểm.\n"
          . "3. Bé hỏi tình huống CỤ THỂ của riêng bé (ví dụ: 'nhà con không có vỉa hè', "
          . "'con đi học bằng xe buýt lúc trời mưa') thì hãy trả lời ĐÚNG tình huống đó, "
          . "vận dụng kiến thức trên — đừng đọc lại bài học chung chung.\n"
          . "4. Nếu bé hỏi ngoài chủ đề giao thông, trả lời ngắn gọn thân thiện rồi khéo léo "
          . "dẫn bé quay lại chuyện an toàn đường phố.\n"
          . "5. Nhớ những gì bé đã nói trước đó trong cuộc trò chuyện để trả lời liền mạch.\n";

    return $ctx;
}

function ai_call_gemini(PDO $pdo, int $sessionId, string $userMessage,
                        string $persona = 'gia-su', int $userId = 1): ?string
{
    $st = $pdo->prepare("SELECT role, content FROM aigs_messages
                         WHERE session_id = ? ORDER BY id DESC LIMIT 10");
    $st->execute([$sessionId]);
    $history = array_reverse($st->fetchAll());

    $contents = [];
    foreach ($history as $m) {
        $contents[] = [
            'role'  => $m['role'] === 'user' ? 'user' : 'model',
            'parts' => [['text' => $m['content']]],
        ];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

    // Các chủ đề đã nói ở những lượt trước — để hiểu câu hỏi tiếp ("vì sao ạ?")
    $prevTopics = [];
    if ($sessionId > 0) {
        $st = $pdo->prepare("SELECT DISTINCT illus FROM aigs_messages
                             WHERE session_id = ? AND illus IS NOT NULL
                             ORDER BY id DESC LIMIT 3");
        $st->execute([$sessionId]);
        $prevTopics = array_column($st->fetchAll(), 'illus');
    }

    // Điểm yếu của bé — để AI giảng kỹ hơn ở đúng chỗ bé hay sai
    $weak = [];
    try {
        $st = $pdo->prepare("SELECT topic, wrong_count FROM aigs_weak
                             WHERE user_id = ? AND wrong_count > 0
                             ORDER BY wrong_count DESC LIMIT 3");
        $st->execute([$userId]);
        $weak = $st->fetchAll();
    } catch (Throwable $e) { /* bảng chưa có → bỏ qua */ }

    // Ghép: tính cách gia sư + vai diễn nhân vật + TRI THỨC TRUY XUẤT ĐƯỢC
    $p = personas()[$persona] ?? personas()['gia-su'];
    $sys = AI_SYSTEM_PROMPT
         . "\n\nVAI DIỄN: " . $p['prompt']
         . ai_knowledge_context($userMessage, $prevTopics, $weak);

    $body = [
        'system_instruction' => ['parts' => [['text' => $sys]]],
        'contents'           => $contents,
        'generationConfig'   => ['temperature' => 0.8, 'maxOutputTokens' => 2048],
    ];

    return gemini_request($body);
}

/* =====================================================================
   NHẬN DIỆN ẢNH BIỂN BÁO (Gemini Vision) — bé chụp/gửi ảnh, AI đọc giúp
   ===================================================================== */
function ai_vision(string $base64, string $mime, string $persona = 'gia-su'): ?string
{
    if (GEMINI_API_KEY === '' || !function_exists('curl_init')) return null;

    $p = personas()[$persona] ?? personas()['gia-su'];
    $sys = "Bạn là AI dạy an toàn giao thông cho trẻ tiểu học Việt Nam (6-11 tuổi). "
         . "VAI DIỄN: " . $p['prompt'] . "\n"
         . "Nhiệm vụ: nhìn ảnh bé gửi và giải thích. Nếu là BIỂN BÁO giao thông: "
         . "cho biết tên biển, thuộc nhóm nào (cấm/nguy hiểm/hiệu lệnh/chỉ dẫn), ý nghĩa, "
         . "và bé phải làm gì khi gặp biển này. Nếu là tình huống giao thông: "
         . "chỉ ra điều gì AN TOÀN và điều gì NGUY HIỂM trong ảnh. "
         . "Nếu ảnh không liên quan giao thông: nói nhẹ nhàng và mời bé gửi ảnh biển báo. "
         . "Trả lời có bố cục, dùng **in đậm**, emoji, và kết bằng một câu hỏi nhỏ cho bé.";

    $body = [
        'system_instruction' => ['parts' => [['text' => $sys]]],
        'contents' => [[
            'role'  => 'user',
            'parts' => [
                ['inline_data' => ['mime_type' => $mime, 'data' => $base64]],
                ['text' => 'Đây là ảnh con vừa gửi. Ảnh này là gì vậy ạ?'],
            ],
        ]],
        'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 2048],
    ];

    return gemini_request($body);
}

/* Gọi API Gemini (dùng chung cho chat, nhận diện ảnh, và kiểm tra key) */
function gemini_request(array $body): ?string
{
    return gemini_request_with_key(GEMINI_API_KEY, $body);
}

function gemini_request_with_key(string $key, array $body): ?string
{
    if ($key === '' || !function_exists('curl_init')) return null;

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/'
                    . GEMINI_MODEL . ':generateContent?key=' . $key);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => 45,
        // XAMPP trên Windows hay thiếu chứng chỉ SSL → tắt kiểm tra (chỉ dùng ở localhost)
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false) return null;

    $data = json_decode($res, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

/* ---------- Chế độ 2: Kho kiến thức offline ---------- */
function ai_khong_dau(string $str): string
{
    $str = mb_strtolower($str, 'UTF-8');
    $map = [
        'a' => ['á','à','ả','ã','ạ','ă','ắ','ằ','ẳ','ẵ','ặ','â','ấ','ầ','ẩ','ẫ','ậ'],
        'e' => ['é','è','ẻ','ẽ','ẹ','ê','ế','ề','ể','ễ','ệ'],
        'i' => ['í','ì','ỉ','ĩ','ị'],
        'o' => ['ó','ò','ỏ','õ','ọ','ô','ố','ồ','ổ','ỗ','ộ','ơ','ớ','ờ','ở','ỡ','ợ'],
        'u' => ['ú','ù','ủ','ũ','ụ','ư','ứ','ừ','ử','ữ','ự'],
        'y' => ['ý','ỳ','ỷ','ỹ','ỵ'],
        'd' => ['đ'],
    ];
    foreach ($map as $to => $from) $str = str_replace($from, $to, $str);
    return $str;
}

function ai_rule_based(string $msg): string
{
    $t = ai_normalize($msg);   // chuẩn hoá: teencode, viết tắt, bỏ dấu câu

    /* ---- Ưu tiên: bé hỏi đúng TÊN hoặc MÃ một biển báo cụ thể?
       Ví dụ: "biển P.127 là gì?", "biển trẻ em qua đường nghĩa là sao?"
       → tra thẳng thư viện, trả lời chính xác biển đó. ---- */
    foreach (sign_library() as $group) {
        foreach ($group['signs'] as $sg) {
            $codeNorm = ai_normalize($sg['code']);   // "P.127" → "p 127"
            $nameNorm = ai_normalize($sg['name']);
            if ((mb_strlen($nameNorm) >= 8 && str_contains($t, $nameNorm))
                || (mb_strlen($codeNorm) >= 4 && str_contains($t, $codeNorm))) {

                return "🚸 **Biển {$sg['code']} — {$sg['name']}**\n\n"
                     . "Thuộc nhóm: {$group['label']}\n"
                     . "_{$group['note']}_\n\n"
                     . "**Ý nghĩa:** {$sg['mean']}\n\n"
                     . "💡 Con có thể mở **🚸 Thư viện biển báo** (nút trên góc phải) để xem hình và toàn bộ các biển khác nhé!\n\n"
                     . "❓ Đố con: biển này thuộc nhóm hình gì — tròn, tam giác hay vuông? 🤔";
            }
        }
    }

    /* ===== [NÂNG CẤP V2 — THÊM MỚI] Bộ não luật thông minh:
       trả lời chính xác mức phạt theo loại xe, khung phạt tốc độ theo số km
       vượt, 3 mức nồng độ cồn, hệ thống 12 điểm GPLX, so sánh xe máy - ô tô.
       Không khớp thì trả về null → mọi tầng cũ bên dưới chạy y như cũ. ===== */
    $v2Ans = ai2_law_reply($msg);
    if ($v2Ans !== null) return $v2Ans;

    /* ---- Bé hỏi về LUẬT hoặc MỨC PHẠT? → tra thư viện luật trước ---- */
    $lawAns = ai_law_reply($msg);
    if ($lawAns !== null) return $lawAns;

    /* Chủ đề CỤ THỂ xếp trước, chủ đề CHUNG xếp sau.
       Mỗi câu trả lời có bố cục: giải thích → con nhớ nhé → mẹo nhớ →
       điều không nên làm → câu hỏi ôn tập cho bé. */
    $kb = [

        /* ===== [MỞ RỘNG] Ghế an toàn cho trẻ em trên ô tô ===== */
        [['ghe an toan', 'ghe tre em', 'ghe cho be', 'thiet bi an toan', 'dem nang', 'ngoi ghe truoc', 'ngoi ghe sau'], [
            "Con có biết trên ô tô có một chiếc ghế đặc biệt dành riêng cho trẻ em không? Đó là **ghế an toàn cho trẻ em** 🚗👶 — nó ôm lấy người con vừa vặn, chắc chắn hơn nhiều so với dây an toàn của người lớn.\n\nVì sao lại cần ghế riêng? Vì dây an toàn trên xe được thiết kế cho người cao **trên 1,35 mét**. Nếu con còn nhỏ mà thắt dây đó, dây sẽ vắt ngang **cổ** thay vì ngang vai, và vắt ngang **bụng** thay vì ngang hông. Khi xe phanh gấp, dây siết đúng vào những chỗ mềm yếu nhất — rất nguy hiểm.\n\n📜 **Quy định mới con nên biết:** Từ ngày **1/1/2026**, trẻ **dưới 10 tuổi** và **cao dưới 1,35 mét** thì **không được ngồi cùng hàng ghế với người lái xe** (trừ loại xe chỉ có một hàng ghế duy nhất). Người lớn còn phải **dùng và hướng dẫn dùng thiết bị an toàn phù hợp** cho con nữa.\n_(Khoản 3 Điều 10 — Luật Trật tự, an toàn giao thông đường bộ 2024, số 36/2024/QH15)_\n\n📝 **Con nhớ nhé:**\n• Trẻ nhỏ ngồi **hàng ghế sau** — an toàn hơn hàng ghế trước rất nhiều\n• Dùng **ghế an toàn** hoặc **đệm nâng** phù hợp với chiều cao của con\n• Ghế phải được **lắp chặt** vào xe, không để lỏng lẻo\n• Ghế trước còn nguy hiểm vì **túi khí** bung ra rất mạnh, có thể làm trẻ nhỏ bị thương\n\n💡 **Mẹo nhớ:** \"Chưa cao 1 mét 35 — Ngồi hàng sau mới đúng\" 🚗\n\n⚠️ **Điều KHÔNG nên làm:**\n• Ngồi ghế trước cạnh người lái khi con còn nhỏ\n• Ngồi trong lòng người lớn khi xe đang chạy — phanh gấp là con bị văng ra\n• Hai người thắt chung một dây an toàn\n• Tháo dây an toàn giữa đường vì thấy vướng\n\n❓ Đố con: vì sao dây an toàn của người lớn lại chưa hợp với các bạn nhỏ? 🤔",
        ]],

        /* ===== [MỞ RỘNG] An toàn ở khu cổng trường ===== */
        [['cong truong', 'tan hoc', 'tan truong', 'truoc cong truong', 'gio ra ve'], [
            "Con có để ý không — **cổng trường giờ tan học** là một trong những chỗ đông và lộn xộn nhất trong ngày đó 🏫\n\nHàng trăm bạn cùng ùa ra một lúc, phụ huynh dựng xe kín cả lề đường, xe máy len lỏi, có khi xe còn đỗ tràn xuống lòng đường. Ở chỗ đông như vậy, chỉ cần một bạn xô nhẹ là bạn bên cạnh có thể ngã nhào ra đường — nơi xe đang chạy.\n\n📝 **Con nhớ nhé:**\n• Ra khỏi cổng thì **đi, không chạy** — càng vội càng dễ ngã\n• **Không xô đẩy, chen lấn**, không đuổi bắt nhau ở cổng trường\n• Đứng chờ ba mẹ ở **đúng chỗ đã hẹn trước**, không đi lang thang tìm\n• Ba mẹ chưa tới → **quay vào trong sân trường** chờ, đừng đứng sát lòng đường\n• Muốn qua đường thì **chờ người lớn**, không tự băng qua giữa đám đông\n• Có bác bảo vệ, cô giáo hoặc chú cảnh sát hướng dẫn → **nghe theo ngay**\n\n💡 **Mẹo nhớ:** \"Tan trường — Đi chậm — Đúng chỗ hẹn\" 🏫\n\n⚠️ **Điều KHÔNG nên làm:**\n• Chạy ùa ra cổng ngay khi vừa nghe trống\n• Luồn qua đường giữa những chiếc xe đang đỗ trước cổng\n• Đứng giữa lòng đường vẫy tay gọi ba mẹ\n• Đi theo người lạ dù họ nói \"ba mẹ nhờ chú đón con\"\n\n❓ Đố con: nếu ba mẹ tới đón muộn, con nên đứng chờ ở đâu cho an toàn nhất? 🤔",
        ]],

        /* ===== [MỞ RỘNG] Khi gặp tai nạn giao thông ===== */
        [['gap tai nan', 'thay tai nan', 'chung kien tai nan', 'nguoi bi nan', 'so cuu', 'co tai nan'], [
            "Nếu một ngày con nhìn thấy tai nạn giao thông trên đường, con sẽ rất sợ và bối rối — điều đó là bình thường thôi. Mình chỉ con cách xử lý đúng nhé 🚑\n\n**Điều quan trọng số 1: con phải AN TOÀN trước đã.** Hãy đứng lên vỉa hè, tránh xa lòng đường. Vì sau một vụ tai nạn, các xe khác vẫn đang chạy tới và rất dễ xảy ra va chạm tiếp theo.\n\n**Điều quan trọng số 2: gọi người lớn ngay.** Con hãy gọi to để nhờ người lớn ở gần đó tới giúp. Con còn nhỏ, sức con chưa đủ và con cũng chưa được học sơ cứu — việc của con là **BÁO TIN**, không phải tự mình cứu người.\n\n📞 **Số điện thoại khẩn cấp:**\n• **115** — Cấp cứu y tế 🚑\n• **113** — Cảnh sát 👮\n• **114** — Cứu hoả 🚒\n\n📝 **Con nhớ nhé:**\n• **Không tự bế, kéo hay lay người bị nạn** — nếu họ bị gãy xương hay chấn thương cổ, di chuyển sai sẽ làm họ nặng thêm\n• Nếu người bị nạn còn tỉnh, con có thể **trấn an**: \"Có người tới giúp rồi, cô/chú cố lên nhé\"\n• Nhớ **địa điểm** xảy ra tai nạn để nói lại cho người lớn\n• Nếu chính con bị ngã → **ngồi yên và gọi người lớn**, đừng cố đứng dậy đi tiếp ngay\n\n💡 **Mẹo nhớ:** \"Đứng chỗ an toàn — Gọi người lớn — Không tự di chuyển người bị nạn\" 💛\n\n⚠️ **Điều KHÔNG nên làm:**\n• Chạy vào giữa đường để xem\n• Xúm đông vây quanh làm người bị nạn khó thở\n• Quay phim, chụp ảnh thay vì đi gọi người giúp\n• Bỏ đi luôn mà không báo cho ai\n\n❓ Đố con: số điện thoại gọi xe cứu thương là số mấy nào? 🤔",
        ]],

        /* ===== [MỞ RỘNG] Đường ngập nước ===== */
        [['ngap nuoc', 'duong ngap', 'ho ga', 'loi nuoc', 'nuoc chay xiet', 'ngap lut'], [
            "Ở nhiều nơi, chỉ cần một trận mưa lớn là đường đã ngập thành \"sông\" rồi 🌊 — và đó là lúc con phải cẩn thận nhất đó.\n\n**Vì sao nước ngập lại nguy hiểm đến vậy?** Vì mặt nước đục che kín hết mọi thứ bên dưới. Con không thể biết chỗ nào nông, chỗ nào sâu, và nguy hiểm nhất là những **hố ga bị mất nắp** — nước xoáy vào đó rất mạnh, người lớn còn khó chống lại được.\n\n📝 **Con nhớ nhé:**\n• **Tuyệt đối không lội qua chỗ nước chảy xiết** — chỉ cần nước ngang đầu gối mà chảy mạnh đã đủ cuốn con đi\n• **Không nghịch nước, không lội cho vui** ở chỗ ngập\n• Nếu buộc phải đi qua → **đi cùng người lớn**, đi thật chậm, men theo **chỗ cao**, bám vào tường hoặc lan can\n• Dùng một cây gậy **dò trước mặt đất** xem có hố hay không\n• Tránh xa **cột điện, dây điện rơi, biển hiệu** — nước dẫn điện rất nguy hiểm ⚡\n• Tốt nhất là **chờ nước rút** hoặc **tìm đường khác** cao hơn\n\n💡 **Mẹo nhớ:** \"Nước đục che hố — Không thấy đáy thì không bước\" 🌊\n\n⚠️ **Điều KHÔNG nên làm:**\n• Lội nước ngập để đi cho nhanh\n• Đạp xe qua vùng nước sâu\n• Đứng gần miệng cống đang hút nước xuống\n• Chơi đùa, thả thuyền giấy ở chỗ nước đang chảy\n\n❓ Đố con: vì sao con không nên bước xuống chỗ nước ngập mà con không nhìn thấy đáy? 🤔",
        ]],

        /* ===== [MỞ RỘNG] Qua đường ở chỗ khuất tầm nhìn ===== */
        [['khuat tam nhin', 'cho khuat', 'xe dang do', 'giua hai xe', 'goc cua', 'bi che khuat'], [
            "Con biết vì sao nhiều bạn nhỏ vẫn gặp tai nạn dù đã nhìn trước khi qua đường không? Vì các bạn ấy qua đường ở **chỗ bị che khuất** 🙈\n\nHãy tưởng tượng: con đứng nép giữa hai chiếc ô tô đang đỗ để chuẩn bị qua đường. Con nhìn ra — nhưng chiếc ô tô to đã che mất tầm nhìn, con chỉ thấy được một khoảng rất hẹp. Và điều đáng sợ hơn: **người lái xe đang chạy tới cũng hoàn toàn không nhìn thấy con**. Cả hai bên đều \"mù\", đến khi con bước ra thì đã quá muộn.\n\n**Những chỗ khuất nguy hiểm nhất:**\n🚗 **Giữa hai xe đang đỗ** ven đường\n🌳 Sau **bụi cây lớn, cột điện, bảng quảng cáo**\n↩️ Ngay tại **góc cua**, khúc quanh\n🚌 Trước đầu hoặc sau đuôi **xe buýt, xe tải** đang dừng\n🏠 Chỗ **ra khỏi ngõ hẹp** đâm ra đường lớn\n\n📝 **Con nhớ nhé:**\n• Luôn tìm chỗ **trống trải, nhìn rõ được cả hai đầu đường**\n• Ưu tiên **vạch kẻ đường dành cho người đi bộ**\n• Ra khỏi ngõ → **dừng lại hẳn**, nhô người ra nhìn kỹ rồi mới bước\n• Quy tắc chung: **nếu con không nhìn thấy xe thì xe cũng không nhìn thấy con**\n\n💡 **Mẹo nhớ:** \"Nhìn không rõ — Chưa được qua\" 👀\n\n⚠️ **Điều KHÔNG nên làm:**\n• Chui ra từ giữa hai chiếc xe đang đỗ\n• Qua đường ngay tại góc cua\n• Vừa ra khỏi ngõ vừa chạy thẳng xuống đường\n\n❓ Đố con: vì sao qua đường giữa hai chiếc ô tô đang đỗ lại nguy hiểm hơn qua đường ở chỗ trống? 🤔",
        ]],

        [['den vang'], [
            "Đèn vàng nghĩa là **chuẩn bị dừng lại**, chứ KHÔNG phải \"đi nhanh lên\" đâu con nhé 🟡. Rất nhiều người lớn hiểu nhầm điều này và cố phóng nhanh qua ngã tư khi đèn vàng — đó là lúc dễ xảy ra tai nạn nhất đó.\n\nĐèn vàng giống như lời nhắc nhở nhẹ nhàng: \"Sắp đến lượt dừng rồi, con hãy giảm tốc độ đi nào\". Nếu xe đã đi quá gần vạch dừng và không kịp phanh an toàn thì mới được đi tiếp; còn nếu còn xa thì phải dừng lại trước vạch.\n\n📝 **Con nhớ nhé:**\n• Đèn vàng = giảm tốc độ, chuẩn bị dừng\n• Nếu con đi bộ: **đứng yên trên vỉa hè**, chờ đèn xanh dành cho người đi bộ\n• Không bao giờ chạy vụt qua đường khi đèn vàng\n• Đèn vàng nhấp nháy liên tục = được đi nhưng phải **chú ý quan sát**\n\n💡 **Mẹo nhớ:** \"Vàng là chuẩn bị — Đỏ là dừng ngay — Xanh mới được đi\" 🚦\n\n⚠️ **Điều KHÔNG nên làm:**\n• Cố chạy nhanh để \"vượt kịp\" đèn vàng\n• Vừa qua đường vừa nhìn điện thoại\n• Chạy ào ra đường khi thấy đèn sắp đổi màu\n\n❓ Đố con nè: nếu con đang đứng chờ ở vỉa hè mà đèn chuyển sang màu vàng, con nên làm gì?",
        ]],

        [['den do'], [
            "Đèn đỏ nghĩa là **DỪNG LẠI HOÀN TOÀN** trước vạch kẻ đường 🔴. Đây là quy tắc nghiêm ngặt nhất của giao thông đó con.\n\nCon có biết vì sao không? Vì khi đèn của hướng con đang màu đỏ, thì đèn của hướng khác đang màu xanh — nghĩa là xe bên đó đang chạy qua. Nếu con vượt đèn đỏ, con sẽ đâm thẳng vào dòng xe đang chạy. Rất nguy hiểm!\n\n📝 **Con nhớ nhé:**\n• Đèn đỏ = dừng lại, **dù đường có vắng đến mấy**\n• Dừng **trước vạch trắng**, không được đè lên vạch\n• Vượt đèn đỏ là vi phạm luật, bị phạt tiền\n• Chỉ vài chục giây thôi — chờ một chút để an toàn cả đời\n\n💡 **Mẹo nhớ:** Đèn đỏ giống như bàn tay của mẹ giữ con lại — \"Khoan đã con ơi, nguy hiểm lắm!\" ✋\n\n⚠️ **Điều KHÔNG nên làm:**\n• Vượt đèn đỏ vì thấy \"không có xe nào\"\n• Rẽ phải khi đèn đỏ mà không có biển cho phép\n• Giục người lớn đi nhanh khi đang chờ đèn\n\n❓ Đố con: nếu ba mẹ chở con mà định vượt đèn đỏ, con sẽ nói gì với ba mẹ nào?",
        ]],

        [['den xanh'], [
            "Đèn xanh nghĩa là **được đi** 🟢 — nhưng con ơi, đây là điều nhiều bạn nhỏ hiểu chưa đủ đâu: đèn xanh KHÔNG có nghĩa là \"chắc chắn an toàn\".\n\nĐèn xanh chỉ là **được phép** đi thôi. Vẫn có thể có xe vượt đèn đỏ từ hướng khác, có xe cứu thương đang chạy khẩn cấp, hoặc có xe đang rẽ. Vì vậy trước khi bước chân xuống đường, con vẫn phải **nhìn trái — nhìn phải — nhìn trái lần nữa**.\n\n📝 **Con nhớ nhé:**\n• Đèn xanh = được đi, nhưng **vẫn phải quan sát**\n• Đợi xe đang chạy dở dừng hẳn rồi mới bước\n• Đi thẳng, đều bước, không chạy nhảy giữa đường\n• Nếu đèn xanh sắp hết mà con chưa qua kịp → đứng lại, chờ lượt sau\n\n💡 **Mẹo nhớ:** \"Đèn xanh — mắt vẫn nhanh\" 👀 (được đi nhưng mắt phải luôn quan sát)\n\n⚠️ **Điều KHÔNG nên làm:**\n• Thấy đèn xanh là chạy ào ra không nhìn gì\n• Vừa đi vừa nói chuyện, nghe nhạc, xem điện thoại\n• Đi ngoài vạch kẻ dù đèn đang xanh\n\n❓ Đố con: đèn xanh vừa bật lên, nhưng con thấy một chiếc ô tô vẫn đang chạy tới — con nên làm gì?",
        ]],

        [['den giao thong', 'den tin hieu', 'tin hieu den', 'may mau den'], [
            "Đèn giao thông có **3 màu**, mỗi màu là một mệnh lệnh con phải nhớ thật kỹ 🚦:\n\n🔴 **ĐỎ — Dừng lại!** Phải dừng hẳn trước vạch trắng. Dù đường vắng cũng không được vượt.\n🟡 **VÀNG — Chuẩn bị dừng.** Giảm tốc độ, sắp phải dừng rồi. Không phải để đi nhanh hơn!\n🟢 **XANH — Được đi.** Nhưng vẫn phải quan sát hai bên cẩn thận nhé.\n\nMột số nơi còn có **đèn riêng cho người đi bộ** — hình người màu đỏ (đứng yên) và hình người màu xanh (được đi). Nếu có đèn này, con phải theo đèn dành cho người đi bộ chứ không theo đèn xe nha.\n\n📝 **Con nhớ nhé:**\n• Thứ tự đèn từ trên xuống: **Đỏ → Vàng → Xanh**\n• Có chú cảnh sát giao thông ra hiệu → **nghe chú, không nghe đèn**\n• Đèn vàng nhấp nháy = được đi nhưng phải chú ý quan sát\n• Đèn hỏng, không sáng → giảm tốc độ, nhìn kỹ mọi hướng\n\n💡 **Mẹo nhớ:** \"Đỏ dừng — Vàng chờ — Xanh đi\" 🚦 Con hát theo nhịp cho dễ nhớ nha!\n\n⚠️ **Điều KHÔNG nên làm:**\n• Nghĩ đèn vàng là \"đi nhanh lên\"\n• Chỉ nhìn đèn mà không nhìn xe\n• Sang đường khi đèn người đi bộ đang đỏ\n\n❓ Đố con: nếu đèn đang xanh nhưng chú cảnh sát giơ tay ra hiệu dừng, con làm theo ai nào? 🤔",
        ]],

        [['mu bao hiem', 'doi mu', 'non bao hiem'], [
            "Mũ bảo hiểm là **\"chiếc khiên\" bảo vệ bộ não** của con đó ⛑️. Não là bộ phận quan trọng nhất — giúp con học bài, nhớ tên bạn bè, cảm nhận tình yêu thương. Một chiếc mũ đúng cách có thể giảm tới **70% nguy cơ chấn thương đầu** khi có va chạm.\n\n**Đội mũ đúng cách gồm 3 bước:**\n1️⃣ **Đội ngay ngắn** — vành mũ phía trước song song với lông mày, cách lông mày khoảng 2 đốt ngón tay. Không đội lệch ra sau gáy!\n2️⃣ **Cài quai chắc chắn** — quai phải ôm sát dưới cằm.\n3️⃣ **Kiểm tra độ khít** — đút vừa **2 ngón tay** giữa quai và cằm là chuẩn. Lỏng quá thì mũ văng ra khi ngã, chặt quá thì con khó thở.\n\n📝 **Con nhớ nhé:**\n• Mũ phải **vừa đầu con**, không mượn mũ quá to của người lớn\n• Chọn mũ có **tem CR** (tem hợp quy) — mũ đạt chuẩn\n• Đội mũ cả khi đi **quãng đường ngắn**, vì tai nạn không báo trước\n• Mũ đã từng bị va đập mạnh → **phải thay mũ mới**, dù nhìn bề ngoài vẫn lành\n\n💡 **Mẹo nhớ:** \"Đội — Cài — Khít\" — 3 bước, 3 giây, an toàn cả chuyến đi! ⛑️\n\n⚠️ **Điều KHÔNG nên làm:**\n• Đội mũ mà **không cài quai** — mũ sẽ văng ra ngay khi ngã, coi như chưa đội\n• Đội mũ lệch về sau, hở cả trán\n• Đội mũ lưỡi trai, mũ vải thay cho mũ bảo hiểm\n• Treo mũ ở tay lái \"cho tiện\"\n\n❓ Đố con: làm sao để biết quai mũ đã cài vừa khít? (Gợi ý: liên quan đến ngón tay đó 😉)",
        ]],

        [['sang duong', 'qua duong', 'bang qua duong', 'vach ke', 'loi di bo', 'nguoi di bo', 'di bo'], [
            "Sang đường là lúc **nguy hiểm nhất** với các bạn nhỏ đó con 🚸. Vì con nhỏ, người lái xe khó nhìn thấy con — nhất là khi con bị che khuất sau ô tô đang đỗ.\n\n**Quy tắc vàng khi sang đường: DỪNG — NHÌN — NGHE — ĐI**\n1️⃣ **DỪNG** lại ở mép vỉa hè, cách lề khoảng một bước chân.\n2️⃣ **NHÌN** trái → nhìn phải → **nhìn trái lần nữa** (vì xe từ bên trái sẽ tới chỗ con trước).\n3️⃣ **NGHE** tiếng động cơ, tiếng còi — có khi con nghe thấy xe trước cả khi nhìn thấy nó.\n4️⃣ **ĐI** thẳng, bước đều, mắt vẫn quan sát. Không chạy!\n\n**Vạch kẻ trắng** (vạch ngựa vằn 🦓) là lối đi dành riêng cho người đi bộ. Đi trên vạch, người lái xe sẽ **biết trước** là có người sang đường và chủ động nhường con.\n\n📝 **Con nhớ nhé:**\n• Luôn qua đường **đúng vạch kẻ**, hoặc cầu vượt / hầm đi bộ nếu có\n• **Nắm tay người lớn** khi qua đường — đây là điều quan trọng nhất\n• **Giơ tay xin đường** để người lái xe nhìn thấy con\n• Nhìn vào **mắt người lái xe** — chắc chắn họ đã thấy con rồi mới bước\n\n💡 **Mẹo nhớ:** \"Dừng — Nhìn — Nghe — Đi\" 🚸 Con đọc to 4 chữ này mỗi lần qua đường nha!\n\n⚠️ **Điều KHÔNG nên làm:**\n• **Chạy vụt** qua đường — dễ vấp ngã và tài xế không kịp phanh\n• Băng qua đường **giữa hai ô tô đang đỗ** — tài xế hoàn toàn không thấy con\n• Vừa qua đường vừa nghe nhạc, xem điện thoại\n• Đuổi theo quả bóng lăn ra đường 🏀 — bóng mất còn mua được, con thì không!\n\n❓ Đố con: vì sao khi qua đường con phải nhìn **bên trái trước** rồi mới nhìn bên phải? 🤔",
        ]],

        [['diem mu', 'xe tai', 'xe container', 'xe khach lon', 'xe bon'], [
            "Con ơi, đây là bài học **cứu mạng** đó — hãy nghe thật kỹ nhé! 🚛\n\n**\"Điểm mù\"** là những vùng xung quanh xe mà **người lái KHÔNG NHÌN THẤY**, dù họ có nhìn gương chiếu hậu. Xe càng to thì điểm mù càng lớn. Với xe tải, xe container, xe buýt — điểm mù có thể **rộng tới vài mét**, đủ để \"nuốt chửng\" cả một bạn nhỏ đứng ngay cạnh xe mà bác tài không hề hay biết!\n\n**4 điểm mù nguy hiểm nhất của xe tải:**\n• **Ngay trước đầu xe** — vì ca-bin cao, bác tài không thấy gì thấp hơn nắp capo\n• **Bên phải xe** — đây là vùng nguy hiểm NHẤT, rất nhiều tai nạn xảy ra khi xe rẽ phải\n• **Ngay sau đuôi xe** — bác tài lùi xe hoàn toàn không thấy con\n• **Sát hai bên hông xe**\n\n📝 **Con nhớ nhé:**\n• **Không bao giờ đứng, đi bộ hay đạp xe sát bên hông xe tải/xe buýt**\n• Quy tắc vàng: **nếu con không nhìn thấy gương chiếu hậu của xe → bác tài KHÔNG nhìn thấy con**\n• Xe tải rẽ phải, bánh sau **cắt vào trong** — con đứng gần sẽ bị cuốn vào\n• Muốn qua đường → đi vòng **phía trước xe, cách xa vài mét**, không luồn phía sau\n\n💡 **Mẹo nhớ:** \"Không thấy gương — Bác không thấy con\" 🪞\n\n⚠️ **Điều KHÔNG nên làm:**\n• Đạp xe men theo hông xe tải đang chờ đèn đỏ\n• Đi qua **ngay phía sau** xe đang lùi\n• Nghĩ rằng \"mình thấy xe thì xe cũng thấy mình\" — **SAI hoàn toàn!**\n\n❓ Đố con: làm sao để biết bác tài xe tải có nhìn thấy con hay không? 🤔",
        ]],

        [['xuong xe buyt', 'len xe buyt', 'xe buyt', 'xe bus', 'tram xe', 'xe khach'], [
            "Đi xe buýt vừa tiết kiệm, vừa bảo vệ môi trường 🚌 — nhưng con cần biết cách đi cho an toàn nhé!\n\n**🚏 Khi CHỜ xe ở trạm:**\n• Đứng **trên vỉa hè**, lùi lại sau vạch chờ, **không đứng sát mép đường**\n• **Không chen lấn, xô đẩy** khi xe tới\n• Chờ xe **dừng hẳn** rồi mới bước lên\n• Xếp hàng, nhường người già và em nhỏ lên trước\n\n**🚌 Khi Ở TRÊN xe:**\n• Tìm chỗ ngồi; nếu phải đứng thì **bám chặt tay vịn**\n• **Không thò đầu, thò tay ra cửa sổ**\n• Không chạy nhảy, đùa nghịch trong xe\n• Chuẩn bị xuống trước 1 trạm, nhưng **ngồi yên đến khi xe dừng hẳn**\n\n**🚶 Khi XUỐNG xe — phần nguy hiểm nhất:**\n• Xuống xong, **đứng yên trên vỉa hè**, chờ xe buýt **đi khuất hẳn**\n• Tuyệt đối **không băng qua đường ngay trước hoặc sau xe buýt** đang đỗ — vì xe buýt to che khuất tầm nhìn, xe máy đang chạy tới sẽ không thấy con, và con cũng không thấy họ!\n\n📝 **Con nhớ nhé:** Xe buýt đi khuất rồi mới được nghĩ đến chuyện qua đường 🚌\n\n💡 **Mẹo nhớ:** \"Xe đi khuất — Mắt nhìn rõ — Mới qua đường\" 👀\n\n⚠️ **Điều KHÔNG nên làm:**\n• Chạy đuổi theo xe buýt đang chuyển bánh\n• Băng qua đường ngay phía trước đầu xe buýt\n• Đứng sát mép đường khi chờ xe\n\n❓ Đố con: sau khi xuống xe buýt, vì sao con phải chờ xe **đi khuất hẳn** rồi mới qua đường? 🤔",
        ]],

        [['troi mua', 'duong tron', 'mua bao', 'ngap nuoc'], [
            "Trời mưa, đường phố trở nên **nguy hiểm gấp nhiều lần** đó con ☔\n\n**Vì sao mưa lại nguy hiểm hơn?**\n• Đường **trơn trượt** — xe phanh lâu hơn bình thường **gấp đôi**\n• Người lái xe **nhìn kém** vì mưa che kính, kính mờ\n• Con che ô, mặc áo mưa trùm đầu → **tầm nhìn của con cũng bị hạn chế**\n• Áo mưa màu tối làm con **khó bị nhìn thấy**\n• Nước đọng che mất **ổ gà, cống, hố sâu**\n\n📝 **Con nhớ nhé:**\n• Mặc áo mưa **màu sáng** (vàng, cam, đỏ) — người lái xe dễ thấy con\n• Áo mưa phải **gọn**, không để tà áo cuốn vào bánh xe\n• Che ô nhưng vẫn phải **nhìn rõ hai bên**, không cụp ô che kín mặt\n• Đi **chậm hơn**, chờ lâu hơn trước khi qua đường\n• **Không đi qua vùng nước ngập sâu** — có thể có cống mất nắp, hố sâu bên dưới\n• Mưa to có sấm sét → **tìm chỗ trú**, không đứng dưới gốc cây, cột điện ⚡\n\n💡 **Mẹo nhớ:** \"Trời mưa — Đi chậm — Mặc sáng — Nhìn kỹ\" ☔\n\n⚠️ **Điều KHÔNG nên làm:**\n• Chạy vụt qua đường cho đỡ ướt — đây là lúc dễ trượt ngã nhất!\n• Nghịch nước, lội vào chỗ ngập\n• Trùm áo mưa kín đầu che hết tầm nhìn\n\n❓ Đố con: vì sao trời mưa con nên mặc áo mưa **màu sáng** thay vì màu đen? 🤔",
        ]],

        [['di bo ban dem', 'ban dem', 'buoi toi', 'trong toi', 'phan quang', 'thieu anh sang'], [
            "Ban đêm, con **gần như tàng hình** trước mắt người lái xe đó! 🌙\n\nCon thử tưởng tượng: người lái xe chỉ nhìn được trong vùng đèn pha chiếu tới. Một bạn nhỏ mặc áo tối màu đi ven đường, họ chỉ thấy con khi còn cách **khoảng 20-30 mét** — quá gần để kịp phanh. Nhưng nếu con mặc **áo sáng màu hoặc có dải phản quang**, họ có thể thấy con từ **hơn 100 mét** — xa gấp 4-5 lần! Chỉ một chiếc áo thôi mà khác biệt cả mạng sống đó con 💛\n\n📝 **Con nhớ nhé:**\n• Mặc **áo sáng màu** hoặc áo có **dải phản quang** khi ra đường buổi tối\n• Xe đạp phải có **đèn trước (trắng), đèn sau (đỏ)** và tấm phản quang\n• Đi ở nơi **có đèn đường**, tránh chỗ tối om\n• **Luôn đi cùng người lớn** khi trời tối\n• Nếu bị đèn pha xe chiếu thẳng vào mắt → **nhìn xuống mép đường**, đừng nhìn thẳng vào đèn (sẽ bị loá, không thấy gì trong vài giây)\n\n💡 **Mẹo nhớ:** \"Tối trời — Sáng áo — Rõ đường về\" 🌙✨\n\n⚠️ **Điều KHÔNG nên làm:**\n• Mặc đồ đen, đồ tối màu đi bộ ven đường ban đêm\n• Đi xe đạp không đèn\n• Chơi đùa ngoài đường khi trời đã tối\n\n❓ Đố con: mặc áo sáng màu giúp người lái xe nhìn thấy con từ xa hơn bao nhiêu lần? 🤔",
        ]],

        [['nga tu', 'nga ba', 'nga nam', 'vong xuyen', 'giao lo'], [
            "Ngã tư là nơi **nhiều tai nạn nhất** trên đường phố đó con 🛣️ — vì đây là chỗ xe từ **4 hướng** cùng gặp nhau!\n\n**Ngã tư có đèn giao thông** thì dễ rồi: con chỉ cần theo đèn dành cho **người đi bộ**, đi đúng vạch kẻ, và vẫn quan sát hai bên.\n\n**Ngã tư KHÔNG có đèn** mới khó! Lúc này phải quan sát cực kỳ cẩn thận, vì mỗi xe đi theo một ý. Con hãy tìm **cầu vượt, hầm đi bộ** nếu có, hoặc **đi cùng người lớn**.\n\n**Vòng xuyến (bùng binh)** 🔄 — là vòng tròn ở giữa ngã tư, xe chạy vòng quanh theo chiều **ngược kim đồng hồ**. Xe **đang trong vòng xuyến được ưu tiên**, xe muốn vào phải nhường. Vòng xuyến rất khó cho người đi bộ vì xe đến từ nhiều hướng — con nên qua đường ở vạch kẻ **cách xa vòng xuyến** một chút.\n\n📝 **Con nhớ nhé:**\n• Ngã tư = **nguy hiểm nhất** → chậm lại, nhìn kỹ **cả 4 hướng**\n• Chú ý xe **rẽ phải** — nhiều xe rẽ mà không xi-nhan, không nhìn người đi bộ\n• Xe **rẽ trái** cũng cắt qua đường con đang đi\n• Có cầu vượt / hầm đi bộ → **luôn ưu tiên dùng**, an toàn 100%\n\n💡 **Mẹo nhớ:** \"Ngã tư bốn hướng — Bốn lần nhìn\" 👀\n\n⚠️ **Điều KHÔNG nên làm:**\n• Qua đường **chéo góc** cắt ngang ngã tư\n• Chỉ nhìn xe đi thẳng mà quên xe đang rẽ\n• Đứng chờ **giữa ngã tư**\n\n❓ Đố con: ở ngã tư, ngoài xe đi thẳng, con còn phải chú ý loại xe nào nữa? 🤔",
        ]],

        [['via he', 'le duong'], [
            "Vỉa hè là **\"ngôi nhà\" của người đi bộ** đó con 🚶 — nơi an toàn nhất để con đi lại trên phố!\n\n**Khi CÓ vỉa hè:**\n• Luôn đi **trên vỉa hè**, đi phía **trong**, tránh xa mép đường\n• Đi **thẳng hàng**, không dàn hàng ngang chắn lối người khác\n• Không vừa đi vừa nhìn điện thoại 📱\n\n**Khi KHÔNG có vỉa hè** (hoặc vỉa hè bị chiếm dụng):\n• Đi **sát mép đường bên trái**, tức là **ngược chiều xe chạy**\n• Vì sao? Vì như vậy con **nhìn thấy xe đang đi tới**, và có thể tránh kịp. Nếu đi cùng chiều, xe từ sau lưng lao tới con hoàn toàn không biết!\n• Đi **hàng một**, người lớn đi phía ngoài để che chắn cho con\n\n📝 **Con nhớ nhé:**\n• Có vỉa hè → **đi vỉa hè**\n• Không có vỉa hè → **đi mép trái, ngược chiều xe**, để nhìn thấy xe\n• Ra khỏi ngõ hẹp → **dừng lại, nhìn kỹ** rồi mới bước ra đường lớn\n• Vỉa hè bị chiếm (hàng quán, xe đỗ) → đi vòng ra ngoài **thật cẩn thận**, không chạy\n\n💡 **Mẹo nhớ:** \"Không có vỉa hè — Đi bên trái để **thấy** xe\" 👀\n\n⚠️ **Điều KHÔNG nên làm:**\n• Đi bộ **giữa lòng đường** dù đường vắng\n• Đi sát mép đường bên phải khi không có vỉa hè (xe từ sau lưng, con không thấy)\n• Vừa đi vừa dán mắt vào điện thoại\n\n❓ Đố con: khi không có vỉa hè, vì sao con phải đi **ngược chiều** với xe? 🤔",
        ]],

        [['choi tren duong', 'da bong', 'bong lan ra duong', 'bong lan', 'tha dieu', 'chay nhay tren duong', 'duoi bat'], [
            "Con ơi, mình nói thật lòng: **lòng đường KHÔNG PHẢI sân chơi** ⛔\n\nMình biết đường trước nhà có khi rộng và vắng, chơi ở đó rất tiện. Nhưng con biết không — rất nhiều bạn nhỏ gặp tai nạn thương tâm **ngay trước cửa nhà mình**, khi đang chơi đùa. Vì:\n• Xe có thể xuất hiện **bất ngờ**, con đang mải chơi không kịp phản ứng\n• Khi con **chạy theo quả bóng**, con **không nhìn đường**\n• Người lái xe không ngờ có trẻ em lao ra, nên **không kịp phanh**\n\n**Đặc biệt nguy hiểm:**\n⚽ **Đá bóng dưới lòng đường** — bóng lăn ra, con chạy theo theo phản xạ\n🪁 **Thả diều gần đường điện** — dây diều dẫn điện, cực kỳ nguy hiểm ⚡\n🛹 Trượt patin, đuổi bắt trên đường\n🚴 Đạp xe lạng lách giữa dòng xe\n\n📝 **Con nhớ nhé — QUY TẮC QUAN TRỌNG NHẤT:**\nNếu **quả bóng lăn ra đường** → **DỪNG LẠI!** Đừng chạy theo ngay. Hãy **nhờ người lớn** lấy giúp, hoặc **dừng — nhìn — nghe** cẩn thận rồi mới ra.\n\n💡 **Mẹo nhớ:** \"Bóng mất còn mua được — Con thì không\" ⚽💛\n\n⚠️ **Chơi ở đâu mới đúng?**\n• Công viên, sân trường, sân chơi 🏞️\n• Sân nhà, khu vui chơi\n• Bất cứ đâu **không có xe chạy qua**\n\n❓ Đố con: quả bóng của con lăn ra giữa đường — con làm gì đầu tiên? 🤔",
        ]],

        [['bi lac', 'lac duong', 'so khan cap', 'goi cuu ho', 'goi giup do', '113', '115'], [
            "Nếu con **bị lạc** hoặc gặp chuyện nguy hiểm, đừng hoảng sợ nhé — mình chỉ con cách xử lý 💛\n\n**🧍 Nếu con bị lạc:**\n1️⃣ **ĐỨNG YÊN TẠI CHỖ** — đừng chạy lung tung tìm ba mẹ. Ba mẹ sẽ quay lại đúng chỗ con đứng. Con càng chạy, càng khó tìm nhau!\n2️⃣ **Tìm người đáng tin để nhờ giúp:** chú **cảnh sát** 👮, chú **bảo vệ**, cô **nhân viên bán hàng** đang mặc đồng phục, hoặc một **bà mẹ đang dắt con nhỏ**.\n3️⃣ **Nói rõ:** \"Con bị lạc ba mẹ. Con tên là… Số điện thoại của mẹ con là…\"\n\n**📞 Số điện thoại khẩn cấp phải thuộc lòng:**\n• **113** — Cảnh sát 👮\n• **114** — Cứu hoả 🚒\n• **115** — Cấp cứu y tế 🚑\n\n📝 **Con nhớ nhé — học thuộc ngay hôm nay:**\n• **Họ tên đầy đủ** của con\n• **Số điện thoại của ba hoặc mẹ** ☎️\n• **Địa chỉ nhà** con\n• Tên **trường học** của con\n\n💡 **Mẹo nhớ:** \"Lạc thì **đứng yên** — Tìm chú **cảnh sát** — Nhớ **số mẹ**\" 💛\n\n⚠️ **Điều KHÔNG nên làm:**\n• **Chạy lung tung** tìm ba mẹ\n• Đi theo **người lạ** dù họ nói \"chú đưa con về\" hay cho quà, cho kẹo 🍬\n• Lên xe của người lạ\n• Khóc và đứng im không nhờ ai giúp\n\n❓ Đố con: con có nhớ số điện thoại của mẹ không? Thử đọc thuộc lòng ngay bây giờ nhé! 📞",
        ]],

        [['toc do', 'chay nhanh', 'phong nhanh', 'gioi han toc do'], [
            "Tốc độ là **kẻ thù nguy hiểm nhất** trên đường đó con 🏎️💨\n\nCon thử hình dung con số này nhé — nó sẽ làm con giật mình:\n• Xe chạy **30 km/h** đâm phải người → khả năng sống sót khoảng **90%**\n• Xe chạy **50 km/h** đâm phải người → khả năng sống sót chỉ còn khoảng **50%**\n• Xe chạy **60 km/h** trở lên → **rất khó sống sót**\n\nChỉ nhanh hơn một chút thôi mà khác biệt cả **sự sống và cái chết**. Đó là lý do vì sao gần **trường học** luôn có biển giới hạn tốc độ 🚸 — để bảo vệ chính các con!\n\n**Vì sao xe nhanh lại nguy hiểm hơn nhiều?**\n• Xe càng nhanh → **quãng đường phanh càng dài**. Xe 60 km/h cần khoảng **40 mét** mới dừng được — dài bằng cả sân bóng rổ!\n• Người lái **ít thời gian** để phản ứng\n• Va chạm mạnh hơn **rất nhiều lần**\n\n📝 **Con nhớ nhé:**\n• Biển **tròn viền đỏ có số** = **tốc độ tối đa** được phép chạy\n• Khu vực **trường học, khu dân cư** → xe phải đi thật chậm\n• Con hãy là **\"cảnh sát nhỏ\"** — nhắc ba mẹ đi chậm lại khi chở con nhé! 👮\n\n💡 **Mẹo nhớ:** \"Chậm một giây — Không mất cả đời\" ⏱️\n\n⚠️ **Điều KHÔNG nên làm:**\n• Giục ba mẹ \"đi nhanh lên cho kịp giờ\"\n• Thấy đường vắng là nghĩ chạy nhanh cũng không sao\n\n❓ Đố con: xe chạy 60 km/h cần bao xa mới phanh dừng được? 🤔",
        ]],

        [['canh sat giao thong', 'chu canh sat', 'cong an giao thong', 'chu cong an'], [
            "Chú cảnh sát giao thông 👮 là người **giữ trật tự và bảo vệ an toàn** cho mọi người trên đường đó con.\n\nĐiều quan trọng nhất con phải nhớ: **hiệu lệnh của chú cảnh sát có ưu tiên CAO NHẤT** — cao hơn cả đèn giao thông và biển báo! Nghĩa là nếu đèn đang xanh nhưng chú giơ tay ra hiệu dừng, thì **mọi người phải dừng**. Vì sao? Vì có thể phía trước đang có tai nạn, có đoàn xe ưu tiên, hoặc đèn bị hỏng mà con không biết.\n\n**Vài hiệu lệnh cơ bản:**\n• Chú **giơ tay thẳng lên trời** ✋ → tất cả mọi hướng phải **dừng lại**\n• Chú **dang ngang hai tay** → xe phía trước và sau chú phải dừng; xe hai bên được đi\n• Chú **vẫy tay** về hướng nào → hướng đó được đi\n\n📝 **Con nhớ nhé:**\n• Thứ tự ưu tiên: **Cảnh sát > Đèn giao thông > Biển báo > Vạch kẻ đường**\n• Nếu con bị lạc đường hoặc gặp nguy hiểm → **tìm chú cảnh sát để nhờ giúp đỡ**\n• Chú cảnh sát là **người bạn tốt**, không có gì phải sợ cả\n\n💡 **Mẹo nhớ:** \"Chú giơ tay — Cả đường dừng lại\" ✋\n\n⚠️ **Điều KHÔNG nên làm:**\n• Cứ đi theo đèn xanh trong khi chú ra hiệu dừng\n• Sợ hãi, chạy trốn khi thấy chú cảnh sát\n\n❓ Đố con: đèn đang **xanh** nhưng chú cảnh sát giơ tay bảo dừng — con nghe ai nào? 🤔",
        ]],

        [['qua duong sat', 'duong sat', 'tau hoa', 'duong ray', 'xe lua', 'duong tau'], [
            "Đường sắt là nơi **cực kỳ nguy hiểm** con nhé 🚂. Con có biết vì sao không?\n\nMột đoàn tàu nặng hàng nghìn tấn — nặng gấp cả nghìn lần ô tô! Khi tài xế nhìn thấy con và đạp phanh, tàu vẫn **trượt thêm cả trăm mét** mới dừng được. Nghĩa là tàu **không thể tránh con**, chỉ có con tránh tàu thôi. Tàu cũng chạy nhanh và êm hơn con tưởng — khi con nghe thấy tiếng thì nó đã rất gần rồi.\n\n📝 **Con nhớ nhé:**\n• Đến gần đường sắt: **DỪNG LẠI — NHÌN — LẮNG NGHE**\n• Thấy **đèn đỏ nhấp nháy**, **chuông reo**, hoặc **rào chắn hạ xuống** → đứng chờ, tuyệt đối không qua\n• Chờ tàu qua rồi, phải đợi rào chắn **mở hẳn** mới được đi\n• Không bao giờ chui qua hoặc luồn dưới rào chắn\n\n💡 **Mẹo nhớ:** \"Tàu không tránh được con — Chỉ con tránh được tàu\" 🚂\n\n⚠️ **Điều KHÔNG nên làm:**\n• **Chơi đùa, đi bộ, chụp ảnh trên đường ray** — rất nhiều tai nạn thương tâm xảy ra vì điều này\n• Cố băng qua khi chuông đã reo (\"chắc còn kịp\")\n• Đặt vật gì lên đường ray\n• Nghe nhạc bằng tai nghe khi đi gần đường sắt\n\n❓ Đố con: vì sao tàu hoả **không thể phanh gấp** như ô tô được? 🤔",
        ]],

        [['xe cuu thuong', 'cuu thuong', 'cuu hoa', 'xe uu tien', 'canh sat', 'cuu ho', 'uu tien'], [
            "Khi con nghe tiếng **còi hú** và thấy **đèn xanh đỏ nhấp nháy** — đó là xe ưu tiên đang làm nhiệm vụ khẩn cấp 🚨.\n\n**Các xe ưu tiên gồm:**\n🚑 **Xe cứu thương** — đang chở người bệnh nguy kịch đi cấp cứu\n🚒 **Xe cứu hoả** — đang chạy đi dập lửa cứu người\n🚓 **Xe cảnh sát** — đang đi làm nhiệm vụ khẩn cấp\n\nCon ơi, hãy nghĩ thế này: **trên chiếc xe cứu thương đó có thể là ông bà của một bạn nhỏ nào đó**, đang rất cần đến bệnh viện. Mỗi giây nhường đường của chúng ta có thể cứu được một mạng người đó!\n\n📝 **Con nhớ nhé:**\n• Xe ưu tiên **được đi trước tất cả**, kể cả khi đèn đỏ\n• Con đang **đi bộ** → đứng gọn lên vỉa hè, chờ xe qua hẳn\n• Con đang **ngồi sau xe của ba mẹ** → nhắc ba mẹ tấp vào lề nhường đường\n• Nhường xong, đợi xe đi qua **hẳn** rồi mới tiếp tục đi\n\n💡 **Mẹo nhớ:** \"Nghe còi hú — Nhường đường mau — Cứu một người — Vui cả nhà\" 💛\n\n⚠️ **Điều KHÔNG nên làm:**\n• Cố chen lấn, không nhường đường\n• **Chạy theo sau xe cứu thương** để \"đi nhanh hơn\" — rất nguy hiểm\n• Đứng lại giữa đường xem, gây cản trở\n\n❓ Đố con: nếu con đang đi bộ mà nghe tiếng còi xe cứu thương phía sau, con sẽ làm gì đầu tiên? 🤔",
        ]],

        [['stop'], [
            "Biển **STOP** 🛑 là một trong những biển báo quan trọng nhất, và cũng là biển **dễ nhận ra nhất thế giới**!\n\nNó có hình **bát giác (8 cạnh)** màu đỏ, viền trắng, chữ STOP to rõ. Con có biết vì sao lại chọn hình 8 cạnh không? Vì đó là hình dạng **độc nhất** — không biển nào khác có hình này. Kể cả khi trời tối, biển bị bẩn hay tuyết phủ mờ chữ, người lái xe **chỉ cần nhìn hình dáng là biết ngay** đó là biển STOP. Thông minh phải không con! 💡\n\n📝 **Con nhớ nhé:**\n• STOP = **dừng lại HẲN**, bánh xe đứng yên hoàn toàn (không phải chỉ chạy chậm lại)\n• Dừng, quan sát kỹ, thấy **thật sự an toàn** rồi mới đi tiếp\n• Áp dụng cho **tất cả**: ô tô, xe máy, xe đạp\n• Thường đặt ở nơi tầm nhìn bị che khuất, hoặc trước đường sắt\n\n💡 **Mẹo nhớ:** \"Tám cạnh đỏ tươi — Dừng ngay tức thời\" 🛑\n\n⚠️ **Điều KHÔNG nên làm:**\n• Chỉ **chạy chậm lại** rồi đi luôn — như vậy là sai, phải dừng HẲN\n• Dừng quá vạch, nhô đầu xe ra đường\n\n❓ Đố con: biển STOP có mấy cạnh, và vì sao người ta chọn hình dáng đặc biệt như vậy? 🤔",
        ]],

        [['bien bao', 'bien cam', 'bien nguy hiem', 'bien chi dan', 'y nghia bien', 'bien hieu lenh'], [
            "Biển báo giao thông giống như **\"ngôn ngữ của con đường\"** vậy đó con 🚸. Chỉ cần nhìn **hình dạng và màu sắc**, con đoán được ý nghĩa mà chưa cần đọc chữ!\n\n**5 nhóm biển báo chính ở Việt Nam:**\n\n🔴 **Biển CẤM** — hình **tròn**, viền đỏ, nền trắng.\nÝ nghĩa: điều bị **cấm làm**. Ví dụ: cấm đi ngược chiều, cấm xe đạp, cấm rẽ trái.\n\n🔺 **Biển NGUY HIỂM** — hình **tam giác**, viền đỏ, nền vàng.\nÝ nghĩa: **cảnh báo** phía trước có nguy hiểm, hãy đi chậm và chú ý. Ví dụ: khúc cua gấp, có trẻ em qua đường 🚸, đường trơn trượt.\n\n🔵 **Biển HIỆU LỆNH** — hình **tròn**, nền **xanh dương**.\nÝ nghĩa: điều **bắt buộc phải làm**. Ví dụ: hướng đi bắt buộc, đường dành cho người đi bộ.\n\n🟦 **Biển CHỈ DẪN** — hình **vuông/chữ nhật**, nền xanh dương.\nÝ nghĩa: **thông tin hữu ích**. Ví dụ: bệnh viện phía trước, nơi đỗ xe, lối sang đường.\n\n⬜ **Biển PHỤ** — hình chữ nhật nhỏ, đặt dưới biển chính để giải thích thêm.\n\n📝 **Con nhớ nhé:**\n• **Tròn đỏ** = cấm | **Tam giác** = cảnh báo | **Tròn xanh** = bắt buộc | **Vuông xanh** = chỉ dẫn\n• Biển 🚸 (trẻ em qua đường) thường đặt gần **trường học** của con đó\n• Thấy biển lạ → chụp ảnh gửi mình, mình giải thích cho con! 📷\n\n💡 **Mẹo nhớ:** \"Đỏ là cấm — Vàng là lo — Xanh là chỉ cho biết đường\" 🚦\n\n⚠️ **Điều KHÔNG nên làm:**\n• Bỏ qua biển báo vì \"không hiểu\"\n• Nghĩ biển báo chỉ dành cho người lớn — biển dành cho **tất cả mọi người**\n\n❓ Đố con: biển hình **tam giác viền đỏ** vẽ hai bạn nhỏ đang đi — con đoán xem biển đó có ý nghĩa gì? 🤔",
        ]],

        [['xe dap'], [
            "Xe đạp là người bạn tuyệt vời — vừa khoẻ người, vừa bảo vệ môi trường 🚲. Nhưng đi xe đạp trên đường cũng có luật riêng đó con!\n\n**Trước khi đi, kiểm tra xe:**\n• **Phanh** có ăn không? (bóp thử cả hai phanh)\n• **Lốp** có đủ hơi không?\n• **Chuông** có kêu không?\n• Xe có **vừa với chiều cao** của con không? (ngồi lên yên, chân phải chạm được đất)\n\n**Khi đi trên đường:**\n📝 **Con nhớ nhé:**\n• Đi **sát lề bên phải**, đi hàng một, **không dàn hàng ngang** trò chuyện\n• **Không buông cả hai tay**, không chở thêm bạn phía sau\n• Muốn rẽ → **giơ tay xin đường** trước 5-10 mét (rẽ trái giơ tay trái, rẽ phải giơ tay phải)\n• Đi **xe đạp điện** thì **BẮT BUỘC đội mũ bảo hiểm** ⛑️\n• Buổi tối phải có **đèn** hoặc mặc **áo sáng màu/phản quang** để mọi người thấy con\n\n💡 **Mẹo nhớ:** \"Sát lề — Hàng một — Xin đường — Đội mũ\" 🚲\n\n⚠️ **Điều KHÔNG nên làm:**\n• **Bám vào xe ô tô, xe máy** để được kéo đi — cực kỳ nguy hiểm!\n• Đi xe đạp trên **đường cao tốc** (bị cấm hoàn toàn)\n• Vừa đạp xe vừa nghe điện thoại, cầm ô che mưa\n• Lạng lách, đánh võng, thả hai tay khoe với bạn\n• Đi ngược chiều \"cho gần\"\n\n❓ Đố con: khi muốn rẽ **trái**, con phải giơ tay bên nào ra hiệu nào? 🤔",
        ]],

        [['o to', 'oto', 'xe hoi', 'day an toan', 'tui khi', 'ghe truoc', 'ghe sau'], [
            "Ngồi trong ô tô cũng có quy tắc an toàn riêng đó con 🚗\n\n**Điều quan trọng NHẤT: thắt dây an toàn** — ngay khi vừa ngồi vào ghế, trước cả khi xe lăn bánh. Dây an toàn giống như **cái ôm chặt của mẹ** 🤗 — khi xe phanh gấp, nó giữ con lại đúng chỗ, không bị lao về phía trước.\n\n📝 **Con nhớ nhé:**\n• **Luôn thắt dây an toàn** — kể cả ngồi ghế sau, kể cả đi gần\n• Trẻ em dưới 10 tuổi hoặc dưới 1m35 nên **ngồi ghế SAU** — vì ghế trước có **túi khí**, khi bung ra rất mạnh, có thể làm con bị thương\n• **Không thò đầu, thò tay ra cửa sổ** 🚫\n• Không tự ý mở cửa khi xe đang chạy\n• Xuống xe: mở cửa phía **lề đường**, nhìn kỹ phía sau xem có xe máy đang tới không\n\n💡 **Mẹo nhớ:** \"Lên xe — Thắt dây — Mới đi\" 🚗\n\n⚠️ **Điều KHÔNG nên làm:**\n• Nghĩ \"đi gần thôi, không cần thắt dây\" — đa số tai nạn xảy ra **gần nhà**!\n• Thò tay vẫy ra ngoài cửa sổ\n• Đứng dậy, nhảy nhót trong xe đang chạy\n• Ngồi ghế trước khi con còn nhỏ\n\n❓ Đố con: vì sao trẻ em không nên ngồi ghế trước của ô tô? 🤔",
        ]],

        [['xe may', 'ngoi sau', 'xe gan may'], [
            "Ngồi sau xe máy đúng cách sẽ giúp con an toàn hơn rất nhiều đó 🛵\n\nCon là \"người đồng hành\" của ba mẹ trên xe — ngồi đúng cách thì ba mẹ lái cũng vững hơn, cả nhà cùng an toàn!\n\n📝 **Con nhớ nhé:**\n• **Đội mũ bảo hiểm và cài quai** — bắt buộc, không có ngoại lệ ⛑️\n• **Ôm chặt eo người lớn** hoặc bám chắc vào tay nắm phía sau\n• Ngồi **thẳng, ngay ngắn**, hai chân đặt lên **gác chân** (không thả lủng lẳng — dễ vướng vào bánh xe!)\n• **Không đùa nghịch**, không ngọ nguậy, không quay ngang quay ngửa\n• Lên/xuống xe chỉ khi xe đã **dừng hẳn**, và lên xuống ở phía **lề đường**\n\n💡 **Mẹo nhớ:** \"Mũ chắc — Ôm chặt — Chân gác — Ngồi yên\" 🛵\n\n⚠️ **Điều KHÔNG nên làm:**\n• Ngồi mà không đội mũ bảo hiểm, dù đi gần\n• Thả hai chân lủng lẳng gần bánh xe\n• Giơ tay vẫy bạn, nghịch điện thoại khi xe đang chạy\n• Ngủ gật trên xe mà không ai giữ\n• Đứng lên yên xe hoặc ngồi quay ngược\n\n❓ Đố con: vì sao hai chân phải đặt lên gác chân chứ không được thả lủng lẳng? 🤔",
        ]],

        /* ===== [MỞ RỘNG] Nhường đường & văn hoá giao thông ===== */
        [['nhuong duong', 'van hoa giao thong', 'xep hang', 'xa rac', 'bam coi', 'lich su khi di duong'], [
            "Đi đường an toàn đã giỏi rồi, nhưng còn một điều nữa khiến con trở thành **người đi đường thật sự đẹp** — đó là biết **nhường nhịn và cư xử lịch sự** 💚\n\n**Con nên nhường đường cho ai?**\n👵 **Người già** — chân yếu, đi chậm, phản ứng không còn nhanh\n👶 **Em nhỏ** — chưa biết quan sát giỏi như con\n🤰 **Cô đang mang bầu**\n♿ **Người khuyết tật** — nhất là người khiếm thị đi bằng gậy dò đường\n🚑 **Xe ưu tiên** đang hú còi làm nhiệm vụ\n\nCon thấy không, nhường đường chỉ mất của con vài giây thôi, nhưng với người kia có khi là cả sự an toàn của họ.\n\n📝 **Con nhớ nhé — văn hoá giao thông đẹp:**\n• **Xếp hàng** khi chờ xe buýt, chờ qua đường — không chen lấn\n• **Không bấm còi** inh ỏi hay hò hét làm người khác giật mình\n• **Không xả rác** ra đường; giữ lại và bỏ vào thùng rác\n• Được ai nhường đường thì **gật đầu, nói cảm ơn** 🙏\n• Thấy cụ già hay em nhỏ muốn qua đường → **nhờ người lớn giúp đỡ họ**\n• Không cười cợt, trêu chọc người đi đường\n\n💡 **Mẹo nhớ:** \"Nhường một bước — Cả đường cùng vui\" 💚\n\n⚠️ **Điều KHÔNG nên làm:**\n• Chen ngang khi mọi người đang xếp hàng\n• Vứt vỏ bánh kẹo, chai nước xuống đường\n• Đi dàn hàng ngang chắn lối người khác\n• Nghịch bấm còi xe của ba mẹ cho vui\n\n❓ Đố con: hôm nay trên đường đi học, con có thể nhường đường hoặc giúp đỡ ai không? 🤔",
        ]],

        [['an toan giao thong', 'an toan khi di duong', 'an toan la gi'], [
            "\"An toàn giao thông là hạnh phúc của mọi nhà\" 💛 — câu này con nghe quen phải không?\n\nMình tóm tắt cho con **5 điều vàng** để luôn an toàn trên đường nhé:\n\n1️⃣ **Đi bộ trên vỉa hè.** Không có vỉa hè thì đi sát mép đường bên phải, ngược chiều xe chạy để nhìn thấy xe đang tới.\n\n2️⃣ **Qua đường đúng vạch kẻ.** Nhớ quy tắc **Dừng — Nhìn — Nghe — Đi** và nắm tay người lớn.\n\n3️⃣ **Tuân thủ đèn và biển báo.** Đỏ dừng, vàng chờ, xanh đi (nhưng vẫn quan sát).\n\n4️⃣ **Đội mũ bảo hiểm** khi ngồi xe máy, xe đạp điện. **Thắt dây an toàn** khi ngồi ô tô.\n\n5️⃣ **Không đùa nghịch trên đường.** Đường không phải sân chơi — đá bóng, đuổi bắt, chạy nhảy hãy để dành cho công viên và sân trường nhé!\n\n💡 **Mẹo nhớ:** \"Chậm một giây — Không mất cả đời\" ⏱️\n\n📝 **Con còn có thể giúp cả nhà nữa đó:** nhắc ba mẹ đội mũ bảo hiểm, nhắc thắt dây an toàn, nhắc không vượt đèn đỏ. Con là **\"cảnh sát nhỏ\"** của gia đình! 👮\n\n❓ Con muốn mình kể chi tiết phần nào nè? Đèn giao thông 🚦, biển báo 🚸, đội mũ ⛑️, hay cách sang đường an toàn?",
        ]],

        [['ban la ai', 'may la ai', 'cau la ai', 'em la ai', 'gioi thieu'], [
            "Mình là **AI Gia sư** 🤖 của ứng dụng **Siêu Nhí An Toàn Giao Thông**!\n\nMình sinh ra để làm một việc thôi: giúp con đi đường **thật an toàn**, để ngày nào con cũng về nhà bình an với ba mẹ 💛\n\n**Mình có thể giúp con:**\n🚦 Giải thích **đèn giao thông** — đỏ, vàng, xanh nghĩa là gì\n🚸 Dạy con đọc **biển báo** — biển cấm, biển nguy hiểm, biển chỉ dẫn\n⛑️ Hướng dẫn **đội mũ bảo hiểm** đúng cách\n🚶 Chỉ con cách **sang đường an toàn**\n🚲 Mẹo **đi xe đạp** đúng luật\n🚑 Cách **nhường đường xe ưu tiên**\n🚂 An toàn khi qua **đường sắt**\n👮 Hiểu **hiệu lệnh của chú cảnh sát**\n\n**Mẹo dùng mình cho vui:**\n• Bấm nút 🎤 để **nói chuyện** với mình thay vì gõ chữ\n• Bấm 🔊 dưới mỗi câu trả lời để mình **đọc to** cho con nghe\n• Mỗi câu trả lời mình đều kèm **hình ảnh minh hoạ** cho dễ hiểu 📷\n\n❓ Nào, hôm nay con muốn học điều gì trước? 😄",
        ]],

        [['xin chao', 'chao ai', 'chao ban', 'hello', 'chao buoi', 'alo'], [
            "Chào con! 👋 Mình là **AI Gia sư** đây, rất vui được gặp con hôm nay! 😄\n\nCon có thể hỏi mình bất cứ điều gì về an toàn giao thông nhé. Ví dụ như:\n🚦 \"Đèn vàng có được đi không ạ?\"\n⛑️ \"Đội mũ bảo hiểm thế nào là đúng?\"\n🚸 \"Biển báo hình tam giác nghĩa là gì?\"\n🚲 \"Đi xe đạp cần lưu ý gì?\"\n🚑 \"Gặp xe cứu thương thì làm sao?\"\n\n💡 Con có thể **gõ câu hỏi**, hoặc bấm nút 🎤 để **nói** cho nhanh. Mình cũng sẽ đưa **hình ảnh thật** để con dễ hình dung nữa đó!\n\n❓ Nào, con muốn bắt đầu với điều gì? 🚦",
        ]],

        [['cam on', 'thank'], [
            "Không có gì đâu con! 😊 Con chịu khó hỏi và ham học hỏi như vậy là **giỏi lắm** đó!\n\nCon biết không, mỗi điều con học được hôm nay đều có thể **bảo vệ con** vào một ngày nào đó trên đường. Và con còn có thể dạy lại cho **em nhỏ, cho bạn bè** nữa — như vậy con đã giúp bảo vệ nhiều người rồi đấy! 💛\n\n📝 Nhớ nhé con: **Chậm một giây — Không mất cả đời** ⏱️\n\n❓ Con còn muốn học thêm điều gì nữa không nào? Mình luôn sẵn sàng! 🚦",
        ]],

        [['tam biet', 'bye', 'hen gap'], [
            "Tạm biệt con nha! 👋 Hôm nay học vui quá phải không! 😄\n\n**Trước khi đi, mình nhắc lại 3 điều quan trọng nhất:**\n1️⃣ Qua đường: **Dừng — Nhìn — Nghe — Đi**, nhớ nắm tay người lớn 🚸\n2️⃣ Ngồi xe máy: **đội mũ, cài quai** ⛑️ | Ngồi ô tô: **thắt dây an toàn** 🚗\n3️⃣ Đèn đỏ **dừng lại**, dù đường có vắng đến mấy 🔴\n\nChúc con **đi đến nơi, về đến chốn** thật bình an nhé! Ba mẹ luôn chờ con ở nhà đó 💛\n\n❓ Khi nào có thắc mắc gì về giao thông, con cứ quay lại hỏi mình nha. Hẹn gặp lại con! 🤖👋",
        ]],
    ];

    /* Lượt 1: khớp chính xác — từ khoá DÀI NHẤT (cụ thể nhất) thắng.
       [SỬA LỖI] Trước đây lấy bài ĐẦU TIÊN khớp, nên từ khoá chung
       ("di bo", "qua duong") cướp mất bài cụ thể nằm sau trong $kb:
         "Đi bộ ban đêm..."     -> ra bài sang đường (sai)
         "Qua đường sắt..."     -> ra bài sang đường (sai)
       Dài bằng nhau thì bài đứng TRƯỚC trong $kb thắng (cụ thể xếp trước). */
    $hit = null; $hitLen = 0;
    foreach ($kb as $item) {
        foreach ($item[0] as $k) {
            if (str_contains($t, $k) && mb_strlen($k) > $hitLen) {
                $hit = $item; $hitLen = mb_strlen($k);
            }
        }
    }
    if ($hit !== null) return $hit[1][array_rand($hit[1])];
    // Lượt 2: khớp gần đúng (bé gõ sai chính tả vẫn hiểu)
    foreach ($kb as $item) {
        foreach ($item[0] as $k) {
            if (ai_fuzzy_contains($t, $k)) return $item[1][array_rand($item[1])];
        }
    }

    $fallback = [
        "Câu hỏi của con hay quá, nhưng mình chưa chắc chắn câu trả lời 🤔 — mà chuyện an toàn thì mình **không dám đoán bừa** đâu, vì đoán sai có thể làm con gặp nguy hiểm.\n\n📝 **Con thử hỏi mình về những chủ đề này nhé:**\n🚦 Đèn giao thông (đỏ, vàng, xanh)\n🚸 Biển báo giao thông\n⛑️ Đội mũ bảo hiểm đúng cách\n🚶 Sang đường an toàn, vạch kẻ đường\n🚲 Đi xe đạp\n🚑 Nhường đường xe ưu tiên\n🚂 An toàn đường sắt\n👮 Hiệu lệnh cảnh sát giao thông\n\n💡 Hoặc con bấm vào một **câu gợi ý** phía dưới cho nhanh nha!\n\n❓ Con muốn bắt đầu với chủ đề nào? 😄",
    ];
    return $fallback[array_rand($fallback)];
}

/* =====================================================================
   BỘ HIỂU CÂU HỎI THÔNG MINH
   • Hiểu teencode, viết tắt ("ko", "k", "dc", "bhiem"...)
   • Sai chính tả nhẹ vẫn hiểu (so khớp gần đúng)
   • Nhớ ngữ cảnh: "còn cái kia thì sao?" → biết đang nói chủ đề gì
   ===================================================================== */

/* Chuẩn hoá: bỏ dấu, bỏ dấu câu, dịch teencode sang tiếng Việt chuẩn */
function ai_normalize(string $msg): string
{
    $t = ai_khong_dau($msg);
    $t = preg_replace('/[^a-z0-9\s]/u', ' ', $t);   // bỏ dấu câu, emoji
    $t = preg_replace('/\s+/', ' ', trim($t));

    // Từ điển teencode / viết tắt các bé hay gõ
    $teen = [
        'ko' => 'khong', 'k' => 'khong', 'kg' => 'khong', 'hok' => 'khong', 'hong' => 'khong',
        'dc' => 'duoc', 'đc' => 'duoc', 'j' => 'gi', 'z' => 'vay', 'v' => 'vay',
        'bhiem' => 'bao hiem', 'bh' => 'bao hiem', 'mbh' => 'mu bao hiem',
        'atgt' => 'an toan giao thong', 'gt' => 'giao thong', 'csgt' => 'canh sat giao thong',
        'xdap' => 'xe dap', 'xmay' => 'xe may', 'nhu the nao' => 'the nao',
        'ntn' => 'the nao', 'the nao' => 'the nao', 'lm' => 'lam', 'ntt' => 'the nao',
        'e' => 'em', 'a' => 'anh', 'trc' => 'truoc', 'sau do' => 'sau do',
    ];
    $words = explode(' ', $t);
    foreach ($words as &$w) {
        if (isset($teen[$w])) $w = $teen[$w];
    }
    return implode(' ', $words);
}

/* Câu hỏi có phải dạng "hỏi tiếp" không? (không nêu chủ đề, dựa vào câu trước)
   Ví dụ: "vì sao ạ?", "còn cái kia?", "kể thêm đi", "ví dụ?" */
function ai_is_followup(string $msg): bool
{
    // Câu đã nêu rõ chủ đề ("đội mũ bảo hiểm thế nào?") → là câu hỏi MỚI,
    // dù có chứa từ "thế nào". Chỉ câu KHÔNG có chủ đề mới là hỏi tiếp.
    if (ai_match_illus($msg) !== null) return false;

    $t = ai_normalize($msg);
    $words = explode(' ', $t);
    if (count($words) > 8) return false;   // câu dài → chắc là chủ đề mới

    $followWords = [
        'vi sao', 'tai sao', 'the nao', 'con gi', 'con cai', 'con nua',
        'them', 'nua', 'vi du', 'cu the', 'giai thich', 'ke tiep', 'the con',
        'lam sao', 'ra sao', 'y la', 'nghia la', 'cai do', 'cai kia', 'dieu do',
    ];
    foreach ($followWords as $f) {
        if (str_contains($t, $f)) return true;
    }
    return false;
}

/* Lấy chủ đề của câu trả lời GẦN NHẤT (dùng cho câu hỏi tiếp) */
function ai_last_topic(PDO $pdo, int $sessionId): ?string
{
    if ($sessionId <= 0) return null;
    $st = $pdo->prepare(
        "SELECT illus FROM aigs_messages
         WHERE session_id = ? AND role = 'bot' AND illus IS NOT NULL
         ORDER BY id DESC LIMIT 1"
    );
    $st->execute([$sessionId]);
    $row = $st->fetch();
    return $row ? $row['illus'] : null;
}

/* So khớp GẦN ĐÚNG — trả về "mức độ sai" (0 = khớp hoàn hảo), null = không khớp.
   Nhờ đó chọn được từ khoá SÁT NGHĨA NHẤT, thay vì lấy đại cái gặp đầu tiên.
   Ví dụ: "mu bao hem" sai 1 ký tự so với "mu bao hiem" → khớp đúng chủ đề mũ bảo hiểm,
   chứ không nhầm sang "mua bao" (trời mưa). */
function ai_fuzzy_distance(string $haystack, string $needle): ?int
{
    if (str_contains($haystack, $needle)) return 0;   // khớp chính xác

    $hWords = explode(' ', $haystack);
    $nWords = explode(' ', $needle);
    $n = count($nWords);
    // Cho phép sai 1 ký tự với từ khoá ngắn, 2 với từ khoá dài
    $allow = mb_strlen($needle) >= 10 ? 2 : 1;

    $bestDist = null;
    for ($i = 0; $i + $n <= count($hWords); $i++) {
        $chunk = implode(' ', array_slice($hWords, $i, $n));
        $dist  = levenshtein($chunk, $needle);
        if ($dist <= $allow && ($bestDist === null || $dist < $bestDist)) {
            $bestDist = $dist;
        }
    }
    return $bestDist;
}

/* Bản rút gọn: chỉ hỏi "có khớp không" (dùng cho kho kiến thức) */
function ai_fuzzy_contains(string $haystack, string $needle): bool
{
    if (mb_strlen($needle) < 6) return str_contains($haystack, $needle);
    return ai_fuzzy_distance($haystack, $needle) !== null;
}

/* =====================================================================
   4 NHÂN VẬT AI — bé chọn ai cũng dạy đúng kiến thức, chỉ khác giọng kể
   ===================================================================== */
function personas(): array
{
    return [
        'gia-su' => [
            'name'   => 'AI Gia sư',
            'emoji'  => '🤖',
            'desc'   => 'Thân thiện, giảng dễ hiểu',
            'prompt' => "Bạn là AI Gia sư thân thiện, xưng 'mình', gọi bé là 'con'. Giọng ấm áp, dễ hiểu, nhiều emoji.",
            'open'   => ['', ''],
        ],
        'canh-sat' => [
            'name'   => 'Chú Cảnh sát',
            'emoji'  => '👮',
            'desc'   => 'Nghiêm túc, dứt khoát',
            'prompt' => "Bạn là chú Cảnh sát giao thông, xưng 'chú', gọi bé là 'cháu'. Giọng nghiêm túc nhưng ấm áp, dứt khoát, hay nhấn mạnh luật lệ và kỷ luật.",
            'open'   => ["👮 **Chú Cảnh sát đây!** Cháu hỏi hay lắm, chú giải thích nhé:\n\n", "\n\n👮 Cháu nhớ chấp hành luật giao thông cho tốt nhé!"],
        ],
        'bac-tai' => [
            'name'   => 'Bác Tài xế',
            'emoji'  => '🚗',
            'desc'   => 'Kể chuyện thực tế trên đường',
            'prompt' => "Bạn là bác tài xế lâu năm, xưng 'bác', gọi bé là 'cháu'. Giọng thân tình, hay kể kinh nghiệm thực tế khi cầm lái, giải thích góc nhìn của người lái xe.",
            'open'   => ["🚗 **Bác Tài đây cháu!** Bác chạy xe mấy chục năm rồi, bác kể cháu nghe:\n\n", "\n\n🚗 Bác nói thật: người lái xe rất khó nhìn thấy các cháu nhỏ. Cháu cẩn thận nhé!"],
        ],
        'ban-rua' => [
            'name'   => 'Bạn Rùa',
            'emoji'  => '🐢',
            'desc'   => 'Vui nhộn, đi chậm mà chắc',
            'prompt' => "Bạn là bạn Rùa vui nhộn, xưng 'tớ', gọi bé là 'cậu'. Giọng hài hước, đáng yêu, hay nhắc 'chậm mà chắc', nhiều emoji vui.",
            'open'   => ["🐢 **Bạn Rùa đây!** Tớ đi chậm nhưng chưa bao giờ bị tai nạn đâu nha! Nghe tớ kể nè:\n\n", "\n\n🐢 Nhớ nhé cậu: **chậm mà chắc** — tớ đi chậm nhất rừng mà vẫn về đích an toàn! 😄"],
        ],
    ];
}

/* Khoác "giọng nhân vật" lên câu trả lời (dùng cho chế độ offline) */
function persona_wrap(string $persona, string $reply): string
{
    $p = personas()[$persona] ?? null;
    if (!$p || $persona === 'gia-su') return $reply;
    return $p['open'][0] . $reply . $p['open'][1];
}

/* =====================================================================
   GỢI Ý CÂU HỎI TIẾP THEO — dẫn bé học sang chủ đề liên quan
   ===================================================================== */
function next_questions(?string $topic): array
{
    $rel = [
        'den-3-mau'   => ['Đèn vàng có được đi không?', 'Cảnh sát ra hiệu thì nghe ai?', 'Vạch kẻ đường dành cho ai?'],
        'den-do'      => ['Đèn vàng nghĩa là gì?', 'Cảnh sát giao thông ra hiệu thì sao?', 'Ngã tư cần chú ý gì?'],
        'den-vang'    => ['Đèn đỏ thì phải làm gì?', 'Sang đường thế nào cho an toàn?', 'Đèn xanh có chắc chắn an toàn?'],
        'den-xanh'    => ['Đèn vàng nghĩa là gì?', 'Ngã tư cần chú ý xe nào?', 'Vạch kẻ đường dành cho ai?'],
        'mu-bao-hiem' => ['Ngồi ô tô có cần thắt dây an toàn?', 'Đi xe đạp cần lưu ý gì?', 'Ngồi sau xe máy thế nào cho đúng?'],
        'vach-ke'     => ['Đèn giao thông có mấy màu?', 'Ngã tư cần chú ý gì?', 'Không có vỉa hè thì đi đâu?'],
        'bien-bao'    => ['Biển STOP nghĩa là gì?', 'Biển giới hạn tốc độ là biển nào?', 'Đèn giao thông có mấy màu?'],
        'bien-stop'   => ['Các loại biển báo giao thông?', 'Qua đường sắt cần chú ý gì?', 'Ngã tư nguy hiểm thế nào?'],
        'xe-dap'      => ['Đội mũ bảo hiểm thế nào là đúng?', 'Đi xe đạp ban đêm cần gì?', 'Điểm mù xe tải là gì?'],
        'day-an-toan' => ['Đội mũ bảo hiểm thế nào là đúng?', 'Vì sao trẻ em nên ngồi ghế sau?', 'Điểm mù của ô tô là gì?'],
        'cuu-thuong'  => ['Cảnh sát giao thông ra hiệu thế nào?', 'Đèn đỏ có được vượt không?', 'Số điện thoại cấp cứu là gì?'],
        'canh-sat'    => ['Con bị lạc thì phải làm gì?', 'Đèn giao thông có mấy màu?', 'Xe cứu thương thì nhường thế nào?'],
        'duong-sat'   => ['Biển STOP nghĩa là gì?', 'Ngã tư cần chú ý gì?', 'Không chơi ở đâu trên đường?'],
        'diem-mu'     => ['Đi xe buýt cần lưu ý gì?', 'Đi xe đạp an toàn thế nào?', 'Ngã tư nguy hiểm ra sao?'],
        'xe-buyt'     => ['Điểm mù xe tải là gì?', 'Sang đường thế nào cho an toàn?', 'Đi bộ trên vỉa hè thế nào?'],
        'troi-mua'    => ['Đi đường ban đêm có nguy hiểm không?', 'Đi xe đạp trời mưa thế nào?', 'Tốc độ ảnh hưởng ra sao?'],
        'ban-dem'     => ['Trời mưa đi đường thế nào?', 'Đi xe đạp ban đêm cần gì?', 'Đi bộ trên vỉa hè thế nào?'],
        'nga-tu'      => ['Đèn giao thông có mấy màu?', 'Điểm mù xe tải là gì?', 'Sang đường thế nào cho an toàn?'],
        'via-he'      => ['Sang đường thế nào cho an toàn?', 'Đi bộ ban đêm cần gì?', 'Không được chơi ở đâu?'],
        'choi-duong'  => ['Bóng lăn ra đường thì làm sao?', 'Đi bộ trên vỉa hè thế nào?', 'Con bị lạc thì làm gì?'],
        'lac-duong'   => ['Cảnh sát giao thông giúp gì được con?', 'Số 113, 114, 115 là gì?', 'Đi xe buýt cần lưu ý gì?'],
        'toc-do'      => ['Biển báo tốc độ là biển nào?', 'Trời mưa xe phanh thế nào?', 'Điểm mù xe tải là gì?'],
        // ----- [MỞ RỘNG] -----
        'ghe-tre-em'  => ['Ngồi ô tô cần lưu ý gì?', 'Vì sao phải thắt dây an toàn?', 'Ngồi sau xe máy thế nào cho đúng?'],
        'cong-truong' => ['Sang đường thế nào cho an toàn?', 'Đi bộ trên vỉa hè thế nào?', 'Con bị lạc thì phải làm gì?'],
        'gap-tai-nan' => ['Số 113, 114, 115 là gì?', 'Gặp xe cứu thương thì làm gì?', 'Con bị lạc thì phải làm gì?'],
        'ngap-nuoc'   => ['Trời mưa đi đường thế nào?', 'Đi xe đạp trời mưa thế nào?', 'Đi bộ ban đêm cần gì?'],
        'cho-khuat'   => ['Sang đường thế nào cho an toàn?', 'Điểm mù xe tải là gì?', 'Đi xe buýt cần lưu ý gì?'],
        'nhuong-duong'=> ['Gặp xe cứu thương thì làm gì?', 'Đi xe buýt cần lưu ý gì?', 'An toàn giao thông là gì?'],
    ];

    // Chưa có chủ đề (chào hỏi, câu lạ) → gợi ý các bài phổ biến
    $default = ['Đèn giao thông có mấy màu?', 'Đội mũ bảo hiểm thế nào là đúng?', 'Sang đường thế nào cho an toàn?'];
    return $rel[$topic] ?? $default;
}

/* =====================================================================
   CHỌN CHỦ ĐỀ / HÌNH MINH HOẠ theo câu hỏi của bé
   Trả về "mã chủ đề" — dùng cho hình minh hoạ, tìm ảnh và bài kiểm tra.
   Dùng chung cho cả 2 chế độ (Gemini và offline).
   ===================================================================== */
/* Bảng từ khoá CHÍNH XÁC của từng chủ đề.
   Dùng chung cho: chọn hình minh hoạ (ai_match_illus) và truy xuất RAG (ai_retrieve).
   Xếp từ CỤ THỂ đến CHUNG — chủ đề cụ thể phải đứng trước. */
function ai_topic_keywords(): array
{
    return [
        // --- Chủ đề cụ thể, phải đứng trước ---
        // [MỞ RỘNG] 6 chủ đề mới
        'ghe-tre-em'  => ['ghe an toan', 'ghe tre em', 'ghe cho be', 'thiet bi an toan',
                          'dem nang', 'ngoi ghe truoc', 'ngoi ghe sau'],
        'cong-truong' => ['cong truong', 'tan hoc', 'tan truong', 'truoc cong truong', 'gio ra ve'],
        'gap-tai-nan' => ['gap tai nan', 'thay tai nan', 'chung kien tai nan', 'nguoi bi nan',
                          'so cuu', 'co tai nan'],
        'ngap-nuoc'   => ['ngap nuoc', 'duong ngap', 'ho ga', 'loi nuoc', 'nuoc chay xiet', 'ngap lut'],
        'cho-khuat'   => ['khuat tam nhin', 'cho khuat', 'xe dang do', 'giua hai xe',
                          'goc cua', 'bi che khuat'],
        'nhuong-duong'=> ['nhuong duong', 'van hoa giao thong', 'xep hang', 'xa rac',
                          'bam coi', 'lich su khi di duong'],
        'diem-mu'     => ['diem mu', 'xe tai', 'xe container', 'xe khach lon', 'xe bon'],
        'xe-buyt'     => ['xuong xe buyt', 'len xe buyt', 'xe buyt', 'xe bus', 'tram xe', 'xe khach'],
        'troi-mua'    => ['troi mua', 'duong tron', 'mua bao'],
        'ban-dem'     => ['di bo ban dem', 'ban dem', 'buoi toi', 'trong toi', 'phan quang', 'thieu anh sang'],
        'nga-tu'      => ['nga tu', 'nga ba', 'nga nam', 'vong xuyen', 'buc giao thong', 'giao lo'],
        'via-he'      => ['via he', 'le duong', 'di bo tren duong'],
        'choi-duong'  => ['choi tren duong', 'da bong', 'bong lan ra duong', 'tha dieu', 'chay nhay tren duong', 'duoi bat'],
        'lac-duong'   => ['bi lac', 'lac duong', 'so khan cap', 'goi cuu ho', 'goi giup do', '113', '115'],
        'toc-do'      => ['toc do', 'chay nhanh', 'phong nhanh', 'gioi han toc do'],

        // --- Chủ đề gốc ---
        'canh-sat'    => ['canh sat giao thong', 'chu canh sat', 'cong an giao thong', 'chu cong an'],
        'duong-sat'   => ['qua duong sat', 'duong sat', 'tau hoa', 'duong ray', 'xe lua', 'duong tau'],
        'den-do'      => ['den do'],
        'den-vang'    => ['den vang'],
        'den-xanh'    => ['den xanh'],
        'den-3-mau'   => ['den giao thong', 'den tin hieu', 'tin hieu den', 'may mau den'],
        'mu-bao-hiem' => ['mu bao hiem', 'doi mu', 'non bao hiem'],
        'vach-ke'     => ['sang duong', 'qua duong', 'bang qua duong', 'vach ke', 'loi di bo', 'nguoi di bo', 'di bo'],
        'cuu-thuong'  => ['xe cuu thuong', 'cuu thuong', 'cuu hoa', 'xe uu tien', 'canh sat', 'cuu ho', 'uu tien'],
        'bien-stop'   => ['stop'],
        'bien-bao'    => ['bien bao', 'bien cam', 'bien nguy hiem', 'bien chi dan', 'y nghia bien', 'bien hieu lenh'],
        'xe-dap'      => ['xe dap'],
        'day-an-toan' => ['xe may', 'ngoi sau', 'o to', 'oto', 'xe hoi', 'day an toan'],
        'luat-giao-thong' => ['luat giao thong', 'dieu luat', 'nghi dinh', 'muc phat',
                              'bi phat', 'xu phat', 'phat tien', 'nghiem cam'],
        'an-toan-chung' => ['an toan giao thong', 'an toan khi di duong'],
    ];
}

function ai_match_illus(string $msg): ?string
{
    $t = ai_normalize($msg);   // chuẩn hoá: teencode, bỏ dấu câu
    $map = ai_topic_keywords();

    /* ---- Lượt 1: khớp CHÍNH XÁC, chấm điểm để chọn chủ đề ĐÚNG NHẤT ----
       Bé hỏi "đi xe đạp có cần đội mũ không?" → khớp cả 'xe dap' lẫn 'doi mu'.
       Quy tắc: từ khoá DÀI HƠN (cụ thể hơn) thì thắng;
       dài bằng nhau thì từ khoá xuất hiện SỚM HƠN trong câu thắng.
       [SỬA LỖI] Trước đây ưu tiên vị trí trước, nên từ khoá CHUNG nằm đầu câu
       ("qua duong", "di bo") cướp mất chủ đề CỤ THỂ nằm sau:
         "Qua đường sắt cần chú ý gì?"  -> ra bài sang đường (sai)
         "Đi bộ ban đêm có nguy hiểm?"  -> ra bài sang đường (sai)
       Ưu tiên độ dài trước sẽ chọn đúng 'duong sat' / 'ban dem'. */
    $best = null; $bestPos = PHP_INT_MAX; $bestLen = 0;
    foreach ($map as $key => $keywords) {
        foreach ($keywords as $k) {
            $pos = mb_strpos($t, $k);
            if ($pos === false) continue;
            $len = mb_strlen($k);
            if ($len > $bestLen || ($len === $bestLen && $pos < $bestPos)) {
                $best = $key; $bestPos = $pos; $bestLen = $len;
            }
        }
    }
    if ($best !== null) return $best;

    /* ---- Lượt 2: khớp GẦN ĐÚNG — cứu trường hợp bé gõ sai chính tả ----
       Chọn từ khoá sai ÍT NHẤT (không lấy đại cái đầu tiên gặp),
       tránh nhầm "mu bao hem" thành "mua bao" (trời mưa). */
    $best = null; $bestDist = PHP_INT_MAX; $bestLen = 0;
    foreach ($map as $key => $keywords) {
        foreach ($keywords as $k) {
            if (mb_strlen($k) < 6) continue;   // từ khoá ngắn không đoán mò
            $d = ai_fuzzy_distance($t, $k);
            if ($d === null) continue;
            $len = mb_strlen($k);
            if ($d < $bestDist || ($d === $bestDist && $len > $bestLen)) {
                $best = $key; $bestDist = $d; $bestLen = $len;
            }
        }
    }
    return $best;   // null nếu không khớp gì cả
}

/* =====================================================================
   NGÂN HÀNG CÂU HỎI TRẮC NGHIỆM — 2 câu cho mỗi chủ đề
   Mỗi câu: q = câu hỏi, o = 4 đáp án, a = số thứ tự đáp án đúng (0-3),
            e = lời giải thích hiện ra sau khi bé chọn
   ===================================================================== */
function quiz_bank(): array
{
    return [

// ===== [MỞ RỘNG] Câu hỏi cho 6 chủ đề mới =====
'ghe-tre-em' => [
  ['q'=>'Trên ô tô, bạn nhỏ chưa cao 1,35 mét nên ngồi ở đâu?',
   'o'=>['Ghế trước cạnh người lái','Hàng ghế sau, dùng ghế an toàn phù hợp','Trong lòng người lớn','Đứng giữa xe cho thoáng'],'a'=>1,
   'e'=>'Đúng rồi! Từ 1/1/2026, trẻ dưới 10 tuổi và cao dưới 1,35 mét không được ngồi cùng hàng ghế với người lái, phải ngồi hàng sau và dùng thiết bị an toàn phù hợp.'],
  ['q'=>'Vì sao dây an toàn của người lớn chưa hợp với bạn nhỏ?',
   'o'=>['Vì dây quá ngắn','Vì dây vắt ngang cổ và bụng thay vì vai và hông','Vì dây có màu xấu','Vì dây làm bằng vải'],'a'=>1,
   'e'=>'Chính xác! Dây được thiết kế cho người cao trên 1,35 mét. Với bạn nhỏ, dây siết vào cổ và bụng — những chỗ mềm yếu nhất.'],
],

'cong-truong' => [
  ['q'=>'Giờ tan học ở cổng trường rất đông. Con nên làm gì?',
   'o'=>['Chạy ùa ra thật nhanh','Chen lấn cho ra trước','Đi trật tự, chờ ba mẹ ở chỗ đã hẹn','Đứng giữa đường vẫy tay gọi'],'a'=>2,
   'e'=>'Giỏi lắm! Chỗ đông người rất dễ xô đẩy làm bạn ngã ra lòng đường. Hãy đi chậm và chờ đúng chỗ đã hẹn.'],
  ['q'=>'Ba mẹ đến đón muộn, con nên chờ ở đâu?',
   'o'=>['Đứng sát mép lòng đường','Quay vào trong sân trường chờ','Đi bộ về một mình','Đi theo người lạ nhận là bạn của ba mẹ'],'a'=>1,
   'e'=>'Đúng! Vào trong sân trường chờ là an toàn nhất. Tuyệt đối không đi theo người lạ, dù họ nói gì.'],
],

'gap-tai-nan' => [
  ['q'=>'Con nhìn thấy một vụ tai nạn trên đường. Việc ĐẦU TIÊN con nên làm là gì?',
   'o'=>['Chạy vào giữa đường để xem','Đứng chỗ an toàn và gọi người lớn tới giúp','Tự kéo người bị nạn dậy','Quay phim đăng lên mạng'],'a'=>1,
   'e'=>'Chuẩn! Con phải an toàn trước đã, rồi gọi người lớn. Việc của con là BÁO TIN, không phải tự cứu người.'],
  ['q'=>'Số điện thoại gọi xe cứu thương là số nào?',
   'o'=>['113','114','115','116'],'a'=>2,
   'e'=>'Đúng rồi! 115 là cấp cứu y tế 🚑. Còn 113 là cảnh sát 👮 và 114 là cứu hoả 🚒.'],
],

'ngap-nuoc' => [
  ['q'=>'Vì sao đường ngập nước lại nguy hiểm với con?',
   'o'=>['Vì nước làm bẩn giày','Vì nước đục che mất hố ga và chỗ sâu','Vì nước lạnh','Vì nước làm chậm bước chân'],'a'=>1,
   'e'=>'Chính xác! Mặt nước đục che kín mọi thứ bên dưới — nguy hiểm nhất là hố ga bị mất nắp mà con không nhìn thấy.'],
  ['q'=>'Gặp chỗ nước ngập đang chảy xiết, con nên làm gì?',
   'o'=>['Lội thật nhanh qua','Không lội qua, chờ nước rút hoặc tìm đường khác','Nhảy qua','Thả thuyền giấy chơi'],'a'=>1,
   'e'=>'Giỏi lắm! Nước chảy xiết chỉ ngang đầu gối đã đủ cuốn con đi. Hãy chờ nước rút hoặc đi đường khác cao hơn.'],
],

'cho-khuat' => [
  ['q'=>'Chỗ nào KHÔNG an toàn để qua đường?',
   'o'=>['Trên vạch kẻ dành cho người đi bộ','Giữa hai chiếc ô tô đang đỗ','Chỗ trống nhìn rõ hai đầu đường','Nơi có cầu vượt đi bộ'],'a'=>1,
   'e'=>'Đúng! Giữa hai xe đang đỗ, con không thấy xe tới mà người lái xe cũng không thấy con — cả hai bên đều bị che khuất.'],
  ['q'=>'Khi đi từ trong ngõ hẹp ra đường lớn, con phải làm gì?',
   'o'=>['Chạy thẳng ra cho nhanh','Dừng lại hẳn, nhìn kỹ hai bên rồi mới bước ra','Vừa đi vừa nhìn điện thoại','Nhắm mắt bước ra'],'a'=>1,
   'e'=>'Chuẩn! Cửa ngõ là chỗ khuất tầm nhìn. Nhớ quy tắc: nếu con không nhìn thấy xe thì xe cũng không nhìn thấy con.'],
],

'nhuong-duong' => [
  ['q'=>'Đâu là hành động của một người có văn hoá giao thông?',
   'o'=>['Chen lấn khi lên xe buýt','Xếp hàng trật tự và không xả rác ra đường','Bấm còi ầm ĩ cho vui','Đi dàn hàng ngang chắn lối'],'a'=>1,
   'e'=>'Đúng rồi! Xếp hàng, giữ đường sạch và cư xử lịch sự chính là văn hoá giao thông đẹp 💚'],
  ['q'=>'Thấy một cụ già muốn qua đường, con nên làm gì?',
   'o'=>['Chen lên đi trước cho nhanh','Nhường bước và nhờ người lớn giúp cụ','Giả vờ không nhìn thấy','Chạy vụt qua'],'a'=>1,
   'e'=>'Giỏi quá! Nhường đường chỉ mất của con vài giây, nhưng với người khác có khi là cả sự an toàn của họ.'],
],

'luat-giao-thong' => [
  ['q'=>'Khi qua đường ở chỗ KHÔNG có vạch kẻ và không có đèn, luật yêu cầu con phải làm gì?',
   'o'=>['Chạy thật nhanh qua đường','Quan sát kỹ và giơ tay xin đường','Nhắm mắt đi thẳng','Đi theo người phía trước'],'a'=>1,
   'e'=>'Đúng rồi! Luật quy định phải quan sát các xe đang tới và khi qua đường phải có tín hiệu bằng tay — tức là giơ tay lên cho người lái xe nhìn thấy con (điểm b khoản 1 Điều 30 Luật Trật tự, an toàn giao thông đường bộ 2024).'],
  ['q'=>'Theo luật, trẻ em từ mấy tuổi trở lên ngồi trên xe máy bắt buộc phải đội mũ bảo hiểm?',
   'o'=>['Từ đủ 3 tuổi','Từ đủ 6 tuổi','Từ đủ 10 tuổi','Từ đủ 16 tuổi'],'a'=>1,
   'e'=>'Chính xác! Từ đủ 6 tuổi trở lên là bắt buộc phải đội mũ bảo hiểm và cài quai đúng cách. Em bé dưới 6 tuổi thì luật chưa bắt buộc, nhưng đội mũ vừa đầu vẫn an toàn hơn nhiều.'],
],

'den-3-mau' => [
  ['q'=>'Đèn giao thông có mấy màu?','o'=>['2 màu','3 màu','4 màu','5 màu'],'a'=>1,
   'e'=>'Đúng rồi! Có 3 màu: 🔴 Đỏ (dừng lại), 🟡 Vàng (chuẩn bị dừng), 🟢 Xanh (được đi).'],
  ['q'=>'Đèn đang XANH nhưng chú cảnh sát giơ tay ra hiệu dừng. Con làm theo ai?','o'=>['Theo đèn xanh, cứ đi','Theo chú cảnh sát, dừng lại','Hỏi người bên cạnh','Nhắm mắt chạy nhanh'],'a'=>1,
   'e'=>'Chính xác! Hiệu lệnh của chú cảnh sát có ưu tiên CAO NHẤT, cao hơn cả đèn giao thông.'],
],

'den-do' => [
  ['q'=>'Đèn đỏ, đường vắng hoàn toàn không có xe nào. Con nên làm gì?','o'=>['Đi nhanh qua vì không có xe','Vẫn dừng lại chờ đèn xanh','Đi chậm qua cũng được','Chạy thật nhanh cho kịp'],'a'=>1,
   'e'=>'Giỏi lắm! Đèn đỏ là phải dừng, dù đường có vắng đến mấy. Chờ vài giây để an toàn cả đời.'],
  ['q'=>'Khi dừng đèn đỏ, con phải dừng ở đâu?','o'=>['Đè lên vạch trắng','Sau vạch trắng','Giữa ngã tư','Đâu cũng được'],'a'=>1,
   'e'=>'Đúng! Phải dừng SAU vạch trắng, không được đè lên hoặc vượt quá vạch.'],
],

'den-vang' => [
  ['q'=>'Đèn vàng nghĩa là gì?','o'=>['Đi nhanh lên cho kịp','Chuẩn bị dừng lại','Được đi thoải mái','Đèn bị hỏng'],'a'=>1,
   'e'=>'Chuẩn! Đèn vàng = CHUẨN BỊ DỪNG. Nhiều người hiểu nhầm là "đi nhanh lên" — đó là lúc dễ tai nạn nhất!'],
  ['q'=>'Con đang đứng ở vỉa hè, đèn chuyển sang vàng. Con nên?','o'=>['Chạy vụt qua đường','Đứng yên chờ đèn xanh cho người đi bộ','Đi chậm qua đường','Vẫy tay cho xe dừng'],'a'=>1,
   'e'=>'Đúng rồi! Đứng yên trên vỉa hè, chờ đèn xanh dành cho NGƯỜI ĐI BỘ mới qua.'],
],

'den-xanh' => [
  ['q'=>'Đèn xanh vừa bật, nhưng còn một ô tô đang chạy tới. Con làm gì?','o'=>['Cứ đi vì đèn đã xanh','Chờ xe đó dừng hẳn rồi mới đi','Chạy thật nhanh qua trước','Hét lên cho xe biết'],'a'=>1,
   'e'=>'Rất giỏi! Đèn xanh là ĐƯỢC PHÉP đi, không có nghĩa là chắc chắn an toàn. Luôn quan sát trước!'],
  ['q'=>'Đèn xanh sắp hết mà con chưa qua kịp đường. Con nên?','o'=>['Chạy thật nhanh cho kịp','Đứng lại chờ lượt sau','Đi tiếp thật chậm','Dừng giữa đường'],'a'=>1,
   'e'=>'Đúng! Thà chờ thêm một lượt đèn còn hơn vội vàng gặp nguy hiểm.'],
],

'mu-bao-hiem' => [
  ['q'=>'Làm sao biết quai mũ bảo hiểm đã cài vừa khít?','o'=>['Đút vừa 2 ngón tay dưới cằm','Càng chặt càng tốt','Càng lỏng càng thoải mái','Không cần cài quai'],'a'=>0,
   'e'=>'Chuẩn luôn! Đút vừa 2 ngón tay giữa quai và cằm là vừa khít. Lỏng quá mũ sẽ văng ra khi ngã.'],
  ['q'=>'Đội mũ bảo hiểm nhưng KHÔNG cài quai thì sao?','o'=>['Vẫn an toàn như thường','Mũ sẽ văng ra khi ngã, coi như chưa đội','Chỉ hơi kém an toàn một chút','Không sao cả'],'a'=>1,
   'e'=>'Đúng rồi! Đội mũ mà không cài quai thì khi ngã mũ văng đi mất, chẳng bảo vệ được gì cả.'],
],

'vach-ke' => [
  ['q'=>'Quy tắc vàng khi sang đường là gì?','o'=>['Chạy — Nhanh — Qua','Dừng — Nhìn — Nghe — Đi','Nhắm mắt — Chạy','Vẫy tay — Chạy'],'a'=>1,
   'e'=>'Chính xác! DỪNG lại, NHÌN trái-phải-trái, NGHE tiếng xe, rồi mới ĐI. Không bao giờ chạy!'],
  ['q'=>'Khi qua đường, con phải nhìn bên nào TRƯỚC?','o'=>['Bên phải','Bên trái','Nhìn xuống đất','Nhìn lên trời'],'a'=>1,
   'e'=>'Giỏi lắm! Nhìn TRÁI trước, vì xe từ bên trái sẽ tới chỗ con trước. Sau đó nhìn phải, rồi nhìn trái lần nữa.'],
],

'bien-bao' => [
  ['q'=>'Biển báo hình TAM GIÁC viền đỏ có ý nghĩa gì?','o'=>['Biển cấm','Biển nguy hiểm (cảnh báo)','Biển chỉ dẫn','Biển hiệu lệnh'],'a'=>1,
   'e'=>'Đúng! Tam giác viền đỏ = NGUY HIỂM, cảnh báo phía trước có điều cần chú ý.'],
  ['q'=>'Biển hình TRÒN viền đỏ nền trắng là biển gì?','o'=>['Biển cấm','Biển chỉ dẫn','Biển nguy hiểm','Biển hiệu lệnh'],'a'=>0,
   'e'=>'Chuẩn! Tròn viền đỏ = biển CẤM, cho biết điều gì đó không được phép làm.'],
],

'bien-stop' => [
  ['q'=>'Biển STOP có hình gì?','o'=>['Hình tròn','Hình tam giác','Hình bát giác (8 cạnh)','Hình vuông'],'a'=>2,
   'e'=>'Đúng rồi! Bát giác 8 cạnh — hình độc nhất, để dù trời tối hay biển bẩn, ai cũng nhận ra ngay.'],
  ['q'=>'Gặp biển STOP, xe phải làm gì?','o'=>['Chạy chậm lại rồi đi tiếp','Dừng lại HẲN, quan sát rồi mới đi','Bấm còi rồi đi','Chỉ ô tô mới cần dừng'],'a'=>1,
   'e'=>'Chuẩn! Phải dừng HẲN, bánh xe đứng yên hoàn toàn — không phải chỉ chạy chậm lại.'],
],

'xe-dap' => [
  ['q'=>'Đi xe đạp muốn rẽ TRÁI, con giơ tay bên nào?','o'=>['Tay phải','Tay trái','Cả hai tay','Không cần giơ tay'],'a'=>1,
   'e'=>'Đúng! Rẽ trái giơ tay trái, rẽ phải giơ tay phải — báo trước cho mọi người biết.'],
  ['q'=>'Điều nào SAI khi đi xe đạp?','o'=>['Đi sát lề bên phải','Bám vào xe ô tô để được kéo đi','Đội mũ bảo hiểm khi đi xe đạp điện','Có đèn khi đi buổi tối'],'a'=>1,
   'e'=>'Chính xác! Bám vào xe khác để "đi ké" là cực kỳ nguy hiểm, tuyệt đối không được làm.'],
],

'day-an-toan' => [
  ['q'=>'Vì sao trẻ em nên ngồi ghế SAU của ô tô?','o'=>['Vì ghế sau êm hơn','Vì túi khí ghế trước bung rất mạnh, có thể gây thương tích','Vì ghế trước dành cho người lớn','Vì ngồi sau nhìn được nhiều hơn'],'a'=>1,
   'e'=>'Đúng rồi! Túi khí bung ra với lực rất mạnh, nguy hiểm cho trẻ nhỏ. Ghế sau an toàn hơn nhiều.'],
  ['q'=>'Đi ô tô một đoạn ngắn gần nhà, có cần thắt dây an toàn không?','o'=>['Không cần, đi gần mà','Có, luôn luôn phải thắt','Chỉ cần khi đi xa','Chỉ người lớn mới cần'],'a'=>1,
   'e'=>'Chuẩn! Đa số tai nạn xảy ra GẦN NHÀ. Lên xe là thắt dây, không có ngoại lệ.'],
],

'cuu-thuong' => [
  ['q'=>'Nghe tiếng còi hú xe cứu thương, con đang đi bộ nên làm gì?','o'=>['Chạy nhanh qua đường trước','Đứng gọn lên vỉa hè, chờ xe qua hẳn','Chạy theo sau xe','Đứng giữa đường xem'],'a'=>1,
   'e'=>'Rất tốt! Đứng gọn vào lề, nhường đường. Trên xe có thể là người đang cần cấp cứu gấp đó con.'],
  ['q'=>'Xe cứu thương đang hú còi có được vượt đèn đỏ không?','o'=>['Không, phải chờ như mọi xe','Có, vì đang làm nhiệm vụ khẩn cấp','Chỉ được vượt ban đêm','Phải xin phép cảnh sát'],'a'=>1,
   'e'=>'Đúng! Xe ưu tiên (cứu thương, cứu hoả, cảnh sát) được đi trước tất cả để cứu người kịp thời.'],
],

'canh-sat' => [
  ['q'=>'Thứ tự ưu tiên nào ĐÚNG?','o'=>['Đèn > Cảnh sát > Biển báo','Cảnh sát > Đèn > Biển báo > Vạch kẻ','Biển báo > Cảnh sát > Đèn','Vạch kẻ > Đèn > Cảnh sát'],'a'=>1,
   'e'=>'Chuẩn luôn! Hiệu lệnh của chú cảnh sát cao nhất, rồi tới đèn, biển báo, cuối cùng là vạch kẻ đường.'],
  ['q'=>'Con bị lạc đường, nên tìm ai giúp?','o'=>['Người lạ đi ngang qua','Chú cảnh sát 👮','Trốn vào chỗ tối','Tự đi tìm ba mẹ'],'a'=>1,
   'e'=>'Đúng rồi! Chú cảnh sát là người đáng tin cậy nhất để nhờ giúp đỡ khi con gặp khó khăn.'],
],

'duong-sat' => [
  ['q'=>'Vì sao tàu hoả không thể phanh gấp để tránh con?','o'=>['Vì tàu không có phanh','Vì tàu rất nặng, phải trượt cả trăm mét mới dừng được','Vì bác lái tàu không muốn phanh','Vì tàu đi quá chậm'],'a'=>1,
   'e'=>'Chính xác! Tàu nặng hàng nghìn tấn. Nhớ nhé: "Tàu không tránh được con — chỉ con tránh được tàu".'],
  ['q'=>'Chuông reo, rào chắn đang hạ xuống. Con nên?','o'=>['Chạy nhanh qua cho kịp','Đứng chờ, tuyệt đối không qua','Chui dưới rào chắn','Đi vòng qua bên cạnh rào'],'a'=>1,
   'e'=>'Giỏi lắm! Chuông reo = tàu sắp tới. Đứng chờ, và phải đợi rào chắn MỞ HẲN mới được đi.'],
],

'diem-mu' => [
  ['q'=>'"Điểm mù" của xe tải nghĩa là gì?','o'=>['Vùng bác tài KHÔNG nhìn thấy được','Đèn xe bị hỏng','Chỗ đường tối','Gương xe bị vỡ'],'a'=>0,
   'e'=>'Đúng! Điểm mù là vùng quanh xe mà bác tài không thấy, dù có nhìn gương. Xe càng to, điểm mù càng lớn.'],
  ['q'=>'Làm sao biết bác tài xe tải CÓ nhìn thấy con?','o'=>['Nếu con thấy xe thì xe cũng thấy con','Nếu con nhìn thấy gương chiếu hậu của xe','Nếu con vẫy tay','Nếu con đứng gần xe'],'a'=>1,
   'e'=>'Chuẩn! Quy tắc vàng: "Không thấy gương → bác tài KHÔNG thấy con". Hãy tránh xa hông xe tải!'],
],

'xe-buyt' => [
  ['q'=>'Vừa xuống xe buýt, con muốn qua đường. Nên làm gì?','o'=>['Băng qua ngay trước đầu xe buýt','Chờ xe buýt đi khuất hẳn rồi mới qua','Băng qua phía sau xe buýt','Chạy nhanh qua'],'a'=>1,
   'e'=>'Rất giỏi! Xe buýt to che khuất tầm nhìn — xe máy đang chạy tới sẽ không thấy con, và con cũng không thấy họ.'],
  ['q'=>'Khi chờ xe buýt ở trạm, con nên đứng ở đâu?','o'=>['Sát mép đường cho dễ lên xe','Trên vỉa hè, lùi sau vạch chờ','Giữa lòng đường vẫy xe','Chạy ra đón xe từ xa'],'a'=>1,
   'e'=>'Đúng! Đứng trên vỉa hè, chờ xe dừng HẲN rồi mới bước lên. Không chen lấn, xô đẩy.'],
],

'troi-mua' => [
  ['q'=>'Trời mưa nên mặc áo mưa màu gì?','o'=>['Màu đen cho đẹp','Màu sáng (vàng, cam, đỏ)','Màu nào cũng được','Màu xám'],'a'=>1,
   'e'=>'Chuẩn! Áo sáng màu giúp người lái xe nhìn thấy con từ xa, dù trời mưa mù mịt.'],
  ['q'=>'Trời mưa, đường trơn. Xe phanh sẽ như thế nào?','o'=>['Phanh nhanh hơn bình thường','Phanh lâu hơn, có thể gấp đôi','Không ảnh hưởng gì','Xe tự dừng được'],'a'=>1,
   'e'=>'Đúng rồi! Đường trơn làm xe phanh lâu hơn nhiều. Vì vậy con phải đi chậm và chờ lâu hơn trước khi qua đường.'],
],

'ban-dem' => [
  ['q'=>'Ban đêm mặc áo tối màu, người lái xe thấy con từ khoảng cách nào?','o'=>['Hơn 100 mét','Chỉ khoảng 20-30 mét — quá gần để phanh kịp','Từ rất xa','Luôn nhìn thấy rõ'],'a'=>1,
   'e'=>'Chính xác! Áo tối màu làm con gần như "tàng hình". Mặc áo sáng màu giúp họ thấy con xa gấp 4-5 lần.'],
  ['q'=>'Bị đèn pha ô tô chiếu thẳng vào mắt, con nên?','o'=>['Nhìn thẳng vào đèn','Nhìn xuống mép đường bên phải','Nhắm chặt mắt và đi tiếp','Chạy thật nhanh'],'a'=>1,
   'e'=>'Đúng! Nhìn thẳng vào đèn pha sẽ bị loá, không thấy gì trong vài giây — rất nguy hiểm.'],
],

'nga-tu' => [
  ['q'=>'Ở ngã tư, ngoài xe đi thẳng con còn phải chú ý xe nào?','o'=>['Không cần chú ý gì thêm','Xe đang RẼ (trái và phải)','Chỉ xe máy','Chỉ ô tô'],'a'=>1,
   'e'=>'Rất tốt! Nhiều xe rẽ mà không xi-nhan, không để ý người đi bộ. Xe rẽ phải là nguy hiểm nhất.'],
  ['q'=>'Có cầu vượt hoặc hầm đi bộ gần đó, con nên?','o'=>['Băng qua đường cho nhanh','Luôn ưu tiên dùng cầu vượt / hầm','Tuỳ tâm trạng','Chỉ dùng khi trời mưa'],'a'=>1,
   'e'=>'Đúng! Cầu vượt và hầm đi bộ an toàn 100% — không có xe nào có thể đụng vào con.'],
],

'via-he' => [
  ['q'=>'Đường KHÔNG có vỉa hè, con nên đi bên nào?','o'=>['Mép phải, cùng chiều xe chạy','Mép trái, ngược chiều xe chạy','Giữa lòng đường','Bên nào cũng được'],'a'=>1,
   'e'=>'Chuẩn! Đi ngược chiều xe để NHÌN THẤY xe đang tới. Nếu đi cùng chiều, xe từ sau lưng lao tới con không hề biết.'],
  ['q'=>'Vừa đi bộ vừa nhìn điện thoại thì sao?','o'=>['Không sao, vẫn nghe được tiếng xe','Rất nguy hiểm, mất tập trung hoàn toàn','Chỉ nguy hiểm khi qua đường','Không sao nếu đi chậm'],'a'=>1,
   'e'=>'Đúng rồi! Nhìn điện thoại làm con không thấy xe, không nghe tiếng còi. Cất điện thoại khi đi đường nhé!'],
],

'choi-duong' => [
  ['q'=>'Quả bóng của con lăn ra giữa đường. Con làm gì ĐẦU TIÊN?','o'=>['Chạy ngay ra nhặt bóng','DỪNG LẠI, nhờ người lớn hoặc quan sát thật kỹ','Chạy thật nhanh cho an toàn','Nhắm mắt chạy ra'],'a'=>1,
   'e'=>'Giỏi lắm! Nhớ nhé: "Bóng mất còn mua được — con thì không". Đừng bao giờ chạy theo bóng ra đường!'],
  ['q'=>'Nơi nào KHÔNG được chơi đùa?','o'=>['Công viên','Sân trường','Lòng đường','Sân nhà'],'a'=>2,
   'e'=>'Chính xác! Lòng đường không phải sân chơi. Rất nhiều bạn nhỏ gặp tai nạn ngay trước cửa nhà mình.'],
],

'lac-duong' => [
  ['q'=>'Con bị lạc ba mẹ ở nơi đông người. Việc ĐẦU TIÊN cần làm?','o'=>['Chạy đi tìm ba mẹ khắp nơi','ĐỨNG YÊN TẠI CHỖ, chờ ba mẹ quay lại','Đi theo người lạ tốt bụng','Khóc thật to rồi ngồi im'],'a'=>1,
   'e'=>'Đúng rồi! Đứng yên tại chỗ — ba mẹ sẽ quay lại đúng nơi đó. Con càng chạy, càng khó tìm nhau.'],
  ['q'=>'Số điện thoại gọi CẢNH SÁT là số nào?','o'=>['113','114','115','116'],'a'=>0,
   'e'=>'Chuẩn! Nhớ 3 số: 113 (cảnh sát), 114 (cứu hoả), 115 (cấp cứu). Học thuộc ngay hôm nay nhé!'],
],

'toc-do' => [
  ['q'=>'Xe chạy 30 km/h và 50 km/h đâm phải người — khả năng sống sót?','o'=>['Như nhau','30km/h ~90%, còn 50km/h chỉ còn ~50%','50km/h an toàn hơn','Đều rất an toàn'],'a'=>1,
   'e'=>'Đúng! Chỉ nhanh hơn một chút mà khác biệt cả sự sống. Đó là lý do gần trường học xe phải đi thật chậm.'],
  ['q'=>'Biển tròn viền đỏ có ghi số (ví dụ 40) nghĩa là gì?','o'=>['Tốc độ tối đa được phép chạy','Số nhà','Khoảng cách còn lại','Tốc độ bắt buộc phải chạy'],'a'=>0,
   'e'=>'Chuẩn! Đó là giới hạn tốc độ TỐI ĐA. Con hãy nhắc ba mẹ đi chậm lại nhé — "cảnh sát nhỏ" của gia đình! 👮'],
],

'an-toan-chung' => [
  ['q'=>'"Chậm một giây — Không mất cả đời" nghĩa là gì?','o'=>['Đi chậm thì mất thời gian','Chờ thêm vài giây để an toàn thì đáng giá hơn cả đời người','Không nên đi chậm','Phải luôn đi nhanh'],'a'=>1,
   'e'=>'Rất đúng! Vội vàng vài giây không đáng để đánh đổi sự an toàn của con và mọi người.'],
  ['q'=>'Điều nào sau đây là ĐÚNG?','o'=>['Đội mũ bảo hiểm chỉ khi đi xa','Đường vắng thì vượt đèn đỏ cũng được','Luôn đi bộ trên vỉa hè và qua đường đúng vạch','Trẻ em không cần thắt dây an toàn'],'a'=>2,
   'e'=>'Chính xác! Vỉa hè và vạch kẻ đường là nơi an toàn nhất dành cho con.'],
],

    ];
}

/* Tên hiển thị của mỗi chủ đề (dùng cho bảng tiến độ và chứng chỉ) */
function topic_names(): array
{
    return [
        'den-3-mau'    => '🚦 Đèn giao thông',
        'den-do'       => '🔴 Đèn đỏ',
        'den-vang'     => '🟡 Đèn vàng',
        'den-xanh'     => '🟢 Đèn xanh',
        'mu-bao-hiem'  => '⛑️ Mũ bảo hiểm',
        'vach-ke'      => '🚸 Sang đường an toàn',
        'bien-bao'     => '🚸 Biển báo giao thông',
        'bien-stop'    => '🛑 Biển STOP',
        'xe-dap'       => '🚲 Đi xe đạp',
        'day-an-toan'  => '🚗 Dây an toàn',
        'cuu-thuong'   => '🚑 Xe ưu tiên',
        'canh-sat'     => '👮 Cảnh sát giao thông',
        'duong-sat'    => '🚂 Đường sắt',
        'diem-mu'      => '🚛 Điểm mù xe tải',
        'xe-buyt'      => '🚌 Đi xe buýt',
        'troi-mua'     => '☔ Trời mưa, đường trơn',
        'ban-dem'      => '🌙 Đi đường ban đêm',
        'nga-tu'       => '🛣️ Ngã tư, vòng xuyến',
        'via-he'       => '🚶 Vỉa hè',
        'choi-duong'   => '⛔ Không chơi dưới lòng đường',
        'lac-duong'    => '📞 Bị lạc đường',
        'toc-do'       => '🏎️ Tốc độ',
        'luat-giao-thong'=> '📜 Luật giao thông Việt Nam',
        'an-toan-chung'=> '💛 An toàn giao thông chung',
        // ----- [MỞ RỘNG] 6 chủ đề mới -----
        'ghe-tre-em'   => '👶 Ghế an toàn cho trẻ',
        'cong-truong'  => '🏫 An toàn cổng trường',
        'gap-tai-nan'  => '🚑 Khi gặp tai nạn',
        'ngap-nuoc'    => '🌊 Đường ngập nước',
        'cho-khuat'    => '🙈 Chỗ khuất tầm nhìn',
        'nhuong-duong' => '💚 Nhường đường, văn hoá giao thông',
    ];
}

/* =====================================================================
   HỌC QUA TÌNH HUỐNG TƯƠNG TÁC
   Bé xem hoạt cảnh → chọn cách xử lý → AI giải thích vì sao đúng/sai.
   Đây là "điểm sáng tạo" cốt lõi: bé THỰC HÀNH RA QUYẾT ĐỊNH,
   chứ không chỉ nghe giảng lý thuyết.
   ===================================================================== */

/* ---------- Các mảnh vẽ dùng lại cho nhiều cảnh ---------- */
function sc_road($y = 90, $h = 70) {   // mặt đường
    return '<rect x="0" y="' . $y . '" width="440" height="' . $h . '" fill="#5B6478"/>'
         . '<rect x="0" y="' . ($y - 6) . '" width="440" height="6" fill="#93A0B5"/>'
         . '<rect x="0" y="' . ($y + $h) . '" width="440" height="6" fill="#93A0B5"/>';
}
function sc_kid($x, $y, $color = '#38BDF8', $scale = 1) {   // bé
    return '<g transform="translate(' . $x . ',' . $y . ') scale(' . $scale . ')">'
         . '<circle cx="0" cy="0" r="11" fill="#FFD9B8"/>'
         . '<rect x="-9" y="12" width="18" height="24" rx="7" fill="' . $color . '"/>'
         . '<path d="M-6 36 l-4 14 M6 36 l4 14" stroke="#2C3550" stroke-width="4" stroke-linecap="round"/>'
         . '<path d="M-9 20 l-10 8 M9 20 l10 8" stroke="#FFD9B8" stroke-width="4.5" stroke-linecap="round"/>'
         . '</g>';
}
function sc_car($x, $y, $color = '#FF6B57', $flip = false) {   // ô tô nhìn ngang
    $t = $flip ? 'translate(' . ($x + 90) . ',' . $y . ') scale(-1,1)' : 'translate(' . $x . ',' . $y . ')';
    return '<g transform="' . $t . '">'
         . '<rect x="0" y="18" width="90" height="26" rx="7" fill="' . $color . '"/>'
         . '<path d="M16 18 l10 -14 h38 l10 14" fill="' . $color . '" opacity=".85"/>'
         . '<rect x="26" y="6" width="20" height="12" rx="3" fill="#BFE4F8"/>'
         . '<circle cx="20" cy="46" r="9" fill="#2C3550"/><circle cx="70" cy="46" r="9" fill="#2C3550"/>'
         . '</g>';
}
function sc_truck($x, $y) {   // xe tải
    return '<g transform="translate(' . $x . ',' . $y . ')">'
         . '<rect x="0" y="0" width="86" height="48" rx="5" fill="#E9EFF8" stroke="#B8C6DC" stroke-width="2"/>'
         . '<path d="M86 14 h22 l16 20 v14 h-38 Z" fill="#4A5F8F"/>'
         . '<rect x="90" y="18" width="18" height="13" rx="3" fill="#9FD5F5"/>'
         . '<circle cx="24" cy="50" r="10" fill="#2C3550"/><circle cx="104" cy="50" r="10" fill="#2C3550"/>'
         . '</g>';
}
function sc_light($x, $y, $on) {   // đèn giao thông nhỏ
    $c = ['do' => '#3A4358', 'vang' => '#3A4358', 'xanh' => '#3A4358'];
    $c[$on] = ['do' => '#EF4444', 'vang' => '#FBBF24', 'xanh' => '#22C55E'][$on] ?? '#3A4358';
    return '<g transform="translate(' . $x . ',' . $y . ')">'
         . '<rect x="0" y="0" width="26" height="62" rx="7" fill="#2C3550"/>'
         . '<circle cx="13" cy="14" r="8" fill="' . $c['do'] . '"/>'
         . '<circle cx="13" cy="31" r="8" fill="' . $c['vang'] . '"/>'
         . '<circle cx="13" cy="48" r="8" fill="' . $c['xanh'] . '"/>'
         . '<rect x="10" y="62" width="6" height="20" fill="#8A94A8"/></g>';
}
function sc_zebra($x) {   // vạch kẻ đường
    $s = '';
    for ($i = 0; $i < 4; $i++) {
        $s .= '<rect x="' . ($x + $i * 22) . '" y="92" width="13" height="66" rx="2" fill="#fff"/>';
    }
    return $s;
}
function sc_svg($inner) {
    return '<svg viewBox="0 0 440 190" xmlns="http://www.w3.org/2000/svg" role="img">' . $inner . '</svg>';
}

/* =====================================================================
   12 TÌNH HUỐNG — bé phải RA QUYẾT ĐỊNH
   Mỗi tình huống: cảnh + câu hỏi + 3 lựa chọn + giải thích từng lựa chọn
   ===================================================================== */
function situations(): array
{
    return [

['id'=>'s01','title'=>'Đèn vàng bật lên','skill'=>'Đèn tín hiệu','topic'=>'den-vang',
 'scene'=>sc_svg(sc_road().sc_zebra(150).sc_light(390,18,'vang').sc_kid(120,60).sc_car(20,100,'#FF6B57')),
 'q'=>'Con đang đứng ở vỉa hè chuẩn bị sang đường thì đèn chuyển sang màu VÀNG. Con làm gì?',
 'o'=>[
   ['t'=>'Chạy thật nhanh qua đường cho kịp','ok'=>false,
    'e'=>'Nguy hiểm lắm con ơi! 😰 Đèn vàng nghĩa là xe sắp dừng, nhưng cũng có xe đang cố chạy nhanh qua. Con chạy ra lúc này rất dễ bị đâm.'],
   ['t'=>'Đứng yên trên vỉa hè, chờ đèn xanh cho người đi bộ','ok'=>true,
    'e'=>'Chính xác! 🎉 Đèn vàng = chuẩn bị dừng, KHÔNG phải để đi nhanh. Con hãy kiên nhẫn đứng chờ đèn xanh dành cho người đi bộ. An toàn hơn hết!'],
   ['t'=>'Vừa đi vừa giơ tay ra hiệu cho xe dừng','ok'=>false,
    'e'=>'Chưa đúng đâu con. Giơ tay là tốt, nhưng lúc đèn vàng xe không kịp dừng lại đâu. Phải chờ đèn xanh rồi mới bước xuống đường nhé.'],
 ]],

['id'=>'s02','title'=>'Quả bóng lăn ra đường','skill'=>'Nguy hiểm & khẩn cấp','topic'=>'choi-duong',
 'scene'=>sc_svg(sc_road().sc_kid(60,60,'#FFD166').'<circle cx="240" cy="128" r="16" fill="#FF6B57"/><path d="M240 112 a16 16 0 0 1 0 32" fill="#fff" opacity=".5"/>'.sc_car(330,100,'#38BDF8',true).'<path d="M92 118 q60 -30 130 -2" stroke="#FFD166" stroke-width="3" stroke-dasharray="6 5" fill="none"/>'),
 'q'=>'Con đang chơi bóng thì quả bóng lăn ra giữa đường. Xe đang chạy tới. Con làm gì?',
 'o'=>[
   ['t'=>'Chạy ngay ra nhặt bóng trước khi xe tới','ok'=>false,
    'e'=>'KHÔNG được con ơi! 🚫 Đây là tình huống khiến RẤT NHIỀU bạn nhỏ gặp tai nạn. Khi chạy theo bóng, con không nhìn đường, còn bác tài không kịp phanh.'],
   ['t'=>'DỪNG LẠI, nhờ người lớn lấy giúp hoặc chờ đường vắng hẳn','ok'=>true,
    'e'=>'Giỏi lắm! 🎉 Nhớ câu này nhé: **"Bóng mất còn mua được — con thì không"**. Luôn dừng lại và nhờ người lớn giúp.'],
   ['t'=>'Vẫy tay cho xe dừng rồi chạy ra','ok'=>false,
    'e'=>'Chưa an toàn đâu con. Xe có thể không thấy con vẫy, hoặc thấy nhưng không phanh kịp. Đừng bao giờ bước xuống lòng đường vì quả bóng.'],
 ]],

['id'=>'s03','title'=>'Xe đỗ che khuất tầm nhìn','skill'=>'Sang đường','topic'=>'vach-ke',
 'scene'=>sc_svg(sc_road().sc_car(90,100,'#B8C6DC').sc_car(200,100,'#B8C6DC').sc_kid(150,72,'#34D399',0.85).'<path d="M330 130 h60 m-12 -8 l12 8 l-12 8" stroke="#EF4444" stroke-width="4" fill="none" stroke-linecap="round"/><text x="360" y="115" font-size="12" font-weight="700" fill="#EF4444" text-anchor="middle" font-family="sans-serif">Xe đang tới!</text>'),
 'q'=>'Con muốn qua đường, nhưng chỗ đó có 2 chiếc ô tô đang đỗ che khuất. Con làm gì?',
 'o'=>[
   ['t'=>'Lách qua giữa 2 xe rồi băng nhanh sang','ok'=>false,
    'e'=>'Rất nguy hiểm! 😰 Đứng giữa 2 xe đỗ, con **không thấy xe đang chạy tới**, và tài xế cũng **không thấy con**. Đây là chỗ nguy hiểm nhất!'],
   ['t'=>'Đi bộ đến chỗ có vạch kẻ đường, nơi nhìn rõ hai bên','ok'=>true,
    'e'=>'Xuất sắc! 🎉 Đi thêm vài chục mét không mất bao lâu, nhưng đổi lại con nhìn rõ xe và xe cũng nhìn rõ con. Đó mới là qua đường an toàn.'],
   ['t'=>'Trèo lên nắp xe để nhìn cho rõ rồi mới qua','ok'=>false,
    'e'=>'Không được đâu con 😅 Trèo lên xe người khác là sai, và cũng chẳng an toàn hơn chút nào. Hãy tìm vạch kẻ đường nhé!'],
 ]],

['id'=>'s04','title'=>'Vừa xuống xe buýt','skill'=>'Xe đạp & xe buýt','topic'=>'xe-buyt',
 'scene'=>sc_svg(sc_road().'<rect x="60" y="52" width="150" height="62" rx="8" fill="#FFD166"/><rect x="70" y="60" width="30" height="22" rx="3" fill="#BFE4F8"/><rect x="110" y="60" width="30" height="22" rx="3" fill="#BFE4F8"/><rect x="150" y="60" width="30" height="22" rx="3" fill="#BFE4F8"/><circle cx="90" cy="116" r="11" fill="#2C3550"/><circle cx="180" cy="116" r="11" fill="#2C3550"/><text x="135" y="98" font-size="11" font-weight="700" fill="#8A6A00" text-anchor="middle" font-family="sans-serif">XE BUÝT</text>'.sc_kid(240,72,'#FF6B57',0.85).sc_car(340,104,'#38BDF8',true)),
 'q'=>'Con vừa xuống xe buýt. Nhà con ở bên kia đường. Con làm gì?',
 'o'=>[
   ['t'=>'Đi vòng ra phía trước đầu xe buýt rồi băng qua','ok'=>false,
    'e'=>'Nguy hiểm lắm! 😰 Xe buýt to che hết tầm nhìn. Xe máy đang chạy tới **không thấy con**, mà con cũng **không thấy họ**. Đây là tai nạn rất hay xảy ra.'],
   ['t'=>'Đứng yên trên vỉa hè, chờ xe buýt đi khuất hẳn rồi mới qua','ok'=>true,
    'e'=>'Chuẩn luôn! 🎉 Chờ xe buýt đi hẳn, lúc đó con nhìn rõ cả hai chiều đường. Chờ 10 giây đổi lấy an toàn — quá đáng giá!'],
   ['t'=>'Chạy vòng ra phía sau đuôi xe buýt cho nhanh','ok'=>false,
    'e'=>'Cũng nguy hiểm như phía trước con ạ. Xe buýt vẫn che khuất tầm nhìn. Hãy CHỜ xe đi khuất nhé!'],
 ]],

['id'=>'s05','title'=>'Đứng cạnh xe tải chờ đèn đỏ','skill'=>'Nguy hiểm & khẩn cấp','topic'=>'diem-mu',
 'scene'=>sc_svg('<rect x="0" y="120" width="440" height="70" fill="#5B6478"/><rect x="120" y="30" width="200" height="90" fill="#EF4444" opacity=".14"/>'.sc_truck(180,60).sc_kid(140,90,'#FFD166',0.8).sc_light(370,20,'do').'<text x="150" y="46" font-size="12" font-weight="700" fill="#B91C1C" text-anchor="middle" font-family="sans-serif">⚠️ ĐIỂM MÙ</text>'),
 'q'=>'Con đi xe đạp, dừng đèn đỏ ngay sát bên hông một chiếc xe tải to. Con nên làm gì?',
 'o'=>[
   ['t'=>'Cứ đứng yên đó, xe tải cũng đang dừng mà','ok'=>false,
    'e'=>'Nguy hiểm cực kỳ! 😰 Con đang ở trong **ĐIỂM MÙ** — bác tài hoàn toàn không thấy con. Khi đèn xanh, xe tải rẽ phải, bánh sau sẽ cuốn vào con.'],
   ['t'=>'Lùi lại phía sau xe tải, hoặc dừng cách xa ra một đoạn','ok'=>true,
    'e'=>'Rất giỏi! 🎉 Nhớ quy tắc vàng: **"Không thấy gương chiếu hậu → bác tài KHÔNG thấy con"**. Luôn tránh xa hông xe tải, xe buýt.'],
   ['t'=>'Bấm chuông thật to để bác tài biết con ở đó','ok'=>false,
    'e'=>'Ý tốt nhưng chưa đủ con ạ. Xe tải rất ồn, bác tài có thể không nghe. Cách chắc chắn nhất là **tránh xa** khỏi vùng điểm mù.'],
 ]],

['id'=>'s06','title'=>'Trời mưa to','skill'=>'Sang đường','topic'=>'troi-mua',
 'scene'=>sc_svg('<rect x="0" y="0" width="440" height="190" fill="#4A5568"/>'.sc_road().'<g stroke="#9FD5F5" stroke-width="2" opacity=".6"><path d="M30 10 l-8 26 M80 0 l-8 26 M130 14 l-8 26 M180 4 l-8 26 M230 12 l-8 26 M280 2 l-8 26 M330 16 l-8 26 M390 6 l-8 26"/></g>'.sc_kid(130,62,'#FFD166').'<path d="M100 44 q30 -22 60 0" stroke="#FF6B57" stroke-width="5" fill="#FF6B57" opacity=".9"/><path d="M130 44 v16" stroke="#8A94A8" stroke-width="2.5"/>'.sc_car(300,100,'#38BDF8',true).'<ellipse cx="330" cy="152" rx="46" ry="7" fill="#9FD5F5" opacity=".4"/>'),
 'q'=>'Trời mưa to, con che ô và muốn qua đường cho nhanh kẻo ướt. Con làm gì?',
 'o'=>[
   ['t'=>'Chạy thật nhanh qua đường cho đỡ ướt','ok'=>false,
    'e'=>'Nguy hiểm con ơi! 😰 Trời mưa **đường trơn**, con dễ trượt ngã. Xe cũng **phanh lâu gấp đôi** bình thường. Chạy lúc này là lúc dễ tai nạn nhất!'],
   ['t'=>'Nghiêng ô ra sau để nhìn rõ, đi CHẬM và chờ lâu hơn bình thường','ok'=>true,
    'e'=>'Chuẩn! 🎉 Trời mưa phải đi CHẬM hơn, chờ LÂU hơn, và **không che ô kín mặt**. Ướt một chút không sao, an toàn mới quan trọng.'],
   ['t'=>'Cụp ô xuống thấp che kín đầu rồi băng qua','ok'=>false,
    'e'=>'Không nên đâu con! Che kín ô là con **không nhìn thấy xe**, mà xe cũng khó thấy con. Hãy nghiêng ô ra sau và quan sát kỹ nhé.'],
 ]],

['id'=>'s07','title'=>'Đường không có vỉa hè','skill'=>'Sang đường','topic'=>'via-he',
 'scene'=>sc_svg('<rect x="0" y="80" width="440" height="110" fill="#5B6478"/><rect x="0" y="74" width="440" height="6" fill="#93A0B5"/><path d="M0 130 h440" stroke="#FFD166" stroke-width="3" stroke-dasharray="18 12"/>'.sc_kid(70,100,'#34D399',0.9).sc_car(260,140,'#FF6B57',true).'<path d="M240 160 h-70 m12 -8 l-12 8 l12 8" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round"/><text x="120" y="60" font-size="12" font-weight="700" fill="#243B6B" font-family="sans-serif">Đường không có vỉa hè</text>'),
 'q'=>'Con đi bộ trên đoạn đường KHÔNG có vỉa hè. Con nên đi bên nào?',
 'o'=>[
   ['t'=>'Đi mép bên PHẢI, cùng chiều với xe chạy','ok'=>false,
    'e'=>'Chưa an toàn con ạ. Đi cùng chiều nghĩa là xe **từ sau lưng** lao tới mà con không hề biết, không tránh kịp.'],
   ['t'=>'Đi mép bên TRÁI, ngược chiều xe chạy để NHÌN THẤY xe','ok'=>true,
    'e'=>'Đúng rồi! 🎉 Đi ngược chiều xe để con **nhìn thấy xe đang tới** và tránh kịp. Nhớ nhé: không có vỉa hè → đi bên trái!'],
   ['t'=>'Đi giữa lòng đường vì đường đang vắng','ok'=>false,
    'e'=>'Tuyệt đối không con ơi! 🚫 Đường vắng lúc này nhưng xe có thể xuất hiện bất ngờ. Luôn đi sát mép đường.'],
 ]],

['id'=>'s08','title'=>'Xe cứu thương hú còi','skill'=>'Nguy hiểm & khẩn cấp','topic'=>'cuu-thuong',
 'scene'=>sc_svg(sc_road().'<rect x="70" y="70" width="120" height="50" rx="8" fill="#fff" stroke="#D9E6F8" stroke-width="2"/><rect x="70" y="104" width="120" height="16" fill="#EF4444"/><path d="M190 82 h22 l16 20 v18 h-38 Z" fill="#EEF4FC" stroke="#D9E6F8" stroke-width="2"/><rect x="110" y="80" width="28" height="9" rx="2" fill="#EF4444"/><rect x="120" y="70" width="9" height="28" rx="2" fill="#EF4444"/><rect x="95" y="58" width="30" height="11" rx="5" fill="#3B82F6"/><circle cx="98" cy="122" r="12" fill="#2C3550"/><circle cx="180" cy="122" r="12" fill="#2C3550"/><text x="60" y="40" font-size="13" font-weight="700" fill="#E03131" font-family="sans-serif">🔊 Còi hú!</text>'.sc_kid(330,66,'#FFD166')),
 'q'=>'Con đang chuẩn bị sang đường thì nghe tiếng còi hú, thấy xe cứu thương chạy tới. Con làm gì?',
 'o'=>[
   ['t'=>'Chạy nhanh qua đường trước khi xe cứu thương tới','ok'=>false,
    'e'=>'Không được con ơi! 🚫 Xe cứu thương đang chạy rất nhanh để cứu người. Con chạy ra là cản đường và tự đẩy mình vào nguy hiểm.'],
   ['t'=>'Đứng gọn trên vỉa hè, chờ xe qua hẳn rồi mới đi','ok'=>true,
    'e'=>'Rất tốt! 🎉 Trên xe có thể là ông bà của một bạn nhỏ nào đó đang cần cấp cứu. Mỗi giây con nhường có thể cứu một mạng người 💛'],
   ['t'=>'Chạy theo sau xe cứu thương để qua đường cho nhanh','ok'=>false,
    'e'=>'Cực kỳ nguy hiểm! 😰 Phía sau xe cứu thương có thể còn xe khác đang chạy tới. Hãy đứng yên chờ nhé.'],
 ]],

['id'=>'s09','title'=>'Chuông đường sắt reo','skill'=>'Nguy hiểm & khẩn cấp','topic'=>'duong-sat',
 'scene'=>sc_svg('<rect x="0" y="110" width="440" height="80" fill="#7A8395"/><rect x="0" y="130" width="440" height="7" fill="#3A4358"/><rect x="0" y="152" width="440" height="7" fill="#3A4358"/><g fill="#5B6478"><rect x="40" y="126" width="9" height="38"/><rect x="120" y="126" width="9" height="38"/><rect x="200" y="126" width="9" height="38"/><rect x="280" y="126" width="9" height="38"/><rect x="360" y="126" width="9" height="38"/></g><rect x="150" y="96" width="180" height="9" rx="3" fill="#E03131"/><rect x="150" y="96" width="30" height="9" fill="#fff"/><rect x="210" y="96" width="30" height="9" fill="#fff"/><rect x="270" y="96" width="30" height="9" fill="#fff"/><rect x="140" y="60" width="10" height="50" fill="#8A94A8"/><circle cx="145" cy="52" r="10" fill="#EF4444"/><text x="240" y="40" font-size="13" font-weight="700" fill="#E03131" text-anchor="middle" font-family="sans-serif">🔔 Chuông reo — rào đang hạ!</text>'.sc_kid(60,72,'#38BDF8',0.85)),
 'q'=>'Con sắp qua đường sắt thì chuông reo, rào chắn bắt đầu hạ xuống. Con làm gì?',
 'o'=>[
   ['t'=>'Chạy nhanh qua trước khi rào hạ hẳn','ok'=>false,
    'e'=>'TUYỆT ĐỐI KHÔNG! 🚫 Tàu đang tới rất gần rồi. Tàu nặng hàng nghìn tấn, **không thể phanh gấp** — tàu không tránh được con đâu!'],
   ['t'=>'Đứng chờ sau vạch, đợi tàu qua và rào MỞ HẲN mới đi','ok'=>true,
    'e'=>'Chính xác! 🎉 Nhớ nhé: **"Tàu không tránh được con — chỉ con tránh được tàu"**. Và phải chờ rào mở HẲN, vì có thể còn tàu thứ hai.'],
   ['t'=>'Chui qua dưới rào chắn cho nhanh','ok'=>false,
    'e'=>'Rất nhiều tai nạn thương tâm xảy ra vì điều này 😢 Chui qua rào là đặt mình ngay trên đường ray khi tàu sắp tới. Đừng bao giờ làm vậy!'],
 ]],

['id'=>'s10','title'=>'Quên mũ bảo hiểm','skill'=>'Mũ bảo hiểm & ngồi xe','topic'=>'mu-bao-hiem',
 'scene'=>sc_svg(sc_road().'<circle cx="150" cy="132" r="22" fill="none" stroke="#2C3550" stroke-width="5"/><circle cx="250" cy="132" r="22" fill="none" stroke="#2C3550" stroke-width="5"/><path d="M150 132 L190 132 L210 100 L250 132" stroke="#FF6B57" stroke-width="6" fill="none" stroke-linecap="round"/><rect x="185" y="96" width="40" height="14" rx="6" fill="#4A5F8F"/><circle cx="205" cy="70" r="14" fill="#FFD9B8"/><path d="M191 68 A14 14 0 0 1 219 68 Z" fill="#38BDF8"/><rect x="197" y="84" width="16" height="14" rx="5" fill="#34D399"/><circle cx="240" cy="72" r="12" fill="#FFD9B8"/><rect x="233" y="84" width="14" height="14" rx="5" fill="#FFD166"/><text x="240" y="52" font-size="26" text-anchor="middle" font-family="sans-serif">❓</text><text x="330" y="70" font-size="12" font-weight="700" fill="#E03131" font-family="sans-serif">Con chưa đội mũ!</text>'),
 'q'=>'Ba chở con đi học bằng xe máy, nhưng con quên mũ bảo hiểm ở nhà. Ba bảo "đi gần thôi, không sao đâu". Con làm gì?',
 'o'=>[
   ['t'=>'Nghe lời ba, đi luôn vì trường gần mà','ok'=>false,
    'e'=>'Chưa đúng đâu con. Con biết không, **đa số tai nạn xảy ra GẦN NHÀ** đấy! Mũ bảo hiểm giảm tới 70% nguy cơ chấn thương đầu.'],
   ['t'=>'Xin ba chờ một chút để con quay vào lấy mũ','ok'=>true,
    'e'=>'Con giỏi lắm! 🎉 Con vừa bảo vệ chính mình, vừa nhắc được ba. Con chính là **"cảnh sát nhỏ"** của gia đình đó! 👮'],
   ['t'=>'Đội mũ của ba, dù mũ rất rộng so với đầu con','ok'=>false,
    'e'=>'Ý tốt nhưng chưa an toàn con ạ. Mũ **quá rộng** sẽ văng ra khi ngã, không bảo vệ được. Mũ phải vừa với đầu con mới có tác dụng.'],
 ]],

['id'=>'s11','title'=>'Ngồi ô tô','skill'=>'Mũ bảo hiểm & ngồi xe','topic'=>'day-an-toan',
 'scene'=>sc_svg('<rect x="40" y="30" width="360" height="130" rx="20" fill="#EEF4FC" stroke="#B8C6DC" stroke-width="3"/><path d="M100 40 h50 a9 9 0 0 1 9 9 v66 h-68 v-66 a9 9 0 0 1 9 -9 Z" fill="#4A5F8F"/><path d="M250 40 h50 a9 9 0 0 1 9 9 v66 h-68 v-66 a9 9 0 0 1 9 -9 Z" fill="#4A5F8F"/><circle cx="125" cy="66" r="15" fill="#FFD9B8"/><rect x="112" y="84" width="26" height="28" rx="9" fill="#FFD166"/><text x="125" y="140" font-size="11" font-weight="700" fill="#E03131" text-anchor="middle" font-family="sans-serif">Ghế TRƯỚC</text><circle cx="275" cy="66" r="15" fill="#FFD9B8"/><rect x="262" y="84" width="26" height="28" rx="9" fill="#34D399"/><path d="M260 82 L292 110" stroke="#E03131" stroke-width="5" stroke-linecap="round"/><text x="275" y="140" font-size="11" font-weight="700" fill="#177C4F" text-anchor="middle" font-family="sans-serif">Ghế SAU ✓</text>'),
 'q'=>'Con lên ô tô đi chơi. Con nên ngồi ở đâu và làm gì?',
 'o'=>[
   ['t'=>'Ngồi ghế trước cạnh ba cho vui, không cần thắt dây vì đi gần','ok'=>false,
    'e'=>'Không an toàn con ạ 😰 Ghế trước có **túi khí**, khi bung ra rất mạnh, có thể làm trẻ em bị thương. Và dây an toàn thì LUÔN phải thắt.'],
   ['t'=>'Ngồi ghế SAU và thắt dây an toàn ngay khi lên xe','ok'=>true,
    'e'=>'Xuất sắc! 🎉 Trẻ em dưới 10 tuổi nên ngồi ghế sau. Dây an toàn giống như **cái ôm chặt của mẹ**, giữ con lại khi xe phanh gấp 🤗'],
   ['t'=>'Ngồi ghế sau nhưng không thắt dây cho thoải mái','ok'=>false,
    'e'=>'Gần đúng rồi nhưng còn thiếu con ơi! Ngồi ghế sau đúng rồi, nhưng **vẫn phải thắt dây an toàn**. Không có ngoại lệ nào cả!'],
 ]],

['id'=>'s12','title'=>'Bị lạc ba mẹ','skill'=>'Nguy hiểm & khẩn cấp','topic'=>'lac-duong',
 'scene'=>sc_svg('<rect x="0" y="0" width="440" height="190" fill="#F0F5FC"/><rect x="0" y="150" width="440" height="40" fill="#93A0B5"/><g fill="#D9E6F8"><rect x="20" y="50" width="60" height="100" rx="4"/><rect x="100" y="30" width="70" height="120" rx="4"/><rect x="330" y="45" width="80" height="105" rx="4"/></g>'.sc_kid(220,90,'#FF6B57').'<text x="220" y="60" font-size="24" text-anchor="middle" font-family="sans-serif">😰</text><circle cx="330" cy="105" r="14" fill="#FFD9B8"/><rect x="318" y="120" width="24" height="30" rx="8" fill="#2B6CD4"/><text x="330" y="82" font-size="20" text-anchor="middle" font-family="sans-serif">👮</text><text x="330" y="180" font-size="11" font-weight="700" fill="#177C4F" text-anchor="middle" font-family="sans-serif">Chú cảnh sát</text>'),
 'q'=>'Con đi siêu thị với mẹ nhưng bị lạc. Con hoảng quá. Con làm gì đầu tiên?',
 'o'=>[
   ['t'=>'Chạy đi khắp nơi tìm mẹ thật nhanh','ok'=>false,
    'e'=>'Chưa đúng con ơi. Con càng chạy, mẹ càng khó tìm thấy con. Hai người cứ đi tìm nhau mà không gặp được!'],
   ['t'=>'ĐỨNG YÊN tại chỗ, rồi nhờ chú cảnh sát / bảo vệ / nhân viên giúp','ok'=>true,
    'e'=>'Chính xác! 🎉 **Đứng yên** — mẹ sẽ quay lại đúng chỗ đó tìm con. Rồi nhờ chú cảnh sát 👮 hoặc người mặc đồng phục. Nhớ số điện thoại của mẹ nhé!'],
   ['t'=>'Đi theo một cô chú lạ tốt bụng nói sẽ đưa con về nhà','ok'=>false,
    'e'=>'TUYỆT ĐỐI KHÔNG con ơi! 🚫 Dù họ nói gì, cho quà hay kẹo, con cũng **không đi theo người lạ**. Chỉ nhờ chú cảnh sát hoặc người mặc đồng phục.'],
 ]],

    ];
}

/* Lấy 1 tình huống theo mã */
function situation_by_id(string $id): ?array
{
    foreach (situations() as $s) {
        if ($s['id'] === $id) return $s;
    }
    return null;
}

/* Gợi ý hoạt động THỰC HÀNH NGOÀI ĐỜI — dành cho phụ huynh/giáo viên */
function practice_tip(string $topic): string
{
    $t = [
        'den-3-mau'   => 'Khi chở bé qua ngã tư, hãy hỏi: "Đèn đang màu gì? Mình phải làm gì?" — để bé tự trả lời.',
        'den-do'      => 'Dừng đèn đỏ, hãy nhờ bé đếm ngược thời gian và nhắc: "Dù đường vắng cũng phải chờ".',
        'den-vang'    => 'Chỉ cho bé thấy khi đèn vàng bật, xe phải GIẢM tốc chứ không tăng tốc.',
        'den-xanh'    => 'Đèn xanh, hãy cùng bé nhìn trái–phải trước khi đi, tạo thói quen quan sát.',
        'mu-bao-hiem' => 'Cho bé TỰ đội mũ và tự cài quai mỗi ngày. Kiểm tra "vừa 2 ngón tay" cùng bé.',
        'vach-ke'     => 'Mỗi lần qua đường, đọc to cùng bé: "Dừng — Nhìn — Nghe — Đi". Để bé dẫn, bạn theo sau.',
        'bien-bao'    => 'Trên đường đi học, chơi trò "săn biển báo": bé chỉ biển, nói tên và ý nghĩa.',
        'bien-stop'   => 'Tìm biển STOP gần nhà, cho bé xem hình 8 cạnh và giải thích vì sao dừng hẳn.',
        'xe-dap'      => 'Tập cùng bé ở sân rộng: đi thẳng hàng, giơ tay xin đường trước khi rẽ.',
        'day-an-toan' => 'Quy định trong nhà: "Xe chưa lăn bánh nếu chưa ai thắt dây". Cho bé kiểm tra cả nhà.',
        'cuu-thuong'  => 'Nghe còi hú, hãy giải thích cho bé: "Trên xe có người đang cần cấp cứu, mình nhường đường".',
        'canh-sat'    => 'Chỉ cho bé thấy chú CSGT đang điều khiển, giải thích hiệu lệnh tay.',
        'duong-sat'   => 'Nếu gần nhà có đường sắt, dẫn bé tới xem rào chắn hạ và giải thích vì sao tàu không phanh kịp.',
        'diem-mu'     => 'Cho bé đứng cạnh ô tô nhà mình (xe TẮT máy), bạn ngồi ghế lái — để bé thấy bạn KHÔNG nhìn thấy bé.',
        'xe-buyt'     => 'Đi xe buýt cùng bé, thực hành: xuống xe → đứng chờ xe đi khuất → mới qua đường.',
        'troi-mua'    => 'Ngày mưa, cùng bé quan sát mặt đường trơn và giải thích vì sao xe phanh lâu hơn.',
        'ban-dem'     => 'Buổi tối, cho bé đứng xa và so sánh: mặc áo tối vs áo sáng — bé sẽ thấy khác biệt ngay.',
        'nga-tu'      => 'Đứng ở ngã tư (nơi an toàn), cùng bé đếm xem có bao nhiêu xe RẼ mà không xi-nhan.',
        'via-he'      => 'Đi bộ cùng bé trên đoạn không vỉa hè, để bé tự chọn bên đi và giải thích lý do.',
        'choi-duong'  => 'Thoả thuận với bé: bóng lăn ra đường thì DỪNG và gọi người lớn. Tập thử vài lần ở sân.',
        'lac-duong'   => 'Cho bé học thuộc số điện thoại của bạn. Đến nơi đông người, chỉ trước "nếu lạc thì đứng đây".',
        'toc-do'      => 'Chỉ biển giới hạn tốc độ gần trường, giải thích vì sao khu trường học xe phải đi chậm.',
        // ----- [MỞ RỘNG] -----
        'ghe-tre-em'  => 'Đo chiều cao của bé và cùng xem bé đã đủ 1,35 m chưa; giải thích vì sao bé ngồi hàng ghế sau.',
        'cong-truong' => 'Thống nhất với bé MỘT điểm hẹn cố định ở cổng trường, và tập chờ đúng chỗ đó vài hôm.',
        'gap-tai-nan' => 'Cho bé học thuộc 113 - 114 - 115. Đóng vai: bé gọi to nhờ người lớn giúp, không tự chạm vào người bị nạn.',
        'ngap-nuoc'   => 'Ngày mưa ngập, chỉ cho bé thấy chỗ nước xoáy trên miệng cống và giải thích vì sao không được lội qua.',
        'cho-khuat'   => 'Cho bé đứng nép sau xe nhà mình rồi hỏi: \"Con thấy được bao xa?\" — bé sẽ hiểu ngay thế nào là bị che khuất.',
        'nhuong-duong'=> 'Trên đường đi, cùng bé tìm một người để nhường đường hoặc giúp đỡ, rồi khen bé ngay khi bé làm được.',
    ];
    return $t[$topic] ?? 'Cùng bé quan sát và trò chuyện về chủ đề này khi đi đường.';
}

/* =====================================================================
   CÁ NHÂN HOÁ — ghi nhận bé hay sai chủ đề nào
   ===================================================================== */
function record_weak(PDO $pdo, int $userId, ?string $topic): void
{
    if ($topic === null || $topic === '') return;
    $pdo->prepare(
        "INSERT INTO aigs_weak (user_id, topic, wrong_count) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE wrong_count = wrong_count + 1, last_wrong = NOW()"
    )->execute([$userId, $topic]);
}

/* Câu hỏi gợi ý để bé ôn lại một chủ đề */
function topic_question(string $topic): string
{
    $q = [
        'den-3-mau'=>'Đèn giao thông có mấy màu?', 'den-do'=>'Đèn đỏ nghĩa là gì?',
        'den-vang'=>'Đèn vàng có được đi không?', 'den-xanh'=>'Đèn xanh nghĩa là gì?',
        'mu-bao-hiem'=>'Đội mũ bảo hiểm thế nào là đúng?',
        'vach-ke'=>'Sang đường thế nào cho an toàn?',
        'bien-bao'=>'Các loại biển báo giao thông?', 'bien-stop'=>'Biển STOP nghĩa là gì?',
        'xe-dap'=>'Đi xe đạp an toàn thế nào?', 'day-an-toan'=>'Ngồi ô tô cần lưu ý gì?',
        'cuu-thuong'=>'Gặp xe cứu thương thì làm gì?', 'canh-sat'=>'Cảnh sát giao thông ra hiệu thế nào?',
        'duong-sat'=>'Qua đường sắt cần chú ý gì?', 'diem-mu'=>'Điểm mù của xe tải là gì?',
        'xe-buyt'=>'Đi xe buýt cần lưu ý gì?', 'troi-mua'=>'Trời mưa đi đường thế nào?',
        'ban-dem'=>'Đi bộ ban đêm có nguy hiểm không?', 'nga-tu'=>'Ngã tư cần chú ý gì?',
        'via-he'=>'Đi bộ trên vỉa hè thế nào?', 'choi-duong'=>'Bóng lăn ra đường thì làm sao?',
        'lac-duong'=>'Con bị lạc thì phải làm gì?', 'toc-do'=>'Tốc độ ảnh hưởng thế nào?',
        'an-toan-chung'=>'An toàn giao thông là gì?',
        // [MỞ RỘNG]
        'ghe-tre-em'=>'Ghế an toàn cho trẻ em là gì?', 'cong-truong'=>'Ở cổng trường cần chú ý gì?',
        'gap-tai-nan'=>'Gặp tai nạn thì con phải làm gì?', 'ngap-nuoc'=>'Đường ngập nước có nguy hiểm không?',
        'cho-khuat'=>'Chỗ khuất tầm nhìn là chỗ nào?', 'nhuong-duong'=>'Nhường đường cho ai và vì sao?',
    ];
    return $q[$topic] ?? 'Giải thích về chủ đề này giúp con';
}

/* =====================================================================
   GOM TOÀN BỘ DỮ LIỆU HỌC TẬP CỦA BÉ
   Dùng chung cho: tiến độ, huy hiệu, chứng chỉ, bài kiểm tra
   (và sau này là trang báo cáo cho giáo viên)
   ===================================================================== */
function user_stats(PDO $pdo, int $userId): array
{
    $names = topic_names();
    $bank  = quiz_bank();

    // Các chủ đề đã học
    $st = $pdo->prepare("SELECT topic FROM aigs_progress WHERE user_id = ?");
    $st->execute([$userId]);
    $learned = array_column($st->fetchAll(), 'topic');

    // Điểm các bài kiểm tra nhỏ
    $st = $pdo->prepare("SELECT topic, best_score, total FROM aigs_quiz WHERE user_id = ?");
    $st->execute([$userId]);
    $quiz = [];
    $stars = 0;
    $quizPts = 0;
    foreach ($st->fetchAll() as $r) {
        $quiz[$r['topic']] = ['s' => (int)$r['best_score'], 't' => (int)$r['total']];
        $quizPts += (int) $r['best_score'];
        if ($r['total'] > 0 && $r['best_score'] >= $r['total']) $stars++;
    }

    // Bài kiểm tra đầu vào / đầu ra
    $st = $pdo->prepare("SELECT phase, score, total, detail, taken_at FROM aigs_test WHERE user_id = ?");
    $st->execute([$userId]);
    $pre = $post = null;
    foreach ($st->fetchAll() as $r) {
        $row = [
            'score'  => (int) $r['score'],
            'total'  => (int) $r['total'],
            'detail' => $r['detail'] ? json_decode($r['detail'], true) : null,
            'at'     => $r['taken_at'],
        ];
        if ($r['phase'] === 'pre') $pre = $row; else $post = $row;
    }

    // Kết quả game "Đường đến trường"
    $st = $pdo->prepare("SELECT sid, passed, first_try FROM aigs_situations WHERE user_id = ?");
    $st->execute([$userId]);
    $sitRows = $st->fetchAll();
    $sitPassed = 0; $sitPerfect = 0;
    foreach ($sitRows as $r) {
        if ($r['passed'])   $sitPassed++;
        if ($r['first_try']) $sitPerfect++;
    }
    $sitTotal = count(situations());

    // Điểm yếu (dùng cho cá nhân hoá và báo cáo giáo viên)
    $st = $pdo->prepare("SELECT topic, wrong_count FROM aigs_weak
                         WHERE user_id = ? AND wrong_count > 0
                         ORDER BY wrong_count DESC LIMIT 5");
    $st->execute([$userId]);
    $weak = $st->fetchAll();

    $totalTopics = count($names);
    // Điểm: chủ đề đã học ×10 · câu quiz đúng ×5 · tình huống qua được ×15
    $points = count($learned) * 10 + $quizPts * 5 + $sitPassed * 15;
    $level  = intdiv($points, 50) + 1;
    $certified = (count($learned) >= $totalTopics
                  && $stars >= (int) ceil(count($bank) * 0.8));

    return compact('names', 'bank', 'learned', 'quiz', 'stars', 'quizPts',
                   'points', 'level', 'totalTopics', 'certified', 'pre', 'post',
                   'sitPassed', 'sitPerfect', 'sitTotal', 'weak');
}

/* =====================================================================
   HỆ THỐNG HUY HIỆU 🏅
   Mỗi huy hiệu có điều kiện riêng — tự động trao khi bé đạt được.
   ===================================================================== */
function badge_list(): array
{
    return [
        'khoi-dau' => [
            'icon' => '🌱', 'name' => 'Khởi đầu',
            'desc' => 'Học chủ đề đầu tiên',
            'hint' => 'Hỏi AI Gia sư một câu bất kỳ về giao thông',
        ],
        'ham-hoc' => [
            'icon' => '📚', 'name' => 'Ham học',
            'desc' => 'Học đủ 5 chủ đề',
            'hint' => 'Hỏi AI về 5 chủ đề khác nhau',
        ],
        'chuyen-can' => [
            'icon' => '🔥', 'name' => 'Chuyên cần',
            'desc' => 'Học đủ 12 chủ đề',
            'hint' => 'Còn nhiều chủ đề thú vị lắm, cố lên!',
        ],
        'hoc-gioi' => [
            'icon' => '🎓', 'name' => 'Học giỏi toàn diện',
            'desc' => 'Học đủ TẤT CẢ chủ đề',
            'hint' => 'Mở bảng tiến độ xem còn thiếu chủ đề nào',
        ],
        'sao-dau-tien' => [
            'icon' => '⭐', 'name' => 'Ngôi sao đầu tiên',
            'desc' => 'Làm đúng 100% một bài kiểm tra',
            'hint' => 'Bấm "Làm bài kiểm tra" sau mỗi câu trả lời',
        ],
        'nam-sao' => [
            'icon' => '🌟', 'name' => 'Năm sao sáng',
            'desc' => 'Đạt 5 ngôi sao',
            'hint' => 'Làm đúng hết 5 bài kiểm tra khác nhau',
        ],
        'sieu-tri-nho' => [
            'icon' => '🧠', 'name' => 'Siêu trí nhớ',
            'desc' => 'Đạt 15 ngôi sao',
            'hint' => 'Làm lại bài nào chưa đạt điểm tuyệt đối nhé',
        ],
        'vua-den' => [
            'icon' => '🚦', 'name' => 'Vua đèn giao thông',
            'desc' => 'Đúng hết bài kiểm tra về cả 4 chủ đề đèn',
            'hint' => 'Học và kiểm tra: đèn đỏ, đèn vàng, đèn xanh, đèn 3 màu',
        ],
        'mu-vang' => [
            'icon' => '⛑️', 'name' => 'Mũ bảo hiểm vàng',
            'desc' => 'Đúng hết bài kiểm tra về mũ bảo hiểm',
            'hint' => 'Hỏi "Đội mũ bảo hiểm thế nào là đúng?" rồi làm bài',
        ],
        'nha-bien-bao' => [
            'icon' => '🚸', 'name' => 'Nhà biển báo học',
            'desc' => 'Đúng hết bài kiểm tra về biển báo và biển STOP',
            'hint' => 'Mở Thư viện biển báo rồi làm bài kiểm tra nhé',
        ],
        'tho-san-diem-mu' => [
            'icon' => '🚛', 'name' => 'Thợ săn điểm mù',
            'desc' => 'Đúng hết bài kiểm tra về điểm mù xe tải',
            'hint' => 'Hỏi "Điểm mù của xe tải là gì?" — bài học cứu mạng đó!',
        ],
        'nguoi-hung' => [
            'icon' => '🦸', 'name' => 'Người hùng nhỏ',
            'desc' => 'Đúng hết bài về xe cứu thương và bị lạc đường',
            'hint' => 'Học cách nhường xe ưu tiên và xử lý khi bị lạc',
        ],
        'khoi-dong' => [
            'icon' => '📝', 'name' => 'Sẵn sàng học',
            'desc' => 'Hoàn thành bài kiểm tra ĐẦU VÀO',
            'hint' => 'Bấm nút 🏅 → Làm bài kiểm tra đầu vào',
        ],
        'tien-bo' => [
            'icon' => '📈', 'name' => 'Tiến bộ vượt bậc',
            'desc' => 'Bài kiểm tra ĐẦU RA cao hơn đầu vào',
            'hint' => 'Học xong các chủ đề rồi làm bài kiểm tra đầu ra',
        ],
        'hoan-hao' => [
            'icon' => '💎', 'name' => 'Hoàn hảo',
            'desc' => 'Đúng 100% bài kiểm tra ĐẦU RA',
            'hint' => 'Học thật kỹ rồi làm bài đầu ra nhé!',
        ],
        'nha-thuc-hanh' => [
            'icon' => '🎮', 'name' => 'Nhà thực hành',
            'desc' => 'Vượt qua 5 tình huống trong game',
            'hint' => 'Mở game "Đường đến trường" 🗺️ và xử lý các tình huống',
        ],
        'di-het-duong' => [
            'icon' => '🏁', 'name' => 'Về đến trường',
            'desc' => 'Vượt qua TẤT CẢ 12 tình huống',
            'hint' => 'Hoàn thành cả chặng đường đến trường nhé!',
        ],
        'phan-xa-nhanh' => [
            'icon' => '⚡', 'name' => 'Phản xạ nhanh',
            'desc' => 'Xử lý đúng NGAY LẦN ĐẦU ở 8 tình huống',
            'hint' => 'Suy nghĩ kỹ rồi hãy chọn — đúng ngay lần đầu mới được ⭐',
        ],
        'tot-nghiep' => [
            'icon' => '🏆', 'name' => 'Tốt nghiệp',
            'desc' => 'Nhận được chứng chỉ hoàn thành khoá học',
            'hint' => 'Học hết chủ đề + đạt đủ số sao yêu cầu',
        ],
    ];
}

/* Tính xem bé đạt được những huy hiệu nào (dựa trên dữ liệu học tập) */
function badge_check(array $learned, array $quiz, int $stars, int $totalTopics,
                     ?array $pre, ?array $post, bool $certified,
                     int $sitPassed = 0, int $sitPerfect = 0, int $sitTotal = 12): array
{
    /* $quiz: ['mu-bao-hiem' => ['s'=>2,'t'=>2], ...] — s = điểm cao nhất, t = tổng câu */
    $perfect = function (string $topic) use ($quiz): bool {
        return isset($quiz[$topic]) && $quiz[$topic]['t'] > 0
               && $quiz[$topic]['s'] >= $quiz[$topic]['t'];
    };
    $allPerfect = function (array $topics) use ($perfect): bool {
        foreach ($topics as $t) if (!$perfect($t)) return false;
        return true;
    };

    $n = count($learned);
    $got = [];

    if ($n >= 1)  $got[] = 'khoi-dau';
    if ($n >= 5)  $got[] = 'ham-hoc';
    if ($n >= 12) $got[] = 'chuyen-can';
    if ($n >= $totalTopics) $got[] = 'hoc-gioi';

    if ($stars >= 1)  $got[] = 'sao-dau-tien';
    if ($stars >= 5)  $got[] = 'nam-sao';
    if ($stars >= 15) $got[] = 'sieu-tri-nho';

    if ($allPerfect(['den-do', 'den-vang', 'den-xanh', 'den-3-mau'])) $got[] = 'vua-den';
    if ($perfect('mu-bao-hiem'))                                       $got[] = 'mu-vang';
    if ($allPerfect(['bien-bao', 'bien-stop']))                        $got[] = 'nha-bien-bao';
    if ($perfect('diem-mu'))                                           $got[] = 'tho-san-diem-mu';
    if ($allPerfect(['cuu-thuong', 'lac-duong']))                      $got[] = 'nguoi-hung';

    if ($sitPassed >= 5)          $got[] = 'nha-thuc-hanh';
    if ($sitTotal > 0 && $sitPassed >= $sitTotal) $got[] = 'di-het-duong';
    if ($sitPerfect >= 8)         $got[] = 'phan-xa-nhanh';

    if ($pre)  $got[] = 'khoi-dong';
    if ($pre && $post && $post['score'] > $pre['score'])   $got[] = 'tien-bo';
    if ($post && $post['score'] >= $post['total'])         $got[] = 'hoan-hao';
    if ($certified) $got[] = 'tot-nghiep';

    return $got;
}

/* =====================================================================
   BÀI KIỂM TRA ĐẦU VÀO / ĐẦU RA — 15 câu, đo mức tiến bộ của bé
   Cùng một bộ đề cho cả 2 lần → so sánh mới công bằng.
   ===================================================================== */
function test_set(): array
{
    /* Mỗi dòng: [mã chủ đề, số thứ tự câu hỏi trong ngân hàng, nhóm kỹ năng]
       Chọn trải đều các nhóm kỹ năng để báo cáo biết bé YẾU MẢNG NÀO. */
    return [
        ['den-3-mau',   0, 'Đèn tín hiệu'],
        ['den-do',      0, 'Đèn tín hiệu'],
        ['den-vang',    0, 'Đèn tín hiệu'],
        ['bien-bao',    0, 'Biển báo'],
        ['bien-bao',    1, 'Biển báo'],
        ['bien-stop',   1, 'Biển báo'],
        ['vach-ke',     0, 'Sang đường'],
        ['vach-ke',     1, 'Sang đường'],
        ['via-he',      0, 'Sang đường'],
        ['mu-bao-hiem', 0, 'Mũ bảo hiểm & ngồi xe'],
        ['day-an-toan', 1, 'Mũ bảo hiểm & ngồi xe'],
        ['xe-dap',      1, 'Xe đạp & xe buýt'],
        ['xe-buyt',     0, 'Xe đạp & xe buýt'],
        ['diem-mu',     1, 'Nguy hiểm & khẩn cấp'],
        ['lac-duong',   1, 'Nguy hiểm & khẩn cấp'],
    ];
}

/* Lấy đề (KHÔNG kèm đáp án — chấm ở máy chủ cho công bằng) */
function test_questions(): array
{
    $bank = quiz_bank();
    $out  = [];
    foreach (test_set() as $i => [$topic, $idx, $skill]) {
        if (!isset($bank[$topic][$idx])) continue;
        $q = $bank[$topic][$idx];
        $out[] = ['i' => $i, 'q' => $q['q'], 'o' => $q['o'], 'skill' => $skill];
    }
    return $out;
}

/* Chấm bài, thống kê theo từng nhóm kỹ năng */
function test_grade(array $answers): array
{
    $bank  = quiz_bank();
    $set   = test_set();
    $score = 0;
    $skills = [];   // ['Đèn tín hiệu' => ['ok'=>2,'total'=>3], ...]
    $wrongTopics = [];

    foreach ($set as $i => [$topic, $idx, $skill]) {
        if (!isset($bank[$topic][$idx])) continue;

        if (!isset($skills[$skill])) $skills[$skill] = ['ok' => 0, 'total' => 0];
        $skills[$skill]['total']++;

        $pick    = $answers[$i] ?? -1;
        $correct = ($pick === $bank[$topic][$idx]['a']);
        if ($correct) {
            $score++;
            $skills[$skill]['ok']++;
        } else {
            $wrongTopics[] = $topic;   // dùng cho phần cá nhân hoá sau này
        }
    }

    return [
        'score'  => $score,
        'total'  => count($set),
        'skills' => $skills,
        'wrong'  => array_values(array_unique($wrongTopics)),
    ];
}

/* =====================================================================
   THƯ VIỆN LUẬT GIAO THÔNG VIỆT NAM
   Căn cứ:
   • Luật Trật tự, an toàn giao thông đường bộ 2024 (Luật số 36/2024/QH15),
     hiệu lực 01/01/2025, được sửa đổi bổ sung bởi Luật số 118/2025/QH15.
   • Nghị định 168/2024/NĐ-CP (hiệu lực 01/01/2025) — mức xử phạt.
   • Nghị định 238/2026/NĐ-CP (hiệu lực 15/08/2026) — sửa đổi NĐ 168.

   Mỗi điều luật gồm:
     id    — mã tra cứu
     icon  — biểu tượng
     title — tên ngắn gọn
     kid   — GIẢI THÍCH CHO BÉ (6-11 tuổi): vì sao có luật này, con phải làm gì
     rule  — NỘI DUNG LUẬT viết lại cho dễ hiểu
     base  — CĂN CỨ pháp lý của quy định
     fine  — MỨC PHẠT (để '' nếu luật không phạt tiền hành vi này)
     fbase — căn cứ của mức phạt
     who   — 'be' (bé cần thuộc) | 'lon' (dành cho ba mẹ, thầy cô)
   ===================================================================== */
function law_library(): array
{
    return [

/* ══════════ NHÓM 1: NGƯỜI ĐI BỘ ══════════ */
'di-bo' => [
  'label' => '🚶 Người đi bộ',
  'note'  => 'Nhóm quan trọng NHẤT với các con — vì hằng ngày con đi bộ đến trường. Luật quy định tại Điều 30 Luật Trật tự, an toàn giao thông đường bộ 2024.',
  'laws'  => [

    ['id'=>'db-01','icon'=>'🚶','who'=>'be',
     'title'=>'Đi bộ phải đi trên vỉa hè',
     'kid'=>'Vỉa hè là "con đường riêng" của con đó! Lòng đường là chỗ của xe, còn vỉa hè là chỗ của người đi bộ. Nếu đoạn đường nhà con không có vỉa hè, con hãy đi **sát mép đường bên phải** theo hướng con đang đi nhé.',
     'rule'=>'Người đi bộ phải đi trên vỉa hè, lề đường hoặc đường dành riêng cho người đi bộ. Nơi không có vỉa hè thì phải đi sát mép đường bên phải theo chiều đi của mình.',
     'base'=>'Điểm a khoản 1 Điều 30 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'150.000 – 250.000 đồng',
     'fbase'=>'Điểm a khoản 1 Điều 10 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'db-02','icon'=>'🚸','who'=>'be',
     'title'=>'Chỉ qua đường ở nơi có vạch kẻ, đèn, cầu vượt hoặc hầm đi bộ',
     'kid'=>'Con không được băng qua đường ở chỗ nào con thích đâu nhé. Phải tìm **vạch kẻ trắng (vạch ngựa vằn)**, **đèn cho người đi bộ**, **cầu vượt** hoặc **hầm đi bộ**. Ở những chỗ đó, người lái xe đã biết trước là sẽ có người sang đường nên họ chủ động nhường con.',
     'rule'=>'Người đi bộ chỉ được qua đường ở nơi có đèn tín hiệu, có vạch kẻ đường hoặc có cầu vượt, hầm dành cho người đi bộ, và phải tuân thủ tín hiệu chỉ dẫn, báo hiệu đường bộ.',
     'base'=>'Điểm b khoản 1 Điều 30 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'150.000 – 250.000 đồng',
     'fbase'=>'Điểm a khoản 1 Điều 10 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'db-03','icon'=>'✋','who'=>'be',
     'title'=>'Không có vạch kẻ thì phải quan sát và GIƠ TAY xin đường',
     'kid'=>'Đây là điều nhiều bạn nhỏ chưa biết! Nếu đoạn đường không có vạch kẻ, không có đèn, con phải **nhìn kỹ hai bên**, chờ thật an toàn, rồi **giơ một tay lên cao** để người lái xe nhìn thấy con. Cánh tay bé xíu của con chính là "tín hiệu" báo cho các bác tài biết: "Cháu sắp qua đường ạ!" 🙋',
     'rule'=>'Trường hợp không có đèn tín hiệu, vạch kẻ đường, cầu vượt, hầm dành cho người đi bộ thì phải quan sát các xe đang đi tới, chỉ qua đường khi bảo đảm an toàn; khi qua đường phải có tín hiệu bằng tay.',
     'base'=>'Điểm b khoản 1 Điều 30 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'150.000 – 250.000 đồng',
     'fbase'=>'Điểm a khoản 1 Điều 10 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'db-04','icon'=>'🚦','who'=>'be',
     'title'=>'Đi bộ cũng phải chấp hành đèn tín hiệu và biển báo',
     'kid'=>'Đèn giao thông không chỉ dành cho ô tô, xe máy đâu con! Nhiều ngã tư có **đèn riêng cho người đi bộ**: hình người màu đỏ là đứng yên, hình người màu xanh là được đi. Nếu có đèn này, con phải nghe theo đèn dành cho người đi bộ chứ không nhìn đèn của xe nhé.',
     'rule'=>'Người đi bộ phải chấp hành hiệu lệnh và chỉ dẫn của đèn tín hiệu, biển báo hiệu, vạch kẻ đường, cũng như hiệu lệnh của người điều khiển giao thông.',
     'base'=>'Điều 11 và Điều 30 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'150.000 – 250.000 đồng',
     'fbase'=>'Điểm b, c khoản 1 Điều 10 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'db-05','icon'=>'🚧','who'=>'be',
     'title'=>'Không trèo qua dải phân cách',
     'kid'=>'Dải phân cách là bức tường hoặc hàng rào ở giữa đường, ngăn hai chiều xe chạy. Có bạn thấy trèo qua thì nhanh hơn — nhưng đó là việc **cực kỳ nguy hiểm**! Xe bên kia đang chạy rất nhanh và họ không hề ngờ rằng có người bất ngờ nhảy ra. Con hãy chịu khó đi thêm vài chục mét đến chỗ có vạch kẻ nhé.',
     'rule'=>'Người đi bộ không được vượt qua dải phân cách.',
     'base'=>'Điểm c khoản 1 Điều 30 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'150.000 – 250.000 đồng',
     'fbase'=>'Điểm a khoản 1 Điều 10 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'db-06','icon'=>'⛔','who'=>'be',
     'title'=>'Không đu, bám vào xe đang chạy',
     'kid'=>'Tuyệt đối không được bám vào xe tải, xe buýt, xe ba gác… đang chạy để "đi nhờ" cho vui đâu con. Chỉ cần xe phanh gấp hoặc con tuột tay là ngã ngay xuống đường, xe phía sau không kịp tránh. Đây là trò chơi **có thể mất mạng**, không bao giờ được thử dù chỉ một lần.',
     'rule'=>'Người đi bộ không được đu, bám vào phương tiện giao thông đường bộ đang di chuyển.',
     'base'=>'Điểm c khoản 1 Điều 30 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'400.000 – 600.000 đồng',
     'fbase'=>'Điểm c khoản 2 Điều 10 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'db-07','icon'=>'🛣️','who'=>'be',
     'title'=>'Không đi bộ vào đường cao tốc',
     'kid'=>'Đường cao tốc là nơi xe chạy rất nhanh, có khi tới 120 km/h — nhanh gấp hơn 20 lần con chạy bộ! Người đi bộ **tuyệt đối không được vào đó**, kể cả đi trên làn khẩn cấp sát mép. Ở tốc độ đó, bác tài nhìn thấy con thì cũng đã quá muộn để phanh rồi.',
     'rule'=>'Người đi bộ không được đi vào đường cao tốc, trừ người phục vụ việc quản lý, bảo trì đường cao tốc.',
     'base'=>'Điều 25 và Điều 30 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'400.000 – 600.000 đồng',
     'fbase'=>'Điểm a khoản 2 Điều 10 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'db-08','icon'=>'📦','who'=>'be',
     'title'=>'Mang vác đồ cồng kềnh phải bảo đảm an toàn',
     'kid'=>'Khi con mang một vật to (tấm bìa lớn, cây quạt, bó cây…), vật đó có thể **che mất tầm nhìn** của con và **va vào người khác**. Con hãy nhờ người lớn giúp, hoặc mang thành nhiều lần cho gọn nhé.',
     'rule'=>'Khi mang, vác vật cồng kềnh, người đi bộ phải bảo đảm an toàn và không gây trở ngại cho người và phương tiện tham gia giao thông đường bộ.',
     'base'=>'Điểm c khoản 1 Điều 30 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'400.000 – 600.000 đồng',
     'fbase'=>'Điểm b khoản 2 Điều 10 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'db-09','icon'=>'🤝','who'=>'be',
     'title'=>'Trẻ em dưới 7 tuổi qua đường phải có người lớn dắt',
     'kid'=>'Nếu con có em nhỏ dưới 7 tuổi, khi qua đường **em phải được người lớn dắt tay**. Con cũng có thể giúp đỡ bằng cách nắm tay em thật chặt và nhắc em đi đúng vạch kẻ. Người lớn đi đường thấy trẻ nhỏ qua đường một mình thì có trách nhiệm giúp đỡ đó con.',
     'rule'=>'Trẻ em dưới 7 tuổi khi đi qua đường phải có người lớn dắt. Mọi người có trách nhiệm giúp đỡ trẻ em dưới 7 tuổi khi đi qua đường.',
     'base'=>'Khoản 2 Điều 30 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>''],
  ],
],

/* ══════════ NHÓM 2: XE ĐẠP – XE ĐẠP ĐIỆN ══════════ */
'xe-dap' => [
  'label' => '🚲 Xe đạp & xe đạp điện',
  'note'  => 'Xe đạp cũng là phương tiện tham gia giao thông, cũng có luật riêng. Quy định tại Điều 31 Luật Trật tự, an toàn giao thông đường bộ 2024 và Điều 9 Nghị định 168/2024/NĐ-CP.',
  'laws'  => [

    ['id'=>'xd-01','icon'=>'➡️','who'=>'be',
     'title'=>'Đi bên phải, đúng phần đường của mình',
     'kid'=>'Xe đạp phải đi **sát bên phải** theo chiều đi của mình. Nhiều bạn thích đi ra giữa đường cho thoáng — nhưng như vậy ô tô, xe máy phía sau rất khó vượt qua và dễ va vào con.',
     'rule'=>'Người điều khiển xe đạp, xe đạp máy phải đi bên phải theo chiều đi của mình, đi đúng phần đường, làn đường quy định.',
     'base'=>'Điều 31 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'100.000 – 200.000 đồng',
     'fbase'=>'Khoản 1 Điều 9 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xd-02','icon'=>'👬','who'=>'be',
     'title'=>'Không đi dàn hàng ngang từ 3 xe trở lên',
     'kid'=>'Đi học cùng bạn thì vui lắm, nhưng ba bạn đi song song nhau sẽ chiếm gần hết làn đường. Con hãy rủ bạn **đi thành hàng một**, vừa an toàn vừa vẫn nói chuyện được mà!',
     'rule'=>'Người điều khiển xe đạp, xe đạp máy không được đi dàn hàng ngang từ 03 xe trở lên.',
     'base'=>'Điều 31 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'100.000 – 200.000 đồng',
     'fbase'=>'Khoản 1 Điều 9 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xd-03','icon'=>'📱','who'=>'be',
     'title'=>'Không cầm điện thoại khi đang đạp xe',
     'kid'=>'Chỉ cần con nhìn xuống điện thoại **2 giây thôi**, chiếc xe đạp đã đi được khoảng 5-6 mét trong tình trạng "nhắm mắt". Trong 5 mét đó có thể xuất hiện một chiếc xe, một người đi bộ, một cái ổ gà. Nếu cần dùng điện thoại, con hãy **dừng hẳn xe vào lề** rồi mới lấy ra nhé.',
     'rule'=>'Người điều khiển xe đạp, xe đạp máy không được dùng tay cầm và sử dụng điện thoại hoặc các thiết bị điện tử khác khi đang điều khiển xe.',
     'base'=>'Điều 31 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'100.000 – 200.000 đồng',
     'fbase'=>'Khoản 1 Điều 9 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xd-04','icon'=>'🙌','who'=>'be',
     'title'=>'Không buông cả hai tay, không đánh võng, không đuổi nhau',
     'kid'=>'Buông hai tay để "khoe tài" là trò rất nguy hiểm con nhé. Chỉ cần bánh xe gặp một viên sỏi nhỏ là con mất thăng bằng ngay, mà lúc đó tay con không kịp nắm lại ghi-đông. Đuổi nhau trên đường cũng vậy — mải nhìn bạn thì không còn nhìn đường nữa.',
     'rule'=>'Người điều khiển xe đạp, xe đạp máy không được buông cả hai tay; không chuyển hướng đột ngột trước đầu xe cơ giới đang chạy; không lạng lách, đánh võng, đuổi nhau trên đường; không đi xe bằng một bánh.',
     'base'=>'Điều 31 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'150.000 – 250.000 đồng (buông hai tay, chuyển hướng đột ngột) · 300.000 – 400.000 đồng (lạng lách, đánh võng, đuổi nhau, đi một bánh)',
     'fbase'=>'Khoản 2 và khoản 3 Điều 9 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xd-05','icon'=>'⛑️','who'=>'be',
     'title'=>'Đi xe đạp điện BẮT BUỘC đội mũ bảo hiểm',
     'kid'=>'Rất nhiều bạn nghĩ "xe đạp điện chạy chậm, không cần mũ" — điều đó **SAI** con nhé! Xe đạp điện chạy tới 25 km/h, ngã ở tốc độ đó vẫn đủ gây chấn thương đầu rất nặng. Luật quy định đi xe đạp điện (xe đạp máy) **phải đội mũ bảo hiểm và cài quai đúng cách**, giống hệt như đi xe máy.',
     'rule'=>'Người điều khiển và người được chở trên xe đạp máy (bao gồm xe đạp điện) phải đội mũ bảo hiểm dành cho người đi mô tô, xe máy và cài quai đúng quy cách.',
     'base'=>'Điều 31 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'400.000 – 600.000 đồng',
     'fbase'=>'Điểm d, đ khoản 4 Điều 9 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xd-06','icon'=>'👤','who'=>'be',
     'title'=>'Xe đạp chỉ được chở 1 người',
     'kid'=>'Xe đạp của con chỉ được chở **một người** phía sau thôi. Riêng trường hợp chở thêm **một em bé dưới 7 tuổi** thì được chở tối đa hai người. Chở ba, chở bốn nhìn thì vui nhưng xe sẽ rất nặng, phanh không ăn và dễ đổ.',
     'rule'=>'Người điều khiển xe đạp, xe đạp máy chỉ được chở một người; trường hợp chở thêm một trẻ em dưới 07 tuổi thì được chở tối đa hai người.',
     'base'=>'Khoản 1 Điều 31 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'100.000 – 200.000 đồng (người đi xe đạp, xe đạp máy chở quá số người quy định)',
     'fbase'=>'Điều 9 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xd-07','icon'=>'☂️','who'=>'be',
     'title'=>'Người ngồi sau xe đạp không được che ô (dù)',
     'kid'=>'Trời mưa hay nắng, con ngồi sau mà giương ô lên thì gió sẽ đẩy chiếc ô như một cánh buồm, làm cả xe nghiêng ngả. Ô cũng che mất tầm nhìn của xe phía sau. Con hãy **mặc áo mưa hoặc đội mũ** thay vì che ô nhé.',
     'rule'=>'Người ngồi trên xe đạp, xe đạp máy không được sử dụng ô (dù).',
     'base'=>'Điều 31 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'100.000 – 200.000 đồng',
     'fbase'=>'Khoản 1 Điều 9 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xd-08','icon'=>'🚫','who'=>'be',
     'title'=>'Không đi vào đường cấm, không đi ngược chiều',
     'kid'=>'Khi con thấy biển tròn viền đỏ có hình xe đạp bị gạch chéo (biển P.110a) nghĩa là **xe đạp không được vào đoạn đường đó**. Đi ngược chiều còn nguy hiểm hơn nữa, vì con và chiếc xe đối diện lao thẳng vào nhau.',
     'rule'=>'Người điều khiển xe đạp, xe đạp máy không được đi vào khu vực cấm, đường có biển báo cấm đối với loại phương tiện đang điều khiển; không đi ngược chiều của đường một chiều hoặc đường có biển "Cấm đi ngược chiều".',
     'base'=>'Điều 31 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'300.000 – 400.000 đồng',
     'fbase'=>'Khoản 3 Điều 9 Nghị định 168/2024/NĐ-CP'],
  ],
],

/* ══════════ NHÓM 3: MŨ BẢO HIỂM & NGỒI SAU XE MÁY ══════════ */
'mu-xe-may' => [
  'label' => '⛑️ Mũ bảo hiểm & ngồi sau xe máy',
  'note'  => 'Ba mẹ chở con đi học mỗi ngày — đây là những điều luật con và ba mẹ cùng phải nhớ. Quy định tại Điều 33 Luật Trật tự, an toàn giao thông đường bộ 2024 và Điều 7 Nghị định 168/2024/NĐ-CP.',
  'laws'  => [

    ['id'=>'mu-01','icon'=>'⛑️','who'=>'be',
     'title'=>'Trẻ em từ đủ 6 tuổi trở lên ngồi xe máy PHẢI đội mũ bảo hiểm',
     'kid'=>'Từ khi con **đủ 6 tuổi**, mỗi lần ngồi sau xe máy con đều phải đội mũ bảo hiểm và cài quai. Em bé dưới 6 tuổi thì luật chưa bắt buộc, nhưng ba mẹ vẫn nên cho em đội mũ vừa đầu để bảo vệ em. Mũ bảo hiểm giúp giảm tới khoảng **70% nguy cơ chấn thương đầu** khi có va chạm đó con.',
     'rule'=>'Người lái xe và người được chở trên xe mô tô, xe gắn máy phải đội mũ bảo hiểm dành cho người đi mô tô, xe máy và cài quai đúng quy cách. Việc xử phạt không áp dụng với trường hợp chở người bệnh đi cấp cứu, trẻ em dưới 06 tuổi, áp giải người có hành vi vi phạm pháp luật.',
     'base'=>'Điều 33 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'400.000 – 600.000 đồng (phạt riêng từng người vi phạm)',
     'fbase'=>'Điểm h, i khoản 2 Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mu-02','icon'=>'🔒','who'=>'be',
     'title'=>'Đội mũ mà KHÔNG cài quai vẫn bị coi là vi phạm',
     'kid'=>'Đây là điều rất nhiều người nhầm! Đội mũ lên đầu nhưng để quai lủng lẳng thì khi ngã, chiếc mũ sẽ **văng ra trước cả khi đầu con chạm đất** — coi như con chưa đội mũ vậy. Luật ghi rõ là phải "cài quai đúng quy cách". Con thử luồn 2 ngón tay giữa quai và cằm: vừa khít là chuẩn.',
     'rule'=>'Hành vi đội mũ bảo hiểm nhưng không cài quai đúng quy cách bị xử phạt như hành vi không đội mũ bảo hiểm.',
     'base'=>'Điều 33 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'400.000 – 600.000 đồng',
     'fbase'=>'Điểm h khoản 2 Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mu-03','icon'=>'👨‍👩‍👧','who'=>'lon',
     'title'=>'Xe máy chỉ được chở 1 người — trừ vài trường hợp đặc biệt',
     'kid'=>'Xe máy bình thường chỉ chở được một người phía sau. Nhưng nếu người được chở là **trẻ em dưới 12 tuổi** (như con), hoặc người bệnh đi cấp cứu, người già yếu, người khuyết tật, thì được chở **hai người**. Chở từ ba người trở lên thì luôn là vi phạm.',
     'rule'=>'Người lái xe mô tô, xe gắn máy chỉ được chở một người, trừ trường hợp chở người bệnh đi cấp cứu, trẻ em dưới 12 tuổi, người già yếu hoặc người khuyết tật, áp giải người có hành vi vi phạm pháp luật thì được chở tối đa hai người.',
     'base'=>'Điều 33 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'400.000 – 600.000 đồng (chở 2 người sai quy định) · 600.000 – 800.000 đồng (chở từ 3 người trở lên)',
     'fbase'=>'Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mu-04','icon'=>'🏷️','who'=>'be',
     'title'=>'Mũ phải là mũ bảo hiểm thật, có tem hợp quy CR',
     'kid'=>'Mũ lưỡi trai, mũ vải, mũ nhựa mỏng của đồ chơi **không phải là mũ bảo hiểm**. Mũ thật có lớp xốp dày bên trong, có **tem hợp quy CR** dán bên ngoài, và phải **vừa đầu con** — mũ quá rộng sẽ xoay lệch khi ngã, mũ quá chật thì con khó chịu không muốn đội.',
     'rule'=>'Mũ bảo hiểm phải là loại dành cho người đi mô tô, xe máy, đạt quy chuẩn kỹ thuật quốc gia (có tem hợp quy CR).',
     'base'=>'Điều 33 Luật Trật tự, an toàn giao thông đường bộ 2024; QCVN 2:2021/BKHCN',
     'fine'=>'Không có mức phạt riêng cho mũ kém chất lượng nếu vẫn đội và cài quai đúng cách — nhưng mũ giả thì không bảo vệ được con.',
     'fbase'=>''],

    ['id'=>'mu-05','icon'=>'🦶','who'=>'be',
     'title'=>'Ngồi sau xe máy phải ngồi đúng tư thế, không đứng lên, không nghịch',
     'kid'=>'Con ngồi sau thì phải **ngồi hẳn trên yên, hai chân đặt lên gác chân, hai tay ôm hoặc bám chắc**. Không đứng lên, không quay ngang, không thò chân vào bánh xe, không giơ tay chỉ trỏ. Con nghịch một chút là ba mẹ mất thăng bằng ngay.',
     'rule'=>'Người được chở trên xe mô tô, xe gắn máy phải ngồi đúng tư thế, không được đứng trên yên, giá đèo hàng hoặc ngồi trên tay lái; không bám, kéo, đẩy phương tiện khác.',
     'base'=>'Điều 33 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'600.000 – 800.000 đồng (khi để người được chở đứng trên yên, giá đèo hàng hoặc bám, kéo, đẩy xe khác)',
     'fbase'=>'Điều 7 Nghị định 168/2024/NĐ-CP'],
  ],
],

/* ══════════ NHÓM 4: ĐI Ô TÔ ══════════ */
'o-to' => [
  'label' => '🚗 Đi ô tô an toàn',
  'note'  => 'Từ năm 2025 - 2026, luật Việt Nam có nhiều quy định MỚI để bảo vệ trẻ em trên ô tô. Đây là phần ba mẹ rất nên đọc.',
  'laws'  => [

    ['id'=>'ot-01','icon'=>'🔗','who'=>'be',
     'title'=>'Thắt dây an toàn ở MỌI vị trí có trang bị dây',
     'kid'=>'Rất nhiều người nghĩ chỉ người lái mới cần thắt dây — **sai** rồi! Luật quy định **ai ngồi ở chỗ nào có dây an toàn thì phải thắt**, kể cả ghế sau. Khi xe phanh gấp, người không thắt dây sẽ bị lao về phía trước với lực rất mạnh, va vào ghế trước hoặc kính chắn gió.',
     'rule'=>'Người lái xe và người được chở trên xe ô tô phải thắt dây đai an toàn tại những chỗ có trang bị dây đai an toàn khi tham gia giao thông đường bộ.',
     'base'=>'Khoản 3 Điều 10 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'800.000 – 1.000.000 đồng (người lái không thắt hoặc chở người không thắt) · 350.000 – 400.000 đồng (người được chở không thắt)',
     'fbase'=>'Điều 6 và Điều 11 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'ot-02','icon'=>'🪑','who'=>'lon',
     'title'=>'Trẻ dưới 10 tuổi và cao dưới 1,35 m không ngồi cùng hàng ghế với người lái',
     'kid'=>'Ghế trước có **túi khí**. Túi khí bung ra rất nhanh và rất mạnh — với người lớn thì đó là lá chắn cứu mạng, nhưng với một bạn nhỏ thì lực đó lại có thể gây thương tích. Vì vậy con hãy ngồi **hàng ghế phía sau** cho an toàn nhé (trừ khi xe chỉ có đúng một hàng ghế).',
     'rule'=>'Người lái xe ô tô không được chở trẻ em dưới 10 tuổi và chiều cao dưới 1,35 mét ngồi cùng hàng ghế với người lái xe, trừ loại xe ô tô chỉ có một hàng ghế. Quy định này có hiệu lực từ 01/01/2026.',
     'base'=>'Khoản 3 Điều 10 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'800.000 – 1.000.000 đồng',
     'fbase'=>'Điểm m khoản 3 Điều 6 Nghị định 168/2024/NĐ-CP (hiệu lực thi hành từ 01/01/2026)'],

    ['id'=>'ot-03','icon'=>'👶','who'=>'lon',
     'title'=>'Trẻ dưới 10 tuổi và cao dưới 1,35 m phải dùng thiết bị an toàn phù hợp',
     'kid'=>'"Thiết bị an toàn" là **ghế trẻ em chuyên dụng** hoặc **đệm nâng** gắn trên ô tô. Dây an toàn của xe được thiết kế cho người lớn, quàng qua người bạn nhỏ sẽ nằm ngang cổ chứ không nằm ở vai — rất nguy hiểm khi phanh gấp. Ghế riêng giúp dây nằm đúng chỗ trên người con.',
     'rule'=>'Khi chở trẻ em dưới 10 tuổi và chiều cao dưới 1,35 mét trên xe ô tô, người lái xe phải sử dụng, hướng dẫn sử dụng thiết bị an toàn phù hợp cho trẻ em.',
     'base'=>'Khoản 3 Điều 10 Luật Trật tự, an toàn giao thông đường bộ 2024 (hiệu lực từ 01/01/2026)',
     'fine'=>'⚠️ QUY ĐỊNH ĐANG THAY ĐỔI: đến hết 14/8/2026 áp dụng mức phạt 800.000 – 1.000.000 đồng theo Nghị định 168/2024/NĐ-CP. Từ 15/8/2026, Nghị định 238/2026/NĐ-CP bỏ mức phạt tiền này và chuyển thành hình thức PHẠT CẢNH CÁO.',
     'fbase'=>'Điểm m khoản 3 Điều 6 Nghị định 168/2024/NĐ-CP, được sửa đổi bởi Nghị định 238/2026/NĐ-CP (hiệu lực 15/8/2026)'],

    ['id'=>'ot-04','icon'=>'🚪','who'=>'be',
     'title'=>'Lên xuống ô tô ở phía lề đường, mở cửa phải quan sát',
     'kid'=>'Khi xuống xe, con hãy xuống ở **phía sát lề đường** (bên phải), không xuống phía lòng đường. Trước khi mở cửa, hãy **ngoái nhìn ra sau** xem có xe máy, xe đạp đang tới không. Một cánh cửa mở bất ngờ có thể hất ngã người đi xe máy phía sau — người ta gọi đây là tai nạn "cửa mở".',
     'rule'=>'Người lái xe và người được chở không được mở cửa xe, để cửa xe mở khi không bảo đảm an toàn.',
     'base'=>'Điều 10 và Điều 20 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'400.000 – 600.000 đồng (mở cửa xe không bảo đảm an toàn)',
     'fbase'=>'Điều 6 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'ot-05','icon'=>'🙅','who'=>'be',
     'title'=>'Không thò đầu, thò tay ra ngoài cửa sổ ô tô đang chạy',
     'kid'=>'Nhìn cảnh vật bay qua cửa sổ thì thích thật, nhưng con **đừng bao giờ thò đầu hay tay ra ngoài** khi xe đang chạy. Xe ngược chiều đi rất sát, chỉ cần một chiếc gương chiếu hậu quệt qua là con bị thương ngay.',
     'rule'=>'Người được chở trên xe ô tô không được thò đầu, tay hoặc các bộ phận khác của cơ thể ra ngoài xe khi xe đang chạy.',
     'base'=>'Điều 10 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>''],
  ],
],

/* ══════════ NHÓM 5: ĐÈN, BIỂN BÁO, CẢNH SÁT ══════════ */
'den-bien' => [
  'label' => '🚦 Đèn, biển báo & cảnh sát',
  'note'  => 'Ai cũng phải chấp hành báo hiệu đường bộ. Luật còn quy định rõ thứ tự ưu tiên khi các loại báo hiệu "nói khác nhau". Quy định tại Điều 11 Luật Trật tự, an toàn giao thông đường bộ 2024.',
  'laws'  => [

    ['id'=>'db-tt','icon'=>'🥇','who'=>'be',
     'title'=>'Thứ tự ưu tiên: Cảnh sát → Đèn → Biển báo → Vạch kẻ',
     'kid'=>'Đây là câu hỏi thi rất hay gặp đó con! Khi các báo hiệu "không giống nhau", con phải nghe theo thứ tự này:

1️⃣ **Hiệu lệnh của người điều khiển giao thông** (chú cảnh sát) — cao nhất
2️⃣ **Đèn tín hiệu**
3️⃣ **Biển báo hiệu**
4️⃣ **Vạch kẻ đường và các báo hiệu khác**

Ví dụ: đèn đang xanh nhưng chú cảnh sát giơ tay ra hiệu dừng → con phải **dừng lại**, vì chú cảnh sát ưu tiên hơn đèn.',
     'rule'=>'Khi đồng thời bố trí các hình thức báo hiệu khác nhau ở cùng một khu vực, người tham gia giao thông phải chấp hành theo thứ tự: hiệu lệnh của người điều khiển giao thông; tín hiệu đèn giao thông; biển báo hiệu; vạch kẻ đường và các dấu hiệu khác trên mặt đường; cọc tiêu, tường bảo vệ, rào chắn, đinh phản quang, cột Km, cọc H; thiết bị âm thanh báo hiệu.',
     'base'=>'Khoản 2 Điều 11 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'db-den','icon'=>'🚦','who'=>'be',
     'title'=>'Ý nghĩa 3 màu đèn theo LUẬT',
     'kid'=>'Luật ghi rất rõ:

🟢 **Xanh** — được đi.
🟡 **Vàng** — phải dừng lại trước vạch dừng. Nếu xe đã đi quá vạch dừng hoặc quá gần vạch mà dừng lại sẽ nguy hiểm thì được đi tiếp.
🔴 **Đỏ** — cấm đi.

Vậy nên con nhớ nhé: **đèn vàng KHÔNG phải là "đi nhanh lên"** như nhiều người vẫn nghĩ, mà là "chuẩn bị dừng".',
     'rule'=>'Tín hiệu đèn màu xanh là được đi; tín hiệu đèn màu vàng phải dừng lại trước vạch dừng, trường hợp đang đi trên vạch dừng hoặc đã đi qua vạch dừng mà tín hiệu đèn màu vàng thì được đi tiếp; tín hiệu đèn màu vàng nhấp nháy là được đi nhưng phải quan sát, giảm tốc độ hoặc dừng lại nhường đường; tín hiệu đèn màu đỏ là cấm đi.',
     'base'=>'Khoản 4 Điều 11 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy vượt đèn đỏ: 4.000.000 – 6.000.000 đồng và trừ 4 điểm giấy phép lái xe · Ô tô vượt đèn đỏ: 18.000.000 – 20.000.000 đồng và trừ 4 điểm giấy phép lái xe',
     'fbase'=>'Điểm c khoản 7 Điều 7 và khoản 9 Điều 6 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'db-bien','icon'=>'🚸','who'=>'be',
     'title'=>'4 nhóm biển báo con cần nhận ra',
     'kid'=>'Chỉ cần nhìn **hình dạng và màu sắc** là con đoán được ý nghĩa:

🔴 **Tròn, viền đỏ, nền trắng** = biển CẤM — không được làm điều đó
🔺 **Tam giác, viền đỏ, nền vàng** = biển NGUY HIỂM — phía trước có nguy hiểm, đi chậm lại
🔵 **Tròn, nền xanh dương** = biển HIỆU LỆNH — bắt buộc phải làm theo
🟦 **Vuông hoặc chữ nhật, nền xanh** = biển CHỈ DẪN — cho biết thông tin hữu ích

Con mở mục **🚸 Thư viện biển báo** trong app để xem hình từng biển nhé!',
     'rule'=>'Biển báo hiệu đường bộ gồm các nhóm: biển báo cấm, biển báo nguy hiểm và cảnh báo, biển hiệu lệnh, biển chỉ dẫn và biển phụ, biển viết bằng chữ.',
     'base'=>'Điều 11 Luật Trật tự, an toàn giao thông đường bộ 2024; QCVN 41:2024/BGTVT',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'db-uut','icon'=>'🚑','who'=>'be',
     'title'=>'Phải nhường đường cho xe ưu tiên',
     'kid'=>'Khi con nghe tiếng còi hú và thấy đèn nhấp nháy, đó là **xe ưu tiên** đang đi làm nhiệm vụ khẩn cấp — trên xe có thể có người đang cần cấp cứu. Mọi xe khác phải **nhanh chóng nhường đường**, tấp vào lề. Nếu con đang định qua đường, hãy **đứng yên lại** để xe đi qua trước nhé.',
     'rule'=>'Xe ưu tiên gồm: xe chữa cháy, xe quân sự, xe công an đi làm nhiệm vụ khẩn cấp, xe cứu thương, xe hộ đê, xe đi làm nhiệm vụ cứu nạn, xe của đoàn tang lễ (theo thứ tự ưu tiên). Khi có tín hiệu của xe ưu tiên, người tham gia giao thông phải giảm tốc độ, đi sát lề đường bên phải hoặc dừng lại nhường đường, không được gây cản trở.',
     'base'=>'Điều 27 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 4.000.000 – 6.000.000 đồng · Ô tô: 6.000.000 – 8.000.000 đồng',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],
  ],
],

/* ══════════ NHÓM 6: BAO NHIÊU TUỔI ĐƯỢC LÁI XE ══════════ */
'do-tuoi' => [
  'label' => '🎂 Bao nhiêu tuổi được lái xe?',
  'note'  => 'Câu hỏi các bạn nhỏ hay thắc mắc nhất! Quy định tại Điều 59 Luật Trật tự, an toàn giao thông đường bộ 2024 và Điều 18 Nghị định 168/2024/NĐ-CP.',
  'laws'  => [

    ['id'=>'tu-01','icon'=>'🛵','who'=>'be',
     'title'=>'Đủ 16 tuổi mới được lái xe gắn máy (dưới 50 cm³)',
     'kid'=>'Con phải **đủ 16 tuổi** mới được lái xe gắn máy loại nhỏ (dung tích dưới 50 cm³ hoặc động cơ điện công suất dưới 4 kW). Còn bây giờ, phương tiện của con là **đôi chân và chiếc xe đạp** thôi nhé. Chờ thêm vài năm nữa, khi tay chân con đủ dài và phản xạ đủ nhanh, con sẽ lái xe an toàn hơn nhiều.',
     'rule'=>'Người đủ 16 tuổi trở lên được điều khiển xe gắn máy (xe có dung tích xi-lanh dưới 50 cm³ hoặc công suất động cơ điện dưới 04 kW).',
     'base'=>'Khoản 1 Điều 59 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Từ đủ 14 đến dưới 16 tuổi điều khiển xe mô tô, xe gắn máy: phạt cảnh cáo',
     'fbase'=>'Khoản 1 Điều 18 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'tu-02','icon'=>'🏍️','who'=>'be',
     'title'=>'Đủ 18 tuổi mới được lái xe mô tô và ô tô, phải có bằng lái',
     'kid'=>'Xe mô tô (từ 50 cm³ trở lên) và ô tô thì phải **đủ 18 tuổi** và **có giấy phép lái xe** mới được điều khiển. Muốn có bằng lái, người ta phải đi học và thi hẳn hoi — vì lái xe là việc đòi hỏi rất nhiều kỹ năng.',
     'rule'=>'Người đủ 18 tuổi trở lên được cấp giấy phép lái xe hạng A1, A, B1, B và được điều khiển xe mô tô, xe ô tô tương ứng với hạng giấy phép được cấp.',
     'base'=>'Khoản 1 Điều 59 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Từ đủ 16 đến dưới 18 tuổi điều khiển xe mô tô từ 50 cm³ trở lên: 400.000 – 600.000 đồng · Người lớn giao xe cho người chưa đủ tuổi: cá nhân đến 10.000.000 đồng, tổ chức đến 20.000.000 đồng',
     'fbase'=>'Điều 18 và Điều 32 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'tu-03','icon'=>'⚠️','who'=>'be',
     'title'=>'Chưa đủ tuổi mà lái xe máy thì bị xử lý thế nào?',
     'kid'=>'Có bạn nghĩ "mình còn nhỏ nên không bị phạt" — không đúng đâu con. Luật quy định rất rõ:

• Từ **đủ 14 đến dưới 16 tuổi** lái xe mô tô, xe gắn máy → **phạt cảnh cáo**
• Từ **đủ 16 đến dưới 18 tuổi** lái xe mô tô từ 50 cm³ trở lên → **phạt tiền 400.000 – 600.000 đồng**

Ngoài ra, công an còn gửi thông báo về **trường học** của bạn đó nữa.',
     'rule'=>'Người từ đủ 14 tuổi đến dưới 16 tuổi điều khiển xe mô tô, xe gắn máy bị phạt cảnh cáo. Người từ đủ 16 tuổi đến dưới 18 tuổi điều khiển xe mô tô có dung tích xi-lanh từ 50 cm³ trở lên hoặc công suất động cơ điện từ 04 kW trở lên bị phạt tiền.',
     'base'=>'Điều 59 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Phạt cảnh cáo (14 – dưới 16 tuổi) · 400.000 – 600.000 đồng (16 – dưới 18 tuổi)',
     'fbase'=>'Khoản 1 và điểm a khoản 4 Điều 18 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'tu-04','icon'=>'🔑','who'=>'lon',
     'title'=>'Người lớn giao xe cho trẻ chưa đủ tuổi bị phạt rất nặng',
     'kid'=>'Nếu con xin ba mẹ cho lái thử xe máy, ba mẹ từ chối là vì thương con đó! Người lớn giao xe cho người chưa đủ điều kiện điều khiển sẽ bị phạt tới **10 triệu đồng**. Nếu xảy ra tai nạn nghiêm trọng, người giao xe còn có thể bị truy cứu trách nhiệm hình sự.',
     'rule'=>'Chủ xe giao xe hoặc để cho người không đủ điều kiện điều khiển xe tham gia giao thông (bao gồm người chưa đủ tuổi, người không có giấy phép lái xe) là hành vi bị xử phạt.',
     'base'=>'Khoản 1 Điều 56 Luật Trật tự, an toàn giao thông đường bộ 2024; Điều 264 Bộ luật Hình sự 2015 (sửa đổi 2017)',
     'fine'=>'Xe mô tô, xe gắn máy: 8.000.000 – 10.000.000 đồng (cá nhân), 16.000.000 – 20.000.000 đồng (tổ chức) · Ô tô: 28.000.000 – 30.000.000 đồng (cá nhân), 56.000.000 – 60.000.000 đồng (tổ chức)',
     'fbase'=>'Khoản 10 và khoản 14 Điều 32 Nghị định 168/2024/NĐ-CP'],
  ],
],

/* ══════════ NHÓM 7: HÀNH VI BỊ NGHIÊM CẤM ══════════ */
'nghiem-cam' => [
  'label' => '⛔ Điều bị NGHIÊM CẤM',
  'note'  => 'Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024 liệt kê những hành vi bị nghiêm cấm. Đây là những điều con tuyệt đối không được làm, dù chỉ để đùa vui.',
  'laws'  => [

    ['id'=>'nc-01','icon'=>'⚽','who'=>'be',
     'title'=>'Cấm chơi đùa, đá bóng, thả diều dưới lòng đường',
     'kid'=>'Lòng đường không phải sân chơi con nhé. Khi con mải chạy theo quả bóng, con **không còn nhìn thấy xe** nữa — mà xe thì không thể phanh gấp trong tích tắc. Quả bóng lăn ra đường thì hãy **nhờ người lớn** lấy giúp, hoặc chờ đường vắng hẳn. Bóng mất còn mua được, con thì không.',
     'rule'=>'Nghiêm cấm hành vi họp chợ, mua bán hàng hóa, chơi đùa, thả diều, đá bóng và các hoạt động khác trên lòng đường, hè phố gây cản trở, mất an toàn giao thông đường bộ.',
     'base'=>'Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Người đi bộ vi phạm quy tắc giao thông (chơi đùa, tụ tập dưới lòng đường gây cản trở): 150.000 – 250.000 đồng',
     'fbase'=>'Điều 10 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'nc-02','icon'=>'🪨','who'=>'be',
     'title'=>'Cấm ném đất, đá, vật gì đó vào xe đang chạy',
     'kid'=>'Có bạn nghĩ ném hòn sỏi vào ô tô đang chạy là trò đùa. Nhưng ở tốc độ cao, hòn sỏi có thể **làm vỡ kính**, khiến bác tài giật mình đánh lái và gây tai nạn cho cả một xe đầy người. Đây là hành vi bị pháp luật nghiêm cấm và người ném có thể phải chịu trách nhiệm rất nặng.',
     'rule'=>'Nghiêm cấm hành vi ném gạch, đất, đá, cát hoặc vật thể khác vào người, phương tiện đang tham gia giao thông đường bộ.',
     'base'=>'Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Hành vi bị nghiêm cấm: bị xử phạt hành chính; nếu làm hư hỏng phương tiện hoặc gây thương tích, người vi phạm còn có thể bị truy cứu trách nhiệm hình sự',
     'fbase'=>'Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024 và Bộ luật Hình sự'],

    ['id'=>'nc-03','icon'=>'🍺','who'=>'lon',
     'title'=>'Cấm tuyệt đối lái xe khi trong máu, hơi thở có nồng độ cồn',
     'kid'=>'Việt Nam áp dụng quy tắc **"đã uống rượu bia thì không lái xe"** — nồng độ cồn phải bằng 0. Rượu bia làm mắt nhìn chậm, tay chân phản ứng chậm. Nếu con thấy người lớn vừa uống bia mà định lái xe, con hãy nhẹ nhàng nói: "Ba/chú ơi, mình gọi xe về nhé!" — con đang cứu người đó.',
     'rule'=>'Nghiêm cấm điều khiển phương tiện tham gia giao thông đường bộ mà trong máu hoặc hơi thở có nồng độ cồn.',
     'base'=>'Khoản 2 Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 2.000.000 – 10.000.000 đồng · Ô tô: 6.000.000 – 40.000.000 đồng, kèm trừ điểm hoặc tước giấy phép lái xe · Xe đạp: 100.000 – 600.000 đồng',
     'fbase'=>'Điều 6, Điều 7 và Điều 9 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'nc-04','icon'=>'🏁','who'=>'lon',
     'title'=>'Cấm đua xe, lạng lách, đánh võng, bốc đầu',
     'kid'=>'Đôi khi con thấy trên mạng có người "bốc đầu" xe máy trông rất "ngầu". Nhưng đó là hành vi **bị pháp luật nghiêm cấm**, và ngoài đời thật những người đó thường kết thúc ở bệnh viện. "Ngầu" thật sự là biết bảo vệ mình và người khác con ạ.',
     'rule'=>'Nghiêm cấm hành vi lạng lách, đánh võng, đua xe trái phép, tổ chức đua xe trái phép, cổ vũ đua xe trái phép.',
     'base'=>'Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy lạng lách, đánh võng: 8.000.000 – 10.000.000 đồng · Đua xe trái phép có thể bị truy cứu trách nhiệm hình sự',
     'fbase'=>'Điều 7 Nghị định 168/2024/NĐ-CP; Điều 266 Bộ luật Hình sự'],

    ['id'=>'nc-05','icon'=>'🔊','who'=>'lon',
     'title'=>'Cấm bấm còi, nẹt pô liên tục trong khu dân cư, gần bệnh viện',
     'kid'=>'Tiếng còi inh ỏi và tiếng nẹt pô làm người khác **giật mình** — mà giật mình khi đang lái xe rất dễ gây tai nạn. Gần bệnh viện còn có người bệnh cần yên tĩnh nữa. Còi chỉ nên dùng để báo hiệu khi thật cần thiết thôi.',
     'rule'=>'Nghiêm cấm bấm còi, rú ga (nẹt pô) liên tục trong khu đông dân cư, khu vực cơ sở khám bệnh, chữa bệnh, trừ xe ưu tiên đang đi làm nhiệm vụ.',
     'base'=>'Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 8.000.000 – 10.000.000 đồng',
     'fbase'=>'Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'nc-06','icon'=>'🚸','who'=>'lon',
     'title'=>'Cấm bỏ trốn sau khi gây tai nạn, không cứu giúp người bị nạn',
     'kid'=>'Nếu chẳng may thấy ai đó bị tai nạn, việc đầu tiên con nên làm là **gọi người lớn** và **gọi 115** (cấp cứu). Con còn nhỏ nên đừng tự mình di chuyển người bị thương nhé — hãy gọi người lớn tới giúp.',
     'rule'=>'Nghiêm cấm hành vi bỏ trốn sau khi gây tai nạn giao thông để trốn tránh trách nhiệm; khi có điều kiện mà cố ý không cứu giúp người bị tai nạn giao thông.',
     'base'=>'Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Có thể bị xử phạt hành chính rất nặng hoặc truy cứu trách nhiệm hình sự',
     'fbase'=>'Nghị định 168/2024/NĐ-CP; Điều 260 Bộ luật Hình sự'],
  ],
],

/* ══════════ NHÓM 8: BẢNG MỨC PHẠT (CHO BA MẸ) ══════════ */
'muc-phat' => [
  'label' => '💰 Bảng mức phạt (cho ba mẹ)',
  'note'  => 'Phần này dành cho ba mẹ và thầy cô. Các mức phạt dưới đây theo Nghị định 168/2024/NĐ-CP (hiệu lực 01/01/2025). Mức phạt có thể thay đổi — hãy tra cứu văn bản gốc tại chinhphu.vn khi cần chính xác tuyệt đối.',
  'laws'  => [

    ['id'=>'mp-01','icon'=>'🔴','who'=>'lon',
     'title'=>'Vượt đèn đỏ (không chấp hành đèn tín hiệu)',
     'kid'=>'Đây là lỗi bị tăng mức phạt mạnh nhất từ năm 2025 — tăng gấp 3 đến 6 lần so với trước.',
     'rule'=>'Không chấp hành hiệu lệnh của đèn tín hiệu giao thông.',
     'base'=>'Khoản 4 Điều 11 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 4.000.000 – 6.000.000 đồng, trừ 4 điểm GPLX · Ô tô: 18.000.000 – 20.000.000 đồng, trừ 4 điểm GPLX · Gây tai nạn: xe máy 10 – 14 triệu, trừ 10 điểm GPLX',
     'fbase'=>'Điểm c khoản 7 Điều 7 và khoản 9 Điều 6 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-02','icon'=>'⛑️','who'=>'lon',
     'title'=>'Không đội mũ bảo hiểm / không cài quai đúng quy cách',
     'kid'=>'Lưu ý: mỗi người vi phạm bị phạt riêng. Nếu cả người lái và người ngồi sau đều không đội mũ thì tổng mức phạt cho một xe có thể lên tới 1.200.000 đồng.',
     'rule'=>'Không đội mũ bảo hiểm hoặc đội mũ bảo hiểm không cài quai đúng quy cách khi điều khiển hoặc khi được chở trên xe mô tô, xe gắn máy, xe đạp máy.',
     'base'=>'Điều 33 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'400.000 – 600.000 đồng cho mỗi người vi phạm',
     'fbase'=>'Điểm h, i khoản 2 Điều 7 và điểm d, đ khoản 4 Điều 9 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-03','icon'=>'🍺','who'=>'lon',
     'title'=>'Vi phạm nồng độ cồn',
     'kid'=>'Việt Nam áp dụng ngưỡng bằng 0 — chỉ cần có nồng độ cồn là đã vi phạm, không có mức "cho phép".',
     'rule'=>'Điều khiển phương tiện mà trong máu hoặc hơi thở có nồng độ cồn.',
     'base'=>'Khoản 2 Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 2.000.000 – 10.000.000 đồng tuỳ mức · Ô tô: 6.000.000 – 40.000.000 đồng tuỳ mức, có thể bị tước GPLX 22 – 24 tháng ở mức cao nhất · Xe đạp: 100.000 – 600.000 đồng',
     'fbase'=>'Điều 6, Điều 7, Điều 9 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-04','icon'=>'📱','who'=>'lon',
     'title'=>'Dùng điện thoại khi đang lái xe',
     'kid'=>'Nhìn điện thoại 2 giây ở tốc độ 50 km/h nghĩa là xe đã đi được gần 28 mét trong tình trạng người lái không nhìn đường.',
     'rule'=>'Dùng tay cầm và sử dụng điện thoại hoặc thiết bị điện tử khác khi điều khiển phương tiện tham gia giao thông.',
     'base'=>'Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Ô tô: 4.000.000 – 6.000.000 đồng · Xe máy: 800.000 – 1.000.000 đồng · Xe đạp: 100.000 – 200.000 đồng',
     'fbase'=>'Điều 6, Điều 7, Điều 9 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-05','icon'=>'🔗','who'=>'lon',
     'title'=>'Không thắt dây đai an toàn',
     'kid'=>'Áp dụng cho mọi vị trí ngồi có trang bị dây, kể cả hàng ghế sau.',
     'rule'=>'Không thắt dây đai an toàn tại vị trí có trang bị dây đai an toàn khi xe đang chạy.',
     'base'=>'Khoản 3 Điều 10 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Người lái xe hoặc chở người không thắt dây: 800.000 – 1.000.000 đồng · Người được chở không thắt dây: 350.000 – 400.000 đồng',
     'fbase'=>'Điều 6 và Điều 11 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-06','icon'=>'🏎️','who'=>'lon',
     'title'=>'Chạy quá tốc độ quy định',
     'kid'=>'Khu vực đông dân cư và gần trường học thường có biển hạn chế tốc độ riêng để bảo vệ trẻ em.',
     'rule'=>'Điều khiển xe chạy quá tốc độ quy định.',
     'base'=>'Điều 12 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: từ 400.000 – 600.000 đồng (quá 5 – 10 km/h) đến 8.000.000 – 10.000.000 đồng (quá trên 20 km/h) · Gây tai nạn: 10.000.000 – 14.000.000 đồng, trừ 10 điểm GPLX · Ô tô: từ 800.000 đồng đến 12.000.000 – 14.000.000 đồng tuỳ mức',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-07','icon'=>'↩️','who'=>'lon',
     'title'=>'Đi ngược chiều, đi trên vỉa hè',
     'kid'=>'Đi xe máy lên vỉa hè lấn chỗ của người đi bộ, đặc biệt nguy hiểm với trẻ em đang đi học về.',
     'rule'=>'Đi ngược chiều của đường một chiều, đi ngược chiều trên đường có biển "Cấm đi ngược chiều"; đi trên vỉa hè (trừ trường hợp đi qua vỉa hè để vào nhà, cơ quan).',
     'base'=>'Điều 9 và Điều 10 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 4.000.000 – 6.000.000 đồng · Ô tô đi ngược chiều: 18.000.000 – 20.000.000 đồng',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-08','icon'=>'🔑','who'=>'lon',
     'title'=>'Giao xe cho người chưa đủ điều kiện điều khiển',
     'kid'=>'Đây là lỗi nhiều phụ huynh mắc phải khi cho con em chưa đủ tuổi mượn xe đi học.',
     'rule'=>'Chủ xe giao xe hoặc để cho người không đủ điều kiện điều khiển xe tham gia giao thông đường bộ.',
     'base'=>'Khoản 1 Điều 56 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe mô tô, xe gắn máy: 8.000.000 – 10.000.000 đồng (cá nhân) · Ô tô: 28.000.000 – 30.000.000 đồng (cá nhân); mức phạt với tổ chức gấp đôi',
     'fbase'=>'Khoản 10 và khoản 14 Điều 32 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-09','icon'=>'🚑','who'=>'lon',
     'title'=>'Không nhường đường cho xe ưu tiên',
     'kid'=>'Mỗi giây xe cứu thương bị chậm lại đều có thể ảnh hưởng đến tính mạng của người bệnh trên xe.',
     'rule'=>'Không nhường đường hoặc gây cản trở xe ưu tiên đang phát tín hiệu ưu tiên đi làm nhiệm vụ.',
     'base'=>'Điều 27 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 4.000.000 – 6.000.000 đồng · Ô tô: 6.000.000 – 8.000.000 đồng, kèm trừ điểm GPLX',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-10','icon'=>'🎯','who'=>'lon',
     'title'=>'Trừ điểm và phục hồi điểm giấy phép lái xe',
     'kid'=>'Đây là cơ chế mới áp dụng từ 01/01/2025 bên cạnh việc phạt tiền.',
     'rule'=>'Mỗi giấy phép lái xe có 12 điểm. Người vi phạm bị trừ điểm tương ứng với mức độ vi phạm. Nếu bị trừ hết 12 điểm, người lái xe không được điều khiển phương tiện và phải tham gia kiểm tra kiến thức pháp luật về trật tự, an toàn giao thông đường bộ để được phục hồi điểm. Nếu trong 12 tháng kể từ lần trừ điểm gần nhất mà không bị trừ hết điểm thì được phục hồi đủ 12 điểm.',
     'base'=>'Điều 58 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>'Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-11','icon'=>'👨‍👩‍👧‍👦','who'=>'lon',
     'title'=>'Chở quá số người quy định trên xe máy',
     'kid'=>'Xe máy chỉ được chở tối đa một người (trừ khi chở trẻ dưới 12 tuổi, người bệnh đi cấp cứu, người già yếu hoặc người khuyết tật). Chở kẹp ba, kẹp bốn làm xe nặng, khó giữ thăng bằng và phanh không kịp.',
     'rule'=>'Người điều khiển xe mô tô, xe gắn máy chở theo 02 người trở lên, trừ các trường hợp được phép, bị xử phạt.',
     'base'=>'Điều 33 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Chở theo 02 người: 400.000 – 600.000 đồng · Chở từ 03 người trở lên: 600.000 – 800.000 đồng',
     'fbase'=>'Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-12','icon'=>'🪪','who'=>'lon',
     'title'=>'Không có hoặc không mang giấy phép lái xe',
     'kid'=>'Muốn lái xe máy, xe ô tô thì phải học và thi để có giấy phép lái xe. Lái xe khi chưa có giấy phép rất nguy hiểm vì người lái chưa đủ kỹ năng.',
     'rule'=>'Người điều khiển xe không có giấy phép lái xe phù hợp, hoặc có nhưng không mang theo khi tham gia giao thông, bị xử phạt.',
     'base'=>'Điều 56 và Điều 57 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Không có GPLX xe mô tô hai bánh: 2.000.000 – 4.000.000 đồng · Không mang theo GPLX: xe máy 100.000 – 200.000 đồng, ô tô 300.000 – 400.000 đồng',
     'fbase'=>'Điều 18 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-13','icon'=>'👮','who'=>'lon',
     'title'=>'Không chấp hành hiệu lệnh của người điều khiển giao thông',
     'kid'=>'Hiệu lệnh của chú cảnh sát giao thông là cao nhất, phải nghe theo kể cả khi đèn đang xanh. Không chấp hành bị phạt rất nặng.',
     'rule'=>'Không chấp hành hiệu lệnh, hướng dẫn của người điều khiển giao thông hoặc người kiểm soát giao thông.',
     'base'=>'Điều 11 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 4.000.000 – 6.000.000 đồng, trừ 4 điểm GPLX · Ô tô: 18.000.000 – 20.000.000 đồng, trừ 4 điểm GPLX',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-14','icon'=>'🅿️','who'=>'lon',
     'title'=>'Dừng, đỗ xe không đúng quy định',
     'kid'=>'Dừng đỗ xe bừa dưới lòng đường, trên vỉa hè hay chỗ có biển cấm sẽ chắn lối của người khác và che tầm nhìn, dễ gây tai nạn cho các bạn nhỏ đi bộ.',
     'rule'=>'Dừng xe, đỗ xe ở lòng đường gây cản trở giao thông; đỗ, để xe ở lòng đường, vỉa hè trái phép; dừng, đỗ xe nơi có biển cấm dừng, cấm đỗ.',
     'base'=>'Điều 18 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 400.000 – 600.000 đồng (dừng đỗ trên cầu: 600.000 – 800.000 đồng) · Ô tô: 800.000 – 1.000.000 đồng, cao hơn nếu trên đường cao tốc',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-15','icon'=>'🌀','who'=>'lon',
     'title'=>'Lạng lách, đánh võng, bốc đầu xe',
     'kid'=>'Lạng lách, đánh võng, bốc đầu xe là những trò cực kỳ nguy hiểm, chỉ cần một giây mất lái là gây tai nạn cho mình và người khác. Đây là một trong những lỗi bị phạt nặng nhất với xe máy.',
     'rule'=>'Điều khiển xe lạng lách, đánh võng; chạy bằng một bánh đối với xe hai bánh; buông cả hai tay; dùng chân điều khiển xe khi đang chạy.',
     'base'=>'Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 8.000.000 – 10.000.000 đồng, trừ 10 điểm GPLX · Trường hợp nghiêm trọng có thể bị tịch thu phương tiện',
     'fbase'=>'Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-16','icon'=>'⛔','who'=>'lon',
     'title'=>'Đi vào đường cấm, khu vực cấm',
     'kid'=>'Có những con đường hoặc khung giờ cấm một số loại xe để bảo đảm an toàn. Cố đi vào đường cấm vừa nguy hiểm vừa bị phạt.',
     'rule'=>'Đi vào khu vực cấm, đường có biển báo hiệu nội dung cấm đi vào đối với loại phương tiện đang điều khiển.',
     'base'=>'Điều 10 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 400.000 – 600.000 đồng · Ô tô: 2.000.000 – 3.000.000 đồng, trừ điểm GPLX',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-17','icon'=>'🪞','who'=>'lon',
     'title'=>'Không có gương chiếu hậu, không có bảo hiểm bắt buộc',
     'kid'=>'Gương chiếu hậu giúp người lái nhìn thấy xe phía sau, còn bảo hiểm bắt buộc giúp hỗ trợ khi có tai nạn. Thiếu hai thứ này đều bị phạt.',
     'rule'=>'Điều khiển xe không có gương chiếu hậu bên trái hoặc có nhưng không có tác dụng; không có hoặc không mang giấy chứng nhận bảo hiểm bắt buộc trách nhiệm dân sự.',
     'base'=>'Điều 35 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Không gương chiếu hậu (xe máy): 400.000 – 600.000 đồng · Không có bảo hiểm bắt buộc: xe máy 100.000 – 200.000 đồng, ô tô 400.000 – 600.000 đồng',
     'fbase'=>'Điều 12 và Điều 14 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'mp-18','icon'=>'📢','who'=>'lon',
     'title'=>'Bấm còi, rú ga giờ khuya; không bật đèn khi trời tối',
     'kid'=>'Bấm còi inh ỏi hay rú ga lúc đêm khuya làm phiền mọi người đang nghỉ. Trời tối mà không bật đèn thì xe khác không nhìn thấy mình, rất dễ va chạm.',
     'rule'=>'Bấm còi, rú ga liên tục trong khu đông dân cư, gần cơ sở khám chữa bệnh (trừ xe ưu tiên); bấm còi trong thời gian từ 22 giờ đến 05 giờ; không sử dụng đèn chiếu sáng khi trời tối hoặc khi có sương mù, thời tiết xấu.',
     'base'=>'Điều 10 và Điều 20 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 200.000 – 400.000 đồng cho mỗi lỗi · Ô tô: 400.000 – 600.000 đồng',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],
  ],
],

/* ══════════ NHÓM 9: BẢNG PHẠT ĐẦY ĐỦ (TRA CỨU NHANH) ══════════ */
'muc-phat-full' => [
  'label' => '📋 Bảng phạt đầy đủ (tra cứu nhanh)',
  'note'  => 'Bảng tra cứu nhanh, gộp thêm nhiều lỗi thường gặp theo từng nhóm phương tiện. Số liệu theo Nghị định 168/2024/NĐ-CP (hiệu lực 01/01/2025), một số nội dung được sửa đổi bởi Nghị định 238/2026/NĐ-CP. Mức phạt có thể thay đổi — khi cần chính xác tuyệt đối hãy tra cứu văn bản gốc tại chinhphu.vn.',
  'laws'  => [
    ['id'=>'pf-01','icon'=>'🚶','who'=>'lon',
     'title'=>'Tổng hợp lỗi của người đi bộ',
     'kid'=>'Người đi bộ cũng phải theo luật: đi đúng vỉa hè, sang đường đúng vạch, tuân theo đèn tín hiệu. Đi sai cũng có thể bị nhắc nhở hoặc phạt.',
     'rule'=>'Không đi đúng phần đường quy định; vượt qua dải phân cách; không chấp hành đèn tín hiệu, vạch kẻ đường hoặc hiệu lệnh của người điều khiển giao thông; đi vào đường cao tốc (trừ người làm nhiệm vụ).',
     'base'=>'Điều 30 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Đa số lỗi của người đi bộ: 150.000 – 250.000 đồng · Đi vào đường cao tốc: 400.000 – 600.000 đồng',
     'fbase'=>'Điều 10 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'pf-02','icon'=>'🚲','who'=>'lon',
     'title'=>'Tổng hợp lỗi của xe đạp, xe đạp điện',
     'kid'=>'Xe đạp và xe đạp điện tuy nhỏ nhưng vẫn phải theo luật giao thông. Các bạn nhỏ đi xe đạp nhớ đi sát lề phải, không dàn hàng ngang, không dùng điện thoại khi đang đi nhé.',
     'rule'=>'Không đi bên phải theo chiều đi, đi không đúng phần đường; đi dàn hàng ngang từ 03 xe trở lên; dùng tay cầm và sử dụng điện thoại; không chấp hành đèn tín hiệu; đi vào đường cao tốc; điều khiển xe khi trong máu hoặc hơi thở có nồng độ cồn.',
     'base'=>'Điều 31 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Lỗi thường gặp: 100.000 – 200.000 đồng · Vượt đèn đỏ, đi ngược chiều: 150.000 – 250.000 đồng · Đi vào cao tốc, nồng độ cồn mức cao: 300.000 – 600.000 đồng',
     'fbase'=>'Điều 9 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'pf-03','icon'=>'📦','who'=>'lon',
     'title'=>'Chở hàng cồng kềnh, kéo theo xe hoặc vật khác',
     'kid'=>'Chở hàng quá to, quá dài che khuất tầm nhìn hoặc rơi ra đường rất nguy hiểm cho người xung quanh, nhất là các bạn nhỏ.',
     'rule'=>'Xếp hàng hóa vượt quá giới hạn quy định; kéo theo xe khác, vật khác; để người được chở bám, kéo, đẩy vật cồng kềnh.',
     'base'=>'Điều 33 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 600.000 – 800.000 đồng · Ô tô chở hàng vượt quy định: từ 800.000 – 1.000.000 đồng trở lên tùy mức',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'pf-04','icon'=>'↔️','who'=>'lon',
     'title'=>'Vượt xe sai quy định, vượt bên phải',
     'kid'=>'Muốn vượt xe khác phải quan sát kỹ và chỉ vượt khi được phép. Vượt ẩu ở nơi cấm vượt là nguyên nhân của rất nhiều vụ tai nạn.',
     'rule'=>'Vượt xe trong các trường hợp không được vượt; vượt xe tại đoạn đường có biển báo cấm vượt; vượt bên phải không đúng quy định.',
     'base'=>'Điều 14 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 600.000 – 800.000 đồng · Ô tô: 4.000.000 – 6.000.000 đồng, trừ điểm GPLX',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'pf-05','icon'=>'🛣️','who'=>'lon',
     'title'=>'Xe máy, xe thô sơ đi vào đường cao tốc',
     'kid'=>'Đường cao tốc là nơi ô tô chạy rất nhanh, xe máy và xe đạp tuyệt đối không được đi vào vì cực kỳ nguy hiểm.',
     'rule'=>'Điều khiển xe mô tô, xe gắn máy, xe thô sơ đi vào đường cao tốc, trừ xe phục vụ việc quản lý, bảo trì đường cao tốc.',
     'base'=>'Điều 25 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 4.000.000 – 6.000.000 đồng, trừ 4 điểm GPLX',
     'fbase'=>'Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'pf-06','icon'=>'🔢','who'=>'lon',
     'title'=>'Không gắn biển số hoặc gắn biển số không đúng quy định',
     'kid'=>'Biển số giúp nhận ra từng chiếc xe. Xe không có biển số hoặc gắn biển số giả là vi phạm và bị phạt nặng.',
     'rule'=>'Điều khiển xe không gắn biển số; gắn biển số không đúng với chứng nhận đăng ký xe; gắn biển số không do cơ quan có thẩm quyền cấp.',
     'base'=>'Điều 35 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 4.000.000 – 6.000.000 đồng · Ô tô: 20.000.000 – 26.000.000 đồng',
     'fbase'=>'Điều 14 và Điều 32 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'pf-07','icon'=>'🙌','who'=>'lon',
     'title'=>'Buông hai tay, dùng chân lái, chạy bằng một bánh',
     'kid'=>'Buông cả hai tay, đứng lên yên hay chạy bằng một bánh để khoe với bạn bè là những trò rất nguy hiểm. Luật phạt rất nặng, thậm chí tịch thu xe.',
     'rule'=>'Buông cả hai tay khi đang điều khiển xe; dùng chân điều khiển xe; ngồi về một bên điều khiển xe; nằm trên yên xe điều khiển; quay người về phía sau để điều khiển; bịt mắt điều khiển; chạy bằng một bánh đối với xe hai bánh.',
     'base'=>'Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 8.000.000 – 10.000.000 đồng, trừ 10 điểm GPLX; một số hành vi còn bị tịch thu phương tiện',
     'fbase'=>'Khoản 9 và khoản 11 Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'pf-08','icon'=>'🚨','who'=>'lon',
     'title'=>'Gây tai nạn rồi bỏ chạy, không cứu giúp người bị nạn',
     'kid'=>'Nếu chẳng may có va chạm, người lái phải dừng lại, giữ nguyên hiện trường và giúp đỡ người bị thương. Bỏ chạy là vừa sai đạo đức vừa bị phạt rất nặng.',
     'rule'=>'Gây tai nạn giao thông không dừng ngay phương tiện, không giữ nguyên hiện trường, không trợ giúp người bị nạn, không ở lại hiện trường hoặc không trình báo cơ quan có thẩm quyền.',
     'base'=>'Điều 80 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 8.000.000 – 10.000.000 đồng, trừ 10 điểm GPLX · Ô tô: 16.000.000 – 18.000.000 đồng, trừ 10 điểm GPLX · Có thể bị truy cứu trách nhiệm hình sự',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],
  ],
],

/* ═════════════════════════════════════════════════════════════════
   [NÂNG CẤP V2 — PHẦN THÊM MỚI, KHÔNG SỬA CODE CŨ]
   6 NHÓM LUẬT BỔ SUNG (NHÓM 10 → 15) — bảng phạt chi tiết theo
   Nghị định 168/2024/NĐ-CP, Luật TTATGT đường bộ 2024 (36/2024/QH15)
   và Thông tư 38/2024/TT-BGTVT về tốc độ, khoảng cách an toàn.
   ═════════════════════════════════════════════════════════════════ */

/* ══════════ NHÓM 10: XE MÁY — MỨC PHẠT CHI TIẾT ══════════ */
'xe-may-phat' => [
  'label' => '🏍️ Xe máy: phạt chi tiết',
  'note'  => 'Bảng phạt chi tiết dành cho xe mô tô, xe gắn máy theo Điều 7 Nghị định 168/2024/NĐ-CP (hiệu lực 01/01/2025). Nhóm này chủ yếu để ba mẹ tham khảo — các con đọc phần GIẢI THÍCH CHO CON để hiểu vì sao có luật nhé.',
  'laws'  => [

    ['id'=>'xm-01','icon'=>'🚀','who'=>'lon',
     'title'=>'Xe máy chạy quá tốc độ — đủ các mức phạt',
     'kid'=>'Xe chạy càng nhanh thì khi gặp chuyện bất ngờ càng không kịp phanh. Con hãy để ý đồng hồ tốc độ trên xe của ba mẹ: nếu kim vượt quá con số trên biển báo, con nhắc khéo: Ba mẹ ơi, mình đi chậm lại một chút cho an toàn nha!',
     'rule'=>'Mức phạt tăng dần theo số km/h vượt quá: quá từ 05 đến dưới 10 km/h; quá từ 10 đến 20 km/h; quá trên 20 km/h. Chạy quá tốc độ mà gây tai nạn thì bị phạt ở khung nặng nhất và trừ nhiều điểm giấy phép lái xe.',
     'base'=>'Điều 12 Luật Trật tự, an toàn giao thông đường bộ 2024; Thông tư 38/2024/TT-BGTVT',
     'fine'=>'Quá 05 – dưới 10 km/h: 400.000 – 600.000 đồng · Quá 10 – 20 km/h: 800.000 – 1.000.000 đồng · Quá trên 20 km/h: 6.000.000 – 8.000.000 đồng, trừ 4 điểm GPLX · Quá tốc độ gây tai nạn: 10.000.000 – 14.000.000 đồng, trừ 10 điểm GPLX',
     'fbase'=>'Khoản 2, khoản 4, khoản 8, khoản 10 và khoản 13 Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xm-02','icon'=>'🏁','who'=>'lon',
     'title'=>'Chạy quá tốc độ theo nhóm, rượt đuổi nhau trên đường',
     'kid'=>'Vài anh chị thích phóng xe đuổi nhau cho vui — nhưng đường phố không phải trường đua! Chỉ một giây lạc tay lái là cả nhóm gặp nguy hiểm và làm hại cả người đi đường. Luật phạt lỗi này nặng hơn hẳn lỗi chạy nhanh một mình đó con.',
     'rule'=>'Điều khiển xe thành nhóm từ 02 xe trở lên chạy quá tốc độ quy định là hành vi bị xử phạt nặng, tách riêng khỏi lỗi quá tốc độ thông thường.',
     'base'=>'Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'8.000.000 – 10.000.000 đồng',
     'fbase'=>'Điểm b khoản 9 Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xm-03','icon'=>'↩️','who'=>'lon',
     'title'=>'Xe máy đi ngược chiều',
     'kid'=>'Đi ngược chiều giống như bơi ngược dòng giữa đàn cá đang lao tới — ai cũng bất ngờ và không kịp tránh. Dù phải đi vòng xa hơn một chút, đi đúng chiều vẫn nhanh hơn nhiều so với một vụ va chạm, con nhỉ?',
     'rule'=>'Đi ngược chiều của đường một chiều, đi ngược chiều trên đường có biển Cấm đi ngược chiều bị phạt tiền và trừ điểm giấy phép lái xe; nếu gây tai nạn thì bị phạt ở khung rất nặng.',
     'base'=>'Điều 13 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'4.000.000 – 6.000.000 đồng, trừ 2 điểm GPLX · Gây tai nạn: 10.000.000 – 14.000.000 đồng, trừ 10 điểm GPLX',
     'fbase'=>'Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xm-04','icon'=>'🛑','who'=>'lon',
     'title'=>'Xe máy leo lên vỉa hè',
     'kid'=>'Vỉa hè là đường riêng của người đi bộ — trong đó có con và các bạn! Khi kẹt xe, có người phóng xe máy lên vỉa hè cho nhanh, làm các bạn nhỏ đang đi bộ giật mình né tránh. Từ năm 2025 lỗi này bị phạt nặng gấp nhiều lần trước đây.',
     'rule'=>'Điều khiển xe máy đi trên vỉa hè bị phạt tiền và trừ điểm giấy phép lái xe, trừ trường hợp cho xe đi qua vỉa hè để vào nhà, cơ quan.',
     'base'=>'Điều 13 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'4.000.000 – 6.000.000 đồng, trừ 2 điểm GPLX',
     'fbase'=>'Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xm-05','icon'=>'🍺','who'=>'lon',
     'title'=>'Nồng độ cồn khi lái xe máy — đủ 3 mức phạt',
     'kid'=>'Rượu bia làm người lái nhìn chậm, phản ứng chậm và buồn ngủ. Vì vậy luật cấm tuyệt đối: đã uống rượu bia thì KHÔNG lái xe. Nếu ba hoặc người thân vừa uống bia mà định chở con, con hãy dũng cảm nói: Mình gọi taxi hoặc nhờ người khác chở đi ạ!',
     'rule'=>'Mức 1: chưa vượt quá 50 mg/100 ml máu hoặc 0,25 mg/1 lít khí thở. Mức 2: vượt quá 50 mg đến 80 mg/100 ml máu hoặc vượt quá 0,25 đến 0,4 mg/1 lít khí thở. Mức 3: vượt quá 80 mg/100 ml máu hoặc vượt quá 0,4 mg/1 lít khí thở. Không chấp hành yêu cầu kiểm tra nồng độ cồn bị phạt như mức 3.',
     'base'=>'Khoản 1 Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024 (cấm tuyệt đối)',
     'fine'=>'Mức 1: 2.000.000 – 3.000.000 đồng, trừ 4 điểm GPLX · Mức 2: 6.000.000 – 8.000.000 đồng, trừ 10 điểm GPLX · Mức 3: 8.000.000 – 10.000.000 đồng, tước GPLX 22 – 24 tháng · Không chịu thổi nồng độ cồn: 8.000.000 – 10.000.000 đồng, tước GPLX 22 – 24 tháng',
     'fbase'=>'Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xm-06','icon'=>'📱','who'=>'lon',
     'title'=>'Dùng điện thoại khi đang lái xe máy',
     'kid'=>'Chỉ cần cúi xuống nhìn điện thoại 3 giây, chiếc xe đã tự chạy được mấy chục mét mà không ai điều khiển — như xe không người lái vậy! Nếu thấy ba mẹ vừa chạy xe vừa cầm điện thoại, con hãy nói: Để con giữ điện thoại cho, tới nơi ba mẹ xem sau nha!',
     'rule'=>'Người đang điều khiển xe máy sử dụng điện thoại hoặc thiết bị điện tử khác bị phạt tiền và trừ điểm giấy phép lái xe; nếu gây tai nạn thì bị phạt ở khung rất nặng.',
     'base'=>'Khoản 6 Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'800.000 – 1.000.000 đồng, trừ 4 điểm GPLX · Gây tai nạn: 10.000.000 – 14.000.000 đồng, trừ 10 điểm GPLX',
     'fbase'=>'Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xm-07','icon'=>'🔦','who'=>'lon',
     'title'=>'Không bật đèn xe khi trời tối (18 giờ đến 6 giờ sáng)',
     'kid'=>'Đèn xe không chỉ để nhìn đường — mà còn để NGƯỜI KHÁC nhìn thấy mình! Xe không bật đèn trong bóng tối giống như người mặc đồ đen đứng giữa đường khuya. Ngồi sau xe buổi tối, con thấy xe chưa sáng đèn thì nhắc ba mẹ liền nhé.',
     'rule'=>'Không sử dụng đèn chiếu sáng trong thời gian từ 18 giờ hôm trước đến 06 giờ hôm sau, hoặc khi có sương mù, khói, bụi, trời mưa, thời tiết xấu làm hạn chế tầm nhìn.',
     'base'=>'Điều 20 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'200.000 – 400.000 đồng',
     'fbase'=>'Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'xm-08','icon'=>'🪞','who'=>'lon',
     'title'=>'Xe thiếu gương chiếu hậu, đèn, còi',
     'kid'=>'Gương chiếu hậu là con mắt phía sau của người lái — không có gương thì không biết xe nào đang tới gần. Đèn và còi cũng vậy, đều là đồ bảo vệ chứ không phải đồ trang trí. Xe nhà mình gãy gương thì nhắc ba mẹ thay ngay nha con.',
     'rule'=>'Điều khiển xe không có gương chiếu hậu bên trái hoặc có nhưng không có tác dụng; xe không có đèn chiếu sáng, đèn tín hiệu, còi hoặc có nhưng không có tác dụng.',
     'base'=>'Điều 35 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'400.000 – 600.000 đồng',
     'fbase'=>'Điều 14 Nghị định 168/2024/NĐ-CP'],
  ],
],

/* ══════════ NHÓM 11: Ô TÔ — MỨC PHẠT CHI TIẾT ══════════ */
'o-to-phat' => [
  'label' => '🚗 Ô tô: phạt chi tiết',
  'note'  => 'Bảng phạt chi tiết dành cho người lái ô tô theo Điều 6 Nghị định 168/2024/NĐ-CP. Từ 01/01/2025 nhiều lỗi của ô tô bị phạt nặng gấp 5 – 10 lần trước đây, vì ô tô to và nặng, khi gây tai nạn hậu quả rất lớn.',
  'laws'  => [

    ['id'=>'op-01','icon'=>'🚀','who'=>'lon',
     'title'=>'Ô tô chạy quá tốc độ — đủ các mức phạt',
     'kid'=>'Ô tô nặng cả tấn, chạy càng nhanh thì quãng đường phanh càng dài. Ngồi trong xe, con có thể làm trợ lý nhỏ: thấy biển giới hạn tốc độ ven đường, con đọc to lên cho cả nhà cùng biết nhé!',
     'rule'=>'Mức phạt tăng dần: quá từ 05 đến dưới 10 km/h; quá từ 10 đến 20 km/h; quá từ trên 20 đến 35 km/h; quá trên 35 km/h. Chạy quá tốc độ gây tai nạn bị phạt ở khung rất nặng.',
     'base'=>'Điều 12 Luật Trật tự, an toàn giao thông đường bộ 2024; Thông tư 38/2024/TT-BGTVT',
     'fine'=>'Quá 05 – dưới 10 km/h: 800.000 – 1.000.000 đồng · Quá 10 – 20 km/h: 4.000.000 – 6.000.000 đồng, trừ 2 điểm GPLX · Quá trên 20 – 35 km/h: 6.000.000 – 8.000.000 đồng, trừ 4 điểm GPLX · Quá trên 35 km/h: 12.000.000 – 14.000.000 đồng, trừ 6 điểm GPLX · Quá tốc độ gây tai nạn: 20.000.000 – 22.000.000 đồng, trừ 10 điểm GPLX',
     'fbase'=>'Khoản 3, khoản 5, khoản 6, khoản 7 và khoản 10 Điều 6 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'op-02','icon'=>'🍺','who'=>'lon',
     'title'=>'Nồng độ cồn khi lái ô tô — đủ 3 mức phạt',
     'kid'=>'Với ô tô, mức phạt nồng độ cồn cao nhất lên tới 40 triệu đồng và gần 2 năm không được lái xe — vì một chiếc ô tô mất lái có thể làm bị thương rất nhiều người. Cả nhà đi tiệc về mà ba đã uống bia, con hãy gợi ý: Nhà mình gọi tài xế hộ hoặc đi taxi cho an toàn nha ba!',
     'rule'=>'Mức 1: chưa vượt quá 50 mg/100 ml máu hoặc 0,25 mg/1 lít khí thở. Mức 2: vượt quá 50 mg đến 80 mg/100 ml máu hoặc vượt quá 0,25 đến 0,4 mg/1 lít khí thở. Mức 3: vượt quá 80 mg/100 ml máu hoặc vượt quá 0,4 mg/1 lít khí thở. Không chấp hành yêu cầu kiểm tra nồng độ cồn bị phạt như mức 3.',
     'base'=>'Khoản 1 Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024 (cấm tuyệt đối)',
     'fine'=>'Mức 1: 6.000.000 – 8.000.000 đồng, trừ 4 điểm GPLX · Mức 2: 18.000.000 – 20.000.000 đồng, trừ 10 điểm GPLX · Mức 3: 30.000.000 – 40.000.000 đồng, tước GPLX 22 – 24 tháng · Không chịu thổi nồng độ cồn: 30.000.000 – 40.000.000 đồng, tước GPLX 22 – 24 tháng',
     'fbase'=>'Điều 6 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'op-03','icon'=>'🚦','who'=>'lon',
     'title'=>'Ô tô vượt đèn đỏ',
     'kid'=>'Từ năm 2025, ô tô vượt đèn đỏ bị phạt tới 20 triệu đồng — bằng cả một chiếc xe đạp điện mới! Con số lớn như vậy để nhắc mọi người: đèn đỏ là mệnh lệnh phải dừng, không phải lời gợi ý.',
     'rule'=>'Không chấp hành hiệu lệnh của đèn tín hiệu giao thông bị phạt tiền và trừ điểm giấy phép lái xe; nếu gây tai nạn thì bị phạt ở khung rất nặng.',
     'base'=>'Điều 11 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'18.000.000 – 20.000.000 đồng, trừ 4 điểm GPLX · Gây tai nạn: 20.000.000 – 22.000.000 đồng, trừ 10 điểm GPLX',
     'fbase'=>'Điều 6 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'op-04','icon'=>'↩️','who'=>'lon',
     'title'=>'Ô tô đi ngược chiều; lùi xe hoặc đi ngược chiều trên cao tốc',
     'kid'=>'Trên cao tốc, xe nào cũng chạy trên 80 km/h. Một chiếc ô tô bỗng đi lùi hoặc quay đầu ngược chiều thì giống như tảng đá chắn giữa dòng thác — cực kỳ nguy hiểm. Vì vậy đây là một trong những lỗi bị phạt nặng nhất của ô tô.',
     'rule'=>'Đi ngược chiều của đường một chiều hoặc đường có biển Cấm đi ngược chiều bị phạt nặng. Riêng hành vi điều khiển xe đi ngược chiều hoặc lùi xe trên đường cao tốc bị phạt ở khung cao nhất.',
     'base'=>'Điều 13 và Điều 25 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Đi ngược chiều: 18.000.000 – 20.000.000 đồng, trừ 4 điểm GPLX · Đi ngược chiều hoặc lùi xe trên đường cao tốc: 30.000.000 – 40.000.000 đồng, trừ điểm GPLX ở mức cao',
     'fbase'=>'Điều 6 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'op-05','icon'=>'🚪','who'=>'lon',
     'title'=>'Mở cửa ô tô không quan sát gây tai nạn',
     'kid'=>'Đây là quy định liên quan trực tiếp tới con nè! Khi xuống xe, con hãy dùng MỞ CỬA KIỂU HÀ LAN: dùng tay XA cửa hơn để mở — thân người sẽ tự xoay lại, mắt nhìn được ra phía sau xem có xe máy, xe đạp đang tới không, rồi mới hé cửa từ từ.',
     'rule'=>'Mở cửa xe, để cửa xe mở không bảo đảm an toàn bị phạt tiền; nếu mở cửa xe gây tai nạn giao thông thì bị phạt ở khung rất nặng và trừ 10 điểm giấy phép lái xe.',
     'base'=>'Điều 19 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Mở cửa không bảo đảm an toàn: 400.000 – 600.000 đồng · Mở cửa xe gây tai nạn: 20.000.000 – 22.000.000 đồng, trừ 10 điểm GPLX',
     'fbase'=>'Điều 6 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'op-06','icon'=>'📱','who'=>'lon',
     'title'=>'Dùng điện thoại khi đang lái ô tô',
     'kid'=>'Lái ô tô mà nhìn điện thoại thì hai mắt rời khỏi đường, một tay rời vô lăng — chiếc xe cả tấn đang lao đi mà gần như không ai lái! Con có thể làm tổng đài viên của cả nhà: ai gọi tới thì con nghe giúp và nói: Ba mẹ cháu đang lái xe, lát nữa gọi lại ạ.',
     'rule'=>'Dùng tay cầm và sử dụng điện thoại hoặc thiết bị điện tử khác khi điều khiển ô tô bị phạt tiền và trừ điểm giấy phép lái xe; gây tai nạn thì bị phạt ở khung rất nặng.',
     'base'=>'Khoản 6 Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'4.000.000 – 6.000.000 đồng, trừ 4 điểm GPLX · Gây tai nạn: 20.000.000 – 22.000.000 đồng, trừ 10 điểm GPLX',
     'fbase'=>'Điều 6 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'op-07','icon'=>'🔗','who'=>'lon',
     'title'=>'Dây an toàn và chỗ ngồi của trẻ em trên ô tô',
     'kid'=>'Dây an toàn là cái ôm chắc chắn của chiếc xe dành cho con. Con nhớ công thức: LÊN XE — CÀI DÂY — NGHE TÁCH! Và từ năm 2026, bạn nhỏ dưới 10 tuổi và thấp hơn 1m35 không được ngồi ghế trước cạnh tài xế (trừ ô tô chỉ có một hàng ghế) — ghế sau với thiết bị an toàn mới là ngai vàng của con.',
     'rule'=>'Người lái và người được chở trên ô tô phải thắt dây đai an toàn ở những chỗ có trang bị dây. Không được chở trẻ em dưới 10 tuổi và có chiều cao dưới 1,35 mét ngồi cùng hàng ghế với người lái (trừ xe chỉ có một hàng ghế); trẻ dưới 10 tuổi phải dùng thiết bị an toàn phù hợp.',
     'base'=>'Khoản 3 Điều 10 Luật Trật tự, an toàn giao thông đường bộ 2024 (quy định về trẻ em có hiệu lực từ 01/01/2026)',
     'fine'=>'Người lái không thắt dây hoặc chở người không thắt dây: 800.000 – 1.000.000 đồng · Người được chở không thắt dây: 350.000 – 400.000 đồng · Chở trẻ em ngồi sai quy định nêu trên: 800.000 – 1.000.000 đồng',
     'fbase'=>'Điều 6 và Điều 12 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'op-08','icon'=>'🅿️','who'=>'lon',
     'title'=>'Dừng, đỗ ô tô sai quy định trên đường cao tốc',
     'kid'=>'Nếu xe nhà mình chẳng may hỏng trên cao tốc, ba mẹ sẽ tấp vào làn khẩn cấp, bật đèn cảnh báo và đặt biển tam giác phản quang. Việc của con: theo người lớn RA KHỎI XE, đứng sau hàng rào chắn bên lề — đứng trong xe hay cạnh xe đều nguy hiểm vì xe khác đang lao tới rất nhanh.',
     'rule'=>'Dừng xe, đỗ xe trên đường cao tốc không đúng nơi quy định; không có báo hiệu bằng đèn khẩn cấp hoặc không đặt biển cảnh báo về phía sau xe khi buộc phải dừng khẩn cấp.',
     'base'=>'Điều 25 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'12.000.000 – 14.000.000 đồng, trừ 6 điểm GPLX',
     'fbase'=>'Điều 6 Nghị định 168/2024/NĐ-CP'],
  ],
],

/* ══════════ NHÓM 12: BẰNG LÁI & GIẤY TỜ XE ══════════ */
'giay-to' => [
  'label' => '🪪 Bằng lái & giấy tờ xe',
  'note'  => 'Muốn lái xe hợp pháp cần đúng loại giấy tờ. Quy định tại Điều 56, Điều 57 Luật Trật tự, an toàn giao thông đường bộ 2024. Từ 2025 có thể xuất trình giấy tờ đã tích hợp trong VNeID thay cho bản giấy.',
  'laws'  => [

    ['id'=>'gt-01','icon'=>'📂','who'=>'lon',
     'title'=>'Bốn loại giấy tờ phải có khi lái xe',
     'kid'=>'Người lái xe giống như học sinh đi thi — phải mang đủ giấy tờ: giấy phép lái xe, giấy đăng ký xe, bảo hiểm bắt buộc, và ô tô thì thêm giấy kiểm định. Ngày nay các giấy này có thể nằm gọn trong điện thoại của ba mẹ qua ứng dụng VNeID đó con!',
     'rule'=>'Người lái xe phải mang theo: giấy phép lái xe phù hợp với loại xe; chứng nhận đăng ký xe; chứng nhận kiểm định an toàn kỹ thuật và bảo vệ môi trường (đối với xe ô tô); chứng nhận bảo hiểm bắt buộc trách nhiệm dân sự. Giấy tờ đã tích hợp vào tài khoản định danh điện tử thì việc xuất trình qua VNeID có giá trị như bản giấy.',
     'base'=>'Khoản 1 Điều 56 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'gt-02','icon'=>'🫥','who'=>'lon',
     'title'=>'Quên mang bằng lái (có bằng nhưng không mang theo)',
     'kid'=>'Quên mang bằng lái giống như quên mang vở bài tập — vẫn bị nhắc nhở và phạt nhẹ, dù con đã làm bài rồi. Vì vậy ba mẹ thường để giấy tờ xe cố định trong ví hoặc cốp xe để không bao giờ quên.',
     'rule'=>'Người có giấy phép lái xe nhưng không mang theo khi điều khiển phương tiện bị phạt tiền ở mức thấp (khác với lỗi KHÔNG CÓ giấy phép lái xe bị phạt rất nặng).',
     'base'=>'Điều 56 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 100.000 – 200.000 đồng · Ô tô: 300.000 – 400.000 đồng',
     'fbase'=>'Điều 18 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'gt-03','icon'=>'🚫','who'=>'lon',
     'title'=>'Không có giấy phép lái xe',
     'kid'=>'Bằng lái là tấm giấy chứng minh: người này ĐÃ HỌC và ĐÃ THI ĐẬU cách lái xe an toàn. Chưa có bằng mà lái xe thì giống chưa học bơi đã nhảy xuống sông — nguy hiểm cho mình và cho người khác, nên bị phạt rất nặng.',
     'rule'=>'Điều khiển xe mà không có giấy phép lái xe phù hợp với loại xe đang điều khiển bị phạt tiền rất nặng; phương tiện có thể bị tạm giữ.',
     'base'=>'Điều 56 và Điều 57 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 2.000.000 – 4.000.000 đồng · Ô tô: 18.000.000 – 20.000.000 đồng',
     'fbase'=>'Điều 18 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'gt-04','icon'=>'🛡️','who'=>'lon',
     'title'=>'Bảo hiểm bắt buộc trách nhiệm dân sự',
     'kid'=>'Bảo hiểm giống chiếc phao cứu sinh về tiền bạc: nếu chẳng may gây va chạm, công ty bảo hiểm sẽ giúp đền bù cho người bị nạn. Vì vậy luật bắt buộc mọi xe máy, ô tô đều phải mua.',
     'rule'=>'Người điều khiển phương tiện không có chứng nhận bảo hiểm bắt buộc trách nhiệm dân sự còn hiệu lực bị phạt tiền.',
     'base'=>'Điều 56 Luật Trật tự, an toàn giao thông đường bộ 2024; Nghị định 67/2023/NĐ-CP',
     'fine'=>'Xe máy: 100.000 – 200.000 đồng · Ô tô: 400.000 – 600.000 đồng',
     'fbase'=>'Điều 18 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'gt-05','icon'=>'🎓','who'=>'lon',
     'title'=>'Các hạng giấy phép lái xe mới từ 2025',
     'kid'=>'Bằng lái cũng chia cấp giống lớp học vậy đó con: bằng A1 lái xe máy nhỏ, bằng A lái xe máy lớn, bằng B lái ô tô gia đình, còn muốn lái xe tải to hay xe buýt dài thì phải học lên các bằng cao hơn nữa.',
     'rule'=>'Từ 01/01/2025: hạng A1 — xe mô tô hai bánh đến 125 cm3 hoặc xe điện đến 11 kW; hạng A — xe mô tô hai bánh trên 125 cm3; hạng B1 — xe mô tô ba bánh; hạng B — ô tô chở người đến 8 chỗ (không kể người lái) và ô tô tải đến 3,5 tấn; các hạng C1, C, D1, D2, D, BE... dành cho xe tải và xe khách lớn hơn.',
     'base'=>'Điều 57 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'gt-06','icon'=>'⚡','who'=>'be',
     'title'=>'Xe đạp điện khác xe máy điện thế nào?',
     'kid'=>'Hai xe nhìn giống nhau nhưng luật khác nhau đó con! XE ĐẠP ĐIỆN có bàn đạp, chạy chậm (không quá 25 km/h) — học sinh được đi và phải đội mũ bảo hiểm. XE MÁY ĐIỆN chạy nhanh hơn, được coi như xe gắn máy — phải đủ 16 tuổi mới được lái và xe phải có biển số. Con nhớ hỏi kỹ ba mẹ xe nhà mình thuộc loại nào nhé!',
     'rule'=>'Xe đạp máy (gồm xe đạp điện) có vận tốc thiết kế không quá 25 km/h là xe thô sơ. Xe gắn máy (gồm xe máy điện) có vận tốc thiết kế không quá 50 km/h là xe cơ giới — người lái phải đủ 16 tuổi và xe phải đăng ký, gắn biển số.',
     'base'=>'Điều 34 và Điều 59 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>''],
  ],
],

/* ══════════ NHÓM 13: 12 ĐIỂM BẰNG LÁI ══════════ */
'diem-gplx' => [
  'label' => '⭐ 12 điểm bằng lái',
  'note'  => 'Từ 01/01/2025 mỗi giấy phép lái xe có 12 điểm — giống 12 ngôi sao. Vi phạm là bị trừ sao; giữ được đủ sao nghĩa là lái xe văn minh. Quy định tại Điều 58 Luật Trật tự, an toàn giao thông đường bộ 2024 và Điều 50 Nghị định 168/2024/NĐ-CP.',
  'laws'  => [

    ['id'=>'dg-01','icon'=>'⭐','who'=>'be',
     'title'=>'Bằng lái có 12 điểm — giống 12 ngôi sao',
     'kid'=>'Con tưởng tượng bằng lái của ba mẹ có 12 ngôi sao lấp lánh. Ai vi phạm luật sẽ bị lấy bớt sao: lỗi nhẹ mất 2 sao, lỗi nặng mất tới 10 sao. Ai giữ nguyên 12 sao là tài xế gương mẫu — con có thể hỏi ba mẹ: Bằng lái của ba mẹ còn đủ 12 sao không ạ? 😄',
     'rule'=>'Mỗi giấy phép lái xe có 12 điểm. Số điểm bị trừ mỗi lần vi phạm tùy theo tính chất, mức độ của hành vi (2, 4, 6 hoặc 10 điểm). Dữ liệu về điểm được cập nhật vào hệ thống ngay khi quyết định xử phạt có hiệu lực.',
     'base'=>'Điều 58 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'dg-02','icon'=>'📏','who'=>'lon',
     'title'=>'Nguyên tắc trừ điểm giấy phép lái xe',
     'kid'=>'Trừ điểm có luật chơi công bằng: phạm nhiều lỗi cùng một lúc thì chỉ bị trừ theo lỗi nặng nhất chứ không cộng dồn tất cả; và nếu điểm còn lại ít hơn số điểm phải trừ thì trừ hết phần còn lại.',
     'rule'=>'Việc trừ điểm thực hiện ngay khi quyết định xử phạt có hiệu lực. Nếu một lần bị xử phạt có từ 02 hành vi trở lên cùng bị trừ điểm thì chỉ trừ điểm đối với hành vi bị trừ nhiều điểm nhất. Nếu số điểm còn lại ít hơn số điểm bị trừ thì trừ hết số điểm còn lại.',
     'base'=>'Điều 50 Nghị định 168/2024/NĐ-CP',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'dg-03','icon'=>'📉','who'=>'lon',
     'title'=>'Lỗi nào trừ bao nhiêu điểm? (các lỗi thường gặp)',
     'kid'=>'Lỗi càng nguy hiểm thì càng mất nhiều sao. Nhìn bảng này con sẽ hiểu ngay lỗi nào là nghiêm trọng nhất: uống rượu bia nhiều mà lái xe, lạng lách, gây tai nạn rồi bỏ chạy — đều mất gần hết sao trong một lần!',
     'rule'=>'Mức trừ điểm phổ biến: TRỪ 2 ĐIỂM — ô tô quá tốc độ 10 – 20 km/h; xe máy đi ngược chiều, đi lên vỉa hè. TRỪ 4 ĐIỂM — vượt đèn đỏ; nồng độ cồn mức 1; dùng điện thoại khi lái xe; xe máy quá tốc độ trên 20 km/h; ô tô quá tốc độ trên 20 – 35 km/h. TRỪ 6 ĐIỂM — ô tô quá tốc độ trên 35 km/h; dừng đỗ sai trên cao tốc. TRỪ 10 ĐIỂM — nồng độ cồn mức 2; lạng lách, đánh võng; gây tai nạn không dừng lại; mở cửa xe hoặc dùng điện thoại gây tai nạn.',
     'base'=>'Điều 6, Điều 7 Nghị định 168/2024/NĐ-CP',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'dg-04','icon'=>'🕳️','who'=>'lon',
     'title'=>'Bị trừ hết 12 điểm thì sao?',
     'kid'=>'Mất hết 12 sao nghĩa là tạm thời không được lái loại xe đó nữa. Muốn có lại sao, người lái phải chờ ít nhất nửa năm rồi đi KIỂM TRA LẠI kiến thức luật giao thông — thi đậu mới được phục hồi đủ 12 sao. Giống như học lại bài cho thuộc rồi mới được chơi tiếp vậy đó con.',
     'rule'=>'Giấy phép lái xe bị trừ hết điểm thì người đó không được điều khiển phương tiện theo giấy phép đó. Sau ít nhất 06 tháng kể từ ngày bị trừ hết điểm, người lái được tham gia kiểm tra nội dung kiến thức pháp luật về trật tự, an toàn giao thông đường bộ; có kết quả đạt yêu cầu thì được phục hồi đủ 12 điểm.',
     'base'=>'Khoản 3 Điều 58 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'dg-05','icon'=>'🌱','who'=>'lon',
     'title'=>'Lái xe ngoan 12 tháng — sao tự mọc lại đủ',
     'kid'=>'Tin vui nè: ai bị trừ vài sao nhưng sau đó chấp hành luật thật tốt, suốt 12 tháng không bị trừ thêm lần nào — thì các ngôi sao tự phục hồi đủ 12! Luật vừa nghiêm vừa cho người ta cơ hội sửa sai, giống cô giáo cho gỡ điểm vậy.',
     'rule'=>'Giấy phép lái xe chưa bị trừ hết điểm và không bị trừ điểm trong thời hạn 12 tháng kể từ ngày bị trừ điểm gần nhất thì được phục hồi đủ 12 điểm.',
     'base'=>'Khoản 2 Điều 58 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>''],
  ],
],

/* ══════════ NHÓM 14: TỐC ĐỘ TỐI ĐA & KHOẢNG CÁCH AN TOÀN ══════════ */
'toc-do' => [
  'label' => '🚀 Tốc độ & khoảng cách',
  'note'  => 'Xe được chạy nhanh nhất bao nhiêu trên từng loại đường? Quy định tại Thông tư 38/2024/TT-BGTVT (hiệu lực 01/01/2025). Nhớ nhé: con số trên biển báo luôn được ưu tiên trước các mức chung dưới đây.',
  'laws'  => [

    ['id'=>'td-01','icon'=>'🏘️','who'=>'be',
     'title'=>'Trong khu đông dân cư: tối đa 50 – 60 km/h',
     'kid'=>'Trong phố có trường học, chợ, nhà cửa — nhiều người qua lại nên xe phải đi chậm. Đường lớn có dải phân cách giữa: tối đa 60 km/h. Đường nhỏ hai chiều: tối đa 50 km/h. Vì thế khu vực cổng trường của con, xe nào cũng phải bò chậm lại là vậy đó!',
     'rule'=>'Trong khu vực đông dân cư: đường đôi hoặc đường một chiều có từ hai làn xe cơ giới trở lên — tối đa 60 km/h; đường hai chiều hoặc đường một chiều có một làn xe cơ giới — tối đa 50 km/h (áp dụng cho ô tô, xe mô tô).',
     'base'=>'Điều 6 Thông tư 38/2024/TT-BGTVT',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'td-02','icon'=>'🌄','who'=>'be',
     'title'=>'Ngoài khu đông dân cư: xe máy tối đa 60 – 70 km/h',
     'kid'=>'Ra khỏi thành phố, đường vắng hơn nên xe được chạy nhanh hơn một chút — nhưng vẫn có giới hạn! Xe máy nhanh nhất chỉ được 70 km/h trên đường lớn có dải phân cách, còn đường thường là 60 km/h. Ô tô con được chạy nhanh hơn: 80 – 90 km/h.',
     'rule'=>'Ngoài khu vực đông dân cư: xe ô tô con, ô tô chở người đến 28 chỗ — tối đa 90 km/h (đường đôi, đường một chiều từ hai làn trở lên) hoặc 80 km/h (đường hai chiều, một làn); xe mô tô — tối đa 70 km/h hoặc 60 km/h tương ứng.',
     'base'=>'Điều 7 Thông tư 38/2024/TT-BGTVT',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'td-03','icon'=>'🛵','who'=>'be',
     'title'=>'Xe gắn máy, xe máy điện: tối đa 40 km/h',
     'kid'=>'Xe gắn máy nhỏ (dưới 50 phân khối) và xe máy điện chỉ được chạy tối đa 40 km/h dù đi trên đường nào. Các anh chị học sinh cấp 3 đi loại xe này nhớ nằm lòng con số 40 nhé — chạy nhanh hơn vừa phạm luật vừa dễ ngã.',
     'rule'=>'Xe gắn máy (kể cả xe máy điện) và các loại xe tương tự: tốc độ tối đa 40 km/h trên mọi tuyến đường bộ (trừ đường cao tốc — loại xe này không được phép đi vào).',
     'base'=>'Điều 8 Thông tư 38/2024/TT-BGTVT',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'td-04','icon'=>'🛣️','who'=>'be',
     'title'=>'Đường cao tốc: không quá 120 km/h',
     'kid'=>'Cao tốc là đường chạy nhanh nhất Việt Nam — nhưng nhanh nhất cũng chỉ tới 120 km/h thôi. Trên cao tốc còn có cả tốc độ TỐI THIỂU nữa: chạy chậm quá cũng bị nhắc, vì xe rùa bò giữa đàn xe đang phóng nhanh sẽ gây nguy hiểm. Ngồi trên xe, con thử tìm biển ghi số tốc độ xem sao!',
     'rule'=>'Tốc độ khai thác tối đa trên đường cao tốc không vượt quá 120 km/h; người lái phải tuân thủ tốc độ tối đa và tối thiểu ghi trên biển báo hoặc vạch kẻ mặt đường của từng đoạn.',
     'base'=>'Điều 9 Thông tư 38/2024/TT-BGTVT',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'td-05','icon'=>'📐','who'=>'be',
     'title'=>'Khoảng cách an toàn giữa hai xe',
     'kid'=>'Xe phía sau phải giữ khoảng cách với xe phía trước — như khi xếp hàng mình không đứng dí sát bạn phía trước vậy. Chạy càng nhanh phải cách càng xa: nhanh 60 – 80 km/h thì cách ít nhất 35 mét (khoảng 7 chiếc ô tô nối đuôi), 80 – 100 km/h thì 55 mét. Trời mưa, sương mù còn phải cách xa hơn nữa!',
     'rule'=>'Khi mặt đường khô ráo, khoảng cách an toàn tối thiểu: tốc độ trên 60 đến 80 km/h — 35 m; trên 80 đến 100 km/h — 55 m; trên 100 đến 120 km/h — 70 m. Tốc độ dưới 60 km/h: chủ động giữ khoảng cách phù hợp. Trời mưa, sương mù, đường trơn: phải tăng thêm khoảng cách.',
     'base'=>'Điều 11 Thông tư 38/2024/TT-BGTVT',
     'fine'=>'',
     'fbase'=>''],
  ],
],

/* ══════════ NHÓM 15: TÌNH HUỐNG ĐẶC BIỆT ══════════ */
'tinh-huong-db' => [
  'label' => '🚨 Tình huống đặc biệt',
  'note'  => 'Đường sắt, xe ưu tiên, vòng xuyến, đèn pha, còi xe... — những tình huống ít gặp nhưng phải xử lý đúng. Quy định tại Luật Trật tự, an toàn giao thông đường bộ 2024.',
  'laws'  => [

    ['id'=>'th-01','icon'=>'🚆','who'=>'be',
     'title'=>'Qua đường ngang giao với đường sắt',
     'kid'=>'Tàu hỏa rất dài và rất nặng nên KHÔNG THỂ phanh gấp — muốn dừng phải trượt cả cây số! Vì vậy khi đèn đỏ nhấp nháy, chuông reo hoặc rào chắn đang hạ, mọi người phải dừng lại chờ, tuyệt đối không chui qua rào. Chỗ không có rào chắn, con phải dừng lại, nhìn kỹ hai phía, cách đường ray ít nhất 5 mét — bằng chiều dài một chiếc ô tô — rồi mới qua.',
     'rule'=>'Tại nơi đường bộ giao nhau cùng mức với đường sắt: khi có tín hiệu đèn, chuông báo hoặc chắn đã đóng, người tham gia giao thông phải dừng lại phía phần đường của mình và giữ khoảng cách an toàn; nơi không có rào chắn, phải quan sát hai phía, chỉ đi qua khi chắc chắn không có phương tiện đường sắt đang tới và phải cách ray gần nhất tối thiểu 5 mét.',
     'base'=>'Điều 24 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'th-02','icon'=>'🚒','who'=>'be',
     'title'=>'Thứ tự các xe ưu tiên — xe nào đi trước?',
     'kid'=>'Có những chiếc xe được cả đường phố nhường lối vì đang đi cứu người, dập lửa. Thứ tự ưu tiên như sau: 1️⃣ Xe chữa cháy đi làm nhiệm vụ, 2️⃣ Xe quân sự, công an, kiểm sát đi làm nhiệm vụ khẩn cấp, 3️⃣ Xe cứu thương đang cấp cứu, 4️⃣ Xe hộ đê, xe đi làm nhiệm vụ chống thiên tai dịch bệnh, 5️⃣ Đoàn xe tang. Con nghe tiếng còi ú...ú... là biết ngay có xe ưu tiên đang tới!',
     'rule'=>'Thứ tự xe ưu tiên: (1) xe chữa cháy của lực lượng PCCC đi làm nhiệm vụ; (2) xe quân sự, công an, kiểm sát đi làm nhiệm vụ khẩn cấp; đoàn xe có xe cảnh sát dẫn đường; (3) xe cứu thương đi làm nhiệm vụ cấp cứu; (4) xe hộ đê, xe làm nhiệm vụ ứng phó thiên tai, dịch bệnh hoặc tình trạng khẩn cấp; (5) đoàn xe tang.',
     'base'=>'Khoản 1, khoản 2 Điều 27 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Không nhường đường cho xe ưu tiên: xe máy 4.000.000 – 6.000.000 đồng · ô tô 6.000.000 – 8.000.000 đồng, trừ điểm GPLX',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'th-03','icon'=>'🌀','who'=>'be',
     'title'=>'Đi qua vòng xuyến (bùng binh) thế nào cho đúng?',
     'kid'=>'Vòng xuyến giống chiếc đu quay khổng lồ cho xe cộ: mọi xe đều đi vòng theo MỘT chiều quanh đảo tròn ở giữa. Luật chơi là: xe sắp vào vòng xuyến phải NHƯỜNG cho xe đang chạy trong vòng xuyến (xe tới từ bên trái mình) đi trước, rồi mới nhập vào. Vào rồi thì cứ đi theo vòng tới lối ra của mình.',
     'rule'=>'Khi đến gần nơi giao nhau, người điều khiển phương tiện phải giảm tốc độ; tại nơi giao nhau có vòng xuyến, phải nhường đường cho xe đi bên trái (xe đang lưu thông trong vòng xuyến).',
     'base'=>'Điều 22 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>''],

    ['id'=>'th-04','icon'=>'💡','who'=>'be',
     'title'=>'Đèn pha và đèn cốt — trong phố chỉ dùng đèn cốt',
     'kid'=>'Đèn xe có hai chế độ: đèn CỐT chiếu gần xuống mặt đường, đèn PHA chiếu xa nhưng rất chói. Trong phố mà bật pha thì người đi ngược chiều bị lóa mắt, không nhìn thấy đường — nguy hiểm lắm! Nên nhớ: trong khu đông dân cư và khi có xe ngược chiều — dùng đèn cốt.',
     'rule'=>'Trong khu vực đông dân cư (trừ đường không có hệ thống chiếu sáng) và khi tránh xe đi ngược chiều, người lái không được sử dụng đèn chiếu xa; phải chuyển sang đèn chiếu gần.',
     'base'=>'Điều 20 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Dùng đèn chiếu xa sai quy định: xe máy 200.000 – 400.000 đồng · ô tô 800.000 – 1.000.000 đồng',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'th-05','icon'=>'📣','who'=>'be',
     'title'=>'Bấm còi đúng lúc, đúng chỗ',
     'kid'=>'Còi xe là để BÁO HIỆU nhẹ nhàng, không phải để hối thúc hay trút giận. Ban đêm từ 22 giờ đến 5 giờ sáng trong khu dân cư không được bấm còi — mọi người còn đang ngủ mà! Con thấy đó, người lái xe văn minh rất ít khi phải bấm còi.',
     'rule'=>'Chỉ được sử dụng còi trong các trường hợp báo hiệu cần thiết; không sử dụng còi liên tục; không sử dụng còi trong thời gian từ 22 giờ đến 5 giờ trong khu đông dân cư, khu vực bệnh viện (trừ xe ưu tiên).',
     'base'=>'Điều 21 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'Xe máy: 200.000 – 400.000 đồng · Ô tô: 400.000 – 600.000 đồng cho mỗi hành vi',
     'fbase'=>'Điều 6 và Điều 7 Nghị định 168/2024/NĐ-CP'],

    ['id'=>'th-06','icon'=>'🚌','who'=>'be',
     'title'=>'An toàn khi đi xe buýt, xe đưa đón học sinh',
     'kid'=>'Chờ xe buýt phải đứng trên vỉa hè hoặc điểm chờ, lùi xa mép đường một bước chân. Xe DỪNG HẲN mới bước lên hoặc xuống, không chen lấn. Xuống xe xong đừng vội băng qua đường ngay trước hoặc sau đuôi xe — chiếc xe to đang che mất tầm nhìn của con và của các xe khác; hãy chờ xe buýt chạy đi rồi tìm chỗ qua đường an toàn.',
     'rule'=>'Hành khách phải lên, xuống xe tại điểm dừng, đỗ quy định khi xe đã dừng hẳn; không đu bám phương tiện. Xe đưa đón học sinh có quy định riêng về thiết bị an toàn và người quản lý học sinh trên xe.',
     'base'=>'Điều 30 và Điều 46 Luật Trật tự, an toàn giao thông đường bộ 2024',
     'fine'=>'',
     'fbase'=>''],
  ],
],


    ];
}

/* Tìm 1 điều luật theo mã id */
function law_by_id(string $id): ?array
{
    foreach (law_library() as $gKey => $g) {
        foreach ($g['laws'] as $l) {
            if ($l['id'] === $id) {
                $l['group']      = $gKey;
                $l['groupLabel'] = $g['label'];
                return $l;
            }
        }
    }
    return null;
}

/* Đếm tổng số điều luật trong thư viện */
function law_count(): int
{
    $n = 0;
    foreach (law_library() as $g) $n += count($g['laws']);
    return $n;
}

/* ---------------------------------------------------------------------
   BÉ HỎI VỀ LUẬT / MỨC PHẠT?  → tra thẳng thư viện luật, trả lời chính xác.
   Trả về null nếu câu hỏi không liên quan đến luật.
   --------------------------------------------------------------------- */
function ai_asks_about_law(string $msg): bool
{
    $t = ai_normalize($msg);
    foreach (['luat giao thong', 'luat gi', 'theo luat', 'dieu luat', 'phap luat',
              'quy dinh', 'nghi dinh', 'nghiem cam', 'vi pham',
              'phat bao nhieu', 'muc phat', 'bi phat', 'xu phat', 'phat tien',
              'co bi phat', 'phat khong', 'bao nhieu tien', 'nop phat',
              'bat buoc', 'co duoc phep', 'duoc phep khong',
              'bao nhieu tuoi', 'may tuoi', 'du tuoi', 'chua du tuoi',
              'giay phep lai xe', 'bang lai', 'tru diem'] as $kw) {
        if (str_contains($t, $kw)) return true;
    }
    return false;
}

/* Chấm điểm để tìm điều luật khớp nhất với câu hỏi của bé */
function ai_law_match(string $msg, int $k = 3): array
{
    $t = ai_normalize($msg);
    if ($t === '') return [];

    // Từ khoá riêng cho từng điều luật — giúp bắt đúng ý bé hỏi
    $extra = [
        'db-01' => ['via he', 'le duong', 'di bo o dau', 'khong co via he'],
        'db-02' => ['vach ke', 'sang duong', 'qua duong', 'cau vuot', 'ham di bo', 'ngua van'],
        'db-03' => ['gio tay', 'tin hieu bang tay', 'xin duong', 'vay tay'],
        'db-04' => ['den cho nguoi di bo', 'den tin hieu di bo'],
        'db-05' => ['dai phan cach', 'treo qua', 'leo qua'],
        'db-06' => ['du bam', 'bam xe', 'deo bam', 'di nho'],
        'db-07' => ['duong cao toc', 'cao toc'],
        'db-08' => ['vat cong kenh', 'mang vac'],
        'db-09' => ['duoi 7 tuoi', 'dat tay', 'em nho qua duong'],
        'xd-01' => ['di ben phai', 'phan duong xe dap'],
        'xd-02' => ['dan hang ngang', 'di song song', 'ba xe'],
        'xd-03' => ['dien thoai khi dap xe', 'vua di vua dung dien thoai'],
        'xd-04' => ['buong hai tay', 'buong ca hai tay', 'danh vong', 'duoi nhau', 'boc dau'],
        'xd-05' => ['xe dap dien', 'xe dap may', 'xe may dien', 'mu bao hiem xe dap'],
        'xd-06' => ['cho may nguoi', 'cho ba', 'cho hai nguoi', 'xe dap cho'],
        'xd-07' => ['che o', 'cam du', 'che du'],
        'xd-08' => ['duong cam', 'nguoc chieu'],
        'mu-01' => ['may tuoi phai doi mu', 'tre em doi mu', 'duoi 6 tuoi',
                    'phai doi mu', 'co phai doi mu', 'tuoi doi mu', 'doi mu bao hiem'],
        'mu-02' => ['cai quai', 'quai mu', 'khong cai quai'],
        'mu-03' => ['xe may cho may nguoi', 'cho ba nguoi', 'duoi 12 tuoi'],
        'mu-04' => ['tem cr', 'mu dat chuan', 'mu gia', 'mu luoi trai'],
        'mu-05' => ['ngoi sau the nao', 'tu the ngoi', 'dung tren yen'],
        'ot-01' => ['day an toan', 'day dai an toan', 'that day'],
        'ot-02' => ['ghe truoc', 'ngoi ghe truoc', 'tui khi', '1m35', '1 35', 'duoi 10 tuoi',
                    'ngoi dau', 'ngoi ghe nao', 'ghe sau', 'tre em ngoi o to'],
        'ot-03' => ['ghe tre em', 'thiet bi an toan', 'ghe an toan', 'dem nang'],
        'ot-04' => ['mo cua xe', 'xuong xe o to', 'len xuong xe'],
        'ot-05' => ['tho dau', 'tho tay', 'cua so o to'],
        'db-tt' => ['thu tu uu tien', 'nghe ai', 'canh sat hay den', 'uu tien cao nhat'],
        'db-den'=> ['den do', 'den vang', 'den xanh', 'den giao thong', 'vuot den do'],
        'db-bien'=> ['nhom bien bao', 'may nhom bien', 'bien tron', 'bien tam giac'],
        'db-uut'=> ['xe uu tien', 'cuu thuong', 'cuu hoa', 'nhuong duong'],
        'tu-01' => ['16 tuoi', 'xe gan may', '50 cm3', 'may tuoi duoc di xe',
                    'bao nhieu tuoi', 'may tuoi', 'lai xe may', 'di xe may', 'chay xe may'],
        'tu-02' => ['18 tuoi', 'bang lai', 'giay phep lai xe', 'xe mo to',
                    'bao nhieu tuoi', 'lai o to', 'lai xe o to'],
        'tu-03' => ['chua du tuoi', '14 tuoi', 'hoc sinh lai xe', 'tre em lai xe'],
        'tu-04' => ['giao xe', 'cho muon xe', 'ba me giao xe'],
        'nc-01' => ['da bong', 'choi duoi long duong', 'tha dieu', 'chay ra duong'],
        'nc-02' => ['nem da', 'nem gach', 'nem vao xe'],
        'nc-03' => ['nong do con', 'ruou bia', 'uong bia lai xe'],
        'nc-04' => ['dua xe', 'lang lach', 'boc dau'],
        'nc-05' => ['bam coi', 'net po', 'ru ga', 'coi to'],
        'nc-06' => ['bo tron', 'gay tai nan', 'cuu giup', '115'],
        'mp-01' => ['vuot den do phat', 'phat den do', 'vuot den do'],
        'mp-02' => ['phat khong doi mu', 'khong doi mu'],
        'mp-03' => ['phat nong do con'],
        'mp-04' => ['phat dien thoai'],
        'mp-05' => ['phat day an toan'],
        'mp-06' => ['qua toc do', 'chay qua toc do', 'phat toc do', 'toc do'],
        'mp-07' => ['di nguoc chieu phat', 'len via he phat'],
        'mp-08' => ['phat giao xe'],
        'mp-09' => ['phat khong nhuong xe uu tien'],
        'mp-10' => ['tru diem', 'phuc hoi diem', '12 diem',
                    'tru diem bang lai', 'tru diem giay phep', 'diem giay phep'],
    ];

    // Từ xuất hiện ở quá nhiều câu → không dùng để chấm điểm,
    // nếu không "khong", "phai", "luat"... sẽ khớp bừa vào mọi điều luật.
    $stop = ['khong', 'phai', 'duoc', 'nguoi', 'tren', 'trong', 'cung', 'hoac',
             'nhung', 'theo', 'viec', 'tuyet', 'luon', 'nhau', 'minh', 'luat',
             'giao', 'thong', 'quy', 'dinh'];

    // Bé hỏi chung về cả một NHÓM ("luật cho người đi bộ", "luật xe đạp"...)
    $groupKw = [
        'di-bo'      => ['nguoi di bo', 'di bo', 'sang duong', 'qua duong'],
        'xe-dap'     => ['xe dap', 'dap xe'],
        'mu-xe-may'  => ['mu bao hiem', 'xe may', 'ngoi sau xe'],
        'o-to'       => ['o to', 'oto', 'xe hoi'],
        'den-bien'   => ['den tin hieu', 'den giao thong', 'bien bao', 'canh sat'],
        'do-tuoi'    => ['do tuoi', 'bao nhieu tuoi', 'may tuoi', 'lai xe'],
        'nghiem-cam' => ['nghiem cam', 'bi cam', 'luat cam', 'cam gi', 'khong duoc lam'],
        'muc-phat'   => ['muc phat', 'bang phat', 'phat bao nhieu', 'bi phat'],
        'muc-phat-full' => ['bang phat day du', 'tra cuu phat', 'muc phat day du',
                           'bang tra cuu phat', 'tong hop muc phat'],
    ];

    $scores = [];
    foreach (law_library() as $gKey => $g) {

        // Câu hỏi có nhắc tới tên nhóm → cả nhóm được cộng một chút điểm nền
        $gBonus = 0;
        foreach ($groupKw[$gKey] ?? [] as $kw) {
            if (str_contains($t, $kw)) { $gBonus = 6; break; }
        }

        foreach ($g['laws'] as $l) {
            $s = $gBonus;

            // (1) Trùng NGUYÊN tiêu đề — xảy ra khi bé bấm nút
            //     "Hỏi AI Gia sư về điều luật này". Đây là tín hiệu chắc chắn nhất.
            $tieuDe = ai_normalize($l['title']);
            if (mb_strlen($tieuDe) >= 10 && str_contains($t, $tieuDe)) {
                $s += 200 + mb_strlen($tieuDe);
            }

            // (2) Từ khoá riêng của từng điều — tin cậy cao
            foreach ($extra[$l['id']] ?? [] as $kw) {
                if (str_contains($t, $kw)) $s += mb_strlen($kw) * 2 + 12;
            }

            // (3) Trùng nhiều từ trong tiêu đề
            $tuTieuDe = [];
            foreach (explode(' ', $tieuDe) as $w) {
                if (mb_strlen($w) >= 3 && !in_array($w, $stop, true)) $tuTieuDe[] = $w;
            }
            $trung = 0;
            foreach ($tuTieuDe as $w) {
                if (!str_contains($t, $w)) continue;
                $trung++;
                if (mb_strlen($w) >= 5) $s += 4;   // từ dài, cụ thể → đáng tin hơn
            }
            // Chỉ xét tỉ lệ khi tiêu đề đủ dài, tránh tiêu đề 1-2 từ ăn điểm oan
            if (count($tuTieuDe) >= 3) {
                $tyLe = $trung / count($tuTieuDe);
                if     ($tyLe >= 0.75) $s += 40;
                elseif ($tyLe >= 0.5)  $s += 12;
            }

            if ($s > 0) $scores[$l['id']] = $s;
        }
    }

    arsort($scores);
    if ($scores) {
        $top = reset($scores);
        $scores = array_filter($scores, fn($v) => $v >= $top * 0.45);
    }
    return array_slice($scores, 0, $k, true);
}

/* Soạn câu trả lời về LUẬT cho bé (dùng khi chạy offline, không có Gemini) */
function ai_law_reply(string $msg): ?string
{
    // Chỉ trả lời bằng LUẬT khi bé thật sự hỏi về luật / mức phạt / độ tuổi.
    // Câu hỏi thường ("đèn vàng nghĩa là gì?") vẫn để kho bài học cũ trả lời,
    // vì bài học cũ viết gần gũi và có mẹo nhớ cho bé hơn.
    if (!ai_asks_about_law($msg)) return null;

    $hits = ai_law_match($msg, 2);
    if (!$hits) return null;

    $ids  = array_keys($hits);
    $main = law_by_id($ids[0]);
    if (!$main) return null;

    $out  = "📜 **{$main['icon']} {$main['title']}**\n\n";
    $out .= $main['kid'] . "\n\n";
    $out .= "📘 **Luật quy định:** " . $main['rule'] . "\n\n";
    $out .= "⚖️ **Căn cứ:** _" . $main['base'] . "_\n\n";

    if ($main['fine'] !== '') {
        $out .= "💰 **Mức phạt:** " . $main['fine'] . "\n";
        if ($main['fbase'] !== '') $out .= "_(" . $main['fbase'] . ")_\n";
        $out .= "\n";
    }

    // Điều luật liên quan
    if (count($ids) > 1) {
        $second = law_by_id($ids[1]);
        if ($second) {
            $out .= "🔎 **Điều liên quan:** {$second['icon']} {$second['title']}\n"
                  . "_" . mb_substr(strip_tags($second['rule']), 0, 180) . "..._\n\n";
        }
    }

    $out .= "💡 Con mở mục **📜 Luật giao thông** (nút trên góc phải) để xem đầy đủ "
          . law_count() . " điều luật nhé!\n\n"
          . "❓ Đố con: theo con, vì sao người ta lại đặt ra điều luật này? 🤔";

    return $out;
}

/* =====================================================================
   [NÂNG CẤP V2 — TOÀN BỘ LÀ CODE THÊM MỚI, KHÔNG SỬA CODE CŨ]
   BỘ NÃO LUẬT THÔNG MINH (ai2_*)
   ---------------------------------------------------------------------
   Khả năng mới của AI Gia sư:
   • Trả lời CHÍNH XÁC mức phạt theo từng loại xe (xe máy / ô tô / xe đạp
     / người đi bộ) — kèm số điểm GPLX bị trừ và căn cứ pháp lý.
   • Hiểu câu hỏi có CON SỐ: "chạy quá tốc độ 25km bị phạt bao nhiêu?",
     "chạy 80 trong đường 60 thì sao?" → tự tính đúng khung phạt.
   • Trả lời trọn bộ 3 mức phạt NỒNG ĐỘ CỒN cho từng loại xe.
   • Giải thích hệ thống 12 ĐIỂM giấy phép lái xe (trừ, phục hồi).
   • SO SÁNH mức phạt xe máy với ô tô trong cùng một lỗi.
   • Trả lời tốc độ tối đa và khoảng cách an toàn theo Thông tư 38/2024.
   Cách hoạt động: ai2_law_reply() được gọi TRƯỚC các tầng cũ; nếu câu
   hỏi không thuộc phạm vi thì trả về null → mọi thứ chạy y như cũ.
   ===================================================================== */

/* Bảng phạt máy-đọc-được: mỗi lỗi có từ khóa nhận diện (không dấu),
   mức phạt theo từng loại phương tiện và căn cứ pháp lý. */
function ai2_fine_table(): array
{
    return [
      'vuot-den-do' => [
        'ten' => 'Vượt đèn đỏ (không chấp hành đèn tín hiệu)', 'icon' => '🚦',
        'kw'  => ['vuot den do', 'vuot den', 'khong chap hanh den', 'khong dung den do', 'but den do', 'thong chot den'],
        'muc' => ['xe may' => '4.000.000 – 6.000.000 đồng, trừ 4 điểm GPLX (gây tai nạn: 10 – 14 triệu đồng, trừ 10 điểm)',
                  'o to'   => '18.000.000 – 20.000.000 đồng, trừ 4 điểm GPLX (gây tai nạn: 20 – 22 triệu đồng, trừ 10 điểm)',
                  'xe dap' => '150.000 – 250.000 đồng'],
        'canci' => 'Điều 6, Điều 7, Điều 9 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Đèn đỏ là mệnh lệnh DỪNG LẠI với tất cả mọi người — kể cả khi đường vắng. Chờ vài chục giây luôn rẻ hơn một vụ tai nạn.'],

      'mu-bao-hiem' => [
        'ten' => 'Không đội mũ bảo hiểm / không cài quai đúng cách', 'icon' => '⛑️',
        'kw'  => ['khong doi mu', 'mu bao hiem', 'doi mu', 'cai quai mu', 'quen mu'],
        'muc' => ['xe may' => '400.000 – 600.000 đồng cho MỖI người vi phạm — cả người lái lẫn người ngồi sau (trẻ từ đủ 6 tuổi trở lên bắt buộc đội)'],
        'canci' => 'Điều 7 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Đội mũ xong nhớ CÀI QUAI vừa khít — mũ không cài quai thì khi ngã sẽ văng ra, coi như không đội. Đi xe đạp điện cũng phải đội mũ nhé!'],

      'dien-thoai' => [
        'ten' => 'Dùng điện thoại khi đang lái xe', 'icon' => '📱',
        'kw'  => ['dien thoai', 'nghe dien thoai', 'bam dien thoai', 'nhan tin khi lai', 'luot dien thoai', 'xem dien thoai'],
        'muc' => ['xe may' => '800.000 – 1.000.000 đồng, trừ 4 điểm GPLX (gây tai nạn: 10 – 14 triệu đồng, trừ 10 điểm)',
                  'o to'   => '4.000.000 – 6.000.000 đồng, trừ 4 điểm GPLX (gây tai nạn: 20 – 22 triệu đồng, trừ 10 điểm)',
                  'xe dap' => '100.000 – 200.000 đồng'],
        'canci' => 'Điều 6, Điều 7, Điều 9 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Cúi nhìn điện thoại 3 giây ở tốc độ 40 km/h là xe tự đi hơn 30 mét không ai điều khiển. Cần dùng điện thoại thì dừng xe vào nơi an toàn trước.'],

      'nguoc-chieu' => [
        'ten' => 'Đi ngược chiều', 'icon' => '↩️',
        'kw'  => ['nguoc chieu', 'di nguoc', 'chay nguoc', 'duong mot chieu'],
        'muc' => ['xe may' => '4.000.000 – 6.000.000 đồng, trừ 2 điểm GPLX (gây tai nạn: 10 – 14 triệu đồng, trừ 10 điểm)',
                  'o to'   => '18.000.000 – 20.000.000 đồng, trừ 4 điểm GPLX · Ngược chiều hoặc LÙI XE trên cao tốc: 30 – 40 triệu đồng',
                  'xe dap' => '150.000 – 250.000 đồng'],
        'canci' => 'Điều 6, Điều 7, Điều 9 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Đi ngược chiều để tiết kiệm vài chục mét nhưng khiến mọi xe đối diện đều bất ngờ — đi vòng đúng chiều luôn là đường ngắn nhất về nhà an toàn.'],

      'via-he' => [
        'ten' => 'Điều khiển xe chạy trên vỉa hè', 'icon' => '🛑',
        'kw'  => ['len via he', 'leo via he', 'chay tren via he', 'phong len via he', 'xe may via he', 'o to via he', 'xe len via he', 'lai xe via he', 'di xe tren via he'],
        'muc' => ['xe may' => '4.000.000 – 6.000.000 đồng, trừ 2 điểm GPLX',
                  'o to'   => '6.000.000 – 8.000.000 đồng'],
        'canci' => 'Điều 6, Điều 7 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Vỉa hè là phần đường riêng của người đi bộ — trong đó có các bạn nhỏ đang đi học. Kẹt xe mấy cũng không được leo lề!'],

      'qua-toc-do' => [
        'ten' => 'Chạy quá tốc độ quy định', 'icon' => '🚀',
        'kw'  => ['qua toc do', 'vuot toc do', 'chay qua toc', 'phong nhanh', 'chay nhanh qua'],
        'muc' => ['xe may' => 'Từ 400.000 đồng đến 8.000.000 đồng tùy mức vượt (xem chi tiết từng mức ở nhóm 🏍️ Xe máy: phạt chi tiết)',
                  'o to'   => 'Từ 800.000 đồng đến 14.000.000 đồng tùy mức vượt (xem chi tiết từng mức ở nhóm 🚗 Ô tô: phạt chi tiết)'],
        'canci' => 'Điều 6, Điều 7 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Con hỏi kèm con số cụ thể (ví dụ: quá tốc độ 25 km/h) là mình tính đúng khung phạt cho con luôn nha!'],

      'day-an-toan' => [
        'ten' => 'Không thắt dây đai an toàn trên ô tô', 'icon' => '🔗',
        'kw'  => ['day an toan', 'that day', 'day dai an toan', 'khong that day'],
        'muc' => ['o to' => 'Người lái không thắt dây hoặc chở người không thắt dây: 800.000 – 1.000.000 đồng · Người được chở không thắt dây: 350.000 – 400.000 đồng'],
        'canci' => 'Điều 6 và Điều 12 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'LÊN XE — CÀI DÂY — NGHE TÁCH! Ngồi ghế sau cũng phải thắt dây y như ghế trước nhé.'],

      'khong-gplx' => [
        'ten' => 'Không có giấy phép lái xe', 'icon' => '🚫',
        'kw'  => ['khong co bang lai', 'khong co giay phep', 'chua co bang', 'khong bang lai', 'lai xe khong bang'],
        'muc' => ['xe may' => '2.000.000 – 4.000.000 đồng', 'o to' => '18.000.000 – 20.000.000 đồng'],
        'canci' => 'Điều 18 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Khác với lỗi QUÊN MANG bằng (chỉ 100.000 – 400.000 đồng), KHÔNG CÓ bằng bị phạt rất nặng — vì người chưa học lái là mối nguy cho cả đường phố.'],

      'quen-gplx' => [
        'ten' => 'Có bằng lái nhưng quên mang theo', 'icon' => '🫥',
        'kw'  => ['quen bang lai', 'khong mang bang', 'quen giay phep', 'khong mang giay phep', 'quen mang giay to', 'quen mang bang', 'quen mang giay phep', 'bo quen bang', 'quen bang o nha'],
        'muc' => ['xe may' => '100.000 – 200.000 đồng', 'o to' => '300.000 – 400.000 đồng'],
        'canci' => 'Điều 18 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Mẹo của các bác tài: tích hợp giấy tờ vào VNeID trên điện thoại — xuất trình bản điện tử có giá trị như bản giấy, khỏi lo quên.'],

      'bao-hiem-xe' => [
        'ten' => 'Không có bảo hiểm bắt buộc trách nhiệm dân sự', 'icon' => '🛡️',
        'kw'  => ['bao hiem xe', 'bao hiem bat buoc', 'khong co bao hiem', 'bao hiem trach nhiem'],
        'muc' => ['xe may' => '100.000 – 200.000 đồng', 'o to' => '400.000 – 600.000 đồng'],
        'canci' => 'Điều 18 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Bảo hiểm bắt buộc giống phao cứu sinh về tiền bạc — chẳng may va chạm sẽ có bảo hiểm cùng đền bù cho người bị nạn.'],

      'lang-lach' => [
        'ten' => 'Lạng lách, đánh võng, bốc đầu, rượt đuổi', 'icon' => '🏍️',
        'kw'  => ['lang lach', 'danh vong', 'boc dau', 'dua xe', 'ruot duoi', 'net po', 'ru ga', 'bieu dien xe'],
        'muc' => ['xe may' => '8.000.000 – 10.000.000 đồng, trừ 10 điểm GPLX · Tái phạm hoặc gây tai nạn: có thể bị TỊCH THU XE',
                  'o to'   => '40.000.000 – 50.000.000 đồng, kèm hình thức xử lý bổ sung rất nặng'],
        'canci' => 'Điều 6, Điều 7 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Đường phố không phải phim hành động. Bốc đầu để khoe với bạn bè có thể đổi bằng cả chiếc xe lẫn sự an toàn của người khác.'],

      'giao-xe' => [
        'ten' => 'Giao xe cho người chưa đủ điều kiện điều khiển', 'icon' => '🔑',
        'kw'  => ['giao xe', 'cho muon xe', 'dua xe cho con', 'giao xe cho hoc sinh', 'cho con chay xe', 'muon xe', 'cho em muon', 'cho con muon', 'ba cho muon', 'me cho muon'],
        'muc' => ['xe may' => 'Chủ xe là cá nhân: 8.000.000 – 10.000.000 đồng (tổ chức: gấp đôi)',
                  'o to'   => 'Chủ xe là cá nhân: 28.000.000 – 30.000.000 đồng (tổ chức: gấp đôi)'],
        'canci' => 'Điều 32 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Ba mẹ giao xe máy cho con chưa đủ tuổi thì chính BA MẸ bị phạt gần chục triệu đồng — thương con là chờ con đủ tuổi và có bằng lái.'],

      'kep-ba' => [
        'ten' => 'Xe máy chở quá số người quy định', 'icon' => '3️⃣',
        'kw'  => ['kep ba', 'kep 3', 'cho ba nguoi', 'deo ba', 'cho qua nguoi', 'cho 3 nguoi', 'ngoi ba nguoi'],
        'muc' => ['xe may' => 'Chở thêm 02 người trên xe: 400.000 – 600.000 đồng · Chở thêm từ 03 người trở lên: 600.000 – 800.000 đồng (không tính trường hợp chở người bệnh đi cấp cứu, trẻ em dưới 12 tuổi, người khuyết tật)'],
        'canci' => 'Điều 7 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Xe máy sinh ra cho tối đa 2 người lớn. Kẹp ba làm xe mất thăng bằng, phanh yếu hẳn đi — cả nhóm cùng gặp nguy.'],

      'dung-do' => [
        'ten' => 'Dừng, đỗ xe không đúng quy định', 'icon' => '🅿️',
        'kw'  => ['dung do sai', 'do xe sai', 'dau xe sai', 'dung xe sai', 'do xe long duong', 'dung do khong dung'],
        'muc' => ['xe may' => '400.000 – 600.000 đồng (dừng đỗ trên cầu: 600.000 – 800.000 đồng)',
                  'o to'   => '800.000 – 1.000.000 đồng · Dừng, đỗ sai trên đường CAO TỐC: 12 – 14 triệu đồng, trừ 6 điểm GPLX'],
        'canci' => 'Điều 6, Điều 7 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Đỗ xe che khuất lối đi, che biển báo hay chắn cổng trường đều làm khổ người khác — người văn minh luôn tìm đúng chỗ được phép đỗ.'],

      'xe-uu-tien' => [
        'ten' => 'Không nhường đường cho xe ưu tiên', 'icon' => '🚒',
        'kw'  => ['khong nhuong xe uu tien', 'xe uu tien', 'nhuong xe cuu thuong', 'xe cuu hoa', 'xe chua chay', 'xe cuu thuong'],
        'muc' => ['xe may' => '4.000.000 – 6.000.000 đồng', 'o to' => '6.000.000 – 8.000.000 đồng, trừ 4 điểm GPLX'],
        'canci' => 'Điều 6, Điều 7 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Nghe còi ú...ú... là dạt sang phải nhường đường ngay — phía sau tiếng còi đó có thể là một người đang chờ được cứu.'],

      'mo-cua' => [
        'ten' => 'Mở cửa ô tô không bảo đảm an toàn', 'icon' => '🚪',
        'kw'  => ['mo cua xe', 'mo cua o to', 'mo cua gay tai nan'],
        'muc' => ['o to' => 'Mở cửa không bảo đảm an toàn: 400.000 – 600.000 đồng · Mở cửa GÂY TAI NẠN: 20 – 22 triệu đồng, trừ 10 điểm GPLX'],
        'canci' => 'Điều 6 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Con hãy tập MỞ CỬA KIỂU HÀ LAN: dùng tay xa cửa để mở — người tự xoay lại, mắt nhìn được xe phía sau rồi mới hé cửa từ từ.'],

      'den-toi' => [
        'ten' => 'Không bật đèn khi trời tối / dùng đèn pha sai chỗ', 'icon' => '🔦',
        'kw'  => ['khong bat den', 'quen bat den', 'den chieu sang', 'den pha', 'bat pha trong pho', 'den cot'],
        'muc' => ['xe may' => 'Không bật đèn từ 18h đến 6h sáng: 200.000 – 400.000 đồng · Dùng đèn chiếu xa (pha) sai quy định: 200.000 – 400.000 đồng',
                  'o to'   => 'Không bật đèn hoặc dùng đèn chiếu xa sai quy định: 800.000 – 1.000.000 đồng'],
        'canci' => 'Điều 6, Điều 7 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Bật đèn không chỉ để nhìn đường mà để NGƯỜI KHÁC nhìn thấy mình. Trong phố nhớ dùng đèn cốt kẻo làm lóa mắt người đối diện.'],

      'vao-cao-toc' => [
        'ten' => 'Đi vào đường cao tốc trái phép', 'icon' => '🛣️',
        'kw'  => ['vao cao toc', 'di vao duong cao toc', 'len cao toc', 'xe may cao toc', 'xe may di cao toc', 'xe dap cao toc', 'di bo cao toc', 'vao duong cao toc'],
        'muc' => ['xe may' => '4.000.000 – 6.000.000 đồng, trừ 4 điểm GPLX',
                  'xe dap' => '300.000 – 600.000 đồng',
                  'di bo'  => '400.000 – 600.000 đồng'],
        'canci' => 'Điều 7, Điều 9, Điều 10 Nghị định 168/2024/NĐ-CP',
        'meo'   => 'Cao tốc chỉ dành cho ô tô. Xe chạy tới 120 km/h — người đi bộ, xe đạp, xe máy vào đó thì tài xế có thấy cũng không kịp phanh.'],
    ];
}

/* Nhận diện loại phương tiện được nhắc tới trong câu hỏi (đã normalize) */
function ai2_detect_vehicle(string $t): ?string
{
    $map = [
        'o to'   => ['o to', 'oto', 'xe hoi', 'xe con', 'xe o to', 'xe tai', 'xe khach', 'xe bon banh'],
        'xe may' => ['xe may', 'xe mo to', 'mo to', 'xe ga', 'xe so', 'xe gan may', 'xe may dien', 'honda', 'xe hai banh'],
        'xe dap' => ['xe dap', 'dap xe', 'xe dap dien'],
        'di bo'  => ['di bo', 'nguoi di bo'],
    ];
    foreach ($map as $veh => $kws) {
        foreach ($kws as $kw) {
            if (str_contains($t, $kw)) return $veh;
        }
    }
    return null;
}

/* Câu hỏi có ý muốn biết mức phạt / hậu quả pháp lý không? */
function ai2_wants_fine(string $t): bool
{
    foreach (['phat bao nhieu', 'muc phat', 'bi phat', 'xu phat', 'phat tien',
              'phat khong', 'co bi phat', 'bao nhieu tien', 'nop phat', 'bi gi khong',
              'tru diem', 'tru bao nhieu diem', 'bi xu ly', 'phat the nao', 'phat nhu the nao',
              'phat nang', 'tien phat', 'bi lam sao', 'co sao khong', 'hau qua'] as $kw) {
        if (str_contains($t, $kw)) return true;
    }
    // Chỉ 1 chữ "phat" đứng riêng cũng tính, ví dụ "vượt đèn đỏ phạt sao"
    return (bool) preg_match('/\bphat\b/', $t);
}

/* Tìm lỗi vi phạm khớp nhất trong bảng phạt (trả về key hoặc null) */
function ai2_find_violation(string $t): ?string
{
    $best = null; $bestScore = 0;
    foreach (ai2_fine_table() as $key => $row) {
        $s = 0;
        foreach ($row['kw'] as $kw) {
            if (str_contains($t, $kw)) $s += mb_strlen($kw) * 2 + 10;
            elseif (function_exists('ai_fuzzy_contains') && mb_strlen($kw) >= 8
                    && ai_fuzzy_contains($t, $kw)) $s += mb_strlen($kw) + 4;
        }
        if ($s > $bestScore) { $bestScore = $s; $best = $key; }
    }
    return $bestScore >= 18 ? $best : null;
}

/* Tên hiển thị + icon cho từng loại phương tiện */
function ai2_veh_label(string $veh): string
{
    return ['xe may' => '🏍️ Xe máy', 'o to' => '🚗 Ô tô',
            'xe dap' => '🚲 Xe đạp', 'di bo' => '🚶 Người đi bộ'][$veh] ?? $veh;
}

/* ---------- TRẢ LỜI: mức phạt của một lỗi (theo loại xe nếu bé nêu) ---------- */
function ai2_fine_reply(string $t): ?string
{
    if (!ai2_wants_fine($t)) return null;
    $key = ai2_find_violation($t);
    if ($key === null) return null;

    $row = ai2_fine_table()[$key];
    $veh = ai2_detect_vehicle($t);

    $out = "💰 **Mức phạt: {$row['icon']} {$row['ten']}**\n";
    $out .= "_(theo Nghị định 168/2024/NĐ-CP, áp dụng từ 01/01/2025)_\n\n";

    if ($veh !== null && isset($row['muc'][$veh])) {
        $out .= "👉 **" . ai2_veh_label($veh) . ":** {$row['muc'][$veh]}\n\n";
        $khac = array_diff_key($row['muc'], [$veh => 1]);
        if ($khac) {
            $out .= "Các loại xe khác:\n";
            foreach ($khac as $v => $m) $out .= "• " . ai2_veh_label($v) . ": {$m}\n";
            $out .= "\n";
        }
    } else {
        foreach ($row['muc'] as $v => $m) $out .= "• **" . ai2_veh_label($v) . ":** {$m}\n";
        $out .= "\n";
    }

    $out .= "⚖️ **Căn cứ:** _{$row['canci']}_\n\n";
    $out .= "🧒 **Điều con cần nhớ:** {$row['meo']}\n\n";
    $out .= "💡 Con có thể hỏi thêm: _\"so sánh mức phạt xe máy và ô tô\"_, "
          . "hoặc mở mục **📜 Luật giao thông** để xem đầy đủ " . law_count() . " điều luật nhé!\n\n"
          . "❓ Đố con: vì sao cùng một lỗi mà ô tô thường bị phạt nặng hơn xe máy? 🤔";
    return $out;
}

/* ---------- TRẢ LỜI: nồng độ cồn — trọn bộ 3 mức theo loại xe ---------- */
function ai2_alcohol_reply(string $t): ?string
{
    $hit = false;
    foreach (['nong do con', 'ruou bia', 'uong ruou', 'uong bia', 'say ruou', 'say xin',
              'uong say', 'co con', 'thoi nong do', 'do con'] as $kw) {
        if (str_contains($t, $kw)) { $hit = true; break; }
    }
    if (!$hit) return null;
    // Chỉ trả lời khi câu hỏi gắn với việc LÁI XE hoặc hỏi mức phạt
    if (!ai2_wants_fine($t) && !str_contains($t, 'lai xe') && !str_contains($t, 'chay xe')
        && !str_contains($t, 'dieu khien') && ai2_detect_vehicle($t) === null) return null;

    $veh = ai2_detect_vehicle($t);

    $bang = [
      'xe may' => "🏍️ **Xe máy** _(Điều 7 NĐ 168/2024)_\n"
                . "• Mức 1 (≤ 50 mg/100 ml máu hoặc ≤ 0,25 mg/lít khí thở): **2 – 3 triệu đồng**, trừ 4 điểm GPLX\n"
                . "• Mức 2 (trên 50 – 80 mg hoặc 0,25 – 0,4 mg/lít): **6 – 8 triệu đồng**, trừ 10 điểm GPLX\n"
                . "• Mức 3 (trên 80 mg hoặc trên 0,4 mg/lít): **8 – 10 triệu đồng**, tước GPLX 22 – 24 tháng\n",
      'o to'   => "🚗 **Ô tô** _(Điều 6 NĐ 168/2024)_\n"
                . "• Mức 1: **6 – 8 triệu đồng**, trừ 4 điểm GPLX\n"
                . "• Mức 2: **18 – 20 triệu đồng**, trừ 10 điểm GPLX\n"
                . "• Mức 3: **30 – 40 triệu đồng**, tước GPLX 22 – 24 tháng\n",
      'xe dap' => "🚲 **Xe đạp, xe đạp điện** _(Điều 9 NĐ 168/2024)_\n"
                . "• Phạt từ **100.000 đến 600.000 đồng** tùy mức nồng độ cồn\n",
    ];

    $out = "🍺 **Mức phạt NỒNG ĐỘ CỒN khi lái xe** _(Nghị định 168/2024/NĐ-CP)_\n\n"
         . "Luật Việt Nam cấm TUYỆT ĐỐI: đã có nồng độ cồn trong người thì không được lái bất kỳ xe gì "
         . "_(khoản 1 Điều 9 Luật Trật tự, an toàn giao thông đường bộ 2024)_.\n\n";

    if ($veh !== null && isset($bang[$veh])) {
        $out .= $bang[$veh] . "\n";
        foreach ($bang as $v => $b) if ($v !== $veh) $out .= $b . "\n";
    } else {
        foreach ($bang as $b) $out .= $b . "\n";
    }

    $out .= "⚠️ Không chấp hành yêu cầu thổi nồng độ cồn của CSGT: bị phạt bằng **mức 3** (mức cao nhất).\n\n"
          . "🧒 **Điều con có thể làm:** nếu ba hoặc người thân vừa uống rượu bia mà định lái xe, "
          . "con hãy dũng cảm nói: _\"Mình gọi taxi hoặc nhờ người khác chở cho an toàn nha!\"_ — "
          . "một câu nói của con có thể cứu cả nhà đó.\n\n"
          . "❓ Đố con: vì sao người uống rượu bia lại lái xe kém an toàn hơn bình thường? 🤔";
    return $out;
}

/* ---------- TRẢ LỜI: tốc độ — giới hạn tối đa & tính khung phạt theo số km vượt ---------- */
function ai2_speed_reply(string $t): ?string
{
    /* (a) Hỏi về TỐC ĐỘ TỐI ĐA / GIỚI HẠN */
    $hoiGioiHan = false;
    foreach (['toc do toi da', 'gioi han toc do', 'duoc chay bao nhieu', 'chay toi da',
              'duoc phep chay', 'toc do cho phep', 'chay nhanh nhat', 'toi da bao nhieu km'] as $kw) {
        if (str_contains($t, $kw)) { $hoiGioiHan = true; break; }
    }
    if ($hoiGioiHan) {
        $out = "🚀 **Tốc độ tối đa theo Thông tư 38/2024/TT-BGTVT** _(áp dụng từ 01/01/2025)_\n\n"
             . "🏘️ **Trong khu đông dân cư:**\n"
             . "• Đường đôi / một chiều có từ 2 làn trở lên: **60 km/h**\n"
             . "• Đường hai chiều / một chiều có 1 làn: **50 km/h**\n\n"
             . "🌄 **Ngoài khu đông dân cư:**\n"
             . "• Ô tô con: **90 km/h** (đường đôi) hoặc **80 km/h** (đường hai chiều)\n"
             . "• Xe máy: **70 km/h** (đường đôi) hoặc **60 km/h** (đường hai chiều)\n\n"
             . "🛵 **Xe gắn máy (dưới 50 cm3) và xe máy điện:** tối đa **40 km/h** trên mọi đường\n"
             . "🛣️ **Đường cao tốc:** không quá **120 km/h**, theo biển báo từng đoạn\n\n"
             . "📐 Khoảng cách an toàn khi đường khô: chạy 60 – 80 km/h phải cách xe trước ít nhất **35 m**; "
             . "80 – 100 km/h: **55 m**; 100 – 120 km/h: **70 m** _(Điều 11 Thông tư 38/2024)_.\n\n"
             . "💡 Nhớ nhé: con số trên **biển báo** tại chỗ luôn được ưu tiên trước các mức chung này!\n\n"
             . "❓ Đố con: vì sao trong khu dân cư xe phải chạy chậm hơn ngoài quốc lộ? 🤔";
        return $out;
    }

    /* (a2) Hỏi riêng về KHOẢNG CÁCH AN TOÀN giữa hai xe */
    foreach (['khoang cach an toan', 'giu khoang cach', 'cach xe truoc', 'cach xe phia truoc',
              'khoang cach giua hai xe', 'khoang cach voi xe'] as $kw) {
        if (str_contains($t, $kw)) {
            return "📐 **Khoảng cách an toàn giữa hai xe** _(Điều 11 Thông tư 38/2024/TT-BGTVT)_\n\n"
                 . "Xe phía sau phải cách xe phía trước một đoạn đủ xa để kịp phanh — giống khi xếp hàng "
                 . "mình không đứng dí sát bạn phía trước vậy đó con. Khi mặt đường KHÔ RÁO:\n\n"
                 . "• Chạy trên 60 đến 80 km/h → cách ít nhất **35 mét** (khoảng 7 chiếc ô tô nối đuôi)\n"
                 . "• Chạy trên 80 đến 100 km/h → cách ít nhất **55 mét**\n"
                 . "• Chạy trên 100 đến 120 km/h → cách ít nhất **70 mét**\n"
                 . "• Chạy dưới 60 km/h → chủ động giữ khoảng cách phù hợp\n\n"
                 . "🌧️ Trời mưa, sương mù, đường trơn: phải nới khoảng cách XA HƠN các mức trên.\n\n"
                 . "🧒 **Mẹo cho con:** ngồi trên xe, con thử chọn một cột mốc bên đường — xe trước vừa đi qua, "
                 . "con đếm chậm hai giây, xe mình mới tới cột đó là khoảng cách đang ổn.\n\n"
                 . "❓ Đố con: vì sao trời mưa lại phải giữ khoảng cách xa hơn trời nắng? 🤔";
        }
    }

    /* (b) Hỏi khung phạt KÈM CON SỐ: "quá tốc độ 25km", "chạy 80 trong đường 60" */
    $coQuaTocDo = str_contains($t, 'qua toc do') || str_contains($t, 'vuot toc do')
               || str_contains($t, 'chay qua toc');
    // Bỏ số hiệu văn bản (nghị định 168/2024...) để không nhầm với số km
    $tNum = preg_replace('/(nghi dinh|thong tu|luat|nd cp|qh15|dieu|khoan)\s*\d+(\s+\d+)*/u', ' ', $t);
    preg_match_all('/\d{1,3}/', $tNum, $m);
    $nums = array_values(array_filter(array_map('intval', $m[0] ?? []),
                                      fn($n) => $n >= 1 && $n <= 200));
    $over = null;

    if ($coQuaTocDo && preg_match('/qua toc do\D{0,14}?(\d{1,3})/', $tNum, $mm)) {
        $over = (int) $mm[1];                   // "quá tốc độ 25km/h"
    } elseif ($coQuaTocDo && $nums) {
        $over = $nums[0];
    } elseif (count($nums) >= 2 && (str_contains($t, 'chay') || str_contains($t, 'di'))
              && ai2_wants_fine($t)) {
        $over = max($nums[0], $nums[1]) - min($nums[0], $nums[1]);   // "chạy 80 đường 60"
    }
    if ($over === null || $over <= 0 || $over > 120) {
        return null;                            // không đủ dữ kiện → nhường tầng khác
    }
    if (!$coQuaTocDo && !ai2_wants_fine($t)) return null;

    $veh = ai2_detect_vehicle($t);

    $khungXeMay = function (int $o): string {
        if ($o < 5)   return 'Quá dưới 5 km/h: chưa tới mức bị phạt tiền — nhưng vẫn nên đi đúng tốc độ nhé';
        if ($o < 10)  return '**400.000 – 600.000 đồng** (mức quá 5 – dưới 10 km/h)';
        if ($o <= 20) return '**800.000 – 1.000.000 đồng** (mức quá 10 – 20 km/h)';
        return '**6.000.000 – 8.000.000 đồng, trừ 4 điểm GPLX** (mức quá trên 20 km/h)';
    };
    $khungOto = function (int $o): string {
        if ($o < 5)   return 'Quá dưới 5 km/h: chưa tới mức bị phạt tiền — nhưng vẫn nên đi đúng tốc độ nhé';
        if ($o < 10)  return '**800.000 – 1.000.000 đồng** (mức quá 5 – dưới 10 km/h)';
        if ($o <= 20) return '**4.000.000 – 6.000.000 đồng, trừ 2 điểm GPLX** (mức quá 10 – 20 km/h)';
        if ($o <= 35) return '**6.000.000 – 8.000.000 đồng, trừ 4 điểm GPLX** (mức quá trên 20 – 35 km/h)';
        return '**12.000.000 – 14.000.000 đồng, trừ 6 điểm GPLX** (mức quá trên 35 km/h)';
    };

    $out = "🚀 **Chạy quá tốc độ {$over} km/h thì bị phạt thế nào?**\n"
         . "_(Nghị định 168/2024/NĐ-CP, áp dụng từ 01/01/2025)_\n\n";
    if ($veh === 'xe may') {
        $out .= "🏍️ **Xe máy:** " . $khungXeMay($over) . "\n\n"
              . "_Tham khảo thêm — ô tô cùng mức vượt: " . strip_tags(str_replace('**', '', $khungOto($over))) . "_\n\n";
    } elseif ($veh === 'o to') {
        $out .= "🚗 **Ô tô:** " . $khungOto($over) . "\n\n"
              . "_Tham khảo thêm — xe máy cùng mức vượt: " . strip_tags(str_replace('**', '', $khungXeMay($over))) . "_\n\n";
    } else {
        $out .= "🏍️ **Xe máy:** " . $khungXeMay($over) . "\n"
              . "🚗 **Ô tô:** " . $khungOto($over) . "\n\n";
    }
    $out .= "⚠️ Chạy quá tốc độ mà **gây tai nạn**: xe máy 10 – 14 triệu đồng, ô tô 20 – 22 triệu đồng, đều trừ 10 điểm GPLX.\n\n"
          . "🧒 **Vì sao phạt nặng vậy?** Xe chạy càng nhanh thì quãng đường phanh càng dài — "
          . "gặp em bé chạy ra đường là không kịp dừng nữa. Đi chậm một chút, về nhà chắc chắn hơn nhiều!\n\n"
          . "❓ Đố con: xe đang chạy 60 km/h thì mỗi giây đi được khoảng bao nhiêu mét? (Gợi ý: gần 17 mét đó!) 🤔";
    return $out;
}

/* ---------- TRẢ LỜI: hệ thống 12 điểm giấy phép lái xe ---------- */
function ai2_points_reply(string $t): ?string
{
    $hit = false;
    foreach (['tru diem', '12 diem', 'phuc hoi diem', 'het diem', 'diem bang lai',
              'diem giay phep', 'diem gplx', 'bao nhieu diem', 'con bao nhieu diem', 'mat diem'] as $kw) {
        if (str_contains($t, $kw)) { $hit = true; break; }
    }
    if (!$hit) return null;

    return "⭐ **Hệ thống 12 ĐIỂM giấy phép lái xe** _(áp dụng từ 01/01/2025)_\n\n"
         . "Con tưởng tượng mỗi bằng lái có **12 ngôi sao**. Vi phạm luật là bị lấy bớt sao:\n\n"
         . "• Trừ **2 điểm**: ô tô quá tốc độ 10 – 20 km/h; xe máy đi ngược chiều, leo vỉa hè...\n"
         . "• Trừ **4 điểm**: vượt đèn đỏ; nồng độ cồn mức 1; dùng điện thoại khi lái xe; xe máy quá tốc độ trên 20 km/h...\n"
         . "• Trừ **6 điểm**: ô tô quá tốc độ trên 35 km/h; dừng đỗ sai trên cao tốc...\n"
         . "• Trừ **10 điểm**: nồng độ cồn mức 2; lạng lách đánh võng; gây tai nạn rồi bỏ chạy; mở cửa xe gây tai nạn...\n\n"
         . "📏 **Luật chơi công bằng:** phạm nhiều lỗi trong một lần chỉ bị trừ theo lỗi nặng nhất "
         . "_(Điều 50 Nghị định 168/2024/NĐ-CP)_.\n\n"
         . "🕳️ **Hết 12 điểm:** không được lái loại xe đó nữa; sau ít nhất **6 tháng** phải đi kiểm tra lại "
         . "kiến thức luật, đạt mới được phục hồi đủ 12 điểm.\n"
         . "🌱 **Lái xe ngoan:** không bị trừ điểm nào suốt **12 tháng** thì các sao tự phục hồi đủ 12 "
         . "_(Điều 58 Luật Trật tự, an toàn giao thông đường bộ 2024)_.\n\n"
         . "💡 Con thử hỏi ba mẹ xem: _\"Bằng lái của ba mẹ còn đủ 12 sao không ạ?\"_ 😄\n\n"
         . "❓ Đố con: lỗi nào vừa bị phạt tiền nhiều nhất vừa bị trừ nhiều sao nhất? 🤔";
}

/* ---------- TRẢ LỜI: so sánh mức phạt xe máy với ô tô ---------- */
function ai2_compare_reply(string $t): ?string
{
    $coSoSanh = str_contains($t, 'so sanh') || str_contains($t, 'nang hon')
             || str_contains($t, 'khac nhau') || str_contains($t, 'hon hay')
             || (str_contains($t, 'xe may') && str_contains($t, 'o to') && ai2_wants_fine($t));
    if (!$coSoSanh) return null;
    if (!str_contains($t, 'xe may') || !str_contains($t, 'o to')) return null;

    $key = ai2_find_violation($t);
    $out = "⚖️ **So sánh mức phạt: 🏍️ Xe máy và 🚗 Ô tô**\n"
         . "_(Nghị định 168/2024/NĐ-CP)_\n\n";

    if ($key !== null) {
        $row = ai2_fine_table()[$key];
        $out .= "Lỗi: **{$row['icon']} {$row['ten']}**\n\n";
        $out .= "• 🏍️ Xe máy: " . ($row['muc']['xe may'] ?? 'không áp dụng riêng cho xe máy') . "\n";
        $out .= "• 🚗 Ô tô: " . ($row['muc']['o to'] ?? 'không áp dụng riêng cho ô tô') . "\n\n";
        $out .= "⚖️ _Căn cứ: {$row['canci']}_\n\n";
    } else {
        $out .= "Vài lỗi tiêu biểu để con thấy sự khác biệt:\n\n"
              . "• 🚦 Vượt đèn đỏ — xe máy: 4 – 6 triệu · ô tô: **18 – 20 triệu**\n"
              . "• 🍺 Nồng độ cồn mức cao nhất — xe máy: 8 – 10 triệu · ô tô: **30 – 40 triệu**\n"
              . "• ↩️ Đi ngược chiều — xe máy: 4 – 6 triệu · ô tô: **18 – 20 triệu**\n"
              . "• 📱 Dùng điện thoại — xe máy: 0,8 – 1 triệu · ô tô: **4 – 6 triệu**\n\n";
    }

    $out .= "🧒 **Vì sao ô tô luôn bị phạt nặng hơn?** Ô tô nặng cả tấn, chạy nhanh, "
          . "khi gây tai nạn thì hậu quả lớn hơn xe máy rất nhiều — trách nhiệm càng lớn, mức phạt càng cao.\n\n"
          . "❓ Đố con: ngoài phạt tiền, người vi phạm còn bị trừ gì trên bằng lái nữa nè? 🤔";
    return $out;
}

/* ---------- BỘ ĐIỀU PHỐI: thử lần lượt từng bộ trả lời thông minh ---------- */
function ai2_law_reply(string $msg): ?string
{
    $t = ai_normalize($msg);
    if ($t === '') return null;

    foreach (['ai2_alcohol_reply', 'ai2_speed_reply', 'ai2_points_reply',
              'ai2_compare_reply', 'ai2_fine_reply'] as $fn) {
        $ans = $fn($t);
        if ($ans !== null) return $ans;
    }
    return null;
}

/* ---------- Từ khóa nhận diện cho 38 ĐIỀU LUẬT MỚI (nhóm 10 → 15) ---------- */
function ai2_law_kw(): array
{
    return [
      'xm-01' => ['xe may qua toc do', 'toc do xe may', 'xe may chay nhanh'],
      'xm-02' => ['ruot duoi', 'dua toc do', 'chay theo nhom'],
      'xm-03' => ['xe may nguoc chieu', 'xe may di nguoc'],
      'xm-04' => ['xe may via he', 'xe may len via he', 'leo le'],
      'xm-05' => ['nong do con xe may', 'xe may ruou bia', 'uong bia chay xe may'],
      'xm-06' => ['dien thoai xe may', 'xe may nghe dien thoai'],
      'xm-07' => ['khong bat den', 'quen bat den', 'den xe toi'],
      'xm-08' => ['khong guong', 'gay guong', 'thieu guong', 'khong coi', 'mat guong'],
      'op-01' => ['o to qua toc do', 'toc do o to', 'o to chay nhanh'],
      'op-02' => ['nong do con o to', 'o to ruou bia', 'uong bia lai o to'],
      'op-03' => ['o to vuot den do', 'o to den do'],
      'op-04' => ['o to nguoc chieu', 'lui xe cao toc', 'o to di lui', 'nguoc chieu cao toc'],
      'op-05' => ['mo cua xe', 'mo cua o to', 'mo cua kieu ha lan'],
      'op-06' => ['dien thoai o to', 'lai o to nghe dien thoai'],
      'op-07' => ['day an toan', 'tre em ghe truoc', 'ghe truoc o to', '1m35'],
      'op-08' => ['dung do cao toc', 'do xe cao toc', 'hong xe cao toc', 'dung khan cap'],
      'gt-01' => ['giay to xe', 'mang giay to gi', 'vneid', 'giay to khi lai xe', 'can giay to gi'],
      'gt-02' => ['quen bang lai', 'khong mang bang', 'quen giay phep'],
      'gt-03' => ['khong co bang lai', 'chua co bang', 'khong giay phep lai xe'],
      'gt-04' => ['bao hiem xe', 'bao hiem bat buoc', 'bao hiem trach nhiem'],
      'gt-05' => ['hang bang lai', 'bang a1', 'bang a2', 'hang a1', 'hang b', 'cac hang giay phep', 'bang b'],
      'gt-06' => ['xe dap dien khac xe may dien', 'xe may dien khac gi', 'phan biet xe dap dien'],
      'dg-01' => ['12 diem', 'bang lai bao nhieu diem', 'diem giay phep'],
      'dg-02' => ['nguyen tac tru diem', 'tru diem the nao', 'cach tru diem'],
      'dg-03' => ['tru may diem', 'loi nao tru diem', 'tru bao nhieu diem'],
      'dg-04' => ['het diem', 'tru het 12 diem', 'mat het diem', 'thi lai'],
      'dg-05' => ['phuc hoi diem', 'lay lai diem', 'hoi phuc diem'],
      'td-01' => ['toc do trong khu dan cu', 'toc do trong pho', 'toc do trong thanh pho', 'khu dong dan cu'],
      'td-02' => ['toc do ngoai khu dan cu', 'toc do quoc lo', 'ngoai thanh'],
      'td-03' => ['toc do xe gan may', 'toc do xe may dien', '40 km'],
      'td-04' => ['toc do cao toc', 'cao toc chay bao nhieu', '120 km'],
      'td-05' => ['khoang cach an toan', 'giu khoang cach', 'cach xe truoc'],
      'th-01' => ['duong sat', 'tau hoa', 'duong ngang', 'rao chan', 'xe lua'],
      'th-02' => ['xe uu tien', 'thu tu uu tien', 'xe chua chay', 'xe cuu thuong', 'xe cuu hoa'],
      'th-03' => ['vong xuyen', 'bung binh', 'vong xoay'],
      'th-04' => ['den pha', 'den cot', 'den chieu xa', 'den chieu gan', 'bat pha'],
      'th-05' => ['bam coi', 'boi coi', 'coi xe', 'coi to', 'bam coi ban dem'],
      'th-06' => ['xe buyt', 'xe dua don', 'xe dua ruoc', 'diem cho xe buyt'],
    ];
}

/* So khớp câu hỏi với các điều luật MỚI — dùng để nạp ngữ cảnh cho Gemini */
function ai2_law_match(string $msg, int $k = 3): array
{
    $t = ai_normalize($msg);
    if ($t === '') return [];
    $scores = [];
    foreach (ai2_law_kw() as $id => $kws) {
        $s = 0;
        foreach ($kws as $kw) {
            if (str_contains($t, $kw)) $s += mb_strlen($kw) * 2 + 12;
        }
        if ($s > 0) $scores[$id] = $s;
    }
    arsort($scores);
    return array_slice($scores, 0, $k, true);
}

/* Ngữ cảnh SỐ LIỆU PHẠT chi tiết bơm thêm cho Gemini (chế độ online),
   giúp AI nêu đúng con số thay vì nói chung chung. */
function ai2_law_context(string $msg): string
{
    $t = ai_normalize($msg);
    if ($t === '') return '';
    $ctx = '';

    $key = ai2_find_violation($t);
    if ($key !== null) {
        $row = ai2_fine_table()[$key];
        $ctx .= "\n[BẢNG PHẠT CHI TIẾT — SỐ LIỆU CHUẨN NĐ 168/2024, dùng đúng các con số này]\n"
              . "Lỗi: {$row['ten']}\n";
        foreach ($row['muc'] as $v => $m) $ctx .= "- " . ucfirst($v) . ": {$m}\n";
        $ctx .= "Căn cứ: {$row['canci']}\n";
    }

    foreach (['nong do con', 'ruou bia', 'uong ruou', 'uong bia'] as $kw) {
        if (str_contains($t, $kw)) {
            $ctx .= "\n[NỒNG ĐỘ CỒN — NĐ 168/2024] Xe máy: mức 1: 2-3 triệu, trừ 4 điểm; mức 2: 6-8 triệu, "
                  . "trừ 10 điểm; mức 3: 8-10 triệu, tước GPLX 22-24 tháng. Ô tô: mức 1: 6-8 triệu, trừ 4 điểm; "
                  . "mức 2: 18-20 triệu, trừ 10 điểm; mức 3: 30-40 triệu, tước GPLX 22-24 tháng. "
                  . "Xe đạp: 100-600 nghìn đồng. Không chấp hành kiểm tra: phạt như mức 3.\n";
            break;
        }
    }

    if (str_contains($t, 'toc do') || str_contains($t, 'chay nhanh')) {
        $ctx .= "\n[QUÁ TỐC ĐỘ — NĐ 168/2024] Xe máy: 5-<10km/h: 400-600k; 10-20: 800k-1 triệu; "
              . ">20: 6-8 triệu, trừ 4 điểm. Ô tô: 5-<10: 800k-1 triệu; 10-20: 4-6 triệu, trừ 2 điểm; "
              . ">20-35: 6-8 triệu, trừ 4 điểm; >35: 12-14 triệu, trừ 6 điểm. "
              . "[TỐC ĐỘ TỐI ĐA — TT 38/2024] Trong khu đông dân cư: 60 (đường đôi) / 50 km/h; "
              . "ngoài khu dân cư: ô tô con 90/80, xe máy 70/60; xe gắn máy và xe máy điện: 40 km/h; cao tốc: tối đa 120 km/h.\n";
    }

    if (str_contains($t, 'tru diem') || str_contains($t, '12 diem') || str_contains($t, 'phuc hoi diem')) {
        $ctx .= "\n[ĐIỂM GPLX — Điều 58 Luật TTATGT 2024 & Điều 50 NĐ 168/2024] Mỗi GPLX có 12 điểm. "
              . "Nhiều lỗi cùng lúc: chỉ trừ lỗi nặng nhất. Hết điểm: sau ít nhất 6 tháng kiểm tra lại kiến thức, "
              . "đạt thì phục hồi 12 điểm. Không bị trừ điểm trong 12 tháng: tự phục hồi đủ 12 điểm.\n";
    }

    return $ctx;
}


/* =====================================================================
   THƯ VIỆN BIỂN BÁO GIAO THÔNG VIỆT NAM
   Vẽ bằng SVG ngay trong file → không cần ảnh, không lo hỏng link.
   Chia theo 4 nhóm chính của QCVN 41 (Quy chuẩn biển báo Việt Nam).
   ===================================================================== */

/* Khung nền cho từng loại biển (dùng lại cho gọn) */
function sv_cam($inner) {      // Biển cấm: tròn, viền đỏ, nền trắng
    return '<svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="44" fill="#fff" stroke="#E03131" stroke-width="9"/>' . $inner . '</svg>';
}
function sv_nguyhiem($inner) { // Biển nguy hiểm: tam giác, viền đỏ, nền vàng
    return '<svg viewBox="0 0 100 100"><polygon points="50,8 94,86 6,86" fill="#FFD166" stroke="#E03131" stroke-width="8" stroke-linejoin="round"/>' . $inner . '</svg>';
}
function sv_hieulenh($inner) { // Biển hiệu lệnh: tròn, nền xanh dương
    return '<svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="44" fill="#2B6CD4" stroke="#fff" stroke-width="4"/>' . $inner . '</svg>';
}
function sv_chidan($inner) {   // Biển chỉ dẫn: vuông/chữ nhật, nền xanh dương
    return '<svg viewBox="0 0 100 100"><rect x="8" y="8" width="84" height="84" rx="6" fill="#2B6CD4" stroke="#fff" stroke-width="4"/>' . $inner . '</svg>';
}

function sign_library(): array
{
    return [

/* ══════════ NHÓM 1: BIỂN CẤM (tròn viền đỏ) ══════════ */
'cam' => [
  'label' => '🔴 Biển CẤM',
  'note'  => 'Hình TRÒN, viền ĐỎ, nền trắng — cho biết điều KHÔNG được phép làm.',
  'signs' => [
    ['code'=>'P.101','name'=>'Đường cấm',
     'mean'=>'Cấm tất cả các loại xe đi vào cả hai chiều. Chỉ xe ưu tiên đang làm nhiệm vụ mới được đi.',
     'svg'=>sv_cam('')],

    ['code'=>'P.102','name'=>'Cấm đi ngược chiều',
     'mean'=>'Cấm mọi loại xe đi vào theo chiều đặt biển. Đi ngược chiều là cực kỳ nguy hiểm — dễ đâm trực diện.',
     'svg'=>sv_cam('<rect x="24" y="43" width="52" height="14" rx="2" fill="#fff"/>')],

    ['code'=>'P.103a','name'=>'Cấm ô tô',
     'mean'=>'Cấm ô tô đi vào. Thường đặt ở đường nhỏ, ngõ hẹp, khu vực dành cho người đi bộ.',
     'svg'=>sv_cam('<rect x="26" y="46" width="48" height="17" rx="4" fill="#2C3550"/><path d="M34 46 l6 -9 h20 l6 9" fill="#2C3550"/><circle cx="36" cy="65" r="5" fill="#2C3550"/><circle cx="64" cy="65" r="5" fill="#2C3550"/><line x1="22" y1="78" x2="78" y2="22" stroke="#E03131" stroke-width="7"/>')],

    ['code'=>'P.110a','name'=>'Cấm xe đạp',
     'mean'=>'Cấm xe đạp đi vào đoạn đường này. Con nhớ tìm đường khác an toàn hơn nhé!',
     'svg'=>sv_cam('<circle cx="34" cy="60" r="12" fill="none" stroke="#2C3550" stroke-width="4"/><circle cx="66" cy="60" r="12" fill="none" stroke="#2C3550" stroke-width="4"/><path d="M34 60 L48 60 L58 40 L66 60 M48 60 L58 40" stroke="#2C3550" stroke-width="4" fill="none" stroke-linecap="round"/><line x1="22" y1="78" x2="78" y2="22" stroke="#E03131" stroke-width="7"/>')],

    ['code'=>'P.112','name'=>'Cấm người đi bộ',
     'mean'=>'Người đi bộ KHÔNG được đi vào (ví dụ: đường cao tốc, hầm chui xe cơ giới). Rất nguy hiểm cho con!',
     'svg'=>sv_cam('<circle cx="50" cy="30" r="7" fill="#2C3550"/><path d="M50 38 v20 M50 45 l-9 8 M50 45 l9 8 M50 58 l-8 14 M50 58 l8 14" stroke="#2C3550" stroke-width="5" fill="none" stroke-linecap="round"/><line x1="22" y1="78" x2="78" y2="22" stroke="#E03131" stroke-width="7"/>')],

    ['code'=>'P.127','name'=>'Tốc độ tối đa cho phép',
     'mean'=>'Xe KHÔNG được chạy nhanh hơn số ghi trên biển (ví dụ 40 km/h). Gần trường học thường có biển này để bảo vệ các con.',
     'svg'=>sv_cam('<text x="50" y="64" font-size="38" font-weight="800" fill="#2C3550" text-anchor="middle" font-family="Arial,sans-serif">40</text>')],

    ['code'=>'P.130','name'=>'Cấm dừng và đỗ xe',
     'mean'=>'Xe không được dừng, cũng không được đỗ ở đây. Thường đặt ở chỗ hay tắc đường hoặc che khuất tầm nhìn.',
     'svg'=>'<svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="44" fill="#2B6CD4" stroke="#E03131" stroke-width="9"/><line x1="24" y1="76" x2="76" y2="24" stroke="#E03131" stroke-width="8"/><line x1="24" y1="24" x2="76" y2="76" stroke="#E03131" stroke-width="8"/></svg>'],

    ['code'=>'P.123a','name'=>'Cấm rẽ trái',
     'mean'=>'Xe không được rẽ trái tại nơi đặt biển. Biển này cũng có nghĩa là cấm quay đầu xe.',
     'svg'=>sv_cam('<path d="M62 72 V48 H38 m10 -10 l-10 10 l10 10" stroke="#2C3550" stroke-width="7" fill="none" stroke-linecap="round" stroke-linejoin="round"/><line x1="22" y1="78" x2="78" y2="22" stroke="#E03131" stroke-width="7"/>')],
  ],
],

/* ══════════ NHÓM 2: BIỂN NGUY HIỂM (tam giác) ══════════ */
'nguy-hiem' => [
  'label' => '🔺 Biển NGUY HIỂM',
  'note'  => 'Hình TAM GIÁC, viền ĐỎ, nền vàng — CẢNH BÁO phía trước có nguy hiểm, hãy đi chậm và chú ý.',
  'signs' => [
    ['code'=>'W.201a','name'=>'Chỗ ngoặt nguy hiểm bên trái',
     'mean'=>'Phía trước có khúc cua gấp sang trái. Xe phải giảm tốc độ, không được vượt.',
     'svg'=>sv_nguyhiem('<path d="M58 76 V56 q0 -14 -14 -14 h-2 m8 -8 l-8 8 l8 8" stroke="#2C3550" stroke-width="6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>')],

    ['code'=>'W.207','name'=>'Giao nhau với đường không ưu tiên',
     'mean'=>'Phía trước là ngã tư/ngã ba. Đây là nơi nguy hiểm nhất — phải quan sát cả 4 hướng.',
     'svg'=>sv_nguyhiem('<path d="M50 40 V80 M30 60 H70" stroke="#2C3550" stroke-width="7" stroke-linecap="round"/>')],

    ['code'=>'W.210','name'=>'Giao nhau với đường sắt có rào chắn',
     'mean'=>'Phía trước có đường sắt CÓ rào chắn. Thấy chuông reo, rào hạ xuống thì phải dừng hẳn, tuyệt đối không chui qua.',
     'svg'=>sv_nguyhiem('<rect x="26" y="52" width="48" height="7" rx="2" fill="#2C3550"/><rect x="28" y="59" width="7" height="20" fill="#2C3550"/><rect x="65" y="59" width="7" height="20" fill="#2C3550"/>')],

    ['code'=>'W.211a','name'=>'Giao nhau với đường sắt KHÔNG rào chắn',
     'mean'=>'Đường sắt KHÔNG có rào chắn — nguy hiểm hơn nhiều! Con phải DỪNG LẠI, NHÌN, LẮNG NGHE thật kỹ.',
     'svg'=>sv_nguyhiem('<path d="M30 72 L70 44 M30 44 L70 72" stroke="#2C3550" stroke-width="7" stroke-linecap="round"/>')],

    ['code'=>'W.224','name'=>'Đường người đi bộ cắt ngang',
     'mean'=>'Phía trước có lối đi bộ sang đường. Xe phải giảm tốc, nhường đường cho người đi bộ.',
     'svg'=>sv_nguyhiem('<circle cx="50" cy="42" r="6" fill="#2C3550"/><path d="M50 49 v14 M50 54 l-8 6 M50 54 l8 6 M50 63 l-7 13 M50 63 l7 13" stroke="#2C3550" stroke-width="4.5" fill="none" stroke-linecap="round"/>')],

    ['code'=>'W.225','name'=>'TRẺ EM qua đường',
     'mean'=>'⭐ Biển quan trọng nhất với các con! Báo phía trước có trường học, có nhiều trẻ em. Xe phải đi thật chậm và cực kỳ chú ý.',
     'svg'=>sv_nguyhiem('<circle cx="40" cy="44" r="5" fill="#2C3550"/><path d="M40 50 v11 M40 54 l-6 5 M40 54 l6 5 M40 61 l-5 12 M40 61 l5 12" stroke="#2C3550" stroke-width="4" fill="none" stroke-linecap="round"/><circle cx="62" cy="47" r="5" fill="#2C3550"/><path d="M62 53 v10 M62 56 l-6 5 M62 56 l6 5 M62 63 l-5 11 M62 63 l5 11" stroke="#2C3550" stroke-width="4" fill="none" stroke-linecap="round"/>')],

    ['code'=>'W.227','name'=>'Công trường (đang thi công)',
     'mean'=>'Phía trước đang sửa đường. Có thể có hố sâu, vật liệu, máy móc. Đi chậm và tránh xa khu vực này nhé!',
     'svg'=>sv_nguyhiem('<circle cx="50" cy="40" r="5" fill="#2C3550"/><path d="M50 46 v12 M50 58 l-6 15 M50 58 l6 15" stroke="#2C3550" stroke-width="4.5" stroke-linecap="round"/><path d="M40 52 L66 42" stroke="#2C3550" stroke-width="4.5" stroke-linecap="round"/><rect x="62" y="36" width="12" height="7" rx="1" fill="#2C3550" transform="rotate(-20 68 40)"/>')],

    ['code'=>'W.219','name'=>'Dốc xuống nguy hiểm',
     'mean'=>'Phía trước là dốc xuống. Xe đạp phải bóp phanh từ từ, không thả trôi tự do kẻo mất kiểm soát!',
     'svg'=>sv_nguyhiem('<path d="M26 50 L74 78 L26 78 Z" fill="#2C3550"/><text x="56" y="62" font-size="15" font-weight="800" fill="#FFD166" font-family="Arial,sans-serif">10%</text>')],
  ],
],

/* ══════════ NHÓM 3: BIỂN HIỆU LỆNH (tròn xanh) ══════════ */
'hieu-lenh' => [
  'label' => '🔵 Biển HIỆU LỆNH',
  'note'  => 'Hình TRÒN, nền XANH DƯƠNG — điều BẮT BUỘC phải làm theo.',
  'signs' => [
    ['code'=>'R.301a','name'=>'Hướng đi phải theo — đi thẳng',
     'mean'=>'Bắt buộc chỉ được đi THẲNG, không được rẽ trái hay rẽ phải.',
     'svg'=>sv_hieulenh('<path d="M50 74 V32 m-13 13 l13 -13 l13 13" stroke="#fff" stroke-width="8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>')],

    ['code'=>'R.303','name'=>'Nơi giao nhau chạy theo vòng xuyến',
     'mean'=>'Đây là vòng xuyến (bùng binh). Xe phải chạy vòng quanh theo chiều ngược kim đồng hồ. Xe đang trong vòng xuyến được ưu tiên.',
     'svg'=>sv_hieulenh('<path d="M50 26 a24 24 0 1 1 -17 41" stroke="#fff" stroke-width="7" fill="none" stroke-linecap="round"/><path d="M42 20 l8 6 l-8 6" stroke="#fff" stroke-width="6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>')],

    ['code'=>'R.304','name'=>'Đường dành cho xe thô sơ (xe đạp)',
     'mean'=>'Đường này DÀNH RIÊNG cho xe đạp và xe thô sơ. Đây là nơi an toàn nhất để con đạp xe!',
     'svg'=>sv_hieulenh('<circle cx="34" cy="60" r="12" fill="none" stroke="#fff" stroke-width="4"/><circle cx="66" cy="60" r="12" fill="none" stroke="#fff" stroke-width="4"/><path d="M34 60 L48 60 L58 40 L66 60 M48 60 L58 40" stroke="#fff" stroke-width="4" fill="none" stroke-linecap="round"/>')],

    ['code'=>'R.305','name'=>'Đường dành cho NGƯỜI ĐI BỘ',
     'mean'=>'Đường dành riêng cho người đi bộ. Xe cộ không được đi vào — đây là nơi an toàn cho con.',
     'svg'=>sv_hieulenh('<circle cx="50" cy="30" r="8" fill="#fff"/><path d="M50 39 v20 M50 46 l-10 8 M50 46 l10 8 M50 59 l-8 15 M50 59 l8 15" stroke="#fff" stroke-width="5.5" fill="none" stroke-linecap="round"/>')],

    ['code'=>'R.307','name'=>'Hết hạn chế tốc độ tối thiểu',
     'mean'=>'Kết thúc đoạn đường bắt buộc chạy tốc độ tối thiểu.',
     'svg'=>sv_hieulenh('<text x="50" y="63" font-size="34" font-weight="800" fill="#fff" text-anchor="middle" font-family="Arial,sans-serif">60</text><line x1="24" y1="76" x2="76" y2="24" stroke="#fff" stroke-width="5"/>')],

    ['code'=>'R.403a','name'=>'Đường dành cho ô tô',
     'mean'=>'Đường chỉ dành cho ô tô. Xe đạp và người đi bộ KHÔNG được vào — rất nguy hiểm!',
     'svg'=>sv_hieulenh('<rect x="26" y="46" width="48" height="17" rx="4" fill="#fff"/><path d="M34 46 l6 -9 h20 l6 9" fill="#fff"/><circle cx="36" cy="65" r="5" fill="#fff"/><circle cx="64" cy="65" r="5" fill="#fff"/>')],
  ],
],

/* ══════════ NHÓM 4: BIỂN CHỈ DẪN (vuông xanh) ══════════ */
'chi-dan' => [
  'label' => '🟦 Biển CHỈ DẪN',
  'note'  => 'Hình VUÔNG/CHỮ NHẬT, nền XANH DƯƠNG — cung cấp thông tin hữu ích.',
  'signs' => [
    ['code'=>'I.423a','name'=>'Vạch qua đường cho người đi bộ',
     'mean'=>'Đây là chỗ CON ĐƯỢC QUA ĐƯỜNG! Hãy tìm biển này và vạch kẻ trắng dưới đất để sang đường an toàn.',
     'svg'=>sv_chidan('<rect x="22" y="52" width="56" height="34" fill="#fff"/><rect x="27" y="52" width="6" height="34" fill="#2B6CD4"/><rect x="40" y="52" width="6" height="34" fill="#2B6CD4"/><rect x="53" y="52" width="6" height="34" fill="#2B6CD4"/><rect x="66" y="52" width="6" height="34" fill="#2B6CD4"/><circle cx="50" cy="28" r="7" fill="#fff"/><path d="M50 36 v14 M50 41 l-8 6 M50 41 l8 6" stroke="#fff" stroke-width="5" fill="none" stroke-linecap="round"/>')],

    ['code'=>'I.424a','name'=>'Cầu vượt qua đường cho người đi bộ',
     'mean'=>'Có cầu vượt dành cho người đi bộ. Đây là cách qua đường AN TOÀN NHẤT — không xe nào đụng vào con được!',
     'svg'=>sv_chidan('<path d="M22 78 q28 -34 56 0" stroke="#fff" stroke-width="6" fill="none"/><circle cx="50" cy="34" r="6" fill="#fff"/><path d="M50 41 v12 M50 45 l-7 5 M50 45 l7 5" stroke="#fff" stroke-width="4.5" fill="none" stroke-linecap="round"/>')],

    ['code'=>'I.434a','name'=>'Bến xe buýt',
     'mean'=>'Nơi xe buýt dừng đón khách. Con hãy đứng trên vỉa hè chờ, không đứng sát mép đường nhé!',
     'svg'=>sv_chidan('<rect x="28" y="28" width="44" height="40" rx="5" fill="#fff"/><rect x="33" y="34" width="14" height="12" rx="2" fill="#2B6CD4"/><rect x="53" y="34" width="14" height="12" rx="2" fill="#2B6CD4"/><rect x="33" y="52" width="34" height="5" rx="2" fill="#2B6CD4"/><circle cx="38" cy="72" r="5" fill="#fff"/><circle cx="62" cy="72" r="5" fill="#fff"/>')],

    ['code'=>'I.436','name'=>'Trạm y tế / Bệnh viện',
     'mean'=>'Phía trước có bệnh viện hoặc trạm y tế. Nếu con hoặc ai đó bị thương, đây là nơi cần tìm đến.',
     'svg'=>sv_chidan('<rect x="42" y="24" width="16" height="52" rx="2" fill="#fff"/><rect x="24" y="42" width="52" height="16" rx="2" fill="#fff"/>')],

    ['code'=>'I.401','name'=>'Bắt đầu khu đông dân cư',
     'mean'=>'Bắt đầu khu vực có nhiều nhà cửa, nhiều người. Xe phải chạy chậm lại vì có thể có trẻ em bất ngờ chạy ra.',
     'svg'=>sv_chidan('<path d="M22 60 L36 44 L50 60 Z" fill="#fff"/><rect x="26" y="60" width="20" height="18" fill="#fff"/><path d="M52 66 L64 52 L76 66 Z" fill="#fff"/><rect x="56" y="66" width="16" height="12" fill="#fff"/>')],

    ['code'=>'I.409','name'=>'Chỗ quay xe',
     'mean'=>'Nơi được phép quay đầu xe an toàn.',
     'svg'=>sv_chidan('<path d="M36 76 V46 a14 14 0 0 1 28 0 v14 m-9 -9 l9 9 l9 -9" stroke="#fff" stroke-width="6.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>')],
  ],
],

    ];
}

/* Lấy 8 biển báo TIÊU BIỂU NHẤT VỚI TRẺ EM để hiện ngay trong khung chat
   (2 biển mỗi nhóm — chọn những biển các con hay gặp trên đường đi học) */
function sign_preview(): array
{
    $pick = [
        'P.110a',   // Cấm xe đạp        — bé đi xe đạp hay gặp
        'P.127',    // Tốc độ tối đa     — gần trường học
        'W.225',    // TRẺ EM qua đường  ⭐ quan trọng nhất với các con
        'W.210',    // Đường sắt có rào chắn
        'R.305',    // Đường dành cho người đi bộ
        'R.304',    // Đường dành cho xe đạp
        'I.423a',   // Vạch qua đường cho người đi bộ
        'I.424a',   // Cầu vượt cho người đi bộ
    ];

    $all = [];
    foreach (sign_library() as $g) {
        foreach ($g['signs'] as $s) {
            $all[$s['code']] = [
                'code'  => $s['code'],
                'name'  => $s['name'],
                'svg'   => $s['svg'],
                'group' => $g['label'],
            ];
        }
    }

    $out = [];
    foreach ($pick as $code) {
        if (isset($all[$code])) $out[] = $all[$code];
    }
    return $out;
}

/* =====================================================================
   ẢNH THẬT CHO TỪNG BIỂN BÁO
   Thứ tự ưu tiên:
   1. Ảnh BẠN TỰ BỎ VÀO thư mục "bien-bao/" — đặt tên theo mã biển,
      ví dụ: bien-bao/P.102.png, bien-bao/W.225.jpg, bien-bao/R.303.svg
      (đây là cách cho ảnh CHUẨN NHẤT — bạn toàn quyền chọn ảnh)
   2. Ảnh thật tự tải từ Wikimedia Commons theo mã biển
   3. Không có gì → dùng hình vẽ SVG có sẵn (không bao giờ vỡ giao diện)
   ===================================================================== */

define('SIGN_DIR', 'bien-bao');   // thư mục ảnh biển báo (cạnh file này)

/* Tìm ảnh bạn tự bỏ vào thư mục bien-bao/ */
function sign_local_photo(string $code): ?string
{
    foreach (['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'] as $ext) {
        $rel  = SIGN_DIR . '/' . $code . '.' . $ext;
        $path = __DIR__ . '/' . $rel;
        if (is_file($path)) return $rel;   // trả về đường dẫn tương đối để trình duyệt tải
    }
    return null;
}

/* Lấy ảnh thật cho cả 1 NHÓM biển báo (gọi 1 lần, có cache) */
function sign_photos_for_group(PDO $pdo, string $group): array
{
    $lib = sign_library();
    if (!isset($lib[$group])) return [];

    $out = [];
    foreach ($lib[$group]['signs'] as $sg) {
        $code = $sg['code'];

        // 1. Ảnh bạn tự bỏ vào → ưu tiên cao nhất
        $local = sign_local_photo($code);
        if ($local !== null) {
            $out[$code] = ['url' => $local, 'src' => 'local'];
            continue;
        }

        if (!IMG_ENABLED) continue;

        // 2. Tải từ Wikimedia Commons (có cache trong CSDL)
        $q  = 'Vietnam road sign ' . $code;
        $st = $pdo->prepare("SELECT data FROM aigs_img_cache WHERE q = ?");
        $st->execute([$q]);
        $row = $st->fetch();

        if ($row) {
            $cached = json_decode($row['data'], true);
        } else {
            $cached = img_search_commons($q, 1);
            $pdo->prepare("INSERT INTO aigs_img_cache (q, data) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE data = VALUES(data), created_at = NOW()")
                ->execute([$q, json_encode($cached, JSON_UNESCAPED_UNICODE)]);
        }

        if (!empty($cached[0]['thumb'])) {
            $out[$code] = ['url' => $cached[0]['thumb'], 'src' => 'commons'];
        }
        // 3. Không tìm thấy → bỏ qua, trình duyệt sẽ dùng hình vẽ SVG
    }
    return $out;
}

/* =====================================================================
   TÌM ẢNH THẬT — từ kho Wikimedia Commons
   • Miễn phí, KHÔNG cần API key, giấy phép tự do (dùng cho bài tập/luận văn thoải mái)
   • Máy bạn tự gọi lúc chạy → link ảnh luôn còn sống, không bao giờ hỏng
   • Có cache trong CSDL: tìm 1 lần, những lần sau hiện ngay
   • Mất mạng / không tìm thấy → tự quay về hình vẽ SVG, chat vẫn chạy bình thường
   ===================================================================== */

/* Từ khoá tìm ảnh ĐÃ TUYỂN CHỌN cho từng chủ đề.
   Mỗi chủ đề có 2 từ khoá → ảnh đa dạng, phong phú hơn.
   Đây là chìa khoá để ảnh RA ĐÚNG — không thả cho hệ thống tìm bừa.
   Bạn có thể sửa/thêm từ khoá ở đây nếu muốn ảnh khác. */
function img_curated_query(?string $illusKey): ?array
{
    $q = [
        // --- Chủ đề gốc ---
        'canh-sat'    => ['traffic police officer directing', 'police officer road Vietnam'],
        'duong-sat'   => ['railway level crossing barrier', 'railway crossing warning sign'],
        'den-do'      => ['traffic light red signal', 'red traffic light street'],
        'den-vang'    => ['traffic light yellow amber signal', 'amber traffic light'],
        'den-xanh'    => ['traffic light green signal', 'green traffic light street'],
        'den-3-mau'   => ['traffic light signal road', 'pedestrian traffic signal'],
        'mu-bao-hiem' => ['child bicycle helmet', 'children wearing safety helmet'],
        'vach-ke'     => ['pedestrian crossing zebra crosswalk', 'children crossing street crosswalk'],
        'cuu-thuong'  => ['ambulance emergency vehicle', 'fire truck emergency siren'],
        'bien-stop'   => ['stop sign road', 'octagonal stop sign'],
        // 'bien-bao' đã bị BỎ khỏi đây: ảnh tìm tự động hay ra biển nước ngoài,
        // sai lệch với trẻ em. Thay bằng bộ biển báo VẼ CHUẨN trong sign_library().
        'xe-dap'      => ['child riding bicycle helmet', 'bicycle lane road'],
        'day-an-toan' => ['child car seat belt', 'car seat belt safety'],

        // --- 9 CHỦ ĐỀ MỚI ---
        'xe-buyt'     => ['school bus children', 'bus stop passengers waiting'],
        'diem-mu'     => ['truck blind spot danger', 'large truck mirror road'],
        'troi-mua'    => ['wet road rain driving', 'rainy street pedestrian umbrella'],
        'ban-dem'     => ['night road street lights', 'reflective safety vest pedestrian'],
        'nga-tu'      => ['road intersection crossroads', 'roundabout traffic aerial'],
        'via-he'      => ['sidewalk pedestrian walking', 'city footpath pavement'],
        'choi-duong'  => ['children playing playground', 'road danger children street'],
        'lac-duong'   => ['police officer helping child', 'emergency phone call help'],
        'toc-do'      => ['speed limit sign road', 'school zone speed sign'],
        'an-toan-chung' => ['road safety children education', 'traffic safety awareness'],
    ];
    return $q[$illusKey] ?? null;
}

/* Câu hỏi LẠ (không thuộc chủ đề nào) → tự dựng từ khoá tìm ảnh.
   Luôn kèm "traffic road" để ảnh bám chủ đề giao thông,
   tránh ra ảnh linh tinh không phù hợp với trẻ em. */
function img_build_query(string $msg): ?string
{
    $t = ai_khong_dau($msg);

    // Bảng dịch từ khoá tiếng Việt → tiếng Anh (Commons tìm bằng tiếng Anh tốt hơn)
    $dict = [
        'via he' => 'sidewalk', 'duong sat' => 'railway crossing', 'tau hoa' => 'railway train',
        'xe buyt' => 'bus', 'xe tai' => 'truck', 'nga tu' => 'intersection',
        'vong xuyen' => 'roundabout', 'cau vuot' => 'overpass bridge', 'ham' => 'tunnel',
        'canh sat giao thong' => 'traffic police officer', 'toc do' => 'speed limit sign',
        'cam' => 'prohibition sign', 'truong hoc' => 'school zone sign', 'mua' => 'rain wet road',
        'ban dem' => 'night road', 'coi' => 'horn', 'guong' => 'mirror',
    ];
    $terms = [];
    foreach ($dict as $vi => $en) {
        if (str_contains($t, $vi)) $terms[] = $en;
    }
    if (!$terms) return null;   // không nhận ra chủ đề → thà không có ảnh còn hơn ảnh sai

    return implode(' ', array_slice($terms, 0, 2)) . ' road traffic';
}

/* Gọi API Wikimedia Commons lấy ảnh thật */
function img_search_commons(string $query, int $limit): array
{
    if (!function_exists('curl_init')) return [];

    $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action'      => 'query',
        'format'      => 'json',
        'generator'   => 'search',
        'gsrsearch'   => 'filetype:bitmap ' . $query,
        'gsrnamespace'=> 6,          // 6 = File (chỉ lấy file ảnh)
        'gsrlimit'    => $limit,
        'prop'        => 'imageinfo',
        'iiprop'      => 'url|extmetadata',
        'iiurlwidth'  => 480,        // lấy ảnh thu nhỏ cho nhẹ, tải nhanh
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_USERAGENT      => 'SieuNhiATGT-AIGiaSu/1.0 (bai tap sinh vien)',
        CURLOPT_SSL_VERIFYPEER => false,   // XAMPP Windows hay thiếu chứng chỉ SSL
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false) return [];

    $data  = json_decode($res, true);
    $pages = $data['query']['pages'] ?? [];

    $out = [];
    foreach ($pages as $p) {
        $info = $p['imageinfo'][0] ?? null;
        if (!$info || empty($info['thumburl'])) continue;

        $lic = $info['extmetadata']['LicenseShortName']['value'] ?? 'Wikimedia Commons';
        $out[] = [
            'thumb'   => $info['thumburl'],                       // ảnh hiện lên
            'page'    => $info['descriptionurl'] ?? '',           // bấm vào → trang gốc
            'license' => strip_tags($lic),                        // giấy phép (ghi nguồn)
        ];
        if (count($out) >= $limit) break;
    }
    return $out;
}

/* Hàm tổng: tìm ảnh cho 1 câu hỏi (ưu tiên lấy từ cache) */
function img_fetch_for(PDO $pdo, string $msg, ?string $illusKey): array
{
    if (!IMG_ENABLED) return [];

    // 1. Chủ đề đã biết → dùng 2 từ khoá tuyển chọn (ảnh đúng + đa dạng)
    // 2. Câu hỏi lạ  → tự dựng 1 từ khoá
    $queries = img_curated_query($illusKey);
    if ($queries === null) {
        $auto = img_build_query($msg);
        if ($auto === null) return [];
        $queries = [$auto];
    }

    $cacheKey = implode(' | ', $queries);

    // Đã tìm rồi thì lấy lại từ cache, khỏi phải chờ
    $st = $pdo->prepare("SELECT data FROM aigs_img_cache WHERE q = ?");
    $st->execute([$cacheKey]);
    $row = $st->fetch();
    if ($row) {
        $cached = json_decode($row['data'], true);
        if (is_array($cached)) return $cached;
    }

    // Chia đều số ảnh cho các từ khoá, rồi gộp lại
    $perQuery = (int) ceil(IMG_COUNT / count($queries));
    $photos = [];
    $seen   = [];
    foreach ($queries as $q) {
        foreach (img_search_commons($q, $perQuery + 2) as $p) {
            if (isset($seen[$p['thumb']])) continue;   // bỏ ảnh trùng
            $seen[$p['thumb']] = true;
            $photos[] = $p;
            if (count($photos) >= IMG_COUNT) break 2;
        }
    }

    // Lưu cache (kể cả khi rỗng, để lần sau khỏi gọi mạng liên tục)
    $st = $pdo->prepare("INSERT INTO aigs_img_cache (q, data) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE data = VALUES(data), created_at = NOW()");
    $st->execute([$cacheKey, json_encode($photos, JSON_UNESCAPED_UNICODE)]);

    return $photos;
}

/* =====================================================================
   PHẦN 2 — GIAO DIỆN TRANG
   ===================================================================== */
$fullname = $_SESSION['fullname'] ?? 'Bé Minh An';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Gia sư · Siêu Nhí An Toàn Giao Thông AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --navy:#1E2A4A; --navy-2:#141E38;
  --ink:#243B6B; --ink-soft:#4A5F8F;
  --coral:#FF6B57; --coral-2:#FF8A5C;
  --sky:#38BDF8; --sun:#FFD166; --leaf:#34D399;
  --bg:#F2F7FE; --line:#E1EAF8; --white:#fff;
}
*{ box-sizing:border-box; margin:0; padding:0; }
html,body{ height:100%; overflow:hidden; }
body{
  font-family:'Be Vietnam Pro', system-ui, -apple-system, sans-serif;
  color:var(--ink); background:var(--bg); -webkit-font-smoothing:antialiased;
}
h1,h2,h3{ font-family:'Baloo 2','Be Vietnam Pro',sans-serif; }
a{ color:inherit; }
button{ font-family:inherit; }

/* --- Thanh báo lỗi hệ thống (chỉ hiện khi có sự cố) --- */
.sysbar{
  display:none; background:#FFF3CD; border-bottom:2px solid #FFC107;
  color:#7A5C00; font-size:13px; padding:10px 18px; line-height:1.5;
}
.sysbar.show{ display:block; }
.sysbar b{ color:#5C4500; }

.btn{ border:none; cursor:pointer; display:inline-flex; align-items:center; gap:8px; font-weight:700; transition:.15s; }
.btn-primary-sm{
  background:linear-gradient(135deg,var(--coral),var(--coral-2));
  color:#fff; font-size:13.5px; padding:11px 14px; border-radius:12px;
  box-shadow:0 6px 16px rgba(255,107,87,.35);
}
.btn-primary-sm:hover{ transform:translateY(-1px); }

.icon-btn{
  width:36px; height:36px; border-radius:11px; background:#EFF5FD;
  display:grid; place-items:center; cursor:pointer; font-size:16px; transition:.15s; user-select:none;
}
.icon-btn:hover{ background:#DFEBFB; transform:translateY(-1px); }
.icon-btn-sm{
  width:34px; height:34px; border:none; border-radius:10px; background:transparent;
  font-size:16px; cursor:pointer; display:grid; place-items:center; transition:.15s; flex-shrink:0;
}
.icon-btn-sm:hover{ background:#EFF5FD; }
.icon-btn-sm.send{
  background:linear-gradient(135deg,var(--coral),var(--coral-2));
  color:#fff; border-radius:12px; box-shadow:0 4px 12px rgba(255,107,87,.35);
}

::-webkit-scrollbar{ width:8px; }
::-webkit-scrollbar-thumb{ background:#C9D9F0; border-radius:999px; }
.sidebar::-webkit-scrollbar{ width:6px; }
.sidebar::-webkit-scrollbar-thumb{ background:rgba(255,255,255,.18); }

/* --- Bố cục --- */
.page{ display:flex; flex-direction:column; height:100vh; }
.app{ display:flex; flex:1; min-height:0; }

.sidebar{
  width:282px; flex-shrink:0;
  background:linear-gradient(180deg,var(--navy),var(--navy-2));
  color:#fff; padding:18px 16px;
  display:flex; flex-direction:column; gap:4px; overflow-y:auto;
}
.side-brand{ display:flex; align-items:center; gap:10px; font-family:'Baloo 2',sans-serif; font-weight:800; font-size:19px; }
.side-brand .mark{
  width:38px; height:38px; border-radius:12px;
  background:linear-gradient(135deg,var(--coral),var(--sun));
  display:grid; place-items:center; font-size:20px;
}
.side-back{ display:inline-block; color:rgba(255,255,255,.55); font-size:12.5px; text-decoration:none; margin:10px 0 16px; }
.side-back:hover{ color:#fff; }
.side-link{
  display:flex; align-items:center; gap:8px;
  color:rgba(255,255,255,.72); font-size:13.5px;
  padding:9px 10px; border-radius:10px; text-decoration:none; cursor:pointer; transition:.15s;
}
.side-link:hover{ background:rgba(255,255,255,.07); color:#fff; }
.side-link.active{ background:rgba(255,255,255,.13); color:#fff; font-weight:600; }
.side-link .ic{ width:20px; text-align:center; }
.side-divider{ height:1px; background:rgba(255,255,255,.1); margin:12px 0; flex-shrink:0; }
.sidebar-foot{
  margin-top:auto; flex-shrink:0; display:flex; gap:10px; align-items:center;
  background:rgba(255,255,255,.06); padding:10px; border-radius:14px;
}
.sidebar-foot .av{
  width:38px; height:38px; border-radius:50%; flex-shrink:0;
  background:linear-gradient(135deg,var(--sky),var(--leaf));
  display:grid; place-items:center; font-size:19px;
}
.sidebar-foot .txt b{ display:block; font-size:13.5px; }
.sidebar-foot .txt span{ font-size:11.5px; color:rgba(255,255,255,.55); }

.session-list{ display:flex; flex-direction:column; }
.group-label{
  color:rgba(255,255,255,.4) !important; font-size:11px;
  text-transform:uppercase; letter-spacing:.06em; pointer-events:none; margin-top:10px;
}
.session-item{ display:flex; align-items:center; gap:6px; cursor:pointer; }
.session-item .s-title{ flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.session-item .del{ opacity:0; font-size:11px; padding:2px 6px; border-radius:6px; transition:.15s; }
.session-item:hover .del{ opacity:.75; }
.session-item .del:hover{ opacity:1; background:rgba(255,255,255,.15); }

.chat-col{ flex:1; min-width:0; display:flex; flex-direction:column; }
.chat-top{
  display:flex; align-items:center; justify-content:space-between;
  padding:14px 22px; background:var(--white); border-bottom:1px solid var(--line); flex-shrink:0;
}
.chat-top h2{ font-size:17px; display:flex; align-items:center; gap:9px; }
.status-dot{ width:9px; height:9px; border-radius:50%; background:var(--leaf); animation:statusPulse 2s infinite; }
@keyframes statusPulse{
  0%,100%{ box-shadow:0 0 0 4px rgba(52,211,153,.18); }
  50%{ box-shadow:0 0 0 7px rgba(52,211,153,.08); }
}
.top-actions{ display:flex; gap:8px; }

.chat-scroll{ flex:1; overflow-y:auto; padding:22px; }
.chat-inner{ max-width:760px; margin:0 auto; display:flex; flex-direction:column; gap:16px; }

.msg{ display:flex; gap:10px; align-items:flex-end; }
.msg.user{ flex-direction:row-reverse; }
.msg-avatar{ width:36px; height:36px; border-radius:50%; flex-shrink:0; display:grid; place-items:center; font-size:18px; }
.msg.bot .msg-avatar{ background:linear-gradient(135deg,var(--sky),#6C8CFF); }
.msg.user .msg-avatar{ background:linear-gradient(135deg,var(--sun),var(--coral-2)); }
.msg-body{ max-width:80%; display:flex; flex-direction:column; gap:8px; }
.msg.user .msg-body{ align-items:flex-end; max-width:72%; }
.msg-bubble{ padding:13px 16px; border-radius:18px; font-size:14.5px; line-height:1.68; word-wrap:break-word; }
.msg.bot .msg-bubble b{ color:#1B3A6B; }
.msg.bot .msg-bubble{
  background:var(--white); color:#2A3B5F;
  border-bottom-left-radius:6px; box-shadow:0 3px 12px rgba(30,42,74,.07);
}
.msg.user .msg-bubble{
  background:linear-gradient(135deg,var(--coral),var(--coral-2)); color:#fff;
  border-bottom-right-radius:6px; box-shadow:0 4px 14px rgba(255,107,87,.3);
}
.msg.error .msg-bubble{ background:#FFEAEA; color:#B23B3B; border:1px solid #FFC9C9; }
.msg-tools{ display:flex; gap:6px; flex-wrap:wrap; }

/* ============ TÌNH HUỐNG TƯƠNG TÁC ============ */
.sit-card{
  background:var(--white); border:2px solid var(--sun); border-radius:18px;
  overflow:hidden; width:min(470px,100%);
  box-shadow:0 6px 22px rgba(255,209,102,.28); animation:illusIn .35s ease-out;
}
.sit-head{
  padding:10px 14px; background:linear-gradient(135deg,#FFF6E5,#FFEFE5);
  display:flex; justify-content:space-between; align-items:center;
}
.sit-head b{ font-size:13.5px; color:var(--ink); }
.sit-head span{ font-size:11px; color:#8A6A00; font-weight:600; }
.sit-scene{ background:#EAF2FB; }
.sit-scene svg{ display:block; width:100%; height:auto; }
.sit-body{ padding:13px 15px; }
.sit-q{ font-size:14.5px; font-weight:600; line-height:1.55; color:var(--ink); margin-bottom:11px; }
.sit-opt{
  display:block; width:100%; text-align:left; margin-bottom:7px;
  background:#F4F8FE; border:1.5px solid var(--line); border-radius:12px;
  padding:11px 13px; font-size:13.5px; color:var(--ink); line-height:1.45;
  cursor:pointer; transition:.15s; font-family:inherit;
}
.sit-opt:hover:not(:disabled){ border-color:var(--sky); background:#EAF4FE; transform:translateX(2px); }
.sit-opt:disabled{ cursor:default; }
.sit-opt.right{ background:#DCFCE7 !important; border-color:var(--leaf) !important; color:#166534; font-weight:600; }
.sit-opt.wrong{ background:#FEE2E2 !important; border-color:#F87171 !important; color:#B91C1C; }

/* ============ GAME "ĐƯỜNG ĐẾN TRƯỜNG" ============ */
.map-head{
  background:linear-gradient(135deg,#EAF6FF,#E9FBF1);
  border-radius:14px; padding:12px 14px; margin-bottom:14px;
  display:flex; justify-content:space-between; align-items:center;
}
.map-head .mh-l b{ font-size:14px; color:var(--ink); }
.map-head .mh-l span{ display:block; font-size:11.5px; color:var(--ink-soft); margin-top:2px; }
.map-head .mh-r{ text-align:right; font-size:12px; color:var(--ink-soft); }
.map-head .mh-r b{ font-size:20px; color:var(--ink); font-family:'Baloo 2',sans-serif; }

.map-path{ position:relative; padding:6px 0 6px 42px; }
.map-path::before{
  content:''; position:absolute; left:19px; top:14px; bottom:14px; width:5px;
  background:repeating-linear-gradient(180deg,#C9D9F0 0 12px,transparent 12px 22px);
  border-radius:3px;
}
.map-stop{
  position:relative; display:flex; align-items:center; gap:11px;
  background:#F8FBFF; border:1.5px solid var(--line); border-radius:13px;
  padding:11px 13px; margin-bottom:9px; cursor:pointer; transition:.15s;
}
.map-stop:hover:not(.locked){ border-color:var(--coral); transform:translateX(3px); }
.map-stop.done{ background:#F2FDF7; border-color:var(--leaf); }
.map-stop.locked{ opacity:.5; cursor:not-allowed; background:#F2F5F9; }
.map-stop .dot{
  position:absolute; left:-32px; width:26px; height:26px; border-radius:50%;
  display:grid; place-items:center; font-size:12px; font-weight:800;
  background:#C9D9F0; color:#fff; border:3px solid #F2F7FE;
}
.map-stop.done .dot{ background:var(--leaf); }
.map-stop.now .dot{ background:var(--coral); animation:pulse 1.4s infinite; }
.map-stop .ms-t{ flex:1; }
.map-stop .ms-t b{ font-size:13px; color:var(--ink); display:block; }
.map-stop .ms-t span{ font-size:11px; color:var(--ink-soft); }
.map-stop .ms-s{ font-size:15px; }
.map-goal{
  text-align:center; padding:14px; border-radius:14px; margin-top:6px;
  background:linear-gradient(135deg,#FFF6E5,#FFEFE5); border:2px dashed var(--sun);
}
.map-goal .mg-ic{ font-size:32px; }
.map-goal b{ display:block; font-size:14px; color:var(--ink); margin-top:4px; }
.map-goal span{ font-size:11.5px; color:var(--ink-soft); }

/* ============ CÁ NHÂN HOÁ (bài ôn riêng) ============ */
.weak-card{
  background:linear-gradient(135deg,#FFF6E5,#FFF0F0);
  border:1.5px dashed var(--coral); border-radius:14px;
  padding:12px 14px; width:min(430px,100%); animation:illusIn .35s ease-out;
}
.weak-card h4{ font-size:13.5px; color:var(--ink); margin-bottom:3px; }
.weak-card .wsub{ font-size:11.5px; color:var(--ink-soft); margin-bottom:9px; line-height:1.5; }
.weak-item{
  display:flex; align-items:center; gap:8px; background:#fff;
  border-radius:10px; padding:8px 11px; margin-bottom:6px; font-size:12.5px;
}
.weak-item .wn{ flex:1; color:var(--ink); font-weight:600; }
.weak-item .wc{ font-size:10.5px; color:#B23B3B; font-weight:700; }
.weak-item button{
  border:none; cursor:pointer; background:var(--sky); color:#fff;
  font-size:11px; font-weight:600; padding:5px 10px; border-radius:8px; font-family:inherit;
}
.weak-item button:hover{ filter:brightness(1.08); }

/* ============ BÁO CÁO GIÁO VIÊN / PHỤ HUYNH ============ */
.rep-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:9px; margin-bottom:14px; }
.rep-kpi{
  background:#F6FAFF; border-radius:13px; padding:11px 9px; text-align:center;
  border:1.5px solid var(--line);
}
.rep-kpi .rv{ font-size:21px; font-weight:800; font-family:'Baloo 2',sans-serif; color:var(--ink); }
.rep-kpi .rl{ font-size:10.5px; color:var(--ink-soft); margin-top:2px; }
.rep-sec{ margin-bottom:14px; }
.rep-sec h4{ font-size:13.5px; margin-bottom:9px; }
.rep-table{ width:100%; border-collapse:collapse; font-size:12.5px; }
.rep-table th{
  text-align:left; padding:7px 9px; background:#F0F5FC; color:var(--ink-soft);
  font-size:11px; font-weight:600;
}
.rep-table th:first-child{ border-radius:8px 0 0 8px; }
.rep-table th:last-child{ border-radius:0 8px 8px 0; }
.rep-table td{ padding:7px 9px; border-bottom:1px solid var(--line); color:var(--ink); }
.rep-table tr:last-child td{ border-bottom:none; }
.rep-tip{
  background:#FFF8E5; border-left:4px solid var(--sun); border-radius:10px;
  padding:10px 12px; margin-bottom:7px; font-size:12.5px; line-height:1.6; color:#6B5200;
}
.rep-tip b{ color:#8A6A00; }
.rep-ok{ color:#177C4F; font-weight:700; }
.rep-no{ color:#B23B3B; font-weight:700; }

/* ============ HUY HIỆU ============ */
.badge-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; }
.badge-item{
  background:#F6FAFF; border:1.5px solid var(--line); border-radius:14px;
  padding:12px 9px; text-align:center; transition:.15s;
}
.badge-item.got{
  background:linear-gradient(160deg,#FFF9E8,#FFF1E8);
  border-color:var(--sun); box-shadow:0 4px 14px rgba(255,209,102,.3);
}
.badge-item.got:hover{ transform:translateY(-2px) scale(1.02); }
.badge-item .bi{ font-size:32px; line-height:1.1; }
.badge-item.locked .bi{ filter:grayscale(1); opacity:.35; }
.badge-item .bn{ font-size:12px; font-weight:700; color:var(--ink); margin-top:5px; }
.badge-item.locked .bn{ color:#9FB2D3; }
.badge-item .bd{ font-size:10.5px; color:var(--ink-soft); line-height:1.4; margin-top:3px; }
.badge-item.locked .bd{ color:#B8C6DC; }

/* Thông báo đạt huy hiệu mới */
.badge-toast{
  position:fixed; top:22px; left:50%; transform:translateX(-50%) translateY(-24px);
  background:linear-gradient(135deg,#FFD166,#FF8A5C); color:#3D2600;
  padding:12px 20px; border-radius:16px; z-index:1200;
  box-shadow:0 12px 34px rgba(255,138,92,.45);
  display:flex; align-items:center; gap:11px;
  opacity:0; transition:.35s; pointer-events:none;
}
.badge-toast.show{ opacity:1; transform:translateX(-50%) translateY(0); }
.badge-toast .bt-ic{ font-size:30px; animation:badgePop .6s ease-out; }
@keyframes badgePop{
  0%{ transform:scale(.4) rotate(-14deg); }
  60%{ transform:scale(1.2) rotate(6deg); }
  100%{ transform:scale(1) rotate(0); }
}
.badge-toast b{ display:block; font-size:14px; }
.badge-toast span{ font-size:11.5px; }

/* ============ BÀI KIỂM TRA TRƯỚC / SAU ============ */
.test-cards{ display:grid; grid-template-columns:1fr 1fr; gap:11px; margin-bottom:14px; }
.test-card{ border-radius:14px; padding:13px; border:2px solid var(--line); background:#F8FBFF; }
.test-card.done{ border-color:var(--leaf); background:#F2FDF7; }
.test-card h4{ font-size:13px; margin-bottom:3px; }
.test-card .tsub{ font-size:11px; color:var(--ink-soft); line-height:1.45; margin-bottom:9px; }
.test-card .tscore{ font-family:'Baloo 2',sans-serif; font-size:26px; font-weight:800; color:var(--ink); }
.test-card .tscore small{ font-size:14px; color:var(--ink-soft); font-weight:600; }
.test-btn{
  width:100%; border:none; cursor:pointer; margin-top:8px;
  background:linear-gradient(135deg,var(--sky),#6C8CFF); color:#fff;
  font-weight:700; font-size:12.5px; padding:9px; border-radius:10px; font-family:inherit;
}
.test-btn:disabled{ background:#D9E6F8; color:#9FB2D3; cursor:not-allowed; }
.test-btn.again{ background:#EFF5FD; color:#4A5F8F; }

/* Biểu đồ tiến bộ theo kỹ năng */
.prog-chart{ background:#F8FBFF; border-radius:14px; padding:14px; margin-bottom:12px; }
.prog-chart h4{ font-size:13.5px; margin-bottom:11px; }
.prog-row{ margin-bottom:11px; }
.prog-row .pl{
  display:flex; justify-content:space-between; font-size:11.5px;
  color:var(--ink-soft); margin-bottom:4px;
}
.prog-row .pl b{ color:var(--ink); }
.prog-line{ display:flex; align-items:center; gap:6px; margin-bottom:3px; }
.prog-tag{ font-size:9.5px; color:#8CA0C4; width:38px; flex-shrink:0; }
.pb{ flex:1; height:11px; border-radius:999px; background:#E4EDF9; overflow:hidden; }
.pb i{ display:block; height:100%; border-radius:999px; transition:width .8s ease; }
.pb.pre i{ background:#B8C6DC; }
.pb.post i{ background:linear-gradient(90deg,var(--leaf),var(--sky)); }

.improve{
  text-align:center; padding:14px; border-radius:14px; margin-bottom:12px;
  background:linear-gradient(135deg,#E9FBF1,#EAF6FF);
}
.improve .big{ font-size:30px; font-weight:800; font-family:'Baloo 2',sans-serif; color:#177C4F; }
.improve .txt{ font-size:12.5px; color:var(--ink-soft); margin-top:3px; line-height:1.5; }

/* ============ TIẾN ĐỘ HỌC (sidebar) ============ */
.progress-box{
  background:rgba(255,255,255,.07); border-radius:14px; padding:11px 12px; margin-bottom:12px;
}
.progress-head{
  display:flex; justify-content:space-between; align-items:center;
  font-size:12px; color:rgba(255,255,255,.75); margin-bottom:7px;
}
.progress-head b{ color:#fff; font-size:12.5px; }
.progress-bar{ height:7px; background:rgba(255,255,255,.14); border-radius:999px; overflow:hidden; }
.progress-bar i{
  display:block; height:100%; width:0;
  background:linear-gradient(90deg,var(--leaf),var(--sky));
  border-radius:999px; transition:width .6s ease;
}
.progress-stats{
  display:flex; gap:10px; margin-top:8px;
  font-size:11.5px; color:rgba(255,255,255,.7);
}
.progress-stats span{ display:flex; align-items:center; gap:3px; }
.progress-btn{
  width:100%; margin-top:9px; border:none; cursor:pointer;
  background:rgba(255,255,255,.12); color:#fff;
  font-size:11.5px; font-weight:600; padding:7px; border-radius:9px; transition:.15s;
}
.progress-btn:hover{ background:rgba(255,255,255,.22); }

/* ============ THẺ BÀI KIỂM TRA (trong chat) ============ */
.quiz-card{
  background:var(--white); border:2px solid var(--sky); border-radius:16px;
  padding:14px 16px; width:min(420px,100%);
  box-shadow:0 6px 20px rgba(56,189,248,.18); animation:illusIn .35s ease-out;
}
.quiz-top{
  display:flex; justify-content:space-between; align-items:center;
  font-size:12px; color:var(--ink-soft); margin-bottom:9px;
}
.quiz-top b{ color:var(--ink); font-size:13px; }
.quiz-q{ font-size:14.5px; font-weight:600; line-height:1.5; margin-bottom:11px; color:var(--ink); }
.quiz-opt{
  display:block; width:100%; text-align:left; margin-bottom:7px;
  background:#F4F8FE; border:1.5px solid var(--line); border-radius:11px;
  padding:10px 12px; font-size:13.5px; color:var(--ink);
  cursor:pointer; transition:.15s; font-family:inherit;
}
.quiz-opt:hover:not(:disabled){ border-color:var(--sky); background:#EAF4FE; transform:translateX(2px); }
.quiz-opt:disabled{ cursor:default; }
.quiz-opt.right{ background:#DCFCE7 !important; border-color:var(--leaf) !important; color:#166534; font-weight:600; }
.quiz-opt.wrong{ background:#FEE2E2 !important; border-color:#F87171 !important; color:#B91C1C; }
.quiz-explain{
  margin-top:9px; padding:10px 12px; border-radius:11px;
  background:#FFF8E5; border-left:4px solid var(--sun);
  font-size:13px; line-height:1.55; color:#6B5200;
}
.quiz-next{
  margin-top:10px; width:100%; border:none; cursor:pointer;
  background:linear-gradient(135deg,var(--coral),var(--coral-2)); color:#fff;
  font-weight:700; font-size:13.5px; padding:10px; border-radius:11px; font-family:inherit;
}
.quiz-result{ text-align:center; padding:6px 0; }
.quiz-result .big{ font-size:38px; margin-bottom:6px; }
.quiz-result .score{ font-size:19px; font-weight:800; color:var(--ink); font-family:'Baloo 2',sans-serif; }
.quiz-result .msg2{ font-size:13.5px; color:var(--ink-soft); margin-top:5px; line-height:1.5; }

/* Nút mở bài kiểm tra dưới câu trả lời */
.quiz-start{
  background:linear-gradient(135deg,var(--sun),var(--coral-2)) !important;
  color:#fff !important; font-weight:700 !important;
}

/* ============ HỘP THOẠI (tiến độ / chứng chỉ) ============ */
.modal{
  position:fixed; inset:0; background:rgba(20,30,56,.7);
  display:none; align-items:center; justify-content:center; z-index:900; padding:22px;
}
.modal.open{ display:flex; }
.modal-box{
  background:var(--white); border-radius:20px; padding:22px;
  width:min(560px,100%); max-height:86vh; overflow-y:auto;
  box-shadow:0 24px 70px rgba(0,0,0,.35);
}
.modal-box h3{ font-size:19px; margin-bottom:4px; }
.modal-sub{ font-size:12.5px; color:var(--ink-soft); margin-bottom:14px; }
.modal-close{
  float:right; cursor:pointer; font-size:20px; color:#9FB2D3;
  width:30px; height:30px; display:grid; place-items:center; border-radius:8px;
}
.modal-close:hover{ background:#F0F4FB; color:var(--ink); }

.topic-row{
  display:flex; align-items:center; gap:9px;
  padding:9px 11px; border-radius:11px; margin-bottom:5px;
  background:#F6FAFF; font-size:13.5px;
}
.topic-row.done{ background:#F0FDF4; }
.topic-row .tick{ font-size:15px; width:20px; text-align:center; }
.topic-row .tname{ flex:1; }
.topic-row .tquiz{ font-size:11.5px; color:var(--ink-soft); font-weight:600; }
.topic-row .tgo{
  font-size:11px; padding:4px 9px; border-radius:8px; border:none; cursor:pointer;
  background:var(--sky); color:#fff; font-weight:600; font-family:inherit;
}
.topic-row .tgo:hover{ filter:brightness(1.08); }

/* ============ CHỨNG CHỈ ============ */
.cert{
  border:5px double var(--coral); border-radius:16px; padding:26px 22px;
  text-align:center; background:linear-gradient(160deg,#FFFDF5,#F2F7FE);
}
.cert .seal{ font-size:52px; margin-bottom:6px; }
.cert h2{ font-size:23px; color:var(--coral); margin-bottom:3px; }
.cert .cert-sub{ font-size:12.5px; color:var(--ink-soft); margin-bottom:16px; }
.cert .cert-name{
  font-family:'Baloo 2',sans-serif; font-size:30px; font-weight:800;
  color:var(--ink); margin:10px 0; padding-bottom:7px;
  border-bottom:2.5px dashed var(--sun); display:inline-block;
}
.cert .cert-body{ font-size:14px; line-height:1.75; color:var(--ink); margin-top:12px; }
.cert .cert-foot{
  margin-top:18px; padding-top:12px; border-top:1px solid var(--line);
  font-size:11.5px; color:var(--ink-soft); display:flex; justify-content:space-between;
}
.cert-locked{ text-align:center; padding:22px 10px; }
.cert-locked .lock{ font-size:46px; margin-bottom:10px; }
.cert-locked p{ font-size:13.5px; color:var(--ink-soft); line-height:1.65; }

@media print{
  body *{ visibility:hidden; }
  /* In được CẢ chứng chỉ lẫn báo cáo giáo viên */
  #certArea, #certArea *,
  #printArea, #printArea *{ visibility:visible; }
  #certArea, #printArea{ position:absolute; left:0; top:0; width:100%; }
  /* Bỏ position:fixed khi in, nếu không báo cáo dài sẽ bị cắt cụt ở trang 1 */
  .modal{
    position:static !important; display:block !important;
    background:none !important; padding:0 !important; overflow:visible !important;
  }
  .modal-box{
    box-shadow:none !important; max-height:none !important;
    overflow:visible !important; width:100% !important; padding:0 !important;
  }
  .rep-sec, .badge-item, .rep-tip{ break-inside:avoid; page-break-inside:avoid; }
  .no-print{ display:none !important; }
}

/* ============ BẢNG BIỂN BÁO TRONG CHAT ============ */
.signs-card{
  background:var(--white); border-radius:16px; overflow:hidden;
  box-shadow:0 4px 14px rgba(30,42,74,.09);
  width:min(420px,100%); animation:illusIn .35s ease-out;
}
.signs-grid{
  display:grid; grid-template-columns:repeat(4,1fr); gap:1px;
  background:var(--line); padding:1px;
}
.signs-item{
  background:#fff; padding:9px 5px 7px; text-align:center;
  cursor:pointer; transition:.15s;
}
.signs-item:hover{ background:#FFF6F4; transform:translateY(-1px); }
.signs-item svg{ width:48px; height:48px; display:block; margin:0 auto 4px; }
.signs-item .si-code{ font-size:9px; font-weight:700; color:var(--coral); }
.signs-item .si-name{
  font-size:9.5px; color:var(--ink-soft); line-height:1.25; margin-top:1px;
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
}
.signs-foot{
  padding:10px 13px; font-size:12.5px; font-weight:600; color:var(--sky);
  border-top:1px solid var(--line); cursor:pointer; text-align:center; transition:.15s;
}
.signs-foot:hover{ background:#F0F8FF; }

/* ============ THƯ VIỆN BIỂN BÁO ============ */
.sign-tabs{ display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px; }
.sign-tab{
  background:#F0F5FC; border:1.5px solid var(--line); color:var(--ink-soft);
  font-size:12.5px; font-weight:600; padding:7px 13px; border-radius:999px;
  cursor:pointer; transition:.15s; user-select:none;
}
.sign-tab:hover{ border-color:var(--sky); }
.sign-tab.on{
  background:linear-gradient(135deg,var(--sky),#6C8CFF); color:#fff; border-color:transparent;
}
.sign-note{
  background:#FFF8E5; border-left:4px solid var(--sun); border-radius:10px;
  padding:9px 12px; font-size:12.5px; color:#6B5200; line-height:1.55; margin-bottom:12px;
}
.sign-search{
  width:100%; border:1.5px solid var(--line); border-radius:11px;
  padding:9px 12px; font-size:13.5px; font-family:inherit; color:var(--ink);
  outline:none; margin-bottom:12px;
}
.sign-search:focus{ border-color:var(--sky); }

.sign-grid{
  display:grid; grid-template-columns:repeat(auto-fill,minmax(112px,1fr)); gap:10px;
}
.sign-item{
  background:#F8FBFF; border:1.5px solid var(--line); border-radius:13px;
  padding:9px 7px; text-align:center; cursor:pointer; transition:.15s;
}
.sign-item:hover{
  border-color:var(--coral); background:#FFF6F4;
  transform:translateY(-2px); box-shadow:0 6px 16px rgba(255,107,87,.18);
}
.sign-item svg{ width:62px; height:62px; display:block; margin:0 auto 6px; }
.sign-item img.real{
  width:62px; height:62px; object-fit:contain; display:block;
  margin:0 auto 6px; background:#fff; border-radius:6px;
}
.sign-item .sc{ font-size:10px; font-weight:700; color:var(--coral); letter-spacing:.02em; }
.sign-item .sn{ font-size:11px; color:var(--ink); line-height:1.35; margin-top:2px; }

/* Ảnh thật cỡ lớn ở trang chi tiết */
.sign-detail img.dbig{
  width:170px; height:170px; object-fit:contain;
  margin:6px auto 8px; display:block; cursor:zoom-in;
  background:#fff; border-radius:12px; padding:6px;
  box-shadow:0 4px 14px rgba(30,42,74,.1);
}
.src-note{
  font-size:11px; color:#8CA0C4; margin-bottom:12px;
}

/* Chi tiết 1 biển báo */
.sign-detail{ text-align:center; }
.sign-detail svg{ width:130px; height:130px; margin:6px auto 12px; display:block; }
.sign-detail .dcode{
  display:inline-block; background:var(--coral); color:#fff;
  font-size:12px; font-weight:700; padding:4px 12px; border-radius:999px; margin-bottom:7px;
}
.sign-detail h3{ font-size:19px; margin-bottom:10px; }
.sign-detail .dmean{
  background:#F4F8FE; border-radius:12px; padding:12px 14px;
  font-size:14px; line-height:1.65; color:var(--ink); text-align:left;
}
.sign-photos{ margin-top:14px; }
.sign-photos h4{ font-size:13px; color:var(--ink-soft); margin-bottom:7px; text-align:left; }
.sign-photos .pg{ display:grid; grid-template-columns:repeat(4,1fr); gap:5px; }
.sign-photos img{
  width:100%; height:72px; object-fit:cover; border-radius:9px;
  cursor:zoom-in; background:#E9F1FC;
}

/* ============ THƯ VIỆN LUẬT GIAO THÔNG ============ */
.law-note{
  background:#EEF4FF; border-left:4px solid var(--sky); border-radius:10px;
  padding:9px 12px; font-size:12.5px; color:#2C4B7C; line-height:1.55; margin-bottom:12px;
}
.law-legal{
  background:#FFF8E5; border:1px dashed #F0C96A; border-radius:10px;
  padding:8px 11px; font-size:11.5px; color:#6B5200; line-height:1.5; margin-bottom:12px;
}
.law-list{ display:flex; flex-direction:column; gap:9px; }
.law-item{
  display:flex; gap:11px; align-items:flex-start;
  background:#F8FBFF; border:1.5px solid var(--line); border-radius:14px;
  padding:11px 12px; cursor:pointer; transition:.15s;
}
.law-item:hover{
  border-color:var(--coral); background:#FFF6F4;
  transform:translateY(-2px); box-shadow:0 6px 16px rgba(255,107,87,.16);
}
.law-item .li-ic{
  font-size:22px; line-height:1; flex:0 0 38px; height:38px; border-radius:11px;
  background:#fff; display:flex; align-items:center; justify-content:center;
  box-shadow:0 2px 7px rgba(30,42,74,.08);
}
.law-item .li-body{ flex:1; min-width:0; }
.law-item .li-title{ font-size:14px; font-weight:700; color:var(--ink); line-height:1.35; }
.law-item .li-kid{
  font-size:12px; color:var(--ink-soft); line-height:1.5; margin-top:3px;
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
}
.law-item .li-tags{ display:flex; flex-wrap:wrap; gap:5px; margin-top:7px; }
.law-tag{
  font-size:10px; font-weight:600; padding:3px 8px; border-radius:999px;
  background:#E9F1FC; color:#41639E; line-height:1.4;
}
.law-tag.fine{ background:#FFECE8; color:#C23C24; }
.law-tag.who{ background:#E7F9F1; color:#137A55; }

/* Chi tiết 1 điều luật */
.law-detail .ld-ic{ font-size:40px; text-align:center; margin-bottom:4px; }
.law-detail h3{ font-size:19px; text-align:center; line-height:1.35; margin-bottom:12px; }
.law-sec{ border-radius:13px; padding:12px 14px; margin-bottom:10px; font-size:13.5px; line-height:1.68; }
.law-sec h4{ font-size:12.5px; margin-bottom:5px; letter-spacing:.02em; }
.law-sec.kid { background:#F0F8FF; color:var(--ink); }
.law-sec.kid h4 { color:var(--sky); }
.law-sec.rule{ background:#F6F8FC; color:var(--ink); }
.law-sec.rule h4{ color:#5A6E9B; }
.law-sec.base{ background:#F4F1FB; color:#4A3E77; font-size:12.5px; }
.law-sec.base h4{ color:#7B5CC7; }
.law-sec.fine{ background:#FFF2EE; color:#8C3320; }
.law-sec.fine h4{ color:var(--coral); }

/* ============ HUY HIỆU CHẾ ĐỘ AI ============ */
.ai-badge{
  display:inline-flex; align-items:center; gap:5px;
  font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px;
  cursor:pointer; transition:.15s; white-space:nowrap;
}
.ai-badge.real{ background:linear-gradient(135deg,#34D399,#38BDF8); color:#fff; }
.ai-badge.off{ background:#FFF3CD; color:#7A5C00; border:1px solid #FFD166; }
.ai-badge:hover{ filter:brightness(1.06); transform:translateY(-1px); }

/* ============ CÀI ĐẶT AI ============ */
.cfg-box{ text-align:left; }
.cfg-step{
  background:#F6FAFF; border-radius:12px; padding:11px 14px; margin-bottom:9px;
  font-size:13.5px; line-height:1.6; color:var(--ink);
}
.cfg-step b{ color:var(--coral); }
.cfg-step a{ color:var(--sky); font-weight:600; }
.cfg-input{
  width:100%; border:2px solid var(--line); border-radius:12px;
  padding:11px 13px; font-size:13.5px; font-family:monospace; color:var(--ink);
  outline:none; margin:10px 0;
}
.cfg-input:focus{ border-color:var(--sky); }
.cfg-state{
  border-radius:12px; padding:11px 14px; font-size:13px; line-height:1.55; margin-bottom:12px;
}
.cfg-state.on{ background:#E9FBF1; border-left:4px solid var(--leaf); color:#166534; }
.cfg-state.off{ background:#FFF8E5; border-left:4px solid var(--sun); color:#7A5C00; }
.cfg-warn{ font-size:11.5px; color:#8CA0C4; margin-top:10px; line-height:1.5; }

/* ============ CHỌN NHÂN VẬT AI ============ */
.persona-bar{
  display:flex; gap:6px; padding:10px 22px 0;
  max-width:804px; margin:0 auto; width:100%; overflow-x:auto;
}
.persona-chip{
  display:flex; align-items:center; gap:6px; white-space:nowrap;
  background:var(--white); border:1.5px solid var(--line); border-radius:999px;
  padding:6px 12px; font-size:12.5px; cursor:pointer; transition:.15s; user-select:none;
}
.persona-chip:hover{ border-color:var(--sky); }
.persona-chip.on{
  background:linear-gradient(135deg,var(--sky),#6C8CFF); color:#fff;
  border-color:transparent; font-weight:600;
  box-shadow:0 4px 12px rgba(56,189,248,.35);
}
.persona-chip .pe{ font-size:15px; }

/* ============ GỢI Ý CÂU HỎI TIẾP THEO ============ */
.next-box{ display:flex; flex-wrap:wrap; gap:6px; margin-top:2px; }
.next-chip{
  background:#FFF6E8; border:1.5px dashed var(--sun); color:#8A6A00;
  font-size:12px; padding:6px 11px; border-radius:999px;
  cursor:pointer; transition:.15s; user-select:none;
}
.next-chip:hover{ background:var(--sun); color:#3D2F00; border-style:solid; transform:translateY(-1px); }

/* ============ Ô TÌM KIẾM LỊCH SỬ ============ */
.side-search{
  display:flex; align-items:center; gap:6px; margin-bottom:10px;
  background:rgba(255,255,255,.08); border-radius:10px; padding:7px 10px;
}
.side-search input{
  flex:1; min-width:0; border:none; outline:none; background:transparent;
  color:#fff; font-size:12.5px; font-family:inherit;
}
.side-search input::placeholder{ color:rgba(255,255,255,.42); }

/* ============ ẢNH BÉ GỬI ============ */
.msg-img{
  max-width:230px; border-radius:14px; display:block;
  cursor:zoom-in; box-shadow:0 4px 14px rgba(30,42,74,.18);
}
.img-preview{
  display:none; align-items:center; gap:9px; margin-bottom:8px;
  background:#EAF4FE; border:1.5px solid var(--sky); border-radius:12px; padding:7px 10px;
}
.img-preview.on{ display:flex; }
.img-preview img{ width:42px; height:42px; object-fit:cover; border-radius:8px; }
.img-preview .nm{ flex:1; font-size:12.5px; color:var(--ink); }
.img-preview .rm{
  cursor:pointer; color:#9FB2D3; font-size:16px; padding:3px 7px; border-radius:7px;
}
.img-preview .rm:hover{ background:#fff; color:var(--coral); }

/* --- Thẻ ảnh thật (Wikimedia Commons) --- */
.photo-card{
  background:var(--white); border-radius:16px; overflow:hidden;
  box-shadow:0 4px 14px rgba(30,42,74,.09);
  width:min(420px,100%); animation:illusIn .35s ease-out;
}
.photo-hero{
  width:100%; height:200px; object-fit:cover; display:block;
  background:#E9F1FC; cursor:zoom-in; transition:.2s;
}
.photo-hero:hover{ opacity:.9; }
.photo-thumbs{
  display:grid; grid-template-columns:repeat(4,1fr);
  gap:2px; background:var(--line); border-top:2px solid var(--line);
}
.photo-thumbs img{
  width:100%; height:70px; object-fit:cover; display:block;
  background:#E9F1FC; cursor:zoom-in; transition:.2s;
}
.photo-thumbs img:hover{ opacity:.75; transform:scale(1.04); }
.photo-cap{
  padding:10px 13px; font-size:12.5px; font-weight:600; color:var(--ink);
  border-top:1px solid var(--line);
}
.photo-cap .src{ display:block; font-size:10.5px; font-weight:400; color:#8CA0C4; margin-top:3px; }

/* --- Xem ảnh phóng to --- */
.lightbox{
  position:fixed; inset:0; background:rgba(20,30,56,.88);
  display:none; align-items:center; justify-content:center; z-index:1000; padding:24px;
}
.lightbox.open{ display:flex; }
.lightbox img{ max-width:92%; max-height:82%; border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.5); }
.lightbox .close{
  position:absolute; top:18px; right:24px; color:#fff; font-size:30px; cursor:pointer;
  width:44px; height:44px; display:grid; place-items:center; border-radius:50%;
  background:rgba(255,255,255,.12);
}
.lightbox .close:hover{ background:rgba(255,255,255,.25); }

/* --- Thẻ hình minh hoạ (bấm để xem to) --- */
.illus-card{
  background:var(--white); border-radius:16px; overflow:hidden;
  box-shadow:0 4px 14px rgba(30,42,74,.09);
  width:min(430px,100%); animation:illusIn .35s ease-out;
  cursor:zoom-in; transition:.18s;
}
.illus-card:hover{ box-shadow:0 8px 24px rgba(56,189,248,.25); transform:translateY(-2px); }
@keyframes illusIn{ from{ opacity:0; transform:translateY(8px); } to{ opacity:1; transform:none; } }
.illus-visual{ background:#F6FAFF; padding:6px; }
.illus-card svg{ display:block; width:100%; height:auto; }
.illus-caption{
  padding:10px 13px; font-size:12.5px; font-weight:600; color:var(--ink);
  border-top:1px solid var(--line);
  display:flex; align-items:center; justify-content:space-between; gap:8px;
}
.zoom-hint{ font-size:10.5px; font-weight:400; color:#9FB2D3; white-space:nowrap; }

/* --- Hình minh hoạ phóng to --- */
.illus-zoom{
  background:#fff; border-radius:20px; padding:16px;
  width:min(720px,94vw); max-height:88vh; overflow-y:auto; cursor:default;
  box-shadow:0 24px 70px rgba(0,0,0,.4);
}
.illus-zoom svg{ width:100%; height:auto; display:block; background:#F6FAFF; border-radius:12px; }
.iz-cap{
  margin-top:12px; font-size:16px; font-weight:700; color:var(--ink);
  text-align:center; font-family:'Baloo 2',sans-serif;
}

/* --- Thư viện hình minh hoạ --- */
.gal-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(195px,1fr)); gap:11px; }
.gal-item{
  background:#F8FBFF; border:1.5px solid var(--line); border-radius:14px;
  overflow:hidden; cursor:pointer; transition:.15s;
}
.gal-item:hover{ border-color:var(--sky); transform:translateY(-2px); box-shadow:0 6px 18px rgba(56,189,248,.2); }
.gal-visual{ background:#fff; padding:5px; }
.gal-item svg{ width:100%; height:auto; display:block; }
.gal-cap{
  padding:8px 10px; font-size:11.5px; font-weight:600; color:var(--ink);
  line-height:1.4; border-top:1px solid var(--line);
}
.msg-tool{
  font-size:11.5px; padding:5px 10px; border-radius:999px;
  background:#E9F1FC; color:var(--ink-soft); cursor:pointer; transition:.15s; user-select:none;
}
.msg-tool:hover{ background:#D8E7FA; }
.msg-tool.active{ background:var(--sun) !important; color:var(--ink) !important; }

.typing-dots{ display:inline-flex; gap:5px; align-items:center; padding:5px 2px; }
.typing-dots i{ width:7px; height:7px; border-radius:50%; background:currentColor; opacity:.35; animation:tdots 1s infinite; }
.typing-dots i:nth-child(2){ animation-delay:.15s; }
.typing-dots i:nth-child(3){ animation-delay:.3s; }
@keyframes tdots{ 0%,100%{ opacity:.25; transform:translateY(0); } 50%{ opacity:1; transform:translateY(-3px); } }

.suggest-row{
  display:flex; gap:8px; flex-shrink:0; max-width:804px; margin:0 auto; width:100%;
  padding:0 22px 10px; overflow-x:auto; scrollbar-width:thin;
}
.suggest-row::-webkit-scrollbar{ height:5px; }
.suggest-chip{
  background:var(--white); border:1.5px solid #D9E6F8; color:#3B5384; white-space:nowrap;
  font-size:12.5px; padding:8px 13px; border-radius:999px; cursor:pointer; transition:.15s; user-select:none;
}
.suggest-chip:hover{ border-color:var(--coral); color:var(--coral); background:#FFF4F1; }

.chat-input-wrap{ padding:6px 22px 16px; flex-shrink:0; }
.chat-input-inner{ max-width:760px; margin:0 auto; position:relative; }
.tutor-input{
  display:flex; align-items:center; gap:4px;
  background:var(--white); border:2px solid var(--line); border-radius:18px;
  padding:8px 10px; box-shadow:0 6px 20px rgba(30,42,74,.06); transition:.15s;
}
.tutor-input:focus-within{ border-color:var(--sky); }
.tutor-input input{
  flex:1; min-width:0; border:none; outline:none; background:transparent;
  font-family:inherit; font-size:14.5px; color:var(--ink); padding:0 6px;
}
.tutor-input input::placeholder{ color:#9FB2D3; }
.input-hint{ text-align:center; font-size:11.5px; color:#8CA0C4; margin-top:8px; }

#micBtn.rec{ background:#ff5a5f !important; color:#fff !important; animation:pulse 1s infinite; }
@keyframes pulse{ 0%,100%{ transform:scale(1); } 50%{ transform:scale(1.12); } }

.emoji-pop{
  position:absolute; right:8px; bottom:calc(100% + 8px);
  background:#fff; border-radius:14px; box-shadow:0 8px 30px rgba(0,0,0,.18);
  padding:8px; display:none; gap:2px; flex-wrap:wrap; width:232px; z-index:50;
}
.emoji-pop.open{ display:flex; }
.emoji-pop span{ font-size:20px; padding:6px; cursor:pointer; border-radius:8px; }
.emoji-pop span:hover{ background:#f0f4ff; }

.toast{
  position:fixed; left:50%; bottom:26px; transform:translateX(-50%) translateY(20px);
  background:var(--navy); color:#fff; padding:10px 18px; border-radius:999px;
  font-size:13px; opacity:0; pointer-events:none; transition:.25s; z-index:999;
}
.toast.show{ opacity:1; transform:translateX(-50%) translateY(0); }

@media (max-width:1024px){ .sidebar{ width:240px; } }

/* ---- ĐIỆN THOẠI ---- */
.menu-btn{ display:none; }
.side-backdrop{ display:none; }

@media (max-width:760px){
  /* Sidebar trượt ra từ bên trái khi bấm nút ☰ (trước đây bị ẩn hẳn, không mở lại được) */
  .sidebar{
    position:fixed; left:0; top:0; bottom:0; z-index:950;
    width:270px; transform:translateX(-100%); transition:transform .25s ease;
    box-shadow:6px 0 28px rgba(0,0,0,.28);
  }
  .sidebar.open{ transform:translateX(0); }
  .side-backdrop{
    position:fixed; inset:0; background:rgba(20,30,56,.5); z-index:940;
  }
  .side-backdrop.open{ display:block; }
  .menu-btn{ display:grid; }

  .msg-body{ max-width:88%; }
  .msg.user .msg-body{ max-width:85%; }

  /* Bảng biểu co lại cho vừa màn hình hẹp */
  .rep-grid{ grid-template-columns:repeat(2,1fr); }
  .test-cards{ grid-template-columns:1fr; }
  .signs-grid{ grid-template-columns:repeat(3,1fr); }
  .gal-grid{ grid-template-columns:1fr; }
  .badge-grid{ grid-template-columns:repeat(2,1fr); }
  .modal{ padding:12px; }
  .modal-box{ padding:16px; max-height:92vh; }
  .rep-table{ font-size:11.5px; }
  .rep-table th, .rep-table td{ padding:6px 5px; }
  .top-actions{ gap:3px; }
  .persona-bar, .suggest-row{ padding-left:14px; padding-right:14px; }
}
</style>
</head>
<body>

<div class="page">
  <!-- Thanh này chỉ hiện khi hệ thống có sự cố -->
  <div id="sysbar" class="sysbar"></div>

  <div class="app">
    <aside class="sidebar">
      <div>
        <div class="side-brand"><div class="mark">🤖</div>AI Gia sư</div>
        <a class="side-back" href="sieu-nhi-atgt-ai.html">← Về trang chủ</a>
      </div>
      <button class="btn btn-primary-sm" style="width:100%; justify-content:center; margin-bottom:14px;" onclick="newChat()">＋ Cuộc trò chuyện mới</button>

      <!-- Bảng tiến độ học tập -->
      <div class="progress-box">
        <div class="progress-head">
          <span>Tiến độ học</span>
          <b id="pgText">0/23</b>
        </div>
        <div class="progress-bar"><i id="pgBar"></i></div>
        <div class="progress-stats">
          <span>⭐ <b id="pgStars">0</b></span>
          <span>🏅 <b id="pgBadges">0</b></span>
          <span>🎖️ Cấp <b id="pgLevel">1</b></span>
          <span>💎 <b id="pgPoints">0</b></span>
        </div>
        <button class="progress-btn" onclick="openMap()">🗺️ Chơi "Đường đến trường"</button>
        <button class="progress-btn" style="margin-top:5px" onclick="openBadges()">🏅 Huy hiệu & Bài kiểm tra</button>
        <button class="progress-btn" style="margin-top:5px" onclick="openProgress()">📊 Xem chi tiết tiến độ</button>
      </div>

      <div class="side-search">
        <span>🔍</span>
        <input id="searchBox" type="text" placeholder="Tìm trong lịch sử chat..."
               oninput="onSearch(this.value)">
      </div>
      <div id="sessionList" class="session-list"></div>

      <div class="side-divider"></div>
      <div class="side-link" onclick="openMap()"><span class="ic">🗺️</span> Game: Đường đến trường</div>
      <div class="side-link" onclick="showPersonalized()"><span class="ic">🎯</span> Bài ôn tập riêng</div>
      <div class="side-link" onclick="openReport()"><span class="ic">👩‍🏫</span> Báo cáo giáo viên</div>
      <div class="side-link" onclick="openGallery()"><span class="ic">🖼️</span> Thư viện hình minh hoạ</div>
      <div class="side-link" onclick="openSigns()"><span class="ic">🚸</span> Thư viện biển báo</div>
      <div class="side-link" onclick="openLaws()"><span class="ic">📜</span> Luật giao thông Việt Nam</div>
      <a class="side-link" href="ai-camera.html"><span class="ic">📷</span> AI Camera</a>
      <a class="side-link" href="ai-mo-phong.html"><span class="ic">🚦</span> Mô phỏng</a>
      <a class="side-link" href="ai-truyen-tranh.html"><span class="ic">📖</span> Truyện tranh</a>
      <a class="side-link" href="game-mini.html"><span class="ic">🎮</span> Game Mini</a>
      <a class="side-link" href="dashboard-hoc-sinh.html"><span class="ic">🎒</span> Dashboard học sinh</a>

      <div class="sidebar-foot">
        <div class="av">🧒</div>
        <div class="txt"><b><?php echo htmlspecialchars($fullname); ?></b><span>Lớp 3 · Cấp độ 7</span></div>
      </div>
    </aside>

    <!-- Nền mờ khi mở sidebar trên điện thoại -->
    <div id="sideBackdrop" class="side-backdrop" onclick="toggleSidebar(false)"></div>

    <div class="chat-col">
      <div class="chat-top">
        <h2>
          <span class="icon-btn menu-btn" title="Menu" onclick="toggleSidebar()">☰</span>
          <span class="status-dot"></span> AI Gia sư
          <span id="aiBadge" class="ai-badge off" onclick="openAiSetup()">⚙️ Đang tải...</span>
        </h2>
        <div class="top-actions">
          <div class="icon-btn" title="Game: Đường đến trường" onclick="openMap()">🗺️</div>
          <div class="icon-btn" title="Huy hiệu & Bài kiểm tra" onclick="openBadges()">🏅</div>
          <div class="icon-btn" title="Báo cáo cho giáo viên/phụ huynh" onclick="openReport()">👩‍🏫</div>
          <div class="icon-btn" title="Thư viện hình minh hoạ" onclick="openGallery()">🖼️</div>
          <div class="icon-btn" title="Thư viện biển báo giao thông" onclick="openSigns()">🚸</div>
          <div class="icon-btn" title="Luật giao thông Việt Nam" onclick="openLaws()">📜</div>
          <div class="icon-btn" title="Bảng tiến độ học" onclick="openProgress()">📊</div>
          <div class="icon-btn" title="Chứng chỉ hoàn thành" onclick="openCert()">🏆</div>
          <div class="icon-btn" title="Xuất báo cáo trò chuyện" onclick="exportChat()">📄</div>
          <div class="icon-btn" title="Cài đặt AI thật (Gemini)" onclick="openAiSetup()">⚙️</div>
        </div>
      </div>

      <div class="chat-scroll" id="chatScroll">
        <div class="chat-inner" id="chatInner"></div>
      </div>

      <!-- Chọn nhân vật AI -->
      <div class="persona-bar" id="personaBar"></div>

      <div class="suggest-row">
        <div class="suggest-chip" onclick="askSuggested(this)">Đèn giao thông có mấy màu?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Đội mũ như thế nào là đúng?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Các loại biển báo giao thông?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Điểm mù của xe tải là gì?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Con nên làm gì khi gặp xe cứu thương?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Vạch kẻ đường dành cho ai?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Bóng lăn ra đường thì làm sao?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Con bị lạc thì phải làm gì?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Đi xe buýt cần lưu ý gì?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Trời mưa đi đường thế nào?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Đi bộ ban đêm có nguy hiểm không?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Qua đường sắt cần chú ý gì?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Luật quy định thế nào về người đi bộ?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Bao nhiêu tuổi mới được lái xe máy?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Đi xe đạp điện có bắt buộc đội mũ không?</div>
        <div class="suggest-chip" onclick="askSuggested(this)">Vượt đèn đỏ bị phạt bao nhiêu ạ?</div>
      </div>

      <div class="chat-input-wrap">
        <div class="chat-input-inner">
          <!-- Ảnh bé sắp gửi cho AI nhận diện -->
          <div class="img-preview" id="imgPreview">
            <img id="imgThumb" alt="">
            <span class="nm">📷 Ảnh đã chọn — bấm ➤ để AI xem giúp con</span>
            <span class="rm" onclick="clearImage()">✕</span>
          </div>

          <input type="file" id="fileInput" accept="image/*" style="display:none" onchange="onPickImage(this)">
          <input type="file" id="camInput" accept="image/*" capture="environment" style="display:none" onchange="onPickImage(this)">

          <div class="tutor-input">
            <button class="icon-btn-sm" title="Gửi ảnh biển báo cho AI xem" onclick="document.getElementById('fileInput').click()">📎</button>
            <button class="icon-btn-sm" title="Chụp ảnh biển báo" onclick="document.getElementById('camInput').click()">📷</button>
            <input id="chatText" type="text" placeholder="Hỏi AI Gia sư điều gì đó về giao thông..." onkeydown="if(event.key==='Enter')sendMsg();">
            <button class="icon-btn-sm" title="Emoji" data-emoji-btn onclick="toggleEmoji(event)">😊</button>
            <button class="icon-btn-sm" id="micBtn" title="Nói chuyện với AI" onclick="toggleMic(this)">🎤</button>
            <button class="icon-btn-sm send" title="Gửi" onclick="sendMsg()">➤</button>
          </div>
          <div id="emojiPop" class="emoji-pop"></div>
          <div class="input-hint">AI Gia sư có thể trả lời bằng văn bản và giọng nói — bấm 🔊 dưới mỗi câu trả lời để nghe.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Hộp thoại: Bảng tiến độ học -->
<div id="progressModal" class="modal">
  <div class="modal-box">
    <div class="modal-close" onclick="closeModal('progressModal')">✕</div>
    <h3>📊 Bảng tiến độ học tập</h3>
    <div class="modal-sub" id="pgSummary">Đang tải...</div>
    <div id="topicList"></div>
  </div>
</div>

<!-- Hộp thoại: Huy hiệu & Bài kiểm tra -->
<div id="badgeModal" class="modal">
  <div class="modal-box">
    <div class="modal-close" onclick="closeModal('badgeModal')">✕</div>
    <div id="badgeArea"></div>
  </div>
</div>

<!-- Hộp thoại: Cài đặt AI -->
<div id="aiModal" class="modal">
  <div class="modal-box">
    <div class="modal-close" onclick="closeModal('aiModal')">✕</div>
    <div id="aiArea"></div>
  </div>
</div>

<!-- Hộp thoại: Thư viện biển báo -->
<div id="signModal" class="modal">
  <div class="modal-box">
    <div class="modal-close" onclick="closeModal('signModal')">✕</div>
    <div id="signArea"></div>
  </div>
</div>

<!-- Hộp thoại: Luật giao thông Việt Nam -->
<div id="lawModal" class="modal">
  <div class="modal-box">
    <div class="modal-close" onclick="closeModal('lawModal')">✕</div>
    <div id="lawArea"></div>
  </div>
</div>

<!-- Hộp thoại: Chứng chỉ -->
<div id="certModal" class="modal">
  <div class="modal-box">
    <div class="modal-close no-print" onclick="closeModal('certModal')">✕</div>
    <div id="certArea"></div>
  </div>
</div>

<script>
const STUDENT_NAME = <?php echo json_encode($fullname, JSON_UNESCAPED_UNICODE); ?>;
const API = window.location.pathname;   // API nằm ngay trong chính file này
let currentSessionId = 0, sending = false, chatLog = [];

document.addEventListener('DOMContentLoaded', () => {
  buildEmojiPop();
  checkSystem();      // kiểm tra CSDL trước
  loadAiCfg();        // AI đang chạy chế độ nào?
  loadProgress();     // tải tiến độ học
  checkBadges(true);  // nạp huy hiệu (không hiện thông báo lúc mới vào)
  suggestPreTest();   // chưa làm bài đầu vào → mời bé làm
  loadSessions();
  newChat(false);
});

/* ---------- Gọi API an toàn: luôn báo được lỗi thật ---------- */
async function callApi(url, options) {
  const res  = await fetch(url, options);
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch (e) {
    // Máy chủ trả về HTML/lỗi PHP thay vì JSON → báo nguyên văn để dễ sửa
    throw new Error('Máy chủ trả về dữ liệu lạ (mã ' + res.status + '): '
                    + text.replace(/<[^>]+>/g, ' ').trim().slice(0, 200));
  }
}

/* ---------- Kiểm tra hệ thống ---------- */
async function checkSystem(showOk) {
  const bar = document.getElementById('sysbar');
  try {
    const d = await callApi(API + '?action=ping');
    if (d.status === 'success') {
      bar.classList.remove('show');
      if (d.personas) buildPersonaBar(d.personas);   // nạp 4 nhân vật
      if (showOk) toast('✅ CSDL: ' + d.db + ' · AI: ' + d.ai + ' · Nhận diện ảnh: ' + d.vision);
    } else {
      bar.innerHTML = '⚠️ <b>Có sự cố:</b> ' + esc(d.message);
      bar.classList.add('show');
    }
  } catch (e) {
    bar.innerHTML = '⚠️ <b>Không gọi được máy chủ.</b> ' + esc(e.message)
      + '<br>Kiểm tra: Apache đã bật chưa? Địa chỉ có bắt đầu bằng <b>http://localhost/</b> không (không phải file:///)?';
    bar.classList.add('show');
  }
}

/* =====================================================================
   BỘ HÌNH MINH HOẠ — vẽ bằng SVG ngay trong trang
   (không cần file ảnh, không cần internet)
   ===================================================================== */
/* =====================================================================
   BỘ HÌNH MINH HOẠ NÂNG CẤP
   • Có NHÃN CHÚ THÍCH tiếng Việt ngay trên hình
   • Có CHUYỂN ĐỘNG (đèn nhấp nháy, bé bước đi...)
   • Bấm vào để XEM TO
   ===================================================================== */

/* Đèn giao thông — có nhãn từng màu, đèn đang bật thì nhấp nháy */
function denGiaoThong(sang){   // 'do' | 'vang' | 'xanh' | 'all'
  const on = m => (sang === m || sang === 'all');
  const lamp = (y, color, m, label, sub) => `
    <circle cx="72" cy="${y}" r="21"
            fill="${on(m) ? color : '#3A4358'}"
            stroke="${color}" stroke-width="${on(m) ? 7 : 0}" stroke-opacity="0.3">
      ${on(m) ? `<animate attributeName="stroke-opacity" values="0.15;0.45;0.15" dur="1.6s" repeatCount="indefinite"/>` : ''}
    </circle>
    <text x="108" y="${y - 2}" font-size="15" font-weight="700"
          fill="${on(m) ? 'var(--ink,#243B6B)' : '#A9B6CE'}" font-family="sans-serif">${label}</text>
    <text x="108" y="${y + 14}" font-size="11.5"
          fill="${on(m) ? '#5C7099' : '#C3CDDF'}" font-family="sans-serif">${sub}</text>`;

  return `<svg viewBox="0 0 300 190" xmlns="http://www.w3.org/2000/svg" role="img"
               aria-label="Đèn giao thông ba màu">
    <rect x="40" y="14" width="64" height="150" rx="16" fill="#2C3550"/>
    <rect x="66" y="164" width="12" height="18" fill="#8A94A8"/>
    ${lamp(45,  '#EF4444', 'do',   'ĐỎ',   'Dừng lại!')}
    ${lamp(89,  '#FBBF24', 'vang', 'VÀNG', 'Chuẩn bị dừng')}
    ${lamp(133, '#22C55E', 'xanh', 'XANH', 'Được đi (vẫn quan sát)')}
  </svg>`;
}

const ILLUS = {
  'den-do':   { svg: denGiaoThong('do'),   cap: '🔴 Đèn đỏ — Dừng lại hoàn toàn!' },
  'den-vang': { svg: denGiaoThong('vang'), cap: '🟡 Đèn vàng — Chuẩn bị dừng, KHÔNG phải đi nhanh' },
  'den-xanh': { svg: denGiaoThong('xanh'), cap: '🟢 Đèn xanh — Được đi, nhưng vẫn phải quan sát' },
  'den-3-mau':{ svg: denGiaoThong('all'),  cap: '🚦 Ba màu đèn giao thông — bấm để xem to' },

  /* Mũ bảo hiểm — 3 bước đội mũ đúng cách */
  'mu-bao-hiem': {
    cap: '⛑️ Ba bước đội mũ đúng: Đội — Cài — Khít',
    svg: `<svg viewBox="0 0 340 190" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ba bước đội mũ bảo hiểm đúng cách">
      <!-- Bước 1 -->
      <circle cx="55" cy="72" r="26" fill="#FFD9B8"/>
      <path d="M29 70 A26 26 0 0 1 81 70 Z" fill="#FF6B57"/>
      <rect x="29" y="70" width="52" height="7" rx="3" fill="#E8543F"/>
      <path d="M32 82 h46" stroke="#34D399" stroke-width="2.5" stroke-dasharray="4 3"/>
      <circle cx="46" cy="80" r="2.5" fill="#2C3550"/><circle cx="64" cy="80" r="2.5" fill="#2C3550"/>
      <circle cx="55" cy="122" r="13" fill="#38BDF8"/>
      <text x="55" y="127" font-size="15" font-weight="800" fill="#fff" text-anchor="middle" font-family="sans-serif">1</text>
      <text x="55" y="152" font-size="12" font-weight="700" fill="#243B6B" text-anchor="middle" font-family="sans-serif">ĐỘI</text>
      <text x="55" y="167" font-size="10" fill="#5C7099" text-anchor="middle" font-family="sans-serif">Ngang lông mày</text>

      <!-- Bước 2 -->
      <circle cx="170" cy="72" r="26" fill="#FFD9B8"/>
      <path d="M144 70 A26 26 0 0 1 196 70 Z" fill="#FF6B57"/>
      <rect x="144" y="70" width="52" height="7" rx="3" fill="#E8543F"/>
      <path d="M150 80 L164 104 M190 80 L176 104" stroke="#2C3550" stroke-width="3.5" stroke-linecap="round"/>
      <rect x="162" y="101" width="16" height="7" rx="3.5" fill="#2C3550"/>
      <circle cx="161" cy="80" r="2.5" fill="#2C3550"/><circle cx="179" cy="80" r="2.5" fill="#2C3550"/>
      <circle cx="170" cy="122" r="13" fill="#38BDF8"/>
      <text x="170" y="127" font-size="15" font-weight="800" fill="#fff" text-anchor="middle" font-family="sans-serif">2</text>
      <text x="170" y="152" font-size="12" font-weight="700" fill="#243B6B" text-anchor="middle" font-family="sans-serif">CÀI QUAI</text>
      <text x="170" y="167" font-size="10" fill="#5C7099" text-anchor="middle" font-family="sans-serif">Kêu "cách"</text>

      <!-- Bước 3 -->
      <circle cx="285" cy="72" r="26" fill="#FFD9B8"/>
      <path d="M259 70 A26 26 0 0 1 311 70 Z" fill="#FF6B57"/>
      <rect x="259" y="70" width="52" height="7" rx="3" fill="#E8543F"/>
      <path d="M265 80 L279 104 M305 80 L291 104" stroke="#2C3550" stroke-width="3.5" stroke-linecap="round"/>
      <rect x="277" y="101" width="16" height="7" rx="3.5" fill="#2C3550"/>
      <!-- 2 ngón tay -->
      <rect x="296" y="96" width="5" height="16" rx="2.5" fill="#FFD9B8" stroke="#E8B98F" stroke-width="1"/>
      <rect x="303" y="96" width="5" height="16" rx="2.5" fill="#FFD9B8" stroke="#E8B98F" stroke-width="1"/>
      <circle cx="285" cy="122" r="13" fill="#34D399"/>
      <path d="M279 122 l4 4 l8 -8" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
      <text x="285" y="152" font-size="12" font-weight="700" fill="#243B6B" text-anchor="middle" font-family="sans-serif">KIỂM TRA</text>
      <text x="285" y="167" font-size="10" fill="#5C7099" text-anchor="middle" font-family="sans-serif">Vừa 2 ngón tay</text>
    </svg>`
  },

  /* Sang đường — quy tắc Dừng · Nhìn · Nghe · Đi, bé bước trên vạch */
  'vach-ke': {
    cap: '🚸 Quy tắc vàng: Dừng — Nhìn — Nghe — Đi',
    svg: `<svg viewBox="0 0 320 190" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Sang đường an toàn trên vạch kẻ">
      <rect x="0" y="42" width="320" height="96" fill="#5B6478"/>
      <rect x="0" y="36" width="320" height="7" fill="#93A0B5"/>
      <rect x="0" y="137" width="320" height="7" fill="#93A0B5"/>
      <g fill="#fff">
        <rect x="96" y="45" width="15" height="90" rx="2"/>
        <rect x="121" y="45" width="15" height="90" rx="2"/>
        <rect x="146" y="45" width="15" height="90" rx="2"/>
        <rect x="171" y="45" width="15" height="90" rx="2"/>
      </g>
      <!-- Bé đi bộ, có chuyển động nhẹ -->
      <g>
        <animateTransform attributeName="transform" type="translate"
                          values="0 0; 14 0; 0 0" dur="3.4s" repeatCount="indefinite"/>
        <circle cx="132" cy="64" r="11" fill="#FFD9B8"/>
        <rect x="123" y="76" width="18" height="25" rx="7" fill="#38BDF8"/>
        <path d="M126 101 l-5 16 M138 101 l5 16" stroke="#2C3550" stroke-width="4" stroke-linecap="round"/>
        <path d="M123 84 l-11 8 M141 84 l11 -13" stroke="#FFD9B8" stroke-width="4.5" stroke-linecap="round"/>
      </g>
      <!-- Nhãn 4 bước -->
      <rect x="8" y="152" width="70" height="30" rx="8" fill="#FFF1E8" stroke="#FF6B57" stroke-width="1.5"/>
      <text x="43" y="166" font-size="11" font-weight="700" fill="#E8543F" text-anchor="middle" font-family="sans-serif">1. DỪNG</text>
      <text x="43" y="177" font-size="9" fill="#8A6A55" text-anchor="middle" font-family="sans-serif">ở mép vỉa hè</text>

      <rect x="84" y="152" width="70" height="30" rx="8" fill="#EAF6FF" stroke="#38BDF8" stroke-width="1.5"/>
      <text x="119" y="166" font-size="11" font-weight="700" fill="#1E7FB8" text-anchor="middle" font-family="sans-serif">2. NHÌN</text>
      <text x="119" y="177" font-size="9" fill="#5C7099" text-anchor="middle" font-family="sans-serif">trái – phải – trái</text>

      <rect x="160" y="152" width="70" height="30" rx="8" fill="#FFF8E0" stroke="#FFD166" stroke-width="1.5"/>
      <text x="195" y="166" font-size="11" font-weight="700" fill="#8A6A00" text-anchor="middle" font-family="sans-serif">3. NGHE</text>
      <text x="195" y="177" font-size="9" fill="#8A7A55" text-anchor="middle" font-family="sans-serif">tiếng xe, còi</text>

      <rect x="236" y="152" width="76" height="30" rx="8" fill="#E9FBF1" stroke="#34D399" stroke-width="1.5"/>
      <text x="274" y="166" font-size="11" font-weight="700" fill="#177C4F" text-anchor="middle" font-family="sans-serif">4. ĐI</text>
      <text x="274" y="177" font-size="9" fill="#4E7D68" text-anchor="middle" font-family="sans-serif">đều bước, không chạy</text>

      <text x="255" y="30" font-size="11" font-weight="700" fill="#243B6B" font-family="sans-serif">Nắm tay người lớn 🤝</text>
    </svg>`
  },

  /* Điểm mù xe tải — bài học cứu mạng */
  'diem-mu': {
    cap: '🚛 Điểm mù — vùng đỏ là nơi bác tài KHÔNG nhìn thấy con',
    svg: `<svg viewBox="0 0 320 190" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Các điểm mù nguy hiểm quanh xe tải">
      <!-- Vùng điểm mù (đỏ nhạt) -->
      <rect x="20" y="30" width="130" height="130" fill="#EF4444" opacity=".16"/>
      <rect x="230" y="30" width="70" height="130" fill="#EF4444" opacity=".16"/>
      <rect x="150" y="130" width="80" height="35" fill="#EF4444" opacity=".16"/>
      <!-- Xe tải nhìn từ trên xuống -->
      <rect x="150" y="42" width="80" height="90" rx="7" fill="#E9EFF8" stroke="#B8C6DC" stroke-width="2"/>
      <rect x="150" y="30" width="80" height="24" rx="6" fill="#4A5F8F"/>
      <rect x="158" y="35" width="64" height="12" rx="3" fill="#9FD5F5"/>
      <text x="190" y="95" font-size="12" font-weight="700" fill="#5C7099" text-anchor="middle" font-family="sans-serif">XE TẢI</text>
      <!-- Gương -->
      <rect x="141" y="34" width="10" height="6" rx="2" fill="#2C3550"/>
      <rect x="229" y="34" width="10" height="6" rx="2" fill="#2C3550"/>
      <!-- Bé đứng trong điểm mù -->
      <circle cx="80" cy="88" r="9" fill="#FFD9B8"/>
      <rect x="73" y="98" width="14" height="19" rx="5" fill="#FF6B57"/>
      <path d="M76 117 l-4 12 M84 117 l4 12" stroke="#2C3550" stroke-width="3" stroke-linecap="round"/>
      <text x="80" y="146" font-size="10.5" font-weight="700" fill="#B91C1C" text-anchor="middle" font-family="sans-serif">Bác tài KHÔNG</text>
      <text x="80" y="158" font-size="10.5" font-weight="700" fill="#B91C1C" text-anchor="middle" font-family="sans-serif">nhìn thấy con!</text>
      <!-- Nhãn -->
      <text x="45" y="24" font-size="11" font-weight="700" fill="#B91C1C" font-family="sans-serif">⚠️ ĐIỂM MÙ</text>
      <text x="252" y="24" font-size="11" font-weight="700" fill="#B91C1C" font-family="sans-serif">⚠️ ĐIỂM MÙ</text>
      <text x="190" y="180" font-size="11" font-weight="700" fill="#243B6B" text-anchor="middle" font-family="sans-serif">"Không thấy gương → bác không thấy con"</text>
    </svg>`
  },

  /* Ban đêm — áo sáng màu giúp thấy xa gấp nhiều lần */
  'ban-dem': {
    cap: '🌙 Áo sáng màu → tài xế thấy con từ xa gấp 4–5 lần',
    svg: `<svg viewBox="0 0 320 190" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="So sánh áo tối màu và áo sáng màu ban đêm">
      <rect x="0" y="0" width="320" height="190" fill="#1B2540"/>
      <!-- Hàng trên: áo tối -->
      <text x="10" y="22" font-size="11.5" font-weight="700" fill="#F87171" font-family="sans-serif">✗ Áo TỐI màu — chỉ thấy từ 25m</text>
      <rect x="10" y="30" width="240" height="4" rx="2" fill="#3A4358"/>
      <circle cx="248" cy="52" r="8" fill="#5B6478"/>
      <rect x="242" y="61" width="13" height="17" rx="4" fill="#3A4358"/>
      <path d="M28 60 L52 46 L52 74 Z" fill="#FBBF24" opacity=".5"/>
      <rect x="14" y="52" width="18" height="16" rx="4" fill="#93A0B5"/>
      <path d="M52 60 h178" stroke="#F87171" stroke-width="1.5" stroke-dasharray="5 4"/>
      <text x="140" y="52" font-size="10" fill="#F87171" text-anchor="middle" font-family="sans-serif">quá gần — không kịp phanh!</text>

      <!-- Hàng dưới: áo sáng -->
      <text x="10" y="118" font-size="11.5" font-weight="700" fill="#34D399" font-family="sans-serif">✓ Áo SÁNG màu — thấy từ hơn 100m</text>
      <rect x="10" y="126" width="300" height="4" rx="2" fill="#3A4358"/>
      <circle cx="290" cy="150" r="8" fill="#FFD9B8"/>
      <rect x="283" y="159" width="15" height="19" rx="4" fill="#FFD166">
        <animate attributeName="opacity" values="1;.55;1" dur="1.4s" repeatCount="indefinite"/>
      </rect>
      <path d="M28 158 L58 140 L58 176 Z" fill="#FBBF24" opacity=".55"/>
      <rect x="14" y="150" width="18" height="16" rx="4" fill="#93A0B5"/>
      <path d="M58 158 h218" stroke="#34D399" stroke-width="1.5" stroke-dasharray="5 4"/>
      <text x="165" y="150" font-size="10" fill="#34D399" text-anchor="middle" font-family="sans-serif">nhìn thấy sớm — an toàn ✓</text>
    </svg>`
  },

  /* Xe cứu thương */
  'cuu-thuong': {
    cap: '🚑 Nghe còi hú → nhường đường ngay',
    svg: `<svg viewBox="0 0 300 170" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Nhường đường cho xe cứu thương">
      <rect x="0" y="118" width="300" height="52" fill="#5B6478"/>
      <rect x="0" y="112" width="300" height="6" fill="#93A0B5"/>
      <rect x="36" y="58" width="120" height="60" rx="9" fill="#fff" stroke="#D9E6F8" stroke-width="2"/>
      <rect x="36" y="99" width="120" height="19" fill="#EF4444"/>
      <path d="M156 72 h24 l16 22 v24 h-40 Z" fill="#EEF4FC" stroke="#D9E6F8" stroke-width="2"/>
      <rect x="159" y="76" width="22" height="15" rx="3" fill="#9FD5F5"/>
      <rect x="80" y="68" width="32" height="10" rx="2" fill="#EF4444"/>
      <rect x="91" y="57" width="10" height="32" rx="2" fill="#EF4444"/>
      <rect x="66" y="46" width="34" height="12" rx="6" fill="#3B82F6">
        <animate attributeName="fill" values="#3B82F6;#EF4444;#3B82F6" dur="0.9s" repeatCount="indefinite"/>
      </rect>
      <path d="M56 36 l7 5 M110 36 l-7 5" stroke="#FBBF24" stroke-width="3" stroke-linecap="round">
        <animate attributeName="opacity" values="1;.2;1" dur="0.9s" repeatCount="indefinite"/>
      </path>
      <circle cx="70" cy="120" r="14" fill="#2C3550"/><circle cx="70" cy="120" r="5" fill="#93A0B5"/>
      <circle cx="160" cy="120" r="14" fill="#2C3550"/><circle cx="160" cy="120" r="5" fill="#93A0B5"/>
      <!-- Bé đứng gọn trên vỉa hè -->
      <circle cx="252" cy="78" r="10" fill="#FFD9B8"/>
      <rect x="244" y="89" width="16" height="21" rx="6" fill="#34D399"/>
      <path d="M247 110 l-4 8 M257 110 l4 8" stroke="#2C3550" stroke-width="3" stroke-linecap="round"/>
      <text x="252" y="136" font-size="10" font-weight="700" fill="#177C4F" text-anchor="middle" font-family="sans-serif">Đứng gọn</text>
      <text x="252" y="148" font-size="10" font-weight="700" fill="#177C4F" text-anchor="middle" font-family="sans-serif">vỉa hè ✓</text>
      <text x="20" y="28" font-size="12" font-weight="700" fill="#243B6B" font-family="sans-serif">🔊 Còi hú — xe đang cứu người!</text>
    </svg>`
  },

  /* Biển STOP */
  'bien-stop': {
    cap: '🛑 Biển STOP — dừng HẲN, không phải chạy chậm',
    svg: `<svg viewBox="0 0 260 170" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Biển báo STOP tám cạnh">
      <rect x="118" y="105" width="10" height="55" fill="#8A94A8"/>
      <polygon points="86,18 146,18 174,46 174,86 146,114 86,114 58,86 58,46"
               fill="#E03131" stroke="#fff" stroke-width="6"/>
      <text x="116" y="76" font-size="30" font-weight="900" fill="#fff" text-anchor="middle" font-family="Arial Black, Arial, sans-serif">STOP</text>
      <path d="M186 50 h44 m-9 -8 l9 8 l-9 8" stroke="#E03131" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
      <text x="208" y="34" font-size="11" font-weight="700" fill="#E03131" text-anchor="middle" font-family="sans-serif">8 cạnh</text>
      <text x="208" y="72" font-size="10" fill="#5C7099" text-anchor="middle" font-family="sans-serif">hình độc nhất</text>
      <text x="208" y="85" font-size="10" fill="#5C7099" text-anchor="middle" font-family="sans-serif">→ nhìn là biết</text>
      <text x="116" y="150" font-size="12" font-weight="700" fill="#243B6B" text-anchor="middle" font-family="sans-serif">Dừng HẲN — bánh xe đứng yên</text>
    </svg>`
  },

  /* Xe đạp */
  'xe-dap': {
    cap: '🚲 Sát lề — Hàng một — Xin đường — Đội mũ',
    svg: `<svg viewBox="0 0 300 175" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Đi xe đạp an toàn">
      <rect x="0" y="128" width="300" height="47" fill="#5B6478"/>
      <rect x="0" y="122" width="300" height="6" fill="#93A0B5"/>
      <path d="M0 150 h300" stroke="#FFD166" stroke-width="3" stroke-dasharray="16 10"/>
      <circle cx="72" cy="118" r="27" fill="none" stroke="#2C3550" stroke-width="5"/>
      <circle cx="168" cy="118" r="27" fill="none" stroke="#2C3550" stroke-width="5"/>
      <circle cx="72" cy="118" r="4" fill="#2C3550"/><circle cx="168" cy="118" r="4" fill="#2C3550"/>
      <path d="M72 118 L108 118 L132 80 L168 118 M108 118 L132 80 M132 80 L148 80"
            stroke="#FF6B57" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M108 118 V74" stroke="#FF6B57" stroke-width="5" stroke-linecap="round"/>
      <path d="M98 72 h20" stroke="#2C3550" stroke-width="4" stroke-linecap="round"/>
      <circle cx="120" cy="46" r="13" fill="#FFD9B8"/>
      <path d="M107 44 A13 13 0 0 1 133 44 Z" fill="#38BDF8"/>
      <rect x="113" y="59" width="15" height="18" rx="5" fill="#34D399"/>
      <path d="M116 77 l-9 13" stroke="#FFD9B8" stroke-width="4" stroke-linecap="round"/>
      <!-- Tay xin đường -->
      <path d="M128 64 l26 -10" stroke="#FFD9B8" stroke-width="4.5" stroke-linecap="round"/>
      <text x="196" y="52" font-size="11" font-weight="700" fill="#177C4F" font-family="sans-serif">✋ Xin đường</text>
      <text x="196" y="66" font-size="10" fill="#5C7099" font-family="sans-serif">trước 5–10m</text>
      <text x="40" y="30" font-size="11" font-weight="700" fill="#177C4F" font-family="sans-serif">⛑️ Đội mũ</text>
      <text x="196" y="163" font-size="11" font-weight="700" fill="#FFD166" font-family="sans-serif">Đi sát lề phải</text>
    </svg>`
  },

  /* Dây an toàn */
  'day-an-toan': {
    cap: '🚗 Lên xe — Thắt dây — Mới đi',
    svg: `<svg viewBox="0 0 260 175" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Thắt dây an toàn khi ngồi ô tô">
      <path d="M58 26 h60 a11 11 0 0 1 11 11 v82 h-82 v-82 a11 11 0 0 1 11 -11 Z" fill="#4A5F8F"/>
      <rect x="46" y="117" width="92" height="17" rx="7" fill="#3B4E75"/>
      <circle cx="92" cy="56" r="18" fill="#FFD9B8"/>
      <circle cx="85" cy="54" r="2.6" fill="#2C3550"/><circle cx="99" cy="54" r="2.6" fill="#2C3550"/>
      <path d="M85 64 q7 6 14 0" stroke="#2C3550" stroke-width="2.6" fill="none" stroke-linecap="round"/>
      <rect x="75" y="76" width="34" height="38" rx="11" fill="#FFD166"/>
      <path d="M73 74 L116 112" stroke="#E03131" stroke-width="7" stroke-linecap="round"/>
      <rect x="107" y="105" width="15" height="13" rx="3" fill="#2C3550"/>
      <circle cx="180" cy="42" r="18" fill="#34D399"/>
      <path d="M171 42 l6 7 l12 -13" stroke="#fff" stroke-width="3.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
      <text x="212" y="46" font-size="12" font-weight="700" fill="#177C4F" text-anchor="middle" font-family="sans-serif">ĐÚNG</text>
      <text x="150" y="90" font-size="11" font-weight="700" fill="#243B6B" font-family="sans-serif">Trẻ em ngồi</text>
      <text x="150" y="105" font-size="11" font-weight="700" fill="#243B6B" font-family="sans-serif">GHẾ SAU</text>
      <text x="150" y="122" font-size="10" fill="#5C7099" font-family="sans-serif">(túi khí ghế trước</text>
      <text x="150" y="134" font-size="10" fill="#5C7099" font-family="sans-serif">bung rất mạnh)</text>
      <text x="92" y="160" font-size="11.5" font-weight="700" fill="#E8543F" text-anchor="middle" font-family="sans-serif">Đi gần cũng phải thắt dây!</text>
    </svg>`
  },

  /* Các chủ đề chỉ dùng ảnh thật (không có hình vẽ) */
  'canh-sat':   { svg:'', cap:'👮 Hiệu lệnh cảnh sát là ưu tiên cao nhất' },
  'duong-sat':  { svg:'', cap:'🚂 Qua đường sắt — dừng lại, nhìn, lắng nghe' },
  'xe-buyt':    { svg:'', cap:'🚌 Xuống xe buýt — chờ xe đi khuất mới qua đường' },
  'troi-mua':   { svg:'', cap:'☔ Trời mưa — đường trơn, xe phanh lâu gấp đôi' },
  'nga-tu':     { svg:'', cap:'🛣️ Ngã tư — chú ý cả xe đang RẼ' },
  'via-he':     { svg:'', cap:'🚶 Không có vỉa hè → đi ngược chiều xe để THẤY xe' },
  'choi-duong': { svg:'', cap:'⛔ Bóng mất còn mua được — con thì không' },
  'lac-duong':  { svg:'', cap:'📞 Bị lạc: đứng yên · tìm cảnh sát · nhớ 113' },
  'toc-do':     { svg:'', cap:'🏎️ Chậm một giây — không mất cả đời' },
  'bien-bao':   { svg:'', cap:'🚸 Bốn nhóm biển báo giao thông' },
  'an-toan-chung': { svg:'', cap:'💛 An toàn giao thông là hạnh phúc mọi nhà' },
};

/* =====================================================================
   BÀI KIỂM TRA TRẮC NGHIỆM
   ===================================================================== */
let quizState = null;   // { topic, name, questions, idx, score }

/* Nút "Làm bài kiểm tra" hiện dưới câu trả lời của AI */
function quizButton(topic){
  return topic
    ? `<div class="msg-tool quiz-start" onclick="startQuiz('${topic}')">📝 Làm bài kiểm tra</div>`
    : '';
}

async function startQuiz(topic){
  try {
    const d = await callApi(API + '?action=quiz&topic=' + encodeURIComponent(topic));
    if (d.status !== 'success') { toast(d.message); return; }
    quizState = { topic, name: d.topic_name, questions: d.questions, idx: 0, score: 0 };
    closeModal('progressModal');
    renderQuiz();
  } catch (e) { toast('Không tải được bài kiểm tra 😢'); }
}

function renderQuiz(){
  const s = quizState;
  const q = s.questions[s.idx];

  const opts = q.o.map((text, i) =>
    `<button class="quiz-opt" data-i="${i}" onclick="pickAnswer(${i})">${esc(text)}</button>`
  ).join('');

  const card = document.createElement('div');
  card.className = 'msg bot';
  card.id = 'quizCard';
  card.innerHTML = `<div class="msg-avatar">📝</div>
    <div class="msg-body"><div class="quiz-card">
      <div class="quiz-top">
        <b>${esc(s.name)}</b>
        <span>Câu ${s.idx + 1}/${s.questions.length} · ⭐ ${s.score}</span>
      </div>
      <div class="quiz-q">${esc(q.q)}</div>
      <div id="quizOpts">${opts}</div>
      <div id="quizFeed"></div>
    </div></div>`;

  const old = document.getElementById('quizCard');
  if (old) old.remove();
  document.getElementById('chatInner').appendChild(card);
  scrollBottom();
}

async function pickAnswer(pick){
  const s = quizState;
  const q = s.questions[s.idx];

  // Khoá các nút lại, tránh bấm nhiều lần
  document.querySelectorAll('#quizOpts .quiz-opt').forEach(b => b.disabled = true);

  const fd = new FormData();
  fd.append('action', 'quiz_check');
  fd.append('topic', s.topic);
  fd.append('i', q.i);
  fd.append('pick', pick);

  let d;
  try { d = await callApi(API, { method:'POST', body:fd }); }
  catch (e) { toast('Lỗi chấm bài 😢'); return; }

  // Tô màu: đáp án đúng xanh, chọn sai thì tô đỏ
  const btns = document.querySelectorAll('#quizOpts .quiz-opt');
  btns[d.answer].classList.add('right');
  if (!d.correct) btns[pick].classList.add('wrong');
  if (d.correct) s.score++;

  const last = (s.idx === s.questions.length - 1);
  document.getElementById('quizFeed').innerHTML = `
    <div class="quiz-explain">${d.correct ? '🎉 <b>Đúng rồi!</b> ' : '💡 <b>Chưa đúng.</b> '}${esc(d.explain)}</div>
    <button class="quiz-next" onclick="${last ? 'finishQuiz()' : 'nextQuiz()'}">
      ${last ? '🏁 Xem kết quả' : 'Câu tiếp theo →'}
    </button>`;
  scrollBottom();
}

function nextQuiz(){
  quizState.idx++;
  renderQuiz();
}

async function finishQuiz(){
  const s = quizState;
  const total = s.questions.length;

  // Lưu điểm vào CSDL
  const fd = new FormData();
  fd.append('action', 'quiz_submit');
  fd.append('topic', s.topic);
  fd.append('score', s.score);
  try { await callApi(API, { method:'POST', body:fd }); } catch (e) { }

  const perfect = (s.score === total);
  const emoji = perfect ? '🏆' : (s.score >= total / 2 ? '👏' : '💪');
  const msg = perfect
    ? 'Xuất sắc! Con trả lời đúng hết — nhận 1 ngôi sao ⭐'
    : (s.score >= total / 2
        ? 'Khá lắm! Con thử làm lại để đạt điểm tuyệt đối nhé.'
        : 'Không sao đâu con! Đọc lại bài rồi thử lại nha, mình tin con làm được! 💛');

  document.getElementById('quizCard').querySelector('.quiz-card').innerHTML = `
    <div class="quiz-result">
      <div class="big">${emoji}</div>
      <div class="score">${s.score} / ${total} câu đúng</div>
      <div class="msg2">${msg}</div>
      <button class="quiz-next" onclick="startQuiz('${s.topic}')">🔁 Làm lại bài này</button>
    </div>`;

  loadProgress();   // cập nhật lại thanh tiến độ
  checkBadges();    // có thể vừa mở khoá huy hiệu mới 🏅
  scrollBottom();
}

/* =====================================================================
   TIẾN ĐỘ HỌC TẬP
   ===================================================================== */
let progressData = null;

async function loadProgress(){
  try {
    const d = await callApi(API + '?action=progress');
    if (d.status !== 'success') return;
    progressData = d;

    document.getElementById('pgText').textContent  = d.learned + '/' + d.total;
    document.getElementById('pgBar').style.width   = Math.round(d.learned / d.total * 100) + '%';
    document.getElementById('pgStars').textContent = d.stars;
    document.getElementById('pgLevel').textContent = d.level;
    document.getElementById('pgPoints').textContent= d.points;
    const bEl = document.getElementById('pgBadges');
    if (bEl && d.badges !== undefined) bEl.textContent = d.badges;
  } catch (e) { }
}

async function openProgress(){
  document.getElementById('progressModal').classList.add('open');
  await loadProgress();
  const d = progressData;
  if (!d) return;

  document.getElementById('pgSummary').innerHTML =
    `Đã học <b>${d.learned}/${d.total}</b> chủ đề · ⭐ <b>${d.stars}</b> sao · 🏅 Cấp <b>${d.level}</b> · 💎 <b>${d.points}</b> điểm`;

  document.getElementById('topicList').innerHTML = d.topics.map(t => {
    const quizTxt = t.quiz
      ? `<span class="tquiz">${t.quiz.s}/${t.quiz.t} ${t.quiz.s >= t.quiz.t ? '⭐' : ''}</span>`
      : '';
    const btn = t.has_quiz
      ? `<button class="tgo" onclick="startQuiz('${t.key}')">${t.quiz ? 'Làm lại' : 'Kiểm tra'}</button>`
      : '';
    return `<div class="topic-row ${t.learned ? 'done' : ''}">
      <span class="tick">${t.learned ? '✅' : '⬜'}</span>
      <span class="tname">${t.name}</span>
      ${quizTxt}${btn}
    </div>`;
  }).join('');
}

/* =====================================================================
   CHỨNG CHỈ HOÀN THÀNH
   ===================================================================== */
async function openCert(){
  await loadProgress();
  const d = progressData;
  const box = document.getElementById('certArea');
  document.getElementById('certModal').classList.add('open');
  if (!d) return;

  if (!d.certified) {
    const needStars = Math.ceil(23 * 0.8);
    box.innerHTML = `<div class="cert-locked">
      <div class="lock">🔒</div>
      <h3>Chứng chỉ chưa mở khoá</h3>
      <p>Con cần <b>học đủ ${d.total} chủ đề</b> và đạt <b>${needStars} ngôi sao ⭐</b> (làm đúng hết bài kiểm tra).<br><br>
      Hiện tại: đã học <b>${d.learned}/${d.total}</b> chủ đề, được <b>${d.stars}</b> sao.<br>
      Cố lên con nhé, sắp tới rồi! 💪</p>
      <button class="quiz-next" style="max-width:220px;margin:16px auto 0" onclick="closeModal('certModal'); openProgress()">📊 Xem cần học gì tiếp</button>
    </div>`;
    return;
  }

  const today = new Date().toLocaleDateString('vi-VN');
  box.innerHTML = `
    <div class="cert">
      <div class="seal">🏆</div>
      <h2>CHỨNG CHỈ HOÀN THÀNH</h2>
      <div class="cert-sub">SIÊU NHÍ AN TOÀN GIAO THÔNG AI</div>
      <div>Chứng nhận em</div>
      <div class="cert-name">${esc(STUDENT_NAME)}</div>
      <div class="cert-body">
        đã hoàn thành xuất sắc khoá học <b>An toàn giao thông</b><br>
        với <b>${d.total}/${d.total}</b> chủ đề và <b>${d.stars} ngôi sao ⭐</b><br>
        Tổng điểm: <b>${d.points}</b> · Cấp độ: <b>${d.level}</b> 🏅
      </div>
      <div class="cert-foot">
        <span>Ngày cấp: ${today}</span>
        <span>🤖 AI Gia sư</span>
      </div>
    </div>
    <button class="quiz-next no-print" onclick="window.print()">🖨️ In / Lưu chứng chỉ</button>`;
}

function closeModal(id){
  document.getElementById(id).classList.remove('open');
}

/* Trên điện thoại: bấm một mục trong sidebar xong thì tự đóng sidebar lại */
document.addEventListener('click', ev => {
  if (window.innerWidth > 760) return;
  if (ev.target.closest('.side-link, .session-item, .progress-btn, .btn-primary-sm')) {
    toggleSidebar(false);
  }
});

/* Bấm ra ngoài để đóng hộp thoại */
document.addEventListener('click', ev => {
  if (ev.target.classList && ev.target.classList.contains('modal')) {
    ev.target.classList.remove('open');
  }
});

/* =====================================================================
   THƯ VIỆN BIỂN BÁO GIAO THÔNG
   ===================================================================== */
let SIGNS = null;          // toàn bộ thư viện (tải 1 lần)
let SIGN_PHOTOS = {};      // ảnh thật theo mã biển: { "P.102": {url, src} }
let signGroup = 'cam';     // nhóm đang xem

async function openSigns(){
  document.getElementById('signModal').classList.add('open');
  const area = document.getElementById('signArea');

  if (!SIGNS) {
    area.innerHTML = '<h3>🚸 Thư viện biển báo</h3><div class="modal-sub">Đang tải...</div>';
    try {
      const d = await callApi(API + '?action=signs');
      if (d.status !== 'success') { area.innerHTML = '<p>Không tải được thư viện 😢</p>'; return; }
      SIGNS = d.library;
    } catch (e) {
      area.innerHTML = '<p>Không tải được thư viện 😢</p>';
      return;
    }
  }
  renderSigns();
  loadSignPhotos(signGroup);   // tải ảnh thật nền, xong thì thay vào
}

/* Tải ảnh thật cho nhóm đang xem, rồi thay hình vẽ bằng ảnh thật */
async function loadSignPhotos(group){
  if (SIGN_PHOTOS['__' + group]) { applySignPhotos(); return; }   // đã tải rồi
  try {
    const d = await callApi(API + '?action=sign_photos&group=' + encodeURIComponent(group));
    if (d.status !== 'success') return;
    Object.assign(SIGN_PHOTOS, d.photos);
    SIGN_PHOTOS['__' + group] = true;   // đánh dấu nhóm này đã tải
    applySignPhotos();
  } catch (e) { /* không có ảnh → giữ hình vẽ SVG */ }
}

/* Thay hình vẽ SVG bằng ảnh thật (ảnh hỏng thì tự trả về hình vẽ) */
function applySignPhotos(){
  document.querySelectorAll('.sign-item[data-code]').forEach(el => {
    const code = el.dataset.code;
    const p = SIGN_PHOTOS[code];
    if (!p || !p.url || el.querySelector('img.real')) return;

    const svg = el.querySelector('svg');
    if (!svg) return;
    const img = document.createElement('img');
    img.className = 'real';
    img.src = p.url;
    img.alt = code;
    img.onerror = () => { img.remove(); svg.style.display = 'block'; };   // ảnh hỏng → dùng lại hình vẽ
    svg.style.display = 'none';
    el.insertBefore(img, svg);
  });
}

function renderSigns(keyword){
  const area = document.getElementById('signArea');
  const kw = (keyword || '').trim().toLowerCase();

  const tabs = Object.entries(SIGNS).map(([k, g]) =>
    `<div class="sign-tab ${k === signGroup && !kw ? 'on' : ''}" onclick="switchSignGroup('${k}')">${g.label}</div>`
  ).join('');

  // Đang tìm kiếm → gộp tất cả nhóm; không tìm → chỉ nhóm đang chọn
  let items = [], note = '';
  if (kw) {
    Object.values(SIGNS).forEach(g => {
      g.signs.forEach(s => {
        if ((s.name + ' ' + s.code + ' ' + s.mean).toLowerCase().includes(kw)) items.push(s);
      });
    });
    note = `<div class="sign-note">🔍 Tìm thấy <b>${items.length}</b> biển báo khớp với "<b>${esc(kw)}</b>"</div>`;
  } else {
    const g = SIGNS[signGroup];
    items = g.signs;
    note  = `<div class="sign-note">${esc(g.note)}</div>`;
  }

  const grid = items.length
    ? items.map(s =>
        `<div class="sign-item" data-code="${esc(s.code)}" onclick="showSign('${s.code}')">
           ${s.svg}
           <div class="sc">${esc(s.code)}</div>
           <div class="sn">${esc(s.name)}</div>
         </div>`).join('')
    : '<p style="color:#8CA0C4;font-size:13.5px">Không tìm thấy biển báo nào 😅</p>';

  const totalSigns = Object.values(SIGNS).reduce((n, g) => n + g.signs.length, 0);

  area.innerHTML = `
    <h3>🚸 Thư viện biển báo giao thông</h3>
    <div class="modal-sub">${totalSigns} biển báo Việt Nam · Bấm vào biển để xem chi tiết và hỏi AI</div>
    <input class="sign-search" placeholder="🔍 Tìm biển báo (ví dụ: trẻ em, cấm, tốc độ...)"
           value="${esc(keyword || '')}" oninput="renderSigns(this.value)">
    <div class="sign-tabs">${tabs}</div>
    ${note}
    <div class="sign-grid">${grid}</div>`;

  applySignPhotos();   // ảnh nào đã tải rồi thì hiện luôn
}

function switchSignGroup(k){
  signGroup = k;
  renderSigns();
  loadSignPhotos(k);
}

/* Xem chi tiết 1 biển báo */
function showSign(code){
  let sign = null;
  Object.values(SIGNS).forEach(g => {
    g.signs.forEach(s => { if (s.code === code) sign = s; });
  });
  if (!sign) return;

  const p = SIGN_PHOTOS[code];
  // Có ảnh thật → hiện ảnh thật to; ảnh hỏng thì tự đổi lại thành hình vẽ
  const visual = (p && p.url)
    ? `<img class="dbig" src="${esc(p.url)}" alt="${esc(code)}" onclick="zoom(this)"
            onerror="this.outerHTML = SIGN_SVG_FALLBACK">`
    : sign.svg;
  const srcNote = (p && p.src === 'local')
    ? '<div class="src-note">🖼️ Ảnh do bạn tự thêm vào (thư mục <b>bien-bao/</b>)</div>'
    : (p ? '<div class="src-note">📷 Ảnh thật · Nguồn: Wikimedia Commons</div>'
         : '<div class="src-note">✏️ Hình vẽ minh hoạ (chưa có ảnh thật cho biển này)</div>');

  window.SIGN_SVG_FALLBACK = sign.svg;   // dự phòng nếu ảnh thật lỗi

  document.getElementById('signArea').innerHTML = `
    <div class="sign-detail">
      <div class="dcode">${esc(sign.code)}</div>
      <h3>${esc(sign.name)}</h3>
      ${visual}
      ${srcNote}
      <div class="dmean">${fmt(sign.mean)}</div>
      <button class="quiz-next" onclick="askAboutSign('${esc(sign.code)}')">
        🤖 Hỏi AI Gia sư về biển này
      </button>
      <button class="progress-btn" style="background:#EFF5FD;color:#4A5F8F;margin-top:8px"
              onclick="renderSigns(); loadSignPhotos(signGroup);">← Quay lại thư viện</button>
    </div>`;
}

/* Bấm "Hỏi AI" → đóng thư viện, gửi câu hỏi vào khung chat.
   Nhận MÃ biển (P.102...) rồi tự tra tên — an toàn hơn truyền chuỗi tên. */
function askAboutSign(code){
  let name = code;
  if (SIGNS) {
    Object.values(SIGNS).forEach(g => {
      g.signs.forEach(s => { if (s.code === code) name = s.name; });
    });
  }
  closeModal('signModal');
  document.getElementById('chatText').value = 'Biển báo "' + name + '" nghĩa là gì ạ?';
  sendMsg();
}

/* =====================================================================
   THƯ VIỆN LUẬT GIAO THÔNG VIỆT NAM
   ===================================================================== */
let LAWS = null;            // toàn bộ thư viện luật (tải 1 lần)
let lawGroup = 'di-bo';     // nhóm đang xem

async function openLaws(){
  document.getElementById('lawModal').classList.add('open');
  const area = document.getElementById('lawArea');

  if (!LAWS) {
    area.innerHTML = '<h3>📜 Luật giao thông Việt Nam</h3><div class="modal-sub">Đang tải...</div>';
    try {
      const d = await callApi(API + '?action=laws');
      if (d.status !== 'success') { area.innerHTML = '<p>Không tải được thư viện luật 😢</p>'; return; }
      LAWS = d.library;
    } catch (e) {
      area.innerHTML = '<p>Không tải được thư viện luật 😢</p>';
      return;
    }
  }
  renderLaws();
}

function renderLaws(keyword){
  const area = document.getElementById('lawArea');
  const kw = (keyword || '').trim().toLowerCase();

  const tabs = Object.entries(LAWS).map(([k, g]) =>
    `<div class="sign-tab ${k === lawGroup && !kw ? 'on' : ''}" onclick="switchLawGroup('${k}')">${g.label}</div>`
  ).join('');

  // Đang tìm kiếm → gộp tất cả nhóm; không tìm → chỉ nhóm đang chọn
  let items = [], note = '';
  if (kw) {
    Object.values(LAWS).forEach(g => {
      g.laws.forEach(l => {
        const hay = (l.title + ' ' + l.kid + ' ' + l.rule + ' ' + l.base + ' ' + l.fine).toLowerCase();
        if (hay.includes(kw)) items.push(l);
      });
    });
    note = `<div class="law-note">🔍 Tìm thấy <b>${items.length}</b> điều luật khớp với "<b>${esc(kw)}</b>"</div>`;
  } else {
    const g = LAWS[lawGroup];
    items = g.laws;
    note  = `<div class="law-note">${esc(g.note)}</div>`;
  }

  const list = items.length
    ? items.map(l => `
        <div class="law-item" onclick="showLaw('${esc(l.id)}')">
          <div class="li-ic">${l.icon}</div>
          <div class="li-body">
            <div class="li-title">${esc(l.title)}</div>
            <div class="li-kid">${esc(l.kid.replace(/\*\*/g, '').replace(/\n/g, ' '))}</div>
            <div class="li-tags">
              <span class="law-tag">⚖️ ${esc(l.base.split(';')[0])}</span>
              ${l.fine ? `<span class="law-tag fine">💰 Có mức phạt</span>` : ''}
              ${l.who === 'lon' ? `<span class="law-tag who">👨‍👩‍👧 Dành cho ba mẹ</span>` : ''}
            </div>
          </div>
        </div>`).join('')
    : '<p style="color:#8CA0C4;font-size:13.5px">Không tìm thấy điều luật nào 😅</p>';

  const total = Object.values(LAWS).reduce((n, g) => n + g.laws.length, 0);

  area.innerHTML = `
    <h3>📜 Luật giao thông Việt Nam</h3>
    <div class="modal-sub">${total} điều luật · Bấm vào từng điều để xem giải thích cho bé và mức phạt</div>
    <input class="sign-search" placeholder="🔍 Tìm điều luật (ví dụ: mũ bảo hiểm, đèn đỏ, xe đạp...)"
           value="${esc(keyword || '')}" oninput="renderLaws(this.value)">
    <div class="sign-tabs">${tabs}</div>
    ${note}
    <div class="law-list">${list}</div>
    <div class="law-legal">
      ⚖️ Nội dung tổng hợp từ <b>Luật Trật tự, an toàn giao thông đường bộ 2024</b> (số 36/2024/QH15)
      và <b>Nghị định 168/2024/NĐ-CP</b>. Một số quy định thay đổi theo
      <b>Nghị định 238/2026/NĐ-CP</b> có hiệu lực từ 15/8/2026.
      Đây là tài liệu học tập — khi cần chính xác tuyệt đối, hãy tra cứu văn bản gốc tại chinhphu.vn.
    </div>`;
}

function switchLawGroup(k){
  lawGroup = k;
  renderLaws();
}

/* Xem chi tiết 1 điều luật */
function showLaw(id){
  let law = null;
  Object.values(LAWS).forEach(g => {
    g.laws.forEach(l => { if (l.id === id) law = l; });
  });
  if (!law) return;

  const fineBlock = law.fine
    ? `<div class="law-sec fine">
         <h4>💰 MỨC PHẠT</h4>
         ${fmt(law.fine)}
         ${law.fbase ? `<div style="margin-top:6px;font-size:11.5px;opacity:.8">Căn cứ: ${esc(law.fbase)}</div>` : ''}
       </div>`
    : '';

  document.getElementById('lawArea').innerHTML = `
    <div class="law-detail">
      <div class="ld-ic">${law.icon}</div>
      <h3>${esc(law.title)}</h3>

      <div class="law-sec kid">
        <h4>🧒 GIẢI THÍCH CHO CON</h4>
        ${fmt(law.kid)}
      </div>

      <div class="law-sec rule">
        <h4>📘 LUẬT QUY ĐỊNH</h4>
        ${fmt(law.rule)}
      </div>

      <div class="law-sec base">
        <h4>⚖️ CĂN CỨ PHÁP LÝ</h4>
        ${esc(law.base)}
      </div>

      ${fineBlock}

      <button class="quiz-next" onclick="askAboutLaw('${esc(law.id)}')">
        🤖 Hỏi AI Gia sư về điều luật này
      </button>
      <button class="progress-btn" style="background:#EFF5FD;color:#4A5F8F;margin-top:8px"
              onclick="renderLaws()">← Quay lại thư viện luật</button>
    </div>`;
}

/* Bấm "Hỏi AI" → đóng thư viện luật, gửi câu hỏi vào khung chat */
function askAboutLaw(id){
  let title = '', group = '';
  if (LAWS) {
    Object.entries(LAWS).forEach(([k, g]) => {
      g.laws.forEach(l => { if (l.id === id) { title = l.title; group = k; } });
    });
  }
  if (!title) return;
  closeModal('lawModal');
  // Nhóm "Bảng mức phạt" thì hỏi thẳng về mức phạt cho đúng trọng tâm
  document.getElementById('chatText').value = (group === 'muc-phat' || group === 'muc-phat-full')
    ? 'Mức phạt cho lỗi "' + title + '" là bao nhiêu ạ?'
    : 'Luật giao thông quy định thế nào về "' + title + '" ạ?';
  sendMsg();
}

/* =====================================================================
   NHÂN VẬT AI
   ===================================================================== */
let PERSONAS = {};
let persona = localStorage.getItem('aigs_persona') || 'gia-su';

function buildPersonaBar(list){
  PERSONAS = list;
  const bar = document.getElementById('personaBar');
  bar.innerHTML = Object.entries(list).map(([k, p]) =>
    `<div class="persona-chip ${k === persona ? 'on' : ''}" data-k="${k}"
          title="${esc(p.desc)}" onclick="setPersona('${k}')">
       <span class="pe">${p.emoji}</span> ${esc(p.name)}
     </div>`
  ).join('');
}

function setPersona(k){
  persona = k;
  localStorage.setItem('aigs_persona', k);
  document.querySelectorAll('.persona-chip').forEach(c =>
    c.classList.toggle('on', c.dataset.k === k));
  const p = PERSONAS[k];
  if (p) toast(p.emoji + ' Giờ ' + p.name + ' sẽ trò chuyện với con!');
}

function personaEmoji(){
  return (PERSONAS[persona] && PERSONAS[persona].emoji) || '🤖';
}

/* =====================================================================
   HIỆU ỨNG CHỮ CHẠY DẦN (giống ChatGPT)
   ===================================================================== */
function typeWriter(el, text, done){
  const speed = 12;           // số ký tự hiện mỗi nhịp
  let i = 0;
  el.innerHTML = '';
  const timer = setInterval(() => {
    i += speed;
    el.innerHTML = fmt(text.slice(0, i));
    scrollBottom();
    if (i >= text.length) {
      clearInterval(timer);
      el.innerHTML = fmt(text);
      if (done) done();
    }
  }, 16);
}

/* =====================================================================
   GỬI ẢNH BIỂN BÁO CHO AI NHẬN DIỆN
   ===================================================================== */
let pendingImage = null;   // ảnh đang chờ gửi (dạng base64)

function onPickImage(input){
  const f = input.files && input.files[0];
  input.value = '';                        // reset để chọn lại cùng ảnh vẫn được
  if (!f) return;
  if (!f.type.startsWith('image/')) { toast('Con chọn file ảnh nhé!'); return; }
  if (f.size > 6 * 1024 * 1024) { toast('Ảnh lớn quá (tối đa 6MB) 😅'); return; }

  const r = new FileReader();
  r.onload = e => {
    pendingImage = e.target.result;        // "data:image/jpeg;base64,..."
    document.getElementById('imgThumb').src = pendingImage;
    document.getElementById('imgPreview').classList.add('on');
    document.getElementById('chatText').focus();
  };
  r.readAsDataURL(f);
}

function clearImage(){
  pendingImage = null;
  document.getElementById('imgPreview').classList.remove('on');
}

/* =====================================================================
   TÌM KIẾM TRONG LỊCH SỬ CHAT
   ===================================================================== */
let searchTimer = null;

function onSearch(kw){
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    if (kw.trim() === '') { loadSessions(); return; }
    doSearch(kw.trim());
  }, 300);   // chờ bé gõ xong mới tìm, đỡ gọi máy chủ liên tục
}

async function doSearch(kw){
  try {
    const d = await callApi(API + '?action=search&q=' + encodeURIComponent(kw));
    if (d.status !== 'success') return;
    const box = document.getElementById('sessionList');
    if (!d.sessions.length) {
      box.innerHTML = '<div class="side-link group-label">Không tìm thấy kết quả nào</div>';
      return;
    }
    box.innerHTML = `<div class="side-link group-label">Tìm thấy ${d.sessions.length} kết quả</div>`;
    d.sessions.forEach(s => box.appendChild(sessionItem(s)));
  } catch (e) { }
}

/* =====================================================================
   TRẢ LỜI LẠI CÁCH KHÁC
   ===================================================================== */
async function regenerate(btn){
  if (currentSessionId === 0 || sending) return;
  sending = true;

  const msgEl = btn.closest('.msg');   // xoá câu trả lời cũ trên màn hình
  if (msgEl) msgEl.remove();
  showTyping();

  try {
    const fd = new FormData();
    fd.append('action', 'regen');
    fd.append('session_id', currentSessionId);
    fd.append('persona', persona);

    const [d] = await Promise.all([
      callApi(API, { method:'POST', body:fd }),
      new Promise(r => setTimeout(r, 500)),
    ]);
    hideTyping();
    if (d.status === 'success') addBotMsg(d.reply, false, d.illus, d.photos, d.quiz, d.next, true, d.signs);
    else addBotMsg(d.message, true);
  } catch (e) {
    hideTyping();
    addBotMsg(e.message, true);
  }
  sending = false;
  scrollBottom();
}

/* ---------- Tiện ích ---------- */
/* Escape đầy đủ: cả dấu nháy, để dùng an toàn trong cả nội dung lẫn thuộc tính HTML */
function esc(s){
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function fmt(s){ return esc(s).replace(/\*\*(.+?)\*\*/g,'<b>$1</b>').replace(/\n/g,'<br>'); }
function scrollBottom(){ const sc = document.getElementById('chatScroll'); sc.scrollTop = sc.scrollHeight; }

/* Đóng/mở sidebar trên điện thoại (màn hình rộng thì sidebar luôn hiện) */
function toggleSidebar(force){
  const sb = document.querySelector('.sidebar');
  const bd = document.getElementById('sideBackdrop');
  const open = (force === undefined) ? !sb.classList.contains('open') : force;
  sb.classList.toggle('open', open);
  bd.classList.toggle('open', open);
}
function toast(msg){
  let t = document.getElementById('toast');
  if (!t) { t = document.createElement('div'); t.id='toast'; t.className='toast'; document.body.appendChild(t); }
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 3000);
}

/* ---------- Hiển thị tin nhắn ---------- */
function addUserMsg(text, img){
  chatLog.push({ role:'user', content:text });
  const pic = img ? `<img class="msg-img" src="${img}" alt="" onclick="zoom(this)">` : '';
  const bubble = text ? `<div class="msg-bubble">${fmt(text)}</div>` : '';
  const d = document.createElement('div');
  d.className = 'msg user';
  d.innerHTML = `<div class="msg-avatar">🧒</div>
    <div class="msg-body">${pic}${bubble}</div>`;
  document.getElementById('chatInner').appendChild(d);
}
/* Dựng phần hình ảnh cho câu trả lời.
   Thứ tự: 1) Hình minh hoạ CHUẨN (SVG) → 2) Bảng biển báo (nếu hỏi về biển)
           → 3) Ảnh thật (chỉ là tham khảo thêm) */
function buildArt(illus, photos, signs){
  let html = '';

  // 1. Hình minh hoạ vẽ chuẩn — LUÔN hiện trước, vì đây là hình chính xác nhất
  if (illus && ILLUS[illus] && ILLUS[illus].svg) {
    html += `<div class="illus-card" onclick="zoomIllus('${illus}')" title="Bấm để xem to">
               <div class="illus-visual">${ILLUS[illus].svg}</div>
               <div class="illus-caption">${ILLUS[illus].cap}
                 <span class="zoom-hint">🔍 Xem to</span>
               </div>
             </div>`;
  }

  // 2. Hỏi về biển báo → hiện bảng biển báo CHUẨN ngay trong chat
  if (signs && signs.length) {
    html += `<div class="signs-card">
      <div class="signs-grid">
        ${signs.map(s => `
          <div class="signs-item" onclick="openSignFromChat('${s.code}')" title="${esc(s.name)}">
            ${s.svg}
            <div class="si-code">${esc(s.code)}</div>
            <div class="si-name">${esc(s.name)}</div>
          </div>`).join('')}
      </div>
      <div class="signs-foot" onclick="openSigns()">
        🚸 Xem đầy đủ <b>28 biển báo</b> trong Thư viện →
      </div>
    </div>`;
  }

  // 3. Ảnh thật — chỉ là tham khảo thêm, để sau cùng
  if (photos && photos.length) {
    const hero = `<img class="photo-hero" src="${esc(photos[0].thumb)}" alt="" loading="lazy"
                    onclick="zoom(this)" onerror="this.parentElement.remove()">`;
    const rest = photos.slice(1);
    const thumbs = rest.length
      ? `<div class="photo-thumbs">` + rest.map(p =>
          `<img src="${esc(p.thumb)}" alt="" loading="lazy" onclick="zoom(this)" onerror="this.remove()">`
        ).join('') + `</div>`
      : '';
    const lic = esc(photos[0].license || 'Wikimedia Commons');
    html += `<div class="photo-card">${hero}${thumbs}
      <div class="photo-cap">📷 Ảnh thật tham khảo
        <span class="src">Nguồn: Wikimedia Commons · ${lic} · Bấm vào ảnh để xem to</span>
      </div></div>`;
  }

  return html;
}

/* =====================================================================
   GAME "ĐƯỜNG ĐẾN TRƯỜNG" + TÌNH HUỐNG TƯƠNG TÁC
   ===================================================================== */
let mapData = null, sitState = null;

async function openMap(){
  document.getElementById('badgeModal').classList.add('open');
  document.getElementById('badgeArea').innerHTML =
    '<h3>🗺️ Đường đến trường</h3><div class="modal-sub">Đang tải bản đồ...</div>';
  try {
    mapData = await callApi(API + '?action=map');
    renderMap();
  } catch (e) {
    document.getElementById('badgeArea').innerHTML = '<p>Không tải được bản đồ 😢</p>';
  }
}

function renderMap(){
  const d = mapData;
  if (!d || d.status !== 'success') return;

  // Chặng đang tới lượt = chặng đầu tiên chưa qua và chưa khoá
  const nowIdx = d.stops.findIndex(s => !s.passed && !s.locked);

  const stops = d.stops.map((s, i) => {
    const cls = s.passed ? 'done' : (s.locked ? 'locked' : (i === nowIdx ? 'now' : ''));
    const icon = s.passed ? (s.first_try ? '⭐' : '✅') : (s.locked ? '🔒' : '▶️');
    return `<div class="map-stop ${cls}"
                 onclick="${s.locked ? `toast('Con hãy hoàn thành chặng trước đã nhé! 🔒')` : `startSituation('${s.id}')`}">
      <div class="dot">${s.no}</div>
      <div class="ms-t">
        <b>${esc(s.title)}</b>
        <span>${esc(s.skill)}${s.tries ? ` · đã thử ${s.tries} lần` : ''}</span>
      </div>
      <div class="ms-s">${icon}</div>
    </div>`;
  }).join('');

  const finished = (d.passed >= d.total);

  document.getElementById('badgeArea').innerHTML = `
    <h3>🗺️ Đường đến trường</h3>
    <div class="modal-sub">Xử lý ${d.total} tình huống trên đường đi học · Bấm vào chặng để bắt đầu</div>

    <div class="map-head">
      <div class="mh-l">
        <b>🏠 Nhà con → 🏫 Trường học</b>
        <span>Đúng ngay lần đầu được ⭐ · Sai thì học lại rồi thử tiếp</span>
      </div>
      <div class="mh-r"><b>${d.passed}/${d.total}</b><br>chặng · ⭐ ${d.perfect}</div>
    </div>

    <div class="map-path">${stops}</div>

    <div class="map-goal">
      <div class="mg-ic">${finished ? '🎉🏫' : '🏫'}</div>
      <b>${finished ? 'Con đã về đến trường an toàn!' : 'Đích đến: Trường học'}</b>
      <span>${finished
        ? `Xuất sắc! Con đạt ${d.perfect}/${d.total} ⭐ đúng ngay lần đầu.`
        : `Còn ${d.total - d.passed} chặng nữa thôi, cố lên con!`}</span>
    </div>`;
}

/* --- Bắt đầu 1 tình huống --- */
async function startSituation(id){
  try {
    const d = await callApi(API + '?action=situation&id=' + encodeURIComponent(id));
    if (d.status !== 'success') { toast(d.message); return; }
    sitState = { id, data: d, answered: false };
    closeModal('badgeModal');
    renderSituation();
  } catch (e) { toast('Không tải được tình huống 😢'); }
}

function renderSituation(){
  const d = sitState.data;
  const opts = d.o.map((t, i) =>
    `<button class="sit-opt" data-i="${i}" onclick="pickSituation(${i})">${esc(t)}</button>`).join('');

  const card = document.createElement('div');
  card.className = 'msg bot';
  card.id = 'sitCard';
  card.innerHTML = `<div class="msg-avatar">🎯</div>
    <div class="msg-body"><div class="sit-card">
      <div class="sit-head"><b>🎯 ${esc(d.title)}</b><span>${esc(d.skill)}</span></div>
      <div class="sit-scene">${d.scene}</div>
      <div class="sit-body">
        <div class="sit-q">${esc(d.q)}</div>
        <div id="sitOpts">${opts}</div>
        <div id="sitFeed"></div>
      </div>
    </div></div>`;

  const old = document.getElementById('sitCard');
  if (old) old.remove();
  document.getElementById('chatInner').appendChild(card);
  scrollBottom();
}

async function pickSituation(pick){
  if (sitState.answered) return;
  sitState.answered = true;
  document.querySelectorAll('#sitOpts .sit-opt').forEach(b => b.disabled = true);

  const fd = new FormData();
  fd.append('action', 'situation_answer');
  fd.append('id', sitState.id);
  fd.append('pick', pick);

  let d;
  try { d = await callApi(API, { method:'POST', body:fd }); }
  catch (e) { toast('Lỗi chấm bài 😢'); return; }
  if (d.status !== 'success') { toast(d.message); return; }

  const btns = document.querySelectorAll('#sitOpts .sit-opt');
  btns[d.right].classList.add('right');
  if (!d.correct) btns[pick].classList.add('wrong');

  const star = (d.correct && d.first_try) ? ' <b>+1 ⭐ (đúng ngay lần đầu!)</b>' : '';

  document.getElementById('sitFeed').innerHTML = `
    <div class="quiz-explain">
      ${d.correct ? '🎉 <b>Con xử lý đúng rồi!</b><br>' : '💡 <b>Chưa đúng đâu con.</b><br>'}
      ${fmt(d.explain)}${star}
      ${!d.correct ? `<hr style="border:none;border-top:1px dashed #E0CFA0;margin:8px 0">
        <b>Cách xử lý đúng:</b><br>${fmt(d.right_explain)}` : ''}
    </div>
    <button class="quiz-next" onclick="${d.correct ? 'openMap()' : `startSituation('${sitState.id}')`}">
      ${d.correct ? '🗺️ Đi tiếp chặng sau →' : '🔁 Thử lại tình huống này'}
    </button>
    <button class="progress-btn" style="background:#EFF5FD;color:#4A5F8F;margin-top:7px"
            onclick="askTopic('${d.topic}')">🤖 Hỏi AI Gia sư về chủ đề này</button>`;

  loadProgress();
  checkBadges();
  if (!d.correct) sitState.answered = false;   // cho thử lại
  scrollBottom();
}

function askTopic(topic){
  document.getElementById('chatText').value = TOPIC_ASK[topic] || 'Giải thích chủ đề này giúp con';
  sendMsg();
}

/* =====================================================================
   CÁ NHÂN HOÁ — bài ôn tập riêng theo điểm yếu của bé
   ===================================================================== */
async function showPersonalized(){
  try {
    const d = await callApi(API + '?action=personalized');
    if (d.status !== 'success' || !d.weak.length) {
      toast('Con chưa có điểm yếu nào — giỏi lắm! 🎉');
      return;
    }
    const items = d.weak.map(w => `
      <div class="weak-item">
        <span class="wn">${esc(w.name)}</span>
        <span class="wc">sai ${w.wrong} lần</span>
        <button onclick="askTopic('${esc(w.topic)}')">Học lại</button>
        ${w.has_quiz ? `<button onclick="startQuiz('${w.topic}')" style="background:var(--sun);color:#3D2F00">Kiểm tra</button>` : ''}
      </div>`).join('');

    const box = document.createElement('div');
    box.className = 'msg bot';
    box.innerHTML = `<div class="msg-avatar">🎯</div>
      <div class="msg-body"><div class="weak-card">
        <h4>🎯 Bài ôn tập riêng cho con</h4>
        <div class="wsub">Mình thấy con còn chưa chắc mấy chủ đề này.
          Không sao đâu — học lại là nhớ ngay thôi! 💛</div>
        ${items}
      </div></div>`;
    document.getElementById('chatInner').appendChild(box);
    scrollBottom();
  } catch (e) { toast('Không tải được bài ôn tập 😢'); }
}

function askText(q){
  document.getElementById('chatText').value = q;
  sendMsg();
}

/* =====================================================================
   BÁO CÁO CHO GIÁO VIÊN / PHỤ HUYNH
   ===================================================================== */
async function openReport(){
  document.getElementById('badgeModal').classList.add('open');
  document.getElementById('badgeArea').innerHTML =
    '<h3>👩‍🏫 Báo cáo học tập</h3><div class="modal-sub">Đang tổng hợp...</div>';

  let d;
  try { d = await callApi(API + '?action=report'); }
  catch (e) { document.getElementById('badgeArea').innerHTML = '<p>Không tải được báo cáo 😢</p>'; return; }
  if (d.status !== 'success') return;

  const pct = Math.round(d.learned / d.total * 100);

  const kpi = `<div class="rep-grid">
    <div class="rep-kpi"><div class="rv">${d.learned}/${d.total}</div><div class="rl">Chủ đề đã học</div></div>
    <div class="rep-kpi"><div class="rv">${d.sit_passed}/${d.sit_total}</div><div class="rl">Tình huống đã qua</div></div>
    <div class="rep-kpi"><div class="rv">${d.stars} ⭐</div><div class="rl">Bài kiểm tra đạt</div></div>
    <div class="rep-kpi"><div class="rv">${d.level}</div><div class="rl">Cấp độ (${d.points}đ)</div></div>
  </div>`;

  // Tiến bộ trước/sau
  let test = '<div class="rep-tip">Học sinh chưa làm bài kiểm tra đầu vào.</div>';
  if (d.pre) {
    const post = d.post;
    const diff = post ? post.score - d.pre.score : null;
    test = `<table class="rep-table">
      <tr><th>Bài kiểm tra</th><th>Kết quả</th><th>Ngày làm</th></tr>
      <tr><td>📝 Đầu vào (trước khi học)</td>
          <td><b>${d.pre.score}/${d.pre.total}</b> (${Math.round(d.pre.score/d.pre.total*100)}%)</td>
          <td>${esc(String(d.pre.at).slice(0,10))}</td></tr>
      ${post ? `<tr><td>🎯 Đầu ra (sau khi học)</td>
          <td><b>${post.score}/${post.total}</b> (${Math.round(post.score/post.total*100)}%)</td>
          <td>${esc(String(post.at).slice(0,10))}</td></tr>
      <tr><td><b>Mức tiến bộ</b></td>
          <td colspan="2" class="${diff > 0 ? 'rep-ok' : 'rep-no'}">
            ${diff > 0 ? `📈 +${diff} câu — tiến bộ rõ rệt` : (diff === 0 ? '➖ Giữ nguyên' : `📉 ${diff} câu`)}
          </td></tr>` : `<tr><td>🎯 Đầu ra</td><td colspan="2">Chưa làm</td></tr>`}
    </table>`;
  }

  // Năng lực theo kỹ năng
  const skills = Object.entries(d.skills || {}).map(([k, v]) => {
    const p = Math.round(v.ok / v.total * 100);
    return `<div class="prog-row">
      <div class="pl"><b>${esc(k)}</b><span>${v.ok}/${v.total} (${p}%)</span></div>
      <div class="prog-line"><div class="pb post"><i style="width:${p}%"></i></div></div>
    </div>`;
  }).join('') || '<div class="rep-tip">Chưa có dữ liệu — cần làm bài kiểm tra.</div>';

  // Bảng chi tiết chủ đề
  const rows = d.rows.map(r => `<tr>
    <td>${esc(r.name)}</td>
    <td class="${r.learned ? 'rep-ok' : ''}">${r.learned ? '✅ Đã học' : '⬜ Chưa học'}</td>
    <td>${r.quiz ? `${r.quiz.s}/${r.quiz.t}${r.quiz.s >= r.quiz.t ? ' ⭐' : ''}` : '—'}</td>
    <td class="${r.wrong > 0 ? 'rep-no' : ''}">${r.wrong > 0 ? `⚠️ sai ${r.wrong} lần` : '—'}</td>
  </tr>`).join('');

  // Gợi ý thực hành cho phụ huynh
  const tips = d.tips.length
    ? d.tips.map(t => `<div class="rep-tip"><b>${esc(t.topic)}</b><br>${esc(t.tip)}</div>`).join('')
    : '<div class="rep-tip">Học sinh đang làm rất tốt — chưa có kỹ năng nào cần củng cố thêm! 🎉</div>';

  document.getElementById('badgeArea').innerHTML = `
    <div id="printArea">
    <h3>👩‍🏫 Báo cáo học tập</h3>
    <div class="modal-sub">Học sinh: <b>${esc(d.student)}</b> · Ngày in: ${new Date().toLocaleDateString('vi-VN')} · Dành cho giáo viên &amp; phụ huynh</div>
    ${kpi}
    ${d.certified ? '<div class="rep-tip" style="border-color:#34D399;background:#E9FBF1;color:#166534">🏆 <b>Đã hoàn thành khoá học</b> và nhận chứng chỉ.</div>' : ''}

    <div class="rep-sec"><h4>📊 Kết quả kiểm tra trước &amp; sau</h4>${test}</div>
    <div class="rep-sec"><h4>💪 Năng lực theo nhóm kỹ năng</h4><div class="prog-chart">${skills}</div></div>
    <div class="rep-sec"><h4>🏠 Gợi ý hoạt động thực hành ngoài đời</h4>${tips}</div>
    <div class="rep-sec"><h4>📋 Chi tiết từng chủ đề</h4>
      <table class="rep-table">
        <tr><th>Chủ đề</th><th>Trạng thái</th><th>Kiểm tra</th><th>Điểm yếu</th></tr>
        ${rows}
      </table>
    </div>
    </div>
    <button class="quiz-next no-print" onclick="window.print()">🖨️ In / Lưu báo cáo (PDF)</button>`;
}

/* =====================================================================
   HUY HIỆU 🏅 + BÀI KIỂM TRA ĐẦU VÀO / ĐẦU RA
   ===================================================================== */
let badgeData = null, testData = null;
let testState = null;   // { phase, questions, idx, answers }

/* Kiểm tra huy hiệu mới, hiện thông báo chúc mừng */
async function checkBadges(silent){
  try {
    const d = await callApi(API + '?action=badges');
    if (d.status !== 'success') return;
    badgeData = d;

    if (!silent && d.new && d.new.length) {
      d.new.forEach((key, i) => {
        const b = d.badges.find(x => x.key === key);
        if (b) setTimeout(() => badgeToast(b), i * 2600);
      });
    }
  } catch (e) { }
}

function badgeToast(b){
  let t = document.getElementById('badgeToast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'badgeToast';
    t.className = 'badge-toast';
    document.body.appendChild(t);
  }
  t.innerHTML = `<span class="bt-ic">${b.icon}</span>
    <div><b>🎉 Huy hiệu mới: ${esc(b.name)}</b><span>${esc(b.desc)}</span></div>`;
  t.classList.add('show');
  clearTimeout(t._tm);
  t._tm = setTimeout(() => t.classList.remove('show'), 2400);
}

/* Mở bảng Huy hiệu & Bài kiểm tra */
async function openBadges(){
  document.getElementById('badgeModal').classList.add('open');
  document.getElementById('badgeArea').innerHTML =
    '<h3>🏅 Huy hiệu & Bài kiểm tra</h3><div class="modal-sub">Đang tải...</div>';

  await checkBadges(true);
  try { testData = await callApi(API + '?action=test_status'); } catch (e) { }
  renderBadges();
}

function renderBadges(){
  const b = badgeData, t = testData;
  if (!b) return;

  /* --- Phần 1: Bài kiểm tra đầu vào / đầu ra --- */
  const pre = t && t.pre, post = t && t.post;

  const preCard = `
    <div class="test-card ${pre ? 'done' : ''}">
      <h4>📝 Kiểm tra ĐẦU VÀO</h4>
      <div class="tsub">Làm TRƯỚC khi học — để biết con đang ở đâu</div>
      ${pre
        ? `<div class="tscore">${pre.score}<small>/${pre.total}</small></div>
           <button class="test-btn again" onclick="startTest('pre')">🔁 Làm lại</button>`
        : `<button class="test-btn" onclick="startTest('pre')">Bắt đầu làm bài</button>`}
    </div>`;

  const canPost = t && t.can_post;
  const postCard = `
    <div class="test-card ${post ? 'done' : ''}">
      <h4>🎯 Kiểm tra ĐẦU RA</h4>
      <div class="tsub">${canPost
        ? 'Làm SAU khi học — xem con tiến bộ bao nhiêu'
        : `Cần học ít nhất <b>10 chủ đề</b> (đã học ${t ? t.learned : 0})`}</div>
      ${post
        ? `<div class="tscore">${post.score}<small>/${post.total}</small></div>
           <button class="test-btn again" onclick="startTest('post')">🔁 Làm lại</button>`
        : `<button class="test-btn" ${canPost ? '' : 'disabled'}
                   onclick="startTest('post')">Bắt đầu làm bài</button>`}
    </div>`;

  /* --- Phần 2: Biểu đồ tiến bộ (khi có đủ 2 bài) --- */
  let chart = '';
  if (pre && post) {
    const diff = post.score - pre.score;
    const pct  = pre.score > 0
      ? Math.round(diff / pre.total * 100)
      : Math.round(post.score / post.total * 100);

    const skills = {};
    if (pre.detail && pre.detail.skills)  Object.entries(pre.detail.skills).forEach(([k,v]) => skills[k] = { pre:v });
    if (post.detail && post.detail.skills) Object.entries(post.detail.skills).forEach(([k,v]) => {
      skills[k] = Object.assign(skills[k] || {}, { post:v });
    });

    const rows = Object.entries(skills).map(([name, v]) => {
      const p1 = v.pre  ? Math.round(v.pre.ok  / v.pre.total  * 100) : 0;
      const p2 = v.post ? Math.round(v.post.ok / v.post.total * 100) : 0;
      const up = p2 - p1;
      return `<div class="prog-row">
        <div class="pl"><b>${esc(name)}</b>
          <span>${up > 0 ? '📈 +' + up + '%' : (up < 0 ? '📉 ' + up + '%' : '➖ giữ nguyên')}</span></div>
        <div class="prog-line"><span class="prog-tag">Trước</span>
          <div class="pb pre"><i style="width:${p1}%"></i></div><span class="prog-tag">${p1}%</span></div>
        <div class="prog-line"><span class="prog-tag">Sau</span>
          <div class="pb post"><i style="width:${p2}%"></i></div><span class="prog-tag">${p2}%</span></div>
      </div>`;
    }).join('');

    chart = `
      <div class="improve">
        <div class="big">${diff >= 0 ? '+' : ''}${diff} câu ${diff > 0 ? '📈' : ''}</div>
        <div class="txt">Từ <b>${pre.score}/${pre.total}</b> lên <b>${post.score}/${post.total}</b>
          ${diff > 0 ? `— con đã tiến bộ <b>${pct}%</b>! 🎉` : (diff === 0 ? '— giữ nguyên phong độ 💪' : '— ôn lại chút nhé 💛')}
        </div>
      </div>
      <div class="prog-chart">
        <h4>📊 Tiến bộ theo từng kỹ năng</h4>
        ${rows}
      </div>`;
  }

  /* --- Phần 3: Bộ sưu tập huy hiệu --- */
  const grid = b.badges.map(x => `
    <div class="badge-item ${x.earned ? 'got' : 'locked'}"
         title="${esc(x.earned ? x.desc : 'Cách mở khoá: ' + x.hint)}">
      <div class="bi">${x.icon}</div>
      <div class="bn">${esc(x.name)}</div>
      <div class="bd">${esc(x.earned ? x.desc : x.hint)}</div>
    </div>`).join('');

  document.getElementById('badgeArea').innerHTML = `
    <h3>🏅 Huy hiệu & Bài kiểm tra</h3>
    <div class="modal-sub">Đã đạt <b>${b.earned}/${b.total}</b> huy hiệu</div>
    <div class="test-cards">${preCard}${postCard}</div>
    ${chart}
    <div class="prog-chart" style="padding:0;background:none">
      <h4>🏅 Bộ sưu tập huy hiệu</h4>
      <div class="badge-grid">${grid}</div>
    </div>`;
}

/* --- Làm bài kiểm tra (15 câu) --- */
async function startTest(phase){
  try {
    const d = await callApi(API + '?action=test_start');
    if (d.status !== 'success') { toast(d.message); return; }
    testState = { phase, questions: d.questions, idx: 0, answers: {} };
    closeModal('badgeModal');
    renderTest();
  } catch (e) { toast('Không tải được đề 😢'); }
}

function renderTest(){
  const s = testState;
  const q = s.questions[s.idx];
  const isPre = (s.phase === 'pre');

  const opts = q.o.map((text, i) =>
    `<button class="quiz-opt" onclick="pickTest(${i})">${esc(text)}</button>`).join('');

  const card = document.createElement('div');
  card.className = 'msg bot';
  card.id = 'quizCard';
  card.innerHTML = `<div class="msg-avatar">${isPre ? '📝' : '🎯'}</div>
    <div class="msg-body"><div class="quiz-card">
      <div class="quiz-top">
        <b>${isPre ? '📝 Kiểm tra ĐẦU VÀO' : '🎯 Kiểm tra ĐẦU RA'}</b>
        <span>Câu ${s.idx + 1}/${s.questions.length} · ${esc(q.skill)}</span>
      </div>
      <div class="quiz-q">${esc(q.q)}</div>
      <div id="quizOpts">${opts}</div>
      <div class="cfg-warn" style="margin-top:8px">
        💡 Bài này chưa chấm ngay đâu — con cứ chọn theo suy nghĩ của mình nhé!
      </div>
    </div></div>`;

  const old = document.getElementById('quizCard');
  if (old) old.remove();
  document.getElementById('chatInner').appendChild(card);
  scrollBottom();
}

function pickTest(i){
  const s = testState;
  s.answers[s.questions[s.idx].i] = i;   // ghi nhận, KHÔNG báo đúng/sai (bài đo năng lực)
  s.idx++;
  if (s.idx < s.questions.length) renderTest();
  else submitTest();
}

async function submitTest(){
  const s = testState;
  document.getElementById('quizCard').querySelector('.quiz-card').innerHTML =
    '<div class="quiz-result"><div class="big">⏳</div><div class="score">Đang chấm bài...</div></div>';

  const fd = new FormData();
  fd.append('action', 'test_submit');
  fd.append('phase', s.phase);
  fd.append('answers', JSON.stringify(s.answers));

  let d;
  try { d = await callApi(API, { method:'POST', body:fd }); }
  catch (e) { toast('Lỗi nộp bài 😢'); return; }
  if (d.status !== 'success') { toast(d.message); return; }

  const pctScore = Math.round(d.score / d.total * 100);
  const emoji = pctScore >= 80 ? '🏆' : (pctScore >= 50 ? '👏' : '💪');

  // Nếu là bài đầu ra và đã có bài đầu vào → khoe mức tiến bộ ngay
  let cmp = '';
  if (s.phase === 'post' && d.pre) {
    const diff = d.score - d.pre.score;
    cmp = `<div class="improve" style="margin-top:12px">
             <div class="big">${diff >= 0 ? '+' : ''}${diff} câu</div>
             <div class="txt">Trước khi học: <b>${d.pre.score}/${d.pre.total}</b> →
               Sau khi học: <b>${d.score}/${d.total}</b>
               ${diff > 0 ? '<br>Con đã tiến bộ rất nhiều! 🎉' : ''}</div>
           </div>`;
  }

  const skills = Object.entries(d.skills).map(([k, v]) => {
    const ok = v.ok >= v.total;
    return `<div class="prog-line" style="margin-bottom:6px">
      <span style="flex:1;font-size:12px;text-align:left">${ok ? '✅' : '📌'} ${esc(k)}</span>
      <b style="font-size:12px;color:${ok ? '#177C4F' : '#B23B3B'}">${v.ok}/${v.total}</b>
    </div>`;
  }).join('');

  const weak = (d.wrong || []).slice(0, 3);

  document.getElementById('quizCard').querySelector('.quiz-card').innerHTML = `
    <div class="quiz-result">
      <div class="big">${emoji}</div>
      <div class="score">${d.score} / ${d.total} câu đúng</div>
      <div class="msg2">${s.phase === 'pre'
        ? 'Đây là điểm khởi đầu của con. Học xong các chủ đề rồi làm bài ĐẦU RA để xem mình tiến bộ bao nhiêu nhé!'
        : 'Con đã hoàn thành bài kiểm tra đầu ra!'}</div>
    </div>
    ${cmp}
    <div class="prog-chart" style="margin-top:12px;margin-bottom:0">
      <h4>Kết quả theo kỹ năng</h4>${skills}
    </div>
    ${weak.length ? `<div class="quiz-explain" style="margin-top:10px">
      💡 <b>Con nên ôn thêm:</b> ${weak.map(w => esc(TOPIC_LABEL[w] || w)).join(', ')}.
      Hỏi mình về những chủ đề này nhé!</div>` : ''}
    <button class="quiz-next" onclick="openBadges()">🏅 Xem huy hiệu & tiến bộ</button>`;

  loadProgress();
  checkBadges();      // có thể vừa mở khoá huy hiệu mới
  scrollBottom();
}

/* Câu hỏi tương ứng mỗi chủ đề (dùng cho nút "Hỏi AI Gia sư") */
const TOPIC_ASK = {
  'den-3-mau':'Đèn giao thông có mấy màu?','den-do':'Đèn đỏ nghĩa là gì?',
  'den-vang':'Đèn vàng có được đi không?','den-xanh':'Đèn xanh nghĩa là gì?',
  'mu-bao-hiem':'Đội mũ bảo hiểm thế nào là đúng?','vach-ke':'Sang đường thế nào cho an toàn?',
  'bien-bao':'Các loại biển báo giao thông?','bien-stop':'Biển STOP nghĩa là gì?',
  'xe-dap':'Đi xe đạp an toàn thế nào?','day-an-toan':'Ngồi ô tô cần lưu ý gì?',
  'cuu-thuong':'Gặp xe cứu thương thì làm gì?','canh-sat':'Cảnh sát giao thông ra hiệu thế nào?',
  'duong-sat':'Qua đường sắt cần chú ý gì?','diem-mu':'Điểm mù của xe tải là gì?',
  'xe-buyt':'Đi xe buýt cần lưu ý gì?','troi-mua':'Trời mưa đi đường thế nào?',
  'ban-dem':'Đi bộ ban đêm có nguy hiểm không?','nga-tu':'Ngã tư cần chú ý gì?',
  'via-he':'Đi bộ trên vỉa hè thế nào?','choi-duong':'Bóng lăn ra đường thì làm sao?',
  'lac-duong':'Con bị lạc thì phải làm gì?','toc-do':'Tốc độ ảnh hưởng thế nào?',
};

/* Tên chủ đề (để gợi ý ôn tập) */
const TOPIC_LABEL = {
  'den-3-mau':'Đèn giao thông','den-do':'Đèn đỏ','den-vang':'Đèn vàng','den-xanh':'Đèn xanh',
  'mu-bao-hiem':'Mũ bảo hiểm','vach-ke':'Sang đường','bien-bao':'Biển báo','bien-stop':'Biển STOP',
  'xe-dap':'Đi xe đạp','day-an-toan':'Dây an toàn','cuu-thuong':'Xe ưu tiên','canh-sat':'Cảnh sát giao thông',
  'duong-sat':'Đường sắt','diem-mu':'Điểm mù xe tải','xe-buyt':'Đi xe buýt','troi-mua':'Trời mưa',
  'ban-dem':'Đi đường ban đêm','nga-tu':'Ngã tư','via-he':'Vỉa hè','choi-duong':'Chơi trên đường',
  'lac-duong':'Bị lạc đường','toc-do':'Tốc độ',
};

/* Bé chưa làm bài ĐẦU VÀO → mời làm (chỉ mời 1 lần cho đỡ phiền) */
async function suggestPreTest(){
  try {
    const d = await callApi(API + '?action=test_status');
    testData = d;
    if (d.status !== 'success' || d.pre) return;         // đã làm rồi → thôi
    if (localStorage.getItem('aigs_pretest_shown')) return;
    localStorage.setItem('aigs_pretest_shown', '1');

    setTimeout(() => {
      const box = document.createElement('div');
      box.className = 'msg bot';
      box.innerHTML = `<div class="msg-avatar">📝</div>
        <div class="msg-body"><div class="quiz-card">
          <div class="quiz-q">Trước khi bắt đầu học, con làm thử <b>bài kiểm tra đầu vào</b> nhé!</div>
          <div class="cfg-warn" style="margin:0 0 10px">
            15 câu · Không chấm điểm để so bì với ai cả 😊<br>
            Mục đích là để biết con đang ở đâu — học xong rồi làm lại,
            con sẽ thấy mình tiến bộ bao nhiêu!
          </div>
          <button class="quiz-next" onclick="startTest('pre')">📝 Làm bài kiểm tra đầu vào</button>
          <button class="progress-btn" style="background:#EFF5FD;color:#4A5F8F;margin-top:7px"
                  onclick="this.closest('.msg').remove()">Để sau, con muốn học trước</button>
        </div></div>`;
      document.getElementById('chatInner').appendChild(box);
      scrollBottom();
    }, 1200);
  } catch (e) { }
}

/* =====================================================================
   CÀI ĐẶT AI THẬT (Gemini) — dán key ngay trên web, không cần sửa code
   ===================================================================== */
let aiCfg = null;

async function loadAiCfg(){
  try {
    aiCfg = await callApi(API + '?action=get_config');
    const b = document.getElementById('aiBadge');
    if (aiCfg.has_key) {
      b.className = 'ai-badge real';
      b.textContent = '✨ AI thật (Gemini)';
      b.title = 'Đang dùng AI thật — bấm để đổi key';
    } else {
      b.className = 'ai-badge off';
      b.textContent = '⚙️ Bật AI thật';
      b.title = 'Đang chạy offline — bấm để bật AI thật';
    }
  } catch (e) { }
}

function openAiSetup(){
  document.getElementById('aiModal').classList.add('open');
  renderAiSetup();
}

function renderAiSetup(msg, isErr){
  const c = aiCfg || { has_key:false, masked:'', model:'gemini-2.5-flash', writable:true };

  const state = c.has_key
    ? `<div class="cfg-state on">
         ✨ <b>Đang chạy AI THẬT</b> (Google Gemini · ${esc(c.model)})<br>
         Key: <code>${esc(c.masked)}</code><br>
         AI trả lời được mọi câu hỏi, nhớ ngữ cảnh, và nhận diện được ảnh biển báo 📷
       </div>`
    : `<div class="cfg-state off">
         ⚙️ <b>Đang chạy chế độ OFFLINE</b><br>
         AI chỉ trả lời được 23 chủ đề có sẵn, gặp câu lạ sẽ không biết.<br>
         Dán API key vào bên dưới để bật <b>AI thật</b> — miễn phí!
       </div>`;

  const note = msg
    ? `<div class="cfg-state ${isErr ? 'off' : 'on'}">${esc(msg)}</div>`
    : '';

  const warn = c.writable ? '' :
    `<div class="cfg-state off">⚠️ Thư mục không cho ghi file. Bạn hãy dán key thẳng vào dòng
     <code>$__key = '';</code> ở đầu file <b>ai-gia-su.php</b> nhé.</div>`;

  document.getElementById('aiArea').innerHTML = `
    <h3>✨ Bật AI thật cho AI Gia sư</h3>
    <div class="modal-sub">Dùng Google Gemini — miễn phí, không cần thẻ tín dụng</div>
    ${note}${state}${warn}

    <div class="cfg-box">
      <div class="cfg-step"><b>Bước 1.</b> Mở
        <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a>
        → đăng nhập Google.</div>
      <div class="cfg-step"><b>Bước 2.</b> Bấm <b>Create API key</b> → sao chép chuỗi key
        (bắt đầu bằng <code>AIza...</code>).</div>
      <div class="cfg-step"><b>Bước 3.</b> Dán vào ô dưới đây rồi bấm <b>Lưu &amp; bật AI thật</b>.</div>

      <input class="cfg-input" id="keyInput" type="text" autocomplete="off"
             placeholder="AIzaSy........................................">

      <button class="quiz-next" onclick="saveKey()">🚀 Lưu &amp; bật AI thật</button>
      ${c.has_key ? `<button class="progress-btn" style="background:#FFEAEA;color:#B23B3B;margin-top:8px"
                       onclick="removeKey()">🗑️ Xoá key (quay về offline)</button>` : ''}

      <div class="cfg-warn">
        🔒 Key được lưu trong file <code>aigs-config.php</code> trên máy bạn, không gửi đi đâu khác.
        <b>Đừng đưa file này lên GitHub</b> nhé!<br>
        💡 Bật AI thật rồi, phần <b>nhận diện ảnh biển báo</b> 📷 cũng hoạt động luôn.
      </div>
    </div>`;
}

async function saveKey(){
  const k = document.getElementById('keyInput').value.trim();
  if (!k) { toast('Con dán API key vào ô trước nhé!'); return; }

  renderAiSetup('⏳ Đang kiểm tra key với Google, chờ chút...', false);
  const fd = new FormData();
  fd.append('action', 'save_key');
  fd.append('key', k);

  try {
    const d = await callApi(API, { method:'POST', body:fd });
    if (d.status === 'success') {
      await loadAiCfg();
      renderAiSetup(d.msg, false);
      toast('🎉 Đã bật AI thật!');
      setTimeout(() => location.reload(), 1800);
    } else {
      renderAiSetup(d.message, true);
    }
  } catch (e) {
    renderAiSetup('Lỗi kết nối máy chủ: ' + e.message, true);
  }
}

async function removeKey(){
  if (!confirm('Xoá API key và quay về chế độ offline?')) return;
  const fd = new FormData();
  fd.append('action', 'save_key');
  fd.append('key', '');
  try {
    await callApi(API, { method:'POST', body:fd });
    await loadAiCfg();
    renderAiSetup('Đã xoá key — quay về chế độ offline.', false);
    setTimeout(() => location.reload(), 1200);
  } catch (e) { toast('Không xoá được 😢'); }
}

/* =====================================================================
   XEM TO HÌNH MINH HOẠ + THƯ VIỆN HÌNH
   ===================================================================== */

/* Câu hỏi tương ứng mỗi hình (bấm "Hỏi AI" là gửi luôn) */
const ILLUS_ASK = {
  'den-3-mau':'Đèn giao thông có mấy màu?', 'den-do':'Đèn đỏ nghĩa là gì?',
  'den-vang':'Đèn vàng có được đi không?',  'den-xanh':'Đèn xanh nghĩa là gì?',
  'mu-bao-hiem':'Đội mũ bảo hiểm thế nào là đúng?',
  'vach-ke':'Sang đường thế nào cho an toàn?',
  'diem-mu':'Điểm mù của xe tải là gì?',
  'ban-dem':'Đi bộ ban đêm có nguy hiểm không?',
  'cuu-thuong':'Con nên làm gì khi gặp xe cứu thương?',
  'bien-stop':'Biển STOP nghĩa là gì?',
  'xe-dap':'Đi xe đạp an toàn thế nào?',
  'day-an-toan':'Ngồi ô tô có cần thắt dây an toàn không?',
};

/* Bấm vào hình → xem to */
function zoomIllus(key){
  const it = ILLUS[key];
  if (!it || !it.svg) return;
  let lb = document.getElementById('illusBox');
  if (!lb) {
    lb = document.createElement('div');
    lb.id = 'illusBox';
    lb.className = 'lightbox';
    lb.onclick = () => lb.classList.remove('open');
    document.body.appendChild(lb);
  }
  lb.innerHTML = `<div class="close">✕</div>
    <div class="illus-zoom" onclick="event.stopPropagation()">
      ${it.svg}
      <div class="iz-cap">${it.cap}</div>
      <button class="quiz-next" onclick="askIllus('${key}')">🤖 Hỏi AI Gia sư về hình này</button>
    </div>`;
  lb.classList.add('open');
}

function askIllus(key){
  const lb = document.getElementById('illusBox');
  if (lb) lb.classList.remove('open');
  document.getElementById('chatText').value = ILLUS_ASK[key] || 'Giải thích hình này giúp con';
  sendMsg();
}

/* Thư viện hình minh hoạ — xem tất cả trong 1 chỗ */
function openGallery(){
  const keys = Object.keys(ILLUS).filter(k => ILLUS[k].svg);
  document.getElementById('signModal').classList.add('open');
  document.getElementById('signArea').innerHTML = `
    <h3>🖼️ Thư viện hình minh hoạ</h3>
    <div class="modal-sub">${keys.length} hình minh hoạ · Bấm vào hình để xem to và hỏi AI</div>
    <div class="gal-grid">
      ${keys.map(k => `
        <div class="gal-item" onclick="closeModal('signModal'); zoomIllus('${k}')">
          <div class="gal-visual">${ILLUS[k].svg}</div>
          <div class="gal-cap">${ILLUS[k].cap}</div>
        </div>`).join('')}
    </div>
    <button class="progress-btn" style="background:#EFF5FD;color:#4A5F8F;margin-top:12px"
            onclick="openSigns()">🚸 Xem tiếp Thư viện biển báo (28 biển) →</button>`;
}

/* Bấm 1 biển trong chat → mở thẳng chi tiết biển đó trong Thư viện */
async function openSignFromChat(code){
  if (!SIGNS) {
    try {
      const d = await callApi(API + '?action=signs');
      if (d.status !== 'success') return;
      SIGNS = d.library;
    } catch (e) { return; }
  }
  // Nhảy đúng vào nhóm chứa biển này để ảnh thật cũng được tải
  Object.entries(SIGNS).forEach(([k, g]) => {
    if (g.signs.some(s => s.code === code)) signGroup = k;
  });
  document.getElementById('signModal').classList.add('open');
  showSign(code);
  loadSignPhotos(signGroup);
}

/* Xem ảnh phóng to */
function zoom(img){
  let lb = document.getElementById('lightbox');
  if (!lb) {
    lb = document.createElement('div');
    lb.id = 'lightbox';
    lb.className = 'lightbox';
    lb.innerHTML = '<div class="close">✕</div><img alt="">';
    lb.onclick = () => lb.classList.remove('open');
    document.body.appendChild(lb);
  }
  lb.querySelector('img').src = img.src;
  lb.classList.add('open');
}

function addBotMsg(text, isError, illus, photos, quiz, next, animate, signs){
  if (!isError) chatLog.push({ role:'bot', content:text });
  const art = isError ? '' : buildArt(illus, photos, signs);

  // Gợi ý câu hỏi tiếp theo
  const nextBox = (!isError && next && next.length)
    ? `<div class="next-box">` + next.map(q =>
        `<div class="next-chip" onclick="askSuggested(this)">${esc(q)} →</div>`).join('') + `</div>`
    : '';

  const d = document.createElement('div');
  d.className = 'msg bot' + (isError ? ' error' : '');
  d.innerHTML = `<div class="msg-avatar">${isError ? '⚠️' : personaEmoji()}</div>
    <div class="msg-body">
      <div class="msg-bubble"></div>
      ${art}
      ${isError ? '' : `<div class="msg-tools">
        ${quizButton(quiz)}
        <div class="msg-tool" onclick="speakMsg(this)">🔊 Nghe</div>
        <div class="msg-tool" onclick="regenerate(this)">🔄 Trả lời cách khác</div>
        <div class="msg-tool" onclick="feedback(this)">👍 Hữu ích</div>
      </div>`}
      ${nextBox}
    </div>`;
  document.getElementById('chatInner').appendChild(d);

  const bubble = d.querySelector('.msg-bubble');
  if (animate && !isError) {
    typeWriter(bubble, text);           // chữ chạy dần như ChatGPT
  } else {
    bubble.innerHTML = fmt(text);       // lịch sử cũ → hiện ngay
  }
}
function showTyping(){
  const d = document.createElement('div');
  d.className = 'msg bot'; d.id = 'typingMsg';
  d.innerHTML = `<div class="msg-avatar">🤖</div>
    <div class="msg-body"><div class="msg-bubble">
      <span class="typing-dots"><i></i><i></i><i></i></span>
    </div></div>`;
  document.getElementById('chatInner').appendChild(d);
  scrollBottom();
}
function hideTyping(){ const t = document.getElementById('typingMsg'); if (t) t.remove(); }

/* ---------- Gửi tin nhắn ---------- */
async function sendMsg(){
  const input = document.getElementById('chatText');
  const text  = input.value.trim();
  const img   = pendingImage;
  if ((!text && !img) || sending) return;

  sending = true;
  input.value = '';
  addUserMsg(text || '📷 Con gửi ảnh này, AI xem giúp con nhé!', img);
  clearImage();
  showTyping();
  scrollBottom();

  try {
    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('session_id', currentSessionId);
    fd.append('message', text);
    fd.append('persona', persona);
    if (img) fd.append('image', img);

    const [data] = await Promise.all([
      callApi(API, { method:'POST', body:fd }),
      new Promise(r => setTimeout(r, img ? 300 : 600)),   // hiệu ứng "đang gõ"
    ]);
    hideTyping();

    if (data.status === 'success') {
      const isNew = (currentSessionId === 0);
      currentSessionId = data.session_id;
      // tham số áp chót = true → chữ chạy dần như ChatGPT
      addBotMsg(data.reply, false, data.illus, data.photos, data.quiz, data.next, true, data.signs);
      if (data.illus) loadProgress();   // cập nhật tiến độ khi học chủ đề mới
      if (isNew) loadSessions();
    } else {
      addBotMsg(data.message, true);   // hiện ĐÚNG lỗi thật
    }
  } catch (e) {
    hideTyping();
    addBotMsg(e.message, true);
    checkSystem();
  }

  sending = false;
  scrollBottom();
  input.focus();
}
function askSuggested(el){
  document.getElementById('chatText').value = el.textContent.trim();
  sendMsg();
}

/* ---------- Lịch sử trò chuyện ---------- */
function groupLabel(dateStr){
  const d = new Date(String(dateStr).replace(' ','T'));
  const now = new Date();
  const a = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const b = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const diff = Math.round((a - b) / 86400000);
  if (diff <= 0) return 'Hôm nay';
  if (diff < 7)  return '7 ngày qua';
  return 'Cũ hơn';
}
async function loadSessions(){
  try {
    const data = await callApi(API + '?action=sessions');
    const box = document.getElementById('sessionList');
    box.innerHTML = '';
    if (data.status !== 'success') return;
    if (data.sessions.length === 0) {
      box.innerHTML = '<div class="side-link group-label">Chưa có cuộc trò chuyện</div>';
      return;
    }
    let last = '';
    data.sessions.forEach(s => {
      const g = groupLabel(s.updated_at);
      if (g !== last) {
        box.insertAdjacentHTML('beforeend', `<div class="side-link group-label">${g}</div>`);
        last = g;
      }
      box.appendChild(sessionItem(s));
    });
  } catch (e) { /* lỗi đã hiện ở thanh sysbar */ }
}

/* Tạo 1 dòng cuộc trò chuyện — dùng chung cho danh sách và kết quả tìm kiếm */
function sessionItem(s){
  const item = document.createElement('div');
  item.className = 'side-link session-item' +
    (String(s.id) === String(currentSessionId) ? ' active' : '');
  item.dataset.id = s.id;
  item.innerHTML =
    `<span class="s-title">💬 ${esc(s.title)}</span>` +
    `<span class="del ren" title="Đổi tên">✎</span>` +
    `<span class="del rm" title="Xoá">✕</span>`;
  item.addEventListener('click', () => openSession(s.id));
  item.querySelector('.ren').addEventListener('click', ev => {
    ev.stopPropagation();
    renameSession(s.id, s.title);
  });
  item.querySelector('.rm').addEventListener('click', ev => {
    ev.stopPropagation();
    deleteSession(s.id);
  });
  return item;
}

/* Đổi tên cuộc trò chuyện */
async function renameSession(id, oldTitle){
  const t = prompt('Đổi tên cuộc trò chuyện:', oldTitle);
  if (t === null || t.trim() === '') return;
  const fd = new FormData();
  fd.append('action', 'rename');
  fd.append('session_id', id);
  fd.append('title', t.trim());
  try {
    await callApi(API, { method:'POST', body:fd });
    toast('Đã đổi tên ✏️');
    loadSessions();
  } catch (e) { toast('Không đổi được tên 😢'); }
}
async function openSession(id){
  currentSessionId = id;
  document.querySelectorAll('.session-item').forEach(el =>
    el.classList.toggle('active', String(el.dataset.id) === String(id)));
  document.getElementById('chatInner').innerHTML = '';
  chatLog = [];
  try {
    const data = await callApi(API + '?action=messages&session_id=' + id);
    if (data.status === 'success') {
      data.messages.forEach(m => m.role === 'user' ? addUserMsg(m.content) : addBotMsg(m.content, false, m.illus, m.photos, null, null, false));
    }
  } catch (e) { addBotMsg(e.message, true); }
  scrollBottom();
}
function newChat(focus = true){
  currentSessionId = 0;
  chatLog = [];
  document.getElementById('chatInner').innerHTML = '';
  document.querySelectorAll('.session-item').forEach(el => el.classList.remove('active'));
  const p = PERSONAS[persona] || { emoji:'🤖', name:'AI Gia sư' };
  addBotMsg(
    `Chào ${STUDENT_NAME}! ${p.emoji} **${p.name}** đây!\n\n` +
    `Hôm nay con muốn học điều gì về an toàn giao thông nào?\n\n` +
    `💡 Con có thể: gõ câu hỏi, bấm 🎤 để **nói**, hoặc bấm 📎 để **gửi ảnh biển báo** cho mình nhận diện giúp nhé!`,
    false, null, null, null, ['Đèn giao thông có mấy màu?', 'Đội mũ bảo hiểm thế nào là đúng?', 'Sang đường thế nào cho an toàn?']
  );
  scrollBottom();
  if (focus) document.getElementById('chatText').focus();
}
async function deleteSession(id){
  if (!confirm('Xoá cuộc trò chuyện này?')) return;
  const fd = new FormData();
  fd.append('action', 'delete');
  fd.append('session_id', id);
  try { await callApi(API, { method:'POST', body:fd }); } catch (e) { }
  if (String(id) === String(currentSessionId)) newChat(false);
  loadSessions();
  toast('Đã xoá cuộc trò chuyện 🗑️');
}

/* ---------- AI đọc câu trả lời ---------- */
let speakingBtn = null;

function stopSpeak(){
  speechSynthesis.cancel();
  if (speakingBtn) { speakingBtn.textContent = '🔊 Nghe'; speakingBtn = null; }
}

function speakMsg(btn){
  if (!('speechSynthesis' in window)) { toast('Trình duyệt không hỗ trợ đọc giọng nói'); return; }

  // Đang đọc chính câu này → bấm lần nữa để DỪNG
  if (speakingBtn === btn) { stopSpeak(); return; }
  stopSpeak();   // đang đọc câu khác → dừng câu đó trước

  const text = btn.closest('.msg-body').querySelector('.msg-bubble').innerText;
  const u = new SpeechSynthesisUtterance(
    text.replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}]/gu, '')
  );
  u.lang = 'vi-VN'; u.rate = 0.95;
  const v = speechSynthesis.getVoices().find(x => x.lang && x.lang.startsWith('vi'));
  if (v) u.voice = v;

  u.onend = u.onerror = () => { if (speakingBtn === btn) stopSpeak(); };

  speakingBtn = btn;
  btn.textContent = '⏹️ Dừng đọc';
  speechSynthesis.speak(u);
}

/* ---------- Bé nói, AI nghe ---------- */
let recog = null, recording = false;
function toggleMic(btn){
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) { toast('Trình duyệt chưa hỗ trợ ghi âm 🎤 — con dùng Google Chrome nhé!'); return; }
  if (recording) { recog.stop(); return; }
  recog = new SR();
  recog.lang = 'vi-VN';
  recog.interimResults = false;
  recog.onstart  = () => { recording = true;  btn.classList.add('rec');    toast('Mình đang nghe... con nói đi! 🎤'); };
  recog.onend    = () => { recording = false; btn.classList.remove('rec'); };
  recog.onresult = e  => { document.getElementById('chatText').value = e.results[0][0].transcript; sendMsg(); };
  recog.onerror  = () => toast('Mình chưa nghe rõ, con thử lại nhé!');
  recog.start();
}

/* ---------- Đánh giá ---------- */
function feedback(el){
  el.parentElement.querySelectorAll('.msg-tool').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  toast('Cảm ơn phản hồi của con! 💛');
}

/* ---------- Xuất báo cáo ---------- */
function exportChat(){
  if (chatLog.length <= 1) { toast('Chưa có nội dung để xuất 📄'); return; }
  let out = 'BÁO CÁO TRÒ CHUYỆN — AI GIA SƯ\n';
  out += 'Học sinh: ' + STUDENT_NAME + '\n';
  out += 'Ngày xuất: ' + new Date().toLocaleString('vi-VN') + '\n';
  out += '----------------------------------------\n\n';
  chatLog.forEach(m => {
    out += (m.role === 'user' ? '🧒 ' + STUDENT_NAME : '🤖 AI Gia sư') + ':\n' + m.content + '\n\n';
  });
  const blob = new Blob(['\ufeff' + out], { type:'text/plain;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'bao-cao-ai-gia-su.txt';
  a.click();
  URL.revokeObjectURL(a.href);
}

/* ---------- Emoji ---------- */
const EMOJIS = ['😊','😄','👍','❤️','🚦','🚗','🛵','🚲','⛑️','🚸','🛑','🚑','👮','⭐','🎉','❓'];
function buildEmojiPop(){
  const pop = document.getElementById('emojiPop');
  EMOJIS.forEach(e => {
    const sp = document.createElement('span');
    sp.textContent = e;
    sp.onclick = () => { const i = document.getElementById('chatText'); i.value += e; i.focus(); };
    pop.appendChild(sp);
  });
  document.addEventListener('click', ev => {
    if (!ev.target.closest('#emojiPop') && !ev.target.closest('[data-emoji-btn]')) pop.classList.remove('open');
  });
}
function toggleEmoji(ev){
  ev.stopPropagation();
  document.getElementById('emojiPop').classList.toggle('open');
}
</script>
</body>
</html>
