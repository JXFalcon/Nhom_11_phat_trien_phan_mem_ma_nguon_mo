<?php
/**
 * Chạy migration để bổ sung cột reserved_until vào bảng orders.
 * Dùng: php database\migrate.php
 */
declare(strict_types=1);

$cfg = require __DIR__ . '/../config/database.php';
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $cfg['host'], $cfg['port'], $cfg['dbname'], $cfg['charset']
);
$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// Kiểm tra cột đã tồn tại chưa
$cols = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'reserved_until'")->fetchAll();
if (count($cols) > 0) {
    echo "Cột reserved_until đã tồn tại. Bỏ qua.\n";
    exit(0);
}

$pdo->exec("ALTER TABLE `orders`
  ADD COLUMN `reserved_until` DATETIME NULL COMMENT 'Thời gian hết hạn giữ hàng' AFTER `total_amount`,
  ADD INDEX `idx_orders_reserved_until` (`reserved_until`)");
echo "Đã thêm cột reserved_until vào bảng orders.\n";
