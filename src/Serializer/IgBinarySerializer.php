<?php

declare(strict_types=1);

namespace Kode\Cache\Serializer;

/**
 * IgBinary 序列化器
 *
 * 使用 IgBinary 扩展进行序列化，性能更高
 * 需要安装 igbinary 扩展
 *
 * 类限制（对象注入防线）与 PhpSerializer 同语义：
 * - true：不限制（默认，BC）；含对象的缓存值需保持此值
 * - false：禁止一切类实例化（缓存值仅标量/数组时推荐，最安全）
 * - ['Foo', 'Bar']：类名白名单
 *
 * igbinary >= 3.2.6 的 igbinary_unserialize 支持 ['allowed_classes' => ...]
 * 第二参数（与原生 unserialize 同名选项）；更旧的扩展仅在「不限制」时可用，
 * 试图在旧扩展上启用类限制会直接抛异常而不是静默降级为不设防。
 */
class IgBinarySerializer implements SerializerInterface
{
    /**
     * @param bool|list<string> $allowedClasses 类限制（见类注释），语义与 PhpSerializer 一致
     */
    public function __construct(private readonly bool|array $allowedClasses = true)
    {
    }

    /**
     * 检查扩展是否可用
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('igbinary');
    }

    /**
     * 序列化值
     *
     * @param mixed $value 要序列化的值
     * @return string
     * @throws \Kode\Cache\Exception\CacheException 扩展未安装时抛出
     */
    #[\Override]
    public function serialize(mixed $value): string
    {
        if (!self::isAvailable()) {
            throw new \Kode\Cache\Exception\CacheException(
                'Igbinary 扩展未安装，请运行: pecl install igbinary'
            );
        }

        return igbinary_serialize($value);
    }

    /**
     * 反序列化字符串
     *
     * @param string $value 要反序列化的字符串
     * @return mixed
     * @throws \Kode\Cache\Exception\CacheException 扩展未安装、或旧扩展上启用类限制时抛出
     */
    #[\Override]
    public function unserialize(string $value): mixed
    {
        if (!self::isAvailable()) {
            throw new \Kode\Cache\Exception\CacheException(
                'Igbinary 扩展未安装，请运行: pecl install igbinary'
            );
        }

        if (self::optionsSupported()) {
            /** @var mixed $data */
            $data = igbinary_unserialize($value, ['allowed_classes' => $this->allowedClasses]); // @phpstan-ignore arguments.count (igbinary 3.2.6+ 支持第二参 options，phpstorm-stubs 未收录)

            return $data;
        }

        if ($this->allowedClasses !== true) {
            throw new \Kode\Cache\Exception\CacheException(
                'igbinary < 3.2.6 不支持 allowed_classes 类限制；' .
                '请升级 igbinary 扩展，或改用 PhpSerializer/JsonSerializer'
            );
        }

        /** @var mixed $data */
        $data = igbinary_unserialize($value);

        return $data;
    }

    /**
     * igbinary >= 3.2.6 才支持 ['allowed_classes' => ...] 选项（静态探测一次）
     */
    private static function optionsSupported(): bool
    {
        static $supported = null;
        if ($supported === null) {
            try {
                $supported = (new \ReflectionFunction('igbinary_unserialize'))->getNumberOfParameters() >= 2;
            } catch (\ReflectionException) {
                $supported = false;
            }
        }

        return $supported;
    }
}
