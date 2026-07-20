/* =========================================================
   AI GIA SƯ — Chat thật + Lịch sử CSDL + Giọng nói
   Yêu cầu: ai-chat.php, ai-engine.php, bảng ai_chat_*
   ========================================================= */
const API = "ai-chat.php";
let currentSessionId = 0; // 0 = chưa có cuộc trò chuyện (sẽ tạo khi gửi tin đầu tiên)
let sending = false;
let chatLog = []; // lưu tạm để xuất báo cáo

document.addEventListener("DOMContentLoaded", () => {
  buildEmojiPop();
  loadSessions();
  newChat(false);
});

/* ---------- Tiện ích ---------- */
function esc(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}
/* Cho phép **in đậm** và xuống dòng trong câu trả lời của AI */
function fmt(s) {
  return esc(s)
    .replace(/\*\*(.+?)\*\*/g, "<b>$1</b>")
    .replace(/\n/g, "<br>");
}
function scrollBottom() {
  const sc = document.getElementById("chatScroll");
  sc.scrollTop = sc.scrollHeight;
}
function toast(msg) {
  let t = document.getElementById("toast");
  if (!t) {
    t = document.createElement("div");
    t.id = "toast";
    t.className = "toast";
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.classList.add("show");
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove("show"), 2200);
}

/* ---------- Hiển thị tin nhắn ---------- */
function addUserMsg(text) {
  chatLog.push({ role: "user", content: text });
  const div = document.createElement("div");
  div.className = "msg user";
  div.innerHTML = `<div class="msg-avatar">🧒</div>
     <div class="msg-body"><div class="msg-bubble">${fmt(text)}</div></div>`;
  document.getElementById("chatInner").appendChild(div);
}

function addBotMsg(text) {
  chatLog.push({ role: "bot", content: text });
  const div = document.createElement("div");
  div.className = "msg bot";
  div.innerHTML = `<div class="msg-avatar">🤖</div>
     <div class="msg-body">
       <div class="msg-bubble">${fmt(text)}</div>
       <div class="msg-tools">
         <div class="msg-tool" onclick="speakMsg(this)">🔊 Nghe</div>
         <div class="msg-tool" onclick="feedback(this)">👍 Hữu ích</div>
         <div class="msg-tool" onclick="feedback(this)">👎 Chưa rõ</div>
       </div>
     </div>`;
  document.getElementById("chatInner").appendChild(div);
}

function showTyping() {
  const div = document.createElement("div");
  div.className = "msg bot";
  div.id = "typingMsg";
  div.innerHTML = `<div class="msg-avatar">🤖</div>
     <div class="msg-body"><div class="msg-bubble">
       <span class="typing-dots"><i></i><i></i><i></i></span>
     </div></div>`;
  document.getElementById("chatInner").appendChild(div);
  scrollBottom();
}
function hideTyping() {
  const t = document.getElementById("typingMsg");
  if (t) t.remove();
}

/* ---------- Gửi tin nhắn ---------- */
async function sendMsg() {
  const input = document.getElementById("chatText");
  const text = input.value.trim();
  if (!text || sending) return;

  sending = true;
  input.value = "";
  addUserMsg(text);
  showTyping();
  scrollBottom();

  try {
    const fd = new FormData();
    fd.append("action", "send");
    fd.append("session_id", currentSessionId);
    fd.append("message", text);

    // Chờ tối thiểu 0.7s để bé thấy hiệu ứng AI "đang gõ"
    const [res] = await Promise.all([
      fetch(API, { method: "POST", body: fd }),
      new Promise((r) => setTimeout(r, 700)),
    ]);
    const data = await res.json();
    hideTyping();

    if (data.status === "success") {
      const isNew = currentSessionId === 0;
      currentSessionId = data.session_id;
      addBotMsg(data.reply);
      if (isNew) loadSessions(); // cuộc trò chuyện mới → cập nhật sidebar
    } else {
      addBotMsg(
        "Ôi, có lỗi rồi: " + (data.message || "không xác định") + " 😢",
      );
    }
  } catch (e) {
    hideTyping();
    addBotMsg(
      "Mình chưa kết nối được với máy chủ 😢 Con kiểm tra lại XAMPP (Apache + MySQL) giúp mình nhé!",
    );
  }

  sending = false;
  scrollBottom();
  input.focus();
}

function askSuggested(el) {
  document.getElementById("chatText").value = el.textContent.trim();
  sendMsg();
}

/* ---------- Lịch sử trò chuyện (sidebar) ---------- */
function groupLabel(dateStr) {
  const d = new Date(String(dateStr).replace(" ", "T"));
  const now = new Date();
  const startToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const startThat = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const diffDays = Math.round((startToday - startThat) / 86400000);
  if (diffDays <= 0) return "Hôm nay";
  if (diffDays < 7) return "7 ngày qua";
  return "Cũ hơn";
}

async function loadSessions() {
  try {
    const res = await fetch(API + "?action=sessions");
    const data = await res.json();
    const box = document.getElementById("sessionList");
    box.innerHTML = "";
    if (data.status !== "success") return;

    if (data.sessions.length === 0) {
      box.innerHTML =
        '<div class="side-link group-label">Chưa có cuộc trò chuyện</div>';
      return;
    }

    let lastGroup = "";
    data.sessions.forEach((s) => {
      const g = groupLabel(s.updated_at);
      if (g !== lastGroup) {
        box.insertAdjacentHTML(
          "beforeend",
          `<div class="side-link group-label">${g}</div>`,
        );
        lastGroup = g;
      }
      const item = document.createElement("div");
      item.className =
        "side-link session-item" +
        (String(s.id) === String(currentSessionId) ? " active" : "");
      item.dataset.id = s.id;
      item.innerHTML =
        `<span class="s-title">💬 ${esc(s.title)}</span>` +
        `<span class="del" title="Xoá cuộc trò chuyện">✕</span>`;
      item.addEventListener("click", () => openSession(s.id));
      item.querySelector(".del").addEventListener("click", (ev) => {
        ev.stopPropagation();
        deleteSession(s.id);
      });
      box.appendChild(item);
    });
  } catch (e) {
    /* bỏ qua lỗi mạng khi tải sidebar */
  }
}

async function openSession(id) {
  currentSessionId = id;
  document
    .querySelectorAll(".session-item")
    .forEach((el) =>
      el.classList.toggle("active", String(el.dataset.id) === String(id)),
    );

  const inner = document.getElementById("chatInner");
  inner.innerHTML = "";
  chatLog = [];

  try {
    const res = await fetch(API + "?action=messages&session_id=" + id);
    const data = await res.json();
    if (data.status === "success") {
      data.messages.forEach((m) =>
        m.role === "user" ? addUserMsg(m.content) : addBotMsg(m.content),
      );
    }
  } catch (e) {}
  scrollBottom();
}

function newChat(focus = true) {
  currentSessionId = 0;
  chatLog = [];
  document.getElementById("chatInner").innerHTML = "";
  document
    .querySelectorAll(".session-item")
    .forEach((el) => el.classList.remove("active"));
  addBotMsg(
    `Chào ${STUDENT_NAME}! Mình là AI Gia sư 🤖 — hôm nay con muốn học điều gì về an toàn giao thông nào? Con có thể gõ câu hỏi hoặc bấm nút 🎤 để nói chuyện với mình nhé!`,
  );
  scrollBottom();
  if (focus) document.getElementById("chatText").focus();
}

async function deleteSession(id) {
  if (!confirm("Xoá cuộc trò chuyện này?")) return;
  const fd = new FormData();
  fd.append("action", "delete");
  fd.append("session_id", id);
  try {
    await fetch(API, { method: "POST", body: fd });
  } catch (e) {}
  if (String(id) === String(currentSessionId)) newChat(false);
  loadSessions();
  toast("Đã xoá cuộc trò chuyện 🗑️");
}

/* ---------- AI đọc câu trả lời (Text-to-Speech) ---------- */
function stripEmoji(s) {
  return s.replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}]/gu, "");
}
function speakMsg(btn) {
  if (!("speechSynthesis" in window)) {
    toast("Trình duyệt không hỗ trợ đọc giọng nói");
    return;
  }
  const text = btn.closest(".msg-body").querySelector(".msg-bubble").innerText;
  speechSynthesis.cancel(); // dừng câu đang đọc dở (nếu có)
  const u = new SpeechSynthesisUtterance(stripEmoji(text));
  u.lang = "vi-VN";
  u.rate = 0.95;
  const viVoice = speechSynthesis
    .getVoices()
    .find((v) => v.lang && v.lang.startsWith("vi"));
  if (viVoice) u.voice = viVoice;
  speechSynthesis.speak(u);
}

/* ---------- Bé nói, AI nghe (Speech-to-Text) ---------- */
let recog = null,
  recording = false;
function toggleMic(btn) {
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) {
    toast("Trình duyệt chưa hỗ trợ ghi âm 🎤 — con dùng Google Chrome nhé!");
    return;
  }
  if (recording) {
    recog.stop();
    return;
  }

  recog = new SR();
  recog.lang = "vi-VN";
  recog.interimResults = false;
  recog.onstart = () => {
    recording = true;
    btn.classList.add("rec");
    toast("Mình đang nghe... con nói đi! 🎤");
  };
  recog.onend = () => {
    recording = false;
    btn.classList.remove("rec");
  };
  recog.onresult = (e) => {
    document.getElementById("chatText").value = e.results[0][0].transcript;
    sendMsg();
  };
  recog.onerror = () => toast("Mình chưa nghe rõ, con thử lại nhé!");
  recog.start();
}

/* ---------- Đánh giá 👍/👎 ---------- */
function feedback(el) {
  el.parentElement
    .querySelectorAll(".msg-tool")
    .forEach((t) => t.classList.remove("active"));
  el.classList.add("active");
  toast("Cảm ơn phản hồi của con! 💛");
}

/* ---------- Xuất báo cáo trò chuyện (.txt) ---------- */
function exportChat() {
  if (chatLog.length <= 1) {
    toast("Chưa có nội dung để xuất 📄");
    return;
  }
  let out = "BÁO CÁO TRÒ CHUYỆN — AI GIA SƯ\n";
  out += "Học sinh: " + STUDENT_NAME + "\n";
  out += "Ngày xuất: " + new Date().toLocaleString("vi-VN") + "\n";
  out += "----------------------------------------\n\n";
  chatLog.forEach((m) => {
    out +=
      (m.role === "user" ? "🧒 " + STUDENT_NAME : "🤖 AI Gia sư") +
      ":\n" +
      m.content +
      "\n\n";
  });
  const blob = new Blob(["\ufeff" + out], { type: "text/plain;charset=utf-8" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = "bao-cao-ai-gia-su.txt";
  a.click();
  URL.revokeObjectURL(a.href);
}

/* ---------- Bảng chọn emoji ---------- */
const EMOJIS = [
  "😊",
  "😄",
  "👍",
  "❤️",
  "🚦",
  "🚗",
  "🛵",
  "🚲",
  "⛑️",
  "🚸",
  "🛑",
  "🚑",
  "👮",
  "⭐",
  "🎉",
  "❓",
];
function buildEmojiPop() {
  const pop = document.getElementById("emojiPop");
  if (!pop) return;
  EMOJIS.forEach((e) => {
    const sp = document.createElement("span");
    sp.textContent = e;
    sp.onclick = () => {
      const i = document.getElementById("chatText");
      i.value += e;
      i.focus();
    };
    pop.appendChild(sp);
  });
  // Bấm ra ngoài thì đóng bảng emoji
  document.addEventListener("click", (ev) => {
    if (
      !ev.target.closest("#emojiPop") &&
      !ev.target.closest("[data-emoji-btn]")
    ) {
      pop.classList.remove("open");
    }
  });
}
function toggleEmoji(ev) {
  ev.stopPropagation();
  document.getElementById("emojiPop").classList.toggle("open");
}
