<?php
/* ============================================================
   AI ENGINE — "Bộ não" của AI Gia sư
   Có 2 chế độ, tự động chuyển:
   1. GEMINI  : nếu bạn dán API key vào bên dưới → AI thật,
                trả lời được mọi câu hỏi, nhớ ngữ cảnh hội thoại.
   2. OFFLINE : không cần key, không cần mạng → trả lời theo
                kho kiến thức an toàn giao thông có sẵn.
   ============================================================ */

/* ---------- CẤU HÌNH ----------
   Lấy API key MIỄN PHÍ tại: https://aistudio.google.com/apikey
   (đăng nhập Google → Create API key → dán vào giữa 2 dấu nháy) */
require_once __DIR__ . '/config.php';

/* "Tính cách" của AI Gia sư khi dùng Gemini */
define('AI_SYSTEM_PROMPT',
    "Bạn là 'AI Gia sư' của ứng dụng Siêu Nhí An Toàn Giao Thông AI. " .
    "Nhiệm vụ: dạy các bé học sinh tiểu học Việt Nam (6-11 tuổi) về an toàn giao thông. " .
    "Cách trả lời: thân thiện, xưng 'mình', gọi bé là 'con', câu ngắn gọn dễ hiểu, " .
    "có emoji sinh động, chính xác theo luật giao thông Việt Nam. " .
    "Chỉ nói về chủ đề giao thông và an toàn; nếu bé hỏi lạc đề, hãy nhẹ nhàng " .
    "hướng bé quay lại chủ đề giao thông. Trả lời tối đa khoảng 4-5 câu."
);

/* Hỗ trợ PHP 7 (XAMPP cũ) */
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || mb_strpos($haystack, $needle) !== false;
    }
}

/**
 * Google hiện phát hành 2 dạng key khác nhau cho Gemini API:
 *   - "AIzaSy..." (dạng cũ, API key thường)  -> gửi qua ?key=... trên URL
 *   - "AQ...."    (dạng mới, thực chất là OAuth 2 Access Token) -> PHẢI gửi
 *     qua header Authorization: Bearer, gửi qua ?key= sẽ bị lỗi 401
 *     "Expected OAuth 2 access token" (theo báo cáo chính thức từ Google
 *     AI Developers Forum, không phải lỗi sai key).
 * Hàm này tự nhận diện và trả về đúng [url, headers] cho từng loại key.
 */
function gemini_endpoint(string $model): array
{
    $isAuthToken = substr(GEMINI_API_KEY, 0, 3) === 'AQ.';
    if ($isAuthToken) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . GEMINI_API_KEY];
    } else {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . GEMINI_API_KEY;
        $headers = ['Content-Type: application/json'];
    }
    return [$url, $headers];
}

/* ============================================================
   HÀM CHÍNH — được ai-chat.php gọi
   ============================================================ */
function ai_get_reply(PDO $pdo, int $sessionId, string $userMessage, string $ageGroup = '6-8'): string
{
    if (GEMINI_API_KEY !== '') {
        $reply = ai_call_gemini($pdo, $sessionId, $userMessage, $ageGroup);
        if ($reply !== null && trim($reply) !== '') {
            return trim($reply);
        }
    }
    if (OPENAI_API_KEY !== '') {
        $reply = ai_call_openai($pdo, $sessionId, $userMessage, $ageGroup);
        if ($reply !== null && trim($reply) !== '') {
            return trim($reply);
        }
    }
    return ai_rule_based($userMessage);
}

function ai_call_openai(PDO $pdo, int $sessionId, string $userMessage, string $ageGroup = '6-8'): ?string
{
    $history = [];
    if ($sessionId > 0) {
        $stmt = $pdo->prepare(
            "SELECT role, content FROM ai_chat_messages
             WHERE session_id = ? ORDER BY id DESC LIMIT 10"
        );
        $stmt->execute([$sessionId]);
        $history = array_reverse($stmt->fetchAll());
    }

    $systemPrompt = "Bạn là trợ lý AI dành cho trẻ em học an toàn giao thông. " .
                    AI_SYSTEM_PROMPT . " Nhóm tuổi hiện tại: {$ageGroup}. Dùng từ vựng và ví dụ phù hợp với nhóm tuổi này.";

    $messages = [
        [
            "role"    => "system",
            "content" => $systemPrompt
        ]
    ];
    foreach ($history as $m) {
        $messages[] = [
            "role"    => $m['role'] === 'user' ? 'user' : 'assistant',
            "content" => $m['content'],
        ];
    }
    $messages[] = [
        "role"    => "user",
        "content" => $userMessage
    ];

    $body = [
        "model"                 => OPENAI_MODEL, // gpt-5-nano
        "messages"              => $messages,
        "reasoning_effort"      => "low",
        "max_completion_tokens" => 2048,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false) return null;

    $data = json_decode($res, true);
    return $data['choices'][0]['message']['content'] ?? null;
}

/* ============================================================
   CHẾ ĐỘ 1 — Gọi Google Gemini API (kèm lịch sử hội thoại)
   ============================================================ */
function ai_call_gemini(PDO $pdo, int $sessionId, string $userMessage, string $ageGroup = '6-8'): ?string
{
    // Lấy 10 tin nhắn gần nhất làm ngữ cảnh để AI "nhớ" cuộc trò chuyện
    $stmt = $pdo->prepare(
        "SELECT role, content FROM ai_chat_messages
         WHERE session_id = ? ORDER BY id DESC LIMIT 10"
    );
    $stmt->execute([$sessionId]);
    $history = array_reverse($stmt->fetchAll());

    $contents = [];
    foreach ($history as $m) {
        $contents[] = [
            'role'  => $m['role'] === 'user' ? 'user' : 'model',
            'parts' => [['text' => $m['content']]],
        ];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

    $body = [
        'system_instruction' => ['parts' => [['text' => AI_SYSTEM_PROMPT .
            " Nhóm tuổi hiện tại: {$ageGroup}. Dùng từ vựng và ví dụ phù hợp với nhóm tuổi này. " .
            "Nếu câu hỏi cần quy định cụ thể, chỉ nêu điều đã có trong nguồn được duyệt."]]],
        'contents'           => $contents,
        'generationConfig'   => ['temperature' => 0.7, 'maxOutputTokens' => 1024],
    ];

    [$url, $headers] = gemini_endpoint(GEMINI_MODEL);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => 30,
        // XAMPP trên Windows thường thiếu chứng chỉ SSL → tắt kiểm tra
        // (chỉ dùng cho localhost/demo; khi đưa lên hosting thật nên bật lại)
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false) return null;

    $data = json_decode($res, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

/* ============================================================
   CHẾ ĐỘ 2 — Kho kiến thức offline (không cần API key)
   ============================================================ */

/* Bỏ dấu tiếng Việt để so khớp từ khoá dễ hơn */
function ai_khong_dau(string $str): string
{
    $str = mb_strtolower($str, 'UTF-8');
    $map = [
        'a' => ['á','à','ả','ã','ạ','ă','ắ','ằ','ẳ','ẵ','ặ','â','ấ','ầ','ẩ','ẫ','ậ'],
        'e' => ['é','è','ẻ','ẽ','ẹ','ê','ế','ề','ể','ễ','ệ'],
        'i' => ['í','ì','ỉ','ĩ','ị'],
        'o' => ['ó','ò','ỏ','õ','ọ','ô','ố','ồ','ổ','ỗ','ộ','ơ','ớ','ờ','ở','ỡ','ợ'],
        'u' => ['ú','ù','ủ','ũ','ụ','ư','ứ','ừ','ử','ữ','ự'],
        'y' => ['ý','ỳ','ỷ','ỹ','ỵ'],
        'd' => ['đ'],
    ];
    foreach ($map as $to => $from) {
        $str = str_replace($from, $to, $str);
    }
    return $str;
}

function ai_rule_based(string $msg): string
{
    $t = ai_khong_dau($msg);

    /* Mỗi mục: [danh sách từ khoá (không dấu)] => [các câu trả lời, chọn ngẫu nhiên]
       Chủ đề CỤ THỂ đặt trước, chủ đề chung đặt sau. */
    $kb = [

        [['den vang'], [
            "Đèn vàng nghĩa là **chuẩn bị dừng lại** con nhé 🟡. Khi thấy đèn vàng, con không nên cố đi nhanh qua mà hãy giảm tốc độ và dừng trước vạch. Nếu con đi bộ, hãy đứng chờ trên vỉa hè đến khi đèn xanh dành cho người đi bộ bật sáng nhé! 🚦",
        ]],

        [['den do'], [
            "Đèn đỏ là phải **dừng lại hoàn toàn** trước vạch kẻ đường 🔴. Dù đường có vắng đến mấy, con cũng tuyệt đối không được vượt đèn đỏ nhé. Chờ một chút để an toàn hơn nhiều đó con! 🚦",
        ]],

        [['den xanh'], [
            "Đèn xanh nghĩa là được đi 🟢, nhưng trước khi đi con vẫn nên **quan sát hai bên** xem còn xe nào đang chạy tới không. An toàn rồi mới đi con nhé! 👀",
        ]],

        [['den giao thong', 'den tin hieu', 'tin hieu den', 'may mau den'], [
            "Đèn giao thông có 3 màu con nhé: 🔴 Đỏ là **dừng lại**, 🟡 Vàng là **chuẩn bị dừng**, 🟢 Xanh là **được đi**. Con nhớ luôn quan sát đèn và nhìn hai bên trước khi qua đường nha! 🚦",
        ]],

        [['mu bao hiem', 'doi mu', 'non bao hiem'], [
            "Đội mũ bảo hiểm đúng cách gồm 3 bước nè ⛑️:\n1️⃣ Đội mũ ngay ngắn, vành mũ song song với lông mày.\n2️⃣ Cài quai mũ chắc chắn.\n3️⃣ Kiểm tra quai vừa khít — đút vừa 2 ngón tay dưới cằm là chuẩn.\nCon nhớ chọn mũ đạt chuẩn và vừa với đầu mình nhé!",
            "Mũ bảo hiểm giống như 'chiếc khiên' bảo vệ đầu của con đó ⛑️. Khi ngồi xe máy hay xe đạp điện, con **luôn phải đội mũ** và cài quai cẩn thận. Đội mũ mà không cài quai thì cũng như chưa đội đâu nha! 😊",
        ]],

        [['sang duong', 'qua duong', 'bang qua duong', 'vach ke', 'loi di bo', 'nguoi di bo', 'di bo'], [
            "Khi sang đường, con nhớ quy tắc: **Dừng lại – Quan sát – Lắng nghe** 🚸. Hãy đi trên vạch kẻ trắng dành cho người đi bộ, nhìn trái, nhìn phải rồi nhìn trái lần nữa. Tốt nhất là nắm tay người lớn khi qua đường con nhé! 🤝",
            "Vạch kẻ trắng trên đường (vạch ngựa vằn 🦓) là lối đi dành riêng cho **người đi bộ** sang đường đó con. Con hãy luôn đi đúng vạch, chờ đèn xanh cho người đi bộ và giơ tay xin đường nếu cần nhé! 🚸",
        ]],

        [['cuu thuong', 'cuu hoa', 'xe uu tien', 'canh sat', 'cuu ho', 'uu tien'], [
            "Khi gặp xe cứu thương 🚑, xe cứu hoả 🚒 hay xe cảnh sát 🚓 đang hú còi, mọi người phải **nhường đường** cho xe đi trước, vì các xe này đang làm nhiệm vụ khẩn cấp cứu người đó con. Nếu con đang đi bộ, hãy đứng gọn trên vỉa hè chờ xe qua nhé!",
        ]],

        [['stop'], [
            "Biển STOP 🛑 hình bát giác màu đỏ nghĩa là phải **dừng lại hẳn**, quan sát thấy an toàn rồi mới được đi tiếp. Gặp biển này thì ô tô, xe máy hay xe đạp đều phải dừng đó con!",
        ]],

        [['bien bao', 'bien cam', 'bien nguy hiem', 'bien chi dan', 'y nghia bien', 'bien hieu lenh'], [
            "Biển báo giao thông có mấy nhóm chính nè 🚸:\n🔴 Biển **tròn viền đỏ** → biển cấm.\n🔺 Biển **tam giác viền đỏ** → biển nguy hiểm (cảnh báo).\n🔵 Biển **tròn nền xanh** → biển hiệu lệnh (bắt buộc làm theo).\n⬜ Biển **vuông/chữ nhật xanh** → biển chỉ dẫn.\nCon gặp biển nào lạ cứ hỏi mình nha! 📷",
        ]],

        [['xe dap'], [
            "Đi xe đạp an toàn con nhớ: đi sát lề bên phải, không dàn hàng ngang, không buông cả hai tay 🚲. Nếu đi **xe đạp điện** thì bắt buộc phải đội mũ bảo hiểm nhé. Buổi tối nhớ có đèn hoặc mặc đồ sáng màu để mọi người dễ nhìn thấy con!",
        ]],

        [['xe may', 'ngoi sau', 'o to', 'oto', 'xe hoi', 'day an toan'], [
            "Khi ngồi sau xe máy, con phải đội mũ bảo hiểm ⛑️, ôm chặt người lớn và không đùa nghịch 🛵. Còn khi ngồi ô tô, con nhớ **thắt dây an toàn** và không thò đầu, thò tay ra ngoài cửa sổ nhé! 🚗",
        ]],

        [['an toan giao thong', 'an toan'], [
            "An toàn giao thông là hạnh phúc của mọi nhà 💛. Con nhớ: đi bộ trên vỉa hè, qua đường đúng vạch kẻ, đội mũ bảo hiểm khi ngồi xe máy và luôn quan sát cẩn thận. Con muốn mình kể chi tiết phần nào nè — đèn giao thông 🚦, biển báo 🚸 hay đội mũ ⛑️?",
        ]],

        [['ban la ai', 'may la ai', 'cau la ai', 'em la ai', 'gioi thieu'], [
            "Mình là **AI Gia sư** 🤖 của Siêu Nhí An Toàn Giao Thông! Nhiệm vụ của mình là giúp con học về đèn giao thông 🚦, biển báo 🚸, cách qua đường an toàn và nhiều điều thú vị khác. Con muốn học gì hôm nay nào? 😄",
        ]],

        [['xin chao', 'chao ai', 'chao ban', 'hello', 'chao buoi', 'alo'], [
            "Chào con! 👋 Mình là AI Gia sư đây. Hôm nay con muốn học gì về an toàn giao thông nào? Con có thể hỏi về đèn giao thông 🚦, biển báo 🚸, đội mũ bảo hiểm ⛑️... đủ thứ luôn! 😄",
        ]],

        [['cam on', 'thank'], [
            "Không có gì đâu con! 😊 Con ham học hỏi như vậy là giỏi lắm đó. Có gì thắc mắc về giao thông cứ hỏi mình tiếp nha! ⭐",
        ]],

        [['tam biet', 'bye', 'hen gap'], [
            "Tạm biệt con nha! 👋 Nhớ luôn đi đường cẩn thận và áp dụng những điều đã học nhé. Hẹn gặp lại con! 💛",
        ]],
    ];

    foreach ($kb as [$keywords, $replies]) {
        foreach ($keywords as $k) {
            if (str_contains($t, $k)) {
                return $replies[array_rand($replies)];
            }
        }
    }

    /* Không khớp chủ đề nào → câu trả lời mặc định */
    $fallback = [
        "Câu này hay quá mà mình chưa chắc câu trả lời 🤔. Con thử hỏi mình về: đèn giao thông 🚦, biển báo 🚸, đội mũ bảo hiểm ⛑️, cách sang đường an toàn, hay đi xe đạp 🚲 nhé!",
        "Hmm, mình chưa hiểu rõ câu hỏi của con 😅. Con thử hỏi lại theo cách khác, hoặc bấm vào một câu gợi ý phía dưới nha! Mình giỏi nhất là chuyện an toàn giao thông đó! 🚦",
    ];
    return $fallback[array_rand($fallback)];
}

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
