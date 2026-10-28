<?php
/**
 * /api/orders/expire_all.php
 * POST - Hết hạn tất cả đơn chờ xác nhận quá thời gian giữ hàng.
 * Cho phép cron localhost hoặc admin/staff đã đăng nhập.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Chỉ chấp nhận POST.', 405);
}

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remote, ['127.0.0.1', '::1', 'localhost'], true);
$u = null;
if (!$isLocal) {
    $u = require_role('admin', 'staff');
}

$pdo = db();
$pdo->beginTransaction();
try {
    $st = $pdo->prepare(
        "SELECT id, order_code FROM orders
         WHERE status = 'cho_xac_nhan'
           AND reserved_until IS NOT NULL
           AND reserved_until < NOW()
           AND (stock_restored = 0 OR stock_restored IS NULL)
         ORDER BY id FOR UPDATE"
    );
    $st->execute();
    $expired = $st->fetchAll();

    $updOrder = $pdo->prepare(
        'UPDATE orders SET status = "da_huy", cancel_reason = ? WHERE id = ?'
    );
    $insHist = $pdo->prepare(
        "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
         VALUES (?, 'cho_xac_nhan', 'da_huy', ?, ?)"
    );
    $actorId = $u ? (int)$u['id'] : null;
    $actorName = $u ? ($u['full_name'] ?? 'cron') : 'cron';
    $count = 0;
    foreach ($expired as $ord) {
        $orderId = (int)$ord['id'];
        of_restore_order_inventory(
            $pdo, $orderId, $actorId,
            'Hoàn kho khi hết hạn giữ hàng ' . $ord['order_code'],
            'expired'
        );
        $updOrder->execute(['Hết hạn giữ hàng, tự động hủy (cron)', $orderId]);
        $insHist->execute([
            $orderId,
            'Đơn hết hạn thời gian giữ hàng, tự động hủy, giải phóng giữ hàng và hoàn kho đúng luật.',
            $actorId,
        ]);
        of_fail_pending_payments($pdo, $orderId, $actorId, 'Đơn hết hạn giữ hàng');
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

json_ok(
    ['expired' => $count, 'total' => count($expired), 'actor' => $actorName],
    'Đã hết hạn và hủy ' . $count . ' đơn quá thời gian giữ hàng.'
);
