// Quản lý trạng thái camera và quét AI
let stream = null;
let currentFacingMode = "user";
let isCameraMode = true;
let hasCapturedImage = false; // true khi đã có ảnh thật (chụp hoặc tải lên), tránh bị placeholder ghi đè

// (Đã bỏ mảng kịch bản giả lập ngẫu nhiên — giờ kết quả lấy từ AI thật qua analyze-image.php)

document.addEventListener("DOMContentLoaded", () => {
  initWebcam();
  initDragAndDrop();
});

// 1. CHUYỂN ĐỔI GIỮA TÁP CAMERA & TẢI ẢNH LÊN
function switchTab(mode) {
  const tabs = document.querySelectorAll(".cam-tab");
  const btnShutter = document.getElementById("btnShutter");
  const btnToggleCam = document.getElementById("btnToggleCam");
  const previewImg = document.getElementById("previewImg");
  const video = document.getElementById("webcam");

  if (mode === "camera") {
    isCameraMode = true;
    if (tabs[0]) tabs[0].classList.add("active");
    if (tabs[1]) tabs[1].classList.remove("active");

    if (video) video.style.display = "block";
    if (previewImg) previewImg.style.display = "none";
    if (btnShutter) btnShutter.style.display = "inline-flex";
    if (btnToggleCam) btnToggleCam.style.display = "inline-flex";

    initWebcam();
  } else {
    isCameraMode = false;
    if (tabs[0]) tabs[0].classList.remove("active");
    if (tabs[1]) tabs[1].classList.add("active");

    stopWebcam();
    if (video) video.style.display = "none";
    if (previewImg) previewImg.style.display = "block";
    if (btnShutter) btnShutter.style.display = "none";
    if (btnToggleCam) btnToggleCam.style.display = "none";

    // Chỉ đặt ảnh SVG placeholder nếu CHƯA từng có ảnh thật nào được chụp/tải lên
    // (trước đây code đoán qua previewImg.src nên vô tình xoá luôn ảnh thật mỗi lần chuyển tab)
    if (previewImg && !hasCapturedImage) {
      previewImg.src =
        "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24' fill='none' stroke='%23ffffff33' stroke-width='1'><rect width='20' height='20' x='2' y='2' rx='2'/><circle cx='9' cy='9' r='2'/><path d='m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21'/></svg>";
    }
  }
}

// 2. KHỞI TẠO LUỒNG CAMERA
async function initWebcam() {
  if (!isCameraMode) return;
  stopWebcam();

  const video = document.getElementById("webcam");
  const constraints = {
    video: {
      facingMode: currentFacingMode,
      width: { ideal: 1280 },
      height: { ideal: 720 },
    },
    audio: false,
  };

  try {
    stream = await navigator.mediaDevices.getUserMedia(constraints);
    if (video) video.srcObject = stream;
    const resText = document.getElementById("camResolution");
    if (resText) resText.innerText = "1280×720 · Active";
  } catch (error) {
    console.error("Không thể mở camera:", error);
    const resText = document.getElementById("camResolution");
    if (resText) resText.innerText = "Camera bị chặn hoặc lỗi";
  }
}

function stopWebcam() {
  if (stream) {
    stream.getTracks().forEach((track) => track.stop());
    stream = null;
  }
}

function toggleCamera() {
  currentFacingMode = currentFacingMode === "user" ? "environment" : "user";
  initWebcam();
}

// 3. CHỤP ẢNH TỪ CAMERA TRỰC TIẾP
function capturePhoto() {
  const video = document.getElementById("webcam");
  const canvas = document.getElementById("captureCanvas");
  const previewImg = document.getElementById("previewImg");

  if (!stream || !video || !canvas || !previewImg) return;

  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext("2d");
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

  const dataUrl = canvas.toDataURL("image/jpeg");
  video.style.display = "none";
  previewImg.src = dataUrl;
  previewImg.style.display = "block";
  hasCapturedImage = true;

  runAIScan();
}

// 4. XỬ LÝ KHI TẢI FILE ẢNH LÊN
function triggerUpload() {
  const fileInput = document.getElementById("fileInput");
  if (fileInput) fileInput.click();
}

function handleFileSelect(event) {
  const file = event.target.files[0];
  processUploadedFile(file);
}

function processUploadedFile(file) {
  if (!file || !file.type.startsWith("image/")) {
    alert("Vui lòng chọn tệp tin hình ảnh hợp lệ!");
    return;
  }

  const reader = new FileReader();
  reader.onload = function (e) {
    // Ép trạng thái về Tab Upload để đồng bộ giao diện ẩn/hiện nút bấm
    isCameraMode = false;

    const tabs = document.querySelectorAll(".cam-tab");
    if (tabs.length >= 2) {
      tabs[0].classList.remove("active");
      tabs[1].classList.add("active");
    }

    stopWebcam();

    const video = document.getElementById("webcam");
    const previewImg = document.getElementById("previewImg");
    const btnShutter = document.getElementById("btnShutter");
    const btnToggleCam = document.getElementById("btnToggleCam");

    if (video) video.style.display = "none";
    if (btnShutter) btnShutter.style.display = "none";
    if (btnToggleCam) btnToggleCam.style.display = "none";

    // Đưa dữ liệu ảnh mới vào khung preview hình ảnh
    if (previewImg) {
      previewImg.src = e.target.result;
      previewImg.style.display = "block";
    }
    hasCapturedImage = true;

    // Kích hoạt ngay lập tức hiệu ứng quét laser AI
    runAIScan();
  };
  reader.readAsDataURL(file);
}

// Xử lý kéo thả ảnh trực tiếp vào vùng camera hiển thị
function initDragAndDrop() {
  const dropZone = document.getElementById("dropZone");
  if (!dropZone) return;

  ["dragenter", "dragover"].forEach((eventName) => {
    dropZone.addEventListener(
      eventName,
      (e) => {
        e.preventDefault();
        dropZone.style.borderColor = "#00ffcc";
      },
      false,
    );
  });

  ["dragleave", "drop"].forEach((eventName) => {
    dropZone.addEventListener(
      eventName,
      (e) => {
        e.preventDefault();
        dropZone.style.borderColor = "transparent";
      },
      false,
    );
  });

  dropZone.addEventListener(
    "drop",
    (e) => {
      const dt = e.dataTransfer;
      const file = dt.files[0];
      processUploadedFile(file);
    },
    false,
  );
}

// 5. GỬI ẢNH LÊN AI ĐỂ PHÂN TÍCH THẬT VÀ TRẢ KẾT QUẢ ĐÚNG / SAI
function runAIScan() {
  const scanLine = document.getElementById("scanLine");
  const aiStatus = document.getElementById("aiStatus");
  const previewImg = document.getElementById("previewImg");

  if (scanLine) {
    scanLine.style.animation = "none";
    void scanLine.offsetWidth;
    scanLine.style.animation = "scan 1.5s infinite ease-in-out";
  }
  if (aiStatus) {
    aiStatus.innerHTML = `<i></i> AI ĐANG QUÉT HÌNH ẢNH...`;
    aiStatus.style.color = "#ffcc00";
  }

  // Lấy dữ liệu ảnh hiện tại (dạng data:image/...;base64,....)
  const imageData = previewImg ? previewImg.src : null;

  if (!imageData || !imageData.startsWith("data:image")) {
    finishScanWithError("Không tìm thấy dữ liệu ảnh để quét.");
    return;
  }

  fetch("analyze-image.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ image: imageData }),
  })
    .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
    .then(({ ok, data }) => {
      if (!ok || data.error) {
        console.error("Lỗi phân tích AI:", data.error || data);
        finishScanWithError(
          data.error || "AI phân tích thất bại, vui lòng thử lại.",
        );
        return;
      }
      finishScanWithResult(data);
    })
    .catch((err) => {
      console.error("Lỗi kết nối tới AI:", err);
      finishScanWithError(
        "Không kết nối được tới máy chủ AI. Vui lòng kiểm tra mạng và thử lại.",
      );
    });
}

// Khi AI trả kết quả thành công
function finishScanWithResult(data) {
  const scanLine = document.getElementById("scanLine");
  const aiStatus = document.getElementById("aiStatus");

  if (scanLine) scanLine.style.animation = "none";
  if (aiStatus) {
    aiStatus.innerHTML = `<i></i> PHÂN TÍCH HOÀN TẤT`;
    aiStatus.style.color = "#00ffcc";
  }

  updateResultPanel(data);
}

// Khi có lỗi (mất mạng, API lỗi, v.v.) — không quét ảo, báo thật cho người dùng
function finishScanWithError(message) {
  const scanLine = document.getElementById("scanLine");
  const aiStatus = document.getElementById("aiStatus");

  if (scanLine) scanLine.style.animation = "none";
  if (aiStatus) {
    aiStatus.innerHTML = `<i></i> LỖI PHÂN TÍCH`;
    aiStatus.style.color = "#ff5c5c";
  }

  const accBadge = document.getElementById("accBadge");
  if (accBadge) accBadge.innerText = "⚠ Lỗi";

  const resultPanel = document.querySelector(".result-panel");
  if (resultPanel) {
    const oldItems = resultPanel.querySelectorAll(".detect-item");
    oldItems.forEach((item) => item.remove());
    const adviceBox = resultPanel.querySelector(".advice-box");
    if (adviceBox) {
      const pTag = adviceBox.querySelector("p");
      if (pTag) pTag.innerText = message;
    }
  }
}

// Hàm phân tích và trả về thông tin màu sắc/mức độ an toàn của từng kết quả
function getSeverityInfo(item) {
  const rawSev = String(item.severity || item.type || "").toLowerCase();
  const titleText = String(item.title || "").toLowerCase();
  const descText = String(item.desc || "").toLowerCase();
  const fullText = `${rawSev} ${titleText} ${descText}`;

  // 1. Lỗi nghiêm trọng / Danger / Red (Màu đỏ)
  if (
    rawSev === "danger" ||
    rawSev === "error" ||
    rawSev === "critical" ||
    fullText.includes("nghiêm trọng") ||
    fullText.includes("nguy hiểm") ||
    fullText.includes("cực độ") ||
    fullText.includes("vượt đèn") ||
    fullText.includes("ngược chiều") ||
    fullText.includes("không đội mũ") ||
    fullText.includes("vi phạm nghiêm trọng")
  ) {
    return {
      class: "danger",
      badge: "🚨 Lỗi nghiêm trọng",
      levelText: "🔴 Nguy hiểm",
      barColor: "#ef4444",
      barWidth: "100%",
      shadow: "0 0 10px rgba(239, 68, 68, 0.7)",
    };
  }

  // 2. Lỗi bình thường / Warning / Yellow (Màu vàng)
  if (
    rawSev === "warn" ||
    rawSev === "warning" ||
    fullText.includes("bình thường") ||
    fullText.includes("nhẹ") ||
    fullText.includes("chú ý") ||
    fullText.includes("cảnh báo") ||
    fullText.includes("nhắc nhở")
  ) {
    return {
      class: "warn",
      badge: "⚠️ Lỗi bình thường",
      levelText: "🟡 Cảnh báo",
      barColor: "#f59e0b",
      barWidth: "70%",
      shadow: "0 0 10px rgba(245, 158, 11, 0.7)",
    };
  }

  // 3. An toàn / OK / Green (Màu xanh)
  return {
    class: "ok",
    badge: "✓ Đạt chuẩn",
    levelText: "🟢 An toàn",
    barColor: "#22c55e",
    barWidth: "95%",
    shadow: "0 0 10px rgba(34, 197, 94, 0.7)",
  };
}

// Cập nhật giao diện danh sách kết quả phân tích Đúng/Sai và Lời khuyên
function updateResultPanel(data) {
  const accBadge = document.getElementById("accBadge");
  if (accBadge) accBadge.innerText = data.accuracy;

  const resultPanel = document.querySelector(".result-panel");
  if (!resultPanel) return;

  const oldItems = resultPanel.querySelectorAll(".detect-item");
  oldItems.forEach((item) => item.remove());

  const adviceBox = resultPanel.querySelector(".advice-box");

  data.items.forEach((item) => {
    const itemHtml = document.createElement("div");
    const sevInfo = getSeverityInfo(item);

    itemHtml.className = `detect-item ${sevInfo.class}`;
    itemHtml.innerHTML = `
      <div class="d-icon">${item.icon || "🚦"}</div>
      <div class="d-info">
        <div class="detect-header">
          <b>${item.title}</b>
          <span class="detect-badge ${sevInfo.class}">${sevInfo.badge}</span>
        </div>
        <span>${item.desc}</span>
      </div>
      <div class="detect-bar-box">
        <span class="detect-bar-text ${sevInfo.class}">${sevInfo.levelText}</span>
        <div class="detect-bar">
          <i style="width: ${sevInfo.barWidth}; background: ${sevInfo.barColor}; box-shadow: ${sevInfo.shadow};"></i>
        </div>
      </div>
    `;

    if (adviceBox) {
      resultPanel.insertBefore(itemHtml, adviceBox);
    } else {
      resultPanel.appendChild(itemHtml);
    }
  });

  if (adviceBox) {
    const pTag = adviceBox.querySelector("p");
    if (pTag) pTag.innerText = data.advice;
  }
}

// 6. HÀM QUÉT LẠI
function rescan() {
  if (isCameraMode) {
    const video = document.getElementById("webcam");
    const previewImg = document.getElementById("previewImg");
    if (video) video.style.display = "block";
    if (previewImg) previewImg.style.display = "none";
    initWebcam();
  } else {
    // Nếu đang ở tab tải ảnh, bấm Quét lại sẽ kích hoạt hiệu ứng quét dữ liệu hiện tại
    if (!hasCapturedImage) {
      alert("Vui lòng chụp ảnh hoặc tải ảnh lên trước khi quét!");
      return;
    }
    runAIScan();
  }
}
