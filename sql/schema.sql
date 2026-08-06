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
  age_group     ENUM('6-8','9-11')  NULL DEFAULT '6-8',
  status        ENUM('active','pending','disabled') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Nội dung học có nguồn, phiên bản và cá nhân hóa theo độ tuổi.
CREATE TABLE IF NOT EXISTS topics (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  icon VARCHAR(10) NOT NULL DEFAULT '🚦',
  description TEXT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS legal_sources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  url VARCHAR(500) NOT NULL,
  reference_code VARCHAR(100) NULL,
  effective_from DATE NULL,
  verified_at DATETIME NULL,
  status ENUM('pending','verified','archived') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lessons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  topic_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  age_group ENUM('6-8','9-11') NOT NULL,
  summary TEXT NOT NULL,
  illustration VARCHAR(500) NULL,
  difficulty TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  source_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (topic_id) REFERENCES topics(id),
  FOREIGN KEY (source_id) REFERENCES legal_sources(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id INT UNSIGNED NOT NULL,
  prompt TEXT NOT NULL,
  options_json JSON NOT NULL,
  answer_key VARCHAR(80) NOT NULL,
  explanation TEXT NOT NULL,
  skill_key VARCHAR(100) NOT NULL,
  xp_reward SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  UNIQUE KEY uniq_lesson_prompt (lesson_id, prompt(191)),
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  lesson_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NULL,
  answer_key VARCHAR(80) NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  response_ms INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mastery_scores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  skill_key VARCHAR(100) NOT NULL,
  score DECIMAL(5,2) NOT NULL DEFAULT 0,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_student_skill (student_id, skill_key),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS community_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  province VARCHAR(120) NULL,
  image_path VARCHAR(500) NULL,
  moderation_status ENUM('draft','ai_flagged','pending_review','approved','rejected') NOT NULL DEFAULT 'draft',
  moderation_reason VARCHAR(500) NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO legal_sources (id, title, url, reference_code, effective_from, verified_at, status)
VALUES (1, 'Luật Trật tự, an toàn giao thông đường bộ', 'https://vanban.chinhphu.vn/?docid=211194&pageid=27160', '36/2024/QH15', '2025-01-01', NOW(), 'verified');

INSERT IGNORE INTO topics (slug, name, icon, description, status) VALUES
('qua-duong', 'Qua đường an toàn', '🚸', 'Dừng lại, quan sát và chọn nơi qua đường an toàn.', 'published'),
('tin-hieu', 'Đèn và biển báo', '🚦', 'Nhận biết tín hiệu, biển cấm và biển chỉ dẫn.', 'published'),
('mu-bao-hiem', 'Mũ bảo hiểm', '⛑️', 'Đội mũ đúng cách mỗi lần ngồi xe máy hoặc xe đạp điện.', 'published'),
('tinh-huong', 'Tình huống khẩn cấp', '🚑', 'Biết nhường đường và giữ bình tĩnh khi gặp xe ưu tiên.', 'published');

INSERT IGNORE INTO lessons (topic_id, title, slug, age_group, summary, difficulty, status, source_id)
SELECT id, 'Ba bước qua đường', 'ba-buoc-qua-duong-6-8', '6-8', 'Dừng lại – quan sát – lắng nghe trước khi bước qua đường.', 1, 'published', 1 FROM topics WHERE slug = 'qua-duong';
INSERT IGNORE INTO lessons (topic_id, title, slug, age_group, summary, difficulty, status, source_id)
SELECT id, 'Chọn quyết định an toàn ở ngã tư', 'quyet-dinh-nga-tu-9-11', '9-11', 'Đọc tín hiệu, nhìn phương tiện và giải thích vì sao nên đi hoặc dừng.', 2, 'published', 1 FROM topics WHERE slug = 'tin-hieu';

INSERT IGNORE INTO questions (lesson_id, prompt, options_json, answer_key, explanation, skill_key, xp_reward)
SELECT id, 'Khi muốn qua đường, bước đầu tiên con cần làm gì?', '[{"key":"run","label":"Chạy thật nhanh qua đường"},{"key":"stop","label":"Dừng lại ở nơi an toàn"},{"key":"phone","label":"Nhìn vào điện thoại"}]', 'stop', 'Đúng rồi. Con hãy dừng lại ở vỉa hè hoặc trước vạch qua đường, sau đó mới quan sát hai bên.', 'pedestrian', 10 FROM lessons WHERE slug = 'ba-buoc-qua-duong-6-8';
INSERT IGNORE INTO questions (lesson_id, prompt, options_json, answer_key, explanation, skill_key, xp_reward)
SELECT id, 'Đèn vàng đang bật và con vẫn đứng trước vạch. Lựa chọn an toàn là gì?', '[{"key":"go","label":"Tăng tốc để đi qua"},{"key":"stop","label":"Chuẩn bị dừng và chờ tín hiệu phù hợp"},{"key":"close","label":"Nhắm mắt đi theo bạn"}]', 'stop', 'Đèn vàng báo hiệu chuẩn bị dừng. Con không nên cố tăng tốc để đi qua.', 'signals', 15 FROM lessons WHERE slug = 'quyet-dinh-nga-tu-9-11';

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
  title       VARCHAR(255) NOT NULL DEFAULT 'Cuộc trò chuyện mới',
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
-- Khám phá Buôn Ma Thuột — địa điểm, review, sở thích cá nhân hoá
-- và lịch trình AI (xem thêm sql/migrate-kham-pha.sql)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS places (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(80)  NOT NULL UNIQUE,
  name        VARCHAR(150) NOT NULL,
  type        ENUM('bao-tang','cong-vien','vui-choi','thien-nhien') NOT NULL,
  story       TEXT         NOT NULL,
  open_hours  VARCHAR(100) NOT NULL DEFAULT 'Cả ngày',
  ticket      VARCHAR(100) NOT NULL DEFAULT 'Miễn phí',
  distance_km DECIMAL(4,1) NOT NULL DEFAULT 0,
  age_note    VARCHAR(100) NOT NULL DEFAULT 'Mọi lứa tuổi',
  safety_note TEXT         NOT NULL,
  art_code    VARCHAR(30)  NOT NULL DEFAULT 'cong-vien',
  map_x       TINYINT UNSIGNED NOT NULL DEFAULT 50,
  map_y       TINYINT UNSIGNED NOT NULL DEFAULT 50,
  status      ENUM('published','hidden') NOT NULL DEFAULT 'published',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS place_reviews (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  place_id      INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  stars         TINYINT UNSIGNED NOT NULL,
  content       TEXT NOT NULL,
  photos        TEXT NULL,
  status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reject_reason VARCHAR(255) NULL,
  reviewed_by   INT UNSIGNED NULL,
  reviewed_at   DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_preferences (
  user_id         INT UNSIGNED PRIMARY KEY,
  grade_band      ENUM('tieu-hoc','thcs') NOT NULL DEFAULT 'tieu-hoc',
  fav_topics      VARCHAR(255) NOT NULL DEFAULT '',
  fav_place_types VARCHAR(255) NOT NULL DEFAULT '',
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_itineraries (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  params     TEXT NOT NULL,
  plan       TEXT NOT NULL,
  engine     VARCHAR(10) NOT NULL DEFAULT 'offline',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
