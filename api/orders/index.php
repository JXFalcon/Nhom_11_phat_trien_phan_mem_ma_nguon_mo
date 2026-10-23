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
    $items = $in['items'] ?? [];

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

    $errors = [];
    if (!is_array($items) || count($items) === 0) {
        $errors['items'] = 'Đơn hàng cần ít nhất 1 sản phẩm.';
    }
    if (mb_strlen($customerName) < 2) {
        $errors['customer_name'] = 'Tên khách hàng không hợp lệ.';
    }
    if ($errors) {
        json_fail('Dữ liệu đơn hàng không hợp lệ.', 422, $errors);
    }

    $pdo->beginTransaction();
    try {
        $total = 0;
        $resolved = [];
        foreach ($items as $it) {
            $pid = (int)($it['product_id'] ?? 0);
            $qty = (int)($it['quantity'] ?? 0);
            if ($pid <= 0 || $qty <= 0) {
                $pdo->rollBack();
                json_fail('Sản phẩm hoặc số lượng không hợp lệ.', 422);
            }
            // Khóa dòng sản phẩm để chống tranh hàng cuối
            $st = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
            $st->execute([$pid]);
            $p = $st->fetch();
            if (!$p || $p['status'] !== 'active') {
                $pdo->rollBack();
                json_fail('Sản phẩm #' . $pid . ' không tồn tại hoặc ngừng bán.', 422);
            }
            if ((int)$p['stock'] < $qty) {
                $pdo->rollBack();
                json_fail('Sản phẩm “' . $p['name'] . '” chỉ còn ' . (int)$p['stock'] . ' trong kho.', 409);
            }
            $variant = trim((string)($it['variant'] ?? ''));
            $line = (float)$p['price'] * $qty; // giá lấy từ DB
            $total += $line;
            $resolved[] = [
                'product_id' => $pid,
                'sku' => $p['sku'],
                'name' => $p['name'],
                'variant' => $variant !== '' ? $variant : null,
                'quantity' => $qty,
                'unit_price' => (float)$p['price'],
                'line_total' => $line,
            ];
        }

        $code = next_code($pdo, 'orders', 'order_code', 'DH-');
        $userId = $u['role'] === 'customer' ? (int)$u['id'] : null;
        $ins = $pdo->prepare(
            'INSERT INTO orders (order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status)
             VALUES (?,?,?,?,?,NOW(),?,?,"cho_xac_nhan")'
        );
        $ins->execute([$code, $userId, $customerName, $phone ?: null, $address ?: null, $methodPay, $total]);
        $orderId = (int)$pdo->lastInsertId();

        $insItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $decStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
        $insMove = $pdo->prepare(
            "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
             VALUES (?, ?, 'xuat', ?, ?, ?)"
        );
        foreach ($resolved as $r) {
            $insItem->execute([$orderId, $r['product_id'], $r['sku'], $r['name'], $r['variant'], $r['quantity'], $r['unit_price'], $r['line_total']]);
            $decStock->execute([$r['quantity'], $r['product_id']]); // stock = stock - quantity
            $insMove->execute([$r['product_id'], $orderId, -$r['quantity'], 'Xuất kho khi tạo đơn ' . $code, $u['id']]);
        }

        $pdo->prepare(
            "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (?, NULL, 'cho_xac_nhan', 'Tạo đơn hàng', ?)"
        )->execute([$orderId, $u['id']]);

        // Thanh toán chờ xử lý (mô phỏng)
        $payCode = next_code($pdo, 'payments', 'payment_code', 'GD-');
        $pdo->prepare(
            'INSERT INTO payments (payment_code, order_id, amount, method, status, note) VALUES (?,?,?,?,?,?)'
        )->execute([$payCode, $orderId, $total, $methodPay, 'cho_xu_ly', 'Tạo cùng đơn hàng']);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_fail('Không thể tạo đơn hàng. Vui lòng thử lại.', 500);
    }

    json_ok([
        'id' => $orderId,
        'order_code' => $code,
        'total_amount' => $total,
        'status' => 'cho_xac_nhan',
    ], 'Đã tạo đơn ' . $code . '. Tồn kho đã được giữ.', 201);
}

json_fail('Phương thức không được hỗ trợ.', 405);
