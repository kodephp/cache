<?php

declare(strict_types=1);

namespace Kode\Cache\Store;

/**
 * unserialize 反序列化防线
 *
 * 所有走 PHP 原生 serialize 的存储读取路径统一经本 trait 反序列化，
 * 通过 allowed_classes 限制可实例化的类，阻断缓存后端被投毒时的
 * 对象注入（gadget chain / __destruct 利用面）。
 *
 * - 默认 true：与历史行为一致，已有缓存对象不受影响（BC）；
 * - 生产建议：缓存值不含对象时配置 false；含对象时用类名白名单数组。
 *
 * @package Kode\Cache\Store
 */
trait UnserializeGuard
{
    /** @var bool|list<string> 允许反序列化的类：true 不限制，false 禁止一切类，数组为白名单 */
    protected bool|array $allowedClasses = true;

    /**
     * 受控反序列化
     */
    protected function unserializeGuarded(string $value): mixed
    {
        // 缓存读路径必须是全函数：坏负载（半截写入/磁盘满/序列化版本漂移）
        // 只应返回未定义值，由调用方结构校验兜底，不得让 E_WARNING 升级为异常
        return @unserialize($value, ['allowed_classes' => $this->allowedClasses]);
    }
}
