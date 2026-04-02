<?php

declare(strict_types=1);

namespace Kode\Cache\Store;

use Kode\Cache\Contract\StoreInterface;
use Kode\Cache\Exception\CacheException;

class MemcachedStore implements StoreInterface
{
    protected ?\Memcached $memcached = null;

    protected string $prefix;

    protected int $expire = 0;

    protected string $host;

    protected int $port;

    protected ?string $username = null;

    protected ?string $password = null;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 11211,
        ?string $username = null,
        ?string $password = null,
        string $prefix = '',
        int $expire = 0
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->prefix = $prefix;
        $this->expire = $expire;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->checkConnection();

        $value = $this->memcached->get($this->getKey($key));

        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return $default;
        }

        return $value;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->checkConnection();

        $expire = $ttl ?? $this->expire;

        return $this->memcached->set($this->getKey($key), $value, $expire);
    }

    public function delete(string $key): bool
    {
        $this->checkConnection();
        return $this->memcached->delete($this->getKey($key));
    }

    public function has(string $key): bool
    {
        $this->checkConnection();

        $this->memcached->get($this->getKey($key));
        return $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    public function clear(): bool
    {
        $this->checkConnection();
        return $this->memcached->flush();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $this->checkConnection();

        $keys = is_array($keys) ? $keys : iterator_to_array($keys);
        $memcachedKeys = array_map(fn($k) => $this->getKey((string) $k), $keys);

        $values = $this->memcached->getMulti($memcachedKeys);

        $result = [];
        foreach ($keys as $i => $key) {
            $memcachedKey = $memcachedKeys[$i];
            $result[$key] = $values[$memcachedKey] ?? $default;
        }

        return $result;
    }

    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        $this->checkConnection();

        $expire = $ttl ?? $this->expire;
        $items = [];

        foreach ($values as $key => $value) {
            $items[$this->getKey((string) $key)] = $value;
        }

        return $this->memcached->setMulti($items, $expire);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $this->checkConnection();

        $keys = is_array($keys) ? $keys : iterator_to_array($keys);
        $memcachedKeys = array_map(fn($k) => $this->getKey((string) $k), $keys);

        $this->memcached->deleteMulti($memcachedKeys);

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
        $this->checkConnection();
        return $this->memcached->increment($this->getKey($key), $step);
    }

    public function decrement(string $key, int $step = 1): int|false
    {
        $this->checkConnection();
        return $this->memcached->decrement($this->getKey($key), $step);
    }

    public function add(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->checkConnection();
        $expire = $ttl ?? $this->expire;
        return $this->memcached->add($this->getKey($key), $value, $expire);
    }

    public function getMemcached(): \Memcached
    {
        $this->checkConnection();
        return $this->memcached;
    }

    protected function getKey(string $key): string
    {
        return $this->prefix . $key;
    }

    protected function checkConnection(): void
    {
        if ($this->memcached !== null) {
            return;
        }

        if (!extension_loaded('memcached')) {
            throw new CacheException('Memcached 扩展未安装');
        }

        $this->memcached = new \Memcached();

        if ($this->username !== null && $this->password !== null) {
            $this->memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            $this->memcached->setSaslAuthData($this->username, $this->password);
        }

        if (!$this->memcached->addServer($this->host, $this->port)) {
            throw new CacheException("无法连接到 Memcached 服务器: {$this->host}:{$this->port}");
        }
    }
}
