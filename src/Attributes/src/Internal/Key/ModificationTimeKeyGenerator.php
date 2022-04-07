<?php

/**
 * This file is part of Spiral Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Attributes\Internal\Key;

use ReflectionClass;
use ReflectionProperty;
use ReflectionClassConstant;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use LogicException;
use ReflectionParameter;

/**
 * A generator that returns a key containing information about the
 * time the file was last modified.
 *
 * @internal ModificationTimeKeyGenerator is an internal library class, please do not use it in your code.
 * @psalm-internal Spiral\Attributes
 */
final class ModificationTimeKeyGenerator implements KeyGeneratorInterface
{
    /**
     * {@inheritDoc}
     */
    public function forClass(ReflectionClass $class): string
    {
        if ($class->isUserDefined()) {
            return (string)\filemtime(
                $class->getFileName()
            );
        }

        return $class->getExtension()
            ->getVersion()
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function forProperty(ReflectionProperty $prop): string
    {
        return $this->forClass(
            $prop->getDeclaringClass()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function forConstant(ReflectionClassConstant $const): string
    {
        return $this->forClass(
            $const->getDeclaringClass()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function forFunction(ReflectionFunctionAbstract $fn): string
    {
        if ($fn instanceof ReflectionMethod) {
            return $this->forClass(
                $fn->getDeclaringClass()
            );
        }

        if ($fn->isUserDefined()) {
            return (string)\filemtime(
                $fn->getFileName()
            );
        }

        if ($extension = $fn->getExtension()) {
            return $extension->getVersion();
        }

        throw new LogicException('Can not determine modification time of [' . $fn->getName() . ']');
    }

    /**
     * {@inheritDoc}
     */
    public function forParameter(ReflectionParameter $param): string
    {
        return $this->forFunction(
            $param->getDeclaringFunction()
        );
    }
}
