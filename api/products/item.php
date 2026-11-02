<?php
/**
 * /api/products/item.php?id=N
 * GET    – Chi tiết sản phẩm (đăng nhập)
 * PUT    – Sửa sản phẩm (admin). Giá mới KHÔNG đổi giá trong đơn đã chốt.
 * DELETE – Vô hiệu hóa/xóa (admin). Đơn đã phát sinh → ẩn thay vì xóa vật lý.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';
require_once __DIR__ . '/../../includes/product_images.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    json_fail('Thiếu id sản phẩm.', 422);
}
$pdo = db();

if ($method === 'GET') {
    // Trang bán hàng (product.html) gọi với ?public=1: xem sản phẩm active
    // không cần đăng nhập. Quản trị vẫn xem được ẩn theo cách gọi thường.
    $isPublic = isset($_GET['public']);
    if (!$isPublic) {
        require_auth();
    }
    $st = $pdo->prepare(
        'SELECT p.*, c.name AS category_name
         FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?'
    );
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row || ($isPublic && $row['status'] !== 'active')) {
        json_fail('Không tìm thấy sản phẩm.', 404);
    }
    $row['price'] = (float)$row['price'];
    $row['stock'] = (int)$row['stock'];
    $row['images'] = of_product_images_parse($row['images'] ?? null);
    $row['cover_image'] = $row['images'][0] ?? null;
    $row['description'] = (string)($row['description'] ?? '');
    $row['specs'] = (string)($row['specs'] ?? '');
    $rs = $pdo->prepare(
        'SELECT COUNT(*) AS total, COALESCE(ROUND(AVG(rating), 1), 0) AS average,
                COALESCE(SUM(rating = 1), 0) AS star1,
                COALESCE(SUM(rating = 2), 0) AS star2,
                COALESCE(SUM(rating = 3), 0) AS star3,
                COALESCE(SUM(rating = 4), 0) AS star4,
                COALESCE(SUM(rating = 5), 0) AS star5
         FROM product_reviews WHERE product_id = ?'
    );
    $rs->execute([$id]);
    $summary = $rs->fetch() ?: ['total' => 0, 'average' => 0, 'star1' => 0, 'star2' => 0, 'star3' => 0, 'star4' => 0, 'star5' => 0];
    $row['review_summary'] = [
        'total' => (int)$summary['total'],
        'average' => (float)$summary['average'],
        'counts' => [
            1 => (int)$summary['star1'],
            2 => (int)$summary['star2'],
            3 => (int)$summary['star3'],
            4 => (int)$summary['star4'],
            5 => (int)$summary['star5'],
        ],
    ];
    // biến thể chi tiết
    $pv = $pdo->prepare('SELECT id, sku, name, price, stock FROM product_variants WHERE product_id = ? ORDER BY id');
    $pv->execute([$id]);
    $row['variant_rows'] = $pv->fetchAll();
    foreach ($row['variant_rows'] as &$vr) {
        $vr['id'] = (int)$vr['id'];
        $vr['price'] = $vr['price'] !== null ? (float)$vr['price'] : null;
        $vr['stock'] = (int)$vr['stock'];
    }
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
    $imageInput = of_product_images_input(array_key_exists('images', $in) ? $in['images'] : ($p['images'] ?? null));
    $images = of_product_images_text($imageInput['urls']);
    $description = trim((string)(array_key_exists('description', $in) ? $in['description'] : ($p['description'] ?? '')));
    $specs = trim((string)(array_key_exists('specs', $in) ? $in['specs'] : ($p['specs'] ?? '')));
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
    if ($imageInput['too_many']) {
        $errors['images'] = 'Chỉ nhận tối đa 5 URL ảnh.';
    } elseif ($imageInput['invalid']) {
        $errors['images'] = 'URL ảnh không hợp lệ: ' . implode(', ', array_slice($imageInput['invalid'], 0, 3));
    }
    if (mb_strlen($description) > 20000) {
        $errors['description'] = 'Mô tả tối đa 20.000 ký tự.';
    }
    if (mb_strlen($specs) > 10000) {
        $errors['specs'] = 'Chi tiết sản phẩm tối đa 10.000 ký tự.';
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
        $lock = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
        $lock->execute([$id]);
        $locked = $lock->fetch();
        if (!$locked) {
            throw new OrderFlowException('Không tìm thấy sản phẩm.', 404);
        }
        $variantRows = of_parse_variant_rows($in['variant_rows'] ?? null, $price);
        $finalStock = $stock;
        $up = $pdo->prepare('UPDATE products SET sku=?, name=?, category_id=?, price=?, stock=?, variants=?, emoji=?, images=?, description=?, specs=?, status=? WHERE id=?');
        $up->execute([$sku, $name, $cat, $price, $stock, $variants ?: null, $emoji ?: null, $images, $description ?: null, $specs ?: null, $status, $id]);
        if (!empty($variantRows)) {
            $sync = of_sync_variants($pdo, $id, $variantRows);
            $finalStock = (int)$sync['stock'];
            if ($variants === '' && $sync['count'] > 0) {
                $variants = implode(', ', array_column($variantRows, 'name'));
            }
            $pdo->prepare('UPDATE products SET stock = ?, variants = ? WHERE id = ?')
                ->execute([$finalStock, $variants ?: null, $id]);
        }
        $diff = $finalStock - (int)$locked['stock'];
        if ($diff !== 0) {
            $pdo->prepare("INSERT INTO inventory_movements (product_id, change_type, quantity, note) VALUES (?, 'dieu_chinh', ?, 'Điều chỉnh tồn kho khi sửa sản phẩm')")
                ->execute([$id, $diff]);
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
        json_fail('Không thể cập nhật sản phẩm.', 500);
    }
    $imageList = of_product_images_parse($images);
    json_ok(
        ['id' => $id, 'stock' => $finalStock, 'images' => $imageList, 'cover_image' => $imageList[0] ?? null, 'description' => $description, 'specs' => $specs],
        'Đã cập nhật sản phẩm. Giá mới chỉ áp dụng cho đơn hàng phát sinh sau.'
    );
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
