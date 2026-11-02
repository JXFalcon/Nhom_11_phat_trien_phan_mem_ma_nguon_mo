<?php
/**
 * /api/products/index.php
 * GET  – Danh sách sản phẩm (đăng nhập). Hỗ trợ ?q=&category_id=&status=&page=&per_page=
 * POST – Tạo sản phẩm (admin). Giá & tồn do server xác thực.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/order_lifecycle.php';
require_once __DIR__ . '/../../includes/product_images.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();

if ($method === 'GET') {
    // Storefront (index.html) gọi với ?public=1: chỉ xem các sản phẩm active, không cần đăng nhập.
    $isPublic = isset($_GET['public']);
    if (!$isPublic) {
        require_auth();
    }
    $q = trim((string)($_GET['q'] ?? ''));
    $cat = (int)($_GET['category_id'] ?? 0);
    $status = (string)($_GET['status'] ?? '');
    if ($isPublic && $status !== 'active') {
        $status = 'active';
    }
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 50)));

    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($cat > 0) {
        $where[] = 'p.category_id = ?';
        $params[] = $cat;
    }
    if ($status === 'active' || $status === 'hidden') {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stc = $pdo->prepare("SELECT COUNT(*) FROM products p $w");
    $stc->execute($params);
    $total = (int)$stc->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $st = $pdo->prepare(
        "SELECT p.*, c.name AS category_name,
         (SELECT COALESCE(SUM(ri.quantity_accepted),0) FROM return_items ri WHERE ri.product_id = p.id) AS returned_qty
         FROM products p LEFT JOIN categories c ON c.id = p.category_id
         $w ORDER BY p.id DESC LIMIT $perPage OFFSET $offset"
    );
    $st->execute($params);
    $rows = $st->fetchAll();
    $ids = array_map(static fn(array $r): int => (int)$r['id'], $rows);
    $variantMap = [];
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pv = $pdo->prepare(
            "SELECT id, product_id, sku, name, price, stock
             FROM product_variants WHERE product_id IN ($ph) ORDER BY product_id, id"
        );
        $pv->execute($ids);
        foreach ($pv->fetchAll() as $vr) {
            $pid = (int)$vr['product_id'];
            $variantMap[$pid][] = [
                'id' => (int)$vr['id'],
                'sku' => $vr['sku'],
                'name' => $vr['name'],
                'price' => $vr['price'] !== null ? (float)$vr['price'] : null,
                'stock' => (int)$vr['stock'],
            ];
        }
    }
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['category_id'] = $r['category_id'] !== null ? (int)$r['category_id'] : null;
        $r['price'] = (float)$r['price'];
        $r['stock'] = (int)$r['stock'];
        $r['variant_rows'] = $variantMap[$r['id']] ?? [];
        $r['images'] = of_product_images_parse($r['images'] ?? null);
        $r['cover_image'] = $r['images'][0] ?? null;
    }
    json_ok(['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

if ($method === 'POST') {
    require_role('admin');
    $in = request_json();
    $name = trim((string)($in['name'] ?? ''));
    $sku = trim((string)($in['sku'] ?? ''));
    $cat = (int)($in['category_id'] ?? 0);
    $price = (float)($in['price'] ?? 0);
    $stock = (int)($in['stock'] ?? 0);
    $variants = trim((string)($in['variants'] ?? ''));
    $emoji = trim((string)($in['emoji'] ?? '📦'));
    $imageInput = of_product_images_input($in['images'] ?? null);
    $images = of_product_images_text($imageInput['urls']);
    $description = trim((string)($in['description'] ?? ''));
    $specs = trim((string)($in['specs'] ?? ''));
    $status = ($in['status'] ?? 'active') === 'hidden' ? 'hidden' : 'active';

    $errors = [];
    if (mb_strlen($name) < 2) {
        $errors['name'] = 'Tên sản phẩm phải có ít nhất 2 ký tự.';
    }
    if ($sku === '' || !preg_match('/^SP-\d{3,}$/', $sku)) {
        $errors['sku'] = 'Mã sản phẩm dạng SP-XXXX.';
    }
    if ($cat <= 0) {
        $errors['category_id'] = 'Hãy chọn danh mục.';
    } else {
        $chk = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $chk->execute([$cat]);
        if (!$chk->fetch()) {
            $errors['category_id'] = 'Danh mục không tồn tại.';
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
    if ($errors) {
        json_fail('Dữ liệu sản phẩm không hợp lệ.', 422, $errors);
    }
    $exists = $pdo->prepare('SELECT id FROM products WHERE sku = ?');
    $exists->execute([$sku]);
    if ($exists->fetch()) {
        json_fail('Mã sản phẩm đã tồn tại.', 409, ['sku' => 'Đã tồn tại.']);
    }

    $pdo->beginTransaction();
    try {
        $variantRows = of_parse_variant_rows($in['variant_rows'] ?? null, $price);
        $ins = $pdo->prepare('INSERT INTO products (sku, name, category_id, price, stock, variants, emoji, images, description, specs, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([$sku, $name, $cat, $price, $stock, $variants ?: null, $emoji ?: null, $images, $description ?: null, $specs ?: null, $status]);
        $id = (int)$pdo->lastInsertId();

        if (!empty($variantRows)) {
            $sync = of_sync_variants($pdo, $id, $variantRows);
            $stock = (int)$sync['stock'];
            $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$stock, $id]);
            if ($variants === '' && $sync['count'] > 0) {
                $variants = implode(', ', array_column($variantRows, 'name'));
                $pdo->prepare('UPDATE products SET variants = ? WHERE id = ?')->execute([$variants, $id]);
            }
        }

        if ($stock > 0) {
            $pdo->prepare("INSERT INTO inventory_movements (product_id, change_type, quantity, note) VALUES (?, 'nhap', ?, 'Tồn kho ban đầu khi tạo sản phẩm')")
                ->execute([$id, $stock]);
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
        json_fail('Không thể thêm sản phẩm.', 500);
    }

    $imageList = of_product_images_parse($images);
    json_ok(['id' => $id, 'sku' => $sku, 'name' => $name, 'category_id' => $cat, 'price' => $price, 'stock' => $stock, 'variants' => $variants, 'emoji' => $emoji, 'images' => $imageList, 'cover_image' => $imageList[0] ?? null, 'description' => $description, 'specs' => $specs, 'status' => $status, 'variant_rows' => $variantRows ?? []], 'Đã thêm sản phẩm.', 201);
}

json_fail('Phương thức không được hỗ trợ.', 405);
