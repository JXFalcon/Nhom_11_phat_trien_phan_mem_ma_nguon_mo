/* ============================================================
   Shopee – categories.js (Quản lý danh mục)
   Giai đoạn 2: dữ liệu từ API MySQL (api/categories/*).
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  const EMOJIS = ["📱", "💻", "📟", "🎧", "⌚", "🖱️", "🔊", "🧥", "👟", "🍚", "🔥", "🏠", "🎁", "🧸"];
  let keyword = "";
  let categories = [];
  const isAdmin = () => (OF.session.getUser() || {}).role === "admin";

  async function loadData() {
    const res = await OF.api.get("categories/index.php");
    if (!res.success) {
      OF.ui.toast("error", res.message || "Không tải được danh mục.");
      return;
    }
    categories = res.data || [];
    render();
  }

  function filtered() {
    const kw = keyword.trim().toLowerCase();
    return categories.filter((c) => !kw || c.name.toLowerCase().indexOf(kw) !== -1);
  }

  function render() {
    const rows = filtered();
    document.getElementById("resultCount").textContent = rows.length + " danh mục";
    const tbody = document.getElementById("categoryTableBody");
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="5">' + OF.ui.emptyStateHTML("Không có danh mục phù hợp") + "</td></tr>";
      return;
    }
    tbody.innerHTML = rows.map((c) => {
      const count = Number(c.product_count || 0);
      return "<tr>" +
        '<td><div class="prod-cell"><span class="prod-thumb">' + OF.escapeHtml(c.emoji || "🏷️") + "</span>" +
          '<span class="prod-cell-name">' + OF.escapeHtml(c.name) + "</span></div></td>" +
        "<td><strong>" + OF.escapeHtml(c.code || c.id) + "</strong></td>" +
        '<td><span class="text-2">' + OF.escapeHtml(c.description || "—") + "</span></td>" +
        '<td class="text-right">' + (count ? '<span class="badge badge-indigo">' + count + " sản phẩm</span>" : '<span class="muted">0</span>') + "</td>" +
        (isAdmin()
          ? '<td><div class="cell-actions">' +
              '<button class="btn btn-sm btn-icon" data-edit="' + c.id + '" title="Sửa">' + OF.icon("edit", "icon-sm") + "</button>" +
              '<button class="btn btn-sm btn-icon" data-del="' + c.id + '" title="Xóa" style="color:var(--danger)">' + OF.icon("trash", "icon-sm") + "</button>" +
            "</div></td>"
          : '<td><span class="muted">—</span></td>') +
      "</tr>";
    }).join("");

    tbody.querySelectorAll("[data-edit]").forEach((b) =>
      b.addEventListener("click", () => openForm(Number(b.getAttribute("data-edit")))));
    tbody.querySelectorAll("[data-del]").forEach((b) =>
      b.addEventListener("click", () => removeCategory(Number(b.getAttribute("data-del")))));
  }

  function setErr(id, msg) {
    const input = document.getElementById(id);
    const err = document.querySelector('[data-error-for="' + id + '"]');
    if (!input || !err) return;
    if (msg) { input.classList.add("is-invalid"); err.textContent = msg; err.classList.add("show"); }
    else { input.classList.remove("is-invalid"); err.classList.remove("show"); }
  }

  function openForm(id) {
    if (!isAdmin()) return;
    const editing = id ? categories.find((c) => c.id === id) : null;
    const emojiOpts = EMOJIS.map((e) =>
      '<option value="' + e + '"' + (editing && editing.emoji === e ? " selected" : "") + ">" + e + "</option>"
    ).join("");
    OF.ui.openModal({
      title: editing ? "Sửa danh mục" : "Thêm danh mục mới",
      body:
        '<form id="categoryForm" novalidate>' +
          '<div class="form-group"><label class="form-label">Tên danh mục <span class="req">*</span></label>' +
            '<input class="form-control" id="cName" placeholder="Ví dụ: Điện thoại" value="' + OF.escapeHtml(editing ? editing.name : "") + '" />' +
            '<div class="form-error" data-error-for="cName"></div></div>' +
          '<div class="form-group"><label class="form-label">Biểu tượng</label>' +
            '<select class="form-select" id="cEmoji">' + emojiOpts + "</select></div>" +
          '<div class="form-group"><label class="form-label">Mô tả</label>' +
            '<textarea class="form-control" id="cDesc" rows="3" placeholder="Mô tả ngắn về danh mục…">' + OF.escapeHtml(editing ? editing.description : "") + "</textarea></div>" +
        "</form>",
      footer:
        '<button class="btn" data-close>Hủy</button>' +
        '<button class="btn btn-primary" id="saveCategoryBtn">' + (editing ? "Lưu thay đổi" : "Thêm danh mục") + "</button>",
      onOpen(api) {
        api.el.querySelector("#saveCategoryBtn").addEventListener("click", () => saveForm(api, editing));
      },
    });
  }

  async function saveForm(api, editing) {
    const name = document.getElementById("cName").value.trim();
    const emoji = document.getElementById("cEmoji").value;
    const description = document.getElementById("cDesc").value.trim();
    setErr("cName", null);
    if (name.length < 2) { setErr("cName", "Tên danh mục phải có ít nhất 2 ký tự."); return; }

    const body = { name, emoji, description };
    const res = editing
      ? await OF.api.put("categories/item.php?id=" + editing.id, body)
      : await OF.api.post("categories/index.php", body);
    if (!res.success) {
      if (res.errors && res.errors.name) setErr("cName", res.errors.name);
      else OF.ui.toast("error", res.message || "Lưu danh mục thất bại.");
      return;
    }
    OF.ui.toast("success", res.message || (editing ? "Đã cập nhật danh mục." : "Đã thêm danh mục."));
    api.close();
    loadData();
  }

  function removeCategory(id) {
    const c = categories.find((x) => x.id === id);
    if (!c) return;
    const count = Number(c.product_count || 0);
    if (count > 0) {
      OF.ui.confirm({
        title: "Không thể xóa danh mục",
        message: "Danh mục “" + c.name + "” đang có " + count + " sản phẩm. Hãy chuyển hoặc xóa các sản phẩm này trước.",
        tone: "warning",
        icon: "alert",
        confirmText: "Đã hiểu",
        cancelText: "Đóng",
      });
      return;
    }
    OF.ui.confirm({
      title: "Xóa danh mục?",
      message: "Danh mục “" + c.name + "” sẽ bị xóa khỏi cơ sở dữ liệu.",
      danger: true,
      confirmText: "Xóa",
    }).then(async (ok) => {
      if (!ok) return;
      const res = await OF.api.del("categories/item.php?id=" + id);
      if (!res.success) { OF.ui.toast("error", res.message || "Xóa thất bại."); return; }
      OF.ui.toast("success", "Đã xóa danh mục “" + c.name + "”.");
      loadData();
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    loadData();
    document.getElementById("btnAddCategory").addEventListener("click", () => openForm(null));
    document.getElementById("searchInput").addEventListener("input", (e) => {
      keyword = e.target.value;
      render();
    });
  });
})();
