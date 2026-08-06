# Siêu Nhí An Toàn Giao Thông AI — Hướng dẫn chạy PHP

Từ bản này, site đã chuyển từ HTML tĩnh sang **PHP thật** với:
- Đăng nhập / Đăng ký lưu vào **MySQL** (mật khẩu được hash bằng `password_hash`, không lưu plain text)
- **Session PHP** để ghi nhớ đăng nhập
- **Phân quyền theo vai trò** (`hocsinh`, `phuhuynh`, `giaovien`, `admin`) — mỗi Dashboard chỉ vào được đúng vai trò tương ứng (admin được vào tất cả)

⚠️ Vì đã dùng PHP + MySQL thật, **không thể mở trực tiếp bằng Live Server / double-click file nữa** — bắt buộc phải chạy qua một máy chủ PHP có hỗ trợ MySQL.

---

## 1. Cài môi trường (chọn 1 trong các cách sau)

- **XAMPP** (khuyên dùng, dễ nhất): tải tại https://www.apachefriends.org, cài đặt xong khởi động **Apache** và **MySQL** trong XAMPP Control Panel.
- **Laragon** (Windows): tương tự XAMPP, gọn nhẹ hơn.
- **PHP CLI có sẵn**: chạy `php -S localhost:8000` trong thư mục dự án (cần cài MySQL riêng).

## 2. Đặt project vào đúng thư mục

Với XAMPP: copy toàn bộ thư mục dự án vào `C:\xampp\htdocs\sieu-nhi-atgt-ai\` (Windows) hoặc `/Applications/XAMPP/htdocs/sieu-nhi-atgt-ai/` (Mac).

## 3. Tạo cơ sở dữ liệu

Mở **phpMyAdmin** (http://localhost/phpmyadmin) → tab **Import** → chọn file `schema.sql` → Go.
(File này sẽ tự tạo database tên **`duanmau_atgt`**.)

Nếu bạn đã có database cũ, chạy thêm `sql/migrate-learning.sql` trước khi dùng
AI Gia sư theo độ tuổi, mastery và hàng chờ cộng đồng.

Hoặc dùng dòng lệnh:
```bash
mysql -u root -p < schema.sql
```

## 4. Cấu hình kết nối bằng file `.env`

Từ bản này project **không hard-code mật khẩu DB trong `config.php` nữa** — tất cả thông tin nhạy cảm được đọc từ file `.env` (đã được `.gitignore`, không lên git).

### 4.1. Tạo file `.env` từ mẫu

Ở thư mục gốc dự án đã có sẵn `.env.example`. Chỉ cần copy ra file `.env`:

```bash
# macOS / Linux
cp .env.example .env

# Windows (PowerShell)
Copy-Item .env.example .env
```

### 4.2. Sửa giá trị trong `.env` cho khớp với MySQL của bạn

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=duanmau_atgt
DB_USER=root
DB_PASSWORD=           # XAMPP mặc định để trống
DB_CHARSET=utf8mb4

APP_ENV=local          # local | production
APP_DEBUG=true         # true = hiện lỗi PDO chi tiết, false = chỉ trả 500

GEMINI_API_KEY=        # tùy chọn; để trống sẽ dùng kho kiến thức offline
GEMINI_MODEL=gemini-2.5-flash
OPENAI_API_KEY=        # chỉ dùng cho AI Camera Vision
AI_RATE_LIMIT=30
```

> ⚠️ **Lên production nhớ:**
> - Đổi `DB_PASSWORD` sang mật khẩu thật
> - Đặt `APP_ENV=production` và `APP_DEBUG=false`
> - Không bao giờ commit file `.env` lên git (đã được `.gitignore` sẵn)

### 4.3. Cách hoạt động bên trong

- `env_loader.php` đọc `.env` → đưa vào `getenv()` / `$_ENV`.
- `config.php` gọi `env('DB_HOST', 'localhost')`... rồi `define()` ra các hằng số cũ (`HOST`, `USERNAME`, `DATABASE`, `PASSWORD`, `DB_PORT`, `DB_CHARSET`, `APP_ENV`, `APP_DEBUG`) để `database.php` và code cũ vẫn chạy được — **không cần sửa gì thêm**.
- Nếu biến thật của hệ điều hành (real environment variable) đã set thì loader **không ghi đè** — tiện cho hosting hoặc Docker.

## 5. Tạo tài khoản demo (tuỳ chọn nhưng khuyến khích)

Mở trình duyệt: `http://localhost/Sieunhi_atgt/seed.php`
(nếu bạn để thư mục dự án tên khác thì đổi lại đoạn `Sieunhi_atgt` cho đúng)

Script sẽ tự tạo 4 tài khoản (mật khẩu đều là `123456`):

| Vai trò    | Email               |
|------------|----------------------|
| Học sinh   | hocsinh@demo.com     |
| Phụ huynh  | phuhuynh@demo.com    |
| Giáo viên  | giaovien@demo.com    |
| Admin      | admin@demo.com        |

**Sau khi chạy xong, hãy xoá file `seed.php`** để không ai chạy lại được.

## 6. Truy cập site

Mở: `http://localhost/Sieunhi_atgt/` (Apache sẽ tự phục vụ `index.php`)
hoặc `http://localhost/Sieunhi_atgt/index.php`.

---

## Cấu trúc thư mục

```
.env                        → Cấu hình thật của MÁY BẠN (git ignore, KHÔNG commit)
.env.example                → Mẫu để người khác copy ra .env
.gitignore                  → Loại .env, .DS_Store, log... khỏi git

env_loader.php              → Loader tự viết, đọc .env → getenv()/$_ENV,
                               kèm helper env('KEY', $default) coerce bool/null
config.php                  → Nạp .env rồi define() ra các hằng số cũ
                               (HOST, USERNAME, DATABASE, PASSWORD, DB_PORT,
                                DB_CHARSET, APP_ENV, APP_DEBUG) — tương thích
                               ngược với code cũ, không ai phải sửa
database.php                → Lớp Database — PDO singleton, DSN kèm port +
                               charset, EMULATE_PREPARES=false, không lộ
                               password khi lỗi (chỉ throw khi APP_DEBUG=true)
db_utils.php                → Lớp DB_UTILS — các hàm getAll/getOne/execute/
                               getValue/getLastInsertId/transaction dùng chung
schema.sql                  → Cấu trúc database "duanmau_atgt" (import 1 lần)
seed.php                    → Tạo tài khoản demo (chạy 1 lần rồi xoá)
logout.php                  → Đăng xuất

includes/
  auth.php                  → Xác thực & phân quyền, dùng DB_UTILS bên trong
                               (attemptLogin, attemptRegister, requireRole,
                               currentUser, isLoggedIn, e()...)

index.php       → Trang chủ (hiện trạng thái đăng nhập trên navbar)
dang-nhap.php              → Đăng nhập thật (POST → kiểm tra DB → tạo session)
dang-ky.php                → Đăng ký thật (POST → lưu vào DB, mật khẩu hash)

dashboard-hoc-sinh.php     → Yêu cầu vai trò: hocsinh (hoặc admin)
dashboard-phu-huynh.php    → Yêu cầu vai trò: phuhuynh (hoặc admin)
dashboard-giao-vien.php    → Yêu cầu vai trò: giaovien (hoặc admin)
dashboard-admin.php        → Yêu cầu vai trò: admin

ai-gia-su.php, ai-camera.php, ai-mo-phong.php,
ai-truyen-tranh.php, game-mini.php
                            → Các trang tính năng demo, KHÔNG yêu cầu đăng
                              nhập (thiết kế mở để giới thiệu sản phẩm)

assets/css/style.css        → Design token dùng chung + Landing Page
assets/css/shared-pages.css → Style cho toàn bộ trang con
assets/js/main.js           → Theme toggle, counter, ripple... dùng chung
assets/js/*.js               → Logic riêng từng trang (chat, camera, game...)
```

## Cách hoạt động của phân quyền

Mỗi file dashboard bắt đầu bằng:
```php
<?php
require_once __DIR__ . '/includes/auth.php';
requireRole(['hocsinh', 'admin']);   // chỉ học sinh hoặc admin được vào
$user = currentUser();
?>
```
Nếu chưa đăng nhập → tự động chuyển tới `dang-nhap.php`.
Nếu đăng nhập rồi nhưng sai vai trò → tự động chuyển tới đúng dashboard của họ.

## Các luồng đã có trong bản hiện tại

- Dashboard học sinh đọc XP, mastery, bài học theo nhóm tuổi từ MySQL qua `DB_UTILS`, ví dụ:
  ```php
  require_once __DIR__ . '/db_utils.php';
  $db = new DB_UTILS();
  $progress = $db->getOne('SELECT * FROM student_progress WHERE student_id = ?', [$user['id']]);
  ```
- Thêm chức năng "Quên mật khẩu" (gửi email reset — điền `SMTP_USER` / `SMTP_PASSWORD` trong `.env`, chúng sẽ tự vào hằng số `USERNAME_EMAIL` / `PASSWORD_EMAIL`).
- AI Gia sư dùng CSRF token, rate limit theo session và không lưu lịch sử khách dùng thử.
- `community.php` nhận đóng góp từ phụ huynh/giáo viên; `community-admin.php` duyệt trước khi xuất bản.
- Triển khai lên hosting thật:
  - Copy `.env.example` → `.env` trên server, điền `DB_PASSWORD` mới.
  - Đặt `APP_ENV=production`, `APP_DEBUG=false` trong `.env`.
  - Bật HTTPS, xoá `seed.php`, và bảo đảm thư mục dự án **không** cho phép truy cập trực tiếp file `.env` (Apache mặc định đã chặn dotfile, nhưng nên kiểm tra lại).

## AI Camera — Detect mũ bảo hiểm thật

Từ bản này chức năng AI Camera đã hỗ trợ chạy **model AI thật** trong browser (Roboflow inferencejs, không upload ảnh ra server). Mặc định **tắt** — trang vẫn chạy mock cũ cho tới khi bạn bật.

### Bật chế độ thật

1. Đăng ký free account tại https://roboflow.com, tìm model "helmet detection" trên **Roboflow Universe** (hoặc train riêng, cần bản trả phí).
2. Copy `Publishable Key` từ trang Settings và model slug (dạng `<workspace-project>/<version>`, vd `helmet-detection-abcxyz/3`).
3. Điền vào `.env`:
   ```env
   AI_CAMERA_ENABLED=true
   ROBOFLOW_PUBLISHABLE_KEY=rf_xxxxxxxxxxxxxxxx
   ROBOFLOW_MODEL=helmet-detection-abcxyz/3
   ```
4. Mở `http://localhost/Sieunhi_atgt/ai-camera.php`. Nhãn dưới nút camera phải hiện `● AI thật` (xanh). Bấm nút 📸 → cho phép camera → model detect thật.

### Chế độ mock (mặc định)

Nếu bất kỳ điều nào sau đây đúng, trang giữ nguyên hành vi mock cũ (random 94–98%) và **không** gọi Roboflow / không xin quyền camera:
- `AI_CAMERA_ENABLED=false`
- `ROBOFLOW_PUBLISHABLE_KEY` trống
- `ROBOFLOW_MODEL` trống
- SDK jsDelivr load lỗi (offline hoặc bị firewall chặn)
- Model không tồn tại / key hết hạn (fallback tại thời điểm click camera)

Trường hợp fallback vì lý do kỹ thuật, banner nhỏ ở khung camera sẽ giải thích.

### Privacy — quan trọng

Từ bản pivot Hosted API, luồng dữ liệu như sau:

- Browser chụp frame từ webcam → encode base64 JPEG (chất lượng 60%)
- **POST lên `detect.roboflow.com`** (Roboflow servers) để inference
- Roboflow trả về JSON `predictions` (class + confidence + bbox)
- Ảnh KHÔNG được lưu trên server PHP của bạn (không có endpoint upload)
- Roboflow claim **không lưu ảnh inference** ([Privacy Policy](https://roboflow.com/privacy)) — nhưng ảnh **có** đi qua họ

Nghĩa là **NẾU trường học của bạn có quy định bảo mật nghiêm ngặt về hình ảnh trẻ em, cần thông báo phụ huynh** hoặc `AI_CAMERA_ENABLED=false` để giữ mock.

Lý do bắt buộc phải qua server Roboflow: đa số public helmet-detection model trên Roboflow Universe **KHÔNG** được owner export cho browser (chỉ Hosted API). Nếu muốn 100% offline, phải tự train qua Teachable Machine — kế hoạch dự phòng, mất ~1 ngày thêm.

### Ghi nhớ khác

- Publishable key của Roboflow **an toàn để lộ** ở client (khác với secret key). Việc để trong `.env` là để dễ đổi + không commit lên git.
- Card "Biển báo khu vực trường học" và "Vị trí đứng" gắn nhãn `Demo` — chỉ mũ bảo hiểm là detect thật.
- Preflight probe chạy khi load trang: nếu key/model sai → banner cảnh báo NGAY (không cần chờ click camera). Rơi về mock để trang không bể.
