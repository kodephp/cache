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
