<?php

declare(strict_types=1);

namespace Kode\Cache;

/**
 * 配置管理类
 *
 * 用于管理缓存配置，支持点号语法访问嵌套配置
 */
class Config
{
    /** @var array 配置数据 */
    protected static array $config = [];

    /**
     * 获取配置值
     *
     * @param string $key 配置键名（支持点号语法）
     * @param mixed $default 默认值
     * @return mixed
     */
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

    /**
     * 设置配置值
     *
     * @param string|array $key 配置键名或键值对数组
     * @param mixed $value 配置值
     */
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

    /**
     * 检查配置是否存在
     *
     * @param string $key 配置键名
     * @return bool
     */
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

    /**
     * 获取所有配置
     *
     * @return array
     */
    public static function all(): array
    {
        return self::$config;
    }

    /**
     * 重置配置
     */
    public static function reset(): void
    {
        self::$config = [];
    }

    /**
     * 加载配置
     *
     * @param array $config 配置数组
     */
    public static function load(array $config): void
    {
        self::$config = $config;
    }
}
