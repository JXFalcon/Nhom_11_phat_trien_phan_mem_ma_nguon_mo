<?php
/**
 * Migration bổ sung đánh giá sản phẩm mẫu còn thiếu từ database.sql (canonical seed).
 *
 * Web: http://127.0.0.1:8090/database/migrate_seed_reviews_web.php?run=1
 * CLI: php database/migrate_seed_reviews_web.php --run
 *
 * Nguyên tắc:
 *  - KHÔNG xóa đánh giá hiện có; chỉ thêm dòng canonical chưa tồn tại.
 *  - So khớp theo (product_id, reviewer_name, rating, variant, comment, created_at).
 *  - Idempotent: chạy lại không tạo bản ghi trùng.
 */
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
$run = $isCli ? in_array('--run', $argv ?? [], true) : isset($_GET['run']);
if (!$run) {
    http_response_code(400);
    echo 'Chưa kích hoạt. Web: ?run=1 — CLI: php database/migrate_seed_reviews_web.php --run';
    exit;
}

if (!$isCli) {
    $host = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (!in_array($host, ['127.0.0.1', 'localhost', '0.0.0.0', '::1'], true)) {
        http_response_code(403);
        echo 'Chỉ cho phép truy cập từ localhost.';
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$sql = file_get_contents(__DIR__ . '/../database.sql');
if ($sql === false) {
    fwrite(STDERR, "Không đọc được database.sql\n");
    exit(1);
}

$startMarker = '-- Đánh giá sản phẩm';
$endMarker = '-- Tồn kho ban đầu';
$start = strpos($sql, $startMarker);
$end = strpos($sql, $endMarker);
if ($start === false || $end === false || $end <= $start) {
    fwrite(STDERR, "Không tìm thấy khối đánh giá sản phẩm trong database.sql\n");
    exit(1);
}

$statements = [];
$buffer = '';
foreach (preg_split('/\r?\n/', substr($sql, $start, $end - $start)) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '--')) {
        continue;
    }
    $buffer .= ($buffer === '' ? '' : ' ') . $line;
    if (str_ends_with($line, ';')) {
        $statements[] = trim($buffer);
        $buffer = '';
    }
}

$staged = 0;
$inserted = 0;

$pdo->beginTransaction();
try {
    $pdo->exec('DROP TEMPORARY TABLE IF EXISTS product_reviews_stage');
    $pdo->exec('CREATE TEMPORARY TABLE product_reviews_stage LIKE product_reviews');

    foreach ($statements as $statement) {
        if (!preg_match('/^INSERT INTO `?product_reviews`?/i', $statement)) {
            continue;
        }
        $staged += $pdo->exec(preg_replace('/^INSERT INTO `?product_reviews`?/i', 'INSERT INTO product_reviews_stage', $statement, 1));
    }

    $inserted = $pdo->exec(
        'INSERT INTO product_reviews
            (product_id, reviewer_name, rating, variant, comment, images, helpful_count, created_at)
         SELECT s.product_id, s.reviewer_name, s.rating, s.variant, s.comment, s.images, s.helpful_count, s.created_at
         FROM product_reviews_stage s
         WHERE NOT EXISTS (
             SELECT 1 FROM product_reviews p
             WHERE p.product_id = s.product_id
               AND p.reviewer_name = s.reviewer_name
               AND p.rating = s.rating
               AND p.variant <=> s.variant
               AND p.comment <=> s.comment
               AND p.created_at = s.created_at
         )'
    );

    $pdo->exec('DROP TEMPORARY TABLE IF EXISTS product_reviews_stage');
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Bổ sung đánh giá thất bại: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Đã xử lý đánh giá mẫu.\n";
echo "  - Câu lệnh canonical: $staged\n";
echo "  - Đánh giá bổ sung: $inserted\n";
echo "  - Tổng đánh giá hiện tại: " . (int)$pdo->query('SELECT COUNT(*) FROM product_reviews')->fetchColumn() . "\n";
