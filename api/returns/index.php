<?php
/**
 * /api/returns/index.php
 * GET  – Danh sách yêu cầu đổi trả (customer: của mình; admin/staff: tất cả)
 * POST – Tạo yêu cầu đổi trả (customer cho đơn đã giao của mình).
 *        Hỗ trợ 1 dòng: {order_id, order_item_id, quantity, reason}
 *        hoặc nhiều dòng: {order_id, items: [{order_item_id, quantity}], reason}
 *        Kiểm tra: đơn thuộc về user, đã giao, SL trả ≤ SL đã giao − SL đã trả.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();

if ($method === 'GET') {
    $u = require_auth();
    $q = trim((string)($_GET['q'] ?? ''));
    $status = (string)($_GET['status'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 50)));

    $where = [];
    $params = [];
    if ($u['role'] === 'customer') {
        $where[] = 'r.user_id = ?';
        $params[] = (int)$u['id'];
    }
    if ($q !== '') {
        $where[] = '(r.return_code LIKE ? OR r.order_id IN (SELECT id FROM orders WHERE order_code LIKE ?) OR r.customer_name LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $statuses = ['cho_xu_ly', 'da_chap_nhan', 'tu_choi', 'da_hoan_tat'];
    if (in_array($status, $statuses, true)) {
        $where[] = 'r.status = ?';
        $params[] = $status;
    }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stc = $pdo->prepare("SELECT COUNT(*) FROM return_requests r $w");
    $stc->execute($params);
    $total = (int)$stc->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $st = $pdo->prepare(
        "SELECT r.*,
                (SELECT o.order_code FROM orders o WHERE o.id = r.order_id) AS order_code,
                (SELECT COALESCE(SUM(ri.refund_amount),0) FROM return_items ri WHERE ri.return_request_id = r.id) AS refund_total,
                (SELECT COALESCE(SUM(ri.quantity_requested),0) FROM return_items ri WHERE ri.return_request_id = r.id) AS qty_requested_total,
                (SELECT COALESCE(SUM(ri.quantity_accepted),0) FROM return_items ri WHERE ri.return_request_id = r.id) AS qty_accepted_total,
                (SELECT ri.product_name FROM return_items ri WHERE ri.return_request_id = r.id LIMIT 1) AS product_name,
                (SELECT ri.quantity_requested FROM return_items ri WHERE ri.return_request_id = r.id LIMIT 1) AS qty_requested,
                (SELECT ri.quantity_accepted FROM return_items ri WHERE ri.return_request_id = r.id LIMIT 1) AS qty_accepted,
                (SELECT ri.unit_price FROM return_items ri WHERE ri.return_request_id = r.id LIMIT 1) AS unit_price,
                (SELECT oi.quantity FROM order_items oi JOIN return_items ri ON ri.order_item_id = oi.id WHERE ri.return_request_id = r.id LIMIT 1) AS qty_delivered
         FROM return_requests r $w ORDER BY r.id DESC LIMIT $perPage OFFSET $offset"
    );
    $st->execute($params);
    $rows = $st->fetchAll();

    $lineByRequest = [];
    if ($rows) {
        $ids = array_map(static fn(array $r): int => (int)$r['id'], $rows);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $lineSt = $pdo->prepare(
            "SELECT ri.*, oi.quantity AS delivered_qty, oi.variant
             FROM return_items ri
             JOIN order_items oi ON oi.id = ri.order_item_id
             WHERE ri.return_request_id IN ($marks)
             ORDER BY ri.id"
        );
        $lineSt->execute($ids);
        foreach ($lineSt->fetchAll() as $line) {
            $rid = (int)$line['return_request_id'];
            $lineByRequest[$rid][] = [
                'id' => (int)$line['id'],
                'order_item_id' => (int)$line['order_item_id'],
                'product_id' => (int)$line['product_id'],
                'product_name' => $line['product_name'],
                'variant' => $line['variant'],
                'quantity_requested' => (int)$line['quantity_requested'],
                'quantity_accepted' => (int)$line['quantity_accepted'],
                'quantity_delivered' => (int)$line['delivered_qty'],
                'unit_price' => (float)$line['unit_price'],
                'refund_amount' => (float)$line['refund_amount'],
                'restock' => (int)$line['restock'] === 1,
            ];
        }
    }

    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['order_id'] = (int)$r['order_id'];
        $r['refund_total'] = (float)$r['refund_total'];
        $r['unit_price'] = $r['unit_price'] !== null ? (float)$r['unit_price'] : null;
        $r['items'] = $lineByRequest[(int)$r['id']] ?? [];
    }
    unset($r);
    json_ok(['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

if ($method === 'POST') {
    $u = require_auth();
    $in = request_json();
    $orderId = (int)($in['order_id'] ?? 0);
    $reason = trim((string)($in['reason'] ?? ''));

    $rawItems = $in['items'] ?? null;
    $legacySingle = !is_array($rawItems) || count($rawItems) === 0;
    if ($legacySingle) {
        $rawItems = [[
            'order_item_id' => $in['order_item_id'] ?? 0,
            'quantity' => $in['quantity'] ?? 0,
        ]];
        // Tương thích client cũ: không truyền order_item_id thì lấy dòng đầu của đơn.
        if ((int)$rawItems[0]['order_item_id'] <= 0 && $orderId > 0) {
            $first = $pdo->prepare('SELECT id FROM order_items WHERE order_id = ? ORDER BY id LIMIT 1');
            $first->execute([$orderId]);
            $rawItems[0]['order_item_id'] = (int)$first->fetchColumn();
        }
    }

    $errors = [];
    if ($orderId <= 0) {
        $errors['order_id'] = 'Thiếu đơn hàng.';
    }
    if (mb_strlen($reason) < 5) {
        $errors['reason'] = 'Lý do phải có ít nhất 5 ký tự.';
    }

    $requested = [];
    foreach ($rawItems as $idx => $raw) {
        $itemId = (int)($raw['order_item_id'] ?? 0);
        $qty = (int)($raw['quantity'] ?? 0);
        if ($itemId <= 0) {
            $errors['items.' . $idx . '.order_item_id'] = 'Thiếu order_item_id.';
        }
        if ($qty < 1) {
            $errors['items.' . $idx . '.quantity'] = 'Số lượng trả phải ≥ 1.';
        }
        if ($itemId > 0 && $qty > 0) {
            $requested[$itemId] = ($requested[$itemId] ?? 0) + $qty;
        }
    }
    if (!$requested) {
        $errors['items'] = 'Cần ít nhất 1 sản phẩm hợp lệ để trả.';
    }
    if ($errors) {
        json_fail('Dữ liệu yêu cầu đổi trả không hợp lệ.', 422, $errors);
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $st->execute([$orderId]);
        $order = $st->fetch();
        if (!$order) {
            $pdo->rollBack();
            json_fail('Không tìm thấy đơn hàng.', 404);
        }
        if ($u['role'] === 'customer' && (int)$order['user_id'] !== (int)$u['id']) {
            $pdo->rollBack();
            json_fail('Bạn chỉ được tạo yêu cầu đổi trả cho đơn hàng của chính mình.', 403);
        }
        if ($order['status'] !== 'da_giao') {
            $pdo->rollBack();
            json_fail('Chỉ đơn hàng đã giao mới đủ điều kiện đổi trả.', 409);
        }

        $itemIds = array_keys($requested);
        $marks = implode(',', array_fill(0, count($itemIds), '?'));
        $itSt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? AND id IN ($marks) FOR UPDATE");
        $itSt->execute(array_merge([$orderId], $itemIds));
        $itemById = [];
        foreach ($itSt->fetchAll() as $item) {
            $itemById[(int)$item['id']] = $item;
        }
        if (count($itemById) !== count($itemIds)) {
            $pdo->rollBack();
            json_fail('Có sản phẩm không thuộc đơn hàng này.', 422);
        }

        // SL đã trả/đang chờ của từng order_item
        $alreadySt = $pdo->prepare(
            "SELECT ri.order_item_id, COALESCE(SUM(ri.quantity_requested), 0) AS already
             FROM return_items ri
             JOIN return_requests r ON r.id = ri.return_request_id
             WHERE r.order_id = ? AND ri.order_item_id IN ($marks)
               AND r.status IN ('cho_xu_ly','da_chap_nhan','da_hoan_tat')
             GROUP BY ri.order_item_id"
        );
        $alreadySt->execute(array_merge([$orderId], $itemIds));
        $alreadyById = [];
        foreach ($alreadySt->fetchAll() as $row) {
            $alreadyById[(int)$row['order_item_id']] = (int)$row['already'];
        }

        foreach ($requested as $itemId => $qty) {
            $item = $itemById[$itemId];
            $already = $alreadyById[$itemId] ?? 0;
            $remain = (int)$item['quantity'] - $already;
            if ($qty > $remain) {
                $pdo->rollBack();
                json_fail(
                    'Sản phẩm “' . $item['product_name'] . '”: số lượng trả (' . $qty . ') vượt quá số còn có thể trả (' . $remain . '). Đã giao ' . $item['quantity'] . ', đã yêu cầu/hoàn ' . $already . '.',
                    409
                );
            }
        }

        $code = next_code($pdo, 'return_requests', 'return_code', 'DT-');
        $pdo->prepare(
            'INSERT INTO return_requests (return_code, order_id, user_id, customer_name, reason, status) VALUES (?,?,?,?,?, "cho_xu_ly")'
        )->execute([$code, $orderId, $u['role'] === 'customer' ? (int)$u['id'] : null, $order['customer_name'], $reason]);
        $rid = (int)$pdo->lastInsertId();

        $insItem = $pdo->prepare(
            'INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock)
             VALUES (?,?,?,?,?,0,?,0,1)'
        );
        foreach ($requested as $itemId => $qty) {
            $item = $itemById[$itemId];
            $insItem->execute([$rid, $itemId, $item['product_id'], $item['product_name'], $qty, $item['unit_price']]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_fail('Không thể tạo yêu cầu đổi trả.', 500);
    }

    json_ok([
        'id' => $rid,
        'return_code' => $code,
        'items_count' => count($requested),
        'quantity_requested' => array_sum($requested),
    ], 'Đã gửi yêu cầu đổi trả.', 201);
}

json_fail('Phương thức không được hỗ trợ.', 405);
