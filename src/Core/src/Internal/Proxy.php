<?php

declare(strict_types=1);

namespace Spiral\Core\Internal;

use Spiral\Core\ContainerScope;
use Spiral\Core\Internal\Proxy\ProxyClassRenderer;

/**
 * @internal
 */
final class Proxy
{
    /** @var array<class-string, object> */
    private static array $cache = [];

    /**
     * @template TClass of object
     * @param \ReflectionClass<TClass> $type
     * @return TClass
     */
    public static function create(
        \ReflectionClass $type,
        \Stringable|string|null $context,
        \Spiral\Core\Attribute\Proxy $attribute,
    ): object {
        $interface = $type->getName();

        // Use the container where the proxy was created
        $attachContainer = $attribute->attach;

        $cacheKey = \sprintf(
            '%s%s%s',
            $interface,
            $attachContainer ? '[attached]' : '',
            $attribute->proxyOverloads ? '[magic-calls]' : '',
        );

        if (!\array_key_exists($cacheKey, self::$cache)) {
            $n = 0;
            do {
                /** @var class-string<TClass> $className */
                $className = \sprintf(
                    '%s\%s SCOPED PROXY%s',
                    $type->getNamespaceName(),
                    $type->getShortName(),
                    $n++ > 0 ? " {$n}" : ''
                );
            } while (\class_exists($className));

            try {
                $classString = ProxyClassRenderer::renderClass(
                    $type,
                    $className,
                    $attribute->proxyOverloads,
                    $attachContainer,
                );

                eval($classString);
            } catch (\Throwable $e) {
                throw new \Error("Unable to create proxy for `{$interface}`: {$e->getMessage()}", 0, $e);
            }

            $instance = new $className();
            (static fn() => $instance::$__container_proxy_alias = $interface)->bindTo(null, $instance::class)();

            // Store in cache without context
            self::$cache[$cacheKey] = $instance;
        } else {
            /** @var TClass $instance */
            $instance = self::$cache[$cacheKey];
        }

        if ($context !== null || $attachContainer) {
            $instance = clone $instance;
            (static function () use ($instance, $context, $attachContainer): void {
                // Set the Current Context
                /** @see \Spiral\Core\Internal\Proxy\ProxyTrait::$__container_proxy_context */
                $context === null or $instance->__container_proxy_context = $context;

                // Set the Current Scope Container
                /** @see \Spiral\Core\Internal\Proxy\ProxyTrait::__container_proxy_container */
                $attachContainer and $instance->__container_proxy_container = ContainerScope::getContainer();
            })->bindTo(null, $instance::class)();
        }

        return $instance;
    }
}
