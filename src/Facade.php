<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Contract\StoreInterface;
use Kode\Cache\Exception\InvalidArgumentException;

/**
 * 缓存门面类
 *
 * 提供静态方式访问缓存功能，类似 Laravel Facade
 */
class Facade
{
    /** @var CacheManager|null 缓存管理器实例 */
    protected static ?CacheManager $instance = null;

    /**
     * 获取缓存管理器实例
     *
     * @param CacheManager|null $instance 缓存管理器实例
     * @return CacheManager
     */
    public static function getInstance(?CacheManager $instance = null): CacheManager
    {
        if ($instance !== null) {
            self::$instance = $instance;
        }

        if (self::$instance === null) {
            self::$instance = new CacheManager();
        }

        return self::$instance;
    }

    /**
     * 获取指定驱动的缓存存储
     *
     * @param string $name 驱动名称
     * @return StoreInterface
     */
    public static function store(string $name = 'default'): StoreInterface
    {
        return self::getInstance()->store($name);
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::getInstance()->has($key);
    }

    /**
     * 获取缓存值
     *
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getInstance()->get($key, $default);
    }

    /**
     * 设置缓存值
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    public static function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return self::getInstance()->set($key, $value, $ttl);
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public static function delete(string $key): bool
    {
        return self::getInstance()->delete($key);
    }

    /**
     * 获取并删除缓存
     *
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        return self::getInstance()->pull($key, $default);
    }

    /**
     * 清空所有缓存
     *
     * @return bool
     */
    public static function clear(): bool
    {
        return self::getInstance()->clear();
    }

    /**
     * 记忆缓存：如果不存在则设置并返回
     *
     * @param string $key 缓存键名
     * @param callable $callback 回调函数
     * @param int|null $ttl 过期时间
     * @return mixed
     */
    public static function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        return self::getInstance()->remember($key, $callback, $ttl);
    }

    /**
     * 永久记忆缓存
     *
     * @param string $key 缓存键名
     * @param callable $callback 回调函数
     * @return mixed
     */
    public static function rememberForever(string $key, callable $callback): mixed
    {
        return self::getInstance()->rememberForever($key, $callback);
    }

    /**
     * 忘记缓存（删除）
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public static function forget(string $key): bool
    {
        return self::getInstance()->forget($key);
    }

    /**
     * 清空所有缓存
     *
     * @return bool
     */
    public static function flush(): bool
    {
        return self::getInstance()->flush();
    }

    /**
     * 递增缓存值
     *
     * @param string $key 缓存键名
     * @param int $step 步长
     * @return int|false
     */
    public static function increment(string $key, int $step = 1): int|false
    {
        return self::getInstance()->increment($key, $step);
    }

    /**
     * 递减缓存值
     *
     * @param string $key 缓存键名
     * @param int $step 步长
     * @return int|false
     */
    public static function decrement(string $key, int $step = 1): int|false
    {
        return self::getInstance()->decrement($key, $step);
    }

    /**
     * 永久设置缓存
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @return bool
     */
    public static function forever(string $key, mixed $value): bool
    {
        return self::getInstance()->forever($key, $value);
    }

    /**
     * 获取缓存标签
     *
     * @param string|array $name 标签名
     * @return Tag
     */
    public static function tag(string|array $name): Tag
    {
        return self::getInstance()->tag($name);
    }

    /**
     * 创建本地锁
     *
     * @param string $name 锁名称
     * @param int $seconds 锁持有时间
     * @return Lock
     */
    public static function lock(string $name, int $seconds = 0): Lock
    {
        return new Lock(self::getInstance()->store(), $name, $seconds);
    }

    /**
     * 创建分布式锁
     *
     * @param string $name 锁名称
     * @param int $seconds 锁持有时间
     * @return DistributedLock
     */
    public static function distributedLock(string $name, int $seconds = 0): DistributedLock
    {
        return new DistributedLock(
            self::getInstance()->store('redis'),
            $name,
            $seconds
        );
    }

    /**
     * 魔术方法调用
     *
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        return self::getInstance()->$method(...$args);
    }
}
