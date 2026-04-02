<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Contract\LimiterInterface;
use Kode\Cache\Store\RedisStore;

/**
 * 限流器
 *
 * 基于 Redis 实现的请求限流器，如果安装了 kode/limiting 则使用其高级功能
 */
if (interface_exists('\Kode\Limiting\LimiterInterface')) {
    class RateLimiter extends \Kode\Limiting\Limiter
    {
        public function __construct(RedisStore $store, string $key, int $maxAttempts, int $decaySeconds = 60)
        {
            parent::__construct($store->getRedis(), $key, $maxAttempts, $decaySeconds);
        }
    }
} else {
    class RateLimiter implements LimiterInterface
    {
        /** @var RedisStore Redis 缓存存储 */
        protected RedisStore $store;

        /** @var string 限流键名 */
        protected string $key;

        /** @var int 最大尝试次数 */
        protected int $maxAttempts;

        /** @var int 时间窗口（秒） */
        protected int $decaySeconds;

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
            $this->store = $store;
            $this->key = $key;
            $this->maxAttempts = $maxAttempts;
            $this->decaySeconds = $decaySeconds;
        }

        /**
         * 检查是否超过限制
         *
         * @return bool
         */
        public function tooManyAttempts(): bool
        {
            return $this->attempts() >= $this->maxAttempts;
        }

        /**
         * 获取已尝试次数
         *
         * @return int
         */
        public function attempts(): int
        {
            $attempts = $this->store->getRedis()->get($this->key . ':attempts');
            return $attempts !== false ? (int) $attempts : 0;
        }

        /**
         * 记录一次尝试
         *
         * @return int
         */
        public function hit(): int
        {
            $redis = $this->store->getRedis();

            $attempts = $redis->incr($this->key . ':attempts');

            if ($attempts === 1) {
                $redis->expire($this->key . ':attempts', $this->decaySeconds);
            }

            return $attempts;
        }

        /**
         * 获取剩余可用次数
         *
         * @return int
         */
        public function remaining(): int
        {
            return max(0, $this->maxAttempts - $this->attempts());
        }

        /**
         * 重置限流器
         *
         * @return bool
         */
        public function reset(): bool
        {
            $redis = $this->store->getRedis();
            $redis->del($this->key . ':attempts');
            return true;
        }

        /**
         * 获取距离可再次访问的秒数
         *
         * @return int
         */
        public function availableIn(): int
        {
            $redis = $this->store->getRedis();
            $ttl = $redis->ttl($this->key . ':attempts');
            return $ttl > 0 ? $ttl : 0;
        }

        /**
         * 清空限流器
         *
         * @return bool
         */
        public function clear(): bool
        {
            return $this->reset();
        }

        /**
         * 获取最大尝试次数
         *
         * @return int
         */
        public function getMaxAttempts(): int
        {
            return $this->maxAttempts;
        }

        /**
         * 获取时间窗口
         *
         * @return int
         */
        public function getDecaySeconds(): int
        {
            return $this->decaySeconds;
        }
    }
}
