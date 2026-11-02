<?php
/**
 * Test tích hợp 5 phép thử bắt buộc của Đề tài 19.
 * Chạy: php tests/integration/run.php
 * Yêu cầu: MySQL/MariaDB đang chạy, cấu hình ở config/database.php.
 */
declare(strict_types=1);

putenv('OF_DB_NAME=orderflow_test');
$_ENV['OF_DB_NAME'] = 'orderflow_test';
$_SERVER['OF_DB_NAME'] = 'orderflow_test';

$root = dirname(__DIR__, 2);
require __DIR__ . '/setup.php';

$cfg = require $root . '/config/database.php';
$dbName = (string)$cfg['dbname'];
if (!str_ends_with($dbName, '_test')) {
    fwrite(STDERR, "TỪ CHỐI: test chỉ chạy với database *_test (hiện tại: $dbName).\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $dbName, $cfg['charset']),
    $cfg['user'],
    $cfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

function start_test_server(int $port, string $root): array
{
    $cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $port, 'router.php'];
    $logFile = tempnam(sys_get_temp_dir(), 'of_test_server_');
    $descriptors = [['pipe', 'r'], ['file', $logFile, 'a'], ['file', $logFile, 'a']];
    $server = proc_open($cmd, $descriptors, $pipes, $root);
    if (!is_resource($server)) {
        return [null, 0, $logFile, false];
    }
    $pipes[0] = null;
    $serverPid = (int)(proc_get_status($server)['pid'] ?? 0);
    for ($i = 0; $i < 40; $i++) {
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.25);
        if ($fp) {
            fclose($fp);
            return [$server, $serverPid, $logFile, true];
        }
        usleep(125000);
    }
    return [$server, $serverPid, $logFile, false];
}

function stop_test_server($server, int $pid): void
{
    if (is_resource($server)) {
        proc_terminate($server);
    }
    if ($pid > 0 && DIRECTORY_SEPARATOR === '\\') {
        @exec('taskkill /F /T /PID ' . $pid . ' 2>NUL');
    }
}

$port = (int)(getenv('OF_TEST_PORT') ?: 8097);
$port2 = (int)(getenv('OF_TEST_PORT2') ?: ($port + 1));
$base = 'http://127.0.0.1:' . $port;
$base2 = 'http://127.0.0.1:' . $port2;

$servers = [];
foreach ([$port, $port2] as $p) {
    [$srv, $srvPid, $srvLog, $ok] = start_test_server($p, $root);
    if (!$ok) {
        fwrite(STDERR, "Server không mở cổng $p.\n");
        fwrite(STDERR, (string)@file_get_contents($srvLog));
        stop_test_server($srv, $srvPid);
        foreach ($servers as [$oldSrv, $oldPid, $oldLog]) {
            stop_test_server($oldSrv, $oldPid);
            @unlink($oldLog);
        }
        exit(1);
    }
    $servers[] = [$srv, $srvPid, $srvLog];
}

register_shutdown_function(static function () use ($servers): void {
    foreach ($servers as [$srv, $srvPid, $srvLog]) {
        stop_test_server($srv, $srvPid);
        @unlink($srvLog);
    }
});

$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;

function check(bool $ok, string $name, mixed $detail = null): void
{
    if ($ok) {
        $GLOBALS['pass']++;
        echo "  PASS  $name\n";
        return;
    }
    $GLOBALS['fail']++;
    echo "  FAIL  $name\n";
    if ($detail !== null) {
        echo '        ' . (is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE)) . "\n";
    }
}

function api(string $method, string $path, ?string $token = null, ?array $body = null): array
{
    global $base;
    $ch = curl_init($base . $path);
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return ['code' => 0, 'data' => null, 'raw' => 'curl: ' . $err];
    }
    return ['code' => $code, 'data' => json_decode($raw, true), 'raw' => $raw];
}

function api_multi(array $requests): array
{
    $mh = curl_multi_init();
    $handles = [];
    foreach ($requests as $key => $req) {
        $ch = curl_init($req['url']);
        $headers = ['Content-Type: application/json'];
        if (!empty($req['token'])) {
            $headers[] = 'Authorization: Bearer ' . $req['token'];
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $req['method'] ?? 'GET',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if (array_key_exists('body', $req) && $req['body'] !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req['body'], JSON_UNESCAPED_UNICODE));
        }
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }

    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 0.1);
        }
    } while ($running && $status === CURLM_OK);

    $out = [];
    foreach ($handles as $key => $ch) {
        $raw = curl_multi_getcontent($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        $out[$key] = [
            'code' => $code,
            'data' => is_string($raw) ? json_decode($raw, true) : null,
            'raw' => is_string($raw) ? $raw : 'curl: ' . $err,
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

function login(string $email): string
{
    $r = api('POST', '/api/auth/login.php', null, ['email' => $email, 'password' => '123456']);
    check($r['code'] === 200 && !empty($r['data']['data']['token']), 'Đăng nhập ' . $email, $r);
    return (string)($r['data']['data']['token'] ?? '');
}

function product_id(PDO $pdo, string $sku): int
{
    $st = $pdo->prepare('SELECT id FROM products WHERE sku = ?');
    $st->execute([$sku]);
    return (int)$st->fetchColumn();
}

function product_stock(PDO $pdo, int $id): int
{
    $st = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
    $st->execute([$id]);
    return (int)$st->fetchColumn();
}

function variant_id(PDO $pdo, int $productId, string $name): int
{
    $st = $pdo->prepare('SELECT id FROM product_variants WHERE product_id = ? AND name = ?');
    $st->execute([$productId, $name]);
    return (int)$st->fetchColumn();
}

function variant_stock(PDO $pdo, int $variantId): int
{
    $st = $pdo->prepare('SELECT stock FROM product_variants WHERE id = ?');
    $st->execute([$variantId]);
    return (int)$st->fetchColumn();
}

function order_payload(int $productId, int $qty, ?int $variantId = null, ?string $variantName = null): array
{
    return [
        'customer_name' => 'Khách kiểm thử',
        'phone' => '0900000002',
        'address' => '123 Đường Kiểm Thử, Quận 1, TP.HCM',
        'payment_method' => 'bank',
        'items' => [[
            'product_id' => $productId,
            'variant_id' => $variantId,
            'variant' => $variantName ?? '',
            'quantity' => $qty,
        ]],
    ];
}

function create_order(string $token, int $productId, int $qty, ?int $variantId = null, ?string $variantName = null): array
{
    return api('POST', '/api/orders/index.php', $token, order_payload($productId, $qty, $variantId, $variantName));
}

function create_order_items(string $token, array $items): array
{
    return api('POST', '/api/orders/index.php', $token, [
        'customer_name' => 'Khách kiểm thử',
        'phone' => '0900000002',
        'address' => '123 Đường Kiểm Thử, Quận 1, TP.HCM',
        'payment_method' => 'bank',
        'items' => $items,
    ]);
}

function order_detail(string $token, int $orderId): array
{
    $r = api('GET', '/api/orders/item.php?id=' . $orderId, $token);
    return is_array($r['data']['data'] ?? null) ? $r['data']['data'] : [];
}

function simulate_payment(string $token, int $paymentId, string $result, string $key): array
{
    return api('PATCH', '/api/payments/simulate.php', $token, [
        'id' => $paymentId,
        'result' => $result,
        'idempotency_key' => $key,
    ]);
}

echo "\n== Đăng nhập fixture ==\n";
$admin = login('admin@test.local');
$customer = login('customer@test.local');

echo "\n== 0. Ảnh sản phẩm (URL) ==\n";
$pImg = product_id($pdo, 'SP-1001');
$imageUrls = [
    'https://example.com/sp-1001-1.jpg',
    'https://example.com/sp-1001-2.jpg',
    'https://example.com/sp-1001-3.jpg',
    'https://example.com/sp-1001-4.jpg',
    'https://example.com/sp-1001-5.jpg',
];
$saveImages = api('PUT', '/api/products/item.php?id=' . $pImg, $admin, ['images' => $imageUrls]);
$publicItem = api('GET', '/api/products/item.php?id=' . $pImg . '&public=1');
$publicList = api('GET', '/api/products/index.php?public=1&q=SP-1001&per_page=5');
$listItem = $publicList['data']['data']['items'][0] ?? [];
check(
    $saveImages['code'] === 200 && count($publicItem['data']['data']['images'] ?? []) === 5,
    'Lưu 5 URL ảnh và trả đủ gallery',
    [$saveImages, $publicItem]
);
check(
    ($publicItem['data']['data']['cover_image'] ?? '') === $imageUrls[0]
        && ($listItem['cover_image'] ?? '') === $imageUrls[0],
    'Ảnh đầu tiên là ảnh bìa ở API chi tiết và danh sách',
    [$publicItem, $publicList]
);

echo "\n== 0b. Mô tả, chi tiết & đánh giá ==\n";
$contentItem = api('GET', '/api/products/item.php?id=' . $pImg . '&public=1');
$contentData = $contentItem['data']['data'] ?? [];
$summary = $contentData['review_summary'] ?? [];
check(
    $contentItem['code'] === 200
        && mb_strlen((string)($contentData['description'] ?? '')) > 80
        && str_contains((string)($contentData['specs'] ?? ''), 'Danh mục:')
        && (int)($summary['total'] ?? 0) >= 50,
    'Chi tiết sản phẩm trả mô tả, thông số và tổng quan đánh giá',
    $contentItem
);
$reviewList = api('GET', '/api/reviews/index.php?product_id=' . $pImg . '&per_page=5');
check(
    $reviewList['code'] === 200
        && count($reviewList['data']['data']['items'] ?? []) === 5
        && ($reviewList['data']['data']['total'] ?? 0) === ($summary['total'] ?? -1),
    'API đánh giá trả danh sách và tổng khớp chi tiết sản phẩm',
    $reviewList
);
$fiveStars = api('GET', '/api/reviews/index.php?product_id=' . $pImg . '&rating=5&per_page=50');
$fiveItems = $fiveStars['data']['data']['items'] ?? [];
check(
    $fiveStars['code'] === 200
        && (int)($fiveStars['data']['data']['total'] ?? 0) === (int)($summary['counts']['5'] ?? -1)
        && $fiveItems && count(array_filter($fiveItems, static fn(array $r): bool => (int)$r['rating'] === 5)) === count($fiveItems),
    'Lọc đánh giá theo số sao hoạt động đúng',
    $fiveStars
);
$withImages = api('GET', '/api/reviews/index.php?product_id=' . $pImg . '&has_image=1&per_page=50');
$withImageItems = $withImages['data']['data']['items'] ?? [];
check(
    $withImages['code'] === 200
        && (int)($withImages['data']['data']['total'] ?? 0) > 0
        && $withImageItems
        && count(array_filter($withImageItems, static fn(array $r): bool => (int)($r['image_count'] ?? 0) > 0)) === count($withImageItems),
    'Lọc đánh giá có hình ảnh hoạt động đúng',
    $withImages
);
$newDescription = 'Mô tả kiểm thử: sản phẩm dùng tốt, giao hàng nhanh và đóng gói cẩn thận.';
$newSpecs = "Chất liệu: Nhôm\nBảo hành: 12 tháng";
$saveContent = api('PUT', '/api/products/item.php?id=' . $pImg, $admin, [
    'description' => $newDescription,
    'specs' => $newSpecs,
]);
$contentAfter = api('GET', '/api/products/item.php?id=' . $pImg, $admin);
check(
    $saveContent['code'] === 200
        && ($contentAfter['data']['data']['description'] ?? '') === $newDescription
        && ($contentAfter['data']['data']['specs'] ?? '') === $newSpecs,
    'Admin lưu và đọc lại được mô tả/chi tiết sản phẩm',
    [$saveContent, $contentAfter]
);

echo "\n== 1. Tranh hàng cuối + biến thể ==\n";
$p1 = product_id($pdo, 'SP-901');
$a = create_order($customer, $p1, 1);
$b = create_order($customer, $p1, 1);
$successCount = (int)($a['code'] === 201) + (int)($b['code'] === 201);
check($successCount === 1, 'Chỉ một đơn giữ được sản phẩm cuối', ['a' => $a, 'b' => $b]);
check(product_stock($pdo, $p1) === 1, 'Giữ hàng pending không trừ tồn kho');
$order1 = (int)($a['code'] === 201 ? $a['data']['data']['id'] : $b['data']['data']['id']);
$confirm1 = api('PATCH', '/api/orders/status.php', $admin, ['id' => $order1, 'to_status' => 'da_xac_nhan']);
check($confirm1['code'] === 200, 'Xác nhận đơn tranh hàng thành công', $confirm1);
check(product_stock($pdo, $p1) === 0, 'Xác nhận mới trừ tồn kho thật');
$c = create_order($customer, $p1, 1);
check($c['code'] === 409, 'Không thể đặt thêm khi tồn đã hết', $c);

$p6 = product_id($pdo, 'SP-906');
$vR = variant_id($pdo, $p6, 'Đỏ');
$vB = variant_id($pdo, $p6, 'Xanh');
$vr1 = create_order($customer, $p6, 1, $vR, 'Đỏ');
$vr2 = create_order($customer, $p6, 1, $vR, 'Đỏ');
$vb = create_order($customer, $p6, 1, $vB, 'Xanh');
check($vr1['code'] === 201 && $vr2['code'] === 409 && $vb['code'] === 201,
    'Tồn kho và giá được kiểm tra riêng theo biến thể', ['r1' => $vr1, 'r2' => $vr2, 'b' => $vb]);
check(variant_stock($pdo, $vR) === 1 && variant_stock($pdo, $vB) === 1, 'Pending giữ hàng không trừ tồn biến thể');
check((float)$vr1['data']['data']['total_amount'] === 110.0, 'Giá biến thể Đỏ được snapshot đúng');
check((float)$vb['data']['data']['total_amount'] === 120.0, 'Giá biến thể Xanh được snapshot đúng');

echo "\n== 1b. Tranh hàng cuối thật sự song song (2 server) ==\n";
$p7 = product_id($pdo, 'SP-907');
$parallel = api_multi([
    'a' => [
        'url' => $base . '/api/orders/index.php',
        'method' => 'POST',
        'token' => $customer,
        'body' => order_payload($p7, 1),
    ],
    'b' => [
        'url' => $base2 . '/api/orders/index.php',
        'method' => 'POST',
        'token' => $customer,
        'body' => order_payload($p7, 1),
    ],
]);
$parallelCodes = [(int)$parallel['a']['code'], (int)$parallel['b']['code']];
sort($parallelCodes);
check($parallelCodes === [201, 409], 'Hai request song song: đúng một đơn giữ được hàng', $parallel);
check(product_stock($pdo, $p7) === 1, 'Tranh hàng song song không trừ tồn khi còn pending');
$parallelRes = (int)$pdo->query(
    'SELECT COUNT(*) FROM reservations WHERE product_id = ' . $p7 . " AND status = 'pending'"
)->fetchColumn();
check($parallelRes === 1, 'Chỉ một reservation pending được tạo');

echo "\n== 2. Gửi lặp thanh toán và hủy đơn ==\n";
$p2 = product_id($pdo, 'SP-902');
$o2 = create_order($customer, $p2, 2);
check($o2['code'] === 201, 'Tạo đơn cho test thanh toán', $o2);
$id2 = (int)$o2['data']['data']['id'];
$d2 = order_detail($admin, $id2);
$pay2 = (int)($d2['payments'][0]['id'] ?? 0);
$key2 = 'IT-DUP-' . $id2;
$r21 = simulate_payment($admin, $pay2, 'thanh_cong', $key2);
$r22 = simulate_payment($admin, $pay2, 'thanh_cong', $key2);
$r23 = simulate_payment($admin, $pay2, 'thanh_cong', $key2 . '-B');
check($r21['code'] === 200 && ($r21['data']['data']['status'] ?? '') === 'thanh_cong', 'Thanh toán lần đầu thành công', $r21);
check($r22['code'] === 200 && !empty($r22['data']['data']['idempotent_replay']), 'Gửi lặp cùng key không xử lý lại', $r22);
check($r23['code'] === 200 && !empty($r23['data']['data']['already_settled']), 'Key mới trên giao dịch đã kết thúc bị bỏ qua', $r23);
check(product_stock($pdo, $p2) === 0, 'Tồn kho chỉ bị trừ một lần');
$shipCount = (int)$pdo->query('SELECT COUNT(*) FROM shipments WHERE order_id = ' . $id2)->fetchColumn();
check($shipCount === 1, 'Chỉ tạo một vận đơn');
$duplicateShip = false;
try {
    $pdo->exec(
        "INSERT INTO shipments (shipment_code, order_id, carrier, tracking_code, status)
         VALUES ('VC-DUP-" . $id2 . "', " . $id2 . ", 'Test', 'DUPTEST', 'cho_lay_hang')"
    );
} catch (PDOException $e) {
    $duplicateShip = true;
}
check($duplicateShip, 'Unique shipments.order_id chặn vận đơn trùng');
$cancel1 = api('PATCH', '/api/orders/status.php', $admin, ['id' => $id2, 'to_status' => 'da_huy', 'note' => 'Test hủy']);
$cancel2 = api('PATCH', '/api/orders/status.php', $admin, ['id' => $id2, 'to_status' => 'da_huy', 'note' => 'Test hủy lần 2']);
check($cancel1['code'] === 200, 'Hủy đơn thành công', $cancel1);
check($cancel2['code'] === 409, 'Hủy lặp bị từ chối', $cancel2);
check(product_stock($pdo, $p2) === 2, 'Hoàn kho đúng một lần');
$d2b = order_detail($admin, $id2);
check(($d2b['order']['stock_restored'] ?? 0) == 1, 'Cờ stock_restored = 1');

$o2b = create_order($customer, $p2, 1);
$id2b = (int)$o2b['data']['data']['id'];
$d2c = order_detail($admin, $id2b);
$pay2b = (int)$d2c['payments'][0]['id'];
$f1 = simulate_payment($admin, $pay2b, 'that_bai', 'IT-FAIL-' . $id2b);
$f2 = simulate_payment($admin, $pay2b, 'thanh_cong', 'IT-RETRY-' . $id2b);
check($f1['code'] === 200 && ($f1['data']['data']['status'] ?? '') === 'that_bai', 'Ghi nhận thanh toán thất bại', $f1);
check($f2['code'] === 200 && ($f2['data']['data']['status'] ?? '') === 'thanh_cong', 'Thử lại bằng key mới thành công', $f2);

echo "\n== 3. Đổi giá sau khi đặt ==\n";
$p3 = product_id($pdo, 'SP-903');
$o3 = create_order($customer, $p3, 2);
$id3 = (int)$o3['data']['data']['id'];
$d3 = order_detail($admin, $id3);
$updPrice = api('PUT', '/api/products/item.php?id=' . $p3, $admin, ['price' => 200]);
$d3b = order_detail($admin, $id3);
check((float)$d3['items'][0]['unit_price'] === 100.0, 'Đơn cũ lưu giá snapshot 100', $d3['items']);
check($updPrice['code'] === 200, 'Cập nhật giá sản phẩm thành công', $updPrice);
check((float)$d3b['items'][0]['unit_price'] === 100.0 && (float)$d3b['order']['total_amount'] === 200.0,
    'Đổi giá không làm thay đổi đơn đã đặt', $d3b['order']);
$o3b = create_order($customer, $p3, 1);
check((float)$o3b['data']['data']['total_amount'] === 200.0, 'Đơn mới dùng giá mới 200', $o3b);

echo "\n== 3b. Sửa sản phẩm và biến thể ==\n";
$catId3 = (int)$pdo->query('SELECT category_id FROM products WHERE id = ' . $p3)->fetchColumn();
$addVar = api('POST', '/api/products/index.php', $admin, [
    'name' => 'Sản phẩm test biến thể',
    'sku' => 'SP-950',
    'category_id' => $catId3,
    'price' => 150,
    'stock' => 2,
    'variants' => 'Mặc định',
    'emoji' => '📦',
    'status' => 'active',
    'variant_rows' => [['name' => 'Mặc định', 'sku' => 'SP-950-D', 'price' => 150, 'stock' => 2]],
]);
check($addVar['code'] === 201, 'Thêm sản phẩm có biến thể', $addVar);
$newVarPid = (int)($addVar['data']['data']['id'] ?? 0);
$itemVar = api('GET', '/api/products/item.php?id=' . $newVarPid, $admin);
$newVariantId = (int)($itemVar['data']['data']['variant_rows'][0]['id'] ?? 0);
$editVar = api('PUT', '/api/products/item.php?id=' . $newVarPid, $admin, [
    'name' => 'Sản phẩm test biến thể đã sửa',
    'sku' => 'SP-950',
    'category_id' => $catId3,
    'price' => 180,
    'stock' => 2,
    'variants' => 'Mặc định',
    'emoji' => '📦',
    'status' => 'active',
    'variant_rows' => [['id' => $newVariantId, 'name' => 'Mặc định', 'sku' => 'SP-950-D', 'price' => 180, 'stock' => 2]],
]);
$itemVar2 = api('GET', '/api/products/item.php?id=' . $newVarPid, $admin);
check(
    $editVar['code'] === 200
        && count($itemVar2['data']['data']['variant_rows'] ?? []) === 1
        && (int)($itemVar2['data']['data']['stock'] ?? -1) === 2,
    'Sửa sản phẩm giữ đúng biến thể, không tạo trùng',
    [$editVar, $itemVar2]
);

$addSimple = api('POST', '/api/products/index.php', $admin, [
    'name' => 'Sản phẩm test đơn giản',
    'sku' => 'SP-951',
    'category_id' => $catId3,
    'price' => 100,
    'stock' => 3,
    'variants' => '',
    'emoji' => '📦',
    'status' => 'active',
    'variant_rows' => [],
]);
$simplePid = (int)($addSimple['data']['data']['id'] ?? 0);
$itemSimple = api('GET', '/api/products/item.php?id=' . $simplePid, $admin);
check(
    $addSimple['code'] === 201 && (int)($itemSimple['data']['data']['stock'] ?? -1) === 3,
    'Thêm sản phẩm không biến thể giữ tồn kho',
    [$addSimple, $itemSimple]
);
$editSimple = api('PUT', '/api/products/item.php?id=' . $simplePid, $admin, [
    'name' => 'Sản phẩm test đơn giản đã sửa',
    'sku' => 'SP-951',
    'category_id' => $catId3,
    'price' => 100,
    'stock' => 4,
    'variants' => '',
    'emoji' => '📦',
    'status' => 'active',
    'variant_rows' => [],
]);
$itemSimple2 = api('GET', '/api/products/item.php?id=' . $simplePid, $admin);
check(
    $editSimple['code'] === 200 && (int)($itemSimple2['data']['data']['stock'] ?? -1) === 4,
    'Sửa sản phẩm không biến thể giữ tồn kho',
    [$editSimple, $itemSimple2]
);

echo "\n== 4. Trả một phần, đối chiếu số lượng và hoàn tiền ==\n";
$p4 = product_id($pdo, 'SP-904');
$o4 = create_order($customer, $p4, 5);
$id4 = (int)$o4['data']['data']['id'];
$d4 = order_detail($admin, $id4);
$pay4 = (int)$d4['payments'][0]['id'];
simulate_payment($admin, $pay4, 'thanh_cong', 'IT-PAY-' . $id4);
api('PATCH', '/api/orders/status.php', $admin, ['id' => $id4, 'to_status' => 'dang_giao']);
$deliver = api('PATCH', '/api/orders/status.php', $admin, ['id' => $id4, 'to_status' => 'da_giao']);
check($deliver['code'] === 200, 'Đơn chuyển sang đã giao', $deliver);
$orderItem4 = (int)($d4['items'][0]['id'] ?? 0);
$ret = api('POST', '/api/returns/index.php', $customer, [
    'order_id' => $id4,
    'order_item_id' => $orderItem4,
    'quantity' => 5,
    'reason' => 'Sản phẩm bị lỗi khi kiểm tra',
]);
check($ret['code'] === 201, 'Tạo yêu cầu trả tối đa 5/5', $ret);
$retId = (int)$ret['data']['data']['id'];
$retAccept = api('PATCH', '/api/returns/status.php', $admin, [
    'id' => $retId, 'to_status' => 'da_chap_nhan', 'quantity_accepted' => 2,
]);
$retDone = api('PATCH', '/api/returns/status.php', $admin, [
    'id' => $retId, 'to_status' => 'da_hoan_tat',
]);
check($retAccept['code'] === 200 && $retDone['code'] === 200, 'Chấp nhận và hoàn tất trả một phần', [$retAccept, $retDone]);
check((float)($retDone['data']['data']['refund_total'] ?? 0) === 200.0, 'Hoàn tiền phần trả = 2 × 100', $retDone);
check(product_stock($pdo, $p4) === 7, 'Nhập lại kho đúng 2 sản phẩm');
$refund4 = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM payments WHERE order_id = $id4 AND status = 'hoan_tien'"
)->fetchColumn();
check($refund4 === 200.0, 'Ghi nhận dòng hoàn tiền 200');

$retAdj = api('PATCH', '/api/returns/status.php', $admin, [
    'id' => $retId, 'to_status' => 'da_hoan_tat', 'quantity_accepted' => 5,
]);
check($retAdj['code'] === 200 && (float)($retAdj['data']['data']['refund_total'] ?? 0) === 500.0,
    'Điều chỉnh số lượng chấp nhận lên 5', $retAdj);
check(product_stock($pdo, $p4) === 10, 'Điều chỉnh nhập lại kho đủ 5');
$refund4b = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM payments WHERE order_id = $id4 AND status = 'hoan_tien'"
)->fetchColumn();
$paid4 = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM payments WHERE order_id = $id4 AND status = 'thanh_cong'"
)->fetchColumn();
check($refund4b === 500.0 && $refund4b <= $paid4, 'Tổng hoàn tiền không vượt tiền đã thanh toán');
$retOver = api('PATCH', '/api/returns/status.php', $admin, [
    'id' => $retId, 'to_status' => 'da_hoan_tat', 'quantity_accepted' => 6,
]);
check($retOver['code'] === 409, 'Từ chối trả vượt số lượng đã giao', $retOver);

$retSecond = api('POST', '/api/returns/index.php', $customer, [
    'order_id' => $id4,
    'order_item_id' => $orderItem4,
    'quantity' => 1,
    'reason' => 'Yêu cầu thứ hai cho cùng sản phẩm',
]);
check($retSecond['code'] === 409, 'Yêu cầu trả thứ hai vượt phần còn lại bị chặn', $retSecond);

$retItems4 = (int)$pdo->query(
    'SELECT COUNT(*) FROM return_items WHERE order_item_id = ' . $orderItem4
)->fetchColumn();
check($retItems4 === 1, 'Không tạo thêm return_item khi đã trả hết số lượng');

echo "\n== 4b. Trả nhiều dòng trong một yêu cầu ==\n";
$p8 = product_id($pdo, 'SP-908');
$p9 = product_id($pdo, 'SP-909');
$o4b = create_order_items($customer, [
    ['product_id' => $p8, 'quantity' => 2],
    ['product_id' => $p9, 'quantity' => 1],
]);
check($o4b['code'] === 201, 'Tạo đơn có 2 sản phẩm để trả nhiều dòng', $o4b);
$id4b = (int)$o4b['data']['data']['id'];
$d4b = order_detail($admin, $id4b);
$pay4b = (int)$d4b['payments'][0]['id'];
simulate_payment($admin, $pay4b, 'thanh_cong', 'IT-MULTI-' . $id4b);
api('PATCH', '/api/orders/status.php', $admin, ['id' => $id4b, 'to_status' => 'dang_giao']);
api('PATCH', '/api/orders/status.php', $admin, ['id' => $id4b, 'to_status' => 'da_giao']);
$itemA = null;
$itemB = null;
foreach ($d4b['items'] as $it) {
    if ((int)$it['product_id'] === $p8) {
        $itemA = $it;
    } elseif ((int)$it['product_id'] === $p9) {
        $itemB = $it;
    }
}
$retMulti = api('POST', '/api/returns/index.php', $customer, [
    'order_id' => $id4b,
    'reason' => 'Hai sản phẩm đều bị lỗi khi kiểm tra',
    'items' => [
        ['order_item_id' => (int)($itemA['id'] ?? 0), 'quantity' => 1],
        ['order_item_id' => (int)($itemB['id'] ?? 0), 'quantity' => 1],
    ],
]);
check($retMulti['code'] === 201 && (int)($retMulti['data']['data']['items_count'] ?? 0) === 2,
    'Tạo một yêu cầu trả cho nhiều dòng', $retMulti);
$retMultiId = (int)($retMulti['data']['data']['id'] ?? 0);
check((int)$pdo->query('SELECT COUNT(*) FROM return_items WHERE return_request_id = ' . $retMultiId)->fetchColumn() === 2,
    'Yêu cầu nhiều dòng có đủ 2 return_items');
$retMultiOver = api('POST', '/api/returns/index.php', $customer, [
    'order_id' => $id4b,
    'reason' => 'Yêu cầu vượt phần còn lại',
    'items' => [
        ['order_item_id' => (int)($itemA['id'] ?? 0), 'quantity' => 2],
    ],
]);
check($retMultiOver['code'] === 409, 'Yêu cầu nhiều dòng vẫn bị chặn khi vượt số lượng', $retMultiOver);

$multiLines = $pdo->query(
    'SELECT id, order_item_id FROM return_items WHERE return_request_id = ' . $retMultiId . ' ORDER BY order_item_id'
)->fetchAll();
$lineA = (int)($multiLines[0]['id'] ?? 0);
$lineB = (int)($multiLines[1]['id'] ?? 0);
$multiAccept = api('PATCH', '/api/returns/status.php', $admin, [
    'id' => $retMultiId,
    'to_status' => 'da_chap_nhan',
    'items' => [
        ['return_item_id' => $lineA, 'quantity_accepted' => 1],
        ['return_item_id' => $lineB, 'quantity_accepted' => 0],
    ],
]);
check($multiAccept['code'] === 200, 'Chấp nhận đổi trả theo từng dòng', $multiAccept);
$acceptedByLine = $pdo->query(
    'SELECT id, quantity_accepted FROM return_items WHERE return_request_id = ' . $retMultiId . ' ORDER BY order_item_id'
)->fetchAll();
check(
    (int)$acceptedByLine[0]['quantity_accepted'] === 1 && (int)$acceptedByLine[1]['quantity_accepted'] === 0,
    'Số lượng chấp nhận lưu đúng cho từng dòng',
    $acceptedByLine
);
$histMulti = (int)$pdo->query(
    "SELECT COUNT(*) FROM order_history WHERE order_id = $id4b AND note LIKE 'Chấp nhận đổi trả%'"
)->fetchColumn();
check($histMulti >= 1, 'Chấp nhận đổi trả ghi order_history');
$multiDone = api('PATCH', '/api/returns/status.php', $admin, [
    'id' => $retMultiId,
    'to_status' => 'da_hoan_tat',
    'items' => [
        ['return_item_id' => $lineA, 'quantity_accepted' => 1],
        ['return_item_id' => $lineB, 'quantity_accepted' => 0],
    ],
]);
check(
    $multiDone['code'] === 200 && (float)($multiDone['data']['data']['refund_total'] ?? 0) === 100.0,
    'Hoàn tất nhiều dòng chỉ hoàn dòng được chấp nhận',
    $multiDone
);

echo "\n== 5. Thanh toán muộn không hồi sinh đơn, không bán vượt tồn ==\n";
$p5 = product_id($pdo, 'SP-905');
$o5 = create_order($customer, $p5, 1);
$id5 = (int)$o5['data']['data']['id'];
$d5 = order_detail($admin, $id5);
$pay5 = (int)$d5['payments'][0]['id'];
$pdo->prepare('UPDATE orders SET reserved_until = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')->execute([$id5]);
$exp = api('POST', '/api/orders/expire.php', $admin, ['id' => $id5]);
check($exp['code'] === 200 && (int)($exp['data']['data']['expired_count'] ?? 0) === 1, 'Hết hạn giữ hàng', $exp);
$late = simulate_payment($admin, $pay5, 'thanh_cong', 'IT-LATE-' . $id5);
check($late['code'] === 200 && !empty($late['data']['data']['order_expired']), 'Thanh toán muộn bị từ chối', $late);
check(!empty($late['data']['data']['refund_created']) && (float)$late['data']['data']['refund_amount'] === 300.0,
    'Tự động hoàn tiền thanh toán muộn', $late);
$d5b = order_detail($admin, $id5);
check(($d5b['order']['status'] ?? '') === 'da_huy', 'Đơn vẫn ở trạng thái đã hủy');
check((int)($d5b['order']['stock_restored'] ?? 0) === 1, 'Không hoàn kho lần hai');
check(product_stock($pdo, $p5) === 1, 'Tồn kho vẫn đúng 1 sau thanh toán muộn');
$ship5 = (int)$pdo->query('SELECT COUNT(*) FROM shipments WHERE order_id = ' . $id5)->fetchColumn();
check($ship5 === 0, 'Không tạo vận đơn cho đơn đã hủy');
$lateEvents = (int)$pdo->query(
    "SELECT COUNT(*) FROM payment_events WHERE payment_id = $pay5 AND outcome = 'late_rejected_refund'"
)->fetchColumn();
check($lateEvents === 1, 'Có payment_event late_rejected_refund');

$o5b = create_order($customer, $p5, 1);
check($o5b['code'] === 201, 'Khách khác vẫn mua được hàng còn lại', $o5b);
$confirm5 = api('PATCH', '/api/orders/status.php', $admin, [
    'id' => (int)$o5b['data']['data']['id'], 'to_status' => 'da_xac_nhan',
]);
check($confirm5['code'] === 200 && product_stock($pdo, $p5) === 0, 'Xác nhận đơn mới trừ tồn, không vượt kho', $confirm5);

$rec = api('GET', '/api/reports/reconcile.php?limit=5', $admin);
check(
    $rec['code'] === 200
        && isset(
            $rec['data']['data']['inventory'],
            $rec['data']['data']['variants'],
            $rec['data']['data']['payment_events'],
            $rec['data']['data']['money'],
            $rec['data']['data']['reserves']
        ),
    'Báo cáo đối chiếu chạy được trên dữ liệu vừa phát sinh',
    $rec
);
check((int)($rec['data']['data']['variants']['checked'] ?? 0) >= 2, 'Đối chiếu có kiểm tra tồn biến thể');
check((int)($rec['data']['data']['payment_events']['accepted_events'] ?? 0) >= 1, 'Đối chiếu có nhật ký payment_events');

echo "\n== 6a. Đơn hết hạn không được xác nhận ==\n";
$p10 = product_id($pdo, 'SP-910');
$o10 = create_order($customer, $p10, 1);
check($o10['code'] === 201, 'Tạo đơn để test hết hạn', $o10);
$id10 = (int)$o10['data']['data']['id'];
$pdo->prepare('UPDATE orders SET reserved_until = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')->execute([$id10]);
$pdo->prepare('UPDATE reservations SET reserved_until = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE order_id = ?')->execute([$id10]);
$confirmExpired = api('PATCH', '/api/orders/status.php', $admin, ['id' => $id10, 'to_status' => 'da_xac_nhan']);
check($confirmExpired['code'] === 409, 'Không xác nhận được đơn đã hết hạn', $confirmExpired);
$res10 = (int)$pdo->query('SELECT id FROM reservations WHERE order_id = ' . $id10)->fetchColumn();
$confirmResExpired = api('PUT', '/api/reservations/item.php?id=' . $res10, $admin, ['status' => 'confirmed']);
check($confirmResExpired['code'] === 409, 'Không xác nhận được phiếu giữ hàng đã hết hạn', $confirmResExpired);
check(product_stock($pdo, $p10) === 1, 'Xác nhận hết hạn không trừ tồn kho');
$exp10 = api('POST', '/api/orders/expire.php', $admin, ['id' => $id10]);
check($exp10['code'] === 200 && (int)($exp10['data']['data']['expired_count'] ?? 0) === 1,
    'Xử lý hết hạn đơn sau khi bị chặn xác nhận', $exp10);

echo "\n== 6b. Idempotency key không dùng chéo giao dịch ==\n";
$p13 = product_id($pdo, 'SP-913');
$p14 = product_id($pdo, 'SP-914');
$oA = create_order($customer, $p13, 1);
$oB = create_order($customer, $p14, 1);
$idA = (int)$oA['data']['data']['id'];
$idB = (int)$oB['data']['data']['id'];
$payA = (int)(order_detail($admin, $idA)['payments'][0]['id'] ?? 0);
$payB = (int)(order_detail($admin, $idB)['payments'][0]['id'] ?? 0);
$crossKey = 'IT-CROSS-' . $idA . '-' . $idB;
$crossA = simulate_payment($admin, $payA, 'thanh_cong', $crossKey);
$crossB = simulate_payment($admin, $payB, 'thanh_cong', $crossKey);
check($crossA['code'] === 200, 'Key chéo dùng lần đầu cho giao dịch A', $crossA);
check($crossB['code'] === 409, 'Cùng key bị từ chối khi dùng cho giao dịch B', $crossB);
check((int)$pdo->query('SELECT COUNT(*) FROM payments WHERE order_id = ' . $idB . " AND status = 'thanh_cong'")->fetchColumn() === 0,
    'Giao dịch B không bị đánh dấu thanh toán bởi key chéo');
$cancelB = api('PATCH', '/api/orders/status.php', $admin, ['id' => $idB, 'to_status' => 'da_huy', 'note' => 'Dọn test key chéo']);
check($cancelB['code'] === 200, 'Dọn đơn B sau test key chéo', $cancelB);

echo "\n== 6c. COD được quyết toán khi giao hàng ==\n";
$p11 = product_id($pdo, 'SP-911');
$o11 = api('POST', '/api/orders/index.php', $customer, [
    'customer_name' => 'Khách kiểm thử',
    'phone' => '0900000002',
    'address' => '123 Đường Kiểm Thử, Quận 1, TP.HCM',
    'payment_method' => 'cod',
    'items' => [['product_id' => $p11, 'quantity' => 1]],
]);
check($o11['code'] === 201, 'Tạo đơn COD', $o11);
$id11 = (int)$o11['data']['data']['id'];
$pay11 = (int)(order_detail($admin, $id11)['payments'][0]['id'] ?? 0);
check((string)$pdo->query('SELECT status FROM payments WHERE id = ' . $pay11)->fetchColumn() === 'cho_xu_ly',
    'Payment COD ban đầu chờ xử lý');
$c11 = api('PATCH', '/api/orders/status.php', $admin, ['id' => $id11, 'to_status' => 'da_xac_nhan']);
$g11 = api('PATCH', '/api/orders/status.php', $admin, ['id' => $id11, 'to_status' => 'dang_giao']);
$dl11 = api('PATCH', '/api/orders/status.php', $admin, ['id' => $id11, 'to_status' => 'da_giao']);
check($c11['code'] === 200 && $g11['code'] === 200 && $dl11['code'] === 200, 'Đơn COD giao thành công', [$c11, $g11, $dl11]);
$pay11Row = $pdo->query('SELECT status, processed_at FROM payments WHERE id = ' . $pay11)->fetch();
check(
    ($pay11Row['status'] ?? '') === 'thanh_cong' && !empty($pay11Row['processed_at']),
    'COD chuyển thanh_cong khi đơn đã giao',
    $pay11Row
);
check((int)$pdo->query(
    "SELECT COUNT(*) FROM payment_events WHERE payment_id = $pay11 AND outcome = 'accepted'"
)->fetchColumn() === 1, 'COD có đúng một payment_event accepted');

echo "\n== 6d. Giỏ hàng dùng giá tại thời điểm đặt ==\n";
$p12 = product_id($pdo, 'SP-912');
$addCart = api('POST', '/api/carts/index.php', $customer, ['product_id' => $p12, 'quantity' => 1]);
check($addCart['code'] === 201, 'Thêm sản phẩm vào giỏ', $addCart);
$cartPrice = (float)$pdo->query(
    'SELECT ci.unit_price FROM cart_items ci JOIN carts c ON c.id = ci.cart_id
     WHERE ci.product_id = ' . $p12 . ' ORDER BY ci.id DESC LIMIT 1'
)->fetchColumn();
check($cartPrice === 100.0, 'Giá lưu trong giỏ là 100 tại thời điểm thêm');
$priceUp = api('PUT', '/api/products/item.php?id=' . $p12, $admin, ['price' => 175]);
check($priceUp['code'] === 200, 'Đổi giá sản phẩm sau khi thêm vào giỏ', $priceUp);
$checkout = api('POST', '/api/orders/index.php', $customer, [
    'from_cart' => true,
    'customer_name' => 'Khách kiểm thử',
    'phone' => '0900000002',
    'address' => '123 Đường Kiểm Thử, Quận 1, TP.HCM',
    'payment_method' => 'bank',
]);
check($checkout['code'] === 201, 'Đặt hàng từ giỏ thành công', $checkout);
$id12 = (int)($checkout['data']['data']['id'] ?? 0);
$d12 = order_detail($admin, $id12);
check(
    (float)($checkout['data']['data']['total_amount'] ?? 0) === 175.0
        && (float)($d12['items'][0]['unit_price'] ?? 0) === 175.0,
    'Đơn từ giỏ chốt giá hiện tại 175, không dùng giá cũ 100',
    [$checkout, $d12]
);

echo "\n== 6e. Reconcile phát hiện payment kết thúc thiếu event ==\n";
$pdo->prepare(
    "INSERT INTO payments (payment_code, order_id, amount, method, status, processed_at)
     VALUES ('GD-NOEVENT', ?, 1, 'bank', 'thanh_cong', NOW())"
)->execute([$id4]);
$recMissing = api('GET', '/api/reports/reconcile.php?limit=5', $admin);
check(
    $recMissing['code'] === 200
        && (int)($recMissing['data']['data']['payment_events']['terminal_payments_without_event'] ?? 0) >= 1,
    'Đối chiếu đếm được payment kết thúc thiếu event',
    $recMissing
);
check(($recMissing['data']['data']['payment_events']['ok'] ?? true) === false,
    'eventOk = false khi có payment kết thúc thiếu event', $recMissing);

echo "\n----------------------------------------\n";
echo 'Kết quả: ' . $GLOBALS['pass'] . ' PASS, ' . $GLOBALS['fail'] . " FAIL\n";
if ($GLOBALS['fail'] > 0) {
    exit(1);
}
echo "TẤT CẢ TEST TÍCH HỢP ĐỀU ĐẠT.\n";
