<?php

declare(strict_types=1);

namespace Kode\Cache\Store;

use Kode\Cache\Contract\StoreInterface;
use Kode\Cache\Exception\CacheException;

/**
 * 文件缓存存储
 *
 * 将缓存数据存储到文件系统中，适用于单机或低流量场景
 */
class FileStore implements StoreInterface
{
    /** @var string 缓存存储目录路径 */
    protected string $path;

    /** @var string 缓存键名前缀 */
    protected string $prefix;

    /** @var int 默认过期时间（秒），0 表示永不过期 */
    protected int $expire = 0;

    /** @var bool 是否使用子目录存储 */
    protected bool $subDir = true;

    /** @var string 哈希算法：md5/sha1/xxh3 */
    protected string $hashType = 'md5';

    /**
     * 构造函数
     *
     * @param string $path 缓存目录路径
     * @param string $prefix 缓存键名前缀
     * @param int $expire 默认过期时间
     * @param bool $subDir 是否使用子目录
     * @param string $hashType 哈希算法
     */
    public function __construct(
        string $path = '/tmp/kode_cache',
        string $prefix = '',
        int $expire = 0,
        bool $subDir = true,
        string $hashType = 'md5'
    ) {
        $this->path = rtrim($path, '/\\');
        $this->prefix = $prefix;
        $this->expire = $expire;
        $this->subDir = $subDir;
        $this->hashType = $hashType;
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
        $file = $this->getCacheFile($key);

        if (!is_file($file)) {
            return $default;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return $default;
        }

        $data = unserialize($content);

        if (!is_array($data)) {
            return $default;
        }

        if ($data['expire'] > 0 && $data['expire'] < time()) {
            unlink($file);
            return $default;
        }

        return $data['value'];
    }

    /**
     * 设置缓存值
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间（秒）
     * @return bool
     * @throws CacheException 目录创建失败时抛出
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $file = $this->getCacheFile($key);

        $dir = dirname($file);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw CacheException::make("无法创建缓存目录: {$dir}");
            }
        }

        $expire = $ttl !== null ? time() + $ttl : ($this->expire > 0 ? time() + $this->expire : 0);

        $data = [
            'expire' => $expire,
            'value' => $value,
        ];

        $content = serialize($data);

        $result = file_put_contents($file, $content, LOCK_EX);

        return $result !== false;
    }

    /**
     * 设置缓存值（显式 TTL）
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int $ttl 过期时间（秒）
     * @return bool
     */
    public function put(string $key, mixed $value, int $ttl): bool
    {
        return $this->set($key, $value, $ttl);
    }

    /**
     * 永久设置缓存值
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @return bool
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 0);
    }

    /**
     * 不存在则添加缓存
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    public function add(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
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
     * 删除缓存
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function delete(string $key): bool
    {
        $file = $this->getCacheFile($key);

        if (is_file($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public function has(string $key): bool
    {
        $file = $this->getCacheFile($key);

        if (!is_file($file)) {
            return false;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return false;
        }

        $data = unserialize($content);

        if (!is_array($data)) {
            return false;
        }

        if ($data['expire'] > 0 && $data['expire'] < time()) {
            unlink($file);
            return false;
        }

        return true;
    }

    /**
     * 清空所有缓存
     *
     * @return bool
     */
    public function clear(): bool
    {
        $files = $this->getFiles($this->path);

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * 批量获取缓存
     *
     * @param iterable $keys 缓存键名数组
     * @param mixed $default 默认值
     * @return iterable
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * 批量设置缓存
     *
     * @param iterable $values 键值对数组
     * @param int|null $ttl 过期时间
     * @return bool
     */
    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 批量删除缓存
     *
     * @param iterable $keys 缓存键名数组
     * @return bool
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
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
        $value = $this->get($key, $default);
        $this->delete($key);
        return $value;
    }

    /**
     * 获取缓存文件路径
     *
     * @param string $key 缓存键名
     * @return string
     */
    protected function getCacheFile(string $key): string
    {
        $name = $this->hashType($key);
        $prefix = $this->prefix ? $this->prefix . '_' : '';

        if ($this->subDir) {
            $dir = substr($name, 0, 2) . DIRECTORY_SEPARATOR . substr($name, 2, 2);
            return $this->path . DIRECTORY_SEPARATOR . $prefix . $dir . DIRECTORY_SEPARATOR . $name . '.php';
        }

        return $this->path . DIRECTORY_SEPARATOR . $prefix . $name . '.php';
    }

    /**
     * 根据哈希类型生成哈希值
     *
     * @param string $key 缓存键名
     * @return string
     */
    protected function hashType(string $key): string
    {
        return match ($this->hashType) {
            'md5' => md5($key),
            'sha1' => sha1($key),
            'xxh3' => hash('xxh3', $key),
            default => md5($key),
        };
    }

    /**
     * 获取目录下的所有缓存文件
     *
     * @param string $path 目录路径
     * @return array
     */
    protected function getFiles(string $path): array
    {
        $files = [];

        if (is_dir($path)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * 获取缓存目录路径
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
