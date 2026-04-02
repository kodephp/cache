<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Store\RedisStore;

/**
 * 原子计数器
 *
 * 基于 Redis 实现的原子计数器，适用于高并发计数场景
 */
class AtomicCounter
{
    /** @var RedisStore Redis 缓存存储 */
    protected RedisStore $store;

    /** @var string 计数器键名 */
    protected string $key;

    /**
     * 构造函数
     *
     * @param RedisStore $store Redis 缓存存储
     * @param string $key 计数器键名
     */
    public function __construct(RedisStore $store, string $key)
    {
        $this->store = $store;
        $this->key = $key;
    }

    /**
     * 递增计数器
     *
     * @param int $step 步长
     * @return int 新值
     */
    public function increment(int $step = 1): int
    {
        return (int) $this->store->getRedis()->incrby($this->key, $step);
    }

    /**
     * 递减计数器
     *
     * @param int $step 步长
     * @return int 新值
     */
    public function decrement(int $step = 1): int
    {
        return (int) $this->store->getRedis()->decrby($this->key, $step);
    }

    /**
     * 获取当前值
     *
     * @return int
     */
    public function get(): int
    {
        $value = $this->store->getRedis()->get($this->key);
        return $value !== false ? (int) $value : 0;
    }

    /**
     * 设置计数器值
     *
     * @param int $value 值
     * @return bool
     */
    public function set(int $value): bool
    {
        return (bool) $this->store->getRedis()->set($this->key, $value);
    }

    /**
     * 重置计数器
     *
     * @return bool
     */
    public function reset(): bool
    {
        return (bool) $this->store->getRedis()->del($this->key);
    }
}
