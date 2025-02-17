<?php

declare(strict_types=1);

namespace Spiral\Boot\BootloadManager\AttributeResolver;

use Spiral\Boot\Attribute\SingletonMethod;
use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Core\Config\Factory;

/**
 * @internal
 * @extends AbstractResolver<SingletonMethod, BootloaderInterface>
 */
final class SingletonMethodResolver extends AbstractResolver
{
    public function resolve(object $attribute, object $service, \ReflectionMethod $method): void
    {
        $aliases = $this->getAliases($attribute, $method);
        $closure = new Factory(
            callable: $method->getClosure($service),
            singleton: true,
        );

        $this->bind($aliases, $closure, $this->getScope($method));
    }
}
