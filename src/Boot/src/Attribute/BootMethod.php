<?php

declare(strict_types=1);

namespace Spiral\Boot\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class BootMethod
{
    public function __construct(
        public readonly int $priority = 0,
    ) {}
}
