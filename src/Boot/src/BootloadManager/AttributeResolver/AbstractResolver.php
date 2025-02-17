<?php

declare(strict_types=1);

namespace Spiral\Boot\BootloadManager\AttributeResolver;

use Spiral\Boot\Attribute\AbstractMethod;
use Spiral\Boot\Attribute\BindAlias;
use Spiral\Boot\Attribute\BindScope;
use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Boot\BootloadManager\AttributeResolverInterface;
use Spiral\Core\BinderInterface;
use Spiral\Core\Config\Binding;

/**
 * @internal
 * @template T of AbstractMethod
 * @template TBootloader of BootloaderInterface
 * @implements AttributeResolverInterface<T, TBootloader>
 */
abstract class AbstractResolver implements AttributeResolverInterface
{
    public function __construct(
        protected readonly BinderInterface $binder,
    ) {}

    /**
     * @psalm-param T $attribute
     * @return list<non-empty-string>
     */
    protected function getAliases(object $attribute, \ReflectionMethod $method): array
    {
        $alias = $attribute->alias ?? null;

        $aliases = [];
        if ($alias !== null) {
            $aliases[] = $alias;
        }

        $attrs = $method->getAttributes(name: BindAlias::class);
        foreach ($attrs as $attr) {
            $aliases = [...$aliases, ...$attr->newInstance()->aliases];
        }

        // If no aliases are provided, we will use the return type as the alias.
        if (\count($aliases) > 0 && !$attribute->aliasesFromReturnType) {
            return \array_unique(\array_filter($aliases));
        }

        $type = $method->getReturnType();

        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $type) {
                if ($type->isBuiltin()) {
                    continue;
                }

                $aliases[] = $type->getName();
            }
        } elseif ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $aliases[] = $type->getName();
        }

        if ($aliases === []) {
            throw new \LogicException(
                "No alias provided for binding {$method->getDeclaringClass()->getName()}::{$method->getName()}",
            );
        }

        return \array_unique(\array_filter($aliases));
    }

    protected function getScope(\ReflectionMethod $method): ?string
    {
        $attrs = $method->getAttributes(name: BindScope::class);

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance()->scope;
    }

    protected function bind(array $aliases, Binding $binding, ?string $scope = null): void
    {
        $binder = $this->binder->getBinder($scope);

        $alias = \array_shift($aliases);
        foreach ($aliases as $a) {
            $binder->bind($alias, $a);
            $alias = \array_shift($aliases);
        }

        $binder->bind($alias, $binding);
    }
}
