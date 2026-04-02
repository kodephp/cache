<?php

declare(strict_types=1);

namespace Kode\Cache\Contract;

/**
 * 缓存存储接口 - 遵循 PSR-16 规范
 */
interface StoreInterface
{
    /**
     * 获取缓存值
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * 设置缓存值
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * 删除缓存
     */
    public function delete(string $key): bool;

    /**
     * 判断缓存是否存在
     */
    public function has(string $key): bool;

    /**
     * 清空所有缓存
     */
    public function clear(): bool;

    /**
     * 批量获取缓存
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    /**
     * 批量设置缓存
     */
    public function setMultiple(iterable $values, ?int $ttl = null): bool;

    /**
     * 批量删除缓存
     */
    public function deleteMultiple(iterable $keys): bool;

    /**
     * 获取并删除缓存
     */
    public function pull(string $key, mixed $default = null): mixed;
}
