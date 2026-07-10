/* ai-truyen-tranh.js — Tương tác cho trang AI Truyện tương tác */
document.addEventListener('DOMContentLoaded', () => {
  const story = {
    start: {
      chapter: 'Chương 1', tag: '📍 Cổng trường Tiểu học Việt Nam', emoji: '🏫',
      speaker: 'Bo', avatar: '🧒',
      text: "Bo chuẩn bị đi bộ đến trường một mình lần đầu tiên. Trước mặt Bo là một ngã tư có đèn tín hiệu và khá đông xe cộ qua lại vào giờ cao điểm.",
      choices: [
        { label: 'Đi thẳng qua đường ngay vì thấy vắng xe', icon: '🏃', next: 'rush' },
        { label: 'Tìm vạch qua đường và quan sát đèn tín hiệu', icon: '🦓', next: 'safeCross' }
      ]
    },
    rush: {
      chapter: 'Chương 2', tag: '📍 Giữa ngã tư', emoji: '🚗',
      speaker: 'AI Gia sư', avatar: '🤖',
      text: "Bo băng qua đường không nhìn vạch kẻ. Một chiếc xe máy phanh gấp, còi vang lên inh ỏi! May mắn Bo kịp dừng lại. <span class='hl'>Tình huống nguy hiểm suýt xảy ra.</span>",
      choices: [
        { label: 'Hoảng sợ chạy tiếp qua đường', icon: '😰', next: 'endBad' },
        { label: 'Bình tĩnh lùi lại vỉa hè và tìm vạch qua đường', icon: '🚶', next: 'recover' }
      ]
    },
    safeCross: {
      chapter: 'Chương 2', tag: '📍 Vạch qua đường', emoji: '🦓',
      speaker: 'Bo', avatar: '🧒',
      text: "Bo tìm thấy vạch kẻ dành cho người đi bộ. Đèn tín hiệu đang đỏ. Một bạn cùng lớp tên Na vẫy tay gọi Bo băng qua nhanh trước khi đèn đổi.",
      choices: [
        { label: 'Nghe theo Na, chạy nhanh qua trước khi đèn đổi', icon: '🏃', next: 'peerPressure' },
        { label: 'Đợi đèn xanh rồi mới qua cùng Na', icon: '🟢', next: 'goodWait' }
      ]
    },
    peerPressure: {
      chapter: 'Chương 3', tag: '📍 Vạch qua đường', emoji: '⚠️',
      speaker: 'AI Gia sư', avatar: '🤖',
      text: "Bo và Na chạy vội qua khi đèn còn đỏ. Một chiếc xe buýt trường học phải phanh gấp để nhường đường. Cả hai bạn đều giật mình sợ hãi.",
      choices: [
        { label: 'Rút kinh nghiệm, hứa sẽ luôn chờ đèn xanh', icon: '🙋', next: 'endMid' },
        { label: 'Nghĩ rằng lần này chỉ là may mắn thôi', icon: '🤷', next: 'endBad' }
      ]
    },
    goodWait: {
      chapter: 'Chương 3', tag: '📍 Vạch qua đường', emoji: '🟢',
      speaker: 'Bo', avatar: '🧒',
      text: "Bo giải thích cho Na rằng chờ đèn xanh mới là an toàn nhất. Cả hai cùng đợi, quan sát hai bên rồi bước qua đường trong vạch kẻ, đúng lúc đèn chuyển xanh.",
      choices: [{ label: 'Vẫy tay cảm ơn chú công an đang điều tiết giao thông', icon: '👋', next: 'endGood' }]
    },
    recover: {
      chapter: 'Chương 3', tag: '📍 Vỉa hè', emoji: '🦓',
      speaker: 'Bo', avatar: '🧒',
      text: "Bo bình tĩnh lùi lại vỉa hè, tìm đến vạch qua đường gần đó và đợi đèn tín hiệu chuyển xanh trước khi bước qua an toàn.",
      choices: [{ label: 'Ghi nhớ bài học và kể lại cho AI Gia sư', icon: '💬', next: 'endMid' }]
    },
    endGood: {
      ending: 'good', chapter: 'Kết thúc', tag: '🎉 Hoàn thành xuất sắc', emoji: '🏆',
      speaker: 'AI Gia sư', avatar: '🤖',
      text: "Bo đã đến trường an toàn! Bằng cách chờ đèn xanh và đi đúng vạch kẻ, Bo không chỉ bảo vệ bản thân mà còn làm gương tốt cho bạn bè. <span class='hl'>+50 XP · Mở khoá huy hiệu 'Người đi bộ thông minh'.</span>"
    },
    endMid: {
      ending: 'mid', chapter: 'Kết thúc', tag: '👍 Đã rút kinh nghiệm', emoji: '🙂',
      speaker: 'AI Gia sư', avatar: '🤖',
      text: "Bo đã gặp một tình huống nguy hiểm nhưng kịp thời nhận ra sai lầm và sửa đổi. Lần sau Bo sẽ luôn chờ đèn xanh và đi đúng vạch kẻ nhé. <span class='hl'>+20 XP.</span>"
    },
    endBad: {
      ending: 'bad', chapter: 'Kết thúc', tag: '⚠️ Cần cẩn thận hơn', emoji: '🚨',
      speaker: 'AI Gia sư', avatar: '🤖',
      text: "Rất may Bo không bị thương, nhưng tình huống này rất nguy hiểm. Hãy nhớ: luôn dừng lại, quan sát và chờ đèn xanh trước khi qua đường — dù bạn bè có giục con thế nào đi nữa. <span class='hl'>Hãy thử lại để tìm kết thúc an toàn hơn!</span>"
    }
  };

  let visited = ['start'];
  const baseEndHtml = `
    <div class="end-actions">
      <button class="btn btn-primary-sm" onclick="restart()">🔁 Chơi lại từ đầu</button>
      <a class="btn btn-ghost" href="sieu-nhi-atgt-ai.php">🏠 Về trang chủ</a>
    </div>`;

  function renderNode(key) {
    const node = story[key];
    document.getElementById('chapterTag').textContent = node.chapter;
    document.getElementById('sceneTag').textContent = node.tag;
    document.getElementById('sceneEmoji').textContent = node.emoji;
    document.getElementById('speakerAvatar').textContent = node.avatar;
    document.getElementById('speakerName').textContent = node.speaker;
    document.getElementById('narrationText').innerHTML = node.text;

    const choiceGrid = document.getElementById('choiceGrid');
    const endBlock = document.getElementById('endBlock');
    choiceGrid.innerHTML = '';

    if (node.ending) {
      const badgeClass = node.ending === 'good' ? 'good' : node.ending === 'mid' ? 'mid' : 'bad';
      const badgeLabel = node.ending === 'good' ? '🏆 Kết thúc tốt nhất' : node.ending === 'mid' ? '👍 Kết thúc ổn' : '⚠️ Kết thúc cần cải thiện';
      endBlock.style.display = 'block';
      endBlock.innerHTML = `<div class="ending-badge ${badgeClass}">${badgeLabel}</div>` + baseEndHtml;
    } else {
      endBlock.style.display = 'none';
      node.choices.forEach(choice => {
        const btn = document.createElement('button');
        btn.className = 'choice-btn';
        btn.innerHTML = `<span class="c-ic">${choice.icon}</span> ${choice.label}`;
        btn.onclick = () => { visited.push(choice.next); renderNode(choice.next); renderPath(); };
        choiceGrid.appendChild(btn);
      });
    }
  }

  function renderPath() {
    const row = document.getElementById('pathRow');
    row.innerHTML = '';
    const total = 4;
    for (let i = 0; i < total; i++) {
      const dot = document.createElement('div');
      if (i < visited.length - 1) dot.className = 'path-step done';
      else if (i === visited.length - 1) dot.className = 'path-step now';
      else dot.className = 'path-step';
      row.appendChild(dot);
      if (i < total - 1) {
        const line = document.createElement('div');
        line.className = 'path-line';
        row.appendChild(line);
      }
    }
  }

  window.restart = function () {
    visited = ['start'];
    renderNode('start');
    renderPath();
    document.getElementById('endBlock').innerHTML = baseEndHtml;
  };

  renderNode('start');
  renderPath();
});
