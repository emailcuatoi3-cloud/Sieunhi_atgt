-- =========================================================================
-- migrate-add-game-tables.sql — Thêm 2 bảng còn thiếu vào database hiện có
-- KHÔNG ảnh hưởng tới dữ liệu users/student_progress đã có sẵn.
--
-- Cách chạy:
--   1. Mở phpMyAdmin → chọn database "duanmau_atgt" (bên trái)
--   2. Vào tab "SQL" → dán toàn bộ nội dung file này vào → bấm "Go"
--   Hoặc dùng dòng lệnh:
--   mysql -u root -p duanmau_atgt < migrate-add-game-tables.sql
-- =========================================================================

USE duanmau_atgt;

CREATE TABLE IF NOT EXISTS game_sessions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id  INT UNSIGNED NOT NULL,
  game_id     VARCHAR(50)  NOT NULL,
  xp_earned   INT UNSIGNED NOT NULL DEFAULT 0,
  coin_earned INT UNSIGNED NOT NULL DEFAULT 0,
  played_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS earned_badges (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id  INT UNSIGNED NOT NULL,
  badge_key   VARCHAR(50)  NOT NULL,
  badge_label VARCHAR(150) NOT NULL,
  earned_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_student_badge (student_id, badge_key)
) ENGINE=InnoDB;
