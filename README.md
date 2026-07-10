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

Hoặc dùng dòng lệnh:
```bash
mysql -u root -p < schema.sql
```

## 4. Cấu hình kết nối

Mở `config.php`, kiểm tra/sửa lại cho khớp với MySQL của bạn:
```php
const HOST = 'localhost';
const USERNAME = 'root';
const DATABASE = 'duanmau_atgt';
const PASSWORD = '';   // XAMPP mặc định thường để trống
```

## 5. Tạo tài khoản demo (tuỳ chọn nhưng khuyến khích)

Mở trình duyệt: `http://localhost/sieu-nhi-atgt-ai/seed.php`

Script sẽ tự tạo 4 tài khoản (mật khẩu đều là `123456`):

| Vai trò    | Email               |
|------------|----------------------|
| Học sinh   | hocsinh@demo.com     |
| Phụ huynh  | phuhuynh@demo.com    |
| Giáo viên  | giaovien@demo.com    |
| Admin      | admin@demo.com        |

**Sau khi chạy xong, hãy xoá file `seed.php`** để không ai chạy lại được.

## 6. Truy cập site

Mở: `http://localhost/sieu-nhi-atgt-ai/sieu-nhi-atgt-ai.php`

---

## Cấu trúc thư mục

```
config.php                → Hằng số kết nối MySQL (HOST, USERNAME, DATABASE, PASSWORD)
database.php               → Lớp Database — tạo kết nối PDO từ config.php
db_utils.php                → Lớp DB_UTILS — các hàm getAll/getOne/execute/
                               getValue/getLastInsertId/transaction dùng chung
schema.sql                 → Cấu trúc database "duanmau_atgt" (import 1 lần)
seed.php                    → Tạo tài khoản demo (chạy 1 lần rồi xoá)
logout.php                  → Đăng xuất

includes/
  auth.php                  → Xác thực & phân quyền, dùng DB_UTILS bên trong
                               (attemptLogin, attemptRegister, requireRole,
                               currentUser, isLoggedIn, e()...)

sieu-nhi-atgt-ai.php       → Trang chủ (hiện trạng thái đăng nhập trên navbar)
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

## Việc tiếp theo bạn có thể làm

- Thay dữ liệu tĩnh trong các dashboard (XP, tiến độ, danh sách học sinh...) bằng truy vấn MySQL thật qua `DB_UTILS`, ví dụ:
  ```php
  require_once __DIR__ . '/db_utils.php';
  $db = new DB_UTILS();
  $progress = $db->getOne('SELECT * FROM student_progress WHERE student_id = ?', [$user['id']]);
  ```
- Thêm chức năng "Quên mật khẩu" (gửi email reset — cần cấu hình SMTP, đã có sẵn 2 hằng số email bị comment trong `config.php`).
- Thêm CSRF token cho các form (bảo mật nâng cao hơn).
- Triển khai lên hosting thật (nhớ đổi `PASSWORD` trong `config.php`, bật HTTPS, và xoá `seed.php`).
