<?php
/** POST /api/auth/logout.php – Đăng xuất */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Chỉ chấp nhận POST.', 405);
}
auth_logout();
json_ok(null, 'Đã đăng xuất.');
