/* ai-mo-phong.js — Tương tác cho trang AI Mô phỏng giao thông */
document.addEventListener('DOMContentLoaded', () => {
  const weatherOptions = [
    { key: 'sunny', label: '☀️ Trời nắng', boardClass: '' },
    { key: 'rain', label: '🌧️ Trời mưa', boardClass: 'rain' },
    { key: 'night', label: '🌙 Ban đêm', boardClass: 'night' },
    { key: 'fog', label: '🌫️ Sương mù', boardClass: 'fog' }
  ];
  const vehicleSets = [
    ['🚌', '🚗', '🚲'], ['🚑', '🚗', '🏍️'], ['🚗', '🚌', '🚌'], ['🚲', '🏍️', '🚗'], ['🚌', '🚑', '🚲']
  ];
  const signalStates = ['red', 'yellow', 'green'];
  const scenarioTexts = {
    red: "Đèn tín hiệu dành cho người đi bộ đang đỏ. Có xe cộ qua lại liên tục. Con đang đứng ở vỉa hè trước ngã tư.",
    yellow: "Đèn vừa chuyển sang vàng, các phương tiện đang chuẩn bị dừng. Con đang đứng gần vạch qua đường.",
    green: "Đèn tín hiệu dành cho người đi bộ đang xanh. Con đang chuẩn bị qua đường ở vạch kẻ."
  };
  const correctActions = { red: 'dung', yellow: 'quansat', green: 'qua' };
  const explain = {
    red: "Đèn đỏ nghĩa là chưa được qua đường. Con nên dừng lại ở vỉa hè và đợi đèn chuyển xanh.",
    yellow: "Đèn vàng là lúc cần quan sát kỹ hai bên trước khi quyết định — chưa nên bước xuống lòng đường vội.",
    green: "Đèn xanh cho người đi bộ nghĩa là an toàn để qua đường, nhưng vẫn nên đi trong vạch kẻ và quan sát thêm nhé."
  };

  let round = 1, score = 0, current = {};

  function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

  function buildVehicles(set) {
    const layer = document.getElementById('vehLayer');
    layer.innerHTML = '';
    const configs = [
      { top: '20%', dur: '4.5s', delay: '0s', axis: 'h' },
      { top: '62%', dur: '5.5s', delay: '1.2s', axis: 'h' },
      { left: '22%', dur: '5s', delay: '0.6s', axis: 'v' }
    ];
    set.forEach((v, i) => {
      const el = document.createElement('div');
      const cfg = configs[i];
      el.textContent = v;
      el.className = 'veh ' + (cfg.axis === 'h' ? 'veh-h' : 'veh-v');
      el.style.animationDuration = cfg.dur;
      el.style.animationDelay = cfg.delay;
      if (cfg.axis === 'h') el.style.top = cfg.top; else el.style.left = cfg.left;
      layer.appendChild(el);
    });
  }

  function setSignal(state) {
    document.getElementById('sigRed').classList.toggle('on', state === 'red');
    document.getElementById('sigYellow').classList.toggle('on', state === 'yellow');
    document.getElementById('sigGreen').classList.toggle('on', state === 'green');
  }

  function newScenario() {
    const weather = pick(weatherOptions);
    const signal = pick(signalStates);
    const vehicles = pick(vehicleSets);
    current = { weather, signal, vehicles, answered: false };

    document.getElementById('board').className = 'board ' + weather.boardClass;
    setSignal(signal);
    buildVehicles(vehicles);
    document.getElementById('roundNum').textContent = round;
    document.getElementById('scenarioDesc').textContent = scenarioTexts[signal];

    document.getElementById('scenarioList').innerHTML = `
      <li>🚦 Tín hiệu: <b style="color:#fff; margin-left:4px;">${signal === 'red' ? 'Đèn đỏ' : signal === 'yellow' ? 'Đèn vàng' : 'Đèn xanh'}</b></li>
      <li>${weather.label}</li>
      <li>🚗 Phương tiện: ${vehicles.join(' ')}</li>
    `;

    document.getElementById('aiCallout').textContent = '🤖 AI: Quan sát đèn tín hiệu và phương tiện trước khi quyết định nhé.';
    const box = document.getElementById('feedbackBox');
    box.className = 'feedback-box empty';
    box.innerHTML = 'Chọn một hành động để xem AI chấm điểm và giải thích.';
    document.getElementById('fbActions').style.display = 'none';
    document.querySelectorAll('.action-btn').forEach(b => b.disabled = false);
  }

  function addHistory(isRight, pts) {
    const row = document.getElementById('historyRow');
    if (row.children.length === 1 && row.children[0].textContent.includes('Chưa có')) row.innerHTML = '';
    const item = document.createElement('div');
    item.className = 'history-item';
    item.innerHTML = `<span class="h-dot ${isRight ? 'ok' : 'bad'}"></span><span class="h-txt">Tình huống #${round} — ${current.signal === 'red' ? 'Đèn đỏ' : current.signal === 'yellow' ? 'Đèn vàng' : 'Đèn xanh'}</span><span class="h-pt">+${pts}</span>`;
    row.prepend(item);
  }

  window.chooseAction = function (action) {
    if (current.answered) return;
    current.answered = true;
    document.querySelectorAll('.action-btn').forEach(b => b.disabled = true);

    const correct = correctActions[current.signal];
    const isRight = action === correct;
    const pts = isRight ? 10 : 0;
    score += pts;
    document.getElementById('scoreVal').textContent = score;

    const box = document.getElementById('feedbackBox');
    box.className = 'feedback-box';
    box.innerHTML = `
      <div class="fb-result ${isRight ? 'ok' : 'bad'}">${isRight ? '✅ Chính xác! +10 điểm' : '❌ Chưa đúng, thử lại lần sau nhé'}</div>
      <div class="fb-text">${explain[current.signal]}</div>
    `;
    document.getElementById('fbActions').style.display = 'flex';
    document.getElementById('aiCallout').textContent = isRight
      ? '🤖 AI: Tuyệt vời! Con đã xử lý tình huống rất an toàn.'
      : '🤖 AI: Không sao, mình cùng xem lại cách xử lý đúng nhé.';

    addHistory(isRight, pts);
  };

  window.replay = function () {
    document.querySelectorAll('.action-btn').forEach(b => b.disabled = false);
    current.answered = false;
    const box = document.getElementById('feedbackBox');
    box.className = 'feedback-box empty';
    box.innerHTML = 'Chọn một hành động để xem AI chấm điểm và giải thích.';
    document.getElementById('fbActions').style.display = 'none';
  };

  window.nextRound = function () {
    round += 1;
    newScenario();
  };

  const row = document.getElementById('condRow');
  if (row) {
    row.innerHTML = `
      <div class="cond-chip">🎲 Tình huống ngẫu nhiên mỗi lượt</div>
      <div class="cond-chip">🌦️ 4 kiểu thời tiết</div>
      <div class="cond-chip">🚗 Nhiều loại phương tiện</div>
      <div class="cond-chip">🤖 AI chấm điểm tức thì</div>
    `;
  }

  newScenario();
});
