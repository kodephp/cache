<?php

declare(strict_types=1);

namespace Kode\Cache\Contract;

/**
 * 分布式锁接口
 *
 * 用于实现分布式环境下的互斥访问
 */
interface LockInterface
{
    /**
     * 获取锁
     *
     * @return bool
     */
    public function acquire(): bool;

    /**
     * 释放锁
     *
     * @return bool
     */
    public function release(): bool;

    /**
     * 判断锁是否被当前持有
     *
     * @return bool
     */
    public function isOwned(): bool;

    /**
     * 尝试获取锁（阻塞）
     *
     * @param int $seconds 最大等待秒数
     * @return bool
     */
    public function block(int $seconds): bool;

    /**
     * 获取锁的令牌
     *
     * @return string|null
     */
    public function getToken(): ?string;
}
