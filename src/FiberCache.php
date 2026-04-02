<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Contract\StoreInterface;

/**
 * Fiber 缓存
 *
 * 在 Fiber 中安全使用缓存实例，提供缓存实例的隔离管理
 */
class FiberCache
{
    /** @var array Fiber 缓存实例集合 */
    protected static array $instances = [];

    /** @var StoreInterface 缓存存储 */
    protected StoreInterface $store;

    /**
     * 构造函数
     *
     * @param StoreInterface $store 缓存存储
     */
    public function __construct(StoreInterface $store)
    {
        $this->store = $store;
    }

    /**
     * 获取缓存实例
     *
     * @param string $driver 驱动名称
     * @param array $config 配置数组
     * @return self
     */
    public static function getInstance(string $driver = 'file', array $config = []): self
    {
        $key = $driver . ':' . md5(serialize($config));

        if (!isset(self::$instances[$key])) {
            $manager = new CacheManager($config);
            self::$instances[$key] = new self($manager->store($driver));
        }

        return self::$instances[$key];
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
        return $this->store->get($key, $default);
    }

    /**
     * 设置缓存值
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->store->set($key, $value, $ttl);
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->store->has($key);
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function delete(string $key): bool
    {
        return $this->store->delete($key);
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
        return $this->store->pull($key, $default);
    }

    /**
     * 清空所有缓存
     *
     * @return bool
     */
    public function clear(): bool
    {
        return $this->store->clear();
    }

    /**
     * 记忆缓存：如果不存在则设置并返回
     *
     * @param string $key 缓存键名
     * @param callable $callback 回调函数
     * @param int|null $ttl 过期时间
     * @return mixed
     */
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

    /**
     * 获取缓存存储实例
     *
     * @return StoreInterface
     */
    public function getStore(): StoreInterface
    {
        return $this->store;
    }

    /**
     * 清除所有 Fiber 缓存实例
     */
    public static function clearInstances(): void
    {
        self::$instances = [];
    }
}
