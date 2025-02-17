<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\BootloadManager;

use Spiral\Boot\BootloadManager\AttributeResolver;
use Spiral\Boot\BootloadManager\AttributeResolverRegistryInterface;
use Spiral\Boot\BootloadManager\BootloadManager;
use Spiral\Boot\BootloadManager\Initializer;
use Spiral\Boot\BootloadManager\InitializerInterface;
use Spiral\Tests\Boot\Fixtures\Attribute\SampleMethod;
use Spiral\Tests\Boot\Fixtures\BootloaderWithResolver;
use Spiral\Tests\Boot\Fixtures\SampleBootWithMethodBoot;
use Spiral\Tests\Boot\Fixtures\SampleClass;
use Spiral\Tests\Boot\Fixtures\SampleMethodResolver;
use Spiral\Tests\Boot\TestCase;

final class AttributeResolverTest extends TestCase
{
    public function testRegisterNewResolver(): void
    {
        $resolver = new AttributeResolver($this->container);

        $resolver->register(
            SampleMethod::class,
            new SampleMethodResolver($this->container),
        );

        $bootloader = new SampleBootWithMethodBoot();
        $refl = new \ReflectionClass($bootloader);

        $resolver->resolve(
            new SampleMethod('foo'),
            $bootloader,
            $refl->getMethod('sampleMethod'),
        );

        self::assertInstanceOf(SampleClass::class, $this->container->get('foo'));
    }

    public function testRegisterResolverThroughBootloader(): void
    {
        $this->container->bind(AttributeResolverRegistryInterface::class, AttributeResolver::class);
        $this->container->bind(InitializerInterface::class, new Initializer($this->container, $this->container));

        $bootloader = new BootloadManager(
            $this->container,
            $this->container,
            $this->container,
            $this->container->get(InitializerInterface::class),
        );

        $bootloader->bootload([
            BootloaderWithResolver::class,
            SampleBootWithMethodBoot::class,
        ]);

        self::assertInstanceOf(SampleClass::class, $this->container->get('sampleMethod'));
    }
}
