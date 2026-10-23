/* ============================================================
   OrderFlow – payments.js (Thanh toán mô phỏng)
   Giai đoạn 2: API MySQL — server-side idempotency:
   giao dịch đã kết thúc hoặc cùng idempotency_key gửi lại
   không bị đổi trạng thái/số tiền thêm lần nữa.
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  const PER_PAGE = 8;

  let page = 1;
  let keyword = "";
  let statusF = "";
  let methodF = "";
  let rowsCache = [];
  const role = () => (OF.session.getUser() || {}).role || "customer";
  const canManage = () => role() === "admin" || role() === "staff";

  async function loadPayments() {
    const q = new URLSearchParams();
    if (keyword.trim()) q.set("q", keyword.trim());
    if (statusF) q.set("status", statusF);
    if (methodF) q.set("method", methodF);
    q.set("page", String(page));
    q.set("per_page", String(PER_PAGE));
    const res = await OF.api.get("payments/index.php", q.toString());
    if (!res.success) { OF.ui.toast("error", res.message || "Không tải được giao dịch."); return; }
    rowsCache = res.data.items || [];
    render(rowsCache, res.data.total);
  }

  function render(rows, total) {
    const pages = Math.max(1, Math.ceil(total / PER_PAGE));
    if (page > pages) page = pages;
    document.getElementById("resultCount").textContent = total + " giao dịch";
    const tbody = document.getElementById("paymentTableBody");

    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7">' + OF.ui.emptyStateHTML("Không có giao dịch phù hợp", "Thử đổi bộ lọc.") + "</td></tr>";
      document.getElementById("paginationBar").innerHTML = "";
      return;
    }

    tbody.innerHTML = rows.map((p) => {
      const pending = p.status === "cho_xu_ly";
      const actions = pending && canManage()
        ? '<button class="btn btn-sm btn-success" data-ok="' + p.id + '" title="Mô phỏng thanh toán thành công">' +
            OF.icon("check", "icon-sm") + " Thành công</button>" +
          '<button class="btn btn-sm" data-fail="' + p.id + '" title="Mô phỏng thất bại" style="color:var(--danger)">' +
            OF.icon("close", "icon-sm") + " Thất bại</button>"
        : '<button class="btn btn-sm btn-icon" data-info="' + p.id + '" title="' +
            (pending ? "Chờ xử lý" : "Đã kết thúc – gửi lại không đổi thêm") + '">' +
            OF.icon("info", "icon-sm") + "</button>";
      return "<tr>" +
        "<td><strong>" + OF.escapeHtml(p.payment_code) + "</strong>" +
          (p.note ? '<span class="cell-sub">' + OF.escapeHtml(p.note) + "</span>" : "") + "</td>" +
        '<td><span class="order-id-link" data-order="' + p.order_id + '">' + OF.escapeHtml(p.order_code) + "</span></td>" +
        '<td class="text-right"><strong class="txn-amount">' + OF.fmt.money(p.amount) + "</strong></td>" +
        "<td>" + OF.escapeHtml(OF.PAYMENT_METHODS[p.method] || p.method) + "</td>" +
        "<td>" + OF.badgeHTML(OF.PAYMENT_STATUS, p.status) + "</td>" +
        "<td>" + OF.fmt.dateTime(p.processed_at || p.created_at) + "</td>" +
        '<td><div class="cell-actions">' + actions + "</div></td>" +
      "</tr>";
    }).join("");

    tbody.querySelectorAll("[data-ok]").forEach((el) =>
      el.addEventListener("click", () => simulate(Number(el.getAttribute("data-ok")), "thanh_cong")));
    tbody.querySelectorAll("[data-fail]").forEach((el) =>
      el.addEventListener("click", () => simulate(Number(el.getAttribute("data-fail")), "that_bai")));
    tbody.querySelectorAll("[data-info]").forEach((el) =>
      el.addEventListener("click", () => showSettledInfo(Number(el.getAttribute("data-info")))));
    tbody.querySelectorAll("[data-order]").forEach((el) =>
      el.addEventListener("click", () => openOrderInfo(Number(el.getAttribute("data-order")))));

    OF.ui.renderPagination(document.getElementById("paginationBar"), total, page, PER_PAGE, (p) => {
      page = p;
      loadPayments();
    });
  }

  function simulate(id, result) {
    const p = rowsCache.find((x) => x.id === id);
    if (!p) return;
    if (p.status !== "cho_xu_ly") {
      OF.ui.toast("info", "Giao dịch " + p.payment_code + " đã kết thúc (" + OF.PAYMENT_STATUS[p.status].label +
        "). Gửi lại thông báo không làm đổi số lượng/tiền thêm lần nữa.");
      return;
    }
    OF.ui.confirm({
      title: result === "thanh_cong" ? "Mô phỏng thanh toán thành công?" : "Mô phỏng thanh toán thất bại?",
      message: "Giao dịch " + p.payment_code + " · " + OF.fmt.money(p.amount) + " · đơn " + p.order_code + ".",
      tone: result === "thanh_cong" ? "success" : "danger",
      icon: result === "thanh_cong" ? "check" : "alert",
      confirmText: result === "thanh_cong" ? "Xác nhận thành công" : "Đánh dấu thất bại",
      danger: result === "that_bai",
    }).then(async (ok) => {
      if (!ok) return;
      const res = await OF.api.patch("payments/simulate.php", {
        id: p.id,
        result,
        idempotency_key: "IDEM-" + p.id + "-" + Date.now(),
      });
      if (!res.success) { OF.ui.toast("error", res.message || "Gửi thông báo thất bại."); return; }
      const d = res.data;
      if (d.already_settled) {
        OF.ui.toast("info", "Giao dịch đã ở trạng thái “" + OF.PAYMENT_STATUS[d.status].label + "”, bỏ qua để tránh xử lý trùng.");
      } else {
        OF.ui.toast(result === "thanh_cong" ? "success" : "error",
          "Giao dịch " + p.payment_code + " → " + OF.PAYMENT_STATUS[d.status || result].label + (d.order_status ? ". Đơn " + p.order_code + " → " + OF.ORDER_STATUS[d.order_status].label : ""));
      }
      loadPayments();
    });
  }

  function showSettledInfo(id) {
    const p = rowsCache.find((x) => x.id === id);
    if (!p) return;
    OF.ui.openModal({
      title: "Giao dịch " + p.payment_code + (p.status === "cho_xu_ly" ? " (chờ xử lý)" : " đã kết thúc"),
      size: "sm",
      body:
        '<div class="alert-banner alert-info" style="margin-bottom:0">' + OF.icon("shield") +
          "<div><strong>Nguyên tắc idempotency (kiểm tra tại máy chủ)</strong>" +
          "<p>Thông báo thanh toán gửi lại với cùng <strong>idempotency_key</strong> hoặc giao dịch đã kết thúc " +
          "sẽ bị server bỏ qua: không tăng số đếm, không đổi số tiền, không xử lý lần hai.</p></div></div>" +
        '<div class="detail-grid" style="margin-top:14px;margin-bottom:0">' +
          '<div class="detail-item"><label>Số tiền</label><span>' + OF.fmt.money(p.amount) + "</span></div>" +
          '<div class="detail-item"><label>Trạng thái</label><span>' + OF.badgeHTML(OF.PAYMENT_STATUS, p.status) + "</span></div>" +
          '<div class="detail-item"><label>Phương thức</label><span>' + OF.escapeHtml(OF.PAYMENT_METHODS[p.method] || p.method) + "</span></div>" +
          '<div class="detail-item"><label>Thời điểm xử lý</label><span>' + OF.fmt.dateTime(p.processed_at) + "</span></div>" +
        "</div>",
      footer: '<button class="btn" data-close>Đóng</button>',
    });
  }

  async function openOrderInfo(orderId) {
    const res = await OF.api.get("orders/item.php", "id=" + orderId);
    if (!res.success) { OF.ui.toast("error", res.message || "Không tải được đơn hàng."); return; }
    const o = res.data.order;
    OF.ui.openModal({
      title: "Đơn hàng " + o.order_code,
      body:
        '<div class="detail-grid">' +
          '<div class="detail-item"><label>Khách hàng</label><span>' + OF.escapeHtml(o.customer_name) + "</span></div>" +
          '<div class="detail-item"><label>Tổng đơn</label><span>' + OF.fmt.money(o.total_amount) + "</span></div>" +
          '<div class="detail-item"><label>Phương thức</label><span>' + OF.escapeHtml(OF.PAYMENT_METHODS[o.payment_method] || o.payment_method) + "</span></div>" +
          '<div class="detail-item"><label>Trạng thái</label><span>' + OF.badgeHTML(OF.ORDER_STATUS, o.status) + "</span></div>" +
        "</div>",
      footer: '<button class="btn" data-close>Đóng</button><a class="btn btn-primary" href="orders.html">Mở trang đơn hàng</a>',
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get("status")) {
      statusF = qs.get("status");
      const sel = document.getElementById("statusFilter");
      if (sel) sel.value = statusF;
    }
    loadPayments();
    document.getElementById("searchInput").addEventListener("input", (e) => {
      keyword = e.target.value; page = 1; loadPayments();
    });
    document.getElementById("statusFilter").addEventListener("change", (e) => {
      statusF = e.target.value; page = 1; loadPayments();
    });
    document.getElementById("methodFilter").addEventListener("change", (e) => {
      methodF = e.target.value; page = 1; loadPayments();
    });
  });
})();
