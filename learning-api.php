<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

function learningJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function learningUserId(): int
{
    if (!isLoggedIn() || !in_array($_SESSION['user_role'] ?? '', ['hocsinh', 'admin'], true)) {
        learningJson(['status' => 'error', 'message' => 'Hãy đăng nhập bằng tài khoản học sinh.'], 401);
    }
    return (int)$_SESSION['user_id'];
}

$db = new DB_UTILS();
$action = (string)($_REQUEST['action'] ?? 'next');
$studentId = learningUserId();

if ($action === 'next') {
    $ageGroup = (string)($_GET['age_group'] ?? ($_SESSION['user_age_group'] ?? '6-8'));
    if (!in_array($ageGroup, ['6-8', '9-11'], true)) {
        $ageGroup = '6-8';
    }

    try {
        $lesson = $db->getOne(
            'SELECT l.id, l.title, l.slug, l.age_group, l.summary, l.illustration, l.difficulty,
                    t.name AS topic_name, t.icon AS topic_icon
             FROM lessons l JOIN topics t ON t.id = l.topic_id
             WHERE l.status = "published" AND l.age_group = ?
             ORDER BY l.difficulty ASC, l.id ASC LIMIT 1',
            [$ageGroup]
        );
    } catch (Throwable $ignored) {
        $lesson = null;
    }

    learningJson([
        'status' => 'success',
        'age_group' => $ageGroup,
        'lesson' => $lesson ?: [
            'id' => 0,
            'title' => 'Khởi động hành trình an toàn',
            'slug' => 'khoi-dong',
            'age_group' => $ageGroup,
            'summary' => 'Học cách dừng lại, quan sát và chọn hành động an toàn trước khi qua đường.',
            'illustration' => null,
            'difficulty' => 1,
            'topic_name' => 'Kỹ năng qua đường',
            'topic_icon' => '🚸',
        ],
    ]);
}

if ($action === 'attempt') {
    requireCsrf();
    $lessonId = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT) ?: 0;
    $questionId = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT) ?: 0;
    $answerKey = trim((string)($_POST['answer_key'] ?? ''));
    $responseMs = max(0, (int)($_POST['response_ms'] ?? 0));

    if ($lessonId < 1 || $questionId < 1 || $answerKey === '') {
        learningJson(['status' => 'error', 'message' => 'Dữ liệu bài làm chưa đầy đủ.'], 422);
    }

    try {
        $question = $db->getOne(
            'SELECT lesson_id, answer_key, explanation, skill_key, xp_reward FROM questions WHERE id = ? LIMIT 1',
            [$questionId]
        );
    } catch (Throwable $ignored) {
        $question = null;
    }
    if (!$question || (int)$question['lesson_id'] !== $lessonId) {
        learningJson(['status' => 'error', 'message' => 'Không tìm thấy câu hỏi.'], 404);
    }

    $correct = hash_equals((string)$question['answer_key'], $answerKey);
    $pdo = $db->connection;
    $pdo->beginTransaction();
    try {
        $db->execute(
            'INSERT INTO student_attempts (student_id, lesson_id, question_id, answer_key, is_correct, response_ms)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$studentId, $lessonId, $questionId, $answerKey, $correct ? 1 : 0, $responseMs]
        );
        $db->execute(
            'INSERT INTO mastery_scores (student_id, skill_key, score, attempts)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE score = LEAST(100, ((score * attempts) + VALUES(score)) / (attempts + 1)), attempts = attempts + 1',
            [$studentId, $question['skill_key'], $correct ? 100 : 0]
        );
        if ($correct) {
            $db->execute(
                'UPDATE student_progress SET xp = xp + ?, coin = coin + ? WHERE student_id = ?',
                [(int)$question['xp_reward'], max(1, (int)$question['xp_reward'] / 2), $studentId]
            );
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        learningJson(['status' => 'error', 'message' => 'Không thể lưu kết quả lúc này.'], 500);
    }

    learningJson([
        'status' => 'success',
        'correct' => $correct,
        'explanation' => $question['explanation'],
        'xp_earned' => $correct ? (int)$question['xp_reward'] : 0,
        'next_action' => $correct ? 'Thử một tình huống khó hơn' : 'Luyện lại kỹ năng này',
    ]);
}

learningJson(['status' => 'error', 'message' => 'Action không hợp lệ.'], 400);
