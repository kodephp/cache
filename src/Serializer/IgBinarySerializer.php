<?php

declare(strict_types=1);

namespace Kode\Cache\Serializer;

class IgBinarySerializer implements SerializerInterface
{
    public function serialize(mixed $value): string
    {
        return igbinary_serialize($value);
    }

    public function unserialize(string $value): mixed
    {
        return igbinary_unserialize($value);
    }
}
