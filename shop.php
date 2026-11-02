<?php
declare(strict_types=1);

$pageTitle = 'Shopee - Mua sắm trực tuyến';

require __DIR__ . '/includes/layout/storefront_top.php';
?>

  <section class="sf-hero" style="padding-bottom:20px">
    <div class="sf-container">
      <nav class="sf-breadcrumb mb-4">
        <a href="index.php">Trang chủ</a>
        <span class="sep"><i class="fa-solid fa-chevron-right text-[8px]"></i></span>
        <a href="shop.php">Sản phẩm</a>
        <span class="sep"><i class="fa-solid fa-chevron-right text-[8px]"></i></span>
        <span class="current" id="crumbCurrent">Tất cả</span>
      </nav>
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <span class="sf-eyebrow"><i class="fa-solid fa-bag-shopping"></i> Cửa hàng</span>
          <h1 class="sf-title text-2xl" id="catalogTitle">Tất cả sản phẩm</h1>
          <p class="sf-subtitle" id="catalogSub">Danh mục sản phẩm đồng bộ trực tiếp với hệ thống Shopee.</p>
        </div>
        <div class="flex items-center gap-2">
          <span class="sf-chip" style="cursor:default"><i class="fa-solid fa-boxes-stacked text-[11px]"></i> <span id="shopCount">Đang tải…</span></span>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== CATALOG ===================== -->
  <section class="py-7 min-h-[50vh]">
    <div class="sf-container">
      <div class="sf-panel p-4 sm:p-5 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center gap-4">
          <div class="flex-1 min-w-0">
            <p class="text-[10.5px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2">Danh mục</p>
            <div id="shopCategoryRow" class="flex gap-2 overflow-x-auto pb-1 custom-scrollbar"></div>
          </div>
          <div class="flex items-center gap-2 lg:border-l lg:border-gray-100 lg:pl-4 flex-none">
            <label for="shopSort" class="text-[11px] font-semibold text-gray-400 whitespace-nowrap">Sắp xếp</label>
            <select id="shopSort" class="sf-select">
              <option value="new">Mới nhất</option>
              <option value="low">Giá tăng dần</option>
              <option value="high">Giá giảm dần</option>
            </select>
          </div>
        </div>
      </div>

      <div id="shopGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4">
        <p class="col-span-full text-sm text-gray-400 text-center py-10">Đang tải sản phẩm...</p>
      </div>
      <div id="shopEmpty" class="hidden bg-white rounded-2xl border border-gray-100 p-12 text-center">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-orange-50 text-shopee flex items-center justify-center text-2xl"><i class="fa-solid fa-magnifying-glass"></i></div>
        <h3 class="text-sm font-bold text-gray-800 mb-1">Không tìm thấy sản phẩm phù hợp</h3>
        <p class="text-xs text-gray-400 mb-4">Hãy thử từ khóa khác hoặc bỏ bớt bộ lọc danh mục.</p>
        <a href="shop.php" class="sf-btn sf-btn-soft"><i class="fa-solid fa-rotate-left text-[11px]"></i> Xem tất cả sản phẩm</a>
      </div>
    </div>
  </section>

<?php require __DIR__ . '/includes/layout/storefront_footer.php'; ?>

<script>
    (function () {
      const OF = window.OF;
      const grid = document.getElementById('shopGrid');
      const empty = document.getElementById('shopEmpty');
      const catRow = document.getElementById('shopCategoryRow');
      const params = new URLSearchParams(location.search);
      const catId = params.get('category_id') || '';
      const q = (params.get('q') || '').trim();
      const searchEl = document.getElementById('searchInput');
      if (searchEl && q) searchEl.value = q;
      let products = [];

      function renderCats(cats) {
        const base = 'whitespace-nowrap px-3.5 py-1.5 rounded-full text-xs font-semibold border transition';
        const on = 'bg-shopee-orange text-white border-shopee-orange shadow-sm';
        const off = 'bg-white text-gray-600 border-gray-200 hover:border-shopee hover:text-shopee';
        const htmlHome = '<a href="shop.php" class="' + base + ' ' + (!catId ? on : off) + '">Tất cả</a>';
        catRow.innerHTML = htmlHome + (cats || []).map(c =>
          '<a href="shop.php?category_id=' + c.id + '" class="' + base + ' ' + (String(catId) === String(c.id) ? on : off) + '">' + OF.escapeHtml(c.name) + '</a>'
        ).join('');
      }

      function applySort(list) {
        const mode = document.getElementById('shopSort').value;
        const arr = list.slice();
        if (mode === 'low') arr.sort((a, b) => a.price - b.price);
        if (mode === 'high') arr.sort((a, b) => b.price - a.price);
        return arr;
      }

      function starString(rating) {
        let html = '';
        for (let i = 1; i <= 5; i++) {
          html += '<i class="fa-solid fa-star ' + (i <= Math.round(rating) ? '' : 'is-empty') + '"></i>';
        }
        return html;
      }

      function ratingOf(p) { return 4.3 + ((Number(p.id) * 37) % 7) / 10; }
      function soldOf(p) { return 100 + (Number(p.id) * 137) % 900; }

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

      function render() {
        const list = applySort(products);
        document.getElementById('shopCount').textContent = list.length + ' sản phẩm';
        if (!list.length) { grid.innerHTML = ''; empty.classList.remove('hidden'); return; }
        empty.classList.add('hidden');
        grid.innerHTML = list.map(productCard).join('');
      }

      document.getElementById('shopSort').addEventListener('change', render);

      (async function boot() {
        renderSkeletons(10);
        const catRes = await OF.api.get('categories/index.php', 'public=1');
        const cats = (catRes.success ? catRes.data : []);
        renderCats(cats);
        const active = cats.find(c => String(c.id) === String(catId));
        const titleEl = document.getElementById('catalogTitle');
        const crumbEl = document.getElementById('crumbCurrent');
        if (active) {
          titleEl.textContent = active.name;
          crumbEl.textContent = active.name;
          document.title = active.name + ' – Shopee.vn';
        } else if (q) {
          titleEl.textContent = 'Kết quả cho "' + q + '"';
          crumbEl.textContent = 'Tìm kiếm';
          document.title = 'Tìm kiếm "' + q + '" – Shopee.vn';
        }

        const query = 'per_page=200&status=active&public=1' + (catId ? '&category_id=' + catId : '') + (q ? '&q=' + encodeURIComponent(q) : '');
        const res = await OF.api.get('products/index.php', query);
        if (res.success) { products = res.data.items || []; render(); }
        else { grid.innerHTML = ''; empty.querySelector('p').textContent = res.message || 'Không tải được sản phẩm.'; empty.classList.remove('hidden'); }

        // Header giống trang chủ: tài khoản + giỏ hàng
        const area = document.getElementById('accountArea');
        const user = OF.session.getUser();
        if (!user) {
          area.innerHTML = '<a href="register.php" class="text-[11.5px] font-medium">Đăng ký</a><span class="mx-2.5 opacity-40">/</span><a href="login.php" class="text-[11.5px] font-bold underline-offset-4 hover:underline">Đăng nhập</a>';
        } else {
          const fullName = user.full_name || '';
          const initial = fullName.trim().charAt(0).toUpperCase() || '?';
          const target = (user.role === 'admin' || user.role === 'staff') ? 'dashboard.php' : 'orders.php';
          area.innerHTML = '<a href="' + target + '" class="flex items-center gap-2 text-[11.5px] font-medium">' +
            '<span class="w-6 h-6 rounded-full bg-white/20 border border-white/40 text-white text-[11px] font-bold flex items-center justify-center">' + OF.escapeHtml(initial) + '</span>' +
            '<span class="hidden sm:inline">Xin chào, ' + OF.escapeHtml(fullName) + '</span></a>';
        }
        if (OF.session.getToken()) {
          const cb = document.getElementById('cartBadge');
          const res2 = await OF.api.get('carts/index.php');
          if (res2.success && Number(res2.data.item_count) > 0) {
            cb.textContent = res2.data.item_count;
            cb.classList.remove('scale-0'); cb.classList.add('scale-100');
          }
        }
      })();
    })();
  </script>
</body>
</html>
