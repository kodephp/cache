<?php

declare(strict_types=1);

namespace Kode\Cache\Store;

use Kode\Cache\Contract\StoreInterface;
use Kode\Cache\Exception\CacheException;

/**
 * Memcached 缓存存储
 *
 * 基于 Memcached 的缓存存储，适用于高并发分布式缓存场景
 */
class MemcachedStore implements StoreInterface
{
    /** @var \Memcached|null Memcached 连接实例 */
    protected ?\Memcached $memcached = null;

    /** @var string 缓存键名前缀 */
    protected string $prefix;

    /** @var int 默认过期时间（秒），0 表示永不过期 */
    protected int $expire = 0;

    /** @var string Memcached 服务器主机 */
    protected string $host;

    /** @var int Memcached 服务器端口 */
    protected int $port;

    /** @var string|null SASL 用户名 */
    protected ?string $username = null;

    /** @var string|null SASL 密码 */
    protected ?string $password = null;

    /**
     * 构造函数
     *
     * @param string $host Memcached 服务器地址
     * @param int $port Memcached 服务器端口
     * @param string|null $username SASL 用户名
     * @param string|null $password SASL 密码
     * @param string $prefix 缓存键名前缀
     * @param int $expire 默认过期时间
     */
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

        $value = $this->memcached->get($this->getKey($key));

        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return $default;
        }

        return $value;
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

        $expire = $ttl ?? $this->expire;

        return $this->memcached->set($this->getKey($key), $value, $expire);
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
        return $this->memcached->delete($this->getKey($key));
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

        $this->memcached->get($this->getKey($key));
        return $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    /**
     * 清空所有缓存
     *
     * @return bool
     */
    public function clear(): bool
    {
        $this->checkConnection();
        return $this->memcached->flush();
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

        $values = $this->memcached->getMulti($memcachedKeys);

        $result = [];
        foreach ($keys as $i => $key) {
            $memcachedKey = $memcachedKeys[$i];
            $result[$key] = $values[$memcachedKey] ?? $default;
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

        $expire = $ttl ?? $this->expire;
        $items = [];

        foreach ($values as $key => $value) {
            $items[$this->getKey((string) $key)] = $value;
        }

        return $this->memcached->setMulti($items, $expire);
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
        $memcachedKeys = array_map(fn($k) => $this->getKey((string) $k), $keys);

        $this->memcached->deleteMulti($memcachedKeys);

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
        return $this->memcached->increment($this->getKey($key), $step);
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
        return $this->memcached->decrement($this->getKey($key), $step);
    }

    /**
     * 不存在则添加缓存
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    public function add(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->checkConnection();
        $expire = $ttl ?? $this->expire;
        return $this->memcached->add($this->getKey($key), $value, $expire);
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
     * 检查并建立 Memcached 连接
     *
     * @throws CacheException 连接失败时抛出
     */
    protected function checkConnection(): void
    {
        if ($this->memcached !== null) {
            return;
        }

        if (!extension_loaded('memcached')) {
            throw CacheException::connectionFailed('Memcached 扩展未安装，请运行: pecl install memcached');
        }

        $this->memcached = new \Memcached();

        if ($this->username !== null && $this->password !== null) {
            $this->memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            $this->memcached->setSaslAuthData($this->username, $this->password);
        }

        if (!$this->memcached->addServer($this->host, $this->port)) {
            throw CacheException::connectionFailed("无法连接到 Memcached 服务器: {$this->host}:{$this->port}");
        }
    }

    /**
     * 获取 Memcached 连接实例
     *
     * @return \Memcached
     */
    public function getMemcached(): \Memcached
    {
        $this->checkConnection();
        return $this->memcached;
    }
}
