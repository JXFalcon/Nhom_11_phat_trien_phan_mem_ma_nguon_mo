/* ============================================================
   Shopee – reports.js (Báo cáo thống kê)
   Giai đoạn 2: dữ liệu từ /api/reports/summary.php (MySQL).
   Xuất CSV: gọi API summary.php?export=csv kèm Bearer token.
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
      statCard("money", "green", "Doanh thu ròng", OF.fmt.money(s.revenue_net ?? s.revenue), Number(s.paid_refunds || 0) > 0 ? "Đã trừ hoàn tiền " + OF.fmt.money(s.paid_refunds) : "Không tính đơn đã hủy") +
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
      da_xac_nhan: "#e03f00",
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
    const colors = { cod: "#0ea5e9", bank: "#e03f00", momo: "#f59e0b" };
    const m = methods || {};
    OF.charts.donutChart(document.getElementById("methodChart"),
      Object.keys(OF.PAYMENT_METHODS).map((k) => ({
        label: k.toUpperCase(),
        value: Number(m[k] || 0),
        color: colors[k],
      })));
  }

  function esc(s) { return OF.escapeHtml(String(s == null ? "" : s)); }

  async function runReconcile() {
    const card = document.getElementById("reconcileCard");
    const body = document.getElementById("reconcileBody");
    const sub = document.getElementById("reconcileSub");
    card.style.display = "";
    body.innerHTML = '<div class="empty-state"><p>Đang chạy đối chiếu...</p></div>';
    const res = await OF.api.get("reports/reconcile.php", "limit=20");
    if (!res.success) { body.innerHTML = OF.ui.emptyStateHTML(res.message || "Đối chiếu thất bại."); return; }
    const d = res.data;

    const invItems = d.inventory.items || [];
    const moneyItems = d.money.items || [];
    const variantItems = (d.variants && d.variants.items) || [];
    const eventItems = (d.payment_events && d.payment_events.items) || [];
    const t = d.money.totals || {};

    sub.innerHTML = "Chạy lúc " + esc(d.generated_at) + (d.ok
      ? " — mọi con số khớp nhau ✔"
      : " — phát hiện lệch, cần kiểm tra!");

    body.innerHTML =
      '<div class="top-product-row strikethrough-none">' +
        '<div><div class="top-product-name">🧮 Tồn kho</div>' +
        '<div class="top-product-sold">Đối chiếu ' + esc(d.inventory.checked) + ' sản phẩm có chuyển kho' +
        (Number(d.inventory.integrity_skipped) > 0 ? ' · ' + esc(d.inventory.integrity_skipped) + ' sản phẩm chưa đủ dữ liệu chuyển kho (bỏ qua)' : '') + '</div></div>' +
        '<span class="' + (d.inventory.ok ? "badge badge-green" : "badge badge-red") + '">' + (d.inventory.ok ? "Khớp" : esc(d.inventory.mismatch_count) + " lệch") + "</span>" +
      "</div>" +
      '<div class="top-product-row">' +
        '<div><div class="top-product-name">🎨 Biến thể</div>' +
        '<div class="top-product-sold">Đối chiếu ' + esc((d.variants && d.variants.checked) || 0) + ' biến thể' +
        (Number((d.variants && d.variants.stock_sum_warning_count) || 0) > 0
          ? ' · ' + esc(d.variants.stock_sum_warning_count) + ' cảnh báo tổng tồn'
          : '') + '</div></div>' +
        '<span class="' + (d.variants && d.variants.ok ? "badge badge-green" : "badge badge-red") + '">' +
        (d.variants && d.variants.ok ? "Khớp" : esc((d.variants && d.variants.issue_count) || 0) + " lỗi") + "</span>" +
      "</div>" +
      '<div class="top-product-row">' +
        '<div><div class="top-product-name">🧾 Nhật ký thanh toán</div>' +
        '<div class="top-product-sold">' + esc((d.payment_events && d.payment_events.accepted_events) || 0) + ' event accepted · ' +
        esc((d.payment_events && d.payment_events.terminal_payments_without_event) || 0) + ' payment kết thúc chưa có event</div></div>' +
        '<span class="' + (d.payment_events && d.payment_events.ok ? "badge badge-green" : "badge badge-red") + '">' +
        (d.payment_events && d.payment_events.ok ? "Khớp" : esc((d.payment_events && d.payment_events.issue_count) || 0) + " lỗi") + "</span>" +
      "</div>" +
      '<div class="top-product-row">' +
        '<div><div class="top-product-name">💰 Tiền mô phỏng</div>' +
        '<div class="top-product-sold">Đã thu ' + OF.fmt.money(t.total_paid) + " · Hoàn " + OF.fmt.money(t.total_refunded) + " · Thu ròng " + OF.fmt.money(t.net_collected) + '</div></div>' +
        '<span class="' + (d.money.ok ? "badge badge-green" : "badge badge-red") + '">' + (d.money.ok ? "Khớp" : esc(d.money.issue_count) + " lỗi") + "</span>" +
      "</div>" +
      '<div class="top-product-row">' +
        '<div><div class="top-product-name">📦 Giữ hàng đang hiệu lực</div>' +
        '<div class="top-product-sold">' + esc(d.reserves.products_with_pending) + " sản phẩm · " + esc(d.reserves.pending_units) + ' đơn vị giữ pending</div></div>' +
      "</div>" +
      ((invItems.length || variantItems.length || eventItems.length || moneyItems.length)
        ? '<div class="data-table-wrap" style="margin-top:10px"><table class="data-table">' +
          "<thead><tr><th>Khu vực</th><th>Chi tiết</th><th>Diễn giải</th></tr></thead><tbody>" +
          invItems.map((x) =>
            "<tr><td>" + esc("Tồn kho #" + x.product_id) + "</td>" +
            "<td>" + esc(x.sku ? x.name : "—") + "</td>" +
            "<td>" + esc(x.issue) + "</td></tr>").join("") +
          variantItems.map((x) =>
            "<tr><td>" + esc(x.variant_id ? "Biến thể #" + x.variant_id : "Biến thể") + "</td>" +
            "<td>" + esc(x.name || x.sku || "—") + "</td><td>" + esc(x.issue) + "</td></tr>").join("") +
          eventItems.map((x) =>
            "<tr><td>" + esc(x.payment_id ? "Payment #" + x.payment_id : "Payment event") + "</td>" +
            "<td>—</td><td>" + esc(x.issue) + "</td></tr>").join("") +
          moneyItems.map((x) =>
            "<tr><td>" + esc(x.order_id ? "Đơn " + x.order_code : "Đổi trả " + (x.return_code || "")) + "</td><td>—</td><td>" + esc(x.issue) + "</td></tr>").join("") +
          "</tbody></table></div>" +
          (invItems.length
            ? '<p style="margin-top:8px" class="stat-delta">Dữ liệu seed có thể thiếu dòng nhập kho khởi điểm. Nếu lệch không do nghiệp vụ, dùng nút bên dưới để hiệu chỉnh số dư.</p>' +
              '<button class="btn btn-outline" data-baseline>Cân bằng baseline tồn kho</button>'
            : "")
        : '<div class="empty-state" style="margin-top:8px"><p>✔ Không ghi nhận lệch lặt vặt nào.</p></div>');

    const issueTotal = Number(d.inventory.mismatch_count || 0)
      + Number((d.variants && d.variants.issue_count) || 0)
      + Number((d.payment_events && d.payment_events.issue_count) || 0)
      + Number(d.money.issue_count || 0);
    OF.ui.toast(d.ok ? "success" : "error",
      d.ok ? "Đối chiếu: tồn kho, biến thể, payment events & tiền đều khớp." : "Đối chiếu phát hiện " + issueTotal + " điểm cần kiểm tra.");
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
    const days = rangeDays ? String(rangeDays) : "all";
    const url = OF.api.base() + "/api/reports/summary.php?export=csv&days=" + encodeURIComponent(days);
    const token = OF.session.getToken();
    const headers = token ? { Authorization: "Bearer " + token } : {};
    let res;
    try {
      res = await fetch(url, { headers, credentials: "include" });
    } catch (e) {
      OF.ui.toast("error", "Không kết nối được máy chủ để xuất CSV.");
      return;
    }
    if (!res.ok) {
      let msg = "Xuất CSV thất bại (HTTP " + res.status + ").";
      try {
        const data = await res.json();
        if (data && data.message) msg = data.message;
      } catch (e) { /* không phải JSON */ }
      OF.ui.toast("error", msg);
      return;
    }
    const blob = await res.blob();
    const cd = res.headers.get("Content-Disposition") || "";
    const match = cd.match(/filename="?([^";]+)"?/i);
    const filename = match ? match[1] : ("bao_cao_don_hang_" + days + ".csv");
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(a.href);
    OF.ui.toast("success", "Đã xuất báo cáo CSV từ máy chủ (kèm token đăng nhập).");
  }

  document.addEventListener("DOMContentLoaded", function () {
    loadSummary();
    document.getElementById("rangeFilter").addEventListener("change", (e) => {
      const v = e.target.value;
      rangeDays = v === "" ? null : Number(v);
      loadSummary();
    });
    document.getElementById("btnExport").addEventListener("click", exportCSV);
    document.getElementById("btnReconcile").addEventListener("click", runReconcile);
    const recBody = document.getElementById("reconcileBody");
    recBody.addEventListener("click", async (e) => {
      const btn = e.target.closest("[data-baseline]");
      if (!btn) return;
      btn.disabled = true;
      const res = await OF.api.post("reports/reconcile.php", { action: "baseline" });
      OF.ui.toast(res.success ? "success" : "error", res.message || (res.success ? "Đã cân bằng baseline." : "Cân bằng thất bại."));
      runReconcile();
    });
  });
})();
