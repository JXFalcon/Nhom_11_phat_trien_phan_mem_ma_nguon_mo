<?php
/**
 * /api/shipments/index.php
 * GET – Danh sách vận đơn. customer: đơn của mình; admin/staff: tất cả.
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
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 50)));

$where = [];
$params = [];
if ($u['role'] === 'customer') {
    $where[] = 'o.user_id = ?';
    $params[] = (int)$u['id'];
}
if ($q !== '') {
    $where[] = '(s.tracking_code LIKE ? OR o.order_code LIKE ? OR s.carrier LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if (in_array($status, ['cho_lay_hang', 'dang_van_chuyen', 'da_giao', 'that_bai'], true)) {
    $where[] = 's.status = ?';
    $params[] = $status;
}
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stc = $pdo->prepare("SELECT COUNT(*) FROM shipments s JOIN orders o ON o.id = s.order_id $w");
$stc->execute($params);
$total = (int)$stc->fetchColumn();

$offset = ($page - 1) * $perPage;
$st = $pdo->prepare(
    "SELECT s.*, o.order_code, o.customer_name, o.status AS order_status
     FROM shipments s JOIN orders o ON o.id = s.order_id
     $w ORDER BY s.id DESC LIMIT $perPage OFFSET $offset"
);
$st->execute($params);
$rows = $st->fetchAll();
foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['order_id'] = (int)$r['order_id'];
}
json_ok(['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
