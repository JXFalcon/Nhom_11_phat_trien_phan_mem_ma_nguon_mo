<?php
/**
 * /api/reports/reconcile.php
 * GET – Đối chiếu dữ liệu nghiệp vụ (báo cáo kiểm soát):
 *   1. Hàng tồn:  stock thật vs Σ inventory_movements (loại trừ các hold chưa trừ).
 *   2. Tiền mô phỏng: payments ↔ orders ↔ trả hàng (thanh_cong/hoan_tien/that_bai).
 *   3. Giữ hàng: reservations pending không được vượt quà tồn.
 * Query: ?limit=50 (số dòng lệch tối đa trả về)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET', 'POST'], true)) {
    json_fail('Chỉ chấp nhận GET/POST.', 405);
}

$u = require_role('admin', 'staff');
$limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
$pdo = db();

/* ---------- Hành động (POST): cân bằng baseline tồn kho ---------- */
/* Dữ liệu seed không ghi dòng 'nhap' khởi điểm nên Σ movement ≠ stock.
   Action này thêm 1 dòng 'dieu_chinh' cho mỗi sản phẩm lệch để đối chiếu
   sau đó trở nên đáng tin. Chỉ chạy bằng tay từ trang Báo cáo. */
$baseline = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in = request_json();
    if (($in['action'] ?? '') === 'baseline') {
        $sumRows = $pdo->query(
            "SELECT m.product_id, SUM(m.quantity) AS net
             FROM inventory_movements m
             WHERE NOT (m.change_type = 'xuat' AND m.note LIKE 'Giữ hàng%')
             GROUP BY m.product_id"
        )->fetchAll();
        $netById = [];
        foreach ($sumRows as $r) { $netById[(int)$r['product_id']] = (int)$r['net']; }
        $insBase = $pdo->prepare(
            "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
             VALUES (?, NULL, 'dieu_chinh', ?, 'Baseline đối chiếu – hiệu chỉnh số dư khởi điểm từ dữ liệu seed', ?)"
        );
        $prods = $pdo->query('SELECT id, stock FROM products')->fetchAll();
        $fixed = 0;
        $pdo->beginTransaction();
        try {
            foreach ($prods as $p) {
                $pid = (int)$p['id'];
                $delta = (int)$p['stock'] - ($netById[$pid] ?? 0);
                if ($delta === 0) { continue; }
                $insBase->execute([$pid, $delta, $u['id']]);
                $fixed++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            json_fail('Không thể cân bằng baseline tồn kho.', 500);
        }
        $baseline = true;
    }
}

/* ---------- 1. Đối chiếu HÀNG (inventory_movements ↔ products.stock) ---------- */
$moveRows = $pdo->query(
    "SELECT m.product_id,
            SUM(CASE
                    WHEN m.change_type = 'xuat' AND m.note LIKE 'Giữ hàng%' THEN 0
                    ELSE m.quantity
                END) AS net_qty,
            COUNT(*) AS moves
     FROM inventory_movements m
     GROUP BY m.product_id"
)->fetchAll();

$products = $pdo->query('SELECT id, sku, name, stock FROM products')->fetchAll();
$stockById = [];
foreach ($products as $p) {
    $stockById[(int)$p['id']] = $p;
}

$mismatch = [];
$noData = 0;
$checked = 0;

// Giữ hàng pending đang hiệu lực (info + kiểm tra không vượt tồn)
$holds = $pdo->query(
    "SELECT product_id, COALESCE(SUM(quantity),0) AS pending_qty, COUNT(*) AS n
     FROM reservations
     WHERE status = 'pending' AND reserved_until > NOW()
     GROUP BY product_id"
)->fetchAll();
$holdById = [];
foreach ($holds as $h) {
    $holdById[(int)$h['product_id']] = $h;
}

foreach ($moveRows as $row) {
    $pid = (int)$row['product_id'];
    $prod = $stockById[$pid] ?? null;
    if (!$prod) {
        $mismatch[] = ['product_id' => $pid, 'issue' => 'Tồn tại movement cho sản phẩm đã bị xóa.', 'delta' => (int)$row['net_qty'], 'stock' => null];
        continue;
    }
    $checked++;
    $stock = (int)$prod['stock'];
    $expected = (int)$row['net_qty'];
    $delta = $stock - $expected;
    if ($delta !== 0) {
        $mismatch[] = [
            'product_id' => $pid,
            'sku' => $prod['sku'],
            'name' => $prod['name'],
            'stock' => $stock,
            'expected_from_movements' => $expected,
            'delta' => $delta,
            'issue' => $delta > 0
                ? 'Tồn thật lớn hơn tổng movement ' . $delta . ' đơn vị (kho có thể bị cộng thừa – kiểm tra các lần hoàn kho).'
                : 'Tồn thật nhỏ hơn tổng movement ' . abs($delta) . ' đơn vị (kho có thể bị trừ thừa).',
        ];
    }
    $pendingQty = (int)($holdById[$pid]['pending_qty'] ?? 0);
    if ($pendingQty > $stock) {
        $mismatch[] = [
            'product_id' => $pid,
            'sku' => $prod['sku'],
            'name' => $prod['name'],
            'stock' => $stock,
            'pending_reserved' => $pendingQty,
            'issue' => 'Lượng giữ hàng đang hiệu lực (' . $pendingQty . ') vượt tồn kho (' . $stock . ').',
        ];
    }
}
// Sản phẩm chưa có movement nào: đủ dữ liệu đối chiếu chỉ khi từng phát sinh đơn
$noMovId = [];
foreach ($moveRows as $row) { $noMovId[(int)$row['product_id']] = true; }

$withOrdersNoMoves = $pdo->query(
    'SELECT COUNT(DISTINCT oi.product_id) AS n
     FROM order_items oi
     WHERE oi.product_id NOT IN (SELECT DISTINCT product_id FROM inventory_movements)'
)->fetch();
$noData = (int)($withOrdersNoMoves['n'] ?? 0);

$inventoryOk = count($mismatch) === 0;

/* ---------- 2. Đối chiếu TIỀN (payments ↔ orders ↔ returns) ---------- */
$orderMoney = $pdo->query(
    "SELECT o.id AS order_id, o.order_code, o.status, o.total_amount, o.reserved_until,
            COALESCE(SUM(CASE WHEN p.status = 'thanh_cong' THEN p.amount END), 0) AS paid,
            COALESCE(SUM(CASE WHEN p.status = 'hoan_tien' THEN p.amount END), 0) AS refunded,
            SUM(CASE WHEN p.status = 'cho_xu_ly' THEN 1 ELSE 0 END) AS pending_pay,
            COUNT(p.id) AS pay_rows
     FROM orders o
     LEFT JOIN payments p ON p.order_id = o.id
     GROUP BY o.id"
)->fetchAll();

$moneyIssues = [];
$totalPaid = 0;
$totalRefund = 0;
foreach ($orderMoney as $m) {
    $o = (object)[
        'order_id' => (int)$m['order_id'],
        'order_code' => $m['order_code'],
        'status' => $m['status'],
        'total_amount' => (float)$m['total_amount'],
        'paid' => (float)$m['paid'],
        'refunded' => (float)$m['refunded'],
    ];
    $totalPaid += $o->paid;
    $totalRefund += $o->refunded;

    if ((float)$m['paid'] > 0 && $m['status'] === 'da_huy' && (float)$m['refunded'] <= 0) {
        $moneyIssues[] = [
            'order_id' => $o->order_id,
            'order_code' => $o->order_code,
            'issue' => 'Đơn đã hủy nhưng khách đã thanh toán ' . $m['paid'] . '₫ mà chưa có dòng hoàn tiền (refund).',
        ];
    }
    if ((float)$m['refunded'] > (float)$m['paid']) {
        $moneyIssues[] = [
            'order_id' => $o->order_id,
            'order_code' => $o->order_code,
            'issue' => 'Hoàn tiền (' . $m['refunded'] . '₫) vượt tiền đã thanh toán (' . $m['paid'] . '₫).',
        ];
    }
    if ((float)$m['paid'] > 0 && $m['status'] === 'cho_xac_nhan') {
        $moneyIssues[] = [
            'order_id' => $o->order_id,
            'order_code' => $o->order_code,
            'issue' => 'Đơn có payment thanh_cong nhưng vẫn ở chờ xác nhận (thanh toán muộn hoặc luồng xác nhận chưa đồng bộ).',
        ];
    }
    if ((int)$m['pending_pay'] > 0 && $m['reserved_until'] !== null && $m['status'] === 'cho_xac_nhan') {
        $ru = strtotime((string)$m['reserved_until']);
        if ($ru !== false && $ru < time()) {
            $moneyIssues[] = [
                'order_id' => $o->order_id,
                'order_code' => $o->order_code,
                'issue' => 'Đơn đã quá hạn giữ hàng nhưng payment vẫn chờ xử lý (cron expire nên đưa payment về that_bai).',
            ];
        }
    }
}

// Đối chiếu báo cáo hoàn hàng: return da_hoan_tat chưa sinh dòng refund payment
$missingReturn = $pdo->query(
    "SELECT r.id, r.return_code, COALESCE(SUM(ri.refund_amount), 0) AS refund_amount
     FROM return_requests r
     JOIN return_items ri ON ri.return_request_id = r.id
     WHERE r.status = 'da_hoan_tat' AND ri.refund_amount > 0
       AND NOT EXISTS (
            SELECT 1 FROM payments p
            WHERE p.status = 'hoan_tien'
              AND p.idempotency_key = CONCAT('REFUND-RET-', r.id)
       )
     GROUP BY r.id, r.return_code
     LIMIT " . $limit
)->fetchAll();
foreach ($missingReturn as $r) {
    $moneyIssues[] = [
        'order_id' => null,
        'return_code' => $r['return_code'],
        'issue' => 'Đổi trả ' . $r['return_code'] . ' đã hoàn tất nhưng chưa ghi dòng hoàn tiền ' . $r['refund_amount'] . '₫ vào payments.',
    ];
}

$moneyOk = count($moneyIssues) === 0;

/* ---------- 3. Gom kết quả ---------- */
usort($mismatch, fn($a, $b) => abs((int)$b['delta']) <=> abs((int)$a['delta']));
usort($moneyIssues, fn($a, $b) => (int)($b['order_id'] ?? 0) <=> (int)($a['order_id'] ?? 0));

json_ok([
    'ok' => $inventoryOk && $moneyOk,
    'generated_at' => date('Y-m-d H:i:s'),
    'baseline_applied' => $baseline,
    'inventory' => [
        'ok' => $inventoryOk,
        'checked' => $checked,
        'mismatch_count' => count($mismatch),
        'integrity_skipped' => $noData,
        'items' => array_slice($mismatch, 0, $limit),
    ],
    'money' => [
        'ok' => $moneyOk,
        'issue_count' => count($moneyIssues),
        'items' => array_slice($moneyIssues, 0, $limit),
        'totals' => [
            'total_paid' => $totalPaid,
            'total_refunded' => $totalRefund,
            'net_collected' => $totalPaid - $totalRefund,
        ],
    ],
    'reserves' => [
        'products_with_pending' => count($holds),
        'pending_units' => array_sum(array_map(fn($h) => (int)$h['pending_qty'], $holds)),
    ],
], 'Đã đối chiếu xong.');
