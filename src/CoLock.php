<?php

declare(strict_types=1);

namespace Kode\Cache;

/**
 * 协程锁
 *
 * 适用于协程环境（Swoole/Fiber/Swow等）的同步锁，支持阻塞等待和超时控制
 */
class CoLock
{
    /** @var string 锁名称 */
    protected string $name;

    /** @var int 锁持有超时（秒），0 表示永久 */
    protected int $timeout;

    /** @var string|null 锁令牌 */
    protected ?string $token = null;

    /** @var bool 是否为锁持有者 */
    protected bool $owner = false;

    /** @var string 锁存储键 */
    protected string $storageKey;

    /** @var float 重试间隔（秒） */
    protected float $retryInterval = 0.001;

    /**
     * 构造函数
     *
     * @param string $name 锁名称
     * @param int $timeout 锁持有超时（秒）
     */
    public function __construct(string $name, int $timeout = 0)
    {
        $this->name = $name;
        $this->timeout = $timeout;
        $this->storageKey = 'lock:coroutine:' . md5($name);
    }

    /**
     * 获取锁
     *
     * @return bool
     */
    public function acquire(): bool
    {
        $this->token = bin2hex(random_bytes(16));
        $startTime = microtime(true);

        while (true) {
            if ($this->tryAcquire()) {
                $this->owner = true;
                return true;
            }

            if ($this->timeout > 0) {
                $elapsed = microtime(true) - $startTime;
                if ($elapsed >= $this->timeout) {
                    return false;
                }
            }

            $this->yieldControl();
        }
    }

    /**
     * 尝试获取锁（非阻塞）
     *
     * @return bool
     */
    protected function tryAcquire(): bool
    {
        if (!class_exists(\Kode\Context\Context::class)) {
            return $this->acquireWithoutContext();
        }

        $locks = \Kode\Context\Context::get($this->storageKey, []);

        foreach ($locks as $token => $expireAt) {
            if ($expireAt > 0 && $expireAt < time()) {
                unset($locks[$token]);
            }
        }

        if (isset($locks[$this->token])) {
            return true;
        }

        // 存在他人活令牌即失败，否则锁形同虚设（旧实现恒返回 true，无互斥）
        if ($locks !== []) {
            return false;
        }

        $locks[$this->token] = $this->timeout > 0 ? time() + $this->timeout : 0;
        \Kode\Context\Context::set($this->storageKey, $locks);

        return true;
    }

    /**
     * 无上下文时的获取锁方式（进程内静态存储）
     *
     * @return bool
     */
    protected function acquireWithoutContext(): bool
    {
        $store = &self::localStore();

        if (!isset($store[$this->storageKey])) {
            $store[$this->storageKey] = [];
        }

        $locks = &$store[$this->storageKey];

        foreach ($locks as $token => $expireAt) {
            if ($expireAt > 0 && $expireAt < time()) {
                unset($locks[$token]);
            }
        }

        if (isset($locks[$this->token])) {
            return true;
        }

        if ($locks !== []) {
            return false;
        }

        $locks[$this->token] = $this->timeout > 0 ? time() + $this->timeout : 0;

        return true;
    }

    /**
     * 无 Context 时的进程内锁存储（按引用返回，保证 release 能清掉 acquire 写入的令牌）
     *
     * @return array<string, array<string, int>>
     */
    protected static function &localStore(): array
    {
        static $localStorage = [];
        return $localStorage;
    }

    /**
     * 释放锁
     *
     * @return bool
     */
    public function release(): bool
    {
        if (!$this->owner) {
            return false;
        }

        $this->owner = false;

        if (class_exists(\Kode\Context\Context::class)) {
            $locks = \Kode\Context\Context::get($this->storageKey, []);
            unset($locks[$this->token]);
            \Kode\Context\Context::set($this->storageKey, $locks);
        } else {
            $store = &self::localStore();
            if (isset($store[$this->storageKey][$this->token])) {
                unset($store[$this->storageKey][$this->token]);
            }
        }

        return true;
    }

    /**
     * 判断锁是否被当前持有
     *
     * @return bool
     */
    public function isOwned(): bool
    {
        if (!$this->owner) {
            return false;
        }

        if (class_exists(\Kode\Context\Context::class)) {
            $locks = \Kode\Context\Context::get($this->storageKey, []);
            return isset($locks[$this->token]);
        }

        $store = &self::localStore();
        return isset($store[$this->storageKey][$this->token]);
    }

    /**
     * 阻塞获取锁
     *
     * @param int $seconds 最大等待秒数
     * @return bool
     */
    public function block(int $seconds): bool
    {
        $originalTimeout = $this->timeout;
        $this->timeout = $seconds;

        $result = $this->acquire();

        $this->timeout = $originalTimeout;

        return $result;
    }

    /**
     * 获取锁令牌
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * 获取锁名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取锁持有超时
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * 延期锁持有时间
     *
     * @param int $seconds 延期秒数
     * @return bool
     */
    public function extend(int $seconds): bool
    {
        if (!$this->owner) {
            return false;
        }

        $this->timeout = $seconds;

        if (class_exists(\Kode\Context\Context::class)) {
            $locks = \Kode\Context\Context::get($this->storageKey, []);
            if (isset($locks[$this->token])) {
                $locks[$this->token] = time() + $seconds;
                \Kode\Context\Context::set($this->storageKey, $locks);
            }
        } else {
            $store = &self::localStore();
            if (isset($store[$this->storageKey][$this->token])) {
                $store[$this->storageKey][$this->token] = time() + $seconds;
            }
        }

        return true;
    }

    /**
     * 协程让出控制权
     */
    protected function yieldControl(): void
    {
        if (extension_loaded('swoole')) {
            \Swoole\Coroutine::sleep($this->retryInterval);
        } elseif (class_exists(\Fiber::class)) {
            $fiber = \Fiber::getCurrent();
            if ($fiber !== null) {
                \Fiber::suspend();
            } else {
                usleep((int) ($this->retryInterval * 1000000));
            }
        } else {
            usleep((int) ($this->retryInterval * 1000000));
        }
    }

    /**
     * 创建锁实例的快捷方法
     *
     * @param string $name 锁名称
     * @param int $timeout 超时
     * @return self
     */
    public static function make(string $name, int $timeout = 0): self
    {
        return new self($name, $timeout);
    }

    /**
     * 析构函数，确保释放锁
     */
    public function __destruct()
    {
        $this->release();
    }
}
