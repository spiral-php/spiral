<?php

/**
 * This file is part of Spiral Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Attributes\Internal\Instantiator;

use Spiral\Attributes\Internal\Exception;

/**
 * @internal NamedArgumentsInstantiator is an internal library class, please do not use it in your code.
 * @psalm-internal Spiral\Attributes
 */
final class NamedArgumentsInstantiator extends Instantiator
{
    /**
     * @var string
     */
    private const ERROR_ARGUMENT_NOT_PASSED = '%s::__construct(): Argument #%d ($%s) not passed';

    /**
     * @var string
     */
    private const ERROR_UNKNOWN_ARGUMENT = 'Unknown named parameter $%s';

    /**
     * @var string
     */
    private const ERROR_POSITIONAL_AFTER_NAMED = 'Cannot use positional argument after named argument';

    /**
     * {@inheritDoc}
     */
    public function instantiate(\ReflectionClass $attr, array $arguments, \Reflector $context = null): object
    {
        if ($this->isNamedArgumentsSupported()) {
            try {
                return $attr->newInstanceArgs($arguments);
            } catch (\Throwable $e) {
                throw Exception::withLocation($e, $attr->getFileName(), $attr->getStartLine());
            }
        }

        $constructor = $this->getConstructor($attr);

        if ($constructor === null) {
            return $attr->newInstanceWithoutConstructor();
        }

        return $attr->newInstanceArgs(
            $this->resolveParameters($attr, $constructor, $arguments)
        );
    }

    /**
     * @return bool
     */
    private function isNamedArgumentsSupported(): bool
    {
        return \version_compare(\PHP_VERSION, '8.0') >= 0;
    }

    /**
     * @param \ReflectionClass $ctx
     * @param \ReflectionMethod $constructor
     * @param array $arguments
     * @return array
     * @throws \Throwable
     */
    private function resolveParameters(\ReflectionClass $ctx, \ReflectionMethod $constructor, array $arguments): array
    {
        try {
            return $this->doResolveParameters($ctx, $constructor, $arguments);
        } catch (\Throwable $e) {
            throw Exception::withLocation($e, $constructor->getFileName(), $constructor->getStartLine());
        }
    }

    /**
     * @param \ReflectionClass $ctx
     * @param \ReflectionMethod $constructor
     * @param array $arguments
     * @return array
     * @throws \Throwable
     */
    private function doResolveParameters(\ReflectionClass $ctx, \ReflectionMethod $constructor, array $arguments): array
    {
        // Normalize all numeric keys, but keep string keys.
        $arguments = array_merge($arguments);

        $i = 0;
        $namedArgsBegin = null;
        foreach ($arguments as $k => $_) {
            if ($k !== $i) {
                $namedArgsBegin = $i;
                break;
            }
            ++$i;
        }

        if ($namedArgsBegin === null) {
            // Only numeric / positional keys exist.
            return $arguments;
        }

        // For any further numeric keys, one of them is now $namedArgsBegin.
        if (array_key_exists($namedArgsBegin, $arguments)) {
            throw new \BadMethodCallException(self::ERROR_POSITIONAL_AFTER_NAMED);
        }

        $passed = [];

        foreach ($constructor->getParameters() as $i => $parameter) {
            if ($i < $namedArgsBegin) {
                $passed[] = $arguments[$i];
                unset($arguments[$i]);
            } else {
                $passed[] = $this->resolveParameter($ctx, $parameter, $arguments);
            }
        }

        if (\count($arguments)) {
            $message = \sprintf(self::ERROR_UNKNOWN_ARGUMENT, \array_key_first($arguments));
            throw new \BadMethodCallException($message);
        }

        return $passed;
    }

    /**
     * @param \ReflectionClass $ctx
     * @param \ReflectionParameter $param
     * @param array $arguments
     * @return mixed
     * @throws \Throwable
     */
    private function resolveParameter(\ReflectionClass $ctx, \ReflectionParameter $param, array &$arguments)
    {
        switch (true) {
            case \array_key_exists($param->getName(), $arguments):
                try {
                    return $arguments[$param->getName()];
                } finally {
                    unset($arguments[$param->getName()]);
                }
                // no actual falling through

            case $param->isDefaultValueAvailable():
                return $param->getDefaultValue();

            default:
                $message = \vsprintf(self::ERROR_ARGUMENT_NOT_PASSED, [
                    $ctx->getName(),
                    $param->getPosition() + 1,
                    $param->getName(),
                ]);

                throw new \ArgumentCountError($message);
        }
    }
}
