<?php
/**
 * /api/payments/qr_create.php
 * POST - Tạo một mã QR chuyển khoản mới cho giao dịch ngân hàng.
 *
 * Body: { payment_id } hoặc { order_id }
 * Mỗi lần gọi tạo một memo random mới -> ảnh QR khác nhau,
 * nhưng luôn trỏ về cùng tài khoản ngân hàng trong config/payment.local.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';
require_once __DIR__ . '/../../includes/payment_qr.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Chỉ chấp nhận POST.', 405);
}

$u = require_auth();
$in = request_json();
$paymentId = (int)($in['payment_id'] ?? 0);
$orderId = (int)($in['order_id'] ?? 0);
if ($paymentId <= 0 && $orderId <= 0) {
    json_fail('Thiếu payment_id hoặc order_id.', 422);
}

$pdo = db();
of_qr_expire_stale($pdo);

$pdo->beginTransaction();
try {
    if ($paymentId > 0) {
        $st = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
        $st->execute([$paymentId]);
    } else {
        $st = $pdo->prepare(
            "SELECT * FROM payments WHERE order_id = ? AND method = 'bank'
             ORDER BY id DESC LIMIT 1 FOR UPDATE"
        );
        $st->execute([$orderId]);
    }
    $pay = $st->fetch();
    if (!$pay) {
        throw new OrderFlowException('Không tìm thấy giao dịch thanh toán.', 404);
    }

    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
    $st->execute([(int)$pay['order_id']]);
    $order = $st->fetch();
    if (!$order) {
        throw new OrderFlowException('Không tìm thấy đơn hàng.', 404);
    }

    if ($u['role'] === 'customer' && (int)$order['user_id'] !== (int)$u['id']) {
        throw new OrderFlowException('Bạn không có quyền tạo QR cho đơn hàng này.', 403);
    }
    if ($pay['method'] !== 'bank') {
        throw new OrderFlowException('Đơn hàng này không dùng phương thức chuyển khoản.', 422);
    }
    if ($order['status'] !== 'cho_xac_nhan') {
        throw new OrderFlowException('Đơn hàng không còn ở trạng thái chờ xác nhận.', 409);
    }
    if ($pay['status'] !== 'cho_xu_ly') {
        throw new OrderFlowException('Giao dịch đã kết thúc, không thể tạo QR mới.', 409);
    }

    $cfg = of_payment_bank_config();
    $cfgErrors = of_payment_bank_errors($cfg);
    if ($cfgErrors) {
        throw new InvalidArgumentException(
            'Chưa cấu hình tài khoản nhận tiền (config/payment.local.php): ' . implode(' ', $cfgErrors)
        );
    }

    $amount = (float)$pay['amount'];
    if ($amount <= 0) {
        throw new OrderFlowException('Số tiền giao dịch không hợp lệ.', 422);
    }

    $ttl = (int)($cfg['qr_ttl_minutes'] ?? 15);
    if ($ttl < 1) {
        $ttl = 15;
    }
    $expSt = $pdo->prepare(
        'SELECT LEAST(DATE_ADD(NOW(), INTERVAL ? MINUTE),
                      COALESCE(reserved_until, DATE_ADD(NOW(), INTERVAL ? MINUTE))) AS expires_at,
                (reserved_until IS NOT NULL AND reserved_until < NOW()) AS is_expired
         FROM orders WHERE id = ?'
    );
    $expSt->execute([$ttl, $ttl, (int)$order['id']]);
    $exp = $expSt->fetch();
    if (!$exp || (int)$exp['is_expired'] === 1) {
        throw new OrderFlowException('Đơn hàng đã hết hạn giữ hàng, không thể tạo QR.', 409);
    }
    $expiresAt = (string)$exp['expires_at'];

    // Mã cũ chưa thanh toán không dùng nữa để tránh khách chuyển nhầm nội dung cũ.
    $pdo->prepare(
        "UPDATE payment_qr_sessions SET status = 'replaced'
         WHERE payment_id = ? AND status = 'pending'"
    )->execute([(int)$pay['id']]);

    $token = '';
    $memo = '';
    $imageUrl = '';
    $qrId = 0;
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = of_qr_random_token(6);
        $memo = of_qr_random_memo((string)$order['order_code'], 5);
        $imageUrl = of_qr_image_url($cfg, $amount, $memo);
        try {
            $pdo->prepare(
                'INSERT INTO payment_qr_sessions
                    (qr_token, payment_id, order_id, amount, memo, image_url, status, expires_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, "pending", ?, ?)'
            )->execute([
                $token, (int)$pay['id'], (int)$order['id'], $amount, $memo, $imageUrl,
                $expiresAt, (int)$u['id'],
            ]);
            $qrId = (int)$pdo->lastInsertId();
            break;
        } catch (PDOException $e) {
            if ((string)$e->getCode() !== '23000') {
                throw $e;
            }
        }
    }
    if ($qrId <= 0) {
        throw new RuntimeException('Không sinh được mã QR duy nhất.');
    }

    $pdo->commit();
} catch (OrderFlowException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_fail($e->getMessage(), $e->statusCode);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_fail('Không thể tạo mã QR thanh toán.', 500);
}

json_ok([
    'qr_id' => $qrId,
    'qr_token' => $token,
    'memo' => $memo,
    'amount' => $amount,
    'image_url' => $imageUrl,
    'expires_at' => $expiresAt,
    'payment_id' => (int)$pay['id'],
    'payment_code' => $pay['payment_code'],
    'order_id' => (int)$order['id'],
    'order_code' => $order['order_code'],
    'order_status' => $order['status'],
    'payment_status' => $pay['status'],
    'bank' => of_payment_bank_public($cfg),
], 'Đã tạo mã QR chuyển khoản mới.');
