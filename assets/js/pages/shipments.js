/* ============================================================
   Shopee – shipments.js (Quản lý giao hàng)
   Giai đoạn 2: API MySQL — server đồng bộ trạng thái đơn hàng
   khi giao thành công/thất bại (transaction).
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

  const NEXT_SHIP = {
    cho_lay_hang: ["dang_van_chuyen", "that_bai"],
    dang_van_chuyen: ["da_giao", "that_bai"],
    da_giao: [],
    that_bai: ["dang_van_chuyen"],
  };

  async function loadShipments() {
    const q = new URLSearchParams();
    if (keyword.trim()) q.set("q", keyword.trim());
    if (statusF) q.set("status", statusF);
    q.set("page", String(page));
    q.set("per_page", String(PER_PAGE));
    const res = await OF.api.get("shipments/index.php", q.toString());
    if (!res.success) { OF.ui.toast("error", res.message || "Không tải được vận đơn."); return; }
    rowsCache = res.data.items || [];
    render(rowsCache, res.data.total);
  }

  function render(rows, total) {
    const pages = Math.max(1, Math.ceil(total / PER_PAGE));
    if (page > pages) page = pages;
    document.getElementById("resultCount").textContent = total + " vận đơn";
    const tbody = document.getElementById("shipmentTableBody");

    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6">' + OF.ui.emptyStateHTML("Không có vận đơn phù hợp", "Thử đổi từ khóa hoặc trạng thái.") + "</td></tr>";
      document.getElementById("paginationBar").innerHTML = "";
      return;
    }

    tbody.innerHTML = rows.map((s) =>
      "<tr>" +
        '<td><span class="order-id-link" data-view="' + s.order_id + '">' + OF.escapeHtml(s.order_code) + "</span>" +
          '<span class="cell-sub">' + OF.escapeHtml(s.customer_name || "") + "</span></td>" +
        "<td>" + OF.escapeHtml(s.carrier) + "</td>" +
        '<td><strong>' + OF.escapeHtml(s.tracking_code || s.shipment_code) + "</strong></td>" +
        "<td>" + OF.fmt.date(s.ship_date) + "</td>" +
        "<td>" + OF.badgeHTML(OF.SHIPMENT_STATUS, s.status) + "</td>" +
        '<td><div class="cell-actions">' +
          (canManage() && NEXT_SHIP[s.status] && NEXT_SHIP[s.status].length
            ? '<button class="btn btn-sm" data-status="' + s.id + '">' + OF.icon("refresh", "icon-sm") + " Cập nhật</button>"
            : '<span class="muted" style="font-size:12.5px">Đã kết thúc</span>') +
        "</div></td>" +
      "</tr>"
    ).join("");

    tbody.querySelectorAll("[data-status]").forEach((el) =>
      el.addEventListener("click", () => openStatusModal(Number(el.getAttribute("data-status")))));
    tbody.querySelectorAll("[data-view]").forEach((el) =>
      el.addEventListener("click", () => openOrderInfo(Number(el.getAttribute("data-view")))));

    OF.ui.renderPagination(document.getElementById("paginationBar"), total, page, PER_PAGE, (p) => {
      page = p;
      loadShipments();
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
          '<div class="detail-item"><label>SĐT</label><span>' + OF.escapeHtml(o.phone || "—") + "</span></div>" +
          '<div class="detail-item"><label>Địa chỉ</label><span>' + OF.escapeHtml(o.address || "—") + "</span></div>" +
          '<div class="detail-item"><label>Trạng thái đơn</label><span>' + OF.badgeHTML(OF.ORDER_STATUS, o.status) + "</span></div>" +
        "</div>",
      footer: '<button class="btn" data-close>Đóng</button>',
    });
  }

  function openStatusModal(id) {
    const s = rowsCache.find((x) => x.id === id);
    if (!s) return;
    const nexts = NEXT_SHIP[s.status] || [];
    if (!nexts.length) {
      OF.ui.toast("info", "Vận đơn đã ở trạng thái cuối.");
      return;
    }
    const opts = nexts.map((st) => '<option value="' + st + '">' + OF.SHIPMENT_STATUS[st].label + "</option>").join("");
    OF.ui.openModal({
      title: "Cập nhật vận đơn " + (s.tracking_code || s.shipment_code),
      body:
        '<p class="text-2" style="margin-bottom:14px">Đơn <strong>' + OF.escapeHtml(s.order_code) + "</strong> · Hiện tại: <strong>" +
        OF.SHIPMENT_STATUS[s.status].label + "</strong></p>" +
        '<div class="form-group"><label class="form-label">Chuyển sang trạng thái giao</label>' +
        '<select class="form-select" id="newStatus">' + opts + "</select>" +
        '<div class="form-hint">Server tự đồng bộ trạng thái đơn hàng liên quan (transaction).</div></div>',
      footer:
        '<button class="btn" data-close>Hủy</button>' +
        '<button class="btn btn-primary" id="confirmStatus">Cập nhật</button>',
      onOpen(api) {
        api.el.querySelector("#confirmStatus").addEventListener("click", async () => {
          const ns = api.el.querySelector("#newStatus").value;
          const res = await OF.api.patch("shipments/status.php", { id: s.id, to_status: ns });
          if (!res.success) { OF.ui.toast("error", res.message || "Cập nhật thất bại."); return; }
          OF.ui.toast("success", "Vận đơn " + (s.tracking_code || s.shipment_code) + ": " +
            OF.SHIPMENT_STATUS[res.data.from_status].label + " → " + OF.SHIPMENT_STATUS[res.data.to_status].label);
          api.close();
          loadShipments();
        });
      },
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get("status")) {
      statusF = qs.get("status");
      const sel = document.getElementById("statusFilter");
      if (sel) sel.value = statusF;
    }
    loadShipments();
    document.getElementById("searchInput").addEventListener("input", (e) => {
      keyword = e.target.value; page = 1; loadShipments();
    });
    document.getElementById("statusFilter").addEventListener("change", (e) => {
      statusF = e.target.value; page = 1; loadShipments();
    });
  });
})();
