<?php
/**
 * /api/reports/reconcile.php
 * GET – Đối chiếu dữ liệu nghiệp vụ (báo cáo kiểm soát):
 *   1. Hàng tồn:  stock thật vs Σ inventory_movements (loại trừ các hold chưa trừ).
 *   2. Biến thể: holds theo variant_id, snapshot variant_id, cảnh báo Σ tồn biến thể.
 *   3. Payment events: accepted trùng, event mồ côi, payment kết thúc thiếu nhật ký.
 *   4. Tiền mô phỏng: payments ↔ orders ↔ trả hàng (thanh_cong/hoan_tien/that_bai).
 *   5. Giữ hàng: reservations pending không được vượt quá tồn.
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

/* ---------- 2. Đối chiếu BIẾN THỂ (tồn, giữ hàng, snapshot) ---------- */
$variantRows = $pdo->query(
    "SELECT pv.id, pv.product_id, pv.sku, pv.name, pv.stock,
            p.sku AS product_sku, p.name AS product_name, p.stock AS product_stock
     FROM product_variants pv
     JOIN products p ON p.id = pv.product_id
     ORDER BY pv.product_id, pv.id"
)->fetchAll();

$variantHolds = $pdo->query(
    "SELECT variant_id, COALESCE(SUM(quantity),0) AS pending_qty
     FROM reservations
     WHERE status = 'pending' AND reserved_until > NOW() AND variant_id IS NOT NULL
     GROUP BY variant_id"
)->fetchAll();
$variantHoldById = [];
foreach ($variantHolds as $h) {
    $variantHoldById[(int)$h['variant_id']] = (int)$h['pending_qty'];
}

$variantIssues = [];
$checkedVariants = 0;
foreach ($variantRows as $v) {
    $checkedVariants++;
    $held = $variantHoldById[(int)$v['id']] ?? 0;
    if ($held > (int)$v['stock']) {
        $variantIssues[] = [
            'variant_id' => (int)$v['id'],
            'product_id' => (int)$v['product_id'],
            'sku' => $v['sku'] ?: $v['product_sku'],
            'name' => $v['product_name'] . ' / ' . $v['name'],
            'stock' => (int)$v['stock'],
            'pending_reserved' => $held,
            'issue' => 'Biến thể đang bị giữ ' . $held . ' nhưng tồn chỉ ' . (int)$v['stock'] . '.',
        ];
    }
}

$orphanVariantItems = (int)$pdo->query(
    'SELECT COUNT(*) FROM order_items oi
     LEFT JOIN product_variants pv ON pv.id = oi.variant_id
     WHERE oi.variant_id IS NOT NULL AND pv.id IS NULL'
)->fetchColumn();
if ($orphanVariantItems > 0) {
    $variantIssues[] = [
        'variant_id' => null,
        'issue' => $orphanVariantItems . ' dòng order_items trỏ tới biến thể không còn tồn tại.',
    ];
}

$unresolvedVariantItems = $pdo->query(
    "SELECT oi.id, oi.order_id, oi.product_id, oi.variant
     FROM order_items oi
     JOIN product_variants pv ON pv.product_id = oi.product_id AND pv.name = oi.variant
     WHERE oi.variant_id IS NULL
     LIMIT " . $limit
)->fetchAll();
foreach ($unresolvedVariantItems as $it) {
    $variantIssues[] = [
        'order_item_id' => (int)$it['id'],
        'order_id' => (int)$it['order_id'],
        'issue' => 'Dòng đơn #' . (int)$it['id'] . ' có tên biến thể nhưng chưa gắn variant_id (cần backfill).',
    ];
}

$variantStockSums = $pdo->query(
    "SELECT p.id, p.sku, p.name, p.stock, COALESCE(SUM(pv.stock),0) AS variant_stock
     FROM products p
     JOIN product_variants pv ON pv.product_id = p.id
     GROUP BY p.id
     HAVING p.stock <> variant_stock"
)->fetchAll();
$stockSumMismatches = [];
foreach ($variantStockSums as $s) {
    $stockSumMismatches[] = [
        'product_id' => (int)$s['id'],
        'sku' => $s['sku'],
        'name' => $s['name'],
        'stock' => (int)$s['stock'],
        'variant_stock' => (int)$s['variant_stock'],
        'issue' => 'Tổng tồn biến thể (' . (int)$s['variant_stock'] . ') khác tồn sản phẩm (' . (int)$s['stock'] . ') – chỉ là cảnh báo dữ liệu seed.',
    ];
}

$variantOk = count($variantIssues) === 0;
$variantWarningCount = count($stockSumMismatches);

/* ---------- 3. Đối chiếu PAYMENT EVENTS (nhật ký xử lý thanh toán) ---------- */
$eventIssues = [];
$acceptedEvents = (int)$pdo->query(
    "SELECT COUNT(*) FROM payment_events WHERE outcome = 'accepted'"
)->fetchColumn();
$duplicateAccepted = $pdo->query(
    "SELECT payment_id, COUNT(*) AS n
     FROM payment_events
     WHERE outcome = 'accepted'
     GROUP BY payment_id HAVING COUNT(*) > 1
     LIMIT " . $limit
)->fetchAll();
foreach ($duplicateAccepted as $d) {
    $eventIssues[] = [
        'payment_id' => (int)$d['payment_id'],
        'issue' => 'Payment #' . (int)$d['payment_id'] . ' có ' . (int)$d['n'] . ' event accepted (chỉ nên có 1).',
    ];
}

$orphanEvents = (int)$pdo->query(
    'SELECT COUNT(*) FROM payment_events e
     LEFT JOIN payments p ON p.id = e.payment_id
     WHERE p.id IS NULL'
)->fetchColumn();
if ($orphanEvents > 0) {
    $eventIssues[] = [
        'payment_id' => null,
        'issue' => $orphanEvents . ' payment_event trỏ tới payment không còn tồn tại.',
    ];
}

$terminalNoEvent = (int)$pdo->query(
    "SELECT COUNT(*) FROM payments p
     WHERE p.status IN ('thanh_cong','that_bai','hoan_tien')
       AND p.processed_at IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM payment_events e WHERE e.payment_id = p.id)"
)->fetchColumn();
if ($terminalNoEvent > 0) {
    $eventIssues[] = [
        'payment_id' => null,
        'issue' => $terminalNoEvent . ' payment đã kết thúc nhưng thiếu payment_event nhật ký.',
    ];
}
$eventOk = count($eventIssues) === 0;

/* ---------- 4. Đối chiếu TIỀN (payments ↔ orders ↔ returns) ---------- */
$orderMoney = $pdo->query(
    "SELECT o.id AS order_id, o.order_code, o.status, o.total_amount, o.reserved_until,
            (o.reserved_until IS NOT NULL AND o.reserved_until < NOW()) AS is_expired,
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
    if ((int)$m['pending_pay'] > 0 && (int)$m['is_expired'] === 1 && $m['status'] === 'cho_xac_nhan') {
        $moneyIssues[] = [
            'order_id' => $o->order_id,
            'order_code' => $o->order_code,
            'issue' => 'Đơn đã quá hạn giữ hàng nhưng payment vẫn chờ xử lý (cron expire nên đưa payment về that_bai).',
        ];
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

/* ---------- 5. Gom kết quả ---------- */
usort($mismatch, fn($a, $b) => abs((int)$b['delta']) <=> abs((int)$a['delta']));
usort($moneyIssues, fn($a, $b) => (int)($b['order_id'] ?? 0) <=> (int)($a['order_id'] ?? 0));

json_ok([
    'ok' => $inventoryOk && $variantOk && $eventOk && $moneyOk,
    'generated_at' => date('Y-m-d H:i:s'),
    'baseline_applied' => $baseline,
    'inventory' => [
        'ok' => $inventoryOk,
        'checked' => $checked,
        'mismatch_count' => count($mismatch),
        'integrity_skipped' => $noData,
        'items' => array_slice($mismatch, 0, $limit),
    ],
    'variants' => [
        'ok' => $variantOk,
        'checked' => $checkedVariants,
        'issue_count' => count($variantIssues),
        'stock_sum_warning_count' => $variantWarningCount,
        'items' => array_slice($variantIssues, 0, $limit),
        'stock_sum_warnings' => array_slice($stockSumMismatches, 0, $limit),
    ],
    'payment_events' => [
        'ok' => $eventOk,
        'accepted_events' => $acceptedEvents,
        'issue_count' => count($eventIssues),
        'terminal_payments_without_event' => $terminalNoEvent,
        'items' => array_slice($eventIssues, 0, $limit),
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
