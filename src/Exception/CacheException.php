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
}
