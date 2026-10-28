-- Migration: thêm cột reserved_until vào bảng orders (Đề tài 19)
ALTER TABLE `orders`
  ADD COLUMN `reserved_until` DATETIME NULL COMMENT 'Thời gian hết hạn giữ hàng' AFTER `total_amount`,
  ADD INDEX `idx_orders_reserved_until` (`reserved_until`);
