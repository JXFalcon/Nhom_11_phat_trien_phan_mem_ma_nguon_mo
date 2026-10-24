/* ============================================================
   Shopee – forbidden.js (trang 403 – không có quyền truy cập)
   Hiển thị trang nào bị chặn, tài khoản/vai trò hiện tại và lối thoát.
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  if (!OF) return;

  const PAGE_NAMES = {
    "index.html": "Dashboard tổng quan",
    "reports.html": "Báo cáo thống kê",
    "products.html": "Quản lý sản phẩm",
    "categories.html": "Quản lý danh mục",
    "orders.html": "Quản lý đơn hàng",
    "returns.html": "Quản lý đổi trả",
    "shipments.html": "Quản lý giao hàng",
    "payments.html": "Quản lý thanh toán",
  };

  const ROLE_LABELS = {
    admin: "Quản trị viên",
    staff: "Nhân viên xử lý đơn",
    customer: "Khách hàng",
  };

  const ROLE_ALLOWED_PAGES = {
    admin: ["index.html", "reports.html", "products.html", "categories.html"],
    staff: ["index.html", "reports.html", "products.html", "categories.html"],
    customer: [],
  };

  function blockedPage() {
    const q = new URLSearchParams(window.location.search);
    return (q.get("page") || "").split("/").pop() || "";
  }

  function render(user) {
    const role = (user || {}).role || "";
    const page = blockedPage();
    const name = PAGE_NAMES[page] || page || "trang này";

    const msgEl = document.getElementById("forbiddenMsg");
    const pageEl = document.getElementById("blockedPageName");
    if (pageEl) pageEl.textContent = name + (page ? "  (" + page + ")" : "");

    if (msgEl) {
      msgEl.textContent =
        "Tài khoản " + ((user || {}).full_name || "của bạn") +
        " đang đăng nhập với vai trò " + (ROLE_LABELS[role] || role || "không xác định") +
        " nên không được mở “" + name + "”. Trang này chỉ dành cho quản trị viên và nhân viên xử lý đơn.";
    }

    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val || "—";
    };
    set("fUserName", (user || {}).full_name);
    set("fUserRole", ROLE_LABELS[role] || role);
    set("fUserEmail", (user || {}).email);

    const home = document.getElementById("goHomeBtn");
    if (home) {
      home.textContent = role === "customer" ? "Đi tới Đơn hàng của tôi" : "Về trang tổng quan";
      home.setAttribute("href", role === "customer" ? "orders.html" : "index.html");
    }

    const adminBtn = document.getElementById("goAdminBtn");
    if (adminBtn) {
      const canAdmin = (ROLE_ALLOWED_PAGES[role] || []).indexOf(page) !== -1;
      adminBtn.style.display = canAdmin ? "" : "none";
    }
  }

  function bindLogout() {
    const btn = document.getElementById("logoutBtn403");
    if (!btn) return;
    btn.addEventListener("click", async () => {
      btn.disabled = true;
      try { await OF.api.post("auth/logout.php"); } catch (e) { /* bỏ qua */ }
      OF.session.clear();
      window.location.replace("login.html");
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    bindLogout();
    render(OF.session.getUser());

    // Làm mới vai trò từ server (nguồn chuẩn) rồi render lại
    (async function refresh() {
      const res = await OF.api.get("auth/me.php");
      if (!res.success) return;
      OF.session.setUser(res.data);
      render(res.data);
    })();
  });
})();
