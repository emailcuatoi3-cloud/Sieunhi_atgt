<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ai-engine.php';
require_once __DIR__ . '/lib/places-repo.php';
require_once __DIR__ . '/lib/itinerary.php';
require_once __DIR__ . '/lib/ai-limit.php';
header('Content-Type: application/json; charset=utf-8');
requireCsrf();
allowAiRequest();

$opts = [
    'time_slot' => in_array($_POST['time_slot'] ?? '', ['sang','ca-ngay','cuoi-tuan'], true) ? $_POST['time_slot'] : 'sang',
    'vehicle'   => in_array($_POST['vehicle'] ?? '', ['di-bo','xe-dap','bo-me-cho'], true) ? $_POST['vehicle'] : 'bo-me-cho',
    'types'     => array_values(array_intersect((array)($_POST['types'] ?? []),
                    ['bao-tang','cong-vien','vui-choi','thien-nhien'])),
];
$places = places_all();
$legs = itinerary_ai($places, $opts);
$engine = $legs !== null ? 'gemini' : 'offline';
if ($legs === null) $legs = itinerary_offline($places, $opts);

if (isLoggedIn()) {
    (new DB_UTILS())->execute(
        'INSERT INTO ai_itineraries (user_id, params, plan, engine) VALUES (?,?,?,?)',
        [(int)$_SESSION['user_id'], json_encode($opts, JSON_UNESCAPED_UNICODE),
         json_encode($legs, JSON_UNESCAPED_UNICODE), $engine]);
}
echo json_encode(['status' => 'success', 'engine' => $engine, 'legs' => $legs], JSON_UNESCAPED_UNICODE);
