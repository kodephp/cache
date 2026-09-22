<?php

declare(strict_types=1);

namespace Kode\Cache\Tests;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use PHPUnit\Framework\TestCase;

/**
 * 依赖约束守卫：本包 composer.json 声明的 kode/* 约束必须能容纳当前生态实际发布的版本。
 *
 * 约束写死后不会随兄弟包升主版本而自动跟进，一旦落后就会让下游项目
 * `composer update kode/cache` 静默停在旧版（解算器认为新版不满足约束），
 * 表现为「发了新版却装不上」，且没有任何报错提示约束才是元凶。
 */
class DependencyConstraintTest extends TestCase
{
    /** @return array<string, string> 包名 => 已安装版本 */
    private function installedKodePackages(): array
    {
        $installed = [];
        foreach (InstalledVersions::getInstalledPackages() as $name) {
            if (!str_starts_with($name, 'kode/') || $name === 'kode/cache') {
                continue;
            }
            $version = InstalledVersions::getPrettyVersion($name);
            if ($version === null || str_starts_with($version, 'dev-')) {
                continue;
            }
            $installed[$name] = $version;
        }

        return $installed;
    }

    public function testRequirementsAcceptInstalledKodeVersions(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(__DIR__ . '/../composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $parser = new VersionParser();
        $violations = [];

        foreach ($this->installedKodePackages() as $name => $version) {
            $constraint = $manifest['require'][$name] ?? null;
            if ($constraint === null) {
                continue;
            }
            try {
                $provided = $parser->normalize($version);
                $normalized = $parser->parseConstraints($constraint)->matches(
                    $parser->parseConstraints($provided)
                );
            } catch (\UnexpectedValueException $e) {
                self::fail("{$name} 的约束 [{$constraint}] 或已装版本 [{$version}] 无法解析：{$e->getMessage()}");
                return;
            }
            if (!$normalized) {
                $violations[] = "{$name} 已装 {$version}，不满足本包声明的 {$constraint}";
            }
        }

        self::assertSame([], $violations, implode('；', $violations));
    }

    public function testPhpFloorIsNotAboveRuntime(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(__DIR__ . '/../composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $constraint = $manifest['require']['php'] ?? null;
        self::assertNotNull($constraint, 'composer.json 缺少 php 约束');

        $parser = new VersionParser();
        self::assertTrue(
            $parser->parseConstraints($constraint)->matches($parser->parseConstraints(PHP_VERSION)),
            "运行期 PHP " . PHP_VERSION . " 不满足声明的 php: {$constraint}"
        );
    }
}
