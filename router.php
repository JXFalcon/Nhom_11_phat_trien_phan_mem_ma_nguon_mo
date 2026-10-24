<?php
/**
 * Router cho PHP built-in server (php -S 127.0.0.1:8080 router.php).
 * – /api/* → chạy file PHP corresponding (xử lý query string).
 * – Còn lại → serve file tĩnh nếu tồn tại, nếu không trả index.html.
 */
declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
    if (str_ends_with($file, '.php')) {
        // để PHP tự chạy
        return false;
    }
    // MIME tĩnh
    $types = [
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'ico' => 'image/x-icon',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    readfile($file);
    return true;
}

if (str_starts_with($uri, '/api/')) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'API không tồn tại: ' . $uri], JSON_UNESCAPED_UNICODE);
    return true;
}

// SPA-ish fallback
return false;
