/* comic-scenes.js — Hệ thống minh hoạ cho "AI Truyện tranh"
   =========================================================
   Vẽ bằng SVG vector (không dùng emoji/icon đơn lẻ) cho mỗi chương: có bầu
   trời, đường phố, trường học, cây xanh, đèn tín hiệu, phương tiện, và
   nhân vật Bo với biểu cảm/tư thế phù hợp nội dung — bố cục có tiền cảnh/
   trung cảnh/hậu cảnh như 1 khung truyện tranh, không dùng emoji/icon lẻ.
   ========================================================= */

const ComicScenes = (() => {
  /* ---------------------------------------------------------------
     NHÂN VẬT BO — học sinh tiểu học VN, đồng phục, balo, 5 biểu cảm
     --------------------------------------------------------------- */
  const FACES = {
    happy: {
      browL: "M-14 -23 Q-8 -26 -2 -23",
      browR: "M2 -23 Q8 -26 14 -23",
      mouth: "M-9 -1 Q0 6 9 -1",
      eyeR: 3.2,
      cheeks: true,
    },
    surprised: {
      browL: "M-14 -25 Q-8 -29 -2 -25",
      browR: "M2 -25 Q8 -29 14 -25",
      mouth: "M0 0 m-4 0 a4 5 0 1 0 8 0 a4 5 0 1 0 -8 0",
      eyeR: 3.8,
      cheeks: false,
    },
    worried: {
      browL: "M-14 -21 Q-8 -17 -2 -21",
      browR: "M2 -21 Q8 -17 14 -21",
      mouth: "M-8 1 Q0 -3 8 1",
      eyeR: 3.0,
      cheeks: false,
    },
    think: {
      browL: "M-14 -24 Q-8 -26 -2 -23",
      browR: "M2 -23 Q8 -26 14 -24",
      mouth: "M-6 -1 Q0 0.5 6 -1",
      eyeR: 3.0,
      cheeks: false,
    },
    confident: {
      browL: "M-14 -24 Q-8 -27 -2 -24",
      browR: "M2 -24 Q8 -27 14 -24",
      mouth: "M-9 -3 Q0 5 9 -3",
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
      stand: "M-9 36 L-11 68 M9 36 L11 68",
      run: "M-9 36 L-18 62 M9 36 L4 66",
      wave: "M-9 36 L-11 68 M9 36 L11 68",
      "step-back": "M-9 36 L-16 64 M9 36 L14 64",
      point: "M-9 36 L-11 68 M9 36 L11 68",
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
      <g transform="scale(${flip ? -1 : 1},1)">
        <ellipse cx="0" cy="72" rx="20" ry="5" fill="rgba(0,0,0,0.25)"/>
        <path d="${legs}" stroke="#2b3358" stroke-width="12" stroke-linecap="round" fill="none"/>
        <rect x="-13" y="60" width="10" height="8" rx="3" fill="#1c2340"/>
        <rect x="3" y="60" width="10" height="8" rx="3" fill="#1c2340"/>
        <path d="M-18 4 Q0 -4 18 4 L15 34 Q0 38 -15 34 Z" fill="#3b82f6"/>
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
  /** Một bạn nhỏ nhìn từ SAU LƯNG, đang bước băng qua đường (không thấy mặt,
   *  chỉ thấy tóc sau đầu và balo to rõ giữa lưng) — tạo cảm giác đường phố
   *  có người qua lại, không chỉ có Bo đứng yên. */
  function pedestrianBack({
    scale = 1,
    hair = "#2b1a12",
    shirt = "#2f68d1",
    bag = "#f59e0b",
  } = {}) {
    return `
      <g >
        <ellipse cx="0" cy="72" rx="20" ry="5" fill="rgba(0,0,0,0.25)"/>
        <path d="M-9 36 L-16 66 M9 36 L4 68" stroke="#2b3358" stroke-width="12" stroke-linecap="round" fill="none"/>
        <rect x="-15" y="58" width="10" height="8" rx="3" fill="#1c2340"/>
        <rect x="1" y="60" width="10" height="8" rx="3" fill="#1c2340"/>
        <path d="M-18 4 Q0 -4 18 4 L15 34 Q0 38 -15 34 Z" fill="${shirt}"/>
        <g fill="none" stroke="${shirt}" stroke-width="9" stroke-linecap="round">
          <path d="M-19 6 Q-27 16 -25 32"/><path d="M19 6 Q25 18 20 34"/>
        </g>
        <rect x="-14" y="2" width="28" height="32" rx="8" fill="${bag}"/>
        <rect x="-14" y="2" width="28" height="10" rx="4" fill="rgba(0,0,0,0.18)"/>
        <rect x="-3" y="10" width="6" height="16" rx="2" fill="#fff" opacity="0.45"/>
        <circle cx="0" cy="-18" r="24" fill="${hair}"/>
        <circle cx="-19" cy="-13" r="6" fill="#ffd8a8"/>
        <circle cx="19" cy="-13" r="6" fill="#ffd8a8"/>
      </g>
    `;
  }

  function na({ pose = "wave", scale = 1 } = {}) {
    const arms =
      pose === "wave"
        ? '<path d="M-19 6 Q-26 20 -22 34" /><path d="M19 6 Q30 -6 26 -20" />'
        : '<path d="M-19 6 Q-26 20 -22 34" /><path d="M19 6 Q26 20 22 34" />';
    return `
      <g >
        <ellipse cx="0" cy="72" rx="20" ry="5" fill="rgba(0,0,0,0.25)"/>
        <path d="M-9 36 L-11 68 M9 36 L11 68" stroke="#2b3358" stroke-width="12" stroke-linecap="round" fill="none"/>
        <rect x="-13" y="60" width="10" height="8" rx="3" fill="#1c2340"/>
        <rect x="3" y="60" width="10" height="8" rx="3" fill="#1c2340"/>
        <path d="M-18 4 Q0 -4 18 4 L15 34 Q0 38 -15 34 Z" fill="#ec4899"/>
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
      <g >
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

  /** Vạch qua đường vuông góc với chiều xe chạy: các sọc NGANG (rộng, dẹt)
   *  xếp chồng theo chiều dọc, phủ hết bề ngang lòng đường để người đi bộ
   *  băng qua theo phương thẳng đứng. */
  function crosswalk(cx, roadTop, roadHeight) {
    let stripes = "";
    const count = 6;
    const gap = roadHeight / count;
    const stripeH = gap * 0.6;
    for (let i = 0; i < count; i++) {
      const y = roadTop + i * gap + (gap - stripeH) / 2;
      stripes += `<rect x="${cx - 34}" y="${y}" width="68" height="${stripeH}" fill="#eef1fb" opacity="0.9"/>`;
    }
    return stripes;
  }

  /** Vạch đứt nét phân chia làn xe (đường 1 chiều, không cần vạch tim vàng). */
  function dashedLane(y) {
    let out = "";
    for (let x = 8; x < 636; x += 34) {
      out += `<rect x="${x}" y="${y - 2}" width="18" height="4" fill="rgba(232,236,251,0.65)"/>`;
    }
    return out;
  }

  /** Vỉa hè riêng cho người đi bộ — nằm giữa nền (cây/trường/đèn) và lòng đường,
   *  có bó vỉa (viền sáng) và vài đường lát gạch cho có kết cấu thật. */
  function sidewalkStrip(topY, h) {
    let tiles = "";
    for (let x = 6; x < 640; x += 40) {
      tiles += `<line x1="${x}" y1="${topY + 4}" x2="${x}" y2="${topY + h}" stroke="rgba(0,0,0,0.08)" stroke-width="2"/>`;
    }
    return `
      <rect x="0" y="${topY}" width="640" height="${h}" fill="#9297ac"/>
      <rect x="0" y="${topY}" width="640" height="4" fill="#d7dae6"/>
      ${tiles}
    `;
  }

  /** Đường 1 CHIỀU nhiều làn: chỉ có vạch đứt nét trắng phân làn cùng chiều,
   *  không có vạch tim vàng (vì không có luồng xe ngược chiều). */
  /** Vạch dừng xe — vạch trắng đậm, kẻ ngang qua toàn bộ lòng đường, nơi xe
   *  phải dừng lại chờ đèn đỏ trước khi tới vạch qua đường. */
  function stopLine(x, topY, h) {
    return `<rect x="${x - 3}" y="${topY}" width="6" height="${h}" fill="#eef1fb" opacity="0.95"/>`;
  }

  function roadOneWay(topY, h, lanes = 3) {
    let dividers = "";
    for (let i = 1; i < lanes; i++)
      dividers += dashedLane(topY + h * (i / lanes));
    return `
      <rect x="0" y="${topY}" width="640" height="${h}" fill="#2a2f45"/>
      ${dividers}
      ${crosswalk(320, topY, h)}
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

  function bicycle(x, y, flip = false) {
    return `
      <g transform="translate(${x},${y}) scale(${flip ? -1 : 1},1)">
        <circle cx="-13" cy="8" r="9" fill="none" stroke="#cfd3e6" stroke-width="2"/>
        <circle cx="13" cy="8" r="9" fill="none" stroke="#cfd3e6" stroke-width="2"/>
        <path d="M-13 8 L2 -6 L13 8 M2 -6 L2 -14 M-13 8 L8 0 L13 8" stroke="#2b3358" stroke-width="2" fill="none"/>
        <circle cx="2" cy="-19" r="6" fill="#ffd8a8"/>
        <path d="M-3 -22 Q2 -27 7 -22" fill="#3b2a1e"/>
        <rect x="-2" y="-16" width="8" height="9" rx="2" fill="#f59e0b"/>
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

  // Bố cục chiều sâu chung cho MỌI cảnh, từ xa tới gần:
  //   nền/trời (cây, trường, đèn tín hiệu)  →  VỈA HÈ (người đi bộ đứng ở đây)
  //   →  LÒNG ĐƯỜNG 1 CHIỀU nhiều làn (xe chạy đúng làn, cùng 1 hướng)
  const SIDEWALK_Y = 240; // biên trên vỉa hè gần (giáp nền/cây/trường)
  const SIDEWALK_H = 28; // bề dày vỉa hè gần
  const ROAD_TOP = SIDEWALK_Y + SIDEWALK_H; // = 268, lòng đường bắt đầu từ đây
  const ROAD_H = 62; // bề dày lòng đường
  const SIDEWALK2_Y = ROAD_TOP + ROAD_H; // = 330, vỉa hè phía đối diện
  const SIDEWALK2_H = 360 - SIDEWALK2_Y; // = 30
  const STAND_Y = SIDEWALK_Y + SIDEWALK_H * 0.35; // ~202, chân người đứng vỉa hè gần (cùng phía cột đèn)
  const STAND2_Y = SIDEWALK2_Y + SIDEWALK2_H * 0.5; // ~340, chân người đứng vỉa hè xa (phía đối diện cột đèn)
  const LANE_COUNT = 3;
  const laneY = (i) => ROAD_TOP + ROAD_H * ((i + 0.5) / LANE_COUNT); // tâm làn thứ i (0..2)

  function roadWithCrosswalk() {
    return `
      ${sidewalkStrip(SIDEWALK_Y, SIDEWALK_H)}
      ${roadOneWay(ROAD_TOP, ROAD_H, LANE_COUNT)}
      ${sidewalkStrip(SIDEWALK2_Y, SIDEWALK2_H)}
    `;
  }

  const placeBo = (x, feetY, opts = {}) =>
    wrapCharAt(
      x,
      feetY - 72 * (opts.scale || 1.3),
      opts.scale || 1.3,
      bo(opts),
    );
  const placeNa = (x, feetY, opts = {}) =>
    wrapCharAt(
      x,
      feetY - 72 * (opts.scale || 1.25),
      opts.scale || 1.25,
      na(opts),
    );
  const placeRobot = (x, feetY, opts = {}) =>
    wrapCharAt(
      x,
      feetY - 46 * (opts.scale || 1.1),
      opts.scale || 1.1,
      robot(opts),
    );
  const placePedBack = (x, feetY, opts = {}) =>
    wrapCharAt(
      x,
      feetY - 72 * (opts.scale || 0.85),
      opts.scale || 0.85,
      pedestrianBack(opts),
    );
  const placeTree = (x, scale = 1) =>
    treeCluster(x, SIDEWALK_Y - 18 * scale, scale);
  const placeSchool = (x, scale = 1) =>
    schoolBuilding(x, SIDEWALK_Y - 6 * scale, scale);
  const placeLight = (x, active) => trafficLightPole(x, ROAD_TOP - 46, active);
  // lane: chỉ số làn 0-4 (0 = làn sát vỉa hè gần, 4 = làn sát vỉa hè xa)
  const placeCar = (x, color, lane = 2) =>
    car(x, laneY(lane) - 15, color, false);
  const placeMoto = (x, urgent, lane = 2) =>
    motorbike(x, laneY(lane) - 14, false, urgent);
  const placeBike = (x, lane = 2) => bicycle(x, laneY(lane) - 12, false);
  const placeBus = (x, braking, lane = 2) =>
    bus(x, laneY(lane) - 18, false, braking);
  const placePolice = (x, groundY = STAND_Y) => policeman(x, groundY - 46);
  const placeStopLine = (x) => stopLine(x, ROAD_TOP, ROAD_H);
  const groundBand = (color) =>
    `<rect x="0" y="${SIDEWALK_Y}" width="640" height="${360 - SIDEWALK_Y}" fill="${color}"/>`;

  const SCENES = {
    start: () =>
      frame(`
      ${sky("morning")}
      ${placeSchool(500, 0.75)}
      ${placeTree(55, 1)}${placeTree(605, 0.85)}
      ${roadWithCrosswalk()}
      ${placeStopLine(368)}
      ${placeLight(396, "red")}
      ${placeCar(420, "#8b5cf6", 0)}${placeBike(478, 0)}
      ${placeMoto(400, false, 1)}${placeCar(455, "#3b82f6", 1)}${placeBus(520, false, 1)}
      ${placeCar(430, "#34d399", 2)}${placeMoto(485, false, 2)}${placeBike(535, 2)}
      ${placeBo(110, STAND2_Y, { expression: "think", pose: "stand", scale: 0.475 })}
    `),

    rush: () =>
      frame(`
      ${sky("tense")}
      ${placeTree(40, 0.85)}
      ${roadWithCrosswalk()}
      ${placeMoto(440, true, 2)}
      ${placeBo(320, 340, { expression: "surprised", pose: "step-back", scale: 0.55 })}
      <text x="480" y="170" font-size="46" fill="#fff" font-weight="800" opacity="0.9">!</text>
    `),

    safeCross: () =>
      frame(`
      ${sky("morning")}
      ${placeTree(560, 1)}
      ${roadWithCrosswalk()}
      ${placeStopLine(272)}
      ${placeLight(240, "red")}
      ${placeCar(180, "#3b82f6")}${placeBus(90)}
      ${placeBo(270, STAND_Y, { expression: "think", pose: "stand", scale: 0.5 })}
      ${placeNa(380, STAND_Y, { pose: "wave", scale: 0.45 })}
    `),

    peerPressure: () =>
      frame(`
      ${sky("tense")}
      ${roadWithCrosswalk()}
      ${placeLight(70, "red")}
      ${placeBus(430, true)}
      ${placeBo(220, STAND_Y - 5, { expression: "surprised", pose: "run", scale: 0.5 })}
      ${placeNa(310, STAND_Y, { pose: "stand", scale: 0.45 })}
    `),

    goodWait: () =>
      frame(`
      ${sky("bright")}
      ${placeTree(560, 1)}
      ${roadWithCrosswalk()}
      ${placeLight(70, "green")}
      ${placePolice(560)}
      ${placeBo(250, STAND_Y, { expression: "confident", pose: "stand", scale: 0.5 })}
      ${placeNa(340, STAND_Y, { pose: "wave", scale: 0.45 })}
    `),

    recover: () =>
      frame(`
      ${sky("morning")}
      ${placeTree(80, 1)}
      ${roadWithCrosswalk()}
      ${placeLight(440, "red")}
      ${placeBo(230, STAND_Y, { expression: "think", pose: "step-back", scale: 0.5 })}
    `),

    endGood: () =>
      frame(`
      ${sky("bright")}
      ${placeSchool(460, 0.95)}
      ${placeTree(60, 1.1)}
      ${groundBand("#3d9856")}
      ${placeBo(230, STAND_Y, { expression: "happy", pose: "wave", scale: 0.5 })}
      ${placeNa(320, STAND_Y, { pose: "wave", scale: 0.45 })}
      ${placeRobot(150, 300, { mood: "happy", scale: 0.55 })}
    `),

    endMid: () =>
      frame(`
      ${sky("morning")}
      ${placeTree(540, 1)}
      ${groundBand("#3d9856")}
      ${placeBo(260, STAND_Y, { expression: "think", pose: "stand", scale: 0.5 })}
      ${placeRobot(380, 300, { mood: "neutral", scale: 0.55 })}
    `),

    endBad: () =>
      frame(`
      ${sky("tense")}
      ${roadWithCrosswalk()}
      ${placeLight(520, "red")}
      ${placeBo(260, STAND_Y, { expression: "worried", pose: "stand", scale: 0.5 })}
      ${placeRobot(380, 300, { mood: "concern", scale: 0.55 })}
    `),
  };

  function render(key) {
    const fn = SCENES[key];
    return fn ? fn() : frame(sky("morning"));
  }

  return { render };
})();

window.ComicScenes = ComicScenes;
