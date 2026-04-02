<?php

declare(strict_types=1);

namespace Kode\Cache\Serializer;

class JsonSerializer implements SerializerInterface
{
    protected bool $assoc;

    protected int $depth;

    protected int $options;

    public function __construct(bool $assoc = true, int $depth = 512, int $options = 0)
    {
        $this->assoc = $assoc;
        $this->depth = $depth;
        $this->options = $options;
    }

    public function serialize(mixed $value): string
    {
        return json_encode($value, $this->options, $this->depth);
    }

    public function unserialize(string $value): mixed
    {
        return json_decode($value, $this->assoc, $this->depth, $this->options);
    }
}
