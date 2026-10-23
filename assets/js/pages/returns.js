/* ============================================================
   OrderFlow – returns.js (Quản lý đổi trả)
   Giai đoạn 2: API MySQL — validate số lượng tại server,
   hoàn tiền + nhập kho bằng transaction khi hoàn tất.
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  const PER_PAGE = 8;

  let page = 1;
  let keyword = "";
  let statusF = "";
  let rowsCache = [];
  const role = () => (OF.session.getUser() || {}).role || "customer";
  const canManage = () => role() === "admin" || role() === "staff";

  const NEXT_RETURN = {
    cho_xu_ly: ["da_chap_nhan", "tu_choi"],
    da_chap_nhan: ["da_hoan_tat", "tu_choi"],
    tu_choi: [],
    da_hoan_tat: [],
  };

  async function loadReturns() {
    const q = new URLSearchParams();
    if (keyword.trim()) q.set("q", keyword.trim());
    if (statusF) q.set("status", statusF);
    q.set("page", String(page));
    q.set("per_page", String(PER_PAGE));
    const res = await OF.api.get("returns/index.php", q.toString());
    if (!res.success) { OF.ui.toast("error", res.message || "Không tải được yêu cầu đổi trả."); return; }
    rowsCache = res.data.items || [];
    render(rowsCache, res.data.total);
  }

  function render(rows, total) {
    const pages = Math.max(1, Math.ceil(total / PER_PAGE));
    if (page > pages) page = pages;
    document.getElementById("resultCount").textContent = total + " yêu cầu";
    const tbody = document.getElementById("returnTableBody");

    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9">' + OF.ui.emptyStateHTML("Không có yêu cầu đổi trả phù hợp", "Thử đổi bộ lọc hoặc tạo yêu cầu mới.") + "</td></tr>";
      document.getElementById("paginationBar").innerHTML = "";
      return;
    }

    tbody.innerHTML = rows.map((r) => {
      const refund = r.status === "da_hoan_tat" ? r.refund_total : (r.status === "da_chap_nhan" ? Number(r.qty_accepted_total || 0) * Number(r.unit_price || 0) : 0);
      return "<tr>" +
        '<td><span class="order-id-link" data-view="' + r.id + '">' + OF.escapeHtml(r.return_code) + "</span></td>" +
        '<td><span class="order-id-link" data-order="' + r.order_id + '">' + OF.escapeHtml(r.order_code) + "</span></td>" +
        "<td>" + OF.escapeHtml(r.customer_name) + "</td>" +
        '<td><div class="prod-cell"><span class="prod-thumb">📦</span>' +
          '<span class="prod-cell-name" style="font-weight:500">' + OF.escapeHtml(r.product_name || "—") + "</span></div></td>" +
        '<td class="text-right">' + Number(r.qty_delivered || 0) + "</td>" +
        '<td class="text-right"><strong>' + Number(r.qty_requested_total || r.qty_requested || 0) + "</strong></td>" +
        '<td class="text-right"><strong>' + OF.fmt.money(refund) + "</strong></td>" +
        "<td>" + OF.badgeHTML(OF.RETURN_STATUS, r.status) + "</td>" +
        '<td><div class="cell-actions">' +
          '<button class="btn btn-sm btn-icon" data-view="' + r.id + '" title="Xem chi tiết">' + OF.icon("eye", "icon-sm") + "</button>" +
          (canManage() && NEXT_RETURN[r.status] && NEXT_RETURN[r.status].length
            ? '<button class="btn btn-sm btn-icon" data-status="' + r.id + '" title="Cập nhật trạng thái">' + OF.icon("refresh", "icon-sm") + "</button>"
            : "") +
        "</div></td>" +
      "</tr>";
    }).join("");

    tbody.querySelectorAll("[data-view]").forEach((el) =>
      el.addEventListener("click", () => openDetail(Number(el.getAttribute("data-view")))));
    tbody.querySelectorAll("[data-status]").forEach((el) =>
      el.addEventListener("click", () => openStatusModal(Number(el.getAttribute("data-status")))));
    tbody.querySelectorAll("[data-order]").forEach((el) =>
      el.addEventListener("click", () => openOrderInfo(Number(el.getAttribute("data-order")))));

    OF.ui.renderPagination(document.getElementById("paginationBar"), total, page, PER_PAGE, (p) => {
      page = p;
      loadReturns();
    });
  }

  /* ---------- Chi tiết yêu cầu ---------- */
  function openDetail(id) {
    const r = rowsCache.find((x) => x.id === id);
    if (!r) return;
    const requested = Number(r.qty_requested_total || r.qty_requested || 0);
    const delivered = Number(r.qty_delivered || 0);
    const accepted = Number(r.qty_accepted_total || r.qty_accepted || 0);
    const nexts = NEXT_RETURN[r.status] || [];

    const body =
      '<div class="detail-grid">' +
        '<div class="detail-item"><label>Mã yêu cầu</label><span>' + OF.escapeHtml(r.return_code) + "</span></div>" +
        '<div class="detail-item"><label>Đơn hàng</label><span>' + OF.escapeHtml(r.order_code) + "</span></div>" +
        '<div class="detail-item"><label>Khách hàng</label><span>' + OF.escapeHtml(r.customer_name) + "</span></div>" +
        '<div class="detail-item"><label>Ngày tạo</label><span>' + OF.fmt.dateTime(r.created_at) + "</span></div>" +
        '<div class="detail-item"><label>Sản phẩm</label><span>' + OF.escapeHtml(r.product_name || "—") + "</span></div>" +
        '<div class="detail-item"><label>Đơn giá đã chốt</label><span>' + OF.fmt.money(r.unit_price || 0) + "</span></div>" +
      "</div>" +
      '<div class="qty-boxes">' +
        '<div class="qty-box"><div class="qty-num">' + delivered + '</div><div class="qty-lbl">SL ĐÃ GIAO</div></div>' +
        '<div class="qty-box warn"><div class="qty-num">' + requested + '</div><div class="qty-lbl">SL YÊU CẦU TRẢ</div></div>' +
        '<div class="qty-box ok"><div class="qty-num">' + accepted + '</div><div class="qty-lbl">SL ĐÃ CHẤP NHẬN</div></div>' +
      "</div>" +
      '<div class="form-group"><label class="form-label">Lý do trả hàng</label>' +
      '<p class="text-2">' + OF.escapeHtml(r.reason) + "</p></div>" +
      '<div class="alert-banner alert-info" style="margin-bottom:0">' +
        OF.icon("info") +
        "<div><strong>Tiền hoàn (server tính): " + OF.fmt.money(r.refund_total) + "</strong>" +
        "<p>Số lượng chấp nhận × đơn giá snapshot trong đơn. " +
        (r.status === "da_hoan_tat" ? "Đã hoàn tất: tồn kho đã được nhập lại (transaction)." :
          r.status === "da_chap_nhan" ? "Chấp nhận một phần: chờ hoàn tất để hoàn tiền & nhập kho." :
          "Trạng thái hiện tại: " + OF.RETURN_STATUS[r.status].label + ".") +
        "</p></div>" +
      "</div>";

    let footer = '<button class="btn" data-close>Đóng</button>';
    if (canManage() && nexts.length) footer += '<button class="btn btn-primary" data-open-status>Cập nhật trạng thái</button>';

    OF.ui.openModal({
      title: "Chi tiết yêu cầu đổi trả " + r.return_code,
      size: "md",
      body,
      footer,
      onOpen(api) {
        const btn = api.el.querySelector("[data-open-status]");
        if (btn) btn.addEventListener("click", () => { api.close(); openStatusModal(r.id); });
      },
    });
  }

  async function openOrderInfo(orderId) {
    const res = await OF.api.get("orders/item.php", "id=" + orderId);
    if (!res.success) { OF.ui.toast("warning", res.message || "Không tìm thấy đơn hàng."); return; }
    const o = res.data.order;
    OF.ui.openModal({
      title: "Đơn hàng " + o.order_code,
      body:
        '<div class="detail-grid">' +
          '<div class="detail-item"><label>Khách hàng</label><span>' + OF.escapeHtml(o.customer_name) + "</span></div>" +
          '<div class="detail-item"><label>Ngày đặt</label><span>' + OF.fmt.dateTime(o.order_date) + "</span></div>" +
          '<div class="detail-item"><label>Tổng đơn</label><span>' + OF.fmt.money(o.total_amount) + "</span></div>" +
          '<div class="detail-item"><label>Trạng thái</label><span>' + OF.badgeHTML(OF.ORDER_STATUS, o.status) + "</span></div>" +
        "</div>" +
        '<table class="order-items-table"><thead><tr><th>Sản phẩm</th><th class="text-right">SL</th><th class="text-right">Đơn giá</th></tr></thead><tbody>' +
        (res.data.items || []).map((it) => "<tr><td>" + OF.escapeHtml(it.product_name) + "</td><td class=\"text-right\">" + it.quantity + "</td><td class=\"text-right\">" + OF.fmt.money(it.unit_price) + "</td></tr>").join("") +
        "</tbody></table>",
      footer: '<button class="btn" data-close>Đóng</button>',
    });
  }

  /* ---------- Cập nhật trạng thái ---------- */
  function openStatusModal(id) {
    const r = rowsCache.find((x) => x.id === id);
    if (!r) return;
    const nexts = NEXT_RETURN[r.status] || [];
    if (!nexts.length) {
      OF.ui.toast("info", "Yêu cầu ở trạng thái “" + OF.RETURN_STATUS[r.status].label + "” không còn bước tiếp theo.");
      return;
    }
    const opts = nexts.map((s) => '<option value="' + s + '">' + OF.RETURN_STATUS[s].label + "</option>").join("");
    const requested = Number(r.qty_requested_total || r.qty_requested || 0);
    OF.ui.openModal({
      title: "Cập nhật yêu cầu " + r.return_code,
      body:
        '<p class="text-2" style="margin-bottom:14px">Trạng thái hiện tại: <strong>' + OF.RETURN_STATUS[r.status].label + "</strong></p>" +
        '<div class="qty-boxes">' +
          '<div class="qty-box"><div class="qty-num">' + Number(r.qty_delivered || 0) + '</div><div class="qty-lbl">ĐÃ GIAO</div></div>' +
          '<div class="qty-box warn"><div class="qty-num">' + requested + '</div><div class="qty-lbl">YÊU CẦU TRẢ</div></div>' +
          '<div class="qty-box ok"><div class="qty-num">' + Number(r.qty_accepted_total || 0) + '</div><div class="qty-lbl">ĐÃ CHẤP NHẬN</div></div>' +
        "</div>" +
        '<div class="form-group"><label class="form-label">Chuyển sang</label><select class="form-select" id="newStatus">' + opts + "</select></div>" +
        '<div class="form-group" id="accQtyGroup" style="display:none">' +
          '<label class="form-label">Số lượng chấp nhận trả</label>' +
          '<input class="form-control" id="accQty" type="number" min="1" max="' + requested + '" value="' + requested + '" />' +
          '<div class="form-hint">Server chặn vượt số lượng còn trả được; tiền hoàn = SL chấp nhận × đơn giá trong đơn.</div></div>' +
        '<div class="form-group"><label class="form-label">Ghi chú (không bắt buộc)</label>' +
        '<input class="form-control" id="statusNote" placeholder="Ví dụ: Khách gửi hàng lỗi, đã kiểm tra" /></div>',
      footer:
        '<button class="btn" data-close>Hủy</button>' +
        '<button class="btn btn-primary" id="confirmStatus">Cập nhật</button>',
      onOpen(api) {
        const sel = api.el.querySelector("#newStatus");
        const accGroup = api.el.querySelector("#accQtyGroup");
        const syncAcc = () => { accGroup.style.display = (sel.value === "da_chap_nhan" || sel.value === "da_hoan_tat") ? "" : "none"; };
        sel.addEventListener("change", syncAcc);
        syncAcc();

        api.el.querySelector("#confirmStatus").addEventListener("click", async () => {
          const ns = sel.value;
          const note = api.el.querySelector("#statusNote").value.trim();
          const body = { id: r.id, to_status: ns, note };
          if (ns === "da_chap_nhan" || ns === "da_hoan_tat") {
            body.quantity_accepted = Number(api.el.querySelector("#accQty").value) || 0;
          }
          const res = await OF.api.patch("returns/status.php", body);
          if (!res.success) { OF.ui.toast("error", res.message || "Cập nhật thất bại."); return; }
          OF.ui.toast("success", "Yêu cầu " + r.return_code + ": " +
            OF.RETURN_STATUS[res.data.from_status].label + " → " + OF.RETURN_STATUS[res.data.to_status].label +
            (res.data.to_status === "da_hoan_tat" ? ". Tiền hoàn: " + OF.fmt.money(res.data.refund_total) : ""));
          api.close();
          loadReturns();
        });
      },
    });
  }

  /* ---------- Tạo yêu cầu mới ---------- */
  async function openCreateForm() {
    const orderRes = await OF.api.get("orders/index.php", "status=da_giao&per_page=50");
    if (!orderRes.success) { OF.ui.toast("error", orderRes.message || "Không tải được đơn hàng."); return; }
    const deliveredOrders = orderRes.data.items || [];
    if (!deliveredOrders.length) {
      OF.ui.toast("warning", "Hiện không có đơn nào ở trạng thái Đã giao để tạo yêu cầu đổi trả.");
      return;
    }
    const orderOpts = deliveredOrders.map((o) =>
      '<option value="' + o.id + '">' + o.order_code + " – " + OF.escapeHtml(o.customer_name) + " (" + OF.fmt.date(o.order_date) + ")</option>"
    ).join("");

    OF.ui.openModal({
      title: "Tạo yêu cầu đổi trả",
      size: "md",
      body:
        '<form id="returnForm" novalidate>' +
          '<div class="form-group"><label class="form-label">Đơn hàng (chỉ chọn đơn đã giao) <span class="req">*</span></label>' +
            '<select class="form-select" id="rOrder"><option value="">— Chọn đơn —</option>' + orderOpts + "</select>" +
            '<div class="form-error" data-error-for="rOrder"></div></div>' +
          '<div class="form-group"><label class="form-label">Sản phẩm trong đơn <span class="req">*</span></label>' +
            '<select class="form-select" id="rProduct"><option value="">— Chọn đơn trước —</option></select>' +
            '<div class="form-error" data-error-for="rProduct"></div></div>' +
          '<div class="form-row">' +
            '<div class="form-group"><label class="form-label">Số lượng yêu cầu trả <span class="req">*</span></label>' +
              '<input type="number" id="rQty" class="form-control" min="1" step="1" value="1" />' +
              '<div class="form-error" data-error-for="rQty"></div>' +
              '<div class="form-hint" id="rQtyHint"></div></div>' +
            '<div class="form-group"><label class="form-label">Tiền hoàn dự kiến</label>' +
              '<input class="form-control" id="rRefundPreview" disabled value="0 ₫" /></div>' +
          "</div>" +
          '<div class="form-group"><label class="form-label">Lý do trả hàng <span class="req">*</span></label>' +
            '<textarea class="form-control" id="rReason" rows="3" placeholder="Mô tả lỗi/khuyết điểm của sản phẩm…"></textarea>' +
            '<div class="form-error" data-error-for="rReason"></div></div>' +
        "</form>",
      footer:
        '<button class="btn" data-close>Hủy</button>' +
        '<button class="btn btn-primary" id="createReturnBtn">Tạo yêu cầu</button>',
      onOpen(api) {
        const orderSel = api.el.querySelector("#rOrder");
        const prodSel = api.el.querySelector("#rProduct");
        const qtyInput = api.el.querySelector("#rQty");
        const hint = api.el.querySelector("#rQtyHint");
        const preview = api.el.querySelector("#rRefundPreview");
        let orderItems = [];

        function currentItem() {
          const idx = Number(prodSel.value);
          return orderItems[idx] || null;
        }
        function updatePreview() {
          const it = currentItem();
          if (!it) { preview.value = "0 ₫"; hint.textContent = ""; return; }
          hint.textContent = "Đã giao " + it.quantity + " (server tự chặn vượt phần còn lại)";
          const q = Number(qtyInput.value) || 0;
          preview.value = OF.fmt.money(q * Number(it.unit_price));
        }
        orderSel.addEventListener("change", async () => {
          prodSel.innerHTML = '<option value="">Đang tải…</option>';
          if (!orderSel.value) { prodSel.innerHTML = '<option value="">— Chọn đơn trước —</option>'; updatePreview(); return; }
          const res = await OF.api.get("orders/item.php", "id=" + orderSel.value);
          if (!res.success) { OF.ui.toast("error", res.message || "Không tải được sản phẩm trong đơn."); prodSel.innerHTML = ""; return; }
          orderItems = res.data.items || [];
          prodSel.innerHTML = orderItems.map((it, i) =>
            '<option value="' + i + '">' + OF.escapeHtml(it.product_name) + (it.variant ? " (" + OF.escapeHtml(it.variant) + ")" : "") + "</option>"
          ).join("");
          updatePreview();
        });
        prodSel.addEventListener("change", updatePreview);
        qtyInput.addEventListener("input", updatePreview);

        api.el.querySelector("#createReturnBtn").addEventListener("click", async () => {
          const it = currentItem();
          const qty = Number(qtyInput.value);
          const reason = api.el.querySelector("#rReason").value.trim();
          let ok = true;
          api.el.querySelectorAll(".form-error").forEach((e) => e.classList.remove("show"));
          api.el.querySelectorAll(".is-invalid").forEach((e) => e.classList.remove("is-invalid"));
          if (!orderSel.value) { fieldErr(api, "rOrder", "Hãy chọn đơn hàng."); ok = false; }
          if (!it) { fieldErr(api, "rProduct", "Hãy chọn sản phẩm."); ok = false; }
          if (!qty || qty < 1) { fieldErr(api, "rQty", "Số lượng phải ≥ 1."); ok = false; }
          if (reason.length < 5) { fieldErr(api, "rReason", "Lý do phải có ít nhất 5 ký tự."); ok = false; }
          if (!ok) return;

          const btn = api.el.querySelector("#createReturnBtn");
          btn.disabled = true;
          const res = await OF.api.post("returns/index.php", {
            order_id: Number(orderSel.value),
            order_item_id: it.id,
            quantity: qty,
            reason,
          });
          btn.disabled = false;
          if (!res.success) {
            const errs = res.errors || {};
            if (errs.quantity) fieldErr(api, "rQty", errs.quantity);
            else if (errs.reason) fieldErr(api, "rReason", errs.reason);
            else OF.ui.toast("error", res.message || "Tạo yêu cầu thất bại.");
            return;
          }
          OF.ui.toast("success", res.message || "Đã tạo yêu cầu đổi trả.");
          api.close();
          page = 1;
          loadReturns();
        });
      },
    });
  }

  function fieldErr(api, id, msg) {
    const input = api.el.querySelector("#" + id);
    const err = api.el.querySelector('[data-error-for="' + id + '"]');
    if (input) input.classList.add("is-invalid");
    if (err) { err.textContent = msg; err.classList.add("show"); }
  }

  document.addEventListener("DOMContentLoaded", function () {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get("status")) {
      statusF = qs.get("status");
      const sel = document.getElementById("statusFilter");
      if (sel) sel.value = statusF;
    }
    loadReturns();
    document.getElementById("btnAddReturn").addEventListener("click", openCreateForm);
    document.getElementById("searchInput").addEventListener("input", (e) => {
      keyword = e.target.value; page = 1; loadReturns();
    });
    document.getElementById("statusFilter").addEventListener("change", (e) => {
      statusF = e.target.value; page = 1; loadReturns();
    });
  });
})();
