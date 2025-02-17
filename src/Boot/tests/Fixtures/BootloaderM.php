<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\Fixtures;

use Spiral\Boot\Attribute\SingletonMethod;
use Spiral\Boot\Bootloader\Bootloader;

class BootloaderM extends Bootloader
{
    #[SingletonMethod]
    private function bind(): int|object
    {
        return new SampleClass2();
    }
}
