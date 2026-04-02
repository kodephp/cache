<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Exception\InvalidArgumentException;

class Config
{
    protected static array $config = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function set(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            self::$config = array_merge(self::$config, $key);
            return;
        }

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    public static function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    public static function all(): array
    {
        return self::$config;
    }

    public static function reset(): void
    {
        self::$config = [];
    }

    public static function load(array $config): void
    {
        self::$config = $config;
    }
}
