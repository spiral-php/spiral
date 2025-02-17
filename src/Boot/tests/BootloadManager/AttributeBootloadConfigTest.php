<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\BootloadManager;

use PHPUnit\Framework\Attributes\DataProvider;
use Spiral\Boot\Environment;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Tests\Boot\Fixtures\BootloaderD;
use Spiral\Tests\Boot\Fixtures\BootloaderE;
use Spiral\Tests\Boot\Fixtures\BootloaderF;
use Spiral\Tests\Boot\Fixtures\BootloaderG;
use Spiral\Tests\Boot\Fixtures\BootloaderH;
use Spiral\Tests\Boot\Fixtures\BootloaderI;
use Spiral\Tests\Boot\Fixtures\BootloaderJ;
use Spiral\Tests\Boot\Fixtures\BootloaderK;

final class AttributeBootloadConfigTest extends InitializerTestCase
{
    public static function allowEnvDataProvider(): \Traversable
    {
        yield [
            ['APP_ENV' => 'prod', 'APP_DEBUG' => false, 'RR_MODE' => 'http'],
            [
                BootloaderH::class => [
                    'bootloader' => new BootloaderH(),
                    'options' => [],
                    'init_methods' => ['init'],
                    'boot_methods' => ['boot'],
                ],
            ],
        ];
        yield [
            ['APP_ENV' => 'dev', 'APP_DEBUG' => false, 'RR_MODE' => 'http'],
            [],
        ];
        yield [
            ['APP_ENV' => 'prod', 'APP_DEBUG' => true, 'RR_MODE' => 'http'],
            [],
        ];
        yield [
            ['APP_ENV' => 'prod', 'APP_DEBUG' => false, 'RR_MODE' => 'jobs'],
            [],
        ];
    }

    public static function denyEnvDataProvider(): \Traversable
    {
        yield [
            ['RR_MODE' => 'http', 'APP_ENV' => 'prod', 'DB_HOST' => 'db.example.com'],
            [],
        ];
        yield [
            ['RR_MODE' => 'http', 'APP_ENV' => 'production', 'DB_HOST' => 'db.example.com'],
            [],
        ];
        yield [
            ['RR_MODE' => 'http', 'APP_ENV' => 'production', 'DB_HOST' => 'db.example.com'],
            [],
        ];
        yield [
            ['RR_MODE' => 'jobs', 'APP_ENV' => 'production', 'DB_HOST' => 'db.example.com'],
            [],
        ];
        yield [
            ['RR_MODE' => 'http', 'APP_ENV' => 'dev', 'DB_HOST' => 'db.example.com'],
            [],
        ];
        yield [
            ['RR_MODE' => 'http', 'APP_ENV' => 'dev', 'DB_HOST' => 'localhost'],
            [],
        ];
        yield [
            ['RR_MODE' => 'jobs', 'APP_ENV' => 'dev', 'DB_HOST' => 'localhost'],
            [
                BootloaderI::class => [
                    'bootloader' => new BootloaderI(),
                    'options' => [],
                    'init_methods' => ['init'],
                    'boot_methods' => ['boot'],
                ],
            ],
        ];
    }

    public function testDefaultBootloadConfig(): void
    {
        $result = \iterator_to_array($this->initializer->init([BootloaderE::class, BootloaderD::class]));

        self::assertEquals([
            BootloaderE::class => [
                'bootloader' => new BootloaderE(),
                'options' => [],
                'init_methods' => ['init'],
                'boot_methods' => ['boot'],
            ],
            BootloaderD::class => [
                'bootloader' => new BootloaderD(),
                'options' => [],
                'init_methods' => ['init'],
                'boot_methods' => ['boot'],
            ],
        ], $result);
    }

    public function testDisabledBootloader(): void
    {
        $result = \iterator_to_array($this->initializer->init([BootloaderF::class, BootloaderD::class]));

        self::assertEquals([
            BootloaderD::class => [
                'bootloader' => new BootloaderD(),
                'options' => [],
                'init_methods' => ['init'],
                'boot_methods' => ['boot'],
            ],
        ], $result);
    }

    public function testArguments(): void
    {
        $result = \iterator_to_array($this->initializer->init([BootloaderG::class]));

        self::assertEquals([
            BootloaderG::class => [
                'bootloader' => new BootloaderG(),
                'options' => ['a' => 'b', 'c' => 'd'],
                'init_methods' => ['init'],
                'boot_methods' => ['boot'],
            ],
        ], $result);
    }

    public function testDisabledConfig(): void
    {
        $result = \iterator_to_array($this->initializer->init([BootloaderF::class, BootloaderD::class], false));

        self::assertEquals([
            BootloaderF::class => [
                'bootloader' => new BootloaderF(),
                'options' => [],
                'init_methods' => ['init'],
                'boot_methods' => ['boot'],
            ],
            BootloaderD::class => [
                'bootloader' => new BootloaderD(),
                'options' => [],
                'init_methods' => ['init'],
                'boot_methods' => ['boot'],
            ],
        ], $result);
    }

    #[DataProvider('allowEnvDataProvider')]
    public function testAllowEnv(array $env, array $expected): void
    {
        $this->container->bindSingleton(EnvironmentInterface::class, new Environment($env), true);

        $result = \iterator_to_array($this->initializer->init([BootloaderH::class]));

        self::assertEquals($expected, $result);
    }

    #[DataProvider('denyEnvDataProvider')]
    public function testDenyEnv(array $env, array $expected): void
    {
        $this->container->bindSingleton(EnvironmentInterface::class, new Environment($env), true);

        $result = \iterator_to_array($this->initializer->init([BootloaderI::class]));

        self::assertEquals($expected, $result);
    }

    public function testDenyEnvShouldHaveHigherPriority(): void
    {
        $this->container->bindSingleton(EnvironmentInterface::class, new Environment(['APP_DEBUG' => true]), true);

        $result = \iterator_to_array($this->initializer->init([BootloaderJ::class]));

        self::assertSame([], $result);
    }

    public function testExtendedAttribute(): void
    {
        $this->container->bindSingleton(EnvironmentInterface::class, new Environment(['RR_MODE' => 'http']), true);
        $result = \iterator_to_array($this->initializer->init([BootloaderK::class]));
        self::assertEquals([
            BootloaderK::class => [
                'bootloader' => new BootloaderK(),
                'options' => [],
                'init_methods' => ['init'],
                'boot_methods' => ['boot'],
            ],
        ], $result);

        $this->container->bindSingleton(EnvironmentInterface::class, new Environment(['RR_MODE' => 'jobs']), true);
        $result = \iterator_to_array($this->initializer->init([BootloaderK::class]));
        self::assertSame([], $result);
    }
}
