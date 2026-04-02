<?php

declare(strict_types=1);

namespace Kode\Cache\Serializer;

class PhpSerializer implements SerializerInterface
{
    public function serialize(mixed $value): string
    {
        return serialize($value);
    }

    public function unserialize(string $value): mixed
    {
        return unserialize($value);
    }
}
