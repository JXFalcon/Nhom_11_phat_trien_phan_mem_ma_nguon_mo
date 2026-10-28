<?php
/**
 * Shopee – Bootstrap API: PDO, JSON helpers, CORS, CSRF, định tuyến cơ bản.
 * Mọi file API đều require file này.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---- CORS (cho Live Server khác cổng) ---- */
$allowed = [
    'http://localhost:5500',
    'http://127.0.0.1:5500',
    'http://localhost:8000',
    'http://127.0.0.1:8000',
    'http://localhost',
    'http://127.0.0.1',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Vary: Origin');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* ---- Chuyển mọi lỗi chưa bắt được thành JSON, không lộ chi tiết nội bộ ---- */
set_exception_handler(static function (Throwable $e): void {
    /* Ghi lỗi ẩn danh ra file để debug (không lộ chi tiết cho client) */
    $dir = __DIR__ . '/../logs';
    is_dir($dir) || @mkdir($dir, 0777, true);
    if (is_dir($dir)) {
        @file_put_contents(
            $dir . '/api_errors.log',
            date('c') . ' | ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL,
            FILE_APPEND
        );
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    } else {
        http_response_code(500);
    }
    echo json_encode(
        ['success' => false, 'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', 'errors' => []],
        JSON_UNESCAPED_UNICODE
    );
    exit;
});

/* ---- Chống CSRF cho thao tác thay đổi dữ liệu ----
 * Xác thực chính bằng Bearer token (không dựa vào cookie), nên tấn công CSRF
 * cổ điển không có tác dụng; ở đây kiểm tra thêm Origin/Referer để chặn
 * trình duyệt trang lạ gửi POST/PUT/PATCH/DELETE về API.
 */
$OF_METHOD = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($OF_METHOD, ['GET', 'HEAD', 'OPTIONS'], true)) {
    $ofOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $ofSelf = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    // 'null' = trang mở trực tiếp từ file:// (được hỗ trợ theo cấu hình api.js)
    if ($ofOrigin !== '' && $ofOrigin !== 'null'
        && $ofOrigin !== $ofSelf
        && !in_array($ofOrigin, $allowed, true)
    ) {
        json_fail('Yêu cầu bị chặn: nguồn gửi không hợp lệ.', 403);
    }
    if ($ofOrigin === '' && !empty($_SERVER['HTTP_REFERER'])) {
        $ofRefHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        $ofSelfHost = parse_url($ofSelf, PHP_URL_HOST);
        if ($ofRefHost !== null && $ofRefHost !== $ofSelfHost && !in_array($ofRefHost, ['localhost', '127.0.0.1'], true)) {
            json_fail('Yêu cầu bị chặn: nguồn gửi không hợp lệ.', 403);
        }
    }
}

/* ---- Kết nối PDO ---- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
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
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Không kết nối được cơ sở dữ liệu.']);
        exit;
    }
    return $pdo;
}

/* ---- JSON helpers ---- */
function json_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_ok(mixed $data = null, string $message = '', int $code = 200): void
{
    json_out(['success' => true, 'data' => $data, 'message' => $message], $code);
}

function json_fail(string $message, int $code = 400, array $errors = []): void
{
    json_out(['success' => false, 'message' => $message, 'errors' => $errors], $code);
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/* ---- CSRF (dùng khi đăng nhập qua session cookie) ---- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        json_fail('Token CSRF không hợp lệ. Vui lòng tải lại trang.', 419);
    }
}

/* ---- Xác thực qua bảng sessions (Bearer token) ---- */
function auth_user(): ?array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return null;
    }
    $token = $m[1];
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        return null;
    }
    $st = db()->prepare(
        'SELECT u.id, u.full_name, u.email, u.phone, u.role, u.status
         FROM sessions s JOIN users u ON u.id = s.user_id
         WHERE s.id = ? AND s.expires_at > NOW() AND u.status = 1'
    );
    $st->execute([$token]);
    $u = $st->fetch();
    return $u ?: null;
}

function require_auth(): array
{
    $u = auth_user();
    if (!$u) {
        json_fail('Vui lòng đăng nhập để tiếp tục.', 401);
    }
    return $u;
}

function require_role(string ...$roles): array
{
    $u = require_auth();
    if (!in_array($u['role'], $roles, true)) {
        json_fail('Bạn không có quyền thực hiện thao tác này.', 403);
    }
    return $u;
}

function is_admin(array $u): bool
{
    return ($u['role'] ?? '') === 'admin';
}

/* ---- Sinh mã định danh ---- */
function next_code(PDO $pdo, string $table, string $col, string $prefix, int $start = 1): string
{
    $pdo->prepare('INSERT IGNORE INTO code_sequences (name, value) VALUES (?, 0)')->execute([$table]);
    $curSt = $pdo->prepare('SELECT value FROM code_sequences WHERE name = ?');
    $curSt->execute([$table]);
    $current = (int)$curSt->fetchColumn();
    if ($current < $start) {
        $offset = strlen($prefix) + 1;
        $max = (int)$pdo->query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING($col, $offset) AS UNSIGNED)), 0) FROM `$table`"
        )->fetchColumn();
        $pdo->prepare('UPDATE code_sequences SET value = GREATEST(value, ?) WHERE name = ?')
            ->execute([$max, $table]);
    }
    $pdo->prepare('UPDATE code_sequences SET value = LAST_INSERT_ID(value + 1) WHERE name = ?')
        ->execute([$table]);
    $n = (int)$pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    if ($n < $start) {
        $n = $start;
    }
    return $prefix . $n;
}

/** Chuyển chuỗi ngày yyyy-mm-dd thành chuỗi SQL an toàn (hoặc null) */
function date_or_null(?string $s): ?string
{
    if (!$s) {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s ? $s : null;
}
