<?php
declare(strict_types=1);

$pageTitle = 'Shopee - Mua sắm trực tuyến';

require __DIR__ . '/includes/layout/storefront_top.php';
?>

  <!-- ===================== HERO BANNER ===================== -->
  <section class="sf-hero">
    <div class="sf-container">
      <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,2.05fr)_minmax(0,1fr)] gap-4">
        <div class="relative">
          <div id="heroSlider" class="sf-hero-slider h-[270px] sm:h-[320px] lg:h-[358px]"></div>
          <div class="absolute bottom-5 left-1/2 -translate-x-1/2 z-20 flex gap-2" id="sliderDots"></div>
          <span id="slideCounter" class="absolute top-4 right-4 z-20 bg-black/30 backdrop-blur text-white text-[11px] font-semibold px-3 py-1 rounded-full">1 / 8</span>
          <button onclick="prevSlide()" aria-label="Banner trước" class="sf-hero-arrow absolute left-4 top-1/2 -translate-y-1/2 z-20"><i class="fa-solid fa-chevron-left text-sm"></i></button>
          <button onclick="nextSlide()" aria-label="Banner sau" class="sf-hero-arrow absolute right-4 top-1/2 -translate-y-1/2 z-20"><i class="fa-solid fa-chevron-right text-sm"></i></button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-4">
          <a href="shop.php" class="sf-promo-tile" style="background:linear-gradient(135deg,#0f766e 0%,#0d9488 46%,#14b8a6 100%)">
            <span class="sf-promo-icon"><i class="fa-solid fa-truck-fast"></i></span>
            <div class="min-w-0">
              <span class="block text-[10.5px] font-bold uppercase tracking-[.14em] text-white/75">Ưu đãi vận chuyển</span>
              <span class="block text-[15px] font-extrabold leading-snug mt-0.5">Miễn phí vận chuyển</span>
              <span class="block text-[11.5px] text-white/80 mt-0.5">Cho đơn hàng từ 200.000₫</span>
            </div>
            <i class="fa-solid fa-arrow-right ml-auto text-white/70 text-xs"></i>
          </a>
          <a href="shop.php" class="sf-promo-tile" style="background:linear-gradient(135deg,#f97316 0%,#ee4d2d 52%,#c2410c 100%)">
            <span class="sf-promo-icon"><i class="fa-solid fa-ticket"></i></span>
            <div class="min-w-0">
              <span class="block text-[10.5px] font-bold uppercase tracking-[.14em] text-white/75">Voucher độc quyền</span>
              <span class="block text-[15px] font-extrabold leading-snug mt-0.5">Giảm đến 50%</span>
              <span class="block text-[11.5px] text-white/80 mt-0.5">Săn mã ngay hôm nay</span>
            </div>
            <i class="fa-solid fa-arrow-right ml-auto text-white/70 text-xs"></i>
          </a>
        </div>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
        <a href="shop.php" class="sf-tile-link">
          <span class="sf-tile-icon" style="background:#fff1f0;color:#e11d48"><i class="fa-solid fa-fire"></i></span>
          <div class="min-w-0"><p class="text-xs font-bold text-gray-800">Flash Sale</p><p class="text-[11px] text-gray-400">Deal mỗi ngày</p></div>
        </a>
        <a href="shop.php" class="sf-tile-link">
          <span class="sf-tile-icon" style="background:#fff7ed;color:#ea580c"><i class="fa-solid fa-tags"></i></span>
          <div class="min-w-0"><p class="text-xs font-bold text-gray-800">Voucher</p><p class="text-[11px] text-gray-400">Mã giảm giá</p></div>
        </a>
        <a href="shop.php" class="sf-tile-link">
          <span class="sf-tile-icon" style="background:#fefce8;color:#ca8a04"><i class="fa-solid fa-coins"></i></span>
          <div class="min-w-0"><p class="text-xs font-bold text-gray-800">Shopee Xu</p><p class="text-[11px] text-gray-400">Tích xu hoàn tiền</p></div>
        </a>
        <a href="shop.php" class="sf-tile-link">
          <span class="sf-tile-icon" style="background:#eff6ff;color:#2563eb"><i class="fa-solid fa-store"></i></span>
          <div class="min-w-0"><p class="text-xs font-bold text-gray-800">Shopee Mall</p><p class="text-[11px] text-gray-400">Chính hãng 100%</p></div>
        </a>
      </div>
    </div>
  </section>

  <!-- ===================== TRUST STRIP ===================== -->
  <section class="bg-white border-y border-gray-100">
    <div class="sf-container py-4 grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-truck-fast"></i></div>
        <div><p class="text-xs font-bold text-gray-800">Miễn phí vận chuyển</p><p class="text-[11px] text-gray-400">Đơn từ 200.000đ</p></div>
      </div>
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-shield-halved"></i></div>
        <div><p class="text-xs font-bold text-gray-800">Chính hãng 100%</p><p class="text-[11px] text-gray-400">Cam kết chất lượng</p></div>
      </div>
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-orange-50 text-shopee flex items-center justify-center"><i class="fa-solid fa-rotate-left"></i></div>
        <div><p class="text-xs font-bold text-gray-800">Đổi trả 7 ngày</p><p class="text-[11px] text-gray-400">Thủ tục nhanh gọn</p></div>
      </div>
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center"><i class="fa-solid fa-headset"></i></div>
        <div><p class="text-xs font-bold text-gray-800">Hỗ trợ 24/7</p><p class="text-[11px] text-gray-400">Luôn sẵn sàng</p></div>
      </div>
    </div>
  </section>

  <!-- ===================== CATEGORIES ===================== -->
  <section class="sf-section">
    <div class="sf-container">
      <div class="sf-section-head">
        <div>
          <span class="sf-eyebrow"><i class="fa-solid fa-shapes"></i> Khám phá</span>
          <h2 class="sf-title">Danh mục sản phẩm</h2>
          <p class="sf-subtitle">Chọn danh mục để tìm sản phẩm phù hợp với nhu cầu của bạn</p>
        </div>
        <a href="shop.php" class="sf-link-more">Xem tất cả <i class="fa-solid fa-arrow-right text-[10px]"></i></a>
      </div>
      <div id="categoryGrid" class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2 sm:gap-3">
        <p class="col-span-full text-sm text-gray-400 text-center py-4">Đang tải danh mục...</p>
      </div>
    </div>
  </section>

  <!-- ===================== FLASH SALE + PRODUCT GRID ===================== -->
  <section class="sf-section--tight bg-white border-y border-gray-100 py-8">
    <div class="sf-container">
      <div class="sf-section-head">
        <div>
          <span class="sf-eyebrow"><i class="fa-solid fa-bolt"></i> Bán chạy</span>
          <h2 class="sf-title">Sản phẩm nổi bật</h2>
          <p class="sf-subtitle" id="gridSub">Đồng bộ trực tiếp với hệ thống quản trị</p>
        </div>
        <a href="shop.php" class="sf-link-more">Xem tất cả <i class="fa-solid fa-arrow-right text-[10px]"></i></a>
      </div>

      <div class="sf-flash mb-6">
        <div class="flex items-center gap-3.5">
          <span class="sf-flash-icon"><i class="fa-solid fa-bolt"></i></span>
          <div>
            <p class="text-sm font-extrabold uppercase tracking-wide">Flash Sale hôm nay</p>
            <p class="text-[11px] text-white/85">Số lượng có hạn — nhanh tay săn deal</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span class="hidden sm:inline text-[11px] text-white/80">Kết thúc sau</span>
          <span class="sf-countdown"><i class="fa-regular fa-clock text-sm"></i><span id="countdown">00:15:20</span></span>
        </div>
      </div>

      <div id="productGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4"></div>
    </div>
  </section>

<?php require __DIR__ . '/includes/layout/storefront_footer.php'; ?>

  <script>
    let products = [];
    let currentSlide = 0;
    // Banner hero: dùng ảnh SVG cục bộ (assets/images/) — không phụ thuộc
    // mạng ngoài nên luôn hiển thị được; hoạt ảnh trượt tự động 4.5s/slide.
    const slides = [
      { title: 'Khuyến mãi lớn', desc: 'Giảm đến 70% cho điện thoại, laptop, thời trang', img: 'assets/images/banner-sale.svg?v=2', bg: '#FFF6F0', tag: 'Sale khủng' },
      { title: 'FreeShip toàn quốc', desc: 'Miễn phí vận chuyển cho đơn trên 200.000đ', img: 'assets/images/banner-freeship.svg?v=2', bg: '#F0FFF6', tag: 'Freeship' },
      { title: 'Voucher giảm giá', desc: 'Nhận mã giảm giá đến 50% khi mua hàng hôm nay', img: 'assets/images/banner-voucher.svg?v=2', bg: '#FFF0F0', tag: 'Voucher' },
      { title: 'Flash Sale 12h', desc: 'Săn deal giá sốc mỗi ngày — số lượng có hạn', img: 'assets/images/banner-flash.svg?v=2', bg: '#FEF3C7', tag: 'Flash Sale' },
      { title: 'Xu Shopee x2', desc: 'Tích Xu gấp đôi mỗi đơn hàng — đổi quà khủng', img: 'assets/images/banner-coins.svg?v=2', bg: '#FEF3C7', tag: 'Hoàn xu' },
      { title: 'Thời trang', desc: 'Bộ sưu tập mới cho mọi phong cách, giá tốt mỗi ngày', img: 'assets/images/banner-fashion.svg?v=2', bg: '#F3E8FF', tag: 'Xu hướng' },
      { title: 'Công nghệ', desc: 'Điện thoại, laptop, phụ kiện chính hãng — bảo hành dài', img: 'assets/images/banner-tech.svg?v=2', bg: '#E0E7FF', tag: 'Công nghệ' },
      { title: 'Nhà cửa', desc: 'Đồ gia dụng tiện ích giúp tổ ấm thêm gọn gàng', img: 'assets/images/banner-home.svg?v=2', bg: '#D1FAE5', tag: 'Nhà cửa' },
    ];

    const sliderEl = document.getElementById('heroSlider');
    const dotsEl = document.getElementById('sliderDots');

    function buildSlider() {
      sliderEl.innerHTML = slides.map((s, i) => `
        <div class="carousel-item sf-hero-slide absolute inset-0 transition-opacity duration-700 ${i === 0 ? 'opacity-100' : 'opacity-0 pointer-events-none'}" style="background:${s.bg}">
          <img src="${s.img}" class="absolute inset-0 w-full h-full object-cover" alt="${s.title}" />
          <div class="absolute inset-0 bg-gradient-to-r from-slate-950/85 via-slate-900/45 to-transparent"></div>
          <div class="relative z-10 h-full flex flex-col justify-center px-7 sm:px-11 md:px-14 max-w-xl text-white">
            <span class="sf-hero-chip w-fit mb-3"><i class="fa-solid fa-bolt text-amber-300"></i> ${s.tag}</span>
            <h3 class="text-2xl sm:text-3xl md:text-[34px] font-extrabold leading-tight mb-2 drop-shadow-sm">${s.title}</h3>
            <p class="text-sm md:text-[15px] text-white/85 mb-5 max-w-md leading-relaxed">${s.desc}</p>
            <div class="flex flex-wrap items-center gap-3">
              <a href="shop.php" class="sf-btn sf-btn-primary">Mua ngay <i class="fa-solid fa-arrow-right text-[11px]"></i></a>
              <a href="shop.php" class="sf-btn border border-white/40 text-white hover:bg-white/10 backdrop-blur">Xem ưu đãi</a>
            </div>
            <div class="flex items-center gap-4 mt-6 text-[11px] text-white/70">
              <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-truck-fast text-amber-300"></i> Giao nhanh 2h</span>
              <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-shield-halved text-amber-300"></i> Bảo đảm chính hãng</span>
            </div>
          </div>
        </div>
      `).join('');
      dotsEl.innerHTML = slides.map((_, i) =>
        '<button type="button" data-slide="' + i + '" aria-label="Banner ' + (i + 1) + '" class="' + (i === 0 ? 'w-7 bg-white' : 'w-2 bg-white/50 hover:bg-white/80') + '"></button>'
      ).join('');
      dotsEl.querySelectorAll('[data-slide]').forEach((btn) => {
        btn.addEventListener('click', () => goToSlide(Number(btn.getAttribute('data-slide'))));
      });
    }

    function updateSlider() {
      Array.prototype.forEach.call(sliderEl.children, (el, i) => {
        el.classList.toggle('opacity-100', i === currentSlide);
        el.classList.toggle('opacity-0', i !== currentSlide);
        el.classList.toggle('pointer-events-none', i !== currentSlide);
      });
      Array.prototype.forEach.call(dotsEl.children, (el, i) => {
        el.classList.toggle('w-7', i === currentSlide);
        el.classList.toggle('w-2', i !== currentSlide);
        el.classList.toggle('bg-white', i === currentSlide);
        el.classList.toggle('bg-white/50', i !== currentSlide);
      });
      const counter = document.getElementById('slideCounter');
      if (counter) counter.textContent = (currentSlide + 1) + ' / ' + slides.length;
    }

    function showSlide(idx) {
      currentSlide = (idx + slides.length) % slides.length;
      updateSlider();
    }
    function nextSlide() { showSlide(currentSlide + 1); }
    function prevSlide() { showSlide(currentSlide - 1); }
    function goToSlide(idx) { showSlide(idx); }
    let slideTimer = setInterval(() => { nextSlide(); }, 4500);
    sliderEl.addEventListener('mouseenter', () => clearInterval(slideTimer));
    sliderEl.addEventListener('mouseleave', () => { slideTimer = setInterval(() => { nextSlide(); }, 4500); });
    buildSlider();
    updateSlider();

    const grid = document.getElementById('productGrid');

    function renderAccountArea() {
      const area = document.getElementById('accountArea');
      const user = OF.session.getUser();
      if (!user) {
        area.innerHTML =
          '<a href="register.php" class="text-[11.5px] font-medium">Đăng ký</a>' +
          '<span class="mx-2.5 opacity-40">/</span>' +
          '<a href="login.php" class="text-[11.5px] font-bold underline-offset-4 hover:underline">Đăng nhập</a>';
        return;
      }
      const fullName = user.full_name || '';
      const initial = fullName.trim().charAt(0).toUpperCase() || '?';
      const isAdmin = user.role === 'admin' || user.role === 'staff';
      area.innerHTML =
        '<button id="accountToggle" type="button" class="flex items-center gap-2 text-[11.5px] font-medium">' +
          '<span class="w-6 h-6 rounded-full bg-white/20 border border-white/40 text-white text-[11px] font-bold flex items-center justify-center">' + OF.escapeHtml(initial) + '</span>' +
          '<span class="hidden sm:inline">Xin chào, ' + OF.escapeHtml(fullName) + '</span>' +
          '<i class="fa-solid fa-chevron-down text-[9px] opacity-70"></i>' +
        '</button>' +
        '<div id="accountDropdown" class="hidden sf-account-menu absolute top-full mt-2 right-0 w-60 py-2 z-[70]">' +
          '<div class="px-4 py-2.5 border-b border-gray-50 mb-1">' +
            '<p class="text-sm font-bold text-gray-900 truncate">' + OF.escapeHtml(fullName) + '</p>' +
            '<p class="text-[11px] text-gray-400 truncate">' + OF.escapeHtml(user.email || '') + '</p>' +
          '</div>' +
          '<a href="orders.php" class="px-4 py-2 text-[13px] flex items-center gap-2.5"><i class="fa-solid fa-box text-gray-400 w-4"></i> Đơn hàng của tôi</a>' +
          '<a href="returns.php" class="px-4 py-2 text-[13px] flex items-center gap-2.5"><i class="fa-solid fa-rotate-left text-gray-400 w-4"></i> Yêu cầu đổi trả</a>' +
          '<a href="cart.php" class="px-4 py-2 text-[13px] flex items-center gap-2.5"><i class="fa-solid fa-cart-shopping text-gray-400 w-4"></i> Giỏ hàng</a>' +
          (isAdmin
            ? '<div class="my-1 h-px bg-gray-100"></div><a href="dashboard.php" class="px-4 py-2 text-[13px] flex items-center gap-2.5"><i class="fa-solid fa-gauge-high text-gray-400 w-4"></i> Trang quản trị</a>'
            : '') +
          '<div class="my-1 h-px bg-gray-100"></div>' +
          '<button id="logoutBtn" type="button" class="w-full text-left px-4 py-2 text-[13px] text-red-500 hover:bg-red-50 flex items-center gap-2.5">' +
            '<i class="fa-solid fa-arrow-right-from-bracket w-4"></i> Đăng xuất</button>' +
        '</div>';
      const toggleBtn = document.getElementById('accountToggle');
      const dropdown = document.getElementById('accountDropdown');
      toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('hidden');
      });
      document.getElementById('logoutBtn').addEventListener('click', async () => {
        OF.session.clear();
        dropdown.classList.add('hidden');
        renderAccountArea();
        loadProducts();
        loadCategories();
        showToast('Đã đăng xuất khỏi tài khoản.');
        OF.api.post('auth/logout.php');
      });
      document.addEventListener('click', (e) => {
        if (!dropdown.isConnected || area.contains(e.target)) return;
        dropdown.classList.add('hidden');
      });
    }

    function starString(rating) {
      let html = '';
      for (let i = 1; i <= 5; i++) {
        html += '<i class="fa-solid fa-star ' + (i <= Math.round(rating) ? '' : 'is-empty') + '"></i>';
      }
      return html;
    }

    function ratingOf(p) {
      return 4.3 + ((Number(p.id) * 37) % 7) / 10;
    }

    function soldOf(p) {
      return 100 + (Number(p.id) * 137) % 900;
    }

    function productCard(p) {
      const soldOut = Number(p.stock) <= 0;
      const lowStock = !soldOut && Number(p.stock) <= 5;
      const price = Number(p.price) || 0;
      const discount = [10, 15, 20, 25, 30][Number(p.id) % 5];
      const oldPrice = Math.round(price * (1 + discount / 100) / 1000) * 1000;
      const rating = ratingOf(p);
      const cover = p.cover_image
        ? `<img src="${OF.escapeHtml(p.cover_image)}" alt="${OF.escapeHtml(p.name)}" loading="lazy" decoding="async" onerror="this.style.display='none';this.nextElementSibling.classList.remove('hidden')" /><span class="sf-product-emoji hidden">${OF.escapeHtml(p.emoji || '📦')}</span>`
        : `<span class="sf-product-emoji" title="${OF.escapeHtml(p.sku || '')}">${OF.escapeHtml(p.emoji || '📦')}</span>`;
      return `
        <article class="sf-product group" onclick="location.href='product.php?id=${p.id}'">
          <div class="sf-product-media">
            ${cover}
            ${soldOut ? '<span class="sf-badge sf-badge-out">Hết hàng</span>' : '<span class="sf-badge sf-badge-sale"><i class="fa-solid fa-bolt"></i> -' + discount + '%</span>'}
            ${lowStock ? `<span class="sf-badge sf-badge-low">Chỉ còn ${p.stock}</span>` : ''}
            <span class="sf-quick-add"><i class="fa-solid fa-eye"></i> Xem nhanh</span>
          </div>
          <div class="sf-product-body">
            <h3 class="sf-product-name">${OF.escapeHtml(p.name)}</h3>
            <div class="sf-product-meta">
              <span class="sf-stars">${starString(rating)}</span>
              <span class="sf-rating-num">${rating.toFixed(1)}</span>
              <span class="sf-sold">Đã bán ${soldOf(p)}+</span>
            </div>
            <div class="sf-price-row">
              <span class="sf-price">${price.toLocaleString('vi-VN')}<sup>₫</sup></span>
              ${soldOut ? '' : `<span class="sf-price-old">${oldPrice.toLocaleString('vi-VN')}₫</span>`}
            </div>
            <div class="flex items-center justify-between gap-2 pt-1">
              <span class="sf-badge sf-badge-cat">${OF.escapeHtml(p.category_name || 'Khác')}</span>
              <span class="text-[10.5px] text-gray-400 inline-flex items-center gap-1"><i class="fa-solid fa-location-dot text-[9px]"></i> TP.HCM</span>
            </div>
          </div>
        </article>`;
    }

    function renderSkeletons(count) {
      grid.innerHTML = Array.from({ length: count }, () =>
        '<div class="sf-skeleton-card"><div class="sf-skeleton sf-skeleton-media"></div><div class="sf-skeleton-body">' +
        '<div class="sf-skeleton" style="height:12px;width:92%"></div>' +
        '<div class="sf-skeleton" style="height:12px;width:64%"></div>' +
        '<div class="sf-skeleton" style="height:18px;width:48%;margin-top:4px"></div>' +
        '</div></div>'
      ).join('');
    }

    function renderProducts() {
      if (!products.length) {
        grid.innerHTML = '<div class="col-span-full bg-white rounded-2xl border border-gray-100 p-12 text-center text-gray-500 text-sm">Chưa có sản phẩm đang bán trong hệ thống.</div>';
        return;
      }
      grid.innerHTML = products.map(productCard).join('');
    }

    async function loadProducts() {
      renderSkeletons(10);
      const res = await OF.api.get('products/index.php', 'per_page=200&status=active&public=1');
      if (!res.success) {
        grid.innerHTML = '<div class="col-span-full bg-white rounded-2xl border border-gray-100 p-12 text-center text-gray-500 text-sm">' + OF.escapeHtml(res.message || 'Không tải được sản phẩm.') + '</div>';
        return;
      }
      products = res.data.items || [];
      renderProducts();
      renderSearchChips();
    }

    function catSkeletons() {
      const el = document.getElementById('categoryGrid');
      el.innerHTML = Array.from({ length: 8 }, () =>
        '<div class="flex flex-col items-center gap-2 py-1"><div class="sf-skeleton" style="width:58px;height:58px;border-radius:18px"></div><div class="sf-skeleton" style="width:54px;height:9px"></div></div>'
      ).join('');
    }

    async function loadCategories() {
      const el = document.getElementById('categoryGrid');
      catSkeletons();
      const res = await OF.api.get('categories/index.php', 'public=1');
      if (!res.success) {
        el.innerHTML = '<p class="col-span-full text-sm text-gray-400 text-center">' + OF.escapeHtml(res.message || 'Không tải được danh mục.') + '</p>';
        return;
      }
      el.innerHTML = (res.data || []).map(c => `
        <a href="shop.php?category_id=${c.id}" class="sf-cat">
          <span class="sf-cat-icon">${OF.escapeHtml(c.emoji || '📦')}</span>
          <span class="sf-cat-name">${OF.escapeHtml(c.name)}</span>
        </a>`).join('');
    }

    function renderSearchChips() {
      const wrap = document.getElementById('searchSuggestions');
      wrap.innerHTML = products.slice(0, 8).map(p =>
        '<a href="shop.php?q=' + encodeURIComponent(p.name) + '" class="sf-hotkey">' + OF.escapeHtml(p.name) + '</a>'
      ).join('') || '<span class="sf-hotkey">Chưa có gợi ý</span>';
    }

    const cartBadge = document.getElementById('cartBadge');

    async function refreshCartBadge() {
      const res = await OF.api.get('carts/index.php');
      if (!res.success) return;
      updateCartBadge(Number(res.data.item_count || 0));
    }

    function updateCartBadge(count) {
      if (count > 0) {
        cartBadge.textContent = count;
        cartBadge.classList.remove('scale-0');
        cartBadge.classList.add('scale-100');
      } else {
        cartBadge.classList.remove('scale-100');
        cartBadge.classList.add('scale-0');
      }
    }

    function showToast(msg) {
      if (window.sfToast) window.sfToast(msg);
    }

    document.getElementById('searchInput').addEventListener('input', (e) => {
      const val = e.target.value.toLowerCase();
      const chips = document.querySelectorAll('#searchSuggestions .sf-hotkey');
      chips.forEach(c => {
        c.style.display = val === '' || c.textContent.toLowerCase().includes(val) ? 'inline-flex' : 'none';
      });
    });

    function goSearch() {
      const val = document.getElementById('searchInput').value.trim();
      location.href = val ? ('shop.php?q=' + encodeURIComponent(val)) : 'shop.php';
    }
    document.getElementById('searchInput').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') goSearch();
    });
    document.querySelectorAll('header form').forEach(f => {
      f.addEventListener('submit', (e) => { e.preventDefault(); goSearch(); });
    });

    let secs = 920;
    setInterval(() => {
      secs--;
      if (secs < 0) secs = 920;
      const m = Math.floor(secs / 60).toString().padStart(2, '0');
      const s = (secs % 60).toString().padStart(2, '0');
      const el = document.getElementById('countdown');
      if (el) el.textContent = `00:${m}:${s}`;
    }, 1000);

    // Sản phẩm & danh mục là API công khai (?public=1) — khách chưa đăng
    // nhập vẫn xem được; chỉ giỏ hàng cần token để hiển thị số lượng.
    loadCategories();
    loadProducts();
    if (OF.session.getToken()) {
      refreshCartBadge();
    }
    renderAccountArea();

  </script>
</body>
</html>
