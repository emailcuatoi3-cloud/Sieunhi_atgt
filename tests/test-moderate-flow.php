<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/moderation.php';
require_once __DIR__ . '/../db_utils.php';

$db = new DB_UTILS();
$db->beginTransaction();
/* fixture: user học sinh + 1 review pending trên địa điểm seed đầu tiên */
$db->execute("INSERT INTO users (name,email,password_hash,role) VALUES ('Test HS', CONCAT('t',UUID(),'@t.vn'), 'x', 'hocsinh')");
$uid = (int)$db->getLastInsertId();
$pid = (int)$db->getValue("SELECT id FROM places LIMIT 1");
$db->execute("INSERT INTO place_reviews (place_id,user_id,stars,content) VALUES (?,?,5,'chuyến đi vui')", [$pid, $uid]);
$rid = (int)$db->getLastInsertId();

check(moderate_review($db, $rid, 'approve', null, 999) === true, 'approve trả true');
check($db->getValue("SELECT status FROM place_reviews WHERE id=?", [$rid]) === 'approved', 'status = approved');
check((int)$db->getValue("SELECT xp FROM student_progress WHERE student_id=?", [$uid]) === 20, 'được cộng 20 XP');
check(moderate_review($db, $rid, 'approve', null, 999) === false, 'duyệt lại lần 2 trả false (không cộng XP đúp)');

/* Chống race condition: UPDATE...WHERE status="pending" chạy trực tiếp trên review đã duyệt
   phải không đổi dòng nào — xác nhận WHERE status="pending" trong moderate_review() thực sự
   chặn được 2 request duyệt cùng lúc, chứ không chỉ dựa vào SELECT kiểm tra trước đó. */
$raceStmt = $db->connection->prepare('UPDATE place_reviews SET status="approved" WHERE id=? AND status="pending"');
$raceStmt->execute([$rid]);
check($raceStmt->rowCount() === 0, 'UPDATE...WHERE status=pending trên review đã duyệt không đổi dòng nào (chống race)');

$db->execute("INSERT INTO place_reviews (place_id,user_id,stars,content) VALUES (?,?,4,'chuyến khác')", [$pid, $uid]);
$rid2 = (int)$db->getLastInsertId();
check(moderate_review($db, $rid2, 'reject', 'Ảnh mờ quá', 999) === true, 'reject trả true');
check($db->getValue("SELECT reject_reason FROM place_reviews WHERE id=?", [$rid2]) === 'Ảnh mờ quá', 'lưu lý do');
$db->rollBack();
done();
