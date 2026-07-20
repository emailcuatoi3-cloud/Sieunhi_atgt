/* game-engine.js — Lõi dùng chung cho hệ thống 5 Mini Game mới
   ------------------------------------------------------------------
   Mỗi trang game (game-*.php) include file này + sound-fx.js + main.js,
   rồi gọi GameEngine.init(gameId) và dùng các hàm show* để điều khiển
   luồng: Giới thiệu → Hướng dẫn → Gameplay → Kết quả.
   ------------------------------------------------------------------ */

const GameEngine = (() => {
  let gameId = null;
  let stageEl = null;
  let nextGameInfo = null; // { url, label }

  function fx(name) {
    if (window.SoundFX && typeof SoundFX[name] === "function") SoundFX[name]();
  }

  function toast(msg) {
    let t = document.getElementById("geToast");
    if (!t) {
      t = document.createElement("div");
      t.id = "geToast";
      t.className = "toast";
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.add("show");
    clearTimeout(window._geToastTimer);
    window._geToastTimer = setTimeout(() => t.classList.remove("show"), 2400);
  }

  function init(id, nextGame) {
    gameId = id;
    stageEl = document.getElementById("stage");
    nextGameInfo = nextGame || null;
  }

  /* ---------- Màn hình Giới thiệu ---------- */
  function showIntro({ icon, title, desc, features = [], onStart }) {
    stageEl.innerHTML = `
      <div class="ge-intro">
        <div class="ge-intro-icon">${icon}</div>
        <h2 class="ge-intro-title">${title}</h2>
        <p class="ge-intro-desc">${desc}</p>
        ${features.length ? `<div class="ge-feature-row">${features.map((f) => `<div class="ge-feature-chip">${f}</div>`).join("")}</div>` : ""}
        <button class="btn cta-primary ge-big-btn" id="geStartBtn">🎮 Bắt đầu chơi</button>
      </div>
    `;
    document.getElementById("geStartBtn").onclick = () => {
      fx("click");
      onStart();
    };
  }

  /* ---------- Màn hình Hướng dẫn ---------- */
  function showInstructions({ title = "Cách chơi", steps = [], onReady }) {
    stageEl.innerHTML = `
      <div class="ge-instructions">
        <h3 class="ge-instr-title">📖 ${title}</h3>
        <ol class="ge-instr-list">
          ${steps.map((s) => `<li>${s}</li>`).join("")}
        </ol>
        <button class="btn cta-primary ge-big-btn" id="geReadyBtn">✅ Đã hiểu, chơi ngay!</button>
      </div>
    `;
    document.getElementById("geReadyBtn").onclick = () => {
      fx("click");
      onReady();
    };
  }

  /* ---------- Khung Gameplay (game tự đổ nội dung vào #geGameBody) ---------- */
  function showGameplayShell({ title, progressLabel = "" }) {
    stageEl.innerHTML = `
      <div class="ge-gameplay">
        <div class="ge-gp-top">
          <h3>${title}</h3>
          <span class="ge-progress-label" id="geProgressLabel">${progressLabel}</span>
        </div>
        <div id="geAiBubble" class="ge-ai-bubble" style="display:none;">
          <span class="ge-ai-avatar">🤖</span>
          <div class="ge-ai-text" id="geAiText"></div>
        </div>
        <div id="geGameBody"></div>
      </div>
    `;
    return document.getElementById("geGameBody");
  }

  function setProgress(label) {
    const el = document.getElementById("geProgressLabel");
    if (el) el.textContent = label;
  }

  /** Hiện bong bóng nhận xét của AI. mood: 'good' | 'bad' | 'info' */
  function aiSay(msg, mood = "info") {
    const bubble = document.getElementById("geAiBubble");
    const textEl = document.getElementById("geAiText");
    if (!bubble || !textEl) return;
    bubble.style.display = "flex";
    bubble.className = "ge-ai-bubble ge-ai-" + mood;
    textEl.textContent = msg;
    bubble.style.animation = "none";
    void bubble.offsetWidth;
    bubble.style.animation = "geFadeIn .35s ease both";
  }

  /* ---------- Màn hình Kết quả ---------- */
  function showResults({
    success,
    title,
    summary,
    xp,
    coin,
    badgeKey,
    badgeLabel,
    onReplay,
  }) {
    fx(success ? "success" : "wrong");
    stageEl.innerHTML = `
      <div class="ge-results ${success ? "ge-win" : "ge-lose"}">
        <div class="ge-result-icon">${success ? "🏆" : "💡"}</div>
        <h2>${title}</h2>
        <p class="ge-result-summary">${summary}</p>
        <div class="reward-row ge-reward-row">
          <div class="reward-chip">⭐ +${xp} XP</div>
          <div class="reward-chip">🪙 +${coin} Coin</div>
          ${badgeKey ? `<div class="reward-chip">🏅 ${badgeLabel}</div>` : ""}
        </div>
        <div class="ge-ai-bubble ge-ai-info" style="display:flex; margin:20px auto 0; max-width:520px;">
          <span class="ge-ai-avatar">🤖</span>
          <div class="ge-ai-text" id="geAdviceText">Đang phân tích kết quả...</div>
        </div>
        <div class="ge-result-actions">
          <button class="btn btn-ghost" id="geReplayBtn">🔁 Chơi lại</button>
          <a class="btn btn-ghost" href="game-mini.php">🏠 Về danh sách game</a>
          ${nextGameInfo ? `<a class="btn cta-primary" href="${nextGameInfo.url}">➡️ ${nextGameInfo.label}</a>` : ""}
        </div>
      </div>
    `;
    document.getElementById("geReplayBtn").onclick = () => {
      fx("click");
      onReplay();
    };

    if (xp > 0 || coin > 0) {
      submitReward(xp, coin, badgeKey, badgeLabel);
    }
  }

  function setAdvice(msg) {
    const el = document.getElementById("geAdviceText");
    if (el) el.textContent = msg;
  }

  /* ---------- Lưu thưởng thật vào DB (nếu là học sinh đã đăng nhập) ---------- */
  function submitReward(xp, coin, badgeKey, badgeLabel) {
    const xpEl = document.getElementById("xpVal");
    const coinEl = document.getElementById("coinVal");
    if (xpEl) xpEl.textContent = parseInt(xpEl.textContent) + xp;
    if (coinEl) coinEl.textContent = parseInt(coinEl.textContent) + coin;

    if (!window.IS_STUDENT) {
      toast(
        "💡 Đăng nhập bằng tài khoản Học sinh để lưu kết quả này thật nhé!",
      );
      return;
    }

    fetch("save-progress.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        game_id: gameId,
        xp,
        coin,
        badge_key: badgeKey || null,
        badge_label: badgeLabel || null,
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) {
          toast("⚠️ " + (data.error || "Không lưu được tiến trình"));
          return;
        }
        if (xpEl) xpEl.textContent = data.xp;
        if (coinEl) coinEl.textContent = data.coin;
        const badgeEl = document.getElementById("badgeVal");
        if (badgeEl) badgeEl.textContent = data.badgeCount;
        const levelEl = document.getElementById("levelVal");
        if (levelEl) levelEl.textContent = data.level;

        if (data.newBadge)
          setTimeout(
            () => toast("🏅 Huy hiệu mới: " + data.badgeLabel + "!"),
            800,
          );
        if (data.leveledUp) {
          setTimeout(
            () => {
              toast("🎉 Chúc mừng! Bạn vừa lên Cấp " + data.level + "!");
              fx("levelUp");
            },
            data.newBadge ? 1700 : 800,
          );
        }
      })
      .catch(() => toast("⚠️ Mất kết nối, chưa lưu được tiến trình."));
  }

  /* ---------- Nút "Hướng dẫn" xem theo yêu cầu (không bắt buộc) ---------- */
  function attachHelpButton(container, { title = "Cách chơi", steps = [] }) {
    let btn = document.getElementById("geHelpBtn");
    if (!btn) {
      btn = document.createElement("button");
      btn.id = "geHelpBtn";
      btn.className = "ge-help-fab";
      btn.innerHTML = "❓ Cách chơi";
      document.body.appendChild(btn);
    }
    btn.onclick = () => showHelpModal(title, steps);
  }

  function showHelpModal(title, steps) {
    let modal = document.getElementById("geHelpModal");
    if (modal) modal.remove();
    modal = document.createElement("div");
    modal.id = "geHelpModal";
    modal.className = "ge-modal-overlay";
    modal.innerHTML = `
      <div class="ge-modal-box">
        <div class="ge-modal-top">
          <h3>📖 ${title}</h3>
          <button class="ge-modal-close" id="geHelpClose">✕</button>
        </div>
        <ol class="ge-instr-list">${steps.map((s) => `<li>${s}</li>`).join("")}</ol>
        <button class="btn cta-primary" id="geHelpOkBtn" style="width:100%; justify-content:center;">Đã hiểu!</button>
      </div>
    `;
    document.body.appendChild(modal);
    const close = () => modal.remove();
    document.getElementById("geHelpClose").onclick = close;
    document.getElementById("geHelpOkBtn").onclick = close;
    modal.addEventListener("click", (e) => {
      if (e.target === modal) close();
    });
  }

  function removeHelpButton() {
    const btn = document.getElementById("geHelpBtn");
    if (btn) btn.remove();
  }

  return {
    init,
    showIntro,
    showInstructions,
    showGameplayShell,
    setProgress,
    aiSay,
    showResults,
    setAdvice,
    toast,
    fx,
    attachHelpButton,
    showHelpModal,
    removeHelpButton,
  };
})();

window.GameEngine = GameEngine;
