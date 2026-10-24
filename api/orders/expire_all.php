<?php
/**
 * /api/orders/expire_all.php
 * POST – Hết hạn TẤT CẢ các đơn đang chờ xác nhận quá thời gian giữ hàng.
 * Có thể gọi bởi cron qua HTTP (ví dụ: curl http://127.0.0.1:8090/api/orders/expire_all.php).
 * Chỉ cho phép admin/staff hoặc gọi từ localhost (cron trên máy chủ).
 *
 * Luật hoàn kho (đồng bộ api/orders/expire.php):
 *  - Đơn còn reservation PENDING      → kho chưa từng bị trừ thật: KHÔNG cộng stock,
 *    chỉ giải phóng reservation và đánh dấu hold-movement "ĐÃ GIẢI PHÓNG".
 *  - Đơn còn reservation CONFIRMED    → kho đã trừ thật (khi xác nhận thanh toán) → cộng lại.
 *  - Không có dòng reservation nào    → đơn cũ/seed: hoàn kho theo order_items.
 * Kho hiện tại chỉ được cộng đúng một lần cho mỗi logic; SELECT ... FOR UPDATE
 * + bộ lọc stock_restored = 0 chặn hai cron chạy song song hoàn kho 2 lần.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['POST'], true)) {
    json_fail('Chỉ chấp nhận POST.', 405);
}

// Cho phép gọi từ cron trên localhost hoặc bởi admin/staff đã auth
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remote, ['127.0.0.1', '::1', 'localhost'], true);
$u = null;
if (!$isLocal) {
    $u = require_role('admin', 'staff');
}

$pdo = db();
$pdo->beginTransaction();
try {
    // FOR UPDATE khoá dòng đơn để 2 cron chạy song song không cùng xử lý một đơn
    $st = $pdo->prepare(
        "SELECT o.id, o.order_code FROM orders o
         WHERE o.status = 'cho_xac_nhan' AND o.reserved_until IS NOT NULL AND o.reserved_until < NOW()
             AND (o.stock_restored = 0 OR o.stock_restored IS NULL)
         ORDER BY o.id FOR UPDATE"
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
    $selResPending = $pdo->prepare('SELECT id, product_id, quantity FROM reservations WHERE order_id = ? AND status = "pending"');
    $selResConfirmed = $pdo->prepare('SELECT id, product_id, quantity FROM reservations WHERE order_id = ? AND status = "confirmed"');
    $updRes = $pdo->prepare('UPDATE reservations SET status = "expired", updated_at = NOW() WHERE id = ?');
    $addStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
    $insMove = $pdo->prepare(
        "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
         VALUES (?, ?, 'huy_don', ?, ?, ?)"
    );
    $clearHold = $pdo->prepare(
        "UPDATE inventory_movements SET note = CONCAT(COALESCE(note,''), ' – ĐÃ GIẢI PHÓNG giữ hàng, không trừ kho')
         WHERE order_id = ? AND product_id = ? AND change_type = 'xuat' AND note LIKE 'Giữ hàng%'"
    );
    $failPay = $pdo->prepare(
        "UPDATE payments SET status = 'that_bai', note = CONCAT(COALESCE(note,''), ' – Đơn hết hạn giữ hàng')
         WHERE order_id = ? AND status = 'cho_xu_ly'"
    );

    $actorId = $u ? (int)$u['id'] : null;
    $actorName = $u ? ($u['full_name'] ?? 'cron') : 'cron';

    foreach ($expired as $ord) {
        $orderId = (int)$ord['id'];

        $updOrder->execute(['Hết hạn giữ hàng, tự động hủy (cron)', $orderId]);
        $insHist->execute([$orderId, 'Đơn hết hạn thời gian giữ hàng, tự động hủy, giải phóng giữ hàng và hoàn kho đúng luật.', $actorId]);

        $selResPending->execute([$orderId]);
        $pending = $selResPending->fetchAll();
        $selResConfirmed->execute([$orderId]);
        $confirmedRes = $selResConfirmed->fetchAll();

        if (count($confirmedRes) > 0) {
            // Kho đã trừ thật khi xác nhận thanh toán → cộng lại đúng phần đã trừ
            foreach ($confirmedRes as $r) {
                $addStock->execute([(int)$r['quantity'], $r['product_id']]);
                $insMove->execute([$r['product_id'], $orderId, (int)$r['quantity'], 'Hoàn kho khi hết hạn giữ hàng (đã xác nhận thanh toán) – cron', $actorId]);
                $updRes->execute([(int)$r['id']]);
                $clearHold->execute([$orderId, $r['product_id']]);
            }
        } elseif (count($pending) > 0) {
            // Kho chưa trừ thật (chỉ đang hold) → KHÔNG cộng stock, chỉ giải phóng
            foreach ($pending as $r) {
                $updRes->execute([(int)$r['id']]);
                $clearHold->execute([$orderId, $r['product_id']]);
            }
        } else {
            // Đơn cũ/seed không có reservation nào: hoàn kho theo order_items
            $selItems = $pdo->prepare(
                'SELECT oi.product_id, SUM(oi.quantity) AS qty
                 FROM order_items oi WHERE oi.order_id = ? GROUP BY oi.product_id'
            );
            $selItems->execute([$orderId]);
            foreach ($selItems->fetchAll() as $it) {
                $addStock->execute([(int)$it['qty'], $it['product_id']]);
                $insMove->execute([$it['product_id'], $orderId, (int)$it['qty'], 'Hoàn kho do đơn hết hạn giữ hàng (tự động)', $actorId]);
                $clearHold->execute([$orderId, $it['product_id']]);
            }
        }

        $failPay->execute([$orderId]);
        $count++;
    }

    $pdo->commit();

    json_ok(
        [
            'expired' => $count,
            'total' => count($expired),
            'actor' => $actorName,
        ],
        'Đã hết hạn và hủy ' . $count . ' đơn quá thời gian giữ hàng.' 
    );
} catch (Throwable $e) {
    $pdo->rollBack();
    json_fail('Không thể xử lý hết hạn đơn hàng.', 500);
}
