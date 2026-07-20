<?php
/* ============================================================
   API AI GIA SƯ — xử lý chat và lịch sử trò chuyện
   Các action:
     GET  ?action=sessions                  → danh sách cuộc trò chuyện
     GET  ?action=messages&session_id=...   → tin nhắn của 1 cuộc trò chuyện
     POST action=send (session_id, message) → gửi tin nhắn, nhận trả lời AI
     POST action=delete (session_id)        → xoá 1 cuộc trò chuyện

   Tích hợp với hệ thống có sẵn của dự án:
   - Kết nối DB qua DB_UTILS (db_utils.php) thay vì db.php riêng.
   - Nếu học sinh đã đăng nhập → dùng đúng user_id thật (auth.php),
     lịch sử chat gắn liền với tài khoản của mình.
   - Nếu chưa đăng nhập (khách dùng thử) → dùng chung "tài khoản demo"
     id = 1 giống thiết kế gốc.
   ============================================================ */

require_once __DIR__ . '/auth.php';   // session + currentUser() (đã tự kết nối DB bên trong)
require_once __DIR__ . '/ai-engine.php'; // bộ não AI

header('Content-Type: application/json; charset=utf-8');

$pdo = (new DB_UTILS())->connection;  // lấy PDO thật từ wrapper DB_UTILS của dự án

$userId = isLoggedIn() ? (int) $_SESSION['user_id'] : 1; // chưa đăng nhập → dùng tài khoản demo id = 1
$action = $_REQUEST['action'] ?? '';

function json_out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Kiểm tra cuộc trò chuyện có thuộc về người dùng hiện tại không */
function own_session(PDO $pdo, int $sid, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT id FROM ai_chat_sessions WHERE id = ? AND user_id = ?");
    $stmt->execute([$sid, $userId]);
    return (bool) $stmt->fetch();
}

switch ($action) {

    /* ---------- Danh sách cuộc trò chuyện (sidebar) ---------- */
    case 'sessions':
        $stmt = $pdo->prepare(
            "SELECT id, title, updated_at FROM ai_chat_sessions
             WHERE user_id = ? ORDER BY updated_at DESC LIMIT 50"
        );
        $stmt->execute([$userId]);
        json_out(['status' => 'success', 'sessions' => $stmt->fetchAll()]);

    /* ---------- Tin nhắn của 1 cuộc trò chuyện ---------- */
    case 'messages':
        $sid = (int) ($_GET['session_id'] ?? 0);
        if (!own_session($pdo, $sid, $userId)) {
            json_out(['status' => 'error', 'message' => 'Không tìm thấy cuộc trò chuyện']);
        }
        $stmt = $pdo->prepare(
            "SELECT role, content, created_at FROM ai_chat_messages
             WHERE session_id = ? ORDER BY id ASC"
        );
        $stmt->execute([$sid]);
        json_out(['status' => 'success', 'messages' => $stmt->fetchAll()]);

    /* ---------- Gửi tin nhắn + nhận trả lời AI ---------- */
    case 'send':
        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            json_out(['status' => 'error', 'message' => 'Tin nhắn trống']);
        }
        if (mb_strlen($message, 'UTF-8') > 1000) {
            json_out(['status' => 'error', 'message' => 'Tin nhắn quá dài (tối đa 1000 ký tự)']);
        }

        $sid = (int) ($_POST['session_id'] ?? 0);

        // Chưa có cuộc trò chuyện → tạo mới, lấy câu hỏi đầu tiên làm tiêu đề
        if ($sid === 0) {
            $title = mb_substr($message, 0, 40, 'UTF-8');
            if (mb_strlen($message, 'UTF-8') > 40) $title .= '…';
            $stmt = $pdo->prepare("INSERT INTO ai_chat_sessions (user_id, title) VALUES (?, ?)");
            $stmt->execute([$userId, $title]);
            $sid = (int) $pdo->lastInsertId();
        } elseif (!own_session($pdo, $sid, $userId)) {
            json_out(['status' => 'error', 'message' => 'Không tìm thấy cuộc trò chuyện']);
        }

        // AI "suy nghĩ" câu trả lời (dựa trên lịch sử hội thoại trước đó)
        $reply = ai_get_reply($pdo, $sid, $message);

        // Lưu tin nhắn của bé và của AI vào CSDL
        $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, role, content) VALUES (?, 'user', ?)");
        $stmt->execute([$sid, $message]);
        $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, role, content) VALUES (?, 'bot', ?)");
        $stmt->execute([$sid, $reply]);

        // Cập nhật thời gian để cuộc trò chuyện nhảy lên đầu danh sách
        $pdo->prepare("UPDATE ai_chat_sessions SET updated_at = NOW() WHERE id = ?")->execute([$sid]);

        json_out([
            'status'     => 'success',
            'session_id' => $sid,
            'reply'      => $reply,
            'engine'     => (GEMINI_API_KEY !== '') ? 'gemini' : 'offline',
        ]);

    /* ---------- Xoá cuộc trò chuyện ---------- */
    case 'delete':
        $sid = (int) ($_POST['session_id'] ?? 0);
        if (!own_session($pdo, $sid, $userId)) {
            json_out(['status' => 'error', 'message' => 'Không tìm thấy cuộc trò chuyện']);
        }
        // Xoá session → tin nhắn tự xoá theo (ON DELETE CASCADE)
        $pdo->prepare("DELETE FROM ai_chat_sessions WHERE id = ?")->execute([$sid]);
        json_out(['status' => 'success']);

    default:
        json_out(['status' => 'error', 'message' => 'Action không hợp lệ']);
}