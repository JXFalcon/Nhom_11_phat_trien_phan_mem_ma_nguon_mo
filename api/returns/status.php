<?php
/**
 * /api/returns/status.php
 * PATCH – Xử lý yêu cầu đổi trả (admin/staff).
 *   Body: { id, to_status, items?: [{return_item_id, quantity_accepted}], quantity_accepted?, note?, restock? }
 * Quy tắc:
 *   cho_xu_ly → da_chap_nhan | tu_choi
 *   da_chap_nhan → da_hoan_tat | tu_choi
 *   da_hoan_tat → da_hoan_tat (chỉ điều chỉnh số lượng đã chấp nhận)
 * - quantity_accepted ≤ quantity_requested và ≤ SL đã giao − phần đã trả/đang giữ ở yêu cầu khác.
 * - Khi da_hoan_tat: refund_amount = qty_accepted × đơn giá đã chốt; nhập lại kho nếu restock.
 * - Hoàn tiền luôn bị chặn bởi số tiền đã thanh toán còn lại của đơn.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';

if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['PATCH', 'POST'], true)) {
    json_fail('Chỉ chấp nhận PATCH/POST.', 405);
}
$u = require_role('admin', 'staff');
$in = request_json();
$id = (int)($in['id'] ?? 0);
$to = (string)($in['to_status'] ?? '');
$note = trim((string)($in['note'] ?? ''));

$allowed = [
    'cho_xu_ly' => ['da_chap_nhan', 'tu_choi'],
    'da_chap_nhan' => ['da_hoan_tat', 'tu_choi'],
    'tu_choi' => [],
    'da_hoan_tat' => ['da_hoan_tat'],
];
if ($id <= 0 || !isset($allowed[$to])) {
    json_fail('Trạng thái đích không hợp lệ.', 422);
}

function return_accept_map(array $in, array $riRows, bool $defaultRequested): array
{
    $map = [];
    foreach ($riRows as $ri) {
        $map[(int)$ri['id']] = $defaultRequested
            ? (int)$ri['quantity_requested']
            : (int)$ri['quantity_accepted'];
    }
    if (isset($in['items']) && is_array($in['items'])) {
        foreach ($map as $rid => $_) {
            $map[$rid] = $defaultRequested ? 0 : $map[$rid];
        }
        foreach ($in['items'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rid = (int)($row['return_item_id'] ?? $row['id'] ?? 0);
            if (array_key_exists($rid, $map)) {
                $map[$rid] = (int)($row['quantity_accepted'] ?? 0);
            }
        }
    } elseif (array_key_exists('quantity_accepted', $in)
        && $in['quantity_accepted'] !== null && $in['quantity_accepted'] !== '') {
        $qty = (int)$in['quantity_accepted'];
        foreach ($map as $rid => $_) {
            $map[$rid] = $qty;
        }
    }
    return $map;
}

function return_other_usage(PDO $pdo, int $orderItemId, int $requestId): array
{
    $st = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN r2.status IN ('da_chap_nhan','da_hoan_tat')
                              THEN ri2.quantity_accepted ELSE 0 END), 0) AS accepted,
            COALESCE(SUM(CASE WHEN r2.status = 'cho_xu_ly'
                              THEN ri2.quantity_requested ELSE 0 END), 0) AS pending
         FROM return_items ri2
         JOIN return_requests r2 ON r2.id = ri2.return_request_id
         WHERE ri2.order_item_id = ? AND ri2.return_request_id <> ?"
    );
    $st->execute([$orderItemId, $requestId]);
    $row = $st->fetch();
    return ['accepted' => (int)$row['accepted'], 'pending' => (int)$row['pending']];
}

$pdo = db();
$pdo->beginTransaction();
try {
    $st = $pdo->prepare('SELECT * FROM return_requests WHERE id = ? FOR UPDATE');
    $st->execute([$id]);
    $req = $st->fetch();
    if (!$req) {
        throw new OrderFlowException('Không tìm thấy yêu cầu đổi trả.', 404);
    }
    $from = $req['status'];
    if (!in_array($to, $allowed[$from] ?? [], true)) {
        throw new OrderFlowException('Không thể chuyển từ “' . $from . '” sang “' . $to . '”.', 409);
    }
    $orderStatusSt = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
    $orderStatusSt->execute([(int)$req['order_id']]);
    $orderStatus = (string)$orderStatusSt->fetchColumn();
    $logHistory = $pdo->prepare(
        'INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (?, ?, ?, ?, ?)'
    );

    $items = $pdo->prepare(
        'SELECT ri.*, oi.quantity AS delivered_qty, oi.variant_id
         FROM return_items ri
         JOIN order_items oi ON oi.id = ri.order_item_id
         WHERE ri.return_request_id = ? FOR UPDATE'
    );
    $items->execute([$id]);
    $riRows = $items->fetchAll();
    if (!$riRows) {
        throw new OrderFlowException('Yêu cầu không có sản phẩm trả.', 422);
    }

    if ($to === 'da_chap_nhan') {
        $accepted = return_accept_map($in, $riRows, true);
        foreach ($riRows as $ri) {
            $rid = (int)$ri['id'];
            $qtyAcc = $accepted[$rid];
            $qtyRequested = (int)$ri['quantity_requested'];
            if ($qtyAcc < 0 || $qtyAcc > $qtyRequested) {
                throw new OrderFlowException(
                    'SL chấp nhận (' . $qtyAcc . ') phải nằm trong 0..' . $qtyRequested . '.',
                    409
                );
            }
            $usage = return_other_usage($pdo, (int)$ri['order_item_id'], $id);
            $maxAccept = (int)$ri['delivered_qty'] - $usage['accepted'] - $usage['pending'];
            if ($qtyAcc > $maxAccept) {
                throw new OrderFlowException(
                    'SL chấp nhận (' . $qtyAcc . ') vượt số còn trả được (' . $maxAccept . ').',
                    409
                );
            }
            $pdo->prepare('UPDATE return_items SET quantity_accepted = ?, refund_amount = ? * unit_price WHERE id = ?')
                ->execute([$qtyAcc, $qtyAcc, $rid]);
        }
        $pdo->prepare('UPDATE return_requests SET status = ?, processed_by = ?, processed_at = NOW(), process_note = ? WHERE id = ?')
            ->execute(['da_chap_nhan', $u['id'], $note !== '' ? $note : null, $id]);
        $logHistory->execute([
            (int)$req['order_id'], $orderStatus, $orderStatus,
            'Chấp nhận đổi trả ' . $req['return_code'] . ': ' . array_sum($accepted) . ' sản phẩm',
            (int)$u['id'],
        ]);
    } elseif ($to === 'da_hoan_tat') {
        $accepted = return_accept_map($in, $riRows, false);
        $setStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ? AND stock + ? >= 0');
        $setVariant = $pdo->prepare('UPDATE product_variants SET stock = stock + ? WHERE id = ? AND stock + ? >= 0');
        $insMove = $pdo->prepare(
            "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
             VALUES (?, ?, 'tra_hang', ?, ?, ?)"
        );

        foreach ($riRows as $ri) {
            $rid = (int)$ri['id'];
            $qtyAcc = $accepted[$rid];
            if ($qtyAcc < 0 || $qtyAcc > (int)$ri['quantity_requested']) {
                throw new OrderFlowException('Số lượng chấp nhận không hợp lệ.', 409);
            }
            $usage = return_other_usage($pdo, (int)$ri['order_item_id'], $id);
            $maxAccept = (int)$ri['delivered_qty'] - $usage['accepted'] - $usage['pending'];
            if ($qtyAcc > $maxAccept) {
                throw new OrderFlowException(
                    'SL chấp nhận (' . $qtyAcc . ') vượt số còn trả được (' . $maxAccept . ').',
                    409
                );
            }
            $restockQty = $from === 'da_hoan_tat'
                ? $qtyAcc - (int)$ri['quantity_accepted']
                : $qtyAcc;
            if ($restockQty !== 0 && (int)$ri['restock'] === 1) {
                $setStock->execute([$restockQty, (int)$ri['product_id'], $restockQty]);
                if ($setStock->rowCount() !== 1) {
                    throw new OrderFlowException('Không thể điều chỉnh tồn kho vì tồn không đủ.', 409);
                }
                if (!empty($ri['variant_id'])) {
                    $setVariant->execute([$restockQty, (int)$ri['variant_id'], $restockQty]);
                    if ($setVariant->rowCount() !== 1) {
                        throw new OrderFlowException('Không thể điều chỉnh tồn biến thể vì tồn không đủ.', 409);
                    }
                }
                $insMove->execute([
                    (int)$ri['product_id'], (int)$req['order_id'], $restockQty,
                    ($restockQty > 0 ? 'Nhập lại kho' : 'Điều chỉnh giảm nhập lại kho')
                        . ' khi hoàn tất đổi trả ' . $req['return_code'],
                    (int)$u['id'],
                ]);
            }
            $pdo->prepare('UPDATE return_items SET quantity_accepted = ?, refund_amount = ? * unit_price WHERE id = ?')
                ->execute([$qtyAcc, $qtyAcc, $rid]);
        }

        $sumRef = $pdo->prepare('SELECT COALESCE(SUM(refund_amount), 0) FROM return_items WHERE return_request_id = ?');
        $sumRef->execute([$id]);
        $wanted = (float)$sumRef->fetchColumn();

        $otherRef = $pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE order_id = ? AND status = 'hoan_tien'
               AND (idempotency_key IS NULL OR idempotency_key <> ?)"
        );
        $otherRef->execute([(int)$req['order_id'], 'REFUND-RET-' . $id]);
        $sums = of_order_paid_refunded($pdo, (int)$req['order_id']);
        $cap = max(0.0, $sums['paid'] - (float)$otherRef->fetchColumn());
        $target = (int)round(min($wanted, $cap));
        $key = 'REFUND-RET-' . $id;

        $existing = $pdo->prepare('SELECT * FROM payments WHERE idempotency_key = ? LIMIT 1');
        $existing->execute([$key]);
        $refundRow = $existing->fetch();
        $refundId = 0;
        $refundChanged = false;
        if ($refundRow) {
            $refundId = (int)$refundRow['id'];
            if ((int)$refundRow['amount'] !== $target) {
                $pdo->prepare(
                    'UPDATE payments SET amount = ?, note = ?, processed_at = NOW() WHERE id = ?'
                )->execute([
                    $target,
                    'Điều chỉnh hoàn tiền đổi trả ' . $req['return_code'],
                    $refundId,
                ]);
                $refundChanged = true;
            }
        } elseif ($target > 0) {
            $method = $pdo->prepare(
                "SELECT method FROM payments WHERE order_id = ? AND status = 'thanh_cong' ORDER BY id LIMIT 1"
            );
            $method->execute([(int)$req['order_id']]);
            $refundMethod = $method->fetchColumn() ?: 'bank';
            $code2 = next_code($pdo, 'payments', 'payment_code', 'PM-');
            $pdo->prepare(
                'INSERT INTO payments (payment_code, order_id, amount, method, status, idempotency_key, note, processed_at)
                 VALUES (?,?,?,?, "hoan_tien", ?, ?, NOW())'
            )->execute([
                $code2, (int)$req['order_id'], $target, $refundMethod, $key,
                'Hoàn tiền đổi trả ' . $req['return_code'],
            ]);
            $refundId = (int)$pdo->lastInsertId();
            $refundChanged = true;
        }
        if ($refundId > 0) {
            $hasEvent = $pdo->prepare("SELECT COUNT(*) FROM payment_events WHERE payment_id = ? AND result = 'hoan_tien'");
            $hasEvent->execute([$refundId]);
            if ($refundChanged || (int)$hasEvent->fetchColumn() === 0) {
                of_payment_event(
                    $pdo, $refundId, (int)$req['order_id'], $key,
                    'hoan_tien', 'refunded',
                    'Hoàn tiền đổi trả ' . $req['return_code'] . ': ' . $target . '₫',
                    (int)$u['id']
                );
            }
        }

        $pdo->prepare('UPDATE return_requests SET status = ?, processed_by = ?, processed_at = NOW(), process_note = ? WHERE id = ?')
            ->execute(['da_hoan_tat', $u['id'], $note !== '' ? $note : 'Hoàn tất đổi trả', $id]);
        $logHistory->execute([
            (int)$req['order_id'], $orderStatus, $orderStatus,
            'Hoàn tất đổi trả ' . $req['return_code'] . ': hoàn ' . $target . '₫',
            (int)$u['id'],
        ]);
    } else {
        $pdo->prepare('UPDATE return_items SET quantity_accepted = 0, refund_amount = 0 WHERE return_request_id = ?')
            ->execute([$id]);
        $pdo->prepare('UPDATE return_requests SET status = ?, processed_by = ?, processed_at = NOW(), process_note = ? WHERE id = ?')
            ->execute(['tu_choi', $u['id'], $note !== '' ? $note : 'Từ chối yêu cầu', $id]);
        $logHistory->execute([
            (int)$req['order_id'], $orderStatus, $orderStatus,
            'Từ chối đổi trả ' . $req['return_code'],
            (int)$u['id'],
        ]);
    }

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
    json_fail('Không thể cập nhật yêu cầu đổi trả.', 500);
}

$sum = $pdo->prepare('SELECT COALESCE(SUM(refund_amount),0) FROM return_items WHERE return_request_id = ?');
$sum->execute([$id]);
json_ok([
    'id' => $id,
    'from_status' => $from,
    'to_status' => $to,
    'refund_total' => (float)$sum->fetchColumn(),
], 'Đã cập nhật yêu cầu đổi trả.');
