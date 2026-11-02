<?php
/**
 * CẤU HÌNH CÁ NHÂN - không commit (đã nằm trong .gitignore).
 * Điền tài khoản ngân hàng nhận tiền thật của bạn vào đây.
 * Mẫu tham khảo: config/payment.local.example.php
 */
declare(strict_types=1);

return [
    'bank_bin'       => '970432',           // Mã BIN chuẩn của VPBank
    'bank_name'      => 'VPBank',          // Tên ngân hàng
    'account_number' => '0392103632',        // Số tài khoản của bạn
    'account_holder' => 'NGUYEN QUANG KHANH',// Tên chủ tài khoản viết hoa không dấu
    'qr_template'    => 'compact2',
    'memo_prefix'    => 'DH',
    'qr_ttl_minutes' => 15,
];