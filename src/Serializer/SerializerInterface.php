<?php

declare(strict_types=1);

namespace Kode\Cache\Serializer;

/**
 * 序列化器接口
 *
 * 定义缓存值的序列化和反序列化方法
 */
interface SerializerInterface
{
    /**
     * 序列化值
     *
     * @param mixed $value 要序列化的值
     * @return string
     */
    public function serialize(mixed $value): string;

    /**
     * 反序列化字符串
     *
     * @param string $value 要反序列化的字符串
     * @return mixed
     */
    public function unserialize(string $value): mixed;
}
