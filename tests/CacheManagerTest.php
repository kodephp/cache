<?php

declare(strict_types=1);

namespace Kode\Cache\Tests;

use Kode\Cache\CacheManager;
use Kode\Cache\Store\FileStore;
use Kode\Cache\Store\MemoryStore;
use PHPUnit\Framework\TestCase;

class CacheManagerTest extends TestCase
{
    protected string $cachePath;

    protected CacheManager $manager;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/kode_cache_test_' . uniqid();
        $this->manager = new CacheManager([
            'default' => 'file',
            'path' => $this->cachePath,
        ]);
    }

    protected function tearDown(): void
    {
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

    public function testStoreCreation(): void
    {
        $store = $this->manager->store('file');
        $this->assertInstanceOf(FileStore::class, $store);
    }

    public function testMemoryStoreCreation(): void
    {
        $store = $this->manager->store('memory');
        $this->assertInstanceOf(MemoryStore::class, $store);
    }

    public function testGetDefaultDriver(): void
    {
        $this->assertEquals('file', $this->manager->getDefaultDriver());
    }

    /** array 是 memory 的内置别名（Laravel 习惯：CACHE_DRIVER=array 走进程内存储）。 */
    public function testArrayDriverIsAliasedToMemory(): void
    {
        $manager = new CacheManager([
            'default' => 'array',
            'stores' => ['array' => ['type' => 'array']],
        ]);

        $this->assertInstanceOf(MemoryStore::class, $manager->store('array'));
        // 真的可用，而不是只把类型认下来。
        $manager->set('k', 'v', 10);
        $this->assertEquals('v', $manager->get('k'));
    }

    /** 未知驱动仍要报错（别名表不得顺手放宽）；且报错文案得是给人看的，不是「未知错误」。 */
    public function testUnknownDriverStillThrows(): void
    {
        $manager = new CacheManager([
            'default' => 'redis2',
            'stores' => ['redis2' => ['type' => 'redis2']],
        ]);

        try {
            $manager->store('redis2');
            $this->fail('未知驱动类型应抛异常');
        } catch (\Kode\Cache\Exception\InvalidArgumentException $e) {
            $this->assertStringContainsString('redis2', $e->getMessage());
        }
    }

    /** store 未配置时的报错同样要带驱动名（构造参数错位会把消息吞成「未知错误」）。 */
    public function testMissingStoreConfigReportsDriverName(): void
    {
        $manager = new CacheManager(['default' => 'file', 'path' => $this->cachePath]);

        try {
            $manager->store('nope');
            $this->fail('未配置的 store 应抛异常');
        } catch (\Kode\Cache\Exception\InvalidArgumentException $e) {
            $this->assertStringContainsString('nope', $e->getMessage());
        }
    }

    /** extend() 的入参校验也要说清是哪个类不合规（旧写法把消息塞进了错误码位）。 */
    public function testCustomDriverMustImplementStoreInterface(): void
    {
        try {
            CacheManager::extend('bogus', \stdClass::class);
            $this->fail('未实现 StoreInterface 的类不应被接受为自定义驱动');
        } catch (\Kode\Cache\Exception\InvalidArgumentException $e) {
            $this->assertStringContainsString('stdClass', $e->getMessage());
        }
    }

    public function testSetDefaultDriver(): void
    {
        $this->manager->setDefaultDriver('memory');
        $this->assertEquals('memory', $this->manager->getDefaultDriver());
    }

    public function testBasicOperations(): void
    {
        $this->manager->set('test_key', 'test_value');
        $this->assertTrue($this->manager->has('test_key'));
        $this->assertEquals('test_value', $this->manager->get('test_key'));
        $this->manager->delete('test_key');
        $this->assertFalse($this->manager->has('test_key'));
    }

    public function testGetWithDefault(): void
    {
        $value = $this->manager->get('nonexistent_key', 'default_value');
        $this->assertEquals('default_value', $value);
    }

    public function testPull(): void
    {
        $this->manager->set('pull_key', 'pull_value');
        $value = $this->manager->pull('pull_key');
        $this->assertEquals('pull_value', $value);
        $this->assertFalse($this->manager->has('pull_key'));
    }

    public function testRemember(): void
    {
        $called = false;
        $value = $this->manager->remember('remember_key', function () use (&$called) {
            $called = true;
            return 'computed_value';
        });

        $this->assertTrue($called);
        $this->assertEquals('computed_value', $value);

        $called = false;
        $value = $this->manager->remember('remember_key', function () use (&$called) {
            $called = true;
            return 'different_value';
        });

        $this->assertFalse($called);
        $this->assertEquals('computed_value', $value);
    }

    public function testIncrement(): void
    {
        $this->manager->set('counter', 10);
        $result = $this->manager->increment('counter');
        $this->assertEquals(11, $result);
        $result = $this->manager->increment('counter', 5);
        $this->assertEquals(16, $result);
    }

    public function testDecrement(): void
    {
        $this->manager->set('counter', 10);
        $result = $this->manager->decrement('counter');
        $this->assertEquals(9, $result);
        $result = $this->manager->decrement('counter', 3);
        $this->assertEquals(6, $result);
    }

    public function testClear(): void
    {
        $this->manager->set('key1', 'value1');
        $this->manager->set('key2', 'value2');
        $this->assertTrue($this->manager->has('key1'));
        $this->assertTrue($this->manager->has('key2'));
        $this->manager->clear();
        $this->assertFalse($this->manager->has('key1'));
        $this->assertFalse($this->manager->has('key2'));
    }
}
