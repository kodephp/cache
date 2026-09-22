<?php

declare(strict_types=1);

namespace Kode\Cache\Exception;

/**
 * 参数无效异常
 *
 * 当参数无效或驱动未配置时抛出。
 * 快捷工厂用 BaseException 的 invalidArgument() / driverNotFound()：
 * 本类不再声明 make()，「make(string $message, …)」与父类
 * KodeException::make(ErrorCode $code, …) 签名不兼容，声明会让类加载即 fatal，
 * 报错分支就从「可捕获」变成「进程崩」。
 */
class InvalidArgumentException extends BaseException
{
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
