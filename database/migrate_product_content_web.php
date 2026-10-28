<?php
/**
 * Migration additive: bổ sung mô tả, chi tiết và đánh giá sản phẩm.
 *
 * Web: http://127.0.0.1:8090/database/migrate_product_content_web.php?run=1
 * CLI: php database/migrate_product_content_web.php --run
 *
 * Nguyên tắc:
 *  - Chỉ thêm cột/bảng còn thiếu; không xóa, không sửa dữ liệu nghiệp vụ.
 *  - Chỉ điền mô tả/chi tiết khi cột đang NULL hoặc rỗng.
 *  - Chỉ sinh đánh giá cho sản phẩm chưa có đánh giá nào.
 *  - Idempotent: chạy lại nhiều lần không nhân đôi dữ liệu. Không tự xóa file.
 */
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
$run = $isCli ? in_array('--run', $argv ?? [], true) : isset($_GET['run']);
if (!$run) {
    http_response_code(400);
    echo 'Chưa kích hoạt. Web: ?run=1 hoặc CLI: php database/migrate_product_content_web.php --run';
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
require_once __DIR__ . '/product_content_extra.php';

$pdo = db();
$log = [];
$warnings = [];

function pc_col_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
}

function pc_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
}

function pc_ddl(PDO $pdo, string $sql, string $ok, array &$log, array &$warnings): void
{
    try {
        $pdo->exec($sql);
        $log[] = $ok;
    } catch (Throwable $e) {
        $warnings[] = $ok . ' - bỏ qua: ' . $e->getMessage();
    }
}

if (!pc_col_exists($pdo, 'products', 'description')) {
    pc_ddl($pdo, "ALTER TABLE products ADD COLUMN description TEXT DEFAULT NULL COMMENT 'Mô tả sản phẩm'", 'Thêm products.description', $log, $warnings);
}
if (!pc_col_exists($pdo, 'products', 'specs')) {
    pc_ddl($pdo, "ALTER TABLE products ADD COLUMN specs TEXT DEFAULT NULL COMMENT 'Chi tiết sản phẩm, mỗi dòng Tên: Giá trị'", 'Thêm products.specs', $log, $warnings);
}
if (!pc_table_exists($pdo, 'product_reviews')) {
    pc_ddl(
        $pdo,
        "CREATE TABLE product_reviews (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id INT UNSIGNED NOT NULL,
            reviewer_name VARCHAR(100) NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            variant VARCHAR(120) DEFAULT NULL,
            comment TEXT DEFAULT NULL,
            images TEXT DEFAULT NULL COMMENT 'Danh sách URL ảnh review, mỗi dòng một URL',
            helpful_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pr_product_created (product_id, created_at),
            KEY idx_pr_product_rating (product_id, rating),
            CONSTRAINT fk_pr_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
        ) ENGINE=InnoDB",
        'Tạo bảng product_reviews',
        $log,
        $warnings
    );
}

$select = $pdo->query(
    "SELECT p.id, p.sku, p.name, COALESCE(c.code, '') AS category_code,
            p.price, p.stock, COALESCE(p.variants, '') AS variants,
            COALESCE(p.emoji, '') AS emoji, COALESCE(p.images, '') AS images,
            p.description, p.specs
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.id"
)->fetchAll();

$update = $pdo->prepare('UPDATE products SET description = ?, specs = ? WHERE id = ?');
$reviewCount = $pdo->prepare('SELECT COUNT(*) FROM product_reviews WHERE product_id = ?');
$insertReview = $pdo->prepare(
    'INSERT INTO product_reviews (product_id, reviewer_name, rating, variant, comment, images, helpful_count, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

$productsUpdated = 0;
$reviewsAdded = 0;

$pdo->beginTransaction();
try {
    foreach ($select as $row) {
        $product = [
            (string)$row['sku'],
            (string)$row['name'],
            (string)$row['category_code'],
            (float)$row['price'],
            (int)$row['stock'],
            (string)$row['variants'],
            (string)$row['emoji'],
            (string)$row['images'],
        ];

        $description = trim((string)($row['description'] ?? ''));
        $specs = trim((string)($row['specs'] ?? ''));
        $needsDescription = $description === '';
        $needsSpecs = $specs === '';
        if ($needsDescription || $needsSpecs) {
            $update->execute([
                $needsDescription ? of_product_content_description($product) : $description,
                $needsSpecs ? of_product_content_specs_text(of_product_content_specs($product)) : $specs,
                (int)$row['id'],
            ]);
            $productsUpdated++;
        }

        $reviewCount->execute([(int)$row['id']]);
        if ((int)$reviewCount->fetchColumn() === 0) {
            foreach (of_product_content_reviews($product) as $review) {
                $insertReview->execute([
                    (int)$row['id'],
                    (string)$review['reviewer_name'],
                    (int)$review['rating'],
                    $review['variant'],
                    $review['comment'],
                    $review['images'],
                    (int)$review['helpful_count'],
                    (string)$review['created_at'],
                ]);
                $reviewsAdded++;
            }
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo 'Migration thất bại: ' . $e->getMessage();
    exit;
}

if ($log) {
    echo implode("\n", $log) . "\n";
}
echo "Đã cập nhật mô tả/chi tiết cho $productsUpdated sản phẩm; thêm $reviewsAdded đánh giá.\n";
if ($warnings) {
    echo "Cảnh báo:\n- " . implode("\n- ", $warnings) . "\n";
}
