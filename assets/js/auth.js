/* auth.js — Dùng chung cho trang Đăng nhập & Đăng ký
   ------------------------------------------------------------------
   Việc xác thực thật (kiểm tra email/mật khẩu, tạo tài khoản, phân
   quyền) nay được xử lý ở phía SERVER trong dang-nhap.php / dang-ky.php
   (xem includes/auth.php). File JS này chỉ còn lo phần giao diện:
   chuyển tab vai trò và hiện/ẩn mật khẩu — form vẫn submit bình thường
   (không preventDefault) để trình duyệt POST thẳng lên PHP.
   ------------------------------------------------------------------ */

document.addEventListener("DOMContentLoaded", () => {
  const roleTabs = document.querySelectorAll(".role-tab");
  const roleInput = document.getElementById("roleInput"); // chỉ có ở trang đăng ký
  const ageField = document.getElementById("ageGroupField");
  const syncAgeField = () => {
    if (!ageField || !roleInput) return;
    ageField.hidden = roleInput.value !== "hocsinh";
  };
  syncAgeField();

  roleTabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      roleTabs.forEach((t) => t.classList.remove("active"));
      tab.classList.add("active");
      if (roleInput) roleInput.value = tab.dataset.role;
      syncAgeField();
    });
  });

  /* Hiện/ẩn mật khẩu */
  document.querySelectorAll(".auth-toggle-pw").forEach((btn) => {
    btn.addEventListener("click", () => {
      const input = btn.parentElement.querySelector("input");
      const isPw = input.type === "password";
      input.type = isPw ? "text" : "password";
      btn.textContent = isPw ? "🙈" : "👁️";
    });
  });

  /* Trạng thái loading khi submit (để người dùng biết đang xử lý) */
  ["loginForm", "registerForm"].forEach((id) => {
    const form = document.getElementById(id);
    if (!form) return;
    form.addEventListener("submit", () => {
      const btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.dataset.original = btn.innerHTML;
        btn.innerHTML =
          id === "loginForm"
            ? "⏳ Đang đăng nhập..."
            : "⏳ Đang tạo tài khoản...";
      }
    });
  });
});
