<?php
/**
 * /api/categories/item.php?id=N
 * GET   – Chi tiết danh mục
 * PUT   – Sửa danh mục (admin)
 * DELETE– Xóa danh mục (admin, chặn nếu còn sản phẩm)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    json_fail('Thiếu id danh mục.', 422);
}
$pdo = db();

if ($method === 'GET') {
    require_auth();
    $st = $pdo->prepare('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count FROM categories c WHERE c.id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        json_fail('Không tìm thấy danh mục.', 404);
    }
    json_ok($row);
}

if ($method === 'PUT') {
    require_role('admin');
    $in = request_json();
    $name = trim((string)($in['name'] ?? ''));
    $description = trim((string)($in['description'] ?? ''));
    $emoji = trim((string)($in['emoji'] ?? '🏷️'));
    if (mb_strlen($name) < 2) {
        json_fail('Tên danh mục phải có ít nhất 2 ký tự.', 422);
    }
    $exists = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND id <> ?');
    $exists->execute([$name, $id]);
    if ($exists->fetch()) {
        json_fail('Tên danh mục đã tồn tại.', 409);
    }
    $st = $pdo->prepare('UPDATE categories SET name = ?, description = ?, emoji = ? WHERE id = ?');
    $st->execute([$name, $description ?: null, $emoji ?: null, $id]);
    if ($st->rowCount() === 0) {
        // có thể không đổi gì → vẫn kiểm tra tồn tại
        $chk = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $chk->execute([$id]);
        if (!$chk->fetch()) {
            json_fail('Không tìm thấy danh mục.', 404);
        }
    }
    json_ok(null, 'Đã cập nhật danh mục.');
}

if ($method === 'DELETE') {
    require_role('admin');
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
    $cnt->execute([$id]);
    if ((int)$cnt->fetchColumn() > 0) {
        json_fail('Không thể xóa: danh mục vẫn còn sản phẩm.', 409);
    }
    $st = $pdo->prepare('DELETE FROM categories WHERE id = ?');
    $st->execute([$id]);
    if ($st->rowCount() === 0) {
        json_fail('Không tìm thấy danh mục.', 404);
    }
    json_ok(null, 'Đã xóa danh mục.');
}

json_fail('Phương thức không được hỗ trợ.', 405);
