<?php
/**
 * /api/payments/bank_webhook.php
 * POST - Webhook nhận thông báo chuyển khoản từ ngân hàng / trung gian
 *        (SePay, Casso, cổng ngân hàng hoặc hệ thống đối soát nội bộ).
 *
 * Đây là nguồn xác nhận DUY NHẤT: máy chủ chỉ đánh dấu thanh toán thành công
 * khi nhận được thông báo tiền vào khớp đúng nội dung (memo) và số tiền.
 *
 * Xác thực hỗ trợ:
 *   - Authorization: Bearer <webhook_secret>
 *   - X-Webhook-Secret / X-Secure-Token / X-Api-Key: <webhook_secret>
 *   - ?token=<webhook_secret> (tiện cho môi trường demo)
 *   - X-Signature: HMAC-SHA256(raw body, webhook_secret) [không bắt buộc]
 *
 * Payload linh hoạt: content/description/code, transferAmount/amount,
 * referenceCode/transaction_id/tid/id, transferType...
 * Nếu có qr_id hoặc qr_token (môi trường test) sẽ khớp trực tiếp.
 */
declare(strict_types=1);

/* Webhook đến từ máy chủ ngân hàng nên bỏ qua kiểm tra Origin/Referer. */
if (!defined('OF_SKIP_ORIGIN_CHECK')) {
    define('OF_SKIP_ORIGIN_CHECK', true);
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';
require_once __DIR__ . '/../../includes/payment_qr.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Chỉ chấp nhận POST.', 405);
}

$cfg = of_payment_bank_config();
$secret = trim((string)($cfg['webhook_secret'] ?? ''));
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if ($secret === '') {
    of_webhook_log('config_missing', ['ip' => $ip]);
    json_fail('Webhook chưa được cấu hình. Đặt OF_BANK_WEBHOOK_SECRET hoặc webhook_secret.', 503);
}

$raw = file_get_contents('php://input');
$raw = is_string($raw) ? $raw : '';

$authHeader = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$provided = '';
if (preg_match('/^(?:Bearer|Apikey|ApiKey)\s+(.+)$/i', $authHeader, $m)) {
    $provided = trim($m[1]);
}
if ($provided === '') {
    foreach (['HTTP_X_WEBHOOK_SECRET', 'HTTP_X_SECURE_TOKEN', 'HTTP_SECURE_TOKEN', 'HTTP_X_API_KEY'] as $header) {
        if (!empty($_SERVER[$header])) {
            $provided = trim((string)$_SERVER[$header]);
            break;
        }
    }
}
$queryToken = trim((string)($_GET['token'] ?? $_GET['secret'] ?? ''));
$signature = trim((string)($_SERVER['HTTP_X_SIGNATURE'] ?? ''));
$signature = preg_replace('/^sha256=/i', '', $signature) ?? '';

$authorized = false;
if ($provided !== '' && hash_equals($secret, $provided)) {
    $authorized = true;
} elseif ($queryToken !== '' && hash_equals($secret, $queryToken)) {
    $authorized = true;
} elseif ($signature !== '') {
    $expected = hash_hmac('sha256', $raw, $secret);
    $authorized = hash_equals($expected, strtolower($signature));
}
if (!$authorized) {
    of_webhook_log('unauthorized', ['ip' => $ip]);
    json_fail('Xác thực webhook thất bại.', 401);
}
if (!empty($cfg['webhook_require_hmac']) && $signature === '') {
    of_webhook_log('missing_signature', ['ip' => $ip]);
    json_fail('Webhook yêu cầu chữ ký X-Signature (HMAC-SHA256).', 401);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    $parsed = [];
    parse_str($raw, $parsed);
    $data = is_array($parsed) ? $parsed : [];
}
if (!$data) {
    of_webhook_log('empty_payload', ['ip' => $ip]);
    json_fail('Payload webhook rỗng hoặc không hợp lệ.', 422);
}
if (isset($data['error']) && (int)$data['error'] !== 0) {
    of_webhook_log('provider_error', ['ip' => $ip, 'error' => $data['error']]);
    json_ok(['received' => true, 'ignored' => true, 'reason' => 'provider_error'], 'Đã nhận thông báo lỗi từ cổng thanh toán.');
}

$w = of_webhook_normalize($data);
$ctx = [
    'ip' => $ip,
    'memo' => $w['memo'],
    'amount' => $w['amount'],
    'txn' => $w['txn'],
    'type' => $w['type'],
    'gateway' => $w['gateway'],
];

if (!of_webhook_is_incoming($w['type'])) {
    of_webhook_log('ignored_outgoing', $ctx);
    json_ok(['received' => true, 'ignored' => true, 'reason' => 'not_incoming'], 'Bỏ qua giao dịch tiền ra.');
}
if ($w['amount'] <= 0 || $w['memo'] === '') {
    of_webhook_log('invalid_payload', $ctx);
    json_fail('Payload webhook thiếu nội dung chuyển khoản hoặc số tiền.', 422);
}

$cfgAccount = preg_replace('/\D/', '', (string)($cfg['account_number'] ?? '')) ?? '';
if ($cfgAccount !== '' && $w['account'] !== '' && $w['account'] !== $cfgAccount) {
    of_webhook_log('account_mismatch', ['ip' => $ip, 'account' => $w['account']]);
    json_ok(['received' => true, 'ignored' => true, 'reason' => 'account_mismatch'], 'Bỏ qua giao dịch không thuộc tài khoản nhận tiền.');
}

$pdo = db();
$qr = of_webhook_find_qr(
    $pdo,
    $w['memo'],
    $w['amount'],
    (int)($data['qr_id'] ?? 0),
    trim((string)($data['qr_token'] ?? ''))
);
if (!$qr) {
    of_webhook_log('qr_not_found', $ctx);
    json_ok(['received' => true, 'ignored' => true, 'reason' => 'qr_not_found'], 'Không tìm thấy mã QR khớp nội dung chuyển khoản.');
}

$ctx['qr_id'] = (int)$qr['id'];
$ctx['payment_id'] = (int)$qr['payment_id'];

$txnKey = preg_replace('/[^A-Za-z0-9_-]/', '', $w['txn']) ?? '';
$idem = $txnKey !== ''
    ? 'BANK-' . substr($txnKey, 0, 58)
    : 'BANK-' . substr(of_webhook_norm($w['memo']), 0, 40);
$ctx['idempotency_key'] = $idem;

$pdo->beginTransaction();
try {
    $st = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
    $st->execute([(int)$qr['payment_id']]);
    $pay = $st->fetch();
    if (!$pay) {
        throw new OrderFlowException('Không tìm thấy giao dịch thanh toán.', 404);
    }
    $order = of_lock_order($pdo, (int)$pay['order_id']);
    if ($pay['method'] !== 'bank') {
        $pdo->commit();
        of_webhook_log('not_bank_payment', $ctx);
        json_ok(['received' => true, 'ignored' => true, 'reason' => 'not_bank_payment'], 'Giao dịch không phải chuyển khoản ngân hàng.');
    }

    // Gửi lặp cùng giao dịch ngân hàng -> không xử lý thêm.
    $existing = of_payment_event_lookup($pdo, $idem);
    if ($existing && (int)$existing['payment_id'] !== (int)$pay['id']) {
        $pdo->commit();
        of_webhook_log('idempotency_conflict', $ctx);
        json_fail('Mã giao dịch ngân hàng đã thuộc giao dịch khác.', 409);
    }
    if ($existing) {
        $pdo->prepare("UPDATE payment_qr_sessions SET status = 'paid' WHERE payment_id = ? AND status IN ('pending','replaced')")
            ->execute([(int)$pay['id']]);
        $pdo->commit();
        of_webhook_log('duplicate_replay', $ctx);
        json_ok([
            'received' => true,
            'duplicate' => true,
            'payment_id' => (int)$pay['id'],
            'payment_status' => $pay['status'],
            'order_id' => (int)$order['id'],
            'order_status' => $order['status'],
        ], 'Thông báo chuyển khoản đã được xử lý trước đó.');
    }

    if (in_array($pay['status'], ['thanh_cong', 'hoan_tien'], true)) {
        of_qr_mark_paid($pdo, (int)$pay['id']);
        of_payment_event(
            $pdo, (int)$pay['id'], (int)$order['id'], $idem,
            'thanh_cong', 'duplicate_replay',
            'Webhook lặp sau khi giao dịch đã kết thúc', null
        );
        $pdo->commit();
        of_webhook_log('already_settled', $ctx);
        json_ok([
            'received' => true,
            'duplicate' => true,
            'already_settled' => true,
            'payment_id' => (int)$pay['id'],
            'payment_status' => $pay['status'],
            'order_id' => (int)$order['id'],
            'order_status' => $order['status'],
        ], 'Giao dịch đã ở trạng thái "' . $pay['status'] . '", bỏ qua để tránh xử lý trùng.');
    }

    if (abs((float)$pay['amount'] - $w['amount']) > 0.009) {
        $pdo->commit();
        of_webhook_log('amount_mismatch', $ctx);
        json_ok([
            'received' => true,
            'ignored' => true,
            'reason' => 'amount_mismatch',
            'expected' => (float)$pay['amount'],
            'received_amount' => $w['amount'],
        ], 'Số tiền chuyển khoản không khớp giao dịch, chưa xác nhận.');
    }

    // Chỉ đặt idempotency_key nếu chưa thuộc giao dịch khác (ràng buộc UNIQUE).
    $newKey = null;
    $conflict = $pdo->prepare('SELECT id FROM payments WHERE idempotency_key = ? AND id <> ? LIMIT 1');
    $conflict->execute([$idem, (int)$pay['id']]);
    if (!$conflict->fetch()) {
        $newKey = $idem;
    }

    $pdo->prepare(
        'UPDATE payments
         SET status = ?, idempotency_key = COALESCE(idempotency_key, ?),
             processed_at = NOW(), note = ?
         WHERE id = ?'
    )->execute([
        'thanh_cong',
        $newKey,
        mb_substr('Webhook ngân hàng xác nhận tiền vào' . ($w['gateway'] !== '' ? ' (' . $w['gateway'] . ')' : ''), 0, 255),
        (int)$pay['id'],
    ]);
    of_qr_mark_paid($pdo, (int)$pay['id']);

    $orderStatus = (string)$order['status'];
    $orderExpired = false;
    $resConfirmed = 0;
    $resReleased = 0;
    $refund = ['amount' => 0.0, 'id' => null];

    if ($orderStatus === 'cho_xac_nhan' && $order['reserved_until'] !== null) {
        $expSt = $pdo->prepare('SELECT (reserved_until < NOW()) FROM orders WHERE id = ?');
        $expSt->execute([(int)$order['id']]);
        $orderExpired = (int)$expSt->fetchColumn() === 1;
    }

    if ($orderStatus === 'da_huy' || $orderExpired) {
        // Tiền đến sau khi đơn đã hủy/hết hạn: không hồi sinh đơn, hoàn tiền.
        $orderExpired = true;
        if ($orderStatus === 'cho_xac_nhan') {
            $restore = of_restore_order_inventory(
                $pdo, (int)$order['id'], null,
                'Hoàn kho khi webhook thanh toán đến sau hạn ' . $order['order_code'],
                'expired'
            );
            $resReleased = (int)$restore['pending'];
            $pdo->prepare(
                'UPDATE orders SET status = "da_huy", cancel_reason = ? WHERE id = ?'
            )->execute(['Thanh toán đến sau khi hết hạn giữ hàng', (int)$order['id']]);
            $pdo->prepare(
                "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
                 VALUES (?, 'cho_xac_nhan', 'da_huy', ?, NULL)"
            )->execute([
                (int)$order['id'],
                'Webhook thanh toán đến sau khi hết hạn; không hồi sinh đơn, hoàn tiền tự động.',
            ]);
            $orderStatus = 'da_huy';
        }
        $refund = of_refund_order(
            $pdo, (int)$order['id'], 'REFUND-LATE-' . (int)$pay['id'], null, null,
            'Hoàn tiền chuyển khoản đến sau khi đơn đã hủy/hết hạn'
        );
        of_payment_event(
            $pdo, (int)$pay['id'], (int)$order['id'], $idem,
            'thanh_cong', 'late_rejected_refund',
            'Không hồi sinh đơn; đã tạo hoàn tiền ' . $refund['amount'], null
        );
    } else {
        if ($orderStatus === 'cho_xac_nhan') {
            $confirmed = of_confirm_order(
                $pdo, (int)$order['id'], null,
                'Webhook ngân hàng xác nhận thanh toán'
            );
            $resConfirmed = (int)$confirmed['confirmed'];
            $orderStatus = 'da_xac_nhan';
        } else {
            $resConfirmed = of_confirm_pending_reservations(
                $pdo, (int)$order['id'], null,
                'Webhook ngân hàng xác nhận thanh toán'
            );
            if ($orderStatus === 'da_xac_nhan') {
                of_ensure_shipment($pdo, (int)$order['id']);
            }
            $pdo->prepare(
                'INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
                 VALUES (?, ?, ?, ?, NULL)'
            )->execute([
                (int)$order['id'], $orderStatus, $orderStatus,
                'Webhook ngân hàng ghi nhận thanh toán thành công',
            ]);
        }
        of_payment_event(
            $pdo, (int)$pay['id'], (int)$order['id'], $idem,
            'thanh_cong', 'accepted',
            'Xác nhận thanh toán qua webhook, giữ đơn ' . $orderStatus,
            null
        );
    }

    $pdo->commit();
} catch (OrderFlowException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    of_webhook_log('failed', $ctx + ['error' => $e->getMessage()]);
    json_fail($e->getMessage(), $e->statusCode);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    of_webhook_log('error', $ctx + ['error' => $e->getMessage()]);
    json_fail('Không thể xử lý webhook ngân hàng.', 500);
}

of_webhook_log('processed', $ctx + [
    'order_status' => $orderStatus,
    'refund_created' => $refund['id'] !== null,
]);
json_ok([
    'received' => true,
    'payment_id' => (int)$pay['id'],
    'payment_status' => 'thanh_cong',
    'order_id' => (int)$order['id'],
    'order_code' => $order['order_code'],
    'order_status' => $orderStatus,
    'order_expired' => $orderExpired,
    'res_confirmed' => $resConfirmed,
    'res_released' => $resReleased,
    'refund_created' => $refund['id'] !== null,
    'refund_amount' => $refund['amount'],
], 'Đã xác nhận thanh toán từ webhook ngân hàng.');
