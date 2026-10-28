<?php
/**
 * Shopee – Sinh database.sql (schema chuẩn + dữ liệu mẫu).
 * Chạy: php database/generate_seed.php
 *       php database/generate_seed.php --output=database.preview.sql
 *       php database/generate_seed.php --stdout
 *
 * Schema được đọc từ database.sql hiện tại (canonical) để tránh lặp lại DDL lỗi thời.
 * Script chỉ ghi file, không kết nối MySQL. KHÔNG import đè database thật khi chưa backup.
 */
declare(strict_types=1);

$opts = getopt('', ['output::', 'stdout']);
$toStdout = isset($opts['stdout']);
$target = is_string($opts['output'] ?? null) && $opts['output'] !== ''
    ? $opts['output']
    : __DIR__ . '/../database.sql';

$sourcePath = __DIR__ . '/../database.sql';
$source = is_file($sourcePath) ? file_get_contents($sourcePath) : false;
if ($source === false) {
    fwrite(STDERR, "Không đọc được database.sql để lấy schema chuẩn.\n");
    exit(1);
}

$schemaStart = strpos($source, 'CREATE TABLE users (');
$seedMarker = strpos($source, '-- Tài khoản (mật khẩu');
if ($schemaStart === false || $seedMarker === false || $seedMarker <= $schemaStart) {
    fwrite(STDERR, "database.sql không còn khối schema chuẩn. Khôi phục từ git trước khi chạy generator.\n");
    exit(1);
}
$schema = rtrim(substr($source, $schemaStart, $seedMarker - $schemaStart));
foreach (['payment_events', 'code_sequences', 'carts', 'reservations', 'variant_id', 'reserved_until'] as $required) {
    if (!str_contains($schema, $required)) {
        fwrite(STDERR, "Schema trong database.sql thiếu '$required'. Khôi phục schema chuẩn trước khi sinh seed.\n");
        exit(1);
    }
}

$hash123 = '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW';

function lcg(int $seed): callable
{
    $s = $seed;
    return function () use (&$s): float {
        $s = (int)((($s * 1664525) + 1013904223) % 4294967296);
        return $s / 4294967296;
    };
}

function pick(callable $rng, array $arr)
{
    return $arr[(int)floor($rng() * count($arr))];
}

function intR(callable $rng, int $min, int $max): int
{
    return $min + (int)floor($rng() * ($max - $min + 1));
}

function shuffled(callable $rng, array $arr): array
{
    for ($i = count($arr) - 1; $i > 0; $i--) {
        $j = (int)floor($rng() * ($i + 1));
        [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
    }
    return $arr;
}

function sqlStr(string $s): string
{
    return "'" . addslashes($s) . "'";
}

function sqlDt(string $iso): string
{
    return "'" . date('Y-m-d H:i:s', strtotime($iso)) . "'";
}

$categories = [
    ['DM01', 'Điện thoại', 'Smartphone và điện thoại di động', '📱'],
    ['DM02', 'Laptop', 'Máy tính xách tay các dòng', '💻'],
    ['DM03', 'Máy tính bảng', 'Tablet và phụ kiện đi kèm', '📟'],
    ['DM04', 'Âm thanh', 'Tai nghe, loa bluetooth', '🎧'],
    ['DM05', 'Đồng hồ thông minh', 'Smartwatch và vòng đeo tay', '⌚'],
    ['DM06', 'Phụ kiện', 'Ốp lưng, sạc, cáp, bàn phím, chuột', '🖱️'],
    ['DM07', 'Gia dụng', 'Đồ gia dụng nhà bếp', '🏠'],
    ['DM08', 'Thời trang', 'Áo khoác, giày thể thao', '👟'],
];

$products = [
    ['SP-1001', 'iPhone 15 Pro Max 256GB', 'DM01', 34990000, 42, '256GB / Titan Tự nhiên; 512GB / Xanh; 1TB / Đen', '📱'],
    ['SP-1002', 'Samsung Galaxy S24 Ultra', 'DM01', 27990000, 28, '256GB / Tím; 512GB / Xám', '📱'],
    ['SP-1003', 'Xiaomi 14T Pro 5G', 'DM01', 12990000, 55, '256GB / Đen; 512GB / Xanh', '📱'],
    ['SP-1004', 'MacBook Air M3 13-inch', 'DM02', 28990000, 18, '8GB/256GB; 16GB/512GB', '💻'],
    ['SP-1005', 'Dell XPS 13 Plus', 'DM02', 31500000, 12, '16GB/512GB / Bạch kim', '💻'],
    ['SP-1006', 'ASUS ROG Strix G16', 'DM02', 39990000, 9, '16GB/1TB / Đen; 32GB/1TB', '💻'],
    ['SP-1007', 'iPad Air M2 11-inch', 'DM03', 15990000, 24, '64GB WiFi; 256GB WiFi', '📟'],
    ['SP-1008', 'Samsung Galaxy Tab S9 FE', 'DM03', 10490000, 16, '128GB WiFi / Xám', '📟'],
    ['SP-1009', 'AirPods Pro 2 (USB-C)', 'DM04', 5990000, 60, 'Tiêu chuẩn', '🎧'],
    ['SP-1010', 'Sony WH-1000XM5', 'DM04', 7490000, 33, 'Đen; Bạc; Xanh dương', '🎧'],
    ['SP-1011', 'Loa JBL Charge 5', 'DM04', 3290000, 40, 'Đen; Xanh; Đỏ', '🔊'],
    ['SP-1012', 'Apple Watch Series 9', 'DM05', 10990000, 21, '41mm GPS; 45mm GPS; 45mm Cellular', '⌚'],
    ['SP-1013', 'Garmin Fenix 7 Solar', 'DM05', 18500000, 7, '47mm / Đen', '⌚'],
    ['SP-1014', 'Chuột Logitech MX Master 3S', 'DM06', 2490000, 50, 'Graphite; Pale Grey', '🖱️'],
    ['SP-1015', 'Bàn phím Keychron K2 Pro', 'DM06', 2190000, 35, 'RGB / Brown Switch', '⌨️'],
    ['SP-1016', 'Màn hình LG UltraGear 27GQ750', 'DM06', 7990000, 14, '27" 4K 144Hz', '🖥️'],
    ['SP-1017', 'Nồi cơm điện Panasonic SR-DF183', 'DM07', 1290000, 45, '1.8L', '🍚'],
    ['SP-1018', 'Lò vi sóng Samsung MS23F301', 'DM07', 2590000, 22, '23L', '🔥'],
    ['SP-1019', 'Áo khoác Unifree chống nước', 'DM08', 890000, 80, 'S / Đen; M / Đen; L / Xám', '🧥'],
    ['SP-1020', 'Giày Nike Air Force 1 \'07', 'DM08', 2790000, 3, '40 / Trắng; 41 / Trắng; 42 / Trắng', '👟'],
];

$baseProductCount = count($products);
$catalogExtra = require __DIR__ . '/catalog_extra.php';
$products = array_merge($products, $catalogExtra['products']);
require_once __DIR__ . '/product_content_extra.php';

$carriers = ['Giao Hàng Nhanh (GHN)', 'J&T Express', 'Viettel Post', 'Ninja Van'];

$variantPriceOverrides = [
    1 => ['1TB / Đen' => 39990000],
    4 => ['16GB/512GB' => 32990000],
];

$out = [];
$out[] = '-- ============================================================';
$out[] = '-- Shopee – Cơ sở dữ liệu bài tập lớn đề tài 19';
$out[] = '-- Quản lý đơn hàng và đổi trả trong bán hàng trực tuyến';
$out[] = '-- MySQL / MariaDB (XAMPP) · Import bằng phpMyAdmin hoặc mysql CLI';
$out[] = '-- Mật khẩu demo: 123456 (đã băm bcrypt)';
$out[] = '-- ============================================================';
$out[] = 'DROP DATABASE IF EXISTS orderflow;';
$out[] = 'CREATE DATABASE orderflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
$out[] = 'USE orderflow;';
$out[] = '';
$out[] = $schema;
$out[] = '';

/* ===================== USERS ===================== */
$users = [
    ['Nguyễn Văn Admin', 'admin@orderflow.vn', '0901000001', 'admin'],
    ['Trần Thị Hoa', 'nhanvien@orderflow.vn', '0901000002', 'staff'],
    ['Lê Minh Tuấn', 'khachhang@orderflow.vn', '0901000003', 'customer'],
];
$first = ['Nguyễn','Trần','Lê','Phạm','Hoàng','Vũ','Đặng','Bùi','Đỗ','Hồ'];
$mid = ['Văn','Thị','Quốc','Minh','Thanh','Ngọc','Hữu','Gia','Bảo','Anh'];
$last = ['An','Bình','Chi','Dũng','Em','Phúc','Hà','Khôi','Linh','Mai','Nam','Oanh','Phong','Quân','Sơn','Trang','Uyên','Vinh','Xuân','Yên'];
$rng = lcg(4242);
for ($i = 4; $i <= 30; $i++) {
    $role = pick($rng, ['customer','customer','customer','staff','customer']);
    $users[] = [
        pick($rng, $first) . ' ' . pick($rng, $mid) . ' ' . pick($rng, $last),
        'user' . str_pad((string)$i, 2, '0', STR_PAD_LEFT) . '@orderflow.vn',
        '09' . intR($rng, 10000000, 99999999),
        $role,
    ];
}
$out[] = '-- Tài khoản (mật khẩu 123456 – bcrypt)';
foreach ($users as $i => $u) {
    [$fn, $em, $ph, $role] = $u;
    $out[] = sprintf(
        "INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (%d, %s, %s, %s, %s, '%s');",
        $i + 1, sqlStr($fn), sqlStr($em), sqlStr($ph), sqlStr($hash123), $role
    );
}
$out[] = '';

/* ===================== CATEGORIES & PRODUCTS ===================== */
$out[] = '-- Danh mục';
foreach ($categories as $i => $c) {
    $out[] = sprintf(
        "INSERT INTO categories (id, code, name, description, emoji) VALUES (%d, %s, %s, %s, %s);",
        $i + 1, sqlStr($c[0]), sqlStr($c[1]), sqlStr($c[2]), sqlStr($c[3])
    );
}
$out[] = '';
$out[] = '-- Sản phẩm (stock = lượng có thể bán) và biến thể';
$variantIds = [];
$variantSeq = 0;
foreach ($products as $i => $p) {
    $pid = $i + 1;
    $description = of_product_content_description($p);
    $specs = of_product_content_specs_text(of_product_content_specs($p));
    $out[] = sprintf(
        "INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji, description, specs) VALUES (%d, %s, %s, (SELECT id FROM (SELECT id FROM categories WHERE code=%s) t), %d, %d, %s, %s, %s, %s);",
        $pid, sqlStr($p[0]), sqlStr($p[1]), sqlStr($p[2]), $p[3], $p[4], sqlStr($p[5]), sqlStr($p[6]), sqlStr($description), sqlStr($specs)
    );
    $vs = array_values(array_filter(array_map('trim', explode(';', $p[5])), static fn(string $v): bool => $v !== ''));
    $base = intdiv($p[4], max(1, count($vs)));
    $rem = $p[4] % max(1, count($vs));
    foreach ($vs as $k => $vn) {
        $variantSeq++;
        $variantIds[$pid . '|' . $vn] = $variantSeq;
        $vStock = $base + ($k < $rem ? 1 : 0);
        $variantPrice = $variantPriceOverrides[$pid][$vn] ?? catalog_variant_price($p[3], $vn, $p[2]);
        $out[] = sprintf(
            "INSERT INTO product_variants (id, product_id, sku, name, price, stock) VALUES (%d, %d, %s, %s, %d, %d);",
            $variantSeq, $pid, sqlStr(sprintf('SPV-%d', $variantSeq)), sqlStr($vn), $variantPrice, $vStock
        );
    }
}
$out[] = '';
$out[] = '-- Đánh giá sản phẩm (dữ liệu mẫu)';
foreach ($products as $i => $p) {
    $pid = $i + 1;
    foreach (of_product_content_reviews($p) as $review) {
        $variant = $review['variant'] !== null ? sqlStr((string)$review['variant']) : 'NULL';
        $comment = $review['comment'] !== null ? sqlStr((string)$review['comment']) : 'NULL';
        $images = $review['images'] !== null ? sqlStr((string)$review['images']) : 'NULL';
        $out[] = sprintf(
            "INSERT INTO product_reviews (product_id, reviewer_name, rating, variant, comment, images, helpful_count, created_at) VALUES (%d, %s, %d, %s, %s, %s, %d, %s);",
            $pid,
            sqlStr((string)$review['reviewer_name']),
            (int)$review['rating'],
            $variant,
            $comment,
            $images,
            (int)$review['helpful_count'],
            sqlDt((string)$review['created_at'])
        );
    }
}
$out[] = '';
$out[] = '-- Tồn kho ban đầu cho catalog mở rộng';
for ($i = $baseProductCount; $i < count($products); $i++) {
    $pid = $i + 1;
    $p = $products[$i];
    if ((int)$p[4] <= 0) {
        continue;
    }
    $out[] = sprintf(
        "INSERT INTO inventory_movements (product_id, change_type, quantity, note, created_by) VALUES (%d, 'nhap', %d, 'Tồn kho ban đầu cho catalog mở rộng (seed)', 1);",
        $pid,
        (int)$p[4]
    );
}
$out[] = '';

/* ===================== ORDERS & RELATED ===================== */
$orderProductKeys = range(0, $baseProductCount - 1);
$rng = lcg(20261021);
$now = time();
$customersPool = [];
for ($i = 1; $i <= count($users); $i++) {
    $r = $users[$i - 1][3];
    if ($r === 'customer' || $r === 'staff') {
        $customersPool[] = $i;
    }
}
$statusPool = array_merge(
    array_fill(0, 12, 'cho_xac_nhan'),
    array_fill(0, 10, 'da_xac_nhan'),
    array_fill(0, 18, 'dang_giao'),
    array_fill(0, 45, 'da_giao'),
    array_fill(0, 15, 'da_huy')
);
$statusPool = shuffled($rng, $statusPool);

$orderItems = [];
$itemSeq = 0;
$out[] = '-- Đơn hàng + chi tiết + thanh toán + giao hàng';
for ($i = 0; $i < 100; $i++) {
    $oid = $i + 1;
    $code = 'DH-' . (1000 + $oid);
    $status = $statusPool[$i];
    $daysAgo = intR($rng, 0, 59);
    $ts = $now - $daysAgo * 86400 - intR($rng, 0, 20) * 3600;
    $orderDate = date('Y-m-d H:i:s', $ts);
    $custId = pick($rng, $customersPool);
    $custName = $users[$custId - 1][0];
    $custPhone = $users[$custId - 1][2];
    $address = 'Quận ' . intR($rng, 1, 12) . ', TP. Hồ Chí Minh';
    $method = $rng() < 0.45 ? 'cod' : ($rng() < 0.6 ? 'bank' : 'momo');

    $used = [];
    $items = [];
    $total = 0;
    $itemCount = intR($rng, 1, 3);
    for ($k = 0; $k < $itemCount; $k++) {
        do {
            $pi = pick($rng, $orderProductKeys);
            $pi++;
        } while (isset($used[$pi]));
        $used[$pi] = true;
        $pr = $products[$pi - 1];
        $qty = intR($rng, 1, 3);
        $vs = array_values(array_filter(array_map('trim', explode(';', $pr[5])), static fn(string $v): bool => $v !== ''));
        $variant = pick($rng, $vs);
        $price = $variantPriceOverrides[$pi][$variant] ?? $pr[3];
        $items[] = [
            'product_id' => $pi,
            'variant_id' => $variantIds[$pi . '|' . $variant] ?? null,
            'sku' => $pr[0],
            'name' => $pr[1],
            'variant' => $variant,
            'qty' => $qty,
            'price' => $price,
            'line' => $qty * $price,
        ];
        $total += $qty * $price;
    }

    $out[] = sprintf(
        "INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (%d, %s, %d, %s, %s, %s, %s, '%s', %d, '%s');",
        $oid, sqlStr($code), $custId, sqlStr($custName), sqlStr($custPhone), sqlStr($address), sqlDt($orderDate), $method, $total, $status
    );
    foreach ($items as $it) {
        $itemSeq++;
        $itemId = $itemSeq;
        $orderItems[$oid][] = ['id' => $itemId] + $it;
        $out[] = sprintf(
            "INSERT INTO order_items (id, order_id, product_id, variant_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (%d, %d, %d, %s, %s, %s, %s, %d, %d, %d);",
            $itemId, $oid, $it['product_id'],
            $it['variant_id'] !== null ? (string)$it['variant_id'] : 'NULL',
            sqlStr($it['sku']), sqlStr($it['name']), sqlStr($it['variant']),
            $it['qty'], $it['price'], $it['line']
        );
    }

    if ($status !== 'cho_xac_nhan') {
        $out[] = sprintf("INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (%d, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');", $oid);
    }
    if (in_array($status, ['dang_giao', 'da_giao'], true)) {
        $out[] = sprintf("INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (%d, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');", $oid);
    }
    if ($status === 'da_giao') {
        $out[] = sprintf("INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (%d, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');", $oid);
    }
    if ($status === 'da_huy') {
        $out[] = sprintf("INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (%d, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);", $oid);
    }

    if ($status === 'cho_xac_nhan') {
        $payStatus = $method === 'cod' ? 'cho_xu_ly' : ($rng() < 0.7 ? 'thanh_cong' : 'cho_xu_ly');
    } elseif ($status === 'da_huy') {
        $payStatus = $method === 'cod' ? 'cho_xu_ly' : 'that_bai';
    } else {
        $payStatus = 'thanh_cong';
    }
    $payProcessed = in_array($payStatus, ['thanh_cong', 'that_bai'], true) ? date('Y-m-d H:i:s', $ts + 3600) : null;
    $out[] = sprintf(
        "INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (%d, %s, %d, %d, '%s', '%s', %s, %s);",
        $oid, sqlStr('GD-' . (5000 + $oid)), $oid, $total, $method, $payStatus,
        sqlStr('Thanh toán mô phỏng (seed)'),
        $payProcessed === null ? 'NULL' : sqlDt($payProcessed)
    );

    if (in_array($status, ['da_xac_nhan', 'dang_giao', 'da_giao'], true)) {
        $shipStatus = $status === 'da_xac_nhan'
            ? 'cho_lay_hang'
            : ($status === 'dang_giao' ? ($rng() < 0.08 ? 'that_bai' : 'dang_van_chuyen') : 'da_giao');
        $out[] = sprintf(
            "INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (%d, %s, %d, %s, %s, %s, '%s');",
            $oid, sqlStr('VC-' . (7000 + $oid)), $oid,
            sqlStr(pick($rng, $carriers)),
            sqlStr('OF' . intR($rng, 100000000, 999999999)),
            sqlDt(date('Y-m-d H:i:s', $ts + 86400)),
            $shipStatus
        );
    }
}
$out[] = '';

/* ===================== RETURNS ===================== */
$out[] = '-- Yêu cầu đổi trả (từ đơn đã giao)';
$deliveredOids = [];
foreach ($statusPool as $i => $st) {
    if ($st === 'da_giao' && !empty($orderItems[$i + 1])) {
        $deliveredOids[] = $i + 1;
    }
}
$reasons = [
    'Sản phẩm bị lỗi màn hình, có điểm chết',
    'Giao sai màu so với đơn đã đặt',
    'Khách đổi ý trong 15 ngày, hàng còn nguyên seal',
    'Hộp móp, phụ kiện thiếu cáp sạc',
    'Máy bị nóng bất thường khi sạc',
];
$retStatuses = ['cho_xu_ly', 'da_chap_nhan', 'tu_choi', 'da_hoan_tat'];
$deliveredOids = shuffled($rng, $deliveredOids);
$rid = 0;
foreach (array_slice($deliveredOids, 0, 15) as $oid) {
    $rid++;
    $st = $retStatuses[($rid - 1) % 4];
    $firstItem = $orderItems[$oid][0];
    $qtyReq = min(intR($rng, 1, 2), (int)$firstItem['qty']);
    $qtyAcc = $st === 'da_hoan_tat' ? $qtyReq : ($st === 'da_chap_nhan' ? min(1, $qtyReq) : 0);
    $refund = $qtyAcc * (int)$firstItem['price'];
    $processed = in_array($st, ['da_chap_nhan', 'tu_choi', 'da_hoan_tat'], true)
        ? sqlDt(date('Y-m-d H:i:s', $now - intR($rng, 1, 10) * 86400))
        : 'NULL';
    $out[] = sprintf(
        "INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (%d, %s, %d, (SELECT user_id FROM orders WHERE id=%d), (SELECT customer_name FROM orders WHERE id=%d), %s, '%s', %s, %s);",
        $rid, sqlStr('DT-' . (200 + $rid)), $oid, $oid, $oid, sqlStr(pick($rng, $reasons)), $st,
        in_array($st, ['da_chap_nhan', 'tu_choi', 'da_hoan_tat'], true) ? '1' : 'NULL',
        $processed
    );
    $out[] = sprintf(
        "INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) VALUES (%d, %d, %d, %s, %d, %d, %d, %d, 1);",
        $rid, $firstItem['id'], $firstItem['product_id'], sqlStr($firstItem['name']),
        $qtyReq, $qtyAcc, $firstItem['price'], $refund
    );
}
$out[] = '';

/* ===================== INVENTORY MOVEMENTS ===================== */
$out[] = '-- Phong trào tồn kho mẫu (xuất theo đơn + hủy hoàn kho)';
for ($i = 1; $i <= 40; $i++) {
    $st = $statusPool[$i - 1];
    if ($st === 'da_huy') {
        $out[] = sprintf("INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, %d, 'huy_don', oi.quantity, 'Hoàn kho khi hủy đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = %d;", $i, $i);
    } elseif (in_array($st, ['da_xac_nhan', 'dang_giao', 'da_giao'], true)) {
        $out[] = sprintf("INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, %d, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = %d;", $i, $i);
    }
}
$out[] = '';

/* ===================== EXPIRED DEMO ORDERS ===================== */
$out[] = '-- Đơn hàng hết hạn giữ hàng mẫu (để kiểm thử phép thử 5)';
$expiredOrders = [
    [101, 'DH-1101', 3, 'Lê Minh Tuấn', '0901000003', 'Quận 7, TP. Hồ Chí Minh', 10, 'SP-1010', 'Sony WH-1000XM5', 'Xanh dương', 7490000, 'cod', 10],
    [102, 'DH-1102', 4, 'Nguyễn Minh Quân', '0958054853', 'Quận 8, TP. Hồ Chí Minh', 8, 'SP-1008', 'Samsung Galaxy Tab S9 FE', '128GB WiFi / Xám', 10490000, 'momo', 5],
];
foreach ($expiredOrders as [$oid, $code, $uid, $name, $phone, $addr, $pid, $sku, $pname, $variant, $price, $method, $minutes]) {
    $itemSeq++;
    $out[] = sprintf(
        "INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, reserved_until) VALUES (%d, %s, %d, %s, %s, %s, %s, '%s', %d, 'cho_xac_nhan', DATE_SUB(NOW(), INTERVAL %d MINUTE));",
        $oid, sqlStr($code), $uid, sqlStr($name), sqlStr($phone), sqlStr($addr),
        sqlDt(date('Y-m-d H:i:s', $now - 2 * 86400)), $method, $price, $minutes
    );
    $out[] = sprintf(
        "INSERT INTO order_items (id, order_id, product_id, variant_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (%d, %d, %d, %s, %s, %s, %s, 1, %d, %d);",
        $itemSeq, $oid, $pid, (string)($variantIds[$pid . '|' . $variant] ?? 'NULL'),
        sqlStr($sku), sqlStr($pname), sqlStr($variant), $price, $price
    );
    $out[] = sprintf(
        "INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (%d, NULL, 'cho_xac_nhan', 'Tạo đơn hàng (seed hết hạn)');",
        $oid
    );
    $out[] = sprintf(
        "INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (%d, %s, %d, %d, '%s', 'cho_xu_ly', %s, NULL);",
        $oid, sqlStr('GD-' . (5000 + $oid)), $oid, $price, $method, sqlStr('Thanh toán mô phỏng (seed)')
    );
}
$out[] = '';

$out[] = '-- Reservation hết hạn mẫu';
$out[] = sprintf(
    "INSERT INTO reservations (reservation_code, order_id, user_id, product_id, variant_id, variant, quantity, unit_price, status, reserved_until) VALUES\n"
    . "('RV-2001', 101, 3, 10, %s, 'Xanh dương', 1, 7490000, 'pending', DATE_SUB(NOW(), INTERVAL 10 MINUTE)),\n"
    . "('RV-2002', 102, 4, 8, %s, '128GB WiFi / Xám', 1, 10490000, 'pending', DATE_SUB(NOW(), INTERVAL 5 MINUTE));",
    (string)($variantIds['10|Xanh dương'] ?? 'NULL'),
    (string)($variantIds['8|128GB WiFi / Xám'] ?? 'NULL')
);
$out[] = '';

$out[] = '-- Phiếu giữ hàng mẫu (reservations)';
$out[] = sprintf(
    "INSERT INTO reservations (reservation_code, order_id, user_id, product_id, variant_id, variant, quantity, unit_price, status, reserved_until) VALUES\n"
    . "('RV-1001', 21, 11, 1, %s, '256GB / Titan Tự nhiên', 1, 34990000, 'pending', DATE_ADD(NOW(), INTERVAL 25 MINUTE)),\n"
    . "('RV-1002', 22, 12, 2, %s, '256GB / Tím', 1, 27990000, 'pending', DATE_ADD(NOW(), INTERVAL 25 MINUTE)),\n"
    . "('RV-1003', 23, 13, 3, %s, '256GB / Đen', 1, 12990000, 'confirmed', DATE_SUB(NOW(), INTERVAL 10 MINUTE)),\n"
    . "('RV-1004', 24, 14, 4, %s, '8GB/256GB', 1, 28990000, 'expired', DATE_SUB(NOW(), INTERVAL 1 HOUR)),\n"
    . "('RV-1005', NULL, 15, 5, %s, '16GB/512GB / Bạch kim', 1, 31500000, 'pending', DATE_ADD(NOW(), INTERVAL 30 MINUTE));",
    (string)($variantIds['1|256GB / Titan Tự nhiên'] ?? 'NULL'),
    (string)($variantIds['2|256GB / Tím'] ?? 'NULL'),
    (string)($variantIds['3|256GB / Đen'] ?? 'NULL'),
    (string)($variantIds['4|8GB/256GB'] ?? 'NULL'),
    (string)($variantIds['5|16GB/512GB / Bạch kim'] ?? 'NULL')
);
$out[] = '';

$out[] = '-- Cập nhật reserved_until cho một số đơn chờ xác nhận (một số đã hết hạn)';
$out[] = 'UPDATE orders SET reserved_until = DATE_ADD(NOW(), INTERVAL 25 MINUTE) WHERE id IN (21, 22);';
$out[] = 'UPDATE orders SET reserved_until = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE id IN (23, 24);';
$out[] = '';

/* ===================== CARTS ===================== */
$out[] = '-- Giỏ hàng mẫu (mỗi khách tối đa một giỏ)';
$out[] = "INSERT INTO carts (id, user_id, status) VALUES (1, 3, 'active'), (2, 4, 'active');";
$out[] = sprintf(
    "INSERT INTO cart_items (cart_id, product_id, variant_id, variant, quantity, unit_price) VALUES (1, 1, %s, '256GB / Titan Tự nhiên', 1, 34990000);",
    (string)($variantIds['1|256GB / Titan Tự nhiên'] ?? 'NULL')
);
$out[] = sprintf(
    "INSERT INTO cart_items (cart_id, product_id, variant_id, variant, quantity, unit_price) VALUES (2, 14, %s, 'Graphite', 2, 2490000);",
    (string)($variantIds['14|Graphite'] ?? 'NULL')
);
$out[] = '';

/* ===================== CODE SEQUENCES ===================== */
$out[] = '-- Bộ đếm mã nguyên tử (tránh trùng mã khi tạo đồng thời)';
$out[] = "INSERT INTO code_sequences (name, value) VALUES ('orders', 1102), ('payments', 5102), ('shipments', 7100), ('reservations', 2002), ('return_requests', " . (200 + $rid) . ');';
$out[] = '';

$out[] = '-- Nhật ký thông báo thanh toán mẫu: một callback được gửi lặp';
$out[] = "INSERT INTO payment_events (payment_id, order_id, idempotency_key, result, outcome, detail, created_by) VALUES
(20, 20, 'seed-pay-20', 'thanh_cong', 'accepted', 'Callback đầu tiên (seed)', 1),
(20, 20, 'seed-pay-20', 'thanh_cong', 'duplicate_replay', 'Callback lặp cùng idempotency key (seed)', 1),
(20, 20, 'seed-pay-20-retry', 'thanh_cong', 'ignored_terminal', 'Callback lặp khác key sau khi đã kết thúc (seed)', 1);";
$out[] = '';

$sql = implode("\n", $out) . "\n";
if ($toStdout) {
    echo $sql;
    fwrite(STDERR, 'Đã xuất ' . strlen($sql) . " bytes ra stdout\n");
    exit(0);
}

if (file_put_contents($target, $sql) === false) {
    fwrite(STDERR, "Không ghi được $target\n");
    exit(1);
}
fwrite(STDERR, 'Đã ghi ' . $target . ' (' . strlen($sql) . " bytes)\n");
