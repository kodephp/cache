<?php

declare(strict_types=1);

namespace Kode\Cache\Exception;

/**
 * 异常基类
 *
 * 继承自 Kode\Exception\KodeException，提供统一的错误码和快捷方法
 */
abstract class BaseException extends \Kode\Exception\KodeException implements ExceptionInterface
{
    /** 系统错误码 */
    public const CODE_CACHE_ERROR = 'E5101';
    public const CODE_DRIVER_NOT_FOUND = 'E5102';
    public const CODE_CONNECTION_FAILED = 'E5103';
    public const CODE_INVALID_ARGUMENT = 'E5104';
    public const CODE_OPERATION_FAILED = 'E5105';

    /**
     * 创建缓存错误异常
     *
     * @param string $message 错误消息
     * @param array $context 错误上下文
     * @param \Throwable|null $previous 原始异常
     * @return static
     */
    public static function cacheError(string $message, array $context = [], ?\Throwable $previous = null): static
    {
        return new static(self::CODE_CACHE_ERROR, $message, $previous, self::TYPE_SYSTEM, $context);
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
            "缓存驱动 [{$driver}] 未找到",
            $previous,
            self::TYPE_SYSTEM,
            ['driver' => $driver]
        );
    }

    /**
     * 创建连接失败异常
     *
     * @param string $message 错误消息
     * @param array $context 错误上下文
     * @param \Throwable|null $previous 原始异常
     * @return static
     */
    public static function connectionFailed(string $message, array $context = [], ?\Throwable $previous = null): static
    {
        return new static(self::CODE_CONNECTION_FAILED, $message, $previous, self::TYPE_SYSTEM, $context);
    }

    /**
     * 创建参数无效异常
     *
     * @param string $message 错误消息
     * @param array $context 错误上下文
     * @param \Throwable|null $previous 原始异常
     * @return static
     */
    public static function invalidArgument(string $message, array $context = [], ?\Throwable $previous = null): static
    {
        return new static(self::CODE_INVALID_ARGUMENT, $message, $previous, self::TYPE_BUSINESS, $context);
    }

    /**
     * 创建操作失败异常
     *
     * @param string $message 错误消息
     * @param array $context 错误上下文
     * @param \Throwable|null $previous 原始异常
     * @return static
     */
    public static function operationFailed(string $message, array $context = [], ?\Throwable $previous = null): static
    {
        return new static(self::CODE_OPERATION_FAILED, $message, $previous, self::TYPE_RUNTIME, $context);
    }
}
