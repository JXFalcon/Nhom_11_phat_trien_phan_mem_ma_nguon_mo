/* ============================================================
   Shopee – quick-view.js
   Modal "Xem nhanh" cho thẻ sản phẩm ở trang chủ / cửa hàng.
   Mở sản phẩm tại chỗ, chọn phân loại, số lượng và thêm vào giỏ.
   ============================================================ */
(function () {
  "use strict";

  const OF = window.OF = window.OF || {};
  let root = null;
  let bodyEl = null;
  let product = null;
  let selectedVariantId = null;
  let selectedVariantName = "";
  let qty = 1;
  let lastFocus = null;
  let openSeq = 0;

  function money(n) {
    return (Number(n) || 0).toLocaleString("vi-VN") + "₫";
  }

  function discountOf(p) {
    return [10, 15, 20, 25, 30][Number(p.id) % 5];
  }

  function ratingOf(p) {
    return 4.3 + ((Number(p.id) * 37) % 7) / 10;
  }

  function soldOf(p) {
    return 100 + (Number(p.id) * 137) % 900;
  }

  function starsHtml(rating) {
    let html = "";
    for (let i = 1; i <= 5; i++) {
      html += '<i class="fa-solid fa-star ' + (i <= Math.round(rating) ? "" : "is-empty") + '"></i>';
    }
    return html;
  }

  function ensureRoot() {
    if (root) return;
    root = document.createElement("div");
    root.id = "sfQuickView";
    root.className = "sf-qv";
    root.setAttribute("aria-hidden", "true");
    root.innerHTML =
      '<div class="sf-qv-backdrop" data-qv-close></div>' +
      '<div class="sf-qv-dialog" role="dialog" aria-modal="true" aria-labelledby="sfQvTitle">' +
        '<button type="button" class="sf-qv-close" data-qv-close aria-label="Đóng xem nhanh"><i class="fa-solid fa-xmark"></i></button>' +
        '<div id="sfQvBody" class="sf-qv-body"></div>' +
      "</div>";
    document.body.appendChild(root);
    bodyEl = document.getElementById("sfQvBody");

    root.addEventListener("click", function (e) {
      if (e.target.closest("[data-qv-close]")) close();
    });
    document.addEventListener("keydown", function (e) {
      if (!root.classList.contains("is-open")) return;
      if (e.key === "Escape") { close(); return; }
      if (e.key !== "Tab") return;
      const focusable = root.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });
  }

  function show() {
    ensureRoot();
    lastFocus = document.activeElement;
    root.classList.add("is-open");
    root.setAttribute("aria-hidden", "false");
    document.body.classList.add("sf-qv-lock");
    const closeBtn = root.querySelector(".sf-qv-close");
    if (closeBtn) closeBtn.focus();
  }

  function close() {
    if (!root) return;
    openSeq++;
    root.classList.remove("is-open");
    root.setAttribute("aria-hidden", "true");
    document.body.classList.remove("sf-qv-lock");
    product = null;
    selectedVariantId = null;
    selectedVariantName = "";
    qty = 1;
    if (lastFocus && typeof lastFocus.focus === "function") lastFocus.focus();
  }

  function currentVariant() {
    if (!product || !Array.isArray(product.variant_rows)) return null;
    return product.variant_rows.find(function (v) {
      return Number(v.id) === Number(selectedVariantId);
    }) || null;
  }

  function currentStock() {
    const variant = currentVariant();
    return Number(variant ? variant.stock : (product ? product.stock : 0));
  }

  function currentPrice() {
    const variant = currentVariant();
    return Number(variant && variant.price != null ? variant.price : (product ? product.price : 0));
  }

  function showMessage(message, ok) {
    const el = document.getElementById("sfQvMsg");
    if (!el) return;
    el.textContent = message;
    el.classList.remove("hidden");
    el.className = ok ? "mt-2 text-xs text-green-600" : "mt-2 text-xs text-red-500";
    clearTimeout(showMessage.timer);
    showMessage.timer = setTimeout(function () { el.classList.add("hidden"); }, 2800);
  }

  function updateSummary() {
    if (!product) return;
    const stock = currentStock();
    const price = currentPrice();
    const discount = discountOf(product);
    const oldPrice = Math.round(price * (1 + discount / 100) / 1000) * 1000;

    const priceEl = document.getElementById("sfQvPrice");
    if (priceEl) priceEl.textContent = money(price);
    const oldEl = document.getElementById("sfQvOldPrice");
    if (oldEl) oldEl.textContent = money(oldPrice);
    const discountEl = document.getElementById("sfQvDiscount");
    if (discountEl) discountEl.textContent = "-" + discount + "%";
    const stockNote = document.getElementById("sfQvStockNote");
    if (stockNote) {
      stockNote.textContent = stock > 0
        ? "Còn " + stock + " sản phẩm có sẵn"
        : "Sản phẩm đã hết hàng";
    }
    const qtyInput = document.getElementById("sfQvQty");
    if (qtyInput) qtyInput.value = qty;
    const minus = document.getElementById("sfQvMinus");
    if (minus) minus.disabled = qty <= 1;
    const plus = document.getElementById("sfQvPlus");
    if (plus) plus.disabled = qty >= Math.max(1, stock) || stock <= 0;
    const addBtn = document.getElementById("sfQvAdd");
    if (addBtn) addBtn.disabled = stock <= 0;
  }

  function selectVariant(id, name) {
    selectedVariantId = id;
    selectedVariantName = name || "";
    qty = 1;
    document.querySelectorAll("#sfQvVariants [data-vid]").forEach(function (btn) {
      btn.classList.toggle("is-active", Number(btn.getAttribute("data-vid")) === Number(id));
    });
    updateSummary();
  }

  function bindMedia() {
    const main = document.getElementById("sfQvMainImage");
    const emoji = document.getElementById("sfQvEmoji");
    const thumbs = document.getElementById("sfQvThumbs");
    if (!thumbs) return;
    thumbs.querySelectorAll("[data-qv-src]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        thumbs.querySelectorAll("[data-qv-src]").forEach(function (b) { b.classList.remove("is-active"); });
        btn.classList.add("is-active");
        const src = btn.getAttribute("data-qv-src");
        if (main && emoji) {
          main.onload = function () { main.classList.remove("hidden"); emoji.classList.add("hidden"); };
          main.onerror = function () { main.classList.add("hidden"); emoji.classList.remove("hidden"); };
          main.src = src;
        }
      });
    });
  }

  function bindVariants() {
    const wrap = document.getElementById("sfQvVariants");
    if (!wrap) return;
    wrap.querySelectorAll("[data-vid]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        if (btn.classList.contains("is-disabled")) return;
        selectVariant(btn.getAttribute("data-vid"), btn.getAttribute("data-variant"));
      });
    });
  }

  function bindQty() {
    const minus = document.getElementById("sfQvMinus");
    const plus = document.getElementById("sfQvPlus");
    const input = document.getElementById("sfQvQty");
    if (minus) minus.addEventListener("click", function () {
      if (qty > 1) { qty--; updateSummary(); }
    });
    if (plus) plus.addEventListener("click", function () {
      const max = Math.max(1, currentStock());
      if (qty < max) { qty++; updateSummary(); }
    });
    if (input) input.addEventListener("input", function () {
      const max = Math.max(1, currentStock());
      qty = Math.max(1, Math.min(max, Number(input.value) || 1));
      updateSummary();
    });
  }

  async function refreshCartBadge() {
    if (!OF.session || !OF.session.getToken()) return;
    const res = await OF.api.get("carts/index.php");
    if (!res || !res.success) return;
    const count = Number(res.data && res.data.item_count ? res.data.item_count : 0);
    const badge = document.getElementById("cartBadge");
    if (!badge) return;
    if (count > 0) {
      badge.textContent = count;
      badge.classList.remove("scale-0");
      badge.classList.add("scale-100");
    } else {
      badge.classList.remove("scale-100");
      badge.classList.add("scale-0");
    }
  }

  async function addToCart() {
    if (!product) return;
    const stock = currentStock();
    if (stock <= 0) {
      showMessage("Sản phẩm đã hết hàng.", false);
      return;
    }
    if (Array.isArray(product.variant_rows) && product.variant_rows.length && !selectedVariantId) {
      showMessage("Vui lòng chọn phân loại sản phẩm.", false);
      return;
    }
    if (!OF.session || !OF.session.getToken()) {
      showMessage("Vui lòng đăng nhập để mua sắm!", false);
      setTimeout(function () { location.href = "login.php"; }, 700);
      return;
    }
    const body = { product_id: product.id, quantity: qty };
    if (selectedVariantId) {
      body.variant_id = selectedVariantId;
      body.variant = selectedVariantName;
    }
    const btn = document.getElementById("sfQvAdd");
    if (btn) btn.disabled = true;
    const res = await OF.api.post("carts/index.php", body);
    if (btn) btn.disabled = stock <= 0;
    if (!res || !res.success) {
      showMessage((res && res.message) || "Không thêm được vào giỏ hàng!", false);
      return;
    }
    showMessage("Đã thêm vào giỏ hàng!", true);
    if (window.sfToast) window.sfToast("Đã thêm vào giỏ hàng!");
    refreshCartBadge();
  }

  function render() {
    const esc = OF.escapeHtml;
    const images = Array.isArray(product.images) ? product.images.filter(Boolean) : [];
    const cover = images[0] || product.cover_image || "";
    const emoji = esc(product.emoji || "📦");
    const rating = ratingOf(product);
    const discount = discountOf(product);
    const price = Number(product.price) || 0;
    const oldPrice = Math.round(price * (1 + discount / 100) / 1000) * 1000;
    const stock = Number(product.stock) || 0;

    const media = cover
      ? '<img id="sfQvMainImage" src="' + esc(cover) + '" alt="' + esc(product.name) + '" onerror="this.classList.add(\'hidden\');var e=document.getElementById(\'sfQvEmoji\');if(e)e.classList.remove(\'hidden\')" />' +
        '<span id="sfQvEmoji" class="sf-product-emoji hidden" style="font-size:110px">' + emoji + "</span>"
      : '<span id="sfQvEmoji" class="sf-product-emoji" style="font-size:110px">' + emoji + "</span>";

    const thumbs = images.length > 1
      ? '<div id="sfQvThumbs" class="flex gap-2 mt-3 overflow-x-auto pb-1">' +
          images.map(function (src, i) {
            return '<button type="button" class="sf-thumb' + (i === 0 ? " is-active" : "") + '" data-qv-src="' + esc(src) + '" title="Ảnh ' + (i + 1) + '">' +
              '<img src="' + esc(src) + '" alt="' + esc(product.name + " - ảnh " + (i + 1)) + '" loading="lazy" /></button>';
          }).join("") +
        "</div>"
      : "";

    const variants = Array.isArray(product.variant_rows) ? product.variant_rows : [];
    const variantsBlock = variants.length
      ? '<div class="mb-4">' +
          '<label class="flex items-center gap-2 text-[12.5px] font-semibold text-gray-600 mb-2.5">Phân loại <span class="text-gray-400 font-normal">— chọn phiên bản</span></label>' +
          '<div id="sfQvVariants" class="flex flex-wrap gap-2">' +
            variants.map(function (v) {
              const vPrice = Number(v.price != null ? v.price : product.price);
              const out = Number(v.stock) <= 0;
              return '<button type="button" data-vid="' + v.id + '" data-variant="' + esc(v.name) + '" class="sf-variant' + (out ? " is-disabled" : "") + '">' +
                esc(v.name) + " · " + money(vPrice) + (out ? " (hết)" : "") + "</button>";
            }).join("") +
          "</div>" +
        "</div>"
      : "";

    bodyEl.innerHTML =
      '<div class="grid grid-cols-1 md:grid-cols-2 gap-5 md:gap-7">' +
        "<div>" +
          '<div class="sf-qv-media" data-tone="' + (Number(product.category_id) || 0) + '">' + media +
            '<span class="sf-badge sf-badge-sale" style="position:absolute;top:10px;left:10px"><i class="fa-solid fa-bolt"></i> -' + discount + "%</span>" +
          "</div>" +
          thumbs +
        "</div>" +
        '<div class="min-w-0">' +
          '<span class="sf-badge sf-badge-cat mb-2">' + esc(product.category_name || "Khác") + "</span>" +
          '<h2 id="sfQvTitle" class="text-lg font-extrabold leading-snug text-gray-900">' + esc(product.name) + "</h2>" +
          '<div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-[11.5px] text-gray-400">' +
            '<span class="inline-flex items-center gap-1.5"><span class="sf-stars">' + starsHtml(rating) + '</span> <span class="text-gray-600 font-semibold">' + rating.toFixed(1) + "</span></span>" +
            '<span class="text-gray-200">|</span><span>Đã bán <span class="text-gray-600 font-semibold">' + soldOf(product) + "+</span></span>" +
            '<span class="text-gray-200">|</span><span class="inline-flex items-center gap-1"><i class="fa-solid fa-location-dot text-[10px]"></i> TP.HCM</span>' +
          "</div>" +
          '<div class="sf-pdp-pricebox my-4">' +
            '<span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Giá bán</span>' +
            '<span id="sfQvPrice" class="sf-pdp-price">' + money(price) + "</span>" +
            '<span id="sfQvDiscount" class="sf-badge sf-badge-sale">-' + discount + "%</span>" +
            '<span id="sfQvOldPrice" class="sf-price-old text-sm">' + money(oldPrice) + "</span>" +
          "</div>" +
          '<p id="sfQvStockNote" class="text-[11.5px] text-gray-400 mb-4">' + (stock > 0 ? "Còn " + stock + " sản phẩm có sẵn" : "Sản phẩm đã hết hàng") + "</p>" +
          variantsBlock +
          '<div class="flex items-center gap-4 flex-wrap mb-4">' +
            '<div class="sf-qty">' +
              '<button id="sfQvMinus" type="button" aria-label="Giảm số lượng">−</button>' +
              '<input id="sfQvQty" value="1" inputmode="numeric" aria-label="Số lượng" />' +
              '<button id="sfQvPlus" type="button" aria-label="Tăng số lượng">+</button>' +
            "</div>" +
            '<span class="text-[11.5px] text-gray-400"><i class="fa-solid fa-truck-fast text-emerald-500"></i> Giao nhanh 2h</span>' +
          "</div>" +
          '<div class="flex flex-col sm:flex-row gap-2.5">' +
            '<button id="sfQvAdd" type="button" class="sf-btn sf-btn-primary flex-1"><i class="fa-solid fa-cart-plus"></i> Thêm vào giỏ hàng</button>' +
            '<a href="product.php?id=' + product.id + '" class="sf-btn sf-btn-soft flex-1 text-center">Xem chi tiết <i class="fa-solid fa-arrow-right text-[10px]"></i></a>' +
          "</div>" +
          '<p id="sfQvMsg" class="hidden mt-2 text-xs"></p>' +
          '<div class="grid grid-cols-2 gap-2 mt-4">' +
            '<div class="sf-benefit"><i class="fa-solid fa-rotate-left"></i><span><strong class="block text-gray-700 text-[11.5px]">Đổi trả 7 ngày</strong>Thủ tục nhanh gọn</span></div>' +
            '<div class="sf-benefit"><i class="fa-solid fa-shield-halved"></i><span><strong class="block text-gray-700 text-[11.5px]">Chính hãng 100%</strong>Bảo vệ người mua</span></div>' +
          "</div>" +
        "</div>" +
      "</div>";

    bindMedia();
    bindVariants();
    bindQty();
    const addBtn = document.getElementById("sfQvAdd");
    if (addBtn) addBtn.addEventListener("click", addToCart);
    if (variants.length) {
      const first = variants.find(function (v) { return Number(v.stock) > 0; }) || variants[0];
      selectVariant(first.id, first.name);
    } else {
      selectedVariantId = null;
      selectedVariantName = "";
      updateSummary();
    }
  }

  async function open(id) {
    ensureRoot();
    const seq = ++openSeq;
    show();
    product = null;
    selectedVariantId = null;
    selectedVariantName = "";
    qty = 1;
    bodyEl.innerHTML =
      '<div class="flex items-center justify-center py-20 text-sm text-gray-400">' +
        '<i class="fa-solid fa-circle-notch fa-spin text-shopee mr-2"></i> Đang tải sản phẩm...' +
      "</div>";
    const res = await OF.api.get("products/item.php", "id=" + encodeURIComponent(id) + "&public=1");
    if (seq !== openSeq || !root.classList.contains("is-open")) return;
    if (!res || !res.success || !res.data) {
      bodyEl.innerHTML =
        '<div class="py-16 text-center">' +
          '<div class="w-14 h-14 mx-auto mb-3 rounded-full bg-orange-50 text-shopee flex items-center justify-center text-xl"><i class="fa-solid fa-triangle-exclamation"></i></div>' +
          '<p class="text-sm font-semibold text-gray-700 mb-1">Không tải được sản phẩm</p>' +
          '<p class="text-xs text-gray-400 mb-4">' + OF.escapeHtml((res && res.message) || "Vui lòng thử lại sau.") + "</p>" +
          '<button type="button" class="sf-btn sf-btn-soft" data-qv-close>Đóng</button>' +
        "</div>";
      return;
    }
    product = res.data;
    render();
  }

  OF.quickView = { open: open, close: close };
})();
