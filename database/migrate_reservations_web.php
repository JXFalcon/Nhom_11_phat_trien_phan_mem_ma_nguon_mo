<?php
/**
 * Migration web: thêm bảng reservations.
 * Truy cập: http://127.0.0.1:8090/database/migrate_reservations_web.php?run=1
 * Sau khi chạy xong, file tự xóa mình.
 */
declare(strict_types=1);

if (!isset($_GET['run'])) {
    http_response_code(400);
    echo 'Chưa kích hoạt. Truy cập: /database/migrate_reservations_web.php?run=1';
    exit;
}

$host = $_SERVER['HTTP_HOST'] ?? '';
if (!in_array($host, ['127.0.0.1', 'localhost', '0.0.0.0'], true)) {
    http_response_code(403);
    echo 'Chỉ cho phép truy cập từ localhost.';
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$exists = $pdo->query("SHOW TABLES LIKE 'reservations'")->fetch();
if ($exists) {
    echo 'Bảng reservations đã tồn tại. Bỏ qua.';
} else {
    $pdo->exec("
CREATE TABLE `reservations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_code` VARCHAR(20) NOT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL COMMENT 'Đơn hàng đã xác nhận liên kết (null nếu vẫn đang giữ)',
  `user_id` INT UNSIGNED DEFAULT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant` VARCHAR(120) DEFAULT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(14,0) NOT NULL COMMENT 'Giá chốt khi đặt',
  `status` ENUM('pending','confirmed','released','expired') NOT NULL DEFAULT 'pending',
  `reserved_until` DATETIME NOT NULL COMMENT 'Thời gian hết hạn giữ hàng',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reservations_code` (`reservation_code`),
  KEY `idx_reservations_order` (`order_id`),
  KEY `idx_reservations_user` (`user_id`),
  KEY `idx_reservations_product` (`product_id`),
  KEY `idx_reservations_status` (`status`),
  KEY `idx_reservations_expires` (`reserved_until`),
  CONSTRAINT `fk_res_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_res_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_res_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;
");
    echo 'Đã tạo bảng reservations.';
}

@unlink(__FILE__);
