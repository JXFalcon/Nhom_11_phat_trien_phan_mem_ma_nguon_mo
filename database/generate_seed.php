<?php
/**
 * OrderFlow – Sinh file database.sql (schema + dữ liệu mẫu).
 * Chạy: php database/generate_seed.php > database.sql  (hoặc script sẽ ghi thẳng)
 * KHÔNG truy cập web – chỉ dùng CLI.
 */
declare(strict_types=1);

$hash123 = password_hash('123456', PASSWORD_BCRYPT);

/* deterministic pseudo-random */
function lcg(int $seed): callable {
    $s = $seed;
    return function () use (&$s): float {
        $s = (int)((($s * 1664525) + 1013904223) % 4294967296);
        return $s / 4294967296;
    };
}
function pick(callable $rng, array $arr) { return $arr[(int)floor($rng() * count($arr))]; }
function intR(callable $rng, int $min, int $max) { return $min + (int)floor($rng() * ($max - $min + 1)); }
function sqlStr(string $s): string { return "'" . addslashes($s) . "'"; }
function sqlDt(string $iso): string { return "'" . date('Y-m-d H:i:s', strtotime($iso)) . "'"; }

$categories = [
    ['DM01', 'Điện thoại', 'Smartphone và điện thoại di động', '📱'],
    ['DM02', 'Laptop', 'Máy tính xách tay các dòng', '💻'],
    ['DM03', 'Máy tính bảng', 'Tablet và phụ kiện đi kèm', '📟'],
    ['DM04', 'Âm thanh', 'Tai nghe, loa bluetooth', '🎧'],
    ['DM05', 'Đồng hồ thông minh', 'Smartwatch và vòng đeo tay', '⌚'],
    ['DM06', 'Phụ kiện', 'Bàn phím, chuột, màn hình', '🖱️'],
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

$carriers = ['Giao Hàng Nhanh (GHN)', 'J&T Express', 'Viettel Post', 'Ninja Van'];

$out = [];
$out[] = '-- ============================================================';
$out[] = '-- OrderFlow – Cơ sở dữ liệu bài tập lớn đề tài 19';
$out[] = '-- Quản lý đơn hàng và đổi trả trong bán hàng trực tuyến';
$out[] = '-- MySQL / MariaDB (XAMPP) · Import bằng phpMyAdmin hoặc mysql CLI';
$out[] = '-- Mật khẩu demo: 123456 (đã băm bcrypt)';
$out[] = '-- ============================================================';
$out[] = 'DROP DATABASE IF EXISTS orderflow;';
$out[] = 'CREATE DATABASE orderflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
$out[] = 'USE orderflow;';
$out[] = '';

/* ===================== SCHEMA ===================== */
$out[] = <<<'SQL'
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer',
  status TINYINT NOT NULL DEFAULT 1 COMMENT '1=active 0=disabled',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(10) NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  emoji VARCHAR(10) DEFAULT NULL,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_code (code),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(30) NOT NULL,
  name VARCHAR(150) NOT NULL,
  category_id INT UNSIGNED DEFAULT NULL,
  price DECIMAL(14,0) NOT NULL DEFAULT 0 COMMENT 'Giá hiện tại (đ có thể đổi)',
  stock INT NOT NULL DEFAULT 0 COMMENT 'Lượng hàng có thể bán',
  variants TEXT DEFAULT NULL COMMENT 'Chuỗi biến thể cách nhau dấu ;',
  emoji VARCHAR(10) DEFAULT NULL,
  status ENUM('active','hidden') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_sku (sku),
  KEY idx_products_category (category_id),
  KEY idx_products_status (status),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE product_variants (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pv_product (product_id),
  CONSTRAINT fk_pv_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_code VARCHAR(20) NOT NULL,
  user_id INT UNSIGNED DEFAULT NULL COMMENT 'Tài khoản khách (có thể null với đơn nhập tay)',
  customer_name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  payment_method ENUM('cod','bank','momo') NOT NULL DEFAULT 'cod',
  total_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
  status ENUM('cho_xac_nhan','da_xac_nhan','dang_giao','da_giao','da_huy') NOT NULL DEFAULT 'cho_xac_nhan',
  stock_restored TINYINT NOT NULL DEFAULT 0 COMMENT '1=đã hoàn kho khi hủy (chỉ 1 lần)',
  cancel_reason VARCHAR(255) DEFAULT NULL,
  hold_expires_at DATETIME NULL COMMENT 'Hạn giữ hàng cho đơn chờ xác nhận; NULL với đơn đã qua bước chờ',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_code (order_code),
  KEY idx_orders_user (user_id),
  KEY idx_orders_status (status),
  KEY idx_orders_date (order_date),
  KEY idx_orders_hold (status, hold_expires_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  sku VARCHAR(30) DEFAULT NULL,
  product_name VARCHAR(150) NOT NULL COMMENT 'Snapshot tên tại thời điểm đặt',
  variant VARCHAR(120) DEFAULT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(14,0) NOT NULL COMMENT 'Snapshot giá tại thời điểm đặt',
  line_total DECIMAL(14,0) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_oi_order (order_id),
  KEY idx_oi_product (product_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_code VARCHAR(20) NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  amount DECIMAL(14,0) NOT NULL,
  method ENUM('cod','bank','momo') NOT NULL DEFAULT 'cod',
  status ENUM('thanh_cong','cho_xu_ly','that_bai') NOT NULL DEFAULT 'cho_xu_ly',
  idempotency_key VARCHAR(64) DEFAULT NULL COMMENT 'Chống xử lý trùng thông báo',
  note VARCHAR(255) DEFAULT NULL,
  processed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_code (payment_code),
  KEY idx_payments_order (order_id),
  KEY idx_payments_status (status),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE shipments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  shipment_code VARCHAR(20) NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  carrier VARCHAR(60) NOT NULL,
  tracking_code VARCHAR(40) NOT NULL,
  ship_date DATETIME DEFAULT NULL,
  status ENUM('cho_lay_hang','dang_van_chuyen','da_giao','that_bai') NOT NULL DEFAULT 'cho_lay_hang',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shipments_code (shipment_code),
  KEY idx_shipments_order (order_id),
  KEY idx_shipments_status (status),
  CONSTRAINT fk_shipments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE return_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  return_code VARCHAR(20) NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED DEFAULT NULL,
  customer_name VARCHAR(100) NOT NULL,
  reason TEXT NOT NULL,
  status ENUM('cho_xu_ly','da_chap_nhan','tu_choi','da_hoan_tat') NOT NULL DEFAULT 'cho_xu_ly',
  processed_by INT UNSIGNED DEFAULT NULL,
  processed_at DATETIME DEFAULT NULL,
  process_note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_return_code (return_code),
  KEY idx_rr_order (order_id),
  KEY idx_rr_user (user_id),
  KEY idx_rr_status (status),
  CONSTRAINT fk_rr_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_rr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_rr_processor FOREIGN KEY (processed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE return_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  return_request_id INT UNSIGNED NOT NULL,
  order_item_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name VARCHAR(150) NOT NULL,
  quantity_requested INT NOT NULL,
  quantity_accepted INT NOT NULL DEFAULT 0,
  unit_price DECIMAL(14,0) NOT NULL COMMENT 'Giá đã chốt trong đơn',
  refund_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
  restock TINYINT NOT NULL DEFAULT 1 COMMENT '1=nhập lại kho khi hoàn tất',
  PRIMARY KEY (id),
  KEY idx_ri_req (return_request_id),
  KEY idx_ri_item (order_item_id),
  CONSTRAINT fk_ri_req FOREIGN KEY (return_request_id) REFERENCES return_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_ri_oi FOREIGN KEY (order_item_id) REFERENCES order_items (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ri_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE inventory_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED DEFAULT NULL,
  change_type ENUM('nhap','xuat','huy_don','tra_hang','dieu_chinh') NOT NULL,
  quantity INT NOT NULL COMMENT 'Số dương = cộng, số âm = trừ',
  note VARCHAR(255) DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_im_product (product_id),
  KEY idx_im_order (order_id),
  CONSTRAINT fk_im_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_im_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_im_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE sessions (
  id CHAR(64) NOT NULL COMMENT 'token ngẫu nhiên (không lưu cookie)',
  user_id INT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_expires (expires_at),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  from_status VARCHAR(20) DEFAULT NULL,
  to_status VARCHAR(20) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  changed_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_oh_order (order_id),
  CONSTRAINT fk_oh_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_oh_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;
SQL;
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
    $role = pick($rng, ['customer','customer','customer','staff','reviewer' === 'x' ? 'customer' : 'customer']);
    // chỉ customer/staff/admin trong enum; reviewer -> customer
    if (!in_array($role, ['admin','staff','customer'], true)) $role = 'customer';
    $users[] = [
        pick($rng,$first).' '.pick($rng,$mid).' '.pick($rng,$last),
        'user'.str_pad((string)$i,2,'0',STR_PAD_LEFT).'@orderflow.vn',
        '09'.intR($rng,10000000,99999999),
        $role,
    ];
}
$out[] = '-- Tài khoản (mật khẩu 123456 – bcrypt)';
foreach ($users as $i => $u) {
    [$fn,$em,$ph,$role] = $u;
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
$out[] = '-- Sản phẩm (stock = lượng có thể bán)';
$productIds = [];
foreach ($products as $i => $p) {
    $pid = $i + 1;
    $productIds[] = $pid;
    $out[] = sprintf(
        "INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (%d, %s, %s, (SELECT id FROM (SELECT id FROM categories WHERE code=%s) t), %d, %d, %s, %s);",
        $pid, sqlStr($p[0]), sqlStr($p[1]), sqlStr($p[2]), $p[3], $p[4], sqlStr($p[5]), sqlStr($p[6])
    );
    // biến thể
    $vs = array_map('trim', explode(';', $p[5]));
    foreach ($vs as $k => $vn) {
        if ($vn === '') continue;
        $out[] = sprintf(
            "INSERT INTO product_variants (product_id, name, stock) VALUES (%d, %s, %d);",
            $pid, sqlStr($vn), (int)ceil($p[4] / max(1, count($vs)))
        );
    }
}
$out[] = '';

/* ===================== ORDERS & RELATED ===================== */
$rng = lcg(20261021);
$now = time();
$customersPool = [];
for ($i = 1; $i <= count($users); $i++) {
    // chỉ customer + staff làm khách hàng đơn
    $r = $users[$i-1][3];
    if ($r === 'customer' || $r === 'staff') $customersPool[] = $i;
}
$statusPool = array_merge(
    array_fill(0, 12, 'cho_xac_nhan'),
    array_fill(0, 10, 'da_xac_nhan'),
    array_fill(0, 18, 'dang_giao'),
    array_fill(0, 45, 'da_giao'),
    array_fill(0, 15, 'da_huy')
);
shuffle($statusPool);

$orderIds = [];
$out[] = '-- Đơn hàng + chi tiết + thanh toán + giao hàng';
for ($i = 0; $i < 100; $i++) {
    $oid = $i + 1;
    $orderIds[] = $oid;
    $code = 'DH-' . (1000 + $oid);
    $status = $statusPool[$i];
    $daysAgo = intR($rng, 0, 59);
    $ts = $now - $daysAgo * 86400 - intR($rng, 0, 20) * 3600;
    $orderDate = date('Y-m-d H:i:s', $ts);
    $custId = pick($rng, $customersPool);
    $custName = $users[$custId-1][0];
    $custPhone = $users[$custId-1][2];
    $address = 'Quận '.intR($rng,1,12).', TP. Hồ Chí Minh';
    $method = $rng() < 0.45 ? 'cod' : ($rng() < 0.6 ? 'bank' : 'momo');

    // 1–3 sản phẩm
    $used = [];
    $items = [];
    $itemCount = intR($rng, 1, 3);
    $total = 0;
    for ($k = 0; $k < $itemCount; $k++) {
        do { $pi = pick($rng, $productIds); } while (isset($used[$pi]));
        $used[$pi] = true;
        $pr = $products[$pi - 1];
        $qty = intR($rng, 1, 3);
        $price = $pr[3];
        $vs = array_map('trim', explode(';', $pr[5]));
        $variant = pick($rng, $vs);
        $items[] = [$pi, $pr[0], $pr[1], $variant, $qty, $price, $qty * $price];
        $total += $qty * $price;
    }
    // Đơn chờ xác nhận trong seed được giữ hàng 15 phút tính từ lúc import; đơn đã qua bước chờ để NULL.
    $holdExpires = $status === 'cho_xac_nhan' ? 'DATE_ADD(NOW(), INTERVAL 15 MINUTE)' : 'NULL';
    $out[] = sprintf(
        "INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (%d, %s, %d, %s, %s, %s, %s, '%s', %d, '%s', %s);",
        $oid, sqlStr($code), $custId, sqlStr($custName), sqlStr($custPhone), sqlStr($address), sqlDt($orderDate), $method, $total, $status, $holdExpires
    );
    foreach ($items as $it) {
        [$pi,$sku,$nm,$var,$qty,$price,$lt] = $it;
        $out[] = sprintf(
            "INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (%d, %d, %s, %s, %s, %d, %d, %d);",
            $oid, $pi, sqlStr($sku), sqlStr($nm), sqlStr($var), $qty, $price, $lt
        );
    }
    // lịch sử trạng thái
    if ($status !== 'cho_xac_nhan') {
        $out[] = sprintf("INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (%d, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');", $oid);
    }
    if (in_array($status, ['dang_giao','da_giao'], true)) {
        $out[] = sprintf("INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (%d, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');", $oid);
    }
    if ($status === 'da_giao') {
        $out[] = sprintf("INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (%d, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');", $oid);
    }
    if ($status === 'da_huy') {
        $out[] = sprintf("INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (%d, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);", $oid);
    }

    // thanh toán
    // Đơn còn chờ xác nhận thì chưa thể thanh toán thành công (còn đang giữ hàng).
    if ($status === 'cho_xac_nhan') $payStatus = 'cho_xu_ly';
    elseif ($status === 'da_huy') $payStatus = ($method === 'cod') ? 'cho_xu_ly' : 'that_bai';
    else $payStatus = 'thanh_cong';
    $payProcessed = in_array($payStatus, ['thanh_cong','that_bai'], true) ? date('Y-m-d H:i:s', $ts + 3600) : 'NULL';
    $out[] = sprintf(
        "INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (%d, %s, %d, %d, '%s', '%s', %s, %s);",
        $oid, sqlStr('GD-'.(5000 + $oid)), $oid, $total, $method, $payStatus,
        sqlStr('Thanh toán mô phỏng (seed)'), $payProcessed === 'NULL' ? 'NULL' : sqlDt($payProcessed)
    );

    // giao hàng
    if (in_array($status, ['da_xac_nhan','dang_giao','da_giao'], true)) {
        if ($status === 'da_xac_nhan') $shipStatus = 'cho_lay_hang';
        elseif ($status === 'dang_giao') $shipStatus = $rng() < 0.08 ? 'that_bai' : 'dang_van_chuyen';
        else $shipStatus = 'da_giao';
        $out[] = sprintf(
            "INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (%d, %s, %d, %s, %s, %s, '%s');",
            $oid, sqlStr('VC-'.(7000 + $oid)), $oid,
            sqlStr(pick($rng, $carriers)),
            sqlStr('OF'.intR($rng,100000000,999999999)),
            sqlDt(date('Y-m-d H:i:s', $ts + 86400)),
            $shipStatus
        );
    }
}
$out[] = '';

/* ===================== RETURNS ===================== */
$out[] = '-- Yêu cầu đổi trả (từ đơn đã giao)';
$deliveredOids = [];
foreach ($statusPool as $i => $st) { if ($st === 'da_giao') $deliveredOids[] = $i + 1; }
$reasons = [
    'Sản phẩm bị lỗi màn hình, có điểm chết',
    'Giao sai màu so với đơn đã đặt',
    'Khách đổi ý trong 15 ngày, hàng còn nguyên seal',
    'Hộp móp, phụ kiện thiếu cáp sạc',
    'Máy bị nóng bất thường khi sạc',
];
$retStatuses = ['cho_xu_ly','da_chap_nhan','tu_choi','da_hoan_tat'];
shuffle($deliveredOids);
$rid = 0;
foreach (array_slice($deliveredOids, 0, 15) as $oid) {
    $rid++;
    $st = $retStatuses[($rid - 1) % 4];
    // lấy order_item đầu của đơn
    $q = "SELECT oi.id, oi.product_id, oi.product_name, oi.quantity, oi.unit_price FROM order_items oi WHERE oi.order_id = $oid LIMIT 1";
    // seed tĩnh: cần id order_item – đơn oid có mặt trong thứ tự inserts; tạm tính offset phức tạp → dùng subquery trong INSERT
    $custNameRow = $users[$customersPool[$rid % count($customersPool)] - 1][0];
    $out[] = sprintf(
        "INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (%d, %s, %d, (SELECT user_id FROM orders WHERE id=%d), (SELECT customer_name FROM orders WHERE id=%d), %s, '%s', %s, %s);",
        $rid, sqlStr('DT-'.(200 + $rid)), $oid, $oid, $oid, sqlStr(pick($rng, $reasons)), $st,
        in_array($st, ['da_chap_nhan','tu_choi','da_hoan_tat'], true) ? '1' : 'NULL',
        in_array($st, ['da_chap_nhan','tu_choi','da_hoan_tat'], true) ? sqlDt(date('Y-m-d H:i:s', $now - intR($rng,1,10)*86400)) : 'NULL'
    );
    $qtyReq = intR($rng, 1, 2);
    $qtyAcc = 0; $refund = 0;
    if ($st === 'da_hoan_tat') { $qtyAcc = $qtyReq; }
    elseif ($st === 'da_chap_nhan') { $qtyAcc = 1; }
    $out[] = sprintf(
        "INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT %d, oi.id, oi.product_id, oi.product_name, %d, %d, oi.unit_price, %d, 1 FROM order_items oi WHERE oi.order_id = %d ORDER BY oi.id LIMIT 1;",
        $rid, $qtyReq, $qtyAcc, $qtyAcc * 0 + ($qtyAcc > 0 ? $qtyAcc : 0), $oid
    );
    // refund_amount thực tế = qty_acc * unit_price → cập nhật sau
    $out[] = sprintf(
        "UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = %d;",
        $rid
    );
}
$out[] = '';

/* ===================== INVENTORY MOVEMENTS (một phần) ===================== */
$out[] = '-- Phong trào tồn kho mẫu (xuất theo đơn + hủy hoàn kho)';
for ($i = 1; $i <= 40; $i++) {
    $oid = $i;
    $st = $statusPool[$oid - 1];
    if ($st === 'da_huy') {
        $out[] = sprintf("INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, %d, 'huy_don', oi.quantity, 'Hoàn kho khi hủy đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = %d;", $oid, $oid);
    } elseif (in_array($st, ['da_xac_nhan','dang_giao','da_giao'], true)) {
        $out[] = sprintf("INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, %d, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = %d;", $oid, $oid);
    }
}
$out[] = '';

$sql = implode("\n", $out) . "\n";
$target = __DIR__ . '/../database.sql';
file_put_contents($target, $sql);
fwrite(STDERR, "Đã ghi " . $target . " (" . strlen($sql) . " bytes)\n");
