<?php

declare(strict_types=1);

namespace Kode\Cache\Serializer;

/**
 * 序列化器工厂
 *
 * 用于创建序列化器实例和检查序列化器可用性
 */
class SerializerFactory
{
    /**
     * 创建序列化器实例
     *
     * @param string $type 序列化器类型: php/json/igbinary
     * @return SerializerInterface
     */
    public static function create(string $type = 'php'): SerializerInterface
    {
        return match ($type) {
            'php' => new PhpSerializer(),
            'json' => new JsonSerializer(),
            'igbinary' => new IgBinarySerializer(),
            default => new PhpSerializer(),
        };
    }

    /**
     * 检查序列化器是否可用
     *
     * @param string $type 序列化器类型
     * @return bool
     */
    public static function isAvailable(string $type): bool
    {
        return match ($type) {
            'php' => true,
            'json' => true,
            'igbinary' => extension_loaded('igbinary'),
            default => false,
        };
    }
}
