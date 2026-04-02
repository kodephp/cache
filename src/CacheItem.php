<?php

declare(strict_types=1);

namespace Kode\Cache;

class CacheItem
{
    protected string $key;

    protected mixed $value;

    protected ?int $ttl = null;

    protected bool $isHit = false;

    protected ?int $expireAt = null;

    public function __construct(string $key, mixed $value = null, ?int $ttl = null, bool $isHit = false)
    {
        $this->key = $key;
        $this->value = $value;
        $this->ttl = $ttl;
        $this->isHit = $isHit;

        if ($ttl !== null && $ttl > 0) {
            $this->expireAt = time() + $ttl;
        }
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function set(mixed $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function setHit(bool $isHit): self
    {
        $this->isHit = $isHit;
        return $this;
    }

    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    public function setTtl(?int $ttl): self
    {
        $this->ttl = $ttl;

        if ($ttl !== null && $ttl > 0) {
            $this->expireAt = time() + $ttl;
        } else {
            $this->expireAt = null;
        }

        return $this;
    }

    public function getExpireAt(): ?int
    {
        return $this->expireAt;
    }

    public function isExpired(): bool
    {
        if ($this->expireAt === null) {
            return false;
        }

        return $this->expireAt < time();
    }

    public function expiresAt(?int $timestamp): self
    {
        $this->expireAt = $timestamp;

        if ($timestamp !== null) {
            $this->ttl = $timestamp - time();
        }

        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): self
    {
        if ($time === null) {
            $this->ttl = null;
            $this->expireAt = null;
        } elseif ($time instanceof \DateInterval) {
            $this->ttl = (int) $time->format('%s');
            $this->expireAt = time() + $this->ttl;
        } else {
            $this->ttl = $time;
            $this->expireAt = time() + $time;
        }

        return $this;
    }

    public static function create(string $key): self
    {
        return new self($key);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'ttl' => $this->ttl,
            'isHit' => $this->isHit,
            'expireAt' => $this->expireAt,
        ];
    }
}
