<?php
/**
 * /api/carts/item.php?id=N
 * PUT  – Cập nhật số lượng item trong giỏ. Body: { quantity }
 * DELETE – Xóa item khỏi giỏ.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$u = require_auth();
$id = (int)($_GET['id'] ?? 0);
$pdo = db();

if ($id <= 0) {
    json_fail('Thiếu id item giỏ hàng.', 422);
}
if (!in_array($method, ['PUT', 'DELETE'], true)) {
    json_fail('Chỉ chấp nhận PUT/DELETE.', 405);
}

$st = $pdo->prepare(
    'SELECT ci.id, ci.cart_id, ci.product_id, ci.variant_id, ci.variant, ci.quantity, c.user_id
     FROM cart_items ci JOIN carts c ON c.id = ci.cart_id
     WHERE ci.id = ?'
);
$st->execute([$id]);
$item = $st->fetch();
if (!$item) {
    json_fail('Không tìm thấy item trong giỏ hàng.', 404);
}
if ((int)$item['user_id'] !== (int)$u['id']) {
    json_fail('Bạn không có quyền thao tác với item này.', 403);
}

if ($method === 'DELETE') {
    $pdo->prepare('DELETE FROM cart_items WHERE id = ?')->execute([$id]);
    json_ok(['id' => $id], 'Đã xóa khỏi giỏ hàng.');
}

$in = request_json();
$qty = (int)($in['quantity'] ?? 0);
if ($qty < 0) {
    json_fail('Số lượng không hợp lệ.', 422);
}

$pdo->beginTransaction();
try {
    $lock = $pdo->prepare(
        'SELECT ci.*, c.user_id FROM cart_items ci JOIN carts c ON c.id = ci.cart_id
         WHERE ci.id = ? FOR UPDATE'
    );
    $lock->execute([$id]);
    $item = $lock->fetch();
    if (!$item) {
        throw new OrderFlowException('Không tìm thấy item trong giỏ hàng.', 404);
    }
    if ((int)$item['user_id'] !== (int)$u['id']) {
        throw new OrderFlowException('Bạn không có quyền thao tác với item này.', 403);
    }

    if ($qty === 0) {
        $pdo->prepare('DELETE FROM cart_items WHERE id = ?')->execute([$id]);
    } else {
        $p = of_lock_product($pdo, (int)$item['product_id']);
        if ($p['status'] !== 'active') {
            throw new OrderFlowException('Sản phẩm đã ngừng bán, vui lòng xóa khỏi giỏ.', 422);
        }
        $required = of_variant_required($pdo, (int)$item['product_id']);
        $variant = of_resolve_variant(
            $pdo, (int)$item['product_id'],
            !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
            $item['variant'] !== '' ? $item['variant'] : null,
            false, $required
        );
        if ($required && !$variant) {
            throw new OrderFlowException('Biến thể của sản phẩm không còn tồn tại, vui lòng xóa khỏi giỏ.', 422);
        }
        $avail = of_availability($pdo, $p, $variant);
        if ($qty > $avail['available']) {
            throw new OrderFlowException(
                'Chỉ còn ' . $avail['available'] . ' sản phẩm có thể đặt (đã giữ: ' . $avail['held'] . ').',
                409
            );
        }
        $pdo->prepare('UPDATE cart_items SET quantity = ?, variant_id = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$qty, $variant ? (int)$variant['id'] : null, $id]);
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
    json_fail('Không thể cập nhật giỏ hàng.', 500);
}

json_ok(['id' => $id, 'quantity' => $qty], 'Đã cập nhật giỏ hàng.');
