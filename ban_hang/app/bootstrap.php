<?php
declare(strict_types=1);

require_once __DIR__ . '/JsonStore.php';
require_once __DIR__ . '/SalesService.php';

$seed = [
    'products' => [
        ['id' => 'p-001', 'name' => 'Sổ tay bìa cứng', 'sku' => 'ST-001', 'category' => 'Văn phòng phẩm', 'price' => 45000, 'stock' => 32, 'low_stock' => 8],
        ['id' => 'p-002', 'name' => 'Bình giữ nhiệt 500ml', 'sku' => 'GD-002', 'category' => 'Gia dụng', 'price' => 185000, 'stock' => 4, 'low_stock' => 6],
        ['id' => 'p-003', 'name' => 'Tai nghe Bluetooth', 'sku' => 'DT-003', 'category' => 'Điện tử', 'price' => 420000, 'stock' => 15, 'low_stock' => 5],
        ['id' => 'p-004', 'name' => 'Túi vải Canvas', 'sku' => 'TT-004', 'category' => 'Thời trang', 'price' => 125000, 'stock' => 7, 'low_stock' => 8],
        ['id' => 'p-005', 'name' => 'Đèn bàn LED', 'sku' => 'GD-005', 'category' => 'Gia dụng', 'price' => 265000, 'stock' => 21, 'low_stock' => 5],
        ['id' => 'p-006', 'name' => 'Bút ký kim loại', 'sku' => 'ST-006', 'category' => 'Văn phòng phẩm', 'price' => 89000, 'stock' => 2, 'low_stock' => 10],
    ],
    'customers' => [
        ['id' => 'c-001', 'name' => 'Nguyễn Minh Anh', 'phone' => '0912345678', 'email' => 'minhanh@example.com', 'address' => 'Hà Nội'],
        ['id' => 'c-002', 'name' => 'Trần Hoàng Nam', 'phone' => '0987654321', 'email' => 'hoangnam@example.com', 'address' => 'Đà Nẵng'],
        ['id' => 'c-003', 'name' => 'Lê Thu Hà', 'phone' => '0905123456', 'email' => 'thuha@example.com', 'address' => 'TP. Hồ Chí Minh'],
        ['id' => 'c-004', 'name' => 'Phạm Đức Long', 'phone' => '0978123456', 'email' => '', 'address' => 'Hải Phòng'],
    ],
    'orders' => [
        [
            'id' => 'DH26001',
            'customer_id' => 'c-001',
            'customer_name' => 'Nguyễn Minh Anh',
            'items' => [
                ['product_id' => 'p-001', 'name' => 'Sổ tay bìa cứng', 'sku' => 'ST-001', 'price' => 45000, 'quantity' => 2, 'line_total' => 90000],
                ['product_id' => 'p-002', 'name' => 'Bình giữ nhiệt 500ml', 'sku' => 'GD-002', 'price' => 185000, 'quantity' => 1, 'line_total' => 185000],
            ],
            'total' => 275000,
            'status' => 'completed',
            'note' => '',
            'created_at' => date(DATE_ATOM, strtotime('-2 hours')),
        ],
        [
            'id' => 'DH26002',
            'customer_id' => 'c-003',
            'customer_name' => 'Lê Thu Hà',
            'items' => [
                ['product_id' => 'p-003', 'name' => 'Tai nghe Bluetooth', 'sku' => 'DT-003', 'price' => 420000, 'quantity' => 1, 'line_total' => 420000],
                ['product_id' => 'p-006', 'name' => 'Bút ký kim loại', 'sku' => 'ST-006', 'price' => 89000, 'quantity' => 2, 'line_total' => 178000],
            ],
            'total' => 598000,
            'status' => 'completed',
            'note' => '',
            'created_at' => date(DATE_ATOM, strtotime('-1 day')),
        ],
        [
            'id' => 'DH26003',
            'customer_id' => 'c-002',
            'customer_name' => 'Trần Hoàng Nam',
            'items' => [
                ['product_id' => 'p-005', 'name' => 'Đèn bàn LED', 'sku' => 'GD-005', 'price' => 265000, 'quantity' => 1, 'line_total' => 265000],
            ],
            'total' => 265000,
            'status' => 'completed',
            'note' => '',
            'created_at' => date(DATE_ATOM, strtotime('-3 days')),
        ],
    ],
];

$store = new JsonStore(__DIR__ . '/../data/store.json', $seed);
$sales = new SalesService($store);
