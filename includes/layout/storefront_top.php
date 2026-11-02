<?php
$pageTitle = $pageTitle ?? 'Shopee - Mua sắm trực tuyến';
$bodyClass = 'text-gray-900 antialiased';
$currentPage = basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'));
$headHtml = <<<'HTML'
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/storefront.css?v=6" />
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            shopee: {
              orange: '#EE4D2D',
              orangeDark: '#D73211',
              light: '#FFF6F0',
              grayBg: '#F5F5F5',
            }
          },
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
          },
          animation: {
            'fade-in': 'fadeIn 0.4s ease-out',
            'slide-up': 'slideUp 0.3s ease-out',
            'float': 'float 6s ease-in-out infinite',
          },
          keyframes: {
            fadeIn: {
              '0%': { opacity: '0' },
              '100%': { opacity: '1' },
            },
            slideUp: {
              '0%': { transform: 'translateY(20px)', opacity: '0' },
              '100%': { transform: 'translateY(0)', opacity: '1' },
            },
            float: {
              '0%, 100%': { transform: 'translateY(0)' },
              '50%': { transform: 'translateY(-12px)' },
            }
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #F5F6F8; }
    ::selection { background: #FFD7C2; color: #7C2D12; }
    .shopee-orange { color: #EE4D2D; }
    .bg-shopee-orange { background-color: #EE4D2D; }
    .hover\:bg-shopee-orange:hover { background-color: #EE4D2D; }
    .border-shopee { border-color: #EE4D2D; }
    .text-shopee { color: #EE4D2D; }
    .carousel-item { will-change: opacity; }
    .product-card { transition: transform .25s ease, box-shadow .25s ease; }
    .soft-card { transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
    .soft-card:hover { transform: translateY(-3px); box-shadow: 0 14px 30px rgba(15, 23, 42, .08); }
    .custom-scrollbar::-webkit-scrollbar { height: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #d1d5db; }
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-up { animation: fadeUp .5s ease both; }
    .sf-topbar .hover\:text-shopee:hover { color: #fff !important; }
    .sf-account-menu {
      background: #fff;
      border: 1px solid #eef0f3;
      border-radius: 14px;
      box-shadow: 0 24px 50px -22px rgba(17, 24, 39, .4);
      overflow: hidden;
    }
    .sf-account-menu a,
    .sf-account-menu button { color: #4b5563 !important; }
    .sf-account-menu a:hover,
    .sf-account-menu button:hover { background: #fff6f2 !important; color: #EE4D2D !important; }
  </style>
HTML;
require __DIR__ . '/head.php';
?>

  <!-- ===================== TOP BAR ===================== -->
  <div class="sf-topbar">
    <div class="sf-container">
      <div class="flex justify-between items-center h-9 text-[11.5px]">
        <div class="flex items-center gap-4 min-w-0">
          <a href="shop.php" class="hidden sm:inline-flex items-center gap-1.5 font-medium">
            <i class="fa-solid fa-store text-[11px]"></i> Kênh Người Bán
          </a>
          <span class="hidden sm:inline sf-topbar-sep">|</span>
          <a href="index.php" class="hidden md:inline-flex items-center gap-1.5 font-medium">
            <i class="fa-solid fa-mobile-screen-button text-[11px]"></i> Tải ứng dụng
          </a>
          <span class="hidden md:inline sf-topbar-sep">|</span>
          <span class="hidden lg:inline-flex items-center gap-1.5 sf-topbar-muted">
            <i class="fa-solid fa-truck-fast text-[11px]"></i> Freeship đơn từ 200.000đ
          </span>
          <span class="sf-topbar-badge">
            <i class="fa-solid fa-bolt text-[10px]"></i> Flash Sale mỗi ngày
          </span>
        </div>
        <div class="flex items-center gap-3 sm:gap-4">
          <a href="orders.php" class="hidden sm:inline-flex items-center gap-1.5 font-medium">
            <i class="fa-regular fa-bell text-[11px]"></i> Thông báo
          </a>
          <span class="hidden sm:inline sf-topbar-sep">|</span>
          <a href="#" class="hidden md:inline-flex items-center gap-1.5 font-medium">
            <i class="fa-regular fa-circle-question text-[11px]"></i> Hỗ trợ
          </a>
          <span class="hidden md:inline sf-topbar-sep">|</span>
          <div id="accountArea" class="relative flex items-center"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================== MAIN HEADER ===================== -->
  <header class="sf-header">
    <div class="sf-container">
      <div class="flex items-center gap-3 sm:gap-5 lg:gap-7 h-[76px] md:h-[86px]">
        <!-- Logo -->
        <a href="index.php" class="sf-logo flex items-center gap-2.5 flex-none">
          <span class="sf-logo-mark"><i class="fa-solid fa-bag-shopping text-[18px]"></i></span>
          <span class="sf-logo-text hidden xs:inline sm:inline">SHOPEE<span>.vn</span></span>
        </a>

        <!-- Search -->
        <div class="flex-1 min-w-0">
          <form action="shop.php" method="get" role="search" class="max-w-2xl mx-auto">
            <div class="sf-search">
              <i class="fa-solid fa-magnifying-glass text-gray-400 text-sm mr-2.5"></i>
              <input type="text" id="searchInput" name="q" placeholder="Tìm sản phẩm, thương hiệu, danh mục..." autocomplete="off" aria-label="Tìm kiếm sản phẩm" />
              <button type="submit" class="sf-search-btn" aria-label="Tìm kiếm">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                <span class="hidden sm:inline">Tìm kiếm</span>
              </button>
            </div>
          </form>
          <div class="hidden md:flex items-center gap-2 mt-2 overflow-x-auto scrollbar-hide" id="searchSuggestions"></div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-1 sm:gap-2 flex-none">
          <a href="shop.php" class="sf-action sf-action-lg">
            <i class="fa-solid fa-compass"></i>
            <span>Khám phá</span>
          </a>
          <a href="orders.php" class="sf-action sf-action-sm">
            <i class="fa-solid fa-receipt"></i>
            <span>Đơn hàng</span>
          </a>
          <a href="cart.php" id="cartBtn" class="sf-cart-btn" aria-label="Giỏ hàng">
            <i class="fa-solid fa-cart-shopping text-lg"></i>
            <span id="cartBadge" class="sf-cart-badge scale-0">0</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Category navigation -->
    <nav class="sf-nav">
      <div class="sf-container">
        <div class="flex items-center gap-0.5 overflow-x-auto scrollbar-hide">
          <a href="shop.php" class="sf-nav-link <?= $currentPage === 'shop.php' ? 'is-active' : '' ?>"><i class="fa-solid fa-layer-group text-[11px]"></i> Tất cả sản phẩm</a>
          <a href="shop.php" class="sf-nav-link"><i class="fa-solid fa-fire text-[11px]"></i> Flash Sale</a>
          <a href="shop.php" class="sf-nav-link"><i class="fa-solid fa-ticket text-[11px]"></i> Voucher</a>
          <a href="shop.php" class="sf-nav-link"><i class="fa-solid fa-truck-fast text-[11px]"></i> Freeship</a>
          <a href="shop.php" class="sf-nav-link"><i class="fa-solid fa-shop text-[11px]"></i> Shopee Mall</a>
          <a href="shop.php" class="sf-nav-link"><i class="fa-solid fa-coins text-[11px]"></i> Shopee Xu</a>
          <a href="shop.php" class="sf-nav-link hidden md:inline-flex"><i class="fa-solid fa-gift text-[11px]"></i> Quà tặng</a>
          <span class="ml-auto hidden lg:flex items-center gap-1.5 pl-4 text-[11.5px] text-gray-400 whitespace-nowrap">
            <i class="fa-solid fa-shield-halved text-shopee"></i> Bảo đảm chính hãng · Đổi trả 7 ngày
          </span>
        </div>
      </div>
    </nav>
  </header>
