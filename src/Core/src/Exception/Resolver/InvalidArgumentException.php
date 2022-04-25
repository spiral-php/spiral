<?php

declare(strict_types=1);

namespace Spiral\Core\Exception\Resolver;

use Spiral\Core\Exception\Traits\ClosureRendererTrait;

final class InvalidArgumentException extends ResolvingException
{
    use ClosureRendererTrait;

    public function __construct(
        \ReflectionFunctionAbstract $reflection,
        private readonly string $parameter
    ) {
        $pattern = "Invalid value of the `{$parameter}` argument when validating arguments for `%s` %s.";
        parent::__construct($this->renderFunctionAndParameter($reflection, $pattern));
    }

    public function getParameter(): string
    {
        return $this->parameter;
    }
}
