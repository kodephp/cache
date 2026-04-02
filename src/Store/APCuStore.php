<?php

declare(strict_types=1);

namespace Kode\Cache\Store;

use Kode\Cache\Exception\CacheException;

/**
 * APCu 缓存存储
 *
 * 使用 APCu 扩展存储缓存数据，适用于单机环境
 * APCu 是 APC 的无 opcode 缓存版本，仅存储用户数据
 */
class APCuStore extends AbstractStore
{
    /**
     * 构造函数
     *
     * @param string $prefix 缓存键名前缀
     * @param int $expire 默认过期时间
     */
    public function __construct(string $prefix = '', int $expire = 0)
    {
        if (!extension_loaded('apcu')) {
            throw CacheException::connectionFailed(
                'APCu 扩展未安装，请运行: pecl install apcu'
            );
        }

        if (!\apcu_enabled()) {
            throw CacheException::connectionFailed(
                'APCu 扩展未启用，请在 php.ini 中设置: apc.enabled=1'
            );
        }

        parent::__construct($prefix, $expire);
    }

    /**
     * 检查扩展是否可用
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('apcu') && \apcu_enabled();
    }

    /**
     * 获取存储的数据项
     *
     * @param string $key 完整键名
     * @return array|null
     */
    protected function getItem(string $key): ?array
    {
        $success = false;
        $value = \apcu_fetch($key, $success);

        if (!$success) {
            return null;
        }

        return $value;
    }

    /**
     * 设置存储的数据项
     *
     * @param string $key 完整键名
     * @param mixed $value 值
     * @param int $expire 过期时间戳
     * @return bool
     */
    protected function setItem(string $key, mixed $value, int $expire): bool
    {
        $ttl = $expire > 0 ? $expire - time() : 0;
        $item = [
            'value' => $value,
            'expire' => $expire,
        ];

        return \apcu_store($key, $item, $ttl) !== false;
    }

    /**
     * 删除存储的数据项
     *
     * @param string $key 完整键名
     * @return bool
     */
    protected function deleteItem(string $key): bool
    {
        return \apcu_delete($key) !== false;
    }

    /**
     * 清空所有缓存
     *
     * @return bool
     */
    protected function clearAll(): bool
    {
        return \apcu_clear_cache() !== false;
    }
}
