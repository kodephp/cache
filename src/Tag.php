<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Contract\TagInterface;

/**
 * 缓存标签
 *
 * 用于对缓存进行分组管理，支持批量清除等操作
 */
class Tag implements TagInterface
{
    /** @var CacheManager 缓存管理器 */
    protected CacheManager $cache;

    /** @var string|array 标签名称 */
    protected string|array $name;

    /** @var string 标签前缀 */
    protected string $prefix = 'tag:';

    /**
     * 构造函数
     *
     * @param CacheManager $cache 缓存管理器
     * @param string|array $name 标签名称
     */
    public function __construct(CacheManager $cache, string|array $name)
    {
        $this->cache = $cache;
        $this->name = $name;
    }

    /**
     * 设置带标签的缓存
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $tagKey = $this->getTagKey();
        $cacheKey = $this->getCacheKey($key);

        $this->cache->store()->set($cacheKey, $value, $ttl);

        $items = $this->getItems();
        $items[$cacheKey] = true;
        $this->saveItems($items);

        return true;
    }

    /**
     * 获取带标签的缓存
     *
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = $this->getCacheKey($key);
        return $this->cache->store()->get($cacheKey, $default);
    }

    /**
     * 判断带标签的缓存是否存在
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function has(string $key): bool
    {
        $cacheKey = $this->getCacheKey($key);
        return $this->cache->store()->has($cacheKey);
    }

    /**
     * 删除带标签的缓存
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function delete(string $key): bool
    {
        $cacheKey = $this->getCacheKey($key);

        $this->cache->store()->delete($cacheKey);

        $items = $this->getItems();
        unset($items[$cacheKey]);
        $this->saveItems($items);

        return true;
    }

    /**
     * 清空标签下的所有缓存
     *
     * @return bool
     */
    public function clear(): bool
    {
        $items = $this->getItems();

        foreach (array_keys($items) as $key) {
            $this->cache->store()->delete($key);
        }

        $this->saveItems([]);

        return true;
    }

    /**
     * 追加缓存项到标签
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @return bool
     */
    public function append(string $key, mixed $value): bool
    {
        $items = $this->getItems();

        if (!isset($items[$key])) {
            $items[$key] = true;
            $this->saveItems($items);
        }

        return true;
    }

    /**
     * 获取标签下的所有缓存键
     *
     * @return array
     */
    public function getTagItems(): array
    {
        return array_keys($this->getItems());
    }

    /**
     * 获取标签的缓存键
     *
     * @return string
     */
    protected function getTagKey(): string
    {
        if (is_array($this->name)) {
            sort($this->name);
            return $this->prefix . md5(implode(',', $this->name));
        }

        return $this->prefix . md5($this->name);
    }

    /**
     * 获取带标签的缓存键
     *
     * @param string $key 缓存键名
     * @return string
     */
    protected function getCacheKey(string $key): string
    {
        $tagKey = $this->getTagKey();
        return $tagKey . ':' . $key;
    }

    /**
     * 获取标签关联的缓存项
     *
     * @return array
     */
    protected function getItems(): array
    {
        $tagKey = $this->getTagKey();
        $items = $this->cache->store()->get($tagKey);

        return is_array($items) ? $items : [];
    }

    /**
     * 保存标签关联的缓存项
     *
     * @param array $items 缓存项数组
     */
    protected function saveItems(array $items): void
    {
        $tagKey = $this->getTagKey();
        $this->cache->set($tagKey, $items);
    }

    /**
     * 获取标签名称
     *
     * @return string|array
     */
    public function getName(): string|array
    {
        return $this->name;
    }
}
