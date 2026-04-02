<?php

declare(strict_types=1);

namespace Kode\Cache\Store;

use Kode\Cache\Contract\StoreInterface;
use Kode\Cache\Exception\CacheException;

class FileStore implements StoreInterface
{
    protected string $path;

    protected string $prefix;

    protected int $expire = 0;

    protected bool $subDir = true;

    protected string $hashType = 'md5';

    public function __construct(
        string $path = '/tmp/kode_cache',
        string $prefix = '',
        int $expire = 0,
        bool $subDir = true,
        string $hashType = 'md5'
    ) {
        $this->path = rtrim($path, '/\\');
        $this->prefix = $prefix;
        $this->expire = $expire;
        $this->subDir = $subDir;
        $this->hashType = $hashType;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getCacheFile($key);

        if (!is_file($file)) {
            return $default;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return $default;
        }

        $data = unserialize($content);

        if (!is_array($data)) {
            return $default;
        }

        if ($data['expire'] > 0 && $data['expire'] < time()) {
            unlink($file);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $file = $this->getCacheFile($key);

        $dir = dirname($file);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new CacheException("无法创建缓存目录: {$dir}");
            }
        }

        $expire = $ttl !== null ? time() + $ttl : ($this->expire > 0 ? time() + $this->expire : 0);

        $data = [
            'expire' => $expire,
            'value' => $value,
        ];

        $content = serialize($data);

        $result = file_put_contents($file, $content, LOCK_EX);

        return $result !== false;
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        return $this->set($key, $value, $ttl);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 0);
    }

    public function add(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    public function increment(string $key, int $step = 1): int|false
    {
        $value = (int) $this->get($key, 0);
        $newValue = $value + $step;
        $this->set($key, $newValue);
        return $newValue;
    }

    public function decrement(string $key, int $step = 1): int|false
    {
        return $this->increment($key, -$step);
    }

    public function delete(string $key): bool
    {
        $file = $this->getCacheFile($key);

        if (is_file($file)) {
            return unlink($file);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $file = $this->getCacheFile($key);

        if (!is_file($file)) {
            return false;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return false;
        }

        $data = unserialize($content);

        if (!is_array($data)) {
            return false;
        }

        if ($data['expire'] > 0 && $data['expire'] < time()) {
            unlink($file);
            return false;
        }

        return true;
    }

    public function clear(): bool
    {
        $files = $this->getFiles($this->path);

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->delete($key);
        return $value;
    }

    protected function getCacheFile(string $key): string
    {
        $name = $this->hashType($key);
        $prefix = $this->prefix ? $this->prefix . '_' : '';

        if ($this->subDir) {
            $dir = substr($name, 0, 2) . DIRECTORY_SEPARATOR . substr($name, 2, 2);
            return $this->path . DIRECTORY_SEPARATOR . $prefix . $dir . DIRECTORY_SEPARATOR . $name . '.php';
        }

        return $this->path . DIRECTORY_SEPARATOR . $prefix . $name . '.php';
    }

    protected function hashType(string $key): string
    {
        return match ($this->hashType) {
            'md5' => md5($key),
            'sha1' => sha1($key),
            'xxh3' => hash('xxh3', $key),
            default => md5($key),
        };
    }

    protected function getFiles(string $path): array
    {
        $files = [];

        if (is_dir($path)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
