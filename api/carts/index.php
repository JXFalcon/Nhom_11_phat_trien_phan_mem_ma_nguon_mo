<?php
declare(strict_types=1);
/**
 * /api/carts/index.php
 * GET  – Danh sách giỏ hàng của user hiện tại (mỗi user chỉ có 1 giỏ active).
 * POST – Thêm / cập nhật sản phẩm vào giỏ.
 *        Body: { product_id, variant?, quantity? }
 */

require_once __DIR__ . '/../../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();
$u = require_auth();

/* Lấy hoặc tạo giỏ active của user */
/* Lấy hoặc tạo giỏ active của user */
function getOrCreateCart(PDO $pdo, int $userId): int
{
    // Tìm giỏ hiện có của user (mọi trạng thái) — user chỉ được phép có 1 giỏ
    // (ràng buộc uq_carts_user), nên giỏ cũ chuyển thành đơn có thể hết 'active'.
    $any = $pdo->prepare('SELECT id, status FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $any->execute([$userId]);
    $row = $any->fetch();
    if ($row) {
        if ($row['status'] !== 'active') {
            $act = $pdo->prepare('UPDATE carts SET status = "active" WHERE id = ?');
            $act->execute([(int)$row['id']]);
        }
        return (int)$row['id'];
    }

    // Nếu chưa có, tiến hành tạo mới
    try {
        $ins = $pdo->prepare('INSERT INTO carts (user_id, status) VALUES (?, "active")');
        $ins->execute([$userId]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        // Phòng hờ nếu có tranh chấp, query lại giỏ hiện có (mọi trạng thái)
        $st = $pdo->prepare('SELECT id FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch();
        if ($row) {
            $act = $pdo->prepare('UPDATE carts SET status = "active" WHERE id = ?');
            $act->execute([(int)$row['id']]);
            return (int)$row['id'];
        }
        throw $e;
    }
}

if ($method === 'GET') {
    $cartId = getOrCreateCart($pdo, (int)$u['id']);

    $st = $pdo->prepare(
        "SELECT ci.id, ci.product_id, ci.variant, ci.quantity, ci.unit_price,
                p.name AS product_name, p.sku, p.stock AS product_stock, p.emoji
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         WHERE ci.cart_id = ?
         ORDER BY ci.created_at DESC"
    );
    $st->execute([$cartId]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['product_id'] = (int)$r['product_id'];
        $r['quantity'] = (int)$r['quantity'];
        $r['unit_price'] = (float)$r['unit_price'];
        $r['product_stock'] = (int)$r['product_stock'];
        $r['line_total'] = (float)$r['quantity'] * (float)$r['unit_price'];
    }
    $total = array_reduce($rows, fn($s, $r) => $s + $r['line_total'], 0.0);

    json_ok(['cart_id' => $cartId, 'items' => $rows, 'total' => (float)$total, 'item_count' => count($rows)]);}

if ($method === 'POST') {
    $in = request_json();
    $productId = (int)($in['product_id'] ?? 0);
    $variant = trim((string)($in['variant'] ?? ''));
    $qty = (int)($in['quantity'] ?? 1);

    if ($productId <= 0 || $qty <= 0) {
        json_fail('Sản phẩm hoặc số lượng không hợp lệ.', 422);
    }

    $st = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
    $st->execute([$productId]);
    $p = $st->fetch();
    if (!$p || $p['status'] !== 'active') {
        json_fail('Sản phẩm không tồn tại hoặc ngừng bán.', 422);
    }

    $cartId = getOrCreateCart($pdo, (int)$u['id']);

    $pdo->beginTransaction();
    try {
        // Kiểm tra đã có item này trong giỏ chưa
        $stExist = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND variant = ?');
        $stExist->execute([$cartId, $productId, $variant !== '' ? $variant : null]);
        $exist = $stExist->fetch();

        if ($exist) {
            $newQty = (int)$exist['quantity'] + $qty;
            // Kiểm tra tồn (trừ đi pending reservations)
            $held = $pdo->prepare(
                "SELECT COALESCE(SUM(quantity), 0) FROM reservations
                 WHERE product_id = ? AND status = 'pending' AND reserved_until > NOW()"
            );
            $held->execute([$productId]);
            $heldQty = (int)$held->fetchColumn();
            $available = (int)$p['stock'] - $heldQty;
            if ($newQty > $available) {
                $pdo->rollBack();
                json_fail('Chỉ còn ' . $available . ' sản phẩm có thể thêm vào giỏ (đã giữ: ' . $heldQty . ').', 409);
            }
            $pdo->prepare('UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$newQty, (int)$exist['id']]);
        } else {
            // Kiểm tra tồn
            $held = $pdo->prepare(
                "SELECT COALESCE(SUM(quantity), 0) FROM reservations
                 WHERE product_id = ? AND status = 'pending' AND reserved_until > NOW()"
            );
            $held->execute([$productId]);
            $heldQty = (int)$held->fetchColumn();
            $available = (int)$p['stock'] - $heldQty;
            if ($qty > $available) {
                $pdo->rollBack();
                json_fail('Chỉ còn ' . $available . ' sản phẩm có thể thêm vào giỏ (đã giữ: ' . $heldQty . ').', 409);
            }
            $pdo->prepare(
                'INSERT INTO cart_items (cart_id, product_id, variant, quantity, unit_price)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$cartId, $productId, $variant !== '' ? $variant : null, $qty, (float)$p['price']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_fail('Không thể thêm vào giỏ hàng.', 500);
    }

    json_ok(null, 'Đã thêm vào giỏ hàng.', 201);
}

json_fail('Phương thức không được hỗ trợ.', 405);
