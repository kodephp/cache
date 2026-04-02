<?php

declare(strict_types=1);

namespace Kode\Cache\Exception;

/**
 * 参数无效异常
 *
 * 当参数无效或驱动未配置时抛出
 */
class InvalidArgumentException extends BaseException
{
    /**
     * 创建参数无效异常
     *
     * @param string $message 错误消息
     * @param array $context 错误上下文
     * @param \Throwable|null $previous 原始异常
     * @return static
     */
    public static function make(string $message, array $context = [], ?\Throwable $previous = null): static
    {
        return new static(self::CODE_INVALID_ARGUMENT, $message, $previous, self::TYPE_BUSINESS, $context);
    }

    /**
     * 创建驱动未找到异常
     *
     * @param string $driver 驱动名称
     * @param \Throwable|null $previous 原始异常
     * @return static
     */
    public static function driverNotFound(string $driver, ?\Throwable $previous = null): static
    {
        return new static(
            self::CODE_DRIVER_NOT_FOUND,
            "缓存驱动 [{$driver}] 未配置",
            $previous,
            self::TYPE_SYSTEM,
            ['driver' => $driver]
        );
    }
}
