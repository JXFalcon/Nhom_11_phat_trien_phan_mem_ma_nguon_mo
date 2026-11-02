<?php
declare(strict_types=1);

$pageTitle = 'Quản lý giữ hàng – Shopee';
$scripts = [
    'assets/js/data.js?v=4',
    'assets/js/api.js?v=4',
    'assets/js/app.js?v=4',
    'assets/js/pages/reservations.js?v=4',
];

require __DIR__ . '/includes/layout/admin_top.php';
?>

<div class="page-head">
          <div>
            <h1>Quản lý giữ hàng (Reservations)</h1>
            <p class="page-sub">Theo dõi phiếu giữ hàng: pending → confirmed (xác nhận thanh toán) → released/expired (giải phóng).</p>
          </div>
        </div>

        <div class="alert-banner alert-info">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 12"/></svg>
          <div>
            <strong>Giữ hàng (Reservation)</strong>
            <p>Khi khách đặt đơn, hệ thống tạo reservation pending (giữ hàng 30 phút, chưa trừ kho thật). Khi thanh toán xác nhận, reservation chuyển confirmed và trừ kho thật. Nếu hết hạn hoặc hủy, reservation bị released/expired và hàng được giải phóng.</p>
          </div>
        </div>

        <div class="card">
          <div class="toolbar">
            <div class="input-icon-wrap toolbar-search">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4.5 4.5"/></svg>
              <input type="text" id="searchInput" class="form-control" placeholder="Tìm mã reservation, sản phẩm…" />
            </div>
            <select id="statusFilter" class="form-select">
              <option value="">Tất cả trạng thái</option>
              <option value="pending">Đang giữ (pending)</option>
              <option value="confirmed">Đã xác nhận (confirmed)</option>
              <option value="released">Đã giải phóng (released)</option>
              <option value="expired">Đã hết hạn (expired)</option>
            </select>
            <div class="toolbar-spacer"></div>
            <span class="muted" id="resultCount"></span>
          </div>
          <div class="card-body flush">
            <div class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Mã reservation</th>
                    <th>Sản phẩm</th>
                    <th class="text-right">SL</th>
                    <th class="text-right">Đơn giá</th>
                    <th>Trạng thái</th>
                    <th>Hết hạn vào</th>
                    <th class="text-right">Thao tác</th>
                  </tr>
                </thead>
                <tbody id="resTableBody"></tbody>
              </table>
            </div>
            <div class="pagination-bar" id="paginationBar"></div>
          </div>
        </div>

<?php require __DIR__ . '/includes/layout/admin_bottom.php'; ?>
