<?php

namespace Spiral\Router\Registry;

final class DefaultPatternRegistry implements RoutePatternRegistryInterface
{
    private array $patterns = [
        'int' => '\d+',
        'integer' => '\d+',
        'uuid' => '[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}',
    ];

    public function register(string $name, string|\Stringable $pattern): void
    {
        $this->patterns[$name] = (string)$pattern;
    }

    public function all(): array
    {
        return $this->patterns;
    }
}
