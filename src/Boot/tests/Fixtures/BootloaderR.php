<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\Fixtures;

use Spiral\Boot\Attribute\BindMethod;
use Spiral\Boot\Bootloader\Bootloader;

class BootloaderR extends Bootloader
{
    #[BindMethod]
    private function bind(): SampleClass|SampleClassInterface
    {
        return new SampleClass();
    }
}
