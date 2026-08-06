/* mascot.js — Nhân vật "Siêu Nhí" dùng chung cho mọi mini-game
   ------------------------------------------------------------------
   Vẽ bằng SVG (vector), có gradient/bóng đổ để trông có chiều sâu,
   nhẹ và có thể tái sử dụng, đổi màu, xoay/lệch mũ ở nhiều trạng thái
   khác nhau mà không cần nhiều ảnh riêng lẻ.

   Dùng: MascotSVG.character({ helmet:'on'|'off'|'cap', helmetTransform, badge })
   ------------------------------------------------------------------ */

const MascotSVG = (() => {
  /** Vẽ khuôn mặt/đầu nhân vật (dùng chung cho mọi trạng thái) */
  function head() {
    return `
      <ellipse cx="70" cy="132" rx="40" ry="13" fill="#F2B87E"/>
      <path d="M30 148 Q70 116 110 148 L110 150 L30 150 Z" fill="#5FB0E8"/>
      <circle cx="70" cy="74" r="46" fill="url(#skinGrad)"/>
      <circle cx="25" cy="78" r="9" fill="#F2B87E"/>
      <circle cx="115" cy="78" r="9" fill="#F2B87E"/>
      <path d="M48 68 Q55 63 62 67" stroke="#5B4632" stroke-width="3" fill="none" stroke-linecap="round"/>
      <path d="M78 67 Q85 63 92 68" stroke="#5B4632" stroke-width="3" fill="none" stroke-linecap="round"/>
      <ellipse cx="55" cy="82" rx="9" ry="11" fill="#fff"/>
      <ellipse cx="85" cy="82" rx="9" ry="11" fill="#fff"/>
      <circle cx="56" cy="84" r="6" fill="#4A2E1A"/>
      <circle cx="86" cy="84" r="6" fill="#4A2E1A"/>
      <circle cx="58.5" cy="80.5" r="2" fill="#fff"/>
      <circle cx="88.5" cy="80.5" r="2" fill="#fff"/>
      <path d="M69 90 Q71 95 68 98" stroke="#D99A5B" stroke-width="2.4" fill="none" stroke-linecap="round"/>
      <ellipse cx="41" cy="97" rx="8.5" ry="6" fill="#FF9B9B" opacity=".55"/>
      <ellipse cx="99" cy="97" rx="8.5" ry="6" fill="#FF9B9B" opacity=".55"/>
      <path d="M57 103 Q70 113 83 103" stroke="#A85D3A" stroke-width="3" fill="none" stroke-linecap="round"/>
    `;
  }

  /** Tóc lộ ra khi KHÔNG đội mũ bảo hiểm (hoặc đội mũ lưỡi trai) */
  function hairFull() {
    return `<path d="M24 60 Q26 30 70 24 Q114 30 116 60 Q116 48 108 42 Q94 26 70 26 Q46 26 32 42 Q24 48 24 60 Z" fill="#3B2A1E"/>`;
  }
  /** Tóc lộ ra 2 bên khi ĐÃ đội mũ bảo hiểm (mũ che phần trên) */
  function hairUnderHelmet() {
    return `<path d="M26 66 Q26 52 34 44 L34 66 Z" fill="#3B2A1E"/><path d="M114 66 Q114 52 106 44 L106 66 Z" fill="#3B2A1E"/>`;
  }

  /** Mũ lưỡi trai (lựa chọn SAI ở bước 1) */
  function cap() {
    return `
      <path d="M22 56 Q26 24 70 20 Q114 24 118 56 Q118 44 108 38 Q92 24 70 24 Q48 24 32 38 Q22 44 22 56 Z" fill="#3B82F6"/>
      <ellipse cx="20" cy="58" rx="22" ry="7" fill="#2563EB"/>
      <circle cx="70" cy="24" r="4" fill="#1D4ED8"/>
    `;
  }

  /** Mũ bảo hiểm — form chuẩn, có thể lệch/xoay/phóng to qua `transform` truyền vào <g> */
  function helmet(color1, color2) {
    return `
      <path d="M16 66 Q10 20 70 12 Q130 20 124 66 Q124 76 114 76 L26 76 Q16 76 16 66 Z"
            fill="url(#${color1})" stroke="#0E3E7A" stroke-width="2.5"/>
      <rect x="34" y="30" width="15" height="26" rx="7" fill="#0E3E7A" opacity=".55"/>
      <rect x="91" y="30" width="15" height="26" rx="7" fill="#0E3E7A" opacity=".55"/>
      <path d="M28 26 Q50 12 78 15" stroke="#FFFFFF" stroke-width="6" fill="none" stroke-linecap="round" opacity=".55"/>
      <path d="M16 66 Q70 82 124 66" stroke="#0E3E7A" stroke-width="2.5" fill="none"/>
    `;
  }

  /** Quai mũ (chữ V dưới cằm) — dùng cho bước 3/4, đổi kiểu theo trạng thái */
  function strap(kind) {
    if (kind === "loose") {
      // Quai chùng: vòng cung phình rộng ra ngoài má rồi mới chụm xuống thấp — trông lỏng lẻo
      return `
        <path d="M30 56 Q22 96 40 118 Q52 128 68 126" stroke="#0E3E7A" stroke-width="3.5" fill="none" stroke-linecap="round" opacity=".85"/>
        <path d="M110 56 Q118 96 100 118 Q88 128 72 126" stroke="#0E3E7A" stroke-width="3.5" fill="none" stroke-linecap="round" opacity=".85"/>
      `;
    }
    if (kind === "cut") {
      // Đứt quai một bên: chỉ còn dây bên phải, bên trái bị thiếu hẳn
      return `<path d="M110 56 Q112 92 98 112 Q84 122 70 122" stroke="#0E3E7A" stroke-width="3.5" fill="none" stroke-linecap="round" opacity=".9"/>`;
    }
    // 'snug' — mặc định: 2 dây ôm sát hai bên má, chụm gọn dưới cằm (thấp hơn miệng, không đè lên mặt)
    return `
      <path d="M31 57 Q28 92 42 114 Q54 122 70 122" stroke="#0E3E7A" stroke-width="3.5" fill="none" stroke-linecap="round" opacity=".9"/>
      <path d="M109 57 Q112 92 98 114 Q86 122 70 122" stroke="#0E3E7A" stroke-width="3.5" fill="none" stroke-linecap="round" opacity=".9"/>
    `;
  }

  /**
   * Vẽ nhân vật hoàn chỉnh.
   * options:
   *   helmetMode: 'on' | 'off' | 'cap'   (mặc định 'on')
   *   helmetTransform: chuỗi CSS transform áp cho mũ (lệch/xoay/phóng to)
   *   strapMode: 'snug' | 'loose' | 'cut' | null (null = không vẽ quai)
   *   colorA / colorB: 2 màu gradient của mũ (mặc định xanh dương)
   */
  function character({
    helmetMode = "on",
    helmetTransform = "",
    strapMode = null,
    colorA = "#5FB2FF",
    colorB = "#1B6FE0",
  } = {}) {
    const gid = "hg" + Math.random().toString(36).slice(2, 9); // id gradient riêng mỗi lần vẽ, tránh trùng
    const skinGid = "sg" + Math.random().toString(36).slice(2, 9);

    let hairSvg = "";
    let helmetSvg = "";
    let capSvg = "";
    let strapSvg = "";

    if (helmetMode === "cap") {
      hairSvg = hairFull();
      capSvg = cap();
    } else if (helmetMode === "off") {
      hairSvg = hairFull();
    } else {
      hairSvg = hairUnderHelmet();
      helmetSvg = `<g style="transform:${helmetTransform}; transform-origin:70px 45px;">${helmet(gid, gid)}</g>`;
      if (strapMode) strapSvg = strap(strapMode);
    }

    return `
      <svg viewBox="0 0 140 150" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <radialGradient id="${skinGid}" cx="40%" cy="32%" r="75%">
            <stop offset="0%" stop-color="#FFE0B8"/>
            <stop offset="100%" stop-color="#F2B87E"/>
          </radialGradient>
          <linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="${colorA}"/>
            <stop offset="100%" stop-color="${colorB}"/>
          </linearGradient>
        </defs>
        <style>ellipse,circle,path,rect{ }</style>
        <g>
          ${head().replace("url(#skinGrad)", `url(#${skinGid})`)}
          ${hairSvg}
          ${strapSvg}
          ${helmetSvg}
          ${capSvg}
        </g>
      </svg>
    `;
  }

  /** Vẽ tay theo 4 trạng thái cảm xúc: wave/cheer/worry/point (dùng cho pose()) */
  function arms(state) {
    const leftRest = `
      <path d="M36 118 Q26 138 32 156" stroke="#F2B87E" stroke-width="15" fill="none" stroke-linecap="round"/>
      <circle cx="32" cy="156" r="9" fill="#F2B87E"/>
    `;

    if (state === "cheer") {
      // Hai tay giơ cao ăn mừng
      return `
        <path d="M36 118 Q22 90 28 60" stroke="#F2B87E" stroke-width="15" fill="none" stroke-linecap="round"/>
        <circle cx="28" cy="58" r="9" fill="#F2B87E"/>
        <path d="M104 118 Q118 90 112 60" stroke="#F2B87E" stroke-width="15" fill="none" stroke-linecap="round"/>
        <circle cx="112" cy="58" r="9" fill="#F2B87E"/>
      `;
    }

    if (state === "worry") {
      // Lông mày xéo (vẽ đè lên lông mày mặc định của head()) + hai tay ôm má
      return `
        <path d="M46 70 L64 60" stroke="#5B4632" stroke-width="3.5" stroke-linecap="round"/>
        <path d="M94 70 L76 60" stroke="#5B4632" stroke-width="3.5" stroke-linecap="round"/>
        <path d="M36 118 Q30 100 46 94" stroke="#F2B87E" stroke-width="15" fill="none" stroke-linecap="round"/>
        <circle cx="48" cy="93" r="9" fill="#F2B87E"/>
        <path d="M104 118 Q110 100 94 94" stroke="#F2B87E" stroke-width="15" fill="none" stroke-linecap="round"/>
        <circle cx="92" cy="93" r="9" fill="#F2B87E"/>
      `;
    }

    if (state === "point") {
      // Tay phải chỉ sang phải (callout hướng dẫn), tay trái nghỉ
      return `
        ${leftRest}
        <path d="M104 118 Q120 108 126 96" stroke="#F2B87E" stroke-width="15" fill="none" stroke-linecap="round"/>
        <circle cx="128" cy="94" r="8" fill="#F2B87E"/>
        <path d="M128 94 L139 90" stroke="#F2B87E" stroke-width="8" stroke-linecap="round"/>
      `;
    }

    // 'wave' (mặc định): tay phải giơ vẫy, rotate -30° quanh vai; tay trái nghỉ
    return `
      ${leftRest}
      <g transform="rotate(-30 104 118)">
        <path d="M104 118 Q114 90 108 62" stroke="#F2B87E" stroke-width="15" fill="none" stroke-linecap="round"/>
        <circle cx="108" cy="60" r="9" fill="#F2B87E"/>
      </g>
    `;
  }

  /** Phụ kiện theo trạng thái: confetti quanh đầu khi cheer, giọt mồ hôi khi worry */
  function extras(state) {
    if (state === "cheer") {
      const dots = [
        { x: 92, y: 24, c: "#FFB703" },
        { x: 81, y: 5, c: "#219EBC" },
        { x: 59, y: 5, c: "#E63946" },
        { x: 48, y: 24, c: "#2A9D34" },
        { x: 59, y: 43, c: "#FF7B9C" },
        { x: 81, y: 43, c: "#7BD3EA" },
      ];
      return dots.map((d) => `<circle cx="${d.x}" cy="${d.y}" r="4" fill="${d.c}"/>`).join("");
    }
    if (state === "worry") {
      return `<ellipse cx="118" cy="50" rx="6" ry="9" fill="#BFE6FA" opacity=".85" transform="rotate(15 118 50)"/>`;
    }
    return "";
  }

  /** Vẽ mascot ở 1 trong 4 trạng thái cảm xúc: 'wave' | 'cheer' | 'worry' | 'point' */
  function pose(state = "wave") {
    const skinGid = "sg" + Math.random().toString(36).slice(2, 9); // id gradient riêng mỗi lần vẽ, tránh trùng (giống character())
    return `<svg viewBox="0 0 140 160" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Siêu Nhí ${state}">
      <defs><radialGradient id="${skinGid}" cx="50%" cy="40%"><stop offset="0%" stop-color="#FFD9A8"/><stop offset="100%" stop-color="#F2B87E"/></radialGradient></defs>
      ${extras(state)}${hairFull()}${head().replace("url(#skinGrad)", `url(#${skinGid})`)}${arms(state)}
    </svg>`;
  }

  return { character, pose };
})();

window.MascotSVG = MascotSVG;
