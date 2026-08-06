<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/places-repo.php';

$all = places_all();
check(count($all) >= 15, 'places_all trả >= 15');
check((float)$all[0]['distance_km'] <= (float)end($all)['distance_km'], 'sắp theo khoảng cách tăng dần');
$mus = places_all('bao-tang');
check(count($mus) >= 2 && count(array_unique(array_column($mus, 'type'))) === 1, 'lọc đúng loại');
$p = place_by_slug('bao-tang-dak-lak');
check($p !== null && $p['name'] === 'Bảo tàng Đắk Lắk', 'place_by_slug đúng');
check(place_by_slug('khong-co') === null, 'slug lạ → null');
check(place_type_label('thien-nhien') === 'Thiên nhiên', 'label loại đúng');
check(is_array(place_reviews_approved((int)$p['id'])), 'reviews_approved trả mảng');
done();
