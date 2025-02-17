<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\Fixtures;

use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Boot\BootloadManager\AttributeResolver\AbstractResolver;
use Spiral\Tests\Boot\Fixtures\Attribute\SampleMethod;

/**
 * @extends AbstractResolver<SampleMethod, BootloaderInterface>
 */
final class SampleMethodResolver extends AbstractResolver
{
    public function resolve(
        object $attribute,
        object $service,
        \ReflectionMethod $method,
    ): void {
        $this->binder->bind($attribute->alias, $method->getClosure($service));
    }
}
