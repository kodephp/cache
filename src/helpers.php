<?php

declare(strict_types=1);

namespace Kode\Cache;

function cache(string $key, mixed $value = null, ?int $ttl = null): mixed
{
    $manager = Facade::getInstance();

    if ($value === null && func_num_args() === 1) {
        return $manager->get($key);
    }

    if ($value === null && func_num_args() === 2) {
        return $manager->has($key);
    }

    if ($value === null) {
        return $manager->store();
    }

    return $manager->set($key, $value, $ttl);
}

function cache_store(string $name = 'default'): Contract\StoreInterface
{
    return Facade::store($name);
}

function cache_has(string $key): bool
{
    return Facade::has($key);
}

function cache_get(string $key, mixed $default = null): mixed
{
    return Facade::get($key, $default);
}

function cache_set(string $key, mixed $value, ?int $ttl = null): bool
{
    return Facade::set($key, $value, $ttl);
}

function cache_delete(string $key): bool
{
    return Facade::delete($key);
}

function cache_pull(string $key, mixed $default = null): mixed
{
    return Facade::pull($key, $default);
}

function cache_clear(): bool
{
    return Facade::clear();
}

function cache_remember(string $key, callable $callback, ?int $ttl = null): mixed
{
    return Facade::remember($key, $callback, $ttl);
}

function cache_forget(string $key): bool
{
    return Facade::forget($key);
}

function cache_flush(): bool
{
    return Facade::flush();
}
