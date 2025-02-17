<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\Fixtures;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\BootloadManager\AttributeResolverRegistryInterface;
use Spiral\Core\Container;
use Spiral\Tests\Boot\Fixtures\Attribute\SampleMethod;

final class BootloaderWithResolver extends Bootloader
{
    public function __construct(AttributeResolverRegistryInterface $registry, Container $container)
    {
        $registry->register(SampleMethod::class, new SampleMethodResolver($container));
    }
}
