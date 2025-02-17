<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\BootloadManager;

use PHPUnit\Framework\Attributes\DataProvider;
use Spiral\Boot\Attribute\BootloadConfig;
use Spiral\Boot\Environment;
use Spiral\Boot\Environment\AppEnvironment;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Tests\Boot\Fixtures\BootloaderA;
use Spiral\Tests\Boot\Fixtures\BootloaderD;

final class BootloadConfigTest extends InitializerTestCase
{
    public static function allowEnvDataProvider(): \Traversable
    {
        yield [
            ['APP_ENV' => 'prod', 'APP_DEBUG' => false, 'RR_MODE' => 'http'],
            [
                BootloaderA::class => [
                    'bootloader' => new BootloaderA(),
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
                BootloaderA::class => [
                    'bootloader' => new BootloaderA(),
                    'options' => [],
                    'init_methods' => ['init'],
                    'boot_methods' => ['boot'],
                ],
            ],
        ];
    }

    public function testDefaultBootloadConfig(): void
    {
        $result = \iterator_to_array($this->initializer->init([
            BootloaderA::class => new BootloadConfig(),
            BootloaderD::class,
        ]));

        self::assertEquals([
            BootloaderA::class => [
                'bootloader' => new BootloaderA(),
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
        $result = \iterator_to_array($this->initializer->init([
            BootloaderA::class => new BootloadConfig(enabled: false),
            BootloaderD::class,
        ]));

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
        $result = \iterator_to_array($this->initializer->init([
            BootloaderA::class => new BootloadConfig(args: ['a' => 'b']),
        ]));

        self::assertEquals([
            BootloaderA::class => [
                'bootloader' => new BootloaderA(),
                'options' => ['a' => 'b'],
                'init_methods' => ['init'],
                'boot_methods' => ['boot'],
            ],
        ], $result);
    }

    public function testDisabledConfig(): void
    {
        $result = \iterator_to_array($this->initializer->init([
            BootloaderA::class => new BootloadConfig(enabled: false),
            BootloaderD::class,
        ], false));

        self::assertEquals([
            BootloaderA::class => [
                'bootloader' => new BootloaderA(),
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

    public function testCallableConfig(): void
    {
        $result = \iterator_to_array($this->initializer->init([
            BootloaderA::class => static fn(): BootloadConfig => new BootloadConfig(args: ['a' => 'b']),
        ]));

        self::assertEquals([
            BootloaderA::class => [
                'bootloader' => new BootloaderA(),
                'options' => ['a' => 'b'],
                'init_methods' => ['init'],
                'boot_methods' => ['boot'],
            ],
        ], $result);
    }

    public function testCallableConfigWithArguments(): void
    {
        $this->container->bind(AppEnvironment::class, AppEnvironment::Production);

        $result = \iterator_to_array($this->initializer->init([
            BootloaderA::class => static fn(AppEnvironment $env): BootloadConfig => new BootloadConfig(
                enabled: $env->isLocal(),
            ),
        ]));
        self::assertSame([], $result);

        $result = \iterator_to_array($this->initializer->init([
            BootloaderA::class => static fn(AppEnvironment $env): BootloadConfig => new BootloadConfig(
                enabled: $env->isProduction(),
            ),
        ]));
        self::assertEquals([
            BootloaderA::class => [
                'bootloader' => new BootloaderA(),
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

        $result = \iterator_to_array($this->initializer->init([
            BootloaderA::class => new BootloadConfig(allowEnv: [
                'APP_ENV' => 'prod',
                'APP_DEBUG' => false,
                'RR_MODE' => ['http'],
            ]),
        ]));

        self::assertEquals($expected, $result);
    }

    #[DataProvider('denyEnvDataProvider')]
    public function testDenyEnv(array $env, array $expected): void
    {
        $this->container->bindSingleton(EnvironmentInterface::class, new Environment($env), true);

        $result = \iterator_to_array($this->initializer->init([
            BootloaderA::class => new BootloadConfig(denyEnv: [
                'RR_MODE' => 'http',
                'APP_ENV' => ['production', 'prod'],
                'DB_HOST' => 'db.example.com',
            ]),
        ]));

        self::assertEquals($expected, $result);
    }

    public function testDenyEnvShouldHaveHigherPriority(): void
    {
        $this->container->bindSingleton(EnvironmentInterface::class, new Environment(['APP_DEBUG' => true]), true);

        $result = \iterator_to_array($this->initializer->init([
            BootloaderA::class => new BootloadConfig(allowEnv: ['APP_DEBUG' => true], denyEnv: ['APP_DEBUG' => true]),
        ]));

        self::assertSame([], $result);
    }
}
