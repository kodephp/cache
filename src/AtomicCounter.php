<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Store\RedisStore;

class AtomicCounter
{
    protected RedisStore $store;

    protected string $key;

    protected int $step = 1;

    public function __construct(RedisStore $store, string $key)
    {
        $this->store = $store;
        $this->key = $key;
    }

    public function increment(int $step = 1): int
    {
        return (int) $this->store->getRedis()->incrby($this->key, $step);
    }

    public function decrement(int $step = 1): int
    {
        return (int) $this->store->getRedis()->decrby($this->key, $step);
    }

    public function get(): int
    {
        $value = $this->store->getRedis()->get($this->key);
        return $value !== false ? (int) $value : 0;
    }

    public function set(int $value): bool
    {
        return (bool) $this->store->getRedis()->set($this->key, $value);
    }

    public function reset(): bool
    {
        return (bool) $this->store->getRedis()->del($this->key);
    }
}
