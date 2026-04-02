<?php

declare(strict_types=1);

namespace Kode\Cache\Exception;

if (class_exists('\Kode\Exception\Exception')) {
    abstract class BaseException extends \Kode\Exception\Exception
    {
    }
} else {
    abstract class BaseException extends \RuntimeException implements ExceptionInterface
    {
    }
}
