<?php

declare(strict_types=1);

namespace Kode\Cache;

class Facade
{
    protected static ?CacheManager $instance = null;

    public static function getInstance(?CacheManager $instance = null): CacheManager
    {
        if ($instance !== null) {
            self::$instance = $instance;
        }

        if (self::$instance === null) {
            self::$instance = new CacheManager();
        }

        return self::$instance;
    }

    public static function store(string $name = 'default'): Contract\StoreInterface
    {
        return self::getInstance()->store($name);
    }

    public static function has(string $key): bool
    {
        return self::getInstance()->has($key);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getInstance()->get($key, $default);
    }

    public static function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return self::getInstance()->set($key, $value, $ttl);
    }

    public static function delete(string $key): bool
    {
        return self::getInstance()->delete($key);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        return self::getInstance()->pull($key, $default);
    }

    public static function clear(): bool
    {
        return self::getInstance()->clear();
    }

    public static function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        return self::getInstance()->remember($key, $callback, $ttl);
    }

    public static function rememberForever(string $key, callable $callback): mixed
    {
        return self::getInstance()->rememberForever($key, $callback);
    }

    public static function forget(string $key): bool
    {
        return self::getInstance()->forget($key);
    }

    public static function flush(): bool
    {
        return self::getInstance()->flush();
    }

    public static function increment(string $key, int $step = 1): int|false
    {
        return self::getInstance()->increment($key, $step);
    }

    public static function decrement(string $key, int $step = 1): int|false
    {
        return self::getInstance()->decrement($key, $step);
    }

    public static function forever(string $key, mixed $value): bool
    {
        return self::getInstance()->forever($key, $value);
    }

    public static function tag(string|array $name): Tag
    {
        return self::getInstance()->tag($name);
    }

    public static function lock(string $name, int $seconds = 0): Lock
    {
        return new Lock(self::getInstance()->store(), $name, $seconds);
    }

    public static function distributedLock(string $name, int $seconds = 0): DistributedLock
    {
        return new DistributedLock(
            self::getInstance()->store('redis'),
            $name,
            $seconds
        );
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        return self::getInstance()->$method(...$args);
    }
}
