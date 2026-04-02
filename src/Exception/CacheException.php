<?php

declare(strict_types=1);

namespace Kode\Cache\Exception;

/**
 * 缓存操作异常
 *
 * 当缓存操作失败时抛出，如文件写入失败、Redis 连接失败等
 */
class CacheException extends BaseException
{
    /**
     * 创建缓存错误异常
     *
     * @param string $message 错误消息
     * @param array $context 错误上下文
     * @param \Throwable|null $previous 原始异常
     * @return static
     */
    public static function make(string $message, array $context = [], ?\Throwable $previous = null): static
    {
        return new static(self::CODE_CACHE_ERROR, $message, $previous, self::TYPE_SYSTEM, $context);
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
