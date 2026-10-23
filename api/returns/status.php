<?php
/**
 * /api/returns/status.php
 * PATCH – Xử lý yêu cầu đổi trả (admin/staff).
 *   Body: { id, to_status, quantity_accepted?, note?, restock? }
 * Quy tắc:
 *   cho_xu_ly  → da_chap_nhan | tu_choi
 *   da_chap_nhan → da_hoan_tat | tu_choi
 *   tu_choi / da_hoan_tat = cuối
 * - quantity_accepted ≤ quantity_requested và ≤ SL đã giao − SL đã trả trước đó.
 * - Khi da_hoan_tat: refund_amount = qty_accepted × đơn giá đã chốt; nhập lại kho nếu restock.
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
$note = trim((string)($in['note'] ?? ''));

$allowed = [
    'cho_xu_ly' => ['da_chap_nhan', 'tu_choi'],
    'da_chap_nhan' => ['da_hoan_tat', 'tu_choi'],
    'tu_choi' => [],
    'da_hoan_tat' => [],
];
if ($id <= 0 || !isset($allowed[$to])) {
    json_fail('Trạng thái đích không hợp lệ.', 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $st = $pdo->prepare('SELECT * FROM return_requests WHERE id = ? FOR UPDATE');
    $st->execute([$id]);
    $req = $st->fetch();
    if (!$req) {
        $pdo->rollBack();
        json_fail('Không tìm thấy yêu cầu đổi trả.', 404);
    }
    $from = $req['status'];
    if (!in_array($to, $allowed[$from] ?? [], true)) {
        $pdo->rollBack();
        json_fail('Không thể chuyển từ “' . $from . '” sang “' . $to . '”.', 409);
    }

    $items = $pdo->prepare('SELECT * FROM return_items WHERE return_request_id = ?');
    $items->execute([$id]);
    $riRows = $items->fetchAll();
    if (!$riRows) {
        $pdo->rollBack();
        json_fail('Yêu cầu không có sản phẩm trả.', 422);
    }

    if ($to === 'da_chap_nhan') {
        $askedQty = (int)($in['quantity_accepted'] ?? 0);
        foreach ($riRows as $ri) {
            $qtyRequested = (int)$ri['quantity_requested'];
            $qtyAcc = $askedQty > 0 ? $askedQty : $qtyRequested;
            if ($qtyAcc > $qtyRequested) {
                $pdo->rollBack();
                json_fail(
                    'SL chấp nhận (' . $qtyAcc . ') vượt số lượng đã yêu cầu trả (' . $qtyRequested . ').',
                    409
                );
            }
            // SL đã giao
            $oi = $pdo->prepare('SELECT quantity FROM order_items WHERE id = ?');
            $oi->execute([$ri['order_item_id']]);
            $delivered = (int)$oi->fetchColumn();
            // SL đang giữ ở yêu cầu khác (trừ yêu cầu hiện tại)
            $other = $pdo->prepare(
                "SELECT COALESCE(SUM(ri2.quantity_requested),0)
                 FROM return_items ri2 JOIN return_requests r2 ON r2.id = ri2.return_request_id
                 WHERE ri2.order_item_id = ? AND ri2.return_request_id <> ?
                   AND r2.status IN ('cho_xu_ly','da_chap_nhan','da_hoan_tat')"
            );
            $other->execute([$ri['order_item_id'], $id]);
            $otherQty = (int)$other->fetchColumn();
            $maxAccept = $delivered - $otherQty;
            if ($qtyAcc > $maxAccept) {
                $pdo->rollBack();
                json_fail('SL chấp nhận (' . $qtyAcc . ') vượt số còn trả được (' . $maxAccept . ').', 409);
            }
            $pdo->prepare('UPDATE return_items SET quantity_accepted = ?, refund_amount = ? * unit_price WHERE id = ?')
                ->execute([$qtyAcc, $qtyAcc, $ri['id']]);
        }
        $pdo->prepare('UPDATE return_requests SET status = ?, processed_by = ?, processed_at = NOW(), process_note = ? WHERE id = ?')
            ->execute(['da_chap_nhan', $u['id'], $note !== '' ? $note : null, $id]);
    } elseif ($to === 'da_hoan_tat') {
        foreach ($riRows as $ri) {
            $acc = (int)$ri['quantity_accepted'];
            if ($acc <= 0) {
                $pdo->rollBack();
                json_fail('Không thể hoàn tất khi chưa chấp nhận số lượng trả.', 409);
            }
            $pdo->prepare('UPDATE return_items SET refund_amount = ? * unit_price WHERE id = ?')
                ->execute([$acc, $ri['id']]);
            if ((int)$ri['restock'] === 1) {
                $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')
                    ->execute([$acc, $ri['product_id']]);
                $pdo->prepare(
                    "INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by)
                     VALUES (?, (SELECT order_id FROM return_requests WHERE id = ?), 'tra_hang', ?, ?, ?)"
                )->execute([$ri['product_id'], $id, $acc, 'Nhập lại kho khi hoàn tất đổi trả ' . $req['return_code'], $u['id']]);
            }
        }
        $pdo->prepare('UPDATE return_requests SET status = ?, processed_by = ?, processed_at = NOW(), process_note = ? WHERE id = ?')
            ->execute(['da_hoan_tat', $u['id'], $note !== '' ? $note : 'Hoàn tất đổi trả', $id]);
    } else { // tu_choi
        $pdo->prepare('UPDATE return_items SET quantity_accepted = 0, refund_amount = 0 WHERE return_request_id = ?')
            ->execute([$id]);
        $pdo->prepare('UPDATE return_requests SET status = ?, processed_by = ?, processed_at = NOW(), process_note = ? WHERE id = ?')
            ->execute(['tu_choi', $u['id'], $note !== '' ? $note : 'Từ chối yêu cầu', $id]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_fail('Không thể cập nhật yêu cầu đổi trả.', 500);
}

$sum = $pdo->prepare('SELECT COALESCE(SUM(refund_amount),0) FROM return_items WHERE return_request_id = ?');
$sum->execute([$id]);
json_ok(['id' => $id, 'from_status' => $from, 'to_status' => $to, 'refund_total' => (float)$sum->fetchColumn()], 'Đã cập nhật yêu cầu đổi trả.');
