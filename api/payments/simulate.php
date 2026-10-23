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
    $st = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
    $st->execute([$id]);
    $pay = $st->fetch();
    if (!$pay) {
        $pdo->rollBack();
        json_fail('Không tìm thấy giao dịch.', 404);
    }

    // Idempotency: gửi lại cùng key
    if ($idem !== '' && $pay['idempotency_key'] === $idem && $pay['status'] !== 'cho_xu_ly') {
        $pdo->rollBack();
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

    $pdo->prepare('UPDATE payments SET status = ?, idempotency_key = ?, processed_at = NOW(), note = ? WHERE id = ?')
        ->execute([$result, $idem !== '' ? $idem : null, 'Mô phỏng thanh toán ' . $result, $id]);

    $orderStatus = null;
    if ($result === 'thanh_cong') {
        $ordSt = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
        $ordSt->execute([$pay['order_id']]);
        $orderStatus = $ordSt->fetchColumn();
        if ($orderStatus === 'cho_xac_nhan') {
            $pdo->prepare("UPDATE orders SET status = 'da_xac_nhan' WHERE id = ?")->execute([$pay['order_id']]);
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
        }
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
