# AGENTS.md

Hướng dẫn ngắn cho AI coding agent làm việc trong OrderFlow — POS PHP thuần + MySQL/MariaDB, frontend HTML/CSS/JS thuần, BTL nhóm 11 (nhánh hiện tại `BTL_1`, `main` là nhánh chính).

## Lệnh chạy & kiểm chứng

- Chạy app: `php -S 127.0.0.1:8090 router.php` từ root → http://127.0.0.1:8090. `router.php` cho `/api/*` chạy file PHP tương ứng, còn lại serve static; không cần Apache.
- Với XAMPP: chép repo vào `htdocs` rồi mở `http://localhost/OrderFlow/login.html` — `router.php` chỉ dùng cho `php -S`.
- Trước khi chạy: `php database/test_db.php` phải in `DB OK` (script CLI-only, trả 403 nếu gọi qua web, exit≠0 khi lỗi).
- Lint toàn repo: `find . -name '*.php' -not -path './.git/*' | xargs -n1 php -l`.
- Smoke giống CI: `php -S 127.0.0.1:8000 router.php &` rồi `curl -fsS http://127.0.0.1:8000/` phải HTTP 200 và chứa `OrderFlow`.
- KHÔNG có unit test API (`tests/` đã xóa). CI `.github/workflows/ci.yml` chỉ gồm `php -l` + smoke; đừng thêm bước Node/npm vì repo không có `package.json`.
- Không có Composer/Node/build step; chỉ cần PHP 8.0+ với `pdo_mysql`, `mbstring`.

## Cấu hình DB — một nguồn sự thật

- `config/database.php` là nơi DUY NHẤT đọc config; ưu tiên `OF_DB_HOST/PORT/NAME/USER/PASS` > `.env` ở root > mặc định XAMPP (127.0.0.1:3306, root, pass rỗng, db `orderflow`).
- `.env` bị gitignore (đừng commit); `.env.example` là mẫu. Giá trị chứa `#` phải bọc quote, không thì bị cắt thành comment; BOM được ltrim ; biến OF_HOLD_MINUTES (phút giữ hàng, 1..1440, mặc định 15) CHỈ đọc qua getenv khi khởi động php — ghi trong .env không có tác dụng.
- Không hardcode credential — mọi file PHP lấy PDO qua `db()` trong `includes/bootstrap.php`.
- Import `database.sql` sẽ `DROP DATABASE orderflow` rồi tạo lại — đừng import nhầm nếu DB đang có dữ liệu cần giữ.

## Kiến trúc & điểm dễ sai

- Có 18 endpoint JSON trong `api/<module>/*.php`; response `{success, data|message, errors}` tạo bằng `json_ok`/`json_fail`; chi tiết endpoint ở `docs/API.md`.
- Mẫu file: `index.php` list/create, `item.php?id=N` get/update/delete, `status.php` chuyển trạng thái; method thực tế đọc trong từng file.
- Mọi file API `require_once includes/auth.php` (file này nạp `bootstrap.php`); đừng tự nạp PDO/config trong endpoint.
- `router.php` trả JSON 404 cho `/api/*` không tồn tại; path khác fallback `index.html`.
- Auth = Bearer token lưu bảng `sessions`; endpoint cần đăng nhập gọi `require_auth()`, cần quyền gọi `require_role('admin','staff')`.
- Token hợp lệ phải dài đúng 64 hex, phiên còn hạn và `users.status=1`; client gặp 401 (trừ path `auth/`) tự xóa token và về `login.html`.
- Client lưu token/user ở localStorage với key `orderflow.token` / `orderflow.user`.
- Phân quyền: `admin` toàn quyền; `staff` xử lý đơn/thanh toán/đổi trả/vận chuyển và xem báo cáo; `customer` chỉ dữ liệu của mình, được tạo đơn và gửi yêu cầu đổi trả.
- Frontend gọi qua `OF.api.*` (`assets/js/api.js`) với path KHÔNG có prefix `/api/`, ví dụ `OF.api.get("orders/index.php")` — đừng grep `/api/` để tìm chỗ gọi.
- `assets/js/data.js` chỉ còn helper format/nhãn (`fmt.money`, `fmt.date`, badge/icon), không còn dữ liệu mock sản phẩm.
- Mỗi trang HTML dùng `assets/js/pages/<tên>.js` tương ứng; sửa hành vi UI thì sửa cả cặp file.
- Dashboard/Báo cáo lấy số liệu từ `api/reports/summary.php` (query `days`, `view`).
- Vòng đời đơn: `cho_xac_nhan→da_xac_nhan→dang_giao→da_giao`; chỉ hủy được từ `cho_xac_nhan` hoặc `da_xac_nhan`. `$allowed` trong `api/orders/status.php` phải khớp `NEXT_STATUS` trong `assets/js/pages/orders.js`, lệch sẽ khiến UI hiện nút mà server trả 409.
- `shipments.status` ENUM chỉ có `cho_lay_hang,dang_van_chuyen,da_giao,that_bai` (KHÔNG có `huy_bo`; KHÔNG có cột `note`).
- Hủy đơn phải nằm trong 1 transaction `FOR UPDATE`: hoàn kho đúng 1 lần (cột `orders.stock_restored`) + payment `cho_xu_ly→that_bai` + shipment đang chạy → `that_bai`.
- `payments/simulate.php` dùng `idempotency_key` chống xử lý trùng; thành công khi đơn `cho_xac_nhan` sẽ tự chuyển `da_xac_nhan` + tạo vận đơn nếu chưa có.
- `orders.hold_expires_at` là hạn giữ hàng của đơn `cho_xac_nhan` (tạo đơn dùng `OF_HOLD_MINUTES`, mặc định 15, đọc qua `getenv`). `includes/orders_sweep.php` quét khi GET list/item (tối đa 200 đơn/lần, idempotent nhờ `stock_restored`); `api/orders/status.php` chặn xác nhận đơn quá hạn (chỉ cho hủy) ; orders.js hiện hạn giữ ở badge danh sách + banner modal (so thời gian ở JS chỉ để nhắc hiển thị — quyết định hủy luôn ở server, KHÔNG thêm logic hủy theo giờ client).
- `payments/simulate.php` khóa đơn TRƯỚC rồi mới khóa payment (thứ tự `orders→payments` khớp `orders_sweep.php`); thanh toán đến muộn (đơn đã hủy/đang giao/đã giao hoặc quá hạn giữ hàng) trả `409` kèm `{late:true, order_status}`.
- Xóa sản phẩm đã phát sinh dữ liệu sẽ chuyển `hidden`; xóa danh mục bị chặn nếu còn sản phẩm.
- Đổi trả chỉ nhận đơn `da_giao`; duyệt với `restock` sẽ nhập lại kho.
- `database/generate_seed.php` GHI ĐÈ `database.sql`; chỉ chạy khi có chủ ý, không cần DB ; schema orders phải có cột hold_expires_at + KEY idx_orders_hold — heredoc trong generator và database.sql phải khớp, chạy lại generator nếu lệch sẽ sinh database.sql import-hỏng.
- Seed: `admin@orderflow.vn` / `nhanvien@orderflow.vn` / `khachhang@orderflow.vn`, mật khẩu `123456` — chỉ demo, không dùng prod ; seed đơn cho_xac_nhan chỉ được payment cho_xu_ly (không sinh thanh_cong khi đơn còn giữ hàng, tránh kịch bản "đã trả tiền mà hết hạn bị hủy").

## Gotchas repo

- Repo trên Windows/OneDrive hay churn CRLF; `.gitattributes` ép `* text=auto eol=lf`. Soi diff bằng `git diff --ignore-cr-at-eol` để tránh khối "thay đổi" giả từ line-ending.
- `api.js` chỉ trỏ backend `127.0.0.1:8090` khi trang chạy ở cổng 5500 hoặc `file://`; chạy cổng khác có thể vướng CORS — sửa `$allowed` trong `bootstrap.php` hoặc chạy đúng cổng.
- Commit theo Conventional Commits (feat/fix/docs/ci/...), làm trên nhánh riêng + PR; KHÔNG push thẳng `main` (cần ≥1 thành viên approve, theo `CONTRIBUTING.md`).
- Mọi text UI/log/comment viết tiếng Việt.
