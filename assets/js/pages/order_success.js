/* ============================================================
   Shopee – order_success.js
   Trang xác nhận sau khi ngân hàng báo tiền vào.
   Hiển thị thông tin khách đã điền + sản phẩm + thanh toán.
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  const root = document.getElementById("orderSuccessRoot");
  const qs = new URLSearchParams(window.location.search);
  let orderId = Number(qs.get("order_id") || 0);
  const orderCode = (qs.get("code") || "").trim();

  const esc = (v) => OF.escapeHtml(v == null ? "" : String(v));
  const money = (v) => OF.fmt.money(Number(v || 0));

  function errorCard(message, detail) {
    root.innerHTML =
      '<div class="card"><div class="card-body" style="padding:32px;text-align:center">' +
        '<div class="qr-result is-warning"><div class="qr-result-icon">' + OF.icon("alert") + "</div>" +
        "<h3>Không hiển thị được đơn hàng</h3><p>" + esc(message) + "</p>" +
        (detail ? '<p class="muted">' + esc(detail) + "</p>" : "") +
        '<a class="btn btn-primary" href="orders.php">Xem đơn hàng của tôi</a></div>' +
      "</div></div>";
  }

  async function resolveOrderId() {
    if (orderId > 0) return orderId;
    if (!orderCode) return 0;
    const res = await OF.api.get(
      "orders/index.php",
      "q=" + encodeURIComponent(orderCode) + "&per_page=50"
    );
    if (!res.success) return 0;
    const found = (res.data.items || []).find(
      (o) => String(o.order_code || "").toLowerCase() === orderCode.toLowerCase()
    );
    return found ? Number(found.id) : 0;
  }

  function paymentSummary(payments) {
    const list = (payments || []).filter((p) => p.method === "bank");
    if (!list.length) return { paid: false, refunded: false, rows: [] };
    return {
      paid: list.some((p) => p.status === "thanh_cong"),
      refunded: list.some((p) => p.status === "hoan_tien"),
      rows: list,
    };
  }

  function render(order, items, payments) {
    const status = String(order.status || "");
    const pay = paymentSummary(payments);
    const isCancelled = status === "da_huy";
    const ok = pay.paid && !isCancelled;
    const heading = isCancelled
      ? "Thanh toán đã ghi nhận – đơn đã hủy"
      : ok
        ? "Đặt hàng thành công"
        : "Đang chờ ngân hàng xác nhận";
    const intro = isCancelled
      ? "Hệ thống đã nhận được khoản chuyển khoản nhưng đơn đã hết hạn hoặc đã hủy. Bộ phận CSKH sẽ xử lý hoàn tiền."
      : ok
        ? "Ngân hàng đã báo tiền vào và đơn hàng đã được xác nhận. Dưới đây là thông tin bạn đã điền khi đặt hàng."
        : "Chúng tôi chưa nhận được thông báo tiền vào từ ngân hàng. Trang sẽ tự cập nhật khi thanh toán hoàn tất.";

    const itemsHTML = (items || []).map((it) =>
      "<tr>" +
        "<td><strong>" + esc(it.product_name) + "</strong>" +
          (it.variant ? '<span class="cell-sub">' + esc(it.variant) + "</span>" : "") +
          (it.sku ? '<span class="cell-sub">SKU: ' + esc(it.sku) + "</span>" : "") +
        "</td>" +
        '<td class="text-right">' + Number(it.quantity || 0) + "</td>" +
        '<td class="text-right">' + money(it.unit_price) + "</td>" +
        '<td class="text-right"><strong>' + money(it.line_total) + "</strong></td>" +
      "</tr>"
    ).join("");

    const payHTML = (pay.rows || []).map((p) =>
      "<tr>" +
        "<td>" + esc(p.payment_code || ("#" + p.id)) + "</td>" +
        "<td>" + money(p.amount) + "</td>" +
        "<td>" + OF.badgeHTML(OF.PAYMENT_STATUS, p.status) + "</td>" +
        "<td>" + esc(p.note || "—") + "</td>" +
      "</tr>"
    ).join("");

    root.innerHTML =
      '<div class="card" style="margin-bottom:16px"><div class="card-body" style="padding:32px 24px">' +
        '<div class="qr-result ' + (ok ? "" : "is-warning") + '">' +
          '<div class="qr-result-icon">' + OF.icon(ok ? "check" : "clock") + "</div>" +
          "<h3>" + esc(heading) + "</h3>" +
          "<p>" + esc(intro) + "</p>" +
          '<div class="qr-result-amount">' + money(order.total_amount) + "</div>" +
          '<div class="detail-grid qr-result-grid">' +
            '<div class="detail-item"><label>Mã đơn hàng</label><span><strong>' + esc(order.order_code) + "</strong></span></div>" +
            '<div class="detail-item"><label>Trạng thái đơn</label><span>' + OF.badgeHTML(OF.ORDER_STATUS, status) + "</span></div>" +
            '<div class="detail-item"><label>Ngày đặt</label><span>' + OF.fmt.dateTime(order.order_date) + "</span></div>" +
            '<div class="detail-item"><label>Thanh toán</label><span>' +
              esc(OF.PAYMENT_METHODS[order.payment_method] || order.payment_method) + "</span></div>" +
          "</div>" +
        "</div>" +
      "</div></div>" +

      '<div class="card" style="margin-bottom:16px">' +
        '<div class="card-header"><div><div class="card-title">Thông tin nhận hàng bạn đã điền</div>' +
          '<p class="card-sub">Thông tin này được lưu cùng đơn hàng.</p></div></div>' +
        '<div class="card-body">' +
          '<div class="detail-grid">' +
            '<div class="detail-item"><label>Người nhận</label><span>' + esc(order.customer_name || "—") + "</span></div>" +
            '<div class="detail-item"><label>Số điện thoại</label><span>' + esc(order.phone || "—") + "</span></div>" +
            '<div class="detail-item" style="grid-column:1/-1"><label>Địa chỉ giao hàng</label><span>' + esc(order.address || "—") + "</span></div>" +
          "</div>" +
        "</div>" +
      "</div>" +

      '<div class="card" style="margin-bottom:16px">' +
        '<div class="card-header"><div><div class="card-title">Sản phẩm trong đơn</div></div></div>' +
        '<div class="card-body flush"><div class="table-wrap"><table class="data-table">' +
          "<thead><tr><th>Sản phẩm</th><th class=\"text-right\">SL</th><th class=\"text-right\">Đơn giá</th><th class=\"text-right\">Thành tiền</th></tr></thead>" +
          "<tbody>" + (itemsHTML || '<tr><td colspan="4">Không có sản phẩm.</td></tr>') + "</tbody>" +
        "</table></div></div>" +
        '<div class="card-body"><div class="order-total-row"><span>Tổng đơn:</span><strong>' + money(order.total_amount) + "</strong></div></div>" +
      "</div>" +

      (payHTML
        ? '<div class="card" style="margin-bottom:16px">' +
            '<div class="card-header"><div><div class="card-title">Giao dịch chuyển khoản</div></div></div>' +
            '<div class="card-body flush"><div class="table-wrap"><table class="data-table">' +
              "<thead><tr><th>Mã giao dịch</th><th>Số tiền</th><th>Trạng thái</th><th>Ghi chú</th></tr></thead>" +
              "<tbody>" + payHTML + "</tbody></table></div></div>" +
          "</div>"
        : "") +

      '<div style="display:flex;gap:10px;flex-wrap:wrap">' +
        '<a class="btn btn-primary" href="orders.php">Xem đơn hàng của tôi</a>' +
        '<a class="btn" href="index.php">Tiếp tục mua sắm</a>' +
        (!ok && !isCancelled && order.payment_method === "bank"
          ? '<a class="btn btn-success" href="payments.php">Thanh toán / kiểm tra trạng thái</a>'
          : "") +
      "</div>";
  }

  async function load() {
    const id = await resolveOrderId();
    if (!id) {
      errorCard(
        "Thiếu hoặc sai mã đơn hàng.",
        "Hãy mở trang này từ màn hình thanh toán QR hoặc mở Đơn hàng của tôi."
      );
      return;
    }
    const res = await OF.api.get("orders/item.php", "id=" + id);
    if (!res.success) {
      errorCard(res.message || "Không tải được đơn hàng.");
      return;
    }
    render(res.data.order || {}, res.data.items || [], res.data.payments || []);
  }

  document.addEventListener("DOMContentLoaded", load);
})();
