<?php
/**
 * Shopee – Cấu hình kết nối MySQL (XAMPP mặc định).
 * KHÔNG commit thông tin nhạy cảm thật; đây là cấu hình môi trường demo cục bộ.
 */
declare(strict_types=1);

return [
    'host'    => getenv('OF_DB_HOST') ?: '127.0.0.1',
    'port'    => (int)(getenv('OF_DB_PORT') ?: 3306),
    'dbname'  => getenv('OF_DB_NAME') ?: 'orderflow',
    'user'    => getenv('OF_DB_USER') ?: 'root',
    'pass'    => 'khanh311206',
    'charset' => 'utf8mb4', // <--- Thêm lại dòng này nếu đang bị thiếu
];
