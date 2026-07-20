<?php
/**
 * game-progress.php — Xử lý tiến trình học tập / game hoá
 * -----------------------------------------------------------------------
 * require_once file này (đã tự require auth.php bên trong) ở bất kỳ trang
 * nào cần đọc/ghi XP, Coin, Level, Huy hiệu của học sinh.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/auth.php';

/** Mỗi 100 XP lên 1 cấp. Cấp 1 là mặc định (0-99 XP). */
function calcLevel(int $xp): int {
    return intdiv($xp, 100) + 1;
}

/** XP cần để lên cấp tiếp theo */
function xpForNextLevel(int $level): int {
    return $level * 100;
}

/** Yêu cầu cấp độ tối thiểu để mở khoá từng mini-game */
const GAME_UNLOCK_LEVEL = [
    'pedestrian'    => 1,  // Người qua đường thông minh
    'helmet'        => 1,  // Chiếc mũ thần kỳ
    'signDetective' => 2,  // Thám tử biển báo
    'safeRoute'     => 3,  // Đường đến trường an toàn
    'cityHero'      => 4,  // Siêu nhí xử lý tình huống
];

/** Lấy tiến trình của 1 học sinh (tạo dòng mặc định nếu chưa có) */
function getStudentProgress(int $studentId): array {
    $db = new DB_UTILS();
    $row = $db->getOne('SELECT * FROM student_progress WHERE student_id = ?', [$studentId]);
    if (!$row) {
        $db->execute('INSERT INTO student_progress (student_id, xp, coin, streak_days, level) VALUES (?, 0, 0, 0, 1)', [$studentId]);
        $row = $db->getOne('SELECT * FROM student_progress WHERE student_id = ?', [$studentId]);
    }
    return $row;
}

/** Số huy hiệu đã đạt được */
function countStudentBadges(int $studentId): int {
    $db = new DB_UTILS();
    return (int)$db->getValue('SELECT COUNT(*) FROM earned_badges WHERE student_id = ?', [$studentId]);
}

/** Danh sách huy hiệu đã đạt */
function getStudentBadges(int $studentId): array {
    $db = new DB_UTILS();
    return $db->getAll('SELECT * FROM earned_badges WHERE student_id = ? ORDER BY earned_at DESC', [$studentId]);
}

/**
 * Cộng thưởng XP/Coin sau khi hoàn thành 1 mini-game, ghi log lịch sử,
 * và mở khoá huy hiệu nếu có. Trả về thông tin để hiển thị (có lên cấp
 * không, có huy hiệu mới không).
 */
function addGameReward(int $studentId, string $gameId, int $xp, int $coin, ?string $badgeKey = null, ?string $badgeLabel = null): array {
    $db = new DB_UTILS();

    $before = getStudentProgress($studentId);
    $newXp = (int)$before['xp'] + max(0, $xp);
    $newCoin = (int)$before['coin'] + max(0, $coin);
    $oldLevel = (int)$before['level'];
    $newLevel = calcLevel($newXp);

    $db->execute('UPDATE student_progress SET xp = ?, coin = ?, level = ? WHERE student_id = ?', [$newXp, $newCoin, $newLevel, $studentId]);
    $db->execute('INSERT INTO game_sessions (student_id, game_id, xp_earned, coin_earned) VALUES (?, ?, ?, ?)', [$studentId, $gameId, max(0, $xp), max(0, $coin)]);

    $newBadge = false;
    if ($badgeKey) {
        $exists = $db->getOne('SELECT id FROM earned_badges WHERE student_id = ? AND badge_key = ?', [$studentId, $badgeKey]);
        if (!$exists) {
            $db->execute('INSERT INTO earned_badges (student_id, badge_key, badge_label) VALUES (?, ?, ?)', [$studentId, $badgeKey, $badgeLabel ?? $badgeKey]);
            $newBadge = true;
        }
    }

    return [
        'xp' => $newXp,
        'coin' => $newCoin,
        'level' => $newLevel,
        'leveledUp' => $newLevel > $oldLevel,
        'newBadge' => $newBadge,
        'badgeLabel' => $badgeLabel,
        'badgeCount' => countStudentBadges($studentId),
    ];
}

/** Top học sinh theo XP, dùng cho trang Bảng xếp hạng */
function getLeaderboard(int $limit = 20): array {
    $db = new DB_UTILS();
    return $db->getAll(
        'SELECT u.id, u.name, u.avatar_emoji, sp.xp, sp.coin, sp.level, sp.streak_days
         FROM student_progress sp
         JOIN users u ON u.id = sp.student_id
         WHERE u.role = "hocsinh" AND u.status = "active"
         ORDER BY sp.xp DESC
         LIMIT ' . (int)$limit
    );
}

/** Vị trí xếp hạng của 1 học sinh cụ thể (1 = cao nhất) */
function getStudentRank(int $studentId): int {
    $db = new DB_UTILS();
    $rank = $db->getValue(
        'SELECT COUNT(*) + 1 FROM student_progress sp
         JOIN users u ON u.id = sp.student_id
         WHERE u.role = "hocsinh" AND sp.xp > (SELECT xp FROM student_progress WHERE student_id = ?)',
        [$studentId]
    );
    return (int)$rank;
}

/** Game có mở khoá cho học sinh ở cấp độ hiện tại hay không (khách/vai trò khác luôn xem được để demo) */
function isGameUnlocked(string $gameId, bool $isStudent, int $level): bool {
    if (!$isStudent) return true;
    return $level >= (GAME_UNLOCK_LEVEL[$gameId] ?? 1);
}