/* ============================================================
   OrderFlow – cart.js (Giỏ hàng + checkout)
   Đọc/ghi OF.cart, đối chiếu snapshot với products API mỗi lần mở,
   rồi tạo đơn qua orders/index.php (server tự chốt giá & tồn).
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;

  let cartItems = [];
  let checkoutMode = false;
  let merging = false;
  let placing = false;

  const draft = { address: null, phone: null, method: "cod" };

  const currentUser = () => OF.session.getUser() || {};
  const addressKey = () => "orderflow.address." + (currentUser().id != null ? currentUser().id : "guest");

  function cleanItem(it) {
    const copy = Object.assign({}, it);
    delete copy._unavailable;
    delete copy._reason;
    return copy;
  }

  function persist() {
    OF.cart.write(cartItems.map(cleanItem));
  }

  function allAvailable() {
    return cartItems.length > 0 && cartItems.every((it) => !it._unavailable);
  }

  function estimateTotal() {
    return cartItems.reduce((s, it) => {
      if (it._unavailable) return s;
      return s + (Number(it.price) || 0) * (Number(it.quantity) || 0);
    }, 0);
  }

  function lineHTML(it) {
    const pid = Number(it.product_id);
    const unavailable = !!it._unavailable;
    const qty = Math.max(1, Number(it.quantity) || 1);
    const stock = Number(it.stock);
    const max = Number.isFinite(stock) && stock > 0 ? stock : qty;
    const price = Number(it.price) || 0;
    return '<div class="cart-row' + (unavailable ? " unavailable" : "") + '" data-cart-id="' + pid + '">' +
      '<div class="cart-thumb' + (unavailable ? " out" : "") + '">' + OF.escapeHtml(it.emoji || "📦") + "</div>" +
      '<div class="cart-line-info">' +
        '<div class="cart-line-name">' + OF.escapeHtml(it.name || ("Sản phẩm #" + pid)) + "</div>" +
        '<div class="cart-line-meta">' +
          (unavailable
            ? '<span class="badge badge-red">' + OF.escapeHtml(it._reason || "Không khả dụng") + "</span>"
            : OF.fmt.money(price)) +
        "</div>" +
      "</div>" +
      '<div class="qty-stepper">' +
        '<button type="button" data-dec aria-label="Giảm số lượng"' + (unavailable || qty <= 1 ? " disabled" : "") + ">−</button>" +
        "<span>" + qty + "</span>" +
        '<button type="button" data-inc aria-label="Tăng số lượng"' + (unavailable || qty >= max ? " disabled" : "") + ">+</button>" +
      "</div>" +
      '<div class="cart-line-total">' +
        (unavailable ? '<span class="muted">Không tính</span>' : OF.fmt.money(price * qty)) +
      "</div>" +
      '<button type="button" class="btn btn-sm btn-icon" data-remove title="Xóa khỏi giỏ">' + OF.icon("trash", "icon-sm") + "</button>" +
    "</div>";
  }

  function bindLineEvents() {
    const host = document.getElementById("cartLines");
    if (!host) return;
    host.querySelectorAll(".cart-row").forEach((row) => {
      const pid = Number(row.getAttribute("data-cart-id"));
      const dec = row.querySelector("[data-dec]");
      const inc = row.querySelector("[data-inc]");
      const remove = row.querySelector("[data-remove]");
      if (dec) dec.addEventListener("click", () => changeQty(pid, -1));
      if (inc) inc.addEventListener("click", () => changeQty(pid, 1));
      if (remove) remove.addEventListener("click", () => removeItem(pid));
    });
  }

  function changeQty(pid, delta) {
    const it = cartItems.find((x) => Number(x.product_id) === pid);
    if (!it || it._unavailable) return;
    const stock = Number(it.stock);
    const max = Number.isFinite(stock) && stock > 0 ? stock : Infinity;
    const next = Math.max(1, Math.min(max, (Number(it.quantity) || 1) + delta));
    if (next === it.quantity) return;
    it.quantity = next;
    persist();
    render();
  }

  function removeItem(pid) {
    const it = cartItems.find((x) => Number(x.product_id) === pid);
    if (!it) return;
    OF.ui.confirm({
      title: "Xóa sản phẩm khỏi giỏ?",
      message: "“" + (it.name || "Sản phẩm") + "” sẽ được bỏ khỏi giỏ hàng.",
      danger: true,
      confirmText: "Xóa",
    }).then((ok) => {
      if (!ok) return;
      cartItems = cartItems.filter((x) => Number(x.product_id) !== pid);
      persist();
      render();
    });
  }

  function ensureDraft() {
    if (draft.address == null) draft.address = localStorage.getItem(addressKey()) || "";
    if (draft.phone == null) draft.phone = currentUser().phone || "";
    if (!draft.method) draft.method = "cod";
  }

  function renderCheckout() {
    const host = document.getElementById("checkoutCard");
    if (!host) return;
    const total = estimateTotal();
    const canSubmit = allAvailable();

    if (!checkoutMode) {
      host.innerHTML =
        '<div class="checkout-head"><h2>Đơn của bạn</h2><span class="muted">' + cartItems.length + " sản phẩm</span></div>" +
        '<div class="checkout-summary">' +
          '<div class="checkout-row"><span>Tạm tính (ước tính)</span><strong>' + OF.fmt.money(total) + "</strong></div>" +
        "</div>" +
        '<p class="estimate-note">Giá chính thức do hệ thống chốt khi đặt hàng.</p>' +
        (!canSubmit
          ? '<div class="alert-banner alert-warning" style="margin:0 0 12px"><div><strong>Có sản phẩm không khả dụng</strong><p>Hãy xóa các dòng ngừng bán hoặc hết hàng trước khi đặt.</p></div></div>'
          : "") +
        '<button class="btn btn-primary full-width" id="btnCheckout"' + (canSubmit ? "" : " disabled") + ">Chốt đơn</button>";

      const btn = host.querySelector("#btnCheckout");
      if (btn) btn.addEventListener("click", () => {
        ensureDraft();
        checkoutMode = true;
        render();
      });
      return;
    }

    const methods = Object.keys(OF.PAYMENT_METHODS).map((key) =>
      '<option value="' + OF.escapeHtml(key) + '"' + (draft.method === key ? " selected" : "") + ">" +
      OF.escapeHtml(OF.PAYMENT_METHODS[key]) + "</option>"
    ).join("");

    host.innerHTML =
      '<div class="checkout-head"><h2>Thông tin đặt hàng</h2><span class="muted">' + OF.fmt.money(total) + "</span></div>" +
      '<div class="form-group"><label class="form-label" for="coAddress">Địa chỉ nhận hàng <span class="req">*</span></label>' +
        '<textarea class="form-control" id="coAddress" rows="3" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành">' +
          OF.escapeHtml(draft.address || "") +
        "</textarea>" +
        '<div class="form-error" id="addressError"></div>' +
      "</div>" +
      '<div class="form-group"><label class="form-label" for="coPhone">Số điện thoại</label>' +
        '<input class="form-control" id="coPhone" value="' + OF.escapeHtml(draft.phone || "") + '" placeholder="0901234567" />' +
      "</div>" +
      '<div class="form-group"><label class="form-label" for="coPayment">Phương thức thanh toán</label>' +
        '<select class="form-select" id="coPayment">' + methods + "</select>" +
      "</div>" +
      '<p class="estimate-note">Tạm tính ' + OF.fmt.money(total) + ". Giá chính thức do hệ thống chốt khi đặt hàng.</p>" +
      (!canSubmit
        ? '<div class="alert-banner alert-warning" style="margin:0 0 12px"><div><strong>Có sản phẩm không khả dụng</strong><p>Hãy quay lại xóa trước khi đặt.</p></div></div>'
        : "") +
      '<div class="checkout-actions">' +
        '<button class="btn" id="btnBackCart">Quay lại</button>' +
        '<button class="btn btn-primary" id="btnPlaceOrder"' + (canSubmit ? "" : " disabled") + ">Đặt hàng</button>" +
      "</div>";

    const address = host.querySelector("#coAddress");
    const phone = host.querySelector("#coPhone");
    const method = host.querySelector("#coPayment");
    const err = host.querySelector("#addressError");
    const back = host.querySelector("#btnBackCart");
    const place = host.querySelector("#btnPlaceOrder");

    if (address) address.addEventListener("input", () => {
      draft.address = address.value;
      address.classList.remove("is-invalid");
      if (err) { err.textContent = ""; err.classList.remove("show"); }
    });
    if (phone) phone.addEventListener("input", () => { draft.phone = phone.value; });
    if (method) method.addEventListener("change", () => { draft.method = method.value; });
    if (back) back.addEventListener("click", () => { checkoutMode = false; render(); });
    if (place) place.addEventListener("click", () => placeOrder(place));
  }

  async function placeOrder(btn) {
    if (placing) return;
    const address = document.getElementById("coAddress");
    const phone = document.getElementById("coPhone");
    const method = document.getElementById("coPayment");
    const err = document.getElementById("addressError");
    if (!address || !phone || !method) return;

    const addressValue = address.value.trim();
    const phoneValue = phone.value.trim();
    const methodValue = method.value;
    draft.address = addressValue;
    draft.phone = phoneValue;
    draft.method = methodValue;

    if (addressValue.length < 8) {
      address.classList.add("is-invalid");
      if (err) {
        err.textContent = "Vui lòng nhập địa chỉ nhận hàng đầy đủ (ít nhất 8 ký tự).";
        err.classList.add("show");
      }
      address.focus();
      return;
    }
    if (!allAvailable()) {
      OF.ui.toast("warning", "Có sản phẩm không khả dụng, hãy quay lại xóa trước khi đặt.");
      return;
    }

    placing = true;
    btn.disabled = true;
    localStorage.setItem(addressKey(), addressValue);

    const body = {
      payment_method: methodValue,
      phone: phoneValue,
      address: addressValue,
      items: cartItems.filter((it) => !it._unavailable).map((it) => ({
        product_id: Number(it.product_id),
        quantity: Number(it.quantity) || 1,
      })),
    };

    // Không gửi customer_name: server tự ép theo token của người đặt.
    const res = await OF.api.post("orders/index.php", body);
    if (!res.success) {
      placing = false;
      btn.disabled = false;
      OF.ui.toast("error", res.message || "Đặt hàng thất bại.");
      mergeCart();
      return;
    }

    const code = (res.data && res.data.order_code) || "";
    OF.cart.clear();
    checkoutMode = false;
    OF.ui.toast("success", "Đã tạo đơn " + code + " — đơn giữ hàng 15 phút, chờ nhân viên xử lý.");
    window.location.href = "orders.html?status=cho_xac_nhan";
  }

  function render() {
    const lineHost = document.getElementById("cartLines");
    const emptyHost = document.getElementById("cartEmpty");
    const right = document.getElementById("checkoutCard");
    const summary = document.getElementById("cartSummaryText");
    if (!lineHost || !emptyHost || !right) return;

    if (!cartItems.length) {
      lineHost.innerHTML = "";
      lineHost.classList.add("hidden");
      emptyHost.classList.remove("hidden");
      emptyHost.innerHTML = OF.ui.emptyStateHTML("Giỏ hàng đang trống", "Hãy chọn vài sản phẩm ở cửa hàng rồi quay lại nhé.") +
        '<div class="cart-empty-action"><a class="btn btn-primary" href="shop.html">Về cửa hàng</a></div>';
      right.classList.add("hidden");
      if (summary) summary.textContent = "";
      return;
    }

    emptyHost.classList.add("hidden");
    emptyHost.innerHTML = "";
    lineHost.classList.remove("hidden");
    right.classList.remove("hidden");

    const totalQty = cartItems.reduce((s, it) => s + (Number(it.quantity) || 0), 0);
    if (summary) summary.textContent = cartItems.length + " dòng · " + totalQty + " sản phẩm";

    lineHost.innerHTML = cartItems.map(lineHTML).join("");
    bindLineEvents();
    renderCheckout();
  }

  async function mergeCart() {
    if (merging) return;
    merging = true;

    const stored = OF.cart.read();
    if (!stored.length) {
      cartItems = [];
      merging = false;
      render();
      return;
    }

    // mỗi dòng giỏ tra riêng item — chắc chắn đúng dù catalog > 50 sp
    const lookupIds = [];
    stored.slice(0, 30).forEach((it) => {
      const id = Number(it.product_id);
      if (!lookupIds.includes(id)) lookupIds.push(id);
    });
    const lookups = await Promise.all(
      lookupIds.map((id) => OF.api.get("products/item.php", "id=" + id))
    );
    const map = new Map();
    const checked = new Set(lookupIds);
    lookupIds.forEach((id, idx) => {
      const r = lookups[idx];
      if (r && r.success && r.data) map.set(id, r.data);
    });

    const clamped = [];
    cartItems = stored.map((it) => {
      const productId = Number(it.product_id);
      const next = Object.assign({}, it, {
        product_id: productId,
        quantity: Math.max(1, Number(it.quantity) || 1),
      });
      if (!checked.has(productId)) return next;

      const p = map.get(productId);

      if (!p || p.status !== "active") {
        next._unavailable = true;
        next._reason = "Sản phẩm ngừng bán";
        if (p) {
          next.name = p.name;
          next.price = Number(p.price) || 0;
          next.emoji = p.emoji || next.emoji;
          next.stock = Number(p.stock) || 0;
        }
        return next;
      }

      next.name = p.name;
      next.price = Number(p.price) || 0;
      next.emoji = p.emoji || "📦";
      next.stock = Number(p.stock) || 0;

      if (next.stock <= 0) {
        next._unavailable = true;
        next._reason = "Sản phẩm tạm hết hàng";
      } else if (next.quantity > next.stock) {
        next.quantity = next.stock;
        clamped.push(next.name);
      }
      return next;
    });

    persist();
    merging = false;
    if (clamped.length) {
      OF.ui.toast("warning", "Đã điều chỉnh số lượng theo tồn kho: " + clamped.join(", ") + ".");
    }
    render();
  }

  document.addEventListener("DOMContentLoaded", function () {
    mergeCart();
  });
})();
