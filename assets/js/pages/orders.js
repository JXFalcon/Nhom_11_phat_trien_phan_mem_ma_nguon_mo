/* ============================================================
   OrderFlow – orders.js (Quản lý đơn hàng)
   Giai đoạn 2: list/chi tiết/đổi trạng thái/tạo đơn qua API MySQL.
   Tạo đơn: transaction phía server → chặn tranh hàng, trừ tồn thật.
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  const PER_PAGE = 8;

  let page = 1;
  let keyword = "";
  let statusF = "";
  let dateFrom = "";
  let dateTo = "";
  let ordersCache = [];
  const role = () => (OF.session.getUser() || {}).role || "customer";
  const canManage = () => role() === "admin" || role() === "staff";

  /* Vòng đời đơn (hiển thị lựa chọn; server là nguồn quyết định) */
  const NEXT_STATUS = {
    cho_xac_nhan: ["da_xac_nhan", "da_huy"],
    da_xac_nhan: ["dang_giao", "da_huy"],
    dang_giao: ["da_giao"],
    da_giao: [],
    da_huy: [],
  };

  async function loadOrders() {
    const q = new URLSearchParams();
    if (keyword.trim()) q.set("q", keyword.trim());
    if (statusF) q.set("status", statusF);
    if (dateFrom) q.set("from", dateFrom);
    if (dateTo) q.set("to", dateTo);
    q.set("page", String(page));
    q.set("per_page", String(PER_PAGE));
    const res = await OF.api.get("orders/index.php", q.toString());
    if (!res.success) { OF.ui.toast("error", res.message || "Không tải được danh sách đơn."); return; }
    ordersCache = res.data.items || [];
    render(ordersCache, res.data.total);
  }

  function render(rows, total) {
    const pages = Math.max(1, Math.ceil(total / PER_PAGE));
    if (page > pages) page = pages;
    document.getElementById("resultCount").textContent = total + " đơn hàng";
    const tbody = document.getElementById("orderTableBody");

    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7">' + OF.ui.emptyStateHTML("Không tìm thấy đơn hàng", "Hãy thử đổi từ khóa, trạng thái hoặc khoảng ngày.") + "</td></tr>";
      document.getElementById("paginationBar").innerHTML = "";
      return;
    }

    tbody.innerHTML = rows.map((o) =>
      "<tr>" +
        '<td><span class="order-id-link" data-view="' + o.id + '">' + OF.escapeHtml(o.order_code) + "</span></td>" +
        '<td>' + OF.escapeHtml(o.customer_name) + '<span class="cell-sub">' + OF.escapeHtml(o.phone || "—") + "</span></td>" +
        "<td>" + OF.fmt.date(o.order_date) + "</td>" +
        '<td class="text-right"><strong>' + OF.fmt.money(o.total_amount) + "</strong></td>" +
        "<td>" + OF.escapeHtml(OF.PAYMENT_METHODS[o.payment_method] || o.payment_method).split(" ")[0] + "</td>" +
        "<td>" + OF.badgeHTML(OF.ORDER_STATUS, o.status) + "</td>" +
        '<td><div class="cell-actions">' +
          '<button class="btn btn-sm btn-icon" data-view="' + o.id + '" title="Xem chi tiết">' + OF.icon("eye", "icon-sm") + "</button>" +
          (canManage() && NEXT_STATUS[o.status] && NEXT_STATUS[o.status].length
            ? '<button class="btn btn-sm btn-icon" data-status="' + o.id + '" title="Cập nhật trạng thái">' + OF.icon("refresh", "icon-sm") + "</button>"
            : "") +
          (canManage() && o.status !== "da_huy" && o.status !== "da_giao"
            ? '<button class="btn btn-sm btn-icon" data-cancel="' + o.id + '" title="Hủy đơn" style="color:var(--danger)">' + OF.icon("close", "icon-sm") + "</button>"
            : "") +
        "</div></td>" +
      "</tr>"
    ).join("");

    tbody.querySelectorAll("[data-view]").forEach((el) =>
      el.addEventListener("click", () => openDetail(Number(el.getAttribute("data-view")))));
    tbody.querySelectorAll("[data-status]").forEach((el) =>
      el.addEventListener("click", () => openStatusModal(Number(el.getAttribute("data-status")))));
    tbody.querySelectorAll("[data-cancel]").forEach((el) =>
      el.addEventListener("click", () => cancelOrder(Number(el.getAttribute("data-cancel")))));

    OF.ui.renderPagination(document.getElementById("paginationBar"), total, page, PER_PAGE, (p) => {
      page = p;
      loadOrders();
    });
  }

  /* ---------- Chi tiết đơn ---------- */
  async function openDetail(orderId) {
    const res = await OF.api.get("orders/item.php", "id=" + orderId);
    if (!res.success) { OF.ui.toast("error", res.message || "Không tải được chi tiết đơn."); return; }
    const o = res.data.order;
    const items = res.data.items || [];
    const payments = res.data.payments || [];
    const shipments = res.data.shipments || [];
    const returns = res.data.returns || [];
    const history = res.data.history || [];
    const ship = shipments[0] || null;

    const itemsHTML = items.map((it) =>
      "<tr><td>" + OF.escapeHtml(it.product_name) +
        (it.variant ? ' <span class="muted">(' + OF.escapeHtml(it.variant) + ")</span>" : "") + "</td>" +
      '<td class="text-right">' + it.quantity + "</td>" +
      '<td class="text-right">' + OF.fmt.money(it.unit_price) + "</td>" +
      '<td class="text-right"><strong>' + OF.fmt.money(it.line_total) + "</strong></td></tr>"
    ).join("");

    const payHTML = payments.map((p) =>
      '<tr><td>' + OF.escapeHtml(p.payment_code || p.id) + "</td><td>" + OF.escapeHtml(OF.PAYMENT_METHODS[p.method] || p.method) + "</td>" +
      '<td class="text-right">' + OF.fmt.money(p.amount) + "</td><td>" + OF.badgeHTML(OF.PAYMENT_STATUS, p.status) + "</td>" +
      "<td>" + OF.fmt.dateTime(p.created_at) + "</td></tr>"
    ).join("");

    const historyHTML = history.map((h) =>
      '<tr><td>' + OF.fmt.dateTime(h.created_at) + "</td>" +
      "<td>" + (h.from_status ? OF.escapeHtml(OF.ORDER_STATUS[h.from_status] ? OF.ORDER_STATUS[h.from_status].label : h.from_status) : "—") + " → " +
      OF.escapeHtml(OF.ORDER_STATUS[h.to_status] ? OF.ORDER_STATUS[h.to_status].label : h.to_status) + "</td>" +
      '<td><span class="text-2">' + OF.escapeHtml(h.note || "—") + "</span></td></tr>"
    ).join("");

    const returnsHTML = returns.map((r) =>
      '<tr><td>' + OF.escapeHtml(r.return_code) + "</td>" +
      '<td><span class="text-2">' + OF.escapeHtml(r.reason) + "</span></td>" +
      "<td>" + OF.badgeHTML(OF.RETURN_STATUS, r.status) + "</td>" +
      '<td class="text-right">' + OF.fmt.money(r.refund_total) + "</td></tr>"
    ).join("");

    OF.ui.openModal({
      title: "Chi tiết đơn hàng " + o.order_code,
      size: "lg",
      body:
        '<div class="detail-grid">' +
          '<div class="detail-item"><label>Khách hàng</label><span>' + OF.escapeHtml(o.customer_name) + "</span></div>" +
          '<div class="detail-item"><label>Số điện thoại</label><span>' + OF.escapeHtml(o.phone || "—") + "</span></div>" +
          '<div class="detail-item"><label>Địa chỉ</label><span>' + OF.escapeHtml(o.address || "—") + "</span></div>" +
          '<div class="detail-item"><label>Ngày đặt</label><span>' + OF.fmt.dateTime(o.order_date) + "</span></div>" +
          '<div class="detail-item"><label>Phương thức TT</label><span>' + OF.escapeHtml(OF.PAYMENT_METHODS[o.payment_method] || o.payment_method) + "</span></div>" +
          '<div class="detail-item"><label>Trạng thái đơn</label><span>' + OF.badgeHTML(OF.ORDER_STATUS, o.status) + "</span></div>" +
          '<div class="detail-item"><label>Giao hàng</label><span>' +
            (ship ? OF.escapeHtml(ship.carrier) + " · " + OF.badgeHTML(OF.SHIPMENT_STATUS, ship.status) : "Chưa có") + "</span></div>" +
          '<div class="detail-item"><label>Mã vận đơn</label><span>' + (ship ? OF.escapeHtml(ship.tracking_code || ship.shipment_code) : "—") + "</span></div>" +
        "</div>" +
        '<h3 style="margin-bottom:8px">Sản phẩm trong đơn</h3>' +
        '<table class="order-items-table"><thead><tr><th>Sản phẩm</th><th class="text-right">SL</th><th class="text-right">Đơn giá</th><th class="text-right">Thành tiền</th></tr></thead>' +
        "<tbody>" + itemsHTML + "</tbody></table>" +
        '<div class="order-total-row"><span>Tổng đơn:</span><strong>' + OF.fmt.money(o.total_amount) + "</strong></div>" +
        '<h3 style="margin:18px 0 8px">Thanh toán</h3>' +
        (payHTML
          ? '<div class="table-wrap"><table class="order-items-table"><thead><tr><th>Mã GD</th><th>Phương thức</th><th class="text-right">Số tiền</th><th>Trạng thái</th><th>Thời điểm</th></tr></thead><tbody>' + payHTML + "</tbody></table></div>"
          : '<p class="muted">Chưa có giao dịch thanh toán.</p>') +
        (returnsHTML
          ? '<h3 style="margin:18px 0 8px">Yêu cầu đổi trả</h3>' +
            '<div class="table-wrap"><table class="order-items-table"><thead><tr><th>Mã</th><th>Lý do</th><th>Trạng thái</th><th class="text-right">Hoàn tiền</th></tr></thead><tbody>' + returnsHTML + "</tbody></table></div>"
          : "") +
        (historyHTML
          ? '<h3 style="margin:18px 0 8px">Lịch sử trạng thái</h3>' +
            '<div class="table-wrap"><table class="order-items-table"><thead><tr><th>Thời điểm</th><th>Chuyển</th><th>Ghi chú</th></tr></thead><tbody>' + historyHTML + "</tbody></table></div>"
          : ""),
      footer: '<button class="btn" data-close>Đóng</button>',
    });
  }

  /* ---------- Cập nhật trạng thái ---------- */
  function openStatusModal(orderId) {
    const o = ordersCache.find((x) => x.id === orderId);
    if (!o) return;
    const nexts = NEXT_STATUS[o.status] || [];
    if (!nexts.length) {
      OF.ui.toast("info", "Đơn ở trạng thái “" + OF.ORDER_STATUS[o.status].label + "” không còn bước tiếp theo.");
      return;
    }
    const opts = nexts.map((s) =>
      '<option value="' + s + '">' + OF.ORDER_STATUS[s].label + "</option>"
    ).join("");
    OF.ui.openModal({
      title: "Cập nhật trạng thái đơn " + o.order_code,
      body:
        '<p class="text-2" style="margin-bottom:14px">Trạng thái hiện tại: <strong>' + OF.ORDER_STATUS[o.status].label + "</strong></p>" +
        '<div class="form-group"><label class="form-label">Chuyển sang trạng thái</label>' +
        '<select class="form-select" id="newStatus">' + opts + "</select>" +
        '<div class="form-hint">Server kiểm tra vòng đời đơn bằng transaction (SELECT … FOR UPDATE).</div></div>' +
        '<div class="form-group"><label class="form-label">Ghi chú (không bắt buộc)</label>' +
        '<input class="form-control" id="statusNote" placeholder="Ví dụ: Đã bàn giao đơn vị vận chuyển" /></div>',
      footer:
        '<button class="btn" data-close>Hủy</button>' +
        '<button class="btn btn-primary" id="confirmStatus">Cập nhật</button>',
      onOpen(api) {
        api.el.querySelector("#confirmStatus").addEventListener("click", async () => {
          const ns = api.el.querySelector("#newStatus").value;
          const note = api.el.querySelector("#statusNote").value.trim();
          const res = await OF.api.patch("orders/status.php", { id: o.id, to_status: ns, note });
          if (!res.success) { OF.ui.toast("error", res.message || "Cập nhật thất bại."); return; }
          OF.ui.toast("success", "Đơn " + o.order_code + ": " +
            OF.ORDER_STATUS[res.data.from_status].label + " → " + OF.ORDER_STATUS[res.data.to_status].label +
            (note ? " (ghi chú: " + note + ")" : ""));
          api.close();
          loadOrders();
        });
      },
    });
  }

  /* ---------- Hủy đơn ---------- */
  function cancelOrder(orderId) {
    const o = ordersCache.find((x) => x.id === orderId);
    if (!o) return;
    OF.ui.confirm({
      title: "Hủy đơn " + o.order_code + "?",
      message: "Đơn của “" + o.customer_name + "” sẽ chuyển sang Đã hủy. Server hoàn kho đúng 1 lần (transaction) và đánh dấu thanh toán chờ là thất bại.",
      danger: true,
      confirmText: "Hủy đơn",
    }).then(async (ok) => {
      if (!ok) return;
      const res = await OF.api.patch("orders/status.php", { id: o.id, to_status: "da_huy", note: "Hủy từ giao diện quản trị" });
      if (!res.success) { OF.ui.toast("error", res.message || "Hủy đơn thất bại."); return; }
      OF.ui.toast("success", "Đã hủy đơn " + o.order_code + " và hoàn kho tồn.");
      loadOrders();
    });
  }

  /* ---------- Tạo đơn hàng (yêu cầu 6) ---------- */
  let productOptions = [];

  async function openCreateForm() {
    const prodRes = await OF.api.get("products/index.php", "status=active&per_page=50");
    if (!prodRes.success) { OF.ui.toast("error", prodRes.message || "Không tải được sản phẩm."); return; }
    productOptions = prodRes.data.items || [];
    const u = OF.session.getUser() || {};
    const isCustomer = role() === "customer";

    const prodOpts = productOptions.map((p) =>
      '<option value="' + p.id + '" data-price="' + p.price + '" data-stock="' + p.stock + '" data-name="' + OF.escapeHtml(p.name) + '">' +
      OF.escapeHtml(p.name) + " · " + OF.fmt.money(p.price) + " (còn " + p.stock + ")</option>"
    ).join("");

    const m = OF.ui.openModal({
      title: "Tạo đơn hàng mới",
      size: "md",
      body:
        '<form id="createOrderForm" novalidate>' +
          (isCustomer
            ? ""
            : '<div class="form-row">' +
              '<div class="form-group"><label class="form-label">Tên khách hàng <span class="req">*</span></label>' +
                '<input class="form-control" id="coName" value="' + OF.escapeHtml(u.full_name || "") + '" />' +
                '<div class="form-error" data-error-for="coName"></div></div>' +
              '<div class="form-group"><label class="form-label">Số điện thoại</label>' +
                '<input class="form-control" id="coPhone" placeholder="0901234567" value="' + OF.escapeHtml(u.phone || "") + '" /></div>' +
            "</div>" +
            '<div class="form-group"><label class="form-label">Địa chỉ</label>' +
              '<input class="form-control" id="coAddress" placeholder="Số nhà, đường, quận, thành phố" /></div>'
          ) +
          '<div class="form-group"><label class="form-label">Phương thức thanh toán</label>' +
            '<select class="form-select" id="coPayMethod">' +
              '<option value="cod">COD (thanh toán khi nhận)</option>' +
              '<option value="bank">Chuyển khoản ngân hàng</option>' +
              '<option value="momo">Ví MoMo</option>' +
            "</select></div>" +
          '<div class="form-group"><label class="form-label">Sản phẩm <span class="req">*</span></label>' +
            '<select class="form-select" id="coProduct">' +
              '<option value="">— Chọn sản phẩm —</option>' + prodOpts +
            "</select>" +
            '<div class="form-error" data-error-for="coProduct"></div></div>' +
          '<div class="form-row">' +
            '<div class="form-group"><label class="form-label">Số lượng <span class="req">*</span></label>' +
              '<input class="form-control" id="coQty" type="number" min="1" step="1" value="1" /></div>' +
            '<div class="form-group" style="display:flex;align-items:flex-end">' +
              '<button type="button" class="btn" id="coAddItem">+ Thêm vào đơn</button></div>' +
          "</div>" +
          '<div id="coItems"></div>' +
          '<div class="order-total-row" style="margin-top:14px"><span>Tạm tính:</span><strong id="coTotal">0 ₫</strong></div>' +
        "</form>",
      footer:
        '<button class="btn" data-close>Hủy</button>' +
        '<button class="btn btn-primary" id="coSubmit">Tạo đơn hàng</button>',
      onOpen(api) {
        const picked = [];
        const itemsBox = api.el.querySelector("#coItems");
        const totalEl = api.el.querySelector("#coTotal");

        function renderItems() {
          if (!picked.length) {
            itemsBox.innerHTML = '<p class="muted" style="margin-top:8px">Chưa có sản phẩm nào trong đơn.</p>';
            totalEl.textContent = OF.fmt.money(0);
            return;
          }
          itemsBox.innerHTML = '<div class="table-wrap"><table class="order-items-table"><thead><tr><th>Sản phẩm</th><th class="text-right">SL</th><th class="text-right">Đơn giá</th><th class="text-right">Thành tiền</th><th></th></tr></thead><tbody>' +
            picked.map((it, idx) =>
              "<tr><td>" + OF.escapeHtml(it.name) + "</td>" +
              '<td class="text-right">' + it.quantity + "</td>" +
              '<td class="text-right">' + OF.fmt.money(it.unit_price) + "</td>" +
              '<td class="text-right"><strong>' + OF.fmt.money(it.unit_price * it.quantity) + "</strong></td>" +
              '<td><button type="button" class="btn btn-sm btn-icon" data-rm="' + idx + '" title="Bỏ" style="color:var(--danger)">' + OF.icon("trash", "icon-sm") + "</button></td></tr>"
            ).join("") + "</tbody></table></div>";
          itemsBox.querySelectorAll("[data-rm]").forEach((b) =>
            b.addEventListener("click", () => { picked.splice(Number(b.getAttribute("data-rm")), 1); renderItems(); }));
          const total = picked.reduce((s, it) => s + it.unit_price * it.quantity, 0);
          totalEl.textContent = OF.fmt.money(total);
        }
        renderItems();

        api.el.querySelector("#coAddItem").addEventListener("click", () => {
          const sel = api.el.querySelector("#coProduct");
          const pid = Number(sel.value);
          const qty = Math.max(1, Number(api.el.querySelector("#coQty").value) || 1);
          const err = api.el.querySelector('[data-error-for="coProduct"]');
          err.classList.remove("show");
          if (!pid) { err.textContent = "Hãy chọn sản phẩm."; err.classList.add("show"); return; }
          const opt = sel.options[sel.selectedIndex];
          const stock = Number(opt.getAttribute("data-stock"));
          const price = Number(opt.getAttribute("data-price"));
          const name = opt.getAttribute("data-name");
          const exist = picked.find((x) => x.product_id === pid);
          const currentQty = exist ? exist.quantity : 0;
          if (currentQty + qty > stock) {
            err.textContent = "Tồn kho chỉ còn " + stock + " (đang giữ trong đơn: " + currentQty + ").";
            err.classList.add("show");
            return;
          }
          if (exist) exist.quantity += qty;
          else picked.push({ product_id: pid, name, quantity: qty, unit_price: price });
          renderItems();
        });

        api.el.querySelector("#coSubmit").addEventListener("click", async () => {
          const btn = api.el.querySelector("#coSubmit");
          if (!picked.length) {
            const err = api.el.querySelector('[data-error-for="coProduct"]');
            err.textContent = "Đơn hàng cần ít nhất 1 sản phẩm.";
            err.classList.add("show");
            return;
          }
          btn.disabled = true;
          const body = {
            payment_method: api.el.querySelector("#coPayMethod").value,
            items: picked.map((x) => ({ product_id: x.product_id, quantity: x.quantity })),
          };
          if (!isCustomer) {
            body.customer_name = api.el.querySelector("#coName").value.trim();
            body.phone = api.el.querySelector("#coPhone").value.trim();
            body.address = api.el.querySelector("#coAddress").value.trim();
          } else {
            body.phone = u.phone || "";
            body.address = api.el.querySelector("#coAddress") ? api.el.querySelector("#coAddress").value.trim() : "";
          }
          const res = await OF.api.post("orders/index.php", body);
          btn.disabled = false;
          if (!res.success) {
            OF.ui.toast("error", res.message || "Tạo đơn thất bại.");
            if (res.errors && res.errors.customer_name) {
              const err = api.el.querySelector('[data-error-for="coName"]');
              if (err) { err.textContent = res.errors.customer_name; err.classList.add("show"); }
            }
            return;
          }
          OF.ui.toast("success", res.message || "Đã tạo đơn hàng.");
          api.close();
          page = 1;
          loadOrders();
        });
      },
    });
    void m;
  }

  /* ---------- Khởi tạo ---------- */
  document.addEventListener("DOMContentLoaded", function () {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get("status")) {
      statusF = qs.get("status");
      const sel = document.getElementById("statusFilter");
      if (sel) sel.value = statusF;
    }
    loadOrders();

    document.getElementById("btnCreateOrder").addEventListener("click", openCreateForm);
    document.getElementById("searchInput").addEventListener("input", (e) => {
      keyword = e.target.value; page = 1; loadOrders();
    });
    document.getElementById("statusFilter").addEventListener("change", (e) => {
      statusF = e.target.value; page = 1; loadOrders();
    });
    document.getElementById("dateFrom").addEventListener("change", (e) => {
      dateFrom = e.target.value; page = 1; loadOrders();
    });
    document.getElementById("dateTo").addEventListener("change", (e) => {
      dateTo = e.target.value; page = 1; loadOrders();
    });
    document.getElementById("btnClearFilter").addEventListener("click", () => {
      keyword = ""; statusF = ""; dateFrom = ""; dateTo = ""; page = 1;
      document.getElementById("searchInput").value = "";
      document.getElementById("statusFilter").value = "";
      document.getElementById("dateFrom").value = "";
      document.getElementById("dateTo").value = "";
      loadOrders();
    });
  });
})();
