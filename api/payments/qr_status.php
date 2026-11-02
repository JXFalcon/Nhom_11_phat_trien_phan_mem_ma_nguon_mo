<?php
/**
 * /api/payments/qr_status.php?id=<qr_id> hoặc ?payment_id=<id>
 * GET - Kiểm tra trạng thái mã QR chuyển khoản (frontend poll định kỳ).
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/payment_qr.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_fail('Chỉ chấp nhận GET.', 405);
}

$u = require_auth();
$qrId = (int)($_GET['id'] ?? 0);
$paymentId = (int)($_GET['payment_id'] ?? 0);
if ($qrId <= 0 && $paymentId <= 0) {
    json_fail('Thiếu id QR hoặc payment_id.', 422);
}

$pdo = db();
of_qr_expire_stale($pdo);

$sql = 'SELECT q.*, p.status AS payment_status, o.status AS order_status, o.user_id AS order_user_id,
               o.reserved_until, (q.expires_at < NOW()) AS is_expired
        FROM payment_qr_sessions q
        JOIN payments p ON p.id = q.payment_id
        JOIN orders o ON o.id = q.order_id';
if ($qrId > 0) {
    $sql .= ' WHERE q.id = ? LIMIT 1';
    $params = [$qrId];
} else {
    $sql .= ' WHERE q.payment_id = ? ORDER BY q.id DESC LIMIT 1';
    $params = [$paymentId];
}
$st = $pdo->prepare($sql);
$st->execute($params);
$qr = $st->fetch();
if (!$qr) {
    json_fail('Không tìm thấy mã QR.', 404);
}
if ($u['role'] === 'customer' && (int)$qr['order_user_id'] !== (int)$u['id']) {
    json_fail('Bạn không có quyền xem mã QR này.', 403);
}

$status = (string)$qr['status'];
if ($status === 'pending' && (int)$qr['is_expired'] === 1) {
    $status = 'expired';
}
// Phòng trường hợp payment đã thanh toán nhưng hook chưa kịp cập nhật.
if ($status !== 'paid' && $qr['payment_status'] === 'thanh_cong') {
    $status = 'paid';
    $pdo->prepare("UPDATE payment_qr_sessions SET status = 'paid' WHERE id = ?")
        ->execute([(int)$qr['id']]);
}

json_ok([
    'id' => (int)$qr['id'],
    'payment_id' => (int)$qr['payment_id'],
    'order_id' => (int)$qr['order_id'],
    'memo' => $qr['memo'],
    'amount' => (float)$qr['amount'],
    'status' => $status,
    'payment_status' => $qr['payment_status'],
    'order_status' => $qr['order_status'],
    'expires_at' => $qr['expires_at'],
    'is_expired' => $status === 'expired' || (int)$qr['is_expired'] === 1,
]);
