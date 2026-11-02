<?php
/**
 * Shopee - Cấu hình kết nối MySQL.
 * Ưu tiên: config/database.local.php (không commit) > biến môi trường OF_DB_*.
 */
declare(strict_types=1);

$local = [];
$localFile = __DIR__ . '/database.local.php';
if (is_file($localFile)) {
    $loaded = require $localFile;
    $local = is_array($loaded) ? $loaded : [];
}

return [
    'host'    => getenv('OF_DB_HOST') ?: ($local['host'] ?? '127.0.0.1'),
    'port'    => (int)(getenv('OF_DB_PORT') ?: ($local['port'] ?? 3306)),
    'dbname'  => getenv('OF_DB_NAME') ?: ($local['dbname'] ?? 'orderflow'),
    'user'    => getenv('OF_DB_USER') ?: ($local['user'] ?? 'root'),
    'pass'    => getenv('OF_DB_PASS') ?: ($local['pass'] ?? ''),
    'charset' => 'utf8mb4',
];
