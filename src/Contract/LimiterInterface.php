<?php

declare(strict_types=1);

namespace Kode\Cache\Contract;

/**
 * 限流器接口
 *
 * 用于实现请求频率限制
 */
interface LimiterInterface
{
    /**
     * 检查是否超过限制
     *
     * @return bool
     */
    public function tooManyAttempts(): bool;

    /**
     * 获取已尝试次数
     *
     * @return int
     */
    public function attempts(): int;

    /**
     * 记录一次尝试
     *
     * @return int
     */
    public function hit(): int;

    /**
     * 获取剩余可用次数
     *
     * @return int
     */
    public function remaining(): int;

    /**
     * 重置限流器
     *
     * @return bool
     */
    public function reset(): bool;

    /**
     * 获取距离可再次访问的秒数
     *
     * @return int
     */
    public function availableIn(): int;

    /**
     * 清空限流器
     *
     * @return bool
     */
    public function clear(): bool;
}
