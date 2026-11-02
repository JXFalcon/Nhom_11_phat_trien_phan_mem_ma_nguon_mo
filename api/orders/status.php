<?php
/**
 * /api/orders/status.php
 * PATCH - Cập nhật trạng thái đơn theo vòng đời cho phép.
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
$to = (string)($in['to_status'] ?? '');
$note = trim((string)($in['note'] ?? ''));

$allowed = [
    'cho_xac_nhan' => ['da_xac_nhan', 'da_huy'],
    'da_xac_nhan' => ['dang_giao', 'da_huy'],
    'dang_giao' => ['da_giao'],
    'da_giao' => [],
    'da_huy' => [],
];
if ($id <= 0 || !array_key_exists($to, $allowed)) {
    json_fail('Trạng thái đích không hợp lệ.', 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $order = of_lock_order($pdo, $id);
    $from = $order['status'];
    if (!in_array($to, $allowed[$from] ?? [], true)) {
        throw new OrderFlowException(
            'Không thể chuyển từ "' . $from . '" sang "' . $to . '". Chỉ được đi theo đúng vòng đời đơn.',
            409
        );
    }

    if ($to === 'da_xac_nhan') {
        of_confirm_order($pdo, $id, (int)$u['id'], $note !== '' ? $note : 'Xác nhận đơn hàng');
    } elseif ($to === 'da_huy') {
        if ((int)$order['stock_restored'] === 1) {
            throw new OrderFlowException('Đơn này đã được hoàn kho trước đó, không hoàn lại lần nữa.', 409);
        }
        of_restore_order_inventory(
            $pdo, $id, (int)$u['id'],
            'Hoàn kho khi hủy đơn ' . $order['order_code'],
            'released'
        );
        $pdo->prepare('UPDATE orders SET status = "da_huy", cancel_reason = ? WHERE id = ?')
            ->execute([$note !== '' ? $note : 'Hủy đơn', $id]);
        of_fail_pending_payments($pdo, $id, (int)$u['id'], 'Đơn bị hủy');
        of_refund_order(
            $pdo, $id, 'REFUND-ORD-' . $id, null, (int)$u['id'],
            'Hoàn tiền do hủy đơn ' . $order['order_code']
        );
        $pdo->prepare("UPDATE shipments SET status = 'that_bai' WHERE order_id = ? AND status <> 'da_giao'")
            ->execute([$id]);
        $pdo->prepare(
            'INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (?,?,?,?,?)'
        )->execute([$id, $from, 'da_huy', $note !== '' ? $note : 'Hủy đơn', (int)$u['id']]);
    } else {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$to, $id]);
        $pdo->prepare(
            'INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (?,?,?,?,?)'
        )->execute([$id, $from, $to, $note !== '' ? $note : null, (int)$u['id']]);
        if ($to === 'dang_giao') {
            $pdo->prepare("UPDATE shipments SET status = 'dang_van_chuyen' WHERE order_id = ?")->execute([$id]);
        } elseif ($to === 'da_giao') {
            $pdo->prepare(
                "UPDATE shipments SET status = 'da_giao', ship_date = COALESCE(ship_date, NOW()) WHERE order_id = ?"
            )->execute([$id]);
            of_settle_cod_payment($pdo, $id, (int)$u['id']);
        }
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
    json_fail('Không thể cập nhật trạng thái đơn.', 500);
}

json_ok(['id' => $id, 'from_status' => $from, 'to_status' => $to], 'Đã cập nhật trạng thái đơn.');
