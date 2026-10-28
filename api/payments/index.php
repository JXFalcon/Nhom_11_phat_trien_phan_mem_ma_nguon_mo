<?php
/**
 * /api/payments/index.php
 * GET – Danh sách giao dịch thanh toán mô phỏng.
 *       customer: chỉ đơn của mình; admin/staff: tất cả.
 *       ?q=&status=&method=&page=&per_page=
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    json_fail('Chỉ chấp nhận GET.', 405);
}
$u = require_auth();
$pdo = db();

$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$payMethod = (string)($_GET['method'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 50)));

$where = [];
$params = [];
if ($u['role'] === 'customer') {
    $where[] = 'o.user_id = ?';
    $params[] = (int)$u['id'];
}
if ($q !== '') {
    $where[] = '(p.payment_code LIKE ? OR o.order_code LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if (in_array($status, ['thanh_cong', 'cho_xu_ly', 'that_bai', 'hoan_tien'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if (in_array($payMethod, ['cod', 'bank', 'momo'], true)) {
    $where[] = 'p.method = ?';
    $params[] = $payMethod;
}
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stc = $pdo->prepare("SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id $w");
$stc->execute($params);
$total = (int)$stc->fetchColumn();

$offset = ($page - 1) * $perPage;
$st = $pdo->prepare(
    "SELECT p.*, o.order_code, o.customer_name, o.status AS order_status
     FROM payments p JOIN orders o ON o.id = p.order_id
     $w ORDER BY p.id DESC LIMIT $perPage OFFSET $offset"
);
$st->execute($params);
$rows = $st->fetchAll();
foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['order_id'] = (int)$r['order_id'];
    $r['amount'] = (float)$r['amount'];
}
json_ok(['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
