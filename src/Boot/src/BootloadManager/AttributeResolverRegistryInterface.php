<?php

declare(strict_types=1);

namespace Spiral\Boot\BootloadManager;

/**
 * @internal
 * @template T of object
 */
interface AttributeResolverRegistryInterface
{
    /**
     * @param class-string<T> $attribute
     */
    public function register(string $attribute, AttributeResolverInterface $resolver): void;
}
