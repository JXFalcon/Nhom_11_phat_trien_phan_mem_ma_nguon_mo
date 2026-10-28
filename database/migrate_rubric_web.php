<?php
/**
 * Migration additive cho Đề tài 19 (variant, idempotency, sequence, unique cart).
 *
 * Web:  http://127.0.0.1:8090/database/migrate_rubric_web.php?run=1
 * CLI:  php database/migrate_rubric_web.php --run
 *
 * Nguyên tắc an toàn:
 *  - Chỉ thêm bảng/cột/index còn thiếu; backfill cột mới (sku, variant_id, variant rỗng);
 *    không xóa, không gộp, không sửa dữ liệu nghiệp vụ.
 *  - Nếu dữ liệu hiện có xung đột unique, migration bỏ qua index đó và ghi cảnh báo.
 *  - Có thể chạy lại nhiều lần (idempotent), không tự xóa file.
 */
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
$run = $isCli ? in_array('--run', $argv ?? [], true) : isset($_GET['run']);
if (!$run) {
    http_response_code(400);
    echo 'Chưa kích hoạt. Web: ?run=1 · CLI: php database/migrate_rubric_web.php --run';
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
$log = [];
$warnings = [];

function col_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $st->execute([$table, $index]);
    return (int)$st->fetchColumn() > 0;
}

function table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
}

function fk_exists(PDO $pdo, string $table, string $fk): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?
           AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = "FOREIGN KEY"'
    );
    $st->execute([$table, $fk]);
    return (int)$st->fetchColumn() > 0;
}

function ddl(PDO $pdo, string $sql, string $ok, array &$log, array &$warnings): void
{
    try {
        $pdo->exec($sql);
        $log[] = $ok;
    } catch (Throwable $e) {
        $warnings[] = $ok . ' – bỏ qua: ' . $e->getMessage();
    }
}

function scalar_int(PDO $pdo, string $sql): int
{
    return (int)$pdo->query($sql)->fetchColumn();
}

/* ---------- Cột còn thiếu ở bảng cũ ---------- */
if (!col_exists($pdo, 'orders', 'reserved_until')) {
    ddl($pdo, 'ALTER TABLE orders ADD COLUMN reserved_until DATETIME NULL AFTER total_amount', 'Thêm orders.reserved_until', $log, $warnings);
}
if (!col_exists($pdo, 'orders', 'stock_restored')) {
    ddl($pdo, 'ALTER TABLE orders ADD COLUMN stock_restored TINYINT NOT NULL DEFAULT 0', 'Thêm orders.stock_restored', $log, $warnings);
}
if (!col_exists($pdo, 'orders', 'cancel_reason')) {
    ddl($pdo, 'ALTER TABLE orders ADD COLUMN cancel_reason VARCHAR(255) DEFAULT NULL', 'Thêm orders.cancel_reason', $log, $warnings);
}
if (!col_exists($pdo, 'products', 'images')) {
    ddl($pdo, "ALTER TABLE products ADD COLUMN images TEXT DEFAULT NULL COMMENT 'Danh sách URL ảnh, mỗi dòng một URL, tối đa 5' AFTER emoji", 'Thêm products.images', $log, $warnings);
}

$payStatus = $pdo->query("SHOW COLUMNS FROM payments LIKE 'status'")->fetch();
if ($payStatus && !str_contains((string)$payStatus['Type'], "'hoan_tien'")) {
    ddl(
        $pdo,
        "ALTER TABLE payments MODIFY status ENUM('thanh_cong','cho_xu_ly','that_bai','hoan_tien') NOT NULL DEFAULT 'cho_xu_ly'",
        'Bổ sung payments.status hoan_tien',
        $log,
        $warnings
    );
}

/* ---------- Biến thể ---------- */
if (!col_exists($pdo, 'product_variants', 'sku')) {
    ddl($pdo, 'ALTER TABLE product_variants ADD COLUMN sku VARCHAR(40) DEFAULT NULL AFTER product_id', 'Thêm product_variants.sku', $log, $warnings);
}
if (!col_exists($pdo, 'product_variants', 'price')) {
    ddl($pdo, 'ALTER TABLE product_variants ADD COLUMN price DECIMAL(14,0) DEFAULT NULL AFTER name', 'Thêm product_variants.price', $log, $warnings);
}
$pdo->exec('UPDATE product_variants SET sku = CONCAT("SPV-", id) WHERE sku IS NULL OR sku = ""');

$dupVariants = scalar_int(
    $pdo,
    'SELECT COUNT(*) FROM (
        SELECT product_id, name FROM product_variants GROUP BY product_id, name HAVING COUNT(*) > 1
     ) d'
);
if (!index_exists($pdo, 'product_variants', 'uq_pv_product_name')) {
    if ($dupVariants === 0) {
        ddl($pdo, 'ALTER TABLE product_variants ADD UNIQUE KEY uq_pv_product_name (product_id, name)', 'Thêm unique product_variants(product_id, name)', $log, $warnings);
    } else {
        $warnings[] = "Bỏ qua unique product_variants(product_id, name): có $dupVariants cặp trùng.";
    }
}

/* ---------- variant_id ---------- */
foreach ([
    'order_items' => 'oi',
    'cart_items' => 'ci',
    'reservations' => 'res',
] as $table => $prefix) {
    if (!col_exists($pdo, $table, 'variant_id')) {
        ddl($pdo, "ALTER TABLE `$table` ADD COLUMN variant_id INT UNSIGNED DEFAULT NULL AFTER product_id", "Thêm $table.variant_id", $log, $warnings);
    }
    $idx = 'idx_' . $prefix . '_variant';
    if (!index_exists($pdo, $table, $idx)) {
        ddl($pdo, "ALTER TABLE `$table` ADD KEY `$idx` (variant_id)", "Thêm index $idx", $log, $warnings);
    }
    $fk = 'fk_' . $prefix . '_variant';
    if (!fk_exists($pdo, $table, $fk)) {
        ddl(
            $pdo,
            "ALTER TABLE `$table` ADD CONSTRAINT `$fk`
             FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE SET NULL",
            "Thêm FK $table.variant_id",
            $log,
            $warnings
        );
    }
}

$pdo->exec(
    'UPDATE order_items oi JOIN product_variants pv
       ON pv.product_id = oi.product_id AND pv.name = oi.variant
     SET oi.variant_id = pv.id WHERE oi.variant_id IS NULL'
);
$pdo->exec(
    'UPDATE cart_items ci JOIN product_variants pv
       ON pv.product_id = ci.product_id AND pv.name = ci.variant
     SET ci.variant_id = pv.id WHERE ci.variant_id IS NULL'
);
$pdo->exec(
    'UPDATE reservations r JOIN product_variants pv
       ON pv.product_id = r.product_id AND pv.name = r.variant
     SET r.variant_id = pv.id WHERE r.variant_id IS NULL'
);
$log[] = 'Backfill variant_id theo tên biến thể';

/* ---------- Giỏ hàng ---------- */
if (col_exists($pdo, 'cart_items', 'variant')) {
    $pdo->exec('UPDATE cart_items SET variant = "" WHERE variant IS NULL');
    $cartCol = $pdo->query("SHOW COLUMNS FROM cart_items LIKE 'variant'")->fetch();
    if ($cartCol && strtoupper((string)$cartCol['Null']) === 'YES') {
        ddl($pdo, 'ALTER TABLE cart_items MODIFY variant VARCHAR(120) NOT NULL DEFAULT ""', 'Chuẩn hóa cart_items.variant NOT NULL', $log, $warnings);
    }
}

$dupCarts = scalar_int(
    $pdo,
    'SELECT COUNT(*) FROM (SELECT user_id FROM carts GROUP BY user_id HAVING COUNT(*) > 1) d'
);
if (!index_exists($pdo, 'carts', 'uq_carts_user')) {
    if ($dupCarts === 0) {
        ddl($pdo, 'ALTER TABLE carts ADD UNIQUE KEY uq_carts_user (user_id)', 'Thêm unique carts.user_id', $log, $warnings);
    } else {
        $warnings[] = "Bỏ qua unique carts.user_id: có $dupCarts user có nhiều giỏ.";
    }
}

$dupCartItems = scalar_int(
    $pdo,
    'SELECT COUNT(*) FROM (
        SELECT cart_id, product_id, variant FROM cart_items
        GROUP BY cart_id, product_id, variant HAVING COUNT(*) > 1
     ) d'
);
if (!index_exists($pdo, 'cart_items', 'uq_ci_cart_product_variant')) {
    if ($dupCartItems === 0) {
        ddl(
            $pdo,
            'ALTER TABLE cart_items ADD UNIQUE KEY uq_ci_cart_product_variant (cart_id, product_id, variant)',
            'Thêm unique cart_items(cart_id, product_id, variant)',
            $log,
            $warnings
        );
    } else {
        $warnings[] = "Bỏ qua unique cart_items: có $dupCartItems nhóm dòng trùng.";
    }
}

/* ---------- Idempotency thanh toán ---------- */
$dupKeys = scalar_int(
    $pdo,
    'SELECT COUNT(*) FROM (
        SELECT idempotency_key FROM payments
        WHERE idempotency_key IS NOT NULL AND idempotency_key <> ""
        GROUP BY idempotency_key HAVING COUNT(*) > 1
     ) d'
);
if (!index_exists($pdo, 'payments', 'uq_payments_idem')) {
    if ($dupKeys === 0) {
        ddl($pdo, 'ALTER TABLE payments ADD UNIQUE KEY uq_payments_idem (idempotency_key)', 'Thêm unique payments.idempotency_key', $log, $warnings);
    } else {
        $warnings[] = "Bỏ qua unique payments.idempotency_key: có $dupKeys key bị lặp (không tự sửa dữ liệu).";
    }
}
if (!index_exists($pdo, 'payments', 'idx_payments_refund')) {
    ddl($pdo, 'ALTER TABLE payments ADD KEY idx_payments_refund (status, order_id)', 'Thêm index payments(status, order_id)', $log, $warnings);
}

/* ---------- Vận đơn: mỗi đơn chỉ 1 shipment ---------- */
$dupShipments = scalar_int(
    $pdo,
    'SELECT COUNT(*) FROM (SELECT order_id FROM shipments GROUP BY order_id HAVING COUNT(*) > 1) d'
);
if (!index_exists($pdo, 'shipments', 'uq_shipments_order')) {
    if ($dupShipments === 0) {
        ddl($pdo, 'ALTER TABLE shipments ADD UNIQUE KEY uq_shipments_order (order_id)', 'Thêm unique shipments.order_id', $log, $warnings);
    } else {
        $warnings[] = "Bỏ qua unique shipments.order_id: có $dupShipments đơn có nhiều vận đơn (không tự xóa dữ liệu).";
    }
}

/* ---------- Bảng mới ---------- */
if (!table_exists($pdo, 'payment_events')) {
    ddl(
        $pdo,
        'CREATE TABLE payment_events (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          payment_id INT UNSIGNED NOT NULL,
          order_id INT UNSIGNED NOT NULL,
          idempotency_key VARCHAR(64) DEFAULT NULL,
          result VARCHAR(20) NOT NULL,
          outcome VARCHAR(40) NOT NULL COMMENT \'created|accepted|failed|refunded|duplicate_replay|late_rejected_refund|ignored_terminal\',
          detail VARCHAR(255) DEFAULT NULL,
          created_by INT UNSIGNED DEFAULT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_pe_payment (payment_id),
          KEY idx_pe_order (order_id),
          KEY idx_pe_key (idempotency_key),
          CONSTRAINT fk_pe_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE,
          CONSTRAINT fk_pe_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
          CONSTRAINT fk_pe_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB',
        'Tạo bảng payment_events',
        $log,
        $warnings
    );
}

if (!table_exists($pdo, 'code_sequences')) {
    ddl(
        $pdo,
        'CREATE TABLE code_sequences (
          name VARCHAR(32) NOT NULL,
          value INT UNSIGNED NOT NULL DEFAULT 0,
          PRIMARY KEY (name)
        ) ENGINE=InnoDB',
        'Tạo bảng code_sequences',
        $log,
        $warnings
    );
}

if (table_exists($pdo, 'code_sequences')) {
    $seq = $pdo->prepare(
        'INSERT INTO code_sequences (name, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = GREATEST(value, VALUES(value))'
    );
    foreach ([
        'orders' => ['orders', 'order_code'],
        'payments' => ['payments', 'payment_code'],
        'shipments' => ['shipments', 'shipment_code'],
        'reservations' => ['reservations', 'reservation_code'],
        'return_requests' => ['return_requests', 'return_code'],
    ] as $seqName => [$table, $column]) {
        if (!table_exists($pdo, $table)) {
            continue;
        }
        $max = (int)$pdo->query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(`$column`, LOCATE('-', `$column`) + 1) AS UNSIGNED)), 0)
             FROM `$table`"
        )->fetchColumn();
        $seq->execute([$seqName, $max]);
    }
    $log[] = 'Khởi tạo code_sequences theo mã lớn nhất hiện có';
}

/* ---------- Kết quả ---------- */
$lines = array_merge($log, $warnings ? ['', 'CẢNH BÁO:'] : [], $warnings);
$text = implode("\n", $lines) . "\nHoàn tất. Migration chỉ thêm cấu trúc còn thiếu, không xóa dữ liệu.";
if ($isCli) {
    echo $text . "\n";
} else {
    echo '<pre>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</pre>';
}
