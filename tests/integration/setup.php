<?php
/**
 * Tạo lại database kiểm thử từ database.sql và seed fixture riêng.
 * Chỉ chạy khi tên DB kết thúc bằng "_test" để tránh xóa nhầm DB thật.
 *
 * CLI: php tests/integration/setup.php
 */
declare(strict_types=1);

putenv('OF_DB_NAME=orderflow_test');
$_ENV['OF_DB_NAME'] = 'orderflow_test';
$_SERVER['OF_DB_NAME'] = 'orderflow_test';

$cfg = require __DIR__ . '/../../config/database.php';
$dbName = (string)$cfg['dbname'];
if (!str_ends_with($dbName, '_test')) {
    fwrite(STDERR, "TỪ CHỐI: database kiểm thử phải có tên kết thúc bằng _test (hiện tại: $dbName).\n");
    exit(1);
}

$serverDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $cfg['host'], $cfg['port'], $cfg['charset']);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
];

$sql = file_get_contents(__DIR__ . '/../../database.sql');
if ($sql === false) {
    fwrite(STDERR, "Không đọc được database.sql.\n");
    exit(1);
}
$sql = str_replace(
    [
        'DROP DATABASE IF EXISTS orderflow;',
        'CREATE DATABASE orderflow ',
        'USE orderflow;',
    ],
    [
        "DROP DATABASE IF EXISTS `$dbName`;",
        "CREATE DATABASE `$dbName` ",
        "USE `$dbName`;",
    ],
    $sql
);

try {
    $server = new PDO($serverDsn, $cfg['user'], $cfg['pass'], $options);
    $server->exec($sql);

    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $dbName, $cfg['charset']),
        $cfg['user'],
        $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $hash = password_hash('123456', PASSWORD_BCRYPT);
    $insUser = $pdo->prepare(
        'INSERT INTO users (full_name, email, phone, password_hash, role, status) VALUES (?,?,?,?,?,1)'
    );
    $insUser->execute(['Quản trị kiểm thử', 'admin@test.local', '0900000001', $hash, 'admin']);
    $insUser->execute(['Khách kiểm thử', 'customer@test.local', '0900000002', $hash, 'customer']);

    $pdo->prepare('INSERT INTO categories (code, name, description, status) VALUES (?,?,?,1)')
        ->execute(['TEST', 'Danh mục kiểm thử', 'Fixture cho test tích hợp']);
    $catId = (int)$pdo->lastInsertId();

    $insProduct = $pdo->prepare(
        'INSERT INTO products (sku, name, category_id, price, stock, variants, emoji, status)
         VALUES (?,?,?,?,?,?,?, "active")'
    );
    $products = [
        ['SP-901', 'Tranh hàng cuối', 100, 1, null],
        ['SP-902', 'Gửi lặp thanh toán', 150, 2, null],
        ['SP-903', 'Đổi giá sau đặt', 100, 5, null],
        ['SP-904', 'Trả một phần', 100, 10, null],
        ['SP-905', 'Thanh toán muộn', 300, 1, null],
        ['SP-906', 'Sản phẩm có biến thể', 110, 2, 'Đỏ; Xanh'],
        ['SP-907', 'Tranh hàng song song', 100, 1, null],
        ['SP-908', 'Trả nhiều dòng A', 100, 4, null],
        ['SP-909', 'Trả nhiều dòng B', 200, 3, null],
        ['SP-910', 'Hết hạn giữ hàng', 100, 1, null],
        ['SP-911', 'COD khi giao', 250, 2, null],
        ['SP-912', 'Giá giỏ lúc đặt', 100, 3, null],
        ['SP-913', 'Key chéo A', 100, 1, null],
        ['SP-914', 'Key chéo B', 100, 1, null],
    ];
    $ids = [];
    foreach ($products as [$sku, $name, $price, $stock, $variants]) {
        $insProduct->execute([$sku, $name, $catId, $price, $stock, $variants, '📦']);
        $ids[$sku] = (int)$pdo->lastInsertId();
    }

    $pdo->prepare(
        'INSERT INTO product_variants (product_id, sku, name, price, stock) VALUES (?,?,?,?,?)'
    )->execute([$ids['SP-906'], 'SP-906-R', 'Đỏ', 110, 1]);
    $pdo->prepare(
        'INSERT INTO product_variants (product_id, sku, name, price, stock) VALUES (?,?,?,?,?)'
    )->execute([$ids['SP-906'], 'SP-906-B', 'Xanh', 120, 1]);
} catch (Throwable $e) {
    fwrite(STDERR, 'SETUP LỖI: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Đã tạo database kiểm thử '$dbName' và fixture.\n";
echo json_encode(['products' => $ids], JSON_UNESCAPED_UNICODE) . "\n";
