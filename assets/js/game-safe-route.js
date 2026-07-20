/* game-safe-route.js — GAME 4: Đường đến trường an toàn
   ------------------------------------------------------------------
   AI tạo nhiều tuyến đường với mức độ an toàn khác nhau (xe tải, xe
   khách, ngã tư, đường đông, công trình, mưa, ngập nước, trường học...).
   Người chơi chọn tuyến an toàn nhất, AI giải thích lý do.
   ------------------------------------------------------------------ */

document.addEventListener("DOMContentLoaded", () => {
  GameEngine.init("safeRoute", {
    url: "game-city-hero.php",
    label: "Game tiếp theo: Siêu nhí xử lý tình huống",
  });

  const HAZARDS = [
    { key: "truck", icon: "🚛", label: "Nhiều xe tải lớn", weight: 3 },
    { key: "bus", icon: "🚌", label: "Nhiều xe khách", weight: 2 },
    { key: "intersection", icon: "🚦", label: "Nhiều ngã tư", weight: 2 },
    { key: "busy", icon: "🚗", label: "Đường đông xe cộ", weight: 2 },
    { key: "construction", icon: "🚧", label: "Đang thi công", weight: 3 },
    { key: "rain", icon: "🌧️", label: "Trời mưa trơn trượt", weight: 2 },
    { key: "flood", icon: "🌊", label: "Đoạn đường ngập nước", weight: 3 },
  ];
  const BONUSES = [
    {
      key: "schoolzone",
      icon: "🏫",
      label: "Gần trường, có vạch kẻ rõ",
      weight: -3,
    },
    { key: "sidewalk", icon: "🚶", label: "Vỉa hè rộng rãi", weight: -2 },
    {
      key: "guard",
      icon: "🚸",
      label: "Có người hướng dẫn qua đường",
      weight: -3,
    },
    { key: "light", icon: "💡", label: "Đèn đường sáng rõ", weight: -1 },
  ];

  const TOTAL_ROUNDS = 5;
  let roundIdx = 0;
  let mistakes = 0;

  function helpSteps() {
    return [
      "Mỗi lượt, AI đưa ra 3 tuyến đường khác nhau tới trường.",
      "Mỗi tuyến có các đặc điểm riêng: xe tải, ngã tư, công trình, hay gần trường học, có vỉa hè...",
      "Em hãy chọn tuyến đường mà em nghĩ là AN TOÀN NHẤT.",
      "AI sẽ giải thích vì sao tuyến đó an toàn hơn (hoặc chưa phải lựa chọn tốt nhất).",
    ];
  }

  function start() {
    GameEngine.showIntro({
      icon: "🗺️",
      title: "Đường đến trường an toàn",
      desc: "AI sẽ tạo ra nhiều tuyến đường khác nhau tới trường, mỗi tuyến có mức độ an toàn riêng. Em hãy quan sát kỹ và chọn tuyến đường AN TOÀN NHẤT!",
      features: [
        "🗺️ Nhiều tuyến đường ngẫu nhiên",
        "⚖️ So sánh mức độ nguy hiểm",
        "🤖 AI giải thích lựa chọn tốt nhất",
      ],
      onStart: () => {
        roundIdx = 0;
        mistakes = 0;
        nextRound();
      },
    });
  }

  function generateRoute() {
    const tags = [];
    const hazardCount = 1 + Math.floor(Math.random() * 3);
    const shuffledHazards = [...HAZARDS]
      .sort(() => Math.random() - 0.5)
      .slice(0, hazardCount);
    tags.push(...shuffledHazards);
    if (Math.random() < 0.55) {
      const bonus = BONUSES[Math.floor(Math.random() * BONUSES.length)];
      tags.push(bonus);
    }
    const score = tags.reduce((sum, t) => sum + t.weight, 0);
    return { tags, score };
  }

  function nextRound() {
    let routes = [generateRoute(), generateRoute(), generateRoute()];
    // Đảm bảo không có 2 tuyến hoà điểm để luôn có 1 đáp án an toàn nhất rõ ràng
    while (new Set(routes.map((r) => r.score)).size < 3) {
      routes = [generateRoute(), generateRoute(), generateRoute()];
    }
    const bestIdx = routes.reduce(
      (best, r, i) => (r.score < routes[best].score ? i : best),
      0,
    );

    const body = GameEngine.showGameplayShell({
      title: "🗺️ Chọn tuyến đường an toàn nhất",
      progressLabel: `Lượt ${roundIdx + 1}/${TOTAL_ROUNDS}`,
    });

    body.innerHTML = `<div class="ge-route-grid" id="routeGrid"></div>`;
    GameEngine.aiSay(
      "Em hãy so sánh 3 tuyến đường dưới đây và chọn tuyến AN TOÀN NHẤT tới trường nhé!",
      "info",
    );
    GameEngine.attachHelpButton(body, {
      title: "Cách chơi — Đường đến trường an toàn",
      steps: helpSteps(),
    });

    const grid = document.getElementById("routeGrid");
    routes.forEach((r, i) => {
      const card = document.createElement("div");
      card.className = "ge-route-card";
      card.innerHTML = `
        <h4>🚏 Tuyến ${String.fromCharCode(65 + i)}</h4>
        <div class="ge-route-hazards">${r.tags.map((t) => `<span>${t.icon} ${t.label}</span>`).join("")}</div>
      `;
      card.onclick = () => pickRoute(i, bestIdx, routes, card, body);
      grid.appendChild(card);
    });
  }

  function pickRoute(idx, bestIdx, routes, card, body) {
    document
      .querySelectorAll(".ge-route-card")
      .forEach((c) => (c.style.pointerEvents = "none"));
    const chosen = routes[idx];
    const best = routes[bestIdx];

    if (idx === bestIdx) {
      GameEngine.fx("correct");
      card.classList.add("selected-ok");
      GameEngine.aiSay(
        `✅ Chính xác! Tuyến ${String.fromCharCode(65 + idx)} an toàn nhất vì có ít yếu tố nguy hiểm nhất${best.tags.some((t) => t.weight < 0) ? " và có thêm các yếu tố hỗ trợ an toàn." : "."}`,
        "good",
      );
    } else {
      GameEngine.fx("wrong");
      card.classList.add("selected-bad");
      mistakes++;
      const bestCard = document.querySelectorAll(".ge-route-card")[bestIdx];
      if (bestCard) bestCard.classList.add("selected-ok");
      GameEngine.aiSay(
        `❌ Tuyến ${String.fromCharCode(65 + idx)} có nhiều nguy cơ hơn (${
          chosen.tags
            .filter((t) => t.weight > 0)
            .map((t) => t.label)
            .join(", ") || "một số yếu tố rủi ro"
        }). Tuyến ${String.fromCharCode(65 + bestIdx)} an toàn hơn vì ít nguy hiểm nhất.`,
        "bad",
      );
    }

    setTimeout(() => {
      roundIdx++;
      if (roundIdx < TOTAL_ROUNDS) nextRound();
      else finish();
    }, 2000);
  }

  function finish() {
    GameEngine.removeHelpButton();
    const perfect = mistakes === 0;
    const xp = Math.max(15, 30 - mistakes * 3);
    const coin = Math.max(20, 45 - mistakes * 4);

    GameEngine.showResults({
      success: true,
      title: perfect
        ? "🏆 Chuyên gia chọn đường an toàn!"
        : "🎉 Hoàn thành hành trình!",
      summary: `Em đã đánh giá ${TOTAL_ROUNDS} bộ tuyến đường${mistakes ? ` và chọn đúng ${TOTAL_ROUNDS - mistakes}/${TOTAL_ROUNDS} lần` : " và luôn chọn đúng tuyến an toàn nhất"}.`,
      xp,
      coin,
      badgeKey: perfect ? "chuyen_gia_duong_an_toan" : null,
      badgeLabel: "Chuyên gia chọn đường an toàn",
      onReplay: () => {
        roundIdx = 0;
        mistakes = 0;
        nextRound();
      },
    });

    setTimeout(() => {
      GameEngine.setAdvice(
        "Khi đi trên một con đường mới, em hãy để ý xem có vỉa hè, đèn tín hiệu và ít xe tải/xe khách hay không để chọn đường an toàn hơn nhé!",
      );
    }, 500);
  }

  start();
});
