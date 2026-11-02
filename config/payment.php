<?php
/**
 * Shopee - Cấu hình nhận tiền chuyển khoản / QR VietQR.
 *
 * Thứ tự ưu tiên:
 *   1. Biến môi trường OF_BANK_*
 *   2. config/payment.local.php (không commit)
 *
 * BIN một số ngân hàng phổ biến (mã NAPAS 6 số):
 *   Vietcombank 970436 | Techcombank 970407 | MB Bank 970422
 *   BIDV 970418 | VietinBank 970415 | Agribank 970405
 *   ACB 970416 | Sacombank 970403 | VPBank 970432 | TPBank 970423
 */
declare(strict_types=1);

$local = [];
$localFile = __DIR__ . '/payment.local.php';
if (is_file($localFile)) {
    $loaded = require $localFile;
    $local = is_array($loaded) ? $loaded : [];
}

// DEMO: cho phép khách tự xác nhận "Tôi đã chuyển khoản" để ghi nhận ngay,
// không cần admin bấm. Production nên đặt false và dùng webhook ngân hàng.
$customerConfirmRaw = getenv('OF_BANK_CUSTOMER_CONFIRM');
if ($customerConfirmRaw === false || $customerConfirmRaw === '') {
    $customerConfirmRaw = $local['customer_confirm'] ?? '1';
}
$customerConfirm = !in_array($customerConfirmRaw, [false, 0, '0', 'false', 'off'], true);

return [
    'provider'       => 'vietqr',
    'bank_bin'       => getenv('OF_BANK_BIN') ?: ($local['bank_bin'] ?? ''),
    'bank_name'      => getenv('OF_BANK_NAME') ?: ($local['bank_name'] ?? ''),
    'account_number' => getenv('OF_BANK_ACCOUNT') ?: ($local['account_number'] ?? ''),
    'account_holder' => getenv('OF_BANK_HOLDER') ?: ($local['account_holder'] ?? ''),
    'qr_template'    => getenv('OF_BANK_QR_TEMPLATE') ?: ($local['qr_template'] ?? 'compact2'),
    'memo_prefix'    => getenv('OF_BANK_MEMO_PREFIX') ?: ($local['memo_prefix'] ?? 'DH'),
    'qr_ttl_minutes' => (int)(getenv('OF_BANK_QR_TTL') ?: ($local['qr_ttl_minutes'] ?? 15)),
    'customer_confirm' => $customerConfirm,
];
