<?php
/**
 * /api/payments/customer_confirm.php
 * POST - Khách xác nhận "Tôi đã chuyển khoản" để ghi nhận thanh toán ngay.
 *
 * Chỉ dùng cho DEMO khi chưa có webhook ngân hàng. Endpoint kiểm tra quyền
 * sở hữu đơn, trạng thái payment/QR và hạn giữ hàng; sau đó đánh dấu payment
 * thành công, QR paid và xác nhận đơn (da_xac_nhan).
 *
 * Body: { payment_id } hoặc { qr_id }
 * Tắt bằng config: 'customer_confirm' => false (hoặc OF_BANK_CUSTOMER_CONFIRM=0).
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';
require_once __DIR__ . '/../../includes/payment_qr.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Chỉ chấp nhận POST.', 405);
}

$u = require_auth();
$cfg = of_payment_bank_config();
if (empty($cfg['customer_confirm'])) {
    json_fail('Chức năng khách tự xác nhận đang tắt. Vui lòng chờ cửa hàng xác nhận.', 403);
}

$in = request_json();
$qrId = (int)($in['qr_id'] ?? 0);
$paymentId = (int)($in['payment_id'] ?? 0);
if ($qrId <= 0 && $paymentId <= 0) {
    json_fail('Thiếu qr_id hoặc payment_id.', 422);
}

$pdo = db();
if (!of_qr_table_exists($pdo)) {
    json_fail('Chưa chạy migration bảng QR. Vui lòng chạy database/migrate_payment_qr_web.php?run=1.', 409);
}

$pdo->beginTransaction();
try {
    $qr = null;
    if ($qrId > 0) {
        $st = $pdo->prepare('SELECT * FROM payment_qr_sessions WHERE id = ? FOR UPDATE');
        $st->execute([$qrId]);
        $qr = $st->fetch();
        if (!$qr) {
            throw new OrderFlowException('Không tìm thấy mã QR.', 404);
        }
        $qrPaymentId = (int)$qr['payment_id'];
        if ($paymentId > 0 && $paymentId !== $qrPaymentId) {
            throw new OrderFlowException('Mã QR không thuộc giao dịch đã chọn.', 422);
        }
        $paymentId = $qrPaymentId;
    } else {
        $st = $pdo->prepare(
            'SELECT * FROM payment_qr_sessions WHERE payment_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE'
        );
        $st->execute([$paymentId]);
        $qr = $st->fetch();
        if (!$qr) {
            throw new OrderFlowException('Giao dịch chưa có mã QR. Vui lòng tạo mã QR trước.', 409);
        }
    }

    $st = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
    $st->execute([$paymentId]);
    $pay = $st->fetch();
    if (!$pay) {
        throw new OrderFlowException('Không tìm thấy giao dịch.', 404);
    }
    $order = of_lock_order($pdo, (int)$pay['order_id']);

    if ($u['role'] === 'customer' && (int)$order['user_id'] !== (int)$u['id']) {
        throw new OrderFlowException('Bạn không có quyền xác nhận giao dịch này.', 403);
    }
    if ($pay['method'] !== 'bank') {
        throw new OrderFlowException('Giao dịch này không phải chuyển khoản ngân hàng.', 422);
    }
    if ($order['status'] === 'da_huy') {
        throw new OrderFlowException('Đơn hàng đã hủy, không thể xác nhận thanh toán.', 409);
    }

    if (in_array($pay['status'], ['thanh_cong', 'hoan_tien'], true)) {
        of_qr_mark_paid($pdo, $paymentId);
        $pdo->commit();
        json_ok([
            'payment_id' => $paymentId,
            'qr_id' => (int)$qr['id'],
            'status' => 'paid',
            'payment_status' => $pay['status'],
            'order_id' => (int)$order['id'],
            'order_code' => $order['order_code'],
            'order_status' => $order['status'],
            'already_settled' => true,
        ], 'Giao dịch đã được ghi nhận trước đó.');
    }
    if ($pay['status'] !== 'cho_xu_ly') {
        throw new OrderFlowException('Giao dịch đã kết thúc, không thể xác nhận lại.', 409);
    }
    if (of_order_hold_expired($pdo, $order)) {
        throw new OrderFlowException(
            'Đơn đã hết hạn giữ hàng. Thanh toán không thể kích hoạt đơn; vui lòng liên hệ cửa hàng để hoàn tiền.',
            409
        );
    }

    $pdo->prepare(
        'UPDATE payments SET status = ?, processed_at = NOW(), note = ? WHERE id = ?'
    )->execute(['thanh_cong', 'Khách xác nhận đã chuyển khoản', $paymentId]);
    of_qr_mark_paid($pdo, $paymentId);

    $orderStatus = (string)$order['status'];
    $resConfirmed = 0;
    if ($orderStatus === 'cho_xac_nhan') {
        $confirmed = of_confirm_order(
            $pdo, (int)$order['id'], (int)$u['id'],
            'Khách xác nhận đã chuyển khoản'
        );
        $resConfirmed = (int)$confirmed['confirmed'];
        $orderStatus = 'da_xac_nhan';
    }

    of_payment_event(
        $pdo, $paymentId, (int)$order['id'], 'CUST-QR-' . $paymentId,
        'thanh_cong', 'accepted',
        'Khách xác nhận đã chuyển khoản' . ($orderStatus === 'da_xac_nhan' ? ', đơn đã xác nhận' : ''),
        (int)$u['id']
    );

    $pdo->commit();
} catch (OrderFlowException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_fail($e->getMessage(), $e->statusCode);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_fail('Không thể xác nhận thanh toán. Vui lòng thử lại.', 500);
}

json_ok([
    'payment_id' => $paymentId,
    'qr_id' => (int)$qr['id'],
    'status' => 'paid',
    'payment_status' => 'thanh_cong',
    'order_id' => (int)$order['id'],
    'order_code' => $order['order_code'],
    'order_status' => $orderStatus,
    'res_confirmed' => $resConfirmed,
], 'Đã ghi nhận thanh toán thành công và xác nhận đơn hàng.');
