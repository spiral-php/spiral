<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\Fixtures;

use Spiral\Boot\Attribute\SingletonMethod;
use Spiral\Boot\Bootloader\Bootloader;

class BootloaderP extends Bootloader
{
    #[SingletonMethod]
    private function bind(): int
    {
        return new SampleClass2();
    }
}
