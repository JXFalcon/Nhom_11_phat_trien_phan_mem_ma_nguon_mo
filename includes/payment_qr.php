<?php
/**
 * Shopee - Sinh QR chuyển khoản VietQR (NAPAS 247).
 *
 * Mỗi mã QR dùng cùng một tài khoản ngân hàng của cửa hàng nhưng có
 * nội dung chuyển khoản random, nên ảnh QR khác nhau ở mỗi lần tạo.
 * Số tiền luôn lấy từ bản ghi payments phía server, không tin client.
 */
declare(strict_types=1);

/** Đọc cấu hình ngân hàng (config/payment.php + payment.local.php + env). */
function of_payment_bank_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $loaded = require __DIR__ . '/../config/payment.php';
        $cfg = is_array($loaded) ? $loaded : [];
    }
    return $cfg;
}

/** Danh sách lỗi cấu hình ngân hàng (rỗng = đã cấu hình đúng). */
function of_payment_bank_errors(array $cfg): array
{
    $errors = [];
    $bin = trim((string)($cfg['bank_bin'] ?? ''));
    $name = trim((string)($cfg['bank_name'] ?? ''));
    $acc = trim((string)($cfg['account_number'] ?? ''));
    $holder = trim((string)($cfg['account_holder'] ?? ''));

    if (!preg_match('/^\d{6}$/', $bin)) {
        $errors[] = 'Mã BIN ngân hàng phải gồm 6 chữ số.';
    }
    if ($name === '') {
        $errors[] = 'Thiếu tên ngân hàng.';
    }
    if (!preg_match('/^[A-Za-z0-9]{4,25}$/', $acc)) {
        $errors[] = 'Số tài khoản chỉ gồm chữ/số, dài 4-25 ký tự.';
    }
    if ($holder === '') {
        $errors[] = 'Thiếu tên chủ tài khoản.';
    }
    return $errors;
}

function of_payment_bank_ready(array $cfg): bool
{
    return of_payment_bank_errors($cfg) === [];
}

/** Thông tin ngân hàng an toàn để trả về client (đều là thông tin cần để chuyển tiền). */
function of_payment_bank_public(array $cfg): array
{
    return [
        'bin' => trim((string)($cfg['bank_bin'] ?? '')),
        'name' => trim((string)($cfg['bank_name'] ?? '')),
        'account_number' => trim((string)($cfg['account_number'] ?? '')),
        'account_holder' => trim((string)($cfg['account_holder'] ?? '')),
    ];
}

/** Sinh token ngẫu nhiên dễ đọc trên sao kê ngân hàng. */
function of_qr_random_token(int $length = 5): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/** Nội dung chuyển khoản: mã đơn + token random (tối đa 50 ký tự). */
function of_qr_random_memo(string $orderCode, int $tokenLength = 5): string
{
    $base = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $orderCode) ?? '');
    if ($base === '') {
        $base = 'DONHANG';
    }
    $memo = substr($base, 0, 50 - $tokenLength - 1) . ' ' . of_qr_random_token($tokenLength);
    return $memo;
}

/** Chuyển tên chủ tài khoản tiếng Việt sang ASCII (QR chỉ nên chứa ký tự không dấu). */
function of_qr_ascii_name(string $name): string
{
    $map = [
        'a' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'á' => 'a', 'ạ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ắ' => 'a', 'ặ' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ấ' => 'a', 'ậ' => 'a',
        'e' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'é' => 'e', 'ẹ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ế' => 'e', 'ệ' => 'e',
        'i' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'í' => 'i', 'ị' => 'i',
        'o' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ó' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ố' => 'o', 'ộ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ớ' => 'o', 'ợ' => 'o',
        'u' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ú' => 'u', 'ụ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ứ' => 'u', 'ự' => 'u',
        'y' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ý' => 'y', 'ỵ' => 'y',
        'd' => 'd', 'đ' => 'd',
    ];
    $lower = mb_strtolower($name, 'UTF-8');
    $converted = strtr($lower, $map);
    $converted = preg_replace('/[^a-z0-9 .-]/', '', $converted) ?? '';
    return strtoupper(trim(preg_replace('/\s+/', ' ', $converted) ?? ''));
}

/** URL ảnh QR động theo chuẩn VietQR (img.vietqr.io). */
function of_qr_image_url(array $cfg, float|int $amount, string $memo): string
{
    $errors = of_payment_bank_errors($cfg);
    if ($errors) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }
    $bin = trim((string)$cfg['bank_bin']);
    $acc = trim((string)$cfg['account_number']);
    $holder = trim((string)($cfg['account_holder'] ?? ''));
    $template = (string)($cfg['qr_template'] ?? 'compact2');
    if (!in_array($template, ['compact', 'compact2', 'qr_only'], true)) {
        $template = 'compact2';
    }
    $amount = (int)round($amount);
    if ($amount < 1) {
        throw new InvalidArgumentException('Số tiền QR không hợp lệ.');
    }
    $query = http_build_query([
        'amount' => $amount,
        'addInfo' => $memo,
        'accountName' => of_qr_ascii_name($holder),
    ], '', '&', PHP_QUERY_RFC3986);
    return 'https://img.vietqr.io/image/' . rawurlencode($bin) . '-' . rawurlencode($acc) . '-' . $template . '.png?' . $query;
}

/** Bảng QR đã được migrate chưa (tránh lỗi với DB cũ chưa chạy migration). */
function of_qr_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists === null) {
        try {
            $exists = (bool)$pdo->query("SHOW TABLES LIKE 'payment_qr_sessions'")->fetch();
        } catch (Throwable $e) {
            $exists = false;
        }
    }
    return $exists;
}

/** Đánh dấu các QR đã hết hạn theo thời gian. */
function of_qr_expire_stale(PDO $pdo): int
{
    if (!of_qr_table_exists($pdo)) {
        return 0;
    }
    $st = $pdo->prepare(
        "UPDATE payment_qr_sessions SET status = 'expired'
         WHERE status = 'pending' AND expires_at < NOW()"
    );
    $st->execute();
    return $st->rowCount();
}

/** Đánh dấu QR của một payment là đã thanh toán. */
function of_qr_mark_paid(PDO $pdo, int $paymentId): int
{
    if (!of_qr_table_exists($pdo)) {
        return 0;
    }
    $st = $pdo->prepare(
        "UPDATE payment_qr_sessions SET status = 'paid'
         WHERE payment_id = ? AND status IN ('pending', 'replaced')"
    );
    $st->execute([$paymentId]);
    return $st->rowCount();
}

/** Đánh dấu QR của một đơn là hết hạn/hủy. */
function of_qr_expire_order(PDO $pdo, int $orderId): int
{
    if (!of_qr_table_exists($pdo)) {
        return 0;
    }
    $st = $pdo->prepare(
        "UPDATE payment_qr_sessions SET status = 'expired'
         WHERE order_id = ? AND status = 'pending'"
    );
    $st->execute([$orderId]);
    return $st->rowCount();
}

/* ============================================================
 * Webhook ngân hàng – chuẩn hoá dữ liệu & đối chiếu QR
 * ============================================================ */

/** Ghi log webhook (không chứa secret) để đối chiếu khi tích hợp ngân hàng. */
function of_webhook_log(string $outcome, array $context = []): void
{
    $dir = __DIR__ . '/../logs';
    is_dir($dir) || @mkdir($dir, 0777, true);
    if (!is_dir($dir)) {
        return;
    }
    $safe = [];
    foreach ($context as $k => $v) {
        if (is_scalar($v) || $v === null) {
            $safe[$k] = $v;
        }
    }
    @file_put_contents(
        $dir . '/bank_webhook.log',
        date('c') . ' | ' . $outcome . ' | ' . json_encode($safe, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
}

/** Chuẩn hoá nội dung chuyển khoản để so khớp với memo QR. */
function of_webhook_norm(string $value): string
{
    return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($value, 'UTF-8')) ?? '';
}

/** Lấy chuỗi đầu tiên không rỗng trong danh sách khóa. */
function of_webhook_pick(array $data, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($data[$key]) && is_scalar($data[$key])) {
            $value = trim((string)$data[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }
    return '';
}

/**
 * Chuẩn hoá payload từ nhiều nhà cung cấp (SePay, Casso, generic).
 * Trả về mảng gồm memo, amount, txn, type, account, gateway.
 */
function of_webhook_normalize(array $data): array
{
    // Một số cổng gửi thẳng danh sách giao dịch.
    if (array_is_list($data)) {
        $data = $data[0] ?? [];
    }
    // Casso bọc giao dịch trong data (object hoặc list).
    $root = $data;
    if (isset($data['data']) && is_array($data['data'])) {
        $inner = $data['data'];
        if (array_is_list($inner)) {
            $inner = $inner[0] ?? [];
        }
        if (is_array($inner)) {
            $root = array_merge($data, $inner);
        }
    }
    // Một số cổng bọc trong transaction.
    if (isset($root['transaction']) && is_array($root['transaction'])) {
        $root = array_merge($root, $root['transaction']);
    }

    $memo = of_webhook_pick($root, [
        'content', 'description', 'transaction_content', 'code', 'memo',
        'addInfo', 'add_info', 'transfer_content',
    ]);
    $amountRaw = of_webhook_pick($root, [
        'transferAmount', 'transfer_amount', 'amount', 'transaction_amount', 'money',
    ]);
    $txn = of_webhook_pick($root, [
        'referenceCode', 'reference_code', 'transaction_id', 'transactionId',
        'tid', 'id', 'trans_id',
    ]);
    $type = of_webhook_pick($root, ['transferType', 'transfer_type', 'type', 'direction']);
    $account = of_webhook_pick($root, ['accountNumber', 'account_number', 'bank_sub_acc_id', 'sub_account']);
    $gateway = of_webhook_pick($root, ['gateway', 'bank', 'bank_name', 'bank_brand_name']);

    return [
        'memo' => $memo,
        'amount' => (float)str_replace([',', ' '], '', $amountRaw),
        'txn' => $txn,
        'type' => strtolower($type),
        'account' => preg_replace('/\D/', '', $account) ?? '',
        'gateway' => $gateway,
    ];
}

/** Kiểm tra giao dịch có phải tiền vào (bỏ qua giao dịch tiền ra). */
function of_webhook_is_incoming(string $type): bool
{
    if ($type === '') {
        return true;
    }
    return in_array($type, ['in', 'credit', 'c', 'deposit', 'receive', 'incoming'], true);
}

/**
 * Tìm phiên QR khớp với nội dung chuyển khoản và số tiền.
 * Ưu tiên memo dài nhất để tránh khớp nhầm khi nội dung ngân hàng chứa nhiều mã.
 */
function of_webhook_find_qr(PDO $pdo, string $memo, float $amount, int $qrId = 0, string $token = ''): ?array
{
    if (!of_qr_table_exists($pdo)) {
        return null;
    }
    if ($qrId > 0) {
        $st = $pdo->prepare('SELECT * FROM payment_qr_sessions WHERE id = ? LIMIT 1');
        $st->execute([$qrId]);
        return $st->fetch() ?: null;
    }
    if ($token !== '') {
        $st = $pdo->prepare('SELECT * FROM payment_qr_sessions WHERE qr_token = ? LIMIT 1');
        $st->execute([$token]);
        return $st->fetch() ?: null;
    }

    $normContent = of_webhook_norm($memo);
    if ($normContent === '') {
        return null;
    }
    $st = $pdo->query(
        'SELECT * FROM payment_qr_sessions
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
         ORDER BY id DESC LIMIT 300'
    );
    $best = null;
    $bestLen = 0;
    foreach ($st->fetchAll() as $row) {
        $normMemo = of_webhook_norm((string)$row['memo']);
        if ($normMemo === '' || !str_contains($normContent, $normMemo)) {
            continue;
        }
        if ($amount > 0 && abs((float)$row['amount'] - $amount) > 0.009) {
            continue;
        }
        $len = strlen($normMemo);
        if ($len > $bestLen) {
            $best = $row;
            $bestLen = $len;
        }
    }
    return $best;
}
