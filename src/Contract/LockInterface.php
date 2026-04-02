<?php

declare(strict_types=1);

namespace Kode\Cache\Contract;

/**
 * 分布式锁接口
 */
interface LockInterface
{
    /**
     * 获取锁
     */
    public function acquire(): bool;

    /**
     * 释放锁
     */
    public function release(): bool;

    /**
     * 判断锁是否被持有
     */
    public function isOwned(): bool;

    /**
     * 尝试获取锁（阻塞）
     */
    public function block(int $seconds): bool;

    /**
     * 获取锁的令牌
     */
    public function getToken(): ?string;
}
