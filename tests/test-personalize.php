<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/personalize.php';

$chips = build_suggested_chips([], [], [], 'tieu-hoc');
check(count($chips) === 6, 'mặc định trả 6 chip');
check(isset($chips[0]['topic'], $chips[0]['text']), 'chip có topic + text');

$chips = build_suggested_chips(['xe-dap'], ['bien-bao'], ['mu-bao-hiem'], 'tieu-hoc');
check($chips[0]['topic'] === 'bien-bao', 'chủ đề yếu (weak) đứng đầu');
check($chips[1]['topic'] === 'xe-dap', 'sở thích (fav) đứng nhì');
check($chips[2]['topic'] === 'mu-bao-hiem', 'chủ đề gần đây đứng ba');

$topics = array_column(build_suggested_chips(['xe-dap'], ['xe-dap'], ['xe-dap'], 'thcs'), 'topic');
check(count($topics) === count(array_unique($topics)), 'không trùng topic');

$th = build_suggested_chips([], ['mu-bao-hiem'], [], 'tieu-hoc')[0]['text'];
$cs = build_suggested_chips([], ['mu-bao-hiem'], [], 'thcs')[0]['text'];
check($th !== $cs, 'câu chữ khác nhau theo khối lớp');

check(build_suggested_chips([], [], [], 'tieu-hoc', 3) !== null && count(build_suggested_chips([], [], [], 'tieu-hoc', 3)) === 3, 'limit hoạt động');
done();
