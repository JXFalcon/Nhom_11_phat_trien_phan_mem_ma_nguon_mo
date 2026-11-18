<?php
declare(strict_types=1);

$pageTitle = 'Đăng ký – Shopee.vn';
$scripts = [
    'assets/js/data.js?v=4',
    'assets/js/api.js?v=4',
    'assets/js/app.js?v=5',
    'assets/js/auth.js?v=4',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            shopee: {
              orange: '#EE4D2D',
              orangeDark: '#D73211',
              light: '#FFF6F0',
              grayBg: '#F5F6F8',
            }
          },
          fontFamily: { sans: ['Inter', 'sans-serif'] },
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #F5F6F8; }
    ::selection { background: #FFD7C2; color: #7C2D12; }

    .icon { width: 18px; height: 18px; }
    .auth-card { animation: authRise .45s cubic-bezier(.22,.61,.36,1) both; }
    @keyframes authRise {
      from { opacity: 0; transform: translateY(14px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .form-control.is-invalid {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, .12);
    }
    .form-error {
      display: none;
      margin-top: 5px;
      color: #dc2626;
      font-size: 12px;
    }
    .form-error.show { display: block; }

    .auth-alert { display: none; }
    .auth-alert.show {
      display: flex;
      align-items: flex-start;
      gap: 9px;
      margin-bottom: 16px;
      padding: 11px 13px;
      border-radius: 11px;
      font-size: 13px;
      line-height: 1.55;
    }
    .auth-alert.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .auth-alert.success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .auth-alert svg { width: 18px; height: 18px; flex: none; margin-top: 1px; }

    .auth-social { transition: background .15s ease, border-color .15s ease, transform .15s ease; }
    .auth-social:hover { background: #fafafa; border-color: #cbd5e1; transform: translateY(-1px); }

    .toast-root {
      position: fixed;
      top: 18px;
      right: 18px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
      width: 100%;
      max-width: 340px;
      pointer-events: none;
    }
    .toast {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 12px 14px;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      box-shadow: 0 18px 40px -22px rgba(15, 23, 42, .5);
      animation: toastIn .22s ease;
      pointer-events: auto;
    }
    @keyframes toastIn {
      from { opacity: 0; transform: translateY(-8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .toast-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 30px;
      height: 30px;
      flex: none;
      border-radius: 9px;
    }
    .toast-icon svg { width: 17px; height: 17px; }
    .toast.success .toast-icon { background: #f0fdf4; color: #15803d; }
    .toast.error .toast-icon { background: #fef2f2; color: #b91c1c; }
    .toast-content { flex: 1; min-width: 0; }
    .toast-title { font-size: 13.5px; font-weight: 700; color: #111827; }
    .toast-msg { margin-top: 2px; font-size: 12.8px; color: #6b7280; }
  </style>
</head>
<body class="text-gray-900 antialiased">

  <div class="relative flex min-h-screen flex-col overflow-hidden">
    <!-- Nền trang trí -->
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
      <div class="absolute -right-32 -top-40 h-[420px] w-[420px] rounded-full bg-orange-200/40 blur-3xl"></div>
      <div class="absolute -bottom-44 -left-32 h-[420px] w-[420px] rounded-full bg-rose-200/40 blur-3xl"></div>
      <div class="absolute left-1/2 top-1/3 h-72 w-72 -translate-x-1/2 rounded-full bg-amber-100/40 blur-3xl"></div>
    </div>

    <!-- Header tối giản đồng bộ storefront -->
    <header class="relative z-10">
      <div class="mx-auto flex h-[76px] w-full max-w-6xl items-center justify-between px-5 sm:px-8">
        <a href="index.php" class="flex items-center gap-2.5">
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-shopee-orange text-white shadow-lg shadow-orange-500/30">
            <i class="fa-solid fa-bag-shopping text-lg"></i>
          </span>
          <span class="text-xl font-extrabold tracking-tight text-shopee">SHOPEE<span class="text-gray-900">.vn</span></span>
        </a>
        <div class="flex items-center gap-3 text-sm">
          <span class="hidden text-gray-500 sm:inline">Đã có tài khoản?</span>
          <a href="login.php" class="rounded-lg border border-orange-200 bg-white px-4 py-2 text-xs font-semibold text-shopee shadow-sm transition hover:border-orange-300 hover:bg-shopee-light">
            Đăng nhập
          </a>
        </div>
      </div>
    </header>

    <!-- Nội dung chính -->
    <main class="relative z-10 flex flex-1 items-center justify-center px-4 py-6 sm:py-10">
      <div class="w-full max-w-[520px]">
        <div class="auth-card rounded-3xl border border-gray-100 bg-white p-7 shadow-[0_30px_80px_-34px_rgba(15,23,42,.45)] sm:p-9">
          <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-shopee-light text-shopee">
              <i class="fa-solid fa-user-plus text-xl"></i>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Tạo tài khoản</h1>
            <p class="mt-1.5 text-sm text-gray-500">Đăng ký miễn phí để mua sắm và theo dõi đơn hàng của bạn.</p>
          </div>

          <div class="mb-5 flex items-start gap-2.5 rounded-xl border border-orange-100 bg-shopee-light p-3 text-xs leading-5 text-gray-600">
            <i class="fa-solid fa-gift mt-0.5 text-shopee"></i>
            <div>
              <strong class="text-gray-700">Thành viên mới</strong> — tài khoản đăng ký sẽ có vai trò
              <strong class="text-shopee">Khách hàng</strong>. Bạn có thể đăng nhập ngay sau khi hoàn tất.
            </div>
          </div>

          <div id="registerAlert" class="auth-alert" role="alert"></div>

          <form id="registerForm" class="auth-form" novalidate>
            <div class="mb-4">
              <label class="mb-1.5 block text-xs font-semibold text-gray-700" for="regFullName">Họ và tên <span class="text-red-500">*</span></label>
              <div class="relative">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20.5c1-4 4-6 7.5-6s6.5 2 7.5 6"/></svg>
                <input type="text" id="regFullName" class="form-control w-full rounded-xl border border-gray-300 bg-white py-2.5 pl-11 pr-4 text-sm outline-none transition placeholder:text-gray-400 focus:border-shopee focus:ring-2 focus:ring-orange-100"
                  placeholder="Nguyễn Văn A" autocomplete="name" />
              </div>
              <div class="form-error" data-error-for="regFullName"></div>
            </div>

            <div class="mb-4 grid gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700" for="regEmail">Email <span class="text-red-500">*</span></label>
                <div class="relative">
                  <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/></svg>
                  <input type="email" id="regEmail" class="form-control w-full rounded-xl border border-gray-300 bg-white py-2.5 pl-11 pr-4 text-sm outline-none transition placeholder:text-gray-400 focus:border-shopee focus:ring-2 focus:ring-orange-100"
                    placeholder="ten@domain.com" autocomplete="email" />
                </div>
                <div class="form-error" data-error-for="regEmail"></div>
              </div>
              <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700" for="regPhone">Số điện thoại <span class="text-red-500">*</span></label>
                <div class="relative">
                  <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3.5h4l1.5 4.5-2.2 1.6a14 14 0 0 0 6.1 6.1L16 13.5l4.5 1.5v4a2 2 0 0 1-2.2 2C10.4 20 4 13.6 3 6.7A2 2 0 0 1 5 3.5z"/></svg>
                  <input type="tel" id="regPhone" class="form-control w-full rounded-xl border border-gray-300 bg-white py-2.5 pl-11 pr-4 text-sm outline-none transition placeholder:text-gray-400 focus:border-shopee focus:ring-2 focus:ring-orange-100"
                    placeholder="0901234567" autocomplete="tel" />
                </div>
                <div class="form-error" data-error-for="regPhone"></div>
              </div>
            </div>

            <div class="mb-4">
              <label class="mb-1.5 block text-xs font-semibold text-gray-700" for="regPassword">Mật khẩu <span class="text-red-500">*</span></label>
              <div class="relative">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>
                <input type="password" id="regPassword" class="form-control w-full rounded-xl border border-gray-300 bg-white py-2.5 pl-11 pr-11 text-sm outline-none transition placeholder:text-gray-400 focus:border-shopee focus:ring-2 focus:ring-orange-100"
                  placeholder="Tối thiểu 6 ký tự" autocomplete="new-password" />
                <button type="button" id="toggleRegPw" aria-label="Hiện mật khẩu"
                  class="absolute right-3 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-shopee">
                  <svg class="icon h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.8-7 10-7 10 7 10 7-3.8 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <div class="mt-1.5 text-[11px] text-gray-400">Nên dùng ít nhất 6 ký tự, kết hợp chữ và số để bảo mật hơn.</div>
              <div class="form-error" data-error-for="regPassword"></div>
            </div>

            <div class="mb-4">
              <label class="mb-1.5 block text-xs font-semibold text-gray-700" for="regConfirm">Xác nhận mật khẩu <span class="text-red-500">*</span></label>
              <div class="relative">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>
                <input type="password" id="regConfirm" class="form-control w-full rounded-xl border border-gray-300 bg-white py-2.5 pl-11 pr-11 text-sm outline-none transition placeholder:text-gray-400 focus:border-shopee focus:ring-2 focus:ring-orange-100"
                  placeholder="Nhập lại mật khẩu" autocomplete="new-password" />
                <button type="button" id="toggleRegPw2" aria-label="Hiện mật khẩu"
                  class="absolute right-3 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-shopee">
                  <svg class="icon h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.8-7 10-7 10 7 10 7-3.8 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <div class="form-error" data-error-for="regConfirm"></div>
            </div>

            <label class="mb-5 flex cursor-pointer items-start gap-2 text-xs leading-5 text-gray-600">
              <input type="checkbox" checked class="mt-0.5 h-4 w-4 flex-none rounded border-gray-300 accent-orange-500" />
              <span>Tôi đồng ý với <a href="#" class="font-semibold text-shopee hover:underline">Điều khoản sử dụng</a> và <a href="#" class="font-semibold text-shopee hover:underline">Chính sách bảo mật</a> của Shopee.vn.</span>
            </label>

            <button type="submit"
              class="flex w-full items-center justify-center gap-2 rounded-xl bg-shopee-orange py-3 text-sm font-bold text-white shadow-lg shadow-orange-500/25 transition hover:bg-shopee-orangeDark active:scale-[.99] disabled:cursor-not-allowed disabled:opacity-60">
              Đăng ký tài khoản <i class="fa-solid fa-arrow-right text-xs"></i>
            </button>
          </form>

          <div class="my-5 flex items-center gap-3">
            <span class="h-px flex-1 bg-gray-200"></span>
            <span class="whitespace-nowrap text-[11px] font-medium text-gray-400">hoặc tiếp tục với</span>
            <span class="h-px flex-1 bg-gray-200"></span>
          </div>

          <div class="flex gap-3">
            <button type="button" class="auth-social flex flex-1 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white py-2.5 text-xs font-semibold text-gray-600">
              <i class="fa-brands fa-facebook-f" style="color:#1877f2"></i> Facebook
            </button>
            <button type="button" class="auth-social flex flex-1 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white py-2.5 text-xs font-semibold text-gray-600">
              <i class="fa-brands fa-google" style="color:#ea4335"></i> Google
            </button>
          </div>

          <p class="mt-6 text-center text-[13px] text-gray-500">
            Đã có tài khoản? <a href="login.php" class="font-semibold text-shopee hover:underline">Đăng nhập ngay</a>
          </p>
        </div>

        <p class="mt-5 text-center text-xs text-gray-400">
          <a href="index.php" class="inline-flex items-center gap-1.5 transition hover:text-shopee">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Về trang chủ Shopee.vn
          </a>
        </p>
      </div>
    </main>

    <footer class="relative z-10 pb-6 text-center text-[11px] text-gray-400">
      © 2026 Shopee.vn · Nền tảng thương mại điện tử · Đề tài 19 – Nhóm 11
    </footer>
  </div>

<?php require __DIR__ . '/includes/layout/scripts.php'; ?>
</body>
</html>
