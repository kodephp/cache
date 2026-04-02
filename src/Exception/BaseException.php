<?php

declare(strict_types=1);

namespace Kode\Cache\Exception;

/**
 * 异常基类
 *
 * 如果安装了 kode/exception 则使用其作为基类，否则使用内置基类
 */
if (class_exists('\Kode\Exception\Exception')) {
    abstract class BaseException extends \Kode\Exception\Exception
    {
    }
} else {
    abstract class BaseException extends \RuntimeException implements ExceptionInterface
    {
    }
}
