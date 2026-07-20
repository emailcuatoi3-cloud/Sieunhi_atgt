/* sound-fx.js — Hiệu ứng âm thanh ngắn dùng Web Audio API (không cần file mp3)
   ------------------------------------------------------------------
   Dùng: SoundFX.correct(), SoundFX.wrong(), SoundFX.success(), SoundFX.click()
   Tự động im lặng nếu trình duyệt chặn AudioContext (cần tương tác người
   dùng trước) — không bao giờ throw lỗi làm hỏng phần còn lại của trang.
   ------------------------------------------------------------------ */

const SoundFX = (() => {
  let ctx = null;
  function getCtx() {
    if (!ctx) {
      try {
        ctx = new (window.AudioContext || window.webkitAudioContext)();
      } catch (e) {
        return null;
      }
    }
    if (ctx.state === 'suspended') ctx.resume().catch(() => {});
    return ctx;
  }

  function tone(freq, duration, type = 'sine', startDelay = 0, volume = 0.18) {
    const audio = getCtx();
    if (!audio) return;
    try {
      const osc = audio.createOscillator();
      const gain = audio.createGain();
      osc.type = type;
      osc.frequency.value = freq;
      gain.gain.value = volume;
      osc.connect(gain);
      gain.connect(audio.destination);
      const t0 = audio.currentTime + startDelay;
      gain.gain.setValueAtTime(volume, t0);
      gain.gain.exponentialRampToValueAtTime(0.001, t0 + duration);
      osc.start(t0);
      osc.stop(t0 + duration);
    } catch (e) { /* im lặng nếu có lỗi trình duyệt */ }
  }

  return {
    /** Tiếng "tinh" cao, ngắn — dùng khi trả lời/ghép đúng */
    correct() {
      tone(880, 0.12, 'triangle', 0);
      tone(1320, 0.12, 'triangle', 0.07);
    },
    /** Tiếng trầm, buzz nhẹ — dùng khi sai */
    wrong() {
      tone(180, 0.18, 'sawtooth', 0, 0.12);
    },
    /** Chuỗi âm thắng cuộc, dùng khi hoàn thành cả game */
    success() {
      tone(523, 0.14, 'triangle', 0);
      tone(659, 0.14, 'triangle', 0.12);
      tone(784, 0.22, 'triangle', 0.24);
    },
    /** Tiếng click nhẹ cho tương tác UI thông thường */
    click() {
      tone(440, 0.06, 'square', 0, 0.08);
    },
    /** Chuông nhỏ khi lên cấp */
    levelUp() {
      tone(660, 0.1, 'triangle', 0);
      tone(880, 0.1, 'triangle', 0.1);
      tone(1100, 0.18, 'triangle', 0.2);
    }
  };
})();

window.SoundFX = SoundFX;
