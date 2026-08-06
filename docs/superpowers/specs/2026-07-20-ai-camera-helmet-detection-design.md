# AI Camera — Helmet Detection (Real)

- Trạng thái: **Design approved, chưa implement**
- Ngày: 2026-07-20
- Chủ sở hữu: quangnvaws@gmail.com
- Phạm vi: `ai-camera.php`, `assets/js/ai-camera.js`, `.env`, `.env.example`, `config.php`
- Không đụng vào: các trang tính năng khác, database schema, luồng auth

## 1. Vấn đề

Trang `ai-camera.php` hiện là **mock** hoàn toàn:

- Không gọi `getUserMedia()` — chưa mở camera bao giờ.
- `assets/js/ai-camera.js` (26 dòng) chỉ pick random `[94,95,96,97,98]` mỗi lần bấm "Quét lại".
- Các badge `Mũ bảo hiểm 98%` / `Biển báo 95%` là HTML hardcode.

Mục tiêu: biến chức năng **detect mũ bảo hiểm** thành thật, chạy hoàn toàn trong trình duyệt, không upload ảnh trẻ em ra ngoài. Hai detection còn lại (biển báo + vị trí đứng) giữ mock nhưng gắn nhãn `Demo` rõ ràng.

## 2. Ràng buộc

- **Privacy**: ảnh trẻ em KHÔNG được rời khỏi máy user. Inference client-side bắt buộc.
- **Offline-friendly**: sau lần load model đầu, phải chạy được khi mất mạng.
- **Không thêm hạ tầng**: không thêm Python microservice, không dựng server ML. Vẫn chỉ PHP + JS + XAMPP.
- **Fallback an toàn**: nếu model không load được / key sai / admin tắt flag → không được để trang trắng, phải rớt về mock cũ + banner giải thích.
- **HTTPS-aware**: Chrome chặn `getUserMedia` trên `http://` (trừ `localhost`). Trang phải nhận biết và hướng dẫn user rõ ràng.

## 3. Kiến trúc

### 3.1. Cấu hình qua `.env`

Thêm 3 biến mới:

```env
# --- AI Camera ---
AI_CAMERA_ENABLED=false                       # true = ban real detection, false = mock cu
ROBOFLOW_PUBLISHABLE_KEY=                     # publishable key tu roboflow.com, an toan lo o client
ROBOFLOW_MODEL=                               # slug + version, vd: helmet-detection-abcxyz/3
```

Mặc định `false` trong `.env.example` — ai clone repo về chạy sẽ thấy mock, không lỗi Roboflow ngay từ đầu.

### 3.2. Cầu nối PHP → JS trong `config.php`

```php
defined('AI_CAMERA_ENABLED') || define('AI_CAMERA_ENABLED', (bool) env('AI_CAMERA_ENABLED', false));
defined('ROBOFLOW_KEY')      || define('ROBOFLOW_KEY',      env('ROBOFLOW_PUBLISHABLE_KEY', ''));
defined('ROBOFLOW_MODEL')    || define('ROBOFLOW_MODEL',    env('ROBOFLOW_MODEL', ''));
```

### 3.3. Server tự động vô hiệu hoá khi thiếu key

Trong `ai-camera.php`:

```php
$aiEnabled = AI_CAMERA_ENABLED && ROBOFLOW_KEY !== '' && ROBOFLOW_MODEL !== '';
?>
<script>
  window.__AI_CAMERA__ = {
    enabled: <?= $aiEnabled ? 'true' : 'false' ?>,
    key:     "<?= $aiEnabled ? e(ROBOFLOW_KEY)   : '' ?>",
    model:   "<?= $aiEnabled ? e(ROBOFLOW_MODEL) : '' ?>"
  };
</script>
```

Điều này ngăn user viết `.env` bật flag mà quên key.

### 3.4. JavaScript — cấu trúc module

Rewrite `assets/js/ai-camera.js` từ 26 dòng mock → ~180 dòng thật, chia rõ trách nhiệm:

```
HelmetDetector
  - Wrap Roboflow inferencejs.
  - Load model 1 lan, cache trong memory.
  - Method: detect(mediaElement) -> [{class, confidence, bbox}]

CameraStream
  - Wrap getUserMedia({ video: { facingMode: 'user', width: 1280 } }).
  - Handle permission denied, no-camera, HTTPS block.
  - Method: start(), stop().

FrameLoop
  - requestAnimationFrame throttled ~2 fps (500ms).
  - Goi HelmetDetector.detect() moi vong.
  - Method: start(cb), stop().

ResultRenderer
  - Ve bounding-box + label len canvas overlay.
  - Update panel phai (3 card + "Loi khuyen tu AI").
  - Update badge do chinh xac tren goc phai canvas.

ScanHistory
  - Lu 5 lan detect gan nhat vao localStorage.
  - Render "LICH SU QUET GAN DAY" strip duoi.
  - KHONG len server — privacy tre em.

main()
  - Doc window.__AI_CAMERA__.
  - Neu !enabled -> runMockMode() (giu behavior cu).
  - Neu enabled  -> khoi tao 5 module tren, dang ky nut camera.
```

### 3.5. Flow tổng thể

```
.env
  AI_CAMERA_ENABLED=true
  ROBOFLOW_PUBLISHABLE_KEY=rf_xxx
  ROBOFLOW_MODEL=helmet-detection-abcxyz/3
        │
        ▼ (env_loader.php da co san)
config.php  ──►  define()
        │
        ▼
ai-camera.php  ──►  <script>window.__AI_CAMERA__ = {...}</script>
        │
        ▼
ai-camera.js
  ├── main() doc config
  ├── neu disabled -> runMockMode()
  └── neu enabled  -> HelmetDetector.load()
                       CameraStream.start()
                       FrameLoop.start(frame => {
                         const dets = HelmetDetector.detect(frame);
                         ResultRenderer.render(dets);
                         if (dets.length) ScanHistory.push(dets[0]);
                       })
```

## 4. UX — 2 tab hoạt động thật

| Tab | Nguồn frame | Tần suất inference | Kết quả |
|---|---|---|---|
| 📷 Camera trực tiếp | `getUserMedia()` live | ~2 fps (throttle rAF, 500ms) | Cập nhật liên tục |
| 🖼 Tải ảnh lên | User drop / chọn JPG/PNG | 1 lần / ảnh mới | Cố định |

### 4.1. Trạng thái UI (3 mức confidence)

| Confidence | Badge phải | Bounding-box | Card "Mũ bảo hiểm" | Lời khuyên |
|---|---|---|---|---|
| ≥ 0.60 | `✓ Độ chính xác NN%` (xanh) | Xanh lá | `Đạt chuẩn` | Khen + gợi ý đứng đúng vạch |
| 0.30 – 0.60 | `⚠ Chưa rõ NN%` (vàng) | Vàng | `Chưa rõ` | "Hãy đưa mũ vào giữa khung" |
| < 0.30 hoặc 0 detection | `⏸ Chưa thấy mũ` (xám) | Không vẽ | `Chưa thấy mũ bảo hiểm` | "Đội mũ hoặc đưa mũ vào khung camera nhé" |

### 4.2. Camera trực tiếp — chi tiết

1. Vào trang → thay `<div>` demo bằng `<video>` thật (chưa stream) + `<canvas>` overlay bbox.
   - Badge phải: `⏸ Chạm vào camera để bắt đầu`.
2. User bấm nút camera-with-flash (giữ icon cũ trong mock).
   - `getUserMedia({ video: { facingMode: 'user', width: 1280 } })`.
   - **Từ chối permission** → toast: _"Bạn chưa cho phép camera. Bấm vào biểu tượng ở thanh địa chỉ để bật lại."_ Nút chuyển sang `Thử lại` màu vàng.
   - **Chấp nhận** → video hiển thị live, badge chuyển `🔴 AI ĐANG PHÂN TÍCH`, FrameLoop bắt đầu.
3. FrameLoop chạy 2 fps, cập nhật ResultRenderer theo bảng 4.1.
4. User bấm nút camera lần 2 → `stream.getTracks().forEach(t => t.stop())`, về trạng thái ban đầu.
5. Nút "Quét lại" chỉ reset UI (không rerun model — nó đang chạy liên tục).

### 4.3. Tải ảnh lên — chi tiết

1. User drop file / bấm chọn → hiện preview `<img>`.
2. `HelmetDetector.detect(imgEl)` 1 lần.
3. Vẽ bbox lên canvas overlay theo tỉ lệ ảnh gốc.
4. Nút "Quét lại" xoá ảnh, quay lại trạng thái empty.

### 4.4. Edge case

- **HTTP không phải `localhost`** (browser chặn `getUserMedia`): toast _"Hãy mở qua `localhost` hoặc bật HTTPS để dùng camera."_
- **Model tải lỗi** (offline lần đầu, key sai, network timeout): tự chuyển mode demo cũ + banner nhỏ _"Đang chạy chế độ demo — chưa kết nối được model AI."_
- **Frame tối/mờ** (0 detection liên tục ≥ 5 giây): gợi ý _"Hãy đứng gần cửa sổ hoặc bật đèn."_
- **iOS Safari cũ không có `getUserMedia`**: hiện fallback message, ẩn nút camera, tab "Tải ảnh" vẫn dùng được (đã có sẵn ảnh tĩnh).

### 4.5. Hai card mock — gắn nhãn Demo

Cạnh title "Biển báo khu vực trường học" và "Vị trí đứng — Cần chú ý" thêm pill nhỏ `Demo` (xám, 10px, uppercase). Người dùng không bị nhầm là AI thật đang detect 3 thứ.

### 4.6. Nhãn dưới nút camera

`● AI thật` (xanh lá) khi `enabled=true`, `● Demo` (xám) khi false. Vị trí: ngay dưới hàng "Hỗ trợ JPG, PNG…".

## 5. Không làm (YAGNI)

- **Không lưu ảnh user lên server** — privacy trẻ em.
- **Không thêm nút chụp/lưu ảnh**.
- **Không confidence threshold slider** cho user chỉnh.
- **Không detect thật** cho biển báo và vị trí đứng (giữ mock, gắn nhãn `Demo`).
- **Không dùng backend PHP làm ML proxy** — publishable key an toàn để lộ, không cần giấu.
- **Không training pipeline** — dùng model có sẵn trên Roboflow Universe.

## 6. Rủi ro & giảm thiểu

| Rủi ro | Giảm thiểu |
|---|---|
| Roboflow đổi giá / khoá free tier | Publishable key dễ đổi qua `.env`. Nếu cần thoát khỏi Roboflow: giữ interface `HelmetDetector`, thay implementation bằng TF.js + Teachable Machine model. Không đụng UI. |
| Model 5–15 MB tải chậm trên mạng yếu | Roboflow SDK cache vào IndexedDB, lần 2 offline. Hiện progress bar khi load lần đầu. |
| Detect kém trên trẻ em Việt Nam (bias model) | Ghi rõ trong docs cho maintainer biết cần đánh giá model trước khi bật `AI_CAMERA_ENABLED=true` production. Nếu tệ → chuyển sang tự train Teachable Machine (đã có approach dự phòng). |
| Phụ huynh lo camera | UI nói rõ "Ảnh KHÔNG rời khỏi máy bạn". Server không có endpoint upload ảnh — không thể lưu kể cả muốn. |

## 7. Testing

- **Manual**: XAMPP local, `http://localhost/Sieunhi_atgt/ai-camera.php`
  - Trường hợp 1: `AI_CAMERA_ENABLED=false` → mock cũ chạy y hệt hôm nay.
  - Trường hợp 2: `true` + key rỗng → `$aiEnabled=false` server-side → mock cũ. Không lộ lỗi.
  - Trường hợp 3: `true` + key hợp lệ + model hợp lệ → real mode; test permission grant/deny, test tab upload, test bbox vẽ đúng tỉ lệ.
- **E2E (Playwright)**: mock `navigator.mediaDevices.getUserMedia` với video test, verify bbox xuất hiện + card phải update. Roboflow SDK có thể stub bằng fixture JSON.
- **Không có unit test cho HelmetDetector**: nó là wrapper mỏng quanh SDK bên ngoài — kiểm bằng E2E là đủ.

## 8. File cần đụng

- `.env` — thêm 3 biến (`AI_CAMERA_ENABLED`, `ROBOFLOW_PUBLISHABLE_KEY`, `ROBOFLOW_MODEL`).
- `.env.example` — thêm 3 biến như trên, `enabled=false` mặc định.
- `config.php` — 3 dòng `define()`.
- `ai-camera.php` — thêm khối `<script>window.__AI_CAMERA__ = ...</script>`, gắn nhãn Demo trên 2 card mock, thêm `<canvas>` overlay và nhãn AI thật/Demo.
- `assets/js/ai-camera.js` — rewrite hoàn toàn (26 dòng → ~180 dòng), 5 module + `main()`.

## 9. Không phải phạm vi lần này

- Detect biển báo giao thông thật.
- Detect vị trí đứng trên vạch qua đường.
- Train model tuỳ chỉnh (giữ như dự phòng trong mục 6).
- Lưu lịch sử scan lên DB.
- Chức năng chia sẻ kết quả ra social.
