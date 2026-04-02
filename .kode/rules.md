# Kode Cache 项目开发规范

## 1. 项目信息

- **项目名称**: kode/cache
- **版本**: 1.0.0
- **许可证**: Apache-2.0
- **PHP 版本**: >=8.1
- **作者**: Kode Team

## 2. 命名规范

### 2.1 命名空间
```
Kode\Cache\           # 主命名空间
Kode\Cache\Contract\  # 接口定义
Kode\Cache\Exception\  # 异常类
Kode\Cache\Serializer\ # 序列化器
Kode\Cache\Store\     # 驱动实现
```

### 2.2 类命名
- 类名使用 `PascalCase`
- 接口名以 `Interface` 结尾
- 异常类以 `Exception` 结尾
- 驱动类以 `Store` 结尾

### 2.3 方法命名
- 使用 `camelCase`
- 缓存操作: `get`, `set`, `delete`, `has`, `clear`, `pull`
- 批量操作: `getMultiple`, `setMultiple`, `deleteMultiple`
- 便捷方法: `remember`, `forever`, `increment`, `decrement`, `add`

## 3. 驱动接口

### 3.1 必须实现的方法 (StoreInterface - PSR-16)
```php
interface StoreInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, ?int $ttl = null): bool;
    public function delete(string $key): bool;
    public function has(string $key): bool;
    public function clear(): bool;
    public function getMultiple(iterable $keys, mixed $default = null): iterable;
    public function setMultiple(iterable $values, ?int $ttl = null): bool;
    public function deleteMultiple(iterable $keys): bool;
    public function pull(string $key, mixed $default = null): mixed;
}
```

### 3.2 可选扩展方法
```php
public function increment(string $key, int $step = 1): int|false;
public function decrement(string $key, int $step = 1): int|false;
public function forever(string $key, mixed $value): bool;
public function add(string $key, mixed $value, ?int $ttl = null): bool;
```

## 4. 驱动列表

| 驱动 | 类 | 说明 |
|------|-----|------|
| File | FileStore | 文件缓存，默认驱动 |
| Memory | MemoryStore | 内存缓存，数组存储 |
| Redis | RedisStore | Redis 缓存，分布式 |
| Memcached | MemcachedStore | Memcached 缓存，高并发 |

## 5. 外部依赖

### 5.1 可选依赖
```json
{
    "kode/exception": "统一异常处理 (可选)",
    "kode/limiting": "限流组件 (可选)",
    "ext-redis": "Redis 扩展",
    "ext-memcached": "Memcached 扩展",
    "ext-igbinary": "Igbinary 扩展 (序列化优化)"
}
```

### 5.2 优雅降级
- 异常类优先使用 `kode/exception`，不存在则使用内置基类
- 限流器优先继承 `kode/limiting`，不存在则使用内置实现

## 6. 配置结构

### 6.1 通用配置
```php
[
    'default' => 'file',
    'path' => '/tmp/kode_cache',
    'prefix' => 'kode:',
    'expire' => 3600,
]
```

### 6.2 Redis 配置
```php
[
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null,
    'database' => 0,
    'timeout' => 0.0,
    'persistent' => null,
]
```

### 6.3 Memcached 配置
```php
[
    'host' => '127.0.0.1',
    'port' => 11211,
    'username' => null,
    'password' => null,
]
```

## 7. 分布式组件

### 7.1 分布式锁 (DistributedLock)
- 基于 Redis 实现
- 使用唯一令牌防止误删
- 支持锁延期 (extend)

### 7.2 原子计数器 (AtomicCounter)
- 基于 Redis INCR/DECR
- 适用于高并发计数

### 7.3 限流器 (RateLimiter)
- 基于 Redis 实现
- 支持滑动窗口
- 可与 `kode/limiting` 集成

## 8. 目录结构

```
kode/cache/
├── src/
│   ├── Contract/           # 接口定义
│   │   ├── StoreInterface.php
│   │   ├── TagInterface.php
│   │   ├── LockInterface.php
│   │   └── LimiterInterface.php
│   ├── Exception/          # 异常类
│   │   ├── ExceptionInterface.php
│   │   ├── BaseException.php
│   │   ├── CacheException.php
│   │   ├── InvalidArgumentException.php
│   │   └── RuntimeException.php
│   ├── Serializer/         # 序列化器
│   │   ├── SerializerInterface.php
│   │   ├── SerializerFactory.php
│   │   ├── PhpSerializer.php
│   │   ├── JsonSerializer.php
│   │   └── IgBinarySerializer.php
│   ├── Store/              # 驱动实现
│   │   ├── FileStore.php
│   │   ├── MemoryStore.php
│   │   ├── RedisStore.php
│   │   └── MemcachedStore.php
│   ├── CacheManager.php
│   ├── CacheItem.php
│   ├── Facade.php
│   ├── Tag.php
│   ├── Lock.php
│   ├── DistributedLock.php
│   ├── AtomicCounter.php
│   ├── RateLimiter.php
│   ├── FiberCache.php
│   ├── Config.php
│   └── helpers.php
├── tests/
├── .kode/
│   └── rules.md
├── composer.json
├── phpunit.xml
└── README.md
```

## 9. 异常处理

### 9.1 异常类层次
```
Kode\Exception\Exception (kode/exception)
    └── Kode\Cache\Exception\BaseException
            ├── CacheException
            ├── InvalidArgumentException
            └── RuntimeException
```

### 9.2 异常使用场景
- `CacheException`: 缓存操作失败
- `InvalidArgumentException`: 参数无效或驱动未配置

## 10. 代码风格

- 使用 `declare(strict_types=1)`
- 所有类和方法必须有中文注释
- 属性和方法必须有完整类型声明
- 使用 match 表达式替代 switch
- 常量使用大写下划线命名

## 11. 版本规则

- 遵循语义化版本 (SemVer)
- 主版本.次版本.修订号
- 首次发布: 1.0.0
