<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../db_utils.php';

$db = new DB_UTILS();
$tables = ['places', 'place_reviews', 'user_preferences', 'ai_itineraries'];
foreach ($tables as $t) {
    $n = (int)$db->getValue(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?", [$t]);
    check($n === 1, "bảng $t tồn tại");
}
$cols = $db->getAll(
    "SELECT column_name FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'places'");
$names = array_column($cols, 'column_name') ?: array_column($cols, 'COLUMN_NAME');
foreach (['slug','name','type','story','safety_note','distance_km','map_x','map_y','art_code'] as $c) {
    check(in_array($c, $names, true), "places có cột $c");
}
done();
