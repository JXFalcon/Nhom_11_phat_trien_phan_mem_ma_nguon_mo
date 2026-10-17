# An Nhiên — Quản lý bán hàng

Ứng dụng quản lý bán hàng chạy trên PHP 8.1 trở lên. Giao diện được xây dựng bằng HTML, CSS và JavaScript thuần; dữ liệu được lưu cục bộ trong `data/store.json`. Không cần Composer, database, framework hoặc thư viện bên ngoài.

## Chạy ứng dụng

Mở Ubuntu/WSL và chạy:

```bash
cd ~/lap2/Ma_nguon/ban_hang
php -S 127.0.0.1:8085 -t public
```

Giữ cửa sổ terminal mở và truy cập **http://127.0.0.1:8085** trong trình duyệt. Dừng máy chủ bằng `Ctrl+C`. Ứng dụng chỉ lắng nghe trên máy hiện tại; không bind lên địa chỉ mạng công cộng.

## Tính năng

- Trang tổng quan với doanh thu trong ngày, số đơn hoàn thành, khách hàng, hàng sắp hết và biểu đồ doanh thu bảy ngày.
- Thêm, sửa, tìm kiếm, lọc danh mục và xóa sản phẩm; kiểm tra mã SKU trùng.
- Thêm, sửa, tìm kiếm và xóa thông tin khách hàng.
- Tạo đơn bán lẻ hoặc đơn gắn với khách hàng; chọn nhiều sản phẩm, tính tiền và trừ kho tự động.
- Hủy đơn hoặc khôi phục đơn; số lượng tồn kho được hoàn lại hoặc trừ lại tương ứng.
- Dữ liệu mẫu để có thể xem giao diện ngay lần đầu; dữ liệu bán hàng của bạn được lưu cục bộ khi thay đổi.

## Chạy kiểm thử

Từ thư mục `ban_hang`, chạy:

```bash
php tests/run.php
```

Các kiểm thử nghiệp vụ sử dụng tệp tạm riêng, không thay đổi dữ liệu của cửa hàng.

> Ứng dụng này độc lập, nằm trong `Ma_nguon/ban_hang`; không thay đổi chương trình Lab 02 chạy bằng `bash lab.sh run`.
