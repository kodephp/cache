<?php

declare(strict_types=1);

namespace Kode\Cache\Serializer;

/**
 * PHP 原生序列化器
 *
 * 使用 PHP 原生的 serialize/unserialize 函数。
 *
 * unserialize 默认带 allowed_classes 限制（对象注入防线）：
 * - true：不限制（默认，BC）；含对象的缓存值需保持此值
 * - false：禁止一切类实例化（缓存值仅标量/数组时推荐，最安全）
 * - ['Foo', 'Bar']：类名白名单
 *
     * 注意：IgBinarySerializer 自 v2.0.6 起支持同样的类限制
     * （igbinary >= 3.2.6 的 allowed_classes 选项），语义与此一致。
 */
class PhpSerializer implements SerializerInterface
{
    public function __construct(private readonly bool|array $allowedClasses = true)
    {
    }
    /**
     * 序列化值
     *
     * @param mixed $value 要序列化的值
     * @return string
     */
    #[\Override]
    public function serialize(mixed $value): string
    {
        return serialize($value);
    }

    /**
     * 反序列化字符串
     *
     * @param string $value 要反序列化的字符串
     * @return mixed
     */
    #[\Override]
    public function unserialize(string $value): mixed
    {
        return unserialize($value, ['allowed_classes' => $this->allowedClasses]);
    }
}
