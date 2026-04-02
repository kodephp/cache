<?php

declare(strict_types=1);

namespace Kode\Cache\Exception;

/**
 * 运行时异常
 *
 * 当运行时发生错误时抛出
 */
class RuntimeException extends BaseException
{
    /**
     * 创建运行时异常
     *
     * @param string $message 错误消息
     * @param array $context 错误上下文
     * @param \Throwable|null $previous 原始异常
     * @return static
     */
    public static function make(string $message, array $context = [], ?\Throwable $previous = null): static
    {
        return new static(self::CODE_OPERATION_FAILED, $message, $previous, self::TYPE_RUNTIME, $context);
    }
}
