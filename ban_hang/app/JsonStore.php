<?php
declare(strict_types=1);

final class JsonStore
{
    public function __construct(
        private readonly string $path,
        private readonly array $seed
    ) {
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Không thể tạo thư mục dữ liệu.');
        }

        $this->withLock(LOCK_EX, function (array &$data): void {
            if ($data === []) {
                $data = $this->seed;
            }
        }, true);
    }

    public function read(callable $callback): mixed
    {
        return $this->withLock(LOCK_SH, static function (array &$data) use ($callback): mixed {
            return $callback($data);
        }, false);
    }

    public function update(callable $callback): mixed
    {
        return $this->withLock(LOCK_EX, $callback, true);
    }

    private function withLock(int $lock, callable $callback, bool $write = false): mixed
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Không thể mở tệp dữ liệu.');
        }

        try {
            if (!flock($handle, $lock)) {
                throw new RuntimeException('Không thể khóa tệp dữ liệu.');
            }

            rewind($handle);
            $contents = stream_get_contents($handle);
            if ($contents === false) {
                throw new RuntimeException('Không thể đọc tệp dữ liệu.');
            }

            $data = $contents === '' ? [] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new RuntimeException('Tệp dữ liệu không đúng định dạng.');
            }

            $result = $callback($data);

            if ($write) {
                $encoded = json_encode(
                    $data,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
                rewind($handle);
                if (!ftruncate($handle, 0) || fwrite($handle, $encoded . PHP_EOL) === false || !fflush($handle)) {
                    throw new RuntimeException('Không thể ghi tệp dữ liệu.');
                }
            }

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
