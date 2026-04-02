<?php

declare(strict_types=1);

namespace Kode\Cache\Contract;

/**
 * 缓存存储接口
 *
 * 遵循 PSR-16 规范，定义缓存存储的基本操作方法
 */
interface StoreInterface
{
    /**
     * 获取缓存值
     *
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * 设置缓存值
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间（秒）
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * 删除缓存
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * 清空所有缓存
     *
     * @return bool
     */
    public function clear(): bool;

    /**
     * 批量获取缓存
     *
     * @param iterable $keys 缓存键名数组
     * @param mixed $default 默认值
     * @return iterable
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    /**
     * 批量设置缓存
     *
     * @param iterable $values 键值对数组
     * @param int|null $ttl 过期时间
     * @return bool
     */
    public function setMultiple(iterable $values, ?int $ttl = null): bool;

    /**
     * 批量删除缓存
     *
     * @param iterable $keys 缓存键名数组
     * @return bool
     */
    public function deleteMultiple(iterable $keys): bool;

    /**
     * 获取并删除缓存
     *
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed;
}
