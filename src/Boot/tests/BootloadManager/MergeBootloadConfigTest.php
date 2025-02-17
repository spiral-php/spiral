<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\BootloadManager;

use Spiral\Boot\Attribute\BootloadConfig;
use Spiral\Tests\Boot\Fixtures\BootloaderD;
use Spiral\Tests\Boot\Fixtures\BootloaderF;
use Spiral\Tests\Boot\Fixtures\BootloaderG;
use Spiral\Tests\Boot\Fixtures\BootloaderH;
use Spiral\Tests\Boot\Fixtures\BootloaderI;

final class MergeBootloadConfigTest extends InitializerTestCase
{
    public function testOverrideEnabled(): void
    {
        $result = \iterator_to_array($this->initializer->init([
            BootloaderF::class => new BootloadConfig(enabled: true),
            BootloaderD::class,
        ]));

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

    public function testOverrideArgs(): void
    {
        $result = \iterator_to_array($this->initializer->init([
            BootloaderG::class => new BootloadConfig(args: ['foo' => 'bar']),
        ]));

        self::assertEquals([
            BootloaderG::class => [
                'bootloader' => new BootloaderG(),
                'options' => ['foo' => 'bar'],
                'init_methods' => ['init'],
                'boot_methods' => ['boot'],
            ],
        ], $result);
    }

    public function testMergeArgs(): void
    {
        $result = \iterator_to_array($this->initializer->init([
            BootloaderG::class => new BootloadConfig(args: ['foo' => 'bar', 'a' => 'baz'], override: false),
        ]));

        self::assertEquals([
            BootloaderG::class => [
                'bootloader' => new BootloaderG(),
                'options' => [
                    'a' => 'baz',
                    'foo' => 'bar',
                    'c' => 'd',
                ],
                'init_methods' => ['init'],
                'boot_methods' => ['boot'],
            ],
        ], $result);
    }

    public function testOverrideAllowEnv(): void
    {
        $ref = new \ReflectionMethod($this->initializer, 'getBootloadConfig');
        $config = $ref->invoke(
            $this->initializer,
            BootloaderH::class,
            new BootloadConfig(allowEnv: ['foo' => 'bar']),
        );

        self::assertEquals(['foo' => 'bar'], $config->allowEnv);
    }

    public function testMergeAllowEnv(): void
    {
        $ref = new \ReflectionMethod($this->initializer, 'getBootloadConfig');
        $config = $ref->invoke(
            $this->initializer,
            BootloaderH::class,
            new BootloadConfig(allowEnv: ['APP_ENV' => 'dev', 'foo' => 'bar'], override: false),
        );

        self::assertEquals([
            'foo' => 'bar',
            'APP_ENV' => 'dev',
            'APP_DEBUG' => false,
            'RR_MODE' => ['http'],
        ], $config->allowEnv);
    }

    public function testOverrideDenyEnv(): void
    {
        $ref = new \ReflectionMethod($this->initializer, 'getBootloadConfig');
        $config = $ref->invoke(
            $this->initializer,
            BootloaderI::class,
            new BootloadConfig(denyEnv: ['foo' => 'bar']),
        );

        self::assertEquals(['foo' => 'bar'], $config->denyEnv);
    }

    public function testMergeDenyEnv(): void
    {
        $ref = new \ReflectionMethod($this->initializer, 'getBootloadConfig');
        $config = $ref->invoke(
            $this->initializer,
            BootloaderI::class,
            new BootloadConfig(denyEnv: ['DB_HOST' => 'localhost', 'foo' => 'bar'], override: false),
        );

        self::assertEquals([
            'foo' => 'bar',
            'RR_MODE' => 'http',
            'APP_ENV' => ['production', 'prod'],
            'DB_HOST' => 'localhost',
        ], $config->denyEnv);
    }
}
