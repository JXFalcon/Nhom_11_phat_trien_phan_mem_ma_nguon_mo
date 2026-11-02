<?php
/**
 * /api/orders/expire.php
 * POST - Hết hạn các đơn chờ xác nhận quá thời gian giữ hàng.
 * Body: { id? } - nếu có id chỉ xử lý đơn đó.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Chỉ chấp nhận POST.', 405);
}
$u = require_role('admin', 'staff');
$in = request_json();
$onlyId = (int)($in['id'] ?? $_GET['id'] ?? 0);

$pdo = db();
$pdo->beginTransaction();
try {
    $sql = "SELECT id, order_code FROM orders
            WHERE status = 'cho_xac_nhan'
              AND reserved_until IS NOT NULL
              AND reserved_until < NOW()
              AND (stock_restored = 0 OR stock_restored IS NULL)";
    $params = [];
    if ($onlyId > 0) {
        $sql .= ' AND id = ?';
        $params[] = $onlyId;
    }
    $sql .= ' ORDER BY id FOR UPDATE';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $expired = $st->fetchAll();

    $updOrder = $pdo->prepare(
        'UPDATE orders SET status = "da_huy", cancel_reason = ? WHERE id = ?'
    );
    $insHist = $pdo->prepare(
        "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
         VALUES (?, 'cho_xac_nhan', 'da_huy', ?, ?)"
    );
    $count = 0;
    foreach ($expired as $ord) {
        $orderId = (int)$ord['id'];
        of_restore_order_inventory(
            $pdo, $orderId, (int)$u['id'],
            'Hoàn kho khi hết hạn giữ hàng ' . $ord['order_code'],
            'expired'
        );
        $updOrder->execute(['Hết hạn giữ hàng, tự động hủy', $orderId]);
        $insHist->execute([
            $orderId,
            'Đơn hết hạn thời gian giữ hàng, tự động hủy và giải phóng hàng.',
            (int)$u['id'],
        ]);
        of_fail_pending_payments($pdo, $orderId, (int)$u['id'], 'Đơn hết hạn giữ hàng');
        $count++;
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
    json_fail('Không thể xử lý hết hạn đơn hàng.', 500);
}

json_ok(['expired_count' => $count], 'Đã xử lý ' . $count . ' đơn hết hạn giữ hàng.');
