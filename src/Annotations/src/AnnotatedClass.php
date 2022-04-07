<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Annotations;

use ReflectionClass;

/**
 * @deprecated since v2.12. Will be removed in v3.0
 */
final class AnnotatedClass
{
    private ReflectionClass $class;

    /** @var mixed */
    private $annotation;

    /**
     * @param mixed            $annotation
     */
    public function __construct(ReflectionClass $class, $annotation)
    {
        $this->class = $class;
        $this->annotation = $annotation;
    }

    public function getClass(): ReflectionClass
    {
        return $this->class;
    }

    /**
     * @return mixed
     */
    public function getAnnotation()
    {
        return $this->annotation;
    }
}
