<?php

declare(strict_types=1);

namespace Kode\Cache\Tests;

use Kode\Cache\CoLock;
use Kode\Cache\Store\MemoryStore;
use PHPUnit\Framework\TestCase;

/**
 * 回归：CoLock 互斥、MemoryStore add 双前缀与 ttl=0 永久语义
 */
final class CacheFixesTest extends TestCase
{
    /**
     * tryAcquire/acquireWithoutContext 为 protected，测试内以令牌注入方式探测
     */
    private static function attempt(CoLock $lock): bool
    {
        $ref = new \ReflectionClass($lock);

        $token = $ref->getProperty('token');
        $token->setAccessible(true);
        if ($token->getValue($lock) === null) {
            $token->setValue($lock, bin2hex(random_bytes(16)));
        }

        $try = $ref->getMethod('tryAcquire');
        $try->setAccessible(true);
        return (bool) $try->invoke($lock);
    }

    public function testCoLockIsMutuallyExclusive(): void
    {
        $name = 'mutex_' . uniqid();
        $a = new CoLock($name, 30);
        $b = new CoLock($name, 30);

        self::assertTrue($a->acquire());
        // 第二实例不得同时持有（旧实现恒 true，互斥形同虚设）
        self::assertFalse(self::attempt($b));

        self::assertTrue($a->release());
        self::assertTrue(self::attempt($b));
        // 同令牌重复获取应成功
        self::assertTrue(self::attempt($b));

        $bRef = new \ReflectionClass($b);
        $owner = $bRef->getProperty('owner');
        $owner->setAccessible(true);
        $owner->setValue($b, true);
        self::assertTrue($b->release());
    }

    public function testAddStoresUnderSinglePrefixAndVisibleToGet(): void
    {
        $store = new MemoryStore('app:', 0);

        self::assertTrue($store->add('user.1', 'first'));
        self::assertSame('first', $store->get('user.1'));
        self::assertTrue($store->has('user.1'));
        // 已存在时 add 失败且不覆盖
        self::assertFalse($store->add('user.1', 'second'));
        self::assertSame('first', $store->get('user.1'));
        // 存储键必须只带一层前缀（旧实现双前缀导致 get/has 恒 miss）
        self::assertArrayHasKey('app:user.1', $store->getStorage());
        self::assertArrayNotHasKey('app:app:user.1', $store->getStorage());
    }

    public function testAddAfterExpirySucceeds(): void
    {
        $store = new MemoryStore('', 0);
        self::assertTrue($store->add('k', 'v', -10));
        // 负 ttl 立即过期，再次 add 应成功
        self::assertFalse($store->has('k'));
        self::assertTrue($store->add('k', 'v2'));
        self::assertSame('v2', $store->get('k'));
    }

    public function testSetWithZeroTtlIsPersistent(): void
    {
        $store = new MemoryStore('', 0);
        self::assertTrue($store->set('forever_key', 'val', 0));
        // 旧实现写 -1 哨兵，首次读取即被删除
        self::assertSame('val', $store->get('forever_key'));
        self::assertTrue($store->has('forever_key'));
    }

    public function testFileStoreForeverSurvivesRead(): void
    {
        $path = sys_get_temp_dir() . '/kode_cache_fix_' . uniqid();
        $store = new \Kode\Cache\Store\FileStore($path, '', 0);
        try {
            self::assertTrue($store->forever('fk', 'fv'));
            self::assertSame('fv', $store->get('fk'));
            self::assertTrue($store->set('zk', 'zv', 0));
            self::assertSame('zv', $store->get('zk'));
        } finally {
            foreach (new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            ) as $file) {
                @unlink($file->getPathname());
            }
            @rmdir($path);
        }
    }
}
