<?php
/**
 * Migration additive: thêm bảng payment_qr_sessions (QR chuyển khoản).
 *
 * Web: http://127.0.0.1:8090/database/migrate_payment_qr_web.php?run=1
 * CLI: php database/migrate_payment_qr_web.php --run
 *
 * Nguyên tắc: idempotent, chỉ tạo bảng nếu chưa có, không xóa dữ liệu,
 * không tự xóa file để có thể chạy lại an toàn.
 */
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
$run = $isCli ? in_array('--run', $argv ?? [], true) : isset($_GET['run']);
if (!$run) {
    http_response_code(400);
    echo 'Chưa kích hoạt. Web: ?run=1 hoặc CLI: php database/migrate_payment_qr_web.php --run';
    exit;
}

if (!$isCli) {
    $host = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (!in_array($host, ['127.0.0.1', 'localhost', '0.0.0.0', '::1'], true)) {
        http_response_code(403);
        echo 'Chỉ cho phép truy cập từ localhost.';
        exit;
    }
}

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$exists = $pdo->query("SHOW TABLES LIKE 'payment_qr_sessions'")->fetch();
if ($exists) {
    echo 'Bảng payment_qr_sessions đã tồn tại. Bỏ qua.';
    exit(0);
}

$pdo->exec("
CREATE TABLE `payment_qr_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `qr_token` VARCHAR(16) NOT NULL,
  `payment_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(14,0) NOT NULL,
  `memo` VARCHAR(50) NOT NULL COMMENT 'Nội dung chuyển khoản: mã đơn + hậu tố random',
  `image_url` VARCHAR(600) NOT NULL COMMENT 'URL ảnh QR VietQR',
  `status` ENUM('pending','paid','expired','replaced') NOT NULL DEFAULT 'pending',
  `expires_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qr_token` (`qr_token`),
  KEY `idx_qr_payment` (`payment_id`),
  KEY `idx_qr_order` (`order_id`),
  KEY `idx_qr_status` (`status`),
  KEY `idx_qr_expires` (`expires_at`),
  CONSTRAINT `fk_qr_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qr_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qr_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;
");

echo 'Đã tạo bảng payment_qr_sessions.';
