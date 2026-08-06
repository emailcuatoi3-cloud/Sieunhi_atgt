<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../ai-engine.php';

check(ai_detect_topic('Đèn đỏ thì phải làm gì?') === 'den-tin-hieu', 'đèn đỏ → den-tin-hieu');
check(ai_detect_topic('đội mũ bảo hiểm thế nào') === 'mu-bao-hiem', 'mũ bảo hiểm');
check(ai_detect_topic('Con muốn qua đường') === 'qua-duong', 'qua đường');
check(ai_detect_topic('biển cấm là gì') === 'bien-bao', 'biển báo');
check(ai_detect_topic('đi xe đạp an toàn') === 'xe-dap', 'xe đạp');
check(ai_detect_topic('ngồi sau xe máy') === 'ngoi-xe', 'ngồi xe');
check(ai_detect_topic('gặp xe cứu thương') === 'uu-tien', 'ưu tiên');
check(ai_detect_topic('hôm nay trời đẹp') === null, 'lạc đề → null');
check(ai_detect_topic('MŨ BẢO HIỂM') === 'mu-bao-hiem', 'không phân biệt hoa thường/dấu');
done();
