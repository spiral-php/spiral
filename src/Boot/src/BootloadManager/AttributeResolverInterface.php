<?php

declare(strict_types=1);

namespace Spiral\Boot\BootloadManager;

/**
 * @internal
 * @template T
 * @template TBootloader
 */
interface AttributeResolverInterface
{
    /**
     * @psalm-param T $attribute
     * @psalm-param TBootloader $service
     * @throws \ReflectionException
     */
    public function resolve(object $attribute, object $service, \ReflectionMethod $method): void;
}
