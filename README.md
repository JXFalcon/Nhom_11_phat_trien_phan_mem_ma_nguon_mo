# OrderFlow – Xử lý đơn hàng & quản lý đổi trả trong bán hàng trực tuyến

Bài tập lớn – **Đề tài 19**. Hệ thống quản lý đơn hàng, thanh toán, giao hàng và đổi trả,
kết nối **frontend HTML/CSS/JS thuần** với **backend PHP 8 + MySQL** bằng API JSON.

> **Trạng thái:** giai đoạn 2 – đã kết nối backend và MySQL.
> Toàn bộ dữ liệu hiển thị lấy từ cơ sở dữ liệu qua API (`/api/*`), không còn dữ liệu mẫu trong trình duyệt.

## 1. Công nghệ

| Thành phần | Sử dụng |
|---|---|
| Frontend | HTML5, CSS3, JavaScript thuần (không framework, không thư viện ngoài) |
| Backend | PHP 8 trở lên (`declare(strict_types=1)`) |
| Database | MySQL / MariaDB (XAMPP) |
| Kết nối DB | PDO + prepared statements |
| Xác thực | Bearer token lưu trong `localStorage`, phiên ở bảng `sessions` |
| Môi trường | XAMPP trên Windows |

## 2. Cài đặt và chạy dự án (Windows)

### Bước 1 – Cài và mở XAMPP

Tải và cài [XAMPP](https://www.apachefriends.org/), mở **XAMPP Control Panel**.

### Bước 2 – Khởi động Apache và MySQL

Bấm **Start** cho **Apache** và **MySQL** trong XAMPP Control Panel.

> Nếu đã dùng chung Apache/MySQL của XAMPP, bạn **không bắt buộc** phải chạy
> PHP built-in server. Hai cách chạy website ở Bước 6 đều được hỗ trợ.

### Bước 3 – Import `database.sql`

**Cách A – phpMyAdmin (khuyến nghị):**

1. Mở `http://localhost/phpmyadmin`.
2. Chọn **Import** → **Choose File** → chọn `database.sql` ở thư mục dự án → **Go**.
3. File sẽ tự tạo database `orderflow` (nếu đã có sẽ bị **xóa và tạo lại**).

**Cách B – dòng lệnh:**

```bat
D:\XAMPP\mysql\bin\mysql.exe -uroot < database.sql
```

Kiểm tra nhanh kết nối và mật khẩu demo:

```bat
D:\XAMPP\php\php.exe database\test_db.php
```

### Bước 4 – Đặt dự án vào thư mục web

**Cách A – thư mụchtdocs (dùng Apache của XAMPP):**

Sao chép cả thư mục `PTMNM` vào `D:\XAMPP\htdocs\PTMNM`.
Sau đó truy cập `http://localhost/PTMNM/`.

**Cách B – virtual host (tùy chọn):**

Thêm vào `D:\XAMPP\apache\conf\extra\httpd-vhosts.conf` rồi restart Apache:

```apache
<VirtualHost *:80>
    DocumentRoot "E:\PTMNM"
    ServerName orderflow.local
</VirtualHost>
```

Thêm `127.0.0.1 orderflow.local` vào `C:\Windows\System32\drivers\etc\hosts`.

**Cách C – PHP built-in server (không cần Apache):**

```bat
D:\XAMPP\php\php.exe -S 127.0.0.1:8090 router.php
```

(Chạy trong thư mục gốc dự án; `router.php` đã có sẵn.)

### Bước 5 – Cấu hình kết nối database

Thông tin kết nối nằm ở `config/database.php` (mặc định của XAMPP: host `127.0.0.1`,
database `orderflow`, user `root`, mật khẩu rỗng).

Có thể đổi bằng biến môi trường mà **không cần sửa file**:

```bat
set OF_DB_HOST=127.0.0.1
set OF_DB_NAME=orderflow
set OF_DB_USER=root
set OF_DB_PASS=
```

> Không commit mật khẩu database thật vào mã nguồn được chia sẻ.

### Bước 6 – Mở website

| Cách chạy | Địa chỉ |
|---|---|
| Apache + `htdocs/PTMNM` | `http://localhost/PTMNM/` |
| Virtual host | `http://orderflow.local/` |
| PHP built-in server | `http://127.0.0.1:8090/` |
| Live Server (VS Code, cổng 5500) | `http://127.0.0.1:5500/index.html` |

Khi chạy bằng Live Server (cổng khác backend), `assets/js/api.js` tự động gọi
`http://127.0.0.1:8090` – vì vậy hãy khởi động luôn PHP built-in server ở Bước 4 cách C.

### Bước 7 – Đăng nhập tài khoản demo

Mật khẩu của **tất cả** tài khoản demo là `123456` (đã băm bcrypt trong `database.sql`).

| Tài khoản | Vai trò | Quyền |
|---|---|---|
| `admin@orderflow.vn` | Quản trị viên | Toàn quyền: sản phẩm, danh mục, đơn hàng, đổi trả, thanh toán, giao hàng |
| `nhanvien@orderflow.vn` | Nhân viên xử lý đơn | Xử lý đơn/đổi trả/thanh toán/giao hàng, **không** sửa sản phẩm – danh mục |
| `khachhang@orderflow.vn` | Khách hàng | Chỉ xem/tạo đơn hàng, yêu cầu đổi trả của **chính mình** |

Đăng ký tài khoản mới qua trang `register.html` – backend luôn ép vai trò `customer`,
không thể tự đăng ký thành quản trị viên.

### Bước 8 – Kiểm tra CRUD và nghiệp vụ

| Hạn mục | Cách kiểm tra |
|---|---|
| Sản phẩm | `products.html` → Thêm/Sửa/Xóa/Tìm kiếm (chỉ admin) |
| Danh mục | `categories.html` → Thêm/Sửa/Xóa; chặn xóa danh mục còn sản phẩm |
| Đơn hàng | `orders.html` → tạo đơn (giữ kho), xem chi tiết, hủy đơn, đổi trạng thái theo vòng đời |
| Đổi trả | `returns.html` → khách tạo yêu cầu; admin/staff chấp nhận/từ chối/hoàn tất |
| Thanh toán | `payments.html` → mô phỏng thành công/thất bại (idempotent) |
| Giao hàng | `shipments.html` → cập nhật vận đơn, tự đồng bộ trạng thái đơn |
| Dashboard/Báo cáo | `index.html`, `reports.html` → số liệu tổng hợp trực tiếp từ MySQL |

## 3. Cấu trúc thư mục

```
PTMNM/
├── index.html, login.html, register.html,
│   products.html, categories.html, orders.html, returns.html,
│   shipments.html, payments.html, reports.html
├── database.sql              # Tạo DB + bảng + index + FK + dữ liệu mẫu
├── router.php                # Router cho php -S
├── config/
│   └── database.php          # Cấu hình PDO (đọc từ biến môi trường)
├── includes/
│   ├── bootstrap.php         # PDO, JSON helpers, CORS, chống CSRF, xử lý lỗi
│   └── auth.php              # login / register / logout
├── api/
│   ├── auth/                 # login, register, logout, me
│   ├── products/             # index.php, item.php   (GET/POST/PUT/DELETE)
│   ├── categories/           # index.php, item.php
│   ├── orders/               # index.php, item.php, status.php
│   ├── returns/              # index.php, status.php
│   ├── payments/             # index.php, simulate.php
│   ├── shipments/            # index.php, status.php
│   └── reports/              # summary.php
├── database/
│   ├── generate_seed.php     # Sinh lại database.sql
│   └── test_db.php           # Kiểm tra kết nối DB (CLI)
└── assets/
    ├── css/                  # base, layout, components, pages
    └── js/
        ├── api.js            # Fetch wrapper + Bearer token
        ├── data.js           # Hàm định dạng, nhãn/badge trạng thái
        ├── app.js            # Bố cục, modal, toast, phân trang, biểu đồ
        ├── auth.js           # Đăng nhập / đăng ký
        └── pages/*.js        # Từng trang, gọi API thật
```

## 4. Nghiệp vụ chính đã triển khai

**Đơn hàng**

- Tạo đơn: kiểm tra tồn kho với `SELECT ... FOR UPDATE`, giá và tổng tiền lấy tại **backend**.
- Ghi `order_items` (snapshot tên + giá tại thời điểm mua), trừ kho và ghi `inventory_movements` trong **một transaction**.
- Chuyển trạng thái theo vòng đời: `cho_xac_nhan → da_xac_nhan → dang_giao → da_giao`, nhánh hủy `→ da_huy`.
- Hủy đơn hoàn kho **đúng một lần** (cờ `stock_restored`), mọi thay đổi ghi vào `order_history`.

**Đổi trả**

- Chỉ tạo được cho đơn của chính mình và đơn đã `da_giao`.
- Số lượng trả ≤ (đã giao − đã yêu cầu/đã hoàn); duyệt cũng không được vượt hạn mức.
- Tiền hoàn tính từ `order_items.unit_price` trong database.
- Hoàn tất đổi trả mới nhập lại kho (`restock = 1`); trạng thái cuối không cho thao tác lại.

**Thanh toán & giao hàng**

- Mô phỏng thanh toán idempotent: giao dịch đã kết thúc hoặc lặp cùng `idempotency_key` sẽ không xử lý lại.
- Vận đơn tự sinh khi xác nhận đơn; đồng bộ hai chiều với trạng thái đơn hàng.

**An toàn**

- PDO prepared statements cho mọi truy vấn; kiểm tra quyền ở từng API (`401/403`).
- Chống CSRF: kiểm tra `Origin/Referer` cho mọi thao tác POST/PUT/PATCH/DELETE;
  đồng thời xác thực bằng Bearer token (không dựa vào cookie) và session cookie `SameSite=Lax`.
- Mọi lỗi chưa bắt được trả về JSON 500 chung, không lộ stack trace hay thông tin database.
- Dữ liệu đã phát sinh giao dịch được **vô hiệu hóa (ẩn)** thay vì xóa vật lý.

## 5. API tóm tắt

| Method | Endpoint | Quyền |
|---|---|---|
| POST | `api/auth/login.php`, `api/auth/register.php` | Công khai |
| POST | `api/auth/logout.php` | Đã đăng nhập |
| GET | `api/auth/me.php` | Đã đăng nhập |
| GET/POST | `api/products/index.php` | GET: đã đăng nhập, POST: admin |
| GET/PUT/DELETE | `api/products/item.php?id=` | GET: đã đăng nhập, PUT/DELETE: admin |
| GET/POST | `api/categories/index.php` | GET: đã đăng nhập, POST: admin |
| GET/PUT/DELETE | `api/categories/item.php?id=` | như trên |
| GET/POST | `api/orders/index.php` | GET: tự lọc theo vai trò, POST: đã đăng nhập |
| GET | `api/orders/item.php?id=` | Chỉ chủ đơn / admin |
| PATCH | `api/orders/status.php` | admin, staff |
| GET/POST | `api/returns/index.php` | GET: tự lọc, POST: đã đăng nhập |
| PATCH | `api/returns/status.php` | admin, staff |
| GET | `api/payments/index.php` | admin, staff |
| PATCH | `api/payments/simulate.php` | admin, staff |
| GET | `api/shipments/index.php` | admin, staff |
| GET | `api/shipments/status.php` | admin, staff |
| GET | `api/reports/summary.php?days=7\|30\|60\|all` | Đã đăng nhập |

Định dạng phản hồi luôn là:

```json
{ "success": true,  "data": { }, "message": "..." }
{ "success": false, "message": "...", "errors": { "field": "..." } }
```

## 6. Dữ liệu mẫu

`database.sql` tạo lại database `orderflow` với:

- 31 tài khoản (1 admin, nhiều staff/customer), mật khẩu băm bcrypt `123456`
- 8 danh mục, 20+ sản phẩm kèm biến thể và lịch sử tồn kho
- 100+ đơn hàng trong 50 ngày gần nhất đủ mọi trạng thái (kể cả đơn hủy)
- Thanh toán, vận đơn, lịch sử trạng thái đơn và ~15 yêu cầu đổi trả

Chạy lại `database.sql` sẽ **xóa toàn bộ dữ liệu hiện có** và tạo lại từ đầu.

## 7. Giấy phép & nguồn

- Mã nguồn thuộc bài tập lớn của nhóm – dùng cho mục đích học tập.
- Biểu tượng: SVG tự dựng phong cách stroke (không dùng thư viện ngoài).
- Biểu đồ: tự hiện thực bằng SVG thuần (không dùng Chart.js).
