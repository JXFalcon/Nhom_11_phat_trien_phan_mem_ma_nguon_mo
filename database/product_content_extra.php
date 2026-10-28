<?php
/**
 * Nội dung mẫu dùng chung cho database/generate_seed.php và migration.
 * Sinh mô tả, chi tiết và đánh giá giả lập theo từng sản phẩm.
 */
declare(strict_types=1);

/**
 * @param array{0:string,1:string,2:string,3:int|float,4:int,5:string,6?:string,7?:string} $product
 *        [sku, name, category_code, price, stock, variants, emoji, images]
 */
function of_content_rng(string $seed): callable
{
    $state = abs((int)crc32($seed));
    return static function () use (&$state): float {
        $state = (int)((($state * 1664525) + 1013904223) % 4294967296);
        return $state / 4294967296;
    };
}

function of_content_pick(callable $rng, array $items): mixed
{
    return $items[(int)floor($rng() * count($items))] ?? null;
}

function of_content_variants(string $variants): array
{
    return array_values(array_filter(
        array_map('trim', preg_split('/;+/u', $variants) ?: []),
        static fn(string $v): bool => $v !== ''
    ));
}

function of_content_category_name(string $code): string
{
    return [
        'DM01' => 'Điện thoại',
        'DM02' => 'Laptop',
        'DM03' => 'Máy tính bảng',
        'DM04' => 'Âm thanh',
        'DM05' => 'Đồng hồ thông minh',
        'DM06' => 'Phụ kiện',
        'DM07' => 'Gia dụng',
        'DM08' => 'Thời trang',
    ][$code] ?? 'Sản phẩm';
}

function of_product_content_description(array $p): string
{
    [$sku, $name, $cat, $price, $stock, $variants] = $p;
    $category = of_content_category_name($cat);
    $variantList = implode(', ', of_content_variants($variants));
    $variantText = $variantList !== '' ? 'Các phân loại hiện có: ' . $variantList . '.' : '';

    $intro = match ($cat) {
        'DM01' => $name . ' là mẫu smartphone được nhiều khách hàng lựa chọn nhờ thiết kế hiện đại, hiệu năng ổn định và thời lượng pin đáp ứng tốt nhu cầu học tập, làm việc lẫn giải trí hằng ngày.',
        'DM02' => $name . ' là mẫu laptop phù hợp cho học tập, văn phòng và sáng tạo nội dung. Máy có thiết kế gọn gàng, bàn phím êm và hiệu năng đủ đáp ứng các tác vụ thường ngày.',
        'DM03' => $name . ' mang lại trải nghiệm giải trí và học tập thoải mái với màn hình rộng, âm thanh rõ và thời lượng pin ổn định trong nhiều giờ liên tục.',
        'DM04' => $name . ' được thiết kế để nâng cao trải nghiệm nghe nhạc, xem phim và gọi điện. Âm thanh chi tiết, kết nối nhanh và thiết kế thoải mái khi sử dụng lâu.',
        'DM05' => $name . ' là thiết bị đeo thông minh giúp theo dõi sức khỏe, luyện tập và nhận thông báo tiện lợi. Thiết kế hiện đại, dễ phối đồ và có nhiều tiện ích hằng ngày.',
        'DM06' => $name . ' là phụ kiện được hoàn thiện tỉ mỉ, tương thích tốt với nhiều thiết bị và giúp việc sử dụng hằng ngày trở nên thuận tiện hơn.',
        'DM07' => $name . ' là sản phẩm gia dụng thiết thực, dễ sử dụng và phù hợp với nhu cầu sinh hoạt hằng ngày của nhiều gia đình.',
        'DM08' => $name . ' được làm từ chất liệu thoải mái, form dáng dễ mặc và phù hợp với nhiều phong cách khác nhau từ năng động đến thanh lịch.',
        default => $name . ' là sản phẩm chính hãng được lựa chọn nhiều tại Shopee.',
    };

    $usage = match ($cat) {
        'DM01' => "Màn hình sắc nét, camera chụp ảnh tốt trong nhiều điều kiện ánh sáng. Hệ điều hành mượt, dung lượng bộ nhớ đủ dùng cho công việc và giải trí. Máy hỗ trợ sạc nhanh, bảo mật vân tay/khuôn mặt và nhiều tính năng hiện đại.",
        'DM02' => "Máy khởi động nhanh, xử lý đa nhiệm tốt và đáp ứng các phần mềm văn phòng, học online, thiết kế cơ bản. Màn hình hiển thị rõ, bàn phím có hành trình phù hợp để gõ lâu. Hệ thống tản nhiệt hoạt động ổn định.",
        'DM03' => "Màn hình lớn, màu sắc dễ chịu, phù hợp đọc sách, ghi chú và xem phim. Loa ngoài rõ, kết nối Wi-Fi ổn định. Máy mỏng nhẹ, dễ mang theo khi đi học hoặc đi làm.",
        'DM04' => "Âm bass chắc, âm treble rõ và không bị rè ở âm lượng lớn. Kết nối Bluetooth nhanh, pin dùng được nhiều giờ. Thiết kế nhẹ, đeo thoải mái trong thời gian dài.",
        'DM05' => "Theo dõi nhịp tim, số bước chân, giấc ngủ và nhiều bài tập thể dục. Nhận thông báo từ điện thoại tiện lợi. Màn hình sáng rõ, dây đeo mềm, phù hợp sử dụng cả ngày.",
        'DM06' => "Sản phẩm hoàn thiện chắc chắn, dễ lắp đặt và sử dụng. Thiết kế gọn gàng, phù hợp với nhiều thiết bị. Đóng gói cẩn thận, phù hợp làm quà tặng hoặc sử dụng hằng ngày.",
        'DM07' => "Thao tác đơn giản, dễ vệ sinh và tiết kiệm thời gian nấu nướng. Thiết kế an toàn, phù hợp với không gian bếp hiện đại. Công suất ổn định, đáp ứng nhu cầu sinh hoạt hằng ngày.",
        'DM08' => "Chất liệu mềm mại, thấm hút tốt và tạo cảm giác thoải mái khi mặc. Đường may chắc chắn, form dáng dễ phối với nhiều trang phục. Phù hợp mặc hằng ngày, đi học, đi làm hoặc đi chơi.",
        default => "Sản phẩm được kiểm tra kỹ trước khi giao, đóng gói cẩn thận và phù hợp sử dụng hằng ngày.",
    };

    $commit = "Shop cam kết hàng đúng mô tả, hỗ trợ đổi trả theo chính sách của sàn và tư vấn nhiệt tình trước khi mua. " . $variantText;

    return $intro . "\n\n" . $usage . "\n\n" . $commit;
}

function of_product_content_specs(array $p): array
{
    [$sku, $name, $cat, $price, $stock, $variants] = $p;
    $category = of_content_category_name($cat);
    $variantList = implode(', ', of_content_variants($variants));

    $specs = [
        'Danh mục' => $category,
        'Mã sản phẩm' => $sku,
        'Thương hiệu' => 'Shopee Mall',
        'Xuất xứ' => match ($cat) {
            'DM01', 'DM02', 'DM03' => 'Trung Quốc',
            'DM04', 'DM05', 'DM06' => 'Trung Quốc',
            'DM07' => 'Việt Nam',
            'DM08' => 'Việt Nam',
            default => 'Đang cập nhật',
        },
        'Bảo hành' => in_array($cat, ['DM01', 'DM02', 'DM03', 'DM04', 'DM05', 'DM06', 'DM07'], true) ? '12 tháng' : 'Đổi trả trong 7 ngày',
        'Tình trạng' => (int)$stock > 0 ? 'Còn hàng' : 'Hết hàng',
        'Phân loại' => $variantList !== '' ? $variantList : 'Tiêu chuẩn',
    ];

    $extra = match ($cat) {
        'DM01' => [
            'Màn hình' => '6.1 inch - 6.8 inch, tần số quét cao',
            'Camera' => 'Camera chính 50MP, quay video 4K',
            'Pin' => 'Dung lượng lớn, hỗ trợ sạc nhanh',
            'Bộ nhớ' => '256GB - 1TB',
        ],
        'DM02' => [
            'CPU' => 'Intel Core / Apple Silicon',
            'RAM' => '8GB - 32GB',
            'Ổ cứng' => 'SSD 256GB - 1TB',
            'Màn hình' => '13 inch - 16 inch, độ phân giải cao',
        ],
        'DM03' => [
            'Màn hình' => '10 inch - 13 inch',
            'Bộ nhớ' => '64GB - 256GB',
            'Pin' => 'Dùng liên tục 8 - 12 giờ',
            'Kết nối' => 'Wi-Fi, Bluetooth, USB-C',
        ],
        'DM04' => [
            'Loại' => 'Tai nghe / loa Bluetooth',
            'Kết nối' => 'Bluetooth 5.0 trở lên',
            'Thời lượng pin' => '6 - 30 giờ',
            'Chống nước' => 'IPX4 - IPX7',
        ],
        'DM05' => [
            'Kích thước' => '41mm - 47mm',
            'Màn hình' => 'AMOLED, hiển thị sắc nét',
            'Pin' => 'Sử dụng 1 - 7 ngày',
            'Chống nước' => '5ATM / IP68',
        ],
        'DM06' => [
            'Loại phụ kiện' => 'Phụ kiện công nghệ',
            'Tương thích' => 'Nhiều dòng thiết bị phổ biến',
            'Chất liệu' => 'Nhựa / kim loại / da tổng hợp',
            'Bảo hành' => '6 - 12 tháng',
        ],
        'DM07' => [
            'Dung tích' => '1.8L - 23L',
            'Công suất' => '700W - 1500W',
            'Chất liệu' => 'Inox / thủy tinh / nhựa an toàn',
            'Bảo hành' => '12 tháng',
        ],
        'DM08' => [
            'Chất liệu' => 'Cotton / polyester / vải tổng hợp',
            'Form' => 'Regular fit',
            'Màu sắc' => $variantList !== '' ? $variantList : 'Nhiều màu',
            'Hướng dẫn giặt' => 'Giặt máy ở nhiệt độ thấp, không dùng chất tẩy mạnh',
        ],
        default => [
            'Chất liệu' => 'Đang cập nhật',
            'Bảo hành' => 'Theo chính sách shop',
        ],
    };

    return array_merge($specs, $extra);
}

function of_product_content_specs_text(array $specs): string
{
    $lines = [];
    foreach ($specs as $key => $value) {
        $key = trim((string)$key);
        $value = trim((string)$value);
        if ($key !== '' && $value !== '') {
            $lines[] = $key . ': ' . $value;
        }
    }
    return implode("\n", $lines);
}

function of_product_content_reviews(array $p, int $count = 60): array
{
    [$sku, $name, $cat, $price, $stock, $variants] = $p;
    $imagesRaw = (string)($p[7] ?? '');
    $productImages = array_values(array_filter(
        array_map('trim', preg_split('/\R+/u', $imagesRaw) ?: []),
        static fn(string $u): bool => $u !== ''
    ));
    $variantList = of_content_variants($variants);
    $rng = of_content_rng('reviews|' . $sku);

    $nameParts = [
        'ngoc', 'minh', 'thu', 'linh', 'quan', 'hoa', 'nam', 'trang', 'phuong', 'anh',
        'tuan', 'mai', 'vy', 'khanh', 'huy', 'nhi', 'bao', 'yen', 'duy', 'hanh',
    ];
    $suffixes = ['', '***', '123', '***', '88', '***', '2102', '***', '09', '***'];

    $fiveStar = [
        'Sản phẩm đúng như mô tả, giao hàng nhanh, đóng gói cẩn thận.',
        'Chất lượng tốt hơn mong đợi, sẽ ủng hộ shop tiếp.',
        'Hàng đẹp, đóng gói kỹ, shipper thân thiện.',
        'Mua lần thứ hai rồi, vẫn rất hài lòng.',
        'Giá tốt, hàng chính hãng, dùng ổn định.',
        'Màu ngoài đời đẹp hơn ảnh, form chuẩn.',
        'Shop tư vấn nhiệt tình, giao hàng đúng hẹn.',
    ];
    $fourStar = [
        'Hàng ổn, giao hơi chậm nhưng chất lượng tốt.',
        'Sản phẩm đẹp, đóng gói chắc chắn, chỉ tiếc giao hơi lâu.',
        'Chất lượng ổn trong tầm giá, sẽ cân nhắc mua lại.',
        'Đúng mẫu, dùng tốt, trừ một điểm là đóng gói hơi đơn giản.',
    ];
    $threeStar = [
        'Hàng tạm ổn, đóng gói hơi sơ sài.',
        'Sản phẩm đúng mẫu nhưng giao hơi chậm.',
        'Chất lượng ở mức chấp nhận được trong tầm giá.',
    ];
    $twoStar = [
        'Hàng không giống mô tả lắm, shop cần cải thiện đóng gói.',
        'Giao hàng hơi lâu, sản phẩm chưa được như kỳ vọng.',
    ];
    $oneStar = [
        'Giao sai màu, shop hỗ trợ chậm.',
        'Sản phẩm bị trầy nhẹ, chưa hài lòng.',
        'Đóng gói sơ sài, hàng đến không được đẹp.',
    ];
    $byCategory = [
        'DM01' => 'Máy mượt, pin ổn, camera đẹp.',
        'DM02' => 'Máy chạy êm, màn hình đẹp, bàn phím gõ thích.',
        'DM03' => 'Màn hình đẹp, loa rõ, dùng học tập ổn.',
        'DM04' => 'Âm thanh trong, pin trâu, đeo thoải mái.',
        'DM05' => 'Đồng hồ đẹp, pin ổn, kết nối nhanh.',
        'DM06' => 'Phụ kiện chắc chắn, dùng tốt, đúng mô tả.',
        'DM07' => 'Máy chạy êm, dễ dùng, đun nấu ổn.',
        'DM08' => 'Vải đẹp, form chuẩn, mặc thoải mái.',
    ];

    $fixedRatings = [5, 5, 5, 4, 4, 3, 2, 1];
    $reviews = [];
    for ($i = 0; $i < $count; $i++) {
        if (isset($fixedRatings[$i])) {
            $rating = $fixedRatings[$i];
        } else {
            $roll = $rng();
            $rating = $roll < 0.86 ? 5 : ($roll < 0.94 ? 4 : ($roll < 0.97 ? 3 : ($roll < 0.99 ? 2 : 1)));
        }

        $pool = match ($rating) {
            5 => $fiveStar,
            4 => $fourStar,
            3 => $threeStar,
            2 => $twoStar,
            default => $oneStar,
        };
        $hasComment = $rating <= 3 || $rng() < 0.82;
        $comment = $hasComment ? (string)of_content_pick($rng, $pool) : '';
        if ($hasComment && isset($byCategory[$cat]) && $rng() < 0.35) {
            $comment .= ' ' . $byCategory[$cat];
        }

        $variant = null;
        if ($variantList && $rng() < 0.9) {
            $variant = (string)of_content_pick($rng, $variantList);
            if ($hasComment && $rng() < 0.18) {
                $comment .= ' Phân loại ' . $variant . ' đúng mô tả.';
            }
        }

        $reviewImages = null;
        if ($rng() < 0.25) {
            $imageCount = $rng() < 0.35 ? 2 : 1;
            $picked = [];
            for ($j = 0; $j < $imageCount; $j++) {
                if ($productImages) {
                    $picked[] = (string)of_content_pick($rng, $productImages);
                } else {
                    $picked[] = 'https://picsum.photos/seed/shopee-review-' . rawurlencode($sku) . '-' . ($i + 1) . '-' . ($j + 1) . '/480/480';
                }
            }
            $reviewImages = implode("\n", array_values(array_unique($picked)));
        }

        $reviewer = (string)of_content_pick($rng, $nameParts) . (string)of_content_pick($rng, $suffixes);
        $daysAgo = (int)floor($rng() * 540);
        $timestamp = time() - ($daysAgo * 86400) - ((int)floor($rng() * 86400));

        $reviews[] = [
            'reviewer_name' => $reviewer,
            'rating' => $rating,
            'variant' => $variant,
            'comment' => $comment !== '' ? $comment : null,
            'images' => $reviewImages,
            'helpful_count' => (int)floor($rng() * 31),
            'created_at' => date('Y-m-d H:i:s', $timestamp),
        ];
    }

    return $reviews;
}
