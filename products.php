<?php
declare(strict_types=1);

$pageTitle = 'Quản lý sản phẩm – Shopee';
$scripts = [
    'assets/js/data.js?v=4',
    'assets/js/api.js?v=4',
    'assets/js/app.js?v=4',
    'assets/js/pages/products.js?v=4',
];

require __DIR__ . '/includes/layout/admin_top.php';
?>

<div class="page-head">
          <div>
            <h1>Quản lý sản phẩm</h1>
            <p class="page-sub">Thêm, sửa, xóa sản phẩm và theo dõi tồn kho (đồng bộ MySQL, có transaction).</p>
          </div>
          <div class="page-actions">
            <button class="btn btn-primary" id="btnAddProduct">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
              Thêm sản phẩm
            </button>
          </div>
        </div>

        <div class="card">
          <div class="toolbar">
            <div class="input-icon-wrap toolbar-search">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4.5 4.5"/></svg>
              <input type="text" id="searchInput" class="form-control" placeholder="Tìm theo tên hoặc mã SP…" />
            </div>
            <select id="categoryFilter" class="form-select"><option value="">Tất cả danh mục</option></select>
            <select id="statusFilter" class="form-select">
              <option value="">Tất cả trạng thái</option>
              <option value="active">Đang bán</option>
              <option value="hidden">Ẩn / ngừng bán</option>
            </select>
            <div class="toolbar-spacer"></div>
            <span class="muted" id="resultCount"></span>
          </div>
          <div class="card-body flush">
            <div class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Sản phẩm</th>
                    <th>Mã SP</th>
                    <th>Danh mục</th>
                    <th>Biến thể</th>
                    <th class="text-right">Giá bán</th>
                    <th class="text-right">Tồn kho</th>
                    <th>Trạng thái</th>
                    <th class="text-right">Thao tác</th>
                  </tr>
                </thead>
                <tbody id="productTableBody"></tbody>
              </table>
            </div>
          </div>
          <div class="pagination-bar" id="paginationBar"></div>
        </div>

<?php require __DIR__ . '/includes/layout/admin_bottom.php'; ?>
