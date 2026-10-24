/* ============================================================
   OrderFlow – shop_item.js (Chi tiết sản phẩm)
   Đọc id từ URL, tải chi tiết qua products/item.php, chọn số lượng,
   thêm vào giỏ hoặc mua ngay, và gợi ý sản phẩm cùng danh mục.
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  const EMOJI_FALLBACK = "📦";

  let qty = 1;
  let selectedVariant = null;

  const pItemEl = () => document.getElementById("pItem");

  function productIdFromQuery() {
    const raw = new URLSearchParams(window.location.search).get("id");
    const id = Number(raw);
    return Number.isInteger(id) && id > 0 ? id : 0;
  }

  function hideRelated() {
    const section = document.getElementById("pRelatedSection");
    if (section) section.classList.add("hidden");
  }

  function notFound() {
    const host = pItemEl();
    if (!host) return;
    host.innerHTML =
      OF.ui.emptyStateHTML("Không tìm thấy sản phẩm", "Sản phẩm không tồn tại hoặc đã bị gỡ khỏi cửa hàng.") +
      '<div class="pitem-empty-action"><a class="btn btn-primary" href="shop.html">Về cửa hàng</a></div>';
    hideRelated();
  }

  function breadcrumbHTML(p) {
    let html = '<nav class="pitem-breadcrumb" aria-label="Đường dẫn">' +
      '<a href="shop.html">‹ Cửa hàng</a>';
    if (p.category_name) {
      const catHref = p.category_id != null
        ? "shop.html?category_id=" + encodeURIComponent(String(p.category_id))
        : "shop.html";
      html += '<span class="sep">/</span><a href="' + catHref + '">' + OF.escapeHtml(p.category_name) + "</a>";
    }
    html += '<span class="sep">/</span><span class="current">' + OF.escapeHtml(p.name) + "</span></nav>";
    return html;
  }

  function variantsHTML(p) {
    const rows = Array.isArray(p.variant_rows) ? p.variant_rows : [];
    if (rows.length) {
      return '<div class="shop-chips pitem-variants" id="pVariants">' +
        rows.map((v) => {
          const name = String(v.name == null ? "" : v.name);
          const stock = Math.max(0, Number(v.stock) || 0);
          const disabled = stock <= 0;
          const active = !disabled && selectedVariant === name;
          return '<button type="button" class="shop-chip' + (active ? " active" : "") + (disabled ? " is-out" : "") + '"' +
            ' data-variant="' + OF.escapeHtml(name) + '"' + (disabled ? " disabled" : "") +
            ' aria-pressed="' + (active ? "true" : "false") + '">' +
            OF.escapeHtml(name) + ' <span class="shop-chip-stock">• ' + (disabled ? "Hết" : "Còn " + stock) + "</span>" +
          "</button>";
        }).join("") +
        "</div>";
    }
    const list = String(p.variants || "").split(";").map((v) => v.trim()).filter(Boolean);
    if (!list.length) return "";
    return '<div class="shop-chips pitem-variants">' +
      list.map((v) => '<span class="shop-chip">' + OF.escapeHtml(v) + "</span>").join("") +
      "</div>";
  }

  function variantStock(p) {
    const rows = Array.isArray(p.variant_rows) ? p.variant_rows : [];
    if (!selectedVariant) return null;
    const row = rows.find((v) => String(v.name) === selectedVariant);
    if (!row) return null;
    const stock = Number(row.stock);
    return Number.isFinite(stock) ? Math.max(0, stock) : null;
  }

  function maxQtyFor(p) {
    const stock = variantStock(p);
    return stock != null ? stock : Math.max(0, Number(p.stock) || 0);
  }

  function bindVariants(p, onVariantChange) {
    const host = document.getElementById("pVariants");
    if (!host) return;
    host.querySelectorAll("[data-variant]").forEach((chip) => {
      chip.addEventListener("click", () => {
        if (chip.disabled) return;
        selectedVariant = chip.getAttribute("data-variant") || null;
        host.querySelectorAll("[data-variant]").forEach((c) => {
          const on = c === chip;
          c.classList.toggle("active", on);
          c.setAttribute("aria-pressed", on ? "true" : "false");
        });
        if (typeof onVariantChange === "function") onVariantChange();
      });
    });
  }

  function stockBadge(stock, out) {
    if (out) return '<span class="badge badge-red">Hết hàng</span>';
    if (stock < 10) return '<span class="badge badge-orange">Còn lại ' + stock + "</span>";
    return '<span class="badge badge-green">Còn lại ' + stock + "</span>";
  }

  function bindQty(p, out) {
    // Giới hạn hiển thị theo tồn kho biến thể đang chọn nếu có, fallback tồn kho tổng.
    // Server vẫn chốt số lượng theo products.stock khi tạo đơn.
    const dec = document.querySelector("#pItem [data-dec]");
    const inc = document.querySelector("#pItem [data-inc]");
    const label = document.getElementById("pQty");
    if (!dec || !inc || !label) return null;

    const sync = () => {
      const max = Math.max(1, maxQtyFor(p));
      qty = Math.max(1, Math.min(qty, max));
      label.textContent = String(qty);
      dec.disabled = out || qty <= 1;
      inc.disabled = out || qty >= max;
    };

    dec.addEventListener("click", () => {
      qty = Math.max(1, qty - 1);
      sync();
    });
    inc.addEventListener("click", () => {
      qty = Math.min(Math.max(maxQtyFor(p), 1), qty + 1);
      sync();
    });
    sync();
    return sync;
  }

  function addToCart(p, qtyValue) {
    const limit = maxQtyFor(p);
    if (p.status !== "active" || limit <= 0) return false;
    const amount = Math.max(1, Math.min(limit, Number(qtyValue) || 1));
    // Snapshot stock theo biến thể để giỏ hàng giới hạn đúng; server vẫn kiểm tồn kho tổng.
    const snap = Object.assign({}, p, { stock: limit });
    OF.cart.add(p.id, amount, selectedVariant, snap);
    OF.cart.refreshBadge();
    return true;
  }

  function bindActions(p, out) {
    const addBtn = document.getElementById("pAddCart");
    const buyBtn = document.getElementById("pBuyNow");

    if (addBtn && !out) {
      addBtn.addEventListener("click", () => {
        if (!addToCart(p, qty)) return;
        OF.ui.toast("success", selectedVariant
          ? "Đã thêm vào giỏ: " + selectedVariant
          : "Đã thêm “" + p.name + "” vào giỏ hàng.");
      });
    }
    if (buyBtn && !out) {
      buyBtn.addEventListener("click", () => {
        if (!addToCart(p, qty)) return;
        window.location.href = "cart.html?checkout=1";
      });
    }
  }

  function renderProduct(p) {
    const host = pItemEl();
    if (!host) return;
    const stock = Math.max(0, Number(p.stock) || 0);
    const out = p.status !== "active" || stock <= 0;
    const emoji = p.emoji || EMOJI_FALLBACK;
    const description = String(p.description || "").trim();
    const variantRows = Array.isArray(p.variant_rows) ? p.variant_rows : [];
    const firstAvailable = variantRows.find((v) => Math.max(0, Number(v.stock) || 0) > 0);
    selectedVariant = firstAvailable ? String(firstAvailable.name) : null;

    host.innerHTML =
      breadcrumbHTML(p) +
      '<div class="pitem-layout">' +
        '<section class="card pitem-media-card">' +
          '<div class="shop-thumb pitem-thumb' + (out ? " out" : "") + '">' + OF.escapeHtml(emoji) + "</div>" +
        "</section>" +
        '<aside class="card pitem-buybox">' +
          '<h1 class="pitem-name">' + OF.escapeHtml(p.name) + "</h1>" +
          '<div class="pitem-meta">' +
            '<span>SKU: <strong>' + OF.escapeHtml(p.sku || "—") + "</strong></span>" +
            (p.category_name
              ? '<span class="pitem-meta-sep">·</span><span>' + OF.escapeHtml(p.category_name) + "</span>"
              : "") +
          "</div>" +
          (description ? '<p class="pitem-desc">' + OF.escapeHtml(description) + "</p>" : "") +
          '<div class="pitem-price">' + OF.fmt.money(p.price) + "</div>" +
          '<div class="pitem-stock-row">' + stockBadge(stock, out) +
            (p.status !== "active" ? '<span class="muted">Sản phẩm đã ngừng bán</span>' : "") +
          "</div>" +
          variantsHTML(p) +
          '<div class="pitem-qty">' +
            '<span class="form-label mb-0">Số lượng</span>' +
            '<div class="qty-stepper">' +
              '<button type="button" data-dec aria-label="Giảm số lượng"' + (out ? " disabled" : "") + ">−</button>" +
              '<span id="pQty">1</span>' +
              '<button type="button" data-inc aria-label="Tăng số lượng"' + (out ? " disabled" : "") + ">+</button>" +
            "</div>" +
          "</div>" +
          '<div class="pitem-actions">' +
            '<button type="button" class="btn pitem-btn-cart" id="pAddCart"' + (out ? " disabled" : "") + ">" +
              (out ? "Hết hàng" : "🧺 Thêm vào giỏ hàng") +
            "</button>" +
            '<button type="button" class="btn pitem-btn-buy" id="pBuyNow"' + (out ? " disabled" : "") + ">" +
              (out ? "Hết hàng" : "⚡ Mua ngay") +
            "</button>" +
          "</div>" +
          '<p class="pitem-order-note">Giá và tồn kho được hệ thống xác nhận khi tạo đơn hàng.</p>' +
        "</aside>" +
      "</div>";

    qty = 1;
    const syncQty = bindQty(p, out);
    bindVariants(p, syncQty);
    bindActions(p, out);
    document.title = p.name + " – OrderFlow";
  }

  function relatedCardHTML(p) {
    const stock = Number(p.stock) || 0;
    const out = p.status !== "active" || stock <= 0;
    return '<article class="shop-card pitem-card" data-related-id="' + Number(p.id) + '" tabindex="0" role="button" aria-label="Xem chi tiết sản phẩm ' + OF.escapeHtml(p.name) + '">' +
      '<div class="shop-thumb' + (out ? " out" : "") + '">' + OF.escapeHtml(p.emoji || EMOJI_FALLBACK) + "</div>" +
      '<div class="shop-body">' +
        '<div class="shop-name">' + OF.escapeHtml(p.name) + "</div>" +
        '<div class="shop-price">' + OF.fmt.money(p.price) + "</div>" +
        '<div class="shop-stock' + (stock > 0 && stock < 10 ? " warning" : "") + '">' +
          (out ? "Hết hàng" : "Còn " + stock + " sản phẩm") +
        "</div>" +
        '<div class="shop-card-action" aria-hidden="true">Xem chi tiết <span>→</span></div>' +
      "</div>" +
    "</article>";
  }

  function goToProduct(id) {
    window.location.href = "shop-item.html?id=" + Number(id);
  }

  async function loadRelated(p) {
    const section = document.getElementById("pRelatedSection");
    const grid = document.getElementById("pRelated");
    if (!section || !grid) return;
    if (p.category_id == null) { hideRelated(); return; }

    const q = new URLSearchParams();
    q.set("category_id", String(p.category_id));
    q.set("per_page", "8");
    q.set("status", "active");

    const res = await OF.api.get("products/index.php", q.toString());
    if (!res.success) { hideRelated(); return; }

    const items = (res.data.items || [])
      .filter((it) => Number(it.id) !== Number(p.id))
      .slice(0, 8);
    if (!items.length) { hideRelated(); return; }

    section.classList.remove("hidden");
    grid.innerHTML = items.map(relatedCardHTML).join("");
    grid.querySelectorAll("[data-related-id]").forEach((card) => {
      const id = Number(card.getAttribute("data-related-id"));
      card.addEventListener("click", () => goToProduct(id));
      card.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          goToProduct(id);
        }
      });
    });
  }

  async function init() {
    const id = productIdFromQuery();
    if (!id) { notFound(); return; }

    const host = pItemEl();
    if (host) {
      host.innerHTML = '<div class="pitem-loading">' +
        '<div class="shop-skeleton pitem-skeleton-media" aria-hidden="true"></div>' +
        '<div class="shop-skeleton pitem-skeleton-info" aria-hidden="true"></div>' +
      "</div>";
    }

    const res = await OF.api.get("products/item.php", "id=" + id);
    if (!res.success || !res.data) {
      notFound();
      return;
    }

    const product = res.data;
    renderProduct(product);
    loadRelated(product);
  }

  document.addEventListener("DOMContentLoaded", init);
})();
