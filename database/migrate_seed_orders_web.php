<?php
/**
 * Migration khôi phục dữ liệu đơn hàng mẫu từ database.sql (canonical seed).
 *
 * Web: http://127.0.0.1:8090/database/migrate_seed_orders_web.php?run=1
 * CLI: php database/migrate_seed_orders_web.php --run
 *
 * Nguyên tắc:
 *  - KHÔNG chạy DROP DATABASE, KHÔNG xóa dữ liệu hiện có.
 *  - Dữ liệu đơn hàng đang có được dời sang dải id lớn (100000+) trước khi
 *    nhập bộ seed canonical (id 1..102), nhờ vậy không ghi đè đơn thật.
 *  - Chỉ nhập các bảng liên quan đơn hàng: orders, order_items, order_history,
 *    payments, shipments, return_requests, return_items, reservations,
 *    inventory_movements, payment_events.
 *  - Idempotent: nếu đã có DH-1001 thì bỏ qua toàn bộ phần nhập seed.
 */
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
$run = $isCli ? in_array('--run', $argv ?? [], true) : isset($_GET['run']);
if (!$run) {
    http_response_code(400);
    echo 'Chưa kích hoạt. Web: ?run=1 — CLI: php database/migrate_seed_orders_web.php --run';
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
$seedPath = __DIR__ . '/../database.sql';
$sql = is_file($seedPath) ? file_get_contents($seedPath) : false;
if ($sql === false) {
    fwrite(STDERR, "Không đọc được database.sql\n");
    exit(1);
}

$marker = '-- Đơn hàng + chi tiết';
$start = strpos($sql, $marker);
if ($start === false) {
    fwrite(STDERR, "Không tìm thấy mốc dữ liệu đơn hàng trong database.sql\n");
    exit(1);
}

$allowed = [
    'orders', 'order_items', 'order_history', 'payments', 'shipments',
    'return_requests', 'return_items', 'reservations', 'inventory_movements',
    'payment_events',
];

$statements = [];
$buffer = '';
foreach (preg_split('/\r?\n/', substr($sql, $start)) as $line) {
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
if ($buffer !== '') {
    $statements[] = trim($buffer);
}

$hasCanonical = (bool)$pdo->query("SELECT 1 FROM orders WHERE order_code = 'DH-1001' LIMIT 1")->fetchColumn();
if ($hasCanonical) {
    echo "Bộ seed đơn hàng DH-1001 đã tồn tại. Bỏ qua (idempotent).\n";
    exit(0);
}

$maxId = 0;
foreach (['orders', 'order_items', 'payments', 'shipments', 'reservations', 'return_requests', 'return_items', 'payment_events'] as $t) {
    $maxId = max($maxId, (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM `$t`")->fetchColumn());
}
$offset = max(100000, $maxId + 1000);
$shifted = 0;
$inserted = 0;
$skipped = 0;
$errors = [];

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->beginTransaction();
try {
    // Dời đơn hàng hiện có (nếu có) sang dải id lớn để nhường id canonical.
    $shifts = [
        ['orders', ['id']],
        ['order_items', ['id', 'order_id']],
        ['payments', ['id', 'order_id']],
        ['shipments', ['id', 'order_id']],
        ['reservations', ['id', 'order_id']],
        ['order_history', ['order_id']],
        ['inventory_movements', ['order_id']],
        ['payment_events', ['order_id', 'payment_id']],
        ['return_requests', ['id', 'order_id']],
        ['return_items', ['id', 'order_item_id', 'return_request_id']],
    ];
    $nullableFk = ['order_id', 'payment_id', 'order_item_id', 'return_request_id'];
    foreach ($shifts as [$table, $columns]) {
        foreach ($columns as $col) {
            $nullGuard = in_array($col, $nullableFk, true) ? "`$col` IS NOT NULL AND " : '';
            $shifted += $pdo->exec(
                "UPDATE `$table` SET `$col` = `$col` + $offset WHERE $nullGuard`$col` <= 200"
            );
        }
    }

    foreach ($statements as $statement) {
        if (!preg_match('/^INSERT INTO `?([a-z_]+)`?/i', $statement, $m)) {
            if (preg_match('/^UPDATE\s+`?orders`?\s/i', $statement)) {
                $pdo->exec($statement);
            }
            continue;
        }
        $table = strtolower($m[1]);
        if (!in_array($table, $allowed, true) || $table === 'code_sequences') {
            $skipped++;
            continue;
        }
        $prepared = preg_replace('/^INSERT INTO/i', 'INSERT IGNORE INTO', $statement, 1);
        $pdo->exec($prepared);
        $inserted++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errors[] = $e->getMessage();
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

if ($errors) {
    fwrite(STDERR, "Khôi phục thất bại: " . implode(' | ', $errors) . "\n");
    exit(1);
}

// Đồng bộ lại bộ đếm mã để next_code() không sinh trùng mã seed.
$sequences = [
    'orders' => ['orders', 'order_code', 'DH-'],
    'payments' => ['payments', 'payment_code', 'GD-'],
    'shipments' => ['shipments', 'shipment_code', 'VC-'],
    'reservations' => ['reservations', 'reservation_code', 'RV-'],
    'return_requests' => ['return_requests', 'return_code', 'DT-'],
];
foreach ($sequences as $name => [$table, $col, $prefix]) {
    $offsetLen = strlen($prefix) + 1;
    $max = (int)$pdo->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(`$col`, $offsetLen) AS UNSIGNED)), 0) FROM `$table`"
    )->fetchColumn();
    $pdo->prepare('INSERT INTO code_sequences (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = GREATEST(value, VALUES(value))')
        ->execute([$name, $max]);
}

echo "Đã khôi phục dữ liệu đơn hàng mẫu.\n";
echo "  - Bản ghi dời sang dải id lớn: $shifted\n";
echo "  - Câu lệnh seed đã nhập: $inserted\n";
echo "  - Bỏ qua (bảng ngoài phạm vi): $skipped\n";
echo "  - Tổng đơn hàng hiện tại: " . (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() . "\n";
