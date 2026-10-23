<?php
/**
 * /api/orders/status.php
 * PATCH – Cập nhật trạng thái đơn theo vòng đời cho phép.
 *   Body: { id, to_status, note? }
 * Quy tắc:
 *   cho_xac_nhan → da_xac_nhan | da_huy
 *   da_xac_nhan  → dang_giao | da_huy
 *   dang_giao    → da_giao
 *   da_giao / da_huy = trạng thái cuối
 * Hủy đơn: hoàn kho đúng 1 lần (cờ stock_restored) + lịch sử + inventory_movements.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'PATCH' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Chỉ chấp nhận PATCH/POST.', 405);
}
$u = require_role('admin', 'staff');
$in = request_json();
$id = (int)($in['id'] ?? 0);
$to = (string)($in['to_status'] ?? '');
$note = trim((string)($in['note'] ?? ''));

$allowed = [
    'cho_xac_nhan' => ['da_xac_nhan', 'da_huy'],
    'da_xac_nhan' => ['dang_giao', 'da_huy'],
    'dang_giao' => ['da_giao'],
    'da_giao' => [],
    'da_huy' => [],
];
if ($id <= 0 || !isset($allowed[$to])) {
    json_fail('Trạng thái đích không hợp lệ.', 422);
}

$pdo = db();
$st = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
// cần transaction để đọc-ghi atomically
$pdo->beginTransaction();
try {
    $st->execute([$id]);
    $order = $st->fetch();
    if (!$order) {
        $pdo->rollBack();
        json_fail('Không tìm thấy đơn hàng.', 404);
    }
    $from = $order['status'];
    if (!in_array($to, $allowed[$from] ?? [], true)) {
        $pdo->rollBack();
        json_fail(
            'Không thể chuyển từ “' . $from . '” sang “' . $to . '”. Chỉ được đi theo đúng vòng đời đơn.',
            409
        );
    }

    // Hủy đơn → hoàn kho đúng một lần
    if ($to === 'da_huy') {
        if ((int)$order['stock_restored'] === 1) {
            $pdo->rollBack();
            json_fail('Đơn này đã được hoàn kho trước đó, không hoàn lại lần nữa.', 409);
        }
        $pdo->prepare('UPDATE orders SET status = ?, cancel_reason = ?, stock_restored = 1 WHERE id = ?')
            ->execute(['da_huy', $note !== '' ? $note : 'Hủy đơn', $id]);
        $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
        $items->execute([$id]);
        $add = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        $mv = $pdo->prepare(
            "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
             VALUES (?, ?, 'huy_don', ?, ?, ?)"
        );
        foreach ($items->fetchAll() as $it) {
            $add->execute([(int)$it['quantity'], $it['product_id']]);
            $mv->execute([$it['product_id'], $id, (int)$it['quantity'], 'Hoàn kho khi hủy đơn', $u['id']]);
        }
        // Đánh dấu thanh toán failed nếu còn chờ
        $pdo->prepare("UPDATE payments SET status = 'that_bai', note = CONCAT(COALESCE(note,''), ' – Đơn bị hủy')
                       WHERE order_id = ? AND status = 'cho_xu_ly'")->execute([$id]);
        // Hủy vận đơn còn đang xử lý/giao; vận đơn đã kết thúc giữ nguyên
        $pdo->prepare(
            "UPDATE shipments SET status = 'that_bai'
             WHERE order_id = ? AND status IN ('cho_lay_hang', 'dang_van_chuyen')"
        )->execute([$id]);
    } else {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$to, $id]);
    }

    $pdo->prepare(
        'INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (?,?,?,?,?)'
    )->execute([$id, $from, $to, $note !== '' ? $note : null, $u['id']]);

    // Đồng bộ giao hàng / thanh toán theo trạng thái đơn
    if ($to === 'da_xac_nhan') {
        $sh = $pdo->prepare("UPDATE shipments SET status = 'cho_lay_hang' WHERE order_id = ? AND status IN ('cho_lay_hang')");
        $sh->execute([$id]);
        // Nếu chưa có vận đơn, tạo mới
        $chk = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE order_id = ?');
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() === 0) {
            $code = next_code($pdo, 'shipments', 'shipment_code', 'VC-');
            $pdo->prepare(
                "INSERT INTO shipments (shipment_code, order_id, carrier, tracking_code, ship_date, status)
                 VALUES (?,?,?, ?, NULL, 'cho_lay_hang')"
            )->execute([$code, $id, 'Giao Hàng Nhanh (GHN)', 'OF' . random_int(100000000, 999999999)]);
        }
    } elseif ($to === 'dang_giao') {
        $pdo->prepare("UPDATE shipments SET status = 'dang_van_chuyen' WHERE order_id = ?")->execute([$id]);
    } elseif ($to === 'da_giao') {
        $pdo->prepare("UPDATE shipments SET status = 'da_giao', ship_date = COALESCE(ship_date, NOW()) WHERE order_id = ?")->execute([$id]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_fail('Không thể cập nhật trạng thái đơn.', 500);
}

json_ok(['id' => $id, 'from_status' => $from, 'to_status' => $to], 'Đã cập nhật trạng thái đơn.');
