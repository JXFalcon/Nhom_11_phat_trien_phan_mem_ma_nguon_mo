<?php
/**
 * Sao chép file này thành config/payment.local.php rồi điền thông tin thật.
 * File payment.local.php đã được .gitignore (không đưa tài khoản lên git).
 */
declare(strict_types=1);

return [
    'bank_bin'       => '970436',          // Mã BIN NAPAS, 6 chữ số
    'bank_name'      => 'Vietcombank',     // Tên hiển thị
    'account_number' => '0123456789',      // Số tài khoản nhận tiền
    'account_holder' => 'NGUYEN VAN A',    // Tên chủ tài khoản (không dấu, in hoa)
    'qr_template'    => 'compact2',        // compact | compact2 | qr_only
    'memo_prefix'    => 'DH',
    'qr_ttl_minutes' => 15,
    // true = demo: khách tự bấm "Tôi đã chuyển khoản" để xác nhận ngay.
    // Production nên đặt false và dùng webhook ngân hàng.
    'customer_confirm' => true,
];
