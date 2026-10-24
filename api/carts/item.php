<?php
/**
 * /api/carts/item.php?id=N
 * PUT  – Cập nhật số lượng item trong giỏ.
 *        Body: { quantity }
 * DELETE – Xóa item khỏi giỏ.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$u = require_auth();
$id = (int)($_GET['id'] ?? 0);
$pdo = db();

if ($id <= 0) {
    json_fail('Thiếu id item giỏ hàng.', 422);
}

// Lấy item và kiểm tra quyền sở hữu (qua cart)
$st = $pdo->prepare(
    'SELECT ci.id, ci.cart_id, ci.product_id, ci.quantity, ci.variant, c.user_id
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

if ($method === 'PUT') {
    $in = request_json();
    $qty = (int)($in['quantity'] ?? 0);

    if ($qty < 0) {
        json_fail('Số lượng không hợp lệ.', 422);
    }

    $pdo->beginTransaction();
    try {
        if ($qty === 0) {
            $pdo->prepare('DELETE FROM cart_items WHERE id = ?')->execute([$id]);
        } else {
            // Kiểm tra tồn (trừ đi pending reservations của sản phẩm khác)
            $held = $pdo->prepare(
                "SELECT COALESCE(SUM(quantity), 0) FROM reservations
                 WHERE product_id = ? AND status = 'pending' AND reserved_until > NOW()"
            );
            $held->execute([$item['product_id']]);
            $heldQty = (int)$held->fetchColumn();
            $stP = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
            $stP->execute([$item['product_id']]);
            $p = $stP->fetch();
            $available = (int)$p['stock'] - $heldQty;
            if ($qty > $available) {
                $pdo->rollBack();
                json_fail('Chỉ còn ' . $available . ' sản phẩm có thể đặt (đã giữ: ' . $heldQty . ').', 409);
            }
            $pdo->prepare('UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$qty, $id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_fail('Không thể cập nhật giỏ hàng.', 500);
    }

    json_ok(['id' => $id, 'quantity' => $qty], 'Đã cập nhật giỏ hàng.');
}

if ($method === 'DELETE') {
    $pdo->prepare('DELETE FROM cart_items WHERE id = ?')->execute([$id]);
    json_ok(['id' => $id], 'Đã xóa khỏi giỏ hàng.');
}

json_fail('Phương thức không được hỗ trợ.', 405);
