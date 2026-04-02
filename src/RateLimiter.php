<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Store\RedisStore;

/**
 * 限流器
 *
 * 基于 Redis 实现的请求限流器，继承自 Kode\Limiting\Limiter
 */
class RateLimiter extends \Kode\Limiting\Limiter
{
    /**
     * 构造函数
     *
     * @param RedisStore $store Redis 缓存存储
     * @param string $key 限流键名
     * @param int $maxAttempts 最大尝试次数
     * @param int $decaySeconds 时间窗口
     */
    public function __construct(RedisStore $store, string $key, int $maxAttempts, int $decaySeconds = 60)
    {
        parent::__construct($store->getRedis(), $key, $maxAttempts, $decaySeconds);
    }
}
