<?php

declare(strict_types=1);

namespace Kode\Cache\Contract;

/**
 * 缓存标签接口
 */
interface TagInterface
{
    /**
     * 设置带标签的缓存
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * 获取带标签的缓存
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * 判断带标签的缓存是否存在
     */
    public function has(string $key): bool;

    /**
     * 删除带标签的缓存
     */
    public function delete(string $key): bool;

    /**
     * 清空标签下的所有缓存
     */
    public function clear(): bool;

    /**
     * 追加缓存项到标签
     */
    public function append(string $key, mixed $value): bool;

    /**
     * 获取标签下的所有缓存键
     */
    public function getTagItems(): array;
}
