<?php
/** POST /api/auth/login.php – Đăng nhập */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Chỉ chấp nhận POST.', 405);
}
$in = request_json();
$email = (string)($in['email'] ?? '');
$pass = (string)($in['password'] ?? '');
if ($email === '' || $pass === '') {
    json_fail('Vui lòng nhập email và mật khẩu.', 422);
}
json_ok(auth_login($email, $pass), 'Đăng nhập thành công.');
