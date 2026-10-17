<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/JsonStore.php';
require_once dirname(__DIR__) . '/app/SalesService.php';

$path = tempnam(sys_get_temp_dir(), 'ban-hang-test-');
if ($path === false) {
    throw new RuntimeException('Không tạo được tệp thử nghiệm.');
}

$tests = 0;
$failed = 0;
$assert = static function (bool $condition, string $name) use (&$tests, &$failed): void {
    $tests++;
    if ($condition) {
        echo "PASS $name\n";
    } else {
        $failed++;
        echo "FAIL $name\n";
    }
};
$expectError = static function (callable $callback, int $status) use ($assert): void {
    try {
        $callback();
        $assert(false, 'expected-error-' . $status);
    } catch (ApiException $exception) {
        $assert($exception->status === $status, 'expected-error-' . $status);
    }
};
$stockFor = static function (array $products, string $id): ?int {
    foreach ($products as $product) {
        if ($product['id'] === $id) {
            return $product['stock'];
        }
    }
    return null;
};

try {
    $seed = [
        'products' => [
            ['id' => 'p1', 'name' => 'Áo xanh', 'sku' => 'AO-1', 'category' => 'Áo', 'price' => 100000, 'stock' => 5, 'low_stock' => 1],
            ['id' => 'p2', 'name' => 'Nón trắng', 'sku' => 'NON-2', 'category' => 'Nón', 'price' => 50000, 'stock' => 0, 'low_stock' => 2],
        ],
        'customers' => [
            ['id' => 'c1', 'name' => 'Khách Mẫu', 'phone' => '0912345678', 'email' => '', 'address' => ''],
        ],
        'orders' => [],
    ];
    $service = new SalesService(new JsonStore($path, $seed));

    $assert(count($service->products()) === 2, 'seed-is-initialized');
    $product = $service->saveProduct(['name' => 'Giày mới', 'sku' => 'giay-1', 'category' => 'Giày', 'price' => 200000, 'stock' => 3, 'low_stock' => 1]);
    $assert($product['sku'] === 'GIAY-1', 'product-sku-is-normalized');
    $expectError(
        static fn () => $service->saveProduct(['name' => 'Trùng mã', 'sku' => 'AO-1', 'category' => 'Áo', 'price' => 1, 'stock' => 1, 'low_stock' => 1]),
        409
    );

    $order = $service->createOrder(['customer_id' => 'c1', 'items' => [['product_id' => 'p1', 'quantity' => 2]]]);
    $assert($order['total'] === 200000 && $order['status'] === 'completed', 'order-total-and-status');
    $assert($stockFor($service->products(), 'p1') === 3, 'order-reserves-stock');
    $expectError(
        static fn () => $service->createOrder(['items' => [['product_id' => 'p2', 'quantity' => 1]]]),
        409
    );

    $service->updateOrderStatus($order['id'], 'cancelled');
    $assert($stockFor($service->products(), 'p1') === 5, 'cancel-restocks-items');
    $service->updateOrderStatus($order['id'], 'completed');
    $assert($stockFor($service->products(), 'p1') === 3, 'restore-order-reserves-items');
    $expectError(static fn () => $service->updateOrderStatus($order['id'], 'pending'), 400);
    $expectError(
        static fn () => $service->createOrder(['items' => [
            ['product_id' => 'p1', 'quantity' => 1],
            ['product_id' => 'p2', 'quantity' => 1],
        ]]),
        409
    );
    $assert($stockFor($service->products(), 'p1') === 3, 'failed-order-keeps-stock-unchanged');

    $service->saveCustomer(['name' => 'Khách mới', 'phone' => '0912345678']);
    $assert(count($service->customers()) === 2, 'customer-can-be-created');
    $service = new SalesService(new JsonStore($path, $seed));
    $assert(count($service->orders()) === 1 && count($service->customers()) === 2, 'records-persist-after-restart');
    $summary = $service->dashboard();
    $assert($summary['order_count'] === 1 && $summary['low_stock_count'] === 1, 'dashboard-counts-and-low-stock');
    $service->deleteProduct($product['id']);
    $assert(count($service->products()) === 2, 'product-can-be-deleted');
    $service->deleteCustomer('c1');
    $assert(count($service->customers()) === 1 && $service->orders()[0]['customer_name'] === 'Khách Mẫu', 'customer-history-is-preserved');

    echo "TESTS=$tests FAILED=$failed\n";
    exit($failed === 0 ? 0 : 1);
} finally {
    if (is_file($path)) {
        unlink($path);
    }
}
