<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Contract\TagInterface;
use Kode\Cache\Exception\InvalidArgumentException;

class Tag implements TagInterface
{
    protected CacheManager $cache;

    protected string|array $name;

    protected string $prefix = 'tag:';

    public function __construct(CacheManager $cache, string|array $name)
    {
        $this->cache = $cache;
        $this->name = $name;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $tagKey = $this->getTagKey();
        $cacheKey = $this->getCacheKey($key);

        $this->cache->store()->set($cacheKey, $value, $ttl);

        $items = $this->getItems();
        $items[$cacheKey] = true;
        $this->saveItems($items);

        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = $this->getCacheKey($key);
        return $this->cache->store()->get($cacheKey, $default);
    }

    public function has(string $key): bool
    {
        $cacheKey = $this->getCacheKey($key);
        return $this->cache->store()->has($cacheKey);
    }

    public function delete(string $key): bool
    {
        $tagKey = $this->getTagKey();
        $cacheKey = $this->getCacheKey($key);

        $this->cache->store()->delete($cacheKey);

        $items = $this->getItems();
        unset($items[$cacheKey]);
        $this->saveItems($items);

        return true;
    }

    public function clear(): bool
    {
        $items = $this->getItems();

        foreach (array_keys($items) as $key) {
            $this->cache->store()->delete($key);
        }

        $this->saveItems([]);

        return true;
    }

    public function append(string $key, mixed $value): bool
    {
        $items = $this->getItems();

        if (!isset($items[$key])) {
            $items[$key] = true;
            $this->saveItems($items);
        }

        return true;
    }

    public function getTagItems(): array
    {
        return array_keys($this->getItems());
    }

    protected function getTagKey(): string
    {
        if (is_array($this->name)) {
            sort($this->name);
            return $this->prefix . md5(implode(',', $this->name));
        }

        return $this->prefix . md5($this->name);
    }

    protected function getCacheKey(string $key): string
    {
        $tagKey = $this->getTagKey();

        if (is_array($this->name)) {
            return $tagKey . ':' . $key;
        }

        return $tagKey . ':' . $key;
    }

    protected function getItems(): array
    {
        $tagKey = $this->getTagKey();
        $items = $this->cache->store()->get($tagKey);

        return is_array($items) ? $items : [];
    }

    protected function saveItems(array $items): void
    {
        $tagKey = $this->getTagKey();
        $this->cache->set($tagKey, $items);
    }

    public function getName(): string|array
    {
        return $this->name;
    }
}
