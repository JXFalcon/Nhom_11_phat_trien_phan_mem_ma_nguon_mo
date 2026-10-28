<?php
/**
 * /api/payments/simulate.php
 * PATCH/POST - Mô phỏng thông báo thanh toán (admin/staff).
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';

if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['PATCH', 'POST'], true)) {
    json_fail('Chỉ chấp nhận PATCH/POST.', 405);
}
$u = require_role('admin', 'staff');
$in = request_json();
$id = (int)($in['id'] ?? 0);
$result = (string)($in['result'] ?? '');
$idem = trim((string)($in['idempotency_key'] ?? ''));

if ($id <= 0 || !in_array($result, ['thanh_cong', 'that_bai'], true)) {
    json_fail('Thiếu id giao dịch hoặc kết quả mô phỏng không hợp lệ.', 422);
}
if (mb_strlen($idem) > 64) {
    json_fail('Idempotency key tối đa 64 ký tự.', 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    if ($idem !== '') {
        $event = of_payment_event_lookup($pdo, $idem);
        if ($event) {
            if ((int)$event['payment_id'] !== $id) {
                throw new OrderFlowException('Idempotency key đã thuộc giao dịch khác.', 409);
            }
            if ($event['result'] !== $result) {
                throw new OrderFlowException('Idempotency key đã được dùng cho kết quả khác.', 409);
            }
            $paySt = $pdo->prepare('SELECT status FROM payments WHERE id = ?');
            $paySt->execute([(int)$event['payment_id']]);
            $current = $paySt->fetchColumn();
            of_payment_event(
                $pdo, (int)$event['payment_id'], (int)$event['order_id'], $idem,
                $result, 'duplicate_replay', 'Gọi lặp cùng idempotency key', (int)$u['id']
            );
            $pdo->commit();
            json_ok([
                'id' => (int)$event['payment_id'],
                'status' => $current ?: $event['result'],
                'idempotent_replay' => true,
                'outcome' => $event['outcome'],
            ], 'Thông báo đã được xử lý trước đó - không thay đổi thêm.', 200);
        }
    }

    $find = $pdo->prepare('SELECT order_id FROM payments WHERE id = ?');
    $find->execute([$id]);
    $payOrderId = (int)$find->fetchColumn();
    if ($payOrderId <= 0) {
        throw new OrderFlowException('Không tìm thấy giao dịch.', 404);
    }
    $lockedOrder = of_lock_order($pdo, $payOrderId);
    $st = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
    $st->execute([$id]);
    $pay = $st->fetch();
    if (!$pay) {
        throw new OrderFlowException('Không tìm thấy giao dịch.', 404);
    }

    if ($idem !== '' && $pay['idempotency_key'] === $idem && $pay['status'] === 'that_bai') {
        throw new OrderFlowException('Idempotency key này đã ghi nhận thanh toán thất bại.', 409);
    }
    if ($idem !== '') {
        $other = $pdo->prepare('SELECT id FROM payments WHERE idempotency_key = ? AND id <> ? LIMIT 1');
        $other->execute([$idem, $id]);
        if ($other->fetch()) {
            throw new OrderFlowException('Idempotency key đã thuộc giao dịch khác.', 409);
        }
    }

    if (in_array($pay['status'], ['thanh_cong', 'hoan_tien'], true)) {
        of_payment_event(
            $pdo, $id, (int)$pay['order_id'], $idem,
            $result, 'ignored_terminal', 'Giao dịch đã kết thúc, bỏ qua thay đổi', (int)$u['id']
        );
        $pdo->commit();
        json_ok([
            'id' => $id,
            'status' => $pay['status'],
            'already_settled' => true,
            'order_status' => null,
        ], 'Giao dịch đã ở trạng thái "' . $pay['status'] . '", bỏ qua để tránh xử lý trùng.', 200);
    }

    $newKey = $idem !== '' ? $idem : null;
    $pdo->prepare(
        'UPDATE payments SET status = ?, idempotency_key = ?, processed_at = NOW(), note = ? WHERE id = ?'
    )->execute([$result, $newKey, 'Mô phỏng thanh toán ' . $result, $id]);

    $orderStatus = null;
    $orderExpired = false;
    $resConfirmed = 0;
    $resReleased = 0;
    $refund = ['amount' => 0.0, 'id' => null];

    if ($result === 'thanh_cong') {
        $order = $lockedOrder;
        $orderStatus = $order['status'];
        $orderExpired = false;
        if ($orderStatus === 'cho_xac_nhan' && $order['reserved_until'] !== null) {
            $expSt = $pdo->prepare('SELECT (reserved_until < NOW()) FROM orders WHERE id = ?');
            $expSt->execute([(int)$order['id']]);
            $orderExpired = (int)$expSt->fetchColumn() === 1;
        }

        if ($orderStatus === 'da_huy' || $orderExpired) {
            $orderExpired = true;
            if ($orderStatus === 'cho_xac_nhan') {
                $restore = of_restore_order_inventory(
                    $pdo, (int)$order['id'], (int)$u['id'],
                    'Hoàn kho khi thanh toán đến sau hạn ' . $order['order_code'],
                    'expired'
                );
                $resReleased = (int)$restore['pending'];
                $pdo->prepare(
                    'UPDATE orders SET status = "da_huy", cancel_reason = ? WHERE id = ?'
                )->execute(['Thanh toán đến sau khi hết hạn giữ hàng', (int)$order['id']]);
                $pdo->prepare(
                    "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
                     VALUES (?, 'cho_xac_nhan', 'da_huy', ?, ?)"
                )->execute([
                    (int)$order['id'],
                    'Thanh toán đến sau khi hết hạn, không hồi sinh đơn, hoàn tiền tự động.',
                    (int)$u['id'],
                ]);
                $orderStatus = 'da_huy';
            }
            $refund = of_refund_order(
                $pdo, (int)$order['id'], 'REFUND-LATE-' . $id, null, (int)$u['id'],
                'Hoàn tiền thanh toán đến sau khi đơn đã hủy/hết hạn'
            );
            of_payment_event(
                $pdo, $id, (int)$order['id'], $idem,
                'thanh_cong', 'late_rejected_refund',
                'Không hồi sinh đơn; đã tạo hoàn tiền ' . $refund['amount'],
                (int)$u['id']
            );
        } else {
            if ($orderStatus === 'cho_xac_nhan') {
                $confirmed = of_confirm_order(
                    $pdo, (int)$order['id'], (int)$u['id'],
                    'Thanh toán mô phỏng thành công'
                );
                $resConfirmed = (int)$confirmed['confirmed'];
                $orderStatus = 'da_xac_nhan';
            } else {
                $resConfirmed = of_confirm_pending_reservations(
                    $pdo, (int)$order['id'], (int)$u['id'],
                    'Thanh toán mô phỏng thành công'
                );
                if ($orderStatus === 'da_xac_nhan') {
                    of_ensure_shipment($pdo, (int)$order['id']);
                }
                $pdo->prepare(
                    'INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    (int)$order['id'], $orderStatus, $orderStatus,
                    'Ghi nhận thanh toán thành công', (int)$u['id'],
                ]);
            }
            of_payment_event(
                $pdo, $id, (int)$order['id'], $idem,
                'thanh_cong', 'accepted',
                'Xác nhận thanh toán, giữ đơn ' . $orderStatus,
                (int)$u['id']
            );
        }
    } else {
        $order = $lockedOrder;
        $orderStatus = $order['status'];
        $pdo->prepare(
            'INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            (int)$order['id'], $orderStatus, $orderStatus,
            'Thanh toán thất bại; giữ hàng tiếp tục đến khi hết hạn', (int)$u['id'],
        ]);
        of_payment_event(
            $pdo, $id, (int)$order['id'], $idem,
            'that_bai', 'failed',
            'Đã ghi nhận thanh toán thất bại, không giải phóng giữ hàng',
            (int)$u['id']
        );
    }

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
    json_fail('Không thể mô phỏng thanh toán.', 500);
}

json_ok([
    'id' => $id,
    'status' => $result,
    'order_status' => $orderStatus,
    'order_expired' => $orderExpired,
    'res_confirmed' => $resConfirmed,
    'res_released' => $resReleased,
    'refund_created' => $refund['id'] !== null,
    'refund_amount' => $refund['amount'],
], 'Đã ghi nhận kết quả thanh toán mô phỏng.');
