<?php

declare(strict_types=1);

namespace Kode\Cache\Tests;

use Kode\Cache\CacheManager;
use Kode\Cache\Serializer\IgBinarySerializer;
use Kode\Cache\Serializer\PhpSerializer;
use Kode\Cache\Serializer\SerializerFactory;
use Kode\Cache\Store\FileStore;
use PHPUnit\Framework\TestCase;

/**
 * unserialize 类白名单（对象注入防线）硬化测试
 */
final class UnserializeHardeningTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/kode_unser_' . bin2hex(random_bytes(4));
        mkdir($this->cachePath, 0755, true);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cachePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        @rmdir($this->cachePath);
    }

    public function testPhpSerializerDefaultStillRoundTripsObjects(): void
    {
        $serializer = new PhpSerializer();
        $value = ['obj' => (object) ['a' => 1]];

        $restored = $serializer->unserialize($serializer->serialize($value));

        self::assertInstanceOf(\stdClass::class, $restored['obj']);
        self::assertSame(1, $restored['obj']->a);
    }

    public function testPhpSerializerFalseBlocksAllClasses(): void
    {
        $serializer = new PhpSerializer(false);
        $restored = $serializer->unserialize(serialize((object) ['x' => 1]));

        self::assertSame('__PHP_Incomplete_Class', \get_class($restored));
    }

    public function testPhpSerializerWhitelistAllowsOnlyListedClasses(): void
    {
        $serializer = new PhpSerializer([\DateTime::class]);

        $date = $serializer->unserialize(serialize(new \DateTime('2026-01-01')));
        self::assertInstanceOf(\DateTime::class, $date);

        $other = $serializer->unserialize(serialize((object) ['x' => 1]));
        self::assertSame('__PHP_Incomplete_Class', \get_class($other));
    }

    public function testFactoryPassesAllowedClassesThrough(): void
    {
        $serializer = SerializerFactory::create('php', false);

        $restored = $serializer->unserialize(serialize((object) ['x' => 1]));
        self::assertSame('__PHP_Incomplete_Class', \get_class($restored));

        $default = SerializerFactory::create('php');
        $ok = $default->unserialize(serialize((object) ['x' => 1]));
        self::assertInstanceOf(\stdClass::class, $ok);
    }

    public function testFileStoreAllowedClassesFalseBlocksObjects(): void
    {
        $store = new FileStore($this->cachePath, '', 0, true, 'md5', false);

        self::assertTrue($store->set('k', ['obj' => (object) ['a' => 1]]));
        $got = $store->get('k');

        self::assertIsArray($got);
        self::assertSame('__PHP_Incomplete_Class', \get_class($got['obj']));
        // 标量值不受影响
        $store->set('n', 42);
        self::assertSame(42, $store->get('n'));
    }

    public function testFileStoreDefaultKeepsObjectsForBc(): void
    {
        $store = new FileStore($this->cachePath);
        $store->set('k', (object) ['a' => 1]);

        $got = $store->get('k');

        self::assertInstanceOf(\stdClass::class, $got);
        self::assertSame(1, $got->a);
    }

    public function testCacheManagerHonorsAllowedClassesConfig(): void
    {
        $manager = new CacheManager([
            'default' => 'file',
            'path' => $this->cachePath,
            'allowed_classes' => false,
        ]);

        self::assertTrue($manager->set('k', ['obj' => (object) ['a' => 1]]));
        $got = $manager->get('k');

        self::assertIsArray($got);
        self::assertSame('__PHP_Incomplete_Class', \get_class($got['obj']));
    }

    public function testCorruptPayloadFallsBackGracefully(): void
    {
        // subDir=false ⇒ 文件名即 path/{md5(key)}.php，便于定点破坏
        $store = new FileStore($this->cachePath, '', 0, false);
        self::assertTrue($store->set('bad', 'ok'));

        $file = $this->cachePath . '/' . md5('bad') . '.php';
        self::assertFileExists($file);
        file_put_contents($file, 'O:8:"stdClass":1:{broken');

        // 坏负载不得升级为异常/告警，结构校验兜底返回默认值
        self::assertNull($store->get('bad'));
    }

    public function testIgbinarySerializerDefaultStillRoundTripsObjects(): void
    {
        if (!IgBinarySerializer::isAvailable()) {
            self::markTestSkipped('igbinary 扩展未安装');
        }

        $serializer = new IgBinarySerializer();
        $value = ['obj' => (object) ['a' => 1]];

        $restored = $serializer->unserialize($serializer->serialize($value));

        self::assertInstanceOf(\stdClass::class, $restored['obj']);
        self::assertSame(1, $restored['obj']->a);
    }

    public function testIgbinarySerializerFalseBlocksClasses(): void
    {
        if (!IgBinarySerializer::isAvailable()) {
            self::markTestSkipped('igbinary 扩展未安装');
        }

        $serializer = new IgBinarySerializer(false);

        if (self::igbinaryOptionsSupported()) {
            $restored = $serializer->unserialize($serializer->serialize((object) ['x' => 1]));
            self::assertSame('__PHP_Incomplete_Class', \get_class($restored));
        } else {
            // 旧扩展无法执行类限制：必须拒绝，而不是静默不设防
            $this->expectException(\Kode\Cache\Exception\CacheException::class);
            $serializer->unserialize($serializer->serialize((object) ['x' => 1]));
        }
    }

    public function testIgbinarySerializerWhitelistAllowsOnlyListedClasses(): void
    {
        if (!IgBinarySerializer::isAvailable() || !self::igbinaryOptionsSupported()) {
            self::markTestSkipped('igbinary 扩展未安装或版本过旧（无 allowed_classes 选项）');
        }

        $serializer = new IgBinarySerializer([\DateTime::class]);

        $date = $serializer->unserialize($serializer->serialize(new \DateTime('2026-01-01')));
        self::assertInstanceOf(\DateTime::class, $date);

        $other = $serializer->unserialize($serializer->serialize((object) ['x' => 1]));
        self::assertSame('__PHP_Incomplete_Class', \get_class($other));
    }

    public function testFactoryPassesAllowedClassesToIgbinary(): void
    {
        $serializer = SerializerFactory::create('igbinary', false);
        self::assertInstanceOf(IgBinarySerializer::class, $serializer);

        if (!IgBinarySerializer::isAvailable()) {
            self::markTestSkipped('igbinary 扩展未安装');
        }

        if (!self::igbinaryOptionsSupported()) {
            $this->expectException(\Kode\Cache\Exception\CacheException::class);
            $serializer->unserialize($serializer->serialize(['a' => 1]));
            return;
        }

        $restored = $serializer->unserialize($serializer->serialize((object) ['x' => 1]));
        self::assertSame('__PHP_Incomplete_Class', \get_class($restored));
    }

    /** igbinary >= 3.2.6 才带 allowed_classes 第二参（与实现同口径探测） */
    private static function igbinaryOptionsSupported(): bool
    {
        try {
            return (new \ReflectionFunction('igbinary_unserialize'))->getNumberOfParameters() >= 2;
        } catch (\ReflectionException) {
            return false;
        }
    }
}
