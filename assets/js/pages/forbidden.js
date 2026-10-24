/* ============================================================
   OrderFlow – forbidden.js (trang 403 – không có quyền truy cập)
   Hiển thị trang nào bị chặn, tài khoản/vai trò hiện tại và lối thoát.
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  if (!OF) return;

  const PAGE_NAMES = {
    "cart.html": "Giỏ hàng",
    "categories.html": "Quản lý danh mục",
    "index.html": "Tổng quan",
    "orders.html": "Quản lý đơn hàng",
    "payments.html": "Quản lý thanh toán",
    "products.html": "Quản lý sản phẩm",
    "reports.html": "Báo cáo thống kê",
    "returns.html": "Quản lý đổi trả",
    "shipments.html": "Quản lý giao hàng",
    "shop.html": "Cửa hàng",
  };

  const ROLE_LABELS = {
    admin: "Quản trị viên",
    staff: "Nhân viên xử lý đơn",
    customer: "Khách hàng",
  };

  const ROLE_ALLOWED_PAGES = {
    admin: ["index.html", "reports.html", "products.html", "categories.html", "orders.html", "returns.html", "shipments.html", "payments.html", "shop.html", "cart.html"],
    staff: ["index.html", "reports.html", "products.html", "orders.html", "returns.html", "shipments.html", "payments.html", "shop.html", "cart.html"],
    customer: ["shop.html", "cart.html", "orders.html", "returns.html"],
  };

  const HOME_BY_ROLE = {
    admin: { href: "index.html", text: "Về trang tổng quan" },
    staff: { href: "orders.html", text: "Đi tới Quản lý đơn hàng" },
    customer: { href: "shop.html", text: "Đi tới Cửa hàng" },
  };

  function goHomeFor(role) {
    return HOME_BY_ROLE[role] || HOME_BY_ROLE.customer;
  }

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
        " nên không được mở “" + name + "”. Trang này không nằm trong quyền truy cập của vai trò hiện tại.";
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
      const dest = goHomeFor(role);
      home.textContent = dest.text;
      home.setAttribute("href", dest.href);
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
