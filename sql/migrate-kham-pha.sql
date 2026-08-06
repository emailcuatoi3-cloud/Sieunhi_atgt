USE duanmau_atgt;

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
