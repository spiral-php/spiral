<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\Fixtures;

final class SampleInjectableClass
{
    public function __construct(
        public string $name,
    ) {}
}
