<?php
/**
 * Catalog mở rộng dùng chung cho database/generate_seed.php và migration.
 * Mỗi sản phẩm có biến thể chi tiết; stock sản phẩm = stock mỗi biến thể × số biến thể.
 */
declare(strict_types=1);

function catalog_grid(array $groups): array
{
    $combos = [[]];
    foreach ($groups as $group) {
        $next = [];
        foreach ($combos as $combo) {
            foreach ($group as $value) {
                $next[] = array_merge($combo, [$value]);
            }
        }
        $combos = $next;
    }
    return array_map(static fn(array $combo): string => implode(' / ', $combo), $combos);
}

function catalog_variant_price(int $basePrice, string $variantName, string $categoryCode): int
{
    if (!in_array($categoryCode, ['DM01', 'DM02', 'DM03'], true)) {
        return $basePrice;
    }
    $add = 0;
    if (preg_match('/1TB/i', $variantName)) {
        $add += 8000000;
    } elseif (preg_match('/512GB/i', $variantName)) {
        $add += 3000000;
    }
    if (stripos($variantName, 'Cellular') !== false || stripos($variantName, 'LTE') !== false) {
        $add += 1200000;
    }
    return $basePrice + $add;
}

function catalog_product(
    string $sku,
    string $name,
    string $categoryCode,
    int $basePrice,
    int $stockPerVariant,
    array $variantGroups,
    string $emoji
): array {
    $variants = catalog_grid($variantGroups);
    return [
        $sku,
        $name,
        $categoryCode,
        $basePrice,
        count($variants) * $stockPerVariant,
        implode('; ', $variants),
        $emoji,
    ];
}

$catalogProducts = [
    /* ---------- Phụ kiện điện thoại ---------- */
    catalog_product('SP-1021', 'Ốp lưng Silicon MagSafe iPhone', 'DM06', 390000, 5, [
        ['iPhone 16 Pro Max', 'iPhone 16 Pro', 'iPhone 16', 'iPhone 15 Pro Max', 'iPhone 15', 'iPhone 14', 'iPhone 13', 'iPhone 12'],
        ['Đen', 'Trong suốt', 'Xanh rêu'],
    ], '🛡️'),
    catalog_product('SP-1022', 'Ốp lưng trong suốt chống sốc iPhone', 'DM06', 290000, 6, [
        ['iPhone 16 Pro Max', 'iPhone 15 Pro Max', 'iPhone 14 Pro Max', 'iPhone 13', 'iPhone 12'],
        ['Trong suốt', 'Viền đen', 'Viền xanh'],
    ], '🛡️'),
    catalog_product('SP-1023', 'Ốp lưng da MagSafe iPhone', 'DM06', 690000, 4, [
        ['iPhone 16 Pro', 'iPhone 15 Pro', 'iPhone 14 Pro', 'iPhone 13 Pro', 'iPhone 12 Pro'],
        ['Nâu cognac', 'Đen', 'Xanh navy'],
    ], '🛡️'),
    catalog_product('SP-1024', 'Ốp lưng nhám chống bám vân tay Samsung Galaxy', 'DM06', 250000, 6, [
        ['Galaxy S24 Ultra', 'Galaxy S24 Plus', 'Galaxy S24', 'Galaxy S23', 'Galaxy A55'],
        ['Đen', 'Xám', 'Tím'],
    ], '🛡️'),
    catalog_product('SP-1025', 'Ốp lưng chống nước IP68 iPhone', 'DM06', 450000, 3, [
        ['iPhone 16 Pro Max', 'iPhone 15 Pro Max', 'iPhone 14', 'iPhone 13', 'iPhone 12'],
        ['Đen', 'Xanh biển', 'Trong suốt'],
    ], '🌊'),
    catalog_product('SP-1026', 'Ốp lưng kiêm ví da iPhone', 'DM06', 590000, 4, [
        ['iPhone 16 Pro Max', 'iPhone 15 Pro Max', 'iPhone 14 Pro Max', 'iPhone 13 Pro Max', 'iPhone 12 Pro Max'],
        ['Nâu', 'Đen'],
    ], '🛡️'),
    catalog_product('SP-1027', 'Cường lực chống nhìn trộm iPhone', 'DM06', 190000, 10, [
        ['iPhone 16 Pro Max', 'iPhone 15 Pro Max', 'iPhone 14 Pro Max', 'iPhone 13 Pro Max', 'iPhone 12 Pro Max'],
        ['1 miếng', '2 miếng'],
    ], '✨'),
    catalog_product('SP-1028', 'Sạc nhanh GaN 65W', 'DM06', 590000, 8, [
        ['1 cổng USB-C', '2 cổng USB-C + 1 USB-A', '3 cổng USB-C'],
        ['Đen', 'Trắng'],
    ], '🔌'),
    catalog_product('SP-1029', 'Cáp USB-C to Lightning chính hãng', 'DM06', 250000, 12, [
        ['1m', '2m'],
        ['Trắng', 'Đen'],
    ], '🔗'),
    catalog_product('SP-1030', 'Pin sạc dự phòng 20.000mAh', 'DM06', 490000, 7, [
        ['10.000mAh', '20.000mAh'],
        ['Đen', 'Trắng', 'Xanh'],
    ], '🔋'),
    catalog_product('SP-1031', 'Giá đỡ điện thoại để bàn', 'DM06', 150000, 10, [
        ['Gập gọn', 'Đế đứng', 'Kẹp bàn'],
        ['Đen', 'Bạc'],
    ], '📐'),
    catalog_product('SP-1032', 'Túi chống nước điện thoại', 'DM06', 120000, 15, [
        ['6.7 inch', '7.2 inch'],
        ['Đen', 'Xanh'],
    ], '🌊'),
    catalog_product('SP-1033', 'Bàn phím cơ mini Bluetooth', 'DM06', 890000, 5, [
        ['65%', '75%'],
        ['Red Switch', 'Brown Switch', 'Blue Switch'],
    ], '⌨️'),
    catalog_product('SP-1034', 'Chuột không dây silent', 'DM06', 290000, 12, [
        ['Đen', 'Trắng', 'Hồng'],
    ], '🖱️'),
    catalog_product('SP-1035', 'Bộ vệ sinh laptop và điện thoại', 'DM06', 150000, 20, [
        ['Bộ 8 món', 'Bộ 12 món'],
    ], '🧽'),

    /* ---------- Điện thoại ---------- */
    catalog_product('SP-1036', 'iPhone 16 Pro Max', 'DM01', 34990000, 2, [
        ['256GB', '512GB', '1TB'],
        ['Titan Sa Mạc', 'Titan Đen', 'Titan Trắng'],
    ], '📱'),
    catalog_product('SP-1037', 'iPhone 16', 'DM01', 21990000, 3, [
        ['128GB', '256GB'],
        ['Đen', 'Trắng', 'Hồng', 'Xanh Lưu Ly'],
    ], '📱'),
    catalog_product('SP-1038', 'Samsung Galaxy S25 Ultra', 'DM01', 30990000, 2, [
        ['256GB', '512GB', '1TB'],
        ['Titan Đen', 'Titan Bạc', 'Xanh Titan'],
    ], '📱'),
    catalog_product('SP-1039', 'Samsung Galaxy A56 5G', 'DM01', 9990000, 5, [
        ['128GB', '256GB'],
        ['Đen', 'Xanh', 'Hồng'],
    ], '📱'),
    catalog_product('SP-1040', 'Xiaomi Redmi Note 14 Pro', 'DM01', 6990000, 4, [
        ['128GB', '256GB', '512GB'],
        ['Đen', 'Xanh', 'Tím'],
    ], '📱'),
    catalog_product('SP-1041', 'OPPO Reno 12 5G', 'DM01', 12990000, 4, [
        ['256GB', '512GB'],
        ['Đen', 'Xanh'],
    ], '📱'),
    catalog_product('SP-1042', 'Google Pixel 9 Pro', 'DM01', 23990000, 2, [
        ['128GB', '256GB', '512GB'],
        ['Obsidian', 'Porcelain', 'Xanh Winter'],
    ], '📱'),
    catalog_product('SP-1043', 'realme 13 Pro+ 5G', 'DM01', 11990000, 3, [
        ['256GB', '512GB'],
        ['Vàng', 'Xanh', 'Đen'],
    ], '📱'),
    catalog_product('SP-1044', 'Nothing Phone (3a)', 'DM01', 7990000, 5, [
        ['128GB', '256GB'],
        ['Đen', 'Trắng'],
    ], '📱'),
    catalog_product('SP-1045', 'Sony Xperia 1 VI', 'DM01', 29990000, 2, [
        ['256GB', '512GB'],
        ['Đen', 'Xanh'],
    ], '📱'),

    /* ---------- Laptop ---------- */
    catalog_product('SP-1046', 'MacBook Pro 14 M4', 'DM02', 39990000, 3, [
        ['16GB/512GB', '24GB/1TB'],
    ], '💻'),
    catalog_product('SP-1047', 'Lenovo ThinkPad X1 Carbon Gen 12', 'DM02', 32990000, 3, [
        ['16GB/512GB', '32GB/1TB'],
    ], '💻'),
    catalog_product('SP-1048', 'HP Spectre x360 14', 'DM02', 35990000, 2, [
        ['16GB/1TB', '32GB/2TB'],
    ], '💻'),
    catalog_product('SP-1049', 'Acer Swift Go 14', 'DM02', 18990000, 5, [
        ['16GB/512GB', '16GB/1TB'],
    ], '💻'),
    catalog_product('SP-1050', 'MSI Katana 15', 'DM02', 24990000, 4, [
        ['16GB/512GB RTX 4050', '16GB/1TB RTX 4060'],
    ], '💻'),
    catalog_product('SP-1051', 'ASUS Zenbook 14 OLED', 'DM02', 26990000, 3, [
        ['16GB/512GB', '32GB/1TB'],
    ], '💻'),
    catalog_product('SP-1052', 'Dell Inspiron 14', 'DM02', 15990000, 6, [
        ['8GB/512GB', '16GB/1TB'],
    ], '💻'),
    catalog_product('SP-1053', 'LG Gram 16', 'DM02', 28990000, 2, [
        ['16GB/512GB', '32GB/1TB'],
    ], '💻'),

    /* ---------- Máy tính bảng ---------- */
    catalog_product('SP-1054', 'iPad Pro 13 M4', 'DM03', 29990000, 2, [
        ['256GB WiFi', '512GB WiFi', '1TB WiFi'],
    ], '📟'),
    catalog_product('SP-1055', 'iPad mini 7', 'DM03', 14990000, 4, [
        ['128GB WiFi', '256GB Cellular'],
    ], '📟'),
    catalog_product('SP-1056', 'Xiaomi Pad 7 Pro', 'DM03', 8990000, 5, [
        ['8GB/256GB', '12GB/512GB'],
    ], '📟'),
    catalog_product('SP-1057', 'Lenovo Tab Plus', 'DM03', 5990000, 6, [
        ['8GB/128GB', '8GB/256GB'],
    ], '📟'),
    catalog_product('SP-1058', 'Samsung Galaxy Tab S10 FE', 'DM03', 12990000, 4, [
        ['128GB WiFi', '256GB WiFi'],
    ], '📟'),

    /* ---------- Âm thanh ---------- */
    catalog_product('SP-1059', 'AirPods 4', 'DM04', 3990000, 8, [
        ['Bản không chống ồn', 'Bản chống ồn chủ động'],
    ], '🎧'),
    catalog_product('SP-1060', 'Samsung Galaxy Buds3 Pro', 'DM04', 4990000, 7, [
        ['Đen', 'Trắng'],
    ], '🎧'),
    catalog_product('SP-1061', 'Marshall Emberton III', 'DM04', 3990000, 6, [
        ['Đen', 'Đồng'],
    ], '🔊'),
    catalog_product('SP-1062', 'JBL Tune 770NC', 'DM04', 2490000, 7, [
        ['Đen', 'Trắng', 'Xanh'],
    ], '🎧'),
    catalog_product('SP-1063', 'Sony WF-1000XM5', 'DM04', 5990000, 5, [
        ['Đen', 'Bạc'],
    ], '🎧'),
    catalog_product('SP-1064', 'Loa Soundbar Xiaomi 3.1ch', 'DM04', 2990000, 4, [
        ['3.1ch / Đen', '5.1ch / Đen'],
    ], '🔊'),

    /* ---------- Đồng hồ thông minh ---------- */
    catalog_product('SP-1065', 'Apple Watch Ultra 2', 'DM05', 21990000, 3, [
        ['49mm'],
        ['Dây Alpine Đen', 'Dây Ocean Xanh', 'Dây Trail Cam'],
    ], '⌚'),
    catalog_product('SP-1066', 'Samsung Galaxy Watch7', 'DM05', 6990000, 4, [
        ['40mm', '44mm'],
        ['Bluetooth', 'LTE'],
    ], '⌚'),
    catalog_product('SP-1067', 'Xiaomi Smart Band 9', 'DM05', 890000, 12, [
        ['Đen', 'Bạc', 'Hồng'],
    ], '⌚'),
    catalog_product('SP-1068', 'Garmin Venu 3', 'DM05', 8990000, 3, [
        ['41mm', '45mm'],
        ['Đen', 'Trắng'],
    ], '⌚'),

    /* ---------- Gia dụng ---------- */
    catalog_product('SP-1069', 'Nồi chiên không dầu 6L', 'DM07', 1890000, 8, [
        ['6L', '8L'],
    ], '🍳'),
    catalog_product('SP-1070', 'Máy xay sinh tố đa năng', 'DM07', 990000, 10, [
        ['1.5L', '2L'],
    ], '🥤'),
    catalog_product('SP-1071', 'Robot hút bụi lau nhà', 'DM07', 5990000, 4, [
        ['Trắng', 'Đen'],
    ], '🤖'),
    catalog_product('SP-1072', 'Bếp từ đôi', 'DM07', 2990000, 5, [
        ['2 vùng nấu', '3 vùng nấu'],
    ], '🔥'),

    /* ---------- Thời trang ---------- */
    catalog_product('SP-1073', 'Áo hoodie unisex', 'DM08', 390000, 5, [
        ['S', 'M', 'L', 'XL', 'XXL'],
        ['Đen', 'Xám', 'Be'],
    ], '🧥'),
    catalog_product('SP-1074', 'Quần jogger thể thao', 'DM08', 290000, 6, [
        ['S', 'M', 'L', 'XL'],
        ['Đen', 'Xanh'],
    ], '👖'),
    catalog_product('SP-1075', 'Giày sneaker nam', 'DM08', 790000, 4, [
        ['39', '40', '41', '42', '43'],
        ['Trắng', 'Đen'],
    ], '👟'),
    catalog_product('SP-1076', 'Balo laptop chống nước', 'DM08', 490000, 8, [
        ['15.6 inch', '17.3 inch'],
        ['Đen', 'Xám'],
    ], '🎒'),
    catalog_product('SP-1077', 'Mũ lưỡi trai thêu', 'DM08', 150000, 12, [
        ['Đen', 'Trắng', 'Be'],
    ], '🧢'),
];

return ['products' => $catalogProducts];
