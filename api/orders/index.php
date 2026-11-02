<?php
/**
 * /api/orders/index.php
 * GET  – Danh sách đơn (customer: đơn của mình; admin/staff: tất cả).
 *        ?q=&status=&from=&to=&page=&per_page=
 * POST – Tạo đơn hàng (customer tạo cho mình; admin/staff tạo cho khách).
 *        Giá & tổng do server tính lại; transaction + trừ tồn + lịch sử.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();

if ($method === 'GET') {
    $u = require_auth();
    $q = trim((string)($_GET['q'] ?? ''));
    $status = (string)($_GET['status'] ?? '');
    $from = date_or_null($_GET['from'] ?? null);
    $to = date_or_null($_GET['to'] ?? null);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 50)));

    $where = [];
    $params = [];
    if ($u['role'] === 'customer') {
        $where[] = 'o.user_id = ?';
        $params[] = (int)$u['id'];
    }
    if ($q !== '') {
        $where[] = '(o.order_code LIKE ? OR o.customer_name LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $statuses = ['cho_xac_nhan', 'da_xac_nhan', 'dang_giao', 'da_giao', 'da_huy'];
    if (in_array($status, $statuses, true)) {
        $where[] = 'o.status = ?';
        $params[] = $status;
    }
    if ($from) {
        $where[] = 'o.order_date >= ?';
        $params[] = $from . ' 00:00:00';
    }
    if ($to) {
        $where[] = 'o.order_date <= ?';
        $params[] = $to . ' 23:59:59';
    }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stc = $pdo->prepare("SELECT COUNT(*) FROM orders o $w");
    $stc->execute($params);
    $total = (int)$stc->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $st = $pdo->prepare(
        "SELECT o.*,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                (SELECT p.status FROM payments p WHERE p.order_id = o.id ORDER BY p.id DESC LIMIT 1) AS payment_status
         FROM orders o $w ORDER BY o.order_date DESC, o.id DESC LIMIT $perPage OFFSET $offset"
    );
    $st->execute($params);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['user_id'] = $r['user_id'] !== null ? (int)$r['user_id'] : null;
        $r['total_amount'] = (float)$r['total_amount'];
    }
    json_ok(['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

if ($method === 'POST') {
    $u = require_auth();
    $in = request_json();
    $customerName = trim((string)($in['customer_name'] ?? ''));
    $phone = trim((string)($in['phone'] ?? ''));
    $address = trim((string)($in['address'] ?? ''));
    $methodPay = (string)($in['payment_method'] ?? 'cod');
    $fromCart = (bool)($in['from_cart'] ?? false);

    if (!in_array($methodPay, ['cod', 'bank', 'momo'], true)) {
        $methodPay = 'cod';
    }
    // Customer chỉ đặt cho mình; admin/staff có thể nhập tên khách
    if ($u['role'] === 'customer') {
        $customerName = $u['full_name'];
        $phone = $phone !== '' ? $phone : ($u['phone'] ?? '');
    } elseif ($customerName === '') {
        $customerName = $u['full_name'];
    }

    $items = [];
    $cartId = 0;
    $cartRawSig = null;
    if ($fromCart) {
        // Lấy items từ giỏ hàng active của user
        $stCart = $pdo->prepare('SELECT id FROM carts WHERE user_id = ? AND status = "active" ORDER BY id DESC LIMIT 1');
        $stCart->execute([(int)$u['id']]);
        $cartRow = $stCart->fetch();
        if (!$cartRow) {
            json_fail('Giỏ hàng trống.', 422);
        }
        $cartId = (int)$cartRow['id'];
        $stItems = $pdo->prepare(
            'SELECT ci.product_id, ci.variant_id, ci.variant, ci.quantity
             FROM cart_items ci WHERE ci.cart_id = ?
             ORDER BY ci.product_id, ci.variant_id, ci.variant'
        );
        $stItems->execute([$cartId]);
        $cartRows = $stItems->fetchAll();
        if (count($cartRows) === 0) {
            json_fail('Giỏ hàng trống.', 422);
        }
        $cartRawSig = json_encode(array_map(static fn(array $r): array => [
            (int)$r['product_id'],
            $r['variant_id'] !== null ? (int)$r['variant_id'] : null,
            (string)$r['variant'],
            (int)$r['quantity'],
        ], $cartRows));
        foreach ($cartRows as $cr) {
            $items[] = [
                'product_id' => (int)$cr['product_id'],
                'variant_id' => $cr['variant_id'] !== null ? (int)$cr['variant_id'] : null,
                'variant' => $cr['variant'],
                'quantity' => (int)$cr['quantity'],
            ];
        }
    } else {
        $items = $in['items'] ?? [];
    }

    $errors = [];
    if (!is_array($items) || count($items) === 0) {
        $errors['items'] = 'Đơn hàng cần ít nhất 1 sản phẩm.';
    }
    if (mb_strlen($customerName) < 2) {
        $errors['customer_name'] = 'Tên khách hàng không hợp lệ.';
    }
    // Xác nhận dữ liệu đặt hàng: SĐT + địa chỉ là bắt buộc để giao được
    if ($phone === '' || !preg_match('/^[0-9+()\s-]{8,15}$/', $phone)) {
        $errors['phone'] = 'Số điện thoại người nhận không hợp lệ (8–15 ký tự số).';
    }
    if (mb_strlen($address) < 5) {
        $errors['address'] = 'Địa chỉ giao hàng cần ít nhất 5 ký tự.';
    }
    if ($errors) {
        json_fail('Dữ liệu đơn hàng không hợp lệ.', 422, $errors);
    }

    $normalized = [];
    foreach ($items as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $qty = (int)($it['quantity'] ?? 0);
        if ($pid <= 0 || $qty <= 0) {
            json_fail('Sản phẩm hoặc số lượng không hợp lệ.', 422);
        }
        $vid = isset($it['variant_id']) && $it['variant_id'] !== null ? (int)$it['variant_id'] : 0;
        $vname = trim((string)($it['variant'] ?? ''));
        $key = $pid . '|' . ($vid > 0 ? 'id:' . $vid : 'name:' . $vname);
        if (!isset($normalized[$key])) {
            $normalized[$key] = [
                'product_id' => $pid,
                'variant_id' => $vid > 0 ? $vid : null,
                'variant' => $vname !== '' ? $vname : null,
                'quantity' => 0,
            ];
        }
        $normalized[$key]['quantity'] += $qty;
    }
    $items = array_values($normalized);
    usort($items, static fn(array $a, array $b): int => $a['product_id'] <=> $b['product_id']);

    $pdo->beginTransaction();
    try {
        if ($fromCart) {
            $lockCart = $pdo->prepare('SELECT id FROM carts WHERE id = ? AND status = "active" FOR UPDATE');
            $lockCart->execute([$cartId]);
            if (!$lockCart->fetch()) {
                throw new OrderFlowException('Giỏ hàng đã được xử lý hoặc không còn hiệu lực. Vui lòng tải lại giỏ hàng.', 409);
            }
            $lockItems = $pdo->prepare(
                'SELECT product_id, variant_id, variant, quantity
                 FROM cart_items WHERE cart_id = ?
                 ORDER BY product_id, variant_id, variant FOR UPDATE'
            );
            $lockItems->execute([$cartId]);
            $nowSig = json_encode(array_map(static fn(array $r): array => [
                (int)$r['product_id'],
                $r['variant_id'] !== null ? (int)$r['variant_id'] : null,
                (string)$r['variant'],
                (int)$r['quantity'],
            ], $lockItems->fetchAll()));
            if ($nowSig !== $cartRawSig) {
                throw new OrderFlowException('Giỏ hàng vừa thay đổi, vui lòng tải lại và đặt hàng lại.', 409);
            }
        }

        $total = 0;
        $resolved = [];
        foreach ($items as $it) {
            $pid = (int)$it['product_id'];
            $qty = (int)$it['quantity'];
            $p = of_lock_product($pdo, $pid);
            if ($p['status'] !== 'active') {
                throw new OrderFlowException('Sản phẩm “' . $p['name'] . '” đã ngừng bán.', 422);
            }
            $variant = of_resolve_variant(
                $pdo, $pid, $it['variant_id'], $it['variant'], false,
                of_variant_required($pdo, $pid)
            );
            if (of_variant_required($pdo, $pid) && !$variant) {
                throw new OrderFlowException('Vui lòng chọn biến thể cho sản phẩm “' . $p['name'] . '”.', 422);
            }
            $avail = of_availability($pdo, $p, $variant);
            if ($qty > $avail['available']) {
                throw new OrderFlowException(
                    'Sản phẩm “' . $p['name'] . '” chỉ còn ' . $avail['available'] . ' có thể đặt (tồn: ' . $avail['stock'] . ', đã giữ: ' . $avail['held'] . ').',
                    409
                );
            }
            // Giá chốt tại thời điểm đặt: luôn lấy giá hiện tại của SP/biến thể,
            // không dùng giá cũ đã lưu trong giỏ.
            $unitPrice = (float)($variant['price'] ?? $p['price']);
            if ($unitPrice <= 0) {
                throw new OrderFlowException('Giá sản phẩm không hợp lệ.', 422);
            }
            $line = $unitPrice * $qty;
            $total += $line;
            $resolved[] = [
                'product_id' => $pid,
                'variant_id' => $variant ? (int)$variant['id'] : null,
                'sku' => $p['sku'],
                'name' => $p['name'],
                'variant' => $variant ? $variant['name'] : null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $line,
            ];
        }

        $code = next_code($pdo, 'orders', 'order_code', 'DH-');
        $userId = $u['role'] === 'customer' ? (int)$u['id'] : null;
        $ins = $pdo->prepare(
            'INSERT INTO orders (order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, reserved_until)
             VALUES (?,?,?,?,?,NOW(),?,?,"cho_xac_nhan", DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
        );
        $ins->execute([$code, $userId, $customerName, $phone ?: null, $address ?: null, $methodPay, $total]);
        $orderId = (int)$pdo->lastInsertId();

        $insItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, variant_id, sku, product_name, variant, quantity, unit_price, line_total)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $insRes = $pdo->prepare(
            'INSERT INTO reservations (reservation_code, order_id, user_id, product_id, variant_id, variant, quantity, unit_price, status, reserved_until)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending", DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
        );
        $insMove = $pdo->prepare(
            "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
             VALUES (?, ?, 'xuat', ?, ?, ?)"
        );
        foreach ($resolved as $r) {
            $insItem->execute([
                $orderId, $r['product_id'], $r['variant_id'], $r['sku'], $r['name'],
                $r['variant'], $r['quantity'], $r['unit_price'], $r['line_total'],
            ]);
            $resCode = next_code($pdo, 'reservations', 'reservation_code', 'RV-');
            $insRes->execute([
                $resCode, $orderId, $userId, $r['product_id'], $r['variant_id'],
                $r['variant'], $r['quantity'], $r['unit_price'],
            ]);
            $insMove->execute([
                $r['product_id'], $orderId, -$r['quantity'],
                'Giữ hàng khi tạo đơn ' . $code, $u['id'],
            ]);
        }

        if ($fromCart) {
            $pdo->prepare('UPDATE carts SET status = "checked_out", updated_at = NOW() WHERE id = ?')
                ->execute([$cartId]);
            $pdo->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cartId]);
        }

        $pdo->prepare(
            "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (?, NULL, 'cho_xac_nhan', 'Tạo đơn hàng và giữ hàng', ?)"
        )->execute([$orderId, $u['id']]);

        $payCode = next_code($pdo, 'payments', 'payment_code', 'GD-');
        $pdo->prepare(
            'INSERT INTO payments (payment_code, order_id, amount, method, status, note) VALUES (?,?,?,?,?,?)'
        )->execute([$payCode, $orderId, $total, $methodPay, 'cho_xu_ly', 'Tạo cùng đơn hàng']);
        of_payment_event(
            $pdo, (int)$pdo->lastInsertId(), $orderId, null,
            'cho_xu_ly', 'created', 'Tạo payment chờ xử lý cùng đơn hàng', (int)$u['id']
        );

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
        json_fail('Không thể tạo đơn hàng. Vui lòng thử lại.', 500);
    }

    json_ok([
        'id' => $orderId,
        'order_code' => $code,
        'total_amount' => $total,
        'status' => 'cho_xac_nhan',
        'from_cart' => $fromCart,
    ], 'Đã tạo đơn ' . $code . '. Hàng đã được giữ trong 30 phút.', 201);
}

json_fail('Phương thức không được hỗ trợ.', 405);
