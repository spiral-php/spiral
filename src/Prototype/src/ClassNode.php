<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Prototype;

use Spiral\Prototype\ClassNode\ConstructorParam;

/**
 * @internal
 */
final class ClassNode
{
    /** @var string */
    public $namespace;

    /** @var string */
    public $class;

    /** @var bool */
    public $hasConstructor = false;

    /** @var ClassNode\ConstructorParam[] */
    public $constructorParams = [];

    /** @var string[] */
    public $constructorVars = [];

    /** @var Dependency[] */
    public $dependencies = [];

    /** @var ClassNode\ClassStmt[] */
    private $stmts = [];

    /**
     * ClassNode constructor.
     */
    private function __construct()
    {
    }

    public static function create(string $class): ClassNode
    {
        $self = new self();
        $self->class = $class;

        return $self;
    }

    public static function createWithNamespace(string $class, string $namespace): ClassNode
    {
        $self = new self();
        $self->class = $class;
        $self->namespace = $namespace;

        return $self;
    }

    public function addImportUsage(string $name, ?string $alias): void
    {
        $this->addStmt(ClassNode\ClassStmt::create($name, $alias));
    }

    /**
     * @return ClassNode\ClassStmt[]
     */
    public function getStmts(): array
    {
        return $this->stmts;
    }

    /**
     * @throws \ReflectionException
     */
    public function addParam(\ReflectionParameter $parameter): void
    {
        $this->constructorParams[$parameter->name] = ConstructorParam::createFromReflection($parameter);
    }

    private function addStmt(ClassNode\ClassStmt $stmt): void
    {
        $this->stmts[(string)$stmt] = $stmt;
    }
}
