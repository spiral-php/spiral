<?php

/**
 * This file is part of Spiral Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Attributes\Composite;

use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionProperty;
use ReflectionClassConstant;
use ReflectionParameter;
use Traversable;
use Spiral\Attributes\Reader;
use Spiral\Attributes\ReaderInterface;

abstract class Composite extends Reader
{
    /**
     * @var ReaderInterface[]
     */
    protected $readers;

    /**
     * @param ReaderInterface[] $readers
     */
    public function __construct(iterable $readers)
    {
        $this->readers = $this->iterableToArray($readers);
    }

    /**
     * {@inheritDoc}
     */
    public function getClassMetadata(ReflectionClass $class, string $name = null): iterable
    {
        return $this->each(static fn (ReaderInterface $reader): iterable
            => $reader->getClassMetadata($class, $name));
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctionMetadata(ReflectionFunctionAbstract $function, string $name = null): iterable
    {
        return $this->each(static fn (ReaderInterface $reader): iterable
            => $reader->getFunctionMetadata($function, $name));
    }

    /**
     * {@inheritDoc}
     */
    public function getPropertyMetadata(ReflectionProperty $property, string $name = null): iterable
    {
        return $this->each(static fn (ReaderInterface $reader): iterable
            => $reader->getPropertyMetadata($property, $name));
    }

    /**
     * {@inheritDoc}
     */
    public function getConstantMetadata(ReflectionClassConstant $constant, string $name = null): iterable
    {
        return $this->each(static fn (ReaderInterface $reader): iterable
            => $reader->getConstantMetadata($constant, $name));
    }

    /**
     * {@inheritDoc}
     */
    public function getParameterMetadata(ReflectionParameter $parameter, string $name = null): iterable
    {
        return $this->each(static fn (ReaderInterface $reader): iterable
            => $reader->getParameterMetadata($parameter, $name));
    }


    /**
     * @param callable(ReaderInterface): list<array-key, object> $resolver
     */
    abstract protected function each(callable $resolver): iterable;

    /**
     * @param Traversable|array $result
     */
    protected function iterableToArray(iterable $result): array
    {
        return $result instanceof Traversable ? \iterator_to_array($result, false) : $result;
    }
}
