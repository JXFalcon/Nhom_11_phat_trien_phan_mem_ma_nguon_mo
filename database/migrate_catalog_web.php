<?php
/**
 * Migration additive: thêm catalog sản phẩm mở rộng (57 sản phẩm, 311 biến thể).
 *
 * Web: http://127.0.0.1:8090/database/migrate_catalog_web.php?run=1
 * CLI: php database/migrate_catalog_web.php --run
 *
 * Nguyên tắc:
 *  - Chỉ thêm sản phẩm chưa tồn tại theo SKU; không sửa/xóa sản phẩm, đơn hàng hay tồn kho cũ.
 *  - Mỗi sản phẩm được thêm trong một transaction cùng biến thể và movement tồn kho ban đầu.
 *  - Idempotent: chạy lại sẽ bỏ qua các SKU đã có. Không tự xóa file.
 */
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
$run = $isCli ? in_array('--run', $argv ?? [], true) : isset($_GET['run']);
if (!$run) {
    http_response_code(400);
    echo 'Chưa kích hoạt. Web: ?run=1 · CLI: php database/migrate_catalog_web.php --run';
    exit;
}

if (!$isCli) {
    $host = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (!in_array($host, ['127.0.0.1', 'localhost', '0.0.0.0', '::1'], true)) {
        http_response_code(403);
        echo 'Chỉ cho phép truy cập từ localhost.';
        exit;
    }
}

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$catalog = require __DIR__ . '/catalog_extra.php';
$categoryIds = [];
foreach ($pdo->query('SELECT id, code FROM categories') as $row) {
    $categoryIds[(string)$row['code']] = (int)$row['id'];
}

$find = $pdo->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
$insertProduct = $pdo->prepare(
    'INSERT INTO products (sku, name, category_id, price, stock, variants, emoji, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, "active")'
);
$insertVariant = $pdo->prepare(
    'INSERT INTO product_variants (product_id, sku, name, price, stock) VALUES (?, ?, ?, ?, ?)'
);
$insertMovement = $pdo->prepare(
    "INSERT INTO inventory_movements (product_id, change_type, quantity, note, created_by)
     VALUES (?, 'nhap', ?, 'Tồn kho ban đầu cho catalog mở rộng (migration)', 1)"
);

$added = 0;
$skipped = 0;
$variantsAdded = 0;
$warnings = [];

foreach ($catalog['products'] as $p) {
    [$sku, $name, $categoryCode, $price, $stock, $variantString, $emoji] = $p;
    $categoryId = $categoryIds[$categoryCode] ?? 0;
    if ($categoryId <= 0) {
        $warnings[] = $sku . ': không tìm thấy danh mục ' . $categoryCode;
        continue;
    }

    $find->execute([$sku]);
    if ($find->fetchColumn()) {
        $skipped++;
        continue;
    }

    $variants = array_values(array_filter(array_map('trim', explode(';', $variantString)), static fn(string $v): bool => $v !== ''));
    if (!$variants) {
        $warnings[] = $sku . ': không có biến thể, bỏ qua.';
        continue;
    }

    $base = intdiv((int)$stock, count($variants));
    $rem = (int)$stock % count($variants);

    $pdo->beginTransaction();
    try {
        $insertProduct->execute([
            $sku,
            $name,
            $categoryId,
            (int)$price,
            (int)$stock,
            $variantString,
            $emoji,
        ]);
        $productId = (int)$pdo->lastInsertId();

        foreach ($variants as $index => $variantName) {
            $variantStock = $base + ($index < $rem ? 1 : 0);
            $insertVariant->execute([
                $productId,
                sprintf('SPV-%d-%02d', $productId, $index + 1),
                $variantName,
                catalog_variant_price((int)$price, $variantName, $categoryCode),
                $variantStock,
            ]);
            $variantsAdded++;
        }

        if ((int)$stock > 0) {
            $insertMovement->execute([$productId, (int)$stock]);
        }
        $pdo->commit();
        $added++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $warnings[] = $sku . ': ' . $e->getMessage();
    }
}

echo "Catalog: thêm $added sản phẩm, $variantsAdded biến thể; bỏ qua $skipped SKU đã có.\n";
if ($warnings) {
    echo "Cảnh báo:\n- " . implode("\n- ", $warnings) . "\n";
}
