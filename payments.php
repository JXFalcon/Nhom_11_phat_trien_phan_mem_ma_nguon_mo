<?php
declare(strict_types=1);

$pageTitle = 'Quản lý thanh toán – Shopee';
$scripts = [
    'assets/js/data.js?v=4',
    'assets/js/api.js?v=4',
    'assets/js/app.js?v=4',
    'assets/js/payment_qr.js?v=4',
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

        <div class="alert-banner alert-warning">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5 22 20H2z"/><path d="M12 10v4.5M12 17.4v.2"/></svg>
          <div>
            <strong>QR chuyển khoản thật – ngân hàng xác nhận qua webhook</strong>
            <p>Khách quét mã VietQR để chuyển tiền thật vào tài khoản cửa hàng (cấu hình trong <code>config/payment.local.php</code>). Máy chủ chỉ đánh dấu thanh toán thành công khi nhận webhook tiền vào khớp đúng nội dung (memo) và số tiền tại <code>api/payments/bank_webhook.php</code>; khách không thể tự bấm xác nhận. Sau khi webhook xác nhận, đơn chuyển sang <strong>Đã xác nhận</strong> và khách được chuyển tới trang thông tin đơn hàng. Gửi lại thông báo cùng mã giao dịch ngân hàng hoặc giao dịch đã kết thúc sẽ không tăng/tiền không đổi lần thứ hai (yêu cầu kỹ thuật 1 &amp; 2). Thanh toán đến sau khi đơn hết hạn giữ hàng không kích hoạt đơn và không cấp hàng (yêu cầu 5).</p>
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
