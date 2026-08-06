<?php
require_once __DIR__ . '/game-progress.php';
requireRole(['hocsinh', 'admin']);
$user = currentUser();

if ($user['role'] === 'hocsinh') {
    $progress = getStudentProgress($user['id']);
    $badgeCount = countStudentBadges($user['id']);
} else {
    $progress = ['xp' => 0, 'coin' => 0, 'streak_days' => 0, 'level' => 1];
    $badgeCount = 0;
}
$level = (int)$progress['level'];
$xpIntoLevel = (int)$progress['xp'] % 100;
$xpNeeded = xpForNextLevel($level);
$ageGroup = $user['age_group'] ?? '6-8';
$mastery = [];
$nextLesson = null;
try {
    $db = new DB_UTILS();
    $rows = $db->getAll('SELECT skill_key, score FROM mastery_scores WHERE student_id = ?', [$user['id']]);
    foreach ($rows as $row) $mastery[$row['skill_key']] = (int)round((float)$row['score']);
    $nextLesson = $db->getOne('SELECT id, title, summary, age_group FROM lessons WHERE status = "published" AND age_group = ? ORDER BY difficulty, id LIMIT 1', [$ageGroup]);
} catch (Throwable $ignored) { /* schema migration may not have run yet */ }
$skillCards = [
    ['key' => 'signals', 'label' => 'Đèn tín hiệu & biển báo', 'icon' => '🚦', 'fallback' => 0],
    ['key' => 'pedestrian', 'label' => 'Qua đường an toàn', 'icon' => '🚸', 'fallback' => 0],
    ['key' => 'helmet', 'label' => 'Mũ bảo hiểm', 'icon' => '⛑️', 'fallback' => 0],
    ['key' => 'emergency', 'label' => 'Tình huống khẩn cấp', 'icon' => '🚑', 'fallback' => 0],
];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
    (function() {
        try {
            document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme") || "light");
        } catch (e) {}
    })();
    </script>
    <title>Dashboard học sinh · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="stylesheet" href="assets/css/fonts.css?v=1">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=21">
    <link rel="stylesheet" href="assets/css/kid-components.css?v=1">
</head>

<body>

    <div class="app">
        <aside class="sidebar">
            <div>
                <div class="side-brand">
                    <div class="mark">🤖</div>SIÊU NHÍ AI
                </div>
                <a class="side-back" href="index.php">← Về trang chủ</a>
            </div>
            <a class="side-link active" href="dashboard-hoc-sinh.php"><span class="ic">🏠</span> Trang chủ</a>
            <a class="side-link" href="ai-gia-su.php"><span class="ic">🎓</span> Khoá học AI</a>
            <a class="side-link" href="game-mini.php"><span class="ic">🏆</span> Thử thách</a>
            <a class="side-link" href="bang-xep-hang.php"><span class="ic">🥇</span> Thành tích</a>
            <a class="side-link" href="game-mini.php"><span class="ic">📜</span> Chứng chỉ</a>
            <a class="side-link" href="index.php#dashboards"><span class="ic">👥</span> Cùng gia đình</a>
            <a class="side-link" href="bang-xep-hang.php"><span class="ic">📊</span> Bảng xếp hạng</a>
            <div class="side-divider"></div>
            <a class="side-link" href="ai-gia-su.php"><span class="ic">💬</span> AI Gia sư</a>
            <a class="side-link" href="ai-camera.php"><span class="ic">📷</span> AI Camera</a>
            <a class="side-link" href="ai-mo-phong.php"><span class="ic">🚦</span> Mô phỏng</a>
            <div class="side-divider"></div>
            <a class="side-link" href="dang-ky.php"><span class="ic">⚙️</span> Cài đặt hồ sơ</a>
            <a class="side-link" href="logout.php"><span class="ic">🚪</span> Đăng xuất</a>

            <div class="sidebar-foot">
                <div class="av"><?= e($user['avatar']) ?></div>
                <div class="txt"><b><?= e($user['name']) ?></b><span>Lớp 3A · Trường TH Việt Nam</span></div>
            </div>
        </aside>

        <main class="main">
            <div class="top-row">
                <div class="greet">
                    <h1>Chào buổi sáng, <?= e($user['name']) ?>! 👋</h1>
                    <p>Lộ trình <?= e($ageGroup) ?> tuổi · chọn một nhiệm vụ nhỏ để tiến bộ hôm nay.</p>
                </div>
                <div class="top-actions">
                    <div class="icon-btn">🔔</div>
                    <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
                    <div class="icon-btn">⚙️</div>
                </div>
            </div>

            <div class="stat-row">
                <div class="mini-stat-card">
                    <div class="st-ic">⭐</div><b><?= (int)$progress['xp'] ?></b><span>Điểm XP</span>
                </div>
                <div class="mini-stat-card">
                    <div class="st-ic">🪙</div>
                    <b><?= number_format((int)$progress['coin'], 0, ',', '.') ?></b><span>Coin</span>
                </div>
                <div class="mini-stat-card">
                    <div class="st-ic">🔥</div><b><?= (int)$progress['streak_days'] ?> ngày</b><span>Streak học
                        tập</span>
                </div>
                <div class="mini-stat-card">
                    <div class="st-ic">🏅</div><b><?= (int)$badgeCount ?></b><span>Huy hiệu đạt được</span>
                </div>
            </div>

            <div class="grid2">
                <div>
                    <div class="card">
                        <div class="card-head">
                            <h3>🗺️ Bản đồ hành trình an toàn</h3><a href="ai-gia-su.php">Học tiếp →</a>
                        </div>
                        <?php foreach ($skillCards as $skill): $score = $mastery[$skill['key']] ?? $skill['fallback']; ?>
                        <div class="prog-item">
                            <div class="prog-top"><span><?= e($skill['icon'] . ' ' . $skill['label']) ?></span><span><?= $score ?>%</span></div>
                            <div class="mini-bar full"><i style="width:<?= $score ?>%;"></i></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h3>✅ Nhiệm vụ hôm nay</h3><span style="font-size:12px; color:rgba(255,255,255,0.42);"
                                id="missionCount">1/4 hoàn thành</span>
                        </div>
                        <div id="missionList"></div>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h3>🎯 Khoá học AI gợi ý</h3><a href="ai-gia-su.php">Xem lộ trình</a>
                        </div>
                        <div class="course-row">
                            <div class="course-card">
                                <div class="course-ic">🚦</div>
                                <div class="course-info"><b><?= e($nextLesson['title'] ?? 'Qua đường an toàn') ?></b><span><?= e($nextLesson['summary'] ?? 'Bài học được chọn theo nhóm tuổi của con') ?></span></div><span class="course-tag">Đề xuất</span>
                            </div>
                            <div class="course-card">
                                <div class="course-ic">🚲</div>
                                <div class="course-info"><b>Đi xe đạp an toàn</b><span>6 bài học · Đang học dở
                                        45%</span></div><span class="course-tag">Tiếp tục</span>
                            </div>
                            <div class="course-card">
                                <div class="course-ic">🚑</div>
                                <div class="course-info"><b>Xử lý tình huống khẩn cấp</b><span>5 bài học · Mới</span>
                                </div><span class="course-tag">Mới</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="card level-card">
                        <div class="level-ring">Lv<?= $level ?></div>
                        <div>
                            <b style="display:block; font-size:14.5px; font-weight:700; margin-bottom:4px;">Chiến binh
                                An toàn</b>
                            <span style="font-size:12px; color:rgba(255,255,255,0.5);"><?= (int)$progress['xp'] ?> /
                                <?= $xpNeeded ?> XP đến cấp <?= $level + 1 ?></span>
                            <div class="mini-bar full" style="margin-top:8px; width:160px;"><i
                                    style="width:<?= $xpIntoLevel ?>%;"></i></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h3>🗓️ Lịch học hôm nay</h3>
                        </div>
                        <div class="sched-item">
                            <div class="sched-time">08:00</div>
                            <div class="sched-info"><b>Bài học: Biển báo cấm</b><span>AI Gia sư · 15 phút</span></div>
                        </div>
                        <div class="sched-item">
                            <div class="sched-time">14:30</div>
                            <div class="sched-info"><b>Mô phỏng ngã tư</b><span>Thử thách hằng ngày · 10 phút</span>
                            </div>
                        </div>
                        <div class="sched-item">
                            <div class="sched-time">19:00</div>
                            <div class="sched-info"><b>Ôn tập cùng phụ huynh</b><span>Xem báo cáo tuần</span></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h3>🔔 Thông báo</h3><a href="dashboard-hoc-sinh.php">Cập nhật lại</a>
                        </div>
                        <div class="notif-item">
                            <div class="notif-ic">🏅</div>
                            <div>
                                <div class="notif-text">Con vừa đạt huy hiệu <b>"Đội mũ chuẩn"</b>!</div>
                                <div class="notif-time">10 phút trước</div>
                            </div>
                        </div>
                        <div class="notif-item">
                            <div class="notif-ic">👨‍👩‍👧</div>
                            <div>
                                <div class="notif-text">Phụ huynh đã xem báo cáo tuần của con.</div>
                                <div class="notif-time">1 giờ trước</div>
                            </div>
                        </div>
                        <div class="notif-item">
                            <div class="notif-ic">🎮</div>
                            <div>
                                <div class="notif-text">Bạn Na vừa vượt qua thử thách "Siêu nhí xử lý tình huống".</div>
                                <div class="notif-time">Hôm qua</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/main.js?v=5"></script>
    <script src="assets/js/dashboard-hoc-sinh.js?v=5"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
