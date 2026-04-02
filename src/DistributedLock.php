<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Exception\CacheException;
use Kode\Cache\Store\RedisStore;

class DistributedLock
{
    protected RedisStore $store;

    protected string $name;

    protected ?string $token = null;

    protected int $seconds = 0;

    protected bool $owner = false;

    protected float $retryDelay = 0.1;

    protected int $lockPrefix = 'lock:';

    public function __construct(
        RedisStore $store,
        string $name,
        int $seconds = 0,
        float $retryDelay = 0.1
    ) {
        $this->store = $store;
        $this->name = $name;
        $this->seconds = $seconds;
        $this->retryDelay = $retryDelay;
        $this->token = $this->generateToken();
    }

    public function acquire(): bool
    {
        $key = $this->getLockKey();

        $result = $this->store->getRedis()->set(
            $key,
            $this->token,
            [
                'NX',
                'EX' => $this->seconds,
            ]
        );

        if ($result) {
            $this->owner = true;
            return true;
        }

        return false;
    }

    public function release(): bool
    {
        if (!$this->owner) {
            return false;
        }

        $key = $this->getLockKey();
        $script = <<<'LUA'
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
LUA;

        $this->store->getRedis()->eval($script, 1, $key, $this->token);
        $this->owner = false;

        return true;
    }

    public function isOwned(): bool
    {
        if (!$this->owner) {
            return false;
        }

        $key = $this->getLockKey();
        $token = $this->store->getRedis()->get($key);

        return $token === $this->token;
    }

    public function block(int $seconds): bool
    {
        $start = microtime(true);

        while (!$this->acquire()) {
            $elapsed = microtime(true) - $start;

            if ($elapsed >= $seconds) {
                return false;
            }

            usleep((int) ($this->retryDelay * 1000000));
        }

        return true;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getName(): string
    {
        return $this->name;
    }

    protected function getLockKey(): string
    {
        return $this->lockPrefix . $this->name;
    }

    protected function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function extend(int $seconds): bool
    {
        if (!$this->owner) {
            return false;
        }

        $key = $this->getLockKey();
        $script = <<<'LUA'
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("expire", KEYS[1], ARGV[2])
else
    return 0
end
LUA;

        $result = $this->store->getRedis()->eval($script, 1, $key, $this->token, $seconds);

        return (bool) $result;
    }
}
