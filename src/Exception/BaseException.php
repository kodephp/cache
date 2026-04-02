<?php

declare(strict_types=1);

namespace Kode\Cache\Exception;

/**
 * 异常基类
 *
 * 继承自 Kode\Exception\Exception，统一异常处理
 */
abstract class BaseException extends \Kode\Exception\Exception implements ExceptionInterface
{
}
