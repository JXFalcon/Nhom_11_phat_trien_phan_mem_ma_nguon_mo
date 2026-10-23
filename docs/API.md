# 📡 OrderFlow API

Tài liệu mô tả các endpoint JSON hiện có trong thư mục `api/` của dự án OrderFlow.
Toàn bộ endpoint đều dùng chung `includes/bootstrap.php` để kết nối PDO, trả JSON, xử lý CORS và xác thực Bearer token.

- Base URL khi chạy bằng PHP built-in server: `http://localhost:8000`
- Base URL khi chạy trong XAMPP: `http://localhost/<thư-mục-dự-án>`
- Định dạng: JSON (`Content-Type: application/json; charset=utf-8`)
- Tổng số file endpoint hiện có: **18 file PHP** trong `api/`

---

## 🔐 Xác thực

1. Gọi `POST /api/auth/login.php` với `email` và `password`.
2. Server trả về `data.token` (chuỗi 64 ký tự hex) và `data.user`.
3. Gửi token ở các request sau qua header:

```http
Authorization: Bearer <token>
```

Token được lưu trong bảng `sessions`, có hiệu lực 7 ngày. Tài khoản bị khóa (`users.status = 0`) sẽ không xác thực được.

Các quyền trong hệ thống:

| Vai trò | Mô tả |
| ------- | ----- |
| `admin` | Quản trị viên: toàn quyền, gồm quản lý sản phẩm/danh mục/đơn hàng/thanh toán/đổi trả/vận chuyển và xem báo cáo. |
| `staff` | Nhân viên: xử lý đơn hàng, thanh toán, đổi trả, vận chuyển và xem báo cáo; không thêm/sửa/xóa sản phẩm, danh mục. |
| `customer` | Khách hàng: chỉ xem dữ liệu của chính mình; có thể tạo đơn và gửi yêu cầu đổi trả. |

---

## 📦 Định dạng phản hồi

Thành công:

```json
{
  "success": true,
  "data": {},
  "message": "Thông báo thành công."
}
```

Lỗi:

```json
{
  "success": false,
  "message": "Mô tả lỗi.",
  "errors": {}
}
```

Một số mã HTTP thường gặp:

| Mã | Ý nghĩa |
| -- | ------- |
| `200` | Xử lý thành công |
| `201` | Tạo mới thành công |
| `401` | Chưa đăng nhập hoặc token hết hạn |
| `403` | Không đủ quyền / nguồn gửi bị chặn |
| `404` | Không tìm thấy tài nguyên |
| `405` | Sai phương thức HTTP |
| `409` | Xung đột nghiệp vụ (trùng mã, sai vòng đời trạng thái, vượt tồn kho...) |
| `422` | Dữ liệu đầu vào không hợp lệ (`errors` chứa lỗi từng trường) |
| `500` | Lỗi hệ thống |

Lưu ý: với các request thay đổi dữ liệu (`POST`, `PUT`, `PATCH`, `DELETE`), `includes/bootstrap.php` kiểm tra thêm `Origin`/`Referer` để chặn nguồn lạ. Các origin được phép gồm `localhost`/`127.0.0.1` ở cổng `5500`, `8000` và cổng mặc định.

---

## 🗂️ Danh sách endpoint

| Method | Path | Đăng nhập | Quyền | Mô tả | Body / Query chính |
| ------ | ---- | --------- | ----- | ----- | ------------------ |
| `POST` | `/api/auth/login.php` | Không | Công khai | Đăng nhập, tạo phiên và trả token | `email`, `password` |
| `POST` | `/api/auth/register.php` | Không | Công khai | Đăng ký tài khoản `customer` | `full_name`, `email`, `phone`, `password`, `password_confirm` |
| `POST` | `/api/auth/logout.php` | Không bắt buộc | Công khai | Đăng xuất, xóa phiên theo Bearer token (nếu có) | — |
| `GET` | `/api/auth/me.php` | Có | admin, staff, customer | Thông tin tài khoản đang đăng nhập | — |
| `GET` | `/api/products/index.php` | Có | admin, staff, customer | Danh sách sản phẩm | Query: `q`, `category_id`, `status`, `page`, `per_page` |
| `POST` | `/api/products/index.php` | Có | admin | Tạo sản phẩm | `name`, `sku`, `category_id`, `price`, `stock`, `variants`, `emoji`, `status` |
| `GET` | `/api/products/item.php?id=N` | Có | admin, staff, customer | Chi tiết sản phẩm | Query: `id` |
| `PUT` | `/api/products/item.php?id=N` | Có | admin | Cập nhật sản phẩm; giá mới không làm đổi đơn đã chốt | `name`, `sku`, `category_id`, `price`, `stock`, `variants`, `emoji`, `status` |
| `DELETE` | `/api/products/item.php?id=N` | Có | admin | Xóa sản phẩm; nếu đã phát sinh dữ liệu thì chuyển sang `hidden` | Query: `id` |
| `GET` | `/api/categories/index.php` | Có | admin, staff, customer | Danh sách danh mục kèm `product_count` | — |
| `POST` | `/api/categories/index.php` | Có | admin | Tạo danh mục | `name`, `description`, `emoji` |
| `GET` | `/api/categories/item.php?id=N` | Có | admin, staff, customer | Chi tiết danh mục | Query: `id` |
| `PUT` | `/api/categories/item.php?id=N` | Có | admin | Cập nhật danh mục | `name`, `description`, `emoji` |
| `DELETE` | `/api/categories/item.php?id=N` | Có | admin | Xóa danh mục; chặn nếu còn sản phẩm | Query: `id` |
| `GET` | `/api/orders/index.php` | Có | admin, staff (tất cả); customer (đơn của mình) | Danh sách đơn hàng; mỗi đơn trả kèm `hold_expires_at` (đơn `cho_xac_nhan` quá hạn sẽ được tự hủy khi gọi) | Query: `q`, `status`, `from`, `to`, `page`, `per_page` |
| `POST` | `/api/orders/index.php` | Có | admin, staff, customer | Tạo đơn; server tự tính giá, trừ tồn kho và tạo giao dịch chờ; `hold_expires_at = now + OF_HOLD_MINUTES` phút (mặc định 15) | `customer_name`, `phone`, `address`, `payment_method`, `items[]` (`product_id`, `quantity`, `variant`) |
| `GET` | `/api/orders/item.php?id=N` | Có | admin, staff; customer chỉ đơn của mình | Chi tiết đơn kèm `hold_expires_at`, `items`, `payments`, `shipments`, `returns`, `history` | Query: `id` |
| `PATCH` (hoặc `POST`) | `/api/orders/status.php` | Có | admin, staff | Chuyển trạng thái đơn theo vòng đời; hủy đơn hoàn kho đúng 1 lần và hủy vận đơn đang xử lý/giao | `id`, `to_status`, `note` |
| `GET` | `/api/payments/index.php` | Có | admin, staff (tất cả); customer (đơn của mình) | Danh sách giao dịch thanh toán | Query: `q`, `status`, `method`, `page`, `per_page` |
| `PATCH` (hoặc `POST`) | `/api/payments/simulate.php` | Có | admin, staff | Mô phỏng kết quả thanh toán, có chống xử lý trùng; thanh toán đến muộn trả `409` kèm `{late:true, order_status}` | `id`, `result` (`thanh_cong`/`that_bai`), `idempotency_key` |
| `GET` | `/api/returns/index.php` | Có | admin, staff (tất cả); customer (của mình) | Danh sách yêu cầu đổi trả | Query: `q`, `status`, `page`, `per_page` |
| `POST` | `/api/returns/index.php` | Có | admin, staff, customer | Tạo yêu cầu đổi trả cho đơn đã giao | `order_id`, `order_item_id`, `quantity`, `reason` |
| `PATCH` (hoặc `POST`) | `/api/returns/status.php` | Có | admin, staff | Xử lý đổi trả, tính tiền hoàn và nhập lại kho nếu cần | `id`, `to_status`, `quantity_accepted`, `note`, `restock` |
| `GET` | `/api/shipments/index.php` | Có | admin, staff (tất cả); customer (của mình) | Danh sách vận đơn | Query: `q`, `status`, `page`, `per_page` |
| `PATCH` (hoặc `POST`) | `/api/shipments/status.php` | Có | admin, staff | Cập nhật trạng thái vận đơn và đồng bộ trạng thái đơn hàng | `id`, `to_status` |
| `GET` | `/api/reports/summary.php` | Có | admin, staff | Tổng hợp số liệu Dashboard/Báo cáo | Query: `days` (`7`, `30`, `60`, `all`), `view` (`dashboard`, `reports`) |

---

## 🔁 Trạng thái nghiệp vụ

### Đơn hàng (`orders.status`)

```
cho_xac_nhan → da_xac_nhan → dang_giao → da_giao
      ↘ da_huy       ↘ da_huy
```

- `cho_xac_nhan`: chờ xác nhận
- `da_xac_nhan`: đã xác nhận
- `dang_giao`: đang giao
- `da_giao`: đã giao (trạng thái cuối)
- `da_huy`: đã hủy (trạng thái cuối), tồn kho được hoàn đúng một lần; vận đơn `cho_lay_hang`/`dang_van_chuyen` chuyển sang `that_bai`

Đơn `cho_xac_nhan` có `hold_expires_at` (hạn giữ hàng, mặc định thêm `OF_HOLD_MINUTES` phút, không đặt thì 15). Khi GET danh sách/chi tiết, server tự quét và hủy các đơn đã quá hạn (`includes/orders_sweep.php`, hoàn kho đúng một lần). Đơn quá hạn chỉ còn có thể hủy; `status.php` trả `409` nếu cố xác nhận.

### Thanh toán (`payments.status`)

`cho_xu_ly`, `thanh_cong`, `that_bai`.
Khi mô phỏng `thanh_cong` và đơn đang `cho_xac_nhan`, server tự chuyển đơn sang `da_xac_nhan`, tạo vận đơn nếu chưa có và trả `order_status` là `da_xac_nhan`.
Nếu thanh toán đến muộn — đơn đã `da_huy`/`dang_giao`/`da_giao`, hoặc `cho_xac_nhan` nhưng đã quá hạn giữ hàng — server trả `409` với `data` gồm `{late: true, order_status}` và ghi giao dịch `that_bai` (không hồi sinh đơn).

### Đổi trả (`return_requests.status`)

```
cho_xu_ly → da_chap_nhan → da_hoan_tat
     ↘ tu_choi        ↘ tu_choi
```

### Vận chuyển (`shipments.status`)

```
cho_lay_hang → dang_van_chuyen → da_giao
       ↘ that_bai ↗
```

---

## 🧪 Ví dụ gọi API bằng curl

### 1. Đăng nhập và lấy token

```bash
BASE=http://localhost:8000

curl -s -X POST "$BASE/api/auth/login.php" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@orderflow.vn","password":"123456"}'
```

Phản hồi rút gọn:

```json
{
  "success": true,
  "data": {
    "token": "64_ky_tu_hex...",
    "user": {
      "id": 1,
      "full_name": "Nguyễn Văn Admin",
      "email": "admin@orderflow.vn",
      "phone": "0901000001",
      "role": "admin"
    }
  },
  "message": "Đăng nhập thành công."
}
```

Lưu token vào biến môi trường (dùng `jq`):

```bash
TOKEN=$(curl -s -X POST "$BASE/api/auth/login.php" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@orderflow.vn","password":"123456"}' | jq -r '.data.token')

echo "$TOKEN"
```

### 2. Gọi endpoint cần đăng nhập

```bash
curl -s "$BASE/api/products/index.php?page=1&per_page=5" \
  -H "Authorization: Bearer $TOKEN"
```

### 3. Tạo đơn hàng

```bash
curl -s -X POST "$BASE/api/orders/index.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "payment_method": "cod",
    "items": [
      { "product_id": 1, "quantity": 2 }
    ]
  }'
```

### 4. Chuyển trạng thái đơn

```bash
curl -s -X PATCH "$BASE/api/orders/status.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"id":1,"to_status":"da_xac_nhan","note":"Xác nhận đơn"}'
```

### 5. Xem báo cáo tổng hợp

```bash
curl -s "$BASE/api/reports/summary.php?days=30&view=reports" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 📝 Ghi chú

- Các endpoint danh sách trả `data.items`, `data.total`, `data.page`, `data.per_page`.
- `reports/summary.php` trả `data.stats`, `data.revenue_daily`, `data.status_counts`, `data.top_products`, `data.payment_methods`, `data.recent_orders`, `data.range_days`.
- `auth/me.php` trả trực tiếp thông tin user trong `data`.
- Mọi kiểm tra quyền đều thực hiện ở server; ẩn nút trên giao diện chỉ mang tính hỗ trợ.
- Khi thử API bằng công cụ khác origin, cần thêm header `Origin` phù hợp hoặc chạy cùng origin với backend.
