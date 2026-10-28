<?php
/**
 * Migration web: thêm cột reserved_until vào bảng orders.
 * Truy cập: http://127.0.0.1:8090/database/migrate_web.php?run=1
 * Sau khi chạy xong, file tự xóa mình.
 */
declare(strict_types=1);

if (!isset($_GET['run'])) {
    http_response_code(400);
    echo 'Chưa kích hoạt. Truy cập: /database/migrate_web.php?run=1';
    exit;
}

// Chỉ cho phép localhost
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!in_array($host, ['127.0.0.1', 'localhost', '0.0.0.0'], true)) {
    http_response_code(403);
    echo 'Chỉ cho phép truy cập từ localhost.';
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$cols = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'reserved_until'")->fetchAll();
if (count($cols) > 0) {
    echo 'Cột reserved_until đã tồn tại. Bỏ qua.';
} else {
    $pdo->exec("ALTER TABLE `orders`
      ADD COLUMN `reserved_until` DATETIME NULL COMMENT 'Thời gian hết hạn giữ hàng' AFTER `total_amount`,
      ADD INDEX `idx_orders_reserved_until` (`reserved_until`)");
    echo 'Đã thêm cột reserved_until vào bảng orders.';
}

// Tự xóa file sau khi chạy
@unlink(__FILE__);
