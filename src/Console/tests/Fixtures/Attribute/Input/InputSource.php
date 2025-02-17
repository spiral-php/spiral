<?php

namespace Spiral\Tests\Console\Fixtures\Attribute\Input;

use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\Option;

class InputSource
{
    #[Argument]
    private string $arg;

    #[Option]
    private int $opt;
}
