<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\Fixtures;

use Spiral\Boot\Attribute\BindMethod;
use Spiral\Boot\Attribute\InjectorMethod;
use Spiral\Boot\Attribute\SingletonMethod;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\BinderInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Tests\Boot\Fixtures\Attribute\SampleMethod;

class SampleBootWithMethodBoot extends Bootloader
{
    public const BOOT = true;
    public const BINDINGS = ['abc' => self::class];
    public const SINGLETONS = ['single' => self::class];

    public function init(BinderInterface $binder): void
    {
        $binder->bind('def', new SampleBoot());
    }

    public function boot(BinderInterface $binder): void
    {
        $binder->bind('efg', new SampleClass());
        $binder->bind('ghi', 'foo');
    }

    #[BindMethod(alias: 'ijk')]
    protected function bindMethodB(): string
    {
        return 'foo';
    }

    #[InjectorMethod(alias: SampleInjectableClass::class)]
    protected function sampleInjector(): InjectorInterface
    {
        return new class implements InjectorInterface {
            public function createInjection(\ReflectionClass $class, ?string $context = null): object
            {
                return new SampleInjectableClass('foo');
            }
        };
    }

    #[BindMethod(alias: 'hij')]
    private function bindMethodA(): SampleClass
    {
        return new SampleClass();
    }

    #[BindMethod]
    private function bindMethodC(): SampleClass2|string|int
    {
        return new SampleClass2();
    }

    #[BindMethod]
    private function bindMethodD(): SampleClass|SampleClassInterface
    {
        return new SampleClass();
    }

    #[SingletonMethod(alias: 'singleAbc')]
    private function singletonMethod(): SampleClass
    {
        return new SampleClass();
    }

    #[SingletonMethod]
    private function singletonMethodA(): SampleClass3
    {
        return new SampleClass3();
    }

    #[SampleMethod('sampleMethod')]
    private function sampleMethod(): SampleClass|string|int
    {
        return new SampleClass();
    }
}
