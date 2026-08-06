-- =========================================================================
-- schema.sql — Cấu trúc cơ sở dữ liệu cho Siêu Nhí An Toàn Giao Thông AI
-- Database: duanmau_atgt
-- Import file này vào MySQL trước khi chạy site (phpMyAdmin / mysql CLI):
--   mysql -u root -p < schema.sql
-- =========================================================================


-- ---------------------------------------------------------------------
-- Bảng người dùng — dùng chung cho cả 4 vai trò, phân biệt bằng cột `role`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150)        NOT NULL,
  email         VARCHAR(190)        NOT NULL UNIQUE,
  password_hash VARCHAR(255)        NOT NULL,
  role          ENUM('hocsinh','phuhuynh','giaovien','admin') NOT NULL DEFAULT 'hocsinh',
  avatar_emoji VARCHAR(10) DEFAULT NULL,
  status        ENUM('active','pending','disabled') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Bảng liên kết Phụ huynh — Học sinh (một phụ huynh có thể theo dõi nhiều con)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS parent_student (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id   INT UNSIGNED NOT NULL,
  student_id  INT UNSIGNED NOT NULL,
  FOREIGN KEY (parent_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_pair (parent_id, student_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Bảng tiến độ học tập của học sinh (XP, Coin, Streak...) — nền tảng cho
-- Dashboard học sinh / phụ huynh sau này đọc dữ liệu thật thay vì mock.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_progress (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id    INT UNSIGNED NOT NULL,
  xp            INT UNSIGNED NOT NULL DEFAULT 0,
  coin          INT UNSIGNED NOT NULL DEFAULT 0,
  streak_days   INT UNSIGNED NOT NULL DEFAULT 0,
  level         INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_student (student_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Bảng lớp học (giáo viên quản lý)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS classes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id  INT UNSIGNED NOT NULL,
  name        VARCHAR(100) NOT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS class_students (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id    INT UNSIGNED NOT NULL,
  student_id  INT UNSIGNED NOT NULL,
  FOREIGN KEY (class_id)   REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_class_student (class_id, student_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Lịch sử chơi game (dùng để tính XP/Coin thật + lịch sử hoạt động)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS game_sessions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id  INT UNSIGNED NOT NULL,
  game_id     VARCHAR(50)  NOT NULL,
  xp_earned   INT UNSIGNED NOT NULL DEFAULT 0,
  coin_earned INT UNSIGNED NOT NULL DEFAULT 0,
  played_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Huy hiệu đã đạt được (mỗi huy hiệu chỉ tính 1 lần dù chơi lại nhiều lần)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS earned_badges (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id  INT UNSIGNED NOT NULL,
  badge_key   VARCHAR(50)  NOT NULL,
  badge_label VARCHAR(150) NOT NULL,
  earned_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_student_badge (student_id, badge_key)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- AI Gia sư — lịch sử trò chuyện (mỗi cuộc trò chuyện là 1 mục sidebar)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_chat_sessions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL DEFAULT 1,
  title       VARCHAR(255) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_chat_messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  session_id  INT NOT NULL,
  role        ENUM('user','bot') NOT NULL,
  content     TEXT NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_session FOREIGN KEY (session_id) REFERENCES ai_chat_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tài khoản demo: KHÔNG tạo trực tiếp bằng SQL ở đây vì password_hash()
-- của PHP sinh ra chuỗi khác nhau mỗi lần chạy (có salt ngẫu nhiên) nên
-- không thể viết cứng một hash "đúng" vào file .sql tĩnh.
--
-- => Sau khi import xong file này, hãy mở seed.php MỘT LẦN trên trình
--    duyệt (vd: http://localhost/seed.php) để tự động tạo 4 tài khoản
--    demo (mật khẩu đều là 123456), rồi XOÁ seed.php đi để tránh bị
--    chạy lại / lộ ra ngoài production.
-- ---------------------------------------------------------------------