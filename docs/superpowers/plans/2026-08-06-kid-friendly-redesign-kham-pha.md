# Kid-Friendly Redesign + Module Khám Phá — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Biến Siêu Nhí ATGT thành web bắt mắt cho học sinh tiểu học/THCS: design system "hoạt hình rực rỡ", chatbot AI bố cục mới có hình minh hoạ + cá nhân hoá, module Khám phá địa điểm Buôn Ma Thuột với AI lịch trình và review cộng đồng có kiểm duyệt.

**Architecture:** Giữ nguyên PHP thuần + MySQL (`duanmau_atgt`) + session auth + `ai-engine.php` (Gemini/offline). Logic mới đặt trong `lib/` (hàm thuần, test được không cần DB khi có thể). Trang mới là file PHP phẳng ở gốc theo convention hiện tại. Không framework, không build step.

**Tech Stack:** PHP ≥7.4 (XAMPP), MySQL, vanilla JS, CSS custom properties, SVG inline, Gemini API (tuỳ chọn, luôn có offline fallback).

**Spec:** `docs/superpowers/specs/2026-08-06-kid-friendly-redesign-kham-pha-design.md`

## Global Constraints

- PHP CLI và MySQL client của XAMPP: `/Applications/XAMPP/xamppfiles/bin/php`, `/Applications/XAMPP/xamppfiles/bin/mysql -u root duanmau_atgt` (không mật khẩu). Đặt alias trong shell khi làm việc: `PHP=/Applications/XAMPP/xamppfiles/bin/php; MYSQL="/Applications/XAMPP/xamppfiles/bin/mysql -u root duanmau_atgt"`.
- Không thêm Composer/npm dependency. Test là script PHP thuần trong `tests/`, chạy `$PHP tests/<file>.php`, exit code ≠ 0 khi fail.
- Mọi endpoint đổi trạng thái: `requireCsrf()` (nhận `X-CSRF-Token` header hoặc `csrf_token` POST field — đã có sẵn trong `auth.php`).
- Escape output bằng helper `e()` có sẵn trong `auth.php`.
- Palette trẻ em (dùng đúng các giá trị này): nền kem `#FFF8EC`, kem đậm `#FFF1D6`, thẻ `#FFFFFF`, chữ nâu `#4B3325`, chữ nhạt `#8A6F5C`, vàng nắng `#FFB703`, xanh da trời `#219EBC`, xanh đậm `#126782`, đỏ tươi `#E63946`, xanh lá `#2A9D34`, hồng má `#FF7B9C`. Ngữ nghĩa: xanh lá = đúng/an toàn, đỏ = sai/nguy hiểm, vàng = chú ý.
- Animation chỉ dùng `transform`/`opacity`; giữ block `prefers-reduced-motion` có sẵn trong `style.css`.
- Copy tiếng Việt, giọng thân thiện: tiểu học xưng "mình"–"con", THCS xưng "mình"–"bạn".
- Commit message theo conventional commits, KHÔNG kèm attribution footer.
- Sau mỗi task chạy `$PHP -l` cho mọi file PHP đã sửa (lint bắt buộc vì không có CI).
- Mã chủ đề ATGT (topic code) dùng thống nhất toàn dự án: `den-tin-hieu`, `bien-bao`, `mu-bao-hiem`, `qua-duong`, `xe-dap`, `ngoi-xe`, `uu-tien`. Mã loại địa điểm: `bao-tang`, `cong-vien`, `vui-choi`, `thien-nhien`.

---

### Task 1: Test harness + migration 4 bảng mới

**Files:**
- Create: `tests/bootstrap.php`
- Create: `sql/migrate-kham-pha.sql`
- Test: `tests/test-migration.php`

**Interfaces:**
- Produces: `check(bool $cond, string $msg): void`, `done(): void` (mọi test file sau dùng); 4 bảng `places`, `place_reviews`, `user_preferences`, `ai_itineraries` với đúng cột như SQL dưới.

- [ ] **Step 1: Viết test harness**

`tests/bootstrap.php`:

```php
<?php
declare(strict_types=1);
error_reporting(E_ALL);
$GLOBALS['__t'] = ['pass' => 0, 'fail' => 0];

function check(bool $cond, string $msg): void {
    if ($cond) { $GLOBALS['__t']['pass']++; echo "  ok  $msg\n"; }
    else       { $GLOBALS['__t']['fail']++; echo "  FAIL $msg\n"; }
}

function done(): void {
    $t = $GLOBALS['__t'];
    echo "== {$t['pass']} pass, {$t['fail']} fail ==\n";
    exit($t['fail'] > 0 ? 1 : 0);
}
```

- [ ] **Step 2: Viết failing test cho migration**

`tests/test-migration.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../db_utils.php';

$db = new DB_UTILS();
$tables = ['places', 'place_reviews', 'user_preferences', 'ai_itineraries'];
foreach ($tables as $t) {
    $n = (int)$db->getValue(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?", [$t]);
    check($n === 1, "bảng $t tồn tại");
}
$cols = $db->getAll(
    "SELECT column_name FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'places'");
$names = array_column($cols, 'column_name') ?: array_column($cols, 'COLUMN_NAME');
foreach (['slug','name','type','story','safety_note','distance_km','map_x','map_y','art_code'] as $c) {
    check(in_array($c, $names, true), "places có cột $c");
}
done();
```

- [ ] **Step 3: Chạy test, xác nhận FAIL** — `$PHP tests/test-migration.php` → expected: `FAIL bảng places tồn tại` (exit 1).

- [ ] **Step 4: Viết migration**

`sql/migrate-kham-pha.sql`:

```sql
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
```

- [ ] **Step 5: Chạy migration + test PASS** — `$MYSQL < sql/migrate-kham-pha.sql && $PHP tests/test-migration.php` → expected: tất cả `ok`, exit 0.

- [ ] **Step 6: Commit** — `git add tests/ sql/migrate-kham-pha.sql && git commit -m "feat: bảng places, place_reviews, user_preferences, ai_itineraries + test harness"`

---

### Task 2: Seed 15 địa điểm Buôn Ma Thuột

**Files:**
- Create: `sql/seed-places.sql`
- Test: `tests/test-seed-places.php`

**Interfaces:**
- Produces: ≥15 hàng `places` status `published`, đủ 4 type, mọi trường text không rỗng. Khoảng cách tính từ khu Ngã Sáu Buôn Ma Thuột (mốc theo spec).

- [ ] **Step 1: Viết failing test**

`tests/test-seed-places.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../db_utils.php';

$db = new DB_UTILS();
check((int)$db->getValue("SELECT COUNT(*) FROM places WHERE status='published'") >= 15, 'có >= 15 địa điểm published');
foreach (['bao-tang','cong-vien','vui-choi','thien-nhien'] as $t) {
    check((int)$db->getValue("SELECT COUNT(*) FROM places WHERE type=?", [$t]) >= 2, "có >= 2 địa điểm loại $t");
}
check((int)$db->getValue(
    "SELECT COUNT(*) FROM places WHERE story='' OR safety_note='' OR open_hours='' OR ticket=''") === 0,
    'không có trường mô tả rỗng');
check((int)$db->getValue("SELECT COUNT(*) FROM places WHERE distance_km <= 2.0") >= 5, '>= 5 điểm đi bộ được (<=2km)');
done();
```

- [ ] **Step 2: Chạy test FAIL** — `$PHP tests/test-seed-places.php` → expected FAIL (bảng rỗng).

- [ ] **Step 3: Viết seed** — `sql/seed-places.sql`. Dùng `INSERT IGNORE` (idempotent nhờ UNIQUE slug). Cột theo thứ tự: `(slug, name, type, story, open_hours, ticket, distance_km, age_note, safety_note, art_code, map_x, map_y)`:

```sql
USE duanmau_atgt;
INSERT IGNORE INTO places (slug, name, type, story, open_hours, ticket, distance_km, age_note, safety_note, art_code, map_x, map_y) VALUES
('tuong-dai-chien-thang','Tượng đài Chiến thắng Buôn Ma Thuột','cong-vien','Ngay giữa vòng xoay Ngã Sáu là chiếc xe tăng thật gắn trên tượng đài — biểu tượng của ngày giải phóng Buôn Ma Thuột 10/3/1975. Đứng ở đây, con đang đứng ngay "trái tim" của thành phố đó!','Cả ngày','Miễn phí',0.1,'Mọi lứa tuổi','Ngã Sáu là vòng xoay ĐÔNG XE NHẤT thành phố. Chỉ ngắm tượng đài từ vỉa hè, tuyệt đối không băng vào vòng xoay; qua đường đúng vạch kẻ và chờ đèn xanh cho người đi bộ.','cong-vien',50,48),
('bao-tang-dak-lak','Bảo tàng Đắk Lắk','bao-tang','Bảo tàng nằm trong toà nhà mô phỏng nhà dài của người Ê Đê, dài như "một tiếng chiêng ngân". Bên trong có cồng chiêng, thuyền độc mộc, voi Tây Nguyên và cả khu rừng thu nhỏ để con khám phá.','8:00–16:30 (nghỉ thứ Hai)','10.000đ, học sinh có thẻ miễn phí',0.9,'Mọi lứa tuổi','Đường Y Ngông có vỉa hè rộng, dễ đi bộ. Đoạn cổng bảo tàng nhiều ô tô ra vào — con đi sát bên trong vỉa hè và quan sát xe rẽ.','bao-tang',44,58),
('nha-day-buon-ma-thuot','Di tích Nhà đày Buôn Ma Thuột','bao-tang','Nhà đày là di tích lịch sử quốc gia đặc biệt, nơi kể câu chuyện về lòng dũng cảm của các chiến sĩ cách mạng. Các phòng trưng bày được phục dựng như thật, vừa học sử vừa như xem phim.','7:30–17:00','Miễn phí',1.2,'Phù hợp từ lớp 3','Đường Tán Thuật khá hẹp và có đoạn không vỉa hè — con đi hàng một, sát lề bên phải, người lớn đi phía ngoài.','bao-tang',56,62),
('dinh-lac-giao','Đình Lạc Giao','bao-tang','Đây là ngôi đình của những người Việt đầu tiên đến lập nghiệp ở Buôn Ma Thuột hơn 90 năm trước. Mái đình cong cong, cột gỗ to — một góc "làng quê Bắc Bộ" ngay giữa phố núi.','7:00–17:00','Miễn phí',0.5,'Mọi lứa tuổi','Đình nằm gần chợ trung tâm, xe máy lên xuống vỉa hè nhiều — con nắm tay người lớn, không chạy nhảy khu vực cổng chợ.','bao-tang',47,54),
('chua-khai-doan','Chùa Sắc Tứ Khải Đoan','bao-tang','Ngôi chùa gỗ lớn nhất Đắk Lắk, được xây từ năm 1951 với mái ngói cong tuyệt đẹp kiểu cung đình Huế. Sân chùa rộng và yên tĩnh, rất nhiều chim bồ câu.','6:00–18:00','Miễn phí',0.8,'Mọi lứa tuổi','Đường Phan Bội Châu một chiều, xe chạy nhanh — con qua đường tại ngã tư có đèn tín hiệu, không băng chéo giữa đường.','bao-tang',42,50),
('bao-tang-ca-phe','Bảo tàng Thế giới Cà phê','bao-tang','Toà nhà cong cong như những chiếc lá úp vào nhau — nơi duy nhất ở Việt Nam kể chuyện cà phê của cả thế giới. Có khu trải nghiệm rang xay và không gian ảnh cực đẹp.','8:00–17:00','30.000đ trẻ em',2.6,'Mọi lứa tuổi','Đường Nguyễn Đình Chiểu rộng, ô tô đông vào cuối tuần. Nếu đi xe đạp, con đi đúng làn sát phải và bật nhớ đội mũ bảo hiểm.','bao-tang',34,40),
('buon-ako-dhong','Buôn Ako Dhông','bao-tang','Buôn làng Ê Đê đẹp nhất thành phố với những ngôi nhà dài truyền thống nằm dưới hàng cây xanh mát. Con sẽ được thấy bến nước, nghe kể chuyện cồng chiêng và thử món cơm lam.','Cả ngày','Miễn phí',2.0,'Mọi lứa tuổi','Đường vào buôn dốc thoai thoải và quanh co — đi bộ theo nhóm sát lề, không thả dốc bằng xe đạp.','thien-nhien',38,32),
('quang-truong-10-3','Quảng trường 10/3','cong-vien','Quảng trường rộng mênh mông để thả diều, trượt patin và xem đài phun nước buổi tối. Cuối tuần có rất nhiều bạn nhỏ ra chơi.','Cả ngày','Miễn phí',0.4,'Mọi lứa tuổi','Bãi xe máy sát mép quảng trường — con chơi ở khu trung tâm, không chơi gần lối xe ra vào; buổi tối mặc đồ sáng màu.','cong-vien',53,44),
('cong-vien-nuoc','Công viên nước Đắk Lắk','vui-choi','Cầu trượt xoắn ốc, dòng sông lười và hồ tạo sóng — chỗ "giải nhiệt" vui nhất thành phố vào ngày nắng. Có khu hồ nông riêng cho các bé nhỏ.','8:00–17:00 (T7, CN đông nhất)','60.000đ trẻ em',3.2,'Đi cùng người lớn','Quãng đường xa, con nên để bố mẹ chở; nhớ đội mũ bảo hiểm cài quai đúng 3 bước và không đùa nghịch khi ngồi sau xe.','vui-choi',28,56),
('duong-sach-ca-phe','Đường sách Cà phê Buôn Ma Thuột','vui-choi','Con phố nhỏ xinh toàn sách, tranh và những quán nước dễ thương — chỗ lý tưởng để đọc truyện tranh ATGT và chụp ảnh cùng bạn bè.','7:00–21:00','Miễn phí',0.4,'Mọi lứa tuổi','Phố đi bộ nhưng hai đầu phố vẫn có xe chạy — con chú ý khi ra vào cổng phố, không mải đọc sách mà bước xuống lòng đường.','vui-choi',49,52),
('khu-vui-choi-thieu-nhi','Nhà Văn hoá Thanh Thiếu nhi Đắk Lắk','vui-choi','Sân chơi quen thuộc của học sinh thành phố: lớp năng khiếu, nhà banh, sân trượt patin và những buổi hội thi vui nhộn — trong đó có cả hội thi an toàn giao thông!','7:00–20:30','Miễn phí (trò chơi tính vé riêng)',1.5,'Mọi lứa tuổi','Giờ tan lớp năng khiếu cổng rất đông — con chờ bố mẹ ở SÂN TRONG, không đứng chờ dưới lòng đường trước cổng.','vui-choi',60,46),
('ho-ea-kao','Hồ Ea Kao','thien-nhien','Hồ nước rộng như biển giữa núi rừng, chiều xuống có đàn cò bay và hoàng hôn tím rực. Nơi cắm trại, dã ngoại cuối tuần được yêu thích nhất.','Cả ngày (đẹp nhất 15:00–18:00)','Miễn phí',12.0,'Đi cùng người lớn','Đường ra hồ xa và có xe tải chạy — bắt buộc bố mẹ chở; ở mép hồ không chơi sát mặt nước, luôn trong tầm mắt người lớn.','thien-nhien',70,70),
('ko-tam','Khu du lịch sinh thái Ko Tam','thien-nhien','Vườn hoa, hồ sen, nhà dài và những trò chơi dân gian Tây Nguyên trong một khu vườn khổng lồ. Mùa nào cũng có hoa để chụp ảnh.','7:00–18:00','20.000đ trẻ em',9.0,'Mọi lứa tuổi','Nằm trên quốc lộ 26 xe container nhiều — chỉ đi bằng ô tô/xe máy cùng người lớn, xuống xe phía trong cổng, không mở cửa xe phía lòng đường.','thien-nhien',78,58),
('thac-dray-nur','Thác Dray Nur','thien-nhien','Ngọn thác hùng vĩ nhất Đắk Lắk — bức tường nước cao 30 mét gầm vang giữa rừng. Truyền thuyết kể đây là nơi trú ngụ của một chàng hoàng tử hoá cá.','7:30–17:00','30.000đ trẻ em',25.0,'Phù hợp từ lớp 3, đi cùng người lớn','Chuyến đi xa 25km — ngồi ô tô nhớ thắt dây an toàn, không thò tay ra cửa sổ; ở thác đi đúng lối có lan can, đá rất trơn.','thien-nhien',85,80),
('buon-don','Khu du lịch Buôn Đôn','thien-nhien','Quê hương của nghề thuần dưỡng voi với cầu treo lắc lư qua sông Sêrêpốk và nhà sàn cổ hơn 130 năm. Một ngày ở Buôn Đôn như một chuyến thám hiểm thật sự!','7:30–17:00','40.000đ trẻ em',40.0,'Phù hợp từ lớp 3, đi cùng người lớn','Chuyến đi cả ngày — kiểm tra dây an toàn trước khi xe chạy; trên cầu treo đi chậm, bám tay vịn, mỗi lượt ít người theo hướng dẫn.','thien-nhien',15,20);
```

- [ ] **Step 4: Chạy seed + test PASS** — `$MYSQL < sql/seed-places.sql && $PHP tests/test-seed-places.php` → tất cả `ok`.

- [ ] **Step 5: Commit** — `git commit -am "feat: seed 15 địa điểm Buôn Ma Thuột với ghi chú ATGT"` (thêm file bằng `git add sql/seed-places.sql tests/test-seed-places.php` trước).

---

### Task 3: Design system "Hoạt hình rực rỡ" (token + sweep + kid-components)

**Files:**
- Modify: `assets/css/style.css` (block `:root` dòng 13-60; block theme-invert dòng 149-161)
- Modify: `assets/css/shared-pages.css` (sweep màu)
- Create: `assets/css/kid-components.css`
- Modify: `assets/js/main.js:9` (default theme)
- Modify: mọi `*.php` có inline snippet `localStorage.getItem("sieu-nhi-theme")||"dark"`

**Interfaces:**
- Produces: các class CSS mọi task sau dùng: `.kid-card`, `.kid-btn`, `.kid-btn--green`, `.kid-btn--red`, `.kid-chip`, `.kid-chip.active`, `.sticker-tilt`, `.kid-badge`, `.kid-input`. Biến mới: `--kid-yellow`, `--kid-sky`, `--kid-sky-deep`, `--kid-red`, `--kid-green`, `--kid-pink`, `--kid-cream`, `--kid-cream-2`, `--kid-ink`, `--kid-ink-soft`.

**Bối cảnh quan trọng cho người thực hiện:** site hiện là dark theme thật (chữ trắng trên `--bg-deep-*` navy); "light theme" hiện tại chỉ là hack `filter: invert(1) hue-rotate(180deg)`. Chiến lược: đổi GIÁ TRỊ token sang palette trẻ em (giữ TÊN biến để 5.600 dòng CSS cũ không phải sửa hàng loạt), xoá hack invert, rồi sweep các màu trắng hardcode.

- [ ] **Step 1: Thay block `:root` trong `style.css`** (thay toàn bộ dòng 13-60) bằng:

```css
:root {
  /* Palette trẻ em — giữ TÊN biến cũ, đổi giá trị (tương thích ngược) */
  --kid-cream: #FFF8EC;  --kid-cream-2: #FFF1D6;
  --kid-ink: #4B3325;    --kid-ink-soft: #8A6F5C;
  --kid-yellow: #FFB703; --kid-sky: #219EBC; --kid-sky-deep: #126782;
  --kid-red: #E63946;    --kid-green: #2A9D34; --kid-pink: #FF7B9C;

  --bg-deep-1: var(--kid-cream);
  --bg-deep-2: var(--kid-cream-2);
  --bg-deep-3: #FFE8BF;
  --blue: var(--kid-sky);
  --blue-light: #7BD3EA;
  --blue-strong: var(--kid-sky-deep);
  --navy: var(--kid-sky-deep);
  --purple: var(--kid-sky-deep);
  --cyan: var(--kid-sky);
  --green: var(--kid-green);
  --yellow: var(--kid-yellow);
  --pink: var(--kid-red);
  --white: var(--kid-ink);          /* chữ chính giờ là nâu đậm */
  --ink: var(--kid-ink);
  --mist: rgba(75, 51, 37, 0.78);
  --mist-dim: rgba(75, 51, 37, 0.55);

  --glass-fill: rgba(255, 255, 255, 0.72);
  --glass-fill-strong: rgba(255, 255, 255, 0.92);
  --glass-border: rgba(75, 51, 37, 0.14);

  --radius-lg: 24px; --radius-md: 16px; --radius-sm: 12px;

  --shadow-soft: 0 14px 34px -14px rgba(75, 51, 37, 0.28);
  --shadow-glow-blue: 0 10px 28px -10px rgba(33, 158, 188, 0.45);
  --shadow-glow-purple: 0 10px 28px -10px rgba(255, 183, 3, 0.45);

  --grad-primary: linear-gradient(135deg, var(--kid-sky-deep) 0%, var(--kid-sky) 55%, #7BD3EA 100%);
  --grad-text: linear-gradient(100deg, var(--kid-sky-deep) 18%, var(--kid-sky) 58%, var(--kid-yellow) 82%);
}
```

- [ ] **Step 2: Xoá hack invert** — trong `style.css` xoá toàn bộ block `html { transition: filter... }` + `html[data-theme="light"] {...}` + `html[data-theme="light"] .hero-city,... {...}` (dòng 149-161 bản gốc).

- [ ] **Step 3: Mặc định theme light** — `assets/js/main.js:9` đổi `|| "dark"` → `|| "light"`. Sweep inline snippet trong PHP:

```bash
grep -rl 'sieu-nhi-theme.*"dark"' --include='*.php' . | xargs sed -i '' 's/localStorage.getItem("sieu-nhi-theme")||"dark"/localStorage.getItem("sieu-nhi-theme")||"light"/g; s/localStorage.getItem("sieu-nhi-theme") || "dark"/localStorage.getItem("sieu-nhi-theme") || "light"/g'
```

- [ ] **Step 4: Sweep màu trắng hardcode** trong 2 file CSS (chữ/viền trắng → nâu; giữ nguyên các chỗ chữ trắng TRÊN nút màu — vì vậy sweep bán tự động, duyệt bằng mắt sau):

```bash
sed -i '' 's/rgba(255, 255, 255,/rgba(75, 51, 37,/g; s/rgba(255,255,255,/rgba(75,51,37,/g' assets/css/style.css assets/css/shared-pages.css
```

Sau đó mở từng trang chính (index, ai-gia-su, dashboard-hoc-sinh, game-mini, bang-xep-hang) trong browser; chỗ nào chữ trên NỀN MÀU ĐẬM (nút primary, badge) bị tối thì sửa lại thành `#fff` trực tiếp tại rule đó. Ghi lại số rule đã sửa tay trong commit message.

- [ ] **Step 5: Viết `assets/css/kid-components.css`**:

```css
/* Kid components — dùng chung mọi trang mới */
.kid-card { background:#fff; border:3px solid var(--glass-border); border-radius:var(--radius-lg);
  box-shadow:var(--shadow-soft); padding:20px; transition:transform .2s ease, box-shadow .2s ease; }
.kid-card:hover { transform:translateY(-4px) scale(1.01); box-shadow:0 18px 40px -14px rgba(75,51,37,.35); }
.sticker-tilt:nth-child(odd) { transform:rotate(-1.2deg); }
.sticker-tilt:nth-child(even) { transform:rotate(1.2deg); }
.sticker-tilt:hover { transform:rotate(0) translateY(-4px); }

.kid-btn { display:inline-flex; align-items:center; gap:8px; min-height:48px; padding:12px 26px;
  border-radius:999px; font-family:"Baloo 2",sans-serif; font-weight:800; font-size:17px;
  background:var(--kid-yellow); color:var(--kid-ink); border:3px solid rgba(75,51,37,.18);
  box-shadow:0 5px 0 rgba(75,51,37,.22); transition:transform .12s ease, box-shadow .12s ease; }
.kid-btn:hover { transform:translateY(-2px); }
.kid-btn:active { transform:translateY(3px); box-shadow:0 1px 0 rgba(75,51,37,.22); }
.kid-btn--sky   { background:var(--kid-sky); color:#fff; }
.kid-btn--green { background:var(--kid-green); color:#fff; }
.kid-btn--red   { background:var(--kid-red); color:#fff; }

.kid-chip { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; border-radius:999px;
  background:#fff; border:2.5px solid var(--kid-sky); color:var(--kid-sky-deep);
  font-weight:700; font-size:14px; cursor:pointer; white-space:nowrap;
  transition:transform .12s ease, background .12s ease; }
.kid-chip:hover { transform:translateY(-2px); }
.kid-chip.active, .kid-chip[aria-pressed="true"] { background:var(--kid-sky); color:#fff; }

.kid-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:999px;
  font-size:12.5px; font-weight:800; background:var(--kid-cream-2); color:var(--kid-ink-soft); }
.kid-badge--green { background:#E3F6E4; color:var(--kid-green); }
.kid-badge--red   { background:#FDE8EA; color:var(--kid-red); }
.kid-badge--yellow{ background:#FFF3D6; color:#9A6B00; }

.kid-input { width:100%; min-height:48px; padding:12px 18px; border-radius:16px;
  border:3px solid var(--glass-border); background:#fff; color:var(--kid-ink);
  font-family:inherit; font-size:16px; }
.kid-input:focus { border-color:var(--kid-sky); outline:none; }

@media (prefers-reduced-motion: reduce) {
  .kid-card, .kid-btn, .kid-chip, .sticker-tilt { transition:none; transform:none; }
}
```

- [ ] **Step 5b: Self-host font (demo offline không cần mạng)** — tải Baloo 2 + Quicksand (thay Be Vietnam Pro) bản woff2 subset vietnamese+latin từ dịch vụ google-webfonts-helper:

```bash
mkdir -p assets/fonts && cd assets/fonts
curl -sLo baloo2.zip "https://gwfh.mranftl.com/api/fonts/baloo-2?download=zip&subsets=latin,vietnamese&variants=600,800&formats=woff2"
curl -sLo quicksand.zip "https://gwfh.mranftl.com/api/fonts/quicksand?download=zip&subsets=latin,vietnamese&variants=500,700&formats=woff2"
unzip -o baloo2.zip && unzip -o quicksand.zip && rm baloo2.zip quicksand.zip && cd ../..
```

Tạo `assets/css/fonts.css` với 4 khối `@font-face` (family "Baloo 2" weight 600/800, "Quicksand" weight 500/700, `src: url('../fonts/<tên file woff2 vừa tải>') format('woff2')`, `font-display: swap`). Trong `style.css` đổi mọi `"Be Vietnam Pro"` → `"Quicksand"`. Sweep bỏ Google Fonts CDN + nạp fonts.css:

```bash
grep -rl 'fonts.googleapis.com' --include='*.php' . | xargs sed -i '' 's#<link[^>]*fonts.googleapis.com[^>]*>##g; s#<link[^>]*fonts.gstatic.com[^>]*>##g'
grep -rl 'assets/css/style.css' --include='*.php' . | xargs sed -i '' 's#\(<link rel="stylesheet" href="assets/css/style.css[^>]*>\)#<link rel="stylesheet" href="assets/css/fonts.css?v=1">\1#'
```

Xác nhận: tắt Wi-Fi, reload trang — chữ vẫn đúng font tròn trịa.

- [ ] **Step 6: Nạp kid-components.css vào mọi trang** — sweep: sau mỗi dòng link `shared-pages.css` thêm link mới:

```bash
grep -rl 'shared-pages.css' --include='*.php' . | xargs sed -i '' 's#\(<link rel="stylesheet" href="assets/css/shared-pages.css[^>]*>\)#\1\n<link rel="stylesheet" href="assets/css/kid-components.css?v=1">#'
```

`index.php` không load shared-pages — thêm tay sau link `style.css`.

- [ ] **Step 7: Kiểm tra bằng mắt** — dùng skill `run` mở `http://localhost/Sieunhi_atgt/` + 4 trang chính, screenshot ở 375px và 1440px. Tiêu chí: không còn mảng navy đậm, chữ đọc rõ trên nền kem (contrast nâu `#4B3325` / kem `#FFF8EC` ≈ 9:1), nút vàng/xanh nổi bật.

- [ ] **Step 8: Commit** — `git add -A && git commit -m "feat: design system hoạt hình rực rỡ — palette kem/vàng/xanh, kid-components, bỏ hack invert"`

---

### Task 4: Mascot có trạng thái cảm xúc

**Files:**
- Modify: `assets/js/mascot.js` (thêm hàm mới vào cuối IIFE, export thêm)
- Test: kiểm tra thủ công qua trang `index.php`

**Interfaces:**
- Consumes: `MascotSVG.character({helmet, badge})` có sẵn (giữ nguyên, không sửa).
- Produces: `MascotSVG.pose(state)` với `state ∈ {'wave','cheer','worry','point'}` → trả chuỗi SVG hoàn chỉnh (`<svg viewBox="0 0 140 160">...`) tái dùng `head()`, `hairFull()` nội bộ; khác nhau ở tay + miệng + phụ kiện: `wave` tay phải giơ vẫy (rotate -30° quanh vai), `cheer` hai tay giơ cao + confetti chấm tròn 6 màu quanh đầu, `worry` hai tay ôm má + lông mày xéo, `point` tay phải chỉ sang phải (dùng làm callout hướng dẫn).

- [ ] **Step 1:** Thêm vào `mascot.js` (trước `return`): hàm `arms(state)` trả các `<path>` tay theo 4 state trên (vẽ theo cùng phong cách stroke `#F2B87E` fill có sẵn của file), hàm `extras(state)` (confetti cho cheer: 6 `<circle r="4">` toạ độ quanh (70,20), màu `#FFB703 #219EBC #E63946 #2A9D34 #FF7B9C #7BD3EA`; giọt mồ hôi cho worry: 1 `<ellipse>` xanh nhạt cạnh thái dương), và:

```js
function pose(state = 'wave') {
  return `<svg viewBox="0 0 140 160" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Siêu Nhí ${state}">
    <defs><radialGradient id="skinGrad" cx="50%" cy="40%"><stop offset="0%" stop-color="#FFD9A8"/><stop offset="100%" stop-color="#F2B87E"/></radialGradient></defs>
    ${extras(state)}${hairFull()}${head()}${arms(state)}
  </svg>`;
}
```

và thêm `pose` vào object return của IIFE.

- [ ] **Step 2: Smoke-test trong console** — mở index.php, chạy `document.body.insertAdjacentHTML('beforeend', '<div style="width:120px">'+MascotSVG.pose('cheer')+'</div>')` cho cả 4 state → mascot hiện đúng, không lỗi console.

- [ ] **Step 3: Gắn mascot chào lên landing** — trong `index.php`, tại hero section thêm `<div id="hero-mascot" style="width:150px"></div>` và script `document.getElementById('hero-mascot').innerHTML = MascotSVG.pose('wave')` (đảm bảo `mascot.js` được load trên index — nếu chưa, thêm `<script src="assets/js/mascot.js"></script>`).

- [ ] **Step 4: Commit** — `git commit -am "feat: mascot 4 trạng thái cảm xúc (wave/cheer/worry/point) + chào trên landing"`

---

### Task 5: Thư viện minh hoạ SVG + endpoint art.php

**Files:**
- Create: `lib/svg-lib.php`
- Create: `art.php`
- Test: `tests/test-svg-lib.php`

**Interfaces:**
- Produces: `svg_art(string $code): ?string` — trả SVG hoàn chỉnh cho các mã: 7 topic (`den-tin-hieu`,`bien-bao`,`mu-bao-hiem`,`qua-duong`,`xe-dap`,`ngoi-xe`,`uu-tien`) + 4 loại địa điểm (`bao-tang`,`cong-vien`,`vui-choi`,`thien-nhien`) + `map-bmt`; mã lạ → `null`. `svg_art_codes(): array` trả danh sách mã. Endpoint `art.php?code=<code>` → `image/svg+xml` (404 nếu mã lạ) — client dùng `<img src="art.php?code=mu-bao-hiem">`.

- [ ] **Step 1: Viết failing test**

`tests/test-svg-lib.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/svg-lib.php';

$codes = ['den-tin-hieu','bien-bao','mu-bao-hiem','qua-duong','xe-dap','ngoi-xe','uu-tien',
          'bao-tang','cong-vien','vui-choi','thien-nhien','map-bmt'];
foreach ($codes as $c) {
    $svg = svg_art($c);
    check(is_string($svg) && str_contains($svg, '<svg'), "svg_art($c) trả SVG");
    check(is_string($svg) && str_contains($svg, 'viewBox'), "svg_art($c) có viewBox");
}
check(svg_art('khong-ton-tai') === null, 'mã lạ trả null');
check(count(svg_art_codes()) === 12, 'svg_art_codes đủ 12 mã');
done();
```

- [ ] **Step 2: Chạy FAIL** — `$PHP tests/test-svg-lib.php` → fatal (file chưa tồn tại) = FAIL.

- [ ] **Step 3: Viết `lib/svg-lib.php`.** Quy cách chung mọi hình: `viewBox="0 0 200 140"`, nền `rx=18` màu `#FFF1D6`, nét dày 4-5px bo tròn, palette Global Constraints, KHÔNG chữ trong SVG. Khung file + 2 hình mẫu đầy đủ:

```php
<?php
declare(strict_types=1);

function svg_art_codes(): array {
    return ['den-tin-hieu','bien-bao','mu-bao-hiem','qua-duong','xe-dap','ngoi-xe','uu-tien',
            'bao-tang','cong-vien','vui-choi','thien-nhien','map-bmt'];
}

function svg_art(string $code): ?string {
    $arts = [
        'den-tin-hieu' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<rect x="82" y="14" width="36" height="92" rx="12" fill="#4B3325"/>'
            . '<circle cx="100" cy="34" r="11" fill="#E63946"/>'
            . '<circle cx="100" cy="60" r="11" fill="#FFB703"/>'
            . '<circle cx="100" cy="86" r="11" fill="#2A9D34"/>'
            . '<rect x="95" y="106" width="10" height="22" fill="#4B3325"/>'
            . '<ellipse cx="100" cy="130" rx="34" ry="6" fill="#E9D9B8"/></svg>',
        'mu-bao-hiem' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<path d="M52 82 Q52 34 100 34 Q148 34 148 82 Z" fill="#219EBC"/>'
            . '<path d="M52 82 Q52 34 100 34 Q100 34 100 82 Z" fill="#7BD3EA" opacity=".55"/>'
            . '<rect x="44" y="80" width="112" height="14" rx="7" fill="#126782"/>'
            . '<path d="M74 94 Q78 116 96 118" stroke="#4B3325" stroke-width="5" fill="none" stroke-linecap="round"/>'
            . '<path d="M126 94 Q122 116 104 118" stroke="#4B3325" stroke-width="5" fill="none" stroke-linecap="round"/>'
            . '<circle cx="100" cy="119" r="6" fill="#FFB703"/></svg>',
        // ... 10 mã còn lại theo Step 4
    ];
    return $arts[$code] ?? null;
}
```

- [ ] **Step 4: Vẽ 10 hình còn lại** cùng quy cách, nội dung bắt buộc từng mã:
  - `bien-bao`: 3 biển trên cột — tròn viền đỏ (cấm), tam giác viền đỏ nền vàng (nguy hiểm), vuông xanh da trời (chỉ dẫn).
  - `qua-duong`: vạch ngựa vằn trắng trên nền đường xám nâu, đèn cho người đi bộ hình người xanh lá, mũi tên qua đường.
  - `xe-dap`: xe đạp thân xanh da trời bánh nét nâu, mũ bảo hiểm vàng treo ghi-đông.
  - `ngoi-xe`: ghế ô tô + dây an toàn chéo đỏ nổi bật, khoá dây vàng.
  - `uu-tien`: xe cứu thương trắng chữ thập đỏ, đèn còi xanh da trời trên nóc, 3 vạch chuyển động phía sau.
  - `bao-tang`: nhà dài Ê Đê — mái dốc nâu, sàn cột, thang gỗ, chiêng tròn vàng cạnh cửa.
  - `cong-vien`: 2 tán cây tròn xanh lá, ghế đá, mặt trời vàng góc phải.
  - `vui-choi`: đu quay — trục đứng + 3 ghế treo 3 màu (vàng/đỏ/xanh da trời).
  - `thien-nhien`: núi xanh 2 lớp, hồ nước xanh da trời, mây trắng, chim chữ V.
  - `map-bmt`: `viewBox="0 0 600 420"` — nền kem, các mảng phố `#FFE8BF` bo tròn, 2 trục đường chính màu trắng viền nâu giao nhau tại vòng xoay tròn ở (300,210) (Ngã Sáu), mảng xanh lá góc dưới-phải (hướng hồ Ea Kao), sông uốn góc trên-trái màu `#7BD3EA` (hướng Buôn Đôn). Không chữ — tên địa điểm là nút HTML đè lên (Task 9).

- [ ] **Step 5: Viết `art.php`**:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/svg-lib.php';
$code = preg_replace('/[^a-z0-9\-]/', '', (string)($_GET['code'] ?? ''));
$svg = svg_art($code);
if ($svg === null) { http_response_code(404); exit('not found'); }
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');
echo $svg;
```

- [ ] **Step 6: Test PASS + smoke HTTP** — `$PHP tests/test-svg-lib.php` → ok hết; mở `http://localhost/Sieunhi_atgt/art.php?code=den-tin-hieu` thấy hình, `?code=xxx` → 404.

- [ ] **Step 7: Commit** — `git add lib/ art.php tests/test-svg-lib.php && git commit -m "feat: thư viện 12 minh hoạ SVG + endpoint art.php"`

---

### Task 6: Nhận diện chủ đề trong ai-engine + API chat trả hình

**Files:**
- Modify: `ai-engine.php` (thêm hàm cuối file)
- Modify: `ai-chat.php` (case `send`: thêm `topic`, `art_url` vào cả 2 nhánh json_out; sửa `suggested_actions` placeholder cũ giữ nguyên)
- Test: `tests/test-topic.php`

**Interfaces:**
- Consumes: `ai_khong_dau()` có sẵn trong `ai-engine.php`.
- Produces: `ai_detect_topic(string $msg): ?string` trả topic code (7 mã Global Constraints) hoặc `null`. Response JSON của `action=send` có thêm `topic: ?string`, `art_url: ?string`.

- [ ] **Step 1: Viết failing test**

`tests/test-topic.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../ai-engine.php';

check(ai_detect_topic('Đèn đỏ thì phải làm gì?') === 'den-tin-hieu', 'đèn đỏ → den-tin-hieu');
check(ai_detect_topic('đội mũ bảo hiểm thế nào') === 'mu-bao-hiem', 'mũ bảo hiểm');
check(ai_detect_topic('Con muốn qua đường') === 'qua-duong', 'qua đường');
check(ai_detect_topic('biển cấm là gì') === 'bien-bao', 'biển báo');
check(ai_detect_topic('đi xe đạp an toàn') === 'xe-dap', 'xe đạp');
check(ai_detect_topic('ngồi sau xe máy') === 'ngoi-xe', 'ngồi xe');
check(ai_detect_topic('gặp xe cứu thương') === 'uu-tien', 'ưu tiên');
check(ai_detect_topic('hôm nay trời đẹp') === null, 'lạc đề → null');
check(ai_detect_topic('MŨ BẢO HIỂM') === 'mu-bao-hiem', 'không phân biệt hoa thường/dấu');
done();
```

- [ ] **Step 2: FAIL** — `$PHP tests/test-topic.php` → undefined function.

- [ ] **Step 3: Thêm vào cuối `ai-engine.php`**:

```php
/* Nhận diện chủ đề ATGT của câu hỏi/câu trả lời — dùng cho minh hoạ + gợi ý */
function ai_detect_topic(string $msg): ?string
{
    $t = ai_khong_dau($msg);
    $map = [
        'mu-bao-hiem'  => ['mu bao hiem', 'doi mu', 'non bao hiem'],
        'den-tin-hieu' => ['den do', 'den vang', 'den xanh', 'den giao thong', 'den tin hieu', 'tin hieu den'],
        'qua-duong'    => ['sang duong', 'qua duong', 'bang qua duong', 'vach ke', 'loi di bo', 'nguoi di bo', 'di bo'],
        'bien-bao'     => ['bien bao', 'bien cam', 'bien nguy hiem', 'bien chi dan', 'bien hieu lenh', 'stop'],
        'xe-dap'       => ['xe dap'],
        'ngoi-xe'      => ['xe may', 'ngoi sau', 'o to', 'oto', 'xe hoi', 'day an toan'],
        'uu-tien'      => ['cuu thuong', 'cuu hoa', 'xe uu tien', 'canh sat', 'cuu ho', 'uu tien'],
    ];
    foreach ($map as $code => $keywords) {
        foreach ($keywords as $k) {
            if (str_contains($t, $k)) return $code;
        }
    }
    return null;
}
```

- [ ] **Step 4: PASS** — `$PHP tests/test-topic.php`.

- [ ] **Step 5: Nối vào `ai-chat.php`** — trong case `send`, sau khi có `$reply` (cả nhánh guest lẫn nhánh đăng nhập), thêm:

```php
$topic = ai_detect_topic($message) ?? ai_detect_topic($reply);
```

và bổ sung vào mảng `json_out` của cả 2 nhánh: `'topic' => $topic, 'art_url' => $topic !== null ? 'art.php?code=' . $topic : null,`.

- [ ] **Step 6: Smoke test endpoint** — đăng nhập demo `hocsinh@demo.com` trên browser, mở DevTools, gửi "đèn đỏ là gì" ở trang AI Gia sư → response JSON có `topic: "den-tin-hieu"`, `art_url: "art.php?code=den-tin-hieu"`.

- [ ] **Step 7: Commit** — `git commit -am "feat: ai_detect_topic + API chat trả topic/art_url cho minh hoạ"`

---

### Task 7: Cá nhân hoá — user_preferences + máy sinh chip gợi ý

**Files:**
- Create: `lib/personalize.php`
- Create: `preferences.php`
- Modify: `ai-chat.php` (thêm `case 'chips':`)
- Test: `tests/test-personalize.php`

**Interfaces:**
- Consumes: bảng `user_preferences` (Task 1), `ai_detect_topic` (Task 6), `requireCsrf/currentUser/isLoggedIn` (auth.php).
- Produces:
  - `pref_topic_codes(): array` — 7 topic code; `pref_place_types(): array` — 4 mã loại.
  - `build_suggested_chips(array $favTopics, array $weakTopics, array $recentTopics, string $gradeBand, int $limit = 6): array` — trả mảng `[{topic, text}]`, ưu tiên: weak → fav → recent → mặc định; mỗi topic tối đa 1 chip; câu chữ theo `gradeBand`.
  - `GET preferences.php` → `{status, prefs: {grade_band, fav_topics: [], fav_place_types: []}, has_prefs: bool}` (guest → `{status:'success', guest:true}`).
  - `POST preferences.php` (CSRF) body `grade_band, fav_topics[], fav_place_types[]` → upsert.
  - `GET ai-chat.php?action=chips` → `{status, chips: [{topic, text}]}` (hoạt động cả guest — chip mặc định).

- [ ] **Step 1: Viết failing test**

`tests/test-personalize.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/personalize.php';

$chips = build_suggested_chips([], [], [], 'tieu-hoc');
check(count($chips) === 6, 'mặc định trả 6 chip');
check(isset($chips[0]['topic'], $chips[0]['text']), 'chip có topic + text');

$chips = build_suggested_chips(['xe-dap'], ['bien-bao'], ['mu-bao-hiem'], 'tieu-hoc');
check($chips[0]['topic'] === 'bien-bao', 'chủ đề yếu (weak) đứng đầu');
check($chips[1]['topic'] === 'xe-dap', 'sở thích (fav) đứng nhì');
check($chips[2]['topic'] === 'mu-bao-hiem', 'chủ đề gần đây đứng ba');

$topics = array_column(build_suggested_chips(['xe-dap'], ['xe-dap'], ['xe-dap'], 'thcs'), 'topic');
check(count($topics) === count(array_unique($topics)), 'không trùng topic');

$th = build_suggested_chips([], ['mu-bao-hiem'], [], 'tieu-hoc')[0]['text'];
$cs = build_suggested_chips([], ['mu-bao-hiem'], [], 'thcs')[0]['text'];
check($th !== $cs, 'câu chữ khác nhau theo khối lớp');

check(build_suggested_chips([], [], [], 'tieu-hoc', 3) !== null && count(build_suggested_chips([], [], [], 'tieu-hoc', 3)) === 3, 'limit hoạt động');
done();
```

- [ ] **Step 2: FAIL** rồi **Step 3: Viết `lib/personalize.php`**:

```php
<?php
declare(strict_types=1);

function pref_topic_codes(): array {
    return ['den-tin-hieu','bien-bao','mu-bao-hiem','qua-duong','xe-dap','ngoi-xe','uu-tien'];
}
function pref_place_types(): array {
    return ['bao-tang','cong-vien','vui-choi','thien-nhien'];
}

/* Ngân hàng câu gợi ý: mỗi topic 2 phiên bản theo khối lớp */
function chip_bank(): array {
    return [
        'den-tin-hieu' => ['tieu-hoc' => 'Đèn vàng thì con phải làm gì? 🚦', 'thcs' => 'Vượt đèn vàng có bị phạt không? 🚦'],
        'bien-bao'     => ['tieu-hoc' => 'Biển tròn viền đỏ nghĩa là gì? 🚫', 'thcs' => 'Phân biệt biển cấm và biển hiệu lệnh? 🚫'],
        'mu-bao-hiem'  => ['tieu-hoc' => 'Đội mũ bảo hiểm đúng cách? ⛑️', 'thcs' => 'Chọn mũ bảo hiểm đạt chuẩn thế nào? ⛑️'],
        'qua-duong'    => ['tieu-hoc' => 'Qua đường an toàn làm sao? 🚸', 'thcs' => 'Quy tắc nhìn trái–phải–trái khi sang đường? 🚸'],
        'xe-dap'       => ['tieu-hoc' => 'Đi xe đạp cần nhớ gì? 🚲', 'thcs' => 'Xe đạp điện có bắt buộc đội mũ không? 🚲'],
        'ngoi-xe'      => ['tieu-hoc' => 'Ngồi sau xe máy thế nào cho an toàn? 🛵', 'thcs' => 'Vì sao phải thắt dây an toàn trên ô tô? 🚗'],
        'uu-tien'      => ['tieu-hoc' => 'Gặp xe cứu thương thì làm gì? 🚑', 'thcs' => 'Những xe nào được quyền ưu tiên? 🚑'],
    ];
}

function build_suggested_chips(array $favTopics, array $weakTopics, array $recentTopics,
                               string $gradeBand, int $limit = 6): array {
    $band = $gradeBand === 'thcs' ? 'thcs' : 'tieu-hoc';
    $bank = chip_bank();
    $order = [];
    foreach ([$weakTopics, $favTopics, $recentTopics, array_keys($bank)] as $group) {
        foreach ($group as $t) {
            if (isset($bank[$t]) && !in_array($t, $order, true)) $order[] = $t;
        }
    }
    $chips = [];
    foreach (array_slice($order, 0, $limit) as $t) {
        $chips[] = ['topic' => $t, 'text' => $bank[$t][$band]];
    }
    return $chips;
}
```

- [ ] **Step 4: PASS** — `$PHP tests/test-personalize.php`.

- [ ] **Step 5: Viết `preferences.php`**:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/personalize.php';
header('Content-Type: application/json; charset=utf-8');
$db = new DB_UTILS();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isLoggedIn()) { echo json_encode(['status' => 'success', 'guest' => true]); exit; }
    $row = $db->getOne('SELECT grade_band, fav_topics, fav_place_types FROM user_preferences WHERE user_id = ?',
                       [(int)$_SESSION['user_id']]);
    echo json_encode([
        'status' => 'success', 'has_prefs' => (bool)$row,
        'prefs' => [
            'grade_band'      => $row['grade_band'] ?? 'tieu-hoc',
            'fav_topics'      => array_values(array_filter(explode(',', $row['fav_topics'] ?? ''))),
            'fav_place_types' => array_values(array_filter(explode(',', $row['fav_place_types'] ?? ''))),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

requireLogin(); requireCsrf();
$band   = ($_POST['grade_band'] ?? '') === 'thcs' ? 'thcs' : 'tieu-hoc';
$topics = array_values(array_intersect((array)($_POST['fav_topics'] ?? []), pref_topic_codes()));
$types  = array_values(array_intersect((array)($_POST['fav_place_types'] ?? []), pref_place_types()));
$db->execute(
    'INSERT INTO user_preferences (user_id, grade_band, fav_topics, fav_place_types)
     VALUES (?,?,?,?)
     ON DUPLICATE KEY UPDATE grade_band = VALUES(grade_band),
       fav_topics = VALUES(fav_topics), fav_place_types = VALUES(fav_place_types)',
    [(int)$_SESSION['user_id'], $band, implode(',', $topics), implode(',', $types)]);
echo json_encode(['status' => 'success']);
```

- [ ] **Step 6: Thêm `case 'chips':` vào `ai-chat.php`** (trước `default:`; nhớ `require_once __DIR__ . '/lib/personalize.php';` đầu file):

```php
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
```

- [ ] **Step 7: Smoke test** — browser đã đăng nhập: `ai-chat.php?action=chips` trả 6 chip; sau khi POST preferences với fav_topics=['xe-dap'] thì chip xe đạp nhảy lên trước.

- [ ] **Step 8: Commit** — `git add lib/personalize.php preferences.php tests/test-personalize.php ai-chat.php && git commit -m "feat: user_preferences API + máy sinh chip gợi ý cá nhân hoá"`

---

### Task 8: Xây lại giao diện AI Gia sư

**Files:**
- Modify: `ai-gia-su.php` (viết lại phần thân trang, giữ block PHP đầu file + head links)
- Modify: `assets/js/ai-gia-su.js` (viết lại)
- Test: thủ công theo checklist Step 4 (UI — không unit test; API đã test ở Task 6-7)

**Interfaces:**
- Consumes: `ai-chat.php` actions `sessions/messages/send/delete/chips` (contract Task 6-7), `preferences.php` (Task 7), `MascotSVG.pose()` (Task 4), class `.kid-*` (Task 3). CSRF: meta tag + header `X-CSRF-Token`.

- [ ] **Step 1: Viết lại thân `ai-gia-su.php`** — giữ nguyên 8 dòng PHP đầu + `<head>` (thêm `<meta name="csrf" content="<?= e(csrfToken()) ?>">`). Body mới:

```html
<body class="chat-page">
<header class="chat-top">
  <a class="kid-btn kid-btn--sky" href="index.php">← Trang chủ</a>
  <div class="chat-title">
    <div id="chat-mascot-mini"></div>
    <div><h1>AI Gia sư 🤖</h1><span id="engine-label" class="kid-badge">đang kết nối…</span></div>
  </div>
  <button id="btn-new-chat" class="kid-btn">✨ Chuyện mới</button>
</header>
<main class="chat-shell">
  <aside id="chat-sessions" class="kid-card chat-sidebar" aria-label="Lịch sử trò chuyện"></aside>
  <section class="chat-main kid-card">
    <div id="chat-log" aria-live="polite"></div>
    <div id="chip-row" class="chip-row" aria-label="Câu hỏi gợi ý"></div>
    <form id="chat-form" class="chat-input-row">
      <input id="chat-input" class="kid-input" type="text" maxlength="1000"
             placeholder="Con muốn hỏi gì về giao thông nào?" autocomplete="off">
      <button class="kid-btn kid-btn--green" type="submit" aria-label="Gửi">🚀</button>
    </form>
  </section>
</main>
<div id="onboard-modal" class="onboard-overlay" hidden><!-- JS đổ nội dung --></div>
<script src="assets/js/mascot.js"></script>
<script src="assets/js/ai-gia-su.js?v=2"></script>
</body>
```

CSS cho `.chat-*`, `.chip-row`, `.onboard-overlay`, bong bóng `.msg.bot/.msg.user`, hình minh hoạ `.msg-art` viết trong `<style>` của trang (theo token kid; bong bóng bot nền `#fff` viền `--kid-sky`, bong bóng user nền `--kid-yellow`; `.msg-art img` max-width 220px bo góc 14px; mobile ≤768px: ẩn sidebar sau nút ☰).

- [ ] **Step 2: Viết lại `assets/js/ai-gia-su.js`** — cấu trúc bắt buộc:

```js
const CSRF = document.querySelector('meta[name="csrf"]').content;
const state = { sessionId: 0, sending: false, gradeBand: 'tieu-hoc' };
// onboarding() gán state.gradeBand từ prefs.grade_band khi GET preferences.php trả về

async function api(url, opts = {}) {
  const res = await fetch(url, { ...opts, headers: { 'X-CSRF-Token': CSRF, ...(opts.headers || {}) } });
  return res.json();
}
function addMsg(role, text, artUrl = null) {
  const log = document.getElementById('chat-log');
  const el = document.createElement('div');
  el.className = 'msg ' + role;
  if (role === 'bot') {
    el.innerHTML = '<div class="msg-avatar"></div><div class="msg-body"></div>';
    el.querySelector('.msg-avatar').innerHTML = MascotSVG.pose('point');
    el.querySelector('.msg-body').textContent = text;           // textContent — chống XSS
    if (artUrl) {
      const art = document.createElement('div');
      art.className = 'msg-art';
      art.innerHTML = '<img alt="Hình minh hoạ" loading="lazy">';
      art.querySelector('img').src = artUrl;
      el.querySelector('.msg-body').appendChild(art);
    }
  } else { el.textContent = text; }
  log.appendChild(el); log.scrollTop = log.scrollHeight;
}
async function send(text) { /* POST ai-chat.php action=send (FormData: message, session_id,
  age_group = state.gradeBand === 'thcs' ? '9-11' : '6-8' — giọng điệu theo khối lớp vào system prompt)
  → addMsg('bot', d.reply, d.art_url); cập nhật state.sessionId, engine-label
  (engine 'gemini' → "● AI thật" badge xanh lá, 'offline' → "● Chế độ offline" badge vàng);
  lỗi mạng → addMsg('bot', 'Ôi, có gì đó chưa ổn, thử lại nhé! 🙈') + MascotSVG.pose('worry') */ }
async function loadChips() { /* GET ai-chat.php?action=chips → render .kid-chip vào #chip-row, click = send(text);
  nếu guest: thêm chip cuối "🔑 Đăng nhập để mình hiểu bạn hơn" link login.php */ }
async function loadSessions() { /* GET ?action=sessions → sidebar; click load ?action=messages; nút xoá POST action=delete */ }
async function onboarding() { /* GET preferences.php; nếu !guest && !has_prefs → render modal 2 bước trong #onboard-modal:
  bước 1 chọn khối lớp (2 nút to 🧒 Tiểu học / 🎒 THCS), bước 2 chọn sticker chủ đề (7 topic, toggle .active)
  + loại địa điểm (4 mã); nút "Xong 🎉" POST preferences.php (FormData grade_band, fav_topics[], fav_place_types[])
  rồi loadChips() lại; mascot pose('cheer') khi xong */ }

document.getElementById('chat-form').addEventListener('submit', e => {
  e.preventDefault();
  const input = document.getElementById('chat-input');
  if (input.value.trim() && !state.sending) { addMsg('user', input.value.trim()); send(input.value.trim()); input.value = ''; }
});
document.getElementById('chat-mascot-mini').innerHTML = MascotSVG.pose('wave');
addMsg('bot', 'Chào con! 👋 Mình là AI Gia sư. Bấm một câu gợi ý bên dưới hoặc tự hỏi mình nhé!');
loadSessions(); loadChips(); onboarding();
```

Các thân hàm chú thích `/* ... */` phải được viết đầy đủ theo mô tả trong chú thích — mô tả là yêu cầu, không phải gợi ý.

- [ ] **Step 3: Lint + reload** — `$PHP -l ai-gia-su.php`; mở trang, hard-refresh.

- [ ] **Step 4: Checklist thủ công (phải đạt hết):**
  - Guest: chat được, chip mặc định hiện, hỏi "đèn đỏ là gì" → bong bóng bot kèm hình đèn tín hiệu.
  - Đăng nhập `hocsinh@demo.com` lần đầu: modal onboarding hiện; chọn THCS + xe đạp → chip "xe đạp điện..." lên đầu; reload không hiện modal nữa.
  - Sidebar hiện lịch sử, bấm chuyện cũ load lại tin nhắn, xoá được.
  - Rút mạng (tắt Wi-Fi): gửi tin → vẫn có trả lời offline + badge vàng "Chế độ offline".
  - Mobile 375px: sidebar ẩn sau ☰, ô nhập không bị bàn phím che.
- [ ] **Step 5: Commit** — `git commit -am "feat: giao diện AI Gia sư mới — mascot, hình minh hoạ, chip cá nhân hoá, onboarding"`

---

### Task 9: Trang Khám phá + chi tiết địa điểm

**Files:**
- Create: `lib/places-repo.php`
- Create: `kham-pha.php`
- Create: `dia-diem.php`
- Modify: `index.php` (thêm link "🗺️ Khám phá" vào nav)
- Test: `tests/test-places-repo.php`

**Interfaces:**
- Consumes: bảng `places` + seed (Task 1-2), `svg_art('map-bmt')` + art theo `art_code` (Task 5), `.kid-*` (Task 3).
- Produces: `places_all(?string $type = null): array` (published, sắp theo distance_km ASC), `place_by_slug(string $slug): ?array`, `place_type_label(string $type): string` (bao-tang → "Bảo tàng & di tích", cong-vien → "Công viên", vui-choi → "Vui chơi", thien-nhien → "Thiên nhiên"), `place_reviews_approved(int $placeId): array` (kèm name + avatar_emoji người viết, mới nhất trước).

- [ ] **Step 1: Viết failing test**

`tests/test-places-repo.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/places-repo.php';

$all = places_all();
check(count($all) >= 15, 'places_all trả >= 15');
check((float)$all[0]['distance_km'] <= (float)end($all)['distance_km'], 'sắp theo khoảng cách tăng dần');
$mus = places_all('bao-tang');
check(count($mus) >= 2 && count(array_unique(array_column($mus, 'type'))) === 1, 'lọc đúng loại');
$p = place_by_slug('bao-tang-dak-lak');
check($p !== null && $p['name'] === 'Bảo tàng Đắk Lắk', 'place_by_slug đúng');
check(place_by_slug('khong-co') === null, 'slug lạ → null');
check(place_type_label('thien-nhien') === 'Thiên nhiên', 'label loại đúng');
check(is_array(place_reviews_approved((int)$p['id'])), 'reviews_approved trả mảng');
done();
```

- [ ] **Step 2: FAIL** rồi **Step 3: Viết `lib/places-repo.php`**:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/../db_utils.php';

function place_type_label(string $type): string {
    $labels = ['bao-tang' => 'Bảo tàng & di tích', 'cong-vien' => 'Công viên',
               'vui-choi' => 'Vui chơi', 'thien-nhien' => 'Thiên nhiên'];
    return $labels[$type] ?? $type;
}

function places_all(?string $type = null): array {
    $db = new DB_UTILS();
    if ($type !== null) {
        return $db->getAll("SELECT * FROM places WHERE status='published' AND type=? ORDER BY distance_km ASC", [$type]);
    }
    return $db->getAll("SELECT * FROM places WHERE status='published' ORDER BY distance_km ASC");
}

function place_by_slug(string $slug): ?array {
    $row = (new DB_UTILS())->getOne("SELECT * FROM places WHERE slug=? AND status='published'", [$slug]);
    return $row ?: null;
}

function place_reviews_approved(int $placeId): array {
    return (new DB_UTILS())->getAll(
        "SELECT r.stars, r.content, r.photos, r.created_at, u.name, u.avatar_emoji
         FROM place_reviews r JOIN users u ON u.id = r.user_id
         WHERE r.place_id = ? AND r.status = 'approved' ORDER BY r.id DESC", [$placeId]);
}
```

- [ ] **Step 4: PASS** — `$PHP tests/test-places-repo.php`.

- [ ] **Step 5: Viết `kham-pha.php`** (public, không cần đăng nhập — theo convention trang tính năng). Cấu trúc: head chuẩn (copy từ `ai-gia-su.php`, đổi title "Khám phá Buôn Ma Thuột"), rồi:

```php
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/places-repo.php';
require_once __DIR__ . '/lib/svg-lib.php';
$type = $_GET['type'] ?? null;
if (!in_array($type, ['bao-tang', 'cong-vien', 'vui-choi', 'thien-nhien'], true)) $type = null;
$places = places_all($type);
$allPlaces = $type === null ? $places : places_all();
?>
```

Body:
- Hero: `<h1>🗺️ Khám phá Buôn Ma Thuột</h1>` + mascot `pose('point')` + câu dẫn "Cùng Siêu Nhí đi chơi khắp thành phố — an toàn trên từng con đường!".
- **Bản đồ sticker**: `<div class="map-wrap">` chứa `<?= svg_art('map-bmt') ?>` + với mỗi `$allPlaces` một `<a class="map-pin" href="dia-diem.php?slug=..." style="left:<?= (int)$p['map_x'] ?>%; top:<?= (int)$p['map_y'] ?>%" title="<?= e($p['name']) ?>">` chứa emoji theo type (🏛️🌳🎡🏞️). `.map-wrap{position:relative}` `.map-pin{position:absolute; transform:translate(-50%,-50%); font-size:26px; transition:transform .15s}` `.map-pin:hover{transform:translate(-50%,-50%) scale(1.35)}`.
- **Bộ lọc**: 5 link `.kid-chip` (Tất cả + 4 loại, active theo `$type`), href `?type=...`.
- **Lưới thẻ**: mỗi `$places` một `.kid-card.sticker-tilt` gồm `<img src="art.php?code=<?= e($p['art_code']) ?>">`, tên, `.kid-badge` loại + `📍 <?= $p['distance_km'] ?>km`, `.kid-badge--yellow` age_note, nút `.kid-btn kid-btn--sky` "Đến xem! →" link `dia-diem.php?slug=...`. Grid `repeat(auto-fill, minmax(240px, 1fr))`.

- [ ] **Step 6: Viết `dia-diem.php`**:

```php
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/places-repo.php';
$p = place_by_slug((string)($_GET['slug'] ?? ''));
if ($p === null) { http_response_code(404); /* render trang 404 thân thiện: mascot worry + nút về kham-pha.php */ exit; }
$reviews = place_reviews_approved((int)$p['id']);
$user = currentUser();
?>
```

Body theo thứ tự:
1. Breadcrumb `← Khám phá` + `<h1><?= e($p['name']) ?></h1>` + badge loại.
2. Hero art `<img src="art.php?code=<?= e($p['art_code']) ?>">` khổ lớn trong `.kid-card`.
3. `.kid-card` "Chuyện kể" — `<?= nl2br(e($p['story'])) ?>`.
4. Lưới 4 ô info: 🕐 `open_hours`, 🎟️ `ticket`, 📍 `distance_km` km từ trường, 🧒 `age_note`.
5. `.kid-card` viền `--kid-yellow` **"🚸 Đường đến an toàn"** — `nl2br(e($p['safety_note']))` + hình `art.php?code=qua-duong`.
6. Review đã duyệt: mỗi review 1 `.kid-card` — avatar_emoji + name + sao (⭐ lặp `stars`) + content (`e()`) + ảnh nếu `photos` (JSON decode → `<img src="uploads/reviews/<?= e($f) ?>" loading="lazy">`). Rỗng → "Chưa có bạn nào kể về nơi này. Con đi rồi kể đầu tiên nha! 🌟".
7. Form review (Task 11 sẽ nối endpoint — Task này render sẵn UI): nếu `$user` → form `#review-form` (5 nút mặt cười ☹️🙁😐🙂😍 chọn sao, textarea `.kid-input`, input file `accept="image/*" multiple`, nút gửi `.kid-btn--green`, hidden csrf) — Task 11 mới cho submit hoạt động, tạm `disabled` + badge "Sắp mở!"; nếu guest → `.kid-card` mời đăng nhập.

- [ ] **Step 7: Thêm link nav** — `index.php`: thêm `<a href="kham-pha.php">🗺️ Khám phá</a>` cạnh các link tính năng hiện có (tìm nav bằng `grep -n "ai-gia-su.php" index.php`).

- [ ] **Step 8: Kiểm tra bằng mắt** — kham-pha.php: 15 pin trên bản đồ đúng vị trí tương đối, lọc hoạt động, thẻ nghiêng xen kẽ; dia-diem.php với `?slug=bao-tang-dak-lak` đủ 7 khối; `?slug=xxx` ra 404 thân thiện; mobile 375px không tràn ngang.

- [ ] **Step 9: Commit** — `git add lib/places-repo.php kham-pha.php dia-diem.php tests/test-places-repo.php index.php && git commit -m "feat: trang Khám phá bản đồ sticker + chi tiết địa điểm với Đường đến an toàn"`

---

### Task 10: AI lịch trình

**Files:**
- Create: `lib/itinerary.php`
- Create: `lich-trinh-api.php`
- Create: `lich-trinh-ai.php`
- Test: `tests/test-itinerary.php`

**Interfaces:**
- Consumes: `places_all()` (Task 9), `GEMINI_API_KEY`/`gemini_endpoint()` (ai-engine.php), bảng `ai_itineraries` (Task 1).
- Produces:
  - `itinerary_offline(array $places, array $opts): array` — `$opts = ['time_slot' => 'sang'|'ca-ngay'|'cuoi-tuan', 'vehicle' => 'di-bo'|'xe-dap'|'bo-me-cho', 'types' => string[]]`. Trả mảng leg: `['slug','name','time','activity','safety_tip']`.
  - `itinerary_ai(array $places, array $opts): ?array` — Gemini; trả `null` nếu lỗi/không hợp lệ.
  - `POST lich-trinh-api.php` (CSRF) → `{status, engine: 'gemini'|'offline', legs: [...]}`; nếu đăng nhập thì lưu `ai_itineraries`.

- [ ] **Step 1: Viết failing test**

`tests/test-itinerary.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/itinerary.php';

function fake_place(string $slug, float $km, string $type = 'cong-vien'): array {
    return ['slug' => $slug, 'name' => "Nơi $slug", 'type' => $type, 'distance_km' => $km,
            'open_hours' => '8:00–17:00', 'story' => "Câu chuyện về $slug. Chi tiết thêm.",
            'safety_note' => "Lưu ý an toàn của $slug."];
}
$places = [fake_place('a', 0.5), fake_place('b', 1.5, 'bao-tang'), fake_place('c', 5.0), fake_place('d', 12.0, 'thien-nhien')];

$legs = itinerary_offline($places, ['time_slot' => 'sang', 'vehicle' => 'di-bo', 'types' => []]);
check(count($legs) === 2, 'buổi sáng → 2 chặng');
check($legs[0]['slug'] === 'a' && $legs[1]['slug'] === 'b', 'đi bộ chỉ lấy điểm <= 2km, gần trước');
check($legs[0]['time'] === '08:00', 'chặng đầu 08:00');
check(str_contains($legs[0]['safety_tip'], 'Lưu ý an toàn của a'), 'ghép safety_note của địa điểm');
check(str_contains($legs[0]['safety_tip'], 'vỉa hè'), 'ghép thêm tip theo phương tiện đi bộ');

$legs = itinerary_offline($places, ['time_slot' => 'cuoi-tuan', 'vehicle' => 'bo-me-cho', 'types' => []]);
check(count($legs) === 4, 'cuối tuần + bố mẹ chở → 4 chặng');

$legs = itinerary_offline($places, ['time_slot' => 'ca-ngay', 'vehicle' => 'xe-dap', 'types' => ['bao-tang']]);
check(count($legs) === 1 && $legs[0]['slug'] === 'b', 'lọc theo loại + xe đạp <= 6km');

check(itinerary_offline([], ['time_slot' => 'sang', 'vehicle' => 'di-bo', 'types' => []]) === [], 'không có điểm → mảng rỗng');
done();
```

- [ ] **Step 2: FAIL** rồi **Step 3: Viết `lib/itinerary.php`**:

```php
<?php
declare(strict_types=1);

function itinerary_vehicle_tip(string $vehicle): string {
    $tips = [
        'di-bo'     => 'Đi trên vỉa hè, qua đường đúng vạch kẻ và nắm tay người lớn nhé! 🚶',
        'xe-dap'    => 'Đội mũ bảo hiểm, đi sát lề phải và không dàn hàng ngang nhé! 🚲',
        'bo-me-cho' => 'Đội mũ bảo hiểm cài quai chắc chắn và ôm chặt bố mẹ nhé! 🛵',
    ];
    return $tips[$vehicle] ?? $tips['bo-me-cho'];
}

function itinerary_offline(array $places, array $opts): array {
    $maxKm  = ['di-bo' => 2.0, 'xe-dap' => 6.0, 'bo-me-cho' => 999.0][$opts['vehicle']] ?? 999.0;
    $stops  = ['sang' => 2, 'ca-ngay' => 3, 'cuoi-tuan' => 4][$opts['time_slot']] ?? 2;
    $slots  = ['08:00', '09:45', '13:30', '15:30'];
    $types  = $opts['types'] ?? [];

    $ok = array_values(array_filter($places, function ($p) use ($maxKm, $types) {
        if ((float)$p['distance_km'] > $maxKm) return false;
        return $types === [] || in_array($p['type'], $types, true);
    }));
    usort($ok, fn($a, $b) => (float)$a['distance_km'] <=> (float)$b['distance_km']);

    $legs = [];
    foreach (array_slice($ok, 0, $stops) as $i => $p) {
        $firstSentence = explode('.', $p['story'])[0] . '.';
        $legs[] = [
            'slug' => $p['slug'], 'name' => $p['name'], 'time' => $slots[$i],
            'activity' => trim($firstSentence),
            'safety_tip' => trim($p['safety_note']) . ' ' . itinerary_vehicle_tip($opts['vehicle']),
        ];
    }
    return $legs;
}
```

- [ ] **Step 4: PASS** — `$PHP tests/test-itinerary.php`.

- [ ] **Step 5: Thêm `itinerary_ai()`** vào `lib/itinerary.php` (require `ai-engine.php` từ phía caller, không require trong lib để test không cần nó):

```php
/* Gemini sắp lịch — chỉ được CHỌN từ danh sách places, không bịa. Trả null nếu lỗi → caller fallback offline. */
function itinerary_ai(array $places, array $opts): ?array {
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') return null;
    $menu = array_map(fn($p) => ['slug' => $p['slug'], 'name' => $p['name'], 'type' => $p['type'],
        'km' => (float)$p['distance_km'], 'open' => $p['open_hours'], 'safety' => $p['safety_note']], $places);
    $prompt = "Bạn lập lịch trình tham quan Buôn Ma Thuột cho học sinh. CHỈ chọn địa điểm từ JSON sau, "
        . "tôn trọng giờ mở cửa và phương tiện '{$opts['vehicle']}' (di-bo tối đa 2km, xe-dap tối đa 6km), "
        . "khung thời gian '{$opts['time_slot']}' (sang=2 điểm, ca-ngay=3, cuoi-tuan=4). "
        . "Trả về DUY NHẤT JSON mảng [{slug,time,activity,safety_tip}] — activity 1 câu vui cho trẻ em, "
        . "safety_tip dựa trên trường safety của điểm đó. Danh sách: " . json_encode($menu, JSON_UNESCAPED_UNICODE);
    [$url, $headers] = gemini_endpoint(GEMINI_MODEL);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode(['contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 1024, 'responseMimeType' => 'application/json']]),
        CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $res = curl_exec($ch); curl_close($ch);
    if ($res === false) return null;
    $text = json_decode($res, true)['candidates'][0]['content']['parts'][0]['text'] ?? null;
    $legs = $text !== null ? json_decode($text, true) : null;
    if (!is_array($legs) || $legs === []) return null;
    $bySlug = array_column($places, null, 'slug');
    $out = [];
    foreach ($legs as $leg) {
        if (!isset($leg['slug'], $bySlug[$leg['slug']])) return null;   // AI bịa slug → loại toàn bộ, dùng offline
        $out[] = ['slug' => $leg['slug'], 'name' => $bySlug[$leg['slug']]['name'],
                  'time' => (string)($leg['time'] ?? ''), 'activity' => (string)($leg['activity'] ?? ''),
                  'safety_tip' => (string)($leg['safety_tip'] ?? $bySlug[$leg['slug']]['safety_note'])];
    }
    return $out;
}
```

- [ ] **Step 6: Viết `lich-trinh-api.php`**:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ai-engine.php';
require_once __DIR__ . '/lib/places-repo.php';
require_once __DIR__ . '/lib/itinerary.php';
header('Content-Type: application/json; charset=utf-8');
requireCsrf();

$opts = [
    'time_slot' => in_array($_POST['time_slot'] ?? '', ['sang','ca-ngay','cuoi-tuan'], true) ? $_POST['time_slot'] : 'sang',
    'vehicle'   => in_array($_POST['vehicle'] ?? '', ['di-bo','xe-dap','bo-me-cho'], true) ? $_POST['vehicle'] : 'bo-me-cho',
    'types'     => array_values(array_intersect((array)($_POST['types'] ?? []),
                    ['bao-tang','cong-vien','vui-choi','thien-nhien'])),
];
$places = places_all();
$legs = itinerary_ai($places, $opts);
$engine = $legs !== null ? 'gemini' : 'offline';
if ($legs === null) $legs = itinerary_offline($places, $opts);

if (isLoggedIn()) {
    (new DB_UTILS())->execute(
        'INSERT INTO ai_itineraries (user_id, params, plan, engine) VALUES (?,?,?,?)',
        [(int)$_SESSION['user_id'], json_encode($opts, JSON_UNESCAPED_UNICODE),
         json_encode($legs, JSON_UNESCAPED_UNICODE), $engine]);
}
echo json_encode(['status' => 'success', 'engine' => $engine, 'legs' => $legs], JSON_UNESCAPED_UNICODE);
```

- [ ] **Step 7: Viết `lich-trinh-ai.php`** — head chuẩn + meta csrf. Body: wizard 3 bước, mỗi bước 1 `.kid-card` với các nút sticker toggle (`data-value`): bước 1 "Đi khi nào?" (🌅 Buổi sáng / ☀️ Cả ngày / 🎉 Cuối tuần), bước 2 "Đi bằng gì?" (🚶 Đi bộ / 🚲 Xe đạp / 🛵 Bố mẹ chở), bước 3 "Thích gì?" (4 loại, multi-select, mặc định lấy `fav_place_types` từ `preferences.php` nếu đăng nhập). Nút `#btn-go` `.kid-btn--green` "Lên lịch thôi! 🚀" → JS `fetch('lich-trinh-api.php', {method:'POST', body: FormData, headers:{'X-CSRF-Token': CSRF}})` → render `#result` dạng **vé hành trình**: khối `.kid-card` viền vàng, mỗi leg 1 hàng timeline (chấm tròn giờ màu `--kid-sky`, tên link `dia-diem.php?slug=`, activity, `safety_tip` trong `.kid-badge--yellow` đầy đủ), mascot `pose('cheer')` trên đầu vé, badge engine (như Task 8), nút "🖨️ In vé" gọi `window.print()` (thêm `@media print` ẩn wizard/nav). JS đặt inline `<script>` cuối trang.

- [ ] **Step 8: Smoke test** — không key: bấm wizard → vé offline đúng số chặng theo lựa chọn; dán key thật vào `ai-engine.php` → engine gemini, các slug đều là địa điểm thật; tắt mạng với key → vẫn ra vé offline. Đăng nhập → bảng `ai_itineraries` có hàng mới.

- [ ] **Step 9: Commit** — `git add lib/itinerary.php lich-trinh-api.php lich-trinh-ai.php tests/test-itinerary.php && git commit -m "feat: AI lịch trình — thuật toán offline + Gemini chống bịa + vé hành trình in được"`

---

### Task 11: Gửi review + upload ảnh an toàn

**Files:**
- Create: `lib/upload.php`
- Create: `review-submit.php`
- Create: `uploads/reviews/.htaccess` (+ tạo thư mục)
- Modify: `dia-diem.php` (kích hoạt form Task 9 Step 6.7 + JS submit)
- Test: `tests/test-upload.php`

**Interfaces:**
- Consumes: form `#review-form` (Task 9), bảng `place_reviews` (Task 1).
- Produces: `validate_review_image(array $file): array` — nhận phần tử chuẩn `$_FILES` dạng `['tmp_name','error','size']`, trả `['ok' => bool, 'error' => ?string, 'ext' => ?string]`; chấp nhận JPEG/PNG/WebP ≤ 5MB, kiểm tra MIME thật bằng `finfo`. `POST review-submit.php` (CSRF, login) fields `place_id, stars, content, photos[]` → `{status:'success'}` | lỗi 4xx `{status:'error', message}`. Giới hạn 5 review/người/ngày.

- [ ] **Step 1: Viết failing test**

`tests/test-upload.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/upload.php';

/* PNG 1x1 thật để finfo nhận diện */
$pngFile = tempnam(sys_get_temp_dir(), 'png');
file_put_contents($pngFile, base64_decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$txtFile = tempnam(sys_get_temp_dir(), 'txt');
file_put_contents($txtFile, 'day khong phai anh');

$r = validate_review_image(['tmp_name' => $pngFile, 'error' => UPLOAD_ERR_OK, 'size' => filesize($pngFile)]);
check($r['ok'] === true && $r['ext'] === 'png', 'PNG thật hợp lệ, ext=png');

$r = validate_review_image(['tmp_name' => $txtFile, 'error' => UPLOAD_ERR_OK, 'size' => 20]);
check($r['ok'] === false && $r['error'] !== null, 'file text đội lốt bị chặn');

$r = validate_review_image(['tmp_name' => $pngFile, 'error' => UPLOAD_ERR_OK, 'size' => 6 * 1024 * 1024]);
check($r['ok'] === false, 'quá 5MB bị chặn');

$r = validate_review_image(['tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]);
check($r['ok'] === false, 'không có file bị chặn');

unlink($pngFile); unlink($txtFile);
done();
```

- [ ] **Step 2: FAIL** rồi **Step 3: Viết `lib/upload.php`**:

```php
<?php
declare(strict_types=1);

function validate_review_image(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Tải ảnh lên chưa thành công, thử lại nhé!', 'ext' => null];
    }
    if ((int)$file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Ảnh to quá (tối đa 5MB) 🙈', 'ext' => null];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
    $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extByMime[$mime])) {
        return ['ok' => false, 'error' => 'Chỉ nhận ảnh JPEG, PNG hoặc WebP nhé!', 'ext' => null];
    }
    return ['ok' => true, 'error' => null, 'ext' => $extByMime[$mime]];
}
```

- [ ] **Step 4: PASS** — `$PHP tests/test-upload.php`.

- [ ] **Step 5: Thư mục upload khoá thực thi** — `mkdir -p uploads/reviews`; `uploads/reviews/.htaccess`:

```apache
Options -Indexes -ExecCGI
<FilesMatch "\.(?i:php|phtml|php3|phar|cgi)$">
  Require all denied
</FilesMatch>
```

Thêm `uploads/reviews/*` vào git bằng file giữ chỗ `uploads/reviews/.gitkeep`, và thêm dòng `uploads/reviews/*` + `!uploads/reviews/.htaccess` + `!uploads/reviews/.gitkeep` vào `.gitignore`.

- [ ] **Step 6: Viết `review-submit.php`**:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/upload.php';
header('Content-Type: application/json; charset=utf-8');
requireLogin(); requireCsrf();

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE); exit;
}

$db = new DB_UTILS();
$userId  = (int)$_SESSION['user_id'];
$placeId = (int)($_POST['place_id'] ?? 0);
$stars   = (int)($_POST['stars'] ?? 0);
$content = trim((string)($_POST['content'] ?? ''));

if (!$db->getOne('SELECT id FROM places WHERE id = ? AND status = "published"', [$placeId])) fail('Địa điểm không tồn tại');
if ($stars < 1 || $stars > 5) fail('Con hãy chọn mức mặt cười nhé!');
$len = mb_strlen($content, 'UTF-8');
if ($len < 5 || $len > 500) fail('Lời kể từ 5 đến 500 ký tự nhé!');
$today = (int)$db->getValue(
    'SELECT COUNT(*) FROM place_reviews WHERE user_id = ? AND created_at >= CURDATE()', [$userId]);
if ($today >= 5) fail('Hôm nay con kể đủ 5 chuyến rồi, mai kể tiếp nha! 🌙', 429);

$saved = [];
foreach (array_slice(array_keys($_FILES['photos']['name'] ?? []), 0, 3) as $i) {
    $file = ['tmp_name' => $_FILES['photos']['tmp_name'][$i],
             'error' => $_FILES['photos']['error'][$i], 'size' => $_FILES['photos']['size'][$i]];
    if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
    $v = validate_review_image($file);
    if (!$v['ok']) fail($v['error']);
    $name = bin2hex(random_bytes(16)) . '.' . $v['ext'];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/reviews/' . $name)) fail('Không lưu được ảnh, thử lại nhé!', 500);
    $saved[] = $name;
}

$db->execute('INSERT INTO place_reviews (place_id, user_id, stars, content, photos) VALUES (?,?,?,?,?)',
    [$placeId, $userId, $stars, $content, $saved !== [] ? json_encode($saved) : null]);
echo json_encode(['status' => 'success', 'message' => 'Cảm ơn con! Bài kể đang chờ cô duyệt 🕐'], JSON_UNESCAPED_UNICODE);
```

- [ ] **Step 7: Kích hoạt form trong `dia-diem.php`** — bỏ `disabled`/badge "Sắp mở!"; JS inline: submit `FormData` (kèm `photos[]`) tới `review-submit.php` với header CSRF; thành công → thay form bằng `.kid-card` thông báo message trả về + mascot `pose('cheer')`; lỗi → hiện message đỏ dưới form + mascot `pose('worry')`.

- [ ] **Step 8: Test tay** — đăng nhập gửi review kèm 1 ảnh → DB có hàng `pending`, ảnh nằm trong `uploads/reviews/` tên random; đổi tên file `.php` giả ảnh → bị chặn; gửi 6 lần/ngày → lần 6 báo nghỉ; truy cập trực tiếp `uploads/reviews/x.php` (tự tạo) → 403.

- [ ] **Step 9: Commit** — `git add -A && git commit -m "feat: gửi review kèm ảnh — validate MIME thật, rate limit, thư mục upload khoá thực thi"`

---

### Task 12: Duyệt review trong dashboard + thưởng XP

**Files:**
- Create: `review-moderate.php`
- Modify: `dashboard-giao-vien.php` (thêm section "Duyệt review" + link sidebar)
- Modify: `dashboard-admin.php` (same — copy section, đổi active link)
- Test: `tests/test-moderate-flow.php` (integration qua DB)

**Interfaces:**
- Consumes: `place_reviews` pending (Task 11), `requireRole(['giaovien','admin'])`, `student_progress.xp`.
- Produces: `POST review-moderate.php` (CSRF) fields `review_id, action ('approve'|'reject'), reason?` → approve: status approved + reviewed_by/reviewed_at + cộng 20 XP cho người viết (upsert `student_progress`); reject: status rejected + reject_reason. Trả `{status:'success'}`.

- [ ] **Step 1: Viết failing integration test** (thao tác DB trực tiếp mô phỏng logic duyệt — test hàm `moderate_review()` tách được):

Tách logic vào `lib/moderation.php` để test không cần HTTP:

`tests/test-moderate-flow.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/moderation.php';
require __DIR__ . '/../db_utils.php';

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

$db->execute("INSERT INTO place_reviews (place_id,user_id,stars,content) VALUES (?,?,4,'chuyến khác')", [$pid, $uid]);
$rid2 = (int)$db->getLastInsertId();
check(moderate_review($db, $rid2, 'reject', 'Ảnh mờ quá', 999) === true, 'reject trả true');
check($db->getValue("SELECT reject_reason FROM place_reviews WHERE id=?", [$rid2]) === 'Ảnh mờ quá', 'lưu lý do');
$db->rollBack();
done();
```

- [ ] **Step 2: FAIL** rồi **Step 3: Viết `lib/moderation.php`**:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/../db_utils.php';

/* Duyệt/từ chối review. Chỉ tác động review đang pending — trả false nếu không còn pending. */
function moderate_review(DB_UTILS $db, int $reviewId, string $action, ?string $reason, int $moderatorId): bool {
    $review = $db->getOne('SELECT id, user_id, status FROM place_reviews WHERE id = ?', [$reviewId]);
    if (!$review || $review['status'] !== 'pending') return false;
    if ($action === 'approve') {
        $db->execute('UPDATE place_reviews SET status="approved", reviewed_by=?, reviewed_at=NOW() WHERE id=?',
                     [$moderatorId, $reviewId]);
        $db->execute('INSERT INTO student_progress (student_id, xp) VALUES (?, 20)
                      ON DUPLICATE KEY UPDATE xp = xp + 20', [(int)$review['user_id']]);
        return true;
    }
    if ($action === 'reject') {
        $db->execute('UPDATE place_reviews SET status="rejected", reject_reason=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?',
                     [$reason !== null && $reason !== '' ? mb_substr($reason, 0, 255) : null, $moderatorId, $reviewId]);
        return true;
    }
    return false;
}

function pending_reviews(DB_UTILS $db): array {
    return $db->getAll(
        'SELECT r.id, r.stars, r.content, r.photos, r.created_at, u.name AS author, p.name AS place_name
         FROM place_reviews r JOIN users u ON u.id = r.user_id JOIN places p ON p.id = r.place_id
         WHERE r.status = "pending" ORDER BY r.id ASC');
}
```

- [ ] **Step 4: PASS** — `$PHP tests/test-moderate-flow.php`.

- [ ] **Step 5: Viết `review-moderate.php`**:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/moderation.php';
header('Content-Type: application/json; charset=utf-8');
requireRole(['giaovien', 'admin']); requireCsrf();

$ok = moderate_review(new DB_UTILS(), (int)($_POST['review_id'] ?? 0),
    (string)($_POST['action'] ?? ''), $_POST['reason'] ?? null, (int)$_SESSION['user_id']);
if (!$ok) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Review không hợp lệ hoặc đã duyệt rồi']); exit; }
echo json_encode(['status' => 'success']);
```

- [ ] **Step 6: Thêm section vào 2 dashboard** — trong `dashboard-giao-vien.php`: sidebar thêm `<a class="side-link" href="#duyet-review"><span class="ic">📝</span> Duyệt review</a>`; cuối main thêm section `id="duyet-review"`: PHP `require_once lib/moderation.php`, `$pending = pending_reviews($db)`; render mỗi review 1 `.kid-card`: place_name + author + sao + content (`e()`) + ảnh thumbnail (decode `photos` JSON, `<img src="uploads/reviews/..." width="90">`) + 2 nút `.kid-btn--green` Duyệt / `.kid-btn--red` Từ chối (prompt lý do). JS inline: POST `review-moderate.php` với CSRF header (thêm meta csrf vào head nếu chưa có) → xoá card khỏi DOM khi success; đếm badge số pending trên link sidebar. Copy y nguyên sang `dashboard-admin.php`.

- [ ] **Step 7: E2E tay vòng khép kín** — hocsinh gửi review (Task 11) → giaovien thấy trong tab, bấm Duyệt → review hiện trên `dia-diem.php`, XP học sinh +20 (xem dashboard học sinh); review khác bấm Từ chối → không hiện công khai.

- [ ] **Step 8: Commit** — `git add -A && git commit -m "feat: hàng chờ duyệt review cho GV/Admin + thưởng 20 XP khi được duyệt"`

---

### Task 13: Tổng kiểm tra + kịch bản demo

**Files:**
- Create: `docs/superpowers/demo-checklist.md`
- Modify: các file phát sinh lỗi khi kiểm tra

- [ ] **Step 1: Chạy toàn bộ test tự động** — `for f in tests/test-*.php; do $PHP "$f" || echo "FAILED: $f"; done` → tất cả pass.
- [ ] **Step 2: Lint toàn bộ** — `for f in *.php lib/*.php; do $PHP -l "$f" | grep -v "No syntax" ; done` → không output.
- [ ] **Step 3: Responsive sweep** — các trang `index, ai-gia-su, kham-pha, dia-diem?slug=bao-tang-dak-lak, lich-trinh-ai, dashboard-giao-vien` tại 320/375/768/1024/1440 px: không tràn ngang, nút bấm được ≥44px. Sửa tại chỗ nếu lỗi.
- [ ] **Step 4: Reduced motion + contrast** — bật "Reduce Motion" của macOS: không còn hiệu ứng nhún/nghiêng; kiểm tra chữ `--kid-ink-soft` trên nền kem đạt AA (nếu < 4.5:1 thì đậm hoá giá trị).
- [ ] **Step 5: Viết `docs/superpowers/demo-checklist.md`** — kịch bản trình diễn 5 phút: (1) landing mới + mascot chào, (2) chat hỏi 2 câu có hình + chip cá nhân hoá, (3) kham-pha → dia-diem → Đường đến an toàn, (4) lên lịch trình + in vé, (5) hocsinh gửi review → giaovien duyệt → hiện công khai + XP. Ghi rõ tài khoản demo và trạng thái cần chuẩn bị trước (có/không API key).
- [ ] **Step 6: Commit** — `git add -A && git commit -m "chore: tổng kiểm tra + kịch bản demo 5 phút"`
