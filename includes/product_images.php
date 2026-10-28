<?php
declare(strict_types=1);

/**
 * Chuẩn hóa danh sách URL ảnh sản phẩm.
 * Hỗ trợ chuỗi (mỗi dòng một URL) hoặc mảng.
 *
 * @return array{urls: string[], invalid: string[], too_many: bool}
 */
function of_product_images_input(mixed $value): array
{
    if (is_array($value)) {
        $parts = $value;
    } else {
        $parts = preg_split('/\R+/u', (string)($value ?? '')) ?: [];
    }

    $urls = [];
    $invalid = [];
    foreach ($parts as $part) {
        $url = trim((string)$part);
        if ($url === '') {
            continue;
        }
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $host = (string)parse_url($url, PHP_URL_HOST);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true) || $host === '') {
            $invalid[] = $url;
            continue;
        }
        if (!in_array($url, $urls, true)) {
            $urls[] = $url;
        }
    }

    return [
        'urls' => $urls,
        'invalid' => $invalid,
        'too_many' => count($urls) > 5,
    ];
}

/** Chỉ trả các URL hợp lệ, tối đa 5, dùng khi đọc dữ liệu cũ. */
function of_product_images_parse(mixed $value): array
{
    $parsed = of_product_images_input($value);
    return array_slice($parsed['urls'], 0, 5);
}

/** Chuyển danh sách URL thành chuỗi lưu DB (mỗi dòng một URL). */
function of_product_images_text(array $urls): ?string
{
    $urls = array_values(array_filter(
        array_map(static fn(mixed $u): string => trim((string)$u), $urls),
        static fn(string $u): bool => $u !== ''
    ));
    return $urls ? implode("\n", array_slice($urls, 0, 5)) : null;
}
