<?php

declare(strict_types=1);

namespace Spiral\Boot\Attribute;

/**
 * @internal
 */
abstract class AbstractMethod
{
    /**
     * @param non-empty-string|null $alias
     */
    public function __construct(
        /**
         * Alias for the method.
         * If not provided, the return type of the method will be used as the alias.
         */
        public readonly ?string $alias = null,
        /**
         * Add aliases from the return type of the method even if the method has an alias.
         */
        public readonly bool $aliasesFromReturnType = false,
    ) {}
}
