<?php

declare(strict_types=1);

namespace Kode\Cache;

/**
 * 缓存项
 *
 * 用于更精细化地控制缓存项的过期时间和状态
 */
class CacheItem
{
    /** @var string 缓存键名 */
    protected string $key;

    /** @var mixed 缓存值 */
    protected mixed $value;

    /** @var int|null 过期时间（秒） */
    protected ?int $ttl = null;

    /** @var bool 是否命中 */
    protected bool $isHit = false;

    /** @var int|null 过期时间戳 */
    protected ?int $expireAt = null;

    /**
     * 构造函数
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间
     * @param bool $isHit 是否命中
     */
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

    /**
     * 获取缓存键名
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * 获取缓存值
     *
     * @return mixed
     */
    public function get(): mixed
    {
        return $this->value;
    }

    /**
     * 设置缓存值
     *
     * @param mixed $value 缓存值
     * @return self
     */
    public function set(mixed $value): self
    {
        $this->value = $value;
        return $this;
    }

    /**
     * 检查是否命中
     *
     * @return bool
     */
    public function isHit(): bool
    {
        return $this->isHit;
    }

    /**
     * 设置是否命中
     *
     * @param bool $isHit 是否命中
     * @return self
     */
    public function setHit(bool $isHit): self
    {
        $this->isHit = $isHit;
        return $this;
    }

    /**
     * 获取过期时间
     *
     * @return int|null
     */
    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    /**
     * 设置过期时间
     *
     * @param int|null $ttl 过期时间
     * @return self
     */
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

    /**
     * 获取过期时间戳
     *
     * @return int|null
     */
    public function getExpireAt(): ?int
    {
        return $this->expireAt;
    }

    /**
     * 检查是否过期
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if ($this->expireAt === null) {
            return false;
        }

        return $this->expireAt < time();
    }

    /**
     * 设置绝对过期时间
     *
     * @param int|null $timestamp 时间戳
     * @return self
     */
    public function expiresAt(?int $timestamp): self
    {
        $this->expireAt = $timestamp;

        if ($timestamp !== null) {
            $this->ttl = $timestamp - time();
        }

        return $this;
    }

    /**
     * 设置相对过期时间
     *
     * @param int|\DateInterval|null $time 过期时间
     * @return self
     */
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

    /**
     * 创建缓存项
     *
     * @param string $key 缓存键名
     * @return self
     */
    public static function create(string $key): self
    {
        return new self($key);
    }

    /**
     * 转换为数组
     *
     * @return array
     */
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
