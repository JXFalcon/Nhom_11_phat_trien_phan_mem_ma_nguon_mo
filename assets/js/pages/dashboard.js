/* ============================================================
   Shopee – dashboard.js (trang Dashboard, dùng design system chung)
   Dữ liệu từ /api/reports/summary.php (MySQL).
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;

  function statCard(iconName, tone, label, value) {
    return '<div class="stat-card">' +
      '<div class="stat-icon ' + (tone || "indigo") + '">' + OF.icon(iconName) + "</div>" +
      '<div class="stat-info">' +
        '<p class="stat-label">' + OF.escapeHtml(label) + "</p>" +
        '<p class="stat-value">' + value + "</p>" +
      "</div>" +
    "</div>";
  }

  function renderStats(s) {
    document.getElementById("statGrid").innerHTML =
      statCard("clipboard", "indigo", "Tổng đơn hàng", Number(s.total_orders || 0).toLocaleString("vi-VN")) +
      statCard("money", "green", "Doanh thu (chưa hủy)", OF.fmt.money(s.revenue)) +
      statCard("clock", "orange", "Chờ xác nhận", Number(s.cho_xac_nhan || 0).toLocaleString("vi-VN")) +
      statCard("truck", "blue", "Đang giao", Number(s.dang_giao || 0).toLocaleString("vi-VN")) +
      statCard("ret", "purple", "Yêu cầu đổi trả", Number(s.total_returns || 0).toLocaleString("vi-VN")) +
      statCard("alert", "red", "Chờ xử lý đổi trả", Number(s.pending_returns || 0).toLocaleString("vi-VN"));
  }

  function renderRevenueChart(daily) {
    const el = document.getElementById("revenueChart");
    const data = (daily || []).map((d) => ({ label: d.label, value: Math.round(d.value / 1e5) * 1e5 }));
    OF.charts.barChart(el, data, { height: 230 });
  }

  function renderStatusDonut(statusCounts) {
    const colors = {
      cho_xac_nhan: "#f59e0b",
      da_xac_nhan: "#e03f00",
      dang_giao: "#0ea5e9",
      da_giao: "#16a34a",
      da_huy: "#ef4444",
    };
    const counts = statusCounts || {};
    const data = Object.keys(OF.ORDER_STATUS).map((k) => ({
      label: OF.ORDER_STATUS[k].label,
      value: Number(counts[k] || 0),
      color: colors[k],
    }));
    OF.charts.donutChart(document.getElementById("statusDonut"), data);
  }

  function renderRecentOrders(rows) {
    const tbody = document.getElementById("recentOrdersBody");
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="5">' + OF.ui.emptyStateHTML("Chưa có đơn hàng") + "</td></tr>";
      return;
    }
    tbody.innerHTML = rows.map((o) =>
      "<tr>" +
        '<td><span class="order-id-link" data-order-id="' + o.id + '">' + OF.escapeHtml(o.order_code) + "</span></td>" +
        "<td>" + OF.escapeHtml(o.customer_name) + "</td>" +
        '<td class="muted">' + OF.fmt.date(o.order_date) + "</td>" +
        '<td style="text-align:right;font-weight:700">' + OF.fmt.money(o.total_amount) + "</td>" +
        "<td>" + OF.badgeHTML(OF.ORDER_STATUS, o.status) + "</td>" +
      "</tr>"
    ).join("");
    tbody.querySelectorAll("[data-order-id]").forEach((el) => {
      el.addEventListener("click", () => quickOrderDetail(Number(el.getAttribute("data-order-id"))));
    });
  }

  async function quickOrderDetail(orderId) {
    const res = await OF.api.get("orders/item.php", "id=" + orderId);
    if (!res.success) { OF.ui.toast("error", res.message || "Không tải được đơn hàng."); return; }
    const o = res.data.order;
    const itemsHTML = (res.data.items || []).map((it) =>
      "<tr><td>" + OF.escapeHtml(it.product_name) + (it.variant ? ' <span class="muted">(' + OF.escapeHtml(it.variant) + ")</span>" : "") + "</td>" +
      '<td style="text-align:right">' + it.quantity + "</td>" +
      '<td style="text-align:right">' + OF.fmt.money(it.unit_price) + "</td>" +
      '<td style="text-align:right"><strong>' + OF.fmt.money(it.line_total) + "</strong></td></tr>"
    ).join("");
    OF.ui.openModal({
      title: "Chi tiết đơn " + o.order_code,
      size: "md",
      body:
        '<div class="detail-grid">' +
          '<div class="detail-item"><label>Khách hàng</label><span>' + OF.escapeHtml(o.customer_name) + "</span></div>" +
          '<div class="detail-item"><label>Số điện thoại</label><span>' + OF.escapeHtml(o.phone || "—") + "</span></div>" +
          '<div class="detail-item"><label>Ngày đặt</label><span>' + OF.fmt.dateTime(o.order_date) + "</span></div>" +
          '<div class="detail-item"><label>Trạng thái</label><span>' + OF.badgeHTML(OF.ORDER_STATUS, o.status) + "</span></div>" +
        "</div>" +
        '<table class="order-items-table"><thead><tr><th>Sản phẩm</th><th style="text-align:right">SL</th><th style="text-align:right">Đơn giá</th><th style="text-align:right">Thành tiền</th></tr></thead>' +
        "<tbody>" + itemsHTML + "</tbody></table>" +
        '<div class="order-total-row"><span>Tổng đơn:</span><strong>' + OF.fmt.money(o.total_amount) + "</strong></div>",
      footer: '<button class="btn" data-close>Đóng</button>',
    });
  }

  async function renderPendingList(s) {
    const [shipRes, payRes] = await Promise.all([
      OF.api.get("shipments/index.php", "status=that_bai&per_page=1"),
      OF.api.get("payments/index.php", "status=cho_xu_ly&per_page=1"),
    ]);
    const failedShip = shipRes.success ? shipRes.data.total : 0;
    const pendingPay = payRes.success ? payRes.data.total : 0;
    const rows = [
      { label: "Đơn chờ xác nhận", count: Number(s.cho_xac_nhan || 0), href: "orders.html?status=cho_xac_nhan", tone: "var(--warning)" },
      { label: "Yêu cầu đổi trả chờ xử lý", count: Number(s.pending_returns || 0), href: "returns.html?status=cho_xu_ly", tone: "var(--purple)" },
      { label: "Đơn đang giao", count: Number(s.dang_giao || 0), href: "orders.html?status=dang_giao", tone: "var(--info)" },
      { label: "Giao hàng thất bại", count: failedShip, href: "shipments.html?status=that_bai", tone: "var(--danger)" },
      { label: "Giao dịch thanh toán chờ xử lý", count: pendingPay, href: "payments.html?status=cho_xu_ly", tone: "var(--muted)" },
    ];
    document.getElementById("pendingList").innerHTML = rows.map((r) =>
      '<div class="quick-row">' +
        '<div class="quick-row-left"><span style="width:9px;height:9px;border-radius:50%;background:' + r.tone + ';flex:none"></span>' +
          "<span>" + OF.escapeHtml(r.label) + "</span></div>" +
        '<div style="display:flex;align-items:center;gap:12px;flex:none">' +
          '<span class="quick-row-count">' + r.count + "</span>" +
          '<a href="' + r.href + '" style="font-size:12.5px;font-weight:600">Mở</a>' +
        "</div>" +
      "</div>"
    ).join("");
  }

  async function load() {
    const res = await OF.api.get("reports/summary.php", "view=dashboard&days=all");
    if (!res.success) {
      OF.ui.toast("error", res.message || "Không tải được số liệu dashboard.");
      return;
    }
    const d = res.data;
    renderStats(d.stats);
    renderRevenueChart(d.revenue_daily);
    renderStatusDonut(d.status_counts);
    renderRecentOrders(d.recent_orders);
    renderPendingList(d.stats);
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("btnRefresh").addEventListener("click", load);
    load();
  });
})();
