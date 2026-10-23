<?php
/**
 * /api/categories/index.php
 * GET  – Danh sách danh mục (cần đăng nhập)
 * POST – Tạo danh mục (admin)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();

if ($method === 'GET') {
    require_auth();
    $rows = $pdo->query(
        'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
         FROM categories c ORDER BY c.id'
    )->fetchAll();
    json_ok($rows);
}

if ($method === 'POST') {
    require_role('admin');
    $in = request_json();
    $name = trim((string)($in['name'] ?? ''));
    $description = trim((string)($in['description'] ?? ''));
    $emoji = trim((string)($in['emoji'] ?? '🏷️'));
    if (mb_strlen($name) < 2) {
        json_fail('Tên danh mục phải có ít nhất 2 ký tự.', 422, ['name' => 'Bắt buộc.']);
    }
    $exists = $pdo->prepare('SELECT id FROM categories WHERE name = ?');
    $exists->execute([$name]);
    if ($exists->fetch()) {
        json_fail('Tên danh mục đã tồn tại.', 409);
    }
    $code = 'DM' . str_pad((string)((int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM categories')->fetchColumn()), 2, '0', STR_PAD_LEFT);
    $ins = $pdo->prepare('INSERT INTO categories (code, name, description, emoji) VALUES (?, ?, ?, ?)');
    $ins->execute([$code, $name, $description ?: null, $emoji ?: null]);
    $id = (int)$pdo->lastInsertId();
    json_ok(['id' => $id, 'code' => $code, 'name' => $name, 'description' => $description, 'emoji' => $emoji, 'product_count' => 0], 'Đã thêm danh mục.', 201);
}

json_fail('Phương thức không được hỗ trợ.', 405);
