/* game-helmet.js — GAME 2: Chiếc mũ thần kỳ
   ------------------------------------------------------------------
   Người chơi giúp nhân vật đội mũ bảo hiểm đúng cách qua 5 bước:
   chọn đúng loại mũ → đặt đúng vị trí → chỉnh quai → cài khoá → kiểm tra.
   Sau khi hoàn thành, có nút mở AI Camera thật để kiểm tra ngoài đời.
   ------------------------------------------------------------------ */

document.addEventListener("DOMContentLoaded", () => {
  GameEngine.init("helmet", {
    url: "game-sign-detective.php",
    label: "Game tiếp theo: Thám tử biển báo",
  });

  const STEPS = [
    {
      title: "Bước 1: Chọn đúng loại mũ",
      question:
        "Nhân vật sắp đi xe đạp tới trường. Em hãy chọn chiếc mũ phù hợp nhất!",
      options: [
        {
          icon: "🪖",
          label: "Mũ bảo hiểm đạt chuẩn, vừa đầu",
          ok: true,
          explain:
            "Đây là mũ bảo hiểm đạt chuẩn, vừa vặn với đầu — lựa chọn đúng!",
          visual: { helmetMode: "on" },
        },
        {
          icon: "🧢",
          label: "Mũ lưỡi trai thời trang",
          ok: false,
          explain: "Mũ lưỡi trai không có tác dụng bảo vệ đầu khi va chạm.",
          visual: { helmetMode: "cap" },
        },
        {
          icon: "⛑️",
          label: "Mũ bảo hiểm quá rộng so với đầu",
          ok: false,
          explain:
            "Mũ quá rộng sẽ bị lệch, xộc xệch, không bảo vệ tốt khi va chạm.",
          visual: {
            helmetMode: "on",
            helmetTransform: "scale(1.32) translateY(-3px)",
          },
        },
      ],
    },
    {
      title: "Bước 2: Đặt mũ đúng vị trí",
      question: "Em hãy chọn cách đội mũ đúng cách!",
      options: [
        {
          icon: "📐",
          label: "Mũ ôm trán, cách chân mày khoảng 2 ngón tay",
          ok: true,
          explain:
            "Chính xác! Mũ cần che kín trán, cách chân mày khoảng 2 ngón tay.",
          visual: { helmetMode: "on" },
        },
        {
          icon: "⬆️",
          label: "Đẩy mũ ngược ra sau gáy",
          ok: false,
          explain:
            "Đội ngược ra sau sẽ để lộ trán, không bảo vệ được phần đầu phía trước.",
          visual: {
            helmetMode: "on",
            helmetTransform: "translate(14px,-18px) rotate(-20deg)",
          },
        },
        {
          icon: "⬇️",
          label: "Kéo mũ trùm xuống che cả mắt",
          ok: false,
          explain:
            "Mũ che mắt sẽ cản tầm nhìn, rất nguy hiểm khi tham gia giao thông.",
          visual: { helmetMode: "on", helmetTransform: "translateY(24px)" },
        },
      ],
    },
    {
      title: "Bước 3: Chỉnh quai mũ",
      question: "Quai mũ hai bên cần được chỉnh như thế nào?",
      options: [
        {
          icon: "🔤",
          label: "Quai tạo hình chữ V ôm sát dưới tai",
          ok: true,
          explain:
            "Đúng rồi! Quai hai bên cần tạo hình chữ V, ôm sát ngay dưới tai.",
          visual: { helmetMode: "on", strapMode: "snug" },
        },
        {
          icon: "〰️",
          label: "Để quai chùng, lỏng lẻo cho thoải mái",
          ok: false,
          explain:
            "Quai lỏng lẻo khiến mũ dễ bị tuột khi va chạm hoặc phanh gấp.",
          visual: {
            helmetMode: "on",
            strapMode: "loose",
            helmetTransform: "rotate(7deg)",
          },
        },
        {
          icon: "✂️",
          label: "Bỏ quai một bên cho đỡ vướng",
          ok: false,
          explain:
            "Thiếu quai một bên làm mũ mất cân bằng và dễ rơi ra khi va chạm.",
          visual: {
            helmetMode: "on",
            strapMode: "cut",
            helmetTransform: "rotate(15deg) translateX(5px)",
          },
        },
      ],
    },
    {
      title: "Bước 4: Cài khoá",
      question: "Khoá cằm cần được cài như thế nào là đúng?",
      options: [
        {
          icon: "🔒",
          label: "Cài vừa khít, nhét lọt 1–2 ngón tay dưới cằm",
          ok: true,
          explain:
            "Chuẩn xác! Khoá vừa khít, nhét lọt 1–2 ngón tay là đạt chuẩn an toàn.",
          visual: { helmetMode: "on", strapMode: "snug" },
        },
        {
          icon: "🔓",
          label: "Không cài khoá, chỉ đội cho có",
          ok: false,
          explain:
            "Không cài khoá thì mũ sẽ rơi ra ngay khi có va chạm, không có tác dụng bảo vệ.",
          visual: {
            helmetMode: "on",
            helmetTransform: "translateY(-10px) rotate(-7deg)",
          },
        },
        {
          icon: "💪",
          label: "Cài siết chặt hết cỡ dưới cằm",
          ok: false,
          explain:
            "Cài quá chặt gây khó chịu, khó thở, ảnh hưởng tới việc quan sát.",
          visual: {
            helmetMode: "on",
            strapMode: "snug",
            colorA: "#FF9B9B",
            colorB: "#E14D4D",
          },
        },
      ],
    },
  ];

  let stepIdx = 0;
  let mistakes = 0;

  function helpSteps() {
    return [
      "Ở mỗi bước, em chọn 1 trong 3 lựa chọn để đội mũ đúng cách.",
      "AI sẽ giải thích ngay vì sao lựa chọn đó đúng hoặc sai.",
      "Hoàn thành đủ 4 bước để được AI chấm điểm cuối cùng.",
      'Sau khi xong, em có thể bấm "Kiểm tra mũ thật" để dùng AI Camera kiểm tra mũ bảo hiểm ngoài đời thật!',
    ];
  }

  function start() {
    GameEngine.showIntro({
      icon: "🪖",
      title: "Chiếc mũ thần kỳ",
      desc: "Giúp bạn nhỏ đội mũ bảo hiểm đúng cách trước khi ra đường! Chọn đúng loại mũ, đặt đúng vị trí, chỉnh quai và cài khoá — AI sẽ chấm điểm từng bước cho em.",
      features: [
        "🪖 4 bước đội mũ chuẩn",
        "🤖 AI chấm điểm chi tiết",
        "🎥 Kiểm tra mũ thật bằng AI Camera",
      ],
      onStart: () => {
        stepIdx = 0;
        mistakes = 0;
        nextStep();
      },
    });
  }

  function nextStep() {
    const step = STEPS[stepIdx];
    const body = GameEngine.showGameplayShell({
      title: step.title,
      progressLabel: `Bước ${stepIdx + 1}/${STEPS.length}`,
    });

    body.innerHTML = `
      <p style="text-align:center; font-size:14.5px; font-weight:600; margin:6px 0 6px;">${step.question}</p>
      <div class="ge-choice-grid helmet-options" id="stepChoices"></div>
    `;

    GameEngine.aiSay(
      "So sánh 3 hình bên dưới rồi chọn cách đội mũ đúng nhất nhé!",
      "info",
    );
    GameEngine.attachHelpButton(body, {
      title: "Cách chơi — Chiếc mũ thần kỳ",
      steps: helpSteps(),
    });

    const wrap = document.getElementById("stepChoices");
    const shuffledOptions = [...step.options].sort(() => Math.random() - 0.5);
    shuffledOptions.forEach((opt) => {
      const btn = document.createElement("button");
      btn.className = "ge-choice-btn helmet-choice";
      btn.innerHTML =
        renderHelmetPreview(opt.visual) +
        `<span class="helmet-choice-label">${opt.label}</span>`;
      btn.onclick = () => pick(opt, btn, body);
      wrap.appendChild(btn);
    });
  }

  function renderHelmetPreview(v = {}) {
    const badge = v.badge || "";
    const svg = MascotSVG.character({
      helmetMode: v.helmetMode || "on",
      helmetTransform: v.helmetTransform || "",
      strapMode: v.strapMode || null,
      colorA: v.colorA || "#5FB2FF",
      colorB: v.colorB || "#1B6FE0",
    });
    return `
      <div class="helmet-preview">
        ${svg}
        ${badge ? `<div class="hp-badge">${badge}</div>` : ""}
      </div>
    `;
  }

  function pick(opt, btn, body) {
    document
      .querySelectorAll("#stepChoices .ge-choice-btn")
      .forEach((b) => (b.disabled = true));
    if (opt.ok) {
      GameEngine.fx("correct");
      btn.classList.add("selected-ok");
      GameEngine.aiSay("✅ " + opt.explain, "good");
      setTimeout(() => {
        stepIdx++;
        if (stepIdx < STEPS.length) nextStep();
        else finish();
      }, 1200);
    } else {
      GameEngine.fx("wrong");
      btn.classList.add("selected-bad");
      mistakes++;
      GameEngine.aiSay("❌ " + opt.explain + " Em thử chọn lại nhé!", "bad");
      setTimeout(() => {
        document
          .querySelectorAll("#stepChoices .ge-choice-btn")
          .forEach((b) => {
            b.disabled = false;
            b.classList.remove("selected-bad");
          });
      }, 900);
    }
  }

  function finish() {
    GameEngine.removeHelpButton();
    const perfect = mistakes === 0;
    const xp = Math.max(15, 30 - mistakes * 3);
    const coin = Math.max(20, 40 - mistakes * 4);

    GameEngine.showResults({
      success: true,
      title: perfect ? "🏆 Đội mũ hoàn hảo!" : "🎉 Hoàn thành đội mũ bảo hiểm!",
      summary: `Em đã hoàn thành 4 bước đội mũ bảo hiểm đúng cách${mistakes ? ` với ${mistakes} lần chọn sai` : " không sai lần nào"}.`,
      xp,
      coin,
      badgeKey: perfect ? "chuyen_gia_doi_mu" : null,
      badgeLabel: "Chuyên gia đội mũ bảo hiểm",
      onReplay: () => {
        stepIdx = 0;
        mistakes = 0;
        nextStep();
      },
    });

    setTimeout(() => {
      GameEngine.setAdvice(
        'Hãy kiểm tra xem mũ bảo hiểm thật của em đã đội đúng cách chưa nhé — bấm nút "Kiểm tra mũ thật" bên dưới để dùng AI Camera!',
      );
      addCameraButton();
    }, 500);
  }

  function addCameraButton() {
    const actions = document.querySelector(".ge-result-actions");
    if (!actions) return;
    const link = document.createElement("a");
    link.href = "ai-camera.php";
    link.className = "btn cta-primary";
    link.innerHTML = "🎥 Kiểm tra mũ thật (AI Camera)";
    actions.insertBefore(link, actions.firstChild);
  }

  start();
});
