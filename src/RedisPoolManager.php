<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Exception\CacheException;

/**
 * Redis 连接池管理器
 *
 * 协程安全，支持 Swoole/Fiber/Swow 等协程环境
 * 基于 Context 实现连接隔离
 */
class RedisPoolManager
{
    /** @var string Redis 主机 */
    protected string $host;

    /** @var int Redis 端口 */
    protected int $port;

    /** @var string|null Redis 密码 */
    protected ?string $password;

    /** @var int 数据库编号 */
    protected int $database;

    /** @var float 连接超时 */
    protected float $timeout;

    /** @var int 最小连接数 */
    protected int $minConnections;

    /** @var int 最大连接数 */
    protected int $maxConnections;

    /** @var int 空闲超时（秒） */
    protected int $idleTimeout;

    /** @var int 等待超时（秒） */
    protected int $waitTimeout;

    /** @var object|null 上下文管理器 */
    protected static ?object $context = null;

    /**
     * 构造函数
     *
     * @param string $host Redis 主机
     * @param int $port Redis 端口
     * @param string|null $password Redis 密码
     * @param int $database 数据库编号
     * @param array $config 连接池配置
     */
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        ?string $password = null,
        int $database = 0,
        array $config = []
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->database = $database;
        $this->timeout = (float) ($config['timeout'] ?? 0.0);
        $this->minConnections = (int) ($config['min_connections'] ?? 1);
        $this->maxConnections = (int) ($config['max_connections'] ?? 10);
        $this->idleTimeout = (int) ($config['idle_timeout'] ?? 60);
        $this->waitTimeout = (int) ($config['wait_timeout'] ?? 5);
    }

    /**
     * 获取连接
     *
     * @return \Redis
     */
    public function getConnection(): \Redis
    {
        $contextClass = self::getContextClass();

        if ($contextClass !== null) {
            return $this->getConnectionFromContext($contextClass);
        }

        return $this->createConnection();
    }

    /**
     * 从上下文获取连接
     *
     * @param string $contextClass 上下文类名
     * @return \Redis
     */
    protected function getConnectionFromContext(string $contextClass): \Redis
    {
        $poolKey = $this->getPoolKey();
        $poolData = $contextClass::get($poolKey, null);

        $pool = is_array($poolData) ? $poolData : [
            'idle' => [],
            'active' => [],
            'created' => 0,
        ];

        if (!empty($pool['idle'])) {
            $connId = array_key_first($pool['idle']);
            $redis = $pool['idle'][$connId];
            unset($pool['idle'][$connId]);

            if (!$this->isConnectionAlive($redis)) {
                $this->closeConnection($redis);
                $pool['created']--;
                $redis = $this->createConnection();
            }

            $pool['active'][$this->getConnectionId($redis)] = time();
            $contextClass::set($poolKey, $pool);

            return $redis;
        }

        if ($pool['created'] < $this->maxConnections) {
            $redis = $this->createConnection();
            $pool['created']++;
            $pool['active'][$this->getConnectionId($redis)] = time();
            $contextClass::set($poolKey, $pool);

            return $redis;
        }

        return $this->waitAndGetConnection($contextClass, $pool);
    }

    /**
     * 等待并获取连接
     *
     * @param string $contextClass 上下文类名
     * @param array $pool 连接池数据
     * @return \Redis
     */
    protected function waitAndGetConnection(string $contextClass, array $pool): \Redis
    {
        $startTime = microtime(true);
        $timeout = (float) $this->waitTimeout;

        while (microtime(true) - $startTime < $timeout) {
            $poolData = $contextClass::get($this->getPoolKey(), []);
            $pool = is_array($poolData) ? $poolData : ['idle' => [], 'active' => [], 'created' => 0];

            if (!empty($pool['idle'])) {
                $connId = array_key_first($pool['idle']);
                $redis = $pool['idle'][$connId];
                unset($pool['idle'][$connId]);

                if (!$this->isConnectionAlive($redis)) {
                    $this->closeConnection($redis);
                    $pool['created']--;
                    $redis = $this->createConnection();
                    $pool['created']++;
                }

                $pool['active'][$this->getConnectionId($redis)] = time();
                $contextClass::set($this->getPoolKey(), $pool);

                return $redis;
            }

            $this->yieldControl();
        }

        throw CacheException::connectionFailed('Redis 连接池等待超时');
    }

    /**
     * 释放连接
     *
     * @param \Redis $redis Redis 连接
     * @return void
     */
    public function releaseConnection(\Redis $redis): void
    {
        $contextClass = self::getContextClass();

        if ($contextClass !== null) {
            $this->releaseConnectionToContext($contextClass, $redis);
        } else {
            $this->closeConnection($redis);
        }
    }

    /**
     * 释放连接到上下文
     *
     * @param string $contextClass 上下文类名
     * @param \Redis $redis Redis 连接
     */
    protected function releaseConnectionToContext(string $contextClass, \Redis $redis): void
    {
        $poolKey = $this->getPoolKey();
        $poolData = $contextClass::get($poolKey, null);
        $pool = is_array($poolData) ? $poolData : ['idle' => [], 'active' => [], 'created' => 0];

        $connId = $this->getConnectionId($redis);

        if (isset($pool['active'][$connId])) {
            unset($pool['active'][$connId]);

            if ($this->isConnectionAlive($redis)) {
                $pool['idle'][$connId] = $redis;
            } else {
                $this->closeConnection($redis);
                $pool['created']--;
            }

            $contextClass::set($poolKey, $pool);
        }
    }

    /**
     * 创建新连接
     *
     * @return \Redis
     */
    protected function createConnection(): \Redis
    {
        $redis = new \Redis();

        try {
            $redis->connect($this->host, $this->port, $this->timeout);

            if ($this->password !== null) {
                $redis->auth($this->password);
            }

            if ($this->database > 0) {
                $redis->select($this->database);
            }
        } catch (\RedisException $e) {
            throw CacheException::connectionFailed(
                '无法创建 Redis 连接: ' . $e->getMessage(),
                [],
                $e
            );
        }

        return $redis;
    }

    /**
     * 检查连接是否存活
     *
     * @param \Redis $redis Redis 连接
     * @return bool
     */
    protected function isConnectionAlive(\Redis $redis): bool
    {
        try {
            $ping = $redis->ping();
            return $ping === '+PONG' || $ping === true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 关闭连接
     *
     * @param \Redis $redis Redis 连接
     */
    protected function closeConnection(\Redis $redis): void
    {
        try {
            $redis->close();
        } catch (\Throwable) {
        }
    }

    /**
     * 获取连接 ID
     *
     * @param \Redis $redis Redis 连接
     * @return string
     */
    protected function getConnectionId(\Redis $redis): string
    {
        return spl_object_id($redis) . ':' . $this->host . ':' . $this->port;
    }

    /**
     * 获取连接池键名
     *
     * @return string
     */
    protected function getPoolKey(): string
    {
        return 'redis_pool:' . $this->host . ':' . $this->port . ':' . $this->database;
    }

    /**
     * 协程让出控制权
     */
    protected function yieldControl(): void
    {
        if (extension_loaded('swoole')) {
            \Swoole\Coroutine::sleep(0.001);
        } elseif (class_exists(\Fiber::class)) {
            $fiber = \Fiber::getCurrent();
            if ($fiber !== null) {
                \Fiber::suspend();
            } else {
                usleep(1000);
            }
        } else {
            usleep(1000);
        }
    }

    /**
     * 获取上下文类名
     *
     * @return string|null
     */
    protected static function getContextClass(): ?string
    {
        if (self::$context !== null) {
            return self::$context instanceof \Stringable ? (string) self::$context : get_class(self::$context);
        }

        if (!class_exists(\Kode\Context::class)) {
            return null;
        }

        self::$context = \Kode\Context::class;

        return \Kode\Context::class;
    }

    /**
     * 设置上下文管理器
     *
     * @param object|string $context 上下文管理器实例或类名
     */
    public static function setContext(object|string $context): void
    {
        self::$context = is_object($context) ? $context : null;
    }

    /**
     * 重置上下文管理器
     */
    public static function resetContext(): void
    {
        self::$context = null;
    }

    /**
     * 获取连接池状态
     *
     * @return array
     */
    public function getStatus(): array
    {
        $contextClass = self::getContextClass();

        if ($contextClass === null) {
            return [
                'host' => $this->host,
                'port' => $this->port,
                'database' => $this->database,
                'mode' => 'single',
            ];
        }

        $poolData = $contextClass::get($this->getPoolKey(), []);
        $pool = is_array($poolData) ? $poolData : ['idle' => [], 'active' => [], 'created' => 0];

        return [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'mode' => 'pool',
            'idle_count' => count($pool['idle'] ?? []),
            'active_count' => count($pool['active'] ?? []),
            'total_created' => $pool['created'] ?? 0,
            'max_connections' => $this->maxConnections,
        ];
    }

    /**
     * 关闭连接池
     */
    public function close(): void
    {
        $contextClass = self::getContextClass();

        if ($contextClass === null) {
            return;
        }

        $poolKey = $this->getPoolKey();
        $poolData = $contextClass::get($poolKey, []);
        $pool = is_array($poolData) ? $poolData : ['idle' => [], 'active' => []];

        foreach ($pool['idle'] ?? [] as $redis) {
            $this->closeConnection($redis);
        }

        foreach ($pool['active'] ?? [] as $connId => $_) {
            foreach ($pool['idle'] ?? [] as $redis) {
                if ($this->getConnectionId($redis) === $connId) {
                    $this->closeConnection($redis);
                }
            }
        }

        $contextClass::delete($poolKey);
    }

    /**
     * 创建连接池实例的快捷方法
     *
     * @param string $host 主机
     * @param int $port 端口
     * @param string|null $password 密码
     * @param int $database 数据库
     * @param array $config 配置
     * @return self
     */
    public static function make(
        string $host = '127.0.0.1',
        int $port = 6379,
        ?string $password = null,
        int $database = 0,
        array $config = []
    ): self {
        return new self($host, $port, $password, $database, $config);
    }
}
