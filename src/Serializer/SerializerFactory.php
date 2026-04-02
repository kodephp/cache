<?php

declare(strict_types=1);

namespace Kode\Cache\Serializer;

class SerializerFactory
{
    public static function create(string $type = 'php'): SerializerInterface
    {
        return match ($type) {
            'php' => new PhpSerializer(),
            'json' => new JsonSerializer(),
            'igbinary' => new IgBinarySerializer(),
            default => new PhpSerializer(),
        };
    }

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
