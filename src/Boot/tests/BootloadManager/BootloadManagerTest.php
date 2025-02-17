<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\BootloadManager;

use PHPUnit\Framework\Attributes\DataProvider;
use Spiral\Core\Container;
use Spiral\Tests\Boot\Fixtures\BootloaderA;
use Spiral\Tests\Boot\Fixtures\BootloaderB;
use Spiral\Tests\Boot\Fixtures\BootloaderL;
use Spiral\Tests\Boot\Fixtures\BootloaderM;
use Spiral\Tests\Boot\Fixtures\BootloaderO;
use Spiral\Tests\Boot\Fixtures\BootloaderP;
use Spiral\Tests\Boot\Fixtures\BootloaderQ;
use Spiral\Tests\Boot\Fixtures\BootloaderR;
use Spiral\Tests\Boot\Fixtures\BootloaderS;
use Spiral\Tests\Boot\Fixtures\SampleBoot;
use Spiral\Tests\Boot\Fixtures\SampleBootWithMethodBoot;
use Spiral\Tests\Boot\Fixtures\SampleClass;
use Spiral\Tests\Boot\Fixtures\SampleClass2;
use Spiral\Tests\Boot\Fixtures\SampleClass3;
use Spiral\Tests\Boot\Fixtures\SampleClassInterface;
use Spiral\Tests\Boot\Fixtures\SampleInjectableClass;
use Spiral\Tests\Boot\TestCase;

final class BootloadManagerTest extends TestCase
{
    public static function provideErrorBootloader(): iterable
    {
        yield [BootloaderL::class];
        yield [BootloaderM::class];
        yield [BootloaderO::class];
        yield [BootloaderP::class];
    }

    public function testWithoutInvokerStrategy(): void
    {
        $bootloader = $this->getBootloadManager();
        $bootloader->bootload(
            $classes = [
                SampleClass::class,
                SampleBootWithMethodBoot::class,
                SampleBoot::class,
            ],
            [
                static function (Container $container, SampleBoot $boot): void {
                    $container->bind('efg', $boot);
                },
            ],
            [
                static function (Container $container, SampleBoot $boot): void {
                    $container->bind('ghi', $boot);
                },
            ],
        );

        self::assertTrue($this->container->has('abc'));
        self::assertTrue($this->container->hasInstance('cde'));
        self::assertTrue($this->container->hasInstance('def'));
        self::assertTrue($this->container->hasInstance('efg'));
        self::assertTrue($this->container->has('single'));
        self::assertTrue($this->container->has('ghi'));
        self::assertTrue($this->container->has(SampleClassInterface::class));
        self::assertTrue($this->container->has(SampleClass::class));
        self::assertTrue($this->container->has(SampleClass2::class));
        self::assertTrue($this->container->has(SampleClass3::class));

        self::assertTrue($this->container->hasInjector(SampleInjectableClass::class));
        self::assertInstanceOf(
            SampleInjectableClass::class,
            $injectable = $this->container->get(SampleInjectableClass::class),
        );
        self::assertSame('foo', $injectable->name);

        self::assertNotInstanceOf(SampleBoot::class, $this->container->get('efg'));
        self::assertInstanceOf(SampleBoot::class, $this->container->get('ghi'));

        self::assertNotSame($this->container->get(SampleClass2::class), $this->container->get(SampleClass2::class));
        self::assertSame($this->container->get(SampleClass3::class), $this->container->get(SampleClass3::class));

        $classes = \array_filter($classes, static fn(string $class): bool => $class !== SampleClass::class);
        self::assertSame(\array_merge($classes, [
            BootloaderA::class,
            BootloaderB::class,
        ]), $bootloader->getClasses());
    }

    public function testSingletonAliases(): void
    {
        $bootloader = $this->getBootloadManager();

        $bootloader->bootload([BootloaderQ::class]);

        self::assertTrue($this->container->has(SampleClass::class));
        self::assertTrue($this->container->has(SampleClassInterface::class));

        self::assertSame(
            $this->container->get(SampleClass::class),
            $this->container->get(SampleClassInterface::class),
        );
    }

    public function testBindingAliases(): void
    {
        $bootloader = $this->getBootloadManager();

        $bootloader->bootload([BootloaderR::class]);

        self::assertTrue($this->container->has(SampleClass::class));
        self::assertTrue($this->container->has(SampleClassInterface::class));

        self::assertNotSame(
            $this->container->get(SampleClass::class),
            $this->container->get(SampleClassInterface::class),
        );
    }

    public function testBindingAliases2(): void
    {
        $bootloader = $this->getBootloadManager();

        $bootloader->bootload([BootloaderS::class]);

        self::assertTrue($this->container->has('sample1'));
        self::assertTrue($this->container->has('sample2'));
        self::assertTrue($this->container->has('sample3'));
        self::assertFalse($this->container->has(SampleClass::class));
        self::assertFalse($this->container->has(SampleClassInterface::class));

        self::assertTrue($this->container->has('sample4'));
        self::assertTrue($this->container->has('sample5'));
        self::assertTrue($this->container->has('sample6'));
        self::assertTrue($this->container->has('sample7'));
        self::assertTrue($this->container->has(SampleClass2::class));
    }

    #[DataProvider('provideErrorBootloader')]
    public function testErrorAttributes(string $bootloaderClass): void
    {
        $bootloader = $this->getBootloadManager();

        $this->expectException(\LogicException::class);

        $bootloader->bootload([$bootloaderClass]);
    }
}
