<?php
/**
 * Shopee – Hàm nghiệp vụ xác thực dùng chung.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** Đăng nhập: verify password → tạo token phiên → trả về user + token */
function auth_login(string $email, string $password): array
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([mb_strtolower(trim($email))]);
    $u = $st->fetch();
    if (!$u || (int)$u['status'] !== 1) {
        json_fail('Email hoặc mật khẩu không đúng.', 401);
    }
    if (!password_verify($password, $u['password_hash'])) {
        json_fail('Email hoặc mật khẩu không đúng.', 401);
    }
    // Tăng dần chi phí nếu hash cũ (password_needs_rehash)
    if (password_needs_rehash($u['password_hash'], PASSWORD_BCRYPT)) {
        $up = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $up->execute([password_hash($password, PASSWORD_BCRYPT), $u['id']]);
    }
    $token = bin2hex(random_bytes(32));
    $ins = $pdo->prepare('INSERT INTO sessions (id, user_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))');
    $ins->execute([$token, $u['id']]);
    return [
        'token' => $token,
        'user' => [
            'id' => (int)$u['id'],
            'full_name' => $u['full_name'],
            'email' => $u['email'],
            'phone' => $u['phone'],
            'role' => $u['role'],
        ],
    ];
}

/** Đăng ký: chỉ được tạo tài khoản customer */
function auth_register(array $input): array
{
    $name = trim((string)($input['full_name'] ?? ''));
    $email = mb_strtolower(trim((string)($input['email'] ?? '')));
    $phone = trim((string)($input['phone'] ?? ''));
    $pass = (string)($input['password'] ?? '');
    $pass2 = (string)($input['password_confirm'] ?? $input['confirm_password'] ?? $input['password_confirmation'] ?? '');

    $errors = [];
    if (mb_strlen($name) < 2) {
        $errors['full_name'] = 'Họ tên phải có ít nhất 2 ký tự.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ.';
    }
    if (!preg_match('/^(0|\+84)[0-9]{9,10}$/', preg_replace('/\s/', '', $phone))) {
        $errors['phone'] = 'Số điện thoại không hợp lệ.';
    }
    if (strlen($pass) < 6) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    if ($pass !== $pass2) {
        $errors['password_confirm'] = 'Mật khẩu xác nhận không khớp.';
    }
    if ($errors) {
        json_fail('Dữ liệu đăng ký không hợp lệ.', 422, $errors);
    }
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    if ($st->fetch()) {
        json_fail('Email này đã được đăng ký.', 409, ['email' => 'Email đã tồn tại.']);
    }
    // Không bao giờ cho phép đăng ký admin từ API công khai
    $ins = $pdo->prepare(
        "INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'customer')"
    );
    $ins->execute([$name, $email, preg_replace('/\s/', '', $phone), password_hash($pass, PASSWORD_BCRYPT)]);
    return [
        'id' => (int)$pdo->lastInsertId(),
        'full_name' => $name,
        'email' => $email,
        'role' => 'customer',
    ];
}

/** Đăng xuất: xóa phiên hiện tại */
function auth_logout(): void
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        db()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$m[1]]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
    }
    session_destroy();
}
