<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\Fixtures;

use Spiral\Boot\Attribute\BindMethod;
use Spiral\Boot\Bootloader\Bootloader;

class BootloaderL extends Bootloader
{
    #[BindMethod]
    private function bind(): int|string|object
    {
        return new SampleClass2();
    }
}
