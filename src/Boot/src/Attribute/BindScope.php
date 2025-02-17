<?php

declare(strict_types=1);

namespace Spiral\Boot\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class BindScope
{
    public readonly string $scope;

    public function __construct(string|\BackedEnum $scope)
    {
        $this->scope = \is_object($scope) ? (string) $scope->value : $scope;
    }
}
