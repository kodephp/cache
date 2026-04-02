<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Contract\StoreInterface;

class FiberCache
{
    protected static array $instances = [];

    protected StoreInterface $store;

    public function __construct(StoreInterface $store)
    {
        $this->store = $store;
    }

    public static function getInstance(string $driver = 'file', array $config = []): self
    {
        $key = $driver . ':' . md5(serialize($config));

        if (!isset(self::$instances[$key])) {
            $manager = new CacheManager($config);
            self::$instances[$key] = new self($manager->store($driver));
        }

        return self::$instances[$key];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store->get($key, $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->store->set($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->store->has($key);
    }

    public function delete(string $key): bool
    {
        return $this->store->delete($key);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->store->pull($key, $default);
    }

    public function clear(): bool
    {
        return $this->store->clear();
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function getStore(): StoreInterface
    {
        return $this->store;
    }

    public static function clearInstances(): void
    {
        self::$instances = [];
    }
}
