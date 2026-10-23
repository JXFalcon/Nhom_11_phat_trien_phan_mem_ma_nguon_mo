-- ============================================================
-- OrderFlow – Cơ sở dữ liệu bài tập lớn đề tài 19
-- Quản lý đơn hàng và đổi trả trong bán hàng trực tuyến
-- MySQL / MariaDB (XAMPP) · Import bằng phpMyAdmin hoặc mysql CLI
-- Mật khẩu demo: 123456 (đã băm bcrypt)
-- ============================================================
DROP DATABASE IF EXISTS orderflow;
CREATE DATABASE orderflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE orderflow;

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

-- Tài khoản (mật khẩu 123456 – bcrypt)
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (1, 'Nguyễn Văn Admin', 'admin@orderflow.vn', '0901000001', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'admin');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (2, 'Trần Thị Hoa', 'nhanvien@orderflow.vn', '0901000002', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'staff');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (3, 'Lê Minh Tuấn', 'khachhang@orderflow.vn', '0901000003', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (4, 'Nguyễn Minh Quân', 'user04@orderflow.vn', '0958054853', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (5, 'Đỗ Anh Mai', 'user05@orderflow.vn', '0935047646', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (6, 'Bùi Thanh Mai', 'user06@orderflow.vn', '0961167108', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (7, 'Phạm Gia Yên', 'user07@orderflow.vn', '0943167347', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'staff');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (8, 'Nguyễn Ngọc Linh', 'user08@orderflow.vn', '0911219547', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (9, 'Phạm Ngọc Uyên', 'user09@orderflow.vn', '0979474561', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (10, 'Nguyễn Gia Quân', 'user10@orderflow.vn', '0968646768', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (11, 'Trần Thị Dũng', 'user11@orderflow.vn', '0918220413', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (12, 'Đỗ Minh Trang', 'user12@orderflow.vn', '0996579788', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (13, 'Trần Gia Uyên', 'user13@orderflow.vn', '0960053075', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (14, 'Vũ Bảo Dũng', 'user14@orderflow.vn', '0986654325', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (15, 'Đặng Quốc Yên', 'user15@orderflow.vn', '0929414106', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'staff');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (16, 'Đỗ Quốc Linh', 'user16@orderflow.vn', '0948940316', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'staff');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (17, 'Hồ Ngọc Hà', 'user17@orderflow.vn', '0970523194', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (18, 'Bùi Anh Phong', 'user18@orderflow.vn', '0924656020', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (19, 'Vũ Văn Nam', 'user19@orderflow.vn', '0930141011', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (20, 'Vũ Văn Mai', 'user20@orderflow.vn', '0945808541', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'staff');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (21, 'Trần Ngọc Trang', 'user21@orderflow.vn', '0994801816', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (22, 'Bùi Ngọc Yên', 'user22@orderflow.vn', '0989584256', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (23, 'Vũ Ngọc Nam', 'user23@orderflow.vn', '0942600055', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (24, 'Hồ Bảo Mai', 'user24@orderflow.vn', '0957822407', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (25, 'Phạm Hữu Quân', 'user25@orderflow.vn', '0943023371', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (26, 'Đặng Bảo Oanh', 'user26@orderflow.vn', '0998867996', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (27, 'Đặng Hữu Sơn', 'user27@orderflow.vn', '0945873291', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (28, 'Vũ Văn Em', 'user28@orderflow.vn', '0971141138', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'staff');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (29, 'Đỗ Thanh Em', 'user29@orderflow.vn', '0960129306', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'staff');
INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES (30, 'Trần Bảo Chi', 'user30@orderflow.vn', '0920504175', '$2y$10$.WR64q20hGwJdjnzTu.Fa.txkBOUx3PqCu9kUwCb2WilWqhKCQayW', 'customer');

-- Danh mục
INSERT INTO categories (id, code, name, description, emoji) VALUES (1, 'DM01', 'Điện thoại', 'Smartphone và điện thoại di động', '📱');
INSERT INTO categories (id, code, name, description, emoji) VALUES (2, 'DM02', 'Laptop', 'Máy tính xách tay các dòng', '💻');
INSERT INTO categories (id, code, name, description, emoji) VALUES (3, 'DM03', 'Máy tính bảng', 'Tablet và phụ kiện đi kèm', '📟');
INSERT INTO categories (id, code, name, description, emoji) VALUES (4, 'DM04', 'Âm thanh', 'Tai nghe, loa bluetooth', '🎧');
INSERT INTO categories (id, code, name, description, emoji) VALUES (5, 'DM05', 'Đồng hồ thông minh', 'Smartwatch và vòng đeo tay', '⌚');
INSERT INTO categories (id, code, name, description, emoji) VALUES (6, 'DM06', 'Phụ kiện', 'Bàn phím, chuột, màn hình', '🖱️');
INSERT INTO categories (id, code, name, description, emoji) VALUES (7, 'DM07', 'Gia dụng', 'Đồ gia dụng nhà bếp', '🏠');
INSERT INTO categories (id, code, name, description, emoji) VALUES (8, 'DM08', 'Thời trang', 'Áo khoác, giày thể thao', '👟');

-- Sản phẩm (stock = lượng có thể bán)
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (1, 'SP-1001', 'iPhone 15 Pro Max 256GB', (SELECT id FROM (SELECT id FROM categories WHERE code='DM01') t), 34990000, 42, '256GB / Titan Tự nhiên; 512GB / Xanh; 1TB / Đen', '📱');
INSERT INTO product_variants (product_id, name, stock) VALUES (1, '256GB / Titan Tự nhiên', 14);
INSERT INTO product_variants (product_id, name, stock) VALUES (1, '512GB / Xanh', 14);
INSERT INTO product_variants (product_id, name, stock) VALUES (1, '1TB / Đen', 14);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (2, 'SP-1002', 'Samsung Galaxy S24 Ultra', (SELECT id FROM (SELECT id FROM categories WHERE code='DM01') t), 27990000, 28, '256GB / Tím; 512GB / Xám', '📱');
INSERT INTO product_variants (product_id, name, stock) VALUES (2, '256GB / Tím', 14);
INSERT INTO product_variants (product_id, name, stock) VALUES (2, '512GB / Xám', 14);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (3, 'SP-1003', 'Xiaomi 14T Pro 5G', (SELECT id FROM (SELECT id FROM categories WHERE code='DM01') t), 12990000, 55, '256GB / Đen; 512GB / Xanh', '📱');
INSERT INTO product_variants (product_id, name, stock) VALUES (3, '256GB / Đen', 28);
INSERT INTO product_variants (product_id, name, stock) VALUES (3, '512GB / Xanh', 28);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (4, 'SP-1004', 'MacBook Air M3 13-inch', (SELECT id FROM (SELECT id FROM categories WHERE code='DM02') t), 28990000, 18, '8GB/256GB; 16GB/512GB', '💻');
INSERT INTO product_variants (product_id, name, stock) VALUES (4, '8GB/256GB', 9);
INSERT INTO product_variants (product_id, name, stock) VALUES (4, '16GB/512GB', 9);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (5, 'SP-1005', 'Dell XPS 13 Plus', (SELECT id FROM (SELECT id FROM categories WHERE code='DM02') t), 31500000, 12, '16GB/512GB / Bạch kim', '💻');
INSERT INTO product_variants (product_id, name, stock) VALUES (5, '16GB/512GB / Bạch kim', 12);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (6, 'SP-1006', 'ASUS ROG Strix G16', (SELECT id FROM (SELECT id FROM categories WHERE code='DM02') t), 39990000, 9, '16GB/1TB / Đen; 32GB/1TB', '💻');
INSERT INTO product_variants (product_id, name, stock) VALUES (6, '16GB/1TB / Đen', 5);
INSERT INTO product_variants (product_id, name, stock) VALUES (6, '32GB/1TB', 5);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (7, 'SP-1007', 'iPad Air M2 11-inch', (SELECT id FROM (SELECT id FROM categories WHERE code='DM03') t), 15990000, 24, '64GB WiFi; 256GB WiFi', '📟');
INSERT INTO product_variants (product_id, name, stock) VALUES (7, '64GB WiFi', 12);
INSERT INTO product_variants (product_id, name, stock) VALUES (7, '256GB WiFi', 12);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (8, 'SP-1008', 'Samsung Galaxy Tab S9 FE', (SELECT id FROM (SELECT id FROM categories WHERE code='DM03') t), 10490000, 16, '128GB WiFi / Xám', '📟');
INSERT INTO product_variants (product_id, name, stock) VALUES (8, '128GB WiFi / Xám', 16);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (9, 'SP-1009', 'AirPods Pro 2 (USB-C)', (SELECT id FROM (SELECT id FROM categories WHERE code='DM04') t), 5990000, 60, 'Tiêu chuẩn', '🎧');
INSERT INTO product_variants (product_id, name, stock) VALUES (9, 'Tiêu chuẩn', 60);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (10, 'SP-1010', 'Sony WH-1000XM5', (SELECT id FROM (SELECT id FROM categories WHERE code='DM04') t), 7490000, 33, 'Đen; Bạc; Xanh dương', '🎧');
INSERT INTO product_variants (product_id, name, stock) VALUES (10, 'Đen', 11);
INSERT INTO product_variants (product_id, name, stock) VALUES (10, 'Bạc', 11);
INSERT INTO product_variants (product_id, name, stock) VALUES (10, 'Xanh dương', 11);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (11, 'SP-1011', 'Loa JBL Charge 5', (SELECT id FROM (SELECT id FROM categories WHERE code='DM04') t), 3290000, 40, 'Đen; Xanh; Đỏ', '🔊');
INSERT INTO product_variants (product_id, name, stock) VALUES (11, 'Đen', 14);
INSERT INTO product_variants (product_id, name, stock) VALUES (11, 'Xanh', 14);
INSERT INTO product_variants (product_id, name, stock) VALUES (11, 'Đỏ', 14);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (12, 'SP-1012', 'Apple Watch Series 9', (SELECT id FROM (SELECT id FROM categories WHERE code='DM05') t), 10990000, 21, '41mm GPS; 45mm GPS; 45mm Cellular', '⌚');
INSERT INTO product_variants (product_id, name, stock) VALUES (12, '41mm GPS', 7);
INSERT INTO product_variants (product_id, name, stock) VALUES (12, '45mm GPS', 7);
INSERT INTO product_variants (product_id, name, stock) VALUES (12, '45mm Cellular', 7);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (13, 'SP-1013', 'Garmin Fenix 7 Solar', (SELECT id FROM (SELECT id FROM categories WHERE code='DM05') t), 18500000, 7, '47mm / Đen', '⌚');
INSERT INTO product_variants (product_id, name, stock) VALUES (13, '47mm / Đen', 7);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (14, 'SP-1014', 'Chuột Logitech MX Master 3S', (SELECT id FROM (SELECT id FROM categories WHERE code='DM06') t), 2490000, 50, 'Graphite; Pale Grey', '🖱️');
INSERT INTO product_variants (product_id, name, stock) VALUES (14, 'Graphite', 25);
INSERT INTO product_variants (product_id, name, stock) VALUES (14, 'Pale Grey', 25);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (15, 'SP-1015', 'Bàn phím Keychron K2 Pro', (SELECT id FROM (SELECT id FROM categories WHERE code='DM06') t), 2190000, 35, 'RGB / Brown Switch', '⌨️');
INSERT INTO product_variants (product_id, name, stock) VALUES (15, 'RGB / Brown Switch', 35);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (16, 'SP-1016', 'Màn hình LG UltraGear 27GQ750', (SELECT id FROM (SELECT id FROM categories WHERE code='DM06') t), 7990000, 14, '27\" 4K 144Hz', '🖥️');
INSERT INTO product_variants (product_id, name, stock) VALUES (16, '27\" 4K 144Hz', 14);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (17, 'SP-1017', 'Nồi cơm điện Panasonic SR-DF183', (SELECT id FROM (SELECT id FROM categories WHERE code='DM07') t), 1290000, 45, '1.8L', '🍚');
INSERT INTO product_variants (product_id, name, stock) VALUES (17, '1.8L', 45);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', (SELECT id FROM (SELECT id FROM categories WHERE code='DM07') t), 2590000, 22, '23L', '🔥');
INSERT INTO product_variants (product_id, name, stock) VALUES (18, '23L', 22);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (19, 'SP-1019', 'Áo khoác Unifree chống nước', (SELECT id FROM (SELECT id FROM categories WHERE code='DM08') t), 890000, 80, 'S / Đen; M / Đen; L / Xám', '🧥');
INSERT INTO product_variants (product_id, name, stock) VALUES (19, 'S / Đen', 27);
INSERT INTO product_variants (product_id, name, stock) VALUES (19, 'M / Đen', 27);
INSERT INTO product_variants (product_id, name, stock) VALUES (19, 'L / Xám', 27);
INSERT INTO products (id, sku, name, category_id, price, stock, variants, emoji) VALUES (20, 'SP-1020', 'Giày Nike Air Force 1 \'07', (SELECT id FROM (SELECT id FROM categories WHERE code='DM08') t), 2790000, 3, '40 / Trắng; 41 / Trắng; 42 / Trắng', '👟');
INSERT INTO product_variants (product_id, name, stock) VALUES (20, '40 / Trắng', 1);
INSERT INTO product_variants (product_id, name, stock) VALUES (20, '41 / Trắng', 1);
INSERT INTO product_variants (product_id, name, stock) VALUES (20, '42 / Trắng', 1);

-- Đơn hàng + chi tiết + thanh toán + giao hàng
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (1, 'DH-1001', 16, 'Đỗ Quốc Linh', '0948940316', 'Quận 1, TP. Hồ Chí Minh', '2026-09-25 00:52:41', 'cod', 2190000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (1, 15, 'SP-1015', 'Bàn phím Keychron K2 Pro', 'RGB / Brown Switch', 1, 2190000, 2190000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (1, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (1, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (1, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (1, 'GD-5001', 1, 2190000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-25 01:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (1, 'VC-7001', 1, 'Viettel Post', 'OF717481701', '2026-09-26 00:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (2, 'DH-1002', 23, 'Vũ Ngọc Nam', '0942600055', 'Quận 8, TP. Hồ Chí Minh', '2026-09-21 14:52:41', 'bank', 20980000, 'da_xac_nhan');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (2, 8, 'SP-1008', 'Samsung Galaxy Tab S9 FE', '128GB WiFi / Xám', 2, 10490000, 20980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (2, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (2, 'GD-5002', 2, 20980000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-21 15:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (2, 'VC-7002', 2, 'J&T Express', 'OF143666519', '2026-09-22 14:52:41', 'cho_lay_hang');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (3, 'DH-1003', 30, 'Trần Bảo Chi', '0920504175', 'Quận 10, TP. Hồ Chí Minh', '2026-10-05 13:52:41', 'momo', 28560000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (3, 11, 'SP-1011', 'Loa JBL Charge 5', 'Xanh', 2, 3290000, 6580000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (3, 12, 'SP-1012', 'Apple Watch Series 9', '45mm GPS', 2, 10990000, 21980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (3, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (3, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (3, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (3, 'GD-5003', 3, 28560000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-05 14:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (3, 'VC-7003', 3, 'Giao Hàng Nhanh (GHN)', 'OF118205767', '2026-10-06 13:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (4, 'DH-1004', 14, 'Vũ Bảo Dũng', '0986654325', 'Quận 12, TP. Hồ Chí Minh', '2026-10-03 08:52:41', 'bank', 10490000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (4, 8, 'SP-1008', 'Samsung Galaxy Tab S9 FE', '128GB WiFi / Xám', 1, 10490000, 10490000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (4, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (4, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (4, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (4, 'GD-5004', 4, 10490000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-03 09:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (4, 'VC-7004', 4, 'Giao Hàng Nhanh (GHN)', 'OF185837717', '2026-10-04 08:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (5, 'DH-1005', 11, 'Trần Thị Dũng', '0918220413', 'Quận 10, TP. Hồ Chí Minh', '2026-09-08 07:52:41', 'bank', 47970000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (5, 7, 'SP-1007', 'iPad Air M2 11-inch', '256GB WiFi', 3, 15990000, 47970000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (5, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (5, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (5, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (5, 'GD-5005', 5, 47970000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-08 08:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (5, 'VC-7005', 5, 'Viettel Post', 'OF211396228', '2026-09-09 07:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (6, 'DH-1006', 21, 'Trần Ngọc Trang', '0994801816', 'Quận 8, TP. Hồ Chí Minh', '2026-09-23 08:52:41', 'cod', 117980000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (6, 7, 'SP-1007', 'iPad Air M2 11-inch', '64GB WiFi', 1, 15990000, 15990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (6, 5, 'SP-1005', 'Dell XPS 13 Plus', '16GB/512GB / Bạch kim', 3, 31500000, 94500000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (6, 10, 'SP-1010', 'Sony WH-1000XM5', 'Bạc', 1, 7490000, 7490000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (6, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (6, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (6, 'GD-5006', 6, 117980000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-23 09:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (6, 'VC-7006', 6, 'Giao Hàng Nhanh (GHN)', 'OF315649520', '2026-09-24 08:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (7, 'DH-1007', 5, 'Đỗ Anh Mai', '0935047646', 'Quận 3, TP. Hồ Chí Minh', '2026-10-18 16:52:41', 'momo', 14980000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (7, 10, 'SP-1010', 'Sony WH-1000XM5', 'Đen', 2, 7490000, 14980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (7, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (7, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (7, 'GD-5007', 7, 14980000, 'momo', 'that_bai', 'Thanh toán mô phỏng (seed)', '2026-10-18 17:52:41');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (8, 'DH-1008', 29, 'Đỗ Thanh Em', '0960129306', 'Quận 2, TP. Hồ Chí Minh', '2026-10-10 09:52:41', 'cod', 40230000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (8, 19, 'SP-1019', 'Áo khoác Unifree chống nước', 'S / Đen', 3, 890000, 2670000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (8, 20, 'SP-1020', 'Giày Nike Air Force 1 \'07', '41 / Trắng', 2, 2790000, 5580000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (8, 7, 'SP-1007', 'iPad Air M2 11-inch', '64GB WiFi', 2, 15990000, 31980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (8, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (8, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (8, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (8, 'GD-5008', 8, 40230000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-10 10:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (8, 'VC-7008', 8, 'Ninja Van', 'OF996458951', '2026-10-11 09:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (9, 'DH-1009', 11, 'Trần Thị Dũng', '0918220413', 'Quận 1, TP. Hồ Chí Minh', '2026-10-11 09:52:41', 'cod', 5580000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (9, 20, 'SP-1020', 'Giày Nike Air Force 1 \'07', '41 / Trắng', 2, 2790000, 5580000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (9, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (9, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (9, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (9, 'GD-5009', 9, 5580000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-11 10:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (9, 'VC-7009', 9, 'Ninja Van', 'OF884514777', '2026-10-12 09:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (10, 'DH-1010', 25, 'Phạm Hữu Quân', '0943023371', 'Quận 1, TP. Hồ Chí Minh', '2026-09-19 07:52:41', 'momo', 3290000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (10, 11, 'SP-1011', 'Loa JBL Charge 5', 'Đen', 1, 3290000, 3290000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (10, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (10, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (10, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (10, 'GD-5010', 10, 3290000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-19 08:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (10, 'VC-7010', 10, 'Ninja Van', 'OF193768596', '2026-09-20 07:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (11, 'DH-1011', 3, 'Lê Minh Tuấn', '0901000003', 'Quận 7, TP. Hồ Chí Minh', '2026-09-11 13:52:41', 'momo', 7490000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (11, 10, 'SP-1010', 'Sony WH-1000XM5', 'Xanh dương', 1, 7490000, 7490000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (11, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (11, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (11, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (11, 'GD-5011', 11, 7490000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-11 14:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (11, 'VC-7011', 11, 'Giao Hàng Nhanh (GHN)', 'OF202399954', '2026-09-12 13:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (12, 'DH-1012', 25, 'Phạm Hữu Quân', '0943023371', 'Quận 2, TP. Hồ Chí Minh', '2026-10-15 02:52:41', 'bank', 13250000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (12, 15, 'SP-1015', 'Bàn phím Keychron K2 Pro', 'RGB / Brown Switch', 2, 2190000, 4380000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (12, 20, 'SP-1020', 'Giày Nike Air Force 1 \'07', '40 / Trắng', 2, 2790000, 5580000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (12, 11, 'SP-1011', 'Loa JBL Charge 5', 'Xanh', 1, 3290000, 3290000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (12, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (12, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (12, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (12, 'GD-5012', 12, 13250000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-15 03:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (12, 'VC-7012', 12, 'Viettel Post', 'OF249889148', '2026-10-16 02:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (13, 'DH-1013', 4, 'Nguyễn Minh Quân', '0958054853', 'Quận 8, TP. Hồ Chí Minh', '2026-10-17 14:52:41', 'cod', 14980000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (13, 10, 'SP-1010', 'Sony WH-1000XM5', 'Đen', 2, 7490000, 14980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (13, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (13, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (13, 'GD-5013', 13, 14980000, 'cod', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (14, 'DH-1014', 22, 'Bùi Ngọc Yên', '0989584256', 'Quận 12, TP. Hồ Chí Minh', '2026-09-20 16:52:41', 'bank', 67940000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (14, 16, 'SP-1016', 'Màn hình LG UltraGear 27GQ750', '27\" 4K 144Hz', 1, 7990000, 7990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (14, 9, 'SP-1009', 'AirPods Pro 2 (USB-C)', 'Tiêu chuẩn', 2, 5990000, 11980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (14, 7, 'SP-1007', 'iPad Air M2 11-inch', '64GB WiFi', 3, 15990000, 47970000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (14, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (14, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (14, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (14, 'GD-5014', 14, 67940000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-20 17:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (14, 'VC-7014', 14, 'Viettel Post', 'OF796110172', '2026-09-21 16:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (15, 'DH-1015', 20, 'Vũ Văn Mai', '0945808541', 'Quận 10, TP. Hồ Chí Minh', '2026-09-11 18:52:41', 'cod', 43950000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (15, 17, 'SP-1017', 'Nồi cơm điện Panasonic SR-DF183', '1.8L', 1, 1290000, 1290000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (15, 6, 'SP-1006', 'ASUS ROG Strix G16', '16GB/1TB / Đen', 1, 39990000, 39990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (15, 19, 'SP-1019', 'Áo khoác Unifree chống nước', 'L / Xám', 3, 890000, 2670000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (15, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (15, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (15, 'GD-5015', 15, 43950000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-11 19:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (15, 'VC-7015', 15, 'Ninja Van', 'OF793926039', '2026-09-12 18:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (16, 'DH-1016', 14, 'Vũ Bảo Dũng', '0986654325', 'Quận 1, TP. Hồ Chí Minh', '2026-10-10 21:52:41', 'cod', 23970000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (16, 16, 'SP-1016', 'Màn hình LG UltraGear 27GQ750', '27\" 4K 144Hz', 3, 7990000, 23970000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (16, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (16, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (16, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (16, 'GD-5016', 16, 23970000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-10 22:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (16, 'VC-7016', 16, 'Viettel Post', 'OF833377324', '2026-10-11 21:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (17, 'DH-1017', 23, 'Vũ Ngọc Nam', '0942600055', 'Quận 3, TP. Hồ Chí Minh', '2026-10-07 23:52:41', 'cod', 39480000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (17, 13, 'SP-1013', 'Garmin Fenix 7 Solar', '47mm / Đen', 1, 18500000, 18500000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (17, 8, 'SP-1008', 'Samsung Galaxy Tab S9 FE', '128GB WiFi / Xám', 2, 10490000, 20980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (17, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (17, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (17, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (17, 'GD-5017', 17, 39480000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-08 00:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (17, 'VC-7017', 17, 'Viettel Post', 'OF309407184', '2026-10-08 23:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (18, 'DH-1018', 6, 'Bùi Thanh Mai', '0961167108', 'Quận 1, TP. Hồ Chí Minh', '2026-10-20 08:52:41', 'cod', 130960000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (18, 12, 'SP-1012', 'Apple Watch Series 9', '45mm GPS', 1, 10990000, 10990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (18, 6, 'SP-1006', 'ASUS ROG Strix G16', '32GB/1TB', 3, 39990000, 119970000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (18, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (18, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (18, 'GD-5018', 18, 130960000, 'cod', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (19, 'DH-1019', 4, 'Nguyễn Minh Quân', '0958054853', 'Quận 2, TP. Hồ Chí Minh', '2026-10-15 00:52:41', 'cod', 96070000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (19, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '256GB / Titan Tự nhiên', 1, 34990000, 34990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (19, 13, 'SP-1013', 'Garmin Fenix 7 Solar', '47mm / Đen', 3, 18500000, 55500000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (19, 20, 'SP-1020', 'Giày Nike Air Force 1 \'07', '42 / Trắng', 2, 2790000, 5580000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (19, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (19, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (19, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (19, 'GD-5019', 19, 96070000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-15 01:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (19, 'VC-7019', 19, 'Giao Hàng Nhanh (GHN)', 'OF426871225', '2026-10-16 00:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (20, 'DH-1020', 29, 'Đỗ Thanh Em', '0960129306', 'Quận 12, TP. Hồ Chí Minh', '2026-09-21 23:52:41', 'bank', 34080000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (20, 5, 'SP-1005', 'Dell XPS 13 Plus', '16GB/512GB / Bạch kim', 1, 31500000, 31500000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (20, 17, 'SP-1017', 'Nồi cơm điện Panasonic SR-DF183', '1.8L', 2, 1290000, 2580000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (20, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (20, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (20, 'GD-5020', 20, 34080000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-22 00:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (20, 'VC-7020', 20, 'Giao Hàng Nhanh (GHN)', 'OF462496906', '2026-09-22 23:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (21, 'DH-1021', 22, 'Bùi Ngọc Yên', '0989584256', 'Quận 12, TP. Hồ Chí Minh', '2026-10-10 01:52:41', 'bank', 42660000, 'cho_xac_nhan', DATE_ADD(NOW(), INTERVAL 15 MINUTE));
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (21, 15, 'SP-1015', 'Bàn phím Keychron K2 Pro', 'RGB / Brown Switch', 2, 2190000, 4380000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (21, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '256GB / Titan Tự nhiên', 1, 34990000, 34990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (21, 11, 'SP-1011', 'Loa JBL Charge 5', 'Xanh', 1, 3290000, 3290000);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (21, 'GD-5021', 21, 42660000, 'bank', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (22, 'DH-1022', 8, 'Nguyễn Ngọc Linh', '0911219547', 'Quận 6, TP. Hồ Chí Minh', '2026-09-04 19:52:41', 'cod', 60460000, 'cho_xac_nhan', DATE_ADD(NOW(), INTERVAL 15 MINUTE));
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (22, 4, 'SP-1004', 'MacBook Air M3 13-inch', '8GB/256GB', 1, 28990000, 28990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (22, 8, 'SP-1008', 'Samsung Galaxy Tab S9 FE', '128GB WiFi / Xám', 3, 10490000, 31470000);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (22, 'GD-5022', 22, 60460000, 'cod', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (23, 'DH-1023', 7, 'Phạm Gia Yên', '0943167347', 'Quận 11, TP. Hồ Chí Minh', '2026-08-29 18:52:41', 'momo', 45150000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (23, 18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', '23L', 2, 2590000, 5180000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (23, 9, 'SP-1009', 'AirPods Pro 2 (USB-C)', 'Tiêu chuẩn', 2, 5990000, 11980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (23, 2, 'SP-1002', 'Samsung Galaxy S24 Ultra', '512GB / Xám', 1, 27990000, 27990000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (23, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (23, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (23, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (23, 'GD-5023', 23, 45150000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-08-29 19:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (23, 'VC-7023', 23, 'Ninja Van', 'OF614606290', '2026-08-30 18:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (24, 'DH-1024', 20, 'Vũ Văn Mai', '0945808541', 'Quận 5, TP. Hồ Chí Minh', '2026-10-04 23:52:41', 'momo', 182930000, 'cho_xac_nhan', DATE_ADD(NOW(), INTERVAL 15 MINUTE));
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (24, 6, 'SP-1006', 'ASUS ROG Strix G16', '16GB/1TB / Đen', 3, 39990000, 119970000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (24, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Pale Grey', 2, 2490000, 4980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (24, 4, 'SP-1004', 'MacBook Air M3 13-inch', '8GB/256GB', 2, 28990000, 57980000);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (24, 'GD-5024', 24, 182930000, 'momo', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (25, 'DH-1025', 5, 'Đỗ Anh Mai', '0935047646', 'Quận 8, TP. Hồ Chí Minh', '2026-08-27 12:52:41', 'bank', 25970000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (25, 13, 'SP-1013', 'Garmin Fenix 7 Solar', '47mm / Đen', 1, 18500000, 18500000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (25, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Graphite', 3, 2490000, 7470000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (25, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (25, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (25, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (25, 'GD-5025', 25, 25970000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-08-27 13:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (25, 'VC-7025', 25, 'Ninja Van', 'OF451829334', '2026-08-28 12:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (26, 'DH-1026', 29, 'Đỗ Thanh Em', '0960129306', 'Quận 6, TP. Hồ Chí Minh', '2026-09-01 23:52:41', 'bank', 7770000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (26, 18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', '23L', 3, 2590000, 7770000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (26, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (26, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (26, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (26, 'GD-5026', 26, 7770000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-02 00:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (26, 'VC-7026', 26, 'J&T Express', 'OF197007474', '2026-09-02 23:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (27, 'DH-1027', 29, 'Đỗ Thanh Em', '0960129306', 'Quận 1, TP. Hồ Chí Minh', '2026-08-30 09:52:41', 'cod', 6580000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (27, 11, 'SP-1011', 'Loa JBL Charge 5', 'Xanh', 2, 3290000, 6580000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (27, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (27, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (27, 'GD-5027', 27, 6580000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-08-30 10:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (27, 'VC-7027', 27, 'Ninja Van', 'OF914970320', '2026-08-31 09:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (28, 'DH-1028', 10, 'Nguyễn Gia Quân', '0968646768', 'Quận 7, TP. Hồ Chí Minh', '2026-10-08 10:52:41', 'momo', 25710000, 'da_xac_nhan');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (28, 20, 'SP-1020', 'Giày Nike Air Force 1 \'07', '40 / Trắng', 3, 2790000, 8370000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (28, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Graphite', 3, 2490000, 7470000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (28, 11, 'SP-1011', 'Loa JBL Charge 5', 'Xanh', 3, 3290000, 9870000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (28, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (28, 'GD-5028', 28, 25710000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-08 11:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (28, 'VC-7028', 28, 'Ninja Van', 'OF850385024', '2026-10-09 10:52:41', 'cho_lay_hang');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (29, 'DH-1029', 17, 'Hồ Ngọc Hà', '0970523194', 'Quận 12, TP. Hồ Chí Minh', '2026-09-21 10:52:41', 'momo', 97460000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (29, 8, 'SP-1008', 'Samsung Galaxy Tab S9 FE', '128GB WiFi / Xám', 1, 10490000, 10490000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (29, 4, 'SP-1004', 'MacBook Air M3 13-inch', '8GB/256GB', 3, 28990000, 86970000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (29, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (29, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (29, 'GD-5029', 29, 97460000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-21 11:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (29, 'VC-7029', 29, 'J&T Express', 'OF538389683', '2026-09-22 10:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (30, 'DH-1030', 7, 'Phạm Gia Yên', '0943167347', 'Quận 8, TP. Hồ Chí Minh', '2026-09-04 14:52:41', 'bank', 68990000, 'da_xac_nhan');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (30, 9, 'SP-1009', 'AirPods Pro 2 (USB-C)', 'Tiêu chuẩn', 1, 5990000, 5990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (30, 5, 'SP-1005', 'Dell XPS 13 Plus', '16GB/512GB / Bạch kim', 2, 31500000, 63000000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (30, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (30, 'GD-5030', 30, 68990000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-04 15:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (30, 'VC-7030', 30, 'Viettel Post', 'OF324637447', '2026-09-05 14:52:41', 'cho_lay_hang');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (31, 'DH-1031', 13, 'Trần Gia Uyên', '0960053075', 'Quận 6, TP. Hồ Chí Minh', '2026-08-24 09:52:41', 'bank', 81760000, 'cho_xac_nhan', DATE_ADD(NOW(), INTERVAL 15 MINUTE));
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (31, 6, 'SP-1006', 'ASUS ROG Strix G16', '16GB/1TB / Đen', 2, 39990000, 79980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (31, 19, 'SP-1019', 'Áo khoác Unifree chống nước', 'L / Xám', 2, 890000, 1780000);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (31, 'GD-5031', 31, 81760000, 'bank', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (32, 'DH-1032', 19, 'Vũ Văn Nam', '0930141011', 'Quận 6, TP. Hồ Chí Minh', '2026-08-24 00:52:41', 'cod', 7770000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (32, 18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', '23L', 3, 2590000, 7770000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (32, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (32, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (32, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (32, 'GD-5032', 32, 7770000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-08-24 01:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (32, 'VC-7032', 32, 'Giao Hàng Nhanh (GHN)', 'OF639454610', '2026-08-25 00:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (33, 'DH-1033', 22, 'Bùi Ngọc Yên', '0989584256', 'Quận 9, TP. Hồ Chí Minh', '2026-09-17 18:52:41', 'cod', 17950000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (33, 16, 'SP-1016', 'Màn hình LG UltraGear 27GQ750', '27\" 4K 144Hz', 1, 7990000, 7990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (33, 18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', '23L', 3, 2590000, 7770000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (33, 15, 'SP-1015', 'Bàn phím Keychron K2 Pro', 'RGB / Brown Switch', 1, 2190000, 2190000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (33, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (33, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (33, 'GD-5033', 33, 17950000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-17 19:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (33, 'VC-7033', 33, 'Ninja Van', 'OF132833117', '2026-09-18 18:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (34, 'DH-1034', 17, 'Hồ Ngọc Hà', '0970523194', 'Quận 6, TP. Hồ Chí Minh', '2026-10-11 03:52:41', 'momo', 70470000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (34, 5, 'SP-1005', 'Dell XPS 13 Plus', '16GB/512GB / Bạch kim', 2, 31500000, 63000000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (34, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Graphite', 3, 2490000, 7470000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (34, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (34, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (34, 'GD-5034', 34, 70470000, 'momo', 'that_bai', 'Thanh toán mô phỏng (seed)', '2026-10-11 04:52:41');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (35, 'DH-1035', 18, 'Bùi Anh Phong', '0924656020', 'Quận 2, TP. Hồ Chí Minh', '2026-09-30 23:52:41', 'cod', 5580000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (35, 20, 'SP-1020', 'Giày Nike Air Force 1 \'07', '41 / Trắng', 2, 2790000, 5580000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (35, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (35, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (35, 'GD-5035', 35, 5580000, 'cod', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (36, 'DH-1036', 3, 'Lê Minh Tuấn', '0901000003', 'Quận 3, TP. Hồ Chí Minh', '2026-10-17 18:52:41', 'momo', 41560000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (36, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '256GB / Titan Tự nhiên', 1, 34990000, 34990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (36, 15, 'SP-1015', 'Bàn phím Keychron K2 Pro', 'RGB / Brown Switch', 3, 2190000, 6570000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (36, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (36, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (36, 'GD-5036', 36, 41560000, 'momo', 'that_bai', 'Thanh toán mô phỏng (seed)', '2026-10-17 19:52:41');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (37, 'DH-1037', 6, 'Bùi Thanh Mai', '0961167108', 'Quận 3, TP. Hồ Chí Minh', '2026-09-11 08:52:41', 'bank', 42760000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (37, 18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', '23L', 3, 2590000, 7770000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (37, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '512GB / Xanh', 1, 34990000, 34990000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (37, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (37, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (37, 'GD-5037', 37, 42760000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-11 09:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (37, 'VC-7037', 37, 'Viettel Post', 'OF825346638', '2026-09-12 08:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (38, 'DH-1038', 18, 'Bùi Anh Phong', '0924656020', 'Quận 10, TP. Hồ Chí Minh', '2026-09-09 10:52:41', 'bank', 1780000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (38, 19, 'SP-1019', 'Áo khoác Unifree chống nước', 'M / Đen', 2, 890000, 1780000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (38, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (38, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (38, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (38, 'GD-5038', 38, 1780000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-09 11:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (38, 'VC-7038', 38, 'J&T Express', 'OF322208090', '2026-09-10 10:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (39, 'DH-1039', 8, 'Nguyễn Ngọc Linh', '0911219547', 'Quận 1, TP. Hồ Chí Minh', '2026-08-27 01:52:41', 'bank', 144930000, 'cho_xac_nhan', DATE_ADD(NOW(), INTERVAL 15 MINUTE));
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (39, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Graphite', 1, 2490000, 2490000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (39, 6, 'SP-1006', 'ASUS ROG Strix G16', '16GB/1TB / Đen', 3, 39990000, 119970000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (39, 10, 'SP-1010', 'Sony WH-1000XM5', 'Xanh dương', 3, 7490000, 22470000);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (39, 'GD-5039', 39, 144930000, 'bank', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (40, 'DH-1040', 27, 'Đặng Hữu Sơn', '0945873291', 'Quận 2, TP. Hồ Chí Minh', '2026-10-17 00:52:41', 'bank', 7560000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (40, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Pale Grey', 2, 2490000, 4980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (40, 17, 'SP-1017', 'Nồi cơm điện Panasonic SR-DF183', '1.8L', 2, 1290000, 2580000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (40, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (40, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (40, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (40, 'GD-5040', 40, 7560000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-17 01:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (40, 'VC-7040', 40, 'Viettel Post', 'OF799018707', '2026-10-18 00:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (41, 'DH-1041', 15, 'Đặng Quốc Yên', '0929414106', 'Quận 8, TP. Hồ Chí Minh', '2026-08-26 21:52:41', 'bank', 55980000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (41, 2, 'SP-1002', 'Samsung Galaxy S24 Ultra', '512GB / Xám', 2, 27990000, 55980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (41, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (41, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (41, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (41, 'GD-5041', 41, 55980000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-08-26 22:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (41, 'VC-7041', 41, 'J&T Express', 'OF345909594', '2026-08-27 21:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (42, 'DH-1042', 25, 'Phạm Hữu Quân', '0943023371', 'Quận 5, TP. Hồ Chí Minh', '2026-10-09 12:52:41', 'bank', 32570000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (42, 11, 'SP-1011', 'Loa JBL Charge 5', 'Xanh', 1, 3290000, 3290000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (42, 17, 'SP-1017', 'Nồi cơm điện Panasonic SR-DF183', '1.8L', 1, 1290000, 1290000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (42, 2, 'SP-1002', 'Samsung Galaxy S24 Ultra', '256GB / Tím', 1, 27990000, 27990000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (42, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (42, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (42, 'GD-5042', 42, 32570000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-09 13:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (42, 'VC-7042', 42, 'Viettel Post', 'OF419867190', '2026-10-10 12:52:41', 'that_bai');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (43, 'DH-1043', 18, 'Bùi Anh Phong', '0924656020', 'Quận 4, TP. Hồ Chí Minh', '2026-09-07 08:52:41', 'momo', 17970000, 'cho_xac_nhan', DATE_ADD(NOW(), INTERVAL 15 MINUTE));
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (43, 9, 'SP-1009', 'AirPods Pro 2 (USB-C)', 'Tiêu chuẩn', 3, 5990000, 17970000);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (43, 'GD-5043', 43, 17970000, 'momo', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (44, 'DH-1044', 27, 'Đặng Hữu Sơn', '0945873291', 'Quận 9, TP. Hồ Chí Minh', '2026-10-01 08:52:41', 'bank', 16070000, 'da_xac_nhan');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (44, 8, 'SP-1008', 'Samsung Galaxy Tab S9 FE', '128GB WiFi / Xám', 1, 10490000, 10490000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (44, 20, 'SP-1020', 'Giày Nike Air Force 1 \'07', '40 / Trắng', 2, 2790000, 5580000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (44, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (44, 'GD-5044', 44, 16070000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-01 09:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (44, 'VC-7044', 44, 'Viettel Post', 'OF708568516', '2026-10-02 08:52:41', 'cho_lay_hang');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (45, 'DH-1045', 20, 'Vũ Văn Mai', '0945808541', 'Quận 10, TP. Hồ Chí Minh', '2026-09-02 22:52:41', 'cod', 47490000, 'da_xac_nhan');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (45, 13, 'SP-1013', 'Garmin Fenix 7 Solar', '47mm / Đen', 2, 18500000, 37000000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (45, 8, 'SP-1008', 'Samsung Galaxy Tab S9 FE', '128GB WiFi / Xám', 1, 10490000, 10490000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (45, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (45, 'GD-5045', 45, 47490000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-02 23:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (45, 'VC-7045', 45, 'Giao Hàng Nhanh (GHN)', 'OF145867404', '2026-09-03 22:52:41', 'cho_lay_hang');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (46, 'DH-1046', 16, 'Đỗ Quốc Linh', '0948940316', 'Quận 10, TP. Hồ Chí Minh', '2026-09-08 02:52:41', 'bank', 35460000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (46, 12, 'SP-1012', 'Apple Watch Series 9', '45mm Cellular', 3, 10990000, 32970000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (46, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Graphite', 1, 2490000, 2490000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (46, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (46, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (46, 'GD-5046', 46, 35460000, 'bank', 'that_bai', 'Thanh toán mô phỏng (seed)', '2026-09-08 03:52:41');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (47, 'DH-1047', 14, 'Vũ Bảo Dũng', '0986654325', 'Quận 3, TP. Hồ Chí Minh', '2026-10-04 07:52:41', 'momo', 26980000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (47, 7, 'SP-1007', 'iPad Air M2 11-inch', '64GB WiFi', 1, 15990000, 15990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (47, 12, 'SP-1012', 'Apple Watch Series 9', '45mm GPS', 1, 10990000, 10990000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (47, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (47, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (47, 'GD-5047', 47, 26980000, 'momo', 'that_bai', 'Thanh toán mô phỏng (seed)', '2026-10-04 08:52:41');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (48, 'DH-1048', 27, 'Đặng Hữu Sơn', '0945873291', 'Quận 9, TP. Hồ Chí Minh', '2026-10-02 14:52:41', 'cod', 31580000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (48, 4, 'SP-1004', 'MacBook Air M3 13-inch', '16GB/512GB', 1, 28990000, 28990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (48, 18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', '23L', 1, 2590000, 2590000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (48, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (48, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (48, 'GD-5048', 48, 31580000, 'cod', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (49, 'DH-1049', 15, 'Đặng Quốc Yên', '0929414106', 'Quận 6, TP. Hồ Chí Minh', '2026-10-09 01:52:41', 'bank', 35040000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (49, 9, 'SP-1009', 'AirPods Pro 2 (USB-C)', 'Tiêu chuẩn', 3, 5990000, 17970000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (49, 8, 'SP-1008', 'Samsung Galaxy Tab S9 FE', '128GB WiFi / Xám', 1, 10490000, 10490000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (49, 11, 'SP-1011', 'Loa JBL Charge 5', 'Đỏ', 2, 3290000, 6580000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (49, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (49, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (49, 'GD-5049', 49, 35040000, 'bank', 'that_bai', 'Thanh toán mô phỏng (seed)', '2026-10-09 02:52:41');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (50, 'DH-1050', 13, 'Trần Gia Uyên', '0960053075', 'Quận 3, TP. Hồ Chí Minh', '2026-09-15 01:52:41', 'cod', 144960000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (50, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '512GB / Xanh', 3, 34990000, 104970000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (50, 6, 'SP-1006', 'ASUS ROG Strix G16', '32GB/1TB', 1, 39990000, 39990000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (50, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (50, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (50, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (50, 'GD-5050', 50, 144960000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-15 02:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (50, 'VC-7050', 50, 'Ninja Van', 'OF275379111', '2026-09-16 01:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (51, 'DH-1051', 10, 'Nguyễn Gia Quân', '0968646768', 'Quận 8, TP. Hồ Chí Minh', '2026-10-19 19:52:41', 'bank', 47970000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (51, 7, 'SP-1007', 'iPad Air M2 11-inch', '256GB WiFi', 3, 15990000, 47970000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (51, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (51, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (51, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (51, 'GD-5051', 51, 47970000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-19 20:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (51, 'VC-7051', 51, 'Ninja Van', 'OF355899464', '2026-10-20 19:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (52, 'DH-1052', 19, 'Vũ Văn Nam', '0930141011', 'Quận 9, TP. Hồ Chí Minh', '2026-10-10 00:52:41', 'cod', 4980000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (52, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Graphite', 2, 2490000, 4980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (52, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (52, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (52, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (52, 'GD-5052', 52, 4980000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-10 01:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (52, 'VC-7052', 52, 'Giao Hàng Nhanh (GHN)', 'OF821810666', '2026-10-11 00:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (53, 'DH-1053', 24, 'Hồ Bảo Mai', '0957822407', 'Quận 10, TP. Hồ Chí Minh', '2026-10-20 16:52:41', 'cod', 41740000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (53, 19, 'SP-1019', 'Áo khoác Unifree chống nước', 'L / Xám', 2, 890000, 1780000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (53, 7, 'SP-1007', 'iPad Air M2 11-inch', '64GB WiFi', 1, 15990000, 15990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (53, 16, 'SP-1016', 'Màn hình LG UltraGear 27GQ750', '27\" 4K 144Hz', 3, 7990000, 23970000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (53, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (53, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (53, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (53, 'GD-5053', 53, 41740000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-20 17:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (53, 'VC-7053', 53, 'Viettel Post', 'OF474630261', '2026-10-21 16:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (54, 'DH-1054', 24, 'Hồ Bảo Mai', '0957822407', 'Quận 11, TP. Hồ Chí Minh', '2026-09-21 16:52:41', 'bank', 7990000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (54, 16, 'SP-1016', 'Màn hình LG UltraGear 27GQ750', '27\" 4K 144Hz', 1, 7990000, 7990000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (54, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (54, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (54, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (54, 'GD-5054', 54, 7990000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-21 17:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (54, 'VC-7054', 54, 'Viettel Post', 'OF131311567', '2026-09-22 16:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (55, 'DH-1055', 17, 'Hồ Ngọc Hà', '0970523194', 'Quận 1, TP. Hồ Chí Minh', '2026-10-14 10:52:41', 'cod', 7470000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (55, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Graphite', 3, 2490000, 7470000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (55, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (55, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (55, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (55, 'GD-5055', 55, 7470000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-14 11:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (55, 'VC-7055', 55, 'Ninja Van', 'OF938141841', '2026-10-15 10:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (56, 'DH-1056', 16, 'Đỗ Quốc Linh', '0948940316', 'Quận 2, TP. Hồ Chí Minh', '2026-10-03 09:52:41', 'bank', 18500000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (56, 13, 'SP-1013', 'Garmin Fenix 7 Solar', '47mm / Đen', 1, 18500000, 18500000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (56, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (56, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (56, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (56, 'GD-5056', 56, 18500000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-03 10:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (56, 'VC-7056', 56, 'Giao Hàng Nhanh (GHN)', 'OF144076537', '2026-10-04 09:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (57, 'DH-1057', 19, 'Vũ Văn Nam', '0930141011', 'Quận 12, TP. Hồ Chí Minh', '2026-09-16 11:52:41', 'bank', 2590000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (57, 18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', '23L', 1, 2590000, 2590000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (57, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (57, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (57, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (57, 'GD-5057', 57, 2590000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-16 12:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (57, 'VC-7057', 57, 'Viettel Post', 'OF492026482', '2026-09-17 11:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (58, 'DH-1058', 25, 'Phạm Hữu Quân', '0943023371', 'Quận 7, TP. Hồ Chí Minh', '2026-10-01 10:52:41', 'cod', 35760000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (58, 2, 'SP-1002', 'Samsung Galaxy S24 Ultra', '512GB / Xám', 1, 27990000, 27990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (58, 18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', '23L', 3, 2590000, 7770000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (58, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (58, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (58, 'GD-5058', 58, 35760000, 'cod', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (59, 'DH-1059', 3, 'Lê Minh Tuấn', '0901000003', 'Quận 11, TP. Hồ Chí Minh', '2026-10-02 00:52:41', 'bank', 79980000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (59, 6, 'SP-1006', 'ASUS ROG Strix G16', '32GB/1TB', 2, 39990000, 79980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (59, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (59, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (59, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (59, 'GD-5059', 59, 79980000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-02 01:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (59, 'VC-7059', 59, 'Giao Hàng Nhanh (GHN)', 'OF770723661', '2026-10-03 00:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (60, 'DH-1060', 7, 'Phạm Gia Yên', '0943167347', 'Quận 9, TP. Hồ Chí Minh', '2026-10-19 12:52:41', 'cod', 97450000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (60, 10, 'SP-1010', 'Sony WH-1000XM5', 'Bạc', 1, 7490000, 7490000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (60, 7, 'SP-1007', 'iPad Air M2 11-inch', '256GB WiFi', 2, 15990000, 31980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (60, 4, 'SP-1004', 'MacBook Air M3 13-inch', '16GB/512GB', 2, 28990000, 57980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (60, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (60, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (60, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (60, 'GD-5060', 60, 97450000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-19 13:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (60, 'VC-7060', 60, 'J&T Express', 'OF940287165', '2026-10-20 12:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (61, 'DH-1061', 14, 'Vũ Bảo Dũng', '0986654325', 'Quận 6, TP. Hồ Chí Minh', '2026-10-20 11:52:41', 'cod', 66270000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (61, 11, 'SP-1011', 'Loa JBL Charge 5', 'Đen', 1, 3290000, 3290000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (61, 2, 'SP-1002', 'Samsung Galaxy S24 Ultra', '512GB / Xám', 1, 27990000, 27990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (61, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '256GB / Titan Tự nhiên', 1, 34990000, 34990000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (61, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (61, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (61, 'GD-5061', 61, 66270000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-20 12:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (61, 'VC-7061', 61, 'Giao Hàng Nhanh (GHN)', 'OF698014679', '2026-10-21 11:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (62, 'DH-1062', 24, 'Hồ Bảo Mai', '0957822407', 'Quận 2, TP. Hồ Chí Minh', '2026-09-04 16:52:41', 'cod', 40950000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (62, 17, 'SP-1017', 'Nồi cơm điện Panasonic SR-DF183', '1.8L', 3, 1290000, 3870000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (62, 5, 'SP-1005', 'Dell XPS 13 Plus', '16GB/512GB / Bạch kim', 1, 31500000, 31500000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (62, 20, 'SP-1020', 'Giày Nike Air Force 1 \'07', '40 / Trắng', 2, 2790000, 5580000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (62, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (62, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (62, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (62, 'GD-5062', 62, 40950000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-04 17:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (62, 'VC-7062', 62, 'Ninja Van', 'OF905879534', '2026-09-05 16:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (63, 'DH-1063', 24, 'Hồ Bảo Mai', '0957822407', 'Quận 7, TP. Hồ Chí Minh', '2026-10-18 12:52:41', 'cod', 39370000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (63, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '512GB / Xanh', 1, 34990000, 34990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (63, 15, 'SP-1015', 'Bàn phím Keychron K2 Pro', 'RGB / Brown Switch', 2, 2190000, 4380000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (63, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (63, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (63, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (63, 'GD-5063', 63, 39370000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-18 13:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (63, 'VC-7063', 63, 'J&T Express', 'OF730672851', '2026-10-19 12:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (64, 'DH-1064', 3, 'Lê Minh Tuấn', '0901000003', 'Quận 12, TP. Hồ Chí Minh', '2026-10-11 23:52:41', 'bank', 140430000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (64, 2, 'SP-1002', 'Samsung Galaxy S24 Ultra', '512GB / Xám', 1, 27990000, 27990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (64, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Pale Grey', 3, 2490000, 7470000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (64, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '512GB / Xanh', 3, 34990000, 104970000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (64, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (64, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (64, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (64, 'GD-5064', 64, 140430000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-12 00:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (64, 'VC-7064', 64, 'Ninja Van', 'OF832435186', '2026-10-12 23:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (65, 'DH-1065', 20, 'Vũ Văn Mai', '0945808541', 'Quận 6, TP. Hồ Chí Minh', '2026-09-04 10:52:41', 'cod', 25980000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (65, 3, 'SP-1003', 'Xiaomi 14T Pro 5G', '512GB / Xanh', 2, 12990000, 25980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (65, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (65, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (65, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (65, 'GD-5065', 65, 25980000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-04 11:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (65, 'VC-7065', 65, 'Viettel Post', 'OF837277736', '2026-09-05 10:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (66, 'DH-1066', 30, 'Trần Bảo Chi', '0920504175', 'Quận 1, TP. Hồ Chí Minh', '2026-08-26 08:52:41', 'momo', 29880000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (66, 4, 'SP-1004', 'MacBook Air M3 13-inch', '8GB/256GB', 1, 28990000, 28990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (66, 19, 'SP-1019', 'Áo khoác Unifree chống nước', 'M / Đen', 1, 890000, 890000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (66, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (66, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (66, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (66, 'GD-5066', 66, 29880000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-08-26 09:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (66, 'VC-7066', 66, 'Ninja Van', 'OF640137846', '2026-08-27 08:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (67, 'DH-1067', 27, 'Đặng Hữu Sơn', '0945873291', 'Quận 8, TP. Hồ Chí Minh', '2026-10-10 13:52:41', 'momo', 35160000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (67, 12, 'SP-1012', 'Apple Watch Series 9', '45mm GPS', 3, 10990000, 32970000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (67, 15, 'SP-1015', 'Bàn phím Keychron K2 Pro', 'RGB / Brown Switch', 1, 2190000, 2190000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (67, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (67, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (67, 'GD-5067', 67, 35160000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-10 14:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (67, 'VC-7067', 67, 'Viettel Post', 'OF590498233', '2026-10-11 13:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (68, 'DH-1068', 10, 'Nguyễn Gia Quân', '0968646768', 'Quận 9, TP. Hồ Chí Minh', '2026-10-15 14:52:41', 'cod', 42180000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (68, 6, 'SP-1006', 'ASUS ROG Strix G16', '16GB/1TB / Đen', 1, 39990000, 39990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (68, 15, 'SP-1015', 'Bàn phím Keychron K2 Pro', 'RGB / Brown Switch', 1, 2190000, 2190000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (68, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (68, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (68, 'GD-5068', 68, 42180000, 'cod', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (69, 'DH-1069', 5, 'Đỗ Anh Mai', '0935047646', 'Quận 4, TP. Hồ Chí Minh', '2026-10-20 15:52:41', 'bank', 12470000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (69, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Graphite', 2, 2490000, 4980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (69, 10, 'SP-1010', 'Sony WH-1000XM5', 'Bạc', 1, 7490000, 7490000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (69, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (69, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (69, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (69, 'GD-5069', 69, 12470000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-20 16:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (69, 'VC-7069', 69, 'Ninja Van', 'OF812394964', '2026-10-21 15:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (70, 'DH-1070', 30, 'Trần Bảo Chi', '0920504175', 'Quận 9, TP. Hồ Chí Minh', '2026-08-25 10:52:41', 'bank', 122160000, 'cho_xac_nhan', DATE_ADD(NOW(), INTERVAL 15 MINUTE));
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (70, 15, 'SP-1015', 'Bàn phím Keychron K2 Pro', 'RGB / Brown Switch', 1, 2190000, 2190000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (70, 6, 'SP-1006', 'ASUS ROG Strix G16', '32GB/1TB', 3, 39990000, 119970000);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (70, 'GD-5070', 70, 122160000, 'bank', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (71, 'DH-1071', 26, 'Đặng Bảo Oanh', '0998867996', 'Quận 8, TP. Hồ Chí Minh', '2026-08-24 16:52:41', 'cod', 17060000, 'cho_xac_nhan', DATE_ADD(NOW(), INTERVAL 15 MINUTE));
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (71, 8, 'SP-1008', 'Samsung Galaxy Tab S9 FE', '128GB WiFi / Xám', 1, 10490000, 10490000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (71, 15, 'SP-1015', 'Bàn phím Keychron K2 Pro', 'RGB / Brown Switch', 3, 2190000, 6570000);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (71, 'GD-5071', 71, 17060000, 'cod', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (72, 'DH-1072', 26, 'Đặng Bảo Oanh', '0998867996', 'Quận 5, TP. Hồ Chí Minh', '2026-09-01 23:52:41', 'cod', 52980000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (72, 13, 'SP-1013', 'Garmin Fenix 7 Solar', '47mm / Đen', 2, 18500000, 37000000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (72, 16, 'SP-1016', 'Màn hình LG UltraGear 27GQ750', '27\" 4K 144Hz', 2, 7990000, 15980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (72, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (72, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (72, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (72, 'GD-5072', 72, 52980000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-02 00:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (72, 'VC-7072', 72, 'Ninja Van', 'OF341466111', '2026-09-02 23:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (73, 'DH-1073', 20, 'Vũ Văn Mai', '0945808541', 'Quận 9, TP. Hồ Chí Minh', '2026-10-14 07:52:41', 'bank', 9870000, 'da_xac_nhan');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (73, 11, 'SP-1011', 'Loa JBL Charge 5', 'Đỏ', 3, 3290000, 9870000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (73, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (73, 'GD-5073', 73, 9870000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-14 08:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (73, 'VC-7073', 73, 'J&T Express', 'OF761439427', '2026-10-15 07:52:41', 'cho_lay_hang');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (74, 'DH-1074', 7, 'Phạm Gia Yên', '0943167347', 'Quận 3, TP. Hồ Chí Minh', '2026-10-15 17:52:41', 'bank', 26960000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (74, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Graphite', 2, 2490000, 4980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (74, 12, 'SP-1012', 'Apple Watch Series 9', '41mm GPS', 2, 10990000, 21980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (74, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (74, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (74, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (74, 'GD-5074', 74, 26960000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-15 18:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (74, 'VC-7074', 74, 'J&T Express', 'OF978040568', '2026-10-16 17:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (75, 'DH-1075', 20, 'Vũ Văn Mai', '0945808541', 'Quận 6, TP. Hồ Chí Minh', '2026-08-29 18:52:41', 'momo', 68180000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (75, 5, 'SP-1005', 'Dell XPS 13 Plus', '16GB/512GB / Bạch kim', 2, 31500000, 63000000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (75, 18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', '23L', 2, 2590000, 5180000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (75, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (75, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (75, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (75, 'GD-5075', 75, 68180000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-08-29 19:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (75, 'VC-7075', 75, 'Viettel Post', 'OF173276559', '2026-08-30 18:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (76, 'DH-1076', 14, 'Vũ Bảo Dũng', '0986654325', 'Quận 2, TP. Hồ Chí Minh', '2026-10-10 01:52:41', 'bank', 82170000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (76, 6, 'SP-1006', 'ASUS ROG Strix G16', '32GB/1TB', 2, 39990000, 79980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (76, 15, 'SP-1015', 'Bàn phím Keychron K2 Pro', 'RGB / Brown Switch', 1, 2190000, 2190000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (76, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (76, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (76, 'GD-5076', 76, 82170000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-10 02:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (76, 'VC-7076', 76, 'Giao Hàng Nhanh (GHN)', 'OF623860479', '2026-10-11 01:52:41', 'that_bai');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (77, 'DH-1077', 18, 'Bùi Anh Phong', '0924656020', 'Quận 7, TP. Hồ Chí Minh', '2026-09-12 15:52:41', 'bank', 131500000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (77, 13, 'SP-1013', 'Garmin Fenix 7 Solar', '47mm / Đen', 2, 18500000, 37000000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (77, 5, 'SP-1005', 'Dell XPS 13 Plus', '16GB/512GB / Bạch kim', 3, 31500000, 94500000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (77, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (77, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (77, 'GD-5077', 77, 131500000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-12 16:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (77, 'VC-7077', 77, 'Giao Hàng Nhanh (GHN)', 'OF862923465', '2026-09-13 15:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (78, 'DH-1078', 20, 'Vũ Văn Mai', '0945808541', 'Quận 11, TP. Hồ Chí Minh', '2026-08-22 09:52:41', 'momo', 10760000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (78, 18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', '23L', 2, 2590000, 5180000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (78, 20, 'SP-1020', 'Giày Nike Air Force 1 \'07', '42 / Trắng', 2, 2790000, 5580000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (78, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (78, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (78, 'GD-5078', 78, 10760000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-08-22 10:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (78, 'VC-7078', 78, 'J&T Express', 'OF508814211', '2026-08-23 09:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (79, 'DH-1079', 29, 'Đỗ Thanh Em', '0960129306', 'Quận 9, TP. Hồ Chí Minh', '2026-09-06 02:52:41', 'momo', 8370000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (79, 20, 'SP-1020', 'Giày Nike Air Force 1 \'07', '42 / Trắng', 3, 2790000, 8370000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (79, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (79, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (79, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (79, 'GD-5079', 79, 8370000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-06 03:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (79, 'VC-7079', 79, 'J&T Express', 'OF298395259', '2026-09-07 02:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (80, 'DH-1080', 26, 'Đặng Bảo Oanh', '0998867996', 'Quận 8, TP. Hồ Chí Minh', '2026-09-05 20:52:41', 'cod', 104960000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (80, 9, 'SP-1009', 'AirPods Pro 2 (USB-C)', 'Tiêu chuẩn', 1, 5990000, 5990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (80, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '1TB / Đen', 2, 34990000, 69980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (80, 4, 'SP-1004', 'MacBook Air M3 13-inch', '16GB/512GB', 1, 28990000, 28990000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (80, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (80, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (80, 'GD-5080', 80, 104960000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-05 21:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (80, 'VC-7080', 80, 'Giao Hàng Nhanh (GHN)', 'OF925762585', '2026-09-06 20:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (81, 'DH-1081', 19, 'Vũ Văn Nam', '0930141011', 'Quận 2, TP. Hồ Chí Minh', '2026-10-10 00:52:41', 'cod', 86560000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (81, 11, 'SP-1011', 'Loa JBL Charge 5', 'Xanh', 2, 3290000, 6580000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (81, 6, 'SP-1006', 'ASUS ROG Strix G16', '16GB/1TB / Đen', 2, 39990000, 79980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (81, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (81, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (81, 'GD-5081', 81, 86560000, 'cod', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (82, 'DH-1082', 11, 'Trần Thị Dũng', '0918220413', 'Quận 9, TP. Hồ Chí Minh', '2026-09-11 22:52:41', 'momo', 86970000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (82, 4, 'SP-1004', 'MacBook Air M3 13-inch', '16GB/512GB', 3, 28990000, 86970000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (82, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (82, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (82, 'GD-5082', 82, 86970000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-11 23:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (82, 'VC-7082', 82, 'Ninja Van', 'OF568234544', '2026-09-12 22:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (83, 'DH-1083', 8, 'Nguyễn Ngọc Linh', '0911219547', 'Quận 11, TP. Hồ Chí Minh', '2026-10-19 13:52:41', 'momo', 47490000, 'cho_xac_nhan', DATE_ADD(NOW(), INTERVAL 15 MINUTE));
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (83, 5, 'SP-1005', 'Dell XPS 13 Plus', '16GB/512GB / Bạch kim', 1, 31500000, 31500000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (83, 7, 'SP-1007', 'iPad Air M2 11-inch', '256GB WiFi', 1, 15990000, 15990000);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (83, 'GD-5083', 83, 47490000, 'momo', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (84, 'DH-1084', 26, 'Đặng Bảo Oanh', '0998867996', 'Quận 3, TP. Hồ Chí Minh', '2026-09-08 13:52:41', 'bank', 112940000, 'da_xac_nhan');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (84, 16, 'SP-1016', 'Màn hình LG UltraGear 27GQ750', '27\" 4K 144Hz', 2, 7990000, 15980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (84, 2, 'SP-1002', 'Samsung Galaxy S24 Ultra', '256GB / Tím', 3, 27990000, 83970000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (84, 3, 'SP-1003', 'Xiaomi 14T Pro 5G', '512GB / Xanh', 1, 12990000, 12990000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (84, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (84, 'GD-5084', 84, 112940000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-08 14:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (84, 'VC-7084', 84, 'J&T Express', 'OF754134592', '2026-09-09 13:52:41', 'cho_lay_hang');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (85, 'DH-1085', 17, 'Hồ Ngọc Hà', '0970523194', 'Quận 6, TP. Hồ Chí Minh', '2026-08-23 00:52:41', 'cod', 35060000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (85, 19, 'SP-1019', 'Áo khoác Unifree chống nước', 'M / Đen', 1, 890000, 890000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (85, 15, 'SP-1015', 'Bàn phím Keychron K2 Pro', 'RGB / Brown Switch', 1, 2190000, 2190000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (85, 7, 'SP-1007', 'iPad Air M2 11-inch', '64GB WiFi', 2, 15990000, 31980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (85, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (85, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (85, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (85, 'GD-5085', 85, 35060000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-08-23 01:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (85, 'VC-7085', 85, 'Viettel Post', 'OF477804002', '2026-08-24 00:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (86, 'DH-1086', 15, 'Đặng Quốc Yên', '0929414106', 'Quận 11, TP. Hồ Chí Minh', '2026-10-06 13:52:41', 'momo', 65470000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (86, 9, 'SP-1009', 'AirPods Pro 2 (USB-C)', 'Tiêu chuẩn', 2, 5990000, 11980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (86, 13, 'SP-1013', 'Garmin Fenix 7 Solar', '47mm / Đen', 1, 18500000, 18500000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (86, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '1TB / Đen', 1, 34990000, 34990000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (86, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (86, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (86, 'GD-5086', 86, 65470000, 'momo', 'that_bai', 'Thanh toán mô phỏng (seed)', '2026-10-06 14:52:41');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (87, 'DH-1087', 10, 'Nguyễn Gia Quân', '0968646768', 'Quận 2, TP. Hồ Chí Minh', '2026-09-18 01:52:41', 'cod', 46950000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (87, 3, 'SP-1003', 'Xiaomi 14T Pro 5G', '256GB / Đen', 1, 12990000, 12990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (87, 9, 'SP-1009', 'AirPods Pro 2 (USB-C)', 'Tiêu chuẩn', 2, 5990000, 11980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (87, 12, 'SP-1012', 'Apple Watch Series 9', '45mm GPS', 2, 10990000, 21980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (87, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (87, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (87, 'GD-5087', 87, 46950000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-18 02:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (87, 'VC-7087', 87, 'Viettel Post', 'OF584970000', '2026-09-19 01:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (88, 'DH-1088', 15, 'Đặng Quốc Yên', '0929414106', 'Quận 2, TP. Hồ Chí Minh', '2026-09-04 19:52:41', 'momo', 69980000, 'cho_xac_nhan', DATE_ADD(NOW(), INTERVAL 15 MINUTE));
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (88, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '1TB / Đen', 2, 34990000, 69980000);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (88, 'GD-5088', 88, 69980000, 'momo', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (89, 'DH-1089', 5, 'Đỗ Anh Mai', '0935047646', 'Quận 4, TP. Hồ Chí Minh', '2026-10-13 01:52:41', 'bank', 38450000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (89, 16, 'SP-1016', 'Màn hình LG UltraGear 27GQ750', '27\" 4K 144Hz', 2, 7990000, 15980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (89, 10, 'SP-1010', 'Sony WH-1000XM5', 'Đen', 3, 7490000, 22470000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (89, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (89, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (89, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (89, 'GD-5089', 89, 38450000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-13 02:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (89, 'VC-7089', 89, 'Ninja Van', 'OF418631114', '2026-10-14 01:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (90, 'DH-1090', 2, 'Trần Thị Hoa', '0901000002', 'Quận 8, TP. Hồ Chí Minh', '2026-09-13 10:52:41', 'bank', 182920000, 'da_huy');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (90, 4, 'SP-1004', 'MacBook Air M3 13-inch', '16GB/512GB', 3, 28990000, 86970000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (90, 2, 'SP-1002', 'Samsung Galaxy S24 Ultra', '512GB / Xám', 3, 27990000, 83970000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (90, 9, 'SP-1009', 'AirPods Pro 2 (USB-C)', 'Tiêu chuẩn', 2, 5990000, 11980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (90, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note, changed_by) VALUES (90, 'cho_xac_nhan', 'da_huy', 'Hủy đơn (seed)', 1);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (90, 'GD-5090', 90, 182920000, 'bank', 'that_bai', 'Thanh toán mô phỏng (seed)', '2026-09-13 11:52:41');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (91, 'DH-1091', 30, 'Trần Bảo Chi', '0920504175', 'Quận 5, TP. Hồ Chí Minh', '2026-09-26 03:52:41', 'cod', 63000000, 'da_xac_nhan');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (91, 5, 'SP-1005', 'Dell XPS 13 Plus', '16GB/512GB / Bạch kim', 2, 31500000, 63000000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (91, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (91, 'GD-5091', 91, 63000000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-26 04:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (91, 'VC-7091', 91, 'Viettel Post', 'OF201950014', '2026-09-27 03:52:41', 'cho_lay_hang');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (92, 'DH-1092', 7, 'Phạm Gia Yên', '0943167347', 'Quận 8, TP. Hồ Chí Minh', '2026-08-24 08:52:41', 'cod', 62250000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (92, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '256GB / Titan Tự nhiên', 1, 34990000, 34990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (92, 11, 'SP-1011', 'Loa JBL Charge 5', 'Đỏ', 1, 3290000, 3290000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (92, 16, 'SP-1016', 'Màn hình LG UltraGear 27GQ750', '27\" 4K 144Hz', 3, 7990000, 23970000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (92, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (92, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (92, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (92, 'GD-5092', 92, 62250000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-08-24 09:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (92, 'VC-7092', 92, 'Viettel Post', 'OF870497012', '2026-08-25 08:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (93, 'DH-1093', 3, 'Lê Minh Tuấn', '0901000003', 'Quận 2, TP. Hồ Chí Minh', '2026-10-18 03:52:41', 'bank', 145440000, 'cho_xac_nhan', DATE_ADD(NOW(), INTERVAL 15 MINUTE));
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (93, 2, 'SP-1002', 'Samsung Galaxy S24 Ultra', '256GB / Tím', 2, 27990000, 55980000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (93, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Pale Grey', 1, 2490000, 2490000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (93, 4, 'SP-1004', 'MacBook Air M3 13-inch', '16GB/512GB', 3, 28990000, 86970000);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (93, 'GD-5093', 93, 145440000, 'bank', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (94, 'DH-1094', 25, 'Phạm Hữu Quân', '0943023371', 'Quận 1, TP. Hồ Chí Minh', '2026-09-29 20:52:41', 'momo', 6470000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (94, 17, 'SP-1017', 'Nồi cơm điện Panasonic SR-DF183', '1.8L', 1, 1290000, 1290000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (94, 18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', '23L', 2, 2590000, 5180000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (94, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (94, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (94, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (94, 'GD-5094', 94, 6470000, 'momo', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-29 21:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (94, 'VC-7094', 94, 'Giao Hàng Nhanh (GHN)', 'OF718892101', '2026-09-30 20:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status, hold_expires_at) VALUES (95, 'DH-1095', 8, 'Nguyễn Ngọc Linh', '0911219547', 'Quận 5, TP. Hồ Chí Minh', '2026-08-22 11:52:41', 'momo', 107550000, 'cho_xac_nhan', DATE_ADD(NOW(), INTERVAL 15 MINUTE));
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (95, 17, 'SP-1017', 'Nồi cơm điện Panasonic SR-DF183', '1.8L', 2, 1290000, 2580000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (95, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '512GB / Xanh', 3, 34990000, 104970000);
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (95, 'GD-5095', 95, 107550000, 'momo', 'cho_xu_ly', 'Thanh toán mô phỏng (seed)', NULL);
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (96, 'DH-1096', 22, 'Bùi Ngọc Yên', '0989584256', 'Quận 5, TP. Hồ Chí Minh', '2026-09-19 23:52:41', 'bank', 55980000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (96, 2, 'SP-1002', 'Samsung Galaxy S24 Ultra', '512GB / Xám', 2, 27990000, 55980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (96, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (96, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (96, 'GD-5096', 96, 55980000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-20 00:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (96, 'VC-7096', 96, 'Viettel Post', 'OF490403712', '2026-09-20 23:52:41', 'dang_van_chuyen');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (97, 'DH-1097', 22, 'Bùi Ngọc Yên', '0989584256', 'Quận 11, TP. Hồ Chí Minh', '2026-10-17 22:52:41', 'cod', 890000, 'da_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (97, 19, 'SP-1019', 'Áo khoác Unifree chống nước', 'L / Xám', 1, 890000, 890000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (97, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (97, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (97, 'dang_giao', 'da_giao', 'Khách đã nhận hàng (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (97, 'GD-5097', 97, 890000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-17 23:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (97, 'VC-7097', 97, 'J&T Express', 'OF518143882', '2026-10-18 22:52:41', 'da_giao');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (98, 'DH-1098', 9, 'Phạm Ngọc Uyên', '0979474561', 'Quận 6, TP. Hồ Chí Minh', '2026-09-07 14:52:41', 'cod', 48230000, 'da_xac_nhan');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (98, 18, 'SP-1018', 'Lò vi sóng Samsung MS23F301', '23L', 3, 2590000, 7770000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (98, 12, 'SP-1012', 'Apple Watch Series 9', '45mm GPS', 3, 10990000, 32970000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (98, 10, 'SP-1010', 'Sony WH-1000XM5', 'Bạc', 1, 7490000, 7490000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (98, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (98, 'GD-5098', 98, 48230000, 'cod', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-07 15:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (98, 'VC-7098', 98, 'J&T Express', 'OF997396992', '2026-09-08 14:52:41', 'cho_lay_hang');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (99, 'DH-1099', 11, 'Trần Thị Dũng', '0918220413', 'Quận 1, TP. Hồ Chí Minh', '2026-10-18 12:52:41', 'bank', 72470000, 'da_xac_nhan');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (99, 14, 'SP-1014', 'Chuột Logitech MX Master 3S', 'Graphite', 1, 2490000, 2490000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (99, 1, 'SP-1001', 'iPhone 15 Pro Max 256GB', '256GB / Titan Tự nhiên', 2, 34990000, 69980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (99, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (99, 'GD-5099', 99, 72470000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-10-18 13:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (99, 'VC-7099', 99, 'Giao Hàng Nhanh (GHN)', 'OF591990172', '2026-10-19 12:52:41', 'cho_lay_hang');
INSERT INTO orders (id, order_code, user_id, customer_name, phone, address, order_date, payment_method, total_amount, status) VALUES (100, 'DH-1100', 15, 'Đặng Quốc Yên', '0929414106', 'Quận 3, TP. Hồ Chí Minh', '2026-09-27 19:52:41', 'bank', 103940000, 'dang_giao');
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (100, 3, 'SP-1003', 'Xiaomi 14T Pro 5G', '256GB / Đen', 1, 12990000, 12990000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (100, 12, 'SP-1012', 'Apple Watch Series 9', '45mm GPS', 3, 10990000, 32970000);
INSERT INTO order_items (order_id, product_id, sku, product_name, variant, quantity, unit_price, line_total) VALUES (100, 4, 'SP-1004', 'MacBook Air M3 13-inch', '16GB/512GB', 2, 28990000, 57980000);
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (100, 'cho_xac_nhan', 'da_xac_nhan', 'Xác nhận đơn (seed)');
INSERT INTO order_history (order_id, from_status, to_status, note) VALUES (100, 'da_xac_nhan', 'dang_giao', 'Bàn giao vận chuyển (seed)');
INSERT INTO payments (id, payment_code, order_id, amount, method, status, note, processed_at) VALUES (100, 'GD-5100', 100, 103940000, 'bank', 'thanh_cong', 'Thanh toán mô phỏng (seed)', '2026-09-27 20:52:41');
INSERT INTO shipments (id, shipment_code, order_id, carrier, tracking_code, ship_date, status) VALUES (100, 'VC-7100', 100, 'Ninja Van', 'OF746734013', '2026-09-28 19:52:41', 'dang_van_chuyen');

-- Yêu cầu đổi trả (từ đơn đã giao)
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (1, 'DT-201', 63, (SELECT user_id FROM orders WHERE id=63), (SELECT customer_name FROM orders WHERE id=63), 'Máy bị nóng bất thường khi sạc', 'cho_xu_ly', NULL, NULL);
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 1, oi.id, oi.product_id, oi.product_name, 2, 0, oi.unit_price, 0, 1 FROM order_items oi WHERE oi.order_id = 63 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 1;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (2, 'DT-202', 57, (SELECT user_id FROM orders WHERE id=57), (SELECT customer_name FROM orders WHERE id=57), 'Giao sai màu so với đơn đã đặt', 'da_chap_nhan', 1, '2026-10-14 03:52:41');
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 2, oi.id, oi.product_id, oi.product_name, 1, 1, oi.unit_price, 1, 1 FROM order_items oi WHERE oi.order_id = 57 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 2;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (3, 'DT-203', 17, (SELECT user_id FROM orders WHERE id=17), (SELECT customer_name FROM orders WHERE id=17), 'Hộp móp, phụ kiện thiếu cáp sạc', 'tu_choi', 1, '2026-10-17 03:52:41');
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 3, oi.id, oi.product_id, oi.product_name, 2, 0, oi.unit_price, 0, 1 FROM order_items oi WHERE oi.order_id = 17 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 3;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (4, 'DT-204', 79, (SELECT user_id FROM orders WHERE id=79), (SELECT customer_name FROM orders WHERE id=79), 'Sản phẩm bị lỗi màn hình, có điểm chết', 'da_hoan_tat', 1, '2026-10-13 03:52:41');
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 4, oi.id, oi.product_id, oi.product_name, 1, 1, oi.unit_price, 1, 1 FROM order_items oi WHERE oi.order_id = 79 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 4;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (5, 'DT-205', 62, (SELECT user_id FROM orders WHERE id=62), (SELECT customer_name FROM orders WHERE id=62), 'Giao sai màu so với đơn đã đặt', 'cho_xu_ly', NULL, NULL);
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 5, oi.id, oi.product_id, oi.product_name, 2, 0, oi.unit_price, 0, 1 FROM order_items oi WHERE oi.order_id = 62 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 5;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (6, 'DT-206', 72, (SELECT user_id FROM orders WHERE id=72), (SELECT customer_name FROM orders WHERE id=72), 'Hộp móp, phụ kiện thiếu cáp sạc', 'da_chap_nhan', 1, '2026-10-13 03:52:41');
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 6, oi.id, oi.product_id, oi.product_name, 2, 1, oi.unit_price, 1, 1 FROM order_items oi WHERE oi.order_id = 72 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 6;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (7, 'DT-207', 1, (SELECT user_id FROM orders WHERE id=1), (SELECT customer_name FROM orders WHERE id=1), 'Khách đổi ý trong 15 ngày, hàng còn nguyên seal', 'tu_choi', 1, '2026-10-13 03:52:41');
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 7, oi.id, oi.product_id, oi.product_name, 1, 0, oi.unit_price, 0, 1 FROM order_items oi WHERE oi.order_id = 1 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 7;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (8, 'DT-208', 25, (SELECT user_id FROM orders WHERE id=25), (SELECT customer_name FROM orders WHERE id=25), 'Hộp móp, phụ kiện thiếu cáp sạc', 'da_hoan_tat', 1, '2026-10-11 03:52:41');
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 8, oi.id, oi.product_id, oi.product_name, 1, 1, oi.unit_price, 1, 1 FROM order_items oi WHERE oi.order_id = 25 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 8;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (9, 'DT-209', 74, (SELECT user_id FROM orders WHERE id=74), (SELECT customer_name FROM orders WHERE id=74), 'Hộp móp, phụ kiện thiếu cáp sạc', 'cho_xu_ly', NULL, NULL);
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 9, oi.id, oi.product_id, oi.product_name, 2, 0, oi.unit_price, 0, 1 FROM order_items oi WHERE oi.order_id = 74 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 9;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (10, 'DT-210', 23, (SELECT user_id FROM orders WHERE id=23), (SELECT customer_name FROM orders WHERE id=23), 'Giao sai màu so với đơn đã đặt', 'da_chap_nhan', 1, '2026-10-19 03:52:41');
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 10, oi.id, oi.product_id, oi.product_name, 1, 1, oi.unit_price, 1, 1 FROM order_items oi WHERE oi.order_id = 23 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 10;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (11, 'DT-211', 60, (SELECT user_id FROM orders WHERE id=60), (SELECT customer_name FROM orders WHERE id=60), 'Giao sai màu so với đơn đã đặt', 'tu_choi', 1, '2026-10-18 03:52:41');
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 11, oi.id, oi.product_id, oi.product_name, 2, 0, oi.unit_price, 0, 1 FROM order_items oi WHERE oi.order_id = 60 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 11;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (12, 'DT-212', 3, (SELECT user_id FROM orders WHERE id=3), (SELECT customer_name FROM orders WHERE id=3), 'Hộp móp, phụ kiện thiếu cáp sạc', 'da_hoan_tat', 1, '2026-10-11 03:52:41');
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 12, oi.id, oi.product_id, oi.product_name, 2, 2, oi.unit_price, 2, 1 FROM order_items oi WHERE oi.order_id = 3 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 12;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (13, 'DT-213', 56, (SELECT user_id FROM orders WHERE id=56), (SELECT customer_name FROM orders WHERE id=56), 'Giao sai màu so với đơn đã đặt', 'cho_xu_ly', NULL, NULL);
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 13, oi.id, oi.product_id, oi.product_name, 2, 0, oi.unit_price, 0, 1 FROM order_items oi WHERE oi.order_id = 56 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 13;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (14, 'DT-214', 40, (SELECT user_id FROM orders WHERE id=40), (SELECT customer_name FROM orders WHERE id=40), 'Khách đổi ý trong 15 ngày, hàng còn nguyên seal', 'da_chap_nhan', 1, '2026-10-13 03:52:41');
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 14, oi.id, oi.product_id, oi.product_name, 1, 1, oi.unit_price, 1, 1 FROM order_items oi WHERE oi.order_id = 40 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 14;
INSERT INTO return_requests (id, return_code, order_id, user_id, customer_name, reason, status, processed_by, processed_at) VALUES (15, 'DT-215', 51, (SELECT user_id FROM orders WHERE id=51), (SELECT customer_name FROM orders WHERE id=51), 'Sản phẩm bị lỗi màn hình, có điểm chết', 'tu_choi', 1, '2026-10-20 03:52:41');
INSERT INTO return_items (return_request_id, order_item_id, product_id, product_name, quantity_requested, quantity_accepted, unit_price, refund_amount, restock) SELECT 15, oi.id, oi.product_id, oi.product_name, 2, 0, oi.unit_price, 0, 1 FROM order_items oi WHERE oi.order_id = 51 ORDER BY oi.id LIMIT 1;
UPDATE return_items ri JOIN order_items oi ON oi.id = ri.order_item_id SET ri.refund_amount = ri.quantity_accepted * oi.unit_price WHERE ri.return_request_id = 15;

-- Phong trào tồn kho mẫu (xuất theo đơn + hủy hoàn kho)
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 1, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 1;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 2, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 2;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 3, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 3;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 4, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 4;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 5, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 5;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 6, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 6;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 7, 'huy_don', oi.quantity, 'Hoàn kho khi hủy đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 7;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 8, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 8;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 9, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 9;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 10, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 10;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 11, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 11;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 12, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 12;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 13, 'huy_don', oi.quantity, 'Hoàn kho khi hủy đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 13;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 14, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 14;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 15, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 15;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 16, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 16;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 17, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 17;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 18, 'huy_don', oi.quantity, 'Hoàn kho khi hủy đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 18;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 19, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 19;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 20, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 20;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 23, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 23;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 25, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 25;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 26, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 26;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 27, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 27;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 28, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 28;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 29, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 29;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 30, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 30;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 32, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 32;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 33, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 33;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 34, 'huy_don', oi.quantity, 'Hoàn kho khi hủy đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 34;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 35, 'huy_don', oi.quantity, 'Hoàn kho khi hủy đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 35;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 36, 'huy_don', oi.quantity, 'Hoàn kho khi hủy đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 36;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 37, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 37;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 38, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 38;
INSERT INTO inventory_movements (product_id, order_id, change_type, quantity, note, created_by) SELECT oi.product_id, 40, 'xuat', -oi.quantity, 'Xuất kho khi xác nhận đơn (seed)', 1 FROM order_items oi WHERE oi.order_id = 40;

