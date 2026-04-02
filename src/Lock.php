<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Contract\LockInterface;
use Kode\Cache\Contract\StoreInterface;

/**
 * 本地锁
 *
 * 适用于单机单进程的同步场景，基于缓存存储实现
 */
class Lock implements LockInterface
{
    /** @var StoreInterface 缓存存储 */
    protected StoreInterface $store;

    /** @var string 锁名称 */
    protected string $name;

    /** @var string|null 锁令牌 */
    protected ?string $token = null;

    /** @var int 锁持有时间（秒） */
    protected int $seconds = 0;

    /** @var bool 是否为锁持有者 */
    protected bool $owner = false;

    /**
     * 构造函数
     *
     * @param StoreInterface $store 缓存存储
     * @param string $name 锁名称
     * @param int $seconds 锁持有时间
     */
    public function __construct(StoreInterface $store, string $name, int $seconds = 0)
    {
        $this->store = $store;
        $this->name = $name;
        $this->seconds = $seconds;
        $this->token = $this->generateToken();
    }

    /**
     * 获取锁
     *
     * @return bool
     */
    public function acquire(): bool
    {
        if (method_exists($this->store, 'add')) {
            if ($this->store->add($this->name, $this->token, $this->seconds)) {
                $this->owner = true;
                return true;
            }
        } else {
            if (!$this->store->has($this->name) && $this->store->set($this->name, $this->token, $this->seconds)) {
                $this->owner = true;
                return true;
            }
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

        $this->owner = false;
        return $this->store->delete($this->name);
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

        $token = $this->store->get($this->name);

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
        $start = time();

        while (!$this->acquire()) {
            if (time() - $start >= $seconds) {
                return false;
            }

            usleep(100000);
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
     * 获取锁持有时间
     *
     * @return int
     */
    public function getSeconds(): int
    {
        return $this->seconds;
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
}
