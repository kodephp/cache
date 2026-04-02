<?php

declare(strict_types=1);

namespace Kode\Cache\Tests;

use Kode\Cache\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
    }

    public function testGetAndSet(): void
    {
        Config::set('database.host', 'localhost');
        $this->assertEquals('localhost', Config::get('database.host'));
    }

    public function testGetWithDefault(): void
    {
        $value = Config::get('nonexistent', 'default');
        $this->assertEquals('default', $value);
    }

    public function testHas(): void
    {
        Config::set('test.key', 'value');
        $this->assertTrue(Config::has('test.key'));
        $this->assertFalse(Config::has('nonexistent'));
    }

    public function testSetArray(): void
    {
        Config::set([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
        $this->assertEquals('value1', Config::get('key1'));
        $this->assertEquals('value2', Config::get('key2'));
    }

    public function testAll(): void
    {
        Config::set('key1', 'value1');
        Config::set('key2', 'value2');
        $all = Config::all();
        $this->assertArrayHasKey('key1', $all);
        $this->assertArrayHasKey('key2', $all);
    }

    public function testReset(): void
    {
        Config::set('key', 'value');
        Config::reset();
        $this->assertFalse(Config::has('key'));
    }

    public function testLoad(): void
    {
        Config::load(['loaded' => 'config']);
        $this->assertEquals('config', Config::get('loaded'));
    }
}
