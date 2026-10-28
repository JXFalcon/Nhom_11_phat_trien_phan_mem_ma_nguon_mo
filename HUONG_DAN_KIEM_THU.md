# Hướng dẫn kiểm thử Đề tài 19 – Hệ thống xử lý đơn hàng và đổi trả

## 0. Chuẩn bị môi trường

### 0.1 Chạy PHP server
```powershell
php -S 127.0.0.1:8090 router.php
```
Hoặc dùng XAMPP Apache, truy cập `http://localhost`.

### 0.2 Import database
Mở phpMyAdmin → tạo database `orderflow` → Import file `database.sql`.

> **Cảnh báo:** `database.sql` có `DROP DATABASE IF EXISTS orderflow`, chỉ dùng khi tạo DB mới. Nếu DB đang có dữ liệu, **sao lưu trước** và dùng migration bên dưới, không import đè.

### 0.3 Chạy migration (bắt buộc)
Truy cập theo thứ tự qua trình duyệt (đảm bảo PHP server đang chạy):

1. `http://127.0.0.1:8090/database/migrate_web.php?run=1`
   → Thêm cột `reserved_until` vào bảng `orders`.

2. `http://127.0.0.1:8090/database/migrate_reservations_web.php?run=1`
   → Tạo bảng `reservations`.

3. `http://127.0.0.1:8090/database/migrate_carts_web.php?run=1`
   → Tạo bảng `carts`, `cart_items`.

4. `http://127.0.0.1:8090/database/migrate_rubric_web.php?run=1`
   → Bổ sung `variant_id`, `product_variants.sku/price`, `products.images`, `payment_events`, `code_sequences`, unique/index còn thiếu.
   Migration này **chỉ thêm cấu trúc + backfill cột mới** (sku, variant_id, variant rỗng), không xóa/gộp dữ liệu; nếu dữ liệu đang trùng unique thì bỏ qua index và ghi cảnh báo. Có thể chạy lại nhiều lần.

5. `http://127.0.0.1:8090/database/migrate_catalog_web.php?run=1`
   → Thêm **57 sản phẩm, 311 biến thể** mới (ốp lưng iPhone 16/15/14/13/12, sạc, cáp, điện thoại, laptop, tablet, âm thanh, đồng hồ, gia dụng, thời trang).
   Migration chỉ thêm sản phẩm chưa có theo SKU, kèm biến thể và tồn kho ban đầu; không sửa/xóa đơn hàng cũ. Chạy lại sẽ bỏ qua SKU đã có.

6. `http://127.0.0.1:8090/database/migrate_product_content_web.php?run=1`
   → Thêm cột `products.description`, `products.specs` và bảng `product_reviews`.
   Migration chỉ điền mô tả/chi tiết khi đang trống và chỉ sinh đánh giá cho sản phẩm chưa có đánh giá; chạy lại không nhân đôi dữ liệu.

   Hoặc chạy CLI:
   ```powershell
   $env:OF_DB_NAME = "orderflow"
   D:\xampp\php\php.exe database\migrate_rubric_web.php --run
   D:\xampp\php\php.exe database\migrate_catalog_web.php --run
   D:\xampp\php\php.exe database\migrate_product_content_web.php --run
   Remove-Item Env:OF_DB_NAME
   ```

Ba migration đầu tự xóa sau khi chạy; `migrate_rubric_web.php`, `migrate_catalog_web.php` và `migrate_product_content_web.php` giữ lại để chạy lại an toàn.

### 0.4 Ảnh sản phẩm
Vào `products.html` → **Sửa sản phẩm** → dán 4–5 URL vào ô **Ảnh sản phẩm (URL)**, mỗi dòng một URL. Ảnh đầu tiên là ảnh bìa; trang chủ, shop và trang chi tiết tự hiển thị.

### 0.5 Mô tả, chi tiết & đánh giá sản phẩm
- Trong form sửa sản phẩm có thêm ô **Mô tả sản phẩm** và **Chi tiết sản phẩm** (mỗi dòng `Tên: Giá trị`).
- Trang `product.html?id=N` hiển thị khối chi tiết, mô tả và khu vực đánh giá: điểm trung bình, biểu đồ số sao, lọc theo sao / có hình ảnh / có bình luận, sắp xếp mới nhất hoặc hữu ích, phân trang.
- API công khai: `GET /api/reviews/index.php?product_id=N&rating=&has_image=1&has_comment=1&sort=newest|helpful&page=&per_page=`.
- `database.sql` sinh sẵn **60 đánh giá/sản phẩm** (4.620 đánh giá cho 77 sản phẩm). Đánh giá chỉ để xem, không có form gửi đánh giá từ khách.

### 0.6 Tài khoản demo (mật khẩu: 123456)
- `admin@orderflow.vn` / `123456` – admin
- `nhanvien@orderflow.vn` / `123456` – staff
- `khachhang@orderflow.vn` / `123456` – customer

---

## 1. Giỏ hàng (chức năng 1)

### API mới
- `GET /api/carts/index.php` – xem giỏ hàng của user hiện tại
- `POST /api/carts/index.php` – thêm sản phẩm vào giỏ `{ product_id, variant?, quantity? }`
- `PUT /api/carts/item.php?id=N` – cập nhật số lượng `{ quantity }`
- `DELETE /api/carts/item.php?id=N` – xóa item khỏi giỏ

### Frontend
- Trang `cart.html` (đã thêm vào menu "Giỏ hàng của tôi")
- Khi tạo đơn: `POST /api/orders/index.php` với `{ from_cart: true }` sẽ lấy từ giỏ

### Cách kiểm thử
1. Đăng nhập customer (`khachhang@orderflow.vn`).
2. Vào trang Sản phẩm → thêm món vào giỏ (nếu có nút) hoặc gọi API `POST /api/carts/index.php`.
3. Vào `cart.html` → xem danh sách, thay đổi số lượng, xóa.
4. Bấm "Tạo đơn hàng từ giỏ" → đơn được tạo với `status = cho_xac_nhan`, `reserved_until = NOW() + 30 phút`.
5. Kiểm tra `api/reservations/index.php` → có reservation pending được tạo.

---

## 2. Tự động hóa expire đơn hàng

### API mới
- `POST /api/orders/expire.php` – admin/staff bấm nút trên UI đơn hàng để hết hạn đơn đơn lẻ
- `POST /api/orders/expire_all.php` – gọi bằng cron (cho localhost hoặc admin/staff)

### Cron (chạy mỗi 5 phút)
```bash
*/5 * * * * curl -s http://127.0.0.1:8090/api/orders/expire_all.php >/dev/null 2>&1
```
Xem chi tiết file `cron_expire.txt`.

### Hành động khi expire
- Đơn `cho_xac_nhan` + `reserved_until < NOW()` → `da_huy`, `stock_restored = 1`
- Reservation `pending` → `expired` (giả phóng, chưa trừ kho)
- Reservation `confirmed` (đã trừ kho) → hoàn lại kho, đổi `expired`
- Payment `cho_xu_ly` → `that_bai`
- Ghi `order_history`

### Frontend
- Trang `orders.html`: nút đồng hồ (🕒) trên đơn `Chờ xác nhận` → gọi `expire.php`
- Cột đếm ngrosso "Hết hạn sau Xp Ys" (đổi màu khi <5 phút, đỏ khi đã hết hạn)

---

## 3. Quản lý giữ hàng (Reservations)

### API mới
- `GET /api/reservations/index.php` – danh sách reservation (lọc theo status)
- `POST /api/reservations/index.php` – tạo reservation mới
- `GET/PUT/DELETE /api/reservations/item.php?id=N` – chi tiết / cập nhật trạng thái / xóa

### Frontend
- Trang `reservations.html` (menu "Quản lý giữ hàng", chỉ admin/staff)
- Nút: Xác nhận (pending → confirmed, trừ kho thật), Giải phóng, Hết hạn

### Cách kiểm thử
1. Đăng nhập admin/staff → vào `reservations.html`.
2. Xem danh sách reservation pending.
3. Bấm "Xác nhận" → kiểm tra `products.stock` đã giảm, reservation status = `confirmed`.
4. Bấm "Giải phóng" → reservation status = `released`, kho không bị ảnh hưởng (chưa từng trừ).
5. Bấm "Hết hạn" → reservation status = `expired`.

---

## 4. Xuất báo cáo CSV (chức năng 5)

### API
- `GET /api/reports/summary.php?export=csv&days=7|30|60|all` → tải file CSV BOM UTF-8.
- API yêu cầu Bearer token của admin/staff (giống mọi API quản trị).

### Cách kiểm thử
1. Đăng nhập admin/staff → vào `reports.html` → bấm **Xuất CSV**. Trình duyệt gọi API kèm token và tải file.
2. Hoặc dùng curl với token lấy từ `localStorage["orderflow.token"]`:
   ```bash
   curl -H "Authorization: Bearer <token>" -o bao_cao.csv \
     "http://127.0.0.1:8090/api/reports/summary.php?export=csv&days=30"
   ```
3. File CSV sẽ bao gồm: Mã đơn, Khách hàng, Ngày đặt, Số tiền, Phương thức, Trạng thái, Thanh toán.

---

## 5. Dữ liệu mẫu (102 đơn, 77 sản phẩm)

`database.sql` đã có:
- 77 sản phẩm với **349 biến thể**; trong đó 57 sản phẩm catalog mở rộng có biến thể chi tiết
- 4.620 đánh giá mẫu (60 đánh giá/sản phẩm), kèm mô tả và thông số chi tiết cho từng sản phẩm
- Ốp lưng iPhone có nhiều kích cỡ: iPhone 16 Pro Max, 16 Pro, 16, 15 Pro Max, 15, 14, 13, 12; nhiều màu và chất liệu
- 100 đơn hàng thường (DH-1001 → DH-1100)
- Đơn đã hủy (`da_huy`), đã giao (`da_giao`), đang giao (`dang_giao`)
- Yêu cầu đổi trả (DT-201 → DT-215) với các trạng thái: cho_xu_ly, da_chap_nhan, tu_choi, da_hoan_tat
- 2 đơn hết hạn mẫu (DH-1101, DH-1102) với `reserved_until` quá hạn
- 7 reservation mẫu (RV-1001 → RV-2002) với các trạng thái pending, confirmed, expired

---

## 6. Kiểm thử 5 phép thử bắt buộc

| # | Yêu cầu | Cách kiểm thử trên web |
|---|---------|----------------------|
| 1 | Hai đơn tranh sản phẩm cuối không cùng giữ được hàng; tồn không âm | Tạo 2 đơn cùng lúc cho sản phẩm có tồn = 1. Chỉ 1 đơn thành công, đơn thứ hai bị lỗi 409 "không đủ tồn". |
| 2 | Gửi lại thông báo thanh toán / lệnh hủy không đổi số lượng/tiền thêm lần nữa | Gọi `PATCH /api/payments/simulate.php` cùng `idempotency_key` 2 lần → lần 2 trả `already_settled` hoặc `idempotent_replay`, không đổi trạng thái. Hủy đơn 2 lần → lần 2 báo "đã hoàn kho trước đó". |
| 3 | Đổi giá sản phẩm sau đặt không thay giá trong đơn đã chốt | Tạo đơn (giá snapshot trong `order_items.unit_price`). Sau đó sửa giá sản phẩm trong `products`. Xem chi tiết đơn → giá trong đơn không đổi. |
| 4 | Tổng lượng trả nhiều lần không vượt lượng đã giao; tiền hoàn theo đơn | Tạo yêu cầu đổi trả (returns) với SL ≤ SL đã giao. Yêu cầu thứ hai vượt quá SL còn lại → bị lỗi 409. Tiền hoàn = qty_accepted × unit_price (giá chốt trong đơn). |
| 5 | Thanh toán đến sau khi đơn hết hạn không tự hồi sinh đơn | Tạo đơn, để quá 30 phút (hoặc dùng đơn mẫu DH-1101 đã hết hạn). Gọi `PATCH /api/payments/simulate.php` với `result = thanh_cong` → API trả `order_expired: true`, tạo hoàn tiền, đơn vẫn `da_huy`, không tạo shipment, không trừ kho. |

### 6.1 Chạy test tích hợp tự động

Bộ test dựng database riêng `orderflow_test`, không đụng dữ liệu thật:

```powershell
D:\xampp\php\php.exe tests\integration\setup.php
D:\xampp\php\php.exe tests\integration\run.php
```

`run.php` tự gọi setup, khởi động **2** PHP built-in server ở cổng `8097` và `8098`
(có thể đổi bằng `OF_TEST_PORT` và `OF_TEST_PORT2`) để kiểm tra tranh hàng song song thật,
rồi chạy 63 kiểm tra thuộc 5 nhóm phép thử: tranh hàng cuối + biến thể, gửi lặp thanh toán/hủy đơn,
snapshot giá, sửa sản phẩm có/không biến thể, trả một phần + hoàn tiền + yêu cầu trả thứ hai + trả nhiều dòng, thanh toán muộn.
Cuối bộ test còn kiểm tra báo cáo đối chiếu có tồn biến thể và `payment_events`.
Runner từ chối chạy nếu tên DB không kết thúc bằng `_test`. Kết quả in `PASS/FAIL`
và thoát mã `1` khi có lỗi.

---

## 7. Danh sách file đã thay đổi / tạo mới

### Backend API
- `api/carts/index.php` (cập nhật: biến thể, giữ hàng)
- `api/carts/item.php` (cập nhật: biến thể, kiểm tra tồn)
- `api/reservations/index.php` (cập nhật: biến thể, giới hạn 30 phút)
- `api/reservations/item.php` (cập nhật: giải phóng/giữ hàng theo vòng đời)
- `api/orders/expire.php` (cập nhật: xử lý reservations)
- `api/orders/expire_all.php` (mới: cho cron)
- `api/orders/index.php` (cập nhật: from_cart, reservation, biến thể)
- `api/orders/status.php` (cập nhật: xác nhận/hủy qua lifecycle)
- `api/payments/simulate.php` (cập nhật: idempotency, thanh toán muộn)
- `api/products/index.php`, `api/products/item.php` (biến thể)
- `api/returns/status.php` (trả một phần, điều chỉnh, hoàn tiền)
- `api/shipments/status.php` (chốt giữ hàng trước khi giao)
- `api/reports/summary.php` (cập nhật: export CSV)
- `api/reports/reconcile.php` (cập nhật: đối chiếu tồn biến thể + payment_events)
- `includes/order_lifecycle.php` (mới: dùng chung cho toàn vòng đời đơn)

### Database
- `database.sql` (thêm bảng carts, cart_items, reservations, cột reserved_until, dữ liệu mẫu)
- `database/generate_seed.php` (cập nhật: đọc schema chuẩn, sinh variant_id/sku/price, code_sequences; giữ đơn hết hạn, giỏ hàng và payment_events mẫu)
- `database/migrate_web.php` (mới)
- `database/migrate_reservations_web.php` (mới)
- `database/migrate_carts_web.php` (mới)
- `database/migrate_reserved_until.sql` (mới)
- `database/migrate_reservations.php` (mới)
- `database/migrate_rubric_web.php` (mới: bổ sung cột/bảng rubric)
- `database/catalog_extra.php` (mới: dữ liệu 57 sản phẩm, 311 biến thể dùng chung cho seed/migration)
- `database/migrate_catalog_web.php` (mới: thêm catalog mở rộng, idempotent)
- `database/product_content_extra.php` (mới: sinh mô tả, thông số, đánh giá dùng chung cho seed/migration)
- `database/migrate_product_content_web.php` (mới: thêm mô tả/chi tiết và bảng đánh giá, idempotent)
- `tests/integration/setup.php`, `tests/integration/run.php` (mới: test tích hợp)
- `cron_expire.txt` (mới)

### Frontend
- `cart.html` (mới)
- `assets/js/pages/cart.js` (mới)
- `reservations.html` (mới)
- `assets/js/pages/reservations.js` (mới)
- `assets/js/pages/orders.js` (cập nhật: nút expire, đếm ngrosso)
- `assets/js/pages/payments.js` (cập nhật: thông báo order_expired)
- `assets/js/pages/reports.js` (cập nhật: hiển thị biến thể + payment events)
- `assets/js/pages/returns.js` (cập nhật: tạo yêu cầu trả nhiều dòng)
- `assets/js/app.js` (cập nhật: menu nav, icon clock)
- `assets/css/components.css` (cập nhật: style expire-badge)
- `orders.html`, `payments.html` (cập nhật banner)

---

## 8. Lưu ý

- `php` không có trong PATH; dùng `D:\xampp\php\php.exe` cho CLI. Migration vẫn có thể chạy qua HTTP (trình duyệt).
- Cron expire chạy qua HTTP `curl`, cần PHP server (`php -S 127.0.0.1:8090 router.php`) đang bật.
- Mật khẩu demo cho tất cả user: `123456`.
