/* ============================================================
   OrderFlow – products.js (Quản lý sản phẩm)
   Giai đoạn 2: CRUD qua API MySQL (api/products/*).
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  const PER_PAGE = 8;

  let page = 1;
  let keyword = "";
  let categoryId = "";
  let statusFilter = "";
  let categories = [];
  let total = 0;

  const EMOJIS = ["📱", "💻", "📟", "🎧", "⌚", "🖱️", "⌨️", "🖥️", "🔊", "🧥", "👟", "🍚", "🔥", "📷", "🎮"];
  const isAdmin = () => (OF.session.getUser() || {}).role === "admin";

  async function loadCategories() {
    const res = await OF.api.get("categories/index.php");
    if (res.success) {
      categories = res.data || [];
      fillCategoryOptions();
      fillFormCategoryOptions();
    }
  }

  async function loadProducts() {
    const q = new URLSearchParams();
    if (keyword.trim()) q.set("q", keyword.trim());
    if (categoryId) q.set("category_id", categoryId);
    if (statusFilter) q.set("status", statusFilter);
    q.set("page", String(page));
    q.set("per_page", String(PER_PAGE));
    const res = await OF.api.get("products/index.php", q.toString());
    if (!res.success) { OF.ui.toast("error", res.message || "Không tải được sản phẩm."); return; }
    total = res.data.total;
    render(res.data.items, total);
  }

  function fillCategoryOptions() {
    const sel = document.getElementById("categoryFilter");
    const current = sel.value;
    sel.innerHTML = '<option value="">Tất cả danh mục</option>' +
      categories.map((c) => '<option value="' + c.id + '">' + OF.escapeHtml(c.name) + "</option>").join("");
    sel.value = current;
  }

  function fillFormCategoryOptions(selectedId) {
    return categories.map((c) =>
      '<option value="' + c.id + '"' + (selectedId != null && String(selectedId) === String(c.id) ? " selected" : "") + ">" +
      OF.escapeHtml(c.name) + "</option>"
    ).join("");
  }

  function render(items, total) {
    const pages = Math.max(1, Math.ceil(total / PER_PAGE));
    if (page > pages) page = pages;
    const tbody = document.getElementById("productTableBody");
    document.getElementById("resultCount").textContent = total + " sản phẩm";

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="8">' + OF.ui.emptyStateHTML("Không tìm thấy sản phẩm", "Thử đổi từ khóa, danh mục hoặc trạng thái.") + "</td></tr>";
      document.getElementById("paginationBar").innerHTML = "";
      return;
    }

    tbody.innerHTML = items.map((p) => {
      const stockBadge = p.stock <= 5
        ? '<span class="badge badge-red">Sắp hết (' + p.stock + ")</span>"
        : '<strong>' + p.stock + "</strong>";
      const st = p.status === "active"
        ? '<span class="badge badge-green">Đang bán</span>'
        : '<span class="badge badge-gray">Ẩn / ngừng bán</span>';
      return "<tr>" +
        '<td><div class="prod-cell"><span class="prod-thumb">' + OF.escapeHtml(p.emoji || "📦") + "</span>" +
          '<span class="prod-cell-name">' + OF.escapeHtml(p.name) + "</span></div></td>" +
        "<td><strong>" + OF.escapeHtml(p.sku) + "</strong></td>" +
        "<td>" + OF.escapeHtml(p.category_name || "—") + "</td>" +
        '<td><span class="text-2" style="font-size:12.5px">' + OF.escapeHtml(p.variants || "—") + "</span></td>" +
        '<td class="text-right"><strong>' + OF.fmt.money(p.price) + "</strong></td>" +
        '<td class="text-right">' + stockBadge + "</td>" +
        "<td>" + st + "</td>" +
        (isAdmin()
          ? '<td><div class="cell-actions">' +
              '<button class="btn btn-sm btn-icon" data-edit="' + p.id + '" title="Sửa">' + OF.icon("edit", "icon-sm") + "</button>" +
              '<button class="btn btn-sm btn-icon" data-del="' + p.id + '" title="Xóa" style="color:var(--danger)">' + OF.icon("trash", "icon-sm") + "</button>" +
            "</div></td>"
          : '<td><span class="muted">—</span></td>') +
      "</tr>";
    }).join("");

    tbody.querySelectorAll("[data-edit]").forEach((b) =>
      b.addEventListener("click", () => openForm(Number(b.getAttribute("data-edit")), items)));
    tbody.querySelectorAll("[data-del]").forEach((b) =>
      b.addEventListener("click", () => removeProduct(Number(b.getAttribute("data-del")), items)));

    OF.ui.renderPagination(document.getElementById("paginationBar"), total, page, PER_PAGE, (p) => {
      page = p;
      loadProducts();
    });
  }

  /* ---------- Form thêm / sửa ---------- */
  function openForm(id, items) {
    if (!isAdmin()) return;
    const editing = id ? (items || []).find((p) => p.id === id) : null;
    const emojiOpts = EMOJIS.map((e) => '<option value="' + e + '"' + (editing && editing.emoji === e ? " selected" : "") + ">" + e + "</option>").join("");

    const m = OF.ui.openModal({
      title: editing ? "Sửa sản phẩm" : "Thêm sản phẩm mới",
      size: "md",
      body:
        '<form id="productForm" novalidate>' +
          '<div class="form-row">' +
            '<div class="form-group"><label class="form-label">Tên sản phẩm <span class="req">*</span></label>' +
              '<input class="form-control" id="pName" placeholder="Ví dụ: iPhone 15 Pro Max" value="' + OF.escapeHtml(editing ? editing.name : "") + '" />' +
              '<div class="form-error" data-error-for="pName"></div></div>' +
            '<div class="form-group"><label class="form-label">Mã sản phẩm <span class="req">*</span></label>' +
              '<input class="form-control" id="pSku" placeholder="SP-XXXX" value="' + OF.escapeHtml(editing ? editing.sku : "") + '" />' +
              '<div class="form-error" data-error-for="pSku"></div></div>' +
          "</div>" +
          '<div class="form-row">' +
            '<div class="form-group"><label class="form-label">Danh mục <span class="req">*</span></label>' +
              '<select class="form-select" id="pCategory"><option value="">— Chọn danh mục —</option>' + fillFormCategoryOptions(editing ? editing.category_id : null) + "</select>" +
              '<div class="form-error" data-error-for="pCategory"></div></div>' +
            '<div class="form-group"><label class="form-label">Biểu tượng</label>' +
              '<select class="form-select" id="pEmoji">' + emojiOpts + "</select></div>" +
          "</div>" +
          '<div class="form-row">' +
            '<div class="form-group"><label class="form-label">Giá bán (₫) <span class="req">*</span></label>' +
              '<input class="form-control" id="pPrice" type="number" min="0" step="1000" placeholder="0" value="' + (editing ? editing.price : "") + '" />' +
              '<div class="form-error" data-error-for="pPrice"></div></div>' +
            '<div class="form-group"><label class="form-label">Tồn kho <span class="req">*</span></label>' +
              '<input class="form-control" id="pStock" type="number" min="0" step="1" placeholder="0" value="' + (editing ? editing.stock : "") + '" />' +
              '<div class="form-error" data-error-for="pStock"></div></div>' +
          "</div>" +
          '<div class="form-group"><label class="form-label">Biến thể (các cách cách nhau bởi dấu ;)</label>' +
            '<input class="form-control" id="pVariants" placeholder="256GB / Đen; 512GB / Trắng" value="' + OF.escapeHtml(editing ? editing.variants : "") + '" />' +
            '<div class="form-hint">Mỗi biến thể cách nhau bằng dấu chấm phẩy.</div></div>' +
          '<div class="form-group"><label class="form-label">Trạng thái</label>' +
            '<select class="form-select" id="pStatus">' +
              '<option value="active"' + (editing && editing.status === "active" ? " selected" : "") + '>Đang bán</option>' +
              '<option value="hidden"' + (editing && editing.status === "hidden" ? " selected" : "") + ">Ẩn / ngừng bán</option>" +
            "</select></div>" +
        "</form>",
      footer:
        '<button class="btn" data-close>Hủy</button>' +
        '<button class="btn btn-primary" id="saveProductBtn">' + (editing ? "Lưu thay đổi" : "Thêm sản phẩm") + "</button>",
      onOpen(api) {
        api.el.querySelector("#saveProductBtn").addEventListener("click", () => saveForm(api, editing));
      },
    });
    void m;
  }

  function setErr(id, msg) {
    const input = document.getElementById(id);
    const err = document.querySelector('[data-error-for="' + id + '"]');
    if (!input || !err) return;
    if (msg) {
      input.classList.add("is-invalid");
      err.textContent = msg;
      err.classList.add("show");
    } else {
      input.classList.remove("is-invalid");
      err.classList.remove("show");
      err.textContent = "";
    }
  }

  async function saveForm(api, editing) {
    const name = document.getElementById("pName").value.trim();
    const sku = document.getElementById("pSku").value.trim();
    const cat = document.getElementById("pCategory").value;
    const price = Number(document.getElementById("pPrice").value);
    const stock = Number(document.getElementById("pStock").value);
    const variants = document.getElementById("pVariants").value.trim();
    const emoji = document.getElementById("pEmoji").value;
    const status = document.getElementById("pStatus").value;

    let ok = true;
    setErr("pName", null); setErr("pSku", null); setErr("pCategory", null);
    setErr("pPrice", null); setErr("pStock", null);
    if (name.length < 2) { setErr("pName", "Tên sản phẩm phải có ít nhất 2 ký tự."); ok = false; }
    if (!sku) { setErr("pSku", "Vui lòng nhập mã sản phẩm."); ok = false; }
    if (!cat) { setErr("pCategory", "Hãy chọn danh mục."); ok = false; }
    if (!price || price < 0) { setErr("pPrice", "Giá bán phải lớn hơn 0."); ok = false; }
    if (isNaN(stock) || stock < 0) { setErr("pStock", "Tồn kho phải là số không âm."); ok = false; }
    if (!ok) return;

    const body = {
      name, sku, category_id: Number(cat), price, stock, variants, emoji, status,
    };
    const res = editing
      ? await OF.api.put("products/item.php?id=" + editing.id, body)
      : await OF.api.post("products/index.php", body);
    if (!res.success) {
      const errs = res.errors || {};
      if (errs.name) setErr("pName", errs.name);
      else if (errs.sku) setErr("pSku", errs.sku);
      else if (errs.category_id) setErr("pCategory", errs.category_id);
      else if (errs.price) setErr("pPrice", errs.price);
      else if (errs.stock) setErr("pStock", errs.stock);
      else OF.ui.toast("error", res.message || "Lưu sản phẩm thất bại.");
      return;
    }
    OF.ui.toast("success", res.message || (editing ? "Đã cập nhật sản phẩm." : "Đã thêm sản phẩm."));
    api.close();
    loadProducts();
  }

  function removeProduct(id, items) {
    const p = (items || []).find((x) => x.id === id);
    if (!p) return;
    OF.ui.confirm({
      title: "Xóa sản phẩm?",
      message: "Sản phẩm “" + p.name + "” (" + p.sku + ") sẽ bị xóa. Nếu đã có đơn hàng, server sẽ chuyển sang Ẩn thay vì xóa.",
      danger: true,
      confirmText: "Xóa",
    }).then(async (ok) => {
      if (!ok) return;
      const res = await OF.api.del("products/item.php?id=" + id);
      if (!res.success) { OF.ui.toast("error", res.message || "Xóa thất bại."); return; }
      OF.ui.toast("success", res.message || "Đã xóa sản phẩm.");
      loadProducts();
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    loadCategories();
    loadProducts();

    const addBtn = document.getElementById("btnAddProduct");
    if (isAdmin()) {
      if (addBtn) addBtn.addEventListener("click", () => openForm(null));
    } else if (addBtn) {
      addBtn.classList.add("hidden");
    }
    document.getElementById("searchInput").addEventListener("input", (e) => {
      keyword = e.target.value;
      page = 1;
      loadProducts();
    });
    document.getElementById("categoryFilter").addEventListener("change", (e) => {
      categoryId = e.target.value;
      page = 1;
      loadProducts();
    });
    document.getElementById("statusFilter").addEventListener("change", (e) => {
      statusFilter = e.target.value;
      page = 1;
      loadProducts();
    });
  });
})();
