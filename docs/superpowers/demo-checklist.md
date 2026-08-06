# Kịch bản demo 5 phút — Siêu Nhí An Toàn Giao Thông AI

Kịch bản trình diễn nhanh (khoảng 5 phút, 5 cảnh) cho bản redesign thân thiện trẻ em. Dùng khi demo trực tiếp trên máy chạy XAMPP tại `http://localhost/Sieunhi_atgt/`.

## Tài khoản demo

Mật khẩu chung cho cả 4 tài khoản: **`123456`**

| Vai trò | Email | Dùng cho cảnh |
|---|---|---|
| Học sinh | `hocsinh@demo.com` (Bé Minh An) | Cảnh 1, 2, 3, 4, 5 |
| Phụ huynh | `phuhuynh@demo.com` (Anh Tuấn Nguyễn) | (dự phòng, không dùng trong 5 cảnh chính) |
| Giáo viên | `giaovien@demo.com` (Cô Lan Anh) | Cảnh 5 (duyệt review) |
| Admin | `admin@demo.com` | (dự phòng) |

## Chuẩn bị trước khi demo

- [ ] Bật XAMPP (Apache + MySQL), xác nhận `http://localhost/Sieunhi_atgt/` mở được.
- [ ] Xoá `localStorage` trình duyệt (hoặc dùng cửa sổ ẩn danh mới) để mascot chào onboarding hiển thị đúng như lần đầu.
- [ ] Đăng xuất mọi tài khoản trước khi bắt đầu Cảnh 1 (trang chủ ở trạng thái khách).
- [ ] **Kiểm tra biến môi trường `GEMINI_API_KEY` trong `.env`:**
  - **Nếu CÓ đặt key hợp lệ:** AI Gia sư trả lời bằng Gemini thật, badge hiển thị `● AI thật` (xanh lá). Cảnh 2 sẽ có câu trả lời phong phú, tự nhiên hơn.
  - **Nếu KHÔNG đặt key (mặc định trong môi trường dev này):** Hệ thống tự động rơi về chế độ offline (rule-based), badge hiển thị `● Chế độ offline` (vàng). Câu trả lời vẫn đúng nội dung an toàn giao thông và vẫn kèm hình minh hoạ, chỉ ít linh hoạt hơn. **Đây là trạng thái mặc định — không cần xin lỗi khi demo, cứ giới thiệu tự nhiên là "bản offline vẫn hoạt động đầy đủ".**
  - Có thể kiểm tra nhanh bằng cách mở `ai-gia-su.php`, gửi 1 câu hỏi, xem badge góc trên bên trái đầu trang.
- [ ] Xác nhận địa điểm mẫu `bao-tang-dak-lak` (Bảo tàng Đắk Lắk) đã có dữ liệu (chạy `tests/test-seed-places.php` nếu nghi ngờ).
- [ ] Nếu đã demo trước đó, dọn các review "pending" thử nghiệm dư thừa trong bảng `place_reviews` để hàng chờ duyệt của giáo viên gọn gàng.

---

## Cảnh 1 — Landing mới + mascot chào (≈45 giây)

1. Mở `index.php` ở trạng thái khách (chưa đăng nhập).
2. Chỉ vào mascot chào ở đầu trang, phần hero với "Chọn nhóm tuổi" (6–8 / 9–11).
3. Bấm thử một nhóm tuổi — nhấn mạnh nút bấm to, dễ chạm (đã chỉnh về chuẩn 44px), trạng thái `.selected` được lưu lại (localStorage) cho lần sau.
4. Cuộn nhanh qua các khối tính năng (AI Gia sư, Khám phá, Lịch trình AI) để cho thấy phong cách hoạt hình rực rỡ, không phải giao diện mặc định.

## Cảnh 2 — Chat AI Gia sư hỏi 2 câu có hình + chip cá nhân hoá (≈90 giây)

1. Đăng nhập bằng `hocsinh@demo.com` / `123456`.
2. Vào `ai-gia-su.php`. Nếu là lần đầu đăng nhập của tài khoản này trên máy demo, màn hình onboarding 2 bước (chọn khối lớp → chọn chủ đề/sticker yêu thích) sẽ hiện ra — hoàn thành nhanh để cá nhân hoá gợi ý.
3. Hỏi câu 1, ví dụ: **"Đèn vàng thì con phải làm gì?"** — chỉ ra câu trả lời kèm hình minh hoạ SVG và badge chế độ (AI thật/offline).
4. Hỏi câu 2, ví dụ: **"Đội mũ bảo hiểm đúng cách?"** — chỉ vào dải chip câu hỏi gợi ý phía dưới khung chat, đã được cá nhân hoá theo sở thích vừa chọn ở bước onboarding.
5. (Tuỳ chọn, nếu màn hình đủ rộng) Bấm nút ☰ để mở sidebar lịch sử trò chuyện, cho thấy các phiên chat trước đó.

## Cảnh 3 — Khám phá → Địa điểm → Đường đến an toàn (≈75 giây)

1. Vào `kham-pha.php`. Chỉ vào bản đồ sticker Buôn Ma Thuột và dải chip lọc theo loại địa điểm.
2. Bấm chip lọc (ví dụ "Bảo tàng & di tích") để cho thấy danh sách cập nhật ngay.
3. Mở chi tiết địa điểm **Bảo tàng Đắk Lắk** (`dia-diem.php?slug=bao-tang-dak-lak`).
4. Cuộn tới khối **"Đường đến an toàn"** (viền vàng cảnh báo) — đọc to đoạn hướng dẫn đi bộ an toàn, chỉ vào minh hoạ vạch qua đường.
5. Lướt nhanh khối review của bạn khác đã kể (nếu có) để dẫn vào Cảnh 5.

## Cảnh 4 — Lên lịch trình AI + in vé (≈60 giây)

1. Vào `lich-trinh-ai.php`.
2. Trả lời 3 câu hỏi nhanh: đi khi nào (VD: Cả ngày), đi bằng gì (VD: Xe đạp), thích gì (VD: Công viên).
3. Bấm **"Lên lịch thôi! 🚀"** — chỉ vào vé hành trình vừa sinh ra: mốc giờ, tên địa điểm, mô tả, và đặc biệt là khối cảnh báo an toàn màu vàng cho từng điểm dừng.
4. Bấm **"🖨️ In vé"** để mở hộp thoại in — nhấn mạnh khi in, chỉ có vé được in ra (phần điều hướng/wizard tự ẩn nhờ CSS `@media print`).

## Cảnh 5 — Học sinh gửi review → Giáo viên duyệt → Hiện công khai + XP (≈70 giây)

1. Vẫn ở tài khoản `hocsinh@demo.com`, quay lại `dia-diem.php?slug=bao-tang-dak-lak`.
2. Cuộn tới khối **"Kể chuyện của con"**: chọn 1 mặt cười (số sao), gõ vài dòng cảm nhận, có thể đính kèm ảnh.
3. Bấm **"Gửi cho Siêu Nhí 🚀"** — chỉ vào thông báo "Cảm ơn con! Bài kể đang chờ cô duyệt 🕐".
4. Đăng xuất, đăng nhập lại bằng `giaovien@demo.com` / `123456`.
5. Vào `dashboard-giao-vien.php`, cuộn tới mục **"Duyệt review"** (có badge đỏ đếm số bài chờ duyệt) — bấm duyệt bài review vừa gửi.
6. Quay lại `dia-diem.php?slug=bao-tang-dak-lak` (có thể mở tab ẩn danh hoặc đăng xuất) để cho thấy review vừa duyệt đã hiển thị công khai trong khối "Bạn nhỏ đã kể gì?".
7. (Nếu có UI hiển thị XP) Đăng nhập lại bằng `hocsinh@demo.com` để cho thấy điểm XP/thưởng đã cộng cho học sinh sau khi review được duyệt.

---

## Ghi chú kỹ thuật khi demo

- Toàn bộ hoạt ảnh (nhún/nghiêng mascot, hiệu ứng chip) chỉ dùng `transform`/`opacity` — nếu máy demo bật "Reduce Motion" (System Settings → Accessibility), các hiệu ứng sẽ tự tắt mà không ảnh hưởng chức năng.
- Nếu mạng/API AI chậm, chế độ offline vẫn đảm bảo demo mượt — không cần internet để chạy toàn bộ 5 cảnh.
- Trang `dashboard-giao-vien.php` yêu cầu đăng nhập vai trò `giaovien` hoặc `admin` — dùng đúng tài khoản `giaovien@demo.com` ở Cảnh 5.
