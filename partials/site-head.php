<?php
/**
 * Master layout — phần mở <head>.
 * Trang dùng đặt $PAGE trước khi require:
 *   $PAGE = [
 *     'title'      => 'Tiêu đề tab',            // bắt buộc
 *     'desc'       => 'Meta description',       // tuỳ chọn
 *     'nav'        => 'trang-chu',              // khoá menu đang active (xem site-nav.php)
 *     'body_class' => 'kham-pha-page',          // tuỳ chọn
 *     'css'        => ['assets/css/x.css'],     // stylesheet thêm, tuỳ chọn
 *     'hero_nav'   => true,                     // navbar trong suốt phủ hero (chỉ trang chủ)
 *     'crumb'      => ['href'=>'index.php','label'=>'← Trang chủ','title'=>'🗺️ Khám phá'],
 *   ];
 * Sau partial này head vẫn MỞ — trang chèn <style> riêng rồi require site-nav.php.
 */
require_once __DIR__ . '/../auth.php';
$PAGE = $PAGE ?? [];
$ASSET_VER = defined('ASSET_VER') ? ASSET_VER : '20260807';
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf" content="<?= e(csrfToken()) ?>">
    <script>
    (function() {
        try {
            document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme") || "light");
        } catch (e) {}
    })();
    </script>
    <title><?= e($PAGE['title'] ?? 'Siêu Nhí An Toàn Giao Thông AI') ?></title>
    <?php if (!empty($PAGE['desc'])): ?>
    <meta name="description" content="<?= e($PAGE['desc']) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/fonts.css?v=<?= $ASSET_VER ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $ASSET_VER ?>">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=<?= $ASSET_VER ?>">
    <link rel="stylesheet" href="assets/css/kid-components.css?v=<?= $ASSET_VER ?>">
    <?php foreach (($PAGE['css'] ?? []) as $href): ?>
    <link rel="stylesheet" href="<?= e($href) ?>?v=<?= $ASSET_VER ?>">
    <?php endforeach; ?>
