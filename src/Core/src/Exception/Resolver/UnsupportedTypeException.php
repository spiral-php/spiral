<?php

declare(strict_types=1);

namespace Spiral\Core\Exception\Resolver;

final class UnsupportedTypeException extends ResolvingException
{
    public function __construct(\ReflectionFunctionAbstract $reflection, string $parameter)
    {
        $pattern = "Can not resolve unsupported type of the `{$parameter}` parameter in `%s` %s.";
        parent::__construct($this->RenderFunctionAndParameter($reflection, $pattern));
    }
}
