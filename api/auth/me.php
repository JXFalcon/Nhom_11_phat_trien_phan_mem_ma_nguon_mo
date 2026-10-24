<?php
/** GET /api/auth/me.php – Thông tin tài khoản đang đăng nhập */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$u = require_auth();
json_ok([
    'id' => (int)$u['id'],
    'full_name' => $u['full_name'],
    'email' => $u['email'],
    'phone' => $u['phone'],
    'role' => $u['role'],
]);
