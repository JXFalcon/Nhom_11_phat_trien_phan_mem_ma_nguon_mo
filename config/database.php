<?php
/**
 * OrderFlow – Cấu hình kết nối MySQL.
 * Thứ tự ưu tiên: biến môi trường hệ thống → file .env ở thư mục gốc → mặc định XAMPP.
 * KHÔNG commit thông tin nhạy cảm thật; file .env đã được .gitignore.
 */
declare(strict_types=1);

/* ---- Đọc file .env (parser đơn giản, không làm app crash nếu file thiếu/rác) ---- */
$envFile = __DIR__ . '/../.env';
$env = [];
if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = ltrim($line, "\xEF\xBB\xBF \t");
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key === '') {
                continue;
            }
            $quoted = false;
            if (strlen($value) >= 2) {
                $first = $value[0];
                if ($first === '"' || $first === "'") {
                    $end = strpos($value, $first, 1);
                    if ($end !== false) {
                        $value = substr($value, 1, $end - 1);
                        $quoted = true;
                    }
                }
            }
            if (!$quoted) {
                $hash = strpos($value, '#');
                if ($hash !== false) {
                    $value = rtrim(substr($value, 0, $hash));
                }
            }
            $env[$key] = $value;
        }
    }
}

/* ---- Lấy giá trị theo thứ tự ưu tiên, không dùng putenv() toàn cục ---- */
$cfg = static function (string $key, string $fallback) use ($env): string {
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    if (array_key_exists($key, $env)) {
        return $env[$key];
    }
    return $fallback;
};

$port = (int)$cfg('OF_DB_PORT', '3306');
if ($port < 1 || $port > 65535) {
    $port = 3306;
}

return [
    'host'    => $cfg('OF_DB_HOST', '127.0.0.1'),
    'port'    => $port,
    'dbname'  => $cfg('OF_DB_NAME', 'orderflow'),
    'user'    => $cfg('OF_DB_USER', 'root'),
    'pass'    => $cfg('OF_DB_PASS', ''),
    'charset' => 'utf8mb4',
];
