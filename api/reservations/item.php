<?php
/**
 * /api/reservations/item.php?id=N
 * GET    – Chi tiết phiếu giữ hàng.
 * PUT    – Xác nhận/phát hành/hết hạn phiếu giữ hàng (admin/staff).
 *          Body: { status: 'confirmed'|'released'|'expired', order_id?, note? }
 * DELETE – Hủy phiếu giữ hàng đang pending (customer sở hữu hoặc admin/staff).
 *          Không xóa cứng: chuyển sang released để giữ lịch sử.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$u = require_auth();
$id = (int)($_GET['id'] ?? 0);
$pdo = db();

if ($id <= 0) {
    json_fail('Thiếu id phiếu giữ hàng.', 422);
}
if (!in_array($method, ['GET', 'PUT', 'DELETE'], true)) {
    json_fail('Chỉ chấp nhận GET/PUT/DELETE.', 405);
}

$st = $pdo->prepare('SELECT * FROM reservations WHERE id = ?');
$st->execute([$id]);
$res = $st->fetch();
if (!$res) {
    json_fail('Không tìm thấy phiếu giữ hàng.', 404);
}
$isOwner = $u['role'] === 'customer' && (int)$res['user_id'] === (int)$u['id'];
$isStaff = in_array($u['role'], ['admin', 'staff'], true);
if (!$isOwner && !$isStaff) {
    json_fail('Bạn không có quyền xem phiếu giữ hàng này.', 403);
}

if ($method === 'GET') {
    $res['id'] = (int)$res['id'];
    $res['order_id'] = $res['order_id'] !== null ? (int)$res['order_id'] : null;
    $res['product_id'] = (int)$res['product_id'];
    $res['variant_id'] = $res['variant_id'] !== null ? (int)$res['variant_id'] : null;
    $res['quantity'] = (int)$res['quantity'];
    $res['unit_price'] = (float)$res['unit_price'];
    json_ok($res);
}

if ($method === 'DELETE') {
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT * FROM reservations WHERE id = ? FOR UPDATE');
        $lock->execute([$id]);
        $row = $lock->fetch();
        if (!$row) {
            throw new OrderFlowException('Không tìm thấy phiếu giữ hàng.', 404);
        }
        if (!$isOwner && !$isStaff) {
            throw new OrderFlowException('Bạn không có quyền hủy phiếu giữ hàng này.', 403);
        }
        if ($row['status'] !== 'pending') {
            throw new OrderFlowException('Chỉ có thể hủy phiếu giữ hàng ở trạng thái pending.', 409);
        }
        $pdo->prepare(
            'UPDATE reservations SET status = "released", updated_at = NOW() WHERE id = ?'
        )->execute([$id]);
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
        json_fail('Không thể hủy phiếu giữ hàng.', 500);
    }
    json_ok(['id' => $id, 'status' => 'released'], 'Đã hủy phiếu giữ hàng và giải phóng chỗ giữ.');
}

if (!$isStaff) {
    json_fail('Bạn không có quyền cập nhật phiếu giữ hàng.', 403);
}
$in = request_json();
$newStatus = (string)($in['status'] ?? '');
$orderId = (int)($in['order_id'] ?? 0);
$note = trim((string)($in['note'] ?? ''));

if (!in_array($newStatus, ['confirmed', 'released', 'expired'], true)) {
    json_fail('Trạng thái không hợp lệ.', 422);
}

$pdo->beginTransaction();
try {
    $lock = $pdo->prepare('SELECT * FROM reservations WHERE id = ? FOR UPDATE');
    $lock->execute([$id]);
    $res = $lock->fetch();
    if (!$res) {
        throw new OrderFlowException('Không tìm thấy phiếu giữ hàng.', 404);
    }
    if ($res['status'] !== 'pending') {
        throw new OrderFlowException('Phiếu giữ hàng đã được xử lý (status: ' . $res['status'] . ').', 409);
    }
    if ($newStatus === 'confirmed') {
        $expSt = $pdo->prepare('SELECT (reserved_until < NOW()) FROM reservations WHERE id = ?');
        $expSt->execute([$id]);
        if ((int)$expSt->fetchColumn() === 1) {
            throw new OrderFlowException('Phiếu giữ hàng đã hết hạn, không thể xác nhận.', 409);
        }
    }

    if ($newStatus === 'confirmed') {
        if ($orderId > 0) {
            $order = of_lock_order($pdo, $orderId);
            if ($order['status'] === 'da_huy') {
                throw new OrderFlowException('Không thể xác nhận giữ hàng cho đơn đã hủy.', 409);
            }
            $pdo->prepare('UPDATE reservations SET order_id = ? WHERE id = ?')->execute([$orderId, $id]);
            if ($order['status'] === 'cho_xac_nhan') {
                $confirmed = of_confirm_order(
                    $pdo, $orderId, (int)$u['id'],
                    $note !== '' ? $note : 'Xác nhận đơn từ phiếu giữ hàng'
                );
            } else {
                $confirmed = ['confirmed' => of_confirm_pending_reservations(
                    $pdo, $orderId, (int)$u['id'],
                    $note !== '' ? $note : 'Xác nhận phiếu giữ hàng'
                )];
            }
        } else {
            $p = of_lock_product($pdo, (int)$res['product_id']);
            $required = of_variant_required($pdo, (int)$res['product_id']);
            $variant = of_resolve_variant(
                $pdo, (int)$res['product_id'],
                !empty($res['variant_id']) ? (int)$res['variant_id'] : null,
                $res['variant'] ?? null,
                true, false
            );
            if ($required && !$variant) {
                throw new OrderFlowException('Không xác định được biến thể của phiếu giữ hàng.', 422);
            }
            $avail = of_availability($pdo, $p, $variant);
            $available = $avail['available'] + (int)$res['quantity'];
            if ((int)$res['quantity'] > $available) {
                throw new OrderFlowException('Không đủ tồn kho để xác nhận giữ hàng (còn ' . $available . ').', 409);
            }
            $qty = (int)$res['quantity'];
            $decProduct = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            $decProduct->execute([$qty, (int)$res['product_id'], $qty]);
            if ($decProduct->rowCount() !== 1) {
                throw new OrderFlowException('Không đủ tồn kho để xác nhận giữ hàng.', 409);
            }
            if ($variant) {
                $decVariant = $pdo->prepare(
                    'UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?'
                );
                $decVariant->execute([$qty, (int)$variant['id'], $qty]);
                if ($decVariant->rowCount() !== 1) {
                    throw new OrderFlowException('Không đủ tồn kho cho biến thể.', 409);
                }
            }
            $pdo->prepare(
                "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
                 VALUES (?, NULL, 'xuat', ?, ?, ?)"
            )->execute([
                (int)$res['product_id'], -$qty,
                'Trừ kho khi xác nhận phiếu giữ hàng ' . $res['reservation_code'],
                (int)$u['id'],
            ]);
            $pdo->prepare(
                'UPDATE reservations SET status = "confirmed", variant_id = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$variant ? (int)$variant['id'] : null, $id]);
            $confirmed = ['confirmed' => 1];
        }
    } else {
        $pdo->prepare('UPDATE reservations SET status = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$newStatus, $id]);
        if ($res['order_id'] !== null) {
            $pdo->prepare(
                "UPDATE inventory_movements SET note = CONCAT(COALESCE(note,''), ' - ĐÃ GIẢI PHÓNG giữ hàng, không trừ kho')
                 WHERE order_id = ? AND product_id = ? AND change_type = 'xuat' AND note LIKE 'Giữ hàng%'"
            )->execute([(int)$res['order_id'], (int)$res['product_id']]);
        }
        $confirmed = ['confirmed' => 0];
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
    json_fail('Không thể cập nhật phiếu giữ hàng.', 500);
}

json_ok([
    'id' => $id,
    'status' => $newStatus,
    'order_id' => $orderId > 0 ? $orderId : null,
    'confirmed_count' => $confirmed['confirmed'],
], 'Đã cập nhật phiếu giữ hàng.');
