<?php
/**
 * Vòng đời đơn hàng dùng chung: giữ hàng, xác nhận, hoàn kho, hoàn tiền.
 * Mọi hàm giả định đang ở trong transaction của caller.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

class OrderFlowException extends RuntimeException
{
    public int $statusCode;

    public function __construct(string $message, int $statusCode = 409)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }
}

function of_lock_order(PDO $pdo, int $orderId): array
{
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
    $st->execute([$orderId]);
    $order = $st->fetch();
    if (!$order) {
        throw new OrderFlowException('Không tìm thấy đơn hàng.', 404);
    }
    return $order;
}

function of_order_hold_expired(PDO $pdo, array $order): bool
{
    if (($order['status'] ?? '') !== 'cho_xac_nhan' || empty($order['reserved_until'])) {
        return false;
    }
    // So sánh bằng NOW() của MySQL để không lệch timezone với PHP.
    $st = $pdo->prepare('SELECT (reserved_until IS NOT NULL AND reserved_until < NOW()) FROM orders WHERE id = ?');
    $st->execute([(int)$order['id']]);
    return (int)$st->fetchColumn() === 1;
}

function of_lock_product(PDO $pdo, int $productId): array
{
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
    $st->execute([$productId]);
    $product = $st->fetch();
    if (!$product) {
        throw new OrderFlowException('Sản phẩm không tồn tại.', 422);
    }
    return $product;
}

function of_resolve_variant(PDO $pdo, int $productId, ?int $variantId, ?string $variantName, bool $lock = false, bool $required = false): ?array
{
    $suffix = $lock ? ' FOR UPDATE' : '';
    if ($variantId !== null && $variantId > 0) {
        $st = $pdo->prepare("SELECT * FROM product_variants WHERE id = ? AND product_id = ?$suffix");
        $st->execute([$variantId, $productId]);
        $row = $st->fetch();
        if (!$row && $required) {
            throw new OrderFlowException('Biến thể không thuộc sản phẩm này.', 422);
        }
        return $row ?: null;
    }
    $name = trim((string)$variantName);
    if ($name !== '') {
        $st = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? AND name = ?$suffix");
        $st->execute([$productId, $name]);
        $row = $st->fetch();
        if (!$row && $required) {
            throw new OrderFlowException('Biến thể không tồn tại.', 422);
        }
        return $row ?: null;
    }
    return null;
}

function of_variant_required(PDO $pdo, int $productId): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM product_variants WHERE product_id = ?');
    $st->execute([$productId]);
    return (int)$st->fetchColumn() > 0;
}

function of_availability(PDO $pdo, array $product, ?array $variant): array
{
    if ($variant) {
        $heldSt = $pdo->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM reservations
             WHERE product_id = ? AND variant_id = ? AND status = 'pending' AND reserved_until > NOW()"
        );
        $heldSt->execute([(int)$product['id'], (int)$variant['id']]);
        $stock = (int)$variant['stock'];
    } else {
        $heldSt = $pdo->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM reservations
             WHERE product_id = ? AND variant_id IS NULL AND status = 'pending' AND reserved_until > NOW()"
        );
        $heldSt->execute([(int)$product['id']]);
        $stock = (int)$product['stock'];
    }
    $held = (int)$heldSt->fetchColumn();
    return ['stock' => $stock, 'held' => $held, 'available' => $stock - $held];
}

function of_confirm_pending_reservations(PDO $pdo, int $orderId, ?int $actorId, string $note): int
{
    $st = $pdo->prepare(
        "SELECT * FROM reservations WHERE order_id = ? AND status = 'pending'
         ORDER BY product_id, variant_id, id FOR UPDATE"
    );
    $st->execute([$orderId]);
    $rows = $st->fetchAll();
    if (!$rows) {
        return 0;
    }

    $updRes = $pdo->prepare('UPDATE reservations SET status = "confirmed", updated_at = NOW() WHERE id = ?');
    $decProduct = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
    $decVariant = $pdo->prepare('UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?');
    $touchMove = $pdo->prepare(
        "UPDATE inventory_movements SET note = ?
         WHERE order_id = ? AND product_id = ? AND change_type = 'xuat' AND note LIKE 'Giữ hàng%'"
    );

    $expiredSt = $pdo->prepare('SELECT (reserved_until < NOW()) FROM reservations WHERE id = ?');
    $touched = [];
    foreach ($rows as $r) {
        $expiredSt->execute([(int)$r['id']]);
        if ((int)$expiredSt->fetchColumn() === 1) {
            throw new OrderFlowException('Giữ hàng đã hết hạn, không thể xác nhận. Hãy xử lý hết hạn đơn trước.', 409);
        }
        $qty = (int)$r['quantity'];
        if ($qty <= 0) {
            throw new OrderFlowException('Số lượng giữ hàng không hợp lệ.', 422);
        }
        of_lock_product($pdo, (int)$r['product_id']);
        $variant = null;
        if (!empty($r['variant_id'])) {
            $variant = of_resolve_variant($pdo, (int)$r['product_id'], (int)$r['variant_id'], null, true, true);
            $decVariant->execute([$qty, (int)$variant['id'], $qty]);
            if ($decVariant->rowCount() !== 1) {
                throw new OrderFlowException('Không đủ tồn kho cho biến thể "' . $variant['name'] . '".', 409);
            }
        }
        $decProduct->execute([$qty, (int)$r['product_id'], $qty]);
        if ($decProduct->rowCount() !== 1) {
            throw new OrderFlowException('Không đủ tồn kho để xác nhận đơn.', 409);
        }
        if ($variant) {
            $pdo->prepare('UPDATE order_items SET variant_id = ? WHERE order_id = ? AND product_id = ? AND variant = ?')
                ->execute([(int)$variant['id'], $orderId, (int)$r['product_id'], $variant['name']]);
        }
        $updRes->execute([(int)$r['id']]);
        $touched[(int)$r['product_id']] = true;
    }

    foreach (array_keys($touched) as $productId) {
        $touchMove->execute(['Trừ kho thật khi xác nhận ' . $note, $orderId, $productId]);
    }
    return count($rows);
}

function of_ensure_shipment(PDO $pdo, int $orderId): void
{
    $chk = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE order_id = ?');
    $chk->execute([$orderId]);
    if ((int)$chk->fetchColumn() > 0) {
        return;
    }
    $code = next_code($pdo, 'shipments', 'shipment_code', 'VC-');
    $pdo->prepare(
        "INSERT INTO shipments (shipment_code, order_id, carrier, tracking_code, status)
         VALUES (?, ?, ?, ?, 'cho_lay_hang')
         ON DUPLICATE KEY UPDATE id = id"
    )->execute([$code, $orderId, 'Giao Hàng Nhanh (GHN)', 'OF' . random_int(100000000, 999999999)]);
}

function of_confirm_order(PDO $pdo, int $orderId, ?int $actorId, string $note): array
{
    $order = of_lock_order($pdo, $orderId);
    if ($order['status'] !== 'cho_xac_nhan') {
        throw new OrderFlowException(
            'Không thể xác nhận đơn ở trạng thái "' . $order['status'] . '".',
            409
        );
    }
    if (of_order_hold_expired($pdo, $order)) {
        throw new OrderFlowException(
            'Đơn đã hết hạn giữ hàng, không thể xác nhận. Hãy chạy xử lý hết hạn hoặc hủy đơn.',
            409
        );
    }
    $confirmed = of_confirm_pending_reservations($pdo, $orderId, $actorId, $note);
    $pdo->prepare("UPDATE orders SET status = 'da_xac_nhan' WHERE id = ?")->execute([$orderId]);
    $pdo->prepare(
        "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
         VALUES (?, 'cho_xac_nhan', 'da_xac_nhan', ?, ?)"
    )->execute([$orderId, $note, $actorId]);
    of_ensure_shipment($pdo, $orderId);
    return ['confirmed' => $confirmed];
}

function of_restore_order_inventory(PDO $pdo, int $orderId, ?int $actorId, string $reason, string $releaseStatus = 'expired'): array
{
    if (!in_array($releaseStatus, ['released', 'expired'], true)) {
        throw new OrderFlowException('Trạng thái giải phóng giữ hàng không hợp lệ.', 422);
    }
    $order = of_lock_order($pdo, $orderId);
    if ((int)$order['stock_restored'] === 1) {
        return ['restored' => 0, 'already' => true, 'pending' => 0, 'confirmed' => 0];
    }

    $resSt = $pdo->prepare(
        'SELECT * FROM reservations WHERE order_id = ? ORDER BY product_id, variant_id, id FOR UPDATE'
    );
    $resSt->execute([$orderId]);
    $reservations = $resSt->fetchAll();

    $updRes = $pdo->prepare('UPDATE reservations SET status = ?, updated_at = NOW() WHERE id = ?');
    $updProduct = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
    $updVariant = $pdo->prepare('UPDATE product_variants SET stock = stock + ? WHERE id = ?');
    $insMove = $pdo->prepare(
        "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
         VALUES (?, ?, 'huy_don', ?, ?, ?)"
    );
    $releaseMove = $pdo->prepare(
        "UPDATE inventory_movements SET note = CONCAT(COALESCE(note,''), ' - ĐÃ GIẢI PHÓNG giữ hàng, không trừ kho')
         WHERE order_id = ? AND product_id = ? AND change_type = 'xuat' AND note LIKE 'Giữ hàng%'"
    );

    $pending = 0;
    $confirmed = 0;
    if (count($reservations) > 0) {
        foreach ($reservations as $r) {
            if ($r['status'] === 'pending') {
                $updRes->execute([$releaseStatus, (int)$r['id']]);
                $releaseMove->execute([$orderId, (int)$r['product_id']]);
                $pending++;
            } elseif ($r['status'] === 'confirmed') {
                of_lock_product($pdo, (int)$r['product_id']);
                $qty = (int)$r['quantity'];
                $updProduct->execute([$qty, (int)$r['product_id']]);
                if (!empty($r['variant_id'])) {
                    $updVariant->execute([$qty, (int)$r['variant_id']]);
                }
                $insMove->execute([
                    (int)$r['product_id'], $orderId, $qty,
                    $reason . ' (đã xác nhận thanh toán)', $actorId,
                ]);
                $updRes->execute([$releaseStatus, (int)$r['id']]);
                $confirmed++;
            }
        }
    } else {
        $items = $pdo->prepare(
            'SELECT product_id, variant_id, SUM(quantity) AS qty
             FROM order_items WHERE order_id = ? GROUP BY product_id, variant_id ORDER BY product_id'
        );
        $items->execute([$orderId]);
        foreach ($items->fetchAll() as $it) {
            of_lock_product($pdo, (int)$it['product_id']);
            $qty = (int)$it['qty'];
            $updProduct->execute([$qty, (int)$it['product_id']]);
            if (!empty($it['variant_id'])) {
                $updVariant->execute([$qty, (int)$it['variant_id']]);
            }
            $insMove->execute([
                (int)$it['product_id'], $orderId, $qty,
                $reason . ' (đơn cũ không có reservation)', $actorId,
            ]);
        }
    }

    $mark = $pdo->prepare('UPDATE orders SET stock_restored = 1 WHERE id = ? AND (stock_restored = 0 OR stock_restored IS NULL)');
    $mark->execute([$orderId]);
    if ($mark->rowCount() !== 1) {
        throw new OrderFlowException('Đơn này đã được hoàn kho trước đó, không hoàn lại lần nữa.', 409);
    }
    return ['restored' => $confirmed + (count($reservations) === 0 ? 1 : 0), 'already' => false, 'pending' => $pending, 'confirmed' => $confirmed];
}

function of_payment_event_lookup(PDO $pdo, string $key): ?array
{
    if ($key === '') {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM payment_events WHERE idempotency_key = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$key]);
    $row = $st->fetch();
    return $row ?: null;
}

function of_payment_event(PDO $pdo, int $paymentId, int $orderId, ?string $key, string $result, string $outcome, string $detail, ?int $actorId): int
{
    $pdo->prepare(
        'INSERT INTO payment_events (payment_id, order_id, idempotency_key, result, outcome, detail, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $paymentId, $orderId, ($key !== null && $key !== '') ? $key : null, $result, $outcome,
        mb_substr($detail, 0, 255), $actorId,
    ]);
    return (int)$pdo->lastInsertId();
}

/** Đánh dấu các payment đang chờ của đơn là thất bại + ghi payment_event. */
function of_fail_pending_payments(PDO $pdo, int $orderId, ?int $actorId, string $reason): int
{
    $st = $pdo->prepare(
        "SELECT id FROM payments WHERE order_id = ? AND status = 'cho_xu_ly' ORDER BY id FOR UPDATE"
    );
    $st->execute([$orderId]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) {
        return 0;
    }
    $upd = $pdo->prepare(
        "UPDATE payments SET status = 'that_bai', processed_at = NOW(),
                note = CONCAT(COALESCE(note,''), ' - ', ?)
         WHERE id = ?"
    );
    foreach ($ids as $pid) {
        $upd->execute([$reason, (int)$pid]);
        of_payment_event($pdo, (int)$pid, $orderId, null, 'that_bai', 'failed', $reason, $actorId);
    }
    return count($ids);
}

/** Thu tiền COD khi đơn đã giao: payment chờ xử lý -> thanh_cong + event. */
function of_settle_cod_payment(PDO $pdo, int $orderId, ?int $actorId): int
{
    $st = $pdo->prepare(
        "SELECT * FROM payments
         WHERE order_id = ? AND method = 'cod' AND status = 'cho_xu_ly'
         ORDER BY id LIMIT 1 FOR UPDATE"
    );
    $st->execute([$orderId]);
    $pay = $st->fetch();
    if (!$pay) {
        return 0;
    }
    $key = 'COD-ORD-' . $orderId;
    $chk = $pdo->prepare('SELECT id FROM payments WHERE idempotency_key = ? LIMIT 1');
    $chk->execute([$key]);
    $paymentKey = $chk->fetch() ? null : $key;

    $pdo->prepare(
        "UPDATE payments
         SET status = 'thanh_cong', idempotency_key = COALESCE(idempotency_key, ?),
             processed_at = NOW(), note = CONCAT(COALESCE(note,''), ' - Thu tiền COD khi giao hàng')
         WHERE id = ?"
    )->execute([$paymentKey, (int)$pay['id']]);
    of_payment_event(
        $pdo, (int)$pay['id'], $orderId, $key,
        'thanh_cong', 'accepted', 'Thu tiền COD khi giao hàng thành công', $actorId
    );
    return 1;
}

function of_order_paid_refunded(PDO $pdo, int $orderId): array
{
    $st = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'thanh_cong' THEN amount END), 0) AS paid,
            COALESCE(SUM(CASE WHEN status = 'hoan_tien' THEN amount END), 0) AS refunded
         FROM payments WHERE order_id = ?"
    );
    $st->execute([$orderId]);
    $row = $st->fetch();
    return ['paid' => (float)$row['paid'], 'refunded' => (float)$row['refunded']];
}

function of_parse_variant_rows(mixed $raw, float $basePrice): ?array
{
    if ($raw === null) {
        return null;
    }
    if (!is_array($raw)) {
        throw new OrderFlowException('Danh sách biến thể không hợp lệ.', 422);
    }
    $rows = [];
    $seen = [];
    foreach ($raw as $r) {
        if (!is_array($r)) {
            throw new OrderFlowException('Dòng biến thể không hợp lệ.', 422);
        }
        $name = trim((string)($r['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new OrderFlowException('Tên biến thể phải có 1-120 ký tự.', 422);
        }
        $key = mb_strtolower($name);
        if (isset($seen[$key])) {
            throw new OrderFlowException('Tên biến thể bị trùng: ' . $name, 422);
        }
        $seen[$key] = true;
        $priceRaw = $r['price'] ?? null;
        $price = ($priceRaw === null || $priceRaw === '') ? null : (float)$priceRaw;
        if ($price !== null && $price <= 0) {
            throw new OrderFlowException('Giá biến thể "' . $name . '" phải lớn hơn 0.', 422);
        }
        $stock = (int)($r['stock'] ?? 0);
        if ($stock < 0) {
            throw new OrderFlowException('Tồn kho biến thể "' . $name . '" phải là số không âm.', 422);
        }
        $id = (int)($r['id'] ?? 0);
        $rows[] = [
            'id' => $id > 0 ? $id : null,
            'sku' => trim((string)($r['sku'] ?? '')) ?: null,
            'name' => $name,
            'price' => $price ?? $basePrice,
            'stock' => $stock,
        ];
    }
    return $rows;
}

function of_sync_variants(PDO $pdo, int $productId, array $rows): array
{
    $st = $pdo->prepare('SELECT id, name FROM product_variants WHERE product_id = ? ORDER BY id');
    $st->execute([$productId]);
    $existing = [];
    $existingByName = [];
    foreach ($st->fetchAll() as $er) {
        $existing[(int)$er['id']] = $er;
        $existingByName[mb_strtolower((string)$er['name'])] = (int)$er['id'];
    }

    $upd = $pdo->prepare(
        'UPDATE product_variants SET sku = ?, name = ?, price = ?, stock = ? WHERE id = ? AND product_id = ?'
    );
    $ins = $pdo->prepare(
        'INSERT INTO product_variants (product_id, sku, name, price, stock) VALUES (?, ?, ?, ?, ?)'
    );
    $keep = [];
    $totalStock = 0;
    foreach ($rows as $row) {
        $rowId = $row['id'] !== null ? (int)$row['id'] : 0;
        $nameKey = mb_strtolower($row['name']);
        // Client cũ có thể bỏ id khi sửa: khớp lại theo tên để không tạo biến thể trùng.
        if ($rowId <= 0 || !isset($existing[$rowId])) {
            $matchId = $existingByName[$nameKey] ?? 0;
            if ($matchId > 0 && !isset($keep[$matchId])) {
                $rowId = $matchId;
            }
        }
        if ($rowId > 0 && isset($existing[$rowId])
            && isset($existingByName[$nameKey]) && $existingByName[$nameKey] !== $rowId
        ) {
            throw new OrderFlowException('Tên biến thể "' . $row['name'] . '" đã tồn tại.', 409);
        }
        if ($rowId > 0 && isset($existing[$rowId])) {
            $upd->execute([$row['sku'], $row['name'], $row['price'], $row['stock'], $rowId, $productId]);
            $keep[$rowId] = true;
        } else {
            $ins->execute([$productId, $row['sku'], $row['name'], $row['price'], $row['stock']]);
            $keep[(int)$pdo->lastInsertId()] = true;
        }
        $totalStock += (int)$row['stock'];
    }

    foreach ($existing as $vid => $er) {
        if (isset($keep[$vid])) {
            continue;
        }
        $refs = $pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM order_items WHERE variant_id = ?)
                  + (SELECT COUNT(*) FROM reservations WHERE variant_id = ?)
                  + (SELECT COUNT(*) FROM cart_items WHERE variant_id = ?)'
        );
        $refs->execute([$vid, $vid, $vid]);
        if ((int)$refs->fetchColumn() > 0) {
            $pdo->prepare('UPDATE product_variants SET stock = 0 WHERE id = ? AND product_id = ?')
                ->execute([$vid, $productId]);
        } else {
            $pdo->prepare('DELETE FROM product_variants WHERE id = ? AND product_id = ?')
                ->execute([$vid, $productId]);
        }
    }

    $count = (int)$pdo->query(
        'SELECT COUNT(*) FROM product_variants WHERE product_id = ' . $productId
    )->fetchColumn();
    return ['stock' => $count > 0 ? $totalStock : 0, 'count' => $count];
}

function of_refund_order(PDO $pdo, int $orderId, string $key, ?float $wanted, ?int $actorId, string $note): array
{
    $existing = $pdo->prepare('SELECT * FROM payments WHERE idempotency_key = ? LIMIT 1');
    $existing->execute([$key]);
    $found = $existing->fetch();
    if ($found) {
        return ['id' => (int)$found['id'], 'amount' => (float)$found['amount'], 'existing' => true];
    }
    $sum = of_order_paid_refunded($pdo, $orderId);
    $remaining = max(0.0, $sum['paid'] - $sum['refunded']);
    $amount = $wanted === null ? $remaining : min($remaining, max(0.0, $wanted));
    if ($amount <= 0) {
        return ['id' => null, 'amount' => 0.0, 'existing' => false];
    }
    $methodSt = $pdo->prepare(
        "SELECT method FROM payments WHERE order_id = ? AND status = 'thanh_cong' ORDER BY id LIMIT 1"
    );
    $methodSt->execute([$orderId]);
    $method = $methodSt->fetchColumn() ?: 'bank';
    $code = next_code($pdo, 'payments', 'payment_code', 'PM-');
    $pdo->prepare(
        'INSERT INTO payments (payment_code, order_id, amount, method, status, idempotency_key, note, processed_at)
         VALUES (?, ?, ?, ?, "hoan_tien", ?, ?, NOW())'
    )->execute([$code, $orderId, (int)round($amount), $method, $key, mb_substr($note, 0, 255)]);
    $refundId = (int)$pdo->lastInsertId();
    of_payment_event(
        $pdo, $refundId, $orderId, $key,
        'hoan_tien', 'refunded', 'Hoàn tiền ' . (int)round($amount) . '₫: ' . $note, $actorId
    );
    return ['id' => $refundId, 'amount' => $amount, 'existing' => false];
}
