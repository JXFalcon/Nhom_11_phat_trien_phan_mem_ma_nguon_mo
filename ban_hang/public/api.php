<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestBody(): array
{
    $content = file_get_contents('php://input');
    if ($content === false || $content === '') {
        return [];
    }

    try {
        $body = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new ApiException('Dữ liệu gửi lên không phải JSON hợp lệ.');
    }

    if (!is_array($body)) {
        throw new ApiException('Dữ liệu gửi lên không đúng định dạng.');
    }
    return $body;
}

try {
    $resource = (string) ($_GET['resource'] ?? '');
    $id = isset($_GET['id']) ? (string) $_GET['id'] : null;
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $result = match ($resource) {
            'dashboard' => $sales->dashboard(),
            'products' => $sales->products(),
            'customers' => $sales->customers(),
            'orders' => $sales->orders(),
            default => throw new ApiException('Không tìm thấy tài nguyên.', 404),
        };
        respond(['data' => $result]);
    }

    $input = requestBody();
    if ($method === 'POST') {
        $result = match ($resource) {
            'products' => $sales->saveProduct($input),
            'customers' => $sales->saveCustomer($input),
            'orders' => $sales->createOrder($input),
            default => throw new ApiException('Không tìm thấy tài nguyên.', 404),
        };
        respond(['data' => $result], 201);
    }

    if ($method === 'PUT') {
        if ($id === null) {
            throw new ApiException('Thiếu mã bản ghi.');
        }
        $result = match ($resource) {
            'products' => $sales->saveProduct($input, $id),
            'customers' => $sales->saveCustomer($input, $id),
            default => throw new ApiException('Không tìm thấy tài nguyên.', 404),
        };
        respond(['data' => $result]);
    }

    if ($method === 'PATCH' && $resource === 'orders' && $id !== null) {
        respond(['data' => $sales->updateOrderStatus($id, (string) ($input['status'] ?? ''))]);
    }

    if ($method === 'DELETE' && $id !== null) {
        match ($resource) {
            'products' => $sales->deleteProduct($id),
            'customers' => $sales->deleteCustomer($id),
            default => throw new ApiException('Không tìm thấy tài nguyên.', 404),
        };
        respond(['data' => ['deleted' => true]]);
    }

    header('Allow: GET, POST, PUT, PATCH, DELETE');
    respond(['error' => 'Phương thức không được hỗ trợ.'], 405);
} catch (ApiException $exception) {
    respond(['error' => $exception->getMessage()], $exception->status);
} catch (JsonException) {
    respond(['error' => 'Không thể đọc dữ liệu lưu trữ.'], 500);
} catch (Throwable $exception) {
    error_log((string) $exception);
    respond(['error' => 'Đã xảy ra lỗi máy chủ. Xem terminal để biết chi tiết.'], 500);
}
