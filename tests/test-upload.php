<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../lib/upload.php';

/* PNG 1x1 thật để finfo nhận diện */
$pngFile = tempnam(sys_get_temp_dir(), 'png');
file_put_contents($pngFile, base64_decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$txtFile = tempnam(sys_get_temp_dir(), 'txt');
file_put_contents($txtFile, 'day khong phai anh');

$r = validate_review_image(['tmp_name' => $pngFile, 'error' => UPLOAD_ERR_OK, 'size' => filesize($pngFile)]);
check($r['ok'] === true && $r['ext'] === 'png', 'PNG thật hợp lệ, ext=png');

$r = validate_review_image(['tmp_name' => $txtFile, 'error' => UPLOAD_ERR_OK, 'size' => 20]);
check($r['ok'] === false && $r['error'] !== null, 'file text đội lốt bị chặn');

$r = validate_review_image(['tmp_name' => $pngFile, 'error' => UPLOAD_ERR_OK, 'size' => 6 * 1024 * 1024]);
check($r['ok'] === false, 'quá 5MB bị chặn');

$r = validate_review_image(['tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]);
check($r['ok'] === false, 'không có file bị chặn');

unlink($pngFile); unlink($txtFile);
done();
