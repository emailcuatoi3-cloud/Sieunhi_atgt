<?php
/**
 * Master layout — footer chung + script nền tảng.
 * $PAGE['scripts'] = ['assets/js/x.js'] để nạp script thêm sau main.js.
 */
$user = $user ?? currentUser();
$ASSET_VER = defined('ASSET_VER') ? ASSET_VER : '20260807';
?>
    <footer class="site-footer" id="footer">
        <div class="wrap">
            <div class="foot-grid">
                <div class="foot-brand">
                    <a href="index.php" class="logo">
                        <img src="assets/images/sieu-nhi-logo.png" alt="SIÊU NHÍ AI" class="site-logo-img" />
                    </a>
                    <p>
                        Nền tảng AI giáo dục an toàn giao thông cho học sinh Việt Nam. Học
                        thông minh — Đi an toàn — Vì tương lai Việt Nam.
                    </p>
                </div>
                <div class="foot-col">
                    <h5>Sản phẩm</h5>
                    <a href="ai-gia-su.php">AI Gia sư</a><a href="ai-mo-phong.php">Mô phỏng</a><a
                        href="game-mini.php">Thử thách</a><a href="bang-xep-hang.php">Bảng xếp hạng</a>
                </div>
                <div class="foot-col">
                    <h5>Khám phá</h5>
                    <a href="kham-pha.php">Địa điểm Buôn Ma Thuột</a><a href="lich-trinh-ai.php">Lịch trình AI</a><a
                        href="ai-truyen-tranh.php">Truyện tranh</a><a href="ai-camera.php">AI Camera</a>
                </div>
                <div class="foot-col">
                    <h5>Đối tượng</h5>
                    <?php if (!$user || in_array($user['role'], ['hocsinh', 'admin'], true)): ?>
                    <a href="dashboard-hoc-sinh.php">Học sinh</a>
                    <?php endif; ?>
                    <?php if (!$user || in_array($user['role'], ['phuhuynh', 'admin'], true)): ?>
                    <a href="dashboard-phu-huynh.php">Phụ huynh</a>
                    <?php endif; ?>
                    <?php if (!$user || in_array($user['role'], ['giaovien', 'admin'], true)): ?>
                    <a href="dashboard-giao-vien.php">Giáo viên</a>
                    <?php endif; ?>
                    <a href="dang-ky.php">Nhà trường</a>
                </div>
                <div class="foot-col">
                    <h5>Cộng đồng</h5>
                    <a href="index.php#hero">Về dự án</a><a href="community.php">Đóng góp tình huống</a><a
                        href="index.php#features">Tính năng</a><a href="dang-ky.php">Liên hệ</a>
                </div>
            </div>
            <div class="foot-bottom">
                <span>© 2026 Siêu Nhí An Toàn Giao Thông AI · AI Traffic Hero</span>
                <div class="social-row">
                    <a href="index.php#footer" aria-label="Kênh thông tin">ⓘ</a>
                </div>
            </div>
        </div>
    </footer>

    <script>window.SIEU_NHI_AUTH = <?= $user ? 'true' : 'false' ?>; window.SIEU_NHI_CSRF = <?= json_encode(csrfToken(), JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/js/main.js?v=<?= $ASSET_VER ?>"></script>
    <?php foreach (($PAGE['scripts'] ?? []) as $src): ?>
    <script src="<?= e($src) ?>?v=<?= $ASSET_VER ?>"></script>
    <?php endforeach; ?>
</body>

</html>
