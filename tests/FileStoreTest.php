<?php

declare(strict_types=1);

namespace Kode\Cache\Tests;

use Kode\Cache\Store\FileStore;
use PHPUnit\Framework\TestCase;

class FileStoreTest extends TestCase
{
    protected string $cachePath;

    protected FileStore $store;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/kode_file_cache_test_' . uniqid();
        mkdir($this->cachePath, 0755, true);
        $this->store = new FileStore($this->cachePath);
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

    public function testSetAndGet(): void
    {
        $this->store->set('test_key', 'test_value');
        $this->assertEquals('test_value', $this->store->get('test_key'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->store->has('nonexistent'));
        $this->store->set('exists', 'value');
        $this->assertTrue($this->store->has('exists'));
    }

    public function testDelete(): void
    {
        $this->store->set('to_delete', 'value');
        $this->assertTrue($this->store->has('to_delete'));
        $this->store->delete('to_delete');
        $this->assertFalse($this->store->has('to_delete'));
    }

    public function testClear(): void
    {
        $this->store->set('key1', 'value1');
        $this->store->set('key2', 'value2');
        $this->store->clear();
        $this->assertFalse($this->store->has('key1'));
        $this->assertFalse($this->store->has('key2'));
    }

    public function testGetMultiple(): void
    {
        $this->store->set('key1', 'value1');
        $this->store->set('key2', 'value2');
        $result = $this->store->getMultiple(['key1', 'key2', 'key3'], 'default');
        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
        $this->assertEquals('default', $result['key3']);
    }

    public function testSetMultiple(): void
    {
        $values = ['key1' => 'value1', 'key2' => 'value2'];
        $this->store->setMultiple($values, 3600);
        $this->assertEquals('value1', $this->store->get('key1'));
        $this->assertEquals('value2', $this->store->get('key2'));
    }

    public function testDeleteMultiple(): void
    {
        $this->store->setMultiple(['key1' => 'v1', 'key2' => 'v2', 'key3' => 'v3']);
        $this->store->deleteMultiple(['key1', 'key2']);
        $this->assertFalse($this->store->has('key1'));
        $this->assertFalse($this->store->has('key2'));
        $this->assertTrue($this->store->has('key3'));
    }

    public function testPull(): void
    {
        $this->store->set('pull_key', 'pull_value');
        $value = $this->store->pull('pull_key');
        $this->assertEquals('pull_value', $value);
        $this->assertFalse($this->store->has('pull_key'));
    }

    public function testTtl(): void
    {
        $this->store->set('ttl_key', 'value', 1);
        $this->assertEquals('value', $this->store->get('ttl_key'));
        sleep(2);
        $this->assertNull($this->store->get('ttl_key'));
    }

    public function testGetPath(): void
    {
        $this->assertEquals($this->cachePath, $this->store->getPath());
    }

    public function testArrayValue(): void
    {
        $data = ['name' => 'test', 'value' => 123];
        $this->store->set('array_key', $data);
        $result = $this->store->get('array_key');
        $this->assertEquals($data, $result);
    }
}
