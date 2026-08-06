# Thiết kế: Giao diện trẻ em + Chatbot mới + Module Khám phá Đắk Lắk

**Ngày:** 2026-08-06
**Trạng thái:** Đã duyệt qua brainstorming, chờ user review file spec
**Mục tiêu:** Demo/thi — gây ấn tượng với giám khảo

## 1. Bối cảnh & vấn đề

Phản hồi nhận được về Siêu Nhí ATGT:

- Ý tưởng tốt nhưng ứng dụng khó dùng, thiếu bắt mắt với trẻ em; cần nhiều hình ảnh phù hợp lứa tuổi.
- Chatbot AI cần bố cục đẹp hơn; trả lời nên kèm hình minh hoạ.
- Gợi ý chỉ hiển thị chung chung, chưa tuỳ chỉnh theo người dùng.
- Thiếu phần giới thiệu địa điểm đầy đủ; AI đề xuất lịch trình chưa tối ưu.
- Dữ liệu nên tự xây hoặc lấy từ cộng đồng (người dùng upload review thực tế).

Quyết định phạm vi: gộp yếu tố "khám phá địa điểm/lịch trình" vào Siêu Nhí ATGT theo hướng **du lịch giáo dục thực tế**, dữ liệu địa điểm quanh một trường học tại **Đắk Lắk (Buôn Ma Thuột)**. Mốc tính khoảng cách: một trường tiểu học khu trung tâm Buôn Ma Thuột (mặc định lấy khu vực Ngã Sáu làm tâm; tên trường có thể thay khi seed dữ liệu mà không ảnh hưởng thiết kế). Đối tượng: học sinh tiểu học và THCS.

## 2. Phương án được chọn

**Phương án A — "Khoác áo mới + module mới":** giữ nguyên kiến trúc PHP + MySQL + session auth + phân quyền 4 vai trò + `ai-engine.php` (Gemini/offline). Viết lại design system CSS dùng chung, xây lại trang chatbot, thêm module Khám phá. Không thêm framework, không build step — chạy thẳng trên XAMPP.

Phương án bị loại: (B) làm lại toàn bộ frontend — khối lượng quá lớn, rủi ro vỡ tiến độ; (C) chỉ đánh bóng trang demo — lộ chắp vá khi giám khảo bấm trang khác.

## 3. Kiến trúc & trang

### Trang mới

| File | Vai trò |
|---|---|
| `kham-pha.php` | Danh sách địa điểm quanh trường — bản đồ SVG cách điệu + lưới thẻ sticker, lọc theo loại |
| `dia-diem.php?id=` | Chi tiết địa điểm: giới thiệu đầy đủ, khối "Đường đến an toàn", review đã duyệt |
| `lich-trinh-ai.php` | AI lập lịch trình 3 bước, kết quả dạng vé hành trình, lưu và in được |
| `review-submit.php` | API nhận review + ảnh, lưu trạng thái `pending` |

### Trang sửa

- `ai-gia-su.php` — xây lại toàn bộ giao diện chat (mục 5).
- `dashboard-giao-vien.php`, `dashboard-admin.php` — thêm tab "Duyệt review".
- Mọi trang còn lại hưởng giao diện mới qua CSS chung.

### Bảng dữ liệu mới

| Bảng | Nội dung chính |
|---|---|
| `places` | Tên, loại (bảo tàng/công viên/vui chơi/thiên nhiên), mô tả kể chuyện, giờ mở cửa, giá vé, khoảng cách từ trường, độ tuổi phù hợp, ghi chú ATGT tuyến đường, mã minh hoạ SVG |
| `place_reviews` | user_id, place_id, sao (1-5), lời kể, ảnh (tối đa 3), trạng thái `pending/approved/rejected`, người duyệt, lý do từ chối |
| `user_preferences` | user_id, khối lớp, chủ đề ATGT yêu thích, loại địa điểm yêu thích |
| `ai_itineraries` | user_id, tham số đầu vào (thời gian/phương tiện/sở thích), JSON hành trình, thời điểm tạo |

Seed ~15 địa điểm thật quanh Buôn Ma Thuột (Bảo tàng Đắk Lắk, Ngã Sáu Ban Mê, Tượng đài chiến thắng, công viên nước, hồ Ea Kao, chùa Sắc Tứ Khải Đoan...), mỗi địa điểm có mô tả đầy đủ + ghi chú ATGT.

### Luồng dữ liệu

Trang PHP render server-side như hiện tại. Tương tác AI (chat, lịch trình) gọi endpoint PHP qua `fetch` → `ai-engine` → Gemini hoặc offline fallback → JSON về client. Ảnh review upload vào `uploads/reviews/`.

## 4. Design system "Hoạt hình rực rỡ"

Viết lại `assets/css/style.css` thành bộ token dùng chung, `shared-pages.css` kế thừa.

- **Màu:** nền kem sáng `#FFF8EC`, chữ nâu đậm; 4 màu chủ đạo từ thế giới giao thông — vàng nắng, xanh da trời, đỏ tươi, xanh lá — dùng **theo ngữ nghĩa** (xanh lá = an toàn/đúng, đỏ = nguy hiểm/sai, vàng = chú ý) để bé học quy ước màu giao thông qua chính UI.
- **Chữ:** font tròn trịa hỗ trợ tiếng Việt (Baloo 2 hoặc Quicksand), self-host để demo offline; tiêu đề rất to, chữ thân ≥17px.
- **Thành phần:** nút bầu dục cao ≥48px, hiệu ứng nhún khi bấm; thẻ sticker bo góc lớn, viền dày màu, bóng mềm, nghiêng nhẹ xen kẽ; huy hiệu, thanh XP đồng bộ ngôn ngữ.
- **Mascot:** nâng cấp `mascot.js` — Siêu Nhí SVG có trạng thái (chào, ăn mừng, lo lắng, chỉ dẫn), xuất hiện ở landing, chat, kết quả game.
- **Minh hoạ:** toàn bộ SVG vẽ trong code (biển báo VN chuẩn, cảnh đường phố, đèn tín hiệu, minh hoạ từng loại địa điểm) + emoji làm icon phụ — không phụ thuộc mạng, không vấn đề bản quyền.
- **Chuyển động:** chỉ `transform`/`opacity`; tôn trọng `prefers-reduced-motion`.

## 5. Chatbot AI Gia sư mới + cá nhân hoá

- **Bố cục:** khung chat trọn màn hình kiểu app nhắn tin; mascot làm avatar AI; bong bóng bo tròn hai màu; ô nhập to, nút gửi tròn nổi bật.
- **Chip gợi ý** cuộn ngang phía trên ô nhập — bé bấm là hỏi luôn, không cần gõ.
- **Trả lời kèm hình:** thư viện SVG gắn thẻ chủ đề (biển báo, mũ bảo hiểm, qua đường, đèn tín hiệu...). Gemini được prompt trả thêm topic tag; chế độ offline tra từ khoá nên có sẵn topic. Client hiện bong bóng kèm hình đúng chủ đề — hoạt động cả khi offline.
- **Cá nhân hoá:**
  - Onboarding lần đầu: hỏi khối lớp + chọn sticker chủ đề/loại địa điểm yêu thích → `user_preferences`.
  - Giọng điệu theo khối lớp: tiểu học xưng "con", câu ngắn nhiều emoji; THCS xưng "bạn", nội dung sâu hơn (đưa vào system prompt).
  - Chip gợi ý sinh từ: sở thích + bài hay sai (`student_progress`, `game_sessions`) + chủ đề đã hỏi (`ai_chat_messages`).
  - Khách chưa đăng nhập vẫn chat được, thấy lời mời đăng nhập để được cá nhân hoá.

## 6. Module Khám phá + AI lịch trình

- **`kham-pha.php`:** bản đồ SVG cách điệu Buôn Ma Thuột (không dùng Google Maps — offline, đồng bộ phong cách), địa điểm là nút sticker trên bản đồ; dưới là lưới thẻ lọc theo loại; mỗi thẻ có minh hoạ, tên, khoảng cách từ trường, nhãn độ tuổi.
- **`dia-diem.php`:** giới thiệu giọng kể chuyện, giờ mở cửa, giá vé; khối **"Đường đến an toàn"** — tuyến từ trường + điểm cần chú ý kèm biển báo minh hoạ (mắt xích gắn du lịch với ATGT); review cộng đồng đã duyệt ở cuối.
- **`lich-trinh-ai.php`:** 3 bước chọn sticker (đi khi nào / bằng gì / thích gì — sở thích lấy sẵn từ hồ sơ) → AI trả hành trình theo dòng thời gian: mỗi chặng có địa điểm, giờ gợi ý, hoạt động, lời dặn an toàn. Kết quả dạng vé hành trình, lưu `ai_itineraries`, in được.
- **Chống "AI bịa":** AI chỉ chọn từ `places` thật kèm ràng buộc (giờ mở cửa, khoảng cách, phương tiện); Gemini chỉ sắp xếp + viết lời dặn. Offline fallback: sắp theo khoảng cách gần → xa. Không bao giờ trắng trang.

## 7. Review cộng đồng + kiểm duyệt

- **Gửi:** người dùng đăng nhập (mọi vai trò) → form ở trang địa điểm: chấm 1-5 mặt cười, lời kể, tối đa 3 ảnh → trạng thái `pending`; người gửi thấy nhãn "đang chờ cô duyệt 🕐".
- **Duyệt:** tab "Duyệt review" ở dashboard giáo viên/admin — xem đủ nội dung + ảnh, Duyệt ✅ / Từ chối ❌ (kèm lý do). Duyệt xong hiện công khai, người gửi được cộng XP.
- **Upload an toàn:** chỉ JPEG/PNG/WebP ≤5MB, kiểm tra MIME thật phía server, đổi tên file ngẫu nhiên, lưu ngoài tầm thực thi PHP, CSRF token, giới hạn 5 review/người/ngày.

## 8. Xử lý lỗi

- Mọi lời gọi AI có fallback offline (chat → kho kiến thức; lịch trình → thuật toán khoảng cách) + nhãn nhỏ cho biết chế độ đang chạy.
- Lỗi upload/DB: thông báo thân thiện giọng mascot; log chi tiết server-side khi `APP_DEBUG=true`.

## 9. Kiểm thử

- Kịch bản demo end-to-end với 4 tài khoản seed: bé đăng review → cô duyệt → hiện công khai; chat có/không API key; lịch trình có/không mạng.
- Responsive: 320 / 768 / 1024 / 1440.
- `prefers-reduced-motion` và tương phản màu chữ trên nền kem.

## 10. Ngoài phạm vi (YAGNI)

- Google Maps / bản đồ thật, định vị GPS.
- AI tự kiểm duyệt review (chọn duyệt tay bởi GV/Admin).
- Ứng dụng mobile, PWA, thông báo đẩy.
- Quên mật khẩu qua email, các hạng mục production khác trong README.
