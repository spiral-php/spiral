<?php

declare(strict_types=1);

namespace Spiral\Boot\BootloadManager\AttributeResolver;

use Spiral\Boot\Attribute\InjectorMethod;
use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Boot\BootloadManager\AttributeResolverInterface;
use Spiral\Core\BinderInterface;
use Spiral\Core\Config\Injectable;
use Spiral\Core\InvokerInterface;

/**
 * @internal
 * @implements AttributeResolverInterface<InjectorMethod, BootloaderInterface>
 */
final class InjectorMethodResolver implements AttributeResolverInterface
{
    public function __construct(
        private readonly BinderInterface $binder,
        private readonly InvokerInterface $invoker,
    ) {}

    public function resolve(object $attribute, object $service, \ReflectionMethod $method): void
    {
        $this->binder->bind(
            $attribute->alias,
            new Injectable($this->invoker->invoke($method->getClosure($service))),
        );
    }
}
