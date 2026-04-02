<?php

declare(strict_types=1);

namespace Kode\Cache\Store;

use Kode\Cache\Contract\StoreInterface;

/**
 * 缓存存储抽象基类
 *
 * 提供通用的缓存存储逻辑，子类只需实现核心存储方法
 * 包含前缀处理、过期时间管理等通用功能
 */
abstract class AbstractStore implements StoreInterface
{
    /** @var string 缓存键名前缀 */
    protected string $prefix;

    /** @var int 默认过期时间（秒），0 表示永不过期 */
    protected int $expire;

    /**
     * 构造函数
     *
     * @param string $prefix 缓存键名前缀
     * @param int $expire 默认过期时间
     */
    public function __construct(string $prefix = '', int $expire = 0)
    {
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
        $value = $this->getItem($this->getKey($key));

        if ($value === null) {
            return $default;
        }

        if ($this->isExpired($value)) {
            $this->delete($key);
            return $default;
        }

        return $value['value'] ?? $default;
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
        $expire = $this->resolveExpire($ttl);

        return $this->setItem($this->getKey($key), $value, $expire);
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
        $fullKey = $this->getKey($key);
        $item = $this->getItem($fullKey);

        if ($item !== null && !$this->isExpired($item)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function delete(string $key): bool
    {
        return $this->deleteItem($this->getKey($key));
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function has(string $key): bool
    {
        $fullKey = $this->getKey($key);
        $item = $this->getItem($fullKey);

        if ($item === null) {
            return false;
        }

        if ($this->isExpired($item)) {
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
        return $this->clearAll();
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
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get((string) $key, $default);
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
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
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
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

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
        $value = $this->get($key, 0);

        if (!is_numeric($value)) {
            return false;
        }

        $newValue = $value + $step;
        $this->set($key, $newValue);

        return $newValue;
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
        return $this->increment($key, -$step);
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
    public function getKey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * 获取前缀
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * 解析过期时间
     *
     * @param int|null $ttl
     * @return int
     */
    protected function resolveExpire(?int $ttl): int
    {
        if ($ttl === null) {
            return $this->expire > 0 ? time() + $this->expire : 0;
        }

        if ($ttl === 0) {
            return 0;
        }

        return time() + $ttl;
    }

    /**
     * 检查数据是否过期
     *
     * @param array $item ['value' => mixed, 'expire' => int]
     * @return bool
     */
    protected function isExpired(array $item): bool
    {
        if (!isset($item['expire'])) {
            return false;
        }

        if ($item['expire'] === 0) {
            return false;
        }

        return $item['expire'] < time();
    }

    /**
     * 获取存储的数据项
     *
     * @param string $key 完整键名（带前缀）
     * @return array|null ['value' => mixed, 'expire' => int] 或 null
     */
    abstract protected function getItem(string $key): ?array;

    /**
     * 设置存储的数据项
     *
     * @param string $key 完整键名
     * @param mixed $value 值
     * @param int $expire 过期时间戳
     * @return bool
     */
    abstract protected function setItem(string $key, mixed $value, int $expire): bool;

    /**
     * 删除存储的数据项
     *
     * @param string $key 完整键名
     * @return bool
     */
    abstract protected function deleteItem(string $key): bool;

    /**
     * 清空所有缓存
     *
     * @return bool
     */
    abstract protected function clearAll(): bool;
}
