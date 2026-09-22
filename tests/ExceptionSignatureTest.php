<?php

declare(strict_types=1);

namespace Kode\Cache\Tests;

use Kode\Cache\Exception\CacheException;
use Kode\Cache\Exception\InvalidArgumentException;
use Kode\Cache\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * 异常子类的签名兼容（v1.5.1）。
 *
 * 父类 Kode\Exception\KodeException::make(ErrorCode $code, ?string $msg, array $context, ?Throwable $prev)
 * 已被各包共用；子类若声明 make(string $message, …)，PHP 在「加载该子类」那一刻就报
 * Declaration ... must be compatible —— 不可 try/catch，整进程退出。
 * 于是 throw 一条「缓存驱动未配置」的分支，实际表现是 worker 崩死而非可捕获异常。
 */
final class ExceptionSignatureTest extends TestCase
{
    /** 包内异常类（新增子类时不必改本用例：目录扫描覆盖）。 */
    private function exceptionClasses(): array
    {
        $classes = [];
        foreach (glob(dirname(__DIR__) . '/src/Exception/*.php') ?: [] as $file) {
            $ref = new \ReflectionClass('Kode\\Cache\\Exception\\' . basename($file, '.php'));
            if (!$ref->isInterface()) {
                $classes[] = $ref->getName();
            }
        }

        self::assertNotEmpty($classes, '未扫到任何异常类，用例本身失效了');

        return $classes;
    }

    public function testExceptionClassesLoadWithoutSignatureFatal(): void
    {
        foreach ($this->exceptionClasses() as $class) {
            // 反射会真正加载该类：签名不兼容时这里就 fatal（正是本用例要钉住的回归）。
            $ref = new \ReflectionClass($class);
            self::assertTrue($ref->isSubclassOf(\Kode\Exception\KodeException::class), $class);

            if (!$ref->hasMethod('make') || $ref->getMethod('make')->getDeclaringClass()->getName() === $class) {
                self::fail("{$class} 重新声明了 make()：与父类签名不兼容会让类加载即 fatal，请用具体名字的工厂");
            }
        }
    }

    public function testFactoriesProduceCatchableTypedExceptions(): void
    {
        $cases = [
            [CacheException::cacheError('写盘失败'), 'E5101'],
            [InvalidArgumentException::invalidArgument('参数不对'), 'E5104'],
            [RuntimeException::operationFailed('操作失败'), 'E5105'],
            [InvalidArgumentException::driverNotFound('redis'), 'E5102'],
        ];

        foreach ($cases as [$exception, $code]) {
            self::assertInstanceOf(\Throwable::class, $exception);
            self::assertSame($code, $exception->getErrorCode());
        }
    }

    /** 不支持的驱动类型必须回到「可捕获的异常」，而不是加载异常类时的致命错误。 */
    public function testUnsupportedDriverThrowsCatchableException(): void
    {
        $manager = new \Kode\Cache\CacheManager([
            'default' => 'nope',
            'stores' => ['nope' => ['type' => 'nope']],
        ]);

        try {
            $manager->store('nope');
            self::fail('未知驱动类型应抛异常');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('nope', $e->getMessage());
        }
    }
}
