<?php
/**
 * Migration web: thêm bảng carts và cart_items.
 * Truy cập: http://127.0.0.1:8090/database/migrate_carts_web.php?run=1
 * Sau khi chạy xong, file tự xóa mình.
 */
declare(strict_types=1);

if (!isset($_GET['run'])) {
    http_response_code(400);
    echo 'Chưa kích hoạt. Truy cập: /database/migrate_carts_web.php?run=1';
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

$exists = $pdo->query("SHOW TABLES LIKE 'carts'")->fetch();
if ($exists) {
    echo 'Bảng carts đã tồn tại. Bỏ qua.';
} else {
    $pdo->exec("
CREATE TABLE `carts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('active','checked_out','abandoned') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_carts_user` (`user_id`),
  KEY `idx_carts_status` (`status`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
");
    echo 'Đã tạo bảng carts. ';
}

$exists2 = $pdo->query("SHOW TABLES LIKE 'cart_items'")->fetch();
if ($exists2) {
    echo 'Bảng cart_items đã tồn tại. Bỏ qua.';
} else {
    $pdo->exec("
CREATE TABLE `cart_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant` VARCHAR(120) DEFAULT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(14,0) NOT NULL COMMENT 'Giá sản phẩm tại thời điểm thêm vào giỏ',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ci_cart` (`cart_id`),
  KEY `idx_ci_product` (`product_id`),
  CONSTRAINT `fk_ci_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ci_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;
");
    echo 'Đã tạo bảng cart_items. ';
}

@unlink(__FILE__);
