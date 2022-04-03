<?php

/**
 * Spiral Framework. PHP Data Grid
 *
 * @author Valentin Vintsukevich (vvval)
 */

declare(strict_types=1);

namespace Spiral\Tests\DataGrid\Fixture;

use Spiral\DataGrid\Compiler;
use Spiral\DataGrid\SpecificationInterface;
use Spiral\DataGrid\WriterInterface;

class WriterIterateNonIterable implements WriterInterface
{
    /**
     * {@inheritDoc}
     */
    public function write($source, SpecificationInterface $specification, Compiler $compiler): mixed
    {
        if (!\is_iterable($source)) {
            return [$source];
        }

        return $source;
    }
}
