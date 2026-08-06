/* =========================================================
   AI GIA SƯ — mascot, hình minh hoạ, chip cá nhân hoá, onboarding
   Yêu cầu: ai-chat.php (Task 6-7), preferences.php (Task 7),
   MascotSVG.pose() (Task 4), .kid-* (Task 3)
   ========================================================= */
const CSRF = document.querySelector('meta[name="csrf"]').content;
const state = { sessionId: 0, sending: false, gradeBand: 'tieu-hoc', guest: false };

/* Danh mục sticker chủ đề (7) + loại địa điểm (4) dùng cho onboarding — mã phải khớp
   pref_topic_codes()/pref_place_types() trong lib/personalize.php */
const ONBOARD_TOPICS = [
  { code: 'den-tin-hieu', label: '🚦 Đèn tín hiệu' },
  { code: 'bien-bao', label: '🚫 Biển báo' },
  { code: 'mu-bao-hiem', label: '⛑️ Mũ bảo hiểm' },
  { code: 'qua-duong', label: '🚸 Qua đường' },
  { code: 'xe-dap', label: '🚲 Xe đạp' },
  { code: 'ngoi-xe', label: '🛵 Ngồi xe' },
  { code: 'uu-tien', label: '🚑 Xe ưu tiên' },
];
const ONBOARD_PLACE_TYPES = [
  { code: 'bao-tang', label: '🏛️ Bảo tàng' },
  { code: 'cong-vien', label: '🌳 Công viên' },
  { code: 'vui-choi', label: '🎡 Khu vui chơi' },
  { code: 'thien-nhien', label: '🏞️ Thiên nhiên' },
];
const GREETING = 'Chào con! 👋 Mình là AI Gia sư. Bấm một câu gợi ý bên dưới hoặc tự hỏi mình nhé!';

async function api(url, opts = {}) {
  const res = await fetch(url, { ...opts, headers: { 'X-CSRF-Token': CSRF, ...(opts.headers || {}) } });
  return res.json();
}
function addMsg(role, text, artUrl = null) {
  const log = document.getElementById('chat-log');
  const el = document.createElement('div');
  el.className = 'msg ' + role;
  if (role === 'bot') {
    el.innerHTML = '<div class="msg-avatar"></div><div class="msg-body"></div>';
    el.querySelector('.msg-avatar').innerHTML = MascotSVG.pose('point');
    el.querySelector('.msg-body').textContent = text;           // textContent — chống XSS
    if (artUrl) {
      const art = document.createElement('div');
      art.className = 'msg-art';
      art.innerHTML = '<img alt="Hình minh hoạ" loading="lazy">';
      art.querySelector('img').src = artUrl;
      el.querySelector('.msg-body').appendChild(art);
    }
  } else { el.textContent = text; }
  log.appendChild(el); log.scrollTop = log.scrollHeight;
}

/* ---------- Badge trạng thái engine (AI thật / offline) ---------- */
function setEngineLabel(engine) {
  const badge = document.getElementById('engine-label');
  if (!badge) return;
  if (engine === 'gemini') {
    badge.textContent = '● AI thật';
    badge.className = 'kid-badge kid-badge--green';
  } else {
    badge.textContent = '● Chế độ offline';
    badge.className = 'kid-badge kid-badge--yellow';
  }
}

/* ---------- Gửi tin nhắn & nhận trả lời ---------- */
async function send(text) {
  if (state.sending) return;
  state.sending = true;
  try {
    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('session_id', state.sessionId);
    fd.append('message', text);
    // age_group = state.gradeBand === 'thcs' ? '9-11' : '6-8' — giọng điệu theo khối lớp vào system prompt
    fd.append('age_group', state.gradeBand === 'thcs' ? '9-11' : '6-8');

    const d = await api('ai-chat.php', { method: 'POST', body: fd });
    if (d.status === 'success') {
      state.sessionId = d.session_id;
      addMsg('bot', d.reply, d.art_url);
      setEngineLabel(d.engine);
      if (!state.guest) loadSessions();
    } else {
      addMsg('bot', d.message || 'Ôi, có gì đó chưa ổn, thử lại nhé! 🙈');
      const log = document.getElementById('chat-log');
      const av = log.lastElementChild && log.lastElementChild.querySelector('.msg-avatar');
      if (av) av.innerHTML = MascotSVG.pose('worry');
    }
  } catch (err) {
    // lỗi mạng → vẫn trả lời + đổi mặt mascot lo lắng
    addMsg('bot', 'Ôi, có gì đó chưa ổn, thử lại nhé! 🙈');
    const log = document.getElementById('chat-log');
    const av = log.lastElementChild && log.lastElementChild.querySelector('.msg-avatar');
    if (av) av.innerHTML = MascotSVG.pose('worry');
  }
  state.sending = false;
}

/* ---------- Chip gợi ý cá nhân hoá ---------- */
async function loadChips() {
  const row = document.getElementById('chip-row');

  // Chip câu hỏi phải hiện được dù việc phát hiện guest (để thêm chip đăng nhập) lỗi —
  // tách 2 lệnh gọi + 2 try/catch riêng, không để 1 lệnh fail kéo lệnh kia mất trắng.
  let chips = [];
  try {
    const chipsRes = await api('ai-chat.php?action=chips');
    chips = (chipsRes && chipsRes.status === 'success') ? chipsRes.chips : [];
  } catch (err) {
    /* bỏ qua lỗi mạng khi tải chip gợi ý — vẫn thử vẽ danh sách rỗng bên dưới */
  }

  row.innerHTML = '';
  chips.forEach((chip) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'kid-chip';
    btn.textContent = chip.text; // textContent — chống XSS
    btn.addEventListener('click', () => {
      if (state.sending) return;
      addMsg('user', chip.text);
      send(chip.text);
    });
    row.appendChild(btn);
  });

  try {
    const sessRes = await api('ai-chat.php?action=sessions');
    state.guest = !!(sessRes && sessRes.guest);
    if (state.guest) {
      // guest: thêm chip cuối mời đăng nhập (trang đăng nhập thật của dự án là dang-nhap.php)
      const link = document.createElement('a');
      link.className = 'kid-chip kid-chip--login';
      link.href = 'dang-nhap.php';
      link.textContent = '🔑 Đăng nhập để mình hiểu bạn hơn';
      row.appendChild(link);
    }
  } catch (err) {
    /* bỏ qua lỗi mạng khi phát hiện guest — chip câu hỏi ở trên vẫn đã hiện rồi */
  }
}

/* ---------- Lịch sử trò chuyện (sidebar) ---------- */
async function loadSessions() {
  const box = document.getElementById('chat-sessions');
  try {
    const d = await api('ai-chat.php?action=sessions');
    if (d.status !== 'success') return;
    state.guest = !!d.guest;

    box.innerHTML = '';
    const heading = document.createElement('p');
    heading.className = 'sidebar-heading';
    heading.textContent = 'Lịch sử trò chuyện';
    box.appendChild(heading);

    if (d.guest) {
      const p = document.createElement('p');
      p.className = 'sidebar-empty';
      p.textContent = 'Đăng nhập để lưu lại lịch sử trò chuyện của con nhé!';
      box.appendChild(p);
      return;
    }
    if (!d.sessions || d.sessions.length === 0) {
      const p = document.createElement('p');
      p.className = 'sidebar-empty';
      p.textContent = 'Chưa có cuộc trò chuyện nào. Hỏi mình điều gì đó nhé!';
      box.appendChild(p);
      return;
    }

    d.sessions.forEach((s) => {
      const item = document.createElement('div');
      item.className = 'session-item' + (String(s.id) === String(state.sessionId) ? ' active' : '');
      item.dataset.id = s.id;

      const title = document.createElement('span');
      title.className = 's-title';
      title.textContent = s.title; // textContent — chống XSS

      const del = document.createElement('button');
      del.type = 'button';
      del.className = 's-del';
      del.setAttribute('aria-label', 'Xoá cuộc trò chuyện');
      del.textContent = '✕';

      item.appendChild(title);
      item.appendChild(del);
      item.addEventListener('click', () => openSession(s.id));
      del.addEventListener('click', (ev) => {
        ev.stopPropagation();
        deleteSession(s.id);
      });
      box.appendChild(item);
    });
  } catch (err) {
    /* bỏ qua lỗi mạng khi tải sidebar */
  }
}

async function openSession(id) {
  try {
    const d = await api('ai-chat.php?action=messages&session_id=' + encodeURIComponent(id));
    if (d.status !== 'success') return;
    state.sessionId = id;
    document.querySelectorAll('#chat-sessions .session-item').forEach((el) => {
      el.classList.toggle('active', String(el.dataset.id) === String(id));
    });
    const log = document.getElementById('chat-log');
    log.innerHTML = '';
    d.messages.forEach((m) => addMsg(m.role === 'user' ? 'user' : 'bot', m.content));
    closeSidebarOnMobile();
  } catch (err) {
    /* bỏ qua lỗi mạng */
  }
}

async function deleteSession(id) {
  try {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('session_id', id);
    await api('ai-chat.php', { method: 'POST', body: fd });
  } catch (err) {
    /* bỏ qua lỗi mạng */
  }
  if (String(id) === String(state.sessionId)) {
    state.sessionId = 0;
    document.getElementById('chat-log').innerHTML = '';
    addMsg('bot', GREETING);
  }
  loadSessions();
}

/* ---------- Onboarding cá nhân hoá (chỉ hiện lần đầu, học sinh đã đăng nhập) ---------- */
async function onboarding() {
  let prefs;
  try {
    prefs = await api('preferences.php');
  } catch (err) {
    return; // guest / lỗi mạng — bỏ qua, không chặn phần còn lại của trang
  }
  if (!prefs || prefs.guest) return; // guest → không có onboarding
  if (prefs.prefs && prefs.prefs.grade_band) state.gradeBand = prefs.prefs.grade_band;
  if (prefs.has_prefs) return; // đã có sở thích rồi → không hiện lại modal

  const modal = document.getElementById('onboard-modal');
  let selectedBand = 'tieu-hoc';
  const selectedTopics = new Set();
  const selectedTypes = new Set();

  modal.innerHTML =
    '<div class="onboard-box kid-card">' +
      '<div class="onboard-step" id="onboard-step1">' +
        '<h2>Con học lớp mấy rồi? 🎓</h2>' +
        '<div class="onboard-grade-row">' +
          '<button type="button" class="kid-btn kid-btn--sky" data-band="tieu-hoc">🧒 Tiểu học</button>' +
          '<button type="button" class="kid-btn kid-btn--sky" data-band="thcs">🎒 THCS</button>' +
        '</div>' +
      '</div>' +
      '<div class="onboard-step" id="onboard-step2" hidden>' +
        '<h2>Con thích chủ đề nào? ⭐</h2>' +
        '<div class="onboard-sticker-row" id="onboard-topics"></div>' +
        '<h2>Con thích đi chơi ở đâu? 🗺️</h2>' +
        '<div class="onboard-sticker-row" id="onboard-types"></div>' +
        '<button type="button" class="kid-btn kid-btn--green" id="onboard-done">Xong 🎉</button>' +
      '</div>' +
    '</div>';

  const topicRow = modal.querySelector('#onboard-topics');
  ONBOARD_TOPICS.forEach((t) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'kid-chip';
    b.textContent = t.label;
    b.setAttribute('aria-pressed', 'false');
    b.addEventListener('click', () => {
      const active = b.classList.toggle('active');
      b.setAttribute('aria-pressed', String(active));
      if (active) selectedTopics.add(t.code); else selectedTopics.delete(t.code);
    });
    topicRow.appendChild(b);
  });

  const typeRow = modal.querySelector('#onboard-types');
  ONBOARD_PLACE_TYPES.forEach((t) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'kid-chip';
    b.textContent = t.label;
    b.setAttribute('aria-pressed', 'false');
    b.addEventListener('click', () => {
      const active = b.classList.toggle('active');
      b.setAttribute('aria-pressed', String(active));
      if (active) selectedTypes.add(t.code); else selectedTypes.delete(t.code);
    });
    typeRow.appendChild(b);
  });

  modal.querySelectorAll('#onboard-step1 [data-band]').forEach((btn) => {
    btn.addEventListener('click', () => {
      selectedBand = btn.dataset.band;
      modal.querySelector('#onboard-step1').hidden = true;
      modal.querySelector('#onboard-step2').hidden = false;
    });
  });

  modal.querySelector('#onboard-done').addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('grade_band', selectedBand);
    selectedTopics.forEach((t) => fd.append('fav_topics[]', t));
    selectedTypes.forEach((t) => fd.append('fav_place_types[]', t));
    try {
      await api('preferences.php', { method: 'POST', body: fd });
    } catch (err) {
      /* lỗi mạng — vẫn đóng modal để không chặn bé, thử lại lần sau */
    }
    state.gradeBand = selectedBand;
    modal.hidden = true;
    modal.innerHTML = '';
    const mini = document.getElementById('chat-mascot-mini');
    if (mini) mini.innerHTML = MascotSVG.pose('cheer');
    loadChips();
  });

  modal.hidden = false;
}

/* ---------- Sidebar di động (☰) ---------- */
function closeSidebarOnMobile() {
  const aside = document.getElementById('chat-sessions');
  const btn = document.getElementById('btn-toggle-sidebar');
  aside.classList.remove('open');
  if (btn) btn.setAttribute('aria-expanded', 'false');
}
document.getElementById('btn-toggle-sidebar').addEventListener('click', () => {
  const aside = document.getElementById('chat-sessions');
  const btn = document.getElementById('btn-toggle-sidebar');
  const isOpen = aside.classList.toggle('open');
  btn.setAttribute('aria-expanded', String(isOpen));
});
document.addEventListener('click', (ev) => {
  if (!ev.target.closest('#chat-sessions') && !ev.target.closest('#btn-toggle-sidebar')) {
    closeSidebarOnMobile();
  }
});

document.getElementById('btn-new-chat').addEventListener('click', () => {
  state.sessionId = 0;
  document.querySelectorAll('#chat-sessions .session-item').forEach((el) => el.classList.remove('active'));
  document.getElementById('chat-log').innerHTML = '';
  addMsg('bot', GREETING);
  closeSidebarOnMobile();
});

document.getElementById('chat-form').addEventListener('submit', e => {
  e.preventDefault();
  const input = document.getElementById('chat-input');
  if (input.value.trim() && !state.sending) { addMsg('user', input.value.trim()); send(input.value.trim()); input.value = ''; }
});
document.getElementById('chat-mascot-mini').innerHTML = MascotSVG.pose('wave');
addMsg('bot', 'Chào con! 👋 Mình là AI Gia sư. Bấm một câu gợi ý bên dưới hoặc tự hỏi mình nhé!');
loadSessions(); loadChips(); onboarding();
