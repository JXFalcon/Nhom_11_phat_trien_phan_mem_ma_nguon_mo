<?php
declare(strict_types=1);

$pageTitle = 'Giỏ hàng – Shopee';
$scripts = [
    'assets/js/data.js?v=4',
    'assets/js/api.js?v=4',
    'assets/js/app.js?v=4',
    'assets/js/pages/cart.js?v=5',
];

require_once __DIR__ . '/includes/payment_qr.php';
$bankReady = of_payment_bank_ready(of_payment_bank_config());

require __DIR__ . '/includes/layout/admin_top.php';
?>

<div class="page-head">
          <div>
            <h1>Giỏ hàng của tôi</h1>
            <p class="page-sub">Kiểm tra sản phẩm, điều chỉnh số lượng và hoàn tất thông tin đặt hàng.</p>
          </div>
          <div class="page-actions">
            <a class="btn" href="index.php"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m14.5 6-6 6 6 6"/></svg> Tiếp tục mua sắm</a>
          </div>
        </div>

        <div class="alert-banner alert-info">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 12"/></svg>
          <div>
            <strong>Giỏ hàng được giữ trong 30 phút</strong>
            <p>Sau khi tạo đơn, hàng sẽ được giữ (reservation) trong 30 phút. Nếu chưa thanh toán xác nhận, đơn sẽ hết hạn và hàng sẽ được giải phóng.</p>
          </div>
        </div>

        <div class="cart-layout">
          <div class="card">
            <div class="card-header">
              <div>
                <div class="card-title">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/></svg>
                  Sản phẩm trong giỏ
                </div>
                <p class="card-sub">Giá và tồn kho được kiểm tra lại khi tạo đơn.</p>
              </div>
            </div>
            <div class="card-body flush">
              <div id="cartEmpty" style="display:none;text-align:center;padding:48px 24px">
                <div class="empty-state-icon" style="margin-bottom:16px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/></svg></div>
                <h3 style="margin-bottom:6px">Giỏ hàng đang trống</h3>
                <p class="muted" style="margin-bottom:16px">Khám phá hàng ngàn sản phẩm chính hãng với ưu đãi hấp dẫn.</p>
                <a class="btn btn-primary" href="index.php"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/></svg> Mua sắm ngay</a>
              </div>
              <div id="cartList" style="display:none">
                <div class="table-wrap">
                  <table class="data-table cart-table">
                    <thead>
                      <tr>
                        <th>Sản phẩm</th>
                        <th class="text-right">Đơn giá</th>
                        <th class="text-center">Số lượng</th>
                        <th class="text-right">Thành tiền</th>
                        <th class="text-right">Thao tác</th>
                      </tr>
                    </thead>
                    <tbody id="cartTableBody"></tbody>
                  </table>
                </div>

                <div class="cart-checkout">
                  <div class="cart-checkout-head">
                    <div>
                      <h3>Thông tin nhận hàng</h3>
                      <p class="muted">Vui lòng điền đầy đủ để đơn hàng được giao nhanh nhất.</p>
                    </div>
                    <span class="badge badge-orange">Bắt buộc xác nhận</span>
                  </div>

                  <div id="checkoutInfo" class="cart-form-grid" style="display:none">
                    <div class="cart-field">
                      <label for="inpName">Người nhận</label>
                      <input id="inpName" class="form-control" placeholder="Tên người nhận" autocomplete="name" />
                    </div>
                    <div class="cart-field">
                      <label for="inpPhone">Số điện thoại <span class="req">*</span></label>
                      <input id="inpPhone" class="form-control" inputmode="tel" placeholder="VD: 0912345678" autocomplete="tel" />
                    </div>
                    <div class="cart-field cart-field-full">
                      <label for="inpAddress">Địa chỉ giao hàng <span class="req">*</span></label>
                      <input id="inpAddress" class="form-control" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành" autocomplete="street-address" />
                    </div>
                    <div class="cart-field cart-field-full">
                      <label for="inpPayMethod">Phương thức thanh toán</label>
                      <select id="inpPayMethod" class="form-select">
                        <option value="" disabled selected>-- Chọn phương thức thanh toán --</option>
                        <option value="cod">COD (thanh toán khi nhận hàng)</option>
                        <option value="bank"<?= $bankReady ? '' : ' disabled' ?>>Chuyển khoản ngân hàng (QR)<?= $bankReady ? '' : ' – chưa cấu hình' ?></option>
                        <option value="momo">Ví MoMo</option>
                      </select>
                    </div>
                  </div>

                  <div class="cart-summary-row">
                    <div class="cart-summary-line"><span>Tạm tính</span><strong id="cartSubtotal">0 ₫</strong></div>
                    <div class="cart-summary-line"><span>Phí vận chuyển</span><strong class="text-success">Miễn phí</strong></div>
                    <div class="cart-total-line"><span>Tổng giỏ hàng</span><strong id="cartTotal">0 ₫</strong></div>
                  </div>

                  <div class="cart-actions">
                    <button class="btn btn-primary" id="btnCheckoutFromCart">
                      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 11.5 20.5 4l-4.5 17-4.2-6.8z"/><path d="m11.8 14.2 8.7-10.2"/></svg>
                      Tạo đơn hàng từ giỏ
                    </button>
                    <a class="btn" href="shop.php">Chọn thêm sản phẩm</a>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <aside class="card cart-side">
            <div class="card-body">
              <h3 class="cart-side-title"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 20 6v6c0 5-3.5 8-8 9.5C7.5 20 4 17 4 12V6z"/><path d="m8.8 12 2.2 2.2 4.2-4.4"/></svg> Mua sắm an tâm</h3>
              <ul class="cart-side-list">
                <li><span class="cart-side-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4.5 4.5L19.5 7"/></svg></span><div><strong>Hàng chính hãng 100%</strong><span>Hoàn tiền nếu không đúng mô tả</span></div></li>
                <li><span class="cart-side-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1.5" y="6" width="13" height="11" rx="1.5"/><path d="M14.5 9.5h3.6l3.4 3.5V17h-7z"/><circle cx="5.8" cy="18.8" r="2"/><circle cx="17.8" cy="18.8" r="2"/></svg></span><div><strong>Giao hàng nhanh</strong><span>Đồng kiểm khi nhận hàng</span></div></li>
                <li><span class="cart-side-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.5 15 5 10.5 9.5 6"/><path d="M5 10.5h9a5.5 5.5 0 0 1 0 11h-2.5"/></svg></span><div><strong>Đổi trả trong 7 ngày</strong><span>Thủ tục nhanh gọn, miễn phí</span></div></li>
                <li><span class="cart-side-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.2v.2"/></svg></span><div><strong>Hỗ trợ 24/7</strong><span>Đội ngũ CSKH luôn sẵn sàng</span></div></li>
              </ul>
            </div>
          </aside>
        </div>

<?php require __DIR__ . '/includes/layout/admin_bottom.php'; ?>
