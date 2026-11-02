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

// Tự xác nhận của khách ("Tôi đã chuyển khoản") mặc định TẮT.
// Chỉ bật lại khi demo nội bộ; production phải xác nhận qua webhook ngân hàng.
$customerConfirmRaw = getenv('OF_BANK_CUSTOMER_CONFIRM');
if ($customerConfirmRaw === false || $customerConfirmRaw === '') {
    $customerConfirmRaw = $local['customer_confirm'] ?? '0';
}
$customerConfirm = !in_array($customerConfirmRaw, [false, 0, '0', 'false', 'off'], true);

// Webhook ngân hàng / trung gian (SePay, Casso, ...) xác nhận tiền vào.
// Bắt buộc phải cấu hình; endpoint sẽ từ chối nếu để trống.
$webhookSecret = trim((string)(getenv('OF_BANK_WEBHOOK_SECRET') ?: ($local['webhook_secret'] ?? '')));
$webhookRequireHmac = getenv('OF_BANK_WEBHOOK_REQUIRE_HMAC');
if ($webhookRequireHmac === false || $webhookRequireHmac === '') {
    $webhookRequireHmac = $local['webhook_require_hmac'] ?? '0';
}
$webhookRequireHmac = in_array($webhookRequireHmac, [true, 1, '1', 'true', 'on'], true);

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
    'webhook_secret' => $webhookSecret,
    'webhook_require_hmac' => $webhookRequireHmac,
];
