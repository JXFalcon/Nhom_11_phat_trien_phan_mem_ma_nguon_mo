<?php
/** POST /api/auth/register.php – Đăng ký (chỉ tạo customer) */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Chỉ chấp nhận POST.', 405);
}
$user = auth_register(request_json());
json_ok($user, 'Đăng ký thành công. Hãy đăng nhập.', 201);
