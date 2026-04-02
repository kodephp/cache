<?php

declare(strict_types=1);

namespace Kode\Cache;

/**
 * 设置缓存值
 *
 * @param string $key 缓存键名
 * @param mixed $value 缓存值
 * @param int|null $ttl 过期时间
 * @return bool
 */
function cache_set(string $key, mixed $value, ?int $ttl = null): bool
{
    return Facade::set($key, $value, $ttl);
}

/**
 * 获取缓存值
 *
 * @param string $key 缓存键名
 * @param mixed $default 默认值
 * @return mixed
 */
function cache_get(string $key, mixed $default = null): mixed
{
    return Facade::get($key, $default);
}

/**
 * 检查缓存是否存在
 *
 * @param string $key 缓存键名
 * @return bool
 */
function cache_has(string $key): bool
{
    return Facade::has($key);
}

/**
 * 删除缓存
 *
 * @param string $key 缓存键名
 * @return bool
 */
function cache_delete(string $key): bool
{
    return Facade::delete($key);
}

/**
 * 获取并删除缓存
 *
 * @param string $key 缓存键名
 * @param mixed $default 默认值
 * @return mixed
 */
function cache_pull(string $key, mixed $default = null): mixed
{
    return Facade::pull($key, $default);
}

/**
 * 清空所有缓存
 *
 * @return bool
 */
function cache_clear(): bool
{
    return Facade::clear();
}

/**
 * 记忆缓存：如果不存在则设置并返回
 *
 * @param string $key 缓存键名
 * @param callable $callback 回调函数
 * @param int|null $ttl 过期时间
 * @return mixed
 */
function cache_remember(string $key, callable $callback, ?int $ttl = null): mixed
{
    return Facade::remember($key, $callback, $ttl);
}

/**
 * 忘记缓存（删除）
 *
 * @param string $key 缓存键名
 * @return bool
 */
function cache_forget(string $key): bool
{
    return Facade::forget($key);
}

/**
 * 清空所有缓存
 *
 * @return bool
 */
function cache_flush(): bool
{
    return Facade::flush();
}

/**
 * 获取指定驱动的缓存存储
 *
 * @param string $name 驱动名称
 * @return Contract\StoreInterface
 */
function cache_store(string $name = 'default'): Contract\StoreInterface
{
    return Facade::store($name);
}
