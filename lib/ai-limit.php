<?php
/* ============================================================
   Rate limit dùng chung cho các API gọi AI (Gemini) trả JSON.
   Dùng session-window: tối đa AI_RATE_LIMIT request / giờ / phiên.
   ============================================================ */

if (!function_exists('allowAiRequest')) {
    function allowAiRequest(): void
    {
        $now = time();
        $window = array_values(array_filter($_SESSION['ai_request_times'] ?? [], static fn($time) => ($now - (int)$time) < 3600));
        if (count($window) >= AI_RATE_LIMIT) {
            http_response_code(429);
            echo json_encode(['status' => 'error', 'message' => 'Con đã hỏi khá nhiều trong một giờ. Mình nghỉ một chút rồi học tiếp nhé.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $window[] = $now;
        $_SESSION['ai_request_times'] = $window;
    }
}
