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
require_once __DIR__ . '/lib/personalize.php'; // chip gợi ý cá nhân hoá
require_once __DIR__ . '/lib/ai-limit.php'; // rate limit dùng chung cho các API gọi AI

header('Content-Type: application/json; charset=utf-8');

$pdo = (new DB_UTILS())->connection;  // lấy PDO thật từ wrapper DB_UTILS của dự án

$isGuest = !isLoggedIn();
$userId = $isGuest ? 0 : (int) $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
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
        if ($isGuest) {
            json_out(['status' => 'success', 'sessions' => [], 'guest' => true]);
        }
        $stmt = $pdo->prepare(
            "SELECT id, title, updated_at FROM ai_chat_sessions
             WHERE user_id = ? ORDER BY updated_at DESC LIMIT 50"
        );
        $stmt->execute([$userId]);
        json_out(['status' => 'success', 'sessions' => $stmt->fetchAll()]);

    /* ---------- Tin nhắn của 1 cuộc trò chuyện ---------- */
    case 'messages':
        if ($isGuest) {
            json_out(['status' => 'error', 'message' => 'Đăng nhập để xem lịch sử trò chuyện.']);
        }
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
        requireCsrf();
        allowAiRequest();
        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            json_out(['status' => 'error', 'message' => 'Tin nhắn trống']);
        }
        if (mb_strlen($message, 'UTF-8') > 1000) {
            json_out(['status' => 'error', 'message' => 'Tin nhắn quá dài (tối đa 1000 ký tự)']);
        }

        $sid = (int) ($_POST['session_id'] ?? 0);
        $ageGroup = (string)($_POST['age_group'] ?? ($_SESSION['user_age_group'] ?? '6-8'));
        if (!in_array($ageGroup, ['6-8', '9-11'], true)) $ageGroup = '6-8';

        // Chưa có cuộc trò chuyện → tạo mới, lấy câu hỏi đầu tiên làm tiêu đề
        if ($isGuest) {
            $reply = ai_get_reply($pdo, 0, $message, $ageGroup);
            $topic = ai_detect_topic($message) ?? ai_detect_topic($reply);
            json_out([
                'status' => 'success',
                'session_id' => 0,
                'reply' => $reply,
                'engine' => (GEMINI_API_KEY !== '') ? 'gemini' : 'offline',
                'guest' => true,
                'sources' => ['Luật Trật tự, an toàn giao thông đường bộ · 36/2024/QH15'],
                'suggested_actions' => ['Cho con xem hình', 'Thử tình huống', 'Luyện lại'],
                'safety' => 'safe',
                'topic' => $topic,
                'art_url' => $topic !== null ? 'art.php?code=' . $topic : null,
            ]);
        }

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
        $reply = ai_get_reply($pdo, $sid, $message, $ageGroup);

        // Lưu tin nhắn của bé và của AI vào CSDL
        $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, role, content) VALUES (?, 'user', ?)");
        $stmt->execute([$sid, $message]);
        $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, role, content) VALUES (?, 'bot', ?)");
        $stmt->execute([$sid, $reply]);

        // Cập nhật thời gian để cuộc trò chuyện nhảy lên đầu danh sách
        $pdo->prepare("UPDATE ai_chat_sessions SET updated_at = NOW() WHERE id = ?")->execute([$sid]);

        $topic = ai_detect_topic($message) ?? ai_detect_topic($reply);
        json_out([
            'status'     => 'success',
            'session_id' => $sid,
            'reply'      => $reply,
            'engine'     => (GEMINI_API_KEY !== '') ? 'gemini' : 'offline',
            'sources' => ['Luật Trật tự, an toàn giao thông đường bộ · 36/2024/QH15'],
            'suggested_actions' => ['Cho con xem hình', 'Thử tình huống', 'Luyện lại'],
            'safety' => 'safe',
            'topic' => $topic,
            'art_url' => $topic !== null ? 'art.php?code=' . $topic : null,
        ]);

    /* ---------- Xoá cuộc trò chuyện ---------- */
    case 'delete':
        requireCsrf();
        $sid = (int) ($_POST['session_id'] ?? 0);
        if (!own_session($pdo, $sid, $userId)) {
            json_out(['status' => 'error', 'message' => 'Không tìm thấy cuộc trò chuyện']);
        }
        // Xoá session → tin nhắn tự xoá theo (ON DELETE CASCADE)
        $pdo->prepare("DELETE FROM ai_chat_sessions WHERE id = ?")->execute([$sid]);
        json_out(['status' => 'success']);

    /* ---------- Chip gợi ý cá nhân hoá (hoạt động cả khách) ---------- */
    case 'chips':
        $fav = $weak = $recent = []; $band = 'tieu-hoc';
        if (!$isGuest) {
            $stmt = $pdo->prepare('SELECT grade_band, fav_topics FROM user_preferences WHERE user_id = ?');
            $stmt->execute([$userId]);
            if ($p = $stmt->fetch()) {
                $band = $p['grade_band'];
                $fav = array_filter(explode(',', $p['fav_topics']));
            }
            // Chủ đề "yếu": game có XP trung bình thấp nhất trong các game đã chơi
            $gameTopic = ['game-helmet' => 'mu-bao-hiem', 'game-sign-detective' => 'bien-bao',
                          'game-pedestrian' => 'qua-duong', 'game-safe-route' => 'qua-duong',
                          'game-city-hero' => 'den-tin-hieu'];
            $stmt = $pdo->prepare('SELECT game_id, AVG(xp_earned) a FROM game_sessions
                                   WHERE student_id = ? GROUP BY game_id ORDER BY a ASC LIMIT 2');
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll() as $g) {
                if (isset($gameTopic[$g['game_id']])) $weak[] = $gameTopic[$g['game_id']];
            }
            // Chủ đề hỏi gần đây
            $stmt = $pdo->prepare('SELECT m.content FROM ai_chat_messages m
                                   JOIN ai_chat_sessions s ON s.id = m.session_id
                                   WHERE s.user_id = ? AND m.role = "user" ORDER BY m.id DESC LIMIT 10');
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll() as $m) {
                $t = ai_detect_topic($m['content']);
                if ($t !== null) $recent[] = $t;
            }
        }
        json_out(['status' => 'success', 'chips' => build_suggested_chips($fav, $weak, array_unique($recent), $band)]);

    default:
        json_out(['status' => 'error', 'message' => 'Action không hợp lệ']);
}
