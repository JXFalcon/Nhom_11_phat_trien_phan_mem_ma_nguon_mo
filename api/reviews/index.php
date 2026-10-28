<?php
/**
 * /api/reviews/index.php?product_id=N
 * GET - Danh sách đánh giá công khai của một sản phẩm đang bán.
 * Hỗ trợ: rating=1..5, has_image=1, has_comment=1, sort=newest|helpful, page, per_page.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/product_images.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    json_fail('Phương thức không được hỗ trợ.', 405);
}

$pdo = db();
$productId = (int)($_GET['product_id'] ?? 0);
if ($productId <= 0) {
    json_fail('Thiếu product_id.', 422);
}

$check = $pdo->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
$check->execute([$productId]);
if (!$check->fetch()) {
    json_fail('Không tìm thấy sản phẩm.', 404);
}

$rating = (int)($_GET['rating'] ?? 0);
$hasImage = !empty($_GET['has_image']);
$hasComment = !empty($_GET['has_comment']);
$sort = ($_GET['sort'] ?? '') === 'helpful' ? 'helpful' : 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 10)));

$where = ['product_id = ?'];
$params = [$productId];
if ($rating >= 1 && $rating <= 5) {
    $where[] = 'rating = ?';
    $params[] = $rating;
}
if ($hasImage) {
    $where[] = "images IS NOT NULL AND TRIM(images) <> ''";
}
if ($hasComment) {
    $where[] = "comment IS NOT NULL AND TRIM(comment) <> ''";
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$count = $pdo->prepare("SELECT COUNT(*) FROM product_reviews $whereSql");
$count->execute($params);
$total = (int)$count->fetchColumn();

$summarySt = $pdo->prepare(
    'SELECT COUNT(*) AS total, COALESCE(ROUND(AVG(rating), 1), 0) AS average,
            COALESCE(SUM(rating = 1), 0) AS star1,
            COALESCE(SUM(rating = 2), 0) AS star2,
            COALESCE(SUM(rating = 3), 0) AS star3,
            COALESCE(SUM(rating = 4), 0) AS star4,
            COALESCE(SUM(rating = 5), 0) AS star5,
            COALESCE(SUM(images IS NOT NULL AND TRIM(images) <> \'\'), 0) AS with_images,
            COALESCE(SUM(comment IS NOT NULL AND TRIM(comment) <> \'\'), 0) AS with_comments
     FROM product_reviews WHERE product_id = ?'
);
$summarySt->execute([$productId]);
$summaryRow = $summarySt->fetch() ?: [];

$offset = ($page - 1) * $perPage;
$order = $sort === 'helpful' ? 'helpful_count DESC, created_at DESC, id DESC' : 'created_at DESC, id DESC';
$st = $pdo->prepare(
    "SELECT id, reviewer_name, rating, variant, comment, images, helpful_count, created_at
     FROM product_reviews $whereSql ORDER BY $order LIMIT $perPage OFFSET $offset"
);
$st->execute($params);
$items = [];
foreach ($st->fetchAll() as $row) {
    $images = of_product_images_parse($row['images'] ?? null);
    $items[] = [
        'id' => (int)$row['id'],
        'reviewer_name' => (string)$row['reviewer_name'],
        'rating' => (int)$row['rating'],
        'variant' => $row['variant'] !== null ? (string)$row['variant'] : null,
        'comment' => $row['comment'] !== null ? (string)$row['comment'] : null,
        'images' => $images,
        'image_count' => count($images),
        'helpful_count' => (int)$row['helpful_count'],
        'created_at' => (string)$row['created_at'],
    ];
}

json_ok([
    'items' => $items,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'summary' => [
        'total' => (int)($summaryRow['total'] ?? 0),
        'average' => (float)($summaryRow['average'] ?? 0),
        'counts' => [
            1 => (int)($summaryRow['star1'] ?? 0),
            2 => (int)($summaryRow['star2'] ?? 0),
            3 => (int)($summaryRow['star3'] ?? 0),
            4 => (int)($summaryRow['star4'] ?? 0),
            5 => (int)($summaryRow['star5'] ?? 0),
        ],
        'with_images' => (int)($summaryRow['with_images'] ?? 0),
        'with_comments' => (int)($summaryRow['with_comments'] ?? 0),
    ],
]);
