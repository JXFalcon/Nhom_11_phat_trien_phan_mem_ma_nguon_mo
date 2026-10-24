<?php
/**
 * /api/orders/expire.php
 * POST – Hết hạn các đơn đang chờ xác nhận quá thời gian giữ hàng (admin/staff).
 * Tìm đơn status='cho_xac_nhan' và reserved_until < NOW(), chuyển sang da_huy,
 * giải phóng reservation, hoàn kho nếu đã xác nhận thanh toán, ghi lịch sử.
 * Body: {} (không cần body)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['POST'], true)) {
    json_fail('Chỉ chấp nhận POST.', 405);
}
$u = require_role('admin', 'staff');

$pdo = db();
$pdo->beginTransaction();
try {
    $st = $pdo->prepare(
        "SELECT o.id, o.order_code FROM orders o
         WHERE o.status = 'cho_xac_nhan' AND o.reserved_until IS NOT NULL AND o.reserved_until < NOW()
         ORDER BY o.id"
    );
    $st->execute();
    $expired = $st->fetchAll();

    $count = 0;
    $updOrder = $pdo->prepare(
        "UPDATE orders SET status = 'da_huy', cancel_reason = ?, stock_restored = 1 WHERE id = ?"
    );
    $insHist = $pdo->prepare(
        "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
         VALUES (?, 'cho_xac_nhan', 'da_huy', ?, ?)"
    );
    $failPay = $pdo->prepare(
        "UPDATE payments SET status = 'that_bai', note = CONCAT(COALESCE(note,''), ' – Đơn hết hạn giữ hàng')
         WHERE order_id = ? AND status = 'cho_xu_ly'"
    );

    foreach ($expired as $ord) {
        $orderId = (int)$ord['id'];

        $updOrder->execute(['Hết hạn giữ hàng, tự động hủy', $orderId]);
        $insHist->execute([$orderId, 'Đơn hết hạn thời gian giữ hàng, tự động hủy và giải phóng hàng.', $u['id']]);

        // Giải phóng reservation pending (chưa trừ kho thật)
        $selResPending = $pdo->prepare(
            'SELECT id FROM reservations WHERE order_id = ? AND status = "pending"'
        );
        $selResPending->execute([$orderId]);
        $updRes = $pdo->prepare('UPDATE reservations SET status = "expired", updated_at = NOW() WHERE id = ?');
        foreach ($selResPending->fetchAll() as $r) {
            $updRes->execute([(int)$r['id']]);
        }

        // Nếu có reservation confirmed (đã trừ kho thật) → hoàn lại kho
        $selResConfirmed = $pdo->prepare(
            'SELECT id, product_id, quantity FROM reservations WHERE order_id = ? AND status = "confirmed"'
        );
        $selResConfirmed->execute([$orderId]);
        $confirmedRes = $selResConfirmed->fetchAll();
        $hasConfirmedRes = count($confirmedRes) > 0;

        if ($hasConfirmedRes) {
            $addStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            $mv = $pdo->prepare(
                "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
                 VALUES (?, ?, 'huy_don', ?, ?, ?)"
            );
            foreach ($confirmedRes as $r) {
                $addStock->execute([(int)$r['quantity'], $r['product_id']]);
                $mv->execute([$r['product_id'], $orderId, (int)$r['quantity'], 'Hoàn kho khi hết hạn giữ hàng (đã xác nhận thanh toán)', $u['id']]);
                $updRes->execute([(int)$r['id']]);
            }
        } else {
            // Không có reservation confirmed → hoàn kho từ order_items (đơn cũ/seed)
            $selItems = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
            $selItems->execute([$orderId]);
            $addStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            $mv = $pdo->prepare(
                "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
                 VALUES (?, ?, 'huy_don', ?, ?, ?)"
            );
            foreach ($selItems->fetchAll() as $it) {
                $addStock->execute([(int)$it['quantity'], $it['product_id']]);
                $mv->execute([$it['product_id'], $orderId, (int)$it['quantity'], 'Hoàn kho khi hết hạn giữ hàng', $u['id']]);
            }
        }

        $failPay->execute([$orderId]);
        $count++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_fail('Không thể xử lý hết hạn đơn hàng.', 500);
}

json_ok([
    'expired_count' => $count,
], 'Đã xử lý ' . $count . ' đơn hết hạn giữ hàng.');
