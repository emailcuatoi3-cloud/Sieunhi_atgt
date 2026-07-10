/* game-mini.js — Tương tác cho trang Game Mini */
document.addEventListener('DOMContentLoaded', () => {
  const signs = [
    { id: 'stop', icon: '🛑', name: 'STOP', meaning: 'Dừng lại hoàn toàn trước khi đi tiếp' },
    { id: 'school', icon: '🚸', name: 'Trường học', meaning: 'Khu vực gần trường học, giảm tốc độ' },
    { id: 'nocross', icon: '🚫', name: 'Cấm đi ngược chiều', meaning: 'Không được đi vào theo hướng này' },
    { id: 'bike', icon: '🚲', name: 'Đường dành cho xe đạp', meaning: 'Chỉ dành cho người đi xe đạp' },
    { id: 'hospital', icon: '🏥', name: 'Bệnh viện', meaning: 'Gần bệnh viện, giữ yên tĩnh và cẩn thận' }
  ];

  let correctCount = 0;
  let placed = {};

  function shuffle(arr) { return [...arr].sort(() => Math.random() - 0.5); }

  function buildGame() {
    correctCount = 0;
    placed = {};
    document.getElementById('correctCount').textContent = '0';
    document.getElementById('totalCount').textContent = signs.length;

    const pool = document.getElementById('signPool');
    const list = document.getElementById('dropList');
    pool.innerHTML = '';
    list.innerHTML = '';

    shuffle(signs).forEach(s => {
      const chip = document.createElement('div');
      chip.className = 'sign-chip';
      chip.draggable = true;
      chip.id = 'sign-' + s.id;
      chip.dataset.id = s.id;
      chip.innerHTML = `${s.icon}<small>${s.name}</small>`;
      chip.addEventListener('dragstart', e => {
        e.dataTransfer.setData('text/plain', s.id);
        chip.classList.add('dragging');
      });
      chip.addEventListener('dragend', () => chip.classList.remove('dragging'));
      pool.appendChild(chip);
    });

    shuffle(signs).forEach(s => {
      const slot = document.createElement('div');
      slot.className = 'drop-slot';
      slot.dataset.id = s.id;
      slot.innerHTML = `<span class="slot-filled" id="filled-${s.id}"></span><span class="slot-label">${s.meaning}</span>`;
      slot.addEventListener('dragover', e => { e.preventDefault(); slot.classList.add('over'); });
      slot.addEventListener('dragleave', () => slot.classList.remove('over'));
      slot.addEventListener('drop', e => {
        e.preventDefault();
        slot.classList.remove('over');
        handleDrop(e.dataTransfer.getData('text/plain'), s.id, slot);
      });
      list.appendChild(slot);
    });
  }

  function showToast(msg) {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(() => toast.classList.remove('show'), 1800);
  }

  function bumpRewards(xp, coin) {
    const xpEl = document.getElementById('xpVal');
    const coinEl = document.getElementById('coinVal');
    xpEl.textContent = parseInt(xpEl.textContent) + xp;
    coinEl.textContent = parseInt(coinEl.textContent) + coin;
  }

  function handleDrop(signId, slotId, slotEl) {
    if (placed[slotId]) return;
    const sign = signs.find(s => s.id === signId);
    if (signId === slotId) {
      slotEl.classList.add('correct');
      document.getElementById('filled-' + slotId).textContent = sign.icon;
      const chip = document.getElementById('sign-' + signId);
      if (chip) chip.remove();
      placed[slotId] = true;
      correctCount++;
      document.getElementById('correctCount').textContent = correctCount;
      showToast('✅ Chính xác! ' + sign.name);
      if (correctCount === signs.length) {
        setTimeout(() => {
          showToast('🎉 Hoàn thành! +30 XP · +50 Coin');
          bumpRewards(30, 50);
        }, 300);
      }
    } else {
      slotEl.classList.add('wrong');
      setTimeout(() => slotEl.classList.remove('wrong'), 500);
      showToast('❌ Chưa đúng, thử lại nhé');
    }
  }

  window.openGame = function (e) {
    if (e) e.stopPropagation();
    document.getElementById('gamePanel').classList.add('active');
    buildGame();
    document.getElementById('gamePanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
  window.closeGame = function () { document.getElementById('gamePanel').classList.remove('active'); };
  window.resetGame = function () { buildGame(); showToast('🔁 Bắt đầu lại trò chơi'); };
});
