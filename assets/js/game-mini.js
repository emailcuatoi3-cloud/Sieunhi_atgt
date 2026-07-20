/* game-mini.js — Trung tâm điều khiển cho 6 mini-game, mỗi game có 5 cấp độ
   tăng dần độ khó. Dùng trên trang game-play.php (mỗi game 1 trang riêng).
   ------------------------------------------------------------------ */

document.addEventListener("DOMContentLoaded", () => {
  let currentGameId = null;
  let currentCleanup = null;

  function showToast(msg) {
    const toast = document.getElementById("toast");
    toast.textContent = msg;
    toast.classList.add("show");
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(() => toast.classList.remove("show"), 2200);
  }

  function fx(name) {
    if (window.SoundFX && typeof SoundFX[name] === "function") SoundFX[name]();
  }

  function bumpRewards(xp, coin, badgeKey, badgeLabel) {
    const xpEl = document.getElementById("xpVal");
    const coinEl = document.getElementById("coinVal");
    xpEl.textContent = parseInt(xpEl.textContent) + xp;
    coinEl.textContent = parseInt(coinEl.textContent) + coin;

    if (!window.IS_STUDENT) {
      showToast(
        "💡 Đăng nhập bằng tài khoản Học sinh để lưu kết quả này thật nhé!",
      );
      return;
    }

    fetch("save-progress.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        game_id: currentGameId,
        xp,
        coin,
        badge_key: badgeKey || null,
        badge_label: badgeLabel || null,
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) {
          showToast("⚠️ " + (data.error || "Không lưu được tiến trình"));
          return;
        }
        xpEl.textContent = data.xp;
        coinEl.textContent = data.coin;
        document.getElementById("badgeVal").textContent = data.badgeCount;
        const levelEl = document.getElementById("levelVal");
        if (levelEl) levelEl.textContent = data.level;

        if (data.newBadge)
          setTimeout(
            () => showToast("🏅 Huy hiệu mới: " + data.badgeLabel + "!"),
            900,
          );
        if (data.leveledUp) {
          setTimeout(
            () => {
              showToast("🎉 Chúc mừng! Bạn vừa lên Cấp " + data.level + "!");
              fx("levelUp");
            },
            data.newBadge ? 1800 : 900,
          );
        }
      })
      .catch(() => showToast("⚠️ Mất kết nối, chưa lưu được tiến trình."));
  }

  function shuffle(arr) {
    return [...arr].sort(() => Math.random() - 0.5);
  }

  function setLevelLabel(level) {
    const el = document.getElementById("gpLevelLabel");
    if (el) el.textContent = `Cấp ${level}/5`;
  }

  function advanceOrFinish(
    game,
    root,
    finalXp,
    finalCoin,
    badgeKey,
    badgeLabel,
  ) {
    if (game.level < 5) {
      showToast(
        `✅ Hoàn thành cấp ${game.level}! Chuyển sang cấp ${game.level + 1}...`,
      );
      fx("success");
      game.level++;
      setLevelLabel(game.level);
      setTimeout(() => game.renderLevel(root), 1300);
    } else {
      fx("success");
      showToast(
        `🏆 Hoàn thành cả 5 cấp độ! +${finalXp} XP · +${finalCoin} Coin`,
      );
      bumpRewards(finalXp, finalCoin, badgeKey, badgeLabel);
    }
  }

  /* =====================================================================
     GAME 1 — Kéo thả biển báo giao thông (5 cấp: 5→9 biển báo)
     ===================================================================== */
  const SIGN_POOL = [
    {
      id: "stop",
      icon: "🛑",
      name: "STOP",
      meaning: "Dừng lại hoàn toàn trước khi đi tiếp",
    },
    {
      id: "school",
      icon: "🚸",
      name: "Trường học",
      meaning: "Khu vực gần trường học, giảm tốc độ",
    },
    {
      id: "nocross",
      icon: "🚫",
      name: "Cấm đi ngược chiều",
      meaning: "Không được đi vào theo hướng này",
    },
    {
      id: "bike",
      icon: "🚲",
      name: "Đường dành cho xe đạp",
      meaning: "Chỉ dành cho người đi xe đạp",
    },
    {
      id: "hospital",
      icon: "🏥",
      name: "Bệnh viện",
      meaning: "Gần bệnh viện, giữ yên tĩnh và cẩn thận",
    },
    {
      id: "noparking",
      icon: "🅿️",
      name: "Cấm đỗ xe",
      meaning: "Không được dừng, đỗ xe tại đây",
    },
    {
      id: "roundabout",
      icon: "🔄",
      name: "Vòng xuyến",
      meaning: "Đi theo chiều vòng xuyến phía trước",
    },
    {
      id: "slippery",
      icon: "💧",
      name: "Đường trơn trượt",
      meaning: "Giảm tốc độ, cẩn thận khi trời mưa",
    },
    {
      id: "pedestrian",
      icon: "🚶",
      name: "Đường người đi bộ",
      meaning: "Ưu tiên cho người đi bộ qua lại",
    },
  ];
  const signMatch = {
    title: "🚸 Kéo thả biển báo giao thông",
    desc: "Kéo mỗi biển báo bên trái vào đúng ô ý nghĩa của nó bên phải.",
    level: 1,
    correctCount: 0,
    placed: {},
    data: [],
    init(root) {
      this.level = 1;
      this.renderLevel(root);
    },
    renderLevel(root) {
      setLevelLabel(this.level);
      this.correctCount = 0;
      this.placed = {};
      this.data = SIGN_POOL.slice(0, 4 + this.level); // 5,6,7,8,9
      root.innerHTML = `<div class="drag-area"><div class="sign-pool" id="signPool"></div><div class="drop-list" id="dropList"></div></div>`;
      document.getElementById("gpScore").innerHTML =
        `✅ <span id="correctCount">0</span> / ${this.data.length} đúng`;

      const pool = root.querySelector("#signPool");
      const list = root.querySelector("#dropList");

      shuffle(this.data).forEach((s) => {
        const chip = document.createElement("div");
        chip.className = "sign-chip";
        chip.draggable = true;
        chip.id = "sign-" + s.id;
        chip.dataset.id = s.id;
        chip.innerHTML = `${s.icon}<small>${s.name}</small>`;
        chip.addEventListener("dragstart", (e) => {
          e.dataTransfer.setData("text/plain", s.id);
          chip.classList.add("dragging");
        });
        chip.addEventListener("dragend", () =>
          chip.classList.remove("dragging"),
        );
        pool.appendChild(chip);
      });

      shuffle(this.data).forEach((s) => {
        const slot = document.createElement("div");
        slot.className = "drop-slot";
        slot.dataset.id = s.id;
        slot.innerHTML = `<span class="slot-filled" id="filled-${s.id}"></span><span class="slot-label">${s.meaning}</span>`;
        slot.addEventListener("dragover", (e) => {
          e.preventDefault();
          slot.classList.add("over");
        });
        slot.addEventListener("dragleave", () => slot.classList.remove("over"));
        slot.addEventListener("drop", (e) => {
          e.preventDefault();
          slot.classList.remove("over");
          this.handleDrop(
            e.dataTransfer.getData("text/plain"),
            s.id,
            slot,
            root,
          );
        });
        list.appendChild(slot);
      });
    },
    handleDrop(signId, slotId, slotEl, root) {
      if (this.placed[slotId]) return;
      const sign = this.data.find((s) => s.id === signId);
      if (signId === slotId) {
        slotEl.classList.add("correct");
        document.getElementById("filled-" + slotId).textContent = sign.icon;
        const chip = document.getElementById("sign-" + signId);
        if (chip) chip.remove();
        this.placed[slotId] = true;
        this.correctCount++;
        document.getElementById("correctCount").textContent = this.correctCount;
        fx("correct");
        showToast("✅ Chính xác! " + sign.name);
        if (this.correctCount === this.data.length) {
          setTimeout(
            () =>
              advanceOrFinish(
                this,
                root,
                30,
                50,
                "bien_bao_chuyen_gia",
                "Chuyên gia biển báo",
              ),
            400,
          );
        }
      } else {
        fx("wrong");
        slotEl.classList.add("wrong");
        setTimeout(() => slotEl.classList.remove("wrong"), 500);
        showToast("❌ Chưa đúng, thử lại nhé");
      }
    },
    destroy() {},
  };

  /* =====================================================================
     GAME 2 — Hành trình đến trường an toàn (5 cấp: 5→9 bước, sắp đúng thứ tự)
     ===================================================================== */
  const COMMUTE_STEPS = [
    { id: 0, icon: "🏠", text: "Ra khỏi nhà, kiểm tra cặp sách đầy đủ" },
    {
      id: 1,
      icon: "🪖",
      text: "Đội mũ bảo hiểm nếu đi xe đạp / xe máy cùng người lớn",
    },
    { id: 2, icon: "🚶", text: "Đi bộ trên vỉa hè, không đi dưới lòng đường" },
    { id: 3, icon: "🚏", text: "Đến gần vạch qua đường, dừng lại quan sát" },
    { id: 4, icon: "👀", text: "Nhìn trái, nhìn phải, rồi nhìn trái lần nữa" },
    { id: 5, icon: "🚦", text: "Chờ đèn tín hiệu chuyển xanh cho người đi bộ" },
    {
      id: 6,
      icon: "🦓",
      text: "Qua đường trong vạch kẻ, đi nhanh nhưng không chạy",
    },
    { id: 7, icon: "🚸", text: "Đi tiếp trên vỉa hè tới khu vực gần trường" },
    { id: 8, icon: "🏫", text: "Vào cổng trường an toàn, chào thầy cô bảo vệ" },
  ];
  const puzzle = {
    title: "🎒 Hành trình đến trường an toàn",
    desc: "Kéo từng bước vào đúng vị trí để sắp xếp lại thứ tự đến trường an toàn mỗi ngày.",
    level: 1,
    correctCount: 0,
    placed: {},
    steps: [],
    init(root) {
      this.level = 1;
      this.renderLevel(root);
    },
    renderLevel(root) {
      setLevelLabel(this.level);
      this.correctCount = 0;
      this.placed = {};
      this.steps = COMMUTE_STEPS.slice(0, 4 + this.level); // 5,6,7,8,9 bước — đã đúng thứ tự chuẩn
      root.innerHTML = `<div class="drag-area"><div class="sign-pool" id="stepsPool"></div><div class="drop-list" id="stepsList"></div></div>`;
      document.getElementById("gpScore").innerHTML =
        `✅ <span id="stepsCorrect">0</span> / ${this.steps.length} đúng thứ tự`;

      const pool = root.querySelector("#stepsPool");
      const list = root.querySelector("#stepsList");

      shuffle(this.steps).forEach((s) => {
        const chip = document.createElement("div");
        chip.className = "sign-chip";
        chip.draggable = true;
        chip.id = "step-" + s.id;
        chip.dataset.id = s.id;
        chip.innerHTML = `${s.icon}<small>${s.text}</small>`;
        chip.addEventListener("dragstart", (e) => {
          e.dataTransfer.setData("text/plain", s.id);
          chip.classList.add("dragging");
        });
        chip.addEventListener("dragend", () =>
          chip.classList.remove("dragging"),
        );
        pool.appendChild(chip);
      });

      this.steps.forEach((s, idx) => {
        const slot = document.createElement("div");
        slot.className = "drop-slot";
        slot.dataset.idx = idx;
        slot.innerHTML = `<span class="slot-filled" id="stepfilled-${idx}">${idx + 1}️⃣</span><span class="slot-label" id="steplabel-${idx}">Bước ${idx + 1} của hành trình</span>`;
        slot.addEventListener("dragover", (e) => {
          e.preventDefault();
          slot.classList.add("over");
        });
        slot.addEventListener("dragleave", () => slot.classList.remove("over"));
        slot.addEventListener("drop", (e) => {
          e.preventDefault();
          slot.classList.remove("over");
          this.handleDrop(
            parseInt(e.dataTransfer.getData("text/plain"), 10),
            idx,
            slot,
            root,
          );
        });
        list.appendChild(slot);
      });
    },
    handleDrop(stepId, slotIdx, slotEl, root) {
      if (this.placed[slotIdx]) return;
      const correctStepId = this.steps[slotIdx].id;
      if (stepId === correctStepId) {
        const stepObj = COMMUTE_STEPS.find((s) => s.id === stepId);
        slotEl.classList.add("correct");
        document.getElementById("stepfilled-" + slotIdx).textContent =
          stepObj.icon;
        document.getElementById("steplabel-" + slotIdx).textContent =
          stepObj.text;
        const chip = document.getElementById("step-" + stepId);
        if (chip) chip.remove();
        this.placed[slotIdx] = true;
        this.correctCount++;
        fx("correct");
        document.getElementById("stepsCorrect").textContent = this.correctCount;
        if (this.correctCount === this.steps.length) {
          setTimeout(() => advanceOrFinish(this, root, 20, 30), 400);
        }
      } else {
        fx("wrong");
        slotEl.classList.add("wrong");
        setTimeout(() => slotEl.classList.remove("wrong"), 500);
        showToast("❌ Chưa đúng thứ tự, thử lại nhé");
      }
    },
    destroy() {},
  };

  /* =====================================================================
     GAME 3 — Tìm lỗi sai trong tranh (5 cấp: 8→16 vật thể, có giới hạn thời gian)
     ===================================================================== */
  const SCENE_POOL = [
    { icon: "🧑\u200d🦯", label: "Chú qua đường trong vạch kẻ", bad: false },
    {
      icon: "📱",
      label: "Bạn nhỏ vừa đi vừa dùng điện thoại khi qua đường",
      bad: true,
    },
    { icon: "🪖", label: "Bạn đội mũ bảo hiểm khi đi xe đạp", bad: false },
    { icon: "🚸", label: "Biển báo khu vực trường học", bad: false },
    { icon: "🏃", label: "Bạn chạy băng qua đường lúc đèn đỏ", bad: true },
    { icon: "👦", label: "Hai bạn ngồi 1 xe đạp không đội mũ", bad: true },
    { icon: "🟢", label: "Đèn tín hiệu đang xanh cho người đi bộ", bad: false },
    {
      icon: "🛴",
      label: "Bạn đi xe trượt scooter giữa lòng đường xe chạy",
      bad: true,
    },
    {
      icon: "🎧",
      label: "Bạn đeo tai nghe to không nghe thấy còi xe",
      bad: true,
    },
    {
      icon: "🦺",
      label: "Chú công nhân mặc áo phản quang khi làm việc",
      bad: false,
    },
    {
      icon: "🚴",
      label: "Bạn đạp xe ngược chiều trên đường một chiều",
      bad: true,
    },
    { icon: "🧍", label: "Bạn đứng chờ đúng vạch dừng xe buýt", bad: false },
    { icon: "⚽", label: "Bạn đá bóng ngay giữa lòng đường", bad: true },
    { icon: "🚦", label: "Đèn tín hiệu hoạt động bình thường", bad: false },
    { icon: "🤸", label: "Bạn nhỏ leo trèo qua dải phân cách", bad: true },
    { icon: "👶", label: "Em bé được dắt tay qua đường an toàn", bad: false },
  ];
  const spotError = {
    title: "🔍 Tìm lỗi sai trong tranh",
    desc: "Quan sát và bấm vào NHỮNG HÀNH VI KHÔNG AN TOÀN trong bức tranh.",
    level: 1,
    itemCounts: [8, 10, 12, 14, 16],
    timeLimits: [0, 0, 40, 35, 30],
    scene: [],
    found: 0,
    wrongClicks: 0,
    totalBad: 0,
    timer: null,
    timeLeft: 0,
    _badIndexMap: [],
    init(root) {
      this.level = 1;
      this.renderLevel(root);
    },
    renderLevel(root) {
      clearInterval(this.timer);
      setLevelLabel(this.level);
      this.found = 0;
      this.wrongClicks = 0;
      const count = this.itemCounts[this.level - 1];
      this.scene = shuffle(SCENE_POOL)
        .slice(0, count)
        .map((s) => ({
          ...s,
          top: 10 + Math.random() * 78 + "%",
          left: 8 + Math.random() * 80 + "%",
        }));
      this.totalBad = this.scene.filter((s) => s.bad).length;
      const limit = this.timeLimits[this.level - 1];
      this.timeLeft = limit;

      root.innerHTML = `
        <div class="board" style="height:320px; max-width:680px;" id="spotBoard"></div>
        ${limit ? `<p style="margin-top:10px; font-size:12.5px; color:var(--yellow);">⏱️ Còn lại: <span id="spotTimer">${limit}</span>s</p>` : ""}
        <ul class="scenario-list" id="spotChecklist" style="margin-top:10px;"></ul>
      `;
      document.getElementById("gpScore").innerHTML =
        `🔍 <span id="spotFound">0</span> / ${this.totalBad} lỗi tìm thấy`;

      const board = root.querySelector("#spotBoard");
      const checklist = root.querySelector("#spotChecklist");

      this.scene.forEach((item, i) => {
        const el = document.createElement("div");
        el.className = "hist-thumb";
        el.style.cssText = `position:absolute; top:${item.top}; left:${item.left}; width:42px; height:42px; font-size:20px; cursor:pointer;`;
        el.textContent = item.icon;
        el.title = "Bấm nếu đây là hành vi KHÔNG an toàn";
        el.addEventListener("click", () => this.handleClick(i, el, root));
        board.appendChild(el);
      });

      this._badIndexMap = this.scene
        .map((s, i) => (s.bad ? i : null))
        .filter((i) => i !== null);
      this._badIndexMap.forEach((sceneIdx, checkIdx) => {
        const li = document.createElement("li");
        li.id = "spot-check-" + checkIdx;
        li.textContent = "⬜ " + this.scene[sceneIdx].label;
        checklist.appendChild(li);
      });

      if (limit) {
        this.timer = setInterval(() => {
          this.timeLeft--;
          const t = document.getElementById("spotTimer");
          if (t) t.textContent = this.timeLeft;
          if (this.timeLeft <= 0) {
            clearInterval(this.timer);
            fx("wrong");
            showToast("⏰ Hết giờ! Thử lại cấp này nhé.");
            this.renderLevel(root);
          }
        }, 1000);
      }
    },
    handleClick(i, el, root) {
      if (el.dataset.done) return;
      const item = this.scene[i];
      if (item.bad) {
        el.dataset.done = "1";
        el.style.outline = "2px solid var(--green)";
        this.found++;
        fx("correct");
        document.getElementById("spotFound").textContent = this.found;
        const checkIdx = this._badIndexMap.indexOf(i);
        const li = document.getElementById("spot-check-" + checkIdx);
        if (li) li.textContent = "✅ " + item.label;
        showToast("✅ Đúng! Đây là hành vi không an toàn");
        if (this.found === this.totalBad) {
          clearInterval(this.timer);
          setTimeout(() => advanceOrFinish(this, root, 25, 35), 400);
        }
      } else {
        fx("wrong");
        this.wrongClicks++;
        el.style.outline = "2px solid var(--red)";
        setTimeout(() => {
          el.style.outline = "";
        }, 500);
        showToast("❌ Đây là hành vi ĐÚNG, không phải lỗi đâu!");
      }
    },
    destroy() {
      clearInterval(this.timer);
    },
  };

  /* =====================================================================
     GAME 4 — Băng qua ngã tư an toàn (5 cấp: nhiều ngã tư hơn, giờ gấp hơn)
     ===================================================================== */
  const maze = {
    title: "🚦 Băng qua ngã tư an toàn",
    desc: "Trên đường tới trường, con sẽ gặp nhiều ngã tư. Quan sát đèn tín hiệu và xe cộ, chọn đúng hành động để qua đường an toàn.",
    level: 1,
    counts: [3, 4, 5, 6, 7],
    timeLimits: [0, 0, 6, 5, 4],
    idx: 0,
    total: 0,
    currentState: "red",
    timer: null,
    timeLeft: 0,
    init(root) {
      this.level = 1;
      this.renderLevel(root);
    },
    renderLevel(root) {
      setLevelLabel(this.level);
      this.idx = 0;
      this.total = this.counts[this.level - 1];
      document.getElementById("gpScore").innerHTML =
        `🚦 Ngã tư <span id="crossIdx">1</span>/${this.total}`;
      this.renderIntersection(root);
    },
    renderIntersection(root) {
      clearInterval(this.timer);
      const states = ["red", "yellow", "green"];
      this.currentState = states[Math.floor(Math.random() * 3)];
      const limit = this.timeLimits[this.level - 1];
      this.timeLeft = limit;

      root.innerHTML = `
        <div class="board" style="height:280px; max-width:560px; margin:0 auto;">
          <div class="road-h"></div><div class="road-v"></div>
          <div class="lane-mark-h"></div><div class="lane-mark-v"></div>
          <div class="crosswalk top"></div><div class="crosswalk bottom"></div>
          <div class="crosswalk left"></div><div class="crosswalk right"></div>
          <div class="signal">
            <i class="red ${this.currentState === "red" ? "on" : ""}"></i>
            <i class="yellow ${this.currentState === "yellow" ? "on" : ""}"></i>
            <i class="green ${this.currentState === "green" ? "on" : ""}"></i>
          </div>
          <div class="kid-avatar">🧒</div>
          <div class="veh veh-h" style="top:20%; animation-duration:4s;">🚌</div>
          <div class="veh veh-h" style="top:62%; animation-duration:5.5s; animation-delay:1.2s;">🚗</div>
          <div class="ai-callout">🚸 Ngã tư gần trường — hãy quan sát kỹ trước khi qua đường!</div>
        </div>
        ${limit ? `<p style="text-align:center; font-size:12px; color:var(--yellow); margin-top:10px;">⏱️ Còn lại: <span id="crossTimer">${limit}</span>s</p>` : ""}
        <div class="action-row" style="max-width:560px; margin:16px auto 0;">
          <button class="action-btn" data-a="dung"><span class="a-ic">✋</span>Dừng lại</button>
          <button class="action-btn" data-a="quansat"><span class="a-ic">👀</span>Quan sát</button>
          <button class="action-btn" data-a="qua"><span class="a-ic">🦓</span>Qua đường</button>
        </div>
      `;
      document.getElementById("crossIdx").textContent = this.idx + 1;
      root.querySelectorAll("[data-a]").forEach((btn) => {
        btn.addEventListener("click", () => this.answer(btn.dataset.a, root));
      });

      if (limit) {
        this.timer = setInterval(() => {
          this.timeLeft--;
          const t = document.getElementById("crossTimer");
          if (t) t.textContent = this.timeLeft;
          if (this.timeLeft <= 0) {
            clearInterval(this.timer);
            this.answer("__timeout__", root);
          }
        }, 1000);
      }
    },
    answer(action, root) {
      clearInterval(this.timer);
      const correctMap = { red: "dung", yellow: "quansat", green: "qua" };
      const correct = correctMap[this.currentState];
      if (action === correct) {
        fx("correct");
        showToast("✅ Qua ngã tư an toàn!");
        this.idx++;
        if (this.idx < this.total) {
          setTimeout(() => this.renderIntersection(root), 700);
        } else {
          setTimeout(() => advanceOrFinish(this, root, 35, 45), 500);
        }
      } else {
        fx("wrong");
        showToast(
          action === "__timeout__"
            ? "⏰ Hết giờ! Thử lại ngã tư này."
            : "❌ Chưa an toàn — quan sát kỹ đèn tín hiệu nhé!",
        );
        setTimeout(() => this.renderIntersection(root), 800);
      }
    },
    destroy() {
      clearInterval(this.timer);
    },
  };

  /* =====================================================================
     GAME 5 — Đua xe đạp an toàn (5 cấp: nhiều chặng hơn, ít thời gian hơn)
     ===================================================================== */
  const RACE_POOL = [
    {
      scene: "🚦 Đèn đỏ phía trước!",
      options: [
        { t: "Phanh dừng lại", ok: true },
        { t: "Tăng tốc vượt qua", ok: false },
        { t: "Rẽ đại sang lề", ok: false },
      ],
    },
    {
      scene: "🚸 Sắp tới khu vực trường học",
      options: [
        { t: "Giảm tốc độ, quan sát", ok: true },
        { t: "Giữ nguyên tốc độ", ok: false },
        { t: "Tăng tốc cho nhanh", ok: false },
      ],
    },
    {
      scene: "🚶 Có người đi bộ qua đường",
      options: [
        { t: "Nhường đường, dừng lại", ok: true },
        { t: "Bấm chuông bảo họ tránh", ok: false },
        { t: "Lách qua thật nhanh", ok: false },
      ],
    },
    {
      scene: "🌧️ Trời bắt đầu mưa",
      options: [
        { t: "Giảm tốc, đi sát lề an toàn", ok: true },
        { t: "Đạp nhanh hơn để về sớm", ok: false },
        { t: "Buông 1 tay che đầu", ok: false },
      ],
    },
    {
      scene: "🚑 Xe cứu thương phía sau",
      options: [
        { t: "Nhường đường sát lề phải", ok: true },
        { t: "Đạp nhanh vượt lên trước", ok: false },
        { t: "Đứng im giữa đường", ok: false },
      ],
    },
    {
      scene: "🦓 Tới vạch qua đường",
      options: [
        { t: "Xuống dắt xe qua vạch", ok: true },
        { t: "Đạp thẳng qua thật nhanh", ok: false },
        { t: "Không quan sát, đi luôn", ok: false },
      ],
    },
    {
      scene: "🌙 Trời tối, đèn đường mờ",
      options: [
        { t: "Bật đèn xe, đi chậm lại", ok: true },
        { t: "Đi như bình thường", ok: false },
        { t: "Tăng tốc để về nhanh", ok: false },
      ],
    },
    {
      scene: "🚧 Đoạn đường đang sửa chữa",
      options: [
        { t: "Đi chậm, quan sát biển báo", ok: true },
        { t: "Vượt qua rào chắn", ok: false },
        { t: "Bỏ qua biển cảnh báo", ok: false },
      ],
    },
    {
      scene: "🐕 Có con vật chạy ra đường",
      options: [
        { t: "Giảm tốc, quan sát kỹ", ok: true },
        { t: "Bấm còi thật to xua đuổi", ok: false },
        { t: "Đạp nhanh né qua", ok: false },
      ],
    },
    {
      scene: "👥 Đi cùng nhóm bạn đông người",
      options: [
        { t: "Đi hàng một, đúng làn đường", ok: true },
        { t: "Dàn hàng ngang nói chuyện", ok: false },
        { t: "Đua tốc độ với bạn", ok: false },
      ],
    },
  ];
  const bikeRace = {
    title: "🏁 Đua xe đạp an toàn",
    desc: "Mỗi tình huống có thời gian giới hạn để chọn hành động AN TOÀN nhất.",
    level: 1,
    roundCounts: [6, 6, 7, 7, 8],
    timeLimits: [3.0, 2.5, 2.2, 2.0, 1.8],
    rounds: [],
    roundIdx: 0,
    progress: 0,
    timer: null,
    timeLeft: 3,
    init(root) {
      this.level = 1;
      this.renderLevel(root);
    },
    renderLevel(root) {
      this.rounds = shuffle(RACE_POOL).slice(
        0,
        this.roundCounts[this.level - 1],
      );
      this.roundIdx = 0;
      this.progress = 0;
      setLevelLabel(this.level);
      document.getElementById("gpScore").innerHTML =
        `🏁 Chặng <span id="raceRound">1</span>/${this.rounds.length} · Tiến độ <span id="raceProgress">0</span>%`;
      this.renderRound(root);
    },
    renderRound(root) {
      clearInterval(this.timer);
      const round = this.rounds[this.roundIdx];
      const limit = this.timeLimits[this.level - 1];
      this.timeLeft = limit;
      root.innerHTML = `
        <div style="text-align:center;">
          <div style="font-size:40px; margin-bottom:10px;">${round.scene.split(" ")[0]}</div>
          <p style="font-size:15px; font-weight:600; margin-bottom:6px;">${round.scene}</p>
          <div class="mini-bar full" style="max-width:300px; margin:0 auto 20px;"><i id="raceTimerBar" style="width:100%; background:var(--green); transition:width 1s linear;"></i></div>
          <div class="choice-grid" id="raceChoices" style="max-width:420px; margin:0 auto;"></div>
        </div>
      `;
      const choicesEl = root.querySelector("#raceChoices");
      shuffle(round.options).forEach((opt) => {
        const btn = document.createElement("button");
        btn.className = "choice-btn";
        btn.innerHTML = `<span class="c-ic">${opt.ok ? "🚲" : "⚠️"}</span> ${opt.t}`;
        btn.onclick = () => this.answer(opt.ok, root);
        choicesEl.appendChild(btn);
      });
      document.getElementById("raceRound").textContent = this.roundIdx + 1;

      this.timer = setInterval(() => {
        this.timeLeft -= 0.1;
        const bar = document.getElementById("raceTimerBar");
        if (bar) {
          const pct = Math.max(0, (this.timeLeft / limit) * 100);
          bar.style.width = pct + "%";
          bar.style.background =
            pct < 30
              ? "var(--red)"
              : pct < 60
                ? "var(--yellow)"
                : "var(--green)";
        }
        if (this.timeLeft <= 0) {
          clearInterval(this.timer);
          this.answer(false, root);
        }
      }, 100);
    },
    answer(ok, root) {
      clearInterval(this.timer);
      if (ok) {
        fx("correct");
        this.progress = Math.min(
          100,
          this.progress + Math.round(100 / this.rounds.length),
        );
        showToast("✅ An toàn! Tiếp tục nào 🚲");
      } else {
        fx("wrong");
        showToast("⚠️ Chưa an toàn — cẩn thận hơn nhé!");
      }
      document.getElementById("raceProgress").textContent = this.progress;

      this.roundIdx++;
      if (this.roundIdx < this.rounds.length) {
        setTimeout(() => this.renderRound(root), 700);
      } else {
        setTimeout(() => {
          if (this.progress >= 70) {
            advanceOrFinish(
              this,
              root,
              40,
              60,
              this.level === 5 ? "dua_xe_hoan_hao" : null,
              "Đua xe hoàn hảo",
            );
          } else {
            fx("wrong");
            showToast(
              `⚠️ Chỉ đạt ${this.progress}% an toàn — cần ít nhất 70% để qua cấp. Thử lại nhé!`,
            );
            setTimeout(() => this.renderLevel(root), 1400);
          }
        }, 700);
      }
    },
    destroy() {
      clearInterval(this.timer);
    },
  };

  /* =====================================================================
     GAME 6 — Đố vui an toàn giao thông (5 cấp: nhiều câu hơn, giờ gấp hơn)
     ===================================================================== */
  const QUIZ_POOL = [
    {
      q: "Khi đèn tín hiệu dành cho người đi bộ đang đỏ, con nên làm gì?",
      options: [
        "Dừng lại chờ đèn xanh",
        "Chạy nhanh qua trước khi xe tới",
        "Nhìn điện thoại chờ bạn",
      ],
      correct: 0,
    },
    {
      q: "Trước khi qua đường ở nơi không có đèn tín hiệu, con cần làm gì đầu tiên?",
      options: [
        "Nhìn trái, nhìn phải rồi nhìn trái lần nữa",
        "Chạy thật nhanh qua đường",
        "Đi cùng nhóm bạn ồn ào",
      ],
      correct: 0,
    },
    {
      q: "Khi đi xe đạp tới trường, con luôn cần đội gì?",
      options: [
        "Mũ bảo hiểm đạt chuẩn",
        "Mũ vải thời trang",
        "Không cần đội gì cả",
      ],
      correct: 0,
    },
    {
      q: "Gặp xe cứu thương đang hú còi phía sau, con nên làm gì?",
      options: [
        "Nhường đường, đi sát vào lề",
        "Đạp nhanh vượt lên trước",
        "Dừng lại giữa đường",
      ],
      correct: 0,
    },
    {
      q: "Biển báo hình tam giác có hình hai bạn nhỏ đang đi bộ nghĩa là gì?",
      options: [
        "Khu vực gần trường học, cần giảm tốc độ",
        "Cấm học sinh qua đường",
        "Đường dành riêng cho xe đạp",
      ],
      correct: 0,
    },
    {
      q: "Khi trời mưa, đi bộ tới trường con nên đi ở đâu là an toàn nhất?",
      options: [
        "Đi trên vỉa hè, tránh xa mép đường",
        "Đi sát mép đường cho nhanh",
        "Đi giữa lòng đường",
      ],
      correct: 0,
    },
    {
      q: "Đèn giao thông chuyển sang màu vàng nghĩa là gì?",
      options: [
        "Chuẩn bị dừng lại, không tăng tốc",
        "Được phép đi thật nhanh",
        "Tín hiệu bị hỏng, bỏ qua",
      ],
      correct: 0,
    },
    {
      q: "Khi qua đường, vị trí an toàn nhất để đi là ở đâu?",
      options: [
        "Trong vạch kẻ dành cho người đi bộ",
        "Bất kỳ chỗ nào thấy trống",
        "Ngay sau đuôi một chiếc xe buýt",
      ],
      correct: 0,
    },
    {
      q: "Bạn rủ con băng qua đường khi đèn còn đỏ để không bị trễ học. Con nên làm gì?",
      options: [
        "Từ chối, đợi đèn xanh mới qua",
        "Đồng ý vì sợ trễ học",
        "Chạy thật nhanh theo bạn",
      ],
      correct: 0,
    },
    {
      q: "Khi ngồi sau xe máy/xe đạp của bố mẹ, con cần làm gì?",
      options: [
        "Đội mũ bảo hiểm, ngồi ngay ngắn và bám chắc",
        "Đứng lên xe cho thoáng",
        "Vừa đi vừa nghịch điện thoại",
      ],
      correct: 0,
    },
    {
      q: "Khi đi bộ trên vỉa hè có nhiều xe máy đỗ, con nên làm gì?",
      options: [
        "Đi vòng cẩn thận, quan sát xe đang lùi/ra vào",
        "Chạy nhanh xuyên qua giữa các xe",
        "Không cần để ý gì cả",
      ],
      correct: 0,
    },
    {
      q: "Tan học, con thấy đường trước cổng trường rất đông. Con nên làm gì?",
      options: [
        "Đợi chú bảo vệ/CSGT ra hiệu rồi mới qua",
        "Chen lấn qua thật nhanh cho kịp xe đón",
        "Chạy băng qua giữa dòng xe",
      ],
      correct: 0,
    },
  ];
  const trafficQuiz = {
    title: "🧠 Đố vui an toàn giao thông",
    desc: "Trả lời nhanh các tình huống thực tế mà con có thể gặp trên đường đi học mỗi ngày.",
    level: 1,
    roundCounts: [4, 5, 6, 7, 8],
    timeLimits: [0, 0, 8, 6, 5],
    questions: [],
    idx: 0,
    correctCount: 0,
    timer: null,
    timeLeft: 0,
    init(root) {
      this.level = 1;
      this.renderLevel(root);
    },
    renderLevel(root) {
      setLevelLabel(this.level);
      this.questions = shuffle(QUIZ_POOL).slice(
        0,
        this.roundCounts[this.level - 1],
      );
      this.idx = 0;
      this.correctCount = 0;
      document.getElementById("gpScore").innerHTML =
        `🧠 Câu <span id="quizIdx">1</span>/${this.questions.length} · Đúng <span id="quizCorrect">0</span>`;
      this.renderQuestion(root);
    },
    renderQuestion(root) {
      clearInterval(this.timer);
      const q = this.questions[this.idx];
      const limit = this.timeLimits[this.level - 1];
      this.timeLeft = limit;
      const shuffledOptions = shuffle(
        q.options.map((t, i) => ({ t, ok: i === q.correct })),
      );
      root.innerHTML = `
        <div style="text-align:center; max-width:600px; margin:0 auto;">
          <p style="font-size:17px; font-weight:700; margin-bottom:20px; line-height:1.5;">${q.q}</p>
          ${limit ? `<div class="mini-bar full" style="max-width:280px; margin:0 auto 18px;"><i id="quizTimerBar" style="width:100%; background:var(--green); transition:width 1s linear;"></i></div>` : ""}
          <div class="choice-grid" id="quizChoices"></div>
        </div>
      `;
      document.getElementById("quizIdx").textContent = this.idx + 1;
      const wrap = root.querySelector("#quizChoices");
      shuffledOptions.forEach((opt) => {
        const btn = document.createElement("button");
        btn.className = "choice-btn";
        btn.innerHTML = `<span class="c-ic">🚸</span> ${opt.t}`;
        btn.onclick = () => this.answer(opt.ok, root);
        wrap.appendChild(btn);
      });

      if (limit) {
        this.timer = setInterval(() => {
          this.timeLeft -= 0.1;
          const bar = document.getElementById("quizTimerBar");
          if (bar) {
            const pct = Math.max(0, (this.timeLeft / limit) * 100);
            bar.style.width = pct + "%";
            bar.style.background =
              pct < 30
                ? "var(--red)"
                : pct < 60
                  ? "var(--yellow)"
                  : "var(--green)";
          }
          if (this.timeLeft <= 0) {
            clearInterval(this.timer);
            this.answer(false, root);
          }
        }, 100);
      }
    },
    answer(ok, root) {
      clearInterval(this.timer);
      if (ok) {
        this.correctCount++;
        fx("correct");
        showToast("✅ Chính xác!");
      } else {
        fx("wrong");
        showToast("❌ Chưa đúng, ghi nhớ nhé!");
      }
      document.getElementById("quizCorrect").textContent = this.correctCount;

      this.idx++;
      if (this.idx < this.questions.length) {
        setTimeout(() => this.renderQuestion(root), 700);
      } else {
        setTimeout(() => {
          const pct = Math.round(
            (this.correctCount / this.questions.length) * 100,
          );
          if (pct >= 60) {
            advanceOrFinish(this, root, 20, 25);
          } else {
            fx("wrong");
            showToast(
              `⚠️ Chỉ đúng ${pct}% — cần ít nhất 60% để qua cấp. Thử lại nhé!`,
            );
            setTimeout(() => this.renderLevel(root), 1500);
          }
        }, 700);
      }
    },
    destroy() {
      clearInterval(this.timer);
    },
  };

  /* =====================================================================
     ĐIỀU KHIỂN CHUNG
     ===================================================================== */
  const GAMES = {
    signMatch,
    puzzle,
    spotError,
    maze,
    bikeRace,
    lightOrder: trafficQuiz,
  };

  window.openGame = function (gameId, e) {
    if (e) e.stopPropagation();
    if (currentCleanup) {
      try {
        currentCleanup();
      } catch (err) {}
    }

    currentGameId = gameId;
    const game = GAMES[gameId];
    const panel = document.getElementById("gamePanel");
    const titleEl = document.getElementById("gpTitle");
    if (titleEl && !titleEl.querySelector("#gpLevelLabel")) {
      titleEl.innerHTML = `${game.title} — <span id="gpLevelLabel">Cấp 1/5</span>`;
    }
    document.getElementById("gpDesc").textContent = game.desc;

    const body = document.getElementById("gpBody");
    game.init(body);
    currentCleanup = () => game.destroy();

    if (panel) {
      panel.classList.add("active");
      panel.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  };

  window.closeGame = function () {
    if (currentCleanup) {
      try {
        currentCleanup();
      } catch (err) {}
    }
    currentCleanup = null;
    currentGameId = null;
    const panel = document.getElementById("gamePanel");
    if (panel) panel.classList.remove("active");
  };

  window.resetGame = function () {
    if (!currentGameId) return;
    const game = GAMES[currentGameId];
    if (currentCleanup) {
      try {
        currentCleanup();
      } catch (err) {}
    }
    game.init(document.getElementById("gpBody"));
    currentCleanup = () => game.destroy();
    showToast("🔁 Bắt đầu lại từ Cấp 1");
  };
});
