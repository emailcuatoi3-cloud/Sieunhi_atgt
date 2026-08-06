<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../db_utils.php';

$db = new DB_UTILS();
check((int)$db->getValue("SELECT COUNT(*) FROM places WHERE status='published'") >= 15, 'có >= 15 địa điểm published');
foreach (['bao-tang','cong-vien','vui-choi','thien-nhien'] as $t) {
    check((int)$db->getValue("SELECT COUNT(*) FROM places WHERE type=?", [$t]) >= 2, "có >= 2 địa điểm loại $t");
}
check((int)$db->getValue(
    "SELECT COUNT(*) FROM places WHERE story='' OR safety_note='' OR open_hours='' OR ticket=''") === 0,
    'không có trường mô tả rỗng');
check((int)$db->getValue("SELECT COUNT(*) FROM places WHERE distance_km <= 2.0") >= 5, '>= 5 điểm đi bộ được (<=2km)');
done();
