<?php
declare(strict_types=1);

$pageTitle = 'Danh mục – Shopee';
$scripts = [
    'assets/js/data.js?v=4',
    'assets/js/api.js?v=4',
    'assets/js/app.js?v=4',
    'assets/js/pages/categories.js?v=4',
];

require __DIR__ . '/includes/layout/admin_top.php';
?>

<div class="page-head">
          <div>
            <h1>Quản lý danh mục</h1>
            <p class="page-sub">Tổ chức sản phẩm theo nhóm hàng (đồng bộ MySQL).</p>
          </div>
          <div class="page-actions">
            <button class="btn btn-primary" id="btnAddCategory">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
              Thêm danh mục
            </button>
          </div>
        </div>

        <div class="card">
          <div class="toolbar">
            <div class="input-icon-wrap toolbar-search">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4.5 4.5"/></svg>
              <input type="text" id="searchInput" class="form-control" placeholder="Tìm theo tên danh mục…" />
            </div>
            <div class="toolbar-spacer"></div>
            <span class="muted" id="resultCount"></span>
          </div>
          <div class="card-body flush">
            <div class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Danh mục</th>
                    <th>Mã</th>
                    <th>Mô tả</th>
                    <th class="text-right">Số sản phẩm</th>
                    <th class="text-right">Thao tác</th>
                  </tr>
                </thead>
                <tbody id="categoryTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>

<?php require __DIR__ . '/includes/layout/admin_bottom.php'; ?>
