<?php
declare(strict_types=1);

$pageTitle = 'Quản lý thanh toán – Shopee';
$scripts = [
    'assets/js/data.js?v=4',
    'assets/js/api.js?v=4',
    'assets/js/app.js?v=5',
    'assets/js/payment_qr.js?v=3',
    'assets/js/pages/payments.js?v=5',
];

require __DIR__ . '/includes/layout/admin_top.php';
?>

<div class="page-head">
          <div>
            <h1>Quản lý thanh toán</h1>
            <p class="page-sub">Giao dịch mô phỏng: thành công / thất bại / chờ xử lý, đối chiếu với đơn hàng. Giao dịch chuyển khoản có thêm nút tạo QR VietQR.</p>
          </div>
        </div>

        <div class="card">
          <div class="toolbar">
            <div class="input-icon-wrap toolbar-search">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4.5 4.5"/></svg>
              <input type="text" id="searchInput" class="form-control" placeholder="Tìm mã giao dịch, mã đơn…" />
            </div>
            <select id="statusFilter" class="form-select">
              <option value="">Tất cả trạng thái</option>
              <option value="thanh_cong">Thành công</option>
              <option value="cho_xu_ly">Chờ xử lý</option>
              <option value="that_bai">Thất bại</option>
            </select>
            <select id="methodFilter" class="form-select">
              <option value="">Tất cả phương thức</option>
              <option value="cod">COD</option>
              <option value="bank">Chuyển khoản</option>
              <option value="momo">Ví MoMo</option>
            </select>
            <div class="toolbar-spacer"></div>
            <span class="muted" id="resultCount"></span>
          </div>
          <div class="card-body flush">
            <div class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Mã giao dịch</th>
                    <th>Mã đơn hàng</th>
                    <th class="text-right">Số tiền</th>
                    <th>Phương thức</th>
                    <th>Trạng thái TT</th>
                    <th>Trạng thái đơn</th>
                    <th>Thời điểm</th>
                    <th class="text-right">Thao tác</th>
                  </tr>
                </thead>
                <tbody id="paymentTableBody"></tbody>
              </table>
            </div>
          </div>
          <div class="pagination-bar" id="paginationBar"></div>
        </div>

<?php require __DIR__ . '/includes/layout/admin_bottom.php'; ?>
