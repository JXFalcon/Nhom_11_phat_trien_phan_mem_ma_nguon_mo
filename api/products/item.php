<?php
/**
 * /api/products/item.php?id=N
 * GET    – Chi tiết sản phẩm (đăng nhập)
 * PUT    – Sửa sản phẩm (admin). Giá mới KHÔNG đổi giá trong đơn đã chốt.
 * DELETE – Vô hiệu hóa/xóa (admin). Đơn đã phát sinh → ẩn thay vì xóa vật lý.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    json_fail('Thiếu id sản phẩm.', 422);
}
$pdo = db();

if ($method === 'GET') {
    require_auth();
    $st = $pdo->prepare(
        'SELECT p.*, c.name AS category_name
         FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?'
    );
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        json_fail('Không tìm thấy sản phẩm.', 404);
    }
    $row['price'] = (float)$row['price'];
    $row['stock'] = (int)$row['stock'];
    // biến thể chi tiết
    $pv = $pdo->prepare('SELECT id, name, stock FROM product_variants WHERE product_id = ?');
    $pv->execute([$id]);
    $row['variant_rows'] = $pv->fetchAll();
    json_ok($row);
}

if ($method === 'PUT') {
    require_role('admin');
    $in = request_json();
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) {
        json_fail('Không tìm thấy sản phẩm.', 404);
    }
    $name = trim((string)($in['name'] ?? $p['name']));
    $sku = trim((string)($in['sku'] ?? $p['sku']));
    $cat = (int)($in['category_id'] ?? $p['category_id']);
    $price = array_key_exists('price', $in) ? (float)$in['price'] : (float)$p['price'];
    $stock = array_key_exists('stock', $in) ? (int)$in['stock'] : (int)$p['stock'];
    $variants = trim((string)($in['variants'] ?? ($p['variants'] ?? '')));
    $emoji = trim((string)($in['emoji'] ?? ($p['emoji'] ?? '📦')));
    $status = ($in['status'] ?? $p['status']) === 'hidden' ? 'hidden' : 'active';

    $errors = [];
    if (mb_strlen($name) < 2) {
        $errors['name'] = 'Tên sản phẩm phải có ít nhất 2 ký tự.';
    }
    if ($sku === '' || !preg_match('/^SP-\d{3,}$/', $sku)) {
        $errors['sku'] = 'Mã sản phẩm dạng SP-XXXX.';
    } else {
        $ex = $pdo->prepare('SELECT id FROM products WHERE sku = ? AND id <> ?');
        $ex->execute([$sku, $id]);
        if ($ex->fetch()) {
            $errors['sku'] = 'Mã sản phẩm đã tồn tại.';
        }
    }
    if ($price <= 0) {
        $errors['price'] = 'Giá bán phải lớn hơn 0.';
    }
    if ($stock < 0) {
        $errors['stock'] = 'Tồn kho phải là số không âm.';
    }
    $chk = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
    $chk->execute([$cat]);
    if (!$chk->fetch()) {
        $errors['category_id'] = 'Danh mục không tồn tại.';
    }
    if ($errors) {
        json_fail('Dữ liệu sản phẩm không hợp lệ.', 422, $errors);
    }

    $pdo->beginTransaction();
    try {
        $diff = $stock - (int)$p['stock'];
        $up = $pdo->prepare('UPDATE products SET sku=?, name=?, category_id=?, price=?, stock=?, variants=?, emoji=?, status=? WHERE id=?');
        $up->execute([$sku, $name, $cat, $price, $stock, $variants ?: null, $emoji ?: null, $status, $id]);
        if ($diff !== 0) {
            $pdo->prepare("INSERT INTO inventory_movements (product_id, change_type, quantity, note) VALUES (?, 'dieu_chinh', ?, 'Điều chỉnh tồn kho khi sửa sản phẩm')")
                ->execute([$id, $diff]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_fail('Không thể cập nhật sản phẩm.', 500);
    }
    json_ok(null, 'Đã cập nhật sản phẩm. Giá mới chỉ áp dụng cho đơn hàng phát sinh sau.');
}

if ($method === 'DELETE') {
    require_role('admin');

    $chk = $pdo->prepare('SELECT id FROM products WHERE id = ?');
    $chk->execute([$id]);
    if (!$chk->fetch()) {
        json_fail('Không tìm thấy sản phẩm.', 404);
    }

    // Có dữ liệu tham chiếu (đơn hàng, đổi trả, lịch sử kho) → ẩn thay vì xóa vật lý
    $dep = $pdo->prepare(
        'SELECT (SELECT COUNT(*) FROM order_items WHERE product_id = ?)
              + (SELECT COUNT(*) FROM return_items WHERE product_id = ?)
              + (SELECT COUNT(*) FROM inventory_movements WHERE product_id = ?)'
    );
    $dep->execute([$id, $id, $id]);
    if ((int)$dep->fetchColumn() > 0) {
        $pdo->prepare("UPDATE products SET status = 'hidden' WHERE id = ?")->execute([$id]);
        json_ok(
            ['id' => $id, 'status' => 'hidden'],
            'Sản phẩm đã phát sinh dữ liệu (đơn hàng / đổi trả / kho): chuyển sang Ẩn để giữ lịch sử thay vì xóa.'
        );
    }

    try {
        $st = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $st->execute([$id]);
    } catch (PDOException $e) {
        // Khóa ngoại còn sót → vẫn chuyển sang ẩn thay vì để lỗi vỡ
        $pdo->prepare("UPDATE products SET status = 'hidden' WHERE id = ?")->execute([$id]);
        json_ok(
            ['id' => $id, 'status' => 'hidden'],
            'Sản phẩm không thể xóa do còn dữ liệu liên quan: chuyển sang Ẩn để giữ lịch sử.'
        );
    }
    json_ok(null, 'Đã xóa sản phẩm.');
}

json_fail('Phương thức không được hỗ trợ.', 405);
