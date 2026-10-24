/* ============================================================
   OrderFlow – app.js
   Bố cục dùng chung: sidebar, topbar, bảo vệ trang, modal,
   toast, phân trang, biểu đồ SVG.
   Các trang gọi dữ liệu thật qua assets/js/api.js → /api/*.php.
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;

  /* ================= Bộ biểu tượng SVG (stroke) ================= */
  const ICON_PATHS = {
    home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/><path d="M10 21v-6h4v6"/>',
    box: '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>',
    tag: '<path d="M3 3h8.5L21 12.5 12.5 21 3 12.5z"/><circle cx="7.8" cy="7.8" r="1.4"/>',
    clipboard: '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2.8A.8.8 0 0 1 9.8 2h4.4a.8.8 0 0 1 .8.8V4"/><path d="M9 10h6M9 14h4"/>',
    ret: '<path d="M9.5 15 5 10.5 9.5 6"/><path d="M5 10.5h9a5.5 5.5 0 0 1 0 11h-2.5"/>',
    truck: '<rect x="1.5" y="6" width="13" height="11" rx="1.5"/><path d="M14.5 9.5h3.6l3.4 3.5V17h-7z"/><circle cx="5.8" cy="18.8" r="2"/><circle cx="17.8" cy="18.8" r="2"/>',
    card: '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19"/><path d="M6 15h4"/>',
    cart: '<path d="M3 4h2.2l2.3 10.7a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H6.2"/><circle cx="10" cy="20" r="1.4"/><circle cx="17.5" cy="20" r="1.4"/>',
    chart: '<path d="M4.5 20v-6.5M10 20V8M15.5 20v-9M21 20V4.5"/><path d="M2.5 20h19"/>',
    users: '<circle cx="9" cy="8" r="3.2"/><path d="M2.8 20c.6-3.4 3.2-5.2 6.2-5.2s5.6 1.8 6.2 5.2"/><circle cx="17" cy="9" r="2.4"/><path d="M17.2 14.6c2.2.2 3.7 1.7 4 4.4"/>',
    logout: '<path d="M10 4H6.5A2.5 2.5 0 0 0 4 6.5v11A2.5 2.5 0 0 0 6.5 20H10"/><path d="M15.5 8.5 19 12l-3.5 3.5M19 12H9"/>',
    menu: '<path d="M4 6.5h16M4 12h16M4 17.5h16"/>',
    search: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4.5 4.5"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    edit: '<path d="M4 20.2h4.2L20 8.4 15.8 4.2 4 16z"/><path d="m13.8 6.2 4 4"/>',
    trash: '<path d="M4 7h16"/><path d="M9 7V4.5h6V7"/><path d="m6.5 7 1 13.5h9L17.5 7"/><path d="M10.5 11v6M13.5 11v6"/>',
    eye: '<path d="M2 12s3.8-7 10-7 10 7 10 7-3.8 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    eyeOff: '<path d="M3 3l18 18"/><path d="M10.6 6.2A9.9 9.9 0 0 1 12 6c6.2 0 10 6 10 6a16 16 0 0 1-3.3 3.9M6.4 7.6C3.8 9.3 2 12 2 12s3.8 7 10 7c1.6 0 3-.4 4.3-1"/><path d="M9.9 10a3 3 0 0 0 4.2 4.2"/>',
    close: '<path d="m6 6 12 12M18 6 6 18"/>',
    check: '<path d="m5 13 4.5 4.5L19.5 7"/>',
    alert: '<path d="M12 3.5 22 20H2z"/><path d="M12 10v4.5M12 17.4v.2"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.2v.2"/>',
    calendar: '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
    money: '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M15 9.5c-.6-1-1.7-1.5-3-1.5-1.8 0-3 1-3 2.3 0 3 6 1.5 6 4.4 0 1.4-1.3 2.3-3 2.3-1.4 0-2.6-.6-3.2-1.6"/>',
    chevDown: '<path d="m6 9.5 6 6 6-6"/>',
    chevLeft: '<path d="m14.5 6-6 6 6 6"/>',
    chevRight: '<path d="m9.5 6 6 6-6 6"/>',
    bell: '<path d="M18 9a6 6 0 1 0-12 0c0 6-2.5 7-2.5 7h17S18 15 18 9"/><path d="M13.8 20a2 2 0 0 1-3.6 0"/>',
    refresh: '<path d="M20 11a8 8 0 1 0-.8 4.5"/><path d="M20 4.5V11h-6.5"/>',
    download: '<path d="M12 3v11"/><path d="m7.5 10 4.5 4.5L16.5 10"/><path d="M4 19.5h16"/>',
    filter: '<path d="M3.5 5h17l-6.5 8v6l-4 2v-8z"/>',
    user: '<circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20.5c1-4 4-6 7.5-6s6.5 2 7.5 6"/>',
    shield: '<path d="M12 3 20 6v6c0 5-3.5 8-8 9.5C7.5 20 4 17 4 12V6z"/><path d="m8.8 12 2.2 2.2 4.2-4.4"/>',
    send: '<path d="m3.5 11.5 17-7.5-4.5 17-4.2-6.8z"/><path d="m11.8 14.2 8.7-10.2"/>',
    undo: '<path d="M9 15 4.5 10.5 9 6"/><path d="M4.5 10.5H15a5 5 0 0 1 0 10h-3"/>',
    packageCheck: '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/><path d="m8.5 16 2 2 4-4.5"/>',
    x: '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
  };

  function icon(name, cls) {
    const p = ICON_PATHS[name] || "";
    return '<svg class="' + (cls || "icon") + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + "</svg>";
  }
  OF.icon = icon;

  /* ================= Cấu hình điều hướng ================= */
  function homePageFor(role) {
    if (role === "customer") return "shop.html";
    if (role === "staff") return "orders.html";
    return "index.html";
  }

  const NAV = [
    {
      title: "Cửa hàng",
      items: [
        { href: "shop.html", icon: "box", label: "Cửa hàng", roles: ["admin", "staff", "customer"] },
        { href: "cart.html", icon: "cart", label: "Giỏ hàng", roles: ["admin", "staff", "customer"] },
      ],
    },
    {
      title: "Đơn của tôi",
      items: [
        { href: "orders.html", icon: "clipboard", label: "Đơn của tôi", roles: ["customer"] },
        { href: "returns.html", icon: "ret", label: "Đổi trả của tôi", roles: ["customer"] },
      ],
    },
    {
      title: "Thao tác",
      items: [
        { href: "index.html", icon: "home", label: "Tổng quan", roles: ["admin", "staff"] },
        { href: "orders.html", icon: "clipboard", label: "Quản lý đơn hàng", roles: ["admin", "staff"] },
        { href: "payments.html", icon: "card", label: "Quản lý thanh toán", roles: ["admin", "staff"] },
        { href: "shipments.html", icon: "truck", label: "Quản lý giao hàng", roles: ["admin", "staff"] },
        { href: "returns.html", icon: "ret", label: "Quản lý đổi trả", roles: ["admin", "staff"] },
        { href: "reports.html", icon: "chart", label: "Báo cáo thống kê", roles: ["admin", "staff"] },
      ],
    },
    {
      title: "Quản trị",
      items: [
        { href: "products.html", icon: "box", label: "Quản lý sản phẩm", roles: ["admin", "staff"] },
        { href: "categories.html", icon: "tag", label: "Danh mục", roles: ["admin"] },
      ],
    },
  ];

  function buildPageRoles() {
    const map = {};
    NAV.forEach((g) => g.items.forEach((it) => {
      if (!it.roles) return;
      if (!map[it.href]) map[it.href] = [];
      it.roles.forEach((r) => {
        if (map[it.href].indexOf(r) === -1) map[it.href].push(r);
      });
    }));
    Object.keys(map).forEach((page) => {
      if (map[page].length >= 3) delete map[page];
    });
    map["shipments.html"] = ["admin", "staff"];
    map["payments.html"] = ["admin", "staff"];
    map["shop-item.html"] = ["admin", "staff", "customer"];
    return map;
  }

  /* Trang giới hạn vai trò: nguồn số liệu lấy từ NAV. */
  const PAGE_ROLES = buildPageRoles();

  function allowedRoles(page) {
    return PAGE_ROLES[page] || null;
  }

  function currentRole() {
    return (OF.session.getUser() || {}).role || "";
  }

  function canOpen(page) {
    const allow = allowedRoles(page);
    if (!allow) return true;
    const role = currentRole();
    return !!role && allow.indexOf(role) !== -1;
  }

  /* ================= Sidebar / Topbar ================= */
  function currentPage() {
    const path = window.location.pathname.split("/").pop();
    return path || "index.html";
  }

  function pageTitleFor(file) {
    const role = currentRole();
    for (const g of NAV) {
      for (const it of g.items) {
        if (it.href === file && (!it.roles || it.roles.indexOf(role) !== -1)) return it.label;
      }
    }
    return "OrderFlow";
  }

  function renderSidebar() {
    const el = document.getElementById("app-sidebar");
    if (!el) return;
    const file = currentPage();
    const role = currentRole();
    let html =
      '<div class="sidebar-brand">' +
        '<div class="brand-logo">' + icon("packageCheck") + "</div>" +
        '<div class="brand-text">' +
          '<span class="brand-name">OrderFlow</span>' +
          '<span class="brand-sub">Quản lý đơn hàng</span>' +
        "</div>" +
      "</div>" +
      '<nav class="sidebar-nav">';
    NAV.forEach((g) => {
      const items = g.items.filter((it) => canOpen(it.href) && (!it.roles || it.roles.indexOf(role) !== -1));
      if (!items.length) return;
      html += '<div class="nav-section"><div class="nav-section-title">' + g.title + "</div>";
      items.forEach((it) => {
        const active = it.href === file ? " active" : "";
        html += '<a class="nav-link' + active + '" href="' + it.href + '">' +
          icon(it.icon) + "<span>" + it.label + "</span></a>";
      });
      html += "</div>";
    });
    html += "</nav>" +
      '<div class="sidebar-footer">' +
        '<div class="demo-note">' + icon("info") +
          "<span><strong>Đã kết nối MySQL</strong><br>Dữ liệu lấy từ backend PHP/MySQL qua API (giai đoạn 2).</span>" +
        "</div>" +
      "</div>";
    el.innerHTML = html;
  }

  function initials(name) {
    const parts = String(name || "User").trim().split(/\s+/);
    const last = parts[parts.length - 1] || "U";
    return last.substring(0, 2).toUpperCase();
  }

  const ROLE_LABELS = {
    admin: "Quản trị viên",
    staff: "Nhân viên xử lý đơn",
    customer: "Khách hàng",
  };

  function renderTopbar() {
    const el = document.getElementById("app-topbar");
    if (!el) return;
    const u = OF.session.getUser() || { full_name: "Khách", role: "customer" };
    const file = currentPage();
    const cartLink = u.role === "customer"
      ? '<a class="topbar-cart" id="topbarCart" href="cart.html" title="Giỏ hàng" aria-label="Giỏ hàng">' +
          icon("cart") + '<span class="cart-count hidden" id="cartCount">0</span></a>'
      : "";
    const homeLabel = u.role === "customer" ? "Cửa hàng" : (u.role === "staff" ? "Quản lý đơn hàng" : "Trang tổng quan");
    const homeIcon = u.role === "customer" ? "box" : (u.role === "staff" ? "clipboard" : "home");
    el.innerHTML =
      '<button class="topbar-menu-btn" id="sidebarToggle" aria-label="Mở menu">' + icon("menu") + "</button>" +
      '<div class="topbar-title">' + pageTitleFor(file) + "</div>" +
      '<div class="topbar-spacer"></div>' +
      '<div class="topbar-badge" title="Backend PHP + MySQL"><span class="dot"></span> MySQL kết nối</div>' +
      cartLink +
      '<div class="topbar-user">' +
        '<button class="user-btn" id="userMenuBtn">' +
          '<span class="avatar">' + OF.escapeHtml(initials(u.full_name)) + "</span>" +
          '<span class="user-meta">' +
            '<span class="user-name">' + OF.escapeHtml(u.full_name) + "</span>" +
            '<span class="user-role">' + OF.escapeHtml(ROLE_LABELS[u.role] || u.role) + "</span>" +
          "</span>" +
          icon("chevDown", "icon-sm") +
        "</button>" +
        '<div class="user-menu hidden" id="userMenu">' +
          '<a class="user-menu-item" href="' + homePageFor(u.role) + '">' + icon(homeIcon) +
            OF.escapeHtml(homeLabel) + "</a>" +
          '<div class="user-menu-divider"></div>' +
          '<button class="user-menu-item danger" id="logoutBtn">' + icon("logout") + "Đăng xuất</button>" +
        "</div>";

    const menuBtn = document.getElementById("userMenuBtn");
    const menu = document.getElementById("userMenu");
    menuBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      menu.classList.toggle("hidden");
    });
    document.addEventListener("click", () => menu.classList.add("hidden"));

    document.getElementById("logoutBtn").addEventListener("click", async () => {
      const ok = await confirmDialog({
        title: "Đăng xuất?",
        message: "Bạn sẽ quay lại trang đăng nhập.",
        icon: "info",
        confirmText: "Đăng xuất",
      });
      if (ok) {
        try { await OF.api.post("auth/logout.php"); } catch (e) { /* bỏ qua */ }
        OF.session.clear();
        window.location.href = "login.html";
      }
    });

    const toggle = document.getElementById("sidebarToggle");
    if (toggle) {
      toggle.addEventListener("click", () => document.body.classList.toggle("sidebar-open"));
    }

    if (OF.cart) OF.cart.refreshBadge();
  }

  /* ================= Bảo vệ trang (cần đăng nhập + quyền) ================= */
  function guard() {
    const page = currentPage();
    const isAuthPage = page === "login.html" || page === "register.html";
    if (isAuthPage) return true;
    if (!OF.session.getToken()) {
      window.location.replace("login.html");
      return false;
    }
    if (!canOpen(page)) {
      window.location.replace("403.html?page=" + encodeURIComponent(page));
      return false;
    }
    return true;
  }

  /* ================= Toast ================= */
  const TOAST_ICONS = { success: "check", error: "x", warning: "alert", info: "info" };

  function toast(type, message, title) {
    let root = document.getElementById("toast-root");
    if (!root) {
      root = document.createElement("div");
      root.id = "toast-root";
      root.className = "toast-root";
      document.body.appendChild(root);
    }
    const titles = { success: "Thành công", error: "Thất bại", warning: "Cảnh báo", info: "Thông báo" };
    const el = document.createElement("div");
    el.className = "toast " + type;
    el.innerHTML =
      '<div class="toast-icon">' + icon(TOAST_ICONS[type] || "info") + "</div>" +
      '<div class="toast-content">' +
        '<div class="toast-title">' + OF.escapeHtml(title || titles[type] || "Thông báo") + "</div>" +
        '<div class="toast-msg">' + OF.escapeHtml(message) + "</div>" +
      "</div>" +
      '<button class="toast-close" aria-label="Đóng">' + icon("close") + "</button>";
    const remove = () => {
      el.classList.add("removing");
      setTimeout(() => el.remove(), 190);
    };
    el.querySelector(".toast-close").addEventListener("click", remove);
    root.appendChild(el);
    setTimeout(remove, 3800);
  }

  /* ================= Modal & Confirm ================= */
  function openModal(opts) {
    const overlay = document.createElement("div");
    overlay.className = "modal-overlay";
    const sizeCls = opts.size === "lg" ? " modal-lg" : (opts.size === "md" ? " modal-md" : (opts.size === "sm" ? " modal-sm" : ""));
    const modal = document.createElement("div");
    modal.className = "modal" + sizeCls;
    modal.innerHTML =
      '<div class="modal-header"><h2>' + OF.escapeHtml(opts.title || "Chi tiết") + "</h2>" +
        '<button class="modal-close" data-close>' + icon("close") + "</button></div>" +
      '<div class="modal-body">' + (opts.body || "") + "</div>" +
      (opts.footer ? '<div class="modal-footer">' + opts.footer + "</div>" : "");
    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    const close = () => {
      overlay.remove();
      document.removeEventListener("keydown", onEsc);
    };
    const onEsc = (e) => { if (e.key === "Escape") close(); };
    document.addEventListener("keydown", onEsc);
    overlay.addEventListener("click", (e) => { if (e.target === overlay) close(); });
    modal.querySelectorAll("[data-close]").forEach((b) => b.addEventListener("click", close));

    const api = { el: modal, overlay, close };
    if (typeof opts.onOpen === "function") opts.onOpen(api);
    return api;
  }

  function confirmDialog(opts) {
    return new Promise((resolve) => {
      const tone = opts.danger ? "danger" : (opts.tone || "info");
      const ic = opts.icon || (opts.danger ? "alert" : "info");
      const body =
        '<div class="confirm-body">' +
          '<div class="confirm-icon ' + tone + '">' + icon(ic) + "</div>" +
          '<div class="confirm-text"><h3>' + OF.escapeHtml(opts.title || "Xác nhận") + "</h3>" +
          "<p>" + OF.escapeHtml(opts.message || "") + "</p></div>" +
        "</div>";
      const m = openModal({
        title: "",
        size: "sm",
        body,
        footer:
          '<button class="btn" data-close>' + OF.escapeHtml(opts.cancelText || "Hủy") + "</button>" +
          '<button class="btn ' + (opts.danger ? "btn-danger" : "btn-primary") + '" data-ok>' +
          OF.escapeHtml(opts.confirmText || "Xác nhận") + "</button>",
        onOpen(api) {
          api.el.querySelector(".modal-header").style.display = "none";
          api.el.querySelector("[data-ok]").addEventListener("click", () => { api.close(); resolve(true); });
          api.overlay.addEventListener("click", (e) => { if (e.target === api.overlay) resolve(false); });
        },
      });
      void m;
    });
  }

  /* ================= Phân trang ================= */
  function renderPagination(container, total, page, perPage, onPage) {
    const pages = Math.max(1, Math.ceil(total / perPage));
    const from = total === 0 ? 0 : (page - 1) * perPage + 1;
    const to = Math.min(page * perPage, total);
    let nums = [];
    if (pages <= 7) {
      for (let i = 1; i <= pages; i++) nums.push(i);
    } else {
      nums = [1];
      if (page > 3) nums.push("…");
      for (let i = Math.max(2, page - 1); i <= Math.min(pages - 1, page + 1); i++) nums.push(i);
      if (page < pages - 2) nums.push("…");
      nums.push(pages);
    }
    let html =
      '<div class="pagination-info">Hiển thị <strong>' + from + "–" + to + "</strong> / <strong>" + total + "</strong> bản ghi</div>" +
      '<div class="pagination">' +
        '<button class="page-btn" data-page="' + (page - 1) + '"' + (page <= 1 ? " disabled" : "") + ' aria-label="Trước">' + icon("chevLeft", "icon-sm") + "</button>";
    nums.forEach((n) => {
      if (n === "…") html += '<span class="page-btn ellipsis">…</span>';
      else html += '<button class="page-btn' + (n === page ? " active" : "") + '" data-page="' + n + '">' + n + "</button>";
    });
    html +=
        '<button class="page-btn" data-page="' + (page + 1) + '"' + (page >= pages ? " disabled" : "") + ' aria-label="Sau">' + icon("chevRight", "icon-sm") + "</button>" +
      "</div>";
    container.innerHTML = html;
    container.querySelectorAll("button[data-page]").forEach((b) => {
      b.addEventListener("click", () => {
        const p = Number(b.getAttribute("data-page"));
        if (p >= 1 && p <= pages && p !== page) onPage(p);
      });
    });
  }

  function emptyStateHTML(title, desc) {
    return '<div class="empty-state">' +
      '<div class="empty-state-icon">' + icon("search") + "</div>" +
      "<h3>" + OF.escapeHtml(title || "Không tìm thấy dữ liệu") + "</h3>" +
      "<p>" + OF.escapeHtml(desc || "Hãy thử thay đổi từ khóa hoặc bộ lọc.") + "</p>" +
      "</div>";
  }

  /* ================= Biểu đồ SVG ================= */
  const CHART_COLORS = ["#4f46e5", "#0ea5e9", "#16a34a", "#f59e0b", "#ef4444", "#8b5cf6", "#14b8a6", "#f97316"];

  function barChart(el, data, opts) {
    // data: [{label, value}]
    opts = opts || {};
    if (!data.length) { el.innerHTML = emptyStateHTML("Chưa có dữ liệu biểu đồ"); return; }
    const W = 640, H = opts.height || 240, padL = 46, padR = 14, padT = 18, padB = 34;
    const max = Math.max.apply(null, data.map((d) => d.value).concat([1]));
    const niceMax = niceNumber(max);
    const plotW = W - padL - padR, plotH = H - padT - padB;
    const bw = Math.min(38, (plotW / data.length) * 0.62);
    let bars = "", labels = "", grid = "";
    for (let g = 0; g <= 4; g++) {
      const y = padT + plotH - (plotH * g) / 4;
      const v = (niceMax * g) / 4;
      grid += '<line class="chart-grid-line" x1="' + padL + '" y1="' + y + '" x2="' + (W - padR) + '" y2="' + y + '"/>' +
        '<text class="chart-axis-label" x="' + (padL - 7) + '" y="' + (y + 4) + '" text-anchor="end">' + OF.fmt.moneyShort(v) + "</text>";
    }
    data.forEach((d, i) => {
      const cx = padL + (plotW / data.length) * (i + 0.5);
      const h = (d.value / niceMax) * plotH;
      const y = padT + plotH - h;
      bars += '<rect class="chart-bar" x="' + (cx - bw / 2) + '" y="' + y + '" width="' + bw + '" height="' + Math.max(h, 1.5) + '" rx="6"><title>' +
        OF.escapeHtml(d.label + ": " + OF.fmt.money(d.value)) + "</title></rect>";
      labels += '<text class="chart-bar-label" x="' + cx + '" y="' + (H - 12) + '">' + OF.escapeHtml(d.label) + "</text>";
    });
    el.innerHTML = '<div class="chart-box"><svg viewBox="0 0 ' + W + " " + H + '" role="img" aria-label="Biểu đồ cột">' +
      grid + bars + labels + "</svg></div>";
  }

  function donutChart(el, data) {
    // data: [{label, value, color?}]
    data = data.filter((d) => d.value > 0);
    if (!data.length) { el.innerHTML = emptyStateHTML("Chưa có dữ liệu"); return; }
    const total = data.reduce((s, d) => s + d.value, 0) || 1;
    const size = 220, cx = size / 2, cy = size / 2, R = 86, r = 54;
    let angle = -Math.PI / 2;
    let segs = "";
    data.forEach((d, i) => {
      const frac = d.value / total;
      const a2 = angle + frac * Math.PI * 2;
      segs += donutSeg(cx, cy, R, r, angle, Math.min(a2, angle + Math.PI * 2 - 0.0001), d.color || CHART_COLORS[i % CHART_COLORS.length], d.label + ": " + d.value + " (" + Math.round(frac * 100) + "%)");
      angle = a2;
    });
    let legend = "";
    data.forEach((d, i) => {
      legend += '<span class="legend-item"><span class="legend-dot" style="background:' + (d.color || CHART_COLORS[i % CHART_COLORS.length]) + '"></span>' +
        OF.escapeHtml(d.label) + " (" + d.value + ")</span>";
    });
    el.innerHTML =
      '<div class="chart-box"><svg viewBox="0 0 ' + size + " " + size + '" role="img" aria-label="Biểu đồ vòng">' + segs +
      '<text x="' + cx + '" y="' + (cy - 4) + '" text-anchor="middle" style="font:800 22px var(--font);fill:var(--text)">' + total + "</text>" +
      '<text x="' + cx + '" y="' + (cy + 16) + '" text-anchor="middle" style="font:600 11px var(--font);fill:var(--muted)">Tổng đơn</text>' +
      "</svg></div>" +
      '<div class="chart-legend">' + legend + "</div>";
  }

  function donutSeg(cx, cy, R, r, a1, a2, color, title) {
    const large = a2 - a1 > Math.PI ? 1 : 0;
    const x1 = cx + R * Math.cos(a1), y1 = cy + R * Math.sin(a1);
    const x2 = cx + R * Math.cos(a2), y2 = cy + R * Math.sin(a2);
    const x3 = cx + r * Math.cos(a2), y3 = cy + r * Math.sin(a2);
    const x4 = cx + r * Math.cos(a1), y4 = cy + r * Math.sin(a1);
    const d = "M" + x1 + " " + y1 + "A" + R + " " + R + " 0 " + large + " 1 " + x2 + " " + y2 +
      "L" + x3 + " " + y3 + "A" + r + " " + r + " 0 " + large + " 0 " + x4 + " " + y4 + "Z";
    return '<path class="chart-donut-segment" d="' + d + '" fill="' + color + '"><title>' + OF.escapeHtml(title) + "</title></path>";
  }

  function niceNumber(n) {
    if (n <= 10) return 10;
    const exp = Math.floor(Math.log10(n));
    const f = n / Math.pow(10, exp);
    const nf = f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10;
    return nf * Math.pow(10, exp);
  }

  /* ================= Xuất API ================= */
  OF.ui = {
    toast,
    openModal,
    confirm: confirmDialog,
    renderPagination,
    emptyStateHTML,
    initials,
  };
  OF.charts = { barChart, donutChart, CHART_COLORS };

  /* ================= Giỏ hàng (localStorage theo user) ================= */
  function cartKey() {
    const u = OF.session.getUser() || {};
    return "orderflow.cart." + (u.id != null ? u.id : "guest");
  }

  function cartRead() {
    try {
      const arr = JSON.parse(localStorage.getItem(cartKey()) || "[]");
      return Array.isArray(arr) ? arr.filter((it) => it && Number(it.product_id) > 0) : [];
    } catch (e) {
      return [];
    }
  }

  function cartWrite(items) {
    localStorage.setItem(cartKey(), JSON.stringify(items));
    refreshCartBadge();
  }

  function cartCount() {
    return cartRead().reduce((s, it) => s + Math.max(0, Number(it.quantity) || 0), 0);
  }

  function cartLineKey(productId, variant) {
    const pid = Number(productId);
    const v = variant == null ? "" : String(variant).trim();
    return pid + "|" + v;
  }

  function resolveCartKey(productId, variant) {
    if (typeof productId === "string" && productId.indexOf("|") !== -1) return productId;
    return cartLineKey(productId, variant);
  }

  function cartAdd(productId, qty, variant, product) {
    const pid = Number(productId);
    if (!pid) return;
    let variantValue = variant;
    let snap = product || {};
    // Tương thích lời gọi cũ add(pid, qty, productSnapshot).
    if (variantValue && typeof variantValue === "object") {
      snap = variantValue;
      variantValue = "";
    }
    const variantText = variantValue == null ? "" : String(variantValue).trim();
    const key = cartLineKey(pid, variantText);
    const addQty = Math.max(1, Number(qty) || 1);
    let snapStock = Number(snap.stock);
    // Ưu tiên tồn kho của biến thể đang chọn để giỏ hàng giới hạn đúng.
    if (variantText && Array.isArray(snap.variant_rows)) {
      const row = snap.variant_rows.find((v) => String(v.name) === variantText);
      if (row) {
        const rowStock = Number(row.stock);
        if (Number.isFinite(rowStock)) snapStock = Math.max(0, rowStock);
      }
    }
    const stock = snapStock;
    const max = Number.isFinite(stock) && stock > 0 ? stock : Infinity;
    const items = cartRead();
    const found = items.find((it) => cartLineKey(it.product_id, it.variant) === key);
    if (found) {
      found.quantity = Math.min(max, Math.max(1, Number(found.quantity) || 1) + addQty);
      found.variant = variantText || null;
      if (snap.name) found.name = snap.name;
      if (snap.price != null) found.price = Number(snap.price) || 0;
      if (snap.emoji) found.emoji = snap.emoji;
      if (Number.isFinite(stock)) found.stock = stock;
    } else {
      items.push({
        product_id: pid,
        variant: variantText || null,
        quantity: Math.min(max, addQty),
        name: snap.name || "",
        price: Number(snap.price) || 0,
        emoji: snap.emoji || "📦",
        stock: Number.isFinite(stock) ? stock : null,
      });
    }
    cartWrite(items);
  }

  function cartRemove(productId, variant) {
    const key = resolveCartKey(productId, variant);
    cartWrite(cartRead().filter((it) => cartLineKey(it.product_id, it.variant) !== key));
  }

  function cartSetQty(productId, qty, variant) {
    const key = resolveCartKey(productId, variant);
    const items = cartRead();
    const it = items.find((x) => cartLineKey(x.product_id, x.variant) === key);
    if (!it) return;
    const stock = Number(it.stock);
    const max = Number.isFinite(stock) && stock > 0 ? stock : Infinity;
    it.quantity = Math.max(1, Math.min(max, Number(qty) || 1));
    cartWrite(items);
  }

  function refreshCartBadge() {
    const el = document.getElementById("cartCount");
    if (!el) return;
    const n = cartCount();
    el.textContent = String(n);
    el.classList.toggle("hidden", n === 0);
  }

  OF.cart = {
    key: cartKey,
    read: cartRead,
    write: cartWrite,
    count: cartCount,
    lineKey: cartLineKey,
    add: cartAdd,
    remove: cartRemove,
    setQty: cartSetQty,
    clear: () => cartWrite([]),
    refreshBadge: refreshCartBadge,
  };

  /* ================= Boot ================= */
  document.addEventListener("DOMContentLoaded", function () {
    if (!guard()) return;
    renderSidebar();
    renderTopbar();
    OF.cart.refreshBadge();
    // Xác thực phiên với backend (đồng thời làm mới thông tin user)
    if (OF.session.getToken()) {
      (async function verifySession() {
        const res = await OF.api.get("auth/me.php");
        if (!res.success) {
          OF.session.clear();
          window.location.replace("login.html");
          return;
        }
        OF.session.setUser(res.data);
        // Vai trò lấy từ server là chuẩn mực: chặn lại nếu localStorage bị cũ/lỗi
        if (!canOpen(currentPage())) {
          window.location.replace("403.html?page=" + encodeURIComponent(currentPage()));
          return;
        }
        renderSidebar();
        renderTopbar();
      })();
    }
    // Đóng sidebar khi bấm overlay (mobile)
    let ov = document.querySelector(".sidebar-overlay");
    if (!ov) {
      ov = document.createElement("div");
      ov.className = "sidebar-overlay";
      document.body.appendChild(ov);
    }
    ov.addEventListener("click", () => document.body.classList.remove("sidebar-open"));
    document.querySelectorAll(".nav-link").forEach((a) => {
      a.addEventListener("click", () => document.body.classList.remove("sidebar-open"));
    });
  });
})();
