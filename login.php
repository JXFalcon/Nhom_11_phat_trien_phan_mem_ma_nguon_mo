<?php
declare(strict_types=1);

$pageTitle = 'Đăng nhập – Shopee.vn';
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
    <!-- Cột thương hiệu -->
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
        <span class="auth-eyebrow"><i class="fa-solid fa-bolt"></i> Ưu đãi mỗi ngày</span>
        <h2>Mua sắm thả ga<br />giao hàng tận cửa</h2>
        <p>Hàng ngàn sản phẩm chính hãng, voucher hấp dẫn và trải nghiệm đặt hàng minh bạch — tất cả trong một nền tảng.</p>

        <div class="auth-feature-list">
          <div class="auth-feature">
            <span class="auth-feature-icon"><i class="fa-solid fa-truck-fast"></i></span>
            <div><strong>Freeship toàn quốc</strong><span>Cho đơn hàng từ 200.000đ</span></div>
          </div>
          <div class="auth-feature">
            <span class="auth-feature-icon"><i class="fa-solid fa-shield-halved"></i></span>
            <div><strong>Thanh toán an toàn</strong><span>Bảo vệ người mua 100%</span></div>
          </div>
          <div class="auth-feature">
            <span class="auth-feature-icon"><i class="fa-solid fa-rotate-left"></i></span>
            <div><strong>Đổi trả 7 ngày</strong><span>Thủ tục đơn giản, miễn phí</span></div>
          </div>
        </div>

        <div class="auth-quote">
          <i class="fa-solid fa-quote-left"></i>
          <p>Trải nghiệm mua sắm mượt mà, theo dõi đơn hàng rõ ràng và đổi trả minh bạch.</p>
          <span>— Đề tài 19 · Nhóm 11</span>
        </div>
      </div>

      <div class="auth-visual-footer">© 2026 Shopee.vn · Nền tảng thương mại điện tử</div>
    </aside>

    <!-- Cột form -->
    <main class="auth-form-side">
      <div class="auth-card">
        <div class="auth-mobile-brand">
          <span class="brand-logo"><i class="fa-solid fa-bag-shopping"></i></span>
          <span class="brand-name">Shopee.vn</span>
        </div>

        <h1>Đăng nhập</h1>
        <p class="auth-card-lead">Chào mừng bạn quay lại! Đăng nhập để tiếp tục mua sắm.</p>

        <div class="auth-demo-box">
          <i class="fa-solid fa-circle-info"></i>
          <div>
            <strong>Tài khoản demo</strong> — mật khẩu chung <code>123456</code>. Chọn nhanh vai trò bên dưới để điền tự động.
          </div>
        </div>

        <div class="auth-demo-accounts">
          <button type="button" class="auth-demo-account" data-fill-account="admin@orderflow.vn" data-fill-pw="123456">
            <span class="auth-demo-avatar admin"><i class="fa-solid fa-user-shield"></i></span>
            <span><strong>Quản trị</strong><small>admin@orderflow.vn</small></span>
          </button>
          <button type="button" class="auth-demo-account" data-fill-account="nhanvien@orderflow.vn" data-fill-pw="123456">
            <span class="auth-demo-avatar staff"><i class="fa-solid fa-user-tie"></i></span>
            <span><strong>Nhân viên</strong><small>nhanvien@orderflow.vn</small></span>
          </button>
          <button type="button" class="auth-demo-account" data-fill-account="khachhang@orderflow.vn" data-fill-pw="123456">
            <span class="auth-demo-avatar customer"><i class="fa-solid fa-user"></i></span>
            <span><strong>Khách hàng</strong><small>khachhang@orderflow.vn</small></span>
          </button>
        </div>

        <div id="loginAlert" class="auth-alert" role="alert"></div>

        <form id="loginForm" class="auth-form" novalidate>
          <div class="form-group">
            <label class="form-label" for="loginEmail">Email <span class="req">*</span></label>
            <div class="input-icon-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/></svg>
              <input type="email" id="loginEmail" class="form-control" placeholder="admin@orderflow.vn" autocomplete="username" />
            </div>
            <div class="form-error" data-error-for="loginEmail"></div>
          </div>

          <div class="form-group">
            <label class="form-label" for="loginPassword">Mật khẩu <span class="req">*</span></label>
            <div class="input-icon-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>
              <input type="password" id="loginPassword" class="form-control has-icon-btn" placeholder="Nhập mật khẩu" autocomplete="current-password" />
              <button type="button" class="input-icon-btn" id="toggleLoginPw" aria-label="Hiện mật khẩu">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.8-7 10-7 10 7 10 7-3.8 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="form-error" data-error-for="loginPassword"></div>
          </div>

          <div class="auth-row">
            <label class="form-check">
              <input type="checkbox" id="loginRemember" /> Ghi nhớ đăng nhập
            </label>
            <a href="#" onclick="event.preventDefault(); alert('Chức năng quên mật khẩu chưa hỗ trợ. Hãy dùng tài khoản demo: admin@orderflow.vn / 123456.');" class="auth-forgot">Quên mật khẩu?</a>
          </div>

          <button type="submit" class="btn btn-primary auth-submit">
            Đăng nhập <i class="fa-solid fa-arrow-right text-xs"></i>
          </button>
        </form>

        <div class="auth-divider"><span>hoặc tiếp tục với</span></div>
        <div class="auth-socials">
          <button type="button" class="auth-social"><i class="fa-brands fa-facebook-f" style="color:#1877f2"></i> Facebook</button>
          <button type="button" class="auth-social"><i class="fa-brands fa-google" style="color:#ea4335"></i> Google</button>
        </div>

        <p class="auth-alt">Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
        <p class="auth-back"><a href="index.php"><i class="fa-solid fa-arrow-left text-[10px]"></i> Về trang chủ Shopee.vn</a></p>
      </div>
    </main>
  </div>

<?php require __DIR__ . '/includes/layout/scripts.php'; ?>
</body>
</html>
