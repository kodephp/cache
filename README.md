# Kode Cache

高性能 PHP 缓存组件，支持文件、内存、Redis、Memcached 等多种驱动，可独立使用、框架集成使用或结合 Kode 其他包使用。支持分布式锁、原子计数器、限流器等高级功能。

## 目录

- [特性](#特性)
- [安装](#安装)
- [快速开始](#快速开始)
- [驱动配置](#驱动配置)
- [缓存操作](#缓存操作)
- [缓存标签](#缓存标签)
- [缓存项](#缓存项)
- [序列化器](#序列化器)
- [分布式锁](#分布式锁)
- [原子计数器](#原子计数器)
- [限流器](#限流器)
- [Fiber 支持](#fiber-支持)
- [配置管理](#配置管理)
- [异常处理](#异常处理)
- [框架集成](#框架集成)
- [API 参考](#api-参考)
- [目录结构](#目录结构)
- [测试](#测试)
- [性能建议](#性能建议)
- [版本历史](#版本历史)
- [贡献指南](#贡献指南)
- [许可证](#许可证)

---

## 特性

- **多驱动支持**: File、Memory、Redis、Memcached
- **PSR-16 规范**: 遵循 PHP 标准缓存接口
- **序列化支持**: PHP serialize、JSON、igbinary
- **分布式支持**: Redis 分布式锁、原子计数器、限流器
- **Fiber 支持**: 在 Fiber 中安全使用缓存
- **缓存标签**: 分组管理和批量操作
- **链式调用**: 简洁的 API 设计
- **PHP 8.1+**: 利用最新 PHP 特性和性能优化
- **优雅降级**: 可选依赖 `kode/exception`、`kode/limiting`

---

## 安装

### 环境要求

- PHP >= 8.1
- 可选扩展: ext-redis, ext-memcached, ext-igbinary

### 安装命令

```bash
composer require kode/cache
```

### 推荐安装

```bash
# 安装完整功能 (包含所有可选扩展提示)
composer require kode/cache

# 如需使用 Redis 驱动
pecl install redis

# 如需使用 Memcached 驱动
pecl install memcached

# 如需更高效的序列化
pecl install igbinary
```

---

## 快速开始

### 基本使用

```php
use Kode\Cache\CacheManager;

// 创建缓存管理器
$cache = new CacheManager([
    'default' => 'file',
    'path' => '/tmp/cache',
    'prefix' => 'app:',
    'expire' => 3600,
]);

// 设置缓存
$cache->set('key', 'value', 3600);

// 获取缓存
$value = $cache->get('key', 'default');

// 判断存在
if ($cache->has('key')) {
    // ...
}

// 删除缓存
$cache->delete('key');

// 获取并删除
$value = $cache->pull('key');

// 自动获取/设置
$value = $cache->remember('key', function() {
    return expensive_computation();
}, 3600);
```

### 使用 Facade

```php
use Kode\Cache\Facade as Cache;

// 设置
Cache::set('name', 'value', 3600);

// 获取
$name = Cache::get('name', 'default');

// 删除
Cache::delete('name');

// 自增/自减
Cache::set('counter', 0);
Cache::increment('counter');
Cache::decrement('counter', 5);

// 永久缓存
Cache::forever('user_avatar', $avatarUrl);

// 清除所有
Cache::flush();
```

### 使用辅助函数

```php
// 设置缓存
cache_set('key', 'value', 3600);

// 获取缓存
$value = cache_get('key', 'default');

// 判断存在
if (cache_has('key')) {
    // ...
}

// 删除缓存
cache_delete('key');

// 自动获取/设置
$value = cache_remember('key', function() {
    return expensive_operation();
}, 3600);
```

---

## 驱动配置

### 文件驱动 (默认)

适用于低流量、简单场景、开发测试环境。

```php
$cache = new CacheManager([
    'default' => 'file',
    'path' => '/tmp/kode_cache',     // 缓存目录
    'prefix' => 'app:',               // 缓存前缀
    'expire' => 3600,                // 默认过期时间 (秒)
    'subDir' => true,                 // 是否使用子目录 (减少单目录文件数)
    'hashType' => 'md5',             // 哈希算法: md5/sha1/xxh3
]);
```

**特点**:
- 无需额外扩展
- 支持子目录哈希，减少单目录文件数
- 支持多种哈希算法

### 内存驱动 (Array)

适用于单机进程、测试环境、请求内共享数据。不支持持久化。

```php
$cache = new CacheManager([
    'default' => 'memory',
    'prefix' => 'mem:',
    'expire' => 0,
]);
```

**特点**:
- 性能最高
- 仅在当前进程有效
- 适合测试或请求内缓存

### Redis 驱动

适用于分布式环境、高性能场景、生产环境。

```php
$cache = new CacheManager([
    'default' => 'redis',
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null,               // Redis 密码
    'database' => 0,                  // 数据库编号
    'prefix' => 'kode:',             // 缓存前缀
    'expire' => 3600,                // 默认过期时间
    'timeout' => 0.0,                // 连接超时
    'persistent' => null,            // 持久化连接 ID
]);
```

**特点**:
- 支持分布式
- 性能优异
- 支持原子操作
- 需要 ext-redis 或 predis/predis

### Memcached 驱动

适用于高并发、分布式缓存场景，比 Redis 更轻量。

```php
$cache = new CacheManager([
    'default' => 'memcached',
    'memcached_host' => '127.0.0.1',
    'memcached_port' => 11211,
    'memcached_username' => null,    // SASL 用户名
    'memcached_password' => null,    // SASL 密码
    'prefix' => 'kode:',
    'expire' => 3600,
]);
```

**特点**:
- 专为高并发设计
- 支持 SASL 认证
- 比 Redis 更轻量
- 需要 ext-memcached

### 多驱动配置

```php
$cache = new CacheManager([
    'default' => 'file',
    'stores' => [
        'file' => [
            'type' => 'file',
            'path' => '/tmp/cache',
            'prefix' => 'app:',
        ],
        'redis' => [
            'type' => 'redis',
            'host' => '127.0.0.1',
            'port' => 6379,
        ],
        'memory' => [
            'type' => 'memory',
        ],
        'memcached' => [
            'type' => 'memcached',
            'host' => '127.0.0.1',
            'port' => 11211,
        ],
    ],
]);

// 使用不同驱动
$cache->store('file')->set('key', 'value');
$cache->store('redis')->set('key', 'value');
$cache->store('memory')->set('key', 'value');

// 默认驱动
$cache->get('key');
```

---

## 缓存操作

### 基础操作

```php
// 设置缓存
$cache->set('key', 'value', 3600);           // 3600 秒后过期
$cache->set('key', 'value');                  // 使用默认过期时间
$cache->put('key', 'value', 3600);           // 显式指定过期时间

// 获取缓存
$value = $cache->get('key');
$value = $cache->get('key', 'default');      // 不存在时返回默认值

// 检查存在
$exists = $cache->has('key');

// 删除缓存
$cache->delete('key');

// 获取并删除
$value = $cache->pull('key');
```

### 批量操作

```php
// 批量获取
$values = $cache->many(['key1', 'key2', 'key3'], 'default');
$values = $cache->getMultiple(['key1', 'key2', 'key3'], 'default');

// 批量设置
$cache->putMany(['key1' => 'value1', 'key2' => 'value2'], 3600);
$cache->setMultiple(['key1' => 'value1', 'key2' => 'value2'], 3600);

// 批量删除
$cache->deleteMultiple(['key1', 'key2']);
```

### 自增/自减

适用于计数器场景。

```php
$cache->set('counter', 0);
$cache->increment('counter');      // +1
$cache->increment('counter', 5);  // +5
$cache->decrement('counter');     // -1
$cache->decrement('counter', 3);   // -3
```

### 自动获取/设置

```php
// 如果不存在则设置并返回
$value = $cache->remember('key', function() {
    return expensive_computation();
}, 3600);

// 永久缓存
$value = $cache->rememberForever('key', function() {
    return compute_once();
});
```

### 不存在则添加

```php
// 仅当键不存在时设置成功
$result = $cache->store()->add('key', 'value', 3600);
```

---

## 缓存标签

标签用于对缓存进行分组管理，适合批量清除相关缓存。

```php
// 创建标签
$tag = $cache->tag('products');

// 设置带标签的缓存
$tag->set('product_1', $product1);
$tag->set('product_2', $product2);

// 获取
$product = $tag->get('product_1');

// 判断
if ($tag->has('product_1')) {
    // ...
}

// 删除单个
$tag->delete('product_1');

// 清除标签下所有缓存
$tag->clear();

// 多个标签
$tag = $cache->tag(['user', 'vip']);
$tag->set('user_1', $userData);

// 获取标签下的所有键
$keys = $tag->getTagItems();
```

**注意**: 标签功能需要在存储中额外维护标签与键的映射关系。

---

## 缓存项

用于更精细化地控制缓存项。

```php
use Kode\Cache\CacheItem;

$item = CacheItem::create('key')
    ->set($value)
    ->setTtl(3600);

$cache->set('key', $item->get(), $item->getTtl());

// 使用 DateInterval
$item->expiresAfter(new \DateInterval('PT1H'));

// 设置绝对过期时间
$item->expiresAt(time() + 3600);

// 检查是否过期
if ($item->isExpired()) {
    // ...
}

// 转换为数组
$array = $item->toArray();
```

---

## 序列化器

支持多种序列化方式，可根据需求选择。

```php
use Kode\Cache\Serializer\PhpSerializer;       // PHP 原生序列化 (默认)
use Kode\Cache\Serializer\JsonSerializer;       // JSON 序列化
use Kode\Cache\Serializer\IgBinarySerializer;   // igbinary 扩展 (最高效)

// 检查可用性
if (\Kode\Cache\Serializer\SerializerFactory::isAvailable('igbinary')) {
    $serializer = \Kode\Cache\Serializer\SerializerFactory::create('igbinary');
}

// JSON 序列化器可配置
$jsonSerializer = new JsonSerializer(
    assoc: true,   // 返回关联数组
    depth: 512,    // 最大深度
    options: 0     // JSON 选项 (如 JSON_UNESCAPED_UNICODE)
);
```

**序列化器对比**:

| 类型 | 优点 | 缺点 |
|------|------|------|
| PHP | 无依赖 | 性能一般 |
| JSON | 可跨语言 | 不支持资源类型 |
| igbinary | 最高效 | 需要扩展 |

---

## 分布式锁

### Redis 分布式锁

适用于多进程、跨机器的分布式场景。

```php
use Kode\Cache\DistributedLock;

// 创建分布式锁 (需要 Redis 驱动)
$redisStore = new \Kode\Cache\Store\RedisStore('127.0.0.1', 6379);
$lock = new DistributedLock($redisStore, 'resource_lock', 10);

// 获取锁
if ($lock->acquire()) {
    try {
        // 临界区操作
        do_something();
    } finally {
        $lock->release();
    }
}

// 阻塞获取锁 (最多等待 5 秒)
$lock = new DistributedLock($redisStore, 'blocking_lock', 10);
if ($lock->block(5)) {
    try {
        do_something();
    } finally {
        $lock->release();
    }
}

// 锁延期 (延长锁的持有时间)
$lock->extend(30);

// 检查锁是否被持有
if ($lock->isOwned()) {
    // ...
}
```

### 本地锁

适用于单机单进程的同步场景。

```php
use Kode\Cache\Lock;

$store = $cache->store();
$lock = new Lock($store, 'local_lock', 10);

if ($lock->acquire()) {
    try {
        do_something();
    } finally {
        $lock->release();
    }
}

// 阻塞获取
if ($lock->block(5)) {
    // ...
}
```

---

## 原子计数器

基于 Redis 的原子操作，适用于高并发计数。

```php
use Kode\Cache\AtomicCounter;

$redisStore = new \Kode\Cache\Store\RedisStore('127.0.0.1', 6379);
$counter = new AtomicCounter($redisStore, 'page_views');

// 自增/自减
$counter->increment();        // +1
$counter->increment(10);      // +10
$counter->decrement();       // -1
$counter->decrement(5);       // -5

// 获取当前值
$views = $counter->get();

// 重置
$counter->reset();
```

---

## 限流器

基于 Redis 的请求限流，适用于 API 限流等场景。

```php
use Kode\Cache\RateLimiter;

$redisStore = new \Kode\Cache\Store\RedisStore('127.0.0.1', 6379);
$limiter = new RateLimiter($redisStore, 'api_limit', 100, 60); // 60 秒内最多 100 次

// 检查是否超限
if ($limiter->tooManyAttempts()) {
    $retryAfter = $limiter->availableIn();
    throw new \Exception("请 {$retryAfter} 秒后重试");
}

// 记录请求
$limiter->hit();

// 获取剩余次数
$remaining = $limiter->remaining();

// 获取已用次数
$attempts = $limiter->attempts();

// 重置
$limiter->clear();
```

**限流器可选集成**: 如果安装了 `kode/limiting`，将自动使用其高级功能。

---

## Fiber 支持

在 Fiber 中安全使用缓存实例。

```php
use Kode\Cache\FiberCache;

// 获取实例
$cache = FiberCache::getInstance('file', ['path' => '/tmp/cache']);

// 使用方式与常规缓存相同
$cache->set('key', 'value', 3600);
$value = $cache->get('key');

// 获取底层 Store
$store = $cache->getStore();

// 清除所有 Fiber 实例
FiberCache::clearInstances();
```

---

## 配置管理

```php
use Kode\Cache\Config;

// 设置配置
Config::set('cache.driver', 'redis');
Config::set('cache.prefix', 'app:');

// 获取配置
$driver = Config::get('cache.driver');
$prefix = Config::get('cache.prefix', 'kode:');

// 检查存在
if (Config::has('cache.driver')) {
    // ...
}

// 批量设置
Config::set([
    'cache.driver' => 'redis',
    'cache.host' => '127.0.0.1',
]);

// 获取所有配置
$all = Config::all();

// 重置
Config::reset();

// 加载配置
Config::load($configArray);
```

---

## 异常处理

```php
use Kode\Cache\Exception\CacheException;
use Kode\Cache\Exception\InvalidArgumentException;

try {
    $cache->store('nonexistent')->get('key');
} catch (InvalidArgumentException $e) {
    echo "驱动未配置: " . $e->getMessage();
}

try {
    $cache->set('key', $value);
} catch (CacheException $e) {
    echo "缓存操作失败: " . $e->getMessage();
}
```

**异常类说明**:

| 异常类 | 使用场景 |
|--------|----------|
| `CacheException` | 缓存操作失败，如文件写入失败、Redis 连接失败 |
| `InvalidArgumentException` | 参数无效或驱动未配置 |

**可选集成**: 如果安装了 `kode/exception`，将使用其作为异常基类。

---

## 框架集成

### ThinkPHP 8

```php
use Kode\Cache\CacheManager;

// 方式1: 直接使用
$cache = new CacheManager([
    'default' => 'redis',
    'host' => config('cache.redis.host'),
    'port' => config('cache.redis.port'),
    'password' => config('cache.redis.password'),
]);

// 方式2: 注册为服务 (在 ServiceProvider 中)
Container::getInstance()->bind('cache', function() {
    return new CacheManager([
        'default' => 'redis',
        'host' => config('cache.redis.host'),
        'port' => config('cache.redis.port'),
    ]);
});

// 方式3: Facade
Facade::alias('cache', \Kode\Cache\Facade::class);
```

### Laravel

```php
use Kode\Cache\Facade as Cache;

// 使用方式与 Laravel Cache 相同
$value = Cache::remember('key', function() {
    return Model::find(1);
}, 3600);
```

### 原生 PHP

```php
// 直接使用
require 'vendor/autoload.php';

use Kode\Cache\CacheManager;

$cache = new CacheManager(['default' => 'file']);
$cache->set('key', 'value', 3600);
```

---

## API 参考

### CacheManager

| 方法 | 说明 | 返回值 |
|------|------|--------|
| `store($name)` | 获取指定驱动的缓存实例 | StoreInterface |
| `get($key, $default)` | 获取缓存 | mixed |
| `set($key, $value, $ttl)` | 设置缓存 | bool |
| `put($key, $value, $ttl)` | 设置缓存 (显式TTL) | bool |
| `has($key)` | 检查缓存是否存在 | bool |
| `delete($key)` | 删除缓存 | bool |
| `pull($key, $default)` | 获取并删除 | mixed |
| `clear()` | 清空所有缓存 | bool |
| `many($keys, $default)` | 批量获取 | iterable |
| `putMany($values, $ttl)` | 批量设置 | bool |
| `remember($key, $callback, $ttl)` | 不存在时设置 | mixed |
| `rememberForever($key, $callback)` | 永久缓存 | mixed |
| `increment($key, $step)` | 自增 | int\|false |
| `decrement($key, $step)` | 自减 | int\|false |
| `forever($key, $value)` | 永久缓存 | bool |
| `forget($key)` | 删除缓存 | bool |
| `flush()` | 清空所有缓存 | bool |
| `tag($name)` | 获取标签 | Tag |

### StoreInterface (驱动接口)

| 方法 | 说明 | 返回值 |
|------|------|--------|
| `get($key, $default)` | 获取缓存 | mixed |
| `set($key, $value, $ttl)` | 设置缓存 | bool |
| `put($key, $value, $ttl)` | 设置缓存 | bool |
| `delete($key)` | 删除缓存 | bool |
| `has($key)` | 检查存在 | bool |
| `clear()` | 清空 | bool |
| `getMultiple($keys, $default)` | 批量获取 | iterable |
| `setMultiple($values, $ttl)` | 批量设置 | bool |
| `deleteMultiple($keys)` | 批量删除 | bool |
| `pull($key, $default)` | 获取并删除 | mixed |
| `add($key, $value, $ttl)` | 不存在时添加 | bool |
| `increment($key, $step)` | 自增 | int\|false |
| `decrement($key, $step)` | 自减 | int\|false |
| `forever($key, $value)` | 永久缓存 | bool |

---

## 目录结构

```
kode/cache/
├── src/
│   ├── Contract/               # 接口定义
│   │   ├── StoreInterface.php
│   │   ├── TagInterface.php
│   │   ├── LockInterface.php
│   │   └── LimiterInterface.php
│   ├── Exception/             # 异常类
│   │   ├── ExceptionInterface.php
│   │   ├── BaseException.php
│   │   ├── CacheException.php
│   │   ├── InvalidArgumentException.php
│   │   └── RuntimeException.php
│   ├── Serializer/            # 序列化器
│   │   ├── SerializerInterface.php
│   │   ├── SerializerFactory.php
│   │   ├── PhpSerializer.php
│   │   ├── JsonSerializer.php
│   │   └── IgBinarySerializer.php
│   ├── Store/                 # 驱动实现
│   │   ├── FileStore.php
│   │   ├── MemoryStore.php
│   │   ├── RedisStore.php
│   │   └── MemcachedStore.php
│   ├── CacheManager.php        # 缓存管理器
│   ├── CacheItem.php           # 缓存项
│   ├── Facade.php              # 门面类
│   ├── Tag.php                 # 标签支持
│   ├── Lock.php                # 本地锁
│   ├── DistributedLock.php      # 分布式锁
│   ├── AtomicCounter.php        # 原子计数器
│   ├── RateLimiter.php         # 限流器
│   ├── FiberCache.php           # Fiber 支持
│   ├── Config.php              # 配置管理
│   └── helpers.php             # 辅助函数
├── tests/                       # 单元测试
├── .kode/
│   └── rules.md                # 项目规则
├── composer.json
├── phpunit.xml
├── .gitignore
└── README.md
```

---

## 测试

### 运行所有测试

```bash
./vendor/bin/phpunit
```

### 运行指定测试

```bash
./vendor/bin/phpunit tests/FileStoreTest.php
```

### 显示详细输出

```bash
./vendor/bin/phpunit --testdox
```

### 生成覆盖率报告

```bash
./vendor/bin/phpunit --coverage-html coverage
```

---

## 性能建议

| 驱动 | 适用场景 | 性能 | 持久化 |
|------|----------|------|--------|
| File | 低流量、简单场景、开发测试 | 中 | 是 |
| Memory | 请求内共享、测试 | 最高 | 否 |
| Redis | 生产环境、分布式 | 高 | 是 |
| Memcached | 高并发、分布式 | 高 | 是 |

### 优化建议

1. **文件驱动**: 适用于低流量场景，注意定期清理过期文件
2. **内存驱动**: 适用于请求内共享数据，不支持持久化
3. **Redis 驱动**: 适用于生产环境，推荐使用，支持分布式
4. **Memcached 驱动**: 适用于高并发分布式缓存，比 Redis 更轻量

---

## 版本历史

### v1.0.0 (2024-04-02)

- 初始版本
- 支持 File、Memory、Redis、Memcached 驱动
- 支持分布式锁 (DistributedLock)
- 支持原子计数器 (AtomicCounter)
- 支持限流器 (RateLimiter)
- 支持缓存标签 (Tag)
- 支持 Fiber (FiberCache)
- 支持可选集成 `kode/exception` 和 `kode/limiting`

---

## 贡献指南

欢迎提交 Issue 和 Pull Request。

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 创建 Pull Request

---

## 许可证

本项目基于 [Apache-2.0](LICENSE) 许可证开源。

```
Copyright 2024 Kode Team

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```

---

## 联系方式

- **项目主页**: https://github.com/kode/cache
- **问题反馈**: https://github.com/kode/cache/issues
- **邮箱**: team@kode.cn
