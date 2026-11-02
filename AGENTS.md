# AGENTS.md

Bài tập lớn nhóm 11 (Đề tài 19: xử lý đơn hàng & đổi trả). Giao diện vào được gọi là **"Shopee"** — thương hiệu UI đã được đổi tên từ "OrderFlow".

## Hai ứng dụng riêng biệt — đừng nhầm lẫn

- **Ứng dụng chính (thư mục gốc):** storefront + quản trị PHP/MySQL tĩnh. Các trang HTML trong thư mục gốc + backend `api/`, `includes/`, `config/`, `assets/js/`, `assets/css/`.
- **`ban_hang/`:** một ứng dụng riêng biệt "An Nhiên" (store JSON thuần `data/store.json`, tự chạy trên cổng 8085 thông qua `php -S … -t public`). Không dùng chung gì với thư mục gốc.
- **`src/`, `tests/`, `lab.sh`:** các tạo phẩm bài tập Lab-02 riêng biệt (`CapacityPolicy`/`RegistrationPolicy`). Không liên quan đến ứng dụng chính.

## Chạy, kiểm thử, cơ sở dữ liệu

```powershell
php -S 127.0.0.1:8090 router.php   # từ thư mục gốc; router.php phân giải /api/* + tĩnh
```
- `php` **không có trong PATH** trên máy phát triển này — sử dụng `D:\xampp\php\php.exe`.
- Database MySQL/MariaDB (XAMPP): db `orderflow`, `config/database.php` (cấu hình cứng + các ghi đè env `OF_DB_*`). Nhập `database.sql` qua phpMyAdmin trước — nhiều API đang yêu cầu nó.
- **Các file migrate hoạt động qua trình duyệt và tự xóa bản thân sau khi chạy** (xem `HUONG_DAN_KIEM_THU.md` §0.3): `/database/migrate_web.php?run=1`, `migrate_reservations_web.php?run=1`, `migrate_carts_web.php?run=1` — thường cần thiết sau khi nhập dữ liệu vì một DB trực tiếp cũ có thể thiếu bảng/cột (ví dụ: `cart_items.unit_price`) so với `database.sql`. Đó là sự ảnh hưởng của schema đích.
- Riêng `database/migrate_rubric_web.php`, `database/migrate_catalog_web.php` và `database/migrate_product_content_web.php` là migration additive, idempotent, **không tự xóa**: chạy `php database/migrate_rubric_web.php --run`, `php database/migrate_catalog_web.php --run`, `php database/migrate_product_content_web.php --run` hoặc `?run=1`. Catalog mở rộng nằm ở `database/catalog_extra.php` (57 sản phẩm, 311 biến thể); mô tả/thông số/đánh giá mẫu nằm ở `database/product_content_extra.php`, dùng chung bởi seed và migration. `database.sql` được sinh bởi `database/generate_seed.php` (không sửa tay); bản canonical hiện có 77 sản phẩm, 349 biến thể và 4.620 đánh giá.
- Kiểm thử (không có framework, in ra PASS/FAIL): `php tests/run.php`, `php ban_hang/tests/run.php`, `bash lab.sh test|extend|check` (Lab-02).
- CI (`.github/workflows/ci.yml`) hiện tại: không chạy test PHP; các bước npm chỉ chạy nếu `package.json` tồn tại (hiện chưa có).

## Tài khoản demo (`HUONG_DAN_KIEM_THU.md`)

`admin@orderflow.vn`, `nhanvien@orderflow.vn`, `khachhang@orderflow.vn` — tất cả mật khẩu `123456`. Vai trò cũng có thể được tạo qua DB seed; công khai API chỉ tạo tài khoản `customer`.

## Quy ước kiến trúc (rất dễ sai)

- **Auth:** định danh Bearer. `api.js` lưu trữ token trong `localStorage["orderflow.token"]`; backend xác thực đối chiếu bảng `sessions` (`includes/auth.php`). API mới phải sử dụng `require_auth()` / `require_role('admin')`.
- **Các trang quản trị** (`orders.html`, `products.html`, v.v.) tải `assets/js/{data,api,app}.js` + `pages/<page>.js`, và cần đặt các div trống `<aside id="app-sidebar">` / `<header id="app-topbar">` — `app.js` tự render cả hai và tự cắm guard của riêng nó (chuyển hướng khi chưa xác thực, kiểm tra `PAGE_ROLES`, kiểm tra lại phiên `auth/me.php`).
- **`index.html` (storefront)** cố ý **không** tải `app.js` — nó giữ phong cách thẻ Tailwind "Shopee" riêng biệt, có inline script, kết hợp `data.js` + `api.js` và gọi API tương tự. Từ giữa: thay đổi nhỏ (duy trì một thương hiệu, không tạo lại modal đăng nhập cũ).
- `dashboard.html` cũng là phong cách Shopee với ngoại lệ riêng của nó, nhưng vẫn tải `app.js` (guard + `OF.ui`, `OF.charts`); CSS cho chart/badge/modal được tích hợp trong trang.
- Endpoint JSON trả lời qua `json_ok($data, $message, $code)` / `json_fail($message, $code, $errors)` — cấu trúc tham số là chính xác, nhầm lẫn thứ tự sẽ gây ra lỗi `TypeError` (ví dụ: lỗi đã tìm thấy trong `api/carts/index.php`).
- Bảo vệ CSRF/guốc ở `includes/bootstrap.php`: các phương thức POST/PUT/PATCH từ origin khác sẽ bị từ chối; Live Server (:5500) và :8000 được thêm vào whitelist ở đó.
- Chức năng hết hạn đơn hàng là cron: `*/5 * * * * curl -s http://127.0.0.1:8090/api/orders/expire_all.php` (xem `cron_expire.txt`).

## Các lỗi nhỏ cần tránh

- **Mã hóa UTF-8 (không có BOM) các file tiếng Việt.** PowerShell 5.1 `Get-Content`/`Set-Content` sẽ mã hóa lẫn lộn — sử dụng `.NET` `[IO.File]::ReadAllText/WriteAllText` với `UTF8Encoding($false)`, hoặc dùng Edit tool/Write tool, rồi kiểm tra lại encoding `/Đăng nhập/`.
- Brand rename là phân biệt chữ hoa/thường: `OrderFlow` → `Shopee` trong UI, nhưng `orderflow.vn` (email demo), `orderflow` DB name và `orderflow.*` localStorage keys là **định danh thật — không được đổi tên**.
- Mở `http://127.0.0.1:8090/...` để thử nghiệm frontend; backend nghiêm ngặt ở `/api/*`; sau khi sửa lỗi JS, nhấn Ctrl+F5 — trình duyệt có thể lưu cache `assets/js` cũ (các tag HTML có query `?v=2`).
- `config/database.php` chứa mật khẩu DB demo — không phải commit sản phẩm; giữ nguyên quy ước env-override.
