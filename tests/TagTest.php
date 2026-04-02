<?php

declare(strict_types=1);

namespace Kode\Cache\Tests;

use Kode\Cache\Tag;
use Kode\Cache\CacheManager;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
    protected string $cachePath;

    protected CacheManager $manager;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/kode_tag_test_' . uniqid();
        mkdir($this->cachePath, 0755, true);
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

    public function testTagSetAndGet(): void
    {
        $tag = $this->manager->tag('test_tag');
        $tag->set('key1', 'value1');
        $this->assertEquals('value1', $tag->get('key1'));
    }

    public function testTagHas(): void
    {
        $tag = $this->manager->tag('test_tag');
        $this->assertFalse($tag->has('key1'));
        $tag->set('key1', 'value1');
        $this->assertTrue($tag->has('key1'));
    }

    public function testTagDelete(): void
    {
        $tag = $this->manager->tag('test_tag');
        $tag->set('key1', 'value1');
        $this->assertTrue($tag->has('key1'));
        $tag->delete('key1');
        $this->assertFalse($tag->has('key1'));
    }

    public function testTagClear(): void
    {
        $tag = $this->manager->tag('test_tag');
        $tag->set('key1', 'value1');
        $tag->set('key2', 'value2');
        $this->assertTrue($tag->has('key1'));
        $this->assertTrue($tag->has('key2'));
        $tag->clear();
        $this->assertFalse($tag->has('key1'));
        $this->assertFalse($tag->has('key2'));
    }

    public function testTagGetItems(): void
    {
        $tag = $this->manager->tag('test_tag');
        $tag->set('key1', 'value1');
        $tag->set('key2', 'value2');
        $items = $tag->getTagItems();
        $this->assertCount(2, $items);
    }

    public function testMultipleTags(): void
    {
        $tag1 = $this->manager->tag('tag1');
        $tag2 = $this->manager->tag('tag2');

        $tag1->set('key1', 'value1');
        $tag2->set('key2', 'value2');

        $this->assertEquals('value1', $tag1->get('key1'));
        $this->assertEquals('value2', $tag2->get('key2'));
    }

    public function testArrayTagName(): void
    {
        $tag = $this->manager->tag(['tag1', 'tag2']);
        $tag->set('key1', 'value1');
        $this->assertEquals('value1', $tag->get('key1'));
    }
}
