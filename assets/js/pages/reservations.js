/* ============================================================
   Shopee – reservations.js (Quản lý giữ hàng)
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  const PER_PAGE = 10;

  let page = 1;
  let keyword = "";
  let statusF = "";
  let rowsCache = [];

  const RESERVATION_STATUS = {
    pending: { label: "Đang giữ", badge: "badge-orange" },
    confirmed: { label: "Đã xác nhận", badge: "badge-green" },
    released: { label: "Đã giải phóng", badge: "badge-gray" },
    expired: { label: "Đã hết hạn", badge: "badge-red" },
  };

  async function loadReservations() {
    const q = new URLSearchParams();
    if (keyword.trim()) q.set("q", keyword.trim());
    if (statusF) q.set("status", statusF);
    q.set("page", String(page));
    q.set("per_page", String(PER_PAGE));
    const res = await OF.api.get("reservations/index.php", q.toString());
    if (!res.success) { OF.ui.toast("error", res.message || "Không tải được phiếu giữ hàng."); return; }
    rowsCache = res.data.items || [];
    render(rowsCache, res.data.total);
  }

  function render(rows, total) {
    const pages = Math.max(1, Math.ceil(total / PER_PAGE));
    if (page > pages) page = pages;
    document.getElementById("resultCount").textContent = total + " phiếu giữ hàng";
    const tbody = document.getElementById("resTableBody");

    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7">' + OF.ui.emptyStateHTML("Không có phiếu giữ hàng phù hợp", "Thử đổi bộ lọc.") + "</td></tr>";
      document.getElementById("paginationBar").innerHTML = "";
      return;
    }

    const statusBadge = {
      pending: 'badge-orange',
      confirmed: 'badge-green',
      released: 'badge-gray',
      expired: 'badge-red',
    };

    tbody.innerHTML = rows.map((r) => {
      const expiredSoon = r.status === 'pending' && new Date(r.reserved_until) < new Date(Date.now() + 5 * 60 * 1000);
      const actions = r.status === 'pending'
        ? '<button class="btn btn-sm btn-success" data-confirm="' + r.id + '">' + OF.icon("check", "icon-sm") + " Xác nhận" + '</button>' +
          '<button class="btn btn-sm" data-release="' + r.id + '" style="color:var(--danger)">' + OF.icon("close", "icon-sm") + " Giải phóng" + '</button>' +
          '<button class="btn btn-sm" data-expire="' + r.id + '" style="color:#b45309">' + OF.icon("clock", "icon-sm") + " Hết hạn" + '</button>'
        : '<button class="btn btn-sm btn-icon" data-info="' + r.id + '" title="Xem chi tiết">' + OF.icon("info", "icon-sm") + "</button>";
      return "<tr>" +
        "<td><strong>" + OF.escapeHtml(r.reservation_code) + "</strong></td>" +
        "<td>" + OF.escapeHtml(r.product_name) + (r.variant ? ' <span class="muted">(' + OF.escapeHtml(r.variant) + ")</span>" : "") + "</td>" +
        '<td class="text-right">' + r.quantity + "</td>" +
        '<td class="text-right">' + OF.fmt.money(r.unit_price) + "</td>" +
        "<td>" + OF.badgeHTML(RESERVATION_STATUS, r.status) + "</td>" +
        "<td>" + OF.fmt.dateTime(r.reserved_until) + (expiredSoon ? ' <span class="muted">(sắp hết)</span>' : "") + "</td>" +
        '<td><div class="cell-actions">' + actions + "</div></td>" +
      "</tr>";
    }).join("");

    tbody.querySelectorAll("[data-confirm]").forEach((el) =>
      el.addEventListener("click", () => changeStatus(Number(el.getAttribute("data-confirm")), "confirmed")));
    tbody.querySelectorAll("[data-release]").forEach((el) =>
      el.addEventListener("click", () => changeStatus(Number(el.getAttribute("data-release")), "released")));
    tbody.querySelectorAll("[data-expire]").forEach((el) =>
      el.addEventListener("click", () => changeStatus(Number(el.getAttribute("data-expire")), "expired")));
    tbody.querySelectorAll("[data-info]").forEach((el) =>
      el.addEventListener("click", () => showInfo(Number(el.getAttribute("data-info")))));

    OF.ui.renderPagination(document.getElementById("paginationBar"), total, page, PER_PAGE, (p) => {
      page = p;
      loadReservations();
    });
  }

  async function changeStatus(id, newStatus) {
    const r = rowsCache.find((x) => x.id === id);
    if (!r) return;
    const label = { confirmed: "Xác nhận giữ hàng", released: "Giải phóng giữ hàng", expired: "Đánh dấu hết hạn" }[newStatus];
    OF.ui.confirm({
      title: label + "?",
      message: "Mã " + r.reservation_code + " · " + r.product_name + " · SL " + r.quantity + ".",
      tone: newStatus === "expired" ? "danger" : "primary",
      confirmText: label,
    }).then(async (ok) => {
      if (!ok) return;
      const res = await OF.api.put("reservations/item.php?id=" + id, { status: newStatus, note: "Cập nhật từ giao diện quản lý" });
      if (!res.success) { OF.ui.toast("error", res.message || "Cập nhật thất bại."); return; }
      OF.ui.toast("success", "Đã " + label.toLowerCase() + ".");
      loadReservations();
    });
  }

  function showInfo(id) {
    const r = rowsCache.find((x) => x.id === id);
    if (!r) return;
    OF.ui.openModal({
      title: "Chi tiết " + r.reservation_code,
      body:
        '<div class="detail-grid">' +
        '<div class="detail-item"><label>Sản phẩm</label><span>' + OF.escapeHtml(r.product_name) + "</span></div>" +
        '<div class="detail-item"><label>Biến thể</label><span>' + OF.escapeHtml(r.variant || "—") + "</span></div>" +
        '<div class="detail-item"><label>Số lượng</label><span>' + r.quantity + "</span></div>" +
        '<div class="detail-item"><label>Đơn giá</label><span>' + OF.fmt.money(r.unit_price) + "</span></div>" +
        '<div class="detail-item"><label>Trạng thái</label><span>' + OF.badgeHTML(RESERVATION_STATUS, r.status) + "</span></div>" +
        '<div class="detail-item"><label>Hết hạn vào</label><span>' + OF.fmt.dateTime(r.reserved_until) + "</span></div>" +
        (r.order_code ? '<div class="detail-item"><label>Đơn hàng</label><span>' + OF.escapeHtml(r.order_code) + "</span></div>" : "") +
        '</div>',
      footer: '<button class="btn" data-close>Đóng</button>',
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get("status")) {
      statusF = qs.get("status");
      const sel = document.getElementById("statusFilter");
      if (sel) sel.value = statusF;
    }
    loadReservations();
    document.getElementById("searchInput").addEventListener("input", (e) => {
      keyword = e.target.value; page = 1; loadReservations();
    });
    document.getElementById("statusFilter").addEventListener("change", (e) => {
      statusF = e.target.value; page = 1; loadReservations();
    });
  });
})();
