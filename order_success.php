<?php
declare(strict_types=1);

$pageTitle = 'Thanh toán thành công – Shopee';
$scripts = [
    'assets/js/data.js?v=4',
    'assets/js/api.js?v=4',
    'assets/js/app.js?v=4',
    'assets/js/pages/order_success.js?v=1',
];

require __DIR__ . '/includes/layout/admin_top.php';
?>

<div class="page-head">
  <div>
    <h1>Xác nhận đơn hàng</h1>
    <p class="page-sub">Thông tin sản phẩm và thông tin nhận hàng bạn đã điền.</p>
  </div>
  <div class="page-actions">
    <a class="btn" href="index.php">Tiếp tục mua sắm</a>
  </div>
</div>

<div id="orderSuccessRoot">
  <div class="card">
    <div class="card-body" style="padding:32px;text-align:center">
      <p class="muted" style="margin:0">Đang tải thông tin đơn hàng…</p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout/admin_bottom.php'; ?>
