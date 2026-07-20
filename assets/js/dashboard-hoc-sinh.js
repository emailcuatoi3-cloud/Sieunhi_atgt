/* dashboard-hoc-sinh.js — Tương tác cho Dashboard học sinh */
document.addEventListener("DOMContentLoaded", () => {
  const missions = [
    { text: "Hoàn thành 1 bài học AI Gia sư", reward: "+10 XP", done: true },
    {
      text: 'Chơi trò chơi "Người qua đường thông minh"',
      reward: "+35 XP · 50 Coin",
      done: false,
    },
    { text: "Hoàn thành 1 tình huống Mô phỏng", reward: "+15 XP", done: false },
    { text: "Đọc 1 chương truyện tương tác", reward: "+20 XP", done: false },
  ];

  function renderMissions() {
    const list = document.getElementById("missionList");
    list.innerHTML = "";
    missions.forEach((m) => {
      const item = document.createElement("div");
      item.className = "mission-item" + (m.done ? " done" : "");
      item.innerHTML = `<div class="m-check">${m.done ? "✓" : ""}</div><div class="m-text">${m.text}</div><div class="m-reward">${m.reward}</div>`;
      item.onclick = () => {
        m.done = !m.done;
        renderMissions();
        updateCount();
      };
      list.appendChild(item);
    });
  }
  function updateCount() {
    const done = missions.filter((m) => m.done).length;
    document.getElementById("missionCount").textContent =
      `${done}/${missions.length} hoàn thành`;
  }
  renderMissions();
  updateCount();
});
