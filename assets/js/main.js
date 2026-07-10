/* =======================================================================
   SIÊU NHÍ AN TOÀN GIAO THÔNG AI — main.js
   Toàn bộ tương tác JavaScript dùng chung cho mọi trang.
   ======================================================================= */

/* ---------- Dark/Light theme: áp dụng NGAY để tránh nháy sáng/tối ---------- */
(function () {
  try {
    const saved = localStorage.getItem("sieu-nhi-theme") || "dark";
    document.documentElement.setAttribute("data-theme", saved);
  } catch (e) {
    /* localStorage có thể bị chặn — bỏ qua để không làm dừng script phía dưới */
  }
})();

document.addEventListener("DOMContentLoaded", () => {
  /* ---------- Sticky nav background on scroll ---------- */
  const navbar = document.getElementById("navbar");
  if (navbar) {
    const onScroll = () => {
      if (window.scrollY > 20) navbar.classList.add("scrolled");
      else navbar.classList.remove("scrolled");
    };
    document.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Dark/Light toggle — hoạt động trên MỌI trang, đồng bộ qua localStorage ---------- */
  function syncThemeIcons() {
    const theme = document.documentElement.getAttribute("data-theme") || "dark";
    document.querySelectorAll(".theme-toggle").forEach((btn) => {
      btn.textContent = theme === "light" ? "☀️" : "🌙";
      btn.setAttribute(
        "aria-label",
        theme === "light" ? "Chế độ sáng" : "Chế độ tối",
      );
    });
  }
  syncThemeIcons();
  document.querySelectorAll(".theme-toggle").forEach((btn) => {
    btn.addEventListener("click", () => {
      const current =
        document.documentElement.getAttribute("data-theme") || "dark";
      const next = current === "light" ? "dark" : "light";
      document.documentElement.setAttribute("data-theme", next);
      syncThemeIcons();
      try {
        localStorage.setItem("sieu-nhi-theme", next);
      } catch (e) {
        /* bỏ qua nếu localStorage bị chặn */
      }
    });
  });

  /* ---------- Language toggle visual state ---------- */
  document.querySelectorAll(".lang-toggle span").forEach((el) => {
    el.addEventListener("click", () => {
      document
        .querySelectorAll(".lang-toggle span")
        .forEach((s) => s.classList.remove("on"));
      el.classList.add("on");
    });
  });

  /* ---------- Counter animation on scroll into view ---------- */
  const counters = document.querySelectorAll(".counter");
  const formatNum = (n) => n.toLocaleString("vi-VN");

  const animateCounter = (el) => {
    const target = parseInt(el.dataset.target, 10);
    const suffix = el.dataset.suffix || "";
    const duration = 1800;
    const start = performance.now();
    const step = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const value = Math.floor(eased * target);
      el.textContent = formatNum(value) + suffix;
      if (progress < 1) requestAnimationFrame(step);
      else el.textContent = formatNum(target) + suffix;
    };
    requestAnimationFrame(step);
  };

  if (counters.length) {
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            animateCounter(entry.target);
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.4 },
    );
    counters.forEach((c) => io.observe(c));
  }

  /* ---------- Ripple effect on CTA / primary buttons ---------- */
  window.ripple = function (e) {
    const btn = e.currentTarget;
    const circle = document.createElement("span");
    const d = Math.max(btn.clientWidth, btn.clientHeight);
    circle.className = "ripple";
    circle.style.width = circle.style.height = d + "px";
    const rect = btn.getBoundingClientRect();
    circle.style.left = e.clientX - rect.left - d / 2 + "px";
    circle.style.top = e.clientY - rect.top - d / 2 + "px";
    btn.appendChild(circle);
    setTimeout(() => circle.remove(), 650);
  };
  document.querySelectorAll("[data-ripple]").forEach((btn) => {
    btn.addEventListener("click", window.ripple);
  });

  /* ---------- Subtle mouse parallax on hero floating cards ---------- */
  const scene = document.querySelector(".robot-scene");
  if (scene) {
    scene.addEventListener("mousemove", (e) => {
      const rect = scene.getBoundingClientRect();
      const x = (e.clientX - rect.left) / rect.width - 0.5;
      const y = (e.clientY - rect.top) / rect.height - 0.5;
      scene.querySelectorAll(".float-card").forEach((card, i) => {
        const depth = (i + 1) * 4;
        card.style.transform = `translate(${x * depth}px, ${y * depth}px)`;
      });
    });
    scene.addEventListener("mouseleave", () => {
      scene.querySelectorAll(".float-card").forEach((card) => {
        card.style.transform = "";
      });
    });
  }

  /* ---------- AI Tutor preview: conversation item switching ---------- */
  document.querySelectorAll(".conv-item").forEach((item) => {
    item.addEventListener("click", () => {
      document
        .querySelectorAll(".conv-item")
        .forEach((i) => i.classList.remove("active"));
      item.classList.add("active");
    });
  });

  /* ---------- Suggested question chips (visual feedback only) ---------- */
  document.querySelectorAll(".suggest-chip").forEach((chip) => {
    chip.addEventListener("click", () => {
      chip.style.borderColor = "var(--cyan)";
      chip.style.color = "#fff";
    });
  });
});
