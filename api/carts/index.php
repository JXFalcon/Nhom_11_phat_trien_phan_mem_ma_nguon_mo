<?php
declare(strict_types=1);
/**
 * /api/carts/index.php
 * GET  – Giỏ hàng active của user hiện tại (mỗi user chỉ có 1 giỏ).
 * POST – Thêm / cập nhật sản phẩm vào giỏ.
 *        Body: { product_id, variant_id?, variant?, quantity? }
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();
$u = require_auth();

function getOrCreateCart(PDO $pdo, int $userId): int
{
    $any = $pdo->prepare('SELECT id, status FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $any->execute([$userId]);
    $row = $any->fetch();
    if ($row) {
        if ($row['status'] !== 'active') {
            $pdo->prepare('UPDATE carts SET status = "active" WHERE id = ?')->execute([(int)$row['id']]);
        }
        return (int)$row['id'];
    }
    try {
        $pdo->prepare('INSERT INTO carts (user_id, status) VALUES (?, "active")')->execute([$userId]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        $st = $pdo->prepare('SELECT id FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch();
        if ($row) {
            $pdo->prepare('UPDATE carts SET status = "active" WHERE id = ?')->execute([(int)$row['id']]);
            return (int)$row['id'];
        }
        throw $e;
    }
}

if ($method === 'GET') {
    $cartId = getOrCreateCart($pdo, (int)$u['id']);

    $st = $pdo->prepare(
        "SELECT ci.id, ci.product_id, ci.variant_id, ci.variant, ci.quantity, ci.unit_price,
                p.name AS product_name, p.sku, p.stock AS product_stock, p.emoji,
                pv.name AS variant_name, pv.price AS variant_price, pv.stock AS variant_stock
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         LEFT JOIN product_variants pv ON pv.id = ci.variant_id
         WHERE ci.cart_id = ?
         ORDER BY ci.created_at DESC"
    );
    $st->execute([$cartId]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['product_id'] = (int)$r['product_id'];
        $r['variant_id'] = $r['variant_id'] !== null ? (int)$r['variant_id'] : null;
        $r['quantity'] = (int)$r['quantity'];
        $r['unit_price'] = (float)$r['unit_price'];
        $r['product_stock'] = (int)$r['product_stock'];
        $r['variant_price'] = $r['variant_price'] !== null ? (float)$r['variant_price'] : null;
        $r['variant_stock'] = $r['variant_stock'] !== null ? (int)$r['variant_stock'] : null;
        $r['line_total'] = (float)$r['quantity'] * (float)$r['unit_price'];
    }
    $total = array_reduce($rows, fn($s, $r) => $s + $r['line_total'], 0.0);

    json_ok([
        'cart_id' => $cartId,
        'items' => $rows,
        'total' => (float)$total,
        'item_count' => count($rows),
    ]);
}

if ($method === 'POST') {
    $in = request_json();
    $productId = (int)($in['product_id'] ?? 0);
    $variantId = isset($in['variant_id']) && $in['variant_id'] !== null ? (int)$in['variant_id'] : 0;
    $variantName = trim((string)($in['variant'] ?? ''));
    $qty = (int)($in['quantity'] ?? 1);

    if ($productId <= 0 || $qty <= 0) {
        json_fail('Sản phẩm hoặc số lượng không hợp lệ.', 422);
    }

    $pdo->beginTransaction();
    try {
        $p = of_lock_product($pdo, $productId);
        if ($p['status'] !== 'active') {
            throw new OrderFlowException('Sản phẩm không tồn tại hoặc ngừng bán.', 422);
        }
        $required = of_variant_required($pdo, $productId);
        $variant = of_resolve_variant(
            $pdo, $productId,
            $variantId > 0 ? $variantId : null,
            $variantName !== '' ? $variantName : null,
            false, $required
        );
        if ($required && !$variant) {
            throw new OrderFlowException('Vui lòng chọn biến thể cho sản phẩm này.', 422);
        }
        $variantText = $variant ? $variant['name'] : '';
        $cartId = getOrCreateCart($pdo, (int)$u['id']);
        $avail = of_availability($pdo, $p, $variant);

        $stExist = $pdo->prepare(
            'SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND variant = ? FOR UPDATE'
        );
        $stExist->execute([$cartId, $productId, $variantText]);
        $exist = $stExist->fetch();
        $newQty = ($exist ? (int)$exist['quantity'] : 0) + $qty;
        if ($newQty > $avail['available']) {
            throw new OrderFlowException(
                'Chỉ còn ' . $avail['available'] . ' sản phẩm có thể thêm vào giỏ (đã giữ: ' . $avail['held'] . ').',
                409
            );
        }

        $unitPrice = (float)($variant['price'] ?? $p['price']);
        if ($exist) {
            $pdo->prepare('UPDATE cart_items SET quantity = ?, variant_id = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$newQty, $variant ? (int)$variant['id'] : null, (int)$exist['id']]);
        } else {
            $pdo->prepare(
                'INSERT INTO cart_items (cart_id, product_id, variant_id, variant, quantity, unit_price)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $cartId, $productId,
                $variant ? (int)$variant['id'] : null,
                $variantText, $qty, $unitPrice,
            ]);
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
        json_fail('Không thể thêm vào giỏ hàng.', 500);
    }

    json_ok(null, 'Đã thêm vào giỏ hàng.', 201);
}

json_fail('Phương thức không được hỗ trợ.', 405);
