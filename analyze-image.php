<?php
/* ============================================================
   API AI CAMERA — phân tích ảnh THẬT bằng OpenAI GPT-4o-mini Vision
   (Đổi từ Gemini sang OpenAI do tài khoản Google Gemini hiện chỉ cấp
   được key dạng "AQ." bị lỗi 401 ACCESS_TOKEN_TYPE_UNSUPPORTED — đây
   là sự cố đang xảy ra trên diện rộng phía Google, chưa có cách khắc
   phục từ phía người dùng, nên tạm chuyển sang OpenAI cho tính năng
   này để không bị gián đoạn.)

   Request:  POST { image: "data:image/jpeg;base64,...." }
   Response: { accuracy, items: [{icon,title,desc,severity}], advice }
             hoặc { error: "..." } nếu có lỗi.
   ============================================================ */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

/* ---------- CẤU HÌNH ----------
   Lấy API key tại: https://platform.openai.com/api-keys
   (đăng nhập/đăng ký OpenAI → "Create new secret key" → dán vào giữa 2 dấu nháy)
   LƯU Ý: khác với Gemini, OpenAI KHÔNG có gói dùng thử miễn phí vĩnh viễn —
   cần nạp một khoản nhỏ (vài USD) vào tài khoản OpenAI để API hoạt động. */
// OPENAI_API_KEY is loaded from .env by config.php.
define('OPENAI_MODEL', 'gpt-4o-mini'); // model rẻ, hỗ trợ nhìn ảnh (vision), đủ dùng cho tính năng này

function json_error(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$imageData = $input['image'] ?? '';

if (!$imageData || !preg_match('#^data:image/[a-zA-Z0-9+.-]+;base64,.+$#', $imageData)) {
    json_error('Ảnh không hợp lệ hoặc thiếu dữ liệu.');
}

// Chặn ảnh quá nặng trước khi gửi lên API (an toàn + tiết kiệm băng thông)
if (strlen($imageData) > 8 * 1024 * 1024) {
    json_error('Ảnh quá lớn, vui lòng chọn ảnh dưới khoảng 5MB.');
}

if (OPENAI_API_KEY === '') {
    json_error(
        'AI Camera cần API key OpenAI để phân tích ảnh thật. ' .
        'Lấy key tại https://platform.openai.com/api-keys (cần nạp một khoản nhỏ vào tài khoản) ' .
        'rồi dán vào hằng số OPENAI_API_KEY trong file analyze-image.php.',
        503
    );
}
$prompt = <<<PROMPT
Bạn là "AI Camera" của ứng dụng Siêu Nhí An Toàn Giao Thông AI, chuyên phân tích ảnh chụp tình huống giao thông
của học sinh tiểu học Việt Nam (6-11 tuổi) để đánh giá mức độ an toàn.

Hãy quan sát kỹ bức ảnh và nhận diện các yếu tố an toàn giao thông THỰC SỰ xuất hiện trong ảnh, ví dụ (nếu có):
- Mũ bảo hiểm (đội đúng cách hay sai, có cài quai không, có vừa đầu không)
- Biển báo giao thông (loại biển gì, ý nghĩa, có ai vi phạm không)
- Đèn tín hiệu giao thông (đang màu gì, người/xe có tuân thủ không)
- Người đi bộ (có đi đúng vạch kẻ đường không)
- Xe đạp / xe máy / ô tô (đi đúng làn, giữ khoảng cách an toàn không)
- Vạch qua đường (có ai đi đúng/sai vạch không)
- Xe ưu tiên (cứu thương, cứu hoả, công an)
- Bất kỳ hành vi an toàn hoặc KHÔNG an toàn nào khác quan sát rõ được trong ảnh

Chỉ liệt kê những gì THỰC SỰ nhìn thấy rõ trong ảnh — không suy đoán hay bịa thêm chi tiết không có.
Nếu ảnh không phải cảnh giao thông hoặc không nhận diện được gì liên quan tới an toàn giao thông,
hãy trả về đúng 1 mục duy nhất giải thích điều đó một cách nhẹ nhàng, thân thiện.

Trả lời DUY NHẤT bằng JSON đúng định dạng sau, không thêm bất kỳ chữ nào khác ngoài JSON, không dùng markdown:
{
  "accuracy": "<mức độ tự tin của AI, ví dụ '92% chính xác'>",
  "items": [
    {
      "icon": "<1 emoji phù hợp với mục này>",
      "title": "<tên ngắn gọn của yếu tố nhận diện được>",
      "desc": "<mô tả cụ thể, thực tế những gì quan sát được trong ảnh>",
      "severity": "<đúng 1 trong 3 giá trị: 'ok' nếu an toàn/đạt chuẩn, 'warn' nếu cần chú ý nhẹ, 'danger' nếu nguy hiểm/vi phạm nghiêm trọng>"
    }
  ],
  "advice": "<lời khuyên ngắn gọn 2-3 câu, thân thiện, xưng 'mình', gọi trẻ là 'con', dựa đúng theo nội dung ảnh vừa phân tích>"
}
PROMPT;

$body = [
    'model'    => OPENAI_MODEL,
    'messages' => [[
        'role'    => 'user',
        'content' => [
            ['type' => 'text', 'text' => $prompt],
            ['type' => 'image_url', 'image_url' => ['url' => $imageData]],
        ],
    ]],
    'temperature'     => 0.4,
    'response_format' => ['type' => 'json_object'],
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_TIMEOUT    => 40,
    // XAMPP trên Windows thường thiếu chứng chỉ SSL → tắt kiểm tra
    // (chỉ dùng cho localhost/demo; khi đưa lên hosting thật nên bật lại)
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
]);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($res === false) {
    json_error('Không kết nối được tới máy chủ AI (OpenAI): ' . $curlErr, 502);
}

$data = json_decode($res, true);

if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
    $errMsg = $data['error']['message'] ?? 'AI không trả về kết quả hợp lệ.';
    json_error('Lỗi từ OpenAI API: ' . $errMsg, 502);
}

$text = $data['choices'][0]['message']['content'];
$result = json_decode($text, true);

if (!is_array($result) || !isset($result['items']) || !is_array($result['items'])) {
    json_error('AI trả về định dạng không đọc được, vui lòng thử lại.', 502);
}

// Chuẩn hoá dữ liệu trước khi trả về cho frontend
echo json_encode([
    'accuracy' => $result['accuracy'] ?? '—',
    'items'    => $result['items'],
    'advice'   => $result['advice'] ?? 'Hãy luôn tuân thủ luật giao thông để an toàn cho bản thân và mọi người nhé!',
], JSON_UNESCAPED_UNICODE);
