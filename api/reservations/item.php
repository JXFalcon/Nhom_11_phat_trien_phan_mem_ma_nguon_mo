<?php
/**
 * /api/reservations/item.php?id=N
 * GET  – Chi tiết phiếu giữ hàng.
 * PUT  – Xác nhận/phát hành/hết hạn phiếu giữ hàng (admin/staff).
 *        Body: { status: 'confirmed'|'released'|'expired', order_id?, note? }
 *        Nếu status='confirmed': trừ tồn kho thật, liên kết với đơn hàng.
 *        Nếu status='released'/'expired': hoàn lại chỗ giữ (không trừ kho vì chưa trừ).
 * DELETE – Hủy phiếu giữ hàng (customer của mình hoặc admin/staff).
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$u = require_auth();
$id = (int)($_GET['id'] ?? 0);
$pdo = db();

if ($id <= 0) {
    json_fail('Thiếu id phiếu giữ hàng.', 422);
}

$st = $pdo->prepare('SELECT * FROM reservations WHERE id = ?');
$st->execute([$id]);
$res = $st->fetch();
if (!$res) {
    json_fail('Không tìm thấy phiếu giữ hàng.', 404);
}
if ($u['role'] === 'customer' && (int)$res['user_id'] !== (int)$u['id']) {
    json_fail('Bạn không có quyền xem phiếu giữ hàng này.', 403);
}

if ($method === 'GET') {
    $res['quantity'] = (int)$res['quantity'];
    $res['unit_price'] = (float)$res['unit_price'];
    json_ok($res);
}

if (!in_array($method, ['PUT', 'DELETE'], true)) {
    json_fail('Chỉ chấp nhận GET/PUT/DELETE.', 405);
}
$u = require_role('admin', 'staff');

if ($method === 'DELETE') {
    if ($res['status'] !== 'pending') {
        json_fail('Chỉ có thể xóa phiếu giữ hàng ở trạng thái pending.', 409);
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM reservations WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_fail('Không thể xóa phiếu giữ hàng.', 500);
    }
    json_ok(['id' => $id], 'Đã xóa phiếu giữ hàng.');
}

if ($method === 'PUT') {
    $in = request_json();
    $newStatus = (string)($in['status'] ?? '');
    $orderId = (int)($in['order_id'] ?? 0);
    $note = trim((string)($in['note'] ?? ''));

    if (!in_array($newStatus, ['confirmed', 'released', 'expired'], true)) {
        json_fail('Trạng thái không hợp lệ.', 422);
    }
    if ($res['status'] !== 'pending') {
        json_fail('Phiếu giữ hàng đã được xử lý (status: ' . $res['status'] . ').', 409);
    }

    $pdo->beginTransaction();
    try {
        if ($newStatus === 'confirmed') {
            // Kiểm tra lại tồn kho trước khi xác nhận
            $stP = $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
            $stP->execute([$res['product_id']]);
            $p = $stP->fetch();
            $held = $pdo->prepare(
                "SELECT COALESCE(SUM(quantity), 0) FROM reservations
                 WHERE product_id = ? AND status = 'pending' AND reserved_until > NOW() AND id <> ?"
            );
            $held->execute([$res['product_id'], $id]);
            $heldQty = (int)$held->fetchColumn();
            $available = (int)$p['stock'] - $heldQty;
            if ((int)$res['quantity'] > $available) {
                $pdo->rollBack();
                json_fail('Không đủ tồn kho để xác nhận giữ hàng (còn ' . $available . ').', 409);
            }

            // Trừ tồn kho thật
            $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?')
                ->execute([(int)$res['quantity'], $res['product_id']]);

            // Liên kết với đơn hàng nếu có
            if ($orderId > 0) {
                $pdo->prepare('UPDATE orders SET status = "da_xac_nhan" WHERE id = ?')->execute([$orderId]);
                $pdo->prepare(
                    "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
                     VALUES (?, NULL, 'da_xac_nhan', 'Xác nhận đơn từ phiếu giữ hàng', ?)"
                )->execute([$orderId, $u['id']]);
            }

            $pdo->prepare(
                "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
                 VALUES (?, ?, 'xuat', ?, ?, ?)"
            )->execute([
                $res['product_id'],
                $orderId ?: null,
                -(int)$res['quantity'],
                'Trừ kho khi xác nhận phiếu giữ hàng ' . $res['reservation_code'],
                $u['id'],
            ]);

            $pdo->prepare(
                'UPDATE reservations SET status = "confirmed", order_id = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$orderId ?: null, $id]);
        } else {
            // released hoặc expired: không trừ kho (chưa từng trừ), chỉ thay đổi status
            $pdo->prepare(
                'UPDATE reservations SET status = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$newStatus, $id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_fail('Không thể cập nhật phiếu giữ hàng.', 500);
    }

    json_ok(['id' => $id, 'status' => $newStatus], 'Đã cập nhật phiếu giữ hàng.');
}

json_fail('Phương thức không được hỗ trợ.', 405);
