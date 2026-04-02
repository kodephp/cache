<?php

declare(strict_types=1);

namespace Kode\Cache\Serializer;

/**
 * PHP 原生序列化器
 *
 * 使用 PHP 原生的 serialize/unserialize 函数
 */
class PhpSerializer implements SerializerInterface
{
    /**
     * 序列化值
     *
     * @param mixed $value 要序列化的值
     * @return string
     */
    public function serialize(mixed $value): string
    {
        return serialize($value);
    }

    /**
     * 反序列化字符串
     *
     * @param string $value 要反序列化的字符串
     * @return mixed
     */
    public function unserialize(string $value): mixed
    {
        return unserialize($value);
    }
}
