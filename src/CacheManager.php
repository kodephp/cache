<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Contract\StoreInterface;
use Kode\Cache\Exception\CacheException;
use Kode\Cache\Exception\InvalidArgumentException;
use Kode\Cache\Store\FileStore;
use Kode\Cache\Store\MemoryStore;
use Kode\Cache\Store\MemcachedStore;
use Kode\Cache\Store\RedisStore;

class CacheManager
{
    protected array $stores = [];

    protected array $config = [];

    protected static ?CacheManager $instance = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    public function store(string $name = 'default'): StoreInterface
    {
        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        $config = $this->getConfig($name);

        if ($config === null) {
            throw new InvalidArgumentException("缓存驱动 [{$name}] 未配置");
        }

        $this->stores[$name] = $this->createDriver($config);

        return $this->stores[$name];
    }

    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'file';
    }

    public function setDefaultDriver(string $driver): void
    {
        $this->config['default'] = $driver;
    }

    protected function getConfig(string $name): ?array
    {
        if (isset($this->config['stores'][$name])) {
            return $this->config['stores'][$name];
        }

        if ($name === 'default') {
            return $this->getConfig($this->getDefaultDriver());
        }

        if ($name === 'file' || $name === 'FileStore') {
            return [
                'type' => 'file',
                'path' => $this->config['path'] ?? '/tmp/kode_cache',
                'prefix' => $this->config['prefix'] ?? '',
                'expire' => $this->config['expire'] ?? 0,
                'subDir' => $this->config['subDir'] ?? true,
                'hashType' => $this->config['hashType'] ?? 'md5',
            ];
        }

        if ($name === 'redis' || $name === 'RedisStore') {
            return [
                'type' => 'redis',
                'host' => $this->config['host'] ?? '127.0.0.1',
                'port' => $this->config['port'] ?? 6379,
                'password' => $this->config['password'] ?? null,
                'database' => $this->config['database'] ?? 0,
                'prefix' => $this->config['prefix'] ?? '',
                'expire' => $this->config['expire'] ?? 0,
                'persistent' => $this->config['persistent'] ?? null,
                'timeout' => $this->config['timeout'] ?? 0.0,
            ];
        }

        if ($name === 'memory' || $name === 'MemoryStore') {
            return [
                'type' => 'memory',
                'prefix' => $this->config['prefix'] ?? '',
                'expire' => $this->config['expire'] ?? 0,
            ];
        }

        if ($name === 'memcached' || $name === 'MemcachedStore') {
            return [
                'type' => 'memcached',
                'host' => $this->config['memcached_host'] ?? '127.0.0.1',
                'port' => $this->config['memcached_port'] ?? 11211,
                'username' => $this->config['memcached_username'] ?? null,
                'password' => $this->config['memcached_password'] ?? null,
                'prefix' => $this->config['prefix'] ?? '',
                'expire' => $this->config['expire'] ?? 0,
            ];
        }

        return null;
    }

    protected function createDriver(array $config): StoreInterface
    {
        $type = $config['type'] ?? 'file';

        return match ($type) {
            'file', 'FileStore' => new FileStore(
                $config['path'] ?? '/tmp/kode_cache',
                $config['prefix'] ?? '',
                (int) ($config['expire'] ?? 0),
                (bool) ($config['subDir'] ?? true),
                $config['hashType'] ?? 'md5'
            ),
            'redis', 'RedisStore' => new RedisStore(
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 6379),
                $config['password'] ?? null,
                (int) ($config['database'] ?? 0),
                $config['prefix'] ?? '',
                (int) ($config['expire'] ?? 0),
                $config['persistent'] ?? null,
                (float) ($config['timeout'] ?? 0.0)
            ),
            'memory', 'MemoryStore', 'array' => new MemoryStore(
                $config['prefix'] ?? '',
                (int) ($config['expire'] ?? 0)
            ),
            'memcached', 'MemcachedStore' => new MemcachedStore(
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 11211),
                $config['username'] ?? null,
                $config['password'] ?? null,
                $config['prefix'] ?? '',
                (int) ($config['expire'] ?? 0)
            ),
            default => throw new InvalidArgumentException("不支持的缓存驱动类型: {$type}"),
        };
    }

    public function has(string $key): bool
    {
        return $this->store($this->getDefaultDriver())->has($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store($this->getDefaultDriver())->get($key, $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->store($this->getDefaultDriver())->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->store($this->getDefaultDriver())->delete($key);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->store($this->getDefaultDriver())->pull($key, $default);
    }

    public function clear(): bool
    {
        return $this->store($this->getDefaultDriver())->clear();
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

    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, $callback, null);
    }

    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    public function flush(): bool
    {
        return $this->clear();
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        return $this->set($key, $value, $ttl);
    }

    public function many(iterable $keys, mixed $default = null): iterable
    {
        return $this->store()->getMultiple($keys, $default);
    }

    public function putMany(iterable $values, int $ttl): bool
    {
        return $this->store()->setMultiple($values, $ttl);
    }

    public function increment(string $key, int $step = 1): int|false
    {
        $store = $this->store($this->getDefaultDriver());

        if (method_exists($store, 'increment')) {
            return $store->increment($key, $step);
        }

        $value = (int) $this->get($key, 0);
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
        $store = $this->store($this->getDefaultDriver());

        if (method_exists($store, 'forever')) {
            return $store->forever($key, $value);
        }

        return $this->set($key, $value, 0);
    }

    public function tag(string|array $name): Tag
    {
        return new Tag($this, $name);
    }

    public function getStores(): array
    {
        return $this->stores;
    }

    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
}
