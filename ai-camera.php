<!DOCTYPE html>
<html lang="vi">

<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
    (function() {
        try {
            document.documentElement.setAttribute("data-theme", localStorage.getItem("sieu-nhi-theme") || "dark");
        } catch (e) {}
    })();
    </script>
    <title>AI Camera · Siêu Nhí An Toàn Giao Thông AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
    <link rel="stylesheet" href="assets/css/shared-pages.css?v=25">
</head>

<body>

    <nav class="navbar static" id="navbar">
        <div class="nav-inner">
            <a href="index.php" class="logo"><span class="logo-badge">🤖</span>SIÊU NHÍ <span
                    class="logo-text-en">AI</span></a>
            <a class="back-link" href="index.php">← Về trang chủ</a>
            <div class="nav-actions">
                <button class="icon-btn theme-toggle" aria-label="Chế độ tối">🌙</button>
                <a class="btn btn-ghost" href="ai-gia-su.php">🎓 AI Gia sư</a>
                <a class="btn btn-ghost" href="ai-mo-phong.php">🚦 Mô phỏng</a>
                <a class="btn btn-ghost" href="game-mini.php">🎮 Game Mini</a>
            </div>
        </div>
    </nav>

    <div class="page-head wrap">
        <span class="eyebrow-pill"><span class="dot"></span> AI Camera Vision</span>
        <h1>AI nhìn thấy những gì con nhìn thấy</h1>
        <p>Bật camera hoặc tải ảnh lên — AI sẽ nhận diện mũ bảo hiểm, biển báo, đèn tín hiệu và đưa ra lời khuyên an
            toàn ngay lập tức.</p>
    </div>

    <!-- KHU VỰC HIỂN THỊ CHÍNH (CHIA LÀM 2 CỘT TRÁI - PHẢI) -->
    <div class="wrap cam-layout">

        <!-- CỘT TRÁI: KHUNG CHỨA CAMERA VÀ ẢNH QUÉT -->
        <div class="cam-panel">
            <div class="cam-tabs">
                <div class="cam-tab active" onclick="switchTab('camera')">📷 Camera trực tiếp</div>
                <div class="cam-tab" onclick="switchTab('upload')">🖼️ Tải ảnh lên</div>
            </div>

            <div class="cam-view" id="dropZone">
                <div class="cam-hud-top">
                    <span class="rec-dot" id="aiStatus"><i></i> AI ĐANG PHÂN TÍCH</span>
                    <span id="camResolution">1280×720 · Active</span>
                </div>

                <div class="cam-scene">
                    <!-- Luồng Camera trực tiếp -->
                    <video id="webcam" autoplay playsinline style="width:100%; height:100%; object-fit:cover;"></video>

                    <!-- Ảnh preview khi tải lên hoặc chụp -->
                    <img id="previewImg" style="width:100%; height:100%; object-fit:contain; display:none;"
                        alt="Preview">

                    <!-- Canvas ẩn dùng để chụp ảnh -->
                    <canvas id="captureCanvas" style="display:none;"></canvas>

                    <!-- Thanh quét laze chạy qua chạy lại -->
                    <div class="scan-line" id="scanLine"></div>
                </div>
            </div>

            <div class="cam-controls">
                <button class="ctrl-btn" id="btnToggleCam" title="Đổi camera" onclick="toggleCamera()">🔄</button>
                <button class="ctrl-btn shutter" id="btnShutter" title="Chụp ảnh" onclick="capturePhoto()">📸</button>
                <button class="ctrl-btn" id="btnUploadTrigger" title="Tải ảnh lên"
                    onclick="triggerUpload()">🖼️</button>
                <input type="file" id="fileInput" accept="image/*" style="display: none;"
                    onchange="handleFileSelect(event)">
            </div>
            <div class="upload-hint" id="uploadHint">Hỗ trợ JPG, PNG · Hoặc kéo thả ảnh vào khung camera</div>
        </div> <!-- ĐÓNG CỘT TRÁI -->

        <!-- CỘT PHẢI: BẢNG KẾT QUẢ ĐÚNG / SAI CỦA AI -->
        <div class="cam-panel result-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; font-size: 18px;">📋 Kết quả phân tích AI</h3>
                <span id="accBadge"
                    style="background: rgba(0, 255, 204, 0.1); color: #00ffcc; padding: 4px 10px; border-radius: 20px; font-size: 13px; font-weight: 600;">⚡
                    Đang đợi dữ liệu...</span>
            </div>

            <!-- Mẫu lỗi Đúng/Sai (Sẽ được JS thay thế tự động khi quét ảnh) -->
            <div class="detect-item ok">
                <div class="d-icon">⛑️</div>
                <div class="d-info">
                    <div class="detect-header">
                        <b>Mũ bảo hiểm — Đạt chuẩn</b>
                        <span class="detect-badge ok">✓ Đạt chuẩn</span>
                    </div>
                    <span>Cài quai đúng cách, vừa khít đầu</span>
                </div>
                <div class="detect-bar-box">
                    <span class="detect-bar-text ok">🟢 An toàn</span>
                    <div class="detect-bar">
                        <i style="width: 95%; background: #22c55e; box-shadow: 0 0 10px rgba(34, 197, 94, 0.7);"></i>
                    </div>
                </div>
            </div>

            <!-- Lời khuyên từ AI -->
            <div class="advice-box">
                <h4>💡 Lời khuyên từ AI</h4>
                <p>Hệ thống đang sẵn sàng. Hãy bấm nút Chụp ảnh hoặc Tải ảnh lên để AI bắt đầu quét lỗi Đúng/Sai về an
                    toàn giao thông nhé!</p>
            </div>

            <div class="result-actions">
                <button class="btn btn-primary-sm" style="flex:1; justify-content:center;" onclick="rescan()">🔁 Quét
                    lại</button>
                <button class="btn btn-ghost" style="flex:1; justify-content:center;">📄 Lưu kết quả</button>
            </div>

            <div style="margin-top: 20px;">
                <h4
                    style="font-size:12.5px; text-transform:uppercase; letter-spacing:.06em; color:rgba(255,255,255,0.42); margin-bottom:10px;">
                    Lịch sử quét gần đây</h4>
                <div class="history-strip">
                    <div class="hist-thumb ok">⛑️<span class="hist-status">✓</span></div>
                    <div class="hist-thumb ok">🚦<span class="hist-status">✓</span></div>
                    <div class="hist-thumb warn">🚲<span class="hist-status">!</span></div>
                    <div class="hist-thumb ok">🚸<span class="hist-status">✓</span></div>
                    <div class="hist-thumb ok">🦓<span class="hist-status">✓</span></div>
                </div>
            </div>
        </div> <!-- ĐÓNG CỘT PHẢI -->

    </div> <!-- ĐÓNG .cam-layout -->


    <!-- KHỐI PHẠM VI NHẬN DIỆN (NẰM ĐỘC LẬP Ở DƯỚI CÙNG) -->
    <section class="catalog">
        <div class="wrap">
            <div class="section-head">
                <span class="kicker">Phạm vi nhận diện</span>
                <h2>AI Camera nhận diện được những gì?</h2>
                <p>Được huấn luyện chuyên biệt cho bối cảnh giao thông Việt Nam, từ biển báo đến hành vi an toàn hằng
                    ngày.</p>
            </div>
            <div class="cat-grid">
                <div class="cat-card">
                    <div class="ic">⛑️</div>
                    <h5>Mũ bảo hiểm</h5><span>Đội đúng / sai cách</span>
                </div>
                <div class="cat-card">
                    <div class="ic">🚸</div>
                    <h5>Biển báo</h5><span>Cấm, nguy hiểm, chỉ dẫn</span>
                </div>
                <div class="cat-card">
                    <div class="ic">🚦</div>
                    <h5>Đèn giao thông</h5><span>Xanh, vàng, đỏ</span>
                </div>
                <div class="cat-card">
                    <div class="ic">🚶</div>
                    <h5>Người đi bộ</h5><span>Vị trí, hành vi qua đường</span>
                </div>
                <div class="cat-card">
                    <div class="ic">🚲</div>
                    <h5>Xe đạp</h5><span>Làn đường, tốc độ</span>
                </div>
                <div class="cat-card">
                    <div class="ic">🏍️</div>
                    <h5>Xe máy</h5><span>Khoảng cách an toàn</span>
                </div>
                <div class="cat-card">
                    <div class="ic">🦓</div>
                    <h5>Vạch qua đường</h5><span>Đi đúng vạch kẻ</span>
                </div>
                <div class="cat-card">
                    <div class="ic">🚑</div>
                    <h5>Xe ưu tiên</h5><span>Cứu thương, cứu hoả</span>
                </div>
            </div>
        </div>
    </section>

    <script src="assets/js/main.js?v=8"></script>
    <script src="assets/js/ai-camera.js?v=9"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>