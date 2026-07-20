/* game-city-hero.js — GAME 5: Siêu nhí xử lý tình huống
   ------------------------------------------------------------------
   Không còn là "tìm lỗi trên bản đồ thành phố" nữa — thay vào đó là
   chuỗi tình huống thực tế có thể gặp khi đang đi trên đường: xe cứu
   thương, tai nạn giao thông, xe buýt trường học, trời mưa, công
   trường, con vật chạy lạc... Em phải chọn đúng cách xử lý mỗi tình
   huống, AI giải thích thực tế vì sao lựa chọn đó đúng hay chưa đúng.
   ------------------------------------------------------------------ */

document.addEventListener("DOMContentLoaded", () => {
  GameEngine.init("cityHero", null); // game cuối cùng trong chuỗi 5 game

  const SITUATIONS = [
    {
      icon: "🚑",
      scene:
        "Một chiếc xe cứu thương đang hú còi phía sau, chạy vội tới bệnh viện.",
      options: [
        { t: "Nhường đường ngay, đi sát vào lề bên phải", ok: true },
        { t: "Đạp nhanh vượt lên trước xe cứu thương", ok: false },
        { t: "Đứng yên giữa đường vì hoảng sợ", ok: false },
      ],
      explainOk:
        "Chính xác! Xe cứu thương đang chở người bệnh cần cấp cứu — mọi người phải nhường đường ngay để không làm chậm trễ việc cứu người.",
      explainBad:
        "Chưa đúng. Xe cứu thương đang làm nhiệm vụ khẩn cấp — mọi phương tiện khác phải nhường đường ngay, tuyệt đối không cố vượt lên hay cản đường.",
    },
    {
      icon: "🚨",
      scene:
        "Em nhìn thấy một vụ va chạm giao thông vừa xảy ra phía trước, có người đang bị thương.",
      options: [
        { t: "Đứng cách xa, nhờ người lớn gọi cấp cứu 115", ok: true },
        { t: "Chạy lại thật gần để xem cho rõ", ok: false },
        { t: "Tự ý kéo, di chuyển người bị nạn", ok: false },
      ],
      explainOk:
        "Đúng rồi! Giữ khoảng cách an toàn và nhờ người lớn gọi cấp cứu (115) là việc quan trọng nhất — không tự ý can thiệp vì có thể khiến vết thương nặng hơn.",
      explainBad:
        "Chưa đúng và có thể gây nguy hiểm. Không nên chạy lại gần (dễ gặp nguy hiểm từ xe cộ khác) hay tự ý di chuyển người bị nạn — hãy để người lớn và nhân viên y tế xử lý.",
    },
    {
      icon: "🚒",
      scene: "Một xe cứu hoả đang bật còi, chạy tới dập một đám cháy gần đó.",
      options: [
        { t: "Nhường đường, dừng lại sát lề cho xe đi qua", ok: true },
        { t: "Cố đi tiếp bình thường như không có gì", ok: false },
        { t: "Chạy theo xem xe cứu hoả đi đâu", ok: false },
      ],
      explainOk:
        "Chuẩn xác! Xe cứu hoả cũng là xe ưu tiên như xe cứu thương — luôn phải nhường đường ngay khi thấy xe bật còi, đèn ưu tiên.",
      explainBad:
        "Chưa đúng. Xe cứu hoả cần đến hiện trường càng nhanh càng tốt — cản đường dù chỉ vài giây cũng có thể gây hậu quả nghiêm trọng.",
    },
    {
      icon: "👮",
      scene:
        "Đèn tín hiệu tại ngã tư đang bị hỏng (nhấp nháy vàng), một chú công an đang đứng ra hiệu lệnh điều khiển giao thông.",
      options: [
        { t: "Làm theo hiệu lệnh của chú công an", ok: true },
        { t: "Kệ chú công an, cứ đi theo ý mình", ok: false },
        { t: "Đứng lại giữa ngã tư chờ đèn tín hiệu sửa xong", ok: false },
      ],
      explainOk:
        "Chính xác! Khi có người điều khiển giao thông trực tiếp (công an, người hướng dẫn), hiệu lệnh của họ luôn được ưu tiên hơn cả đèn tín hiệu.",
      explainBad:
        "Chưa đúng. Hiệu lệnh của người điều khiển giao thông luôn quan trọng hơn đèn tín hiệu, đặc biệt khi đèn đang gặp sự cố — cần làm theo ngay để đảm bảo an toàn.",
    },
    {
      icon: "🚌",
      scene:
        "Một xe buýt trường học đang dừng hẳn phía trước, đèn báo đang nhấp nháy để đón/trả học sinh.",
      options: [
        {
          t: "Chờ xe buýt đi hẳn hoặc quan sát thật kỹ trước khi qua",
          ok: true,
        },
        { t: "Chạy băng qua ngay trước đầu xe buýt cho nhanh", ok: false },
        { t: "Chui qua gầm xe buýt để đi tắt", ok: false },
      ],
      explainOk:
        "Rất tốt! Xe buýt cỡ lớn che khuất tầm nhìn hai bên — cần chờ xe đi hẳn hoặc quan sát thật kỹ mới băng qua, vì có thể có xe khác đang tới mà em không thấy.",
      explainBad:
        "Rất nguy hiểm! Khu vực quanh xe buýt trường học có điểm mù rất lớn — tài xế các xe khác khó nhìn thấy em, dễ xảy ra tai nạn nghiêm trọng.",
    },
    {
      icon: "🌧️",
      scene: "Trời bất ngờ đổ mưa to trong lúc em đang đi xe đạp trên đường.",
      options: [
        {
          t: "Giảm tốc độ, tấp vào nơi an toàn trú mưa nếu mưa quá to",
          ok: true,
        },
        { t: "Đạp thật nhanh về nhà cho đỡ ướt", ok: false },
        { t: "Buông một tay để che đầu khỏi mưa", ok: false },
      ],
      explainOk:
        "Chính xác! Đường trơn khi trời mưa khiến xe rất dễ trượt ngã — luôn giảm tốc độ, và nếu mưa quá to hãy tìm chỗ an toàn để trú thay vì cố đi tiếp.",
      explainBad:
        "Chưa an toàn. Đường ướt trơn trượt hơn bình thường rất nhiều — đi nhanh hoặc buông tay lúc này rất dễ khiến em bị ngã hoặc mất lái.",
    },
    {
      icon: "🚧",
      scene:
        "Một đoạn vỉa hè phía trước đang thi công, có rào chắn và biển báo công trường.",
      options: [
        { t: "Đi vòng theo biển chỉ dẫn, tránh xa khu vực thi công", ok: true },
        { t: "Trèo qua rào chắn để đi tắt cho nhanh", ok: false },
        { t: "Đi sát mép rào để nhìn công nhân làm việc", ok: false },
      ],
      explainOk:
        "Đúng vậy! Rào chắn công trường được dựng lên để bảo vệ mọi người khỏi các nguy hiểm như vật liệu rơi, máy móc đang hoạt động — luôn đi vòng theo biển chỉ dẫn.",
      explainBad:
        "Rất nguy hiểm! Khu vực công trường có thể có vật liệu rơi, hố sâu hoặc máy móc đang hoạt động — tuyệt đối không trèo qua rào hoặc lại gần.",
    },
    {
      icon: "🐕",
      scene:
        "Một con chó lạ bất ngờ chạy ra giữa đường ngay trước mặt khi em đang đi bộ.",
      options: [
        { t: "Dừng lại bình tĩnh, đứng yên chờ con vật đi qua", ok: true },
        { t: "Hoảng sợ chạy ra giữa đường để né", ok: false },
        { t: "Đuổi theo hoặc trêu chọc con vật", ok: false },
      ],
      explainOk:
        "Chính xác! Đứng yên, bình tĩnh là cách an toàn nhất — vừa tránh làm con vật hoảng sợ, vừa không tự đẩy mình vào vùng nguy hiểm có xe cộ qua lại.",
      explainBad:
        "Chưa an toàn. Hoảng loạn chạy ra giữa đường rất dễ khiến em va phải xe đang chạy tới — hãy luôn giữ bình tĩnh trong mọi tình huống bất ngờ.",
    },
  ];

  const ROUND_COUNT = 6;
  let rounds = [];
  let roundIdx = 0;
  let correctCount = 0;

  function helpSteps() {
    return [
      `AI sẽ đưa ra ${ROUND_COUNT} tình huống thực tế có thể gặp khi đang đi trên đường.`,
      "Mỗi tình huống có 3 cách xử lý — em hãy chọn cách mà em nghĩ là AN TOÀN và ĐÚNG nhất.",
      "AI sẽ giải thích thực tế vì sao lựa chọn đó đúng hoặc chưa đúng.",
      "Xử lý đúng nhiều tình huống để trở thành Siêu Nhí xử lý tình huống xuất sắc!",
    ];
  }

  function start() {
    GameEngine.showIntro({
      icon: "🦸",
      title: "Siêu nhí xử lý tình huống",
      desc: "Trên đường đi học, đi chơi, em có thể gặp rất nhiều tình huống bất ngờ: xe cứu thương, tai nạn giao thông, trời mưa, công trường... Em sẽ xử lý mỗi tình huống đó như thế nào?",
      features: [
        "🚑 Tình huống thực tế ngẫu nhiên",
        "🤖 AI giải thích cách xử lý đúng",
        "🦸 Trở thành Siêu Nhí xử lý tình huống giỏi nhất",
      ],
      onStart: () => {
        roundIdx = 0;
        correctCount = 0;
        rounds = [...SITUATIONS]
          .sort(() => Math.random() - 0.5)
          .slice(0, ROUND_COUNT);
        nextRound();
      },
    });
  }

  function nextRound() {
    const s = rounds[roundIdx];
    const body = GameEngine.showGameplayShell({
      title: "🚦 Tình huống trên đường",
      progressLabel: `Tình huống ${roundIdx + 1}/${ROUND_COUNT}`,
    });

    body.innerHTML = `
      <div class="ge-scene" style="min-height:170px; display:flex; align-items:center; justify-content:center; margin-bottom:18px;">
        <div style="font-size:72px;">${s.icon}</div>
      </div>
      <p style="text-align:center; font-size:15.5px; font-weight:600; line-height:1.6; max-width:560px; margin:0 auto 4px;">${s.scene}</p>
      <p style="text-align:center; font-size:12.5px; color:rgba(255,255,255,0.5); margin-bottom:16px;">Em sẽ làm gì trong tình huống này?</p>
      <div class="ge-choice-grid" id="situationChoices"></div>
    `;

    GameEngine.aiSay(
      "Em hãy đọc kỹ tình huống rồi chọn cách xử lý đúng nhất nhé!",
      "info",
    );
    GameEngine.attachHelpButton(body, {
      title: "Cách chơi — Siêu nhí xử lý tình huống",
      steps: helpSteps(),
    });

    const grid = document.getElementById("situationChoices");
    const shuffledOptions = [...s.options].sort(() => Math.random() - 0.5);
    shuffledOptions.forEach((opt) => {
      const btn = document.createElement("button");
      btn.className = "ge-choice-btn";
      btn.innerHTML = `<span class="gc-ic">${opt.ok ? "✅" : "⚠️"}</span>${opt.t}`;
      btn.onclick = () => pick(opt, s, btn);
      grid.appendChild(btn);
    });
  }

  function pick(opt, s, btn) {
    document
      .querySelectorAll("#situationChoices .ge-choice-btn")
      .forEach((b) => (b.disabled = true));

    if (opt.ok) {
      GameEngine.fx("correct");
      btn.classList.add("selected-ok");
      correctCount++;
      GameEngine.aiSay("✅ " + s.explainOk, "good");
    } else {
      GameEngine.fx("wrong");
      btn.classList.add("selected-bad");
      GameEngine.aiSay("❌ " + s.explainBad, "bad");
    }

    setTimeout(() => {
      roundIdx++;
      if (roundIdx < ROUND_COUNT) nextRound();
      else finish();
    }, 2000);
  }

  function finish() {
    GameEngine.removeHelpButton();
    const perfect = correctCount === ROUND_COUNT;
    const xp = Math.max(20, Math.round(40 * (correctCount / ROUND_COUNT)));
    const coin = Math.max(30, Math.round(60 * (correctCount / ROUND_COUNT)));

    GameEngine.showResults({
      success: true,
      title: perfect
        ? "🏆 Xử lý tình huống hoàn hảo!"
        : "🎉 Hoàn thành thử thách!",
      summary: `Em đã xử lý đúng ${correctCount}/${ROUND_COUNT} tình huống thực tế trên đường. ${perfect ? "Xuất sắc, em đã sẵn sàng ứng phó với mọi tình huống bất ngờ!" : "Hãy xem lại các giải thích của AI để lần sau xử lý tốt hơn nhé!"}`,
      xp,
      coin,
      badgeKey: perfect ? "sieu_nhi_xu_ly_tinh_huong" : null,
      badgeLabel: "Siêu Nhí xử lý tình huống",
      onReplay: () => {
        roundIdx = 0;
        correctCount = 0;
        rounds = [...SITUATIONS]
          .sort(() => Math.random() - 0.5)
          .slice(0, ROUND_COUNT);
        nextRound();
      },
    });

    setTimeout(() => {
      GameEngine.setAdvice(
        "Em đã hoàn thành cả 5 trò chơi! Hãy luôn bình tĩnh quan sát và nhớ những cách xử lý này khi thực sự gặp phải trên đường nhé!",
      );
    }, 700);
  }

  start();
});
