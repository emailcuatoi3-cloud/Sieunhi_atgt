<?php
require_once __DIR__ . '/auth.php';
$user = currentUser();
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script>
    (function() {
        try {
            document.documentElement.setAttribute(
                "data-theme",
                localStorage.getItem("sieu-nhi-theme") || "dark",
            );
        } catch (e) {}
    })();
    </script>
    <title>SIÊU NHÍ AN TOÀN GIAO THÔNG AI</title>
    <meta name="description"
        content="Nền tảng AI giáo dục an toàn giao thông cho học sinh Việt Nam — Học thông minh, Đi an toàn, Vì tương lai Việt Nam." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
        href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css?v=9" />
</head>

<body>
    <!-- ============ NAVBAR ============ -->
    <nav class="navbar" id="navbar">
        <div class="nav-inner">
            <a href="index.php" class="logo">
                <span class="logo-badge">🤖</span>
                SIÊU NHÍ <span class="logo-text-en">AI</span>
            </a>

            <ul class="nav-menu">
                <li><a href="index.php" class="active">Trang chủ</a></li>
                <li><a href="ai-gia-su.php">AI Gia sư</a></li>
                <li><a href="ai-camera.php">AI Camera</a></li>
                <li><a href="ai-mo-phong.php">Mô phỏng</a></li>
                <li><a href="ai-truyen-tranh.php">Truyện tranh</a></li>
                <li><a href="game-mini.php">Thử thách</a></li>
                <?php if ($user && $user['role'] === 'phuhuynh'): ?>
                <li><a href="dashboard-phu-huynh.php">Phụ huynh</a></li>
                <?php endif; ?>
                <?php if ($user && $user['role'] === 'giaovien'): ?>
                <li><a href="dashboard-giao-vien.php">Giáo viên</a></li>
                <?php endif; ?>
                <?php if ($user && $user['role'] === 'hocsinh'): ?>
                <li><a href="dashboard-hoc-sinh.php">Học sinh</a></li>
                <?php endif; ?>
                <?php if ($user && $user['role'] === 'admin'): ?>
                <li><a href="dashboard-admin.php">Quản trị</a></li>
                <?php endif; ?>
            </ul>

            <div class="nav-actions">
                <button class="icon-btn nav-extra" aria-label="Tìm kiếm">🔍</button>
                <button class="icon-btn nav-extra" aria-label="Thông báo">🔔</button>
                <div class="lang-toggle" role="group" aria-label="Chọn ngôn ngữ">
                    <span class="on" style="cursor: pointer">VN</span>
                    <span style="cursor: pointer">EN</span>
                </div>
                <button class="icon-btn theme-toggle" id="themeToggle" aria-label="Chế độ tối">
                    🌙
                </button>
                <?php if ($user): ?>
                <a href="<?= e(ROLE_DASHBOARDS[$user['role']] ?? 'index.php') ?>"
                    class="btn btn-ghost desktop-only"><?= e($user['avatar']) ?> <?= e($user['name']) ?></a>
                <a href="logout.php" class="btn btn-primary-sm">Đăng xuất</a>
                <?php else: ?>
                <a href="dang-nhap.php" class="btn btn-ghost desktop-only">Đăng nhập</a>
                <a href="dang-ky.php" class="btn btn-primary-sm">Đăng ký</a>
                <?php endif; ?>
                <button class="icon-btn nav-burger" aria-label="Menu">☰</button>
            </div>
        </div>
    </nav>

    <!-- ============ HERO ============ -->
    <header class="hero" id="hero">
        <div class="hero-city" aria-hidden="true">
            <svg viewBox="0 0 1440 800" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#1B2E8C" />
                        <stop offset="55%" stop-color="#3A2B8F" />
                        <stop offset="100%" stop-color="#160B45" />
                    </linearGradient>
                    <linearGradient id="bld1" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2A3A8F" />
                        <stop offset="100%" stop-color="#171C55" />
                    </linearGradient>
                    <linearGradient id="bld2" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#3C2E8C" />
                        <stop offset="100%" stop-color="#1B1450" />
                    </linearGradient>
                    <radialGradient id="sun" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#8FE9FF" stop-opacity="0.9" />
                        <stop offset="100%" stop-color="#8FE9FF" stop-opacity="0" />
                    </radialGradient>
                </defs>
                <rect width="1440" height="800" fill="url(#sky)" />
                <circle cx="1180" cy="140" r="180" fill="url(#sun)" />

                <g opacity="0.55">
                    <rect x="40" y="380" width="80" height="260" fill="url(#bld1)" />
                    <rect x="140" y="320" width="60" height="320" fill="url(#bld2)" />
                    <rect x="220" y="410" width="90" height="230" fill="url(#bld1)" />
                    <rect x="960" y="360" width="70" height="280" fill="url(#bld2)" />
                    <rect x="1050" y="300" width="55" height="340" fill="url(#bld1)" />
                    <rect x="1130" y="400" width="85" height="240" fill="url(#bld2)" />
                    <rect x="1240" y="340" width="65" height="300" fill="url(#bld1)" />
                    <rect x="1330" y="390" width="90" height="250" fill="url(#bld2)" />
                </g>

                <g>
                    <rect x="0" y="480" width="130" height="220" fill="#141B57" />
                    <rect x="150" y="440" width="110" height="260" fill="#161E5E" />
                    <rect x="280" y="500" width="95" height="200" fill="#141B57" />
                    <rect x="1020" y="460" width="120" height="240" fill="#161E5E" />
                    <rect x="1160" y="500" width="100" height="200" fill="#141B57" />
                    <rect x="1280" y="430" width="130" height="270" fill="#161E5E" />
                    <g fill="#7FE3FF" opacity="0.85">
                        <rect x="14" y="500" width="6" height="8" />
                        <rect x="30" y="500" width="6" height="8" />
                        <rect x="46" y="520" width="6" height="8" />
                        <rect x="14" y="540" width="6" height="8" />
                        <rect x="46" y="560" width="6" height="8" />
                        <rect x="90" y="540" width="6" height="8" />
                        <rect x="170" y="470" width="6" height="8" />
                        <rect x="190" y="490" width="6" height="8" />
                        <rect x="210" y="470" width="6" height="8" />
                        <rect x="230" y="520" width="6" height="8" />
                        <rect x="170" y="540" width="6" height="8" />
                        <rect x="1040" y="500" width="6" height="8" />
                        <rect x="1060" y="490" width="6" height="8" />
                        <rect x="1090" y="520" width="6" height="8" />
                        <rect x="1300" y="470" width="6" height="8" />
                        <rect x="1330" y="490" width="6" height="8" />
                        <rect x="1360" y="470" width="6" height="8" />
                        <rect x="1300" y="530" width="6" height="8" />
                        <rect x="1360" y="550" width="6" height="8" />
                    </g>
                </g>

                <path d="M0,760 L1440,760 L1440,800 L0,800 Z" fill="#0E1240" />
                <path d="M-100,760 C300,700 1140,700 1540,760 L1540,800 L-100,800 Z" fill="#12163F" />
                <g stroke="#8FE3FF" stroke-width="6" stroke-dasharray="26 22" opacity="0.55">
                    <path d="M-50,782 C350,738 1090,738 1490,782" fill="none" />
                </g>

                <g fill="#E9F6FF" opacity="0.9">
                    <rect x="560" y="742" width="18" height="42" transform="skewX(-8)" />
                    <rect x="600" y="742" width="18" height="42" transform="skewX(-8)" />
                    <rect x="640" y="742" width="18" height="42" transform="skewX(-8)" />
                    <rect x="680" y="742" width="18" height="42" transform="skewX(-8)" />
                    <rect x="720" y="742" width="18" height="42" transform="skewX(-8)" />
                    <rect x="760" y="742" width="18" height="42" transform="skewX(-8)" />
                </g>

                <g transform="translate(880,560)">
                    <rect x="-4" y="0" width="8" height="180" fill="#2C356E" />
                    <rect x="-22" y="-58" width="44" height="70" rx="12" fill="#1B2260" stroke="#4C5AC9"
                        stroke-width="2" />
                    <circle cx="0" cy="-38" r="8" fill="#FBBF24" />
                    <circle cx="0" cy="-14" r="8" fill="#34D399" />
                    <circle cx="0" cy="-38" r="14" fill="#FBBF24" opacity="0.25" />
                    <circle cx="0" cy="-14" r="14" fill="#34D399" opacity="0.3" />
                </g>

                <g fill="#2FA97A" opacity="0.85">
                    <circle cx="420" cy="710" r="26" />
                    <rect x="415" y="710" width="10" height="30" fill="#3A2A1F" />
                    <circle cx="1000" cy="705" r="22" />
                    <rect x="995" y="705" width="8" height="26" fill="#3A2A1F" />
                </g>

                <g opacity="0.7">
                    <ellipse cx="230" cy="230" rx="90" ry="30" fill="none" stroke="#7FE3FF" stroke-width="2" />
                    <ellipse cx="230" cy="230" rx="60" ry="18" fill="none" stroke="#7FE3FF" stroke-width="1.4"
                        opacity="0.6" />
                </g>
            </svg>
        </div>
        <div class="hero-veil"></div>

        <div class="wrap hero-grid">
            <!-- LEFT -->
            <div class="hero-left">
                <span class="eyebrow-pill"><span class="dot"></span> Nền tảng AI giáo dục an toàn giao thông
                    #1 Việt Nam</span>
                <h1>SIÊU NHÍ AN TOÀN<br />GIAO THÔNG AI</h1>
                <p class="subtitle">
                    AI đồng hành cùng trẻ em Việt Nam học và thực hành an toàn giao
                    thông thông qua trò chơi, mô phỏng và trí tuệ nhân tạo. Học thông
                    minh — Đi an toàn — Vì tương lai Việt Nam.
                </p>

                <div class="cta-row">
                    <a href="ai-gia-su.php" class="cta-btn cta-primary" data-ripple>🚀 Bắt đầu học</a>
                    <a href="ai-gia-su.php" class="cta-btn cta-secondary" data-ripple>✨ Trải nghiệm AI</a>
                    <a href="ai-mo-phong.php" class="cta-btn cta-secondary" data-ripple>▶️ Xem Demo</a>
                    <a href="#features" class="cta-btn cta-tertiary">Khám phá tính năng →</a>
                </div>

                <div class="hero-trust">
                    <div class="avatar-stack" aria-hidden="true">
                        <span>👦</span><span>👧</span><span>🧒</span><span>👩‍🏫</span>
                    </div>
                    <p>
                        <strong>128.000+</strong> học sinh &amp;
                        <strong>500+</strong> trường học đang tin dùng
                    </p>
                </div>
            </div>

            <!-- RIGHT -->
            <div class="hero-visual">
                <div class="visual-stage">
                    <div class="robot-scene">
                        <div class="scene-glow"></div>

                        <div class="hologram-tag">⚠️ Biển báo AI</div>

                        <div class="float-card fc-1">
                            <span class="ic">🪖</span><span class="lb">Mũ bảo hiểm</span>
                        </div>
                        <div class="float-card fc-2">
                            <span class="ic">🚦</span><span class="lb">Đèn giao thông</span>
                        </div>
                        <div class="float-card fc-3">
                            <span class="ic">🚸</span><span class="lb">Vạch qua đường</span>
                        </div>
                        <div class="float-card fc-4">
                            <span class="ic">🚲</span><span class="lb">Xe đạp</span>
                        </div>
                        <div class="float-card fc-5">
                            <span class="ic">🚌</span><span class="lb">Xe buýt trường</span>
                        </div>
                        <div class="float-card fc-6">
                            <span class="ic">🛑</span><span class="lb">Biển báo</span>
                        </div>
                        <div class="float-card fc-7">
                            <span class="ic">🧠</span><span class="lb">Chip AI</span>
                        </div>

                        <svg class="robot-illustration" viewBox="0 0 520 560" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="robotBody" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#EAF6FF" />
                                    <stop offset="100%" stop-color="#BFDCFF" />
                                </linearGradient>
                                <linearGradient id="robotVest" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#FBBF24" />
                                    <stop offset="100%" stop-color="#F59E0B" />
                                </linearGradient>
                                <linearGradient id="kidShirt1" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#60C6FA" />
                                    <stop offset="100%" stop-color="#3B82F6" />
                                </linearGradient>
                                <linearGradient id="kidShirt2" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#F792C7" />
                                    <stop offset="100%" stop-color="#EC4899" />
                                </linearGradient>
                                <radialGradient id="groundShadow" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" stop-color="#000" stop-opacity="0.28" />
                                    <stop offset="100%" stop-color="#000" stop-opacity="0" />
                                </radialGradient>
                            </defs>
                            <ellipse cx="260" cy="530" rx="210" ry="24" fill="url(#groundShadow)" />

                            <!-- girl (left) -->
                            <g transform="translate(60,255)">
                                <ellipse cx="55" cy="270" rx="46" ry="12" fill="#000" opacity="0.12" />
                                <rect x="30" y="150" width="50" height="90" rx="20" fill="url(#kidShirt2)" />
                                <rect x="20" y="230" width="22" height="55" rx="10" fill="#3B2A6B" />
                                <rect x="68" y="230" width="22" height="55" rx="10" fill="#3B2A6B" />
                                <rect x="14" y="278" width="34" height="12" rx="6" fill="#1F1440" />
                                <rect x="62" y="278" width="34" height="12" rx="6" fill="#1F1440" />
                                <circle cx="55" cy="110" r="42" fill="#FFDFC2" />
                                <path d="M13,105 C13,60 97,60 97,105 C97,80 85,66 55,66 C25,66 13,80 13,105 Z"
                                    fill="#3A2417" />
                                <circle cx="40" cy="112" r="4.5" fill="#2B1B12" />
                                <circle cx="70" cy="112" r="4.5" fill="#2B1B12" />
                                <path d="M42,130 Q55,140 68,130" stroke="#B4552F" stroke-width="3" fill="none"
                                    stroke-linecap="round" />
                                <path d="M20,150 Q0,175 15,205" stroke="#F792C7" stroke-width="16" fill="none"
                                    stroke-linecap="round" />
                                <path d="M90,150 Q118,168 110,198" stroke="#F792C7" stroke-width="16" fill="none"
                                    stroke-linecap="round" />
                                <ellipse cx="55" cy="72" rx="46" ry="18" fill="#FBBF24" />
                                <ellipse cx="55" cy="66" rx="44" ry="16" fill="#FDCB4E" />
                            </g>

                            <!-- boy (right) -->
                            <g transform="translate(390,265)">
                                <ellipse cx="45" cy="260" rx="44" ry="12" fill="#000" opacity="0.12" />
                                <rect x="20" y="145" width="50" height="86" rx="18" fill="url(#kidShirt1)" />
                                <rect x="12" y="222" width="20" height="52" rx="9" fill="#22315E" />
                                <rect x="56" y="222" width="20" height="52" rx="9" fill="#22315E" />
                                <rect x="6" y="270" width="30" height="11" rx="5" fill="#101830" />
                                <rect x="50" y="270" width="30" height="11" rx="5" fill="#101830" />
                                <circle cx="45" cy="108" r="40" fill="#FFE0C4" />
                                <path d="M6,100 C6,58 84,58 84,100 C74,78 60,84 45,84 C30,84 16,78 6,100 Z"
                                    fill="#241914" />
                                <circle cx="31" cy="110" r="4.3" fill="#2B1B12" />
                                <circle cx="59" cy="110" r="4.3" fill="#2B1B12" />
                                <path d="M33,128 Q45,136 57,128" stroke="#B4552F" stroke-width="3" fill="none"
                                    stroke-linecap="round" />
                                <path d="M14,150 Q-8,168 4,196" stroke="#3B82F6" stroke-width="15" fill="none"
                                    stroke-linecap="round" />
                                <path d="M76,150 Q104,162 100,188" stroke="#3B82F6" stroke-width="15" fill="none"
                                    stroke-linecap="round" />
                                <ellipse cx="45" cy="70" rx="42" ry="17" fill="#3B82F6" />
                                <ellipse cx="45" cy="64" rx="40" ry="15" fill="#5B9CF9" />
                            </g>

                            <!-- AI robot (center) -->
                            <g transform="translate(160,40)">
                                <ellipse cx="95" cy="470" rx="100" ry="20" fill="#000" opacity="0.15" />
                                <line x1="95" y1="8" x2="95" y2="34" stroke="#8FA6FF" stroke-width="5"
                                    stroke-linecap="round" />
                                <circle cx="95" cy="6" r="9" fill="#22D3EE">
                                    <animate attributeName="opacity" values="1;0.4;1" dur="1.6s"
                                        repeatCount="indefinite" />
                                </circle>
                                <rect x="30" y="34" width="130" height="104" rx="34" fill="url(#robotBody)"
                                    stroke="#9FC3F5" stroke-width="3" />
                                <rect x="52" y="66" width="86" height="42" rx="21" fill="#0E1A4D" />
                                <circle cx="78" cy="87" r="10" fill="#67E8F9">
                                    <animate attributeName="cy" values="87;84;87" dur="2.4s" repeatCount="indefinite" />
                                </circle>
                                <circle cx="112" cy="87" r="10" fill="#67E8F9">
                                    <animate attributeName="cy" values="87;84;87" dur="2.4s" repeatCount="indefinite" />
                                </circle>
                                <path d="M78,116 Q95,128 112,116" stroke="#67E8F9" stroke-width="5" fill="none"
                                    stroke-linecap="round" />
                                <rect x="14" y="72" width="14" height="28" rx="7" fill="#8FA6FF" />
                                <rect x="162" y="72" width="14" height="28" rx="7" fill="#8FA6FF" />
                                <rect x="18" y="150" width="154" height="170" rx="34" fill="url(#robotBody)"
                                    stroke="#9FC3F5" stroke-width="3" />
                                <path d="M18,182 L95,168 L172,182 L172,300 Q95,320 18,300 Z" fill="url(#robotVest)"
                                    opacity="0.96" />
                                <path d="M50,180 L95,196 L140,180" stroke="#FFF7E0" stroke-width="9" fill="none"
                                    stroke-linecap="round" opacity="0.9" />
                                <rect x="80" y="220" width="30" height="30" rx="8" fill="#0E1A4D" />
                                <circle cx="95" cy="235" r="9" fill="#22D3EE">
                                    <animate attributeName="r" values="9;11;9" dur="1.8s" repeatCount="indefinite" />
                                </circle>
                                <path d="M170,210 Q235,190 268,150" stroke="#BFDCFF" stroke-width="24" fill="none"
                                    stroke-linecap="round" />
                                <path d="M170,210 Q235,190 268,150" stroke="#9FC3F5" stroke-width="24" fill="none"
                                    stroke-linecap="round" opacity="0.35" />
                                <circle cx="272" cy="144" r="17" fill="#EAF6FF" stroke="#9FC3F5" stroke-width="3" />
                                <path d="M20,220 Q-20,250 -6,300" stroke="#BFDCFF" stroke-width="24" fill="none"
                                    stroke-linecap="round" />
                                <circle cx="-8" cy="306" r="17" fill="#EAF6FF" stroke="#9FC3F5" stroke-width="3" />
                                <rect x="38" y="308" width="34" height="90" rx="16" fill="#BFDCFF" stroke="#9FC3F5"
                                    stroke-width="3" />
                                <rect x="118" y="308" width="34" height="90" rx="16" fill="#BFDCFF" stroke="#9FC3F5"
                                    stroke-width="3" />
                                <rect x="28" y="392" width="54" height="26" rx="13" fill="#3B82F6" />
                                <rect x="108" y="392" width="54" height="26" rx="13" fill="#3B82F6" />
                                <g transform="translate(250,40)">
                                    <ellipse cx="70" cy="150" rx="46" ry="10" fill="#22D3EE" opacity="0.18" />
                                    <rect x="35" y="20" width="70" height="70" rx="16" fill="rgba(34,211,238,0.16)"
                                        stroke="#67E8F9" stroke-width="2.5" />
                                    <path d="M70,36 L92,74 L48,74 Z" fill="none" stroke="#67E8F9" stroke-width="4"
                                        stroke-linejoin="round" />
                                    <line x1="70" y1="50" x2="70" y2="62" stroke="#67E8F9" stroke-width="4"
                                        stroke-linecap="round" />
                                    <circle cx="70" cy="70" r="1.6" fill="#67E8F9" />
                                </g>
                            </g>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ============ STATS ============ -->
    <section class="stats-section">
        <div class="wrap">
            <div class="section-head">
                <span class="kicker">Con số biết nói</span>
                <h2>Được hàng trăm nghìn Siêu Nhí tin tưởng</h2>
                <p>
                    Nền tảng AI học an toàn giao thông được triển khai tại các trường
                    học trên khắp Việt Nam, đồng hành cùng học sinh, phụ huynh và giáo
                    viên mỗi ngày.
                </p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🎓</div>
                    <div class="stat-number">
                        <span class="counter" data-target="128000" data-suffix="+">0</span>
                    </div>
                    <div class="stat-label">Học sinh</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-number">
                        <span class="counter" data-target="640" data-suffix="+">0</span>
                    </div>
                    <div class="stat-label">Bài học</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🤖</div>
                    <div class="stat-number">
                        <span class="counter" data-target="2100000" data-suffix="+">0</span>
                    </div>
                    <div class="stat-label">Câu hỏi AI</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-number">
                        <span class="counter" data-target="93" data-suffix="%">0</span>
                    </div>
                    <div class="stat-label">Tỷ lệ hoàn thành</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏅</div>
                    <div class="stat-number">
                        <span class="counter" data-target="41200" data-suffix="+">0</span>
                    </div>
                    <div class="stat-label">Chứng nhận</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ FEATURES ============ -->
    <section class="features-section" id="features">
        <div class="wrap">
            <div class="section-head">
                <span class="kicker">Hệ sinh thái AI</span>
                <h2>Một trợ lý, mười khả năng</h2>
                <p>
                    Mỗi tính năng đều được xây dựng để biến kiến thức giao thông khô
                    khan thành trải nghiệm sống động, cá nhân hoá cho từng em nhỏ.
                </p>
            </div>
            <div class="feat-grid">
                <a href="ai-gia-su.php" style="text-decoration: none">
                    <div class="feat-card">
                        <div class="feat-icon">🎓</div>
                        <h4>AI Gia sư</h4>
                        <p>
                            Trò chuyện, giải đáp mọi thắc mắc về luật giao thông bằng văn
                            bản và giọng nói.
                        </p>
                    </div>
                </a>
                <a href="ai-camera.php" style="text-decoration: none">
                    <div class="feat-card">
                        <div class="feat-icon">📷</div>
                        <h4>AI Camera Vision</h4>
                        <p>
                            Nhận diện mũ bảo hiểm, biển báo, đèn tín hiệu qua camera hoặc
                            ảnh chụp.
                        </p>
                    </div>
                </a>
                <div class="feat-card">
                    <div class="feat-icon">💬</div>
                    <h4>AI Chat &amp; Voice</h4>
                    <p>
                        Hỏi đáp tự nhiên bằng giọng nói, nhận phản hồi minh hoạ trực quan.
                    </p>
                </div>
                <div class="feat-card">
                    <div class="feat-icon">📝</div>
                    <h4>AI Quiz</h4>
                    <p>
                        Câu hỏi trắc nghiệm sinh động, độ khó tự điều chỉnh theo năng lực
                        học sinh.
                    </p>
                </div>
                <a href="ai-truyen-tranh.php" style="text-decoration: none">
                    <div class="feat-card">
                        <div class="feat-icon">📖</div>
                        <h4>AI Truyện tương tác</h4>
                        <p>
                            Truyện tranh có nhân vật Việt Nam, mỗi lựa chọn dẫn đến kết thúc
                            khác nhau.
                        </p>
                    </div>
                </a>
                <a href="game-mini.php" style="text-decoration: none">
                    <div class="feat-card">
                        <div class="feat-icon">🕹️</div>
                        <h4>AI Game hoá</h4>
                        <p>
                            5 trò chơi mô phỏng tình huống giao thông thực tế: qua đường,
                            đội mũ, tìm biển báo, chọn đường an toàn và cứu thành phố.
                        </p>
                    </div>
                </a>
                <?php if (!$user || in_array($user['role'], ['phuhuynh', 'admin'], true)): ?>
                <a href="dashboard-phu-huynh.php" style="text-decoration: none">
                    <div class="feat-card">
                        <div class="feat-icon">📈</div>
                        <h4>AI Báo cáo</h4>
                        <p>
                            Theo dõi tiến bộ, điểm mạnh — điểm yếu, gửi báo cáo cho phụ
                            huynh.
                        </p>
                    </div>
                </a>
                <?php else: ?>
                <div class="feat-card">
                    <div class="feat-icon">📈</div>
                    <h4>AI Báo cáo</h4>
                    <p>
                        Theo dõi tiến bộ, điểm mạnh — điểm yếu, gửi báo cáo cho phụ
                        huynh.
                    </p>
                </div>
                <?php endif; ?>
                <div class="feat-card">
                    <div class="feat-icon">🧭</div>
                    <h4>AI Dashboard</h4>
                    <p>
                        Tổng quan trực quan cho học sinh, giáo viên và phụ huynh trên một
                        nền tảng.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ AI TUTOR PREVIEW ============ -->
    <section class="tutor-section" id="tutor">
        <div class="wrap">
            <div class="section-head">
                <span class="kicker">Trung tâm nền tảng</span>
                <h2>AI Gia sư — người bạn luôn lắng nghe</h2>
                <p>
                    Hỏi bất cứ điều gì về giao thông. AI trả lời bằng văn bản, giọng nói
                    và hình minh hoạ, ngay lập tức.
                </p>
            </div>
            <div class="tutor-panel">
                <div class="tutor-side">
                    <h5>Hội thoại gần đây</h5>
                    <div class="conv-item active">🟢 Đèn vàng có được đi?</div>
                    <div class="conv-item">⛑️ Đội mũ bảo hiểm đúng cách</div>
                    <div class="conv-item">🚑 Gặp xe cứu thương</div>
                    <div class="conv-item">🚸 Biển báo trường học</div>
                    <div class="conv-item">🚲 Đi xe đạp an toàn</div>
                </div>
                <div class="tutor-main">
                    <div class="msg user">
                        <div class="msg-avatar">🧒</div>
                        <div class="msg-bubble">
                            Con có được sang đường khi đèn vàng không ạ?
                        </div>
                    </div>
                    <div class="msg bot">
                        <div class="msg-avatar">🤖</div>
                        <div class="msg-bubble">
                            Đèn vàng nghĩa là chuẩn bị dừng lại, không phải để đi nhanh qua.
                            Nếu con đang đứng chờ ở vỉa hè, hãy đợi đèn xanh cho người đi bộ
                            nhé. An toàn luôn là ưu tiên số một! 🚦
                        </div>
                    </div>
                    <div class="msg user">
                        <div class="msg-avatar">🧒</div>
                        <div class="msg-bubble">Đây là biển gì vậy AI?</div>
                    </div>
                    <div class="msg bot">
                        <div class="msg-avatar">🤖</div>
                        <div class="msg-bubble">
                            📸 Mình thấy con vừa gửi ảnh biển báo hình tam giác viền đỏ —
                            đây là biển báo nguy hiểm, cảnh báo phía trước có khúc cua gấp.
                            Con nhớ giảm tốc độ nhé!
                        </div>
                    </div>
                    <div class="suggest-row">
                        <div class="suggest-chip">Đội mũ như thế nào là đúng?</div>
                        <div class="suggest-chip">
                            Con nên làm gì khi gặp xe cứu thương?
                        </div>
                        <div class="suggest-chip">Vạch kẻ đường dành cho ai?</div>
                    </div>
                    <div class="tutor-input">
                        <span class="icon-btn-sm">📎</span>
                        <input type="text" placeholder="Hỏi AI Gia sư điều gì đó về giao thông..." />
                        <span class="icon-btn-sm">🎤</span>
                        <a href="ai-gia-su.php" class="icon-btn-sm send" style="text-decoration: none">➤</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ SIMULATION + GAMIFICATION ============ -->
    <section class="spotlight-section" id="simulation">
        <div class="wrap">
            <div class="section-head">
                <span class="kicker">Học qua trải nghiệm</span>
                <h2>Mô phỏng thật, thử thách vô tận</h2>
                <p>
                    AI dựng ngã tư, thời tiết và tình huống giao thông ngẫu nhiên mỗi
                    lượt chơi — không lần nào giống lần nào.
                </p>
            </div>
            <div class="spot-grid">
                <div class="spot-card">
                    <h3>
                        <a href="ai-mo-phong.php" style="color: inherit; text-decoration: none">🚦 AI Mô phỏng giao
                            thông →</a>
                    </h3>
                    <p class="desc">
                        Chọn Đi, Dừng, Quan sát hay Rẽ tại ngã tư ảo. AI chấm điểm và giải
                        thích ngay khi có lựa chọn sai.
                    </p>
                    <div class="sim-board">
                        <div class="cross"></div>
                        <div class="cross2"></div>
                        <div class="signal"><i class="on"></i><i></i><i></i></div>
                        <div class="veh v1">🚌</div>
                        <div class="veh v2">🚗</div>
                        <div class="veh v3">🚲</div>
                        <div class="sim-badge">🌧️ Trời mưa · Ban ngày</div>
                    </div>
                </div>
                <div class="spot-card">
                    <h3>
                        <a href="game-mini.php" style="color: inherit; text-decoration: none">🏆 Gamification &amp;
                            Thành tích →</a>
                    </h3>
                    <p class="desc">
                        Mỗi bài học đều tích XP, mở khoá huy hiệu và danh hiệu — biến việc
                        học thành hành trình phiêu lưu.
                    </p>
                    <div class="game-row">
                        <div class="avatar-ring">🧒</div>
                        <div class="lvl-track">
                            <div class="lvl-top">
                                <span>Cấp độ 7 · Chiến binh An toàn</span><span>680 / 1000 XP</span>
                            </div>
                            <div class="lvl-bar">
                                <div class="lvl-fill"></div>
                            </div>
                        </div>
                    </div>
                    <div class="badge-row">
                        <div class="badge-chip">🥇 Vượt đèn đỏ: 0 lần</div>
                        <div class="badge-chip">⛑️ Đội mũ chuẩn</div>
                        <div class="badge-chip">🔥 Streak 12 ngày</div>
                        <div class="badge-chip">🏅 Chứng nhận Cấp 1</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ ROLES / DASHBOARDS ============ -->
    <section class="roles-section" id="dashboards">
        <div class="wrap">
            <div class="section-head">
                <span class="kicker">Kết nối mọi vai trò</span>
                <h2>Một nền tảng, bốn góc nhìn</h2>
                <p>
                    Học sinh học tập, phụ huynh theo dõi, giáo viên quản lý lớp, và quản
                    trị viên vận hành hệ thống — tất cả đồng bộ theo thời gian thực.
                </p>
            </div>
            <div class="role-grid">
                <?php if (!$user || in_array($user['role'], ['hocsinh', 'admin'], true)): ?>
                <div class="role-card">
                    <div class="r-icon">🧒</div>
                    <h4>Học sinh</h4>
                    <p>Tiến độ, XP, streak, nhiệm vụ hôm nay và khoá học AI gợi ý.</p>
                    <a href="dashboard-hoc-sinh.php">Xem Dashboard →</a>
                </div>
                <?php endif; ?>
                <?php if (!$user || in_array($user['role'], ['phuhuynh', 'admin'], true)): ?>
                <div class="role-card">
                    <div class="r-icon">👨‍👩‍👧</div>
                    <h4>Phụ huynh</h4>
                    <p>
                        Thời gian học, điểm mạnh — điểm yếu, mức độ nguy hiểm, xuất báo
                        cáo PDF.
                    </p>
                    <a href="dashboard-phu-huynh.php">Xem Dashboard →</a>
                </div>
                <?php endif; ?>
                <?php if (!$user || in_array($user['role'], ['giaovien', 'admin'], true)): ?>
                <div class="role-card">
                    <div class="r-icon">👩‍🏫</div>
                    <h4>Giáo viên</h4>
                    <p>Quản lý lớp học, tạo bài học, heatmap tiến độ, xuất Excel.</p>
                    <a href="dashboard-giao-vien.php">Xem Dashboard →</a>
                </div>
                <?php endif; ?>
                <?php if (!$user || $user['role'] === 'admin'): ?>
                <div class="role-card">
                    <div class="r-icon">🛡️</div>
                    <h4>Quản trị viên</h4>
                    <p>
                        Người dùng, nội dung, AI, cuộc thi, thống kê và nhật ký hệ thống.
                    </p>
                    <a href="dashboard-admin.php">Xem Dashboard →</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ============ CTA ============ -->
    <section class="cta-section">
        <div class="wrap">
            <div class="cta-band">
                <h2>Sẵn sàng để AI đồng hành cùng con trên mọi nẻo đường?</h2>
                <p>
                    Tham gia cùng hơn 128.000 học sinh đang học an toàn giao thông theo
                    cách thông minh hơn, vui hơn và hiệu quả hơn mỗi ngày.
                </p>
                <div class="cta-row-center">
                    <a href="ai-gia-su.php" class="cta-btn cta-primary" data-ripple>🚀 Bắt đầu miễn phí</a>
                    <a href="#" class="cta-btn cta-secondary" data-ripple>📅 Đặt lịch demo cho trường</a>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ FOOTER ============ -->
    <footer class="site-footer" id="footer">
        <div class="wrap">
            <div class="foot-grid">
                <div class="foot-brand">
                    <div class="logo">
                        <span class="logo-badge">🤖</span>SIÊU NHÍ AI
                    </div>
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
                    <a href="#">Nhà trường</a>
                </div>
                <div class="foot-col">
                    <h5>Công ty</h5>
                    <a href="#">Về chúng tôi</a><a href="#">Đối tác</a><a href="#">Tin tức</a><a href="#">Liên hệ</a>
                </div>
                <div class="foot-col">
                    <h5>Pháp lý</h5>
                    <a href="#">Điều khoản</a><a href="#">Chính sách bảo mật</a><a href="#">Tải ứng dụng</a>
                </div>
            </div>
            <div class="foot-bottom">
                <span>© 2026 Siêu Nhí An Toàn Giao Thông AI · AI Traffic Hero</span>
                <div class="social-row">
                    <a href="#">𝕏</a><a href="#">f</a><a href="#">▶</a><a href="#">◎</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js?v=5"></script>
</body>

</html>