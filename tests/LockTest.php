<?php

declare(strict_types=1);

namespace Kode\Cache\Tests;

use Kode\Cache\CacheManager;
use Kode\Cache\Lock;
use PHPUnit\Framework\TestCase;

class LockTest extends TestCase
{
    protected Lock $lock;

    protected string $cachePath;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/kode_lock_test_' . uniqid();
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
        $manager = new CacheManager(['default' => 'file', 'path' => $this->cachePath]);
        $this->lock = new Lock($manager->store(), 'test_lock', 10);
    }

    protected function tearDown(): void
    {
        if (isset($this->lock)) {
            $this->lock->release();
        }
        $this->clearDirectory($this->cachePath);
    }

    protected function clearDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                unlink($file->getPathname());
            }
        }

        @rmdir($path);
    }

    public function testAcquire(): void
    {
        $this->assertTrue($this->lock->acquire());
        $this->assertTrue($this->lock->isOwned());
    }

    public function testRelease(): void
    {
        $this->lock->acquire();
        $this->assertTrue($this->lock->release());
        $this->assertFalse($this->lock->isOwned());
    }

    public function testIsOwned(): void
    {
        $this->assertFalse($this->lock->isOwned());
        $this->lock->acquire();
        $this->assertTrue($this->lock->isOwned());
    }

    public function testGetToken(): void
    {
        $this->lock->acquire();
        $token = $this->lock->getToken();
        $this->assertNotNull($token);
        $this->assertIsString($token);
    }

    public function testBlock(): void
    {
        $manager = new CacheManager(['default' => 'memory']);
        $store = $manager->store('memory');
        $lock1 = new Lock($store, 'blocking_lock', 10);
        $lock2 = new Lock($store, 'blocking_lock', 10);

        $lock1->acquire();
        $result = $lock2->block(1);
        $this->assertFalse($result);

        $lock1->release();
        $result = $lock2->block(1);
        $this->assertTrue($result);
    }

    public function testGetName(): void
    {
        $this->assertEquals('test_lock', $this->lock->getName());
    }

    public function testGetSeconds(): void
    {
        $this->assertEquals(10, $this->lock->getSeconds());
    }
}
