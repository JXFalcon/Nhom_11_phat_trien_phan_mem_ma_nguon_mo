<?php
declare(strict_types=1);

final class ApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}

final class SalesService
{
    public function __construct(private readonly JsonStore $store)
    {
    }

    public function dashboard(): array
    {
        return $this->store->read(function (array &$data): array {
            $today = date('Y-m-d');
            $revenueToday = 0;
            $revenueTotal = 0;
            $completedCount = 0;

            foreach ($data['orders'] as $order) {
                if ($order['status'] !== 'completed') {
                    continue;
                }

                $completedCount++;
                $revenueTotal += $order['total'];
                if (substr($order['created_at'], 0, 10) === $today) {
                    $revenueToday += $order['total'];
                }
            }

            $week = [];
            for ($offset = 6; $offset >= 0; $offset--) {
                $day = date('Y-m-d', strtotime("-$offset days"));
                $week[$day] = ['date' => $day, 'revenue' => 0];
            }

            foreach ($data['orders'] as $order) {
                $day = substr($order['created_at'], 0, 10);
                if ($order['status'] === 'completed' && isset($week[$day])) {
                    $week[$day]['revenue'] += $order['total'];
                }
            }

            $lowStock = array_values(array_filter(
                $data['products'],
                static fn (array $product): bool => $product['stock'] <= $product['low_stock']
            ));
            usort($lowStock, static fn (array $a, array $b): int => $a['stock'] <=> $b['stock']);

            return [
                'product_count' => count($data['products']),
                'customer_count' => count($data['customers']),
                'order_count' => $completedCount,
                'revenue_total' => $revenueTotal,
                'revenue_today' => $revenueToday,
                'low_stock_count' => count($lowStock),
                'low_stock' => array_slice($lowStock, 0, 5),
                'weekly_sales' => array_values($week),
                'recent_orders' => array_slice($this->sortOrders($data['orders']), 0, 6),
            ];
        });
    }

    public function products(): array
    {
        return $this->store->read(static function (array &$data): array {
            usort($data['products'], static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
            return array_values($data['products']);
        });
    }

    public function customers(): array
    {
        return $this->store->read(static function (array &$data): array {
            usort($data['customers'], static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
            return array_values($data['customers']);
        });
    }

    public function orders(): array
    {
        return $this->store->read(fn (array &$data): array => $this->sortOrders($data['orders']));
    }

    public function saveProduct(array $input, ?string $id = null): array
    {
        $name = $this->text($input['name'] ?? null, 'Tên sản phẩm', 120);
        $sku = strtoupper($this->text($input['sku'] ?? null, 'Mã sản phẩm', 40));
        $category = $this->text($input['category'] ?? null, 'Danh mục', 80);
        $price = $this->integer($input['price'] ?? null, 'Giá bán', 0, 2000000000);
        $stock = $this->integer($input['stock'] ?? null, 'Tồn kho', 0, 2000000000);
        $lowStock = $this->integer($input['low_stock'] ?? null, 'Ngưỡng tồn kho thấp', 0, 2000000000);

        return $this->store->update(function (array &$data) use ($id, $name, $sku, $category, $price, $stock, $lowStock): array {
            foreach ($data['products'] as $product) {
                if ($product['id'] !== $id && strcasecmp($product['sku'], $sku) === 0) {
                    throw new ApiException('Mã sản phẩm đã được sử dụng.', 409);
                }
            }

            $index = $id === null ? null : self::findIndex($data['products'], $id);
            if ($id !== null && $index === null) {
                throw new ApiException('Không tìm thấy sản phẩm.', 404);
            }

            $product = [
                'id' => $id ?? $this->newId(),
                'name' => $name,
                'sku' => $sku,
                'category' => $category,
                'price' => $price,
                'stock' => $stock,
                'low_stock' => $lowStock,
            ];

            if ($index === null) {
                $data['products'][] = $product;
            } else {
                $data['products'][$index] = $product;
            }

            return $product;
        });
    }

    public function deleteProduct(string $id): void
    {
        $this->store->update(static function (array &$data) use ($id): void {
            $index = self::findIndex($data['products'], $id);
            if ($index === null) {
                throw new ApiException('Không tìm thấy sản phẩm.', 404);
            }
            array_splice($data['products'], $index, 1);
        });
    }

    public function saveCustomer(array $input, ?string $id = null): array
    {
        $name = $this->text($input['name'] ?? null, 'Tên khách hàng', 120);
        $phone = $this->text($input['phone'] ?? null, 'Số điện thoại', 30);
        if (!preg_match('/^[0-9+().\-\s]{7,30}$/', $phone)) {
            throw new ApiException('Số điện thoại không hợp lệ.');
        }
        $email = $this->optionalText($input['email'] ?? null, 'Email', 160);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiException('Địa chỉ email không hợp lệ.');
        }
        $address = $this->optionalText($input['address'] ?? null, 'Địa chỉ', 240);

        return $this->store->update(function (array &$data) use ($id, $name, $phone, $email, $address): array {
            $index = $id === null ? null : self::findIndex($data['customers'], $id);
            if ($id !== null && $index === null) {
                throw new ApiException('Không tìm thấy khách hàng.', 404);
            }

            $customer = [
                'id' => $id ?? $this->newId(),
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
            ];

            if ($index === null) {
                $data['customers'][] = $customer;
            } else {
                $data['customers'][$index] = $customer;
            }

            return $customer;
        });
    }

    public function deleteCustomer(string $id): void
    {
        $this->store->update(static function (array &$data) use ($id): void {
            $index = self::findIndex($data['customers'], $id);
            if ($index === null) {
                throw new ApiException('Không tìm thấy khách hàng.', 404);
            }
            array_splice($data['customers'], $index, 1);
        });
    }

    public function createOrder(array $input): array
    {
        if (!isset($input['items']) || !is_array($input['items']) || $input['items'] === []) {
            throw new ApiException('Đơn hàng cần có ít nhất một sản phẩm.');
        }

        $requested = [];
        foreach ($input['items'] as $item) {
            if (!is_array($item) || !isset($item['product_id'])) {
                throw new ApiException('Thông tin sản phẩm trong đơn hàng không hợp lệ.');
            }
            $productId = $this->text($item['product_id'], 'Sản phẩm', 80);
            $quantity = $this->integer($item['quantity'] ?? null, 'Số lượng', 1, 100000);
            $requested[$productId] = ($requested[$productId] ?? 0) + $quantity;
        }

        $customerId = $input['customer_id'] ?? null;
        if ($customerId !== null && $customerId !== '') {
            $customerId = $this->text($customerId, 'Khách hàng', 80);
        } else {
            $customerId = null;
        }
        $customerName = $this->optionalText($input['customer_name'] ?? null, 'Tên khách lẻ', 120);
        $note = $this->optionalText($input['note'] ?? null, 'Ghi chú', 500);

        return $this->store->update(function (array &$data) use ($requested, $customerId, $customerName, $note): array {
            $customer = $customerId === null ? null : $this->findById($data['customers'], $customerId);
            if ($customerId !== null && $customer === null) {
                throw new ApiException('Không tìm thấy khách hàng.', 404);
            }

            $items = [];
            $total = 0;
            foreach ($requested as $productId => $quantity) {
                $index = self::findIndex($data['products'], $productId);
                if ($index === null) {
                    throw new ApiException('Một sản phẩm trong đơn hàng không còn tồn tại.', 404);
                }
                $product = $data['products'][$index];
                if ($product['stock'] < $quantity) {
                    throw new ApiException("Sản phẩm {$product['name']} chỉ còn {$product['stock']} sản phẩm.", 409);
                }

                $lineTotal = $product['price'] * $quantity;
                if (!is_int($lineTotal) || $total > 2000000000 - $lineTotal) {
                    throw new ApiException('Tổng giá trị đơn hàng vượt giới hạn cho phép.');
                }
                $total += $lineTotal;
                $product['stock'] -= $quantity;
                $data['products'][$index] = $product;
                $items[] = [
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'sku' => $product['sku'],
                    'price' => $product['price'],
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            }

            $order = [
                'id' => $this->newId(),
                'customer_id' => $customer['id'] ?? null,
                'customer_name' => $customer['name'] ?? ($customerName !== '' ? $customerName : 'Khách lẻ'),
                'items' => $items,
                'total' => $total,
                'status' => 'completed',
                'note' => $note,
                'created_at' => date(DATE_ATOM),
            ];
            $data['orders'][] = $order;
            return $order;
        });
    }

    public function updateOrderStatus(string $id, string $status): array
    {
        if (!in_array($status, ['completed', 'cancelled'], true)) {
            throw new ApiException('Trạng thái đơn hàng không hợp lệ.');
        }

        return $this->store->update(function (array &$data) use ($id, $status): array {
            $orderIndex = self::findIndex($data['orders'], $id);
            if ($orderIndex === null) {
                throw new ApiException('Không tìm thấy đơn hàng.', 404);
            }
            $order = $data['orders'][$orderIndex];
            if ($order['status'] === $status) {
                return $order;
            }

            foreach ($order['items'] as $item) {
                $productIndex = self::findIndex($data['products'], $item['product_id']);
                if ($productIndex === null) {
                    continue;
                }
                $product = $data['products'][$productIndex];
                if ($status === 'completed') {
                    if ($product['stock'] < $item['quantity']) {
                        throw new ApiException("Sản phẩm {$product['name']} không đủ tồn kho để khôi phục đơn.", 409);
                    }
                    $product['stock'] -= $item['quantity'];
                } else {
                    $product['stock'] += $item['quantity'];
                }
                $data['products'][$productIndex] = $product;
            }

            $order['status'] = $status;
            $data['orders'][$orderIndex] = $order;
            return $order;
        });
    }

    private function text(mixed $value, string $label, int $maxLength): string
    {
        if (!is_string($value)) {
            throw new ApiException("$label không hợp lệ.");
        }
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new ApiException("$label là bắt buộc và không được vượt quá $maxLength ký tự.");
        }
        return $value;
    }

    private function optionalText(mixed $value, string $label, int $maxLength): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_string($value) || mb_strlen($value) > $maxLength) {
            throw new ApiException("$label không được vượt quá $maxLength ký tự.");
        }
        return trim($value);
    }

    private function integer(mixed $value, string $label, int $min, int $max): int
    {
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new ApiException("$label phải là số nguyên từ $min đến $max.");
        }
        return $value;
    }

    private function newId(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function sortOrders(array $orders): array
    {
        usort($orders, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
        return array_values($orders);
    }

    private function findById(array $records, string $id): ?array
    {
        $index = self::findIndex($records, $id);
        return $index === null ? null : $records[$index];
    }

    private static function findIndex(array $records, string $id): ?int
    {
        foreach ($records as $index => $record) {
            if ($record['id'] === $id) {
                return $index;
            }
        }
        return null;
    }
}
