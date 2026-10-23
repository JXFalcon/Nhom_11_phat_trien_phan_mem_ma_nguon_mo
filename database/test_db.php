<?php
// Test nhanh kết nối DB (CLI)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=orderflow;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "DB OK: " . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . " users\n";
    echo "Hash check: " . (password_verify('123456', $pdo->query("SELECT password_hash FROM users WHERE email='admin@orderflow.vn'")->fetchColumn()) ? 'OK' : 'FAIL') . "\n";
} catch (Exception $e) {
    echo "DB FAIL: " . $e->getMessage() . "\n";
}
