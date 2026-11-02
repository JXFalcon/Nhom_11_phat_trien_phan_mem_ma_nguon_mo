<?php
/**
 * Kiểm thử sinh QR chuyển khoản (không cần database).
 * Chạy: php tests/payment_qr_test.php
 */
declare(strict_types=1);
require __DIR__ . '/helper.php';
require __DIR__ . '/../includes/payment_qr.php';

$cfg = [
    'bank_bin' => '970436',
    'bank_name' => 'Vietcombank',
    'account_number' => '0123456789',
    'account_holder' => 'NGUYEN VAN A',
    'qr_template' => 'compact2',
    'qr_ttl_minutes' => 15,
];

same(of_payment_bank_errors($cfg), [], 'config-hop-le');
same(of_payment_bank_ready($cfg), true, 'config-san-sang');

$badBin = $cfg;
$badBin['bank_bin'] = '123';
same(of_payment_bank_errors($badBin) !== [], true, 'bin-sai-bi-tu-choi');

$badAcc = $cfg;
$badAcc['account_number'] = '12 34';
same(of_payment_bank_errors($badAcc) !== [], true, 'so-tai-khoan-sai-bi-tu-choi');

$missingName = $cfg;
$missingName['bank_name'] = '';
same(of_payment_bank_errors($missingName) !== [], true, 'thieu-ten-ngan-hang');

$memo = of_qr_random_memo('DH-1234', 5);
same(str_starts_with($memo, 'DH-1234 '), true, 'memo-bat-dau-bang-ma-don');
same(strlen($memo) <= 50, true, 'memo-khong-qua-dai');

$memos = [];
for ($i = 0; $i < 20; $i++) {
    $memos[] = of_qr_random_memo('DH-1234', 5);
}
same(count(array_unique($memos)) > 1, true, 'memo-ngau-nhien-khac-nhau');

$url = of_qr_image_url($cfg, 1250000, $memo);
same(str_contains($url, 'img.vietqr.io/image/970436-0123456789-compact2.png'), true, 'url-dung-bin-va-tai-khoan');
same(str_contains($url, 'amount=1250000'), true, 'url-dung-so-tien');
same(str_contains($url, 'addInfo=' . rawurlencode($memo)), true, 'url-dung-noi-dung-chuyen-khoan');
same(str_contains($url, 'accountName=NGUYEN%20VAN%20A'), true, 'url-dung-ten-chu-tai-khoan');
same(of_qr_ascii_name('Nguyễn Văn Á'), 'NGUYEN VAN A', 'ten-tieng-viet-chuyen-ascii');

$cfgAccent = $cfg;
$cfgAccent['account_holder'] = 'Nguyễn Văn Á';
$urlAccent = of_qr_image_url($cfgAccent, 1000, 'DH-1 TEST');
same(str_contains($urlAccent, 'accountName=NGUYEN%20VAN%20A'), true, 'url-voi-ten-co-dau-van-dung');

$url2 = of_qr_image_url($cfg, 1250000, of_qr_random_memo('DH-1234', 5));
same($url !== $url2, true, 'moi-lan-tao-qr-khac-nhau');

same(of_qr_image_url($cfg, 1000.4, 'DH-1 TEST'), of_qr_image_url($cfg, 1000, 'DH-1 TEST'), 'so-tien-lam-tron');

$threw = false;
try {
    of_qr_image_url($badBin, 1000, 'DH-1 TEST');
} catch (InvalidArgumentException $e) {
    $threw = true;
}
same($threw, true, 'thieu-cau-hinh-thi-bao-loi');

finish();
