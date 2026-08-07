<?php
require_once __DIR__ . '/auth.php';
requireRole(['admin']);
$user = currentUser();
$db = new DB_UTILS();
$userTotal = $studentTotal = $lessonTotal = $chatTotal = 0; $pendingReviewCount = 0;
try {
  $userTotal = (int)$db->getValue('SELECT COUNT(*) FROM users');
  $studentTotal = (int)$db->getValue('SELECT COUNT(*) FROM users WHERE role = "hocsinh"');
  $lessonTotal = (int)$db->getValue('SELECT COUNT(*) FROM lessons WHERE status = "published"');
  $chatTotal = (int)$db->getValue('SELECT COUNT(*) FROM ai_chat_messages WHERE role = "user"');
  $pendingReviewCount = (int)$db->getValue('SELECT COUNT(*) FROM place_reviews WHERE status = "pending"');
} catch (Throwable $ignored) { }
?>
<!DOCTYPE html>
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
    <title>Dashboard Admin · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="stylesheet" href="assets/css/fonts.css?v=1">
    <link rel="stylesheet" href="assets/css/style.css?v=5">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=5">
    <link rel="stylesheet" href="assets/css/kid-components.css?v=1">
</head>

<body>

    <div class="app">
        <aside class="sidebar">
            <div>
                <a href="index.php" class="side-brand"><img src="assets/images/sieu-nhi-logo.png" alt="SIÊU NHÍ AI" class="side-brand-img"></a>
                <a class="side-back" href="index.php">← Về trang chủ</a>
            </div>
            <a class="side-link active" href="dashboard-admin.php"><span class="ic">🏠</span> Tổng quan</a>
            <a class="side-link" href="admin-users.php"><span class="ic">👥</span> Người dùng</a>
            <a class="side-link" href="community-admin.php"><span class="ic">🗂️</span> Nội dung</a>
            <a class="side-link" href="ai-gia-su.php"><span class="ic">🤖</span> AI</a>
            <a class="side-link" href="ai-gia-su.php"><span class="ic">📘</span> Bài học</a>
            <a class="side-link" href="game-mini.php"><span class="ic">🏆</span> Cuộc thi</a>
            <a class="side-link" href="dashboard-admin.php"><span class="ic">📊</span> Thống kê</a>
            <a class="side-link" href="dashboard-admin.php"><span class="ic">🖥️</span> Máy chủ</a>
            <a class="side-link" href="dashboard-admin.php"><span class="ic">📜</span> Logs</a>
            <a class="side-link" href="#duyet-review"><span class="ic">📝</span> Duyệt review<?php if ($pendingReviewCount > 0): ?> <span class="kid-badge kid-badge--red"><?= $pendingReviewCount ?></span><?php endif; ?></a>
            <div class="side-divider"></div>
            <a class="side-link" href="dashboard-hoc-sinh.php"><span class="ic">🎒</span> Dashboard học sinh</a>
            <a class="side-link" href="dashboard-phu-huynh.php"><span class="ic">👨‍👩‍👧</span> Dashboard phụ huynh</a>
            <a class="side-link" href="dashboard-giao-vien.php"><span class="ic">👩‍🏫</span> Dashboard giáo viên</a>
            <div class="side-divider"></div>
            <a class="side-link" href="dashboard-admin.php"><span class="ic">⚙️</span> Cài đặt hệ thống</a>
            <a class="side-link" href="logout.php"><span class="ic">🚪</span> Đăng xuất</a>

            <div class="sidebar-foot">
                <div class="av"><?= e($user['avatar']) ?></div>
                <div class="txt"><b><?= e($user['name']) ?></b><span>Toàn quyền quản trị</span></div>
            </div>
        </aside>

        <main class="main">
            <div class="top-row">
                <div class="greet">
                    <h1>Bảng điều khiển quản trị 🛡️</h1>
                    <p>Tổng quan toàn bộ hệ thống Siêu Nhí An Toàn Giao Thông AI — thời gian thực.</p>
                </div>
                <div class="top-actions">
                    <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
                    <button class="btn btn-ghost" onclick="refreshLogs()">🔄 Làm mới</button>
                    <a class="btn btn-primary-sm" href="community-admin.php">📄 Mở hàng đợi nội dung</a>
                </div>
            </div>

            <div class="stat-row cols-5">
                <div class="mini-stat-card">
                    <div class="st-ic">👥</div><b><?= $userTotal ?></b><span>Tổng người dùng</span>
                </div>
                <div class="mini-stat-card">
                    <div class="st-ic">🧒</div><b><?= $studentTotal ?></b><span>Học sinh</span>
                </div>
                <div class="mini-stat-card">
                    <div class="st-ic">📘</div><b><?= $lessonTotal ?></b><span>Bài học đã xuất bản</span>
                </div>
                <div class="mini-stat-card">
                    <div class="st-ic">🤖</div><b><?= $chatTotal ?></b><span>Câu hỏi đã lưu</span>
                </div>
                <div class="mini-stat-card">
                    <div class="st-ic">🖥️</div><b>—</b><span>Uptime chưa kết nối</span>
                </div>
            </div>

            <div class="grid2">
                <div>
                    <div class="card">
                        <div class="card-head">
                            <h3>👥 Quản lý người dùng gần đây</h3><a href="admin-users.php">Xem tất cả</a>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Người dùng</th>
                                    <th>Vai trò</th>
                                    <th>Ngày tham gia</th>
                                    <th>Trạng thái</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><b>Trần Minh An</b></td>
                                    <td><span class="role-tag student">Học sinh</span></td>
                                    <td>2 ngày trước</td>
                                    <td>
                                        <div class="status-dot-row active"><span class="sdot"></span>Hoạt động</div>
                                    </td>
                                    <td class="row-action">Quản lý</td>
                                </tr>
                                <tr>
                                    <td><b>Anh Tuấn Nguyễn</b></td>
                                    <td><span class="role-tag parent">Phụ huynh</span></td>
                                    <td>2 ngày trước</td>
                                    <td>
                                        <div class="status-dot-row active"><span class="sdot"></span>Hoạt động</div>
                                    </td>
                                    <td class="row-action">Quản lý</td>
                                </tr>
                                <tr>
                                    <td><b>Cô Lan Anh</b></td>
                                    <td><span class="role-tag teacher">Giáo viên</span></td>
                                    <td>5 ngày trước</td>
                                    <td>
                                        <div class="status-dot-row active"><span class="sdot"></span>Hoạt động</div>
                                    </td>
                                    <td class="row-action">Quản lý</td>
                                </tr>
                                <tr>
                                    <td><b>Trường TH Nguyễn Du</b></td>
                                    <td><span class="role-tag teacher">Đối tác trường</span></td>
                                    <td>1 tuần trước</td>
                                    <td>
                                        <div class="status-dot-row pending"><span class="sdot"></span>Chờ duyệt</div>
                                    </td>
                                    <td class="row-action">Duyệt</td>
                                </tr>
                                <tr>
                                    <td><b>Lê Thị Na</b></td>
                                    <td><span class="role-tag student">Học sinh</span></td>
                                    <td>1 tuần trước</td>
                                    <td>
                                        <div class="status-dot-row active"><span class="sdot"></span>Hoạt động</div>
                                    </td>
                                    <td class="row-action">Quản lý</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h3>🗂️ Nội dung chờ duyệt</h3><a href="community-admin.php">Xem hàng đợi</a>
                        </div>
                        <div class="mod-item">
                            <div class="mod-ic">📖</div>
                            <div class="mod-info"><b>Truyện tương tác mới: "Na và chiếc xe buýt"</b><span>Gửi bởi Cô Lan
                                    Anh · 2 giờ trước</span></div>
                            <div class="mod-actions">
                                <div class="mini-btn approve" onclick="moderate(this)">✓</div>
                                <div class="mini-btn reject" onclick="moderate(this)">✕</div>
                            </div>
                        </div>
                        <div class="mod-item">
                            <div class="mod-ic">📘</div>
                            <div class="mod-info"><b>Bài học: "Đi xe buýt trường học an toàn"</b><span>Gửi bởi Trường TH
                                    Nguyễn Du · 5 giờ trước</span></div>
                            <div class="mod-actions">
                                <div class="mini-btn approve" onclick="moderate(this)">✓</div>
                                <div class="mini-btn reject" onclick="moderate(this)">✕</div>
                            </div>
                        </div>
                        <div class="mod-item">
                            <div class="mod-ic">🖼️</div>
                            <div class="mod-info"><b>Ảnh minh hoạ mới cho biển báo khu vực trường học</b><span>Gửi bởi
                                    Đội thiết kế · 1 ngày trước</span></div>
                            <div class="mod-actions">
                                <div class="mini-btn approve" onclick="moderate(this)">✓</div>
                                <div class="mini-btn reject" onclick="moderate(this)">✕</div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h3>📜 Nhật ký hệ thống (Logs)</h3><span
                                style="font-size:11px; color:rgba(255,255,255,0.42);">Cập nhật mỗi 5 giây</span>
                        </div>
                        <div class="log-console" id="logConsole"></div>
                    </div>
                </div>

                <div>
                    <div class="card">
                        <div class="card-head">
                            <h3>🖥️ Trạng thái máy chủ</h3>
                        </div>
                        <div class="metric-grid">
                            <div class="metric-box">
                                <div class="m-val">42%</div>
                                <div class="m-label">CPU</div>
                            </div>
                            <div class="metric-box">
                                <div class="m-val">61%</div>
                                <div class="m-label">RAM</div>
                            </div>
                            <div class="metric-box">
                                <div class="m-val">28ms</div>
                                <div class="m-label">Độ trễ AI</div>
                            </div>
                        </div>
                        <div
                            style="margin-top:14px; display:flex; justify-content:space-between; font-size:12px; color:rgba(255,255,255,0.42);">
                            <span>Khu vực máy chủ: Hà Nội · TP.HCM</span>
                            <span style="color:var(--green); font-weight:700;">● Ổn định</span>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h3>🤖 Trạng thái mô hình AI</h3>
                        </div>
                        <div class="ai-row">
                            <div class="ai-ic">🎓</div>
                            <div class="ai-info"><b>AI Gia sư</b><span>Độ trễ trung bình 1.2s</span></div>
                            <div class="ai-status"><i></i>Online</div>
                        </div>
                        <div class="ai-row">
                            <div class="ai-ic">📷</div>
                            <div class="ai-info"><b>AI Camera Vision</b><span>Độ chính xác 96,2%</span></div>
                            <div class="ai-status"><i></i>Online</div>
                        </div>
                        <div class="ai-row">
                            <div class="ai-ic">🚦</div>
                            <div class="ai-info"><b>AI Mô phỏng</b><span>1.240 phiên đang hoạt động</span></div>
                            <div class="ai-status"><i></i>Online</div>
                        </div>
                        <div class="ai-row">
                            <div class="ai-ic">📖</div>
                            <div class="ai-info"><b>AI Truyện tương tác</b><span>Đang bảo trì nhẹ</span></div>
                            <div class="ai-status" style="color:var(--yellow);"><i
                                    style="background:var(--yellow); box-shadow:0 0 6px var(--yellow);"></i>Bảo trì
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h3>🏆 Cuộc thi đang diễn ra</h3>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <div style="display:flex; justify-content:space-between; font-size:12.5px;"><span>Tuần lễ An
                                    toàn giao thông toàn quốc</span><span style="color:rgba(255,255,255,0.42);">4.200
                                    lượt tham gia</span></div>
                            <div style="display:flex; justify-content:space-between; font-size:12.5px;"><span>Thử thách
                                    "Biển báo thông minh"</span><span style="color:rgba(255,255,255,0.42);">1.850 lượt
                                    tham gia</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <section id="duyet-review"><?php require __DIR__ . '/partials/review-moderation-section.php'; ?></section>
        </main>
    </div>

    <script src="assets/js/main.js?v=5"></script>
    <script src="assets/js/dashboard-admin.js?v=5"></script>
</body>

</html>
