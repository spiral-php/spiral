<?php

declare(strict_types=1);

namespace Spiral\Boot\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class InjectorMethod extends AbstractMethod
{
    public function __construct(string $alias)
    {
        parent::__construct($alias);
    }
}
