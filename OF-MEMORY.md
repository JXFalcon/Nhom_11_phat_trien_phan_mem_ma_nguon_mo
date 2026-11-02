# Shopee Store — Working State

## Next Steps
- Seed thêm dữ liệu DB (đòi hỏi người dùng, PAL): viết `database/seed_more_data.php` INSERT-only; đọc tiếp database/generate_seed.php phần insert orders/order_items/payments/shipments/returns/reservations (~line 80+) để copy đúng cột.
- Búsqueda material: banner ảnh thật ở AI/datasets để thay 5 banner SVG (assets/images/banner-*.svg).

## Objective
- Đề tài 19 nhóm 11 — storefront + quản trị "Shopee". Phiên 24/10/2026: hoàn thành P0+P1+P2 theo audit 5 chức năng.

## Important Details
- Server đang chạy: `php -S 127.0.0.1:8090 router.php`; PHP `D:\xampp\php\php.exe`.
- UTF-8 no-BOM cho file tiếng Việt (Edit/Write tool); đã verify mọi file sửa ngày 24/10.
- **Đã sửa (phiên 24/10 khuya):**
  - P0 cron `expire_all.php` TRỌN BỘ rewrite: hoàn kho theo luật expire.php (pending chừng không cộng; confirmed cộng; legacy order_items), FOR UPDATE + lọc stock_restored, đánh dấu hold movement "ĐÃ GIẢI PHÓNG". Bug gốc: cron cộng stock ảo và bỏ hoàn reservations.
  - P0 bug CŨ đã tìm thấy thêm trong fix: orders/status.php + orders/expire.php — select reservations `confirmed`  thiếu cột `id` → UPDATE id=0 không làm gì (reservations treo confirmed). Đã sửa cả 2 (`SELECT id, product_id, quantity`).
  - P0 orders/index.php from_cart: dùng `cart_items.unit_price` (giá đã báo khách) khi >0; không → p.price snapshot. Meh unit_price snapshot giữ nguyên khi ADD CART.
  - P1 mới `/api/reports/reconcile.php`: GET đối chiếu HÀNG (Σ movements loại trừ hold note 'Giữ hàng%' ↔ stock; cảnh báo giữ pending vượt tồn), TIỀN (policies thanh_cong/hoan_tien vs order status; DT hoàn tất thiếu refund; payment chờ trên đơn quá hạn), reserves. POST `{action:"baseline"}` chèn `dieu_chinh` cân bằng số dư seed thiếu 'nhap' (đã chạy 2 lần → inventory ok mismatch=0; còn 10 money legacy flags: DH-1093… payment thanh_cong nhưng order chờ + DT-204 thiếu refund cũ — để nguyên làm demo đối chiếu).
  - P1 refund: payments ENUM + 'hoan_tien' (migrate_refund_web.php đã chạy & tự xóa; database.sql cập nhật + idx_payments_refund); hủy đơn có thanh_cong → INSERT payment hoan_tien idempčit key `REFUND-ORD-<id>`; hoàn tất đổi trả → `REFUND-RET-<retid>` amount Σ refund_amount; summary thêm paid_refunds + revenue_net; payments/index filter 'hoan_tien'; data.js PAYMENT_STATUS.hoan_tien.
  - P2: phone (regex 8-15) + address ≥5 bắt buộc POST đơn (422); shipments/status.php `da_giao` set ship_date + nhánh that_bai ghi order_history; carts/index.php bỏ ini_set display_errors.
  - Frontend reports.html: nút "Đối chiếu" + panel reconcile + nút baseline (data-baseline, dùng OF.api.post); reports.js statCard "Doanh thu ròng".
- Testing: `php -l` toàn bộ OK; reports.js/data.js/payments.js node --check OK; smoke script `%TEMP%\opencode\smoke2.php` **PASS=25 FAIL=0** (login, summary, reconcile, đặt từ giỏ dùng giá giỏ, 422 thiếu phone, simulate trừ kho, idempotency, hủy→refund ORD stock, cron expire 2 lần stock không đổi + hold mark, trả 1 phần + chặn vượt lượng + refund RET + restock, lọc hoan_tien, revenue_net ≤ revenue).
- tests/run.php 5 PASS (Lab-02 — không test API; lưu ý).
- Smoke scripts ở `%TEMP%\opencode\` (smoke2.php, diag*.php) — file tạm, tái tạo dễ.

## Work State
### Completed
- P0+P1+P2 hoàn tất + verify (không cần thêm unify gregra — mọi todo completed). Data baseline đã cân bằng (inventory reconcile ok=1).

### Blocked
- Không.

## Next Move
1. SEMEED MORE DATA (database/seed_more_data.php).
2. Có thể thêm: P3 CRUD biến thể thật + phí ship (chưa làm, chỉ khi người dùng yêu cầu).
3. Nhắc người dùng Ctrl+F5 cho reports.html.

## Relevant Files
- api/orders/expire_all.php (rewrite), api/orders/expire.php, api/orders/status.php, api/orders/index.php
- api/returns/status.php, api/shipments/status.php, api/payments/index.php, api/carts/index.php
- api/reports/reconcile.php (MỚI), api/reports/summary.php
- database.sql (ENUM payments + 4 hoan_tien), database/migrate_refund_web.php (đã chạy tự xóa)
- assets/js/data.js (hoan_tien), assets/js/pages/reports.js (reconcile+baseline+revenue_net), assets/js/pages/payments.js dùng PAYMENT_STATUS extend sẵn
- reports.html (nút đối chiếu + panel), OF-MEMORY.md
