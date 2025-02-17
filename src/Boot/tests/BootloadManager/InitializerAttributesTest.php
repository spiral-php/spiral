<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\BootloadManager;

use Spiral\Tests\Boot\Fixtures\BootloaderWithAttributes;

final class InitializerAttributesTest extends InitializerTestCase
{
    public function testFindInitMethods(): void
    {
        $result = \iterator_to_array($this->initializer->init([BootloaderWithAttributes::class]));

        self::assertSame([
            'initMethodF',
            'init',
            'initMethodB',
            'initMethodD',
            'initMethodE',
            'initMethodC',
        ], $result[BootloaderWithAttributes::class]['init_methods']);
    }

    public function testFindBootMethods(): void
    {
        $result = \iterator_to_array($this->initializer->init([BootloaderWithAttributes::class]));

        self::assertSame([
            'bootMethodF',
            'boot',
            'bootMethodB',
            'bootMethodD',
            'bootMethodE',
            'bootMethodC',
        ], $result[BootloaderWithAttributes::class]['boot_methods']);
    }
}
