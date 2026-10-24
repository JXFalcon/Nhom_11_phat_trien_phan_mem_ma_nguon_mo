<?php
/**
 * /api/products/index.php
 * GET  – Danh sách sản phẩm (đăng nhập). Hỗ trợ ?q=&category_id=&status=&page=&per_page=
 * POST – Tạo sản phẩm (admin). Giá & tồn do server xác thực.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();

if ($method === 'GET') {
    require_auth();
    $q = trim((string)($_GET['q'] ?? ''));
    $cat = (int)($_GET['category_id'] ?? 0);
    $status = (string)($_GET['status'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 50)));

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
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['category_id'] = $r['category_id'] !== null ? (int)$r['category_id'] : null;
        $r['price'] = (float)$r['price'];
        $r['stock'] = (int)$r['stock'];
    }
    json_ok(['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

if ($method === 'POST') {
    require_role('admin');
    $in = request_json();
    $name = trim((string)($in['name'] ?? ''));
    $sku = trim((string)($in['sku'] ?? ''));
    $description = trim((string)($in['description'] ?? ''));
    $cat = (int)($in['category_id'] ?? 0);
    $price = (float)($in['price'] ?? 0);
    $stock = (int)($in['stock'] ?? 0);
    $variants = trim((string)($in['variants'] ?? ''));
    $emoji = trim((string)($in['emoji'] ?? '📦'));
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
    if (mb_strlen($description) > 2000) {
        $errors['description'] = 'Mô tả sản phẩm tối đa 2000 ký tự.';
    }
    if ($errors) {
        json_fail('Dữ liệu sản phẩm không hợp lệ.', 422, $errors);
    }
    $descriptionValue = $description === '' ? null : $description;
    $exists = $pdo->prepare('SELECT id FROM products WHERE sku = ?');
    $exists->execute([$sku]);
    if ($exists->fetch()) {
        json_fail('Mã sản phẩm đã tồn tại.', 409, ['sku' => 'Đã tồn tại.']);
    }
    $ins = $pdo->prepare('INSERT INTO products (sku, name, category_id, price, stock, variants, emoji, status, description) VALUES (?,?,?,?,?,?,?,?,?)');
    $ins->execute([$sku, $name, $cat, $price, $stock, $variants ?: null, $emoji ?: null, $status, $descriptionValue]);
    $id = (int)$pdo->lastInsertId();
    // Ghi phong trào tồn kho ban đầu
    if ($stock > 0) {
        $pdo->prepare("INSERT INTO inventory_movements (product_id, change_type, quantity, note) VALUES (?, 'nhap', ?, 'Tồn kho ban đầu khi tạo sản phẩm')")
            ->execute([$id, $stock]);
    }
    json_ok(['id' => $id, 'sku' => $sku, 'name' => $name, 'description' => $descriptionValue, 'category_id' => $cat, 'price' => $price, 'stock' => $stock, 'variants' => $variants, 'emoji' => $emoji, 'status' => $status], 'Đã thêm sản phẩm.', 201);
}

json_fail('Phương thức không được hỗ trợ.', 405);
