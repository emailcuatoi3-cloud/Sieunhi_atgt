/* game-pedestrian.js — GAME 1: Người qua đường thông minh
   ------------------------------------------------------------------
   Mô phỏng qua đường THẬT: nhân vật di chuyển theo từng bước, đèn tín
   hiệu người đi bộ chuyển ĐI ↔ CHỜ theo chu kỳ, xe chạy liên tục trên
   3 làn đường khi đèn CHỜ (xe được đi). Di chuyển sai lúc xe đang chạy
   qua làn của mình sẽ bị tông và mất mạng. Hướng dẫn chơi là nút riêng,
   xem khi cần chứ không bắt buộc.
   ------------------------------------------------------------------ */

document.addEventListener("DOMContentLoaded", () => {
  GameEngine.init("pedestrian", {
    url: "game-helmet.php",
    label: "Game tiếp theo: Chiếc mũ thần kỳ",
  });

  const CONTEXTS = [
    {
      key: "school",
      icon: "🏫",
      label: "Trước cổng trường",
      night: false,
      rain: false,
    },
    {
      key: "mainroad",
      icon: "🚗",
      label: "Đường lớn nhiều xe qua lại",
      night: false,
      rain: false,
    },
    {
      key: "busy",
      icon: "🏙️",
      label: "Giờ cao điểm — xe cộ đông đúc",
      night: false,
      rain: false,
    },
    {
      key: "rain",
      icon: "🌧️",
      label: "Trời đang mưa",
      night: false,
      rain: true,
    },
    { key: "night", icon: "🌙", label: "Buổi tối", night: true, rain: false },
  ];

  const TOTAL_CROSSINGS = 3;
  const START_LIVES = 3;
  const RED_MS = 5500; // đèn xe ĐỎ (xe dừng) → người đi bộ được đi
  const GREEN_MS = 4500; // đèn xe XANH (xe chạy) → người đi bộ phải chờ
  const YELLOW_MS = 1800; // đèn xe VÀNG (xe chuẩn bị dừng) → vẫn còn xe, phải chờ
  const LANE_DIRS = { 1: 1, 2: -1, 3: 1 }; // 1 = trái→phải, -1 = phải→trái
  const CAR_ICONS = ["🚗", "🚕", "🚙", "🚌", "🚚"];
  const ROW_BOTTOM = { 0: 7.5, 1: 26.67, 2: 50, 3: 73.33, 4: 92.5 };

  // Nhân vật cậu bé — vẽ SVG tuỳ chỉnh thay vì emoji nhỏ, để trông "thật" và nổi bật hơn.
  const PLAYER_SVG = `
    <svg viewBox="0 0 60 82" width="46" height="63" xmlns="http://www.w3.org/2000/svg">
      <ellipse cx="30" cy="78" rx="15" ry="4" fill="#000" opacity="0.28"/>
      <rect x="20" y="50" width="8" height="20" rx="3" fill="#22315e"/>
      <rect x="32" y="50" width="8" height="20" rx="3" fill="#22315e"/>
      <rect x="18" y="68" width="13" height="7" rx="3.5" fill="#101830"/>
      <rect x="29" y="68" width="13" height="7" rx="3.5" fill="#101830"/>
      <rect x="12" y="29" width="11" height="21" rx="4" fill="#f59e0b"/>
      <rect x="17" y="27" width="26" height="27" rx="10" fill="#3b82f6"/>
      <path d="M17 34 L30 40 L43 34" stroke="#eaf6ff" stroke-width="3" fill="none" stroke-linecap="round" opacity="0.85"/>
      <rect x="9" y="30" width="9" height="19" rx="4.5" fill="#ffd8b0"/>
      <rect x="42" y="30" width="9" height="19" rx="4.5" fill="#ffd8b0"/>
      <rect x="25" y="19" width="10" height="9" fill="#ffd8b0"/>
      <circle cx="30" cy="14" r="13.5" fill="#ffd8b0"/>
      <path d="M16 13 C16 2 44 2 44 13 C44 6 35 4 30 4 C25 4 16 6 16 13 Z" fill="#3a2417"/>
      <circle cx="25" cy="14" r="1.7" fill="#1a1a1a"/>
      <circle cx="35" cy="14" r="1.7" fill="#1a1a1a"/>
      <path d="M25 18.5 Q30 21.5 35 18.5" stroke="#8a4a2b" stroke-width="1.5" fill="none" stroke-linecap="round"/>
      <circle cx="21" cy="17" r="2" fill="#ff9ba8" opacity="0.55"/>
      <circle cx="39" cy="17" r="2" fill="#ff9ba8" opacity="0.55"/>
    </svg>`;
  const PLAYER_SVG_CRASH = `
    <svg viewBox="0 0 60 82" width="46" height="63" xmlns="http://www.w3.org/2000/svg">
      <ellipse cx="30" cy="78" rx="15" ry="4" fill="#000" opacity="0.28"/>
      <rect x="20" y="50" width="8" height="20" rx="3" fill="#22315e"/>
      <rect x="32" y="50" width="8" height="20" rx="3" fill="#22315e"/>
      <rect x="18" y="68" width="13" height="7" rx="3.5" fill="#101830"/>
      <rect x="29" y="68" width="13" height="7" rx="3.5" fill="#101830"/>
      <rect x="12" y="29" width="11" height="21" rx="4" fill="#f59e0b"/>
      <rect x="17" y="27" width="26" height="27" rx="10" fill="#ef4444"/>
      <rect x="9" y="30" width="9" height="19" rx="4.5" fill="#ffd8b0"/>
      <rect x="42" y="30" width="9" height="19" rx="4.5" fill="#ffd8b0"/>
      <rect x="25" y="19" width="10" height="9" fill="#ffd8b0"/>
      <circle cx="30" cy="14" r="13.5" fill="#ffd8b0"/>
      <path d="M16 13 C16 2 44 2 44 13 C44 6 35 4 30 4 C25 4 16 6 16 13 Z" fill="#3a2417"/>
      <path d="M23 12 L27 16 M27 12 L23 16" stroke="#1a1a1a" stroke-width="1.6" stroke-linecap="round"/>
      <path d="M33 12 L37 16 M37 12 L33 16" stroke="#1a1a1a" stroke-width="1.6" stroke-linecap="round"/>
      <path d="M25 20 Q30 17 35 20" stroke="#8a4a2b" stroke-width="1.5" fill="none" stroke-linecap="round"/>
    </svg>`;

  let roundIdx = 0;
  let lives = START_LIVES;
  let usedContexts = [];
  let playerRow = 0;
  let phase = "red"; // 'red' (an toàn) → 'green' (nguy hiểm) → 'yellow' (sắp dừng) → 'red'...
  let phaseTimer = RED_MS;
  let carsByLane = { 1: [], 2: [], 3: [] };
  let loopId = null;
  let crashed = false;
  let finished = false;
  let carIdSeq = 0;
  let totalCrashes = 0;

  function start() {
    GameEngine.showIntro({
      icon: "🚶",
      title: "Người qua đường thông minh",
      desc: "Điều khiển bạn nhỏ băng qua đường thật sự an toàn! Theo dõi đèn tín hiệu, quan sát xe cộ và chọn đúng thời điểm để di chuyển — sai một bước có thể bị xe tông đấy!",
      features: [
        "🚦 Đèn tín hiệu đổi liên tục",
        "🚗 Xe chạy thật trên 3 làn đường",
        "❤️ 3 mạng cho cả hành trình",
      ],
      onStart: () => {
        roundIdx = 0;
        lives = START_LIVES;
        usedContexts = [];
        totalCrashes = 0;
        nextCrossing();
      },
    });
  }

  function helpSteps() {
    return [
      "Nhìn <b>đèn tín hiệu giao thông</b> ở góc phải: <b>Đỏ</b> là xe dừng lại — an toàn để đi; <b>Xanh</b> là xe đang chạy — nguy hiểm; <b>Vàng</b> là xe sắp dừng nhưng vẫn còn xe.",
      'Bấm nút <b>"⬆️ Bước tiếp"</b> (hoặc phím mũi tên ↑) để tiến lên từng làn đường một.',
      "Chỉ nên bước vào làn đường khi đèn đang ĐỎ và không có xe đang chạy tới gần vạch qua đường của em.",
      "Nếu đang đứng giữa làn đường mà đèn chuyển XANH và có xe chạy qua đúng vị trí — em sẽ bị tông!",
      'Bấm <b>"⬇️ Lùi lại"</b> nếu thấy nguy hiểm để lùi về làn an toàn phía sau.',
      "Đi hết 3 làn đường lên tới vỉa hè bên kia là hoàn thành 1 lượt. Em có 3 mạng cho toàn bộ 3 lượt qua đường!",
    ];
  }

  function nextCrossing() {
    let ctx;
    do {
      ctx = CONTEXTS[Math.floor(Math.random() * CONTEXTS.length)];
    } while (
      usedContexts.includes(ctx.key) &&
      usedContexts.length < CONTEXTS.length
    );
    usedContexts.push(ctx.key);

    playerRow = 0;
    phase = "red";
    phaseTimer = RED_MS;
    carsByLane = { 1: [], 2: [], 3: [] };
    crashed = false;
    finished = false;

    const body = GameEngine.showGameplayShell({
      title: `${ctx.icon} ${ctx.label}`,
      progressLabel: `Lượt ${roundIdx + 1}/${TOTAL_CROSSINGS}`,
    });

    body.innerHTML = `
      <div class="cross-sim ${ctx.night ? "is-night" : ""} ${ctx.rain ? "is-rain" : ""}" id="crossSim">
        <div class="cs-hud-top">
          <div class="cs-hud-chip" id="csRoundChip">Lượt ${roundIdx + 1}/${TOTAL_CROSSINGS}</div>
          <div class="cs-hud-chip cs-lives" id="csLives"></div>
        </div>
        <div class="cs-signal-box">
          <div class="cs-trafficlight">
            <i class="red" id="tlRed"></i>
            <i class="yellow" id="tlYellow"></i>
            <i class="green" id="tlGreen"></i>
          </div>
          <div class="cs-ped-icon" id="csPedIcon">🚶</div>
          <div class="cs-signal-label walk" id="csSignalLabel">ĐI</div>
          <div class="cs-timer-bar"><i id="csTimerBar" style="width:100%; background:var(--green);"></i></div>
        </div>
        <div class="cs-sidewalk top"><div class="cs-sidewalk-texture"></div><div class="cs-goal-flag">🏫</div></div>
        <div class="cs-road" id="csRoad">
          <div class="cs-lane" data-lane="3" id="lane3"><div class="cs-stopline"></div></div>
          <div class="cs-lane" data-lane="2" id="lane2"><div class="cs-stopline"></div></div>
          <div class="cs-lane" data-lane="1" id="lane1"><div class="cs-stopline"></div></div>
        </div>
        <div class="cs-crosswalk"></div>
        <div class="cs-sidewalk bottom"><div class="cs-sidewalk-texture"></div></div>
        <div class="cs-player" id="csPlayer" style="bottom:${ROW_BOTTOM[0]}%;">${PLAYER_SVG}</div>
      </div>
      <div class="cs-controls">
        <button class="cs-move-btn secondary" id="csBackBtn">⬇️ Lùi lại</button>
        <button class="cs-move-btn" id="csMoveBtn">⬆️ Bước tiếp</button>
      </div>
      <p class="cs-hint">Mẹo: chỉ bước tiếp khi đèn ĐỎ (xe đã dừng) — đèn XANH nghĩa là xe đang chạy!</p>
    `;

    updateLivesHud();
    GameEngine.aiSay(
      `Em đang ở "${ctx.label}". Hãy quan sát đèn tín hiệu và xe cộ thật kỹ trước khi bước nhé!`,
      "info",
    );
    GameEngine.attachHelpButton(body, {
      title: "Cách chơi — Người qua đường thông minh",
      steps: helpSteps(),
    });

    document.getElementById("csMoveBtn").onclick = moveForward;
    document.getElementById("csBackBtn").onclick = moveBack;
    document.addEventListener("keydown", keyHandler);

    if (loopId) clearInterval(loopId);
    loopId = setInterval(tick, 50);
  }

  function keyHandler(e) {
    if (e.key === "ArrowUp") {
      e.preventDefault();
      moveForward();
    } else if (e.key === "ArrowDown") {
      e.preventDefault();
      moveBack();
    }
  }

  function updateLivesHud() {
    const el = document.getElementById("csLives");
    if (el)
      el.textContent = "❤️".repeat(lives) + "🖤".repeat(START_LIVES - lives);
  }

  /* ---------------- Vòng lặp game (đèn + xe + va chạm) ---------------- */
  function tick() {
    if (finished) return;
    phaseTimer -= 50;
    if (phaseTimer <= 0) advancePhase();
    updateSignalUI();
    updateCars();
    checkCollision();
  }

  function advancePhase() {
    if (phase === "red") {
      phase = "green";
      phaseTimer = GREEN_MS;
    } else if (phase === "green") {
      phase = "yellow";
      phaseTimer = YELLOW_MS;
    } else {
      phase = "red";
      phaseTimer = RED_MS;
      carsByLane = { 1: [], 2: [], 3: [] };
      clearLaneDom();
    }
  }

  function clearLaneDom() {
    [1, 2, 3].forEach((l) => {
      const el = document.getElementById("lane" + l);
      if (el) el.querySelectorAll(".cs-car").forEach((c) => c.remove());
    });
  }

  function updateSignalUI() {
    const icon = document.getElementById("csPedIcon");
    const label = document.getElementById("csSignalLabel");
    const bar = document.getElementById("csTimerBar");
    const tlRed = document.getElementById("tlRed");
    const tlYellow = document.getElementById("tlYellow");
    const tlGreen = document.getElementById("tlGreen");
    if (!icon || !label || !bar) return;

    const totalDur =
      phase === "red" ? RED_MS : phase === "green" ? GREEN_MS : YELLOW_MS;
    const pct = Math.max(0, (phaseTimer / totalDur) * 100);
    bar.style.width = pct + "%";

    tlRed.classList.toggle("on", phase === "red");
    tlYellow.classList.toggle("on", phase === "yellow");
    tlGreen.classList.toggle("on", phase === "green");

    if (phase === "red") {
      icon.textContent = "🚶";
      icon.classList.remove("blink");
      label.textContent = "ĐI — XE ĐÃ DỪNG";
      label.className = "cs-signal-label walk";
      bar.style.background = "var(--green)";
    } else if (phase === "yellow") {
      icon.textContent = "✋";
      icon.classList.add("blink");
      label.textContent = "CHỜ — XE SẮP DỪNG";
      label.className = "cs-signal-label warn";
      bar.style.background = "var(--yellow)";
    } else {
      icon.textContent = "✋";
      icon.classList.remove("blink");
      label.textContent = "CHỜ — XE ĐANG CHẠY";
      label.className = "cs-signal-label stop";
      bar.style.background = "var(--red)";
    }
  }

  function updateCars() {
    if (phase !== "green" && phase !== "yellow") return;
    [1, 2, 3].forEach((lane) => {
      const dir = LANE_DIRS[lane];
      carsByLane[lane].forEach((car) => {
        car.x += dir * car.speed;
      });
      carsByLane[lane] = carsByLane[lane].filter(
        (car) => car.x > -8 && car.x < 108,
      );
      if (
        phase === "green" &&
        carsByLane[lane].length < 3 &&
        Math.random() < 0.06
      ) {
        const startX = dir === 1 ? -6 : 106;
        const tooClose = carsByLane[lane].some(
          (c) => Math.abs(c.x - startX) < 30,
        );
        if (!tooClose) {
          carsByLane[lane].push({
            id: carIdSeq++,
            x: startX,
            speed: 0.9 + Math.random() * 0.7,
            icon: CAR_ICONS[Math.floor(Math.random() * CAR_ICONS.length)],
          });
        }
      }
      renderLane(lane);
    });
  }

  function renderLane(lane) {
    const laneEl = document.getElementById("lane" + lane);
    if (!laneEl) return;
    const dir = LANE_DIRS[lane];
    // Icon xe (🚗🚕🚙🚌🚚) mặc định hướng về bên TRÁI trong hầu hết font emoji.
    // Nếu xe chạy sang PHẢI (dir = 1) cần lật ngang để đầu xe hướng đúng chiều di chuyển.
    const needsFlip = dir === 1;
    const existingIds = new Set();
    carsByLane[lane].forEach((car) => {
      existingIds.add(car.id);
      let el = laneEl.querySelector(`[data-car-id="${car.id}"]`);
      if (!el) {
        el = document.createElement("div");
        el.className = "cs-car" + (needsFlip ? " flip" : "");
        el.dataset.carId = car.id;
        el.textContent = car.icon;
        laneEl.appendChild(el);
      }
      el.style.left = car.x + "%";
    });
    laneEl.querySelectorAll(".cs-car").forEach((el) => {
      if (!existingIds.has(parseInt(el.dataset.carId, 10))) el.remove();
    });
  }

  function checkCollision() {
    if (crashed || finished || playerRow < 1 || playerRow > 3) return;
    const carsHere = carsByLane[playerRow] || [];
    const hit = carsHere.some((car) => car.x > 40 && car.x < 60);
    if (hit) handleCrash();
  }

  /* ---------------- Điều khiển người chơi ---------------- */
  function moveForward() {
    if (crashed || finished) return;
    if (playerRow >= 4) return;

    // Luật cứng: đèn KHÔNG phải màu Đỏ (Xanh hoặc Vàng) mà vẫn bước vào lòng đường -> luôn bị tông.
    if (phase !== "red" && playerRow < 3) {
      playerRow++;
      updatePlayerPosition();
      GameEngine.fx("click");
      setTimeout(handleCrash, 120);
      return;
    }

    playerRow++;
    updatePlayerPosition();
    GameEngine.fx("click");

    if (playerRow >= 1 && playerRow <= 3) {
      const carsHere = carsByLane[playerRow] || [];
      const immediateHit = carsHere.some((car) => car.x > 32 && car.x < 68);
      if (immediateHit) {
        setTimeout(handleCrash, 80);
        return;
      }
    }
    if (playerRow === 4) setTimeout(handleSuccess, 250);
  }

  function moveBack() {
    if (crashed || finished) return;
    if (playerRow <= 0) return;
    playerRow--;
    updatePlayerPosition();
    GameEngine.fx("click");
  }

  function updatePlayerPosition() {
    const el = document.getElementById("csPlayer");
    if (el) el.style.bottom = ROW_BOTTOM[playerRow] + "%";
  }

  function handleCrash() {
    if (crashed || finished) return;
    crashed = true;
    totalCrashes++;
    lives--;
    updateLivesHud();
    GameEngine.fx("wrong");
    const el = document.getElementById("csPlayer");
    if (el) {
      el.classList.add("crashed");
      el.innerHTML = PLAYER_SVG_CRASH;
    }
    GameEngine.aiSay(
      '💥 Ôi không, em vừa bị xe tông vì bước vào làn đường lúc xe đang chạy tới! Hãy chờ đèn "ĐI" và quan sát kỹ trước khi bước nhé.',
      "bad",
    );

    setTimeout(() => {
      if (lives <= 0) {
        finished = true;
        clearInterval(loopId);
        document.removeEventListener("keydown", keyHandler);
        setTimeout(finishGame, 600);
        return;
      }
      playerRow = 0;
      crashed = false;
      if (el) {
        el.classList.remove("crashed");
        el.innerHTML = PLAYER_SVG;
      }
      updatePlayerPosition();
    }, 1100);
  }

  function handleSuccess() {
    if (finished) return;
    finished = true;
    clearInterval(loopId);
    document.removeEventListener("keydown", keyHandler);
    GameEngine.fx("correct");
    GameEngine.aiSay(
      "✅ Tuyệt vời! Em đã qua đường an toàn — quan sát và chờ đúng thời điểm rất tốt!",
      "good",
    );

    setTimeout(() => {
      roundIdx++;
      if (roundIdx < TOTAL_CROSSINGS) nextCrossing();
      else finishGame();
    }, 1200);
  }

  /* ---------------- Kết thúc game ---------------- */
  function finishGame() {
    if (loopId) clearInterval(loopId);
    document.removeEventListener("keydown", keyHandler);
    GameEngine.removeHelpButton();

    const success = lives > 0;
    const accuracy = Math.max(
      0,
      Math.round(100 - (totalCrashes / (TOTAL_CROSSINGS + START_LIVES)) * 100),
    );
    const xp = success
      ? Math.max(20, Math.round(40 * (lives / START_LIVES) + roundIdx * 5))
      : Math.max(5, roundIdx * 8);
    const coin = success
      ? Math.max(25, Math.round(55 * (lives / START_LIVES) + roundIdx * 6))
      : Math.max(10, roundIdx * 10);
    const perfect = success && totalCrashes === 0;

    GameEngine.showResults({
      success: true,
      title: success
        ? perfect
          ? "🏆 Qua đường hoàn hảo, không va chạm nào!"
          : "🎉 Hoàn thành cả 3 lượt qua đường!"
        : "💡 Em đã hết mạng!",
      summary: success
        ? `Em đã qua đường an toàn ${TOTAL_CROSSINGS}/${TOTAL_CROSSINGS} lượt, còn lại ${lives}/${START_LIVES} mạng, với ${totalCrashes} lần va chạm.`
        : `Em đã hoàn thành được ${roundIdx}/${TOTAL_CROSSINGS} lượt qua đường trước khi hết mạng. Đừng lo, hãy thử lại và quan sát kỹ đèn tín hiệu hơn nhé!`,
      xp,
      coin,
      badgeKey: perfect ? "nguoi_qua_duong_thong_minh" : null,
      badgeLabel: "Người qua đường thông minh",
      onReplay: () => {
        roundIdx = 0;
        lives = START_LIVES;
        usedContexts = [];
        totalCrashes = 0;
        nextCrossing();
      },
    });

    setTimeout(() => {
      GameEngine.setAdvice(
        success
          ? "Em làm rất tốt! Hãy nhớ luôn chờ đèn xanh dành cho người đi bộ và quan sát xe cộ hai chiều trước khi bước xuống đường nhé."
          : "Hôm nay em hãy thử quan sát kỹ đèn tín hiệu và tốc độ xe cộ trên đường đi học thật, để luyện phản xạ qua đường an toàn hơn!",
      );
    }, 600);
  }

  start();
});
