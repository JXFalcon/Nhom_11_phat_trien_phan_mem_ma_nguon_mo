<?php
/**
 * OrderFlow – Quét và tự hủy các đơn quá hạn giữ hàng.
 *
 * Quy ước hủy giống api/orders/status.php:
 *   - hoàn kho đúng 1 lần (cờ orders.stock_restored) + ghi inventory_movements;
 *   - payment cho_xu_ly → that_bai;
 *   - shipment cho_lay_hang/dang_van_chuyen → that_bai;
 *   - đơn → da_huy + ghi order_history.
 * Idempotent: điều kiện status='cho_xac_nhan' + cờ stock_restored bảo đảm gọi lại
 * không hoàn kho lần hai.
 */
declare(strict_types=1);

/**
 * Hủy mọi đơn chờ xác nhận đã quá hold_expires_at.
 *
 * @return int Số đơn đã hủy trong lần gọi này.
 */
function orders_cancel_expired_holds(PDO $pdo): int
{
    // Chỉ quét tối đa 200 đơn/lần; lần GET sau sẽ quét tiếp phần còn lại để nhẹ latency.
    $st = $pdo->query(
        "SELECT id FROM orders
         WHERE status = 'cho_xac_nhan'
           AND hold_expires_at IS NOT NULL
           AND hold_expires_at < NOW()
         ORDER BY id
         LIMIT 200"
    );
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    $cancelled = 0;

    foreach ($ids as $orderId) {
        $orderId = (int)$orderId;
        $pdo->beginTransaction();
        try {
            // Khóa lại và kiểm tra lại điều kiện để tránh hủy trùng khi có 2 request quét song song.
            $lock = $pdo->prepare(
                "SELECT id, stock_restored,
                        GREATEST(TIMESTAMPDIFF(MINUTE, created_at, hold_expires_at), 1) AS hold_minutes
                 FROM orders
                 WHERE id = ?
                   AND status = 'cho_xac_nhan'
                   AND hold_expires_at IS NOT NULL
                   AND hold_expires_at < NOW()
                 FOR UPDATE"
            );
            $lock->execute([$orderId]);
            $order = $lock->fetch();
            if (!$order) {
                $pdo->rollBack();
                continue;
            }

            $holdMinutes = (int)$order['hold_minutes'];
            $note = 'Tự hủy: quá hạn giữ hàng (quá ' . $holdMinutes . ' phút không xác nhận thanh toán)';

            // Hoàn kho đúng một lần.
            if ((int)$order['stock_restored'] === 0) {
                $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
                $items->execute([$orderId]);
                $add = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                $mv = $pdo->prepare(
                    "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
                     VALUES (?, ?, 'huy_don', ?, ?, NULL)"
                );
                foreach ($items->fetchAll() as $it) {
                    $add->execute([(int)$it['quantity'], $it['product_id']]);
                    $mv->execute([$it['product_id'], $orderId, (int)$it['quantity'], 'Hoàn kho khi tự hủy đơn quá hạn giữ hàng']);
                }
                $pdo->prepare('UPDATE orders SET stock_restored = 1 WHERE id = ?')->execute([$orderId]);
            }

            // Thanh toán còn chờ → thất bại.
            $pdo->prepare(
                "UPDATE payments
                 SET status = 'that_bai', note = CONCAT(COALESCE(note, ''), ' – Quá hạn giữ hàng')
                 WHERE order_id = ? AND status = 'cho_xu_ly'"
            )->execute([$orderId]);

            // Vận đơn còn đang xử lý/giao → thất bại.
            $pdo->prepare(
                "UPDATE shipments SET status = 'that_bai'
                 WHERE order_id = ? AND status IN ('cho_lay_hang', 'dang_van_chuyen')"
            )->execute([$orderId]);

            // Chuyển đơn sang đã hủy và ghi lịch sử.
            $pdo->prepare(
                "UPDATE orders SET status = 'da_huy', cancel_reason = ?, stock_restored = 1, hold_expires_at = NULL WHERE id = ?"
            )->execute([$note, $orderId]);
            $pdo->prepare(
                "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
                 VALUES (?, 'cho_xac_nhan', 'da_huy', ?, NULL)"
            )->execute([$orderId, $note]);

            $pdo->commit();
            $cancelled++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('orders_cancel_expired_holds: đơn #' . $orderId . ' lỗi – ' . $e->getMessage());
        }
    }

    return $cancelled;
}
