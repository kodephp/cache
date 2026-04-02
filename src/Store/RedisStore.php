<?php

declare(strict_types=1);

namespace Kode\Cache\Store;

use Kode\Cache\Contract\StoreInterface;
use Kode\Cache\Exception\CacheException;

/**
 * Redis 缓存存储
 *
 * 基于 Redis 的缓存存储，适用于分布式环境和高性能场景
 */
class RedisStore implements StoreInterface
{
    /** @var \Redis|\RedisArray|\RedisCluster|null Redis 连接实例 */
    protected \Redis|\RedisArray|\RedisCluster|null $redis = null;

    /** @var string 缓存键名前缀 */
    protected string $prefix;

    /** @var int 默认过期时间（秒），0 表示永不过期 */
    protected int $expire = 0;

    /** @var string|null Redis 服务器主机 */
    protected ?string $host = null;

    /** @var int|null Redis 服务器端口 */
    protected ?int $port = null;

    /** @var string|null Redis 密码 */
    protected ?string $password = null;

    /** @var int 数据库编号 */
    protected int $database = 0;

    /** @var string|null 持久化连接 ID */
    protected ?string $persistent = null;

    /** @var float 连接超时时间 */
    protected float $timeout = 0.0;

    /**
     * 构造函数
     *
     * @param string $host Redis 服务器地址
     * @param int|null $port Redis 服务器端口
     * @param string|null $password Redis 密码
     * @param int $database 数据库编号
     * @param string $prefix 缓存键名前缀
     * @param int $expire 默认过期时间
     * @param string|null $persistent 持久化连接 ID
     * @param float $timeout 连接超时时间
     */
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

    /**
     * 获取缓存值
     *
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
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

    /**
     * 设置缓存值
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间（秒）
     * @return bool
     */
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

    /**
     * 删除缓存
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function delete(string $key): bool
    {
        $this->checkConnection();
        return (bool) $this->redis->del($this->getKey($key));
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键名
     * @return bool
     */
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

    /**
     * 清空所有缓存
     *
     * @return bool
     */
    public function clear(): bool
    {
        $this->checkConnection();

        $keys = $this->redis->keys($this->prefix . '*');

        if (!empty($keys)) {
            $this->redis->del($keys);
        }

        return true;
    }

    /**
     * 批量获取缓存
     *
     * @param iterable $keys 缓存键名数组
     * @param mixed $default 默认值
     * @return iterable
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $this->checkConnection();

        $keys = is_array($keys) ? $keys : iterator_to_array($keys);
        $memcachedKeys = array_map(fn($k) => $this->getKey((string) $k), $keys);

        $values = $this->redis->mget($memcachedKeys);

        $result = [];
        foreach ($keys as $i => $key) {
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

    /**
     * 批量设置缓存
     *
     * @param iterable $values 键值对数组
     * @param int|null $ttl 过期时间
     * @return bool
     */
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

    /**
     * 批量删除缓存
     *
     * @param iterable $keys 缓存键名数组
     * @return bool
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $this->checkConnection();

        $keys = is_array($keys) ? $keys : iterator_to_array($keys);
        $redisKeys = array_map(fn($k) => $this->getKey((string) $k), $keys);

        $this->redis->del($redisKeys);

        return true;
    }

    /**
     * 获取并删除缓存
     *
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->delete($key);
        return $value;
    }

    /**
     * 递增缓存值
     *
     * @param string $key 缓存键名
     * @param int $step 步长
     * @return int|false
     */
    public function increment(string $key, int $step = 1): int|false
    {
        $this->checkConnection();
        return $this->redis->incrby($this->getKey($key), $step);
    }

    /**
     * 递减缓存值
     *
     * @param string $key 缓存键名
     * @param int $step 步长
     * @return int|false
     */
    public function decrement(string $key, int $step = 1): int|false
    {
        $this->checkConnection();
        return $this->redis->decrby($this->getKey($key), $step);
    }

    /**
     * 永久设置缓存值
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @return bool
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 0);
    }

    /**
     * 生成带前缀的缓存键名
     *
     * @param string $key 缓存键名
     * @return string
     */
    protected function getKey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * 检查并建立 Redis 连接
     *
     * @throws CacheException 连接失败时抛出
     */
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

    /**
     * 获取 Redis 连接实例
     *
     * @return \Redis|\RedisArray|\RedisCluster
     */
    public function getRedis(): \Redis|\RedisArray|\RedisCluster
    {
        $this->checkConnection();
        return $this->redis;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getDatabase(): int
    {
        return $this->database;
    }
}
