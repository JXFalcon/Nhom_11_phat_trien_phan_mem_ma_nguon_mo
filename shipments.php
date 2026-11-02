<?php
declare(strict_types=1);

$pageTitle = 'Quản lý giao hàng – Shopee';
$scripts = [
    'assets/js/data.js?v=4',
    'assets/js/api.js?v=4',
    'assets/js/app.js?v=4',
    'assets/js/pages/shipments.js?v=4',
];

require __DIR__ . '/includes/layout/admin_top.php';
?>

<div class="page-head">
          <div>
            <h1>Quản lý giao hàng</h1>
            <p class="page-sub">Theo dõi vận đơn: chờ lấy hàng → đang vận chuyển → đã giao / thất bại.</p>
          </div>
        </div>

        <div class="card">
          <div class="toolbar">
            <div class="input-icon-wrap toolbar-search">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4.5 4.5"/></svg>
              <input type="text" id="searchInput" class="form-control" placeholder="Tìm mã đơn, mã vận đơn…" />
            </div>
            <select id="statusFilter" class="form-select">
              <option value="">Tất cả trạng thái</option>
              <option value="cho_lay_hang">Chờ lấy hàng</option>
              <option value="dang_van_chuyen">Đang vận chuyển</option>
              <option value="da_giao">Đã giao</option>
              <option value="that_bai">Giao thất bại</option>
            </select>
            <div class="toolbar-spacer"></div>
            <span class="muted" id="resultCount"></span>
          </div>
          <div class="card-body flush">
            <div class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Mã đơn</th>
                    <th>Đơn vị vận chuyển</th>
                    <th>Mã vận đơn</th>
                    <th>Ngày gửi</th>
                    <th>Trạng thái giao</th>
                    <th class="text-right">Thao tác</th>
                  </tr>
                </thead>
                <tbody id="shipmentTableBody"></tbody>
              </table>
            </div>
          </div>
          <div class="pagination-bar" id="paginationBar"></div>
        </div>

<?php require __DIR__ . '/includes/layout/admin_bottom.php'; ?>
