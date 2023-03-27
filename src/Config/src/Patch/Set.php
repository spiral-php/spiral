<?php

declare(strict_types=1);

namespace Spiral\Config\Patch;

use Spiral\Config\Exception\DotNotFoundException;
use Spiral\Config\Exception\PatchException;
use Spiral\Config\Patch\Traits\DotTrait;
use Spiral\Config\PatchInterface;

/**
 * Set the value.
 */
final class Set implements PatchInterface
{
    use DotTrait;

    public function __construct(
        private string $key,
        private mixed $value
    ) {
    }

    public function patch(array $config): array
    {
        try {
            $_ = &$this->dotGet($config, $this->key);
            $_ = $this->value;
        } catch (DotNotFoundException $e) {
            throw new PatchException($e->getMessage(), $e->getCode(), $e);
        }

        return $config;
    }
}
