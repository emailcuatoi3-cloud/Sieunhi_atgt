-- =========================================================================
-- schema.sql — Cấu trúc cơ sở dữ liệu cho Siêu Nhí An Toàn Giao Thông AI
-- Database: duanmau_atgt
-- Import file này vào MySQL trước khi chạy site (phpMyAdmin / mysql CLI):
--   mysql -u root -p < schema.sql
-- =========================================================================

CREATE DATABASE IF NOT EXISTS duanmau_atgt
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE duanmau_atgt;

-- ---------------------------------------------------------------------
-- Bảng người dùng — dùng chung cho cả 4 vai trò, phân biệt bằng cột `role`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150)        NOT NULL,
  email         VARCHAR(190)        NOT NULL UNIQUE,
  password_hash VARCHAR(255)        NOT NULL,
  role          ENUM('hocsinh','phuhuynh','giaovien','admin') NOT NULL DEFAULT 'hocsinh',
  avatar_emoji  VARCHAR(10)         NOT NULL DEFAULT '🧒',
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
-- Tài khoản demo: KHÔNG tạo trực tiếp bằng SQL ở đây vì password_hash()
-- của PHP sinh ra chuỗi khác nhau mỗi lần chạy (có salt ngẫu nhiên) nên
-- không thể viết cứng một hash "đúng" vào file .sql tĩnh.
--
-- => Sau khi import xong file này, hãy mở seed.php MỘT LẦN trên trình
--    duyệt (vd: http://localhost/seed.php) để tự động tạo 4 tài khoản
--    demo (mật khẩu đều là 123456), rồi XOÁ seed.php đi để tránh bị
--    chạy lại / lộ ra ngoài production.
-- ---------------------------------------------------------------------
