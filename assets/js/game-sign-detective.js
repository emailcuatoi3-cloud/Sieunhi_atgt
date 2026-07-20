/* game-sign-detective.js — GAME 3: Thám tử biển báo
   ------------------------------------------------------------------
   AI tạo "bản đồ" khác nhau mỗi lần với nhiều biển báo giao thông Việt
   Nam. AI yêu cầu tìm 1 biển cụ thể mỗi lượt, người chơi bấm chọn, AI
   giải thích ý nghĩa. Có bộ sưu tập biển báo đã học.
   ------------------------------------------------------------------ */

document.addEventListener("DOMContentLoaded", () => {
  GameEngine.init("signDetective", {
    url: "game-safe-route.php",
    label: "Game tiếp theo: Đường đến trường an toàn",
  });

  const SIGN_POOL = [
    {
      id: "pedestrian",
      icon: "🚶",
      name: "Biển dành cho người đi bộ",
      meaning:
        "Đây là khu vực dành riêng hoặc ưu tiên cho người đi bộ qua lại.",
    },
    {
      id: "nobike",
      icon: "🚳",
      name: "Biển cấm xe đạp",
      meaning: "Khu vực này không cho phép xe đạp đi vào.",
    },
    {
      id: "children",
      icon: "🚸",
      name: "Biển cảnh báo trẻ em",
      meaning:
        "Cảnh báo phía trước có trẻ em qua lại — thường gặp gần trường học.",
    },
    {
      id: "stop",
      icon: "🛑",
      name: "Biển STOP",
      meaning: "Yêu cầu dừng lại hoàn toàn trước khi tiếp tục di chuyển.",
    },
    {
      id: "hospital",
      icon: "🏥",
      name: "Biển bệnh viện",
      meaning: "Gần khu vực bệnh viện — cần đi chậm và giữ yên tĩnh.",
    },
    {
      id: "construction",
      icon: "🚧",
      name: "Biển công trường",
      meaning: "Phía trước đang thi công, cần đi chậm và quan sát kỹ.",
    },
    {
      id: "noentry",
      icon: "⛔",
      name: "Biển cấm đi ngược chiều",
      meaning: "Không được đi vào theo hướng này.",
    },
    {
      id: "parking",
      icon: "🅿️",
      name: "Biển bãi đỗ xe",
      meaning: "Khu vực được phép dừng, đỗ xe.",
    },
    {
      id: "bicycle",
      icon: "🚴",
      name: "Biển đường ưu tiên xe đạp",
      meaning: "Làn đường này được ưu tiên cho xe đạp di chuyển.",
    },
    {
      id: "roundabout",
      icon: "🔄",
      name: "Biển vòng xuyến",
      meaning: "Phía trước có vòng xuyến, cần đi theo chiều quy định.",
    },
  ];

  const TOTAL_ROUNDS = 5;
  let roundIdx = 0;
  let mistakes = 0;
  let collection = new Set();

  function helpSteps() {
    return [
      'AI sẽ yêu cầu em tìm một biển báo cụ thể trên bản đồ, ví dụ: "Tìm biển dành cho người đi bộ".',
      "Bấm vào đúng biển báo được yêu cầu trong số các biển đang hiện trên bản đồ.",
      "AI sẽ giải thích ý nghĩa của biển báo đó ngay sau khi em chọn đúng.",
      "Sau 5 lượt tìm kiếm, biển báo đã tìm đúng sẽ được thêm vào Bộ sưu tập của em!",
    ];
  }

  function start() {
    GameEngine.showIntro({
      icon: "🕵️",
      title: "Thám tử biển báo",
      desc: "Em sẽ khám phá một thành phố với rất nhiều biển báo giao thông Việt Nam. AI sẽ yêu cầu tìm đúng biển báo được nhắc tới — hãy quan sát kỹ và trở thành thám tử biển báo giỏi nhất!",
      features: [
        "🗺️ Bản đồ ngẫu nhiên mỗi lượt",
        "🤖 AI giải thích ý nghĩa từng biển",
        "📚 Bộ sưu tập biển báo đã học",
      ],
      onStart: () => {
        roundIdx = 0;
        mistakes = 0;
        collection = new Set();
        nextRound();
      },
    });
  }

  function nextRound() {
    const shuffledPool = [...SIGN_POOL].sort(() => Math.random() - 0.5);
    const target = shuffledPool[0];
    const displayed = shuffledPool.slice(0, 6); // 6 biển hiện trên bản đồ, gồm cả biển đích

    const body = GameEngine.showGameplayShell({
      title: "🗺️ Bản đồ thành phố",
      progressLabel: `Lượt ${roundIdx + 1}/${TOTAL_ROUNDS}`,
    });

    body.innerHTML = `<div class="ge-sign-map" id="signMap"></div>
      <div class="ge-collection" id="collectionRow"></div>`;

    GameEngine.aiSay(`🔍 Tìm biển: "${target.name}"`, "info");
    GameEngine.attachHelpButton(body, {
      title: "Cách chơi — Thám tử biển báo",
      steps: helpSteps(),
    });

    const map = document.getElementById("signMap");
    // Chia bản đồ thành lưới 3x2 (đủ 6 ô cho 6 biển báo) rồi đặt mỗi biển vào 1 ô
    // ngẫu nhiên khác nhau, có xê dịch nhẹ trong ô — đảm bảo KHÔNG BAO GIỜ chồng nhau,
    // không cần random-thử-lại nên không có rủi ro treo trang.
    const cols = 3,
      rows = 2;
    const cellW = 100 / cols;
    const cellH = 100 / rows;
    const cellOrder = Array.from({ length: cols * rows }, (_, i) => i).sort(
      () => Math.random() - 0.5,
    );

    displayed.forEach((sign, i) => {
      const cellIdx = cellOrder[i];
      const col = cellIdx % cols;
      const row = Math.floor(cellIdx / cols);
      const jitterX = (Math.random() - 0.5) * (cellW * 0.3);
      const jitterY = (Math.random() - 0.5) * (cellH * 0.3);
      const left = Math.max(
        1,
        Math.min(82, col * cellW + cellW / 2 - 9 + jitterX),
      );
      const top = Math.max(
        2,
        Math.min(70, row * cellH + cellH / 2 - 17 + jitterY),
      );

      const el = document.createElement("div");
      el.className = "ge-sign-item";
      el.style.top = top + "%";
      el.style.left = left + "%";
      el.textContent = sign.icon;
      el.title = "Bấm để chọn biển báo này";
      el.onclick = () => pickSign(sign, target, el, body);
      map.appendChild(el);
    });

    renderCollection();
  }

  function pickSign(sign, target, el, body) {
    if (el.classList.contains("found")) return;
    if (sign.id === target.id) {
      GameEngine.fx("correct");
      el.classList.add("found");
      collection.add(target.id);
      GameEngine.aiSay(
        `✅ Chính xác! ${target.name}: ${target.meaning}`,
        "good",
      );
      renderCollection();
      setTimeout(() => {
        roundIdx++;
        if (roundIdx < TOTAL_ROUNDS) nextRound();
        else finish();
      }, 1400);
    } else {
      GameEngine.fx("wrong");
      mistakes++;
      el.style.outline = "2px solid var(--red)";
      setTimeout(() => {
        el.style.outline = "";
      }, 500);
      GameEngine.aiSay(
        `❌ Chưa đúng. Đây là "${sign.name}" — không phải biển AI đang yêu cầu. Hãy tìm tiếp: "${target.name}"`,
        "bad",
      );
    }
  }

  function renderCollection() {
    const row = document.getElementById("collectionRow");
    if (!row) return;
    row.innerHTML = "";
    SIGN_POOL.forEach((s) => {
      const item = document.createElement("div");
      item.className =
        "ge-collection-item" + (collection.has(s.id) ? "" : " empty");
      item.textContent = collection.has(s.id) ? s.icon : "❓";
      item.title = collection.has(s.id) ? s.name : "Chưa khám phá";
      row.appendChild(item);
    });
  }

  function finish() {
    GameEngine.removeHelpButton();
    const perfect = mistakes === 0;
    const xp = Math.max(15, 25 - mistakes * 2);
    const coin = Math.max(20, 35 - mistakes * 3);

    GameEngine.showResults({
      success: true,
      title: perfect
        ? "🏆 Thám tử biển báo xuất sắc!"
        : "🎉 Hoàn thành nhiệm vụ thám tử!",
      summary: `Em đã tìm đúng ${collection.size} biển báo và thêm vào bộ sưu tập${mistakes ? ` (có ${mistakes} lần chọn nhầm)` : " không nhầm lần nào"}.`,
      xp,
      coin,
      badgeKey: perfect ? "tham_tu_bien_bao" : null,
      badgeLabel: "Thám tử biển báo",
      onReplay: () => {
        roundIdx = 0;
        mistakes = 0;
        collection = new Set();
        nextRound();
      },
    });

    setTimeout(() => {
      GameEngine.setAdvice(
        "Hôm nay em hãy thử quan sát các biển báo trên đường đi học và xem em nhận ra được bao nhiêu biển nhé!",
      );
    }, 500);
  }

  start();
});
