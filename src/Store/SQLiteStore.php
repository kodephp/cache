<?php

declare(strict_types=1);

namespace Kode\Cache\Store;

use Kode\Cache\Exception\CacheException;

/**
 * SQLite 缓存存储
 *
 * 使用 SQLite 数据库存储缓存数据，适用于文件型持久化缓存
 * 无需额外扩展，PHP 内置支持
 */
class SQLiteStore extends AbstractStore
{
    /** @var \PDO SQLite 数据库连接 */
    protected \PDO $pdo;

    /** @var string 数据库文件路径 */
    protected string $path;

    /**
     * 构造函数
     *
     * @param string $path 数据库文件路径
     * @param string $prefix 缓存键名前缀
     * @param int $expire 默认过期时间
     */
    public function __construct(string $path = ':memory:', string $prefix = '', int $expire = 0)
    {
        $this->path = $path;
        $this->initDatabase();
        parent::__construct($prefix, $expire);
    }

    /**
     * 检查扩展是否可用
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('pdo_sqlite');
    }

    /**
     * 初始化数据库
     *
     * @return void
     */
    protected function initDatabase(): void
    {
        $this->pdo = new \PDO('sqlite:' . $this->path, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS cache (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                expire INTEGER NOT NULL DEFAULT 0
            )
        ');

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_expire ON cache(expire)');
    }

    /**
     * 获取存储的数据项
     *
     * @param string $key 完整键名
     * @return array|null
     */
    protected function getItem(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT value, expire FROM cache WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return [
            'value' => unserialize($row['value']),
            'expire' => (int) $row['expire'],
        ];
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
        $serialized = serialize($value);

        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO cache (key, value, expire) VALUES (?, ?, ?)'
        );

        return $stmt->execute([$key, $serialized, $expire]);
    }

    /**
     * 删除存储的数据项
     *
     * @param string $key 完整键名
     * @return bool
     */
    protected function deleteItem(string $key): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM cache WHERE key = ?');
        return $stmt->execute([$key]);
    }

    /**
     * 清空所有缓存
     *
     * @return bool
     */
    protected function clearAll(): bool
    {
        return $this->pdo->exec('DELETE FROM cache') !== false;
    }

    /**
     * 清理过期缓存
     *
     * @return int 删除的条数
     */
    public function prune(): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM cache WHERE expire > 0 AND expire < ?');
        $stmt->execute([time()]);
        return $stmt->rowCount();
    }

    /**
     * 获取数据库连接
     *
     * @return \PDO
     */
    public function getConnection(): \PDO
    {
        return $this->pdo;
    }
}
