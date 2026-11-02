<?php
declare(strict_types=1);

$pageTitle = 'Shopee - Mua sắm trực tuyến';

require __DIR__ . '/includes/layout/storefront_top.php';
?>

<!-- ===================== PRODUCT DETAIL ===================== -->
  <section class="py-6 min-h-[60vh]">
    <div class="sf-container">
      <nav class="sf-breadcrumb mb-4" id="breadcrumb">
        <a href="index.php">Trang chủ</a>
        <span class="sep"><i class="fa-solid fa-chevron-right text-[8px]"></i></span>
        <a href="shop.php" id="crumbCategory">Danh mục</a>
        <span class="sep"><i class="fa-solid fa-chevron-right text-[8px]"></i></span>
        <span class="current truncate max-w-[320px]" id="crumbName">Sản phẩm</span>
      </nav>

      <div class="sf-pdp-card p-4 sm:p-6">
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,460px)_minmax(0,1fr)] gap-7 lg:gap-9">
          <!-- Media -->
          <div>
            <div class="sf-gallery-main">
              <img id="pMainImage" class="hidden" alt="" />
              <span id="pEmoji" class="sf-gallery-emoji">📦</span>
              <span class="absolute top-3 left-3 sf-badge sf-badge-sale"><i class="fa-solid fa-bolt"></i> Ưu đãi hôm nay</span>
              <span class="absolute top-3 right-3 inline-flex items-center gap-1.5 bg-white/85 backdrop-blur text-[10.5px] font-bold text-gray-600 px-2.5 py-1 rounded-full border border-white shadow-sm">
                <i class="fa-solid fa-shield-halved text-emerald-500"></i> Chính hãng
              </span>
            </div>
            <div class="flex gap-2 mt-3 overflow-x-auto pb-1" id="pThumbs"></div>
            <div class="grid grid-cols-3 gap-2 mt-4">
              <div class="flex items-center justify-center gap-1.5 text-[10.5px] font-semibold text-gray-500 py-2 rounded-lg bg-gray-50"><i class="fa-solid fa-rotate-left text-shopee"></i> Đổi trả 7 ngày</div>
              <div class="flex items-center justify-center gap-1.5 text-[10.5px] font-semibold text-gray-500 py-2 rounded-lg bg-gray-50"><i class="fa-solid fa-truck-fast text-emerald-500"></i> Giao nhanh 2h</div>
              <div class="flex items-center justify-center gap-1.5 text-[10.5px] font-semibold text-gray-500 py-2 rounded-lg bg-gray-50"><i class="fa-solid fa-lock text-blue-500"></i> Thanh toán an toàn</div>
            </div>
          </div>

          <!-- Info -->
          <div class="min-w-0">
            <div class="flex items-start justify-between gap-3">
              <h1 id="pTitle" class="sf-pdp-title">Đang tải sản phẩm...</h1>
              <button type="button" class="flex-none inline-flex items-center gap-1.5 text-[11px] font-semibold text-gray-500 hover:text-shopee transition" aria-label="Yêu thích">
                <i class="fa-regular fa-heart text-lg"></i>
              </button>
            </div>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 mt-2.5 text-[11.5px] text-gray-400">
              <span class="inline-flex items-center gap-1.5" id="pRatingLine"><span class="sf-stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></span> <span class="text-gray-600 font-semibold">5.0</span></span>
              <span class="text-gray-200">|</span>
              <span>Đã bán <span id="pSold" class="text-gray-600 font-semibold">—</span></span>
              <span class="text-gray-200">|</span>
              <span class="inline-flex items-center gap-1"><i class="fa-solid fa-location-dot text-[10px]"></i> TP. Hồ Chí Minh</span>
              <span class="text-gray-200">|</span>
              <a href="#" class="hover:text-shopee">Tố cáo</a>
            </div>

            <div class="sf-pdp-pricebox my-4">
              <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Giá bán</span>
              <span id="pPrice" class="sf-pdp-price">…₫</span>
              <span id="pDiscountBadge" class="sf-badge sf-badge-sale">-20%</span>
              <span id="pOldPrice" class="sf-price-old text-sm"></span>
            </div>

            <div id="pVariantBlock" class="mb-5">
              <label class="flex items-center gap-2 text-[12.5px] font-semibold text-gray-600 mb-2.5">Phân loại <span class="text-gray-400 font-normal">— chọn phiên bản phù hợp</span></label>
              <div id="pVariants" class="flex flex-wrap gap-2"></div>
            </div>

            <div class="mb-5">
              <label class="block text-[12.5px] font-semibold text-gray-600 mb-2.5">Số lượng</label>
              <div class="flex items-center gap-4 flex-wrap">
                <div class="sf-qty">
                  <button id="qtyMinus" aria-label="Giảm số lượng">−</button>
                  <input id="qtyInput" value="1" inputmode="numeric" aria-label="Số lượng" />
                  <button id="qtyPlus" aria-label="Tăng số lượng">+</button>
                </div>
                <span class="text-[11.5px] text-gray-400" id="pStockNote"></span>
              </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-2.5">
              <button id="btnAddCart" class="sf-btn sf-btn-soft sf-btn-lg flex-1">
                <i class="fa-solid fa-cart-plus"></i> Thêm vào giỏ hàng
              </button>
              <button id="btnBuyNow" class="sf-btn sf-btn-primary sf-btn-lg flex-1">
                Mua ngay <i class="fa-solid fa-arrow-right text-[11px]"></i>
              </button>
            </div>
            <p id="pError" class="hidden mt-2 text-xs text-red-500"></p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 mt-5">
              <div class="sf-benefit"><i class="fa-solid fa-truck-fast"></i><span><strong class="block text-gray-700 text-[11.5px]">Miễn phí vận chuyển</strong>Cho đơn từ 200.000₫</span></div>
              <div class="sf-benefit"><i class="fa-solid fa-shield-halved"></i><span><strong class="block text-gray-700 text-[11.5px]">Bảo vệ người mua</strong>Hoàn tiền nếu không đúng mô tả</span></div>
              <div class="sf-benefit"><i class="fa-solid fa-rotate-left"></i><span><strong class="block text-gray-700 text-[11.5px]">Đổi trả dễ dàng</strong>Trong vòng 7 ngày</span></div>
              <div class="sf-benefit"><i class="fa-solid fa-box-open"></i><span><strong class="block text-gray-700 text-[11.5px]">Kiểm tra hàng</strong>Đồng kiểm khi nhận</span></div>
            </div>

            <div class="mt-5 flex items-center gap-3 p-3 rounded-xl border border-gray-100 bg-gray-50/60">
              <span class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-shopee"><i class="fa-solid fa-store"></i></span>
              <div class="min-w-0">
                <p class="text-[12.5px] font-bold text-gray-800">Shopee Official Store</p>
                <p class="text-[11px] text-gray-400">Chính hãng · Phản hồi trong 5 phút · 98% đánh giá tốt</p>
              </div>
              <a href="shop.php" class="sf-chip ml-auto hidden sm:inline-flex">Xem shop</a>
            </div>

            <div class="mt-4 text-[12px] text-gray-500">
              <span class="inline-flex items-center gap-2"><i class="fa-solid fa-warehouse text-gray-400"></i> <span id="pStockLine">—</span></span>
            </div>
          </div>
        </div>
      </div>

      <div class="sf-pdp-card mt-5 overflow-hidden">
        <div class="sf-tabbar px-4 sm:px-6">
          <a href="#pSpecs" class="sf-tab is-active">Chi tiết sản phẩm</a>
          <a href="#pDescription" class="sf-tab">Mô tả</a>
          <a href="#reviewsSection" class="sf-tab">Đánh giá</a>
        </div>
        <div class="p-4 sm:p-6">
          <h2 class="text-base font-extrabold text-gray-800 mb-4 flex items-center gap-2"><i class="fa-solid fa-list-check text-shopee text-sm"></i> Thông số sản phẩm</h2>
          <div id="pSpecs" class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-0 text-sm"></div>
        </div>
      </div>

      <div class="sf-pdp-card mt-5 p-4 sm:p-6" id="pDescription">
        <h2 class="text-base font-extrabold text-gray-800 mb-4 flex items-center gap-2"><i class="fa-solid fa-align-left text-shopee text-sm"></i> Mô tả sản phẩm</h2>
        <div id="pDescriptionBody" class="text-[13.5px] leading-7 text-gray-600 whitespace-pre-line"></div>
      </div>

      <section id="reviewsSection" class="sf-pdp-card mt-5 p-4 sm:p-6">
        <div class="flex items-center justify-between gap-3 mb-5">
          <h2 class="text-base font-extrabold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-star text-shopee text-sm"></i> Đánh giá sản phẩm</h2>
          <span class="text-[11px] text-gray-400">Đánh giá từ người mua đã xác thực</span>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-8">
          <div class="lg:border-r lg:border-gray-100 lg:pr-7">
            <div class="flex items-end gap-3">
              <span id="reviewAvg" class="text-[42px] font-extrabold text-shopee leading-none">0</span>
              <div class="pb-1">
                <div id="reviewAvgStars" class="text-sm"></div>
                <div id="reviewTotal" class="text-[11.5px] text-gray-400 mt-1">0 đánh giá</div>
              </div>
            </div>
            <div id="reviewHistogram" class="mt-5 space-y-2"></div>
            <div id="reviewSummaryMeta" class="mt-4 text-[11.5px] leading-5 text-gray-500"></div>
          </div>
          <div class="min-w-0">
            <div id="reviewFilters" class="flex flex-wrap gap-2 mb-3">
              <button type="button" class="sf-chip is-active" data-filter="all">Tất cả</button>
              <button type="button" class="sf-chip" data-filter="5">5 sao</button>
              <button type="button" class="sf-chip" data-filter="4">4 sao</button>
              <button type="button" class="sf-chip" data-filter="3">3 sao</button>
              <button type="button" class="sf-chip" data-filter="2">2 sao</button>
              <button type="button" class="sf-chip" data-filter="1">1 sao</button>
              <button type="button" class="sf-chip" data-filter="images"><i class="fa-regular fa-image text-[10px]"></i> Có hình ảnh</button>
              <button type="button" class="sf-chip" data-filter="comments"><i class="fa-regular fa-comment-dots text-[10px]"></i> Có bình luận</button>
            </div>
            <div class="flex items-center justify-between gap-2 mb-2">
              <span class="text-[11px] text-gray-400">Sắp xếp theo</span>
              <div class="flex gap-2 text-xs">
                <button type="button" class="sf-chip is-active" data-sort="newest">Mới nhất</button>
                <button type="button" class="sf-chip" data-sort="helpful">Hữu ích</button>
              </div>
            </div>
            <div id="reviewList" class="divide-y divide-gray-100"></div>
            <div id="reviewPager" class="mt-5 flex items-center justify-between text-xs text-gray-500"></div>
          </div>
        </div>
      </section>

      <div class="mt-8">
        <div class="sf-section-head">
          <div>
            <span class="sf-eyebrow"><i class="fa-solid fa-wand-magic-sparkles"></i> Gợi ý</span>
            <h2 class="sf-title">Sản phẩm cùng danh mục</h2>
          </div>
          <a href="shop.php" class="sf-link-more">Xem tất cả <i class="fa-solid fa-arrow-right text-[10px]"></i></a>
        </div>
        <div id="relatedGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4"></div>
      </div>
    </div>
  </section>

<?php require __DIR__ . '/includes/layout/storefront_footer.php'; ?>

<script>
    (function () {
      const OF = window.OF;
      const params = new URLSearchParams(location.search);
      const id = Number(params.get('id') || 0);
      let product = null;
      let selectedVariantId = null;
      let selectedVariant = '';
      let qty = 1;

      function currentVariant() {
        if (!product || !product.variant_rows) return null;
        return product.variant_rows.find(v => Number(v.id) === Number(selectedVariantId)) || null;
      }
      function currentStock() {
        const v = currentVariant();
        return Number(v ? v.stock : (product ? product.stock : 0));
      }
      function currentPrice() {
        const v = currentVariant();
        return Number(v && v.price != null ? v.price : (product ? product.price : 0));
      }

      function toast(msg, ok) {
        const el = document.getElementById('pError');
        el.textContent = msg;
        el.classList.remove('hidden');
        el.className = ok ? 'mt-2 text-xs text-green-600' : 'mt-2 text-xs text-red-500';
        setTimeout(() => { el.classList.add('hidden'); }, 2600);
      }

      function updateQtyUI() {
        const stock = currentStock();
        const max = Math.max(1, stock);
        if (qty > max) qty = max;
        document.getElementById('qtyInput').value = qty;
        document.getElementById('qtyMinus').disabled = qty <= 1;
        document.getElementById('qtyPlus').disabled = qty >= max || stock <= 0;
        const note = document.getElementById('pStockNote');
        note.textContent = product ? ('Còn ' + stock + ' sản phẩm có sẵn') : '';
        document.getElementById('pPrice').textContent = product ? (currentPrice().toLocaleString('vi-VN') + '₫') : '…₫';
        const stockLine = document.getElementById('pStockLine');
        if (stockLine && product) stockLine.textContent = stock > 0 ? (stock + ' sản phẩm trong kho') : 'Hết hàng';
        const out = stock <= 0;
        document.getElementById('btnAddCart').disabled = out;
        document.getElementById('btnBuyNow').disabled = out;
      }

      function selectVariant(vid, name) {
        selectedVariantId = vid;
        selectedVariant = name || '';
        document.querySelectorAll('[data-variant]').forEach(b => {
          const on = Number(b.getAttribute('data-vid')) === Number(vid);
          b.classList.toggle('is-active', on);
        });
        updateQtyUI();
      }

      function renderVariants() {
        const wrap = document.getElementById('pVariants');
        const rows = product.variant_rows || [];
        if (!rows.length) {
          document.getElementById('pVariantBlock').style.display = 'none';
          selectedVariantId = null;
          selectedVariant = '';
          return;
        }
        document.getElementById('pVariantBlock').style.display = '';
        wrap.innerHTML = rows.map(v => {
          const price = Number(v.price != null ? v.price : product.price);
          const out = Number(v.stock) <= 0;
          return '<button type="button" data-vid="' + v.id + '" data-variant="' + OF.escapeHtml(v.name) + '" class="sf-variant' + (out ? ' is-disabled' : '') + '">' +
            OF.escapeHtml(v.name) + ' · ' + price.toLocaleString('vi-VN') + '₫' + (out ? ' (hết)' : '') + '</button>';
        }).join('');
        wrap.querySelectorAll('[data-variant]').forEach(btn => {
          btn.addEventListener('click', () => {
            if (btn.classList.contains('is-disabled')) return;
            selectVariant(btn.getAttribute('data-vid'), btn.getAttribute('data-variant'));
          });
        });
        const first = rows.find(v => Number(v.stock) > 0) || rows[0];
        selectVariant(first.id, first.name);
      }

      function bindQty() {
        document.getElementById('qtyMinus').addEventListener('click', () => { if (qty > 1) { qty--; updateQtyUI(); } });
        document.getElementById('qtyPlus').addEventListener('click', () => { const max = Math.max(1, currentStock()); if (qty < max) { qty++; updateQtyUI(); } });
        document.getElementById('qtyInput').addEventListener('input', (e) => {
          const v = Math.max(1, Math.min(Math.max(1, currentStock()), Number(e.target.value) || 1));
          qty = v;
        });
      }

      function hardcodedHeader(user) {
        const area = document.getElementById('accountArea');
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
      }

      async function refreshCartBadge() {
        if (!OF.session.getToken()) return;
        const res = await OF.api.get('carts/index.php');
        if (res.success && Number(res.data.item_count) > 0) {
          const cb = document.getElementById('cartBadge');
          cb.textContent = res.data.item_count;
          cb.classList.remove('scale-0'); cb.classList.add('scale-100');
        }
      }

      async function addToCart(goCart) {
        if (!product) return;
        const stock = currentStock();
        if (stock <= 0) { toast('Sản phẩm đã hết hàng.'); return; }
        if (product.variant_rows && product.variant_rows.length && !selectedVariantId) {
          toast('Vui lòng chọn biến thể.');
          return;
        }
        if (!OF.session.getToken()) {
          toast('Vui lòng đăng nhập để mua sắm!');
          setTimeout(() => { location.href = 'login.php'; }, 700);
          return;
        }
        const body = { product_id: product.id, quantity: qty };
        if (selectedVariantId) {
          body.variant_id = selectedVariantId;
          body.variant = selectedVariant;
        }
        const res = await OF.api.post('carts/index.php', body);
        if (!res.success) { toast(res.message || 'Không thêm được vào giỏ hàng!'); return; }
        toast('Đã thêm vào giỏ hàng!', true);
        refreshCartBadge();
        if (goCart) setTimeout(() => { location.href = 'cart.php'; }, 600);
      }

      function renderGallery() {
        const main = document.getElementById('pMainImage');
        const emoji = document.getElementById('pEmoji');
        const thumbs = document.getElementById('pThumbs');
        const images = Array.isArray(product.images) ? product.images.filter(Boolean) : [];

        if (!images.length) {
          main.classList.add('hidden');
          main.removeAttribute('src');
          emoji.classList.remove('hidden');
          thumbs.innerHTML = [0, 1, 2, 3].map(() =>
            '<div class="sf-thumb flex items-center justify-center"><span class="text-2xl">' + OF.escapeHtml(product.emoji || '📦') + '</span></div>'
          ).join('');
          return;
        }

        function showImage(url) {
          main.onload = function () {
            main.classList.remove('hidden');
            emoji.classList.add('hidden');
          };
          main.onerror = function () {
            main.classList.add('hidden');
            emoji.classList.remove('hidden');
          };
          main.src = url;
        }

        thumbs.innerHTML = images.map((src, i) =>
          '<button type="button" class="sf-thumb' + (i === 0 ? ' is-active' : '') + '" data-gallery-src="' + OF.escapeHtml(src) + '" title="Ảnh ' + (i + 1) + '">' +
            '<img src="' + OF.escapeHtml(src) + '" alt="' + OF.escapeHtml(product.name + ' - ảnh ' + (i + 1)) + '" loading="lazy" />' +
          '</button>'
        ).join('');

        const buttons = Array.from(thumbs.querySelectorAll('[data-gallery-src]'));
        buttons.forEach(function (btn) {
          btn.addEventListener('click', function () {
            buttons.forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            showImage(btn.getAttribute('data-gallery-src'));
          });
        });
        showImage(images[0]);
      }

      function starsHtml(rating, average) {
        let html = '';
        for (let i = 1; i <= 5; i++) {
          if (average != null) {
            if (rating >= i) html += '<i class="fa-solid fa-star text-shopee"></i>';
            else if (rating >= i - 0.5) html += '<i class="fa-solid fa-star-half-stroke text-shopee"></i>';
            else html += '<i class="fa-regular fa-star text-gray-300"></i>';
          } else {
            html += '<i class="' + (i <= rating ? 'fa-solid fa-star text-shopee' : 'fa-regular fa-star text-gray-300') + '"></i>';
          }
        }
        return html;
      }

      function formatReviewDate(value) {
        const d = new Date(String(value || '').replace(' ', 'T'));
        return isNaN(d.getTime()) ? String(value || '') : d.toLocaleDateString('vi-VN');
      }

      function renderProductContent() {
        const specsBox = document.getElementById('pSpecs');
        const descBox = document.getElementById('pDescriptionBody');
        const lines = String(product.specs || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
        const rows = lines.map(line => {
          const idx = line.indexOf(':');
          return idx > 0
            ? { label: line.slice(0, idx).trim(), value: line.slice(idx + 1).trim() }
            : { label: 'Thông tin', value: line };
        }).filter(r => r.value);
        specsBox.innerHTML = rows.length
          ? rows.map(r => '<div class="sf-spec-row"><span class="sf-spec-label">' + OF.escapeHtml(r.label) + '</span><span class="sf-spec-value">' + OF.escapeHtml(r.value) + '</span></div>').join('')
          : '<p class="text-sm text-gray-400">Đang cập nhật chi tiết sản phẩm.</p>';
        descBox.textContent = String(product.description || '').trim() || 'Đang cập nhật mô tả sản phẩm.';
      }

      const reviewState = { page: 1, perPage: 5, rating: 0, hasImage: false, hasComment: false, sort: 'newest' };

      function renderReviewSummary(summary) {
        if (!summary) return;
        const total = Number(summary.total) || 0;
        const avg = Number(summary.average) || 0;
        document.getElementById('reviewAvg').textContent = avg ? avg.toFixed(1) : '0';
        document.getElementById('reviewAvgStars').innerHTML = starsHtml(avg, true);
        document.getElementById('reviewTotal').textContent = total + ' đánh giá';
        const counts = summary.counts || {};
        document.getElementById('reviewHistogram').innerHTML = [5, 4, 3, 2, 1].map(star => {
          const count = Number(counts[star]) || 0;
          const pct = total ? Math.round(count / total * 100) : 0;
          return '<div class="flex items-center gap-2 text-[11px] text-gray-500">' +
            '<span class="w-3 text-right font-semibold">' + star + '</span>' +
            '<i class="fa-solid fa-star text-[9px] text-amber-400"></i>' +
            '<div class="sf-review-bar flex-1"><span style="width:' + pct + '%"></span></div>' +
            '<span class="w-8 text-right">' + count + '</span></div>';
        }).join('');
        const images = Number(summary.with_images) || 0;
        const comments = Number(summary.with_comments) || 0;
        document.getElementById('reviewSummaryMeta').innerHTML = '<span class="inline-flex items-center gap-1.5"><i class="fa-regular fa-image text-gray-400"></i> ' + images + ' đánh giá có hình ảnh</span><br><span class="inline-flex items-center gap-1.5 mt-1"><i class="fa-regular fa-comment-dots text-gray-400"></i> ' + comments + ' đánh giá có bình luận</span>';
        const ratingLine = document.getElementById('pRatingLine');
        if (ratingLine && total) {
          ratingLine.innerHTML = '<span class="sf-stars">' + starsHtml(avg, true) + '</span> <span class="text-gray-600 font-semibold">' + avg.toFixed(1) + '</span> <span class="text-gray-400">(' + total + ')</span>';
        }
      }

      function updateReviewFilterStyles() {
        document.querySelectorAll('.review-filter, [data-filter]').forEach(btn => {
          if (!btn.hasAttribute('data-filter')) return;
          const f = btn.getAttribute('data-filter');
          const active = f === 'all'
            ? (!reviewState.rating && !reviewState.hasImage && !reviewState.hasComment)
            : f === 'images'
              ? reviewState.hasImage
              : f === 'comments'
                ? reviewState.hasComment
                : Number(f) === reviewState.rating;
          btn.classList.toggle('is-active', active);
        });
        document.querySelectorAll('[data-sort]').forEach(btn => {
          btn.classList.toggle('is-active', btn.getAttribute('data-sort') === reviewState.sort);
        });
      }

      function renderReviewItems(data) {
        const list = document.getElementById('reviewList');
        const items = data.items || [];
        if (!items.length) {
          list.innerHTML = '<div class="py-10 text-center"><div class="w-12 h-12 mx-auto mb-3 rounded-full bg-gray-50 text-gray-300 flex items-center justify-center"><i class="fa-regular fa-comment-dots text-lg"></i></div><p class="text-sm text-gray-400">Chưa có đánh giá phù hợp với bộ lọc.</p></div>';
          return;
        }
        list.innerHTML = items.map(r => {
          const initial = String(r.reviewer_name || '?').trim().charAt(0).toUpperCase() || '?';
          const variant = r.variant
            ? '<span class="inline-flex items-center gap-1 mt-1 text-[11px] text-gray-400"><i class="fa-solid fa-tag text-[9px]"></i> ' + OF.escapeHtml(r.variant) + '</span>'
            : '';
          const comment = r.comment
            ? '<p class="mt-2.5 text-[13.5px] leading-6 text-gray-700 whitespace-pre-line">' + OF.escapeHtml(r.comment) + '</p>'
            : '';
          const images = (r.images || []).map(url =>
            '<a href="' + OF.escapeHtml(url) + '" target="_blank" rel="noopener" class="block w-16 h-16 rounded-lg border border-gray-200 overflow-hidden hover:border-shopee transition">' +
              '<img src="' + OF.escapeHtml(url) + '" alt="Ảnh đánh giá" class="w-full h-full object-cover" loading="lazy" onerror="this.parentElement.style.display=\'none\'" /></a>'
          ).join('');
          return '<article class="sf-review-item">' +
            '<div class="flex items-start gap-3">' +
              '<span class="sf-avatar">' + OF.escapeHtml(initial) + '</span>' +
              '<div class="min-w-0 flex-1">' +
                '<div class="flex flex-wrap items-center gap-x-3 gap-y-1">' +
                  '<span class="text-[13px] font-bold text-gray-800">' + OF.escapeHtml(r.reviewer_name || 'Người mua') + '</span>' +
                  '<span class="text-xs">' + starsHtml(Number(r.rating) || 0) + '</span>' +
                  '<span class="text-[11px] text-gray-400 ml-auto inline-flex items-center gap-1"><i class="fa-regular fa-circle-check text-emerald-500"></i> ' + formatReviewDate(r.created_at) + '</span>' +
                '</div>' +
                variant + comment +
                (images ? '<div class="flex flex-wrap gap-2 mt-3">' + images + '</div>' : '') +
                '<div class="mt-3 text-[11px] text-gray-400"><button type="button" class="inline-flex items-center gap-1.5 hover:text-shopee transition"><i class="fa-regular fa-thumbs-up"></i> Hữu ích (' + (Number(r.helpful_count) || 0) + ')</button></div>' +
              '</div>' +
            '</div></article>';
        }).join('');
      }

      function renderReviewPager(data) {
        const pager = document.getElementById('reviewPager');
        const pages = Math.max(1, Math.ceil((Number(data.total) || 0) / reviewState.perPage));
        pager.innerHTML = '<span>Trang <strong class="text-gray-700">' + reviewState.page + '</strong> / ' + pages + '</span>' +
          '<span class="flex gap-2">' +
            '<button type="button" id="reviewPrev" class="sf-chip' + (reviewState.page <= 1 ? ' opacity-40' : '') + '"' + (reviewState.page <= 1 ? ' disabled' : '') + '>Trước</button>' +
            '<button type="button" id="reviewNext" class="sf-chip' + (reviewState.page >= pages ? ' opacity-40' : '') + '"' + (reviewState.page >= pages ? ' disabled' : '') + '>Sau</button>' +
          '</span>';
        const prev = document.getElementById('reviewPrev');
        const next = document.getElementById('reviewNext');
        if (prev) prev.addEventListener('click', () => { if (reviewState.page > 1) { reviewState.page--; loadReviews(); } });
        if (next) next.addEventListener('click', () => { if (reviewState.page < pages) { reviewState.page++; loadReviews(); } });
      }

      async function loadReviews() {
        const list = document.getElementById('reviewList');
        list.innerHTML = '<div class="py-10 text-center text-sm text-gray-400"><i class="fa-solid fa-circle-notch fa-spin text-shopee mr-2"></i> Đang tải đánh giá...</div>';
        const q = new URLSearchParams({
          product_id: String(id),
          page: String(reviewState.page),
          per_page: String(reviewState.perPage),
          sort: reviewState.sort,
        });
        if (reviewState.rating) q.set('rating', String(reviewState.rating));
        if (reviewState.hasImage) q.set('has_image', '1');
        if (reviewState.hasComment) q.set('has_comment', '1');
        const res = await OF.api.get('reviews/index.php', q.toString());
        if (!res.success) {
          list.innerHTML = '<p class="py-8 text-center text-sm text-gray-400">' + OF.escapeHtml(res.message || 'Không tải được đánh giá.') + '</p>';
          document.getElementById('reviewPager').innerHTML = '';
          return;
        }
        renderReviewSummary(res.data.summary);
        renderReviewItems(res.data);
        renderReviewPager(res.data);
      }

      function bindReviewControls() {
        document.querySelectorAll('[data-filter]').forEach(btn => {
          btn.addEventListener('click', () => {
            const f = btn.getAttribute('data-filter');
            if (f === 'all') {
              reviewState.rating = 0;
              reviewState.hasImage = false;
              reviewState.hasComment = false;
            } else if (f === 'images') {
              reviewState.hasImage = !reviewState.hasImage;
            } else if (f === 'comments') {
              reviewState.hasComment = !reviewState.hasComment;
            } else {
              reviewState.rating = Number(f) || 0;
            }
            reviewState.page = 1;
            updateReviewFilterStyles();
            loadReviews();
          });
        });
        document.querySelectorAll('[data-sort]').forEach(btn => {
          btn.addEventListener('click', () => {
            reviewState.sort = btn.getAttribute('data-sort') === 'helpful' ? 'helpful' : 'newest';
            reviewState.page = 1;
            updateReviewFilterStyles();
            loadReviews();
          });
        });
        updateReviewFilterStyles();
      }

      function renderRelated(items) {
        const grid = document.getElementById('relatedGrid');
        const list = items.filter(p => p.id !== id).slice(0, 5);
        if (!list.length) { grid.innerHTML = '<p class="col-span-full text-sm text-gray-400">Chưa có sản phẩm khác trong danh mục này.</p>'; return; }
        grid.innerHTML = list.map(p => {
          const price = Number(p.price) || 0;
          const cover = p.cover_image
            ? `<img src="${OF.escapeHtml(p.cover_image)}" alt="${OF.escapeHtml(p.name)}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.classList.remove('hidden')" /><span class="sf-product-emoji hidden" style="font-size:52px">${OF.escapeHtml(p.emoji || '📦')}</span>`
            : `<span class="sf-product-emoji" style="font-size:52px">${OF.escapeHtml(p.emoji || '📦')}</span>`;
          return `<article class="sf-product" onclick="location.href='product.php?id=${p.id}'">
            <div class="sf-product-media">${cover}<span class="sf-badge sf-badge-sale"><i class="fa-solid fa-bolt"></i> -15%</span></div>
            <div class="sf-product-body">
              <h3 class="sf-product-name" style="min-height:34px">${OF.escapeHtml(p.name)}</h3>
              <div class="sf-price-row"><span class="sf-price" style="font-size:16px">${price.toLocaleString('vi-VN')}<sup>₫</sup></span></div>
            </div>
          </article>`;
        }).join('');
      }

      document.addEventListener('DOMContentLoaded', async function () {
        document.getElementById('btnAddCart').addEventListener('click', () => addToCart(false));
        document.getElementById('btnBuyNow').addEventListener('click', () => addToCart(true));

        const user = OF.session.getUser();
        hardcodedHeader(user);
        bindReviewControls();
        refreshCartBadge();

        if (!id) { document.getElementById('pTitle').textContent = 'Không tìm thấy sản phẩm (thiếu id).'; return; }
        const res = await OF.api.get('products/item.php', 'id=' + id + '&public=1');
        if (!res.success) {
          document.getElementById('pTitle').textContent = 'Không tìm thấy sản phẩm.';
          document.getElementById('btnAddCart').disabled = true;
          document.getElementById('btnBuyNow').disabled = true;
          return;
        }
        product = res.data;
        document.getElementById('pTitle').textContent = product.name;
        document.getElementById('pEmoji').textContent = product.emoji || '📦';
        document.getElementById('pPrice').textContent = Number(product.price).toLocaleString('vi-VN') + '₫';
        document.getElementById('pSold').textContent = (100 + (product.id * 137) % 900) + '+';
        document.getElementById('pStockLine').textContent = product.stock > 0 ? (product.stock + ' sản phẩm trong kho') : 'Hết hàng';
        document.getElementById('crumbName').textContent = product.name;
        document.title = product.name + ' – Shopee.vn';
        const discount = [10, 15, 20, 25, 30][Number(product.id) % 5];
        const oldPrice = Math.round(Number(product.price) * (1 + discount / 100) / 1000) * 1000;
        document.getElementById('pDiscountBadge').textContent = '-' + discount + '%';
        document.getElementById('pOldPrice').textContent = oldPrice.toLocaleString('vi-VN') + '₫';
        const catLink = document.getElementById('crumbCategory');
        if (product.category_id && product.category_name) {
          catLink.textContent = product.category_name;
          catLink.href = 'shop.php?category_id=' + product.category_id;
        }
        bindQty();
        renderVariants();
        updateQtyUI();
        renderGallery();
        renderProductContent();
        if (product.review_summary) renderReviewSummary(product.review_summary);
        loadReviews();

        if (product.category_id) {
          const rel = await OF.api.get('products/index.php', 'per_page=200&status=active&public=1&category_id=' + product.category_id);
          if (rel.success) renderRelated(rel.data.items || []);
        }
      });
    })();
  </script>
</body>
</html>
