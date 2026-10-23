<?php
// Test nhanh kết nối DB (chỉ chạy qua CLI)
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(
        ['success' => false, 'message' => 'Chỉ chạy qua CLI: php database/test_db.php'],
        JSON_UNESCAPED_UNICODE
    );
    exit(1);
}

$cfg = require __DIR__ . '/../config/database.php';
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $cfg['host'],
    $cfg['port'],
    $cfg['dbname'],
    $cfg['charset']
);

try {
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $users = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $hash = (string)$pdo->query("SELECT password_hash FROM users WHERE email='admin@orderflow.vn'")->fetchColumn();

    echo 'DB OK: ' . $cfg['host'] . ':' . $cfg['port'] . '/' . $cfg['dbname'] . "\n";
    echo 'Users: ' . $users . "\n";
    echo 'Hash check: ' . (password_verify('123456', $hash) ? 'OK' : 'FAIL') . "\n";
} catch (PDOException $e) {
    $code = (int)($e->errorInfo[1] ?? $e->getCode());
    switch ($code) {
        case 1045:
        case 1698:
            $message = 'Access denied — sai user/mật khẩu trong .env';
            break;
        case 2002:
            $message = 'Không kết nối được — MySQL chưa chạy hoặc sai cổng';
            break;
        case 1049:
            $message = 'Chưa có database `' . $cfg['dbname'] . '` — import database.sql trước (lệnh hoặc phpMyAdmin)';
            break;
        default:
            $message = 'DB FAIL [' . $code . ']: ' . $e->getMessage();
            break;
    }
    fwrite(STDERR, $message . "\n");
    exit(1);
}
