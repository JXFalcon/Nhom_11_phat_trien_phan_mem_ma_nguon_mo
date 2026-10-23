<?php
/**
 * /api/payments/simulate.php
 * PATCH – Mô phỏng kết quả thanh toán (admin/staff).
 *   Body: { id, result: 'thanh_cong'|'that_bai', idempotency_key? }
 * Idempotency:
 *   - Giao dịch đã kết thúc → trả về trạng thái hiện tại, KHÔNG đổi thêm.
 *   - Cùng idempotency_key gửi lại → không xử lý lần hai.
 *   - Thanh toán thành công + đơn chờ xác nhận → xác nhận đơn + lịch sử.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

/** Trả 409 khi thanh toán đến muộn (đơn đã hủy/quá hạn giữ hàng). */
function payment_late_response(string $orderStatus): void
{
    json_out([
        'success' => false,
        'data' => ['late' => true, 'order_status' => $orderStatus],
        'message' => 'Thanh toán đến muộn: đơn đã bị hủy/quá hạn giữ hàng trước khi thông báo đến.',
        'errors' => [],
    ], 409);
}

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

$pdo = db();
$pdo->beginTransaction();
try {
    $st = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
    $st->execute([$id]);
    $pay = $st->fetch();
    if (!$pay) {
        $pdo->rollBack();
        json_fail('Không tìm thấy giao dịch.', 404);
    }

    // Thứ tự khóa orders→payments khớp orders_sweep.php: khóa đơn trước rồi mới khóa giao dịch.
    $ordSt = $pdo->prepare(
        'SELECT status, (hold_expires_at IS NOT NULL AND hold_expires_at < NOW()) AS hold_expired
         FROM orders WHERE id = ? FOR UPDATE'
    );
    $ordSt->execute([$pay['order_id']]);
    $ord = $ordSt->fetch();

    $paySt = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
    $paySt->execute([$id]);
    $pay = $paySt->fetch();

    // Idempotency: gửi lại cùng key
    if ($idem !== '' && $pay['idempotency_key'] === $idem && $pay['status'] !== 'cho_xu_ly') {
        $pdo->rollBack();
        // Đã xử lý đến muộn trước đó → trả lại đúng kết quả 409 gốc.
        if ($pay['status'] === 'that_bai' && mb_strpos((string)$pay['note'], 'Đến muộn') !== false) {
            $ord = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
            $ord->execute([$pay['order_id']]);
            payment_late_response((string)$ord->fetchColumn());
        }
        json_ok([
            'id' => (int)$pay['id'],
            'status' => $pay['status'],
            'idempotent_replay' => true,
        ], 'Thông báo đã được xử lý trước đó – không thay đổi thêm.', 200);
    }

    // Idempotency: giao dịch đã kết thúc
    if ($pay['status'] !== 'cho_xu_ly') {
        $pdo->rollBack();
        json_ok([
            'id' => (int)$pay['id'],
            'status' => $pay['status'],
            'already_settled' => true,
        ], 'Giao dịch đã ở trạng thái “' . $pay['status'] . '”, bỏ qua để tránh xử lý trùng.', 200);
    }

    $orderStatus = $ord ? (string)$ord['status'] : null;
    $holdExpired = $ord && (int)$ord['hold_expired'] === 1;
    $lateOrder = false;

    if ($result === 'thanh_cong') {
        // Đơn không thể nhận thêm thanh toán: đã hủy/đang giao/đã giao,
        // hoặc còn chờ xác nhận nhưng đã quá hạn giữ hàng (chưa kịp bị quét).
        $lateOrder = in_array($orderStatus, ['da_huy', 'dang_giao', 'da_giao'], true)
            || ($orderStatus === 'cho_xac_nhan' && $holdExpired);
    }

    if ($lateOrder) {
        // Thanh toán đến muộn: không hồi sinh đơn, không hoàn kho (hủy/quét trước đó đã xử lý).
        $pdo->prepare('UPDATE payments SET status = ?, idempotency_key = ?, processed_at = NOW(), note = ? WHERE id = ?')
            ->execute(['that_bai', $idem !== '' ? $idem : null, 'Đến muộn: đơn da_huy/quá hạn giữ hàng', $id]);
        $pdo->prepare(
            "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
             VALUES (?, ?, ?, 'Thanh toán đến muộn: đơn da_huy/quá hạn giữ hàng, đã ghi nhận thất bại', ?)"
        )->execute([$pay['order_id'], $orderStatus, $orderStatus, $u['id']]);
        $pdo->commit();
        payment_late_response((string)$orderStatus);
    }

    $pdo->prepare('UPDATE payments SET status = ?, idempotency_key = ?, processed_at = NOW(), note = ? WHERE id = ?')
        ->execute([$result, $idem !== '' ? $idem : null, 'Mô phỏng thanh toán ' . $result, $id]);

    if ($result === 'thanh_cong' && $orderStatus === 'cho_xac_nhan') {
        $pdo->prepare("UPDATE orders SET status = 'da_xac_nhan', hold_expires_at = NULL WHERE id = ?")->execute([$pay['order_id']]);
        $pdo->prepare(
            "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
             VALUES (?, 'cho_xac_nhan', 'da_xac_nhan', 'Thanh toán mô phỏng thành công', ?)"
        )->execute([$pay['order_id'], $u['id']]);
        // tạo vận đơn nếu chưa có
        $chk = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE order_id = ?');
        $chk->execute([$pay['order_id']]);
        if ((int)$chk->fetchColumn() === 0) {
            $code = next_code($pdo, 'shipments', 'shipment_code', 'VC-');
            $pdo->prepare(
                "INSERT INTO shipments (shipment_code, order_id, carrier, tracking_code, status)
                 VALUES (?,?,?,?,'cho_lay_hang')"
            )->execute([$code, $pay['order_id'], 'Giao Hàng Nhanh (GHN)', 'OF' . random_int(100000000, 999999999)]);
        }
        $orderStatus = 'da_xac_nhan';
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_fail('Không thể mô phỏng thanh toán.', 500);
}

json_ok([
    'id' => $id,
    'status' => $result,
    'order_status' => $orderStatus,
], 'Đã ghi nhận kết quả thanh toán mô phỏng.');
