<?php
/**
 * /api/reservations/index.php
 * GET  – Danh sách phiếu giữ hàng (admin/staff: tất cả; customer: của mình).
 *        ?q=&status=&product=&page=&per_page=
 * POST – Tạo phiếu giữ hàng mới (customer tạo cho mình; admin/staff tạo cho khách).
 *        Body: { product_id, variant_id?, variant?, quantity, reserved_until? }
 *        Thời gian giữ tối đa 30 phút kể từ bây giờ.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();
$u = require_auth();

if ($method === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    $status = (string)($_GET['status'] ?? '');
    $productId = (int)($_GET['product_id'] ?? 0);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 50)));

    $where = [];
    $params = [];
    if ($u['role'] === 'customer') {
        $where[] = 'r.user_id = ?';
        $params[] = (int)$u['id'];
    }
    if ($q !== '') {
        $where[] = '(r.reservation_code LIKE ? OR p.name LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if (in_array($status, ['pending', 'confirmed', 'released', 'expired'], true)) {
        $where[] = 'r.status = ?';
        $params[] = $status;
    }
    if ($productId > 0) {
        $where[] = 'r.product_id = ?';
        $params[] = $productId;
    }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stc = $pdo->prepare("SELECT COUNT(*) FROM reservations r $w");
    $stc->execute($params);
    $total = (int)$stc->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $st = $pdo->prepare(
        "SELECT r.*, p.name AS product_name, p.sku,
                (SELECT o.order_code FROM orders o WHERE o.id = r.order_id) AS order_code
         FROM reservations r
         LEFT JOIN products p ON p.id = r.product_id
         $w ORDER BY r.created_at DESC LIMIT $perPage OFFSET $offset"
    );
    $st->execute($params);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['order_id'] = $r['order_id'] !== null ? (int)$r['order_id'] : null;
        $r['user_id'] = $r['user_id'] !== null ? (int)$r['user_id'] : null;
        $r['product_id'] = (int)$r['product_id'];
        $r['variant_id'] = $r['variant_id'] !== null ? (int)$r['variant_id'] : null;
        $r['quantity'] = (int)$r['quantity'];
        $r['unit_price'] = (float)$r['unit_price'];
    }
    json_ok(['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

if ($method === 'POST') {
    $in = request_json();
    $productId = (int)($in['product_id'] ?? 0);
    $variantId = isset($in['variant_id']) && $in['variant_id'] !== null ? (int)$in['variant_id'] : 0;
    $variantName = trim((string)($in['variant'] ?? ''));
    $qty = (int)($in['quantity'] ?? 0);
    $reservedUntil = trim((string)($in['reserved_until'] ?? ''));

    if ($productId <= 0 || $qty <= 0) {
        json_fail('Sản phẩm hoặc số lượng không hợp lệ.', 422);
    }

    $maxUntil = time() + 1800;
    if ($reservedUntil !== '') {
        $ts = strtotime($reservedUntil);
        if ($ts === false || $ts <= time()) {
            json_fail('Thời gian giữ hàng phải ở tương lai.', 422);
        }
        $until = date('Y-m-d H:i:s', min($ts, $maxUntil));
    } else {
        $until = date('Y-m-d H:i:s', $maxUntil);
    }

    $pdo->beginTransaction();
    try {
        $p = of_lock_product($pdo, $productId);
        if ($p['status'] !== 'active') {
            throw new OrderFlowException('Sản phẩm không tồn tại hoặc ngừng bán.', 422);
        }
        $required = of_variant_required($pdo, $productId);
        $variant = of_resolve_variant(
            $pdo, $productId,
            $variantId > 0 ? $variantId : null,
            $variantName !== '' ? $variantName : null,
            false, $required
        );
        if ($required && !$variant) {
            throw new OrderFlowException('Vui lòng chọn biến thể cho sản phẩm này.', 422);
        }
        $avail = of_availability($pdo, $p, $variant);
        if ($qty > $avail['available']) {
            throw new OrderFlowException(
                'Chỉ còn ' . $avail['available'] . ' sản phẩm có thể giữ (đã giữ: ' . $avail['held'] . ').',
                409
            );
        }

        $code = next_code($pdo, 'reservations', 'reservation_code', 'RV-');
        $unitPrice = (float)($variant['price'] ?? $p['price']);
        $pdo->prepare(
            'INSERT INTO reservations (reservation_code, order_id, user_id, product_id, variant_id, variant, quantity, unit_price, status, reserved_until)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, "pending", ?)'
        )->execute([
            $code,
            $u['role'] === 'customer' ? (int)$u['id'] : null,
            $productId,
            $variant ? (int)$variant['id'] : null,
            $variant ? $variant['name'] : null,
            $qty,
            $unitPrice,
            $until,
        ]);
        $resId = (int)$pdo->lastInsertId();
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
        json_fail('Không thể tạo phiếu giữ hàng.', 500);
    }

    json_ok([
        'id' => $resId,
        'reservation_code' => $code,
        'reserved_until' => $until,
        'variant_id' => $variant ? (int)$variant['id'] : null,
        'unit_price' => $unitPrice,
    ], 'Đã tạo phiếu giữ hàng.', 201);
}

json_fail('Phương thức không được hỗ trợ.', 405);
