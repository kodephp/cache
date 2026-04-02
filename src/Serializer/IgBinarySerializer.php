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
     * 检查扩展是否可用
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('igbinary');
    }

    /**
     * 序列化值
     *
     * @param mixed $value 要序列化的值
     * @return string
     * @throws \Kode\Cache\Exception\CacheException 扩展未安装时抛出
     */
    public function serialize(mixed $value): string
    {
        if (!self::isAvailable()) {
            throw new \Kode\Cache\Exception\CacheException(
                'Igbinary 扩展未安装，请运行: pecl install igbinary'
            );
        }

        return igbinary_serialize($value);
    }

    /**
     * 反序列化字符串
     *
     * @param string $value 要反序列化的字符串
     * @return mixed
     * @throws \Kode\Cache\Exception\CacheException 扩展未安装时抛出
     */
    public function unserialize(string $value): mixed
    {
        if (!self::isAvailable()) {
            throw new \Kode\Cache\Exception\CacheException(
                'Igbinary 扩展未安装，请运行: pecl install igbinary'
            );
        }

        return igbinary_unserialize($value);
    }
}
