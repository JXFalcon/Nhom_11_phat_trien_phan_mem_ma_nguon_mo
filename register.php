<?php
declare(strict_types=1);

$pageTitle = 'Đăng ký – Shopee';
$scripts = [
    'assets/js/data.js?v=4',
    'assets/js/api.js?v=4',
    'assets/js/app.js?v=4',
    'assets/js/auth.js?v=4',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="assets/css/style.css?v=5" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    body { font-family: 'Inter', sans-serif; }
    .auth-alert { display: none; }
    .auth-alert.show { display: flex; }
  </style>
</head>
<body>
  <div class="auth-page">
    <!-- Cột hình ảnh / thương hiệu -->
    <aside class="auth-visual">
      <div class="auth-brand">
        <div class="brand-logo">
          <i class="fa-solid fa-bag-shopping"></i>
        </div>
        <div class="brand-text">
          <span class="brand-name">Shopee.vn</span>
          <span class="brand-sub">Mua sắm trực tuyến an toàn</span>
        </div>
      </div>

      <div class="auth-visual-body">
        <span class="auth-eyebrow"><i class="fa-solid fa-gift"></i> Thành viên mới</span>
        <h2>Tạo tài khoản<br />nhận ngay ưu đãi</h2>
        <p>Đăng ký miễn phí để mua sắm, theo dõi đơn hàng và yêu cầu đổi trả ngay trên hệ thống.</p>

        <div class="auth-feature-list">
          <div class="auth-feature">
            <span class="auth-feature-icon"><i class="fa-solid fa-users"></i></span>
            <div><strong>30 tài khoản mẫu</strong><span>Đầy đủ vai trò quản trị, nhân viên, khách hàng</span></div>
          </div>
          <div class="auth-feature">
            <span class="auth-feature-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
            <div><strong>Dữ liệu phong phú</strong><span>100 đơn hàng, 20+ sản phẩm &amp; dữ liệu đổi trả</span></div>
          </div>
          <div class="auth-feature">
            <span class="auth-feature-icon"><i class="fa-solid fa-shield-halved"></i></span>
            <div><strong>Bảo mật tài khoản</strong><span>Mật khẩu được mã hóa, phiên đăng nhập an toàn</span></div>
          </div>
        </div>

        <div class="auth-quote">
          <i class="fa-solid fa-quote-left"></i>
          <p>Chỉ mất chưa đầy một phút để bắt đầu hành trình mua sắm của bạn.</p>
          <span>— Shopee.vn</span>
        </div>
      </div>

      <div class="auth-visual-footer">Bài tập lớn – Đề tài 19 · Backend PHP + MySQL</div>
    </aside>

    <!-- Cột form -->
    <main class="auth-form-side">
      <div class="auth-card">
        <div class="auth-mobile-brand">
          <span class="brand-logo"><i class="fa-solid fa-bag-shopping"></i></span>
          <span class="brand-name">Shopee.vn</span>
        </div>

        <h1>Tạo tài khoản</h1>
        <p class="auth-card-lead">Điền thông tin bên dưới để đăng ký. Bạn có thể đăng nhập ngay sau khi hoàn tất.</p>

        <div class="auth-alert" id="registerAlert" role="alert"></div>

        <form id="registerForm" class="auth-form" novalidate>
          <div class="form-group">
            <label class="form-label" for="regFullName">Họ và tên <span class="req">*</span></label>
            <div class="input-icon-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20.5c1-4 4-6 7.5-6s6.5 2 7.5 6"/></svg>
              <input type="text" id="regFullName" class="form-control" placeholder="Nguyễn Văn A" autocomplete="name" />
            </div>
            <div class="form-error" data-error-for="regFullName"></div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="regEmail">Email <span class="req">*</span></label>
              <div class="input-icon-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/></svg>
                <input type="email" id="regEmail" class="form-control" placeholder="ten@domain.com" autocomplete="email" />
              </div>
              <div class="form-error" data-error-for="regEmail"></div>
            </div>
            <div class="form-group">
              <label class="form-label" for="regPhone">Số điện thoại <span class="req">*</span></label>
              <div class="input-icon-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3.5h4l1.5 4.5-2.2 1.6a14 14 0 0 0 6.1 6.1L16 13.5l4.5 1.5v4a2 2 0 0 1-2.2 2C10.4 20 4 13.6 3 6.7A2 2 0 0 1 5 3.5z"/></svg>
                <input type="tel" id="regPhone" class="form-control" placeholder="0901234567" autocomplete="tel" />
              </div>
              <div class="form-error" data-error-for="regPhone"></div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="regPassword">Mật khẩu <span class="req">*</span></label>
            <div class="input-icon-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>
              <input type="password" id="regPassword" class="form-control has-icon-btn" placeholder="Tối thiểu 6 ký tự" autocomplete="new-password" />
              <button type="button" class="input-icon-btn" id="toggleRegPw" aria-label="Hiện mật khẩu">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.8-7 10-7 10 7 10 7-3.8 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="form-hint">Nên dùng ít nhất 6 ký tự, kết hợp chữ và số để bảo mật hơn.</div>
            <div class="form-error" data-error-for="regPassword"></div>
          </div>

          <div class="form-group">
            <label class="form-label" for="regConfirm">Xác nhận mật khẩu <span class="req">*</span></label>
            <div class="input-icon-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>
              <input type="password" id="regConfirm" class="form-control has-icon-btn" placeholder="Nhập lại mật khẩu" autocomplete="new-password" />
              <button type="button" class="input-icon-btn" id="toggleRegPw2" aria-label="Hiện mật khẩu">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.8-7 10-7 10 7 10 7-3.8 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="form-error" data-error-for="regConfirm"></div>
          </div>

          <label class="form-check auth-terms">
            <input type="checkbox" checked />
            <span>Tôi đồng ý với <a href="#">Điều khoản sử dụng</a> và <a href="#">Chính sách bảo mật</a> của Shopee.vn.</span>
          </label>

          <button type="submit" class="btn btn-primary auth-submit">
            Đăng ký tài khoản <i class="fa-solid fa-arrow-right text-xs"></i>
          </button>
        </form>

        <p class="auth-alt">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
        <p class="auth-back"><a href="index.php"><i class="fa-solid fa-arrow-left text-[10px]"></i> Về trang chủ Shopee.vn</a></p>
      </div>
    </main>
  </div>

<?php require __DIR__ . '/includes/layout/scripts.php'; ?>
</body>
</html>
