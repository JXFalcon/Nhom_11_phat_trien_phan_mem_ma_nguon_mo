<?php
/**
 * /api/shipments/status.php
 * PATCH – Cập nhật trạng thái giao hàng (admin/staff) + đồng bộ trạng thái đơn.
 *   Body: { id, to_status }
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['PATCH', 'POST'], true)) {
    json_fail('Chỉ chấp nhận PATCH/POST.', 405);
}
$u = require_role('admin', 'staff');
$in = request_json();
$id = (int)($in['id'] ?? 0);
$to = (string)($in['to_status'] ?? '');

$allowed = [
    'cho_lay_hang' => ['dang_van_chuyen', 'that_bai'],
    'dang_van_chuyen' => ['da_giao', 'that_bai'],
    'da_giao' => [],
    'that_bai' => ['dang_van_chuyen'],
];
if ($id <= 0 || !isset($allowed[$to])) {
    json_fail('Trạng thái đích không hợp lệ.', 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $st = $pdo->prepare('SELECT * FROM shipments WHERE id = ? FOR UPDATE');
    $st->execute([$id]);
    $ship = $st->fetch();
    if (!$ship) {
        $pdo->rollBack();
        json_fail('Không tìm thấy vận đơn.', 404);
    }
    $from = $ship['status'];
    if (!in_array($to, $allowed[$from] ?? [], true)) {
        $pdo->rollBack();
        json_fail('Không thể chuyển vận đơn từ “' . $from . '” sang “' . $to . '”.', 409);
    }

    $pdo->prepare('UPDATE shipments SET status = ? WHERE id = ?')->execute([$to, $id]);

    // Đồng bộ trạng thái đơn hàng
    $orderId = (int)$ship['order_id'];
    $ord = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
    $ord->execute([$orderId]);
    $ordStatus = $ord->fetchColumn();

    if ($to === 'dang_van_chuyen' && $ordStatus === 'da_xac_nhan') {
        $pdo->prepare("UPDATE orders SET status = 'dang_giao' WHERE id = ?")->execute([$orderId]);
        $pdo->prepare(
            "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
             VALUES (?, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển', ?)"
        )->execute([$orderId, $u['id']]);
    } elseif ($to === 'da_giao' && in_array($ordStatus, ['dang_giao', 'da_xac_nhan'], true)) {
        $pdo->prepare("UPDATE orders SET status = 'da_giao' WHERE id = ?")->execute([$orderId]);
        $pdo->prepare(
            "INSERT INTO order_history (order_id, from_status, to_status, note, changed_by)
             VALUES (?, ?, 'da_giao', 'Khách đã nhận hàng', ?)"
        )->execute([$orderId, $ordStatus, $u['id']]);
    } elseif ($to === 'dang_van_chuyen' && $ordStatus === 'that_bai_placeholder') {
        // không dùng
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_fail('Không thể cập nhật vận đơn.', 500);
}

json_ok(['id' => $id, 'from_status' => $from, 'to_status' => $to], 'Đã cập nhật trạng thái giao hàng.');
