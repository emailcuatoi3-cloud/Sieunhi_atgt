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
