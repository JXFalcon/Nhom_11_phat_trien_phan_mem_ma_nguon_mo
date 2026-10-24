/* ============================================================
   OrderFlow – shop.js (Cửa hàng)
   Danh sách sản phẩm đang bán + chi tiết + thêm vào giỏ.
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  const PER_PAGE = 12;

  let page = 1;
  let keyword = "";
  let categoryId = "";
  let categories = [];

  const EMOJI_FALLBACK = "📦";

  function gridEl() { return document.getElementById("shopGrid"); }
  function statusEl() { return document.getElementById("shopStatus"); }
  function pagerEl() { return document.getElementById("shopPagination"); }

  function skeletonHTML() {
    return new Array(6).fill('<div class="shop-skeleton" aria-hidden="true"></div>').join("");
  }

  async function loadCategories() {
    const res = await OF.api.get("categories/index.php");
    if (!res.success) return;
    categories = Array.isArray(res.data) ? res.data : [];
    const sel = document.getElementById("shopCategory");
    if (!sel) return;
    const current = sel.value;
    sel.innerHTML = '<option value="">Tất cả danh mục</option>' +
      categories.map((c) => '<option value="' + Number(c.id) + '">' + OF.escapeHtml(c.name) + "</option>").join("");
    sel.value = current;
  }

  function cardHTML(p) {
    const stock = Number(p.stock) || 0;
    const out = stock <= 0;
    return '<article class="shop-card" data-shop-id="' + Number(p.id) + '" tabindex="0" role="button" aria-label="' + OF.escapeHtml(p.name) + '">' +
      '<div class="shop-thumb' + (out ? " out" : "") + '">' + OF.escapeHtml(p.emoji || EMOJI_FALLBACK) + "</div>" +
      '<div class="shop-body">' +
        '<div class="shop-name">' + OF.escapeHtml(p.name) + "</div>" +
        '<div class="shop-price">' + OF.fmt.money(p.price) + "</div>" +
        '<div class="shop-stock' + (stock > 0 && stock < 10 ? " warning" : "") + '">' +
          (out ? "Hết hàng" : "Còn " + stock + " sản phẩm") +
        "</div>" +
      "</div>" +
    "</article>";
  }

  async function loadProducts() {
    const grid = gridEl();
    const status = statusEl();
    const pager = pagerEl();
    if (!grid) return;

    status.textContent = "Đang tải…";
    grid.innerHTML = skeletonHTML();
    pager.innerHTML = "";

    const q = new URLSearchParams();
    q.set("status", "active");
    if (keyword.trim()) q.set("q", keyword.trim());
    if (categoryId) q.set("category_id", categoryId);
    q.set("page", String(page));
    q.set("per_page", String(PER_PAGE));

    const res = await OF.api.get("products/index.php", q.toString());
    if (!res.success) {
      status.textContent = "";
      grid.innerHTML = OF.ui.emptyStateHTML("Không tải được cửa hàng", res.message || "Vui lòng thử lại sau.");
      return;
    }

    const items = res.data.items || [];
    const total = Number(res.data.total) || 0;
    status.textContent = total + " sản phẩm";

    if (!items.length) {
      grid.innerHTML = OF.ui.emptyStateHTML("Không tìm thấy sản phẩm", "Hãy thử đổi từ khóa hoặc chọn danh mục khác.");
      return;
    }

    grid.innerHTML = items.map(cardHTML).join("");

    const openByCard = (card) => {
      const id = Number(card.getAttribute("data-shop-id"));
      const product = items.find((p) => Number(p.id) === id);
      if (product) openDetail(product);
    };
    grid.querySelectorAll("[data-shop-id]").forEach((card) => {
      card.addEventListener("click", () => openByCard(card));
      card.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          openByCard(card);
        }
      });
    });

    OF.ui.renderPagination(pager, total, page, PER_PAGE, (p) => {
      page = p;
      loadProducts();
    });
  }

  function variantHTML(p) {
    const list = String(p.variants || "").split(";").map((v) => v.trim()).filter(Boolean);
    if (!list.length) return "";
    return '<div class="shop-chips">' +
      list.map((v) => '<span class="shop-chip">' + OF.escapeHtml(v) + "</span>").join("") +
      "</div>";
  }

  function openDetail(p) {
    const stock = Number(p.stock) || 0;
    const out = stock <= 0;
    const body =
      '<div class="shop-detail">' +
        '<div class="shop-detail-thumb' + (out ? " out" : "") + '">' + OF.escapeHtml(p.emoji || EMOJI_FALLBACK) + "</div>" +
        '<div class="shop-detail-info">' +
          '<h3 class="shop-detail-name">' + OF.escapeHtml(p.name) + "</h3>" +
          '<div class="shop-price">' + OF.fmt.money(p.price) + "</div>" +
          '<div class="shop-stock' + (stock > 0 && stock < 10 ? " warning" : "") + '">' +
            (out ? "Hết hàng" : "Còn " + stock + " sản phẩm") +
          "</div>" +
          variantHTML(p) +
          '<div class="form-group mt-16"><label class="form-label" for="shopQty">Số lượng</label>' +
            '<input class="form-control" id="shopQty" type="number" min="1" max="' + Math.max(stock, 1) + '" step="1" value="1"' + (out ? " disabled" : "") + " />" +
          "</div>" +
        "</div>" +
      "</div>";

    OF.ui.openModal({
      title: "Chi tiết sản phẩm",
      size: "md",
      body,
      footer:
        '<button class="btn" data-close>Đóng</button>' +
        '<button class="btn btn-primary" id="shopAddCart"' + (out ? " disabled" : "") + ">" +
          (out ? "Hết hàng" : "Thêm vào giỏ") +
        "</button>",
      onOpen(api) {
        const btn = api.el.querySelector("#shopAddCart");
        if (!btn || out) return;
        btn.addEventListener("click", () => {
          const qtyInput = api.el.querySelector("#shopQty");
          const qty = Math.max(1, Math.min(stock, Number(qtyInput.value) || 1));
          OF.cart.add(p.id, qty, p);
          OF.ui.toast("success", "Đã thêm “" + p.name + "” vào giỏ hàng.");
          api.close();
        });
      },
    });
  }

  document.addEventListener("DOMContentLoaded", async function () {
    await loadCategories();
    await loadProducts();

    const search = document.getElementById("shopSearch");
    let timer = null;
    if (search) {
      search.addEventListener("input", (e) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
          keyword = e.target.value;
          page = 1;
          loadProducts();
        }, 300);
      });
    }

    const cat = document.getElementById("shopCategory");
    if (cat) {
      cat.addEventListener("change", (e) => {
        categoryId = e.target.value;
        page = 1;
        loadProducts();
      });
    }
  });
})();
