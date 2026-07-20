/* comic-scenes.js — Hệ thống minh hoạ cho "AI Truyện tranh"
   =========================================================
   Thay thế 1 emoji lẻ giữa màn hình bằng cảnh minh hoạ ĐẦY ĐỦ cho mỗi
   chương: có bầu trời, đường phố/ngã tư, trường học, cây xanh, đèn tín
   hiệu, phương tiện, và nhân vật Bo với biểu cảm/tư thế phù hợp nội
   dung — bố cục có tiền cảnh/trung cảnh/hậu cảnh như 1 khung truyện
   tranh thật, không dùng emoji/icon đơn lẻ.
   ========================================================= */

const ComicScenes = (() => {
  /* ---------------------------------------------------------------
     NHÂN VẬT BO — học sinh tiểu học VN, đồng phục, balo, 5 biểu cảm
     --------------------------------------------------------------- */
  const FACES = {
    happy: {
      browL: "M-14 -6 Q-8 -9 -2 -6",
      browR: "M2 -6 Q8 -9 14 -6",
      mouth: "M-9 9 Q0 17 9 9",
      eyeR: 3.2,
      cheeks: true,
    },
    surprised: {
      browL: "M-14 -8 Q-8 -12 -2 -8",
      browR: "M2 -8 Q8 -12 14 -8",
      mouth: "M0 11 m-4 0 a4 5 0 1 0 8 0 a4 5 0 1 0 -8 0",
      eyeR: 3.8,
      cheeks: false,
    },
    worried: {
      browL: "M-14 -4 Q-8 0 -2 -4",
      browR: "M2 -4 Q8 0 14 -4",
      mouth: "M-8 12 Q0 8 8 12",
      eyeR: 3.0,
      cheeks: false,
    },
    think: {
      browL: "M-14 -7 Q-8 -9 -2 -6",
      browR: "M2 -6 Q8 -10 14 -7",
      mouth: "M-6 10 Q0 11 7 9",
      eyeR: 3.0,
      cheeks: false,
    },
    confident: {
      browL: "M-14 -7 Q-8 -10 -2 -7",
      browR: "M2 -7 Q8 -10 14 -7",
      mouth: "M-9 8 Q0 18 9 8",
      eyeR: 3.2,
      cheeks: true,
    },
  };

  /** Vẽ nhân vật Bo. pose: 'stand'|'run'|'wave'|'step-back'|'point'  */
  function bo({
    expression = "think",
    pose = "stand",
    flip = false,
    scale = 1,
  } = {}) {
    const f = FACES[expression] || FACES.think;
    const legs = {
      stand: "M-9 40 L-11 68 M9 40 L11 68",
      run: "M-9 40 L-18 62 M9 40 L4 66",
      wave: "M-9 40 L-11 68 M9 40 L11 68",
      "step-back": "M-9 40 L-16 64 M9 40 L14 64",
      point: "M-9 40 L-11 68 M9 40 L11 68",
    }[pose];
    const arms = {
      stand:
        '<path d="M-19 6 Q-26 20 -22 34" /><path d="M19 6 Q26 20 22 34" />',
      run: '<path d="M-19 6 Q-30 10 -30 -2" /><path d="M19 6 Q30 22 26 34" />',
      wave: '<path d="M-19 6 Q-26 20 -22 34" /><path d="M19 6 Q30 -6 26 -20" />',
      "step-back":
        '<path d="M-19 6 Q-28 4 -30 -6" /><path d="M19 6 Q26 20 22 34" />',
      point: '<path d="M-19 6 Q-26 20 -22 34" /><path d="M19 6 Q34 2 40 4" />',
    }[pose];

    return `
      <g transform="translate(0,0) scale(${flip ? -scale : scale},${scale})">
        <ellipse cx="0" cy="72" rx="20" ry="5" fill="rgba(0,0,0,0.25)"/>
        <path d="${legs}" stroke="#2b3358" stroke-width="7" stroke-linecap="round" fill="none"/>
        <rect x="-13" y="60" width="10" height="8" rx="3" fill="#1c2340"/>
        <rect x="3" y="60" width="10" height="8" rx="3" fill="#1c2340"/>
        <path d="M-18 4 Q0 -4 18 4 L16 42 Q0 48 -16 42 Z" fill="#3b82f6"/>
        <path d="M-18 4 Q0 -4 18 4 L17 14 Q0 8 -17 14 Z" fill="#2f68d1"/>
        <rect x="-8" y="14" width="16" height="20" rx="3" fill="#fff" opacity="0.85"/>
        <rect x="-15" y="18" width="9" height="18" rx="4" fill="#f59e0b"/>
        <g fill="none" stroke="#3b82f6" stroke-width="9" stroke-linecap="round">${arms}</g>
        <circle cx="0" cy="-18" r="24" fill="#ffd8a8"/>
        <circle cx="-19" cy="-14" r="6" fill="#ffd8a8"/>
        <circle cx="19" cy="-14" r="6" fill="#ffd8a8"/>
        <path d="M-24 -22 Q-26 -42 0 -44 Q26 -42 24 -22 Q22 -34 0 -35 Q-22 -34 -24 -22 Z" fill="#2b1a12"/>
        <path d="${f.browL}" stroke="#2b1a12" stroke-width="2.6" fill="none" stroke-linecap="round"/>
        <path d="${f.browR}" stroke="#2b1a12" stroke-width="2.6" fill="none" stroke-linecap="round"/>
        <circle cx="-8" cy="-14" r="${f.eyeR}" fill="#2b1a12"/>
        <circle cx="8" cy="-14" r="${f.eyeR}" fill="#2b1a12"/>
        <circle cx="-9" cy="-15.5" r="1" fill="#fff"/>
        <circle cx="7" cy="-15.5" r="1" fill="#fff"/>
        ${f.cheeks ? '<ellipse cx="-15" cy="-6" rx="4" ry="2.6" fill="#ff9b9b" opacity="0.55"/><ellipse cx="15" cy="-6" rx="4" ry="2.6" fill="#ff9b9b" opacity="0.55"/>' : ""}
        <path d="${f.mouth}" stroke="#a85d3a" stroke-width="2.2" fill="none" stroke-linecap="round"/>
      </g>
    `;
  }

  /** Bạn Na — trang phục khác màu để phân biệt với Bo */
  function na({ pose = "wave", scale = 1 } = {}) {
    const arms =
      pose === "wave"
        ? '<path d="M-19 6 Q-26 20 -22 34" /><path d="M19 6 Q30 -6 26 -20" />'
        : '<path d="M-19 6 Q-26 20 -22 34" /><path d="M19 6 Q26 20 22 34" />';
    return `
      <g transform="scale(${scale})">
        <ellipse cx="0" cy="72" rx="20" ry="5" fill="rgba(0,0,0,0.25)"/>
        <path d="M-9 40 L-11 68 M9 40 L11 68" stroke="#2b3358" stroke-width="7" stroke-linecap="round" fill="none"/>
        <rect x="-13" y="60" width="10" height="8" rx="3" fill="#1c2340"/>
        <rect x="3" y="60" width="10" height="8" rx="3" fill="#1c2340"/>
        <path d="M-18 4 Q0 -4 18 4 L16 42 Q0 48 -16 42 Z" fill="#ec4899"/>
        <path d="M-18 4 Q0 -4 18 4 L17 14 Q0 8 -17 14 Z" fill="#d63384"/>
        <rect x="-8" y="14" width="16" height="20" rx="3" fill="#fff" opacity="0.85"/>
        <g fill="none" stroke="#ec4899" stroke-width="9" stroke-linecap="round">${arms}</g>
        <circle cx="0" cy="-18" r="23" fill="#ffe0bd"/>
        <path d="M-23 -20 Q-24 -46 0 -42 Q10 -50 22 -38 Q26 -20 20 -14 Q22 -30 0 -32 Q-20 -30 -20 -16 Z" fill="#4a2e1a"/>
        <path d="M-13 -8 Q-7 -12 -1 -8" stroke="#2b1a12" stroke-width="2.2" fill="none" stroke-linecap="round"/>
        <path d="M1 -8 Q7 -12 13 -8" stroke="#2b1a12" stroke-width="2.2" fill="none" stroke-linecap="round"/>
        <circle cx="-7" cy="-15" r="3" fill="#2b1a12"/>
        <circle cx="7" cy="-15" r="3" fill="#2b1a12"/>
        <path d="M-8 9 Q0 15 8 9" stroke="#a85d3a" stroke-width="2.2" fill="none" stroke-linecap="round"/>
      </g>
    `;
  }

  /** Robot AI Gia sư nhỏ, biểu cảm thân thiện */
  function robot({ mood = "happy", scale = 1 } = {}) {
    const mouth =
      mood === "happy"
        ? "M-7 6 Q0 12 7 6"
        : mood === "concern"
          ? "M-6 8 Q0 5 6 8"
          : "M-6 7 L6 7";
    return `
      <g transform="scale(${scale})">
        <ellipse cx="0" cy="46" rx="18" ry="4.5" fill="rgba(0,0,0,0.25)"/>
        <rect x="-16" y="-6" width="32" height="26" rx="10" fill="#3b82f6"/>
        <rect x="-16" y="-6" width="32" height="26" rx="10" fill="url(#robotShine)" opacity="0.35"/>
        <circle cx="0" cy="-22" r="18" fill="#60c6fa"/>
        <rect x="-10" y="-28" width="20" height="12" rx="6" fill="#0d1030"/>
        <circle cx="-5" cy="-22" r="3" fill="#7ef7ff"/>
        <circle cx="5" cy="-22" r="3" fill="#7ef7ff"/>
        <path d="${mouth}" stroke="#7ef7ff" stroke-width="2" fill="none" stroke-linecap="round"/>
        <rect x="-3" y="-40" width="6" height="8" rx="2" fill="#8b5cf6"/>
        <circle cx="0" cy="-42" r="3.5" fill="#f4b942"/>
        <path d="M-16 4 Q-26 10 -24 22" stroke="#3b82f6" stroke-width="7" fill="none" stroke-linecap="round"/>
        <path d="M16 4 Q26 10 24 22" stroke="#3b82f6" stroke-width="7" fill="none" stroke-linecap="round"/>
      </g>
    `;
  }

  /* ---------------------------------------------------------------
     THÀNH PHẦN NỀN — trời, đường, trường học, cây, đèn, xe...
     --------------------------------------------------------------- */
  function sky(mood = "morning") {
    const grads = {
      morning: ["#8ec9f2", "#eaf6ff"],
      tense: ["#5a6ba8", "#8f9fd6"],
      bright: ["#6fc7ff", "#fff2cf"],
    };
    const [top, bottom] = grads[mood] || grads.morning;
    return `
      <defs><linearGradient id="skyGrad" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="${top}"/><stop offset="100%" stop-color="${bottom}"/>
      </linearGradient>
      <linearGradient id="robotShine" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#fff"/><stop offset="100%" stop-color="transparent"/>
      </linearGradient></defs>
      <rect width="640" height="360" fill="url(#skyGrad)"/>
      <circle cx="560" cy="60" r="34" fill="#ffe17a" opacity="0.9"/>
      <circle cx="90" cy="50" r="22" fill="#fff" opacity="0.8"/>
      <circle cx="120" cy="55" r="16" fill="#fff" opacity="0.7"/>
      <circle cx="150" cy="48" r="20" fill="#fff" opacity="0.75"/>
    `;
  }

  function schoolBuilding(x, y, scale = 1) {
    return `
      <g transform="translate(${x},${y}) scale(${scale})">
        <rect x="-60" y="-70" width="120" height="70" fill="#f5c98a"/>
        <path d="M-66 -70 L0 -96 L66 -70 Z" fill="#c0532f"/>
        <rect x="-6" y="-96" width="12" height="14" fill="#c0532f"/>
        <rect x="-2" y="-108" width="18" height="4" fill="#2f7a44"/>
        <rect x="-2" y="-112" width="4" height="16" fill="#5b3a22"/>
        <rect x="-46" y="-56" width="16" height="16" rx="2" fill="#60c6fa"/>
        <rect x="-8" y="-56" width="16" height="16" rx="2" fill="#60c6fa"/>
        <rect x="30" y="-56" width="16" height="16" rx="2" fill="#60c6fa"/>
        <rect x="-14" y="-28" width="28" height="28" fill="#8b5cf6"/>
        <circle cx="8" cy="-14" r="1.6" fill="#f4b942"/>
        <rect x="-70" y="0" width="140" height="6" fill="#3d9856"/>
      </g>
    `;
  }

  function treeCluster(x, y, scale = 1) {
    return `
      <g transform="translate(${x},${y}) scale(${scale})">
        <rect x="-3" y="0" width="6" height="18" fill="#5b3a22"/>
        <circle cx="0" cy="-8" r="16" fill="#2f7a44"/>
        <circle cx="-9" cy="-4" r="11" fill="#3d9856"/>
        <circle cx="9" cy="-2" r="11" fill="#3d9856"/>
      </g>
    `;
  }

  function trafficLightPole(x, y, active = "red") {
    return `
      <g transform="translate(${x},${y})">
        <rect x="-2" y="0" width="4" height="46" fill="#555a72"/>
        <rect x="-9" y="-48" width="18" height="48" rx="4" fill="#1a1f3a"/>
        <circle cx="0" cy="-40" r="5" fill="${active === "red" ? "#ff3b3b" : "#4a2222"}"/>
        <circle cx="0" cy="-24" r="5" fill="${active === "yellow" ? "#f4b942" : "#4a3f22"}"/>
        <circle cx="0" cy="-8" r="5" fill="${active === "green" ? "#34d399" : "#1f4a36"}"/>
      </g>
    `;
  }

  function crosswalk(x, y, w = 90) {
    let stripes = "";
    for (let i = 0; i < 6; i++)
      stripes += `<rect x="${x - w / 2 + i * (w / 6)}" y="${y}" width="${w / 10}" height="34" fill="#eef1fb" opacity="0.9"/>`;
    return stripes;
  }

  function roadWithCrosswalk(mood = "day") {
    return `
      <rect x="0" y="240" width="640" height="120" fill="#2a2f45"/>
      <rect x="0" y="234" width="640" height="6" fill="#f4b942" opacity="0.85"/>
      ${crosswalk(320, 250, 130)}
    `;
  }

  function car(x, y, color = "#3b82f6", flip = false) {
    return `
      <g transform="translate(${x},${y}) scale(${flip ? -1 : 1},1)">
        <rect x="-28" y="-14" width="56" height="20" rx="6" fill="${color}"/>
        <rect x="-16" y="-24" width="34" height="14" rx="5" fill="${color}"/>
        <rect x="-12" y="-22" width="26" height="10" rx="2" fill="rgba(20,24,40,0.7)"/>
        <circle cx="-16" cy="8" r="7" fill="#111"/>
        <circle cx="16" cy="8" r="7" fill="#111"/>
        <rect x="24" y="-10" width="3" height="3" fill="#fff59d"/>
      </g>
    `;
  }

  function motorbike(x, y, flip = false, urgent = false) {
    return `
      <g transform="translate(${x},${y}) scale(${flip ? -1 : 1},1)">
        ${urgent ? '<path d="M-30 10 L-42 10 M-30 14 L-46 14" stroke="#e8e8ef" stroke-width="2.5" opacity="0.8"/>' : ""}
        <rect x="-20" y="-4" width="34" height="10" rx="4" fill="#e8574c"/>
        <circle cx="-14" cy="8" r="6" fill="#111"/>
        <circle cx="12" cy="8" r="6" fill="#111"/>
        <circle cx="-2" cy="-12" r="7" fill="#ffd8a8"/>
        <rect x="-6" y="-8" width="9" height="10" fill="#3b82f6"/>
      </g>
    `;
  }

  function bus(x, y, flip = false, braking = false) {
    return `
      <g transform="translate(${x},${y}) scale(${flip ? -1 : 1},1)">
        ${braking ? '<path d="M-46 12 L-60 12 M-46 16 L-64 16" stroke="#e8e8ef" stroke-width="3" opacity="0.85"/>' : ""}
        <rect x="-40" y="-26" width="80" height="34" rx="6" fill="#f4b942"/>
        <rect x="-32" y="-20" width="16" height="12" rx="2" fill="rgba(20,24,40,0.7)"/>
        <rect x="-10" y="-20" width="16" height="12" rx="2" fill="rgba(20,24,40,0.7)"/>
        <rect x="12" y="-20" width="16" height="12" rx="2" fill="rgba(20,24,40,0.7)"/>
        <circle cx="-22" cy="10" r="8" fill="#111"/>
        <circle cx="22" cy="10" r="8" fill="#111"/>
      </g>
    `;
  }

  function policeman(x, y) {
    return `
      <g transform="translate(${x},${y})">
        <ellipse cx="0" cy="46" rx="14" ry="4" fill="rgba(0,0,0,0.25)"/>
        <path d="M-6 26 L-7 44 M6 26 L7 44" stroke="#1a2340" stroke-width="6" stroke-linecap="round"/>
        <rect x="-12" y="0" width="24" height="28" rx="4" fill="#f4b942"/>
        <path d="M-12 4 Q-20 14 -16 24" stroke="#f4b942" stroke-width="6" fill="none" stroke-linecap="round"/>
        <path d="M12 4 Q22 -4 26 -12" stroke="#f4b942" stroke-width="6" fill="none" stroke-linecap="round"/>
        <circle cx="0" cy="-10" r="14" fill="#ffd8a8"/>
        <path d="M-14 -14 Q0 -26 14 -14 L14 -18 Q0 -22 -14 -18 Z" fill="#1a2340"/>
        <circle cx="-5" cy="-9" r="1.6" fill="#2b1a12"/><circle cx="5" cy="-9" r="1.6" fill="#2b1a12"/>
      </g>
    `;
  }

  /* Khung SVG bọc ngoài — mọi cảnh đều dùng chung viewBox 640x360 */
  function frame(inner) {
    return `<svg viewBox="0 0 640 360" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="xMidYMax slice">${inner}</svg>`;
  }
  function wrapCharAt(x, y, s, svgInner) {
    return `<g transform="translate(${x},${y}) scale(${s})">${svgInner}</g>`;
  }

  /* ---------------------------------------------------------------
     9 CẢNH TRUYỆN — mỗi chương 1 illustration riêng, góc máy khác nhau
     --------------------------------------------------------------- */
  const SCENES = {
    /* Chương 1 — Wide shot: Bo trước ngã tư đông xe, phân vân */
    start: () =>
      frame(`
      ${sky("morning")}
      ${schoolBuilding(500, 232, 0.72)}
      ${treeCluster(60, 250, 1.1)}${treeCluster(600, 260, 0.9)}
      ${roadWithCrosswalk()}
      ${trafficLightPole(430, 232, "red")}
      ${car(180, 285, "#8b5cf6")}${motorbike(500, 300, true)}${car(520, 210, "#34d399", true)}
      ${wrapCharAt(200, 300, 1.35, bo({ expression: "think", pose: "stand" }))}
    `),

    /* Chương 2 (sai) — Close up: xe máy phanh gấp, Bo giật mình giữa đường */
    rush: () =>
      frame(`
      ${sky("tense")}
      ${treeCluster(40, 250, 0.8)}
      <rect x="0" y="230" width="640" height="130" fill="#2a2f45"/>
      <rect x="0" y="224" width="640" height="6" fill="#f4b942" opacity="0.7"/>
      ${motorbike(430, 260, true, true)}
      ${wrapCharAt(300, 300, 1.7, bo({ expression: "surprised", pose: "step-back" }))}
      <text x="470" y="200" font-size="46" fill="#fff" font-weight="800" opacity="0.9">!</text>
    `),

    /* Chương 2 (đúng) — Bo tại vạch qua đường, đèn đỏ, Na vẫy gọi */
    safeCross: () =>
      frame(`
      ${sky("morning")}
      ${treeCluster(560, 245, 1)}
      ${roadWithCrosswalk()}
      ${trafficLightPole(60, 232, "red")}
      ${car(430, 285, "#3b82f6")}${bus(180, 200, true)}
      ${wrapCharAt(280, 300, 1.4, bo({ expression: "think", pose: "stand" }))}
      ${wrapCharAt(390, 300, 1.3, na({ pose: "wave" }))}
    `),

    /* Chương 3 (sai nhánh) — hai bạn chạy ẩu qua đèn đỏ, xe buýt phanh gấp */
    peerPressure: () =>
      frame(`
      ${sky("tense")}
      ${roadWithCrosswalk()}
      ${trafficLightPole(60, 232, "red")}
      ${bus(420, 250, true, true)}
      ${wrapCharAt(230, 295, 1.3, bo({ expression: "surprised", pose: "run" }))}
      ${wrapCharAt(310, 300, 1.2, na({ pose: "stand" }))}
    `),

    /* Chương 3 (đúng nhánh) — đèn xanh, cùng qua đường, chú công an điều tiết */
    goodWait: () =>
      frame(`
      ${sky("bright")}
      ${treeCluster(560, 245, 1)}
      ${roadWithCrosswalk()}
      ${trafficLightPole(60, 232, "green")}
      ${policeman(560, 260)}
      ${wrapCharAt(260, 300, 1.35, bo({ expression: "confident", pose: "stand" }))}
      ${wrapCharAt(340, 300, 1.25, na({ pose: "wave" }))}
    `),

    /* Chương 3 (phục hồi sau sai lầm) — Bo bình tĩnh lùi lại tìm vạch kẻ */
    recover: () =>
      frame(`
      ${sky("morning")}
      ${treeCluster(80, 250, 1)}
      ${roadWithCrosswalk()}
      ${trafficLightPole(430, 232, "red")}
      ${wrapCharAt(230, 300, 1.4, bo({ expression: "think", pose: "step-back" }))}
    `),

    /* Kết thúc TỐT — Bo & Na tới cổng trường an toàn, robot AI vui mừng */
    endGood: () =>
      frame(`
      ${sky("bright")}
      ${schoolBuilding(460, 240, 0.95)}
      ${treeCluster(60, 250, 1.1)}
      <rect x="0" y="250" width="640" height="110" fill="#3d9856"/>
      ${wrapCharAt(230, 300, 1.4, bo({ expression: "happy", pose: "wave" }))}
      ${wrapCharAt(310, 300, 1.25, na({ pose: "wave" }))}
      ${wrapCharAt(150, 260, 1.1, robot({ mood: "happy" }))}
    `),

    /* Kết thúc TRUNG BÌNH — Bo nhẹ nhõm, rút kinh nghiệm cùng AI Gia sư */
    endMid: () =>
      frame(`
      ${sky("morning")}
      ${treeCluster(540, 250, 1)}
      <rect x="0" y="260" width="640" height="100" fill="#3d9856"/>
      ${wrapCharAt(260, 300, 1.4, bo({ expression: "think", pose: "stand" }))}
      ${wrapCharAt(370, 265, 1.15, robot({ mood: "neutral" }))}
    `),

    /* Kết thúc CẦN CẨN THẬN — Bo và AI lo lắng nhắc nhở, không quá đáng sợ */
    endBad: () =>
      frame(`
      ${sky("tense")}
      <rect x="0" y="240" width="640" height="120" fill="#2a2f45"/>
      ${trafficLightPole(500, 222, "red")}
      ${wrapCharAt(260, 300, 1.4, bo({ expression: "worried", pose: "stand" }))}
      ${wrapCharAt(370, 265, 1.1, robot({ mood: "concern" }))}
    `),
  };

  function render(key) {
    const fn = SCENES[key];
    return fn ? fn() : frame(sky("morning"));
  }

  return { render };
})();

window.ComicScenes = ComicScenes;
