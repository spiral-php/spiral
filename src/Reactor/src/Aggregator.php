<?php

declare(strict_types=1);

namespace Spiral\Reactor;

use Spiral\Reactor\Exception\ReactorException;

/**
 * Provides ability to aggregate specific set of elements (type constrained), render them or
 * apply set of operations.
 */
class Aggregator extends AbstractDeclaration implements
    \ArrayAccess,
    \IteratorAggregate,
    \Countable,
    ReplaceableInterface
{
    /**
     * @param DeclarationInterface[] $elements
     */
    public function __construct(
        private array $allowed,
        private array $elements = []
    ) {
    }

    /**
     * Get element by it's name.
     *
     * @throws ReactorException
     */
    public function __get($name): DeclarationInterface
    {
        return $this->get($name);
    }

    public function isEmpty(): bool
    {
        return empty($this->elements);
    }

    public function count(): int
    {
        return \count($this->elements);
    }

    /**
     * Check if aggregation has named element with given name.
     */
    public function has(string $name): bool
    {
        foreach ($this->elements as $element) {
            if ($element instanceof NamedInterface && $element->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add new element.
     *
     * @throws ReactorException
     */
    public function add(DeclarationInterface $element): self
    {
        $reflector = new \ReflectionObject($element);

        $allowed = false;
        foreach ($this->allowed as $class) {
            if ($reflector->isSubclassOf($class) || $element::class === $class) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            $type = $element::class;
            throw new ReactorException(\sprintf("Elements with type '%s' are not allowed", $type));
        }

        $this->elements[] = $element;

        return $this;
    }

    /**
     * Get named element by it's name.
     *
     * @throws ReactorException
     */
    public function get(string $name): DeclarationInterface
    {
        return $this->find($name);
    }

    /**
     * Remove element by it's name.
     */
    public function remove(string $name): self
    {
        foreach ($this->elements as $index => $element) {
            if ($element instanceof NamedInterface && $element->getName() === $name) {
                unset($this->elements[$index]);
            }
        }

        return $this;
    }

    /**
     * @return \ArrayIterator<array-key, DeclarationInterface>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->elements);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->remove($offset)->add($value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    public function replace(array|string $search, array|string $replace): Aggregator
    {
        foreach ($this->elements as $element) {
            if ($element instanceof ReplaceableInterface) {
                $element->replace($search, $replace);
            }
        }

        return $this;
    }

    public function render(int $indentLevel = 0): string
    {
        $result = '';

        foreach ($this->elements as $element) {
            $result .= $element->render($indentLevel) . "\n\n";
        }

        return \rtrim($result, "\n");
    }

    /**
     * Find element by it's name (NamedDeclarations only).
     *
     * @throws ReactorException When unable to find.
     */
    protected function find(string $name): DeclarationInterface
    {
        foreach ($this->elements as $element) {
            if ($element instanceof NamedInterface && $element->getName() === $name) {
                return $element;
            }
        }

        throw new ReactorException(\sprintf("Unable to find element '%s'", $name));
    }
}
