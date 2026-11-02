<?php
/**
 * /api/shipments/status.php
 * PATCH – Cập nhật trạng thái giao hàng (admin/staff) + đồng bộ trạng thái đơn.
 *   Body: { id, to_status }
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

$allowed = [
    'cho_lay_hang' => ['dang_van_chuyen', 'that_bai'],
    'dang_van_chuyen' => ['da_giao', 'that_bai'],
    'da_giao' => [],
    'that_bai' => ['dang_van_chuyen'],
];
if ($id <= 0 || !isset($allowed[$to])) {
    json_fail('Trạng thái đích không hợp lệ.', 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $st = $pdo->prepare('SELECT * FROM shipments WHERE id = ? FOR UPDATE');
    $st->execute([$id]);
    $ship = $st->fetch();
    if (!$ship) {
        $pdo->rollBack();
        json_fail('Không tìm thấy vận đơn.', 404);
    }
    $from = $ship['status'];
    if (!in_array($to, $allowed[$from] ?? [], true)) {
        $pdo->rollBack();
        json_fail('Không thể chuyển vận đơn từ “' . $from . '” sang “' . $to . '”.', 409);
    }

    $pdo->prepare("UPDATE shipments SET status = ?, ship_date = COALESCE(ship_date, CASE WHEN ? = 'da_giao' THEN NOW() ELSE ship_date END) WHERE id = ?")->execute([$to, $to, $id]);

    // Đồng bộ trạng thái đơn hàng + chốt giữ hàng trước khi giao
    $orderId = (int)$ship['order_id'];
    $ord = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
    $ord->execute([$orderId]);
    $order = $ord->fetch();
    if (!$order) {
        throw new OrderFlowException('Không tìm thấy đơn hàng của vận đơn.', 404);
    }
    $ordStatus = $order['status'];
    if ($ordStatus === 'da_huy') {
        throw new OrderFlowException('Không thể cập nhật vận đơn của đơn đã hủy.', 409);
    }
    if (in_array($to, ['dang_van_chuyen', 'da_giao'], true)) {
        if ($ordStatus === 'cho_xac_nhan') {
            of_confirm_order($pdo, $orderId, (int)$u['id'], 'Xác nhận đơn khi bàn giao vận chuyển');
            $ordStatus = 'da_xac_nhan';
        } else {
            of_confirm_pending_reservations(
                $pdo, $orderId, (int)$u['id'],
                'Chốt giữ hàng khi cập nhật vận đơn'
            );
        }
    }

    if ($to === 'dang_van_chuyen' && $ordStatus === 'da_xac_nhan') {
        $pdo->prepare("UPDATE orders SET status = 'dang_giao' WHERE id = ?")->execute([$orderId]);
        $pdo->prepare(
            "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
             VALUES (?, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển', ?)"
        )->execute([$orderId, $u['id']]);
    } elseif ($to === 'da_giao' && in_array($ordStatus, ['dang_giao', 'da_xac_nhan'], true)) {
        $pdo->prepare("UPDATE orders SET status = 'da_giao', ship_date = COALESCE(ship_date, NOW()) WHERE id = ?")->execute([$orderId]);
        $pdo->prepare(
            "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
             VALUES (?, ?, 'da_giao', 'Khách đã nhận hàng', ?)"
        )->execute([$orderId, $ordStatus, $u['id']]);
    } elseif ($to === 'that_bai') {
        // Vận đơn thất bại: đơn giữ nguyên trạng thái nhưng phải ghi lịch sử
        // để admin biết cần thử giao lại (dang_van_chuyen) hoặc hủy đơn.
        $pdo->prepare(
            "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
             VALUES (?, ?, ?, 'Vận đơn thất bại – cần giao lại hoặc hủy đơn', ?)"
        )->execute([$orderId, $ordStatus, $ordStatus, $u['id']]);
    }

    if ($to === 'da_giao') {
        of_settle_cod_payment($pdo, $orderId, (int)$u['id']);
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
    json_fail('Không thể cập nhật vận đơn.', 500);
}

json_ok(['id' => $id, 'from_status' => $from, 'to_status' => $to], 'Đã cập nhật trạng thái giao hàng.');
