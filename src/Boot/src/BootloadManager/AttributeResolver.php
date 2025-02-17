<?php

declare(strict_types=1);

namespace Spiral\Boot\BootloadManager;

use Spiral\Boot\Attribute\AbstractMethod;
use Spiral\Boot\Attribute\BindMethod;
use Spiral\Boot\Attribute\InjectorMethod;
use Spiral\Boot\Attribute\SingletonMethod;
use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Container;

/**
 * @internal
 * @template TAttribute of AbstractMethod
 * @template TBootloader of BootloaderInterface
 * @implements AttributeResolverInterface<TAttribute, TBootloader>
 * @implements AttributeResolverRegistryInterface<TAttribute>
 */
#[Singleton]
final class AttributeResolver implements AttributeResolverInterface, AttributeResolverRegistryInterface
{
    /**
     * @var array<class-string<TAttribute>, AttributeResolverInterface>
     */
    private array $resolvers = [];

    public function __construct(Container $container)
    {
        /** @psalm-suppress InvalidArgument */
        $this->register(SingletonMethod::class, $container->get(AttributeResolver\SingletonMethodResolver::class));
        /** @psalm-suppress InvalidArgument */
        $this->register(BindMethod::class, $container->get(AttributeResolver\BindMethodResolver::class));
        /** @psalm-suppress InvalidArgument */
        $this->register(InjectorMethod::class, $container->get(AttributeResolver\InjectorMethodResolver::class));
    }

    public function register(string $attribute, AttributeResolverInterface $resolver): void
    {
        $this->resolvers[$attribute] = $resolver;
    }

    /**
     * @return class-string<TAttribute>[]
     */
    public function getResolvers(): array
    {
        return \array_keys($this->resolvers);
    }

    public function resolve(object $attribute, object $service, \ReflectionMethod $method): void
    {
        $attributeClass = $attribute::class;
        if (!isset($this->resolvers[$attributeClass])) {
            throw new \RuntimeException("No resolver for attribute {$attributeClass}");
        }

        $this->resolvers[$attributeClass]->resolve($attribute, $service, $method);
    }
}
