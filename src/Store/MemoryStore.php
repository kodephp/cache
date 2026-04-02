<?php

declare(strict_types=1);

namespace Kode\Cache\Store;

use Kode\Cache\Contract\StoreInterface;

/**
 * 内存缓存存储
 *
 * 使用 PHP 数组存储缓存数据，适用于单机进程或测试环境
 * 注意：不支持跨进程共享数据，不具备持久化能力
 */
class MemoryStore implements StoreInterface
{
    /** @var array 存储数据的数组 */
    protected array $storage = [];

    /** @var string 缓存键名前缀 */
    protected string $prefix;

    /** @var int 默认过期时间（秒），0 表示永不过期 */
    protected int $expire = 0;

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
        $key = $this->getKey($key);

        if (!isset($this->storage[$key])) {
            return $default;
        }

        $item = $this->storage[$key];

        if ($item['expire'] > 0 && $item['expire'] < time()) {
            unset($this->storage[$key]);
            return $default;
        }

        if ($item['expire'] === -1) {
            unset($this->storage[$key]);
            return $default;
        }

        return $item['value'];
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
        $key = $this->getKey($key);

        if ($ttl === null) {
            $expire = $this->expire > 0 ? time() + $this->expire : 0;
        } elseif ($ttl === 0) {
            $expire = -1;
        } else {
            $expire = time() + $ttl;
        }

        $this->storage[$key] = [
            'value' => $value,
            'expire' => $expire,
        ];

        return true;
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
        $key = $this->getKey($key);

        if (isset($this->storage[$key])) {
            $item = $this->storage[$key];
            if (!($item['expire'] > 0 && $item['expire'] < time()) && $item['expire'] !== -1) {
                return false;
            }
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
        $key = $this->getKey($key);
        unset($this->storage[$key]);
        return true;
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function has(string $key): bool
    {
        $key = $this->getKey($key);

        if (!isset($this->storage[$key])) {
            return false;
        }

        $item = $this->storage[$key];

        if ($item['expire'] > 0 && $item['expire'] < time()) {
            unset($this->storage[$key]);
            return false;
        }

        if ($item['expire'] === -1) {
            unset($this->storage[$key]);
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
        $this->storage = [];
        return true;
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
        $key = $this->getKey($key);
        $this->storage[$key] = [
            'value' => $value,
            'expire' => 0,
        ];
        return true;
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
     * 获取存储数据
     *
     * @return array
     */
    public function getStorage(): array
    {
        return $this->storage;
    }
}
