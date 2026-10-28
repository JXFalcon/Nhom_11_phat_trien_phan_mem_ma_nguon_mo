<?php
/**
 * /api/reports/summary.php
 * GET – Tổng hợp cho Dashboard & trang Báo cáo.
 *       ?days=7|30|60|all&view=dashboard|reports
 * Trả: stats, revenue_daily, status_counts, top_products, payment_methods, recent_orders.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    json_fail('Chỉ chấp nhận GET.', 405);
}
$u = require_role('admin', 'staff');
$pdo = db();

/* Xuất báo cáo CSV */
if ((string)($_GET['export'] ?? '') === 'csv') {
    $daysParam = (string)($_GET['days'] ?? 'all');
    $days = in_array($daysParam, ['7', '30', '60'], true) ? (int)$daysParam : null;

    $dayCond = '';
    $dayParams = [];
    if ($days !== null) {
        $dayCond = ' AND o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $dayParams[] = $days;
    }

    $filename = 'bao_cao_don_hang_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
    fputcsv($out, ['Mã đơn', 'Khách hàng', 'Ngày đặt', 'Số tiền', 'Phương thức', 'Trạng thái', 'Thanh toán'], null, ',');

    $st = $pdo->prepare(
        "SELECT o.order_code, o.customer_name, o.order_date, o.total_amount, o.payment_method, o.status,
                (SELECT p.status FROM payments p WHERE p.order_id = o.id ORDER BY p.id DESC LIMIT 1) AS payment_status
         FROM orders o WHERE 1=1 $dayCond ORDER BY o.order_date DESC"
    );
    $st->execute($dayParams);
    foreach ($st->fetchAll() as $row) {
        fputcsv($out, [
            $row['order_code'],
            $row['customer_name'],
            $row['order_date'],
            $row['total_amount'],
            $row['payment_method'],
            $row['status'],
            $row['payment_status'],
        ], null, ',');
    }
    fclose($out);
    exit;
}

$daysParam = (string)($_GET['days'] ?? 'all');
$days = in_array($daysParam, ['7', '30', '60'], true) ? (int)$daysParam : null;

$dayCond = '';
$dayParams = [];
if ($days !== null) {
    $dayCond = ' AND o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)';
    $dayParams[] = $days;
}

/* Thống kê tổng quan */
$stats = [];
$st = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(CASE WHEN status <> 'da_huy' THEN total_amount END), 0) AS revenue,
        SUM(status = 'cho_xac_nhan') AS cho_xac_nhan,
        SUM(status = 'da_xac_nhan') AS da_xac_nhan,
        SUM(status = 'dang_giao') AS dang_giao,
        SUM(status = 'da_giao') AS da_giao,
        SUM(status = 'da_huy') AS da_huy
     FROM orders o WHERE 1=1 $dayCond"
);
$st->execute($dayParams);
$stats = $st->fetch();
$stats['revenue'] = (float)$stats['revenue'];

// Hoàn tiền mô phỏng ghi trong payments (status hoan_tien) – trừ ra khỏi doanh thu
$stP = $pdo->prepare(
    "SELECT COALESCE(SUM(p.amount), 0) AS paid_refunds
     FROM payments p JOIN orders o ON o.id = p.order_id
     WHERE p.status = 'hoan_tien' $dayCond"
);
$stP->execute($dayParams);
$paidRefunds = (float)$stP->fetchColumn();
$stats['paid_refunds'] = $paidRefunds;
$stats['revenue_net'] = $stats['revenue'] - $paidRefunds;

$st = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_returns,
        SUM(r.status = 'cho_xu_ly') AS pending_returns,
        COALESCE(SUM((SELECT SUM(refund_amount) FROM return_items ri WHERE ri.return_request_id = r.id)), 0) AS refund_total
     FROM return_requests r
     JOIN orders o ON o.id = r.order_id WHERE 1=1 $dayCond"
);
$st->execute($dayParams);
$rs = $st->fetch();
$stats['total_returns'] = (int)$rs['total_returns'];
$stats['pending_returns'] = (int)$rs['pending_returns'];
$stats['refund_total'] = (float)$rs['refund_total'];
$stats['return_rate'] = $stats['da_giao'] > 0
    ? round($stats['total_returns'] / max(1, $stats['da_giao']) * 100, 1)
    : 0.0;

/* Doanh thu theo ngày */
$bucketDays = $days !== null ? min($days, 14) : 14;
$st = $pdo->prepare(
    "SELECT DATE(o.order_date) AS d,
            COALESCE(SUM(CASE WHEN o.status <> 'da_huy' THEN o.total_amount END), 0) AS revenue,
            COUNT(*) AS orders
     FROM orders o
     WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY) $dayCond
     GROUP BY DATE(o.order_date) ORDER BY d"
);
$p = [$bucketDays];
foreach ($dayParams as $dp) {
    $p[] = $dp;
}
$st->execute($p);
$dailyMap = [];
foreach ($st->fetchAll() as $row) {
    $dailyMap[$row['d']] = [
        'label' => date('d/m', strtotime($row['d'])),
        'value' => (float)$row['revenue'],
        'orders' => (int)$row['orders'],
    ];
}
// Đủ 14 ngày liên tiếp (điền 0)
$daily = [];
$cur = new DateTime($days !== null ? "-{$bucketDays} days" : '-13 days');
$today = new DateTime('today');
while ($cur <= $today) {
    $key = $cur->format('Y-m-d');
    $daily[] = $dailyMap[$key] ?? ['label' => $cur->format('d/m'), 'value' => 0.0, 'orders' => 0];
    $cur->modify('+1 day');
}

/* Cơ cấu trạng thái */
$st = $pdo->prepare(
    "SELECT status, COUNT(*) AS c FROM orders o WHERE 1=1 $dayCond GROUP BY status"
);
$st->execute($dayParams);
$statusCounts = array_fill_keys(['cho_xac_nhan', 'da_xac_nhan', 'dang_giao', 'da_giao', 'da_huy'], 0);
foreach ($st->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int)$row['c'];
}

/* Top sản phẩm */
$st = $pdo->prepare(
    "SELECT oi.product_id, oi.product_name, p.emoji,
            SUM(oi.quantity) AS sold, SUM(oi.line_total) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE o.status <> 'da_huy' $dayCond
     GROUP BY oi.product_id, oi.product_name, p.emoji
     ORDER BY revenue DESC LIMIT 6"
);
$st->execute($dayParams);
$topProducts = array_map(fn($r) => [
    'name' => $r['product_name'],
    'emoji' => $r['emoji'] ?? '📦',
    'qty' => (int)$r['sold'],
    'revenue' => (float)$r['revenue'],
], $st->fetchAll());

/* Doanh thu theo phương thức */
$st = $pdo->prepare(
    "SELECT o.payment_method, COALESCE(SUM(o.total_amount),0) AS revenue
     FROM orders o WHERE o.status <> 'da_huy' $dayCond
     GROUP BY o.payment_method"
);
$st->execute($dayParams);
$methods = array_fill_keys(['cod', 'bank', 'momo'], 0.0);
foreach ($st->fetchAll() as $row) {
    $methods[$row['payment_method']] = (float)$row['revenue'];
}

/* Đơn gần đây (dashboard) */
$recent = [];
if (($_GET['view'] ?? '') === 'dashboard') {
    $cond = $u['role'] === 'customer' ? ' AND o.user_id = ?' : '';
    $rp = $u['role'] === 'customer' ? [(int)$u['id']] : [];
    $st = $pdo->prepare(
        "SELECT o.id, o.order_code, o.customer_name, o.order_date, o.total_amount, o.status
         FROM orders o WHERE 1=1 $cond ORDER BY o.order_date DESC LIMIT 6"
    );
    $st->execute($rp);
    $recent = array_map(fn($r) => [
        'id' => (int)$r['id'],
        'order_code' => $r['order_code'],
        'customer_name' => $r['customer_name'],
        'order_date' => $r['order_date'],
        'total_amount' => (float)$r['total_amount'],
        'status' => $r['status'],
    ], $st->fetchAll());
}

json_ok([
    'stats' => $stats,
    'revenue_daily' => $daily,
    'status_counts' => $statusCounts,
    'top_products' => $topProducts,
    'payment_methods' => $methods,
    'recent_orders' => $recent,
    'range_days' => $days,
]);
