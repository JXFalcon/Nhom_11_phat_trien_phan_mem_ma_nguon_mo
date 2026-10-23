# 🛒 OrderFlow — Quản lý bán hàng (POS)

**OrderFlow** là web app quản lý bán hàng cơ bản do nhóm xây dựng cho bài tập lớn môn **Phát triển phần mềm mã nguồn mở**. Ứng dụng mô phỏng quy trình bán hàng trực tuyến: quản lý sản phẩm, danh mục, đơn hàng, thanh toán, đổi trả, vận chuyển và xem báo cáo thống kê.

Backend là **PHP thuần** trả JSON, dữ liệu lưu trong **MySQL/MariaDB**; frontend là HTML/CSS/JS thuần, **không dùng framework và không có bước build**.

---

## ✨ Chức năng chính

| Nhóm chức năng | Mô tả |
| -------------- | ----- |
| 📊 Dashboard | Thống kê doanh thu 14 ngày gần đây, tổng đơn, trạng thái đơn, đơn gần đây và các việc cần xử lý. Dữ liệu lấy từ `/api/reports/summary.php`. |
| 📦 Quản lý sản phẩm | Danh sách, thêm, sửa, xóa/ẩn sản phẩm; theo dõi tồn kho và biến thể. Chỉ `admin` được thay đổi. |
| 🏷️ Danh mục | Quản lý danh mục sản phẩm kèm số lượng sản phẩm. Chỉ `admin` được thay đổi. |
| 🧾 Quản lý đơn hàng | Tạo đơn, theo dõi vòng đời đơn, xác nhận/giao/hủy; server tự tính giá và trừ tồn kho. |
| 💳 Quản lý thanh toán | Danh sách giao dịch và mô phỏng kết quả thanh toán `thanh_cong` / `that_bai`, có chống xử lý trùng. |
| ↩️ Quản lý đổi trả | Khách gửi yêu cầu cho đơn đã giao; nhân viên duyệt/từ chối, tính tiền hoàn và nhập lại kho. |
| 🚚 Quản lý giao hàng | Theo dõi vận đơn, cập nhật trạng thái giao hàng và đồng bộ với trạng thái đơn. |
| 📈 Báo cáo thống kê | Doanh thu, trạng thái đơn, tỷ lệ đổi trả, sản phẩm bán chạy, phương thức thanh toán. |

### 👥 Phân quyền

| Vai trò | Quyền hạn |
| ------- | --------- |
| `admin` | Toàn quyền: quản lý sản phẩm, danh mục, đơn hàng, thanh toán, đổi trả, vận chuyển và báo cáo. |
| `staff` | Xử lý đơn hàng, thanh toán, đổi trả, vận chuyển và xem báo cáo; không thêm/sửa/xóa sản phẩm, danh mục. |
| `customer` | Chỉ xem dữ liệu của chính mình; được tạo đơn hàng và gửi yêu cầu đổi trả. |

Khách hàng không mở được Dashboard, Sản phẩm, Danh mục, Báo cáo (bị chuyển sang `403.html`); sau khi đăng nhập, khách được đưa về trang đơn hàng.

---

## 🧰 Công nghệ sử dụng

| Thành phần | Công nghệ |
| ---------- | --------- |
| Backend | PHP 8.0+ thuần, PDO, session, Bearer token lưu bảng `sessions` |
| Cơ sở dữ liệu | MySQL / MariaDB (khuyến nghị MySQL 5.7+ hoặc MariaDB 10.4+) |
| Frontend | HTML5, CSS3, JavaScript thuần (ES6), SVG chart tự vẽ |
| Kiểm thử | Chưa có bộ kiểm thử tự động cho API (nhóm dự kiến bổ sung) |
| CI | GitHub Actions với PHP 8.2 (`shivammathur/setup-php`) |

Không dùng Composer, Node.js, npm hay bundler. Chỉ cần PHP và MySQL là chạy được.

---

## ✅ Yêu cầu môi trường

- **PHP 8.0 trở lên** với các extension: `pdo`, `pdo_mysql`, `mbstring`, `json`, `session`.
- **MySQL 5.7+** hoặc **MariaDB 10.4+**.
- Trình duyệt hiện đại (Chrome, Edge, Firefox...).
- (Khuyến nghị) **XAMPP** để có sẵn Apache + MySQL + phpMyAdmin trên Windows.

Kiểm tra nhanh:

```bash
php -v
php -m | grep -E "pdo_mysql|mbstring"
```

---

## 🧰 Kết nối MySQL trên máy của bạn

`config/database.php` đọc cấu hình theo thứ tự ưu tiên: **biến môi trường hệ thống** (`OF_DB_*`) → **file `.env`** ở thư mục gốc → **giá trị mặc định của XAMPP**. File `.env` không bắt buộc; nếu thiếu hoặc có dòng không hợp lệ, app vẫn dùng giá trị mặc định và không bị lỗi.

### 1. Cách nhanh — XAMPP mặc định (root không mật khẩu, cổng 3306)

**Không cần tạo `.env`.** Chỉ cần bật MySQL trong XAMPP, import `database.sql`, rồi chạy app. Cấu hình mặc định là `127.0.0.1:3306`, database `orderflow`, user `root`, mật khẩu rỗng.

### 2. Cách cho root CÓ mật khẩu (trường hợp thường gặp)

Sao chép file mẫu thành `.env` ở **thư mục gốc dự án**:

```bat
copy .env.example .env
```

Mở `.env` và điền mật khẩu thật của MySQL vào `OF_DB_PASS`:

```dotenv
OF_DB_PASS=MAT-KHAU-CUA-BAN
# Nếu mật khẩu chứa dấu # hãy bọc mật khẩu trong dấu nháy kép.
```

Trên Linux/macOS dùng `cp .env.example .env`. Nếu MySQL chạy cổng khác 3306 (ví dụ MySQL 8 cài riêng dùng 3307), sửa `OF_DB_PORT` cho khớp.

### 3. Các biến cấu hình

| Biến | Giá trị mặc định | Ý nghĩa |
| ---- | ---------------- | ------- |
| `OF_DB_HOST` | `127.0.0.1` | Host của MySQL |
| `OF_DB_PORT` | `3306` | Cổng MySQL (đổi nếu server dùng cổng khác) |
| `OF_DB_NAME` | `orderflow` | Tên database cần kết nối |
| `OF_DB_USER` | `root` | Tài khoản MySQL |
| `OF_DB_PASS` | rỗng | Mật khẩu MySQL (XAMPP mặc định để trống) |

Giá trị trong `.env` là khóa riêng của từng người (file đã được gitignore) — người dùng MySQL 8 cài riêng thường phải điền **cả** `OF_DB_PASS` (và đổi `OF_DB_USER` nếu không dùng `root`).

Biến môi trường hệ thống có độ ưu tiên cao hơn `.env`, ví dụ trên PowerShell:

```powershell
$env:OF_DB_PASS = "mat_khau_cua_ban"
php -S localhost:8000 router.php
```

### 4. Kiểm tra kết nối trước khi chạy app

Từ thư mục gốc dự án, chạy:

```bash
php database/test_db.php
```

Phải thấy dòng `DB OK: 127.0.0.1:3306/orderflow` (host/cổng/database theo cấu hình của bạn) cùng số lượng users và `Hash check: OK`. Nếu lỗi, script in gợi ý cụ thể (sai mật khẩu, MySQL chưa chạy, chưa import database...) và trả mã thoát khác 0.

### 5. Bảo mật

File `.env` đã được `.gitignore` — **không commit mật khẩu thật** lên Git.

---

## 🚀 Hướng dẫn cài đặt

### 1. Cài XAMPP

Tải và cài [XAMPP](https://www.apachefriends.org/) (bản PHP 8.x). Mở **XAMPP Control Panel**, bấm **Start** cho cả **Apache** và **MySQL**.

### 2. Tạo database và import dữ liệu

File `database.sql` ở thư mục gốc **đã bao gồm** các lệnh:

```sql
DROP DATABASE IF EXISTS orderflow;
CREATE DATABASE orderflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE orderflow;
```

Vì vậy **không cần tạo database trước** — chỉ cần import file. Lưu ý: file sẽ **xóa database `orderflow` cũ** nếu đã tồn tại.

**Cách A — dùng phpMyAdmin**

1. Mở `http://localhost/phpmyadmin`.
2. Chọn tab **Import** (ở trang chủ server, không cần chọn database).
3. Chọn file `database.sql` → **Go/Import**.
4. Kiểm tra database `orderflow` đã xuất hiện với 13 bảng và dữ liệu mẫu.

**Cách B — dùng dòng lệnh**

```bash
mysql -u root -p < database.sql
```

Với XAMPP trên Windows, có thể dùng đường dẫn đầy đủ:

```bat
C:\xampp\mysql\bin\mysql.exe -u root -p < database.sql
```

> ⚠️ **MySQL 8 và client cũ của XAMPP:** MySQL 8 dùng plugin xác thực mặc định `caching_sha2_password`, còn `mysql.exe` cũ đi kèm XAMPP (dựa trên MariaDB cũ) **không hỗ trợ** plugin này — sẽ báo `Plugin caching_sha2_password could not be loaded` dù mật khẩu đúng. Nếu server của bạn là MySQL 8 (không phải MariaDB của XAMPP):
>
> - import bằng **phpMyAdmin** (cách A vẫn chạy được vì PHP/PDO dùng mysqlnd nên nối được), hoặc
> - dùng client `mysql.exe` **của riêng bộ cài MySQL 8** (thường ở `C:\Program Files\MySQL\MySQL Server <bản>\bin\`), hoặc
> - dùng **MySQL Workbench**: File → Open SQL Script → chọn `database.sql` → Execute.
>
> Nếu import bằng client MariaDB ≥ 11.8 với MySQL 8 có TLS tự ký thì thêm `--skip-ssl`.

Kiểm tra nhanh kết nối và tài khoản seed:

```bash
php database/test_db.php
```

Kết quả mong đợi có dạng:

```text
DB OK: 127.0.0.1:3306/orderflow
Users: 30
Hash check: OK
```

### 3. Chạy ứng dụng

#### Cách A — XAMPP (khuyến nghị)

1. Chép toàn bộ thư mục dự án vào `C:\xampp\htdocs\OrderFlow`.
2. Bật Apache và MySQL.
3. Mở trình duyệt: `http://localhost/OrderFlow/login.html`.

#### Cách B — PHP built-in server

Từ **thư mục gốc dự án**, chạy:

```bash
php -S localhost:8000 router.php
```

Sau đó mở `http://localhost:8000/login.html`.

`router.php` hoạt động như sau:

- File tĩnh có thật (`.html`, `.css`, `.js`, ảnh...) được đọc và trả về kèm MIME phù hợp.
- File `.php` được để PHP tự thực thi — dùng cho toàn bộ `api/**/*.php`.
- Đường dẫn `/api/...` không tồn tại trả JSON lỗi 404.
- Các đường dẫn còn lại trả về `false` để PHP built-in server xử lý mặc định (trang chủ `/` sẽ phục vụ `index.html`).

> Nếu dùng **Live Server** của VS Code ở cổng `5500`, `assets/js/api.js` sẽ tự gọi backend ở `http://127.0.0.1:8090`. Khi đó hãy chạy backend bằng:
>
> ```bash
> php -S 127.0.0.1:8090 router.php
> ```

### 4. Tài khoản demo

Dữ liệu seed trong `database.sql` có sẵn các tài khoản (mật khẩu chung: **`123456`**, đã băm bcrypt):

| Vai trò | Email | Mật khẩu |
| ------- | ----- | -------- |
| Admin | `admin@orderflow.vn` | `123456` |
| Staff | `nhanvien@orderflow.vn` | `123456` |
| Customer | `khachhang@orderflow.vn` | `123456` |

Ngoài ra còn nhiều tài khoản `user04@orderflow.vn` ... `user30@orderflow.vn` với cùng mật khẩu. Đây là dữ liệu demo, **không dùng cho môi trường thật**.

### 5. Chạy từ WSL (PHP Linux + MySQL trên Windows)

> Phần này chỉ cần nếu PHP chạy trong WSL/VM còn MySQL chạy trên Windows; mọi thứ cùng một máy thì bỏ qua.

1. **Trên Windows** — tạo user riêng cho MySQL 8 (ví dụ trong Workbench), thu hẹp host vì client MySQL 8 mặc định không chấp nhận IP lạ ngoài `localhost`:

   ```sql
   CREATE USER 'orderflow'@'172.18.%' IDENTIFIED BY '...';
   GRANT ALL PRIVILEGES ON orderflow.* TO 'orderflow'@'172.18.%';
   FLUSH PRIVILEGES;
   ```

2. **Trong WSL** — lấy IP của Windows host bằng `ip route | awk '/default/{print $3}'`; đó là giá trị `OF_DB_HOST` phải điền trong `.env`. Cài thêm `sudo apt install php-mbstring` — thiếu extension này app báo `Call to undefined function mb_strtolower()` ngay khi đăng nhập.

3. **Trong WSL** — import bằng client MariaDB ≥ 11.8 (hỗ trợ `caching_sha2_password`) nhưng MySQL 8 dùng TLS tự ký nên phải thêm `--skip-ssl`:

   ```bash
   mysql --skip-ssl -h <IP-windows> -u orderflow -p... < database.sql
   php database/test_db.php
   php -S 127.0.0.1:8090 router.php
   ```

   `test_db.php` phải in `DB OK`; nhờ localhost forwarding của WSL2, trình duyệt trên Windows mở được ngay `http://127.0.0.1:8090/login.html`.

---

## 📁 Cấu trúc thư mục

```text
OrderFlow/
├── api/                    # 18 endpoint JSON
│   ├── auth/               # đăng nhập, đăng ký, đăng xuất, thông tin user
│   ├── products/           # sản phẩm
│   ├── categories/         # danh mục
│   ├── orders/             # đơn hàng
│   ├── payments/           # thanh toán
│   ├── returns/            # đổi trả
│   ├── shipments/          # vận chuyển
│   └── reports/            # báo cáo tổng hợp
├── assets/
│   ├── css/                # giao diện
│   └── js/
│       ├── api.js          # client gọi API + quản lý token
│       ├── app.js          # sidebar, topbar, modal, toast, biểu đồ
│       ├── data.js         # hàm định dạng, nhãn trạng thái
│       └── pages/          # logic từng trang
├── config/database.php     # cấu hình PDO, đọc .env / biến môi trường OF_DB_*
├── .env.example            # mẫu cấu hình kết nối MySQL (sao chép thành .env)
├── database/               # script sinh seed + test kết nối
├── docs/API.md             # tài liệu chi tiết các endpoint
├── includes/
│   ├── bootstrap.php       # PDO, JSON helper, CORS, Bearer auth
│   └── auth.php            # nghiệp vụ đăng nhập/đăng ký/đăng xuất
├── *.html                  # 11 trang giao diện
├── database.sql            # schema + dữ liệu mẫu (13 bảng)
└── router.php              # router cho PHP built-in server
```

---

## 🔌 Tổng quan API

Chi tiết đầy đủ (body, quyền, ví dụ curl) xem tại **[docs/API.md](docs/API.md)**.

| Nhóm | Endpoint chính | Method |
| ---- | -------------- | ------ |
| Xác thực | `/api/auth/login.php`, `/api/auth/register.php`, `/api/auth/logout.php`, `/api/auth/me.php` | `POST`, `GET` |
| Sản phẩm | `/api/products/index.php`, `/api/products/item.php?id=N` | `GET`, `POST`, `PUT`, `DELETE` |
| Danh mục | `/api/categories/index.php`, `/api/categories/item.php?id=N` | `GET`, `POST`, `PUT`, `DELETE` |
| Đơn hàng | `/api/orders/index.php`, `/api/orders/item.php?id=N`, `/api/orders/status.php` | `GET`, `POST`, `PATCH` |
| Thanh toán | `/api/payments/index.php`, `/api/payments/simulate.php` | `GET`, `PATCH` |
| Đổi trả | `/api/returns/index.php`, `/api/returns/status.php` | `GET`, `POST`, `PATCH` |
| Vận chuyển | `/api/shipments/index.php`, `/api/shipments/status.php` | `GET`, `PATCH` |
| Báo cáo | `/api/reports/summary.php` | `GET` |

Mọi response có dạng:

```json
{ "success": true, "data": {}, "message": "..." }
```

hoặc khi lỗi:

```json
{ "success": false, "message": "...", "errors": {} }
```

Xác thực bằng header `Authorization: Bearer <token>`; token nhận được từ `data.token` khi đăng nhập.

---

## 🧪 Kiểm thử

Hiện chưa có bộ kiểm thử tự động cho API — nhóm dự kiến bổ sung. Trong thời gian chờ, có thể kiểm tra cú pháp toàn bộ file PHP:

```bash
find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l
```

---

## 📌 Trạng thái phát triển

- **Backend:** đã có 18 endpoint JSON cho 8 nhóm chức năng, dùng PDO + transaction cho các nghiệp vụ quan trọng (tạo đơn, hủy đơn, thanh toán, đổi trả, vận chuyển).
- **Frontend:** 11 trang HTML (dashboard, đăng nhập, đăng ký, sản phẩm, danh mục, đơn hàng, thanh toán, đổi trả, vận chuyển, báo cáo, 403).
- **Kết nối API:** toàn bộ trang nghiệp vụ hiện gọi API thật qua `assets/js/api.js`. Dashboard và Báo cáo dùng `/api/reports/summary.php`; các trang còn lại dùng endpoint CRUD tương ứng.
- **Dữ liệu mock:** ở giai đoạn trước, các trang từng dùng dữ liệu mock trong `assets/js/data.js`. Hiện file này chỉ còn hàm định dạng tiền/ngày, nhãn trạng thái và helper dùng chung — không còn dữ liệu nghiệp vụ giả.
- **Việc đang tiếp tục:** bổ sung kiểm thử tích hợp API + database, hoàn thiện xử lý các trường hợp biên và cập nhật tài liệu khi hành vi thay đổi.

---

## 🤝 Đóng góp

Quy trình làm việc, đặt tên branch, chuẩn commit và review được quy định tại **[CONTRIBUTING.md](CONTRIBUTING.md)**.

Tóm tắt nhanh:

1. Không push trực tiếp lên `main`; tạo branch `feat/...`, `fix/...`, `docs/...`.
2. Commit theo Conventional Commits.
3. Mở Pull Request, cần ít nhất 1 thành viên khác approve.

---

## 📄 Giấy phép

Dự án phát hành theo giấy phép **MIT** — xem [LICENSE](LICENSE).
