<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Store\RedisStore;

/**
 * 分布式锁
 *
 * 基于 Redis 实现的分布式锁，适用于多进程、跨机器的分布式场景
 */
class DistributedLock
{
    /** @var RedisStore Redis 缓存存储 */
    protected RedisStore $store;

    /** @var string 锁名称 */
    protected string $name;

    /** @var string|null 锁令牌 */
    protected ?string $token = null;

    /** @var int 锁持有时间（秒） */
    protected int $seconds = 0;

    /** @var bool 是否为锁持有者 */
    protected bool $owner = false;

    /** @var float 重试延迟（秒） */
    protected float $retryDelay = 0.1;

    /** @var string 锁键前缀 */
    protected string $lockPrefix = 'lock:';

    /**
     * 构造函数
     *
     * @param RedisStore $store Redis 缓存存储
     * @param string $name 锁名称
     * @param int $seconds 锁持有时间
     * @param float $retryDelay 重试延迟
     */
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

    /**
     * 获取锁
     *
     * @return bool
     */
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

    /**
     * 释放锁
     *
     * @return bool
     */
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

    /**
     * 判断锁是否被当前持有
     *
     * @return bool
     */
    public function isOwned(): bool
    {
        if (!$this->owner) {
            return false;
        }

        $key = $this->getLockKey();
        $token = $this->store->getRedis()->get($key);

        return $token === $this->token;
    }

    /**
     * 阻塞获取锁
     *
     * @param int $seconds 最大等待秒数
     * @return bool
     */
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

    /**
     * 获取锁令牌
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * 获取锁名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取锁键
     *
     * @return string
     */
    protected function getLockKey(): string
    {
        return $this->lockPrefix . $this->name;
    }

    /**
     * 生成唯一令牌
     *
     * @return string
     */
    protected function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * 延期锁持有时间
     *
     * @param int $seconds 延期秒数
     * @return bool
     */
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
