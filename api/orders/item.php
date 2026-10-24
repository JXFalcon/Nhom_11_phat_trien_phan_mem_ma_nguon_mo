<?php
/**
 * /api/orders/item.php?id=N
 * GET – Chi tiết đơn hàng (customer chỉ được xem đơn của mình).
 *       Trả về order + items + payments + shipments + returns + history.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    json_fail('Chỉ chấp nhận GET.', 405);
}
$u = require_auth();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    json_fail('Thiếu id đơn hàng.', 422);
}
$pdo = db();

$st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$st->execute([$id]);
$order = $st->fetch();
if (!$order) {
    json_fail('Không tìm thấy đơn hàng.', 404);
}
if ($u['role'] === 'customer' && (int)$order['user_id'] !== (int)$u['id']) {
    json_fail('Bạn không có quyền xem đơn hàng này.', 403);
}
$order['total_amount'] = (float)$order['total_amount'];

$st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
$st->execute([$id]);
$items = $st->fetchAll();
foreach ($items as &$it) {
    $it['unit_price'] = (float)$it['unit_price'];
    $it['line_total'] = (float)$it['line_total'];
}

$st = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id');
$st->execute([$id]);
$payments = $st->fetchAll();
foreach ($payments as &$p) {
    $p['amount'] = (float)$p['amount'];
}

$st = $pdo->prepare('SELECT * FROM shipments WHERE order_id = ? ORDER BY id DESC');
$st->execute([$id]);
$shipments = $st->fetchAll();

$st = $pdo->prepare(
    'SELECT r.*, (SELECT COALESCE(SUM(refund_amount),0) FROM return_items ri WHERE ri.return_request_id = r.id) AS refund_total
     FROM return_requests r WHERE r.order_id = ? ORDER BY r.id DESC'
);
$st->execute([$id]);
$returns = $st->fetchAll();

$st = $pdo->prepare('SELECT * FROM order_history WHERE order_id = ? ORDER BY id');
$st->execute([$id]);
$history = $st->fetchAll();

json_ok([
    'order' => $order,
    'items' => $items,
    'payments' => $payments,
    'shipments' => $shipments,
    'returns' => $returns,
    'history' => $history,
]);
