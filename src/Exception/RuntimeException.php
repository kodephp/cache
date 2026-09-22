<?php

declare(strict_types=1);

namespace Kode\Cache\Exception;

/**
 * 运行时异常
 *
 * 当运行时发生错误时抛出。
 * 快捷工厂继承自 BaseException（operationFailed / connectionFailed / …）：
 * 这里不再声明 make()——父类 KodeException::make(ErrorCode $code, …) 的签名与
 * 「make(string $message, …)」不兼容，声明即让类加载时 PHP 直接 fatal（不可捕获），
 * 于是所有走到本异常的分支都从「可捕获的异常」变成「进程崩」。
 */
class RuntimeException extends BaseException
{
}
