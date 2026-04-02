<?php

declare(strict_types=1);

namespace Kode\Cache\Store;

use Kode\Cache\Contract\StoreInterface;

class MemoryStore implements StoreInterface
{
    protected array $storage = [];

    protected string $prefix;

    protected int $expire = 0;

    public function __construct(string $prefix = '', int $expire = 0)
    {
        $this->prefix = $prefix;
        $this->expire = $expire;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->getKey($key);

        if (!isset($this->storage[$key])) {
            return $default;
        }

        $item = $this->storage[$key];

        if ($item['expire'] > 0 && $item['expire'] < time()) {
            unset($this->storage[$key]);
            return $default;
        }

        if ($item['expire'] === -1) {
            unset($this->storage[$key]);
            return $default;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $key = $this->getKey($key);

        if ($ttl === null) {
            $expire = $this->expire > 0 ? time() + $this->expire : 0;
        } elseif ($ttl === 0) {
            $expire = -1;
        } else {
            $expire = time() + $ttl;
        }

        $this->storage[$key] = [
            'value' => $value,
            'expire' => $expire,
        ];

        return true;
    }

    public function add(string $key, mixed $value, ?int $ttl = null): bool
    {
        $key = $this->getKey($key);

        if (isset($this->storage[$key])) {
            $item = $this->storage[$key];
            if (!($item['expire'] > 0 && $item['expire'] < time()) && $item['expire'] !== -1) {
                return false;
            }
        }

        return $this->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        $key = $this->getKey($key);
        unset($this->storage[$key]);
        return true;
    }

    public function has(string $key): bool
    {
        $key = $this->getKey($key);

        if (!isset($this->storage[$key])) {
            return false;
        }

        $item = $this->storage[$key];

        if ($item['expire'] > 0 && $item['expire'] < time()) {
            unset($this->storage[$key]);
            return false;
        }

        return true;
    }

    public function clear(): bool
    {
        $this->storage = [];
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get((string) $key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
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

    public function increment(string $key, int $step = 1): int|false
    {
        $value = $this->get($key, 0);

        if (!is_numeric($value)) {
            return false;
        }

        $newValue = $value + $step;
        $this->set($key, $newValue);

        return $newValue;
    }

    public function decrement(string $key, int $step = 1): int|false
    {
        return $this->increment($key, -$step);
    }

    public function forever(string $key, mixed $value): bool
    {
        $key = $this->getKey($key);
        $this->storage[$key] = [
            'value' => $value,
            'expire' => 0,
        ];
        return true;
    }

    protected function getKey(string $key): string
    {
        return $this->prefix . $key;
    }

    public function getStorage(): array
    {
        return $this->storage;
    }
}
