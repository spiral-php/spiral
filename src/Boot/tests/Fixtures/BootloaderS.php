<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\Fixtures;

use Spiral\Boot\Attribute\BindAlias;
use Spiral\Boot\Attribute\BindMethod;
use Spiral\Boot\Bootloader\Bootloader;

class BootloaderS extends Bootloader
{
    #[BindMethod(
        alias: 'sample1',
    )]
    #[BindAlias('sample2')]
    #[BindAlias('sample3')]
    private function bind(): SampleClass|SampleClassInterface
    {
        return new SampleClass();
    }

    #[BindMethod(
        alias: 'sample4',
        aliasesFromReturnType: true,
    )]
    #[BindAlias('sample5')]
    #[BindAlias('sample6', 'sample7')]
    private function bind2(): SampleClass2
    {
        return new SampleClass2();
    }
}
