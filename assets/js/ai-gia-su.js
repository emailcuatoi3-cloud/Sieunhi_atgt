/* ai-gia-su.js — Tương tác cho trang AI Gia sư */
document.addEventListener('DOMContentLoaded', () => {
  const chatInner = document.getElementById('chatInner');
  const chatScroll = document.getElementById('chatScroll');
  const chatText = document.getElementById('chatText');

  const replies = [
    "Con nhớ đội mũ bảo hiểm cài quai đúng cách, không lỏng quá cũng không chặt quá — mũ vừa khít với đầu là tốt nhất nhé! ⛑️",
    "Khi gặp xe cứu thương, con và mọi người nên nhường đường, đứng lại an toàn ở vỉa hè để xe đi qua thật nhanh nhé. 🚑",
    "Vạch kẻ đường dành cho người đi bộ đó con — khi qua đường, hãy đi trong vạch và quan sát hai bên nhé. 🚸",
    "Khi đi xe đạp, con luôn cần đội mũ bảo hiểm đạt chuẩn để bảo vệ đầu nếu chẳng may té ngã nhé! 🚲"
  ];

  function scrollBottom(){ chatScroll.scrollTop = chatScroll.scrollHeight; }

  function appendMsg(role, html){
    const wrap = document.createElement('div');
    wrap.className = 'msg ' + role;
    wrap.innerHTML = `<div class="msg-avatar">${role==='bot'?'🤖':'🧒'}</div><div class="msg-body"><div class="msg-bubble">${html}</div></div>`;
    chatInner.appendChild(wrap);
    scrollBottom();
  }

  function showTyping(){
    const wrap = document.createElement('div');
    wrap.className = 'msg bot';
    wrap.id = 'typingMsg';
    wrap.innerHTML = `<div class="msg-avatar">🤖</div><div class="msg-body"><div class="msg-bubble typing"><span></span><span></span><span></span></div></div>`;
    chatInner.appendChild(wrap);
    scrollBottom();
  }

  function respond(){
    showTyping();
    setTimeout(() => {
      const t = document.getElementById('typingMsg');
      if (t) t.remove();
      appendMsg('bot', replies[Math.floor(Math.random() * replies.length)]);
    }, 1100);
  }

  window.sendMsg = function () {
    const val = chatText.value.trim();
    if (!val) return;
    appendMsg('user', val);
    chatText.value = '';
    respond();
  };

  window.askSuggested = function (el) {
    appendMsg('user', el.textContent);
    respond();
  };

  window.newChat = function () {
    chatInner.innerHTML = '';
    appendMsg('bot', 'Chào con! Mình là AI Gia sư 🤖 — hôm nay con muốn hỏi điều gì về giao thông nào?');
  };

  document.querySelectorAll('.conv-item').forEach(item => {
    item.addEventListener('click', () => {
      document.querySelectorAll('.conv-item').forEach(i => i.classList.remove('active'));
      item.classList.add('active');
    });
  });
});
