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
 * 注意：igbinary 扩展的 igbinary_unserialize 不支持类限制，
 * 需要同等防护时请改用 php 序列化器或保持缓存后端可信。
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
