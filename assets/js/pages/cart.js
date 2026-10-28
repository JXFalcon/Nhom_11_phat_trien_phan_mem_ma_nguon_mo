/* ============================================================
   Shopee – cart.js (Giỏ hàng)
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;

  let cartId = null;
  let items = [];
  let total = 0;

  async function loadCart() {
    const res = await OF.api.get("carts/index.php");
    if (!res.success) {
      OF.ui.toast("error", res.message || "Không tải được giỏ hàng.");
      return;
    }
    const d = res.data;
    cartId = d.cart_id;
    items = d.items || [];
    total = d.total || 0;
    render();
  }

  function render() {
    const emptyEl = document.getElementById("cartEmpty");
    const listEl = document.getElementById("cartList");
    const tbody = document.getElementById("cartTableBody");
    const totalEl = document.getElementById("cartTotal");
    const infoEl = document.getElementById("checkoutInfo");

    if (!items.length) {
      emptyEl.style.display = "block";
      listEl.style.display = "none";
      if (infoEl) infoEl.style.display = "none";
      return;
    }
    emptyEl.style.display = "none";
    listEl.style.display = "block";
    if (infoEl) infoEl.style.display = "grid";

    // Tự điền người nhận từ thông tin tài khoản (không ghi đè nếu user đã nhập)
    const user = OF.session.getUser();
    const uid = "inpName";
    const pid = "inpPhone";
    if (user && document.getElementById(uid) && !document.getElementById(uid).value) {
      document.getElementById(uid).value = user.full_name || "";
    }
    if (user && document.getElementById(pid) && !document.getElementById(pid).value) {
      document.getElementById(pid).value = user.phone || "";
    }

    tbody.innerHTML = items.map((it) => {
      const lineTotal = it.quantity * it.unit_price;
      return "<tr>" +
        "<td>" + OF.escapeHtml(it.product_name) +
          (it.variant ? ' <span class="muted">(' + OF.escapeHtml(it.variant) + ")</span>" : "") + "</td>" +
        '<td class="text-right">' + OF.fmt.money(it.unit_price) + "</td>" +
        '<td class="text-right">' +
          '<input type="number" class="form-control qty-input" data-id="' + it.id + '" value="' + it.quantity + '" min="1" style="width:70px" />' +
        "</td>" +
        '<td class="text-right"><strong>' + OF.fmt.money(lineTotal) + "</strong></td>" +
        '<td class="text-right"><button class="btn btn-sm btn-icon" data-rm="' + it.id + '" title="Xóa" style="color:var(--danger)">' + OF.icon("trash", "icon-sm") + "</button></td>" +
      "</tr>";
    }).join("");

    totalEl.textContent = OF.fmt.money(total);

    tbody.querySelectorAll(".qty-input").forEach((el) =>
      el.addEventListener("change", () => updateQty(Number(el.getAttribute("data-id")), Number(el.value))));
    tbody.querySelectorAll("[data-rm]").forEach((el) =>
      el.addEventListener("click", () => removeItem(Number(el.getAttribute("data-rm")))));
  }

  async function updateQty(id, qty) {
    if (qty < 1) qty = 1;
    const res = await OF.api.put("carts/item.php?id=" + id, { quantity: qty });
    if (!res.success) {
      OF.ui.toast("error", res.message || "Cập nhật thất bại.");
      loadCart();
      return;
    }
    OF.ui.toast("success", "Đã cập nhật số lượng.");
    loadCart();
  }

  async function removeItem(id) {
    const res = await OF.api.del("carts/item.php?id=" + id);
    if (!res.success) {
      OF.ui.toast("error", res.message || "Xóa thất bại.");
      return;
    }
    OF.ui.toast("success", "Đã xóa khỏi giỏ hàng.");
    loadCart();
  }

  async function checkout() {
    if (!items.length) {
      OF.ui.toast("error", "Giỏ hàng trống.");
      return;
    }
    const phone = (document.getElementById("inpPhone").value || "").trim();
    const address = (document.getElementById("inpAddress").value || "").trim();
    const name = (document.getElementById("inpName").value || "").trim();
    if (!phone || !/^[\d+()\s-]{8,15}$/.test(phone)) {
      OF.ui.toast("error", "Vui lòng nhập số điện thoại người nhận hợp lệ (8–15 ký tự số).");
      document.getElementById("inpPhone").focus();
      return;
    }
    if (address.length < 5) {
      OF.ui.toast("error", "Vui lòng nhập địa chỉ giao hàng (ít nhất 5 ký tự).");
      document.getElementById("inpAddress").focus();
      return;
    }
    if (name.length < 2) {
      OF.ui.toast("error", "Vui lòng nhập tên người nhận.");
      document.getElementById("inpName").focus();
      return;
    }
    const res = await OF.api.post("orders/index.php", {
      from_cart: true,
      phone: phone,
      address: address,
      customer_name: name,
    });
    if (!res.success) {
      OF.ui.toast("error", res.message || "Tạo đơn thất bại.");
      return;
    }
    OF.ui.toast("success", res.message || "Đã tạo đơn hàng từ giỏ.");
    window.location.href = "orders.html";
  }

  document.addEventListener("DOMContentLoaded", function () {
    loadCart();
    document.getElementById("btnCheckoutFromCart").addEventListener("click", checkout);
  });
})();
