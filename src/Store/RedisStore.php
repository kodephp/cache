<?php

declare(strict_types=1);

namespace Kode\Cache\Store;

use Kode\Cache\Contract\StoreInterface;
use Kode\Cache\Exception\CacheException;

class RedisStore implements StoreInterface
{
    protected \Redis|\RedisArray|\RedisCluster|object $redis;

    protected string $prefix;

    protected int $expire = 0;

    protected ?string $host = null;

    protected ?int $port = null;

    protected ?string $password = null;

    protected int $database = 0;

    protected ?string $persistent = null;

    protected float $timeout = 0.0;

    protected ?string $license = null;

    public function __construct(
        string $host = '127.0.0.1',
        ?int $port = 6379,
        ?string $password = null,
        int $database = 0,
        string $prefix = '',
        int $expire = 0,
        ?string $persistent = null,
        float $timeout = 0.0
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->database = $database;
        $this->prefix = $prefix;
        $this->expire = $expire;
        $this->persistent = $persistent;
        $this->timeout = $timeout;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->checkConnection();

        $value = $this->redis->get($this->getKey($key));

        if ($value === false) {
            return $default;
        }

        $data = unserialize($value);

        if (!is_array($data)) {
            return $value;
        }

        if (isset($data['expire']) && $data['expire'] > 0 && $data['expire'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'] ?? $default;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->checkConnection();

        $expire = $ttl !== null ? time() + $ttl : ($this->expire > 0 ? time() + $this->expire : 0);

        if ($expire > 0) {
            $data = serialize([
                'expire' => $expire,
                'value' => $value,
            ]);

            $result = $this->redis->setex($this->getKey($key), $expire - time(), $data);
        } else {
            $data = serialize([
                'expire' => 0,
                'value' => $value,
            ]);

            $result = $this->redis->set($this->getKey($key), $data);
        }

        return (bool) $result;
    }

    public function delete(string $key): bool
    {
        $this->checkConnection();
        return (bool) $this->redis->del($this->getKey($key));
    }

    public function has(string $key): bool
    {
        $this->checkConnection();

        $exists = $this->redis->exists($this->getKey($key));

        if (!$exists) {
            return false;
        }

        $value = $this->redis->get($this->getKey($key));

        if ($value === false) {
            return false;
        }

        $data = unserialize($value);

        if (is_array($data) && isset($data['expire']) && $data['expire'] > 0 && $data['expire'] < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function clear(): bool
    {
        $this->checkConnection();

        $keys = $this->redis->keys($this->prefix . '*');

        if (!empty($keys)) {
            $this->redis->del($keys);
        }

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $this->checkConnection();

        $keys = array_map(fn($k) => $this->getKey((string) $k), is_array($keys) ? $keys : iterator_to_array($keys));

        $values = $this->redis->mget($keys);

        $result = [];
        $originalKeys = is_array($keys) ? $keys : iterator_to_array($keys);

        foreach ($originalKeys as $i => $key) {
            $value = $values[$i] ?? false;

            if ($value === false) {
                $result[$key] = $default;
                continue;
            }

            $data = unserialize($value);

            if (is_array($data) && isset($data['expire']) && $data['expire'] > 0 && $data['expire'] < time()) {
                $result[$key] = $default;
            } else {
                $result[$key] = is_array($data) ? ($data['value'] ?? $default) : $value;
            }
        }

        return $result;
    }

    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        $this->checkConnection();

        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $this->checkConnection();

        $keys = array_map(fn($k) => $this->getKey((string) $k), is_array($keys) ? $keys : iterator_to_array($keys));

        $this->redis->del($keys);

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
        return $this->redis->incrby($this->getKey($key), $step);
    }

    public function decrement(string $key, int $step = 1): int|false
    {
        $this->checkConnection();
        return $this->redis->decrby($this->getKey($key), $step);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 0);
    }

    protected function getKey(string $key): string
    {
        return $this->prefix . $key;
    }

    protected function checkConnection(): void
    {
        if (isset($this->redis)) {
            return;
        }

        if (!extension_loaded('redis')) {
            throw new CacheException('Redis 扩展未安装，请使用: composer require predis/predis');
        }

        $this->redis = new \Redis();

        $connected = false;

        if ($this->persistent !== null) {
            $connected = $this->redis->pconnect($this->host, $this->port ?? 6379, $this->timeout, $this->persistent);
        } else {
            $connected = $this->redis->connect($this->host ?? '127.0.0.1', $this->port ?? 6379, $this->timeout);
        }

        if (!$connected) {
            throw new CacheException('无法连接到 Redis 服务器');
        }

        if ($this->password !== null) {
            if (!$this->redis->auth($this->password)) {
                throw new CacheException('Redis 认证失败');
            }
        }

        if ($this->database > 0) {
            $this->redis->select($this->database);
        }
    }

    public function getRedis(): \Redis|\RedisArray|\RedisCluster|object
    {
        $this->checkConnection();
        return $this->redis;
    }
}
