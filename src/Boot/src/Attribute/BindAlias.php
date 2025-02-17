<?php

declare(strict_types=1);

namespace Spiral\Boot\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class BindAlias
{
    /**
     * @param non-empty-string[] $aliases
     */
    public readonly array $aliases;

    public function __construct(string ...$aliases)
    {
        $this->aliases = $aliases;
    }
}
