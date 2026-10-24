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
  let reviewMode = false;
  let successMode = false;
  let placedOrder = null;
  let merging = false;
  let placing = false;

  const MAX_LINES = 30;
  const PHONE_RE = /^(0|\+84)[0-9]{9,10}$/;
  const CHECKOUT_STEPS = ["Giỏ hàng", "Thông tin nhận hàng", "Xác nhận"];

  const draft = { address: null, phone: null, method: "cod" };

  const currentUser = () => OF.session.getUser() || {};
  const addressKey = () => "orderflow.address." + (currentUser().id != null ? currentUser().id : "guest");
  const lineKeyOf = (it) => OF.cart.lineKey(it.product_id, it.variant);
  const variantOf = (it) => (it.variant == null ? "" : String(it.variant).trim());

  function addressError(value) {
    const len = String(value || "").trim().length;
    if (len === 0) return "Vui lòng nhập địa chỉ nhận hàng.";
    if (len < 8) return "Địa chỉ cần ít nhất 8 ký tự.";
    if (len > 255) return "Địa chỉ tối đa 255 ký tự.";
    return "";
  }

  function phoneError(value) {
    const v = String(value || "").trim();
    if (!v) return "";
    return PHONE_RE.test(v) ? "" : "Số điện thoại không hợp lệ (ví dụ 0901234567 hoặc +84901234567).";
  }

  function variantStockFor(p, variant) {
    const rows = Array.isArray(p.variant_rows) ? p.variant_rows : [];
    const v = variant == null ? "" : String(variant).trim();
    if (!v) return null;
    const row = rows.find((r) => String(r.name) === v);
    if (!row) return null;
    const stock = Number(row.stock);
    return Number.isFinite(stock) ? Math.max(0, stock) : null;
  }

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
    const key = lineKeyOf(it);
    const variant = variantOf(it);
    const unavailable = !!it._unavailable;
    const qty = Math.max(1, Number(it.quantity) || 1);
    const stock = Number(it.stock);
    const max = Number.isFinite(stock) && stock > 0 ? stock : qty;
    const price = Number(it.price) || 0;
    return '<div class="cart-row' + (unavailable ? " unavailable" : "") + '" data-cart-key="' + OF.escapeHtml(key) + '">' +
      '<div class="cart-thumb' + (unavailable ? " out" : "") + '">' + OF.escapeHtml(it.emoji || "📦") + "</div>" +
      '<div class="cart-line-info">' +
        '<a class="cart-line-name" href="shop-item.html?id=' + pid + '">' + OF.escapeHtml(it.name || ("Sản phẩm #" + pid)) + "</a>" +
        (variant ? '<div class="cart-line-variant">Phân loại: ' + OF.escapeHtml(variant) + "</div>" : "") +
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
      const key = row.getAttribute("data-cart-key") || "";
      const dec = row.querySelector("[data-dec]");
      const inc = row.querySelector("[data-inc]");
      const remove = row.querySelector("[data-remove]");
      if (dec) dec.addEventListener("click", () => changeQty(key, -1));
      if (inc) inc.addEventListener("click", () => changeQty(key, 1));
      if (remove) remove.addEventListener("click", () => removeItem(key));
    });
  }

  function changeQty(key, delta) {
    const it = cartItems.find((x) => lineKeyOf(x) === key);
    if (!it || it._unavailable) return;
    const stock = Number(it.stock);
    const max = Number.isFinite(stock) && stock > 0 ? stock : Infinity;
    const next = Math.max(1, Math.min(max, (Number(it.quantity) || 1) + delta));
    if (next === it.quantity) return;
    it.quantity = next;
    persist();
    render();
  }

  function removeItem(key) {
    const it = cartItems.find((x) => lineKeyOf(x) === key);
    if (!it) return;
    const variant = variantOf(it);
    OF.ui.confirm({
      title: "Xóa sản phẩm khỏi giỏ?",
      message: "“" + (it.name || "Sản phẩm") + (variant ? " (" + variant + ")" : "") + "” sẽ được bỏ khỏi giỏ hàng.",
      danger: true,
      confirmText: "Xóa",
    }).then((ok) => {
      if (!ok) return;
      cartItems = cartItems.filter((x) => lineKeyOf(x) !== key);
      persist();
      render();
    });
  }

  function ensureDraft() {
    if (draft.address == null) draft.address = localStorage.getItem(addressKey()) || "";
    if (draft.phone == null) draft.phone = currentUser().phone || "";
    if (!draft.method) draft.method = "cod";
  }

  function unavailableWarning(message) {
    return '<div class="alert-banner alert-warning" style="margin:0 0 12px"><div><strong>Có sản phẩm không khả dụng</strong><p>' +
      OF.escapeHtml(message) + "</p></div></div>";
  }

  function renderStepA() {
    const host = document.getElementById("checkoutCard");
    if (!host) return;
    const total = estimateTotal();
    const canSubmit = allAvailable();

    host.innerHTML =
      '<div class="checkout-head"><h2>Đơn của bạn</h2><span class="muted">' + cartItems.length + " dòng</span></div>" +
      '<div class="checkout-summary">' +
        '<div class="checkout-row"><span>Tạm tính (ước tính)</span><strong>' + OF.fmt.money(total) + "</strong></div>" +
        '<div class="checkout-row checkout-row-sub"><span>Số dòng</span><span>' + cartItems.length + " dòng</span></div>" +
      "</div>" +
      '<p class="estimate-note">Giá chính thức do hệ thống chốt khi đặt hàng.</p>' +
      (!canSubmit ? unavailableWarning("Hãy xóa các dòng ngừng bán hoặc hết hàng trước khi đặt.") : "") +
      '<div class="checkout-stack">' +
        '<button class="btn btn-primary full-width" id="btnCheckout"' + (canSubmit ? "" : " disabled") + ">Tiến hành đặt hàng</button>" +
        '<a class="btn full-width" href="shop.html">Tiếp tục mua sắm</a>' +
      "</div>";

    const btn = host.querySelector("#btnCheckout");
    if (btn) btn.addEventListener("click", () => {
      ensureDraft();
      checkoutMode = true;
      reviewMode = false;
      render();
    });
  }

  function checkoutLineHTML(it) {
    const unavailable = !!it._unavailable;
    const qty = Math.max(1, Number(it.quantity) || 1);
    const price = Number(it.price) || 0;
    const variant = variantOf(it);
    return '<div class="checkout-line' + (unavailable ? " unavailable" : "") + '">' +
      '<div class="checkout-line-main">' +
        '<a class="checkout-line-name" href="shop-item.html?id=' + Number(it.product_id) + '">' + OF.escapeHtml(it.name || "Sản phẩm") + "</a>" +
        (variant ? '<div class="checkout-line-variant">Phân loại: ' + OF.escapeHtml(variant) + "</div>" : "") +
        '<div class="checkout-line-meta">' +
          (unavailable
            ? '<span class="badge badge-red">' + OF.escapeHtml(it._reason || "Không khả dụng") + "</span>"
            : "SL " + qty + " × " + OF.fmt.money(price)) +
        "</div>" +
      "</div>" +
      '<div class="checkout-line-total">' + (unavailable ? "—" : OF.fmt.money(price * qty)) + "</div>" +
    "</div>";
  }

  function renderStepB() {
    const formHost = document.getElementById("checkoutForm");
    const summaryHost = document.getElementById("checkoutSummary");
    if (!formHost || !summaryHost) return;

    const total = estimateTotal();
    const canSubmit = allAvailable();
    const methods = Object.keys(OF.PAYMENT_METHODS).map((key) =>
      '<option value="' + OF.escapeHtml(key) + '"' + (draft.method === key ? " selected" : "") + ">" +
      OF.escapeHtml(OF.PAYMENT_METHODS[key]) + "</option>"
    ).join("");

    summaryHost.innerHTML =
      '<div class="checkout-head"><h2>Đơn của bạn</h2><span class="muted">' + cartItems.length + " dòng</span></div>" +
      '<div class="checkout-lines">' + cartItems.map(checkoutLineHTML).join("") + "</div>" +
      '<div class="checkout-total"><span>Tạm tính (ước tính)</span><strong>' + OF.fmt.money(total) + "</strong></div>" +
      '<p class="estimate-note">Giá chính thức do hệ thống chốt khi đặt hàng.</p>' +
      (!canSubmit ? unavailableWarning("Hãy quay lại giỏ và xóa sản phẩm không khả dụng trước khi đặt.") : "");

    formHost.innerHTML =
      '<div class="form-group"><label class="form-label" for="coAddress">Địa chỉ nhận hàng <span class="req">*</span></label>' +
        '<textarea class="form-control" id="coAddress" rows="3" maxlength="255" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành">' +
          OF.escapeHtml(draft.address || "") +
        "</textarea>" +
        '<div class="form-error" id="addressError"></div>' +
      "</div>" +
      '<div class="form-group"><label class="form-label" for="coPhone">Số điện thoại</label>' +
        '<input class="form-control" id="coPhone" maxlength="15" value="' + OF.escapeHtml(draft.phone || "") + '" placeholder="0901234567" />' +
        '<div class="form-error" id="phoneError"></div>' +
      "</div>" +
      '<div class="form-group"><label class="form-label" for="coPayment">Phương thức thanh toán</label>' +
        '<select class="form-select" id="coPayment">' + methods + "</select>" +
      "</div>" +
      '<div class="checkout-actions">' +
        '<button class="btn" id="btnBackCart">Quay lại giỏ</button>' +
        '<button class="btn btn-primary" id="btnPlaceOrder" disabled>Đặt hàng</button>' +
      "</div>";

    const address = formHost.querySelector("#coAddress");
    const phone = formHost.querySelector("#coPhone");
    const method = formHost.querySelector("#coPayment");
    const addressErr = formHost.querySelector("#addressError");
    const phoneErr = formHost.querySelector("#phoneError");
    const back = formHost.querySelector("#btnBackCart");
    const place = formHost.querySelector("#btnPlaceOrder");
    let addressTouched = false;
    let phoneTouched = false;

    const syncSubmit = () => {
      const aMsg = addressError(address.value);
      const pMsg = phoneError(phone.value);
      const showA = addressTouched || address.value.trim().length > 0;
      const showP = phoneTouched || phone.value.trim().length > 0;
      address.classList.toggle("is-invalid", showA && !!aMsg);
      phone.classList.toggle("is-invalid", showP && !!pMsg);
      if (addressErr) {
        addressErr.textContent = showA ? aMsg : "";
        addressErr.classList.toggle("show", showA && !!aMsg);
      }
      if (phoneErr) {
        phoneErr.textContent = showP ? pMsg : "";
        phoneErr.classList.toggle("show", showP && !!pMsg);
      }
      if (place) place.disabled = !(canSubmit && !aMsg && !pMsg);
    };

    if (address) {
      address.addEventListener("input", () => {
        draft.address = address.value;
        addressTouched = true;
        syncSubmit();
      });
      address.addEventListener("blur", () => {
        addressTouched = true;
        syncSubmit();
      });
    }
    if (phone) {
      phone.addEventListener("input", () => {
        draft.phone = phone.value;
        phoneTouched = true;
        syncSubmit();
      });
      phone.addEventListener("blur", () => {
        phoneTouched = true;
        syncSubmit();
      });
    }
    if (method) method.addEventListener("change", () => { draft.method = method.value; });
    if (back) back.addEventListener("click", () => {
      checkoutMode = false;
      reviewMode = false;
      render();
    });
    if (place) place.addEventListener("click", () => {
      const aMsg = addressError(address.value);
      const pMsg = phoneError(phone.value);
      if (aMsg || pMsg) {
        addressTouched = true;
        phoneTouched = true;
        syncSubmit();
        return;
      }
      draft.address = address.value.trim();
      draft.phone = phone.value.trim();
      draft.method = method.value;
      reviewMode = true;
      render();
    });
    syncSubmit();
  }

  function renderStepC() {
    const formHost = document.getElementById("checkoutReview");
    const summaryHost = document.getElementById("reviewSummary");
    if (!formHost || !summaryHost) return;

    const total = estimateTotal();
    const canSubmit = allAvailable();
    const methodLabel = OF.PAYMENT_METHODS[draft.method] || draft.method;
    const userName = currentUser().full_name || "Khách hàng";

    summaryHost.innerHTML =
      '<div class="checkout-head"><h2>Đơn của bạn</h2><span class="muted">' + cartItems.length + " dòng</span></div>" +
      '<div class="checkout-lines">' + cartItems.map(checkoutLineHTML).join("") + "</div>" +
      '<div class="checkout-total"><span>Tạm tính (ước tính)</span><strong>' + OF.fmt.money(total) + "</strong></div>" +
      '<p class="estimate-note">Giá chính thức do hệ thống chốt khi xác nhận đặt hàng.</p>' +
      (!canSubmit ? unavailableWarning("Hãy quay lại giỏ và xóa sản phẩm không khả dụng trước khi đặt.") : "");

    formHost.innerHTML =
      '<div class="review-block">' +
        '<div class="review-title">Thông tin nhận hàng</div>' +
        '<div class="review-row"><span>Người nhận</span><strong>' + OF.escapeHtml(userName) + "</strong></div>" +
        '<div class="review-row"><span>Số điện thoại</span><strong>' + OF.escapeHtml(draft.phone || "—") + "</strong></div>" +
        '<div class="review-row"><span>Địa chỉ</span><strong>' + OF.escapeHtml(draft.address || "—") + "</strong></div>" +
        '<div class="review-row"><span>Thanh toán</span><strong>' + OF.escapeHtml(methodLabel) + "</strong></div>" +
      "</div>" +
      '<div class="checkout-actions">' +
        '<button class="btn" id="btnBackInfo">Quay lại sửa</button>' +
        '<button class="btn btn-primary" id="btnConfirmOrder"' + (canSubmit ? "" : " disabled") + ">Xác nhận đặt hàng</button>" +
      "</div>";

    const back = formHost.querySelector("#btnBackInfo");
    const confirm = formHost.querySelector("#btnConfirmOrder");
    if (back) back.addEventListener("click", () => {
      reviewMode = false;
      render();
    });
    if (confirm) confirm.addEventListener("click", () => placeOrder(confirm));
  }

  function renderSuccess(host) {
    const order = placedOrder || {};
    const code = order.order_code || "";
    const total = Number(order.total_amount) || 0;
    const method = order.payment_method || draft.method || "cod";
    const hold = order.hold_expires_at ? OF.fmt.dateTime(order.hold_expires_at) : "";
    let guide = "Vui lòng chuẩn bị tiền mặt khi nhận hàng.";
    if (method === "bank") {
      guide = "Nội dung chuyển khoản gợi ý: " + code + ". Vui lòng giữ biên lai để đối chiếu.";
    } else if (method === "momo") {
      guide = "Đơn thanh toán MoMo đang chờ nhân viên xác nhận.";
    }

    host.innerHTML =
      '<div class="order-success">' +
        '<div class="success-icon">' + OF.icon("check") + "</div>" +
        "<h2>Đặt hàng thành công</h2>" +
        '<p class="success-code">Mã đơn: <strong>' + OF.escapeHtml(code) + "</strong></p>" +
        '<div class="success-summary">' +
          '<div class="success-row"><span>Khách hàng</span><strong>' + OF.escapeHtml(order.customer_name || currentUser().full_name || "—") + "</strong></div>" +
          '<div class="success-row"><span>Tổng tiền</span><strong>' + OF.fmt.money(total) + "</strong></div>" +
          '<div class="success-row"><span>Thanh toán</span><strong>' + OF.escapeHtml(OF.PAYMENT_METHODS[method] || method) + "</strong></div>" +
        "</div>" +
        (hold
          ? '<div class="success-hold">⏳ Đơn giữ hàng đến <strong>' + OF.escapeHtml(hold) + "</strong>. Quá hạn đơn sẽ được hệ thống tự động hủy.</div>"
          : '<div class="success-hold">Đơn đang chờ nhân viên xác nhận và xử lý.</div>') +
        '<div class="success-guide">' + OF.escapeHtml(guide) + "</div>" +
        '<div class="success-actions">' +
          '<a class="btn btn-primary" href="orders.html?status=cho_xac_nhan">Xem đơn của tôi</a>' +
          '<a class="btn" href="shop.html">Tiếp tục mua sắm</a>' +
        "</div>" +
      "</div>";
  }

  async function placeOrder(btn) {
    if (placing) return;
    const addressValue = String(draft.address || "").trim();
    const phoneValue = String(draft.phone || "").trim();
    const methodValue = draft.method || "cod";

    const aMsg = addressError(addressValue);
    const pMsg = phoneError(phoneValue);
    if (aMsg || pMsg) {
      reviewMode = false;
      checkoutMode = true;
      render();
      OF.ui.toast("warning", aMsg || pMsg);
      return;
    }
    if (!allAvailable()) {
      OF.ui.toast("warning", "Có sản phẩm không khả dụng, hãy quay lại xóa trước khi đặt.");
      return;
    }

    placing = true;
    if (btn) btn.disabled = true;
    localStorage.setItem(addressKey(), addressValue);

    const body = {
      payment_method: methodValue,
      phone: phoneValue,
      address: addressValue,
      items: cartItems.filter((it) => !it._unavailable).map((it) => ({
        product_id: Number(it.product_id),
        quantity: Number(it.quantity) || 1,
        variant: variantOf(it),
      })),
    };

    // Không gửi customer_name: server tự ép theo token của người đặt.
    const res = await OF.api.post("orders/index.php", body);
    if (!res.success) {
      placing = false;
      if (btn) btn.disabled = false;
      OF.ui.toast("error", res.message || "Đặt hàng thất bại.");
      mergeCart();
      return;
    }

    const code = (res.data && res.data.order_code) || "";
    const orderId = Number(res.data && res.data.id);
    OF.cart.clear();
    checkoutMode = false;
    reviewMode = false;
    successMode = true;
    placedOrder = null;

    if (orderId > 0) {
      const detail = await OF.api.get("orders/item.php", "id=" + orderId);
      if (detail && detail.success && detail.data && detail.data.order) {
        placedOrder = detail.data.order;
      }
    }
    if (!placedOrder) {
      placedOrder = {
        order_code: code,
        total_amount: res.data ? res.data.total_amount : 0,
        payment_method: methodValue,
      };
    }
    cartItems = [];
    placing = false;
    render();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function render() {
    const lineHost = document.getElementById("cartLines");
    const emptyHost = document.getElementById("cartEmpty");
    const right = document.getElementById("checkoutCard");
    const summary = document.getElementById("cartSummaryText");
    const stepA = document.getElementById("cartStepA");
    const stepB = document.getElementById("cartStepB");
    const stepC = document.getElementById("cartStepC");
    const successHost = document.getElementById("cartSuccess");
    if (!lineHost || !emptyHost || !right || !stepA || !stepB || !stepC || !successHost) return;

    renderCheckoutProgress();

    if (successMode) {
      stepA.classList.add("hidden");
      stepB.classList.add("hidden");
      stepC.classList.add("hidden");
      lineHost.innerHTML = "";
      emptyHost.classList.add("hidden");
      emptyHost.innerHTML = "";
      right.classList.add("hidden");
      successHost.classList.remove("hidden");
      if (summary) summary.textContent = "";
      renderSuccess(successHost);
      return;
    }

    function renderCheckoutProgress() {
      const host = document.getElementById("checkoutProgress");
      if (!host) return;
      if (!cartItems.length || successMode) {
        host.classList.add("hidden");
        host.innerHTML = "";
        return;
      }

      const current = reviewMode ? 2 : checkoutMode ? 1 : 0;
      host.classList.remove("hidden");
      host.innerHTML = CHECKOUT_STEPS.map((label, index) => {
        const state = index < current ? " complete" : index === current ? " current" : "";
        const currentAttr = index === current ? ' aria-current="step"' : "";
        return '<li class="checkout-progress-step' + state + '"' + currentAttr + '>' +
          '<span class="checkout-progress-number">' + (index + 1) + "</span>" +
          "<span>" + OF.escapeHtml(label) + "</span>" +
        "</li>";
      }).join("");
    }
    successHost.classList.add("hidden");

    if (!cartItems.length) {
      stepB.classList.add("hidden");
      stepC.classList.add("hidden");
      stepA.classList.remove("hidden");
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

    if (reviewMode) {
      stepA.classList.add("hidden");
      stepB.classList.add("hidden");
      stepC.classList.remove("hidden");
      renderStepC();
      return;
    }

    if (checkoutMode) {
      stepA.classList.add("hidden");
      stepC.classList.add("hidden");
      stepB.classList.remove("hidden");
      renderStepB();
      return;
    }

    stepB.classList.add("hidden");
    stepC.classList.add("hidden");
    stepA.classList.remove("hidden");
    lineHost.innerHTML = cartItems.map(lineHTML).join("");
    bindLineEvents();
    renderStepA();
  }

  async function mergeCart() {
    if (merging) return;
    merging = true;

    const seenKeys = new Set();
    const stored = [];
    OF.cart.read().forEach((it) => {
      const key = OF.cart.lineKey(it.product_id, it.variant);
      if (seenKeys.has(key)) return;
      seenKeys.add(key);
      if (stored.length < MAX_LINES) stored.push(it);
    });
    if (!stored.length) {
      cartItems = [];
      merging = false;
      render();
      return;
    }

    // mỗi dòng giỏ tra riêng item — chắc chắn đúng dù catalog > 50 sp
    const lookupIds = [];
    stored.forEach((it) => {
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
      const variant = variantOf(it);
      const next = Object.assign({}, it, {
        product_id: productId,
        variant: variant || null,
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
          next.stock = variantStockFor(p, variant) ?? (Number(p.stock) || 0);
        }
        return next;
      }

      next.name = p.name;
      next.price = Number(p.price) || 0;
      next.emoji = p.emoji || "📦";
      next.stock = variantStockFor(p, variant) ?? (Number(p.stock) || 0);

      if (next.stock <= 0) {
        next._unavailable = true;
        next._reason = variant ? "Biến thể tạm hết hàng" : "Sản phẩm tạm hết hàng";
      } else if (next.quantity > next.stock) {
        next.quantity = next.stock;
        clamped.push(next.name + (variant ? " (" + variant + ")" : ""));
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
    const params = new URLSearchParams(window.location.search);
    if (params.get("checkout") === "1") {
      checkoutMode = true;
      ensureDraft();
    }
    mergeCart();
  });
})();
