<?php

declare(strict_types=1);

namespace Spiral\DataGrid\Specification\Filter;

use Spiral\DataGrid\Specification\FilterInterface;
use Spiral\DataGrid\SpecificationInterface;

final class All extends Group
{
    public function __construct(FilterInterface ...$filter)
    {
        $this->filters = $filter;
    }

    public function withValue(mixed $value): ?SpecificationInterface
    {
        $all = $this->clone($value);
        foreach ($this->filters as $filter) {
            $applied = $filter->withValue($value);

            if ($applied === null) {
                // all nested filters must be configured
                return null;
            }

            $all->filters[] = $applied;
        }

        return !empty($all->filters) ? $all : null;
    }
}
