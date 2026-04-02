<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Store\RedisStore;
use Kode\Limiting\Enum\LimiterType;
use Kode\Limiting\Enum\StoreType;
use Kode\Limiting\Limiter;
use Kode\Limiting\Store\RedisStore as LimiterRedisStore;

/**
 * 限流器
 *
 * 基于 Redis 实现的请求限流器，使用 Kode\Limiting\Limiter
 * 支持令牌桶算法，提供请求频率控制功能
 *
 * @example
 * ```php
 * $store = new RedisStore('127.0.0.1', 6379);
 * $limiter = new RateLimiter($store, 'api:rate', 10, 60);
 *
 * if ($limiter->tooManyAttempts('user:123')) {
 *     $retryAfter = $limiter->retryAfter('user:123');
 *     throw new \Exception("请 {$retryAfter} 秒后重试");
 * }
 *
 * $limiter->hit('user:123');
 * ```
 */
class RateLimiter
{
    /** @var Limiter Kode 限流器实例 */
    private Limiter $limiter;

    /** @var int 最大尝试次数 */
    private int $maxAttempts;

    /** @var int 时间窗口（秒） */
    private int $decaySeconds;

    /** @var string 限流键前缀 */
    private string $prefix;

    /**
     * 构造函数
     *
     * @param RedisStore $store Redis 缓存存储
     * @param string $key 限流键名
     * @param int $maxAttempts 最大尝试次数
     * @param int $decaySeconds 时间窗口（秒）
     */
    public function __construct(RedisStore $store, string $key, int $maxAttempts, int $decaySeconds = 60)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->prefix = $key;

        $limiterStore = LimiterRedisStore::create(
            $store->getHost(),
            $store->getPort() ?? 6379,
            'kode:cache:limiter:',
            $store->getPassword(),
            $store->getDatabase()
        );

        $this->limiter = new Limiter(
            StoreType::REDIS,
            LimiterType::TOKEN_BUCKET,
            $limiterStore,
            [
                'capacity' => $maxAttempts,
                'refillRate' => $maxAttempts / $decaySeconds,
                'ttl' => $decaySeconds,
                'prefix' => $key,
            ]
        );
    }

    /**
     * 检查是否超出限制
     *
     * @param string $key 标识键（如用户ID、IP等）
     * @return bool 超限返回 true
     */
    public function tooManyAttempts(string $key): bool
    {
        return !$this->limiter->allow($this->prefix . ':' . $key);
    }

    /**
     * 获取已使用次数
     *
     * @param string $key 标识键
     * @return int 已使用次数
     */
    public function attempts(string $key): int
    {
        return $this->maxAttempts - (int) $this->limiter->getRemaining($this->prefix . ':' . $key);
    }

    /**
     * 获取剩余可用次数
     *
     * @param string $key 标识键
     * @return int 剩余次数
     */
    public function remaining(string $key): int
    {
        return (int) $this->limiter->getRemaining($this->prefix . ':' . $key);
    }

    /**
     * 获取等待时间
     *
     * @param string $key 标识键
     * @return int 需要等待的秒数
     */
    public function retryAfter(string $key): int
    {
        return (int) $this->limiter->getWaitTime($this->prefix . ':' . $key);
    }

    /**
     * 清除限流记录
     *
     * @param string $key 标识键
     */
    public function clear(string $key): void
    {
        $this->limiter->reset($this->prefix . ':' . $key);
    }

    /**
     * 增加一次访问
     *
     * @param string $key 标识键
     * @return bool 是否允许
     */
    public function hit(string $key): bool
    {
        return $this->limiter->allow($this->prefix . ':' . $key);
    }
}