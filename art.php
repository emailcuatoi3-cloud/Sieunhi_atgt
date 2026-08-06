<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/svg-lib.php';
$code = preg_replace('/[^a-z0-9\-]/', '', (string)($_GET['code'] ?? ''));
$svg = svg_art($code);
if ($svg === null) { http_response_code(404); exit('not found'); }
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');
echo $svg;
