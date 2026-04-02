<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Contract\LimiterInterface;
use Kode\Cache\Store\RedisStore;

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
        protected RedisStore $store;

        protected string $key;

        protected int $maxAttempts;

        protected int $decaySeconds;

        public function __construct(RedisStore $store, string $key, int $maxAttempts, int $decaySeconds = 60)
        {
            $this->store = $store;
            $this->key = $key;
            $this->maxAttempts = $maxAttempts;
            $this->decaySeconds = $decaySeconds;
        }

        public function tooManyAttempts(): bool
        {
            return $this->attempts() >= $this->maxAttempts;
        }

        public function attempts(): int
        {
            $attempts = $this->store->getRedis()->get($this->key . ':attempts');
            return $attempts !== false ? (int) $attempts : 0;
        }

        public function hit(): int
        {
            $redis = $this->store->getRedis();

            $attempts = $redis->incr($this->key . ':attempts');

            if ($attempts === 1) {
                $redis->expire($this->key . ':attempts', $this->decaySeconds);
            }

            return $attempts;
        }

        public function remaining(): int
        {
            return max(0, $this->maxAttempts - $this->attempts());
        }

        public function reset(): bool
        {
            $redis = $this->store->getRedis();
            $redis->del($this->key . ':attempts');
            return true;
        }

        public function availableIn(): int
        {
            $redis = $this->store->getRedis();
            $ttl = $redis->ttl($this->key . ':attempts');
            return $ttl > 0 ? $ttl : 0;
        }

        public function clear(): bool
        {
            return $this->reset();
        }

        public function getMaxAttempts(): int
        {
            return $this->maxAttempts;
        }

        public function getDecaySeconds(): int
        {
            return $this->decaySeconds;
        }
    }
}
