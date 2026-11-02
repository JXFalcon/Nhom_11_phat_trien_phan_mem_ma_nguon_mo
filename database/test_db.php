<?php
// Test nhanh kết nối DB (CLI) - dùng đúng cấu hình của ứng dụng.
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    $pdo = db();
    $cfg = require __DIR__ . '/../config/database.php';
    echo "DB OK: " . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . " users\n";
    echo "Config: {$cfg['user']}@{$cfg['host']}:{$cfg['port']}/{$cfg['dbname']}\n";
    echo "Hash check: " . (password_verify('123456', (string)$pdo->query("SELECT password_hash FROM users WHERE email='admin@orderflow.vn'")->fetchColumn()) ? 'OK' : 'FAIL') . "\n";
} catch (Throwable $e) {
    echo "DB FAIL: " . $e->getMessage() . "\n";
}
