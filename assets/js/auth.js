/* ============================================================
   Shopee – auth.js (trang đăng nhập / đăng ký)
   Giai đoạn 2: gọi API backend PHP (api/auth/*).
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  if (!OF) return;

  /* ---------------- Kiểm tra định dạng ---------------- */
  const validators = {
    required(v) { return String(v || "").trim().length > 0; },
    email(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(v).trim()); },
    phone(v) { return /^(0|\+84)[0-9]{9,10}$/.test(String(v).replace(/\s/g, "")); },
    password(v) { return String(v || "").length >= 6; },
    match(a, b) { return String(a) === String(b); },
  };

  function setFieldError(input, errEl, message) {
    if (message) {
      input.classList.add("is-invalid");
      errEl.textContent = message;
      errEl.classList.add("show");
    } else {
      input.classList.remove("is-invalid");
      errEl.classList.remove("show");
      errEl.textContent = "";
    }
  }

  function bindLiveClear(form) {
    form.querySelectorAll(".form-control").forEach((inp) => {
      inp.addEventListener("input", () => {
        const err = form.querySelector('[data-error-for="' + inp.id + '"]');
        if (err) setFieldError(inp, err, null);
      });
    });
  }

  function showAlert(box, type, message) {
    box.className = "auth-alert " + type + " show";
    box.innerHTML =
      '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' +
      (type === "success"
        ? '<path d="m5 13 4.5 4.5L19.5 7"/>'
        : '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.2v.2"/>') +
      "</svg><span>" + OF.escapeHtml(message) + "</span>";
  }

  function togglePassword(btn, input) {
    btn.addEventListener("click", () => {
      const show = input.type === "password";
      input.type = show ? "text" : "password";
      btn.innerHTML = OF.icon(show ? "eyeOff" : "eye");
      btn.setAttribute("aria-label", show ? "Ẩn mật khẩu" : "Hiện mật khẩu");
    });
  }

  /* ================= TRANG ĐĂNG NHẬP ================= */
  // Xác định trang đích sau đăng nhập: ưu tiên ?redirect=<ten>.html (chỉ nhận
  // tên file .html an toàn), mặc định luôn luôn là trang chủ.
  function resolveLanding() {
    const params = new URLSearchParams(window.location.search);
    const redirect = (params.get("redirect") || "").trim();
    if (/^[a-z][a-z0-9_]*\.html$/i.test(redirect)) return redirect;
    return "index.html";
  }

  function initLogin() {
    // Nếu đã có token thì vào thẳng trang đích (mặc định là trang chủ,
    // tối đa 1 lần tránh ping-pong khi token cũ còn sót trong localStorage)
    if (OF.session.getToken()) {
      window.location.replace(resolveLanding());
      return;
    }
    const form = document.getElementById("loginForm");
    if (!form) return;
    const email = document.getElementById("loginEmail");
    const password = document.getElementById("loginPassword");
    const alertBox = document.getElementById("loginAlert");
    const remember = document.getElementById("loginRemember");

    // Hiện lý do bị đưa về trang đăng nhập (nếu có), rồi xóa ghi chú
    const authInfo = sessionStorage.getItem("orderflow.authInfo");
    if (authInfo) {
      sessionStorage.removeItem("orderflow.authInfo");
      showAlert(alertBox, "error", authInfo);
    }

    togglePassword(document.getElementById("toggleLoginPw"), password);
    bindLiveClear(form);

    // Nút điền nhanh tài khoản demo
    document.querySelectorAll("[data-fill-account]").forEach((btn) => {
      btn.addEventListener("click", () => {
        email.value = btn.getAttribute("data-fill-account");
        password.value = btn.getAttribute("data-fill-pw");
        setFieldError(email, form.querySelector('[data-error-for="loginEmail"]'), null);
        setFieldError(password, form.querySelector('[data-error-for="loginPassword"]'), null);
        alertBox.classList.remove("show");
      });
    });

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      let ok = true;
      if (!validators.email(email.value)) {
        setFieldError(email, form.querySelector('[data-error-for="loginEmail"]'), "Email không hợp lệ. Ví dụ: admin@orderflow.vn");
        ok = false;
      }
      if (!validators.password(password.value)) {
        setFieldError(password, form.querySelector('[data-error-for="loginPassword"]'), "Mật khẩu phải có ít nhất 6 ký tự.");
        ok = false;
      }
      if (!ok) return;

      const submitBtn = form.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      const res = await OF.api.post("auth/login.php", {
        email: email.value.trim(),
        password: password.value,
      });
      if (!res.success) {
        submitBtn.disabled = false;
        showAlert(alertBox, "error", res.message || "Đăng nhập thất bại.");
        return;
      }
      OF.session.setToken(res.data.token);
      OF.session.setUser(res.data.user);
      if (remember && remember.checked) localStorage.setItem("orderflow.remember", "1");
      else localStorage.removeItem("orderflow.remember");
      const landing = resolveLanding();
      showAlert(alertBox, "success", "Đăng nhập thành công! Đang quay về trang chủ…");
      setTimeout(() => { window.location.href = landing; }, 500);
    });
  }

  /* ================= TRANG ĐĂNG KÝ ================= */
  function initRegister() {
    const form = document.getElementById("registerForm");
    if (!form) return;
    const fullName = document.getElementById("regFullName");
    const email = document.getElementById("regEmail");
    const phone = document.getElementById("regPhone");
    const password = document.getElementById("regPassword");
    const confirmPw = document.getElementById("regConfirm");
    const alertBox = document.getElementById("registerAlert");

    togglePassword(document.getElementById("toggleRegPw"), password);
    togglePassword(document.getElementById("toggleRegPw2"), confirmPw);
    bindLiveClear(form);
    confirmPw.addEventListener("input", () => {
      const err = form.querySelector('[data-error-for="regConfirm"]');
      setFieldError(confirmPw, err, null);
    });

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      let ok = true;
      if (!validators.required(fullName.value) || fullName.value.trim().length < 2) {
        setFieldError(fullName, form.querySelector('[data-error-for="regFullName"]'), "Vui lòng nhập họ tên (tối thiểu 2 ký tự).");
        ok = false;
      }
      if (!validators.email(email.value)) {
        setFieldError(email, form.querySelector('[data-error-for="regEmail"]'), "Email không hợp lệ. Ví dụ: ten@domain.com");
        ok = false;
      }
      if (!validators.phone(phone.value)) {
        setFieldError(phone, form.querySelector('[data-error-for="regPhone"]'), "Số điện thoại không hợp lệ (bắt đầu bằng 0 hoặc +84).");
        ok = false;
      }
      if (!validators.password(password.value)) {
        setFieldError(password, form.querySelector('[data-error-for="regPassword"]'), "Mật khẩu phải có ít nhất 6 ký tự.");
        ok = false;
      }
      if (!validators.match(password.value, confirmPw.value)) {
        setFieldError(confirmPw, form.querySelector('[data-error-for="regConfirm"]'), "Mật khẩu xác nhận không khớp.");
        ok = false;
      }
      if (!ok) return;

      const submitBtn = form.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      const res = await OF.api.post("auth/register.php", {
        full_name: fullName.value.trim(),
        email: email.value.trim(),
        phone: phone.value.trim(),
        password: password.value,
        password_confirm: confirmPw.value,
      });
      if (!res.success) {
        submitBtn.disabled = false;
        const msg = res.message || "Đăng ký thất bại.";
        const dup = res.errors && res.errors.email;
        if (dup) {
          setFieldError(email, form.querySelector('[data-error-for="regEmail"]'), dup);
        } else {
          showAlert(alertBox, "error", msg);
        }
        return;
      }

      showAlert(alertBox, "success", "Đăng ký thành công! Đang chuyển về trang đăng nhập…");
      toastAfterRegister("Tài khoản " + res.data.email + " đã được tạo trong cơ sở dữ liệu.");
      setTimeout(() => { window.location.href = "login.html"; }, 1500);
    });
  }

  function toastAfterRegister(msg) {
    let root = document.getElementById("toast-root");
    if (!root) {
      root = document.createElement("div");
      root.id = "toast-root";
      root.className = "toast-root";
      document.body.appendChild(root);
    }
    const el = document.createElement("div");
    el.className = "toast success";
    el.innerHTML =
      '<div class="toast-icon">' + OF.icon("check") + "</div>" +
      '<div class="toast-content"><div class="toast-title">Đăng ký thành công</div>' +
      '<div class="toast-msg">' + OF.escapeHtml(msg) + "</div></div>";
    root.appendChild(el);
  }

  /* ---------------- Boot ---------------- */
  document.addEventListener("DOMContentLoaded", function () {
    initLogin();
    initRegister();
  });
})();
