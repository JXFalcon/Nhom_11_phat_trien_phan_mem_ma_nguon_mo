/* ============================================================
   OrderFlow – shop.js (Cửa hàng)
   Danh sách sản phẩm đang bán; bấm card chuyển sang trang chi tiết.
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  const PER_PAGE = 12;

  const urlCategory = new URLSearchParams(window.location.search).get("category_id");

  let page = 1;
  let keyword = "";
  let categoryId = urlCategory && /^\d+$/.test(urlCategory) ? urlCategory : "";
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
    if (categoryId && !categories.some((c) => Number(c.id) === Number(categoryId))) categoryId = "";
    const sel = document.getElementById("shopCategory");
    if (!sel) return;
    const current = sel.value;
    sel.innerHTML = '<option value="">Tất cả danh mục</option>' +
      categories.map((c) => '<option value="' + Number(c.id) + '">' + OF.escapeHtml(c.name) + "</option>").join("");
    sel.value = categoryId || current;
  }

  function cardHTML(p) {
    const stock = Number(p.stock) || 0;
    const out = stock <= 0;
    return '<article class="shop-card" data-shop-id="' + Number(p.id) + '" tabindex="0" role="button" aria-label="Xem chi tiết sản phẩm ' + OF.escapeHtml(p.name) + '">' +
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
      if (id > 0) window.location.href = "shop-item.html?id=" + id;
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
