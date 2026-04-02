<?php

declare(strict_types=1);

namespace Kode\Cache\Contract;

/**
 * 缓存标签接口
 *
 * 用于对缓存进行分组管理，支持批量操作
 */
interface TagInterface
{
    /**
     * 设置带标签的缓存
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * 获取带标签的缓存
     *
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * 判断带标签的缓存是否存在
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * 删除带标签的缓存
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * 清空标签下的所有缓存
     *
     * @return bool
     */
    public function clear(): bool;

    /**
     * 追加缓存项到标签
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @return bool
     */
    public function append(string $key, mixed $value): bool;

    /**
     * 获取标签下的所有缓存键
     *
     * @return array
     */
    public function getTagItems(): array;
}
