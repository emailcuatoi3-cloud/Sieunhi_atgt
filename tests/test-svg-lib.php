<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/svg-lib.php';

$codes = ['den-tin-hieu','bien-bao','mu-bao-hiem','qua-duong','xe-dap','ngoi-xe','uu-tien',
          'bao-tang','cong-vien','vui-choi','thien-nhien','map-bmt'];
foreach ($codes as $c) {
    $svg = svg_art($c);
    check(is_string($svg) && str_contains($svg, '<svg'), "svg_art($c) trả SVG");
    check(is_string($svg) && str_contains($svg, 'viewBox'), "svg_art($c) có viewBox");
}
check(svg_art('khong-ton-tai') === null, 'mã lạ trả null');
check(count(svg_art_codes()) === 12, 'svg_art_codes đủ 12 mã');
done();
