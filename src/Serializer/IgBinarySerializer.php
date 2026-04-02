<?php

declare(strict_types=1);

namespace Kode\Cache\Serializer;

/**
 * IgBinary 序列化器
 *
 * 使用 IgBinary 扩展进行序列化，性能更高
 * 需要安装 igbinary 扩展
 */
class IgBinarySerializer implements SerializerInterface
{
    /**
     * 序列化值
     *
     * @param mixed $value 要序列化的值
     * @return string
     */
    public function serialize(mixed $value): string
    {
        return igbinary_serialize($value);
    }

    /**
     * 反序列化字符串
     *
     * @param string $value 要反序列化的字符串
     * @return mixed
     */
    public function unserialize(string $value): mixed
    {
        return igbinary_unserialize($value);
    }
}
