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
