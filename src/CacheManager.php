<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Contract\StoreInterface;
use Kode\Cache\Exception\CacheException;
use Kode\Cache\Exception\InvalidArgumentException;
use Kode\Cache\Store\APCuStore;
use Kode\Cache\Store\FileStore;
use Kode\Cache\Store\MemoryStore;
use Kode\Cache\Store\MemcachedStore;
use Kode\Cache\Store\RedisStore;
use Kode\Cache\Store\SQLiteStore;

/**
 * 缓存管理器
 *
 * 负责管理多种缓存驱动的创建和使用，提供统一的缓存操作接口
 * 支持自定义驱动扩展
 */
class CacheManager
{
    /** @var array 已创建的缓存存储实例 */
    protected array $stores = [];

    /** @var array 缓存配置 */
    protected array $config = [];

    /** @var array 已注册的自定义驱动 */
    protected static array $customDrivers = [];

    /** @var array 内置驱动映射 */
    protected static array $driverAliases = [
        'array' => 'memory',
    ];

    /** @var self|null 单例实例 */
    protected static ?self $instance = null;

    /**
     * 注册自定义驱动
     *
     * @param string $name 驱动名称
     * @param string $className 驱动类名（必须实现 StoreInterface）
     * @return void
     */
    public static function extend(string $name, string $className): void
    {
        $reflection = new \ReflectionClass($className);

        if (!$reflection->implementsInterface(StoreInterface::class)) {
            throw new InvalidArgumentException(
                "自定义驱动类 [{$className}] 必须实现 StoreInterface 接口"
            );
        }

        self::$customDrivers[$name] = $className;
    }

    /**
     * 获取单例实例
     *
     * @param array $config 缓存配置
     * @return self
     */
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * 构造函数
     *
     * @param array $config 缓存配置
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * 获取指定名称的缓存存储实例
     *
     * @param string $name 存储名称，默认为 'default'
     * @return StoreInterface
     * @throws InvalidArgumentException 驱动未配置时抛出
     */
    public function store(string $name = 'default'): StoreInterface
    {
        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        $config = $this->getConfig($name);

        if ($config === null) {
            throw new InvalidArgumentException("缓存驱动 [{$name}] 未配置");
        }

        $this->stores[$name] = $this->createDriver($config);

        return $this->stores[$name];
    }

    /**
     * 获取默认驱动名称
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'file';
    }

    /**
     * 设置默认驱动名称
     *
     * @param string $driver 驱动名称
     * @return void
     */
    public function setDefaultDriver(string $driver): void
    {
        $this->config['default'] = $driver;
    }

    /**
     * 获取指定名称的驱动配置
     *
     * @param string $name 驱动名称
     * @return array|null 配置数组，不存在返回 null
     */
    protected function getConfig(string $name): ?array
    {
        if (isset($this->config['stores'][$name])) {
            return $this->config['stores'][$name];
        }

        if ($name === 'default') {
            return $this->getConfig($this->getDefaultDriver());
        }

        return match ($name) {
            'file', 'FileStore' => [
                'type' => 'file',
                'path' => $this->config['path'] ?? '/tmp/kode_cache',
                'prefix' => $this->config['prefix'] ?? '',
                'expire' => $this->config['expire'] ?? 0,
                'subDir' => $this->config['subDir'] ?? true,
                'hashType' => $this->config['hashType'] ?? 'md5',
            ],
            'redis', 'RedisStore' => [
                'type' => 'redis',
                'host' => $this->config['host'] ?? '127.0.0.1',
                'port' => $this->config['port'] ?? 6379,
                'password' => $this->config['password'] ?? null,
                'database' => $this->config['database'] ?? 0,
                'prefix' => $this->config['prefix'] ?? '',
                'expire' => $this->config['expire'] ?? 0,
                'persistent' => $this->config['persistent'] ?? null,
                'timeout' => $this->config['timeout'] ?? 0.0,
            ],
            'memory', 'MemoryStore' => [
                'type' => 'memory',
                'prefix' => $this->config['prefix'] ?? '',
                'expire' => $this->config['expire'] ?? 0,
            ],
            'memcached', 'MemcachedStore' => [
                'type' => 'memcached',
                'host' => $this->config['memcached_host'] ?? '127.0.0.1',
                'port' => $this->config['memcached_port'] ?? 11211,
                'username' => $this->config['memcached_username'] ?? null,
                'password' => $this->config['memcached_password'] ?? null,
                'prefix' => $this->config['prefix'] ?? '',
                'expire' => $this->config['expire'] ?? 0,
            ],
            'apcu', 'APCuStore' => [
                'type' => 'apcu',
                'prefix' => $this->config['prefix'] ?? '',
                'expire' => $this->config['expire'] ?? 0,
            ],
            'sqlite', 'SQLiteStore' => [
                'type' => 'sqlite',
                'path' => $this->config['sqlite_path'] ?? ':memory:',
                'prefix' => $this->config['prefix'] ?? '',
                'expire' => $this->config['expire'] ?? 0,
            ],
            default => null,
        };
    }

    /**
     * 根据配置创建缓存驱动实例
     *
     * @param array $config 驱动配置
     * @return StoreInterface
     * @throws InvalidArgumentException 不支持的驱动类型
     */
    protected function createDriver(array $config): StoreInterface
    {
        $type = $config['type'] ?? 'file';

        if (isset(self::$customDrivers[$type])) {
            $className = self::$customDrivers[$type];
            return new $className($config);
        }

        return match ($type) {
            'file' => new FileStore(
                $config['path'] ?? '/tmp/kode_cache',
                $config['prefix'] ?? '',
                (int) ($config['expire'] ?? 0),
                (bool) ($config['subDir'] ?? true),
                $config['hashType'] ?? 'md5'
            ),
            'redis' => new RedisStore(
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 6379),
                $config['password'] ?? null,
                (int) ($config['database'] ?? 0),
                $config['prefix'] ?? '',
                (int) ($config['expire'] ?? 0),
                $config['persistent'] ?? null,
                (float) ($config['timeout'] ?? 0.0)
            ),
            'memory' => new MemoryStore(
                $config['prefix'] ?? '',
                (int) ($config['expire'] ?? 0)
            ),
            'memcached' => new MemcachedStore(
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 11211),
                $config['username'] ?? null,
                $config['password'] ?? null,
                $config['prefix'] ?? '',
                (int) ($config['expire'] ?? 0)
            ),
            'apcu' => new APCuStore(
                $config['prefix'] ?? '',
                (int) ($config['expire'] ?? 0)
            ),
            'sqlite' => new SQLiteStore(
                $config['path'] ?? ':memory:',
                $config['prefix'] ?? '',
                (int) ($config['expire'] ?? 0)
            ),
            default => throw new InvalidArgumentException("不支持的缓存驱动类型: {$type}"),
        };
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->store($this->getDefaultDriver())->has($key);
    }

    /**
     * 获取缓存值
     *
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store($this->getDefaultDriver())->get($key, $default);
    }

    /**
     * 设置缓存值
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间（秒）
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->store($this->getDefaultDriver())->set($key, $value, $ttl);
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function delete(string $key): bool
    {
        return $this->store($this->getDefaultDriver())->delete($key);
    }

    /**
     * 获取并删除缓存
     *
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->store($this->getDefaultDriver())->pull($key, $default);
    }

    /**
     * 清空所有缓存
     *
     * @return bool
     */
    public function clear(): bool
    {
        return $this->store($this->getDefaultDriver())->clear();
    }

    /**
     * 记忆缓存：如果不存在则设置并返回
     *
     * @param string $key 缓存键名
     * @param callable $callback 回调函数
     * @param int|null $ttl 过期时间
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * 永久记忆缓存
     *
     * @param string $key 缓存键名
     * @param callable $callback 回调函数
     * @return mixed
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, $callback, null);
    }

    /**
     * 忘记缓存（删除）
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * 清空所有缓存
     *
     * @return bool
     */
    public function flush(): bool
    {
        return $this->clear();
    }

    /**
     * 设置缓存（显式 TTL）
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int $ttl 过期时间
     * @return bool
     */
    public function put(string $key, mixed $value, int $ttl): bool
    {
        return $this->set($key, $value, $ttl);
    }

    /**
     * 批量获取缓存
     *
     * @param iterable $keys 键名数组
     * @param mixed $default 默认值
     * @return iterable
     */
    public function many(iterable $keys, mixed $default = null): iterable
    {
        return $this->store()->getMultiple($keys, $default);
    }

    /**
     * 批量设置缓存
     *
     * @param iterable $values 键值对数组
     * @param int $ttl 过期时间
     * @return bool
     */
    public function putMany(iterable $values, int $ttl): bool
    {
        return $this->store()->setMultiple($values, $ttl);
    }

    /**
     * 递增缓存值
     *
     * @param string $key 缓存键名
     * @param int $step 步长
     * @return int|false
     */
    public function increment(string $key, int $step = 1): int|false
    {
        $store = $this->store($this->getDefaultDriver());

        if (method_exists($store, 'increment')) {
            return $store->increment($key, $step);
        }

        $value = (int) $this->get($key, 0);
        $newValue = $value + $step;
        $this->set($key, $newValue);

        return $newValue;
    }

    /**
     * 递减缓存值
     *
     * @param string $key 缓存键名
     * @param int $step 步长
     * @return int|false
     */
    public function decrement(string $key, int $step = 1): int|false
    {
        return $this->increment($key, -$step);
    }

    /**
     * 永久设置缓存
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @return bool
     */
    public function forever(string $key, mixed $value): bool
    {
        $store = $this->store($this->getDefaultDriver());

        if (method_exists($store, 'forever')) {
            return $store->forever($key, $value);
        }

        return $this->set($key, $value, 0);
    }

    /**
     * 获取缓存标签
     *
     * @param string|array $name 标签名
     * @return Tag
     */
    public function tag(string|array $name): Tag
    {
        return new Tag($this, $name);
    }

    /**
     * 获取所有已创建的存储实例
     *
     * @return array
     */
    public function getStores(): array
    {
        return $this->stores;
    }

    /**
     * 设置配置
     *
     * @param array $config 配置数组
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取缓存值（魔术方法）
     *
     * @param string $key 缓存键名
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * 设置缓存值（魔术方法）
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * 检查缓存是否存在（魔术方法）
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * 删除缓存（魔术方法）
     *
     * @param string $key 缓存键名
     * @return void
     */
    public function __unset(string $key): void
    {
        $this->delete($key);
    }
}
