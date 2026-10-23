/* ============================================================
   OrderFlow – reports.js (Báo cáo thống kê)
   Giai đoạn 2: dữ liệu từ /api/reports/summary.php (MySQL).
   Xuất CSV: tải danh sách đơn theo khoảng ngày từ API.
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;

  let rangeDays = 30;
  let summary = null;

  function statCard(iconName, tone, label, value, note) {
    return '<div class="stat-card">' +
      '<div class="stat-icon ' + tone + '">' + OF.icon(iconName) + "</div>" +
      '<div class="stat-info">' +
        '<div class="stat-label">' + OF.escapeHtml(label) + "</div>" +
        '<div class="stat-value">' + value + "</div>" +
        (note ? '<div class="stat-delta flat">' + OF.escapeHtml(note) + "</div>" : "") +
      "</div></div>";
  }

  function renderStats(s) {
    const total = Number(s.total_orders || 0);
    const delivered = Number(s.da_giao || 0);
    const cancelled = Number(s.da_huy || 0);
    const returnRate = Number(s.return_rate || 0);
    const nonCancelled = total - cancelled;
    const avg = nonCancelled > 0 ? Number(s.revenue) / nonCancelled : 0;

    document.getElementById("statGrid").innerHTML =
      statCard("money", "green", "Doanh thu", OF.fmt.money(s.revenue), "Không tính đơn đã hủy") +
      statCard("clipboard", "indigo", "Tổng đơn hàng", total.toLocaleString("vi-VN"), "Trong khoảng chọn") +
      statCard("packageCheck", "blue", "Đơn đã giao", delivered.toLocaleString("vi-VN"), "Tỷ lệ " + (total ? Math.round(delivered / total * 100) : 0) + "% tổng đơn") +
      statCard("close", "red", "Đơn đã hủy", cancelled.toLocaleString("vi-VN"), (total ? Math.round(cancelled / total * 100) : 0) + "% tổng đơn") +
      statCard("ret", "purple", "Tỷ lệ đổi trả", returnRate.toFixed(1) + "%", Number(s.total_returns || 0) + " yêu cầu đổi trả") +
      statCard("chart", "orange", "Giá trị đơn trung bình", OF.fmt.money(avg), "Đơn không hủy");
  }

  function renderRevenueChart(daily) {
    const data = (daily || []).map((d) => ({ label: d.label, value: Math.round(d.value / 1e5) * 1e5 }));
    OF.charts.barChart(document.getElementById("revenueChart"), data, { height: 230 });
    const span = (daily || []).length;
    document.getElementById("revenueSub").textContent =
      "Biểu đồ " + span + " ngày gần nhất · không tính đơn hủy";
  }

  function renderStatusDonut(statusCounts) {
    const colors = {
      cho_xac_nhan: "#f59e0b",
      da_xac_nhan: "#4f46e5",
      dang_giao: "#0ea5e9",
      da_giao: "#16a34a",
      da_huy: "#ef4444",
    };
    const counts = statusCounts || {};
    OF.charts.donutChart(document.getElementById("statusDonut"),
      Object.keys(OF.ORDER_STATUS).map((k) => ({
        label: OF.ORDER_STATUS[k].label, value: Number(counts[k] || 0), color: colors[k],
      })));
  }

  function renderTopProducts(top) {
    const el = document.getElementById("topProducts");
    if (!top || !top.length) {
      el.innerHTML = OF.ui.emptyStateHTML("Chưa có dữ liệu bán hàng");
      return;
    }
    const max = top[0].revenue || 1;
    el.innerHTML = top.map((t, i) =>
      '<div class="top-product-row">' +
        '<span class="top-product-rank r' + (i + 1) + '">' + (i + 1) + "</span>" +
        '<div class="top-product-info">' +
          '<div class="top-product-name">' + OF.escapeHtml(t.emoji || "📦") + " " + OF.escapeHtml(t.name) + "</div>" +
          '<div class="top-product-sold">' + t.qty + " sản phẩm đã bán</div>" +
          '<div class="progress" style="margin-top:5px"><div class="progress-fill" style="width:' + Math.round(t.revenue / max * 100) + '%"></div></div>' +
        "</div>" +
        '<div class="top-product-revenue">' + OF.fmt.money(t.revenue) + "</div>" +
      "</div>"
    ).join("");
  }

  function renderMethodChart(methods) {
    const colors = { cod: "#0ea5e9", bank: "#4f46e5", momo: "#f59e0b" };
    const m = methods || {};
    OF.charts.donutChart(document.getElementById("methodChart"),
      Object.keys(OF.PAYMENT_METHODS).map((k) => ({
        label: k.toUpperCase(),
        value: Number(m[k] || 0),
        color: colors[k],
      })));
  }

  async function loadSummary() {
    const days = rangeDays ? String(rangeDays) : "all";
    const res = await OF.api.get("reports/summary.php", "days=" + days + "&view=reports");
    if (!res.success) { OF.ui.toast("error", res.message || "Không tải được báo cáo."); return; }
    summary = res.data;
    renderStats(summary.stats);
    renderRevenueChart(summary.revenue_daily);
    renderStatusDonut(summary.status_counts);
    renderTopProducts(summary.top_products);
    renderMethodChart(summary.payment_methods);
  }

  async function exportCSV() {
    const from = rangeDays ? OF.fmt.dateInput(new Date(Date.now() - rangeDays * 86400000).toISOString()) : "";
    const to = OF.fmt.dateInput(new Date().toISOString());
    const q = new URLSearchParams();
    if (from) q.set("from", from);
    if (to) q.set("to", to);
    q.set("per_page", "50");
    q.set("page", "1");

    // Tải tối đa 5 trang (500 đơn) cho tệp CSV
    const all = [];
    for (let p = 1; p <= 5; p++) {
      q.set("page", String(p));
      const res = await OF.api.get("orders/index.php", q.toString());
      if (!res.success) { OF.ui.toast("error", res.message || "Không tải được dữ liệu xuất."); return; }
      all.push(...(res.data.items || []));
      if (all.length >= res.data.total) break;
    }

    const header = ["Ma don", "Khach hang", "Ngay dat", "Tong tien", "Phuong thuc", "Trang thai"];
    const lines = [header.join(",")];
    all.forEach((o) => {
      lines.push([
        o.order_code,
        '"' + String(o.customer_name).replace(/"/g, '""') + '"',
        OF.fmt.date(o.order_date),
        o.total_amount,
        o.payment_method,
        OF.ORDER_STATUS[o.status] ? OF.ORDER_STATUS[o.status].label : o.status,
      ].join(","));
    });
    const csv = "\uFEFF" + lines.join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "orderflow-bao-cao-" + to + ".csv";
    document.body.appendChild(a);
    a.click();
    a.remove();
    OF.ui.toast("success", "Đã xuất " + all.length + " dòng đơn hàng ra tệp CSV (chỉ trên máy bạn).");
  }

  document.addEventListener("DOMContentLoaded", function () {
    loadSummary();
    document.getElementById("rangeFilter").addEventListener("change", (e) => {
      const v = e.target.value;
      rangeDays = v === "" ? null : Number(v);
      loadSummary();
    });
    document.getElementById("btnExport").addEventListener("click", exportCSV);
  });
})();
