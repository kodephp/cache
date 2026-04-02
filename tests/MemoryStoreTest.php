<?php

declare(strict_types=1);

namespace Kode\Cache\Tests;

use Kode\Cache\Store\MemoryStore;
use PHPUnit\Framework\TestCase;

class MemoryStoreTest extends TestCase
{
    protected MemoryStore $store;

    protected function setUp(): void
    {
        $this->store = new MemoryStore('test_', 0);
    }

    public function testSetAndGet(): void
    {
        $this->store->set('key', 'value');
        $this->assertEquals('value', $this->store->get('key'));
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

    public function testPull(): void
    {
        $this->store->set('pull_key', 'pull_value');
        $value = $this->store->pull('pull_key');
        $this->assertEquals('pull_value', $value);
        $this->assertFalse($this->store->has('pull_key'));
    }

    public function testIncrement(): void
    {
        $this->store->set('counter', 10);
        $result = $this->store->increment('counter');
        $this->assertEquals(11, $result);
        $result = $this->store->increment('counter', 5);
        $this->assertEquals(16, $result);
    }

    public function testDecrement(): void
    {
        $this->store->set('counter', 10);
        $result = $this->store->decrement('counter');
        $this->assertEquals(9, $result);
        $result = $this->store->decrement('counter', 3);
        $this->assertEquals(6, $result);
    }

    public function testForever(): void
    {
        $this->store->forever('forever_key', 'forever_value');
        $this->assertEquals('forever_value', $this->store->get('forever_key'));
    }

    public function testGetMultiple(): void
    {
        $this->store->set('key1', 'value1');
        $this->store->set('key2', 'value2');
        $result = $this->store->getMultiple(['key1', 'key2'], 'default');
        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
    }

    public function testSetMultiple(): void
    {
        $values = ['key1' => 'value1', 'key2' => 'value2'];
        $this->store->setMultiple($values);
        $this->assertEquals('value1', $this->store->get('key1'));
        $this->assertEquals('value2', $this->store->get('key2'));
    }

    public function testDeleteMultiple(): void
    {
        $this->store->setMultiple(['key1' => 'v1', 'key2' => 'v2']);
        $this->store->deleteMultiple(['key1', 'key2']);
        $this->assertFalse($this->store->has('key1'));
        $this->assertFalse($this->store->has('key2'));
    }

    public function testGetStorage(): void
    {
        $this->store->set('key', 'value');
        $storage = $this->store->getStorage();
        $this->assertArrayHasKey('test_key', $storage);
    }
}
