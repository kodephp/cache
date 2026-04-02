<?php

declare(strict_types=1);

namespace Kode\Cache\Serializer;

/**
 * JSON 序列化器
 *
 * 使用 JSON 编码/解码进行序列化
 */
class JsonSerializer implements SerializerInterface
{
    /** @var bool 是否返回关联数组 */
    protected bool $assoc;

    /** @var int 最大深度 */
    protected int $depth;

    /** @var int JSON 选项 */
    protected int $options;

    /**
     * 构造函数
     *
     * @param bool $assoc 是否返回关联数组
     * @param int $depth 最大深度
     * @param int $options JSON 选项
     */
    public function __construct(bool $assoc = true, int $depth = 512, int $options = 0)
    {
        $this->assoc = $assoc;
        $this->depth = $depth;
        $this->options = $options;
    }

    /**
     * 序列化值为 JSON
     *
     * @param mixed $value 要序列化的值
     * @return string
     */
    public function serialize(mixed $value): string
    {
        return json_encode($value, $this->options, $this->depth);
    }

    /**
     * 反序列化 JSON 字符串
     *
     * @param string $value 要反序列化的字符串
     * @return mixed
     */
    public function unserialize(string $value): mixed
    {
        return json_decode($value, $this->assoc, $this->depth, $this->options);
    }
}
